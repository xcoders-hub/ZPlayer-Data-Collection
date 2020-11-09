<?php
// Shared between movies and shows

namespace Sources\WatchSeries;

use Data\Data;
use Symfony\Component\DomCrawler\Crawler;

use Shared\Request;
use Exception;
use Sources\WatchSeries\Servers\EasyLoad;
use Sources\WatchSeries\Servers\MovCloud;
use Sources\WatchSeries\Servers\Fembed;
use Sources\WatchSeries\Servers\MixDrop;
use Sources\WatchSeries\Servers\Vidcloud;

// For a given page, return all links to watch it.
// For each link recognized return all possible links with names.

class Watch extends Request {

    public $config,$logger;

    function __construct($config,$logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }


    //Fetch All Sources For Link
    public function fetch_sources($url,$is_vidcloud_url=false){
        global $sources,$history,$vidcloud_id_history,$vidcloud_url;

        $sources = [];
        $history = [];
        $vidcloud_id_history = [];

        if(!$is_vidcloud_url){
            $vidcloud_url = $this->iframe_source($url);
        } else {
            preg_match('/vidcloud/i',$url,$matches);
            if(!$matches){
                throw new Exception('Invalid Vicloud URL Found');
            } else {
                $vidcloud_url = $url;
            }
            
        }
        
        $response = $this->request($vidcloud_url);
        $content = $this->parse_html($response);

        $parent_source = new Data();
        $parent_source->quality = '';
        $parent_source->url = preg_replace('/&title.+/','',$vidcloud_url);
        $parent_source->server_name = 'Content Page';

        $sources[] = $parent_source;

        $content->filter('ul li.linkserver')->each(function(Crawler $node, $i){
            global $sources,$history,$vidcloud_id_history,$vidcloud_url;

            $url = $node->attr('data-video');
            
            if(!$url || $url == '' || is_numeric($url)){
                $url = $vidcloud_url;
            }

            $url = preg_replace('/^\/\//',"https://",$url);
            preg_match('/^https:\/\/(?:www.)?(\w+)/',$url,$matches);
            
            if(key_exists($url,$history)){
               return;
            }
            
            $history[$url] = 1;

            if($matches && $matches != 'hydrax'){
                $name = strtolower($matches[1]);
                
                $this->logger->debug('Source Type: '.$name);

                if($name == 'movcloud'){
                    $storage = new MovCloud($this->config,$this->logger);
                    preg_match('/\/embed\/([\w\d\_\-\+]+)\?*/',$url,$matches);
                } elseif($name == 'vidcloud9'){

                    preg_match('/\?id=(.+)/',$url,$matches);

                    if($matches){
                        $vidcloud_id = $matches[1];
                
                        if(!key_exists($vidcloud_id,$vidcloud_id_history)){
                            $vidcloud_id_history[$vidcloud_id] = 1;
                        } else {
                            return;
                        }

                    }

                    $storage = new Vidcloud($this->config,$this->logger);
                    preg_match('/\.php\?(.+)/',$url,$matches);
                } elseif($name == 'fembed' || $name == 'gcloud'){
                    // $storage = new Fembed($this->config,$this->logger);
                    // preg_match('/\/v\/([\w\d\_\-\+]+)\#?/',$url,$matches);
                } elseif($name == 'easyload' || $name == 'streamtape'){
                    $storage = new EasyLoad($this->config,$this->logger);
                    $matches = [1, $url];
                } elseif($name == 'mixdrop'){
                    $storage = new MixDrop($this->config,$this->logger);
                    $matches = [1, $url];
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

                }

            }

        });
        
        return $sources;
    }

    public function fetch_movie($url,$query){
        
        $movie = new Data();

        $this->logger->debug("Find $query Movie In: $url");

        try {
            $response = $this->request($url);
            $content = $this->parse_html($response);

            $movie_url = $this->domain . $content->filter('a.view_more')->eq(0)->attr('href');
            
            $movie->sources = $this->fetch_sources($movie_url);
            $movie->content_url = $movie_url;

        } catch(Exception $e){
            $search = new Search($this->config,$this->logger);
            $movie->sources = $search->parent_page_search($query);
        }

        return $movie;
    }

    public function fetch_series($url){

        global $episodes;

        if(!$url){
            throw new Exception('No Series URL Given');
        }
        
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
    
    public function iframe_source($url){
        $response = $this->request($url);
        $content = $this->parse_html($response);
        $this->logger->debug('Finding Iframe URL: '.$url);
        $iframe_url = preg_replace('/^\/\//',"https://",$content->filter('iframe[src]')->eq(0)->attr('src'));
        $iframe_url = preg_replace('/&title.+/','',$iframe_url);
        return $iframe_url;
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
    
    public function vidcloud_episode($url,$episode_number){
        $this->logger->debug('Finding Vidcloud Sources For Episode '. $episode_number);
        
        if(is_null($episode_number) || is_nan($episode_number) ){
            throw new Exception('Epsiode Number Must Be A Number: '. $episode_number);
        }

        $response = $this->request($url);
        $content = $this->parse_html($response);

        global $episode_video_sources;

        try {
            $content->filter('.listing.items.lists .video-block')->each(function(Crawler $node) use ($episode_number){
                global $episode_video_sources;

                $name =  $node->filter('a')->eq(0)->text();
                $url = $this->config->data_parent_page->url . $node->filter('a')->eq(0)->attr('href');
    
                preg_match("/episode 0*$episode_number\b/i",$name,$episode_matches);
                
                if($episode_matches){
                    $this->logger->debug('Episode Found: '. $name);
                    $episode_source_url = $this->iframe_source($url);

                    $episode_video_sources = $this->fetch_sources($episode_source_url,true);
            
                    throw new Exception();
                }
               
            });

        } catch(Exception $e) {
            $this->logger->debug('Correct Episode Found. Vidcloud Source URL Returned');
        }

        return $episode_video_sources;

    }

    public function vidcloud_movie($url){
        $this->logger->debug('Finding Movies Sources');
        return $this->fetch_sources( $this->iframe_source($url) ,true);
    }

}

?>