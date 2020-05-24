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
    //Fetch All Sources For Link
    public function fetch_sources($url){
        global $sources;

        $response = $this->request($url);
        $content = $this->parse_html($response);

        $sources = [];

        $content->filter('a[data-video]')->each(function(Crawler $node, $i){
            global $sources;

            $url = $node->attr('data-video');
            $url = preg_replace('/^\/\//',"https://",$url);
            preg_match('/^https:\/\/(?:www.)?(\w+)/',$url,$matches);
            
            if($matches && $matches != 'hydrax'){
                $name = strtolower($matches[1]);

                if($name == 'movcloud'){
                    $storage = new MovCloud($this->config,$this->logger);
                    preg_match('/\/embed\/([\w\d\_\-\+]+)\?*/',$url,$matches);
                } elseif($name == 'vidcloud9'){
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
                    
                    $video_sources = [];

                    try {
                        $video_sources = $storage->video_locations($video_id);
                    } catch(Exception $e){
                        $this->logger->error('Source Errors: '. $e->getMessage());    
                    }
                   

                    if($video_sources && count($video_sources) > 0){
                        $sources = array_merge($sources,$video_sources);
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

            $episode_text = $node->text();
            preg_match('/episode/i',$episode_text,$matches);

            if($matches){

                $episode_number = str_replace(['Episode ',':'],'',$episode_text);
                $link = $this->domain . $node->attr('href');
    
                $this->logger->debug('Fetching Sources For '.$episode_number);
                $sources = $this->fetch_sources( $link );
                $this->logger->debug('Found '.count( $sources ).' Sources');
    
                $episode = new Data();
                $episode->episode_number = $episode_number;
                $episode->sources = $sources;
                $episode->content_url = $link;
    
                $episodes[] = $episode;

            } else {
                $this->logger->error('None Episode Type Found: '.$episode_text);
            }


        });

        // die( print_r( $this->fetch_sources('https://www6.watchmovie.movie/series/pulp-fiction-scd-episode-1') ) );

        return $episodes;
    }
    
}

?>