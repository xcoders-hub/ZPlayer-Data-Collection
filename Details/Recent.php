<?php
namespace Details;

use Data\Data;
use DateTime;
use Exception;
use Symfony\Component\DomCrawler\Crawler;

class Recent extends Information {

    public $logger,$config,$api;

    function __construct($config,$logger)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->api = $config->api;

    }

    public function recent_content($content_type){
        $url = $this->config->released_page->url . $this->config->released_page->$content_type;

        for($page_number = 1; $page_number < 4;$page_number++){
            $page_url = "$url?page=$page_number";
            $response = $this->request($page_url);
            $content = $this->parse_html($response);

            $content->filter('ul li.video-block')->each(function(Crawler $node, $i) use($content_type){
                $details = $this->find_details($node,$content_type);
                // print_r($details);
                $response = $this->send_recent($details);
                $response_data = $response->data;

                $upper_content_type = ucwords($content_type);

                $title = $details->name;

                $added_date = $details->date;

                preg_match('/^(\d+)/',$added_date,$matches);

                $released_year = $matches[1];

                if(property_exists($response->data,'not_found')){

                    $this->logger->debug("$upper_content_type: $title Not Found. Searching...");

                    $this->logger->debug("---------------  $upper_content_type Start: $title ---------------");

                    $details = $this->overview($title,$content_type,$released_year);
                    
                    if(!$details || !property_exists($details,'name')){
                        return;
                    } else {
                        $details->date_added_to_site = $added_date;
                        $this->logger->debug("Setting Date Added To Site: $added_date");
                        $this->send_details($details,$content_type);
                    }   
    
                    $this->logger->debug("---------------  $upper_content_type Complete: $title ---------------");

                    sleep(10);
                   
                } else {
                    $this->logger->debug("$upper_content_type: $title Found and Updating Now If Required");

                    if($content_type == 'series'){

                        if(property_exists($response_data,'update_required') && $response_data->update_required){
                            $this->logger->notice('Update Series Required. Last Time Content Updated Was'.$response_data->time_difference.' Days Ago');

                            $this->logger->notice("---------------  Updating $upper_content_type Start: $title ---------------");

                            $details = $this->overview($title,$content_type,$released_year);
                            
                            if(!$details || !property_exists($details,'name')){
                                return;
                            } else {
                                $details->date_added_to_site = $added_date;
                                $details->url = null;
                                $this->logger->debug("Setting Date Added To Site: $added_date");
                                
                                $this->update_details($details,'series');
                                
                            }   
            
                            $this->logger->notice("---------------  Updating $upper_content_type Complete: $title ---------------");

                            sleep(5);

                        } else {
                            $this->logger->notice('Update Not Required');
                        }

                    }
                    
                }

            });

        }

    }

    public function find_details($node,$content_type){

        $content = new Data();
        $content->name = preg_replace('/\s*-\s*Season \d.+/','',$node->filter('.name')->text());
        $content->image =  $node->filter('.picture img')->attr('src');
        $content->content_type = $content_type;

        $date = $node->filter('.date')->text();

        $this->logger->debug('New Content Found: '.$content->name);

        $this->logger->debug('Content Date Text Parsed: '.$date);

        preg_match('/(\d+) hour/i',$date,$hour_matches);
        preg_match('/(\d+) minute/i',$date,$minute_matches);
        preg_match('/(\d+) day/i',$date,$day_matches);
        preg_match('/Just now/i',$date,$now_matches);

        $current_date = new DateTime();
        
        if($hour_matches || $day_matches || $minute_matches || $now_matches){

            $this->logger->debug('Content Date Found: '.$date);
            
            if(!$now_matches){

                if($minute_matches){
                    $minutes_ago = $minute_matches[1];
                    $current_date->modify("-$minutes_ago minute");
                } elseif($hour_matches){
                    $hours_ago = $hour_matches[1];
                    $current_date->modify("-$hours_ago hour");
                } elseif($day_matches){
                    $days_ago = $day_matches[1];
                    $current_date->modify("-$days_ago day");
                }

            }

            $content->date_difference = $date;
            $date = $current_date->format('Y-m-d H:i:s');
            
        }

        
        $content->date = $date;

        return $content;
        
    }

    public function send_recent($content){

        $url = $this->config->api->url . $this->config->api->released;

        if($content){
            
            $this->logger->debug('Sending Recent Content Found');

            $api_response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],['data' => $content ]);
            $response = $this->parse_json($api_response);
    
            if(property_exists($response,'errors')){
                return $this->shared_api->errors($response->errors);
            }

            return $response;
        } else {
            throw new Exception('No Content Found To Send');
        }

    }
}

?>