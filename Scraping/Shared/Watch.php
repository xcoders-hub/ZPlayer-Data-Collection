<?php
// Shared between movies and shows

namespace App\Http\Player\Shared;
use Symfony\Component\DomCrawler\Crawler;
use App\Http\Player\Shared\Sources\MovCloud;
use App\Http\Player\Shared\Sources\Fembed;
use App\Http\Player\Shared\Sources\Vidcloud;
use Exception;

// For a given page, return all links to watch it.
// For each link recognized return all possible links with names.

class Watch extends Request {

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
                    $storage = new MovCloud();
                    preg_match('/\/embed\/([\w\d\_\-\+]+)\?*/',$url,$matches);
                } elseif($name == 'vidcloud9'){
                    $storage = new Vidcloud();
                    preg_match('/\.php\?(.+)/',$url,$matches);
                } elseif($name == 'fembed' || $name == 'gcloud'){
                    $storage = new Fembed();
                    preg_match('/\/v\/([\w\d\_\-\+]+)\#?/',$url,$matches);
                }

                if(!$matches){
                    throw new Exception('Unknown Video ID Format: '.$url);
                } elseif(isset($storage)) {
                    $video_id = $matches[1];
                    
                    $video_sources = $storage->video_locations($video_id);

                    if($video_sources){
                        $sources[$name][] = $video_sources;
                    }
                    
                   
                }

            }

        });

        return $sources;
    }

}

?>