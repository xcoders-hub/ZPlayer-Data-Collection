<?php

namespace Sources\WatchSeries;

use Data\Data;
use Shared\API;
use Sources\WatchSeries\Search;

class Shared extends Search {

    public $config,$logger;

    function __construct($config,$logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        $this->api = new API($logger);
    }

    public function details($path){
        $url = $this->config->api->url . $path;

        $response = $this->request($url);
        $content = $this->parse_json($response);

        return $content;
    }

    public function send_sources($content,$delete_old_sources=false){
        
        $url = $this->config->api->url;

        if($delete_old_sources){
            $url .= $this->config->api->sources->delete;
        } else {
            $url .= $this->config->api->sources->insert;
        }

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $content]);
        $content = $this->parse_json($response);

        if( property_exists($content,'errors') ){
            $this->api->errors($content->errors);
        }

    }

    public function parse_results($results,$content_type){

        if($results){
            $correct_results = $results[$content_type];
            
            $item = $correct_results[0];

            if($item && key_exists('url',$item) && $item['url']){

                if($content_type == 'series'){
                    $sources = $this->fetch_series($item['url']);
                } else {
                    $sources = $this->fetch_movie($item['url']);
                }

            } else {
                return false;
            }

           return $sources;

        } else {
            return false;
        }

    }

    public function process_series($details,$content_type){

        foreach($details->data as $data){
            $name = html_entity_decode($data->name,ENT_QUOTES);
            $id = $data->id;
            $imdb_url = $data->imdb_url;

            $this->logger->debug("------------------------- Series $name($id) Start -------------------------");
            $this->logger->debug('IMDB URL: '.$imdb_url);

            foreach($data->seasons as $season){

                $season_number = $season->season_number;

                $search_string =  $name . ' Season ' .$season_number;

                $this->logger->debug("----------- $search_string Start -----------");

                $this->logger->debug("Finding $search_string");
                $results = $this->search_results($search_string);

                $video_episodes = $this->parse_results($results,$content_type);

                $encoded_data = json_encode($video_episodes);

                $this->logger->debug("Saving List");

                file_put_contents(__DIR__.'/../../Downloads/Sources/Episode-List.json',$encoded_data);

                if(!$video_episodes){
                    $this->logger->debug("No Sources Found. New Show Possibly.");
                    break;
                }

                $this->logger->debug("Sending Sources To Endpoint");

                $this->upload_season_sources($season,$video_episodes);

                $this->logger->debug("----------------- $search_string Complete ---------------------");
               
            }

            $this->logger->debug("------------------------- Series $name($id) Complete -------------------------");

            $this->update_source_status($data->id);
        }

    }

    public function process_movies($details){

        foreach($details->data as $data){

            $name = $this->clean_content_name($data->name);

            $this->logger->debug("-------------------------  $name Start ------------------------- ");

            $this->logger->debug("Finding $name");
            $results = $this->search_results($name);
            
            if(key_exists('movies',$results)){
                
                $movie = $this->parse_results($results,'movies');

                $encoded_data = json_encode($movie);

                $this->logger->debug("Saving List");
                
                file_put_contents(__DIR__.'/../../Downloads/Sources/Movie-List.json',$encoded_data);

                if(!$movie){
                    $this->logger->debug("No Sources Found. New Movie Possibly.");

                }else {

                    $this->logger->debug("Sending Sources To Endpoint");

                    $new_source = new Data();
        
                    $new_source->content_id = $data->id;
                    $new_source->content_type = 'movies';
        
                    $this->logger->debug("Uploading Source For $name");
                    
                    if($movie->content_url){
                        $new_source->content_url = $movie->content_url;

                        $new_source->sources = $movie->sources;
                        
                        $this->send_sources($new_source);
                    }

                }

            } else {
                $this->logger->debug("No Movies Watching Description Found");
            }

            $this->logger->debug("-------------------------  $name Complete ------------------------- ");

            $this->update_source_status($data->id);
        }
    }

    public function new_episode_found($content){
        $url = $this->config->api->url . $this->config->api->new->episode;

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $content]);
        $content = $this->parse_json($response);

        if(property_exists($content,'errors')){
            $this->api->errors($content->errors);
        } else {
            return $content->data->content_id;
        }
    }

    public function update_source_status($content_id){
        
        $url = $this->config->api->url . $this->config->api->sources->status;

        $this->logger->debug('Updating Content Souce Status');

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => [ 'content_id' => $content_id ] ]);
        $content = $this->parse_json($response);

        if(property_exists($content,'errors')){
            $this->api->errors($content->errors);
        }

    }

    public function clean_content_name($name){
        $name = html_entity_decode($name,ENT_QUOTES); 
        $name = preg_replace('/\s*\(\d+\)\s*/','',$name);
        return $name;
    }


    public function upload_season_sources($season,$video_episodes){

        $season_number = $season->season_number;

        foreach($video_episodes as $video_episode){

            $episode_number = (int)$video_episode->episode_number;
            $source_list = $video_episode->sources;
            $content_url = $video_episode->content_url;

            $this->logger->debug("Uploading Source For Season $season_number, Episode $episode_number");

            if($source_list && count($source_list) == 0){
                $this->logger->debug('No Sources Found');
                continue;
            }
            
            if(property_exists($season->episodes,$episode_number)){
                $content_id = $season->episodes->{$episode_number}->id;
            }


            $new_source = new Data();

            $new_source->content_url = $content_url;
            $new_source->content_type = 'series';

            if(!isset($content_id)){
                $new_source->name = 'Episode '.$episode_number;
                $new_source->episode_number = (int)$episode_number;
                $new_source->season_id = $season->id;

                $this->logger->notice("New Episode Found: Season $season_number, Episode $episode_number");
                $content_id = $this->new_episode_found($new_source);

                $new_source->content_id = $new_source->id = $content_id;

                $season->episodes->{ $new_source->episode_number } = $new_source;
            } else {
                $new_source->content_id = $content_id;
            }   

            $new_source->sources = $source_list;
        
            if($new_source->sources){
                $this->send_sources($new_source);
            }
           
        }

    }

    public function new_series_sources($series_id){

        $path = $this->config->api->details->series . "/$series_id";

        $details = $this->details($path);

        $this->process_series($details,'series');
    }

}

?>