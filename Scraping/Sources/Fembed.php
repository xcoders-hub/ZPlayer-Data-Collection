<?php

namespace App\Http\Player\Shared\Sources;
use App\Http\Player\Shared\Request;
use Exception;

class Fembed extends Request {

    public $endpoint;

    function __construct(){
        $this->endpoint = 'https://feurl.com/api/source/';
    }

    public function video_locations($id){
        $url = $this->endpoint.$id;
        $response = $this->request($url,'POST');
        $content = $this->parse_json($response);

        $sources = [];

        if ($content->success){
            foreach($content->data as $source){
                $sources[$source->label] = $source->file;
            }
        }

        return $sources;
    }
}

?>