<?php

use WP_Stateless\WP_Stateless_Class as Init;

/**
 * The plugin bootstrap file.
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/asiermusa
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Complete Stateless system (JWT, OTP...)
 * Plugin URI:        https://github.com/asiermusa
 * Description:       JWT based authentication, OTP verification, frontend password reset, Real-time messages with Redis
 * Version:           1.0.0
 * Author:            Asier Musatadi
 * Author URI:        @asiermusa
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       stateless-system
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wp-stateless.php';


/**
 * Define constants
 */
define ( 'PLUGIN_NAME', 'wp-stateless' );
define ( 'PLUGIN_VERSION', '1.0.0' ); 

define ( 'STATELESS_SYSTEM__FILE__', __FILE__ );
define ( 'AUTHY_SECRET', 'XXXXXXXXXXXXXXXX' );
define ( 'REDIS_IP', '127.0.0.1' );
define ( 'REDIS_PWD', '' );

/**
 * Login attempts control
 */
define ( 'ATTEMPT_LIMIT', 3 );
define ( 'LOCKOUT_DURATION', 300 ); // 5 minutes

/**
 * Social Login Secrets
 */
define ( 'GOOGLE_CLIENT_ID', 'XXXXXXXXXXXXXXXX' );
define ( 'TWIITER_API_KEY', 'XXXXXXXXXXXXXXXX');
define ( 'TWIITER_API_SECRET', 'XXXXXXXXXXXXXXXX');

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function init_plugin()
{
    $plugin = new Init();
    $plugin->start();
}
init_plugin();
