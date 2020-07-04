<?php

namespace Details;

use Data\Data;
use Details\IMDB;
use Exception;
use Details\History;
use Shared\API;
use Symfony\Component\DomCrawler\Crawler;

class Information extends IMDB {

    public $logger,$config,$api,$data_page;

    function __construct($config,$logger)
    {
        $this->logger = $logger;
        $this->config = $config;

        $this->data_page = $config->data_page;
        $this->api = $config->api;

        $this->shared_api = new API($this->logger);

    }

    public function series(){
        $series_url =  $this->data_page->url . $this->data_page->series;
        $this->new_series($series_url,'series');
    }

    public function movies(){
        $series_url = $this->data_page->url . $this->data_page->movies;
        $this->new_movies($series_url,'movies');
    }

    public function find_last_details($content_type){

        $api_url =  $this->api_page->new->content->$content_type;

        $response = $this->request($api_url);
        $content = $this->parse_json($response);

        return array('last_page' => $content->last_page,'series_id' => $content->series_id );
    }

    public function similar_list(){
        $api_url =   $this->config->api->url . $this->config->api->similar;

        $response = $this->request($api_url);
        $content = $this->parse_json($response);

        return $content->data;
    }

    private function new_series($url){
        //Check if show already exists in database.

        $history = new History($this->config, $this->logger);
        
        global $last_url;

        $last_details = $history->fetch('series');

        if($last_details){

            if($last_details->errors){
                $this->shared_api->errors($last_details->errors);
            } else {
                $page = $last_details->last_page ?? 1;
                $last_url = $last_details->last_url ?? '';
    
                $this->logger->debug('Last Content: '. $last_url);
                $this->logger->debug('Last Page: '. $page);
            }

        } else {
            $page = 1;
            $last_url = null;
        }
        
        while(true){

            // $response = $this->request("$url?page=$page");
            $response = $this->request("$url?page=$page");
            $content = $this->parse_html($response);
    
            $this->logger->debug("Page $page: $url?page=$page");

            if( $content->filter('li.video-block')->count() == 0){
                $page = 0;
                break;  
            }

            $content->filter('li.video-block')->each(function(Crawler $node, $i) use($history,$page){
                global $last_url;

                $season_title = $node->filter('.home_video_title')->eq(0)->text();
                $season_url =  $this->data_page->url . $node->filter('a.view_more')->eq(0)->attr('href');
                $url = preg_replace('/-*[^seasons]*-*[^seasons]*-episode-\d+/','',$season_url);

                $history->update('series',['last_page' => $page,'last_url'=> $url]);

                if($last_url && $last_url != $url){
                    $this->logger->debug("Skipping This. Already Proccessed: $url");
                    return;
                } else {
                    $last_url = false;
                }

                preg_match('/(.+) (:?-|–) Season/i',$season_title,$matches);

                if($matches){
                    $title = $matches[1];
                } else {
                    $this->logger->error('Failed To Find Name: |'.$season_title.'|');
                    return;
                }

                if(!$this->new_content($url)){
                    $this->logger->debug("Skipping Old: $title - $url");
                    return;
                }

                $this->logger->debug("--------------- Series Start: $title - $url ---------------");


                $details = $this->overview($title,'series');
                
                if(!$details || !property_exists($details,'name')){
                    return;
                } else {
                    $details->url = $url;
                    $this->send_details($details,'series');
                }   

                $this->logger->debug("--------------- Series Complete: $title - $url ---------------");

            });

            $page++;
        }

    }

    private function new_movies($url){

        //Check if show already exists in database.

        $history = new History($this->config, $this->logger);
        
        global $page_url,$current_character;

        $last_details = $history->fetch('movies');

        $alphas = range('A', 'Z');
        $numbers = range(0, 9);

        $character_list = array_merge($numbers,$alphas);

        $index = 0;
        $page = 1;

        if($last_details){

            if( property_exists($last_details,'errors') ){
                $this->shared_api->errors($last_details->errors);
            } else {
                $page = $last_details->last_page ?? 1;
                $index = $last_details->last_character ?? 0;

                $this->logger->debug("History: Index - $index, Page - $page " );
            }

        }

        while( $index < count($character_list) ){
         
            $current_character = $character_list[$index];

            $page_url = "$url/$current_character?page=$page";

            $history->update('movies',['last_page' => $page,'last_url'=> $page_url,'last_character'=> $index ]);

            while(true){

                $page_url = "$url/$current_character?page=$page";

                $history->update('movies',['last_page' => $page,'last_url'=> $page_url]);

                $response = $this->request($page_url);
                $content = $this->parse_html($response);
        
                $this->logger->debug("Page $page: $page_url");
        
                if($content->filter('tr a')->count() == 0){
                    $page = 0;
                    break;  
                }

                $content->filter('tr a')->each(function(Crawler $node, $i) use($history,$page,$content){
                    global $page_url,$current_character;
    
                    $title = $node->text();
                    $url = $this->data_page->url . $node->attr('href');

                    if(!$this->new_content($url)){
                        $this->logger->debug("Skipping Old: $title - $url");
                        return;
                    }   
        
                    $year = $this->get_released_year($url);
                    
                    if(!$year){
                        $this->logger->error('Page Not Found');
                    }

                    $this->logger->debug("--------------- Movie Start: $title - $url ---------------");

                    $details = $this->overview($title,'movies',$year);
                    
                    if(!$details){
                        return;
                    } else {
    
                        $details->url = $url;
    
                        $this->send_details($details,'movies');
    
                    }
    
                    $this->logger->debug("--------------- Movie Complete: $title - $url ---------------");

                    
                });


                $page++;
            }

            if($index == ( sizeof($character_list) - 1 )){
                $index = 0;
            } else {
                $index++;
            }
            
            
        }


    }

