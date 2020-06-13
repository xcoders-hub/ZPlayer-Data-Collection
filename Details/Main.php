<?php

namespace Details;

require_once __DIR__.'/../Shared/Logger.php';
require_once __DIR__.'/../Config/Config.php';

if(count($argv) < 2){
    die('New Content Type Required: Movie/Series');
} else {
    
    $content = new NewContent($Config,$logger);

    if(strtolower($argv[1]) == 'movies'){
        $logger->debug('Finding New Movies');
        $content->movies();
    } elseif(strtolower($argv[1]) == 'series'){
        $logger->debug('Finding New Series');
        $content->series();
    } elseif(strtolower($argv[1]) == 'update'){
        $content->update();
    } elseif(strtolower($argv[1] == 'similar')){
        $content->similar();
    } elseif(strtolower($argv[1] == 'released')){
        $released = new Recent($Config,$logger);
        $content_type = $argv[2] ?? die('No Content Type Found: Update Series/Movies');
        $released->recent_content($content_type);
    } else {
        die('Type Required: Movie or Series');
    }

}

?>