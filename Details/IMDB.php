<?php

namespace Details;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use Shared\Request;
use Data\Data;
use DateTime;

// Fetches Information for any given show/Movie using IMDB
class IMDB extends Request {

    public function overview($name,$content_type,$year=false,$show_id=false){
        
        if(!$show_id){
            $show_id = $this->get_show_id($name,$content_type,$year);
        } else {
            $this->logger->debug('Show Id Passed In: '.$show_id);
        }

        if(!$show_id){
            $this->logger->error('No Matching IMDB Page Found');
            return false;
        }

        $show_url = "https://www.imdb.com/title/$show_id";

        $series_details = new Data();
        $series_details->imdb_url = $show_url;
        $series_details->old_name = htmlspecialchars($name);
        $series_details->content_type = $content_type;
        $series_details->show_id = $show_id;

        $success = $this->process_page($series_details);
        
        if($success){
            return $series_details;
        } else {
            return false;
        }

    }

    public function process_page($series_details){

        $this->logger->debug('IMDB URL: '.$series_details->imdb_url);

        try {
            $response = $this->request($series_details->imdb_url);
            $content = $this->parse_html($response);
        } catch(Exception $e){
            $this->logger->error('Failed To Find IMDB Page. Skipping');
            return false;
        }


        file_put_contents(__DIR__.'/../Downloads/Details/imdb_page.html',$content->html());

        $content_found = false;

        for($i =0;$i < $this->config->retry->request->times;$i++){

            try {
                
                $this->details($content,$series_details);

                $content_found = true;
                
                break;

            } catch(Exception $e){
                $this->logger->error('Content Not Found. Reloading Page: '.$e->getMessage());

                $response = $this->request($series_details->imdb_url);
                $content = $this->parse_html($response);
                
                sleep( $this->config->retry->request->wait );
            }
            
        }

        if(!$content_found){
            throw new Exception('Failed To Find Details');
        }

        return true;
    }

    public function get_show_id($name,$content_type,$year=false){

        $name = preg_replace('/\?|\//','',strtolower($name));

        preg_match('/^.*?(\w)/',$name,$matches);
        if(!$matches){
            throw new Exception('No Letters Found In Title: '.$name);
        } else {
            $first_letter = $matches[1];

            if($first_letter != $name[0]){
                $name = preg_replace("/[^A-Za-z0-9 ]/", '', $name);
            }
        }

        $parsed_name = str_replace(' ','_',$name);

        if($year){
            $parsed_name .= "_$year";
        }
        
        $search_url = "https://v2.sg.media-imdb.com/suggestion/$first_letter/$parsed_name.json";
        $response = $this->request($search_url);
        $content = $this->parse_json($response);        

        file_put_contents(__DIR__.'/../Downloads/Details/imdb_search.json',$response);

        if(!property_exists($content,'d')){
            return null;
        }

        $show_id = $content->d[0]->id;

        if($content_type == 'series'){

            if( ( !property_exists($content->d[0],'q')  || !$this->contains_tv($content->d[0]->q) ) ){

                $page_not_found = true;
    
                foreach($content->d as $suggestion){
                    if(property_exists($suggestion,'q')  && $this->contains_tv($suggestion->q) ){
                        $page_not_found = false;
                        $show_id = $suggestion->id;
                        break;
                    }
                }
    
                if($page_not_found){
                    $this->logger->error('Skipping, Correct Content Type Not Found: '.$search_url);
                    return false;
                }
    
            }

        } else {

            if(  property_exists($content->d[0],'q')  && $this->contains_tv($content->d[0]->q) ){

                $page_not_found = true;
    
                foreach($content->d as $suggestion){
                    if(property_exists($suggestion,'q')  && !$this->contains_tv($suggestion->q) ){
                        $page_not_found = false;
                        $show_id = $suggestion->id;
                        break;
                    }
                }
    
                if($page_not_found){
                    $this->logger->error('Skipping, Correct Content Type Not Found: '.$search_url);
                    return false;
                }
    
            }

        }

        return $show_id;

    }

