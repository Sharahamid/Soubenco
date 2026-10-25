<?php
/**
 * Plugin Name: Soubenco Core
 * Description: Soubenco shop engine — Product Studio, festive offers, app-only prices, persistent carts, wishlist, notifications and customer accounts.
 * Version: 1.0.0
 * Author: Soubenco
 * Requires Plugins: woocommerce
 * Text Domain: soubenco
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SB_VERSION', '1.0.0' );
define( 'SB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SB_URL', plugin_dir_url( __FILE__ ) );
define( 'SB_DB_VERSION', '1' );

require_once SB_DIR . 'includes/helpers.php';
require_once SB_DIR . 'includes/install.php';
require_once SB_DIR . 'includes/product-fields.php';
require_once SB_DIR . 'includes/templates.php';
require_once SB_DIR . 'includes/cart.php';
require_once SB_DIR . 'includes/wishlist.php';
require_once SB_DIR . 'includes/notifications.php';
require_once SB_DIR . 'includes/auth.php';
require_once SB_DIR . 'includes/account.php';
require_once SB_DIR . 'includes/checkout.php';
require_once SB_DIR . 'includes/studio.php';
require_once SB_DIR . 'includes/rest.php';

register_activation_hook( __FILE__, 'sb_install' );
add_action( 'init', function () {
	if ( get_option( 'sb_db_version' ) !== SB_DB_VERSION && function_exists( 'WC' ) ) sb_install();
}, 30 );
