<?php

namespace Sources\WatchSeries;

use Data\Data;
use Exception;
use Sources\WatchSeries\Search;
use Sources\WatchSeries\Shared;
use Symfony\Component\DomCrawler\Crawler;

class Server extends Shared {
    
    public function sources($request){

        $data = $request->data;

        $this->config->retry->request->wait = 1;
        $this->config->retry->request->times = 2;

        global $sources;
                
        $sources = [];
        
        $content_data = $data->content_data;
        
        $content_type = $data->content_type;

        $name = $this->clean_content_name($content_data->name);
        $name = preg_replace('/:.+?\-|\?|\!/',' ',$name);
        $name = str_replace("'",'',$name);

        $this->logger->debug("----------- $name Search Start -----------");

        $this->logger->debug("----------- Content Type: $content_type -----------");
        
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
                
                $results = $search->vidcloud_search($search_string);
    
                $sources = [];
    
                if($results && key_exists('series',$results)){
                    
                    if($results && key_exists('series',$results) && count($results['series']) > 0){
                        $sources = $this->vidcloud_episode($results['series'][0]['url'], $episode_number);    
                    }
                    // $season_url = null;
                    
                    // if(count( $results['series']) > 0){
                    //     $season_url = $results['series'][0]['url'] . '/season';
                    // } else {
                    //     //Some shows are not marked with season on page. Just a desperate attempt
                    //     if($season_number == 1){
                    //         $results = $search->search_results($name);
                    //         if(count($results['movies']) > 0){
                    //             $site_url =  $results['movies'][0]['url'];
                    //             preg_match('/series/i',$site_url,$matches);

                    //             if($matches){
                    //                 $season_url = $site_url . '/season';
                    //             } else {
                    //                 $season_url = preg_replace('/-info/i','',$site_url) . '/all';
                    //             }
                                
                    //         }
                            
                    //     }
    
                    // }
    
                } else {
                    $this->logger->error('No Results Found');
                }
    
            } else {
                // $this->logger->debug("----------- $name Search Start -----------");

                $results = $search->vidcloud_search($name);

                if($results && key_exists('movies',$results) && count($results['movies']) > 0){
                    $sources = $this->vidcloud_movie($results['movies'][0]['url']);    
                }
    
                // $this->logger->debug("----------- $name Search Complete -----------");
            }


        }

        $this->logger->debug("----------- $name Search Complete -----------");

        $response = new Data();
        $response->sources = $sources ?? [];

        $response_json = json_encode( ['data' => $response ]);

        $this->logger->debug('Response Sent');

        return $response_json;

        $this->logger->debug('Updating Content Source List');

        $data->sources = $sources;
        
    }

}

?>