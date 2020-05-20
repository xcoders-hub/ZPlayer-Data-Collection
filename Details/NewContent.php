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

    // public $epi_endpoint = 'http://192.168.1.187:1057/api/new/series';
    // public $series_url = "https://www2.f2movies.to/tv-show";
    // public $movies_url = "https://www2.f2movies.to/movie";

    function __construct($config,$logger)
    {
        $this->logger = $logger;
        $this->config = $config;

        $this->data_page = $config->data_page;
        $this->api = $config->api;

        $this->shared_api = new API($this->logger);

    }

    public function series(){
        $series_url = $this->data_page->series;
        $this->find_new($series_url,'series');
    }

    public function movies(){
        $series_url = $this->data_page->movies;
        $this->find_new($series_url,'movies');
    }

    public function find_last_details($type){

        $api_url =  $this->api_page->new->content->$type;

        $response = $this->request($api_url);
        $content = $this->parse_json($response);

        return array('last_page' => $content->last_page,'series_id' => $content->series_id );
    }

    private function find_new($url,$type){
        //Check if show already exists in database.

        $history = new History($this->config, $this->logger);
        
        global $last_series;

        $last_details = $history->fetch($type);
        if($last_details){

            if($last_details->errors){
                $this->shared_api->errors($last_details->errors);
            } else {
                $page = $last_details->last_page ?? 1;
                $last_series = $last_details->content_id ?? '';
                // $page = 1;
                // $last_series = '/tv/90-day-fiance-self-quarantined-61257';

    
                $this->logger->debug('Last Content: '. $last_series);
                $this->logger->debug('Last Page: '. $page);
            }

        } else {
            $page = 1;
        }
        
        while(true){

            $response = $this->request("$url?page=$page");
            $content = $this->parse_html($response);
    
            $this->logger->debug("Page $page: $url?page=$page");

            $content->filter('h3.film-name')->each(function(Crawler $node, $i) use($history,$type,$page){
                
                global $last_series;

                $item_url = $node->filter('a')->eq(0)->attr('href');
                
                preg_match('/\w\/(.+?)\-\d+/',$item_url,$matches);

                $name = str_replace('-',' ',$matches[1]);

                if($last_series && $last_series != $item_url){
                    $this->logger->debug("Skipping This. Already Proccessed: $item_url");
                    return;
                } else {
                    $last_series = false;
                }

                $history->update($type,['last_page' => $page,'content_id'=> $item_url]);

                $page_url = $this->data_page->domain . $item_url;

                $this->logger->debug("--------------- New Series: $name - $page_url ---------------");
                
                try {
                    $response = $this->request($page_url);
                } catch(Exception $e){
                    $this->logger->error('Content Page Not Exists: '.$page_url);
                    return;
                }
                
                $content = $this->parse_html($response);
                
                $released_year = $content->filter('.elements .row-line')->eq(0)->text();
                
                preg_match('/:\s*(\d{4})/',$released_year,$matches);
                if($matches){
                    $year = $matches[1];
                } else {
                    $year = null;
                }
    
                $details = $this->overview($name,$type,$year);
                if(!$details){
                    return;
                }

                $details->url = $this->data_page->domain . $item_url;
                $details->page_number = $page;

                $response = $this->send_details($details,$type);

                if($response->error){
                    throw new Exception('Error Sending Details: '.$response->error);
                }

                $this->logger->debug("--------------- Series Complete: $name - $page_url ---------------");

            });
            
            $page++;
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



}

?>