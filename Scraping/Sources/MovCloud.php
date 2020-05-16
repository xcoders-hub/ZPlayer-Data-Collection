<?php

namespace App\Http\Player\Shared\Sources;
use App\Http\Player\Shared\Request;

class MovCloud extends Request {

    public $endpoint;

    function __construct(){
        $this->endpoint = 'https://api.movcloud.net/stream/';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;
        $response = $this->request($url);
        $content = $this->parse_json($response);

        $sources = [];

        foreach($content->data->sources as $source){
            $sources["Unknown"] = $source->file;
        }

        return $sources;
    }
}

?>