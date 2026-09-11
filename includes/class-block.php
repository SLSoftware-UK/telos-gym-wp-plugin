<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Block {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$build_dir = TELOS_GYM_SCHEDULE_DIR . 'build';
		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $build_dir, array(
			'render_callback' => array( __CLASS__, 'render' ),
		) );
	}

	public static function render( $attributes ) {
		$days   = isset( $attributes['days'] ) ? $attributes['days'] : 14;
		$accent = isset( $attributes['accentColor'] ) ? $attributes['accentColor'] : '';
		return Telos_Gym_Schedule_Render::render( $days, $accent );
	}
}
