<?php

namespace App\Http\Player;
use App\Http\Player\Scraping\MovieScraper;

class Movie extends MovieScraper {

    // Returns Details of Movie
    public function overview(){

    }

    //Returns All Links For Given Movie Using Given Link.
    public function watch($url){
        $sources = $this->stream($url);

        return $sources;
    }
}

?>