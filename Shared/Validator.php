<?php

namespace Shared;

class Validator {
    
    public function validate_url($url){
        preg_match('/^([a-z][a-z0-9\*\-\.]*):\/\/(?:(?:(?:[\w\.\-\+!$&\'\(\)*\+,;=]|%[0-9a-f]{2})+:)*(?:[\w\.\-\+%!$&\'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:(?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?](?:[\w#!:\.\?\+=&@!$\'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/',$url,$matches);
        if($matches){
            return true;
        } else {
            return false;
        }
    }

}
?>