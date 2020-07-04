<?php

namespace Shared;

require_once __DIR__.'/../vendor/autoload.php';

use Defuse\Crypto\Crypto;
use Exception;

class Config {

    public $logger;

    function __construct($logger)
    {
        $this->logger = $logger;
    }

    //Fetches details from config file
    public function load_config($key){
        $config_location = __DIR__.'/../Config/config.txt';

        try {
            $config_json = Crypto::decryptWithPassword( file_get_contents($config_location),$key);
        } catch(Exception $e) {
            $this->logger->critical('Incorrect Decryption Key');
            exit('Script Exiting');
        }
        
        $config = json_decode($config_json);

        return $config;
    }
}

?>