<?php

namespace sources\WatchSeries;

use Data\Data;
use Shared\API;
use Sources\WatchSeries\Search;

class Main extends Search {

    public $config,$logger;

    function __construct($config,$logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        $this->api = new API($logger);
    }

    public function details($type){
        $url = $this->config->api->url . $this->config->api->source->list->$type;

        $response = $this->request($url);
        $content = $this->parse_json($response);

        return $content;
    }

    public function search($type){

        while(true){
            
            $details = $this->details($type);

            if($details->data){
                
                $this->logger->notice("$type Found Without Sources");

                if($type == 'series'){
                    $this->process_series($details,$type);
                } else {
                    $this->process_movies($details);
                }
                
            } else {
                $this->logger->notice("No More $type Found Without Sources");
                break;
            }

        }

    }

    public function send_details($content){
        $url = $this->config->api->url . $this->config->api->source->insert;
        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $content]);
        $content = $this->parse_json($response);

        if($content->errors){
            $this->api->errors($content->errors);
        }

    }

    public function parse_results($results,$type){

        if($results){
            $correct_results = $results[$type];

            $item = $correct_results[0];

            if($type == 'series'){
                $sources = $this->fetch_series($item['url']);
            } else {
                $sources = $this->fetch_movie($item['url']);
            }

           return $sources;

        } else {
            return false;
        }

    }

    public function process_series($details,$type){

        foreach($details->data as $data){
            $name = $data->name;
            $id = $data->id;
            $imdb_url = $data->imdb_url;

            $this->logger->debug("------------------------- Series $name($id) Start -------------------------");
            $this->logger->debug('IMDB URL: '.$imdb_url);

            foreach($data->seasons as $season){
                // $season = $data->seasons[0];

                // $episodes = get_object_vars($season->episodes);

                $season_number = $season->season_number;

                $search_string =  $name . ' Season ' .$season_number;

                $this->logger->debug("----------- $search_string Start -----------");

                $this->logger->debug("Finding $search_string");
                $results = $this->search_results($search_string);

                $video_episodes = $this->parse_results($results,$type);

                $encoded_data = json_encode($video_episodes);

                $this->logger->debug("Saving List");

                file_put_contents(__DIR__.'/../../Downloads/Sources/Episode-List.json',$encoded_data);

                if(!$video_episodes){
                    $this->logger->debug("No Sources Found. New Show Possibly.");
                    break;
                }

                $this->logger->debug("Sending Sources To Endpoint");

                foreach($video_episodes as $video_episode){

                    $episode_number = (int)$video_episode->episode_number;
                    $source_list = $video_episode->sources;
                    
                    $content_id = $season->episodes->{$episode_number}->id;

                    $new_episode = new Data();

                    $this->logger->debug("Uploading Source For Season $season_number, Episode $episode_number ($content_id)");

    
                    if(!isset($content_id)){
                        $new_episode->name = 'Episode '.$episode_number;
                        $new_episode->episode_number = (int)$episode_number;
                        $new_episode->season_id = $season->id;

                        $this->logger->notice("New Episode Found: Season $season_number, Episode $episode_number");
                        $content_id = $this->new_episode($new_episode);

                        $new_episode->content_id = $content_id;

                        $season->episodes->{ $new_episode->episode_number } = $new_episode;
                    } else {
                        $new_episode->content_id = $content_id;
                    }   

                    $new_episode->type = 'series';

                    foreach($source_list as $links){

                        foreach($links as $source){
                            $new_episode->url = $source->url;
                            $new_episode->quality = $source->quality;
                            $new_episode->server_name = $source->server_name;
                            $this->send_details($new_episode);
                        }

                    }
                   
                   
                }

                $this->logger->debug("----------------- $search_string Complete ---------------------");
               
            }

            $this->logger->debug("------------------------- Series $name($id) Complete -------------------------");

            $this->update_source_status($data->id);
        }

    }

    public function process_movies($details){

        foreach($details->data as $data){
            $old_name = $data->name;    

            $name = preg_replace('/\s*\(\d+\)\s*/','',$old_name);

            $this->logger->debug("-------------------------  $old_name Start ------------------------- ");

            $this->logger->debug("Finding $name");
            $results = $this->search_results($name);

            if($results['movies']){
                
                $source_list = $this->parse_results($results,'movies');

                $encoded_data = json_encode($source_list);

                $this->logger->debug("Saving List");
                
                file_put_contents(__DIR__.'/../../Downloads/Sources/Movie-List.json',$encoded_data);

                if(!$source_list){
                    $this->logger->debug("No Sources Found. New Movie Possibly.");

                }else {

                    $this->logger->debug("Sending Sources To Endpoint");

                    $new_source = new Data();
        
                    $new_source->content_id = $data->id;
                    $new_source->type = 'movies';
        
                    $this->logger->debug("Uploading Source For $name");
        
                    foreach($source_list as $links){
        
                        foreach($links as $source){
                            $new_source->url = $source->url;
                            $new_source->quality = $source->quality;
                            $new_source->server_name = $source->server_name;
                            $this->send_details($new_source);
                        }
        
                    }
                    
                }

            } else {
                $this->logger->debug("No Movies Watching Description Found");
            }

            $this->logger->debug("-------------------------  $old_name Complete ------------------------- ");

            $this->update_source_status($data->id);
        }
    }

    public function new_episode($content){
        $url = $this->config->api->url . $this->config->api->new->episode;

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $content]);
        $content = $this->parse_json($response);

        if($content->errors){
            $this->api->errors($content->errors);
        } else {
            return $content->data->content_id;
        }
    }

    public function update_source_status($content_id){
        
        $url = $this->config->api->url . $this->config->api->source->status;

        $this->logger->debug('Updating Content Souce Status');

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => [ 'content_id' => $content_id ] ]);
        $content = $this->parse_json($response);

        if($content->errors){
            $this->api->errors($content->errors);
        } else {
            return $content->data->content_id;
        } 
    }
}

?>