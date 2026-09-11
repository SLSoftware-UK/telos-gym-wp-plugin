<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Settings_Page {

	const OPTION_NAME = 'telos_gym_schedule_options';
	const PAGE_SLUG    = 'telos-gym-schedule';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function add_menu() {
		add_options_page(
			__( 'TelosGym Schedule', 'telos-gym-schedule' ),
			__( 'TelosGym Schedule', 'telos-gym-schedule' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::PAGE_SLUG, self::OPTION_NAME, array(
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => array(),
		) );
	}

	/**
	 * The embed-key field shows a masked placeholder once a key is saved
	 * (matches the "shown once, masked after" convention on telos-gym's own
	 * Settings → External embeds card) and is submitted blank when the
	 * admin hasn't retyped it — so a blank submission here means "keep the
	 * existing key," not "clear it."
	 */
	public static function sanitize( $input ) {
		$existing = get_option( self::OPTION_NAME, array() );
		$output   = array();

		$new_key = isset( $input['embed_key'] ) ? trim( $input['embed_key'] ) : '';
		if ( '' !== $new_key ) {
			$output['embed_key'] = preg_match( '/^[A-Za-z0-9_-]{20,64}$/', $new_key )
				? $new_key
				: '';
			if ( '' === $output['embed_key'] ) {
				add_settings_error( self::OPTION_NAME, 'invalid_key', __( 'That doesn\'t look like a valid TelosGym embed key — it was not saved.', 'telos-gym-schedule' ) );
				$output['embed_key'] = $existing['embed_key'] ?? '';
			}
		} else {
			$output['embed_key'] = $existing['embed_key'] ?? '';
		}

		$output['accent_color'] = ! empty( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$output['font_stack']   = ( isset( $input['font_stack'] ) && 'system' === $input['font_stack'] ) ? 'system' : 'theme';

		return $output;
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'telos-gym-schedule-admin', TELOS_GYM_SCHEDULE_URL . 'assets/admin.css', array(), TELOS_GYM_SCHEDULE_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'telos-gym-schedule-admin', TELOS_GYM_SCHEDULE_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), TELOS_GYM_SCHEDULE_VERSION, true );
		wp_localize_script( 'telos-gym-schedule-admin', 'telosGymScheduleAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'telos_gym_schedule_admin' ),
			'apiBase' => TELOS_GYM_API_BASE,
			'strings' => array(
				'testing'  => __( 'Testing…', 'telos-gym-schedule' ),
				'ok'       => __( 'Connected', 'telos-gym-schedule' ),
				'fail'     => __( 'Invalid key or origin mismatch', 'telos-gym-schedule' ),
				'noKey'    => __( 'Enter a key first', 'telos-gym-schedule' ),
			),
		) );
	}

	public static function render_page() {
		$options    = get_option( self::OPTION_NAME, array() );
		$has_key    = ! empty( $options['embed_key'] );
		$accent     = $options['accent_color'] ?? '';
		$font_stack = $options['font_stack'] ?? 'theme';
		?>
		<div class="wrap telos-gym-schedule-settings">
			<h1><?php esc_html_e( 'TelosGym Schedule', 'telos-gym-schedule' ); ?></h1>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE_SLUG ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="telos_gym_schedule_embed_key"><?php esc_html_e( 'Embed key', 'telos-gym-schedule' ); ?></label></th>
						<td>
							<input
								type="password"
								id="telos_gym_schedule_embed_key"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embed_key]"
								class="regular-text"
								autocomplete="off"
								placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'telos-gym-schedule' ) : esc_attr__( 'Paste the key from TelosGym Settings → External embeds', 'telos-gym-schedule' ); ?>"
							/>
							<p class="submit-inline">
								<button type="button" class="button" id="telos-gym-schedule-test-connection"><?php esc_html_e( 'Test connection', 'telos-gym-schedule' ); ?></button>
								<span id="telos-gym-schedule-test-result" class="telos-gym-schedule-test-result" aria-live="polite"></span>
							</p>
							<p class="description">
								<?php esc_html_e( 'Get this from your TelosGym staff dashboard: Settings → External embeds → Create key. Use this site\'s URL as the allowed origin.', 'telos-gym-schedule' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="telos_gym_schedule_accent_color"><?php esc_html_e( 'Accent colour', 'telos-gym-schedule' ); ?></label></th>
						<td>
							<input
								type="text"
								id="telos_gym_schedule_accent_color"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[accent_color]"
								class="telos-gym-schedule-color-picker"
								value="<?php echo esc_attr( $accent ); ?>"
								data-default-color="#2563eb"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="telos_gym_schedule_font_stack"><?php esc_html_e( 'Font', 'telos-gym-schedule' ); ?></label></th>
						<td>
							<select id="telos_gym_schedule_font_stack" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[font_stack]">
								<option value="theme" <?php selected( $font_stack, 'theme' ); ?>><?php esc_html_e( 'Match theme', 'telos-gym-schedule' ); ?></option>
								<option value="system" <?php selected( $font_stack, 'system' ); ?>><?php esc_html_e( 'System font', 'telos-gym-schedule' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'How to show your schedule', 'telos-gym-schedule' ); ?></h2>
			<p>
				<?php esc_html_e( 'Classic editor or page builder — paste this shortcode:', 'telos-gym-schedule' ); ?>
				<code>[telos_schedule]</code>
			</p>
			<p>
				<?php esc_html_e( 'Block editor — search the block inserter for "TelosGym Schedule".', 'telos-gym-schedule' ); ?>
			</p>
		</div>
		<?php
	}
}