    public function send_details($details,$content_type){

        // file_put_contents(__DIR__.'/../Downloads/Details/Details_Request.json',json_encode($details));

        $url = $this->api->url . $this->api->new->$content_type;
        $api_response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $details]);
        $response = (object)$this->parse_json($api_response);

        // file_put_contents(__DIR__.'/../Downloads/Details/Details_Response.json',json_encode($api_response));

        if(property_exists($response,'errors')){
            return $this->shared_api->errors($response->errors);
        }

    }

    private function similar_complete($content_id,$related){
        $url = $this->config->api->url . $this->config->api->similar;

        if(!$content_id){
            throw new Exception('No ID Found: '.$content_id);
        }

        $api_response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => ['content_id' => $content_id,'related' => $related] ]);
        $response = $this->parse_json($api_response);

        if(property_exists($response,'errors')){
            return $this->shared_api->errors($response->errors);
        }

    }

    public function update_details($details,$content_type){

        $url = $this->api->url . $this->api->update->$content_type;
        $api_response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $details]);
        $response = $this->parse_json($api_response);

        if(property_exists($response,'errors')){
            return $this->shared_api->errors($response->errors);
        }

    }

    //If this content is new. Send request to api
    public function new_content($site_url){
        $url = $this->config->api->url . $this->config->api->unique;

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => [ 'url' => $site_url ]]);
        $content = $this->parse_json($response);

        return $content->data->new;
    }

    public function get_released_year($page){

        $response = $this->request($page);
        $content = $this->parse_html($response);

        try {
            $released_text = trim($content->filter('p.icon-calendar')->eq(0)->text());

            preg_match('/(\d+)/',$released_text,$matches);
    
            if(!$matches){
                throw new Exception('No Release Year Found: '.$released_text);
            } else {
                $year = $matches[1];
                return $year;
            }
        } catch(Exception $e){
            return false;
        }

    }

    public function update(){

        //Increase Waiting And Retry Times, Not Important Here
        $this->config->retry->request->wait = 180;
        $this->config->retry->request->times = 20;

        //Reach to endpoint and get list of all contents older than 1 week
        $history = new History($this->config, $this->logger);

        while(true){

            if(!$old_contents){
                $this->logger->debug("No Content To Update");
                break;
            }

            foreach($old_contents as $content){
    
                $show_url = $content->imdb_url;
                $name = $content->name;
    
                $this->logger->debug("--------------- Updating $name Start ---------------");
                
                $series_details = new Data();
                $series_details->url = $content->url;
                $series_details->imdb_url = $show_url;
                $series_details->old_name = $name;
                $series_details->content_type = $content->content_type;
                $series_details->show_id = $this->url_id($show_url);
        
                $this->process_page($series_details);
                    
                if($series_details){
                    $this->update_details($series_details,'series');
                }
    
                $this->logger->debug("--------------- Updating $name Complete ---------------");
                
            }

        }



    }
    
    public function similar(){

        $this->config->retry->request->times = 1;

        while(true){
            $content_list = $this->similar_list();

            if(count($content_list) == 0){
                $this->logger->debug('Similar Complete');
                break;
            }

            foreach($content_list as $details){
                
                try {

                    $this->logger->debug('Finding Similar For '.$details->name);

                    $success_status = $this->process_page($details);

                    if($success_status){

                        $content_id = $details->id;
                        $related_contents = $details->related;

                        $new_ids = $this->new_imdb_content($related_contents);
                        
                        if($new_ids){

                            $this->logger->debug("New Similar Contents Found.");

                            foreach($details->related as $related_content){
                                $name = $related_content->name;
                                $id = $related_content->id;
                                $content_type = $related_content->content_type;
                                $released = $related_content->released;
    
                                if(!property_exists($new_ids,$id)){
                                    $this->logger->notice("Existing Similar Content: $name({$content_type})");
                                } else {
                                    $this->logger->notice("New Similar Content: $name({$content_type})");

                                    $this->logger->notice("--------------- Similar Start: $name({$content_type}) - $released ---------------");
                                
                                        $similar_details = $this->overview($name,$content_type,false,$id);
                                    
                                        if(!$similar_details || !property_exists($similar_details,'name')){
                                            continue;
                                        } else {
                                            $this->send_details($similar_details,$content_type);
                                        }
            
                                    $this->logger->notice("--------------- Similar Complete: $name({$content_type}) - $released ---------------");

                                }
                                
                            }

                        } else {
                            $this->logger->debug("No New Similar Contents Found. Updating ...");
                        }

                        $this->logger->notice('Similar Search Complete: '.$content_id);
        
                        $this->similar_complete($content_id,$related_contents);

                    }

                } catch(Exception $e){
                    $this->logger->error("Similar Error: ".$e->getMessage());
                }

            }

        }

    }

    public function content_details($content_id){
        $url = $this->config->api->url . $this->config->details . "/$content_id";

        $response = $this->request($url);
        $content = $this->parse_json($response);

        return $content;
    }

    public function new_imdb_content($contents){
        $search = [];

        foreach($contents as $content){
            $search[] = $content->id;
        }

        $search_string = join(',',$search);
        $url = $this->config->api->url . $this->config->api->imdb_new . "/$search_string";

        $response = $this->request($url);
        $content = $this->parse_json($response);

        return $content->data->new;
    }

}

?>