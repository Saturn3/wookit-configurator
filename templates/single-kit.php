<?php
/**
 * Template pagina singola del kit (landing page a tutta pagina con header/footer del tema).
 * Sovrascrivibile copiandolo in: tuo-tema/wookit/single-kit.php
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

while ( have_posts() ) :
	the_post();
	echo '<main id="wkc-main" class="wkc-main">';
	echo WKC_Frontend::render( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</main>';
endwhile;

get_footer( 'shop' );
