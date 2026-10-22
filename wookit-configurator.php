<?php
/**
 * Plugin Name:       WooKit Configurator – Kit regalo configurabili
 * Plugin URI:        https://nefel.it
 * Description:       Crea kit di prodotti configurabili (es. Christmas Gift Set) composti da una confezione sempre presente e da N prodotti scelti dal cliente per linea (categoria) e fragranza, con landing page dedicata e testi/immagini configurabili.
 * Version:           1.0.2
 * Author:            AcidSoft
 * Author URI:        https://acidsoft.it
 * Text Domain:       wookit-configurator
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   11.2
 * License:           GPL-2.0-or-later
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

define( 'WKC_VERSION', '1.0.2' );
define( 'WKC_FILE', __FILE__ );
define( 'WKC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WKC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Compatibilità HPOS e Cart/Checkout Blocks.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

require_once WKC_DIR . 'includes/class-wkc-settings.php';
require_once WKC_DIR . 'includes/class-wkc-kit.php';
require_once WKC_DIR . 'includes/class-wkc-admin.php';
require_once WKC_DIR . 'includes/class-wkc-frontend.php';
require_once WKC_DIR . 'includes/class-wkc-cart.php';

/**
 * Avvio del plugin (solo se WooCommerce è attivo).
 */
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'wookit-configurator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'WooKit Configurator richiede WooCommerce attivo.', 'wookit-configurator' ) . '</p></div>';
				}
			);
			return;
		}

		WKC_Settings::init();
		WKC_Kit::init();
		WKC_Admin::init();
		WKC_Frontend::init();
		WKC_Cart::init();
	}
);

register_activation_hook(
	__FILE__,
	function () {
		require_once WKC_DIR . 'includes/class-wkc-settings.php';
		require_once WKC_DIR . 'includes/class-wkc-kit.php';
		WKC_Kit::register_post_type();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
