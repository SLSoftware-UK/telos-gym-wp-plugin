<?php
/**
 * Plugin Name:       TelosGym Schedule
 * Plugin URI:        https://telosgym.com
 * Description:       Display your TelosGym class schedule on your WordPress site with a shortcode or block.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            TelosGym
 * Author URI:        https://telosgym.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       telos-gym-schedule
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TELOS_GYM_SCHEDULE_VERSION', '1.0.0' );
define( 'TELOS_GYM_SCHEDULE_FILE', __FILE__ );
define( 'TELOS_GYM_SCHEDULE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TELOS_GYM_SCHEDULE_URL', plugin_dir_url( __FILE__ ) );
// telos-gym's production backend. No custom API domain is configured yet —
// see that repo's llms.txt "Stripe — live mode" section for the current host.
define( 'TELOS_GYM_API_BASE', 'https://studioos-production-c31b.up.railway.app' );

register_activation_hook( __FILE__, 'telos_gym_schedule_activate' );

function telos_gym_schedule_activate() {
	if ( false === get_option( 'telos_gym_schedule_options' ) ) {
		add_option( 'telos_gym_schedule_options', array() );
	}
}

add_action( 'plugins_loaded', 'telos_gym_schedule_load_textdomain' );

function telos_gym_schedule_load_textdomain() {
	load_plugin_textdomain( 'telos-gym-schedule', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-render.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-shortcode.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-settings-page.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-key-validator.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-block.php';

Telos_Gym_Schedule_Shortcode::init();
Telos_Gym_Schedule_Settings_Page::init();
Telos_Gym_Schedule_Key_Validator::init();
Telos_Gym_Schedule_Block::init();
