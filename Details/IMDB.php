<?php

namespace Details;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use Shared\Request;
use Data\Data;

// Fetches Information for any given show/Movie using IMDB
class IMDB extends Request {

    function overview($name,$type,$year){
        //Get IMDB Details

        // $name = 'star wars';
        // $year = 2008;
        // $type = 'movie';

        $name = preg_replace('/\?/','',strtolower($name));

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

        if($type == 'series'){

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


        $show_url = "https://www.imdb.com/title/$show_id";
        // $show_url = "https://www.imdb.com/title/tt6136778";
        
        $this->logger->debug('IMDB URL: '.$show_url);

        try {
            $response = $this->request($show_url);
            $content = $this->parse_html($response);
        } catch(Exception $e){
            $this->logger->error('Failed To Find IMDB Page. Skipping');
            return false;
        }


        file_put_contents(__DIR__.'/../Downloads/Details/imdb_page.html',$content->html());

        $content_found = false;

        for($i =0;$i < $this->config->retry->request->times;$i++){

            try {

                $series_name = trim($content->filter('h1')->eq(0)->text());
        
                $series_details = new Data();
        
                global $genres;
        
                $genres = null;
                
                try {
                    $description = preg_replace('/See full .+/i','',$content->filter('.summary_text')->eq(0)->text());
                } catch(Exception $e){
                    $this->logger->error('Unrecognised Page Type: '.$e->getMessage());
                    return false;
                }


                $content->filter('#titleStoryLine')->each(function(Crawler $node, $i){
        
                    $node->filter('.see-more')->each(function(Crawler $node, $i){
                        global $genres;
        
                        try {
                            $type = str_replace(':','',strtolower($node->filter('h4')->eq(0)->text()));
        
                            if($type == 'genres'){
        
                                $genres = $node->filter('a')->each(function(Crawler $node, $i){
                                    return $node->text();
                                });
        
                            }
                            
                        } catch(Exception $e){
                            
                        }
                        
                    });
        
                });
        
                try {
                    global $released_date;
        
                    $released_date = null;
        
                    $content->filter('#titleDetails .txt-block')->each(function(Crawler $node, $i){
                        global $released_date;
        
                        $text = $node->text();

                        preg_match('/release date/i',$text,$matches);
                        
                        if( $matches ){
                            $this->logger->debug($text);

                            preg_match('/: (.+)\([^\(\)]+\)/',$text,$matches);
                            return $released_date = trim($matches[1]);   
                        }
        
                    });
                } catch(Exception $e){
                   
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
                $series_details->old_name = htmlspecialchars($name);

                $genres = implode(', ',(array)$genres);

                $series_details->imdb_url = $show_url;
                $series_details->description = $description;
                $series_details->genres = $genres;

                $series_details->released = $released_date;
                $series_details->image = $image;
                $series_details->rating = (float)$rating;

                try {
                    $series_details->num_reviews = str_replace(',','',$content->filter('[itemprop="ratingCount"]')->eq(0)->text());
                } catch(Exception $e) {
                    $series_details->num_reviews = 0;
                }
                

                $this->logger->debug($name.' === '.$series_details->name);
                $this->logger->debug('Genre: '. $genres);
                $this->logger->debug('Released: '.$released_date);

                $this->logger->debug('IMDB Rating: '.$rating);

                if($type == 'series'){

                    $series_details->tv_series = true;

                    try {
                        $total_season_number = (int)$content->filter('.seasons-and-year-nav div a')->first()->text();
                    } catch(Exception $e) {
                        $this->logger->error('Not A Show. Skipping.');
                        return false;
                    }
                    

                    $seasons_list = [];

                    $this->logger->debug("$total_season_number Total Season Found");

                    // $series_details->num_seasons = $total_season_number;

                    //Gettting Season Episode Details.

                    try {

                        for($i =0; $i < $total_season_number;$i++){

                            global $episodes_list,$season_url,$season_number;
    
                            $season = new Data();
                            
                            $season_number = $i + 1;
                            $season_url = "https://www.imdb.com/title/$show_id/episodes/_ajax?season=$season_number";
    
                            $season->season_number = $season_number;
                            $season->url = $season_url;
    
                            $response = $this->request($season_url);
                            $content = $this->parse_html($response);
    
                            $episodes_list = [];
    
                            $content->filter('.list_item')->each(function(Crawler $node, $i) {
                                global $episodes_list,$season_url,$season_number;
    
                                $episode = new Data();
    
                                try {
    
                                    $episode->episode_number = $node->filter('[itemprop="episodeNumber"]')->attr('content');
                                    $episode->name = trim($node->filter('[itemprop="name"]')->text());
                                    $episode->description = trim($node->filter('[itemprop="description"]')->text());
                                    $episode->released = trim($node->filter('.airdate')->text());
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
                
                $content_found = true;
                break;

            } catch(Exception $e){
                $this->logger->error('Content Not Found. Reloading Page');

                $response = $this->request($show_url);
                $content = $this->parse_html($response);
                
                sleep( $this->config->retry->request->wait );
            }
            
        }

        if(!$content_found){
            print_r($content_found);
            throw new Exception('Failed To Find Details');
        }

        return $series_details;

    }

    private function contains_tv($string){
        preg_match('/tv/i',$string,$matches1);
        preg_match('/movie/i',$string,$matches2);

        return ($matches1 && !$matches2);
    }
}
?>