<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin AJAX wrapper — NOT a proxy for the actual validation request (that
 * request is a direct fetch() from admin.js straight to telos-gym's public
 * API, same as the live embed). This endpoint only exists so admin.js can
 * ask for the *currently saved* key when the settings-page field is
 * showing its masked placeholder rather than a freshly typed value.
 */
class Telos_Gym_Schedule_Key_Validator {

	public static function init() {
		add_action( 'wp_ajax_telos_gym_schedule_get_key_for_test', array( __CLASS__, 'get_key_for_test' ) );
	}

	public static function get_key_for_test() {
		check_ajax_referer( 'telos_gym_schedule_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'telos-gym-schedule' ) ), 403 );
		}

		$options = get_option( 'telos_gym_schedule_options', array() );
		$key     = $options['embed_key'] ?? '';

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'No key saved yet.', 'telos-gym-schedule' ) ), 404 );
		}

		wp_send_json_success( array( 'key' => $key ) );
	}
}
