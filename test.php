<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/Shared/Logger.php';
require_once __DIR__.'/Config/Config.php';

use Details\Information;
use Sources\WatchSeries\Search;
use Sources\WatchSeries\Servers\Vidcloud;
use Sources\WatchSeries\Shared;
use Sources\WatchSeries\Watch;

$title = 'hobbs and shaw';
$released_year = false;
$content_type = 'movies';

$content = new Information($Config,$logger);

$logger->debug("---------------  Start: $title ---------------");

$details = $content->overview($title,$content_type,$released_year);

$details->url  = null;

// $content->update_details($details,'series');
// print_r($details);

print_r($details);

$logger->debug("---------------  Complete: $title ---------------");



// $search = new Search($Config,$logger);

// $shared =  new Shared($Config,$logger);
// $search->parent_page_search('star wars new hope');

// $results = $search->search_results($title);

// die(print_r( $results ));

// die(print_r( $shared->parse_results($results,$content_type,$released_year) ));


// $watch = new Vidcloud($Config,$logger);
// $url = 'https://vidcloud9.com/streaming.php?id=ODMxOQ==&title=Star+Wars%3A+Episode+Iv+-+A+New+Hope+HD+720p+&typesub=SUB&sub=L3N0YXItd2Fycy1lcGlzb2RlLWl2LWEtbmV3LWhvcGUtaGQtNzIwcC9zdGFyLXdhcnMtZXBpc29kZS1pdi1hLW5ldy1ob3BlLWhkLTcyMHAudnR0&cover=L3N0YXItd2Fycy1lcGlzb2RlLWl2LWEtbmV3LWhvcGUtZWdzL2NvdmVyLnBuZw==';
// preg_match('/\.php\?(.+)/',$url,$matches);
// $video_id = $matches[1];
// $sources = $watch->video_locations($video_id);
// print_r($sources);

//

// ./vendor/bin/phpunit --colors --testdox tests
?>