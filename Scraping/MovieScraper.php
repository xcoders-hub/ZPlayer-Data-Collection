<?php

// Purpose:

// Watching A Movie
namespace App\Http\Player\Scraping;
use App\Http\Player\Shared\Watch;

class MovieScraper extends Watch {

    public function stream($url){
        $stream_url = $url . '-episode-0';
        $sources = $this->fetch_sources($stream_url);
        return $sources;
    }
}

?>