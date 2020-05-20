<?php

namespace Shared;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use \Symfony\Component\HttpClient\HttpClient;

// Used for sending GET/POST Requests. Retrying And Logging
class Request {

    public function request($url, $method="GET",$headers=[],$data=[]){

        $client = HttpClient::create(['verify_peer' => false]);
        $times_retried = 0;

        if(count($data) == 0){
            $request_data = [ 'headers' => $headers ];
        } else {
            $request_data = [ 'headers' => $headers,'json' => $data];
        }

        $request_send_success = false;

        $wait = $this->config->retry->request->wait;
        $retry =  $this->config->retry->request->times;

        while($times_retried < $retry ){
            
            try {

                $response = $client->request($method, $url,$request_data);

                $this->logger->debug("Sending $method Request: $url");

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
                    } elseif($statusCode == 404){
                        throw new Exception('404 Page Not Found');
                    } elseif($statusCode == 429){
                        throw new Exception('Too Many Requests. Slowing Down');
                    } elseif($statusCode == 500){
                        // $this->logger->critical('Something Seriously Wrong With Endpoint.');
                        if($content->message){
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

        
        if($request_send_success){
            return $content;
        } else {
            $this->logger->error('--- Failed To Send Request. ---');
            $this->logger->error("--- URL: $url ---");
            $this->logger->error("--- Method: $method ---");

            throw new Exception('Failed To Send Request: '.$url);
        }

    }

    public function parse_html($content){
        return new Crawler( $content );
    }

    public function parse_json($content){
        return json_decode($content);
    }

}

?>