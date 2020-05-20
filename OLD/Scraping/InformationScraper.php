<?php

namespace App\Http\Player\Scraping;
use App\Http\Player\Shared\Request;
use App\Http\Player\Data\Details;
use App\Http\Player\Data\EpisodeDetails;
use App\Http\Player\Data\SeasonDetails;
use Exception;
use App\Http\Player\Data\SeriesDetails;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

// Fetches Information for any given show/Movie using IMDB
class InformationScraper extends Request {

    function details($name,$url,$image){

        // $response = $this->request("https://v2.sg.media-imdb.com/suggestion/h/how_i_met.json");
        // $content = $this->parse_json($response);

        // $show_details = $content->d[0]->i;

        // $image = $show_details->imageUrl;


        // $response = $this->request($url);
        // $content = $this->parse_html($response);

        // try {
        //     $released = trim($content->filter('.icon-calendar')->eq(0)->text());
        //     $released = preg_replace('/Released:\s*/i','',$released);
        // } catch(Exception $e){
        //     $released = '';
        // }
      
        // try {
        //     $description = trim($content->filter('.video-page-desc')->eq(0)->text());
        // } catch(Exception $e){
        //     $description = '';
        // }

        // try {
        //     $genre = $content->filter('.genres_info')->eq(0)->text();
        // } catch(Exception $e){
        //     $genre = '';
        // }

        $details = new Details();
        $details->name = $name;
        $details->url = $url;
        $details->image = $image;

        return $details;
    }

    function overview($name,$series=true){
        //Get IMDB Details

        // $name = "frozen 2";
        // $series = false;

        $name = strtolower($name);

        $first_letter = $name[0];
        $parsed_name = str_replace(' ','_',$name);

        $response = $this->request("https://v2.sg.media-imdb.com/suggestion/$first_letter/$parsed_name.json");
        $content = $this->parse_json($response);

        

        if(!property_exists($content,'d')){
            return null;
        }

        $show_id = $content->d[0]->id;

        if( $series && ( !property_exists($content->d[0],'q')  || !$this->contains_tv($content->d[0]->q) ) ){

            foreach($content->d as $suggestion){
                if(property_exists($suggestion,'q')  && $this->contains_tv($suggestion->q) ){
                    $show_id = $suggestion->id;
                    break;
                }
            }

        }

        $show_url = "https://www.imdb.com/title/$show_id";
        
        $response = $this->request($show_url);
        $content = $this->parse_html($response);

        $series_name = trim($content->filter('h1')->eq(0)->text());
        $lower_name = strtolower($series_name);

        similar_text($name, $lower_name, $perc);

        $series_details = new SeriesDetails();

        // dd("$name == $lower_name");

        // if($name == $lower_name || $perc >= 70){


        global $genres;

        $genres = null;

        $content->filter('#titleStoryLine')->each(function(Crawler $node, $i){

            $node->filter('.see-more')->each(function(Crawler $node, $i){
                global $genres;

                try {
                    $type = str_replace(':','',strtolower($node->filter('h4')->eq(0)->text()));

                    // dd($type);

                    if($type == 'genres'){

                        $genres = $node->filter('a')->each(function(Crawler $node, $i){
                            return $node->text();
                        });

                    }
                    
                } catch(Exception $e){
                    
                }
                
            });

        });

        $description = $content->filter('.summary_text')->eq(0)->text();

        try {
            global $released_date;

            $released_date = null;

            $content->filter('#titleDetails .txt-block')->each(function(Crawler $node, $i){
                global $released_date;

                $text = $node->text();

                preg_match('/date/i',$text,$matches);
                
                if( $matches ){
                    preg_match('/: (.+)\(\w+\)/',$text,$matches);
                    $released_date = trim($matches[1]);   
                }

            });
        } catch(Exception $e){
           
        }

        $image = $content->filter('.poster img')->eq(0)->attr('src');

        try {
            $rating = $content->filter('.ratingValue')->eq(0)->text();
        } catch(Exception $e){
            $rating = null;
        }
        

        
        $series_details->name = preg_replace('/^\s+|\s+$/','',$series_name);
        $series_details->old_name = $name;
        $series_details->url = $show_url;
        $series_details->description = $description;
        $series_details->genre = $genres;

        $series_details->released = $released_date;
        $series_details->image = $image;
        $series_details->rating = (float)$rating;

        if($series){

            $series_details->tv_series = true;

            // if(count($show_details) >= 2){
            //     $episode_length = $show_details[1];
            //     $series_details->episode_length = $episode_length;
            // }


            $total_season_number = (int)$content->filter('.seasons-and-year-nav div a')->first()->text();

            $seasons_list = [];

            //Gettting Season Episode Details.

            for($i =0; $i < $total_season_number;$i++){

                global $episodes_list,$season_url,$season_number;

                $season = new SeasonDetails();
                
                $season_number = $i + 1;
                $season_url = "https://www.imdb.com/title/$show_id/episodes/_ajax?season=$season_number";

                $season->season_number = $season_number;
                $season->url = $season_url;

                $response = $this->request($season_url);
                $content = $this->parse_html($response);

                $episodes_list = [];

                $content->filter('.list_item')->each(function(Crawler $node, $i){
                    global $episodes_list,$season_url,$season_number;

                    $episode = new EpisodeDetails();

                    try {

                        $episode->episode_number = $node->filter('[itemprop="episodeNumber"]')->attr('content');
                        $episode->name = trim($node->filter('[itemprop="name"]')->text());
                        $episode->description = trim($node->filter('[itemprop="description"]')->text());
                        $episode->released_date = trim($node->filter('.airdate')->text());
                        $episode->rating = trim($node->filter('.ipl-rating-star__rating')->text());
                        $episode->num_reviewed = trim($node->filter('.ipl-rating-star__total-votes')->text());
                        $episode->image = $node->filter('img')->attr('src');

                    } catch(Exception $e) {
                        Log::debug("No Details About Episode: Season $season_number Episode $i: $season_url");
                    }

                    
                    $episodes_list[] = $episode;

                });

                $season->episode_list = $episodes_list;
                
                $seasons_list[] = $season;
            }

            $series_details->seasons = $seasons_list;

        }

    

        return $series_details;

    }

    private function contains_tv($string){
        preg_match('/tv/i',$string,$matches);
        return $matches;
    }
}
?>