<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if(!class_exists('provider_generic')) {
	class provider_generic extends provider
	{
		
		protected function configure($args = null) {
			return true;
		}
		

		function send_sms($token, $phone_number, $return_type="response"){
			//Replacing {token} with $token
			$text = get_option_tsa_token('sms_text');
			$text = str_replace('{token}',$token, $text);

			$body = get_option_tsa_token('gateway_parameters_json');
			$body = str_replace("{text_sms}", $text, $body);
			$body = str_replace("{phone_number}", $phone_number, $body);

			$body = json_decode($body, true);
			
			if(!$body) {
				return __('Error: JSON not well formatted.');
			}

			$url = esc_url_raw(get_option_tsa_token('gateway_url'));
			if(!$url || $url == '' ) {
				return __('Error: Gateway URL not set.');
			}
			
			$args = array(
				'method'      => get_option_tsa_token('gateway_api_method'),
				'timeout'     => 20,
				'redirection' => 5,
				'httpversion' => '1.1',
				'user-agent'  => 'WP: HTTP API; '. home_url(),
				'blocking'    => true,
				'headers'     => array(),
				'cookies'     => array(),
				'body'        => $body,
				'compress'    => false,
				'decompress'  => true,
				'sslverify'   => true,
				'stream'      => false,
				'filename'    => null
			);
			$response = wp_remote_request( $url, $args );

			$this->response_obj = $response;

			$this->response_array['headers'] = wp_remote_retrieve_headers( $response );
			$this->response_array['code']    = wp_remote_retrieve_response_code( $response );
			$this->response_array['message'] = wp_remote_retrieve_response_message( $response );
			$this->response_array['body']    = wp_remote_retrieve_body( $response );
			
			return $this->response_obj;

		}

		function validate_response() {
			
			$body = $this->response_array['body'];
			
			if(is_string($body) && strpos($body, "failed") !== false) { //MoveSMS exception. Body is a string.
				return __('Gateway Body:'.$body);
			}
			else if($this->response_array['code'] == "200") {
				return true;
			}
			else {
				return __('Return Code: '.$this->response_array['code']);
			}
		}

	}
}