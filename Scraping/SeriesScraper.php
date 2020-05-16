<?php

// Purpose:

// Number of seasons
// Number Of episode per season
// Watching episode

namespace App\Http\Player\Scraping;
use App\Http\Player\Shared\Request;
use App\Http\Player\Shared\Watch;
use App\Http\Player\Scraping\InformationScraper;
use App\Http\Player\Scraping\SearchScraper;
use Symfony\Component\DomCrawler\Crawler;

use Exception;

class SeriesScraper extends Watch {

    public $domain,$headers;

    function __construct(){
        $this->headers = array('x-requested-with' => 'XMLHttpRequest');
        $this->domain = "https://watchmovie.movie";
    }
    
    public function details($url){
        //https://www6.watchmovie.movie/series/jack-ryan-season-2
        
        $parsed_show_name = $this->parse_url($url);
        $show_name = $parsed_show_name;
        $info = new InformationScraper();
        $info->overview($show_name,$url);
        
    }

    public function stream($url){
        $sources = $this->fetch_sources($url);
        return $sources;
    }

    public function episodes_list($url,$season_number){
        $parsed_show_name = $this->parse_url($url);
        
        $season_name = $parsed_show_name . " Season $season_number";

        $search = new SearchScraper();
        $results = $search->search_suggestions($season_name);

        $season_url = array_values($results)[0] . '/season';

        $episodes = $this->all_episodes($season_url);
    }

    // Fetches All Episodes For Season From Season Page with All Episodes
    public function all_episodes($season_url){
        global $episodes;

        $response = $this->request($season_url);
        $content = $this->parse_html($response);

        $episodes = [];

        $content->filter('.vid_info a')->each(function(Crawler $node, $i){
            global $episodes;

            $episode_data['name'] = str_replace(':','',$node->text());
            $episode_data['link'] = $this->domain . $node->attr('href');
            // $episode_data['sources'] = $this->fetch_sources( $episode_data['link'] );

            $episodes[] = $episode_data;
        });

        dd(array_reverse($episodes));
    }

    private function parse_url($url){
        preg_match('/(.+series\/)(.+?)-\d+-*([A-Za-z]*)$/i',$url,$matches);

        if(!$matches){
            throw new Exception("Unknown URL Sesion Type");
        } else {
            $parsed_name = trim(str_replace('-',' ',str_replace('-season','',$matches[2])));
            return $parsed_name;
        }
    }

}

?>