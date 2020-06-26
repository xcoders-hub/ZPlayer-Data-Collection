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

        $this->config->retry->request->wait = 1;
        $this->config->retry->request->times = 2;

        global $sources;
                
        $sources = [];
        
        $content_data = $data->content_data;
        
        $name = $this->clean_content_name($content_data->name);
        $name = preg_replace('/ and | & |:.+?\-|\?|\!/',' ',$name);

        $this->logger->debug("----------- $name Search Start -----------");

        if(property_exists($content_data,'url') && !is_null($content_data->url)){
            $this->logger->notice('Quick Route Taken. Vidcloud URL Found');
            $sources = $this->fetch_sources($content_data->url,true);
        } else {
            $this->logger->notice('Long Route Taken. No Vidcloud URL Found');
            $search = new Search($this->config,$this->logger);
    
            if( property_exists($data,'content_type') && $data->content_type == 'series'){
    
                $season_number = $content_data->season_number;
                $episode_number = $content_data->episode_number;
    
                $search_string =  $name . ' Season ' .$season_number;
    
                $this->logger->debug("Episode Search: $search_string Episode $episode_number Search");
                
                if(!$episode_number){
                    // throw new Exception('No Episode Number Provided');
                    $this->logger->error('No Episode Number Provided');
                }

                if(!$season_number){
                    $this->logger->error('No Season Number Provided');
                }
                
                $results = $search->search_results($search_string);
    
                $sources = [];
    
                if($results && key_exists('series',$results)){
                    
                    $season_url = null;
                    
                    if(count( $results['series']) > 0){
                        $season_url = $results['series'][0]['url'] . '/season';
                    } else {
                        //Some shows are not marked with season on page. Just a desperate attempt
                        if($season_number == 1){
                            $results = $search->search_results($name);
                            if(count($results['movies']) > 0){
                                $site_url =  $results['movies'][0]['url'];
                                preg_match('/series/i',$site_url,$matches);

                                if($matches){
                                    $season_url = $site_url . '/season';
                                } else {
                                    $season_url = preg_replace('/-info/i','',$site_url) . '/all';
                                }
                                
                            }
                            
                        }
    
                    }
                    
                    if($season_url){
    
                        $response = $this->request($season_url);
                        $content = $this->parse_html($response);
                        
                        $this->logger->debug('Fetching Episode Link');
                        $this->logger->debug("Searching For Episode $episode_number");
        
                        try {
        
                            $content->filter('.vid_info a')->each(function(Crawler $node, $i) use($episode_number){
                                global $sources;
        
                                $name = $node->text();
        
                                $this->logger->debug("Episode $episode_number =~ $name");
        
                                preg_match("/Episode $episode_number(?!\d)/i",$name,$matches);
                                
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
    
                    }
    
    
                } else {
                    $this->logger->error('No Results Found');
                }
    
            } else {
                // $this->logger->debug("----------- $name Search Start -----------");
    
                $results = $search->search_results($name);
    
                if($results && key_exists('movies',$results) && count($results['movies']) > 0){
                    
                    $movie = $this->parse_results($results,'movies',$content_data->released ?? false);
    
                    if($movie && property_exists($movie,'sources')){
                        $sources = $movie->sources;
                    }
    
                } else {
                    //If not found on child page, check straight in parent page
                    $search = new Search($this->config,$this->logger);
                    $sources = $search->parent_page_search($name);
                }
    
                // $this->logger->debug("----------- $name Search Complete -----------");
            }


        }

        $this->logger->debug("----------- $name Search Complete -----------");

        $response = new Data();
        $response->sources = $sources;

        $response_json = json_encode( ['data' => $response ]);

        $this->logger->debug('Response Sent');

        return $response_json;

        $this->logger->debug('Updating Content Source List');

        $data->sources = $sources;
        
    }

}

?>