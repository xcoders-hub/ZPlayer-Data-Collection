<?php
// Gets a show, insert information into database.
namespace App\Http\Player;

use App\Http\Player\Scraping\InformationScraper;
use App\Http\Player\Shared\Watch;
use Symfony\Component\DomCrawler\Crawler;
use App\Jobs\CreateSeries;

class NewContent extends Watch {

    public $series_url = "https://www2.f2movies.to/tv-show";
    public $movies_url = "https://www2.f2movies.to/movie";

    public function series(){
        $this->find_new($this->series_url,'newShows');
    }

    public function movies(){
        $this->find_new($this->movies_url,'newMovies',false);
    }

    private function find_new($url,$queue='',$tv_series=true){
        //Check if show already exists in database.

        $response = $this->request($url);
        $content = $this->parse_html($response);

        $content->filter('h3.film-name')->each(function(Crawler $node, $i) use($queue,$tv_series){

            $name = trim($node->text());
            $item_url = $node->filter('a')->eq(0)->attr('href');

            $info = new InformationScraper();
            $details = $info->overview($name,$tv_series);

            $details->item_url = $item_url;

            dd($details);

            CreateSeries::dispatch( json_encode($details) )->onQueue($queue);

        });
    }
}
?>