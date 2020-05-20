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

    public function fetch($type){
        $url = $this->config->api->url . "/history/$type";
        $response = $this->request($url,'GET',['x-requested-with' => 'XMLHttpRequest']);
        $content = $this->parse_json($response);
        return $content;
    }

    public function update($type,$data){
        $url = $this->config->api->url . "/history/$type";
        $response = $this->request($url,'POST',['x-requested-with' => 'XMLHttpRequest'],$data);
        $content = $this->parse_json($response);
        return $content;
    }

}
?>