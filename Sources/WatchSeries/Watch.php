<?php
// Shared between movies and shows

namespace Sources\WatchSeries;

use Data\Data;
use Symfony\Component\DomCrawler\Crawler;

use Shared\Request;
use Exception;

use Sources\WatchSeries\Servers\MovCloud;
use Sources\WatchSeries\Servers\Fembed;
use Sources\WatchSeries\Servers\Vidcloud;

// For a given page, return all links to watch it.
// For each link recognized return all possible links with names.

class Watch extends Request {

    public $domain = "https://watchmovie.movie";

    public $config,$logger;

    function __construct($config,$logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }


    //Fetch All Sources For Link
    public function fetch_sources($url){
        global $sources,$history,$vidcloud_id_history;

        $response = $this->request($url);
        $content = $this->parse_html($response);

        $sources = [];
        $history = [];
        $vidcloud_id_history = [];

        $content->filter('a[data-video]')->each(function(Crawler $node, $i){
            global $sources,$history,$vidcloud_id_history;

            $url = $node->attr('data-video');
            $url = preg_replace('/^\/\//',"https://",$url);
            preg_match('/^https:\/\/(?:www.)?(\w+)/',$url,$matches);
            
            if(key_exists($url,$history)){
               return;
            }

            $history[$url] = 1;

            if($matches && $matches != 'hydrax'){
                $name = strtolower($matches[1]);

                if($name == 'movcloud'){
                    $storage = new MovCloud($this->config,$this->logger);
                    preg_match('/\/embed\/([\w\d\_\-\+]+)\?*/',$url,$matches);
                } elseif($name == 'vidcloud9'){

                    preg_match('/\?id=(.+)/',$url,$matches);

                    if($matches){
                        $vidcloud_id = $matches[1];
                        
                        $vidcloud_page = $url;
                        
                        if(!key_exists($vidcloud_id,$vidcloud_id_history)){
                            $vidcloud_id_history[$vidcloud_id] = 1;
                        } else {
                            return;
                        }

                    }

                    $storage = new Vidcloud($this->config,$this->logger);
                    preg_match('/\.php\?(.+)/',$url,$matches);
                } elseif($name == 'fembed' || $name == 'gcloud'){
                    $storage = new Fembed($this->config,$this->logger);
                    preg_match('/\/v\/([\w\d\_\-\+]+)\#?/',$url,$matches);
                }

                if(!$matches){
                    throw new Exception('Unknown Video ID Format: '.$url);
                } elseif(isset($storage)) {
                    $video_id = $matches[1];
                    
                    $this->logger->debug('Found Video Link: '.$url);

                    $video_sources = [];

                    try {
                        $video_sources = $storage->video_locations($video_id);
                    } catch(Exception $e){
                        $this->logger->error('Source Errors: '. $e->getMessage());    
                    }
                   

                    if($video_sources && count($video_sources) > 0){
                        $sources = array_merge($sources,$video_sources);
                    }
                    
                    if($vidcloud_page){
                        $source = new Data();
                        $source->quality = '';
                        $source->url = $vidcloud_page;
                        $source->server_name = 'Content Page';

                        array_unshift($sources,$source);
                    }

                }

            }

        });
        
        return $sources;
    }

    public function fetch_movie($url){
        
        $response = $this->request($url);
        $content = $this->parse_html($response);

        $movie = new Data();

        try {
            $movie_url = $this->domain . $content->filter('a.view_more')->eq(0)->attr('href');
        } catch(Exception $e){
            return false;
        }
        
        $movie->sources = $this->fetch_sources($movie_url);
        $movie->content_url = $movie_url;

        return $movie;
    }

    public function fetch_series($url){

        global $episodes;

        $season_url = $url . '/season';
        $response = $this->request($season_url);
        $content = $this->parse_html($response);

        $episodes = [];

        $this->logger->debug('Fetching Sources For');

        $content->filter('.vid_info a')->each(function(Crawler $node, $i){

            global $episodes;

            $episode = $this->fetch_episode($node);

            $episodes[] = $episode;

        });

        // die( print_r( $this->fetch_sources('https://www6.watchmovie.movie/series/stargirl-season-1-2020-episode-2') ) );

        return $episodes;
    }
    
    public function fetch_episode($node){

        $episode_text = $node->text();
        preg_match('/episode/i',$episode_text,$matches);

        $episode = new Data();

        if($matches){

            $episode_number = str_replace(['Episode ',':'],'',$episode_text);
            $link = $this->domain . $node->attr('href');

            $this->logger->debug('Fetching Sources For Episode '.$episode_number);
            $sources = $this->fetch_sources( $link );
            $this->logger->debug('Found '.count( $sources ).' Sources');

            
            $episode->episode_number = $episode_number;
            $episode->sources = $sources;
            $episode->content_url = $link;

        } else {
            $this->logger->error('None Episode Type Found: '.$episode_text);
        }

        return $episode;
    }
}

?>