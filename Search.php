<?php

namespace App\Http\Player;
use App\Http\Player\Scraping\SearchScraper;

// Search For Movies And Shows
// If season in name = Movie, Otherwise = Show
// With every search do one with and without season to do both show and movie search
// Return list of shows and movies with imdb details

class Search extends SearchScraper {

    public function ajax($query){
        //Search Website, Return and filter results.
        //Just Fetch Names and Types

        $results = $this->search_suggestions($query);

        return $results;
    }

    public function results($query){
        //When user clicks on suggested results. 
        //Return all possible filtered by show or movie

        $results = $this->search_results($query);

        return $results;
    }

}

?>