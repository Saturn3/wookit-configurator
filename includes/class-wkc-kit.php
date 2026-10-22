<?php
/**
 * Custom post type "Kit" e modello dati.
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

class WKC_Kit {

	const POST_TYPE = 'wkc_kit';
	const META      = '_wkc_';

	/** @var array Cache runtime dei dati frontend. */
	protected static $data_cache = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	public static function register_post_type() {
		$slug = WKC_Settings::get( 'slug' );
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Kit configurabili', 'wookit-configurator' ),
					'singular_name'      => __( 'Kit', 'wookit-configurator' ),
					'menu_name'          => __( 'Kit regalo', 'wookit-configurator' ),
					'add_new'            => __( 'Nuovo kit', 'wookit-configurator' ),
					'add_new_item'       => __( 'Aggiungi nuovo kit', 'wookit-configurator' ),
					'edit_item'          => __( 'Modifica kit', 'wookit-configurator' ),
					'new_item'           => __( 'Nuovo kit', 'wookit-configurator' ),
					'view_item'          => __( 'Visualizza landing page', 'wookit-configurator' ),
					'search_items'       => __( 'Cerca kit', 'wookit-configurator' ),
					'not_found'          => __( 'Nessun kit trovato', 'wookit-configurator' ),
					'not_found_in_trash' => __( 'Nessun kit nel cestino', 'wookit-configurator' ),
					'all_items'          => __( 'Tutti i kit', 'wookit-configurator' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 56,
				'menu_icon'           => 'dashicons-archive',
				'supports'            => array( 'title', 'thumbnail', 'revisions' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'rewrite'             => array(
					'slug'       => $slug ? $slug : 'kit',
					'with_front' => false,
				),
				'capability_type'     => 'product',
				'map_meta_cap'        => true,
			)
		);
	}

	public static function maybe_flush() {
		if ( get_option( 'wkc_flush_rewrite' ) ) {
			delete_option( 'wkc_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Definizione campi del kit con valori predefiniti (esempio Christmas Gift Set).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Configurazione.
			'box_product'      => 0,
			'categories'       => array(),
			'include_children' => 'no',
			'allow_duplicates' => 'yes',
			'preselect'        => 'category',
			'after_add'        => 'stay',
			// Hero.
			'show_hero'        => 'yes',
			'hero_image'       => 0,
			'hero_title'       => "Quest'anno il Natale ha un profumo speciale",
			'hero_text'        => 'Ordina il tuo Christmas Gift Set entro il 17 dicembre per riceverlo entro Natale. Le spedizioni partiranno in 24/48 ore lavorative.',
			// Configuratore.
			'slots'            => array(
				array(
					'title'       => '1. Scegli il primo prodotto',
					'description' => 'Seleziona la linea e la fragranza per il primo prodotto del tuo cofanetto.',
				),
				array(
					'title'       => '2. Scegli il secondo prodotto',
					'description' => 'Seleziona la linea e la fragranza per il secondo prodotto del tuo cofanetto.',
				),
			),
			'label_line'       => 'Linea prodotto',
			'label_fragrance'  => 'Fragranza',
			'label_soldout'    => 'Esaurito',
			// Riepilogo.
			'summary_title'    => '3. Il tuo GIFT SET',
			'summary_image'    => 0,
			'box_label'        => 'GIFT SET',
			'box_name'         => '',
			'label_empty'      => 'Scegli linea e fragranza',
			'label_edit'       => 'Modifica',
			'label_subtotal'   => 'Subtotale',
			'button_label'     => 'Aggiungi al carrello',
			'label_added'      => 'Il tuo gift set è stato aggiunto al carrello.',
			'label_view_cart'  => 'Vai al carrello',
			// Sezione finale.
			'show_story'       => 'yes',
			'story_image'      => 0,
			'story_title'      => 'Bastano pochi gesti per creare un regalo su misura, capace di conquistare ogni personalità!',
			'story_text'       => "L'esclusivo Christmas Gift Set nasce per accogliere i nostri saponi e bagnoschiuma da 480 ml. Crearlo è semplicissimo: scegli la combinazione che preferisci, divertiti a mixare le fragranze e completa la confezione con lo speciale packaging natalizio.",
			// Colori.
			'color_accent'     => '#80161b',
			'color_accent_txt' => '#ffffff',
			'color_button'     => '#000000',
			'color_button_txt' => '#ffffff',
			'color_selected'   => '#1a1a1a',
		);
	}

	/**
	 * Configurazione completa di un kit (meta + default).
	 *
	 * @param int $kit_id ID kit.
	 * @return array
	 */
	public static function get_config( $kit_id ) {
		$config = array();
		foreach ( self::defaults() as $key => $default ) {
			if ( metadata_exists( 'post', $kit_id, self::META . $key ) ) {
				$config[ $key ] = get_post_meta( $kit_id, self::META . $key, true );
			} else {
				$config[ $key ] = $default;
			}
		}
		if ( ! is_array( $config['categories'] ) ) {
			$config['categories'] = array();
		}
		if ( ! is_array( $config['slots'] ) || empty( $config['slots'] ) ) {
			$config['slots'] = self::defaults()['slots'];
		}
		$config['slots'] = array_values( $config['slots'] );
		return $config;
	}

	/**
	 * Traduce un ID oggetto con WPML (se presente).
	 *
	 * @param int    $id   ID.
	 * @param string $type Tipo (product, product_cat).
	 * @return int
	 */
	public static function translate_id( $id, $type ) {
		$id = absint( $id );
		if ( ! $id ) {
			return 0;
		}
		return absint( apply_filters( 'wpml_object_id', $id, $type, true ) );
	}

	/**
	 * Risolve il valore di un campo immagine (ID, URL, array ACF).
	 *
	 * @param mixed  $value Valore.
	 * @param string $size  Dimensione.
	 * @return string
	 */
	public static function resolve_image( $value, $size = 'woocommerce_thumbnail' ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['ID'] ) ) {
				$value = $value['ID'];
			} elseif ( ! empty( $value['id'] ) ) {
				$value = $value['id'];
			} elseif ( ! empty( $value['url'] ) ) {
				$value = $value['url'];
			} else {
				$value = reset( $value );
			}
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$url = wp_get_attachment_image_url( (int) $value, $size );
			return $url ? $url : '';
		}
		if ( is_string( $value ) && preg_match( '#^(https?:)?//|^/#', trim( $value ) ) ) {
			return esc_url_raw( trim( $value ) );
		}
		return '';
	}

	/**
	 * Testo semplice da un valore meta (gestisce array ACF select/checkbox).
	 *
	 * @param mixed $value Valore.
	 * @return string
	 */
	protected static function meta_text( $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['label'] ) ) {
				return (string) $value['label'];
			}
			$value = implode( ' - ', array_filter( array_map( 'strval', $value ) ) );
		}
		return trim( wp_strip_all_tags( (string) $value ) );
	}

	/**
	 * Prezzo da mostrare (rispetta impostazioni IVA del negozio).
	 *
	 * @param WC_Product $product Prodotto.
	 * @return float
	 */
	protected static function display_price( $product ) {
		return (float) wc_get_price_to_display( $product );
	}

	/**
	 * Dati del prodotto confezione.
	 *
	 * @param array $config Config.
	 * @return array|null
	 */
	public static function get_box_data( $config ) {
		$box_id = self::translate_id( $config['box_product'], 'product' );
		$box    = $box_id ? wc_get_product( $box_id ) : null;
		if ( ! $box ) {
			return null;
		}
		$thumb = wp_get_attachment_image_url( $box->get_image_id(), 'woocommerce_thumbnail' );
		return array(
			'id'       => $box->get_id(),
			'name'     => '' !== trim( (string) $config['box_name'] ) ? $config['box_name'] : $box->get_name(),
			'label'    => (string) $config['box_label'],
			'thumb'    => $thumb ? $thumb : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'price'    => self::display_price( $box ),
			'in_stock' => $box->is_purchasable() && $box->is_in_stock(),
		);
	}

	/**
	 * Linee (categorie) e prodotti selezionabili per il kit.
	 *
	 * @param int $kit_id ID kit.
	 * @return array
	 */
	public static function get_lines_data( $kit_id ) {
		$lang      = apply_filters( 'wpml_current_language', null );
		$cache_key = $kit_id . '|' . ( $lang ? $lang : '' );
		if ( isset( self::$data_cache[ $cache_key ] ) ) {
			return self::$data_cache[ $cache_key ];
		}

		$config        = self::get_config( $kit_id );
		$key_name      = WKC_Settings::get( 'meta_fragrance_name' );
		$key_image     = WKC_Settings::get( 'meta_fragrance_image' );
		$key_notes     = WKC_Settings::get( 'meta_fragrance_notes' );
		$hide_missing  = 'yes' === WKC_Settings::get( 'hide_without_fragrance' );
		$include_child = 'yes' === $config['include_children'];
		$box_id        = self::translate_id( $config['box_product'], 'product' );
		$lines         = array();

		foreach ( $config['categories'] as $row ) {
			$term_id = self::translate_id( isset( $row['term_id'] ) ? $row['term_id'] : 0, 'product_cat' );
			$term    = $term_id ? get_term( $term_id, 'product_cat' ) : null;
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$image = '';
			if ( ! empty( $row['image_id'] ) ) {
				$image = self::resolve_image( (int) $row['image_id'], 'woocommerce_thumbnail' );
			}
			if ( ! $image ) {
				$image = self::resolve_image( (int) get_term_meta( $term->term_id, 'thumbnail_id', true ), 'woocommerce_thumbnail' );
			}

			$query = new WP_Query(
				array(
					'post_type'        => 'product',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'orderby'          => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'no_found_rows'    => true,
					'suppress_filters' => false,
					'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => array( $term->term_id ),
							'include_children' => $include_child,
						),
					),
				)
			);

			$products = array();
			foreach ( $query->posts as $pid ) {
				if ( (int) $pid === $box_id ) {
					continue;
				}
				$product = wc_get_product( $pid );
				if ( ! $product || ! $product->is_type( 'simple' ) ) {
					continue;
				}
				$fragrance = $key_name ? self::meta_text( $product->get_meta( $key_name, true ) ) : '';
				if ( '' === $fragrance ) {
					if ( $hide_missing ) {
						continue;
					}
					$fragrance = $product->get_name();
				}
				$preview = $key_image ? self::resolve_image( $product->get_meta( $key_image, true ), 'woocommerce_thumbnail' ) : '';
				$thumb   = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );

				$products[] = array(
					'id'        => $product->get_id(),
					'name'      => $product->get_name(),
					'thumb'     => $thumb ? $thumb : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
					'fragrance' => $fragrance,
					'notes'     => $key_notes ? self::meta_text( $product->get_meta( $key_notes, true ) ) : '',
					'preview'   => $preview ? $preview : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
					'price'     => self::display_price( $product ),
					'in_stock'  => $product->is_purchasable() && $product->is_in_stock(),
				);
			}

			$lines[] = array(
				'id'       => $term->term_id,
				'label'    => ! empty( $row['label'] ) ? $row['label'] : $term->name,
				'subtitle' => ! empty( $row['subtitle'] ) ? $row['subtitle'] : wp_strip_all_tags( $term->description ),
				'image'    => $image ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				'products' => $products,
			);
		}

		$lines                          = apply_filters( 'wkc_kit_lines_data', $lines, $kit_id, $config );
		self::$data_cache[ $cache_key ] = $lines;
		return $lines;
	}

	/**
	 * Mappa product_id => line (solo prodotti acquistabili).
	 *
	 * @param int $kit_id ID kit.
	 * @return array
	 */
	public static function get_allowed_products( $kit_id ) {
		$map = array();
		foreach ( self::get_lines_data( $kit_id ) as $line ) {
			foreach ( $line['products'] as $p ) {
				if ( $p['in_stock'] ) {
					$map[ (int) $p['id'] ] = $line;
				}
			}
		}
		return $map;
	}

	/**
	 * ID dei prodotti usati come confezione in kit pubblicati (tutte le lingue).
	 *
	 * @return int[]
	 */
	public static function get_box_product_ids() {
		$cached = wp_cache_get( 'wkc_box_ids', 'wkc' );
		if ( false !== $cached ) {
			return $cached;
		}
		$ids  = array();
		$kits = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $kits as $kit_id ) {
			$box = absint( get_post_meta( $kit_id, self::META . 'box_product', true ) );
			if ( $box ) {
				$ids[] = $box;
				$ids[] = self::translate_id( $box, 'product' );
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		wp_cache_set( 'wkc_box_ids', $ids, 'wkc', 300 );
		return $ids;
	}
}
