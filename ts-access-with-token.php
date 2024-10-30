<?php
/*
Plugin Name:  Login with Token
Description:  Login using SMS with URL including a randomly generated token.
Plugin URI:   https://github.com/tschweizer79/ts-access-with-token
Author:       Tomas Schweizer (tschweizer@gmail.com)
Version:      1.0
Text Domain:  login-with-token
Domain Path:  /languages
License:      GPL v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.txt
*/

//TODO: Limit Bots to try logins. Define how-to

// exit if file is called directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . "includes/generic_functions.php";

require_once plugin_dir_path( __FILE__ ) . "includes/class.provider.php"; 			//Abstraction
require_once plugin_dir_path( __FILE__ ) . "includes/class.provider_generic.php";

if(!class_exists('tsa_token')) {
	class tsa_token {

		function __construct() {
			
			$this->initialize_variables();

			wp_enqueue_script('jquery');															// Adding jQuery Dependency
			
			add_action( 'plugins_loaded', array(&$this, 'load_textdomain') );						// Textdomain
			
			add_action('admin_menu', array(&$this, 'add_toplevel_menu')); 							// Plugin Menu
			add_action('admin_menu', array(&$this,'add_sublevel_menu'));							// Plugin Submenus
			add_action('admin_init', array(&$this,'register_settings')); 							// Plugin Settings Page
			add_filter('manage_users_columns', array(&$this,'modify_user_table'));					// New User Columns
			add_filter('manage_users_custom_column', array(&$this,'modify_user_table_row'), 10, 3);	// Display User Columns in Users Page
			add_action('show_user_profile', array(&$this,'extra_user_profile_fields') );			// Display new fields in Show User Profile
			add_action('edit_user_profile', array(&$this,'extra_user_profile_fields') );			// Display new fields in Edit User Profile
			add_action('personal_options_update', array(&$this,'save_extra_user_profile_fields') );	// Save new fields on Personal options update
			add_action('edit_user_profile_update', array(&$this,'save_extra_user_profile_fields') );// Save new fields on Edit User Profile
			add_filter('init',array(&$this,'auto_login_using_token'));								// Auto-login if token is set on GET. TODO: check best hook: init or wp_loaded

			add_action( 'wp_ajax_send_token_ajax_php', array(&$this,'send_token_ajax_php') );		// PHP executed via Ajax from login page
			add_action( 'wp_ajax_nopriv_send_token_ajax_php', array(&$this,'send_token_ajax_php') );// PHP executed via Ajax from login page
			
			add_shortcode( 'tsa_token_login', array(&$this,'tsa_login_page') );						// Create shortcode function for login page (page with [tsa_token_login])
			add_action( 'wp_enqueue_scripts', array(&$this,'enqueue_js_scripts') );					// JS Files + Variables to be used in Login Form

			add_filter( 'cron_schedules', array(&$this,'wpcron_intervals') );						// Adding WP Cron intervals
			
			register_activation_hook( __FILE__, array(&$this,'wpcron_activation') );				// Hook to activate Cron Job
			register_deactivation_hook( __FILE__, array(&$this,'wpcron_deactivation') );			// Hook to deactivate Cron Job
			
			add_action( 'token_expiration_check', array(&$this,'wpcron_token_expiration_check') );	// Adding event in WPCron
			add_action( 'admin_notices', array(&$this,'admin_notices') );

			//Test Gateway
			add_action( 'admin_enqueue_scripts', array(&$this,'ajax_admin_enqueue_scripts') );
			add_action( 'wp_ajax_admin_hook', array(&$this,'ajax_admin_handler') );


		}

		//Initiate variables, required this way due to i18n
		function initialize_variables() {

			$this->plugin_slug = "tsa_token";
			$this->plugin_name = "Login with Token";
			$this->default_options = array(
				'token_expiration' 	=> '15',
				'provider_name'		=> 'generic',
				'sms_text'			=> sprintf(__('Click here: %s?tsa_token={token} to access the plataform','login-with-token'), get_home_url()),
				'redirect_url'		=> get_home_url(),
				'use_plugin_css'		=> 'enable',
				'log_events'		=> 'enable'
			);

			$this->api_methods = array(
				'POST'	=> 'POST',
				'GET'	=> 'GET'
			);

			$this->token_expiration_options = array(
				'-1'	=> __('Never Expires','login-with-token'),
				'0'		=> __('Expires immediately after usage','login-with-token'),
				'1'		=> __('1 day','login-with-token'),
				'2'		=> __('2 days','login-with-token'),
				'5'		=> __('5 days','login-with-token'),
				'10'	=> __('10 days','login-with-token'),
				'15'	=> __('15 days','login-with-token')
			);

			$this->providers = array(
				//'vonage'	=> 'Vonage (https://nexmo.com)',
				//'movesms'	=> 'MoveSMS (https://www.movesms.co.ke/) - Under testing',
				'generic'	=> __("Other (Customized Gateway)",'login-with-token')
			);
		}	

		// add top-level administrative menu
		function add_toplevel_menu() {
			// 	add_menu_page( string   $page_title, string   $menu_title, string   $capability, string   $menu_slug, callable $function = '', string   $icon_url = '', int      $position = null ) 
			add_menu_page(
				$this->plugin_name.' - '.__('Settings','login-with-token'),
				"$this->plugin_name",
					'manage_options',
				'menu_plugin',
				array(&$this, 'display_settings_page'),
				'dashicons-tickets-alt',
				null
			);
			
		}

		//Sublevel menus
		function add_sublevel_menu() {
		
			/*
			add_submenu_page(
				string   $parent_slug,
				string   $page_title,
				string   $menu_title,
				string   $capability,
				string   $menu_slug,
				callable $function = ''
			);
			*/		
			
			add_submenu_page(
				'menu_plugin',
				$this->plugin_name.' - '.__('Settings','login-with-token'),
				__('Settings','login-with-token'),
				'manage_options',
				'menu_plugin',
				array(&$this, 'display_settings_page'),
			);

			add_submenu_page(
				'menu_plugin',
				$this->plugin_name.' - '.__('Instructions','login-with-token'),
				__('Instructions','login-with-token'),
				'manage_options',
				'submenu_settings',
				array(&$this, 'display_instructions_page'),
			);

			add_submenu_page(
				'menu_plugin',
				$this->plugin_name.' - '.__('Test Gateway','login-with-token'),
				__('Test Gateway','login-with-token'),
				'manage_options',
				'test_gateway',
				array(&$this,'ajax_admin_display_settings_page'),
			);

			add_submenu_page(
				'menu_plugin',
				$this->plugin_name.' - '.__('Log','login-with-token'),
				__('Log','login-with-token'),
				'manage_options',
				'sms_log',
				array(&$this,'display_log'),
			);
			
		}

		//Settinges Page
		function display_settings_page() {
		
			// check if user is allowed access
			if ( ! current_user_can( 'manage_options' ) ) return;
			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<form action="options.php" method="post">
					<?php
					// output security fields
					settings_fields( 'tsa_token_options' );
					// output setting sections
					do_settings_sections( 'tsa_token_sections' );
					// submit button
					submit_button();
					?>
				</form>
			</div>
			<?php
		}

		//Instructions page
		function display_instructions_page() {
		
			// check if user is allowed access
			if ( ! current_user_can( 'manage_options' ) ) return;
			$menu_settings = menu_page_url( 'menu_plugin', false );
			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				
				<h2>1. <?php _e('Configure Plugin settings', 'login-with-token'); ?></h2>
				<p><?php printf(__("<a href='%s'>Click here</a> to go to settings configuration page (or go to submenu Settings).", 'login-with-token'), $menu_settings); ?></p>
		
				<h2>2. <?php _e('Create Login Page & Shortcode instructions', 'login-with-token')?></h2>
				<p>
					<?php _e('Create a page, name it "Login" and add the following shortcode: [tsa_token_login]. This will be your login page.', 'login-with-token'); ?><br>
					<?php _e('The shortcode can have the following attributes (none of them are mandatory):', 'login-with-token') ?>
				</p>
				<ol>
					<li><b>terms_and_conditions (true/false)</b>: <?php _e('Display the confirmation checkbox for Terms and Conditions. Default is "true".', 'login-with-token') ?></li>
					<li><b>phone_number_placeholder (string)</b>: <?php _e('Placeholder used inside phone number field in the Login form. Default is "Your phone number".', 'login-with-token') ?></li>
					<li><b>phone_pattern_regex (string)</b>: <?php _e('Regular Expression for validation in the phone number. Example: \d{8} (for 8-digit number). Default is empty.', 'login-with-token') ?></li>
					<li><b>phone_maxlength (number)</b>: <?php _e('Maximum length for phone number field (empty means no limit). Default is empty.', 'login-with-token') ?></li>
					<li><b>terms_and_conditions_url (string)</b>: <?php _e('URL for Terms and Conditions. If this attribute isset, checkbox for Terms and Conditions will display a link for this URL. efault is empty.', 'login-with-token') ?></li>
					<li><b>animation (true/false)</b>: <?php _e('Show instructions animation (GIF). Default is "false".', 'login-with-token') ?></li>
					<li><b>animation_width_px (integer)</b>: <?php _e('Animation Width (pixel). Default is 1010px.', 'login-with-token') ?></li>
					<li><b>animation_height_px (integer)</b>: <?php _e('Animation Height (pixel). Default is 610px.', 'login-with-token') ?></li>
					<li><b>title (string) </b>: <?php _e('Title of login form. Default is "Enter your Phone Number".', 'login-with-token') ?></li>
					<li><b>label (string) </b>: <?php _e('Text shown below the title of login page. Default is "Phone Number".', 'login-with-token') ?></li>
					<li><b>text_button (string) </b>: <?php _e('Text displayed in button of Login Form. Default is "Send SMS".', 'login-with-token') ?></li>
					<li><b>hide_country_code (true/false) </b>: <?php _e('If set to true, it will hide the coutnry code field. Only works if a single country code is used. Default is "false".', 'login-with-token') ?></li>
				</ol>	
				<h2>3. <?php _e('Gateway Configuration - Notes', 'login-with-token') ?></h2>
				<p><?php _e('Parameters JSON - Example:', 'login-with-token') ?></p>
				<pre>
	{
		"sender":"Your Company Name",
		"apiKey":"user@example.com",
		"username":"example_user_name",
		"another_var1":"Variable 1 value",
		"text_message": "{text_sms}",
		"phone": "{phone_number}"
	}
				</pre>
				<p><?php _e('Change variables name and values accordingly to Gateway Provider instructions.', 'login-with-token') ?><br>
				<?php _e('<b>{text_sms}</b> will be replaced by SMS Text, as defined in this Settings page.', 'login-with-token') ?><br>
				<?php _e('<b>{phone_number}</b> will be replaced by the user Phone Number.', 'login-with-token') ?></p>
			</div>
			<?php
			$this->wpcron_token_expiration_check();
		}

		//Register Plugin settings
		function register_settings() {
			//	register_setting( string   $option_group, string   $option_name, callable $sanitize_callback);
			register_setting( 
				'tsa_token_options', 
				'tsa_token_options', 
				'callback_validate_options' 
			); 

			// add_settings_section( string   $id, string   $title, callable $callback, string   $page);
			add_settings_section( 
				'tsa_token_section_general', 
				__('Customize Plugin','login-with-token'), 
				array(&$this,'callback_section_general'), 
				'tsa_token_sections'
			);

			add_settings_section( 
				'tsa_token_section_gateway', 
				__('Customize Gateway','login-with-token'), 
				array(&$this,'callback_section_gateway'), 
				'tsa_token_sections'
			);

			// add_settings_field(string   $id,string   $title,callable $callback,string   $page,string   $section = 'default',array    $args = []);
			add_settings_field(
				'log_events',
				__('Log events','login-with-token'),
				array(&$this,'callback_field_radio'),
				'tsa_token_sections',
				'tsa_token_section_general',
				[ 'id' => 'log_events', 'label' => __('Log events into log file.')]
			);

			add_settings_field(
				'use_plugin_css',
				__('Use Plugin CSS','login-with-token'),
				array(&$this,'callback_field_radio'),
				'tsa_token_sections',
				'tsa_token_section_general',
				[ 'id' => 'use_plugin_css', 'label' => __('Use Plugin CSS in Login page, if enable.')]
			);

			add_settings_field(
				'token_expiration',
				__('Token Expiration','login-with-token'),
				array(&$this,'callback_field_select'),
				'tsa_token_sections',
				'tsa_token_section_general',
				[ 'id' => 'token_expiration', 'label' => __('Define how token expires. Apply to all users.','login-with-token'), 'options'=>$this->token_expiration_options ]
			);

			add_settings_field(
				'redirect_url',
				__('Redirect URL','login-with-token'),
				array(&$this,'callback_field_text'),
				'tsa_token_sections',
				'tsa_token_section_general',
				[ 'id' => 'redirect_url', 'label' => sprintf(__('Redirect to this URL from login page, if user is already logged and after login using Access Token. Example: %s','login-with-token'), home_url()) ]
			);

			add_settings_field(
				'phone_country_code',
				__('Country Code','login-with-token'),
				array(&$this,'callback_field_text'),
				'tsa_token_sections',
				'tsa_token_section_general',
				[ 'id' => 'phone_country_code', 'label' => __('Country Code for phone numbers, will be prepended to numbers used in login page. You can use multiple codes separated by comma.','login-with-token') ]
			);

			add_settings_field(
				'sms_text',
				__('SMS Message','login-with-token'),
				array(&$this,'callback_field_textarea'),
				'tsa_token_sections',
				'tsa_token_section_general',
				[ 'id' => 'sms_text', 'label' => sprintf(__('Template for SMS. {token} will be replaced by token automatically. <br> This message will NOT be translated automatically.<br>URL to access is %s?tsa_token={token}','login-with-token'),home_url()) ]
			);

			add_settings_field(
				'provider',
				__('Gateway/Provider Name','login-with-token'),
				array(&$this,'callback_field_select'),
				'tsa_token_sections',
				'tsa_token_section_gateway',
				[ 'id' => 'provider_name', 'label' => __('Gateway or provider used to send SMS.','login-with-token'), 'options'=>$this->providers ]
			);

			add_settings_field(
				'gateway_url',
				__('Gateway URL','login-with-token'),
				array(&$this,'callback_field_text'),
				'tsa_token_sections',
				'tsa_token_section_gateway',
				[ 'id' => 'gateway_url', 'label' => __('Gateway URL used by API.','login-with-token')]
			);

			add_settings_field(
				'gateway_api_method',
				__('Method','login-with-token'),
				array(&$this,'callback_field_select'),
				'tsa_token_sections',
				'tsa_token_section_gateway',
				[ 'id' => 'gateway_api_method', 'label' => __('Method used by API.','login-with-token'), 'options'=>$this->api_methods ]
			);

			add_settings_field(
				'gateway_parameters_json',
				__('Parameters JSON','login-with-token'),
				array(&$this,'callback_field_textarea'),
				'tsa_token_sections',
				'tsa_token_section_gateway',
				[ 'id' => 'gateway_parameters_json', 'label' => __('Parameters in JSON format. {text_sms} and {phone_number} placeholders can be used.<br>See instructions for more details.','login-with-token')]
			);

			

			

			

		}

		// callback: general section
		function callback_section_general() {
			echo '<p>'.__('General settings used by the plugin.','login-with-token').'</p>';
		}

		// callback: provider and sms section
		function callback_section_gateway() {
			echo '<p>'.__('Specific settings necessary to configure the Gateway.','login-with-token').'</p>';
		}

		// callback: provider and sms section
		function callback_section_provider() {
			echo '<p>'.__('Specific settings necessary to configure the SMS details and Gateway.','login-with-token').'</p>';
		}
		
		// callback: text field
		function callback_field_text( $args ) {
			$options = get_option( 'tsa_token_options', $this->default_options );
			
			$id    = isset( $args['id'] )    ? $args['id']    : '';
			$label = isset( $args['label'] ) ? $args['label'] : '';
			
			$value = isset( $options[$id] ) ? sanitize_text_field( $options[$id] ) : '';
			
			echo '<input id="tsa_token_options_'. esc_html($id).'" name="tsa_token_options['. esc_html($id) .']" type="text" size="40" value="'. esc_html($value) .'"><br />';
			echo '<label for="tsa_token_options_'. esc_html($id) .'">'. esc_html($label) .'</label>';
		}

		// callback: radio field
		function callback_field_radio( $args ) {
			$options = get_option( 'tsa_token_options', $this->default_options );
			
			$id    = isset( $args['id'] )    ? $args['id']    : '';
			$label = isset( $args['label'] ) ? $args['label'] : '';
			
			$selected_option = isset( $options[$id] ) ? sanitize_text_field( $options[$id] ) : '';
			
			$radio_options = array(
				'enable'  => __('Enabled','login-with-token'),
				'disable' => __('Disabled','login-with-token')
			);
			
			foreach ( $radio_options as $value => $label ) {
				$checked = checked( $selected_option === $value, true, false );
				
				echo '<label><input name="tsa_token_options['. esc_html($id) .']" type="radio" value="'. esc_html($value) .'"'. esc_html($checked) .'> ';
				echo '<span>'. esc_html($label) .'</span></label><br />';
			}
		}

		// callback: textarea field
		function callback_field_textarea( $args ) {
			$options = get_option( 'tsa_token_options', $this->default_options );
			
			$id    = isset( $args['id'] )    ? $args['id']    : '';
			$label = isset( $args['label'] ) ? $args['label'] : '';
			
			$allowed_tags = wp_kses_allowed_html( 'post' );
			
			$value = isset( $options[$id] ) ? wp_kses( stripslashes_deep( $options[$id] ), $allowed_tags ) : '';
			
			echo '<textarea id="tsa_token_options_'. esc_html($id) .'" name="tsa_token_options['. esc_html($id) .']" rows="5" cols="50">'. esc_html($value) .'</textarea><br />';
			echo '<label for="tsa_token_options_'. esc_html($id) .'">'. esc_html($label) .'</label>';
		}

		// callback: checkbox field
		function callback_field_checkbox( $args ) {
			$options = get_option( 'tsa_token_options', $this->default_options );
			
			$id    = isset( $args['id'] )    ? $args['id']    : '';
			$label = isset( $args['label'] ) ? $args['label'] : '';
			
			$checked = isset( $options[$id] ) ? checked( $options[$id], 1, false ) : '';
			
			echo '<input id="tsa_token_options_'. esc_html($id) .'" name="tsa_token_options['. esc_html($id) .']" type="checkbox" value="1"'. esc_html($checked) .'> ';
			echo '<label for="tsa_token_options_'. esc_html($id) .'">'. esc_html($label) .'</label>';
		}

		// callback: select field
		function callback_field_select( $args ) {
			$options = get_option( 'tsa_token_options', $this->default_options );

			$id    		= isset( $args['id'] )      ? $args['id']    : '';
			$label		= isset( $args['label'] )   ? $args['label'] : '';
			$select_options 	= isset( $args['options'] ) ? $args['options'] : [];
			
			$selected_option = isset( $options[$id] ) ? sanitize_text_field( $options[$id] ) : '';

			echo '<select id="tsa_token_options_'. esc_html($id) .'" name="tsa_token_options['. esc_html($id) .']">';
			
			foreach ( $select_options as $value => $option ) {
				
				$selected = selected( $selected_option == $value, true, false );
				
				echo '<option value="'. esc_html($value) .'"'. esc_html($selected) .'>'. esc_html($option) .'</option>';
				
			}
			echo '</select> <label for="tsa_token_options_'. esc_html($id) .'">'. esc_html($label) .'</label>';
		}

		// callback: validate options
		function callback_validate_options( $input ) {
			
			if ( ! isset( $input['use_plugin_css'] ) ) {
				$input['use_plugin_css'] = null;
			}
			if ( isset( $input['use_plugin_css'] ) ) {
				$input['use_plugin_css'] = sanitize_text_field( $input['use_plugin_css'] );
			}

			if ( ! isset( $input['log_events'] ) ) {
				$input['log_events'] = null;
			}
			if ( isset( $input['log_events'] ) ) {
				$input['log_events'] = sanitize_text_field( $input['log_events'] );
			}


			if ( ! isset( $input['token_expiration'] ) ) {
				$input['token_expiration'] = null;
			}
			$select_options = $this->token_expiration_options;
			if ( ! array_key_exists( $input['token_expiration'], $select_options ) ) {
				$input['token_expiration'] = null;
			}

			if ( ! isset( $input['provider_name'] ) ) {
				$input['provider_name'] = null;
			}
			$select_options = $this->providers;
			if ( ! array_key_exists( $input['provider_name'], $select_options ) ) {
				$input['provider_name'] = null;
			}

			if ( isset( $input['sms_from'] ) ) {
				$input['sms_from'] = sanitize_text_field( $input['sms_from'] );
			}

			if ( isset( $input['sms_text'] ) ) {
				$input['sms_text'] = sanitize_text_field( $input['sms_text'] );
			}

			
			if ( isset( $input['redirect_url'] ) ) {
				$input['redirect_url'] = esc_url( $input['redirect_url'] );
			}

			if ( isset( $input['phone_country_code'] ) ) {
				$input['phone_country_code'] = sanitize_text_field( $input['phone_country_code'] );
			}

			if ( isset( $input['gateway_url'] ) ) {
				$input['gateway_url'] = sanitize_text_field( $input['gateway_url'] );
			}

			if ( ! isset( $input['gateway_api_method'] ) ) {
				$input['gateway_api_method'] = null;
			}
			$select_options = $this->providers;
			if ( ! array_key_exists( $input['gateway_api_method'], $select_options ) ) {
				$input['gateway_api_method'] = null;
			}
			
			if ( isset( $input['gateway_parameters_json'] ) ) {
				$input['gateway_parameters_json'] = sanitize_text_field( $input['gateway_parameters_json'] );
			}
			
			return $input;
		}

		// Textdomain / Internationalization
		function load_textdomain() {
			load_plugin_textdomain( 'login-with-token', false, plugin_dir_path( __FILE__ ) . 'languages/' );
		}

		//Change user table adding new columns
		function modify_user_table($column){

			$column['tsa_token_access'] = __('Access Token', 'login-with-token');
			$column['tsa_token_create_date'] = __('Access Token Create Date', 'login-with-token');
			$column['phone_number'] = __('Phone Number', 'login-with-token');
			return $column;

		}

		//Adding new columns as rows in User Table
		function modify_user_table_row($val, $column_name, $user_id){
			$udata = get_userdata($user_id);
			switch ($column_name) {
				case 'phone_number' :
					return get_the_author_meta('phone_number', $user_id);
				case 'tsa_token_access' :
					return get_the_author_meta('tsa_token_access', $user_id);
				case 'tsa_token_create_date' :
					return get_the_author_meta('tsa_token_create_date', $user_id);
				default:
			}
			return $val;
		}

		//Adding new User fields in Edit User Profile Page
		function extra_user_profile_fields( $user ) { 
			//Only Administrators can change fields
			if(current_user_can('administrator')) {
			?>
			<h3><?php _e("Extra profile information",'login-with-token'); ?></h3>
		
			<table class="form-table">
			<tr>
				<th><label for="tsa_token_access"><?php _e("Access Token",'login-with-token'); ?></label></th>
				<td>
					<input type="text" name="tsa_token_access" id="tsa_token_access" value="<?php echo esc_attr( get_the_author_meta( 'tsa_token_access', $user->ID ) ); ?>" class="regular-text" /><br />
					<span class="description"><?php _e("Please enter Access Token.", 'login-with-token'); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="phone_number"><?php _e("Phone Number",'login-with-token'); ?></label></th>
				<td>
					<input type="number" name="phone_number" id="phone_number" value="<?php echo esc_attr( get_the_author_meta( 'phone_number', $user->ID ) ); ?>" class="regular-text" /><br />
					<span class="description"><?php _e("Please enter Phone Number (only digits including country code).", 'login-with-token'); ?></span>
				</td>
			</tr>
			</table>
			<?php 
			}
		}

		//Saving new User fields by submiting Save Profile
		function save_extra_user_profile_fields( $user_id ) {
			if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
				return;
			}
			
			if ( !current_user_can( 'edit_user', $user_id ) ) { 
				return false; 
			}
			$phone_number = sanitize_text_field($_POST['phone_number']);
			if(validate_phone_number($phone_number)){
				update_user_meta( $user_id, 'phone_number', $phone_number );
			}
			$tsa_token_access = sanitize_text_field($_POST['tsa_token_access']);
			if(validate_token_access($tsa_token_access)){
				update_user_meta( $user_id, 'tsa_token_access', $tsa_token_access );
			}
		}

		//Login using TOKEN
		function auto_login_using_token() {
			//Token must be set
			$tsa_token = @sanitize_text_field($_GET['tsa_token']);
			if(!$tsa_token != '') {
				return false;
			}	
			$user = $this->get_user_by_token_access($tsa_token);
			if($user) {
				if(get_option_tsa_token('token_expiration') == 0) {
					$this->expires_token($user->ID);
				}
				$this->log("Log in using token for ".$user->ID);
				wp_clear_auth_cookie();
				wp_set_current_user ( $user->ID );
				wp_set_auth_cookie  ( $user->ID );
				wp_redirect( get_option_tsa_token('redirect_url') );
			}
			else {
				wp_logout();
			}
		}

		//Retrieve User by Token Access
		function get_user_by_token_access($tsa_token) {
			if(empty($tsa_token)) {
				return false;		
			}
			
			if(!empty($tsa_token) && $tsa_token != "") {
				//Looking for user with given token
				$user = get_users(array(
					'meta_key' => 'tsa_token_access',
					'meta_value' => $tsa_token
				));
				if(isset($user[0])) {
					return $user[0];
				}
			}
			return false;
		}

		//Expires a token for the user
		function expires_token($user_id) {
			update_user_meta($user_id, "tsa_token_access",null);
			update_user_meta($user_id, "tsa_token_create_date",null);
			$this->log("Token expired for user  ".$user_id);
		}
		
		
		//Send SMS
		function send_sms($token, $phone) {
			$provider = $this->get_provider();
			$reponse_array = $provider->send_sms($token,$phone, "array");
			return $provider->validate_response($reponse_array);
		}

		//Return Gateway provider object
		function get_provider(){
			$provider_name = get_option_tsa_token('provider_name');
			$class_name = "provider_".$provider_name;
			if(class_exists($class_name)){
				$provider = new $class_name();
			}
			else {
				$provider = new provider_generic();
			}
			return $provider;
		}

		//[tsa_token_login] shortcode
		function tsa_login_page( $atts ){
			
			ob_start();
			
			if (!is_user_logged_in() && ! current_user_can( 'manage_options' )) {
				if(get_option_tsa_token('use_plugin_css') == "enable") {
					wp_enqueue_style('tsa_token', plugin_dir_url( __FILE__).'public/css/tsa_login.css', array(), false);
				}
				$shortcode_attributes = shortcode_atts( array(
					'terms_and_conditions' => true,
					'phone_number_placeholder' => "Your phone number",
					'show_country_code' => false,
					'phone_pattern_regex' => null,
					'phone_maxlength' => null,
					'terms_and_conditions_url' => null,
					'animation' => false,
					'animation_width_px' => 1010,
					'animation_height_px' => 610,
					'title' => "Enter your Phone Number",
					'label' => "Phone Number",
					'text_button' => "Send SMS",
					'hide_country_code' => false,
				), $atts );

				$country_codes = explode(",",str_replace(" ","",get_option_tsa_token("phone_country_code")));
				?>
				<div class="wrap">
					<div class="tsa_form_main_div">
						<?php if($shortcode_attributes['animation']){ 
							?>
							<div class="tsa_animation_div">
							<img src="<?php echo plugin_dir_url( __FILE__ );?>/public/animation.gif" alt="Animation" style="width:<?php echo esc_html($shortcode_attributes['animation_width_px']);?>px;height:<?php echo esc_html($shortcode_attributes['animation_height_px']);?>px;">
							</div>
							<?php
						}
						?>
						<form id="tsa_form_login_id" class="" action="login" style="" method="post">
							<div class="tsa_form_title"><h2><?php echo esc_html($shortcode_attributes['title']); ?><h2></div>
							<?php wp_nonce_field('ajax-login-nonce', 'security'); ?>
							<input name="action" value="ajax-login-nonce" type="hidden">
							<div class="tsa_form_div_response" style="display:none">
								<p><span id="tsa_form_div_response_text"></span></p>
							</div>
							<div class="tsa_form_div">

								<label class="tsa_form_labels"><?php echo esc_html($shortcode_attributes['label']); ?></label>
									
								<div class="tsa_form_input">
									
										<?php if(count($country_codes) == "1") { ?>
											<input type="hidden" name = "tsa_country_code" id ="tsa_country_code" value="<?php echo esc_html($country_codes[0]); ?>">
											
											
												<?php if(!$shortcode_attributes['hide_country_code']) { ?>
													<div class="tsa_form_input_country_code_div" style="">
														<input type="text" disabled class="required tsa_form_input_country_code" value="+<?php echo esc_html($country_codes[0]); ?>">
													</div>
												<?php } ?>
												
										<?php } else { ?>

											<div class="tsa_form_input_country_code_div" style="">
												<select class="required tsa_form_input_country_code" name = "tsa_country_code" id ="tsa_country_code">
												">
												<?php foreach($country_codes as $country_code) {
													?>
													<option value="<?php echo esc_html($country_code); ?>">+<?php echo esc_html($country_code); ?></option>
													<?php
												}
												?>
												</select>
											</div>
										<?php
										}
									
									?>	
									<div class="tsa_form_input_country_phone_number_div">
										<i class="fa fa-phone icon"></i>
										<meta name="viewport" content="width=device-width, height=device-height,  initial-scale=1.0, user-scalable=no;user-scalable=0;"/>
										<input type="text" class="required tsa_form_input_phone_number" required 
										<?php if($shortcode_attributes['phone_pattern_regex']){ 
											?>
											pattern="<?php echo esc_html($shortcode_attributes['phone_pattern_regex']); ?>"
											<?php
										}
										?>
											maxlength="<?php echo esc_html($shortcode_attributes['phone_maxlength']); ?>" name="tsa_phone_number" id ="tsa_phone_number" placeholder="<?php echo esc_html($shortcode_attributes['phone_number_placeholder']); ?>">
									</div>
								</div>	
								<?php 
									if($shortcode_attributes['terms_and_conditions'] == "true") { 
										$url = $shortcode_attributes['terms_and_conditions_url'];
										
										?>
										<div class="accept_terms_and_conditions">
											<input class="required" type="checkbox" name="accept_terms_check" required>
											<span class="accept_terms_and_conditions_text"><?php echo terms_and_conditions_text($url); ?></span>
										</div>
										<?php 
									} 
								?>
								<div class="tsa_form_button_div">
									<button class="tsa_form_submit_button" type="submit" id='ajax_submit_button'><?php echo esc_html($shortcode_attributes['text_button']); ?></button>
								</div>
							</div>
							
						</form>
					</div>							
				</div>
				<?php
			}
			return ob_get_clean();

		}
		
		// Enqueue JS file and Localize PHP variables
		function enqueue_js_scripts() {

			wp_enqueue_script( 'tsa_token', plugin_dir_url( __FILE__ ) . 'public/js/ajax.js', array('jquery'), null, true );
			wp_localize_script( 'tsa_token', 'tsa_token',
				array( 
					'ajaxurl' => admin_url( 'admin-ajax.php' )
				)
			);
		}

		//PHP Ajax function to send SMS
		function send_token_ajax_php() {
			$username = $phone_number = sanitize_text_field($_POST['tsa_phone_number']);
			if(empty($username)){
				_e("Please provide Phone Number.", 'login-with-token');
				wp_die(); 
			}
			$phone_number = str_replace(" ","",$phone_number);
			$user_id = tsa_phone_number_exists($phone_number);
			
			if(!$user_id) {
				$info = array();
				$info['user_login'] = tsa_generate_username($phone_number);
				$info['user_nicename'] = $info['nickname'] = $info['display_name'] = tsa_generate_username($phone_number);
				$user_id = wp_insert_user($info);
				$this->log("User created for ".obfuscate_phone_number($phone_number));
			}
			if (is_wp_error($user_id)) {
				_e("Erro creating new user.", 'login-with-token');
				wp_die(); 
			}
			else {
				update_user_meta($user_id, 'phone_number', $phone_number);
			}
			
			$token = tsa_generate_token_access($user_id);
			update_user_meta($user_id, "tsa_token_access", $token);
			update_user_meta($user_id, "tsa_token_create_date",date("Y-m-d H:i:s"));

			$return_sms = $this->send_sms($token, $phone_number);
			if($return_sms === true) {
				$this->log("Token sent to user ".$user_id);
				echo __("SMS sent successfully. Please click on the link provided in the SMS.", 'login-with-token');
			}
			else {
				$this->log("ERROR sending token to user ".$user_id);
				echo __("Error sending message. ", 'login-with-token').esc_html($return_sms);
			}
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		// Add Cron Intervals
		function wpcron_intervals( $schedules ) {

			// one minute
			$one_minute = array(
							'interval' => 60,
							'display' => __('One Minute', 'login-with-token')
						);
			$schedules[ 'one_minute' ] = $one_minute;

			return $schedules;
		}

		// Cron Activation
		function wpcron_activation() {
			if ( ! wp_next_scheduled( 'token_expiration_check' ) ) {
				wp_schedule_event( time(), 'one_minute',  'token_expiration_check' );
			}
		}

		// Cron Function to expire tokens
		function wpcron_token_expiration_check() {

			if ( ! defined( 'DOING_CRON' ) ) return;
		
			//Check if Expiration Flag is set
			$token_expiration_days = get_option_tsa_token('token_expiration');
			if($token_expiration_days <= 0 || $token_expiration_days == '' || !is_numeric($token_expiration_days)) {
				return;
			}
			$limit_for_expiration = date("Y-m-d H:i:s", strtotime("-$token_expiration_days days"));
			
			//Look for users with Tokens
			$args = array(
				'meta_query' => array(
					array(
						'key' => 'tsa_token_access',
						'value' => null,
						'compare' => '!='
					)
				)
			);
			$users_with_token = get_users($args);

			//Reset Token if expired
			foreach($users_with_token as $user) {
				$token_create_date = get_user_meta( $user->ID, 'tsa_token_create_date')[0];
				if($token_create_date == '' || $token_create_date < $limit_for_expiration) {
					$this->expires_token($user->ID);
				}
			}
		
		}

		// Cron Deactivation
		function wpcron_deactivation() {
			wp_clear_scheduled_hook( 'token_expiration_check' );
		}

		//Enabling Admin Notices
		function admin_notices() {
			settings_errors();
		}

		// Test Gateway - Enqueue Scripts
		function ajax_admin_enqueue_scripts( $hook ) {

			// define script url
			$script_url = plugin_dir_url( __FILE__ ) . 'public/js/test_gateway.js';
			// enqueue script
			wp_enqueue_script( 'ajax-admin', $script_url, array( 'jquery' ) );
		
			// create nonce
			$nonce = wp_create_nonce( 'ajax_admin' );
		
			// define script
			$script = array( 'nonce' => $nonce );
		
			// localize script
			wp_localize_script( 'ajax-admin', 'ajax_admin', $script );
		
		}

		// Test Gateway - Ajax Handler
		function ajax_admin_handler() {

			// check nonce
			check_ajax_referer( 'ajax_admin', 'nonce' );
		
			// check user
			if ( ! current_user_can( 'manage_options' ) ) return;
		
			// define the url
			$phone_number = sanitize_text_field($_POST['phone_number']);
			$phone_number = !empty($phone_number) ? $phone_number : false;
			
			$token = sanitize_text_field($_POST['token']);
			$token = !empty($token) ? $token : false;
			
			if(!$phone_number || !$token) {
				echo __('Phone Number and Token are required.','login-with-token');
				wp_die();
			}
			else {
				// make head request
				$provider = $this->get_provider();
				$response = $provider->send_sms($token, $phone_number );
				$this->log("Gateway test to ".obfuscate_phone_number($phone_number));
				// get response headers
				$headers = wp_remote_retrieve_headers( $response );
				$code    = wp_remote_retrieve_response_code( $response );
				$message = wp_remote_retrieve_response_message( $response );
				$body    = wp_remote_retrieve_body( $response );
			
			}
			
		
			// output the results
		
			echo '<pre>';
		
			if ( ! empty( $headers ) ) {
		
				echo __('Code: ','login-with-token') . "\n";
				print_r( $code );
				echo "\n\n";
				echo __('Response: ','login-with-token') . "\n";
				print_r( $headers );
				echo "\n\n";
				echo __('Message: ','login-with-token') . "\n";
				print_r( $message );
				echo "\n\n";
				echo __('Body: ','login-with-token') . "\n";
				print_r( $body );
			} else {
		
				echo __('No results. Please review settings and try again.','login-with-token');
		
			}
		
			echo '</pre>';
		
		
		
			// end processing
			wp_die();
		
		}

		// Test Gateway - Form
		function ajax_admin_display_form() {

			?>
		
			<style>
				.ajax-form-wrap { width: 100%; overflow: hidden; margin: 0 0 20px 0; }
				.ajax-form { float: left; width: 400px; }
				pre {
					width: 95%; overflow: auto; margin: 20px 0; padding: 20px;
					color: #fff; background-color: #424242;
				}
			</style>
		
			<h3><?php _e("Test Gateway",'login-with-token') ?></h3>
		
			<div class="ajax-form-wrap">
		
				<form class="ajax-form" method="post">
					<p><label for="phone_number"><?php _e('Phone Number (including country code):','login-with-token'); ?></label></p>
					<p><input id="phone_number" name="phone_number" type="text" class="regular-text"></p>
					<p><label for="token"><?php _e('Token (just for testing purposes):','login-with-token'); ?></label></p>
					<p><input id="token" name="token" type="text" class="regular-text"></p>
					<input type="submit" value="<?php _e('Submit','login-with-token'); ?>" class="button button-primary">
				</form>
			</div>
		
			<div class="ajax-response"></div>
		
		<?php
		
		}

		// Test Gateway - display the plugin settings page
		function ajax_admin_display_settings_page() {

			// check if user is allowed access
			if ( ! current_user_can( 'manage_options' ) ) return;

			?>

			<div class="wrap">

				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

				<?php echo esc_html($this->ajax_admin_display_form()); ?>

			</div>

		<?php

		}

		// Display Log file
		function display_log() {

			// check if user is allowed access
			if ( ! current_user_can( 'manage_options' ) ) return;

			?>

			<div class="wrap">

				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

				<?php
				$log_enable = get_option_tsa_token("log_events");
				echo "<p>".__(sprintf("Log Events are <strong>%s</strong>. To change, go to Settings Menu.",esc_html($log_enable)),"'login-with-token'")."</p>";
				$upload_dir = wp_upload_dir();
				$upload_dir = $upload_dir['basedir'];
				$file_content = file_get_contents($upload_dir.'/token.log');
				echo "<pre>".esc_html($file_content)."</pre>";
				?>

			</div>

		<?php

		}

		// Log events in a file
		function log( $entry, $mode = 'a', $file = 'token' , $file_month = false) { 
			
			if(get_option_tsa_token('log_events') != "enable") return;

			// Get WordPress uploads directory.
			$upload_dir = wp_upload_dir();
			
			$upload_dir = $upload_dir['basedir'];
			
			// If the entry is array, json_encode.
			if ( is_array( $entry ) ) { 
				$entry = json_encode( $entry ); 
			}
			// Write the log file.
			if($file_month) {
				$file  = $upload_dir . '/' . $file . "-".date("Y-m").'.log';
			}
			else {
				$file  = $upload_dir . '/' . $file . '.log';
			}
			
			$file  = fopen( $file, $mode );
			$bytes = fwrite( $file, current_time( 'mysql' ) . "::" . $entry . "\n" ); 
			fclose( $file ); 
			return $bytes;
		}

	}

	$tsa_token = new tsa_token();
}