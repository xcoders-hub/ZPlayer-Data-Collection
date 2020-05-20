<?php

namespace Sources;

use Sources\WatchSeries\Main;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../Shared/Logger.php';
require_once __DIR__.'/../Config/Config.php';

if(count($argv) < 3){
    die('Site And Type Required');
} else {

    $site = strtolower($argv[1]);
    $type = strtolower($argv[2]);

    if($site == 'watchseries'){
        $content_page = new WatchSeries\Main($Config,$logger);
    } elseif(strtolower($argv[1]) == 'series'){
        
    } else {
        die('Source Location Required');
    }


    $sources = $content_page->search($type);

}

?>