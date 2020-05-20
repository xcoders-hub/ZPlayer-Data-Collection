<?php

namespace Details;

require_once __DIR__.'/../Shared/Logger.php';
require_once __DIR__.'/../Config/Config.php';

if(count($argv) < 2){
    die('New Content Type Required: Movie/Series');
} else {
    
    if(strtolower($argv[1]) == 'movies'){
        $tv_series = false;
        $logger->debug('Finding New Movies');
    } elseif(strtolower($argv[1]) == 'series'){
        $tv_series = true;
        $logger->debug('Finding New Series');
    } else {
        die('Type Required: Movie or Series');
    }

    $content = new NewContent($Config,$logger);

    if($tv_series){
        $content->series();
    } else {
        $content->movies();
    }

}

?>