<?php
/*
	Plugin Name: Appointments + SMS
	Plugin URI: http://plugins.mindblaze.net
	Description: Enable SMS integration for Appointments+ using Twilio and Clockwork
	Version: 0.2.5
	Author: <code>Hassan Asad</code>
	Author URI: http://plugins.mindblaze.net
	License: MBT.
*/


function load_appointments_sms() {

if ( class_exists('Appointments') ) {

require ( plugin_dir_path( __FILE__ ) . 'lib/functions.php' );

class Appointments_SMS extends Appointments {

	var $slug 				= null;
	var $plugin_file		= null;
	var $folder_slug		= null;
	var $plugin_url 		= null;
	var $plugin_path 		= null;
	var $MBT_KEY			= null;
	var $api_url 			= null;

	var $enable_sms 				= 0;
	var $enable_sms_notification 	= 0;
	var $enable_sms_confirmation 	= 0;
	var $enable_sms_reminder 		= 0;
	var $sms_service_provider 		= null;
	var $default_code 				= null;
	
	//var $sms_notification_text 		= null;
	var $sms_confirmation_text 		= null;
	var $sms_reminder_text 			= null;

	var $lang_domain 				= null;

	private $extender	= array();
	
	function __construct() {

		$this->slug 		= strtolower(get_class($this));
		$this->plugin_file	= basename(__FILE__);
		$this->folder_slug	= basename(__DIR__);
		$this->lang_domain 	= strtolower($this->slug);

		$this->api_url 		= 'http://plugins.mindblaze.net/api/';

		$this->plugin_url 	= plugins_url( '', __FILE__ );
		$this->plugin_path 	= plugin_dir_path( __FILE__ );

		$this->time_format			= get_option('time_format');
		$this->date_format			= get_option('date_format');
		$this->datetime_format		= $this->date_format . " " . $this->time_format;

		$this->enable_sms 				= get_site_option( 'appointments_sms_enabled', 0 );
		$this->enable_sms_notification 	= get_site_option( 'appointments_sms_enabled_notification', 0 );
		$this->enable_sms_confirmation 	= get_site_option( 'appointments_sms_enabled_confirmation', 0 );
		$this->enable_sms_reminder 		= get_site_option( 'appointments_sms_enabled_reminder', 0 );
		$this->sms_service_provider		= get_site_option( 'appointments_sms_service_provider', 'twilio' );
		$this->default_code				= get_site_option( 'appointments_sms_default_code' );
		
		$this->sms_notification_text	= get_site_option( 'appointments_sms_notification_text' );
		$this->sms_confirmation_text	= get_site_option( 'appointments_sms_confirmation_text' );
		$this->sms_reminder_text		= get_site_option( 'appointments_sms_reminder_text' );

		add_action( 'admin_menu', array(&$this, '_admin_init') );
		add_action( 'admin_notices', array($this,'show_admin_messages') );
		
		/* load translations on plugin loaded hook ( so in constructor ) */
		$this->_init_();

		/* MBT KEY SYSTEM */
		$this->mbt_key_init( $this->slug, $this->slug . '_mbt_key', $this);
		
		//register_activation_hook( __FILE__, array( $this, '_activate') );

		/* Check for updates of plugin from server */
		// Uncomment to check for instant updates
		//set_site_transient('update_plugins', null);
		
		// Take over the update check
		add_filter('pre_set_site_transient_update_plugins', array(&$this, 'check_for_plugin_updates'));

		// Take over the Plugin info screen
		add_filter('plugins_api', array(&$this, 'plugin_update_api_call'), 1000, 3);
		
		
		do_action( 'appointments_sms_init' );

		if ( $this->enable_sms ) {
			
			/* Appointment+ Hooks */
			//add_action( 'app_notification_sent', array(&$this, 'compose_message'), 10, 3 );
			//add_action( 'app_confirmation_sent', array(&$this, 'compose_message'), 10, 3 );
			//add_action( 'app-appointment-inline_edit-after_save', array(&$this, 'test_compose_message'), 10, 2 );
			
			//app_change_status {$stat, $app_id}
			//app_new_appointment { $app_id }
			//app_bulk_status_change { $_POST['app'] }
			//add_action( 'app_bulk_status_change',  array(&$this, 'bulk_status_change' ) );
			//add_action( 'app_new_appointment', array(&$this, 'test_compose_message'), 10, 2 );
			//add_action( 'app_change_status', array(&$this, 'test_compose_message'), 10, 2 );
			//add_action( 'sms_app_confirmation_sent', array(&$this, 'test_compose_message'), 11 );
			//do_action('app-appointment-inline_edit-after_save', ($update_result ? $app_id : $wpdb->insert_id), $data);
			
			/* Send SMS upon status update from wordpress backend Even if email checkbox is unchecked */
			if ( $this->enable_sms_confirmation ) {
				add_action('app-appointment-inline_edit-after_save', array($this, 'check_appointment_update'), 10, 2);
			}

			if ( $this->enable_sms_notification ) {
				add_filter( 'app_notification_message', array($this, 'compose_notification_message'), 10, 3);
			}

			if ( $this->enable_sms_confirmation ) {
				add_filter( 'app_confirmation_message', array($this, 'compose_confirmation_message'), 10, 3);
			}

			if ( $this->enable_sms_reminder ) {
				add_filter( 'app_reminder_message', array($this, 'compose_reminder_message'), 10, 3);
			}

			//Filter: app_confirmation_message { replaced_body, row, app_id}
			//Filter: app_notification_message { body, row, app_id}
			//Filter: app_reminder_message { body, row, app_id}
			do_action( 'appointments_sms_enabled' );
		}
		
	}


	function check_appointment_update( $app_id, $data ) {
		$status	= $data['status'];
		$phone	= $data['phone'];
		switch ($status) {
			case 'confirmed':
				$this->compose_confirmation_message( null, null, $app_id, $phone );
			break;
		}

	}

	/* ::BEGIN:: Extension system for Class function separation */
	function addExtender($obj){
		$this->extenders[] = $obj;
	}


	function __call($name, $params){
		foreach($this->extenders as $extender){
			//do reflection to see if extender has this method with this argument count
			if (method_exists($extender, $name)){
				return call_user_func_array(array($extender, $name), $params);
			}
		}
	}
	/* ::END:: Extension system for Class function separation */

	/* -- MBT KEY FUNCTIONS [START] -- */
	function mbt_key_init( $plugin_slug, $menu_slug, $class_obj ) {
		require_once( plugin_dir_path( __FILE__ ) . 'lib/mbt.php' );
		$MBT_KEY  = new MBT_KEY( $plugin_slug, $menu_slug, $class_obj );
		$this->MBT_KEY = $MBT_KEY;
		add_action( 'admin_init', array( &$this, 'mbt_key_plugin_data') );
	}

	
	function mbt_key_plugin_data() {
		$MBT_KEY = $this->MBT_KEY;
		$MBT_KEY->set_plugin_data( __FILE__ );
	}
	/* -- MBT KEY FUNCTIONS [START] -- */


	function _init_() {
		load_plugin_textdomain( $this->lang_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	}


	function show_admin_messages() {
		$error_flag = 0;
		$error_message 	= null;
		if ( $this->enable_sms ) {
			switch ( $this->sms_service_provider ) {
				default:
				case 'twilio':
					if ( !class_exists('MBT_Twilio') ) {
						$error_message 	= __('MBT Twilio Plugin not found!', $this->lang_domain);
						$error_flag 	= 1;
					}
				break;

				case 'clockwork':
					if ( !class_exists('MBT_Clockwork') ) {
						$error_message 	= __('MBT Clockwork Plugin not found!', $this->lang_domain);
						$error_flag 	= 1;
					}
				break;
			}

			if ( $error_flag ) {
				echo '<div id="setting-error-settings_updated" class="error settings-error"><p><strong>' . $error_message . '</strong></p></div>';
				return;
			}
		}
	}


	/* Function overiding from Appointment+ plugin */
	function get_app( $app_id ) {
		$app_table = $this->get_app_table_name();
		global $wpdb;
		if ( !$app_id )
			return false;
		$app = wp_cache_get( 'app_'. $app_id );
		if ( false === $app ) {
			/* modified $this->db TO $wpdb-> */
			$app = $wpdb->get_row( "SELECT * FROM " . $app_table . " WHERE ID=".$app_id." " );
			wp_cache_set( 'app_'. $app_id, $app );
		}
		return $app;
	}


	function get_phone_number( $app_id ) {
		$app = $this->get_app( $app_id );
		if ( $app->user ) {
			$phone = get_user_meta( $app->user, 'app_phone', true );
		} 
		if ( empty($phone) || !$phone ) {
			$phone = $app->phone;
		}
		$phone = apply_filters( 'appointments_sms_phone_number_before_default_code', $phone );
		if ( $phone ) {
			if ( substr($phone, 0, 2) != '00' && substr($phone, 0, 1) != '+' ) {
				/* Apply default code if number starts without + or 00 */
				if ( substr($phone, 0, 1) == '0' ) {
					/* if first character is 0, trim it */
					/* 0321 converted to 321 For internation standard */
					$phone = substr($phone, 1);
				}
				$phone = $this->default_code . $phone;
			}
		}
		$phone = str_replace(' ', '', $phone);
		$phone = apply_filters( 'appointments_sms_phone_number', $phone );
		return $phone;
	}

	function get_app_table_name() {
		$appointments = new Appointments();
		$app_table = $appointments->app_table;
		return $app_table;
	}

	function process_replacements( $app_id, $text ) {
		global $wpdb;
		$app_table = $this->get_app_table_name();
		$r = $wpdb->get_row( "SELECT * FROM " . $app_table . " WHERE ID=".$app_id." " );
		if ( $r != null ) {
			$text = $this->_replace( 
				$text, 
				$r->name, 
				$this->get_service_name( $r->service), 
				$this->get_worker_name( $r->worker), 
				$r->start, 
				$r->price,
				$this->get_deposit($r->price), 
				$r->phone, 
				$r->note, 
				$r->address, 
				$r->email, 
				$r->city
			);
			$text = apply_filters( 'process_replacements', $text );
			return $text;
		}
	}


	/* function overriding from appointment+ */
	function _replace( $text, $user, $service, $worker, $datetime, $price, $deposit, $phone, $note, $address, $email, $city ) {
		/*
		return str_replace(
					array( "SITE_NAME", "CLIENT", "SERVICE_PROVIDER", "SERVICE", "DATE_TIME", "PRICE", "DEPOSIT", "PHONE", "NOTE", "ADDRESS", "EMAIL", "CITY" ),
					array( wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), $user, $worker, $service, mysql2date( $this->datetime_format, $datetime ), $price, $deposit, $phone, $note, $address, $email, $city ),
					$text
				);
		*/
		$balance = !empty($price) && !empty($deposit)
			? (float)$price - (float)$deposit
			: (!empty($price) ? $price : 0.0)
		;
		$replacement = array(
			'SITE_NAME' => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
			'CLIENT' => $user,
			'SERVICE_PROVIDER' => $worker,
			'SERVICE' => $service,
			'DATE_TIME' => mysql2date($this->datetime_format, $datetime),
			'PRICE' => $price,
			'DEPOSIT' => $deposit,
			'BALANCE' => $balance,
			'PHONE' => $phone,
			'NOTE' => $note,
			'ADDRESS' => $address,
			'EMAIL' => $email,
			'CITY' => $city,
		);
		foreach($replacement as $macro => $repl) {
			$text = preg_replace('/' . preg_quote($macro, '/') . '/U', $repl, $text);
		}
		$text = apply_filters( 'appointments_sms_replace', $text );
		return $text;
	}


	function compose_notification_message( $email_replaced_body, $db_row, $app_id ) {
		/* Send the notification generated by Appointments+ after stripping HTML */
		$phone = $this->get_phone_number( $app_id );

		$sms_body = strip_tags($email_replaced_body);
		$sms_body = apply_filters( 'appointments_sms_notification_message',  $sms_body);
		$this->push_SMS( $sms_body, $phone, $app_id );

		/* have to return as this is a filter */
		return $email_replaced_body;
	}


	function compose_reminder_message( $email_replaced_body, $db_row, $app_id ) {
		/* Send the notification generated by Appointments+ after stripping HTML */
		$phone = $this->get_phone_number( $app_id );

		$sms_body = strip_tags($email_replaced_body);
		
		if ( !empty($this->sms_reminder_text) ) {
			$sms_body = $this->process_replacements( $app_id, $this->sms_reminder_text );
		}

		$sms_body = apply_filters( 'appointments_sms_reminder_message',  $sms_body);
		$this->push_SMS( $sms_body, $phone, $app_id );

		/* have to return as this is a filter */
		return $email_replaced_body;
	}


	function compose_confirmation_message( $email_replaced_body, $db_row, $app_id, $phone = null ) {
		/* For inline save where phone number can be different */
		if ( $phone == null ) {
			$phone = $this->get_phone_number( $app_id );
		}
		
		$sms_body = $this->process_replacements( $app_id, $this->sms_confirmation_text );
		$sms_body = apply_filters( 'appointments_sms_confirmation_message',  $sms_body);
		$this->push_SMS( $sms_body, $phone, $app_id );

		/* have to return as this is a filter */
		return $email_replaced_body;
	}

	/*
		function push_SMS( $message, $to_number, $app_id )
		exists in /lib/functions.php
	*/


	function _admin_init() {
		add_menu_page( __('Appointments SMS', $this->lang_domain), __('Appointments SMS', $this->lang_domain), 'manage_options', $this->slug, array(&$this, '_settings') );
		//add_submenu_page( __('Appointments SMS', $this->lang_domain), __('Appointments SMS', $this->lang_domain), 'manage_options', $this->slug, array(&$this, '_settings') );
		add_submenu_page( $this->slug, 'MBT Key', 'MBT Key', 'manage_options', $this->slug .'_mbt_key', array($this->MBT_KEY, 'key_form') ); 
	}


	function _settings() {
		
		?>
		<?php
		$update_flag 	= 0;
		$message 		= null;
		$errors 		= array();
		if ( isset($_POST['save']) ) { 
			$update_flag = 1;
			$message = __('Settings Saved!', $this->lang_domain);
			
			$enable_sms					= ( isset($_POST['enable_sms']) ) ? 1 : 0;
			$enable_sms_notification	= ( isset($_POST['enable_sms_notification']) ) ? 1 : 0;
			$enable_sms_confirmation	= ( isset($_POST['enable_sms_confirmation']) ) ? 1 : 0;
			$enable_sms_reminder		= ( isset($_POST['enable_sms_reminder']) ) ? 1 : 0;
			$sms_service_provider		= $_POST['sms_service_provider'];
			$default_code				= $_POST['default_code'];
			
			$sms_notification_text		= $_POST['sms_notification_text'];
			$sms_confirmation_text		= $_POST['sms_confirmation_text'];
			$sms_reminder_text			= $_POST['sms_reminder_text'];

			update_site_option('appointments_sms_enabled', $enable_sms);
			update_site_option('appointments_sms_enabled_notification', $enable_sms_notification);
			update_site_option('appointments_sms_enabled_confirmation', $enable_sms_confirmation);
			update_site_option('appointments_sms_enabled_reminder', $enable_sms_reminder);
			update_site_option('appointments_sms_service_provider', $sms_service_provider);
			update_site_option('appointments_sms_default_code', $default_code);
			
			//update_site_option('appointments_sms_notification_text', $sms_notification_text);
			update_site_option('appointments_sms_confirmation_text', $sms_confirmation_text);
			update_site_option('appointments_sms_reminder_text', $sms_reminder_text);

			do_action( 'appointments_sms_settings_save', $_POST );
		}

		if ( isset($_POST['test_sms']) ) { 
			$test_number	= $_POST['test_number'];
			$sms_message	= __('This is a Test SMS coming from ', $this->lang_domain) . site_url();
			$return = $this->send_SMS( $sms_message, $test_number );
			if ( $return == 1 ) {
				echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>SMS Sent to [' . $test_number . '] successfully!</strong></p></div>';
			} else {
				echo '<div id="setting-error-settings_updated" class="error"><p><strong>Error: ' . $return . '</strong></p></div>';	
			}
		}

		$sms_confirmation_text_default 	= "Hi CLIENT,\n\nWe are pleased to confirm your appointment.\nDate and time: DATE_TIME\n\nThank you";
		$sms_reminder_text_default 		= "Hi CLIENT,\n\nWe would like to remind you your appointment.\nDate and time: DATE_TIME\n\nThank you";

		$enable_sms 				= get_site_option( 'appointments_sms_enabled', 0 );
		$enable_sms_notification 	= get_site_option( 'appointments_sms_enabled_notification', 0 );
		$enable_sms_confirmation 	= get_site_option( 'appointments_sms_enabled_confirmation', 0 );
		$enable_sms_reminder 		= get_site_option( 'appointments_sms_enabled_reminder', 0 );
		$sms_service_provider 		= get_site_option( 'appointments_sms_service_provider', 'twilio' );
		$default_code 				= get_site_option( 'appointments_sms_default_code' );
		
		//$sms_notification_text 		= get_site_option( 'appointments_sms_notification_text' );
		$sms_confirmation_text 		= get_site_option( 'appointments_sms_confirmation_text', $sms_confirmation_text_default );
		$sms_reminder_text 			= get_site_option( 'appointments_sms_reminder_text', $sms_reminder_text_default );
		do_action( 'appointments_sms_settings' );
		?>

		<div class="wrap sms_plugin">
			<?php screen_icon( 'options-general' ); ?>
			<h2><?php _e('Appointments SMS Settings', $this->lang_domain);?></h2>
			<?php
			if ( $update_flag == 1 ) {
				echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>' . $message . '</strong></p></div>';
			}
			?>
			<style>
			.sms_plugin label {
				width: 180px;
				display: inline-block;
			}
			.sms_plugin input[type="text"] {
				width: 200px;
			}

			.sms_plugin hr {
				height: 0px;
				background: none;
				border: 0px;
				border-top: 1px solid #eee;
			}

			.sms_plugin textarea {
				width: 300px;
				height: 100px;
				resize: none;
			}

			.right {
				text-align: right;
			}
			</style>
			<script>

				jQuery(document).ready( function() {
					var limit_textareas = new Array('#sms_notification_text', '#sms_confirmation_text', '#sms_reminder_text');
					for( var i = 0; i < limit_textareas.length; i++ ) {
						character_limit(jQuery(limit_textareas[i]));
						jQuery(limit_textareas[i]).keyup( function() { character_limit(jQuery(this)); } );
					}
				});

				function character_limit( field ) {
					try {
						var max = 160;
						var len = jQuery(field).val().length;
						var count_field_id = jQuery(field).attr('id') + '_count';
						var count_field = jQuery('#' + count_field_id);
						if (len >= max) {
							field.val(field.val().substr(0, max));
							count_field.html(' you have reached the limit');
						} else {
							var char = max - len;
							count_field.html(char + ' characters left');
						}
					} catch (e) {
						console.log(e);
					}
				}
			</script>
			<form id="media-single-form" class="media-upload-form" action="" method="post">
				<table style="width: 500px;">
					<tr>
						<td colspan="2"><p class="submit"><input type="submit" value="Save" class="button-primary" name="save" /></p></td>
					</tr>

					<tr>
						<td colspan="2"><h2><?php _e('SMS Settings', $this->lang_domain); ?></h2></td>
					</tr>

					<tr>
						<td><label for="enable_sms"><span class="alignleft"><?php _e('Enable SMS', $this->lang_domain); ?></span></label></td>
						<td><input type="checkbox" aria-required="true" <?php echo checked( $enable_sms, 1 ); ?> value="1" name="enable_sms" id="enable_sms" /></td>
					</tr>
					<tr>
						<td><label for="enable_sms_notification"><span class="alignleft"><?php _e('Send SMS for Notifications', $this->lang_domain); ?></span></label></td>
						<td><input type="checkbox" aria-required="true" <?php echo checked( $enable_sms_notification, 1 ); ?> value="1" name="enable_sms_notification" id="enable_sms_notification" /></td>
					</tr>
					<tr>
						<td><label for="enable_sms_confirmation"><span class="alignleft"><?php _e('Send SMS for Confirmations', $this->lang_domain); ?></span></label></td>
						<td><input type="checkbox" aria-required="true" <?php echo checked( $enable_sms_confirmation, 1 ); ?> value="1" name="enable_sms_confirmation" id="enable_sms_confirmation" /></td>
					</tr>						
					<tr>
						<td><label for="enable_sms_reminder"><span class="alignleft"><?php _e('Send SMS for Reminders', $this->lang_domain); ?></span></label></td>
						<td><input type="checkbox" aria-required="true" <?php echo checked( $enable_sms_reminder, 1 ); ?> value="1" name="enable_sms_reminder" id="enable_sms_reminder" /></td>
					</tr>
					<tr>
						<td><label for="sms_service_provider"><span class="alignleft"><?php _e('SMS Service Provider', $this->lang_domain); ?></span></label></td>
						<td>
							<select name="sms_service_provider" id="sms_service_provider">
								<option value="twilio" <?php selected( $sms_service_provider, 'twilio' ); ?>>Twilio</option>
								<option value="clockwork" <?php selected( $sms_service_provider, 'clockwork' ); ?>>Clockwork</option>
							</select>
						</td>
					</tr>
					<tr>
						<td><label for="default_code"><span class="alignleft"><?php _e('Default Code', $this->lang_domain); ?></span></label></td>
						<td>
							<input type="text" aria-required="true" value="<?php echo $default_code; ?>" placeholder="<?php _e('Default Code Example +45', $this->lang_domain); ?>" name="default_code" id="default_code" />
							<br />
							<small class="description"><?php _e('Applied when a user enters a phone starting without `+` or `00`', $this->lang_domain); ?></small>
						</td>
					</tr>
					<?php do_action( 'appointments_sms_settings_basic' ); ?>
					<tr>
						<td colspan="2"><hr /></td>
					</tr>
					<tr>
						<td colspan="2"><h2><?php _e('Messages', $this->lang_domain); ?></h2><br /><?php _e('You may use these reserved keywords: ', $this->lang_domain); ?><br /><i>SITE_NAME, CLIENT, SERVICE, SERVICE_PROVIDER, DATE_TIME, PRICE, DEPOSIT, BALANCE, PHONE, NOTE, ADDRESS, CITY, EMAIL (Client's email)</i><br /><br /><small style="color: #CA4F4F;"><?php _e('Please note if the replacement words make the SMS longer than 160 characters, multiple SMS will be sent for each Text body', $this->lang_domain); ?></small></td>
					</tr>
					<!--
					<tr>
						<td><label for="sms_notification_text"><span class="alignleft">Notification Text Message</span></label></td>
						<td><textarea id="sms_notification_text" name="sms_notification_text"><?php echo $sms_notification_text; ?></textarea><div id="sms_notification_text_count" class="right"></div></td>
					</tr>
					-->
					<tr>
						<td><label for="sms_confirmation_text"><span class="alignleft"><?php _e('Confirmation Text Message', $this->lang_domain); ?></span></label></td>
						<td><textarea id="sms_confirmation_text" name="sms_confirmation_text"><?php echo $sms_confirmation_text; ?></textarea><div id="sms_confirmation_text_count" class="right"></div></td>
					</tr>
					<tr>
						<td><label for="sms_reminder_text"><span class="alignleft"><?php _e('Reminder Text Message', $this->lang_domain); ?></span></label></td>
						<td><textarea id="sms_reminder_text" name="sms_reminder_text"><?php echo $sms_reminder_text; ?></textarea><div id="sms_reminder_text_count" class="right"></div></td>
					</tr>
					<?php do_action( 'appointments_sms_settings_messages' ); ?>
					<tr>
						<td colspan="2"><p class="submit"><input type="submit" value="Save" class="button-primary" name="save" /></p></td>
					</tr>
				</table>
			</form>
		</div> 
		<?php
	}


	function check_for_plugin_updates($checked_data) {
		global $wp_version;
		
		//Comment out these two lines during testing.
		if (empty($checked_data->checked))
			return $checked_data;
		
		$plugin_slug	= strtolower($this->slug);
		$plugin_file	= $this->plugin_file;
		$plugin_folder	= $this->folder_slug;
		
		$args = @array(
			'slug' => $plugin_slug,
			'version' => $checked_data->checked[$plugin_folder .'/'. $plugin_file],
		);

		$request_string = array(
				'body' => array(
					'action' => 'basic_check', 
					'request' => serialize($args),
					'api-key' => md5(get_bloginfo('url'))
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
			);
		
		// Start checking for an update
		$raw_response = wp_remote_post($this->api_url, $request_string);
		
		if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200))
			$response = unserialize($raw_response['body']);
		
		if (is_object($response) && !empty($response)) // Feed the update data into WP updater
			$checked_data->response[$plugin_folder .'/'. $plugin_file ] = $response;
		
		return $checked_data;
	}


	
	function plugin_update_api_call($def, $action, $args) {
		global $wp_version;
		
		$plugin_slug	= strtolower($this->slug);
		$plugin_file	= $this->plugin_file;
		$plugin_folder	= $this->folder_slug;

		if (!isset($args->slug) || ($args->slug != $plugin_slug))
			return false;
		
		// Get the current version
		$plugin_info = get_site_transient('update_plugins');
		$current_version = @$plugin_info->checked[$plugin_folder .'/'. $plugin_file];
		$args->version = $current_version;
		
		$request_string = array(
				'body' => array(
					'action' => $action, 
					'request' => serialize($args),
					'api-key' => md5(get_bloginfo('url'))
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
			);
		
		$request = wp_remote_post($this->api_url, $request_string);
		
		
		if (is_wp_error($request)) {
			$res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
		} else {
			$res = unserialize($request['body']);
			
			if ($res === false)
				$res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
		}
		
		return $res;
	}
}

$Appointments_SMS = new Appointments_SMS();
$Appointments_SMS->addExtender(new Appointments_SMS_Functions($Appointments_SMS) );

} /* Class exists check */

} /* Plugin loaded */

add_action( 'plugins_loaded', 'load_appointments_sms' );
