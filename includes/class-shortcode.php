<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Shortcode {

	public static function init() {
		add_shortcode( 'telos_schedule', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array( 'days' => 14 ),
			$atts,
			'telos_schedule'
		);

		return Telos_Gym_Schedule_Render::render( $atts['days'] );
	}
}
