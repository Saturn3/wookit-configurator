<?php
/**
 * Disinstallazione: rimuove solo le impostazioni globali.
 * I kit (post type wkc_kit) vengono conservati per sicurezza: eliminali manualmente se non servono più.
 *
 * @package WooKitConfigurator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wkc_settings' );
delete_option( 'wkc_flush_rewrite' );
