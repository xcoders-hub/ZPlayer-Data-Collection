<?php

namespace Shared;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use \Symfony\Component\HttpClient\HttpClient;

// Used for sending GET/POST Requests. Retrying And Logging
class Request extends Validator {
 

    public function request($url, $method="GET",$headers=[],$data=[],$timeout=2000){

        $client = HttpClient::create(['verify_peer' => false]);
        
        $times_retried = 0;

        if(count($data) == 0){
            $request_data = [ 'headers' => $headers,'timeout' => $timeout];
        } else {
            $request_data = [ 'headers' => $headers,'json' => $data, 'timeout' => $timeout];
        }

        $request_send_success = false;

        $retry =  $this->config->retry->request->times;
        
        $ignore_response = false;

        while($times_retried < $retry ){
            
            $wait = $this->config->retry->request->wait;

            try {

                $this->logger->debug("Sending $method Request: $url");

                $response = $client->request($method, $url,$request_data);

                $statusCode = $response->getStatusCode();
                
                $content = $response->getContent(false);
                
                $this->logger->debug('Response Status Code: '.$statusCode);
                
                if ($statusCode == 200 ){    
                    $request_send_success = true;
                    break;
                } else {

                    $this->logger->error('Response Error: '.$content);

                    if($statusCode == 422) {
                        $request_send_success = true;
                        break;
                    } elseif($statusCode == 404 || $statusCode == 524){
                        preg_match('/imdb|vidcloud/i',$url,$matches);
                        if($matches){
                            $this->logger->error('Page Doesn\'t Exists');
                            $content = false;
                            $ignore_response = true;
                            break;
                        } else {
                            throw new Exception('404 Page Not Found');
                        }
                        
                    } elseif($statusCode == 429){
                        // $wait = 1;
                        throw new Exception('Too Many Requests. Slowing Down');
                    } elseif($statusCode == 400){
                        $this->logger->error('Page Doesn\'t Exists');
                        $content = false;
                        $ignore_response = true;
                        break;
                    } elseif($statusCode == 500 ){

                        if(property_exists($content,'message')){
                            $this->logger->critical('Error Message: '.$content->message);
                        }
    
                        $wait =+ 60;
                        throw new Exception('Failed To Connect To Server - Internal Server');
                    } else {
                        throw new Exception("$statusCode Error: ".$content);
                    }

                } 

            } catch(Exception $e){
                $this->logger->error('Request Error: '.$e->getMessage() );
                $this->logger->error("Waiting $wait Seconds");
                sleep( $wait );
            }

            $times_retried++;
        }

        
        if($request_send_success || $ignore_response){
            return $content;
        } else {
            $this->logger->error('--- Failed To Send Request. ---');
            $this->logger->error("--- URL: $url ---");
            $this->logger->error("--- Method: $method ---");

            throw new Exception('Failed To Send Request: '.$url);
        }

    }
    
    public function page_exists($url,$method='GET'){

        $this->logger->debug('Checking Existence Of: '.$url);

        if(!isset($url)){
            $this->logger->error('No URL Found');
            return false;
        }
        
        if(!$this->validate_url($url)){
            $this->logger->error('Invalid URL '.$url);
            return false;
        }
            
        try {
            $client = HttpClient::create(['verify_peer' => false]);
            $response = $client->request($method, $url);
            $content = $response->getContent();
        } catch(Exception $e) {
            $this->logger->error('Page Doesn\'t Exist: '.$url);
            return false;
        }

        return true;
    }

    public function parse_html($content){
        return new Crawler( $content );
    }

    public function parse_json($content){
        return json_decode($content);
    }

}

?>