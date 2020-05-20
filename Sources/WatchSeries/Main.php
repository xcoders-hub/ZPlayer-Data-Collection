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

        $details = $this->details($type);

        if($details->data){

            if($type == 'series'){
                $this->process_series($details,$type);
            } else {
                // $results = $this->search_results($name);
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

            // foreach($correct_results as $item){
            $item = $correct_results[0];

            if($type == 'movies'){
                // $sources[$item['name']] = $this->fetch_movie($item['url']);
            } else {
                // $sources[$item['name']] = $this->fetch_series($item['url']);
                $sources = $this->fetch_series($item['url']);
            }

            // }

           return $sources;

        } else {
            return false;
        }

    }

    public function process_series($details,$type){

        foreach($details->data as $data){
            $name = $data->name;
            

            foreach($data->seasons as $season){
                // $season = $data->seasons[0];

                $episodes = $season->episodes;

                $search_string =  $name . ' Season ' .$season->season_number;
                $this->logger->debug("Finding $search_string");
                $results = $this->search_results($name);

                $video_episodes = $this->parse_results($results,$type);

                $encoded_data = json_encode($video_episodes);

                file_put_contents(__DIR__.'/../../Downloads/Sources/List.json',$encoded_data);

                print_r($video_episodes);

                foreach($video_episodes as $video_episode){

                    $episode_number = $video_episode->episode_number;
                    $source_list = $video_episode->sources;

                    $new_episode = new Data();
                    $new_episode->content_id = $episodes[$episode_number - 1]->id;
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

               
            }

        }

    }

}

?>