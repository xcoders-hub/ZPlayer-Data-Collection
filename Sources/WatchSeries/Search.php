<?php
// Fetches Search Data. Parses And Returns It
// Return names with linkes

namespace Sources\WatchSeries;

use Sources\WatchSeries\Watch;
use Data\Data;
use Details\Information;
use Exception;
use Sources\WatchSeries\Servers\Vidcloud;
use Symfony\Component\DomCrawler\Crawler;

class Search extends Watch {

    public $domain = "https://watchmovie.movie";
    public $headers = array('x-requested-with' => 'XMLHttpRequest');
    public $information;

    function __construct($config,$logger){
        $this->config = $config;
        $this->logger = $logger;

        $this->information = new Information($this->config,$this->logger);
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
        
        $query = str_replace('&','and',$query);
        
        $this->logger->debug('Searching For '.$query);

        $response = $this->request($this->domain."/search.html?keyword=$query");
        $content = $this->parse_html($response);

        global $search_results,$duplicate_movie_names,$unique_movie_names;

        $search_results = array('series' => [],'movies' => []);
        $duplicate_movie_names = [];
        $unique_movie_names = [];

        if( $content->filter('li.video-block')->count() == 0 ){
            $query = $this->convert_word_to_number($query);
            $response = $this->request($this->domain."/search.html?keyword=$query");
            $content = $this->parse_html($response);    
        }   

        $content->filter('li.video-block')->each(function(Crawler $node, $i){
            global $search_results,$duplicate_movie_names,$unique_movie_names;
            global $name,$url;

            $node->filter('a.videoHname')->each(function(Crawler $node, $i){
                global $name,$url;
    
                $url  = $this->domain . $node->attr('href');
                
                $name = $node->attr('title');
    
            });

            preg_match('/season/i',$name,$matches);

            $name = $this->clean_name($name);
            
            if($matches){
                $search_results['series'][] = ['name' => $name,'url' => $url];
            } else {
                if (key_exists($name,$unique_movie_names)){
                    $duplicate_movie_names[] = $name;
                } else {
                    $unique_movie_names[$name] = 1;
                }

                $search_results['movies'][] = ['name' => $name,'url' => $url];
            }

        });

        if(count($duplicate_movie_names) > 0){
            foreach($duplicate_movie_names as $duplicate_name){
                foreach($search_results['movies'] as $index => $movie){
                    if($movie['name'] == $duplicate_name){
                        $search_results['movies'][$index]['year'] = $this->information->get_released_year($movie['url']);
                    }
                }
            }
        
            $search_results['year_search_required'] = true;
        }
        
        // print_r($search_results);
        // usort($search_results['movies'], function ($a, $b) use ($query) {
        //     return $this->sort_by_similar($a,$b,$query);
        // });

        // usort($search_results['series'], function ($a, $b) use ($query) {
        //     return $this->sort_by_similar($a,$b,$query);
        // });

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
        $name = preg_replace('/\s*-\s*Season\s*\d|\(\d+\)$/i','',$name);
        $name = ucwords($name);
        $name = utf8_encode($name);
        $name = trim($name);
        return $name;
    }

    private function sort_by_similar($a, $b,$query){
        $levA = levenshtein($query, $a['name']);
        $levB = levenshtein($query, $b['name']);

        $this->logger->debug($a['name'] .' similar : '.$levA);
        $this->logger->debug($b['name'] .' similar : '.$levB);

        return $levA === $levB ? 0 : ($levA > $levB ? 1 : -1);
    }


    public function parent_page_search($query){

        $response = $this->request($this->config->data_parent_page->url . $this->config->data_parent_page->search .$query);
        $content = $this->parse_html($response);
        
        $node = $content->filter('.video-block')->eq(0);
        $url = $this->config->data_parent_page->url . $node->filter('a')->eq(0)->attr('href');

        $this->logger->debug('Parent URL: '. $url);


        $response = $this->request($url);
        $content = $this->parse_html($response);

        $vidcloud_page = preg_replace('/^\/\//',"https://",$content->filter('iframe[src]')->eq(0)->attr('src'));

        $this->logger->debug('Parent Iframe URL: '. $vidcloud_page);

        $this->logger->debug('Finding Parent Page Sources');

        $storage = new Vidcloud($this->config,$this->logger);
        preg_match('/\.php\?(.+)/',$vidcloud_page,$matches);

        $video_sources = $storage->video_locations($matches[1]);

        $source = new Data();
        $source->quality = '';
        $source->url = $vidcloud_page;
        $source->server_name = 'Content Page';
        
        array_unshift($video_sources,$source);

        return $video_sources;
    }

    public function convert_word_to_number($query){

        //Convert Number Text -> Number
        $number_conversion = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10
        ];

        foreach($number_conversion as $text => $number){
            preg_match("/$text/i",$query,$matches);
            if($matches){
                $query = preg_replace("/$text/i",$number,$query);
            }
        }

        return $query;
    }
}
?>