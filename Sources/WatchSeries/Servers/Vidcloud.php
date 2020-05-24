<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;
use Exception;
use Shared\Validator;

class Vidcloud extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;
        $this->endpoint = 'https://vidcloud9.com/ajax.php?';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;

        $response = $this->request($url,'GET',array('x-requested-with' => 'XMLHttpRequest'));
        $content = $this->parse_json($response);

        $sources = [];

        if($content && property_exists($content,'source')){

            foreach($content->source as $video){
                $url = $video->file;
                
                preg_match('/m3u8|st3.cdnfile/i',$url,$matches);
    
                if(!$matches && $this->validate_url($url)){
    
                    $source = new Data();
                    $source->quality = '';
                    $source->url = $url;
                    $source->server_name = 'vidcloud';
        
                    $sources[] = $source;
    
                }
    
            }
            
        }


        return $sources;
    }
}

?>