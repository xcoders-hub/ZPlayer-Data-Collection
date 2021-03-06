<?php

namespace Details;

use Shared\Request;

class History extends Request{

    public $logger,$config;

    function __construct($config,$logger)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function fetch($content_type){
        $url = $this->config->api->url . "/history/$content_type";
        $response = $this->request($url,'GET',['x-requested-with' => 'XMLHttpRequest']);
        $content = $this->parse_json($response);
        return $content;
    }

    public function update($content_type,$data){
        $url = $this->config->api->url . "/history/$content_type";
        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],$data);
        $content = $this->parse_json($response);
        return $content;
    }

}
?>