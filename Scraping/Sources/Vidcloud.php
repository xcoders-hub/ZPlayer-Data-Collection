<?php

namespace App\Http\Player\Shared\Sources;
use App\Http\Player\Shared\Request;

class Vidcloud extends Request {

    public $endpoint;

    function __construct(){
        $this->endpoint = 'https://vidcloud9.com/ajax.php?';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;
        $headers = array('x-requested-with' => 'XMLHttpRequest');

        $response = $this->request($url,'GET',$headers);
        $content = $this->parse_json($response);

        $sources = [];

        foreach($content->source as $source){
            $sources["Unknown"] = $source->file;
        }

        return array_unique($sources);
    }
}

?>