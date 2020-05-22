<?php

namespace Details;

use Data\Data;
use Details\IMDB;
use Exception;
use Details\History;
use Shared\API;
use Symfony\Component\DomCrawler\Crawler;

class NewContent extends IMDB {

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

    public function find_last_details($type){

        $api_url =  $this->api_page->new->content->$type;

        $response = $this->request($api_url);
        $content = $this->parse_json($response);

        return array('last_page' => $content->last_page,'series_id' => $content->series_id );
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

            $content->filter('li.video-block')->each(function(Crawler $node, $i) use($history,$page){
                global $last_url;

                $season_title = $node->filter('.home_video_title')->eq(0)->text();
                $season_url =  $this->data_page->url . $node->filter('a.view_more')->eq(0)->attr('href');
                $url = preg_replace('/-season-\d+-episode-\d+/','',$season_url);


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
                
                if(!$details){
                    return;
                } else {

                    $details->url = $url;

                    $response = $this->send_details($details,'series');
    
                    if($response->error){
                        throw new Exception('Error Sending Details: '.$response->error);
                    }

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

            if($last_details->errors){
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
    
                        $response = $this->send_details($details,'movies');
        
                        if($response->error){
                            throw new Exception('Error Sending Details: '.$response->error);
                        }
    
                    }
    
                    $this->logger->debug("--------------- Movie Complete: $title - $url ---------------");

                    
                });


                $page++;
            }

            
            $index++;
        }


    }

    private function send_details($details,$type){

        file_put_contents(__DIR__.'/../Downloads/Details/Details_Request.json',json_encode($details));

        $url = $this->api->url . $this->api->new->$type;
        $api_response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $details]);
        $response = $this->parse_json($api_response);

        file_put_contents(__DIR__.'/../Downloads/Details/Details_Response.json',json_encode($api_response));

        if($response->errors){
            $this->shared_api->errors($response->errors);
        }

    }

    //If this content is new. Send request to api
    public function new_content($site_url){
        $url = $this->config->api->url . $this->config->api->unique;

        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => [ 'url' => $site_url ]]);
        $content = $this->parse_json($response);

        return $content->data->new;
    }

    private function get_released_year($page){

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

}

?>