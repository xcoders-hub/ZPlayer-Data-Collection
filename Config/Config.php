<?php

(object) $Config = array(

    'api' => [

        'url' => 'http://192.168.1.187:1057/api',

        'new' => [
            'movies' => '/movies/new/details',
            'series' => '/series/new/details'
        ],

        'source' => [

            'list' => [
                'series' => '/sources/list/series',
                'movies' => '/sources/list/movies'
            ],

            'insert' => '/sources/insert'
        ]
    ],

    'data_page' => [

        'domain' => 'https://www2.f2movies.to',

        'movies' => 'https://www2.f2movies.to/movie',
        'series' => 'https://www2.f2movies.to/tv-show',

    ],

    'retry' => [
        'request' => [
            'wait' => 60,
            'times' => 5
        ]
    ]
);

function arrayToObject($array) {
    if (!is_array($array)) {
        return $array;
    }
    
    $object = new stdClass();
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $name=>$value) {
            $name = strtolower(trim($name));
            if (!empty($name)) {
                $object->$name = arrayToObject($value);
            }
        }
        return $object;
    }
    else {
        return FALSE;
    }
}

$Config = arrayToObject($Config);

?>
