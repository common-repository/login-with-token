<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if(!class_exists('provider')) {
    abstract class provider
    {
        public $template = null;
        
        // Force Extending class to define this method
        abstract protected function configure($args = null);
        abstract function send_sms($token, $phone_number, $return_type="response_obj"); //return response obj or array
        abstract function validate_response(); //return TRUE in success, or (string) with Error Message otherwise.
        

        public function __construct($args = null) {
            $this->configure($args);
        }

    }
}