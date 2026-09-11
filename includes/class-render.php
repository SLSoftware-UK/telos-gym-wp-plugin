<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Render {

	/**
	 * Builds the <div data-telos-schedule> embed markup and enqueues the
	 * widget script. Shared by the shortcode and the block so there is
	 * exactly one place that builds this markup.
	 */
	public static function render( $days = 14, $accent_override = '' ) {
		$options = get_option( 'telos_gym_schedule_options', array() );
		$key     = isset( $options['embed_key'] ) ? $options['embed_key'] : '';

		if ( empty( $key ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p>' . esc_html__( 'TelosGym Schedule: no embed key configured yet. Add one under Settings → TelosGym Schedule.', 'telos-gym-schedule' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_script(
			'telos-gym-schedule-widget',
			trailingslashit( TELOS_GYM_API_BASE ) . 'static/public-schedule-widget.js',
			array(),
			null,
			true
		);

		$days = absint( $days );
		if ( $days < 1 || $days > 30 ) {
			$days = 14;
		}

		$style  = '';
		$accent = sanitize_hex_color( $accent_override ? $accent_override : ( $options['accent_color'] ?? '' ) );
		if ( $accent ) {
			$style .= '--telos-accent:' . $accent . ';';
		}
		if ( ! empty( $options['font_stack'] ) && 'theme' !== $options['font_stack'] ) {
			$style .= '--telos-font:' . self::font_stack_value( $options['font_stack'] ) . ';';
		}

		return sprintf(
			'<div data-telos-schedule data-key="%1$s" data-days="%2$d"%3$s></div>',
			esc_attr( $key ),
			$days,
			$style ? ' style="' . esc_attr( $style ) . '"' : ''
		);
	}

	/**
	 * 'theme' omits the --telos-font override entirely so the widget's
	 * built-in default inherits page CSS. 'system' is the only other
	 * choice in v1 (Settings page dropdown) and writes an explicit stack.
	 */
	public static function font_stack_value( $choice ) {
		if ( 'system' === $choice ) {
			return '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif';
		}
		return '';
	}
}
