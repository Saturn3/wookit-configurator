<?php
/**
 * Frontend: shortcode, template landing page, asset.
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

class WKC_Frontend {

	/** @var int Contatore istanze nella pagina. */
	protected static $instance = 0;

	public static function init() {
		add_shortcode( 'wookit_kit', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function register_assets() {
		wp_register_style( 'wkc-frontend', WKC_URL . 'assets/css/frontend.css', array(), WKC_VERSION );
		wp_register_script( 'wkc-frontend', WKC_URL . 'assets/js/frontend.js', array( 'jquery' ), WKC_VERSION, true );
		wp_localize_script(
			'wkc-frontend',
			'wkcParams',
			array(
				'ajaxUrl'  => WC_AJAX::get_endpoint( 'wkc_add_kit' ),
				'cartUrl'  => wc_get_cart_url(),
				'currency' => array(
					'symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'format'    => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
					'decimals'  => wc_get_price_decimals(),
					'decimal'   => wc_get_price_decimal_separator(),
					'thousand'  => wc_get_price_thousand_separator(),
				),
				'i18n'     => array(
					'error'   => __( 'Si è verificato un errore, riprova.', 'wookit-configurator' ),
					'missing' => __( 'Completa la scelta di tutti i prodotti del kit.', 'wookit-configurator' ),
					'remove'  => __( 'Rimuovi', 'wookit-configurator' ),
					'prev'    => __( 'Precedente', 'wookit-configurator' ),
					'next'    => __( 'Successivo', 'wookit-configurator' ),
				),
			)
		);

		$post = get_post();
		if ( is_singular( WKC_Kit::POST_TYPE ) || ( $post && self::post_has_kit( $post ) ) ) {
			wp_enqueue_style( 'wkc-frontend' );
			wp_enqueue_script( 'wkc-frontend' );
		}
	}

	/**
	 * La pagina contiene lo shortcode (contenuto classico o Elementor)?
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected static function post_has_kit( $post ) {
		if ( has_shortcode( (string) $post->post_content, 'wookit_kit' ) ) {
			return true;
		}
		$el = get_post_meta( $post->ID, '_elementor_data', true );
		return is_string( $el ) && false !== strpos( $el, 'wookit_kit' );
	}

	public static function body_class( $classes ) {
		if ( is_singular( WKC_Kit::POST_TYPE ) ) {
			$classes[] = 'wkc-landing-page';
		}
		return $classes;
	}

	public static function template( $template ) {
		if ( is_singular( WKC_Kit::POST_TYPE ) ) {
			$theme = locate_template( array( 'wookit/single-kit.php' ) );
			return $theme ? $theme : WKC_DIR . 'templates/single-kit.php';
		}
		return $template;
	}

	/**
	 * Shortcode [wookit_kit id="123"].
	 *
	 * @param array $atts Attributi.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts   = shortcode_atts( array( 'id' => 0 ), $atts, 'wookit_kit' );
		$kit_id = absint( $atts['id'] );
		if ( ! $kit_id && is_singular( WKC_Kit::POST_TYPE ) ) {
			$kit_id = get_the_ID();
		}
		return self::render( $kit_id );
	}

	/**
	 * Rendering landing/configuratore.
	 *
	 * @param int $kit_id ID kit.
	 * @return string
	 */
	public static function render( $kit_id ) {
		$kit_id = WKC_Kit::translate_id( $kit_id, WKC_Kit::POST_TYPE );
		$post   = $kit_id ? get_post( $kit_id ) : null;
		if ( ! $post || WKC_Kit::POST_TYPE !== $post->post_type ) {
			return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'Kit non trovato.', 'wookit-configurator' ) . '</p>' : '';
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $kit_id ) ) {
			return '';
		}

		wp_enqueue_style( 'wkc-frontend' );
		wp_enqueue_script( 'wkc-frontend' );

		$config = WKC_Kit::get_config( $kit_id );
		$lines  = WKC_Kit::get_lines_data( $kit_id );
		$box    = WKC_Kit::get_box_data( $config );
		++self::$instance;
		$uid = 'wkc-' . $kit_id . '-' . self::$instance;

		$redirect = '';
		if ( 'cart' === $config['after_add'] ) {
			$redirect = wc_get_cart_url();
		} elseif ( 'checkout' === $config['after_add'] ) {
			$redirect = wc_get_checkout_url();
		}

		$js = array(
			'kitId'      => $kit_id,
			'lang'       => apply_filters( 'wpml_current_language', null ),
			'slots'      => count( $config['slots'] ),
			'lines'      => $lines,
			'box'        => $box,
			'preselect'  => $config['preselect'],
			'duplicates' => 'yes' === $config['allow_duplicates'],
			'redirect'   => $redirect,
			'labels'     => array(
				'empty'    => $config['label_empty'],
				'edit'     => $config['label_edit'],
				'soldout'  => $config['label_soldout'],
				'added'    => $config['label_added'],
				'viewCart' => $config['label_view_cart'],
			),
		);

		$style = sprintf(
			'--wkc-accent:%s;--wkc-accent-text:%s;--wkc-btn:%s;--wkc-btn-text:%s;--wkc-selected:%s;%s',
			esc_attr( $config['color_accent'] ),
			esc_attr( $config['color_accent_txt'] ),
			esc_attr( $config['color_button'] ),
			esc_attr( $config['color_button_txt'] ),
			esc_attr( $config['color_selected'] ),
			WKC_Settings::get( 'heading_font' ) ? '--wkc-heading-font:' . esc_attr( WKC_Settings::get( 'heading_font' ) ) . ';' : ''
		);

		$classes = array( 'wkc-kit' );
		if ( 'yes' === WKC_Settings::get( 'full_width' ) ) {
			$classes[] = 'wkc-full-width';
		}

		$vars = compact( 'kit_id', 'post', 'config', 'lines', 'box', 'uid', 'js', 'style', 'classes' );
		$vars = apply_filters( 'wkc_template_vars', $vars, $kit_id );

		$template = locate_template( array( 'wookit/landing.php' ) );
		if ( ! $template ) {
			$template = WKC_DIR . 'templates/landing.php';
		}
		ob_start();
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include $template;
		return ob_get_clean();
	}

	/**
	 * Testo con a-capo e HTML base.
	 *
	 * @param string $text Testo.
	 * @return string
	 */
	public static function rich( $text ) {
		return nl2br( wp_kses_post( trim( (string) $text ) ) );
	}

	/**
	 * Immagine da ID allegato.
	 *
	 * @param int    $id    ID.
	 * @param string $size  Dimensione.
	 * @param string $class Classe.
	 * @return string
	 */
	public static function image( $id, $size, $class ) {
		$id = absint( $id );
		if ( ! $id ) {
			return '';
		}
		return wp_get_attachment_image(
			$id,
			$size,
			false,
			array(
				'class'   => $class,
				'loading' => 'lazy',
			)
		);
	}
}
