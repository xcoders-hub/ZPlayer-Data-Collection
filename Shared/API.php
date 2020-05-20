<?php

namespace Shared;
use Exception;

class API {

    public $logger;

    function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function errors($errors){

        $this->logger->error('---- API Errors ----');

        print_r($errors);

        foreach( $errors as $field => $reasons){
            foreach($reasons as $reason){
                $this->logger->error("Error $field: ".$reason);
            }
        }

        throw new Exception('API Errors');
    }
}
?>