    public function details($content,$series_details){

        try {
            $series_name = trim($content->filter('h1')->eq(0)->text());
        } catch(Exception $e) {
            throw new Exception('No Series Name Found');
        }
        
        
        global $genres;

        $genres = null;
        
        try {
            $description = preg_replace('/See full .+/i','',$content->filter('.summary_text')->eq(0)->text());
        } catch(Exception $e){
            $this->logger->error('Unrecognised Page Type: '.$e->getMessage());
            return false;
        }


        try {

            $content->filter('#titleStoryLine')->each(function(Crawler $node, $i){

                $node->filter('.see-more')->each(function(Crawler $node, $i){
                    global $genres;
    
                    try {
                        $content_type = str_replace(':','',strtolower($node->filter('h4')->eq(0)->text()));
    
                        if($content_type == 'genres'){
    
                            $genres = $node->filter('a')->each(function(Crawler $node, $i){
                                return $node->text();
                            });
    
                        }
                        
                    } catch(Exception $e){
                        
                    }
                    
                });
    
            });

        } catch(Exception $e) {
            $this->logger->error('Error: No Genres Found');
        }


        try {
            global $released_date;

            $released_date = null;

            $content->filter('#titleDetails .txt-block')->each(function(Crawler $node, $i){
                global $released_date;

                $text = $node->text();

                preg_match('/release date/i',$text,$matches);
                
                if( $matches ){
                    $this->logger->debug($text);

                    $released_date = $this->parse_date($text);
                }

            });
        } catch(Exception $e){
            $this->logger->error('Error Getting Realeased Date');
        }

        try {
            $image = $content->filter('.poster img')->eq(0)->attr('src');
        } catch(Exception $e){
            $image = null;
        }

        try {
            $rating = $content->filter('.ratingValue')->eq(0)->text();
        } catch(Exception $e){
            $rating = null;
        }

        $series_details->name = htmlspecialchars( preg_replace('/^\s+|\s+$/','',$series_name) );

        $genres = implode(', ',(array)$genres);

        $series_details->related = $this->related($content);
        $series_details->description = $description;
        $series_details->genres = $genres;

        $series_details->released = $released_date;
        $series_details->image = $image;
        $series_details->rating = (float)$rating;

        $series_details->show_id = $this->url_id( $series_details->imdb_url );
        
        try {
            $series_details->num_reviews = str_replace(',','',$content->filter('[itemprop="ratingCount"]')->eq(0)->text());
        } catch(Exception $e) {
            $series_details->num_reviews = 0;
        }
        
        $old_name = $series_details->old_name ?? $series_details->name;
        $new_name = $series_details->name;

        $this->logger->debug("$old_name === $new_name");
        $this->logger->debug('Genre: '. $genres);
        $this->logger->debug('Released: '.$released_date);

        $this->logger->debug('IMDB Rating: '.$rating);

        if($series_details->content_type == 'series'){
            $this->season_details($content,$series_details);
        }

    }

    public function season_details($content,$series_details){

        $series_details->tv_series = true;
        $seasons_list = [];

        try {
            $total_season_number = (int)$content->filter('.seasons-and-year-nav div a')->first()->text();
        } catch(Exception $e) {
            $this->logger->error('No Seasons Found');
            $series_details->seasons = $seasons_list;
            return false;
        }
        
        $this->logger->debug("$total_season_number Total Season Found");

        try {

            for($i =0; $i < $total_season_number;$i++){

                global $episodes_list,$season_url,$season_number;

                $season = new Data();
                
                $season_number = $i + 1;
                $season_url = "https://www.imdb.com/title/{$series_details->show_id}/episodes/_ajax?season=$season_number";

                $season->season_number = $season_number;
                $season->imdb_url = $season_url;

                $episodes_list = [];

                try {
                    $response = $this->request($season_url);
                    $content = $this->parse_html($response);
                } catch(Exception $e) {
                    $this->logger->error('Failed To Find Season Page: '.$season_url);
                    $season->episodes = $episodes_list;
                    $seasons_list[] = $season;
                    
                    continue;
                }

                
                if(!$content){
                    break;
                }

                $content->filter('.list_item')->each(function(Crawler $node, $i) {
                    global $episodes_list,$season_number;

                    $episode = new Data();

                    try {

                        $episode->episode_number = trim(preg_replace('/\(|\)|,/','',$node->filter('[itemprop="episodeNumber"]')->attr('content')));
                        $episode->name = trim($node->filter('[itemprop="name"]')->attr('title'));
                        $episode->description = trim($node->filter('[itemprop="description"]')->text());
                        $episode->released = $this->parse_date( trim($node->filter('.airdate')->text()) );
                        $episode->rating = trim($node->filter('.ipl-rating-star__rating')->text());
                        $episode->num_reviews = trim(preg_replace('/\(|\)|,/','',$node->filter('.ipl-rating-star__total-votes')->text()));
                        $episode->image = $node->filter('img')->attr('src');

                    } catch(Exception $e) {
                        $this->logger->debug("No Details: Season $season_number - Episode $i");
                    }

                    $episodes_list[] = $episode;

                });

                $season->episodes = $episodes_list;
                $seasons_list[] = $season;

            }

        } catch(Exception $e){
            $this->logger->error('Season Details Error: '.$e->getMessage());
        }

        $series_details->seasons = $seasons_list;

    }

    public function related($content){

        global $names;
        $names = [];

        $content->filter('.rec_item a')->each(function(Crawler $node, $i){
            global $names;

            try {

                $link = $node->attr('href');
                $id = $this->url_id($link);
                $name = $node->filter('img')->eq(0)->attr('title');
    
                $names[$id] = $name;

            } catch(Exception $e) {
                $this->logger->error('Error With Related Items');
            }


        });

        return $names;
    }

    public function parse_date($text_date){
        preg_match('/: (.+)\([^\(\)]+\)/',$text_date,$matches);
        $released_date = trim($matches[1]);   

        $released_date = date("Y-m-d H:i:s", strtotime($released_date));
        return $released_date;
    }

    public function url_id($imdb_url){

        preg_match('/title\/(\w+)/', $imdb_url,$matches);

        if(!$matches){
            throw new Exception('No IMDB ID URL Found: '. $imdb_url );
        } else {
            return $matches[1];
        }
    }

    private function contains_tv($string){
        preg_match('/tv/i',$string,$matches1);
        preg_match('/movie/i',$string,$matches2);

        return ($matches1 && !$matches2);
    }
}
?>