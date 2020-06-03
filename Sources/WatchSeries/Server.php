<?php

namespace Sources\WatchSeries;

use Data\Data;
use Exception;
use Shared\API;
use Sources\WatchSeries\Search;
use Sources\WatchSeries\Shared;
use Symfony\Component\DomCrawler\Crawler;

class Server extends Shared {
    
    public function sources($request_json){

        $this->logger->debug('Waiting For Data ....');

        $default_wait = $this->config->retry->request->wait;
        $default_times = $this->config->retry->request->times;
        
        $response = (object)json_decode($request_json);

        $this->logger->debug('------- Incoming Request --------');
        $data = $response->data;

        $this->logger->debug('Response: '.$request_json);

        $this->config->retry->request->wait = 5;
        $this->config->retry->request->times = 3;

        global $sources;
                
        $sources = [];
        
        $content_data = $data->content_data;

        $name = $this->clean_content_name($content_data->name);
        $name = preg_replace('/ and | & |:.+?\-|\?|\!/',' ',$name);

        if( $data->content_type == 'series'){

            $season_number = $content_data->season_number;
            $episode_number = $content_data->episode_number;
            $series_id = $content_data->series_id;

            $search_string =  $name . ' Season ' .$season_number;

            $this->logger->debug("----------- $search_string Episode $episode_number Search -----------");

            $results = $this->search_results($search_string);
            
            $sources = [];

            if($results){

                $season_url = $results['series'][0]['url'] . '/season';
                $response = $this->request($season_url);
                $content = $this->parse_html($response);
        
                $this->logger->debug('Fetching Episode Link');
                $this->logger->debug("Searching For Episode $episode_number");

                try {

                    $content->filter('.vid_info a')->each(function(Crawler $node, $i) use($episode_number){
                        global $sources;

                        $name = $node->text();

                        $this->logger->debug("Episode $episode_number: =~ $name");

                        preg_match("/Episode $episode_number:/i",$name,$matches);
                        
                        if($matches){
                            $this->logger->debug("Found $name");
                            
                            try {
                                $episode = $this->fetch_episode($node);
                                $sources = $episode->sources;

                            } catch(Exception $e) {
                                $this->logger->error('No Episode Found');
                            }

                            throw new Exception('Found Episode. Returning Sources');
                            
                        }

                    });
                    
                } catch(Exception $e){
                    $this->logger->debug('Complete');
                }

            } else {
                $this->logger->error('No Results Found');
            }

        } else {
            $this->logger->debug("----------- $name Search Start -----------");

            $results = $this->search_results($name);

            if(key_exists('movies',$results)){
                
                $movie = $this->parse_results($results,'movies');

                if($movie->content_url){
                    $sources = $movie->sources;
                }

            }

            $this->logger->debug("----------- $name Search Complete -----------");
        }

        $response = new Data();
        $response->sources = $sources;

        $response_json = json_encode( ['data' => $response ]);

        $this->logger->debug('Response Sent');

        return $response_json;

        $this->logger->debug('Updating Content Source List');

        $data->sources = $sources;

        // if(isset($series_id)){
        //     $this->new_series_sources($series_id);
        // } else {
        //     $this->send_sources($data);
        // }
        
    }

}

?>