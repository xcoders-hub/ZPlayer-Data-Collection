<?php

namespace Sources\WatchSeries;

use Data\Data;
use Exception;
use Shared\API;
use Sources\WatchSeries\Search;
use Sources\WatchSeries\Shared;


class Server extends Shared {
    
    public function listen(){

        $this->logger->debug('Server Listening On 9875');

        // Receives url, return sources
        $server = stream_socket_server('tcp://192.168.1.108:9875');

        $this->logger->debug('Waiting For Data ....');

        while(true){

            try {

                $socket = stream_socket_accept($server);

                $request_json = stream_socket_recvfrom($socket, 1500, 0, $peer);
                
                if (false === empty($request_json)) {
                    
                    $response = (object)json_decode($request_json);

                    $this->logger->debug('------- Incoming Request --------');
                    $data = $response->data;
                    $url = $data->content_url;

                    $this->logger->debug('Response: '.$request_json);

                    $this->logger->debug('Fetch Sources For: '. $url );

                    $this->config->retry->request->wait = 0;
                    $this->config->retry->request->times = 2;

                    $sources =  $this->fetch_sources($url);

                    $this->logger->debug('Found '.count($sources). ' Sources');

                    $response = new Data();
                    $response->sources = $sources;
                    
                    $response_json = json_encode( ['data' => $response ]);

                    print_r($response);

                    $this->logger->debug('Response Sent');
                    //Return data and update sources on database
                    stream_socket_sendto($socket, $response_json, 0, $peer);

                    $this->logger->debug('Updating Movie Source List');

                    $data->sources = $sources;

                    $this->send_sources($data);
        
                }
        
            } catch(Exception $e){
                $this->logger->error('Socket Error: '.$e->getMessage());
            }

        }

    }
}

?>