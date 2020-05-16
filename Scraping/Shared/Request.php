<?php

namespace App\Http\Player\Shared;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use \Symfony\Component\HttpClient\HttpClient;

// Used for sending GET/POST Requests. Retrying And Logging
class Request {

    public function request($url, $method="GET",$headers=[]){

        $client = HttpClient::create(['verify_peer' => false]);
        
        $response = $client->request($method, $url,[ 'headers' => $headers ]);

        $statusCode = $response->getStatusCode();


        if ($statusCode != 200){
            
            $times_retried = 0;
            
            while($times_retried < 5){
                $response = $client->request($method, $url,[ 'headers' => $headers ]);

                if ($statusCode == 200){
                    break;
                } 

                $times_retried++;
            }
        }

        $content = $response->getContent();

        return $content;
    }

    public function parse_html($content){
        return new Crawler( $content );
    }

    public function parse_json($content){
        return json_decode($content);
    }

}

?>