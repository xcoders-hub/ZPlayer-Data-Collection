<?php

namespace Sources;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../Shared/Logger.php';
require_once __DIR__.'/../Config/Config.php';

if(count($argv) < 3){
    die('Site And Type Required');
} else {

    $site = strtolower($argv[1]);
    $option = strtolower($argv[2]);

    if($site == 'watchseries'){
        
        if($option == 'series' || $option == 'movies'){
            $content_page = new WatchSeries\Main($Config,$logger);
            $content_page->search($option);
        } elseif($option == 'update'){
            $update_content = new WatchSeries\Update($Config,$logger);
            $update_content->sources();
        } else {
            die('Source Location Required');
        }

    } else {
        die('Source Location Required');
    }
}

?>