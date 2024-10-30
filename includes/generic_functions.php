<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if(!function_exists('get_option_tsa_token')) {
    //Returns a Plugin Specific configuration value
    function get_option_tsa_token($code) {
        $options = get_option('tsa_token_options');
        if(!isset($options[$code])) return false;
        return $options[$code];		
    }
}


if(!function_exists('tsa_phone_number_exists')) {
    //Check Phone Number exists in WP database. Returns User ID if found.
    function tsa_phone_number_exists($phone_number){
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'phone_number',
                    'value' => $phone_number,
                    'compare' => '='
                )
            )
        );

        $member_arr = get_users($args);
        if ($member_arr && $member_arr[0])
            return $member_arr[0]->ID;
        else
            return 0;
    }
}

if(!function_exists('tsa_generate_token_access')) {
    //Generate a Access Token
    function tsa_generate_token_access($user_id) {
        return md5($user_id.date("YmdHis").rand(0,1000));
    }
}

if(!function_exists('tsa_generate_username')) {
    //Generate a Name base on $text (phone number)
    function tsa_generate_username($text) {
        return $text;
    }
}

if(!function_exists('debug')) {
    //Used for DEBUG during development.
    function debug($mixed) {
        echo "<pre>";
        print_r($mixed);
        echo "</pre>";
    }
}

if(!function_exists('obfuscate_phone_number')) {
    //Obfuscate Phone number to keep privacy safe
    function obfuscate_phone_number($phone_number) {
        return substr($phone_number,0,strlen($phone_number)-4)."xxxx";
    }
}

if(!function_exists('validate_phone_number')) {
    //Validate PHone Number
    function validate_phone_number($phone_number) {

        if(preg_match("#^(\d+)$#",$phone_number)){
            return true;
        }
        return false;
    }
}

if(!function_exists('validate_token_access')) {
    //Validate Token. Cannot be empty
    function validate_token_access($token) {
        if($token != ''){
            return true;
        }
        return false;
    }
}

if(!function_exists('terms_and_conditions_text')) {
    //Retrieve Terms and Conditions text, including link or not if url is defined.
    function terms_and_conditions_text($url = null) {
        if($url != '') {
            $terms_and_conditions_text = sprintf(__("I agree to the <a href='%s' target='_blank'>Terms and Conditions</a>.", 'login-with-token'),$url);
        }
        else {
            $terms_and_conditions_text = __("I agree to the Terms and Conditions.", 'login-with-token');
        }
        return $terms_and_conditions_text;
    }
}