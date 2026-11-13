<?php
/**
 * Plugin Name:          Fake Order Blocker for WooCommerce
 * Plugin URI:           https://github.com/developer-naz/woocommerce-fake-order-blocker
 * Description:          Stops fake and repeated Cash on Delivery orders: per-phone and per-IP order limits, phone/IP/email blacklist, on-hold for first-time COD customers, and a customer order history box on every order. Built for Bangladeshi stores, works anywhere.
 * Version:              1.0.0
 * Author:               Md Nazmul Islam (developer-naz)
 * Author URI:           https://www.linkedin.com/in/developernaz/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          wfob
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 *
 * @package WFOB
 */

defined( 'ABSPATH' ) || exit;

define( 'WFOB_VERSION', '1.0.0' );
define( 'WFOB_FILE', __FILE__ );
define( 'WFOB_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Declare compatibility with HPOS and the Checkout block.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WFOB_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WFOB_FILE, true );
	}
} );

require_once WFOB_PATH . 'includes/functions.php';

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Fake Order Blocker requires WooCommerce to be installed and active.', 'wfob' ) . '</p></div>';
		} );
		return;
	}

	require_once WFOB_PATH . 'includes/checkout-guard.php';

	if ( is_admin() ) {
		require_once WFOB_PATH . 'includes/settings.php';
		require_once WFOB_PATH . 'includes/admin-order.php';
	}
} );
