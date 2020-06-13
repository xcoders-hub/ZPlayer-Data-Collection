<?php

(object) $Config = array(

    'api' => [

        'url' => 'http://192.168.1.187:1057/api',

        'new' => [
            'movies' => '/movies/new/details',
            'series' => '/series/new/details',
            'episode' => '/series/new/episode'
        ],

        'sources' => [

            'list' => [
                'series' => '/sources/list/series',
                'movies' => '/sources/list/movies'
            ],

            'insert' => '/sources/insert',

            'status' => '/sources/status',

            'update' => '/sources/update',

            'delete' => '/sources/delete',
        ],

        'details' => [
            'series' => '/contents/series'
        ],

        'similar' => '/contents/similar',

        'unique' => '/unique',

        'update' => [
            'series' => '/contents/series/update'
        ],

        'released' => '/released'

    ],

    'data_page' => [

        'url' => 'https://www6.watchmovie.movie',

        'series' => '/watch-series',
        'movies' => '/letters',

    ],

    'released_page' => [
        'url' => 'https://vidcloud9.com/',

        'series'=> 'series',
        'movies'=> 'movies',
    ],

    'retry' => [
        'request' => [
            'wait' => 60,
            'times' => 10
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
