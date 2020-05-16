<?php
// Fetches Search Data. Parses And Returns It
// Return names with linkes

namespace App\Http\Player\Scraping;
use App\Http\Player\Shared\Request;
use App\Http\Player\Scraping\InformationScraper;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

class SearchScraper extends Request {

    public $domain,$headers;

    function __construct(){
        $this->headers = array('x-requested-with' => 'XMLHttpRequest');
        $this->domain = "https://watchmovie.movie";
    }

    public function search_suggestions($query){
        
        $content = $this->search_request($query);
        
        preg_match('/ season \d/',$query,$matches);
        
        if(!$matches){
            $content2 = $this->search_request("$query season");
        } else {
            $content2 = [];
        }


        $suggestions_list = array_merge($content2,$content);

        return $suggestions_list;
    }

    public function search_results($query){
        $response = $this->request($this->domain."/search.html?keyword=$query","GET");
        $content = $this->parse_html($response);

        global $search_results,$information;

        $search_results = array();

        $information = new InformationScraper();

        $content->filter('li.video-block')->each(function(Crawler $node, $i){
            global $search_results,$information;
            global $name,$url,$image;

            $node->filter('a.videoHname')->each(function(Crawler $node, $i){
                global $name,$url;
    
                $url = $this->domain . $node->attr('href');
                $name = $node->attr('title');
                $name = $this->clean_name($name);
    
            });

            $node->filter('img.imgHome')->each(function(Crawler $node, $i){
                global $image;
                $image = $node->attr('src');
            });

            $details = $information->details($name,$url,$image); 
            $search_results[ $name ] = $details;

        });

        return $search_results;
    }

    private function search_request($query){
        $response = $this->request($this->domain."/ajax-search.html?keyword=$query&id=-1","GET",$this->headers);
        $content =  $this->parse_json($response)->content;
        $parsed_content = $this->parse_results($content);
        return $parsed_content;
    }

    private function parse_results($content){
        preg_match_all('/a href="(.+?)\".+?>(.+?)<\/a>/',$content,$matches);

        $suggestions_list = array();

        foreach($matches[2] as $index => $name){
            $name = $this->clean_name($name);
            $suggestions_list[$name] = $this->domain.$matches[1][$index];
        }

        return $suggestions_list;
    }

    private function clean_name($name){
        $name = preg_replace('/\s*-\s*Season\s*\d/i','',$name);
        $name = ucwords($name);
        $name = utf8_encode($name);
        $name = trim($name);
        return $name;
    }

    private function filter($data){
        //Filters passed in data into show or movie
    }
}
?>