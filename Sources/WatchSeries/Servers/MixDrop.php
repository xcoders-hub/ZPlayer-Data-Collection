<?php

namespace Sources\WatchSeries\Servers;

use Data\Data;
use Shared\Request;

class MixDrop extends Request {

    public $endpoint,$logger,$config;

    function __construct($config,$logger){
        $this->logger = $logger;
        $this->config = $config;

        $this->endpoint = $config->data_page->sources->movcloud;
    }

    public function video_locations($url){
        $response = $this->request($url);

        preg_match("/'\|*MDCore\|\|*(.+)'.split/im", $response, $matches);

        //s-delivery9.mxdcontent.net/v/0bbabbedf757969f6e18876f5faec528.mp4?s=7CgpGUElJzQCF4RCXe7WfA&e=1604942873&_t=1604921463
        //s-delivery32.mxdcontent.net/v/0bbabbedf757969f6e18876f5faec528.mp4?s=hsIox8KKOCntErXGmlTRGw&e=1604942817&_t=1604923736

        $sources = [];

        if($matches){

            preg_match('/s\|(\w+)\|(\w+)\|(\w+)\|(\w+)\|(\w+)\|(?:\w+)\|(\w+).+\|_t\|(\w+)\|(\w+)/im',$matches[1], $video_matches);
                    
            if($video_matches){

                $video_sub_domain = $video_matches[1];
                $video_domain = $video_matches[4];
                $video_top_level_domain = $video_matches[5];

                $video_id = $video_matches[2];
                $video_type = $video_matches[3];

                $video_token = $video_matches[6];
                $video_expiry = $video_matches[7];
                $video_time = $video_matches[8];
    
                $video_url = "https://s-$video_sub_domain.$video_domain.$video_top_level_domain/v/$video_id.$video_type?s=$video_token&e=$video_expiry&_t=$video_time";
    
                $source = new Data();
                $source->quality = '';
                $source->url = $video_url;
                $source->server_name = 'MixDrop';
    
                $sources[] = $source;

            }

        }

        return $sources;
    }
}

?>