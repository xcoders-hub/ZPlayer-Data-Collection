<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;
use Exception;
use Shared\Validator;
use Symfony\Component\DomCrawler\Crawler;

class Vidcloud extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;

        $this->endpoint = $config->data_page->sources->vidcloud->ajax;
        $this->download_endpoint = $config->data_page->sources->vidcloud->download;
    }

    public function video_locations($id){
        global $vid_sources;

        $vid_sources = [];

        $url = $this->endpoint.$id;

        $response = $this->request($url,'GET',array('x-requested-with' => 'XMLHttpRequest'));
        $content = $this->parse_json($response);

        if($content && property_exists($content,'source')){

            foreach($content->source as $video){
                $url = $video->file;
                
                preg_match('/\/\/m(:?2|3|5)x|st3.cdnfile/i',$url,$matches);
    
                if(!$matches && $this->validate_url($url)){
    
                    $source = new Data();
                    $source->quality = '';
                    $source->url = $url;
                    $source->server_name = 'Vidcloud';
        
                    $vid_sources[] = $source;
    
                }
    
            }
            
        }

        // $url = $this->download_endpoint . $id;

        // $response = $this->request($url,'GET',array('x-requested-with' => 'XMLHttpRequest'));
        // $content = $this->parse_html($response);
        
        // $content->filter('.mirror_link .dowload a')->each(function(Crawler $node, $i){
        //     global $vid_sources;

        //     $quality = preg_replace('/Download| \(|\)| - mp4/i','',$node->text());
        //     $link = $node->attr('href');

        //     preg_match('/ads|Xstreamcdn/i',$quality,$matches);

        //     if(!$matches){

        //         $this->logger->debug('Found: '.$quality);

        //         $source = new Data();
        //         $source->quality = trim($quality);
        //         $source->url = str_replace(' ','%20',$link);
        //         $source->server_name = 'VidCloud';
    
        //         $vid_sources[] = $source;

        //     }

        // });

        return $vid_sources;

    }
}

?>