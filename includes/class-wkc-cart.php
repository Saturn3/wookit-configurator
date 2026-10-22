<?php
/**
 * Carrello e ordini: aggiunta kit, collegamento confezione/prodotti, integrità.
 *
 * Ogni kit aggiunto al carrello genera N+1 righe collegate da un "group" univoco:
 * la confezione (role=box, riga principale) e i prodotti scelti (role=item).
 * Prezzi e giacenze restano quelli dei singoli prodotti WooCommerce.
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

class WKC_Cart {

	/** @var bool Evita ricorsioni durante operazioni a cascata. */
	protected static $busy = false;

	/** @var bool Aggiunta in corso dal configuratore. */
	protected static $adding = false;

	/** @var array Mappa gruppi per "ordina di nuovo". */
	protected static $reorder_groups = array();

	public static function init() {
		add_action( 'wc_ajax_wkc_add_kit', array( __CLASS__, 'ajax_add' ) );

		add_action( 'woocommerce_remove_cart_item', array( __CLASS__, 'on_remove' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( __CLASS__, 'on_restore' ), 10, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'on_qty_update' ), 10, 4 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'check_integrity' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_integrity' ), 5 );

		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'qty_html' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( __CLASS__, 'remove_link' ), 10, 2 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( __CLASS__, 'store_api_editable' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_class', array( __CLASS__, 'item_class' ), 10, 3 );
		add_filter( 'woocommerce_mini_cart_item_class', array( __CLASS__, 'item_class' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'block_standalone_box' ), 10, 2 );

		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );
		add_filter( 'woocommerce_order_item_class', array( __CLASS__, 'order_item_class' ), 10, 2 );
		add_filter( 'woocommerce_order_again_cart_item_data', array( __CLASS__, 'order_again' ), 10, 2 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'cart_styles' ), 20 );
	}

	/* ---------------------------------------------------------------------
	 * Aggiunta al carrello (AJAX)
	 * ------------------------------------------------------------------- */

	protected static function fail( $message ) {
		wp_send_json_error( array( 'message' => wp_strip_all_tags( html_entity_decode( $message, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	public static function ajax_add() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- come l'add-to-cart nativo di WooCommerce (pagine in cache).
		$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
		if ( $lang ) {
			do_action( 'wpml_switch_language', $lang );
		}
		$kit_id   = isset( $_POST['kit_id'] ) ? absint( $_POST['kit_id'] ) : 0;
		$products = isset( $_POST['products'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['products'] ) ) : array();
		// phpcs:enable

		$kit = $kit_id ? get_post( $kit_id ) : null;
		if ( ! $kit || WKC_Kit::POST_TYPE !== $kit->post_type || 'publish' !== $kit->post_status ) {
			self::fail( __( 'Kit non disponibile.', 'wookit-configurator' ) );
		}

		$config = WKC_Kit::get_config( $kit_id );
		$slots  = count( $config['slots'] );
		$box    = WKC_Kit::get_box_data( $config );

		if ( ! $box || ! $box['in_stock'] ) {
			self::fail( __( 'La confezione non è al momento disponibile.', 'wookit-configurator' ) );
		}
		if ( count( $products ) !== $slots || in_array( 0, $products, true ) ) {
			self::fail( __( 'Completa la scelta di tutti i prodotti del kit.', 'wookit-configurator' ) );
		}
		if ( 'yes' !== $config['allow_duplicates'] && count( array_unique( $products ) ) !== count( $products ) ) {
			self::fail( __( 'Scegli prodotti diversi per ogni step del kit.', 'wookit-configurator' ) );
		}

		$allowed = WKC_Kit::get_allowed_products( $kit_id );
		foreach ( $products as $pid ) {
			if ( ! isset( $allowed[ $pid ] ) ) {
				self::fail( __( 'Uno dei prodotti scelti non è disponibile per questo kit.', 'wookit-configurator' ) );
			}
		}

		$validation = apply_filters( 'wkc_validate_kit', true, $kit_id, $products, $config );
		if ( true !== $validation ) {
			self::fail( is_string( $validation ) ? $validation : __( 'Kit non valido.', 'wookit-configurator' ) );
		}

		$group = strtolower( wp_generate_password( 12, false, false ) );
		$base  = array(
			'group'    => $group,
			'kit_id'   => $kit_id,
			'kit_name' => get_the_title( $kit ),
			'size'     => $slots,
		);

		$added        = array();
		self::$adding = true;
		wc_clear_notices();

		$box_key = WC()->cart->add_to_cart( $box['id'], 1, 0, array(), array( 'wkc' => $base + array( 'role' => 'box', 'slot' => -1 ) ) );
		if ( $box_key ) {
			$added[] = $box_key;
			foreach ( $products as $i => $pid ) {
				$key = WC()->cart->add_to_cart(
					$pid,
					1,
					0,
					array(),
					array(
						'wkc' => $base + array(
							'role' => 'item',
							'slot' => (int) $i,
						),
					)
				);
				if ( ! $key ) {
					$added[] = false;
					break;
				}
				$added[] = $key;
			}
		}
		self::$adding = false;

		if ( ! $box_key || in_array( false, $added, true ) ) {
			// Rollback.
			self::$busy = true;
			foreach ( array_filter( $added ) as $key ) {
				WC()->cart->remove_cart_item( $key );
				unset( WC()->cart->removed_cart_contents[ $key ] );
			}
			self::$busy = false;
			$errors = wc_get_notices( 'error' );
			wc_clear_notices();
			$msg = __( 'Impossibile aggiungere il kit al carrello.', 'wookit-configurator' );
			if ( ! empty( $errors ) ) {
				$first = reset( $errors );
				$msg   = is_array( $first ) ? $first['notice'] : $first;
			}
			self::fail( $msg );
		}

		wc_clear_notices();
		do_action( 'wkc_kit_added_to_cart', $group, $kit_id, $products, $box['id'] );
		do_action( 'woocommerce_ajax_added_to_cart', $box['id'] );

		ob_start();
		woocommerce_mini_cart();
		$mini = ob_get_clean();

		wp_send_json_success(
			array(
				'group'     => $group,
				'fragments' => apply_filters(
					'woocommerce_add_to_cart_fragments',
					array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini . '</div>' )
				),
				'cart_hash' => WC()->cart->get_cart_hash(),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Helper
	 * ------------------------------------------------------------------- */

	protected static function wkc( $item ) {
		return ( is_array( $item ) && ! empty( $item['wkc']['group'] ) ) ? $item['wkc'] : null;
	}

	/**
	 * Chiavi carrello appartenenti a un gruppo.
	 *
	 * @param WC_Cart $cart  Carrello.
	 * @param string  $group Gruppo.
	 * @param string  $exclude Chiave da escludere.
	 * @return string[]
	 */
	protected static function group_keys( $cart, $group, $exclude = '' ) {
		$keys = array();
		foreach ( $cart->get_cart_contents() as $key => $item ) {
			$w = self::wkc( $item );
			if ( $w && $w['group'] === $group && $key !== $exclude ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/* ---------------------------------------------------------------------
	 * Collegamento righe
	 * ------------------------------------------------------------------- */

	public static function on_remove( $key, $cart ) {
		if ( self::$busy || self::$adding ) {
			return;
		}
		$item = isset( $cart->cart_contents[ $key ] ) ? $cart->cart_contents[ $key ] : null;
		$w    = self::wkc( $item );
		if ( ! $w ) {
			return;
		}
		self::$busy = true;
		foreach ( self::group_keys( $cart, $w['group'], $key ) as $other ) {
			$cart->remove_cart_item( $other );
		}
		self::$busy = false;
	}

	public static function on_restore( $key, $cart ) {
		if ( self::$busy ) {
			return;
		}
		$w = self::wkc( isset( $cart->cart_contents[ $key ] ) ? $cart->cart_contents[ $key ] : null );
		if ( ! $w ) {
			return;
		}
		self::$busy = true;
		foreach ( $cart->get_removed_cart_contents() as $rkey => $ritem ) {
			$rw = self::wkc( $ritem );
			if ( $rw && $rw['group'] === $w['group'] ) {
				$cart->restore_cart_item( $rkey );
			}
		}
		self::$busy = false;
	}

	public static function on_qty_update( $key, $qty, $old_qty, $cart ) {
		if ( self::$busy ) {
			return;
		}
		$w = self::wkc( isset( $cart->cart_contents[ $key ] ) ? $cart->cart_contents[ $key ] : null );
		if ( ! $w ) {
			return;
		}
		self::$busy = true;
		if ( 'box' === $w['role'] ) {
			foreach ( self::group_keys( $cart, $w['group'], $key ) as $child ) {
				$cart->set_quantity( $child, $qty, false );
			}
		} else {
			// I prodotti del kit seguono sempre la quantità della confezione.
			foreach ( self::group_keys( $cart, $w['group'], $key ) as $other ) {
				$ow = self::wkc( $cart->cart_contents[ $other ] );
				if ( 'box' === $ow['role'] ) {
					$cart->set_quantity( $key, $cart->cart_contents[ $other ]['quantity'], false );
				}
			}
		}
		self::$busy = false;
	}

	/**
	 * Rimuove kit incompleti, allinea quantità, blocca confezioni vendute da sole.
	 */
	public static function check_integrity() {
		if ( self::$busy || self::$adding || ! WC()->cart ) {
			return;
		}
		$cart   = WC()->cart;
		$groups = array();
		foreach ( $cart->get_cart_contents() as $key => $item ) {
			$w = self::wkc( $item );
			if ( $w ) {
				$groups[ $w['group'] ][ $key ] = $item;
			}
		}

		self::$busy = true;
		$broken     = false;
		foreach ( $groups as $group => $items ) {
			$box_key = null;
			$count   = 0;
			$size    = 0;
			foreach ( $items as $key => $item ) {
				if ( 'box' === $item['wkc']['role'] ) {
					$box_key = $key;
					$size    = (int) $item['wkc']['size'];
				} else {
					++$count;
				}
			}
			if ( ! $box_key || $count !== $size ) {
				foreach ( array_keys( $items ) as $key ) {
					unset( $cart->cart_contents[ $key ] );
				}
				$broken = true;
				continue;
			}
			$qty = (int) $items[ $box_key ]['quantity'];
			foreach ( $items as $key => $item ) {
				if ( $key !== $box_key && (int) $item['quantity'] !== $qty ) {
					$cart->cart_contents[ $key ]['quantity'] = $qty;
				}
			}
		}

		if ( 'yes' === WKC_Settings::get( 'box_only_in_kit' ) ) {
			$box_ids = WKC_Kit::get_box_product_ids();
			foreach ( $cart->get_cart_contents() as $key => $item ) {
				if ( ! self::wkc( $item ) && in_array( (int) $item['product_id'], $box_ids, true ) ) {
					unset( $cart->cart_contents[ $key ] );
					$broken = true;
				}
			}
		}
		self::$busy = false;

		if ( $broken ) {
			$cart->set_session();
			if ( doing_action( 'woocommerce_check_cart_items' ) ) {
				wc_add_notice( __( 'Un kit nel carrello era incompleto ed è stato rimosso. Puoi ricomporlo dalla pagina del kit.', 'wookit-configurator' ), 'notice' );
			}
		}
	}

	public static function block_standalone_box( $passed, $product_id ) {
		if ( self::$adding || 'yes' !== WKC_Settings::get( 'box_only_in_kit' ) ) {
			return $passed;
		}
		if ( in_array( (int) $product_id, WKC_Kit::get_box_product_ids(), true ) ) {
			wc_add_notice( __( 'Questa confezione si acquista solo componendo il kit dalla sua pagina dedicata.', 'wookit-configurator' ), 'error' );
			return false;
		}
		return $passed;
	}

	/* ---------------------------------------------------------------------
	 * Visualizzazione carrello
	 * ------------------------------------------------------------------- */

	public static function qty_html( $html, $key, $item = null ) {
		if ( null === $item && WC()->cart ) {
			$item = WC()->cart->get_cart_item( $key );
		}
		$w = self::wkc( $item );
		if ( $w && 'item' === $w['role'] ) {
			return '<span class="wkc-cart-qty">' . esc_html( $item['quantity'] ) . '</span>';
		}
		return $html;
	}

	public static function remove_link( $link, $key ) {
		$item = WC()->cart ? WC()->cart->get_cart_item( $key ) : null;
		$w    = self::wkc( $item );
		return ( $w && 'item' === $w['role'] ) ? '' : $link;
	}

	public static function store_api_editable( $editable, $product, $item ) {
		$w = self::wkc( $item );
		return ( $w && 'item' === $w['role'] ) ? false : $editable;
	}

	public static function item_data( $data, $item ) {
		$w = self::wkc( $item );
		if ( ! $w ) {
			return $data;
		}
		if ( 'box' === $w['role'] ) {
			$names = array();
			if ( WC()->cart ) {
				foreach ( WC()->cart->get_cart_contents() as $ci ) {
					$cw = self::wkc( $ci );
					if ( $cw && $cw['group'] === $w['group'] && 'item' === $cw['role'] && ! empty( $ci['data'] ) ) {
						$names[ (int) $cw['slot'] ] = $ci['data']->get_name();
					}
				}
			}
			ksort( $names );
			if ( $names ) {
				$data[] = array(
					'key'   => __( 'Contenuto', 'wookit-configurator' ),
					'value' => implode( ', ', $names ),
				);
			}
		} else {
			$data[] = array(
				'key'   => __( 'Parte di', 'wookit-configurator' ),
				'value' => $w['kit_name'],
			);
		}
		return $data;
	}

	public static function item_class( $class, $item, $key = '' ) {
		$w = self::wkc( $item );
		if ( $w ) {
			$class .= ' wkc-cart-item wkc-cart-item--' . $w['role'];
		}
		return $class;
	}

	public static function cart_styles() {
		if ( ! ( is_cart() || is_checkout() || is_account_page() ) ) {
			return;
		}
		$css = '.wkc-cart-item--item td.product-name,.wkc-cart-item--item .product-name{padding-left:2.2em!important;position:relative}'
			. '.wkc-cart-item--item td.product-name::before{content:"↳";position:absolute;left:.8em;opacity:.5}'
			. '.wkc-cart-item--item td.product-thumbnail img{max-width:48px}';
		wp_register_style( 'wkc-cart', false, array(), WKC_VERSION );
		wp_enqueue_style( 'wkc-cart' );
		wp_add_inline_style( 'wkc-cart', $css );
	}

	/* ---------------------------------------------------------------------
	 * Ordini
	 * ------------------------------------------------------------------- */

	public static function order_line_item( $item, $key, $values, $order ) {
		$w = self::wkc( $values );
		if ( ! $w ) {
			return;
		}
		$item->add_meta_data( '_wkc_group', $w['group'], true );
		$item->add_meta_data( '_wkc_kit_id', (int) $w['kit_id'], true );
		$item->add_meta_data( '_wkc_role', $w['role'], true );
		$item->add_meta_data( '_wkc_slot', (int) $w['slot'], true );
		$item->add_meta_data( '_wkc_size', (int) $w['size'], true );
		if ( 'item' === $w['role'] ) {
			$item->add_meta_data( __( 'Parte di', 'wookit-configurator' ), $w['kit_name'], true );
		} else {
			$names = array();
			foreach ( WC()->cart->get_cart_contents() as $ci ) {
				$cw = self::wkc( $ci );
				if ( $cw && $cw['group'] === $w['group'] && 'item' === $cw['role'] ) {
					$names[ (int) $cw['slot'] ] = $ci['data']->get_name();
				}
			}
			ksort( $names );
			$item->add_meta_data( __( 'Kit', 'wookit-configurator' ), $w['kit_name'], true );
			if ( $names ) {
				$item->add_meta_data( __( 'Contenuto', 'wookit-configurator' ), implode( ', ', $names ), true );
			}
		}
	}

	public static function hidden_meta( $keys ) {
		return array_merge( $keys, array( '_wkc_group', '_wkc_kit_id', '_wkc_role', '_wkc_slot', '_wkc_size' ) );
	}

	public static function order_item_class( $class, $item ) {
		$role = is_object( $item ) ? $item->get_meta( '_wkc_role' ) : '';
		return $role ? $class . ' wkc-cart-item wkc-cart-item--' . $role : $class;
	}

	/**
	 * "Ordina di nuovo": ricostruisce i kit con un nuovo gruppo.
	 */
	public static function order_again( $data, $item ) {
		$group = $item->get_meta( '_wkc_group' );
		if ( ! $group ) {
			return $data;
		}
		if ( ! isset( self::$reorder_groups[ $group ] ) ) {
			self::$reorder_groups[ $group ] = strtolower( wp_generate_password( 12, false, false ) );
		}
		$kit_id      = (int) $item->get_meta( '_wkc_kit_id' );
		$data['wkc'] = array(
			'group'    => self::$reorder_groups[ $group ],
			'kit_id'   => $kit_id,
			'kit_name' => $kit_id ? get_the_title( $kit_id ) : '',
			'size'     => (int) $item->get_meta( '_wkc_size' ),
			'role'     => $item->get_meta( '_wkc_role' ),
			'slot'     => (int) $item->get_meta( '_wkc_slot' ),
		);
		self::$adding = true; // Consente il riacquisto della confezione.
		if ( ! has_action( 'woocommerce_ordered_again', array( __CLASS__, 'end_order_again' ) ) ) {
			add_action( 'woocommerce_ordered_again', array( __CLASS__, 'end_order_again' ) );
		}
		return $data;
	}

	public static function end_order_again() {
		self::$adding = false;
	}
}
