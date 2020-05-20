<?php

namespace App\Http\Player;
use App\Http\Player\Scraping\SeriesScraper;

class Series extends SeriesScraper {

    // Return Number of seasons and all episodes in season X
    public function overview($url){
        $this->details($url);
    }

    public function episodes($url,$season_number){
        $this->episodes_list($url,$season_number);
    }

    // Return all sources for given episode of show using given link. Watching Episode
    public function watch($url){
        $sources = $this->fetch_sources($url);
        return $sources;
    }
}

?>