<?php
/**
 * Amministrazione: meta box del kit, salvataggio, colonne.
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

class WKC_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_' . WKC_Kit::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . WKC_Kit::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_' . WKC_Kit::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WKC_Kit::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( WKC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . WKC_Kit::POST_TYPE . '&page=wkc-settings' ) ) . '">' . esc_html__( 'Impostazioni', 'wookit-configurator' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || WKC_Kit::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'wkc-admin', WKC_URL . 'assets/css/admin.css', array(), WKC_VERSION );
		wp_enqueue_script( 'wkc-admin', WKC_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker', 'wc-enhanced-select' ), WKC_VERSION, true );
		wp_localize_script(
			'wkc-admin',
			'wkcAdmin',
			array(
				'chooseImage' => __( 'Scegli immagine', 'wookit-configurator' ),
				'useImage'    => __( 'Usa questa immagine', 'wookit-configurator' ),
				'confirmDel'  => __( 'Rimuovere questa riga?', 'wookit-configurator' ),
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box( 'wkc_config', __( 'Composizione del kit', 'wookit-configurator' ), array( __CLASS__, 'box_config' ), null, 'normal', 'high' );
		add_meta_box( 'wkc_content', __( 'Testi e immagini della landing page', 'wookit-configurator' ), array( __CLASS__, 'box_content' ), null, 'normal', 'default' );
		add_meta_box( 'wkc_colors', __( 'Colori', 'wookit-configurator' ), array( __CLASS__, 'box_colors' ), null, 'side', 'default' );
		add_meta_box( 'wkc_publish', __( 'Landing page', 'wookit-configurator' ), array( __CLASS__, 'box_publish' ), null, 'side', 'high' );
	}

	/* ---------------------------------------------------------------------
	 * Helper di rendering campi
	 * ------------------------------------------------------------------- */

	protected static function name( $key ) {
		return 'wkc[' . $key . ']';
	}

	protected static function field_text( $key, $label, $value, $args = array() ) {
		$id = 'wkc_' . $key;
		echo '<div class="wkc-field">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( ! empty( $args['textarea'] ) ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" rows="' . esc_attr( $args['rows'] ?? 3 ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $args['placeholder'] ?? '' ) . '">';
		}
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
		echo '</div>';
	}

	protected static function image_picker( $name, $value, $label = '' ) {
		$value = absint( $value );
		$url   = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
		echo '<div class="wkc-field wkc-image-field">';
		if ( $label ) {
			echo '<label>' . esc_html( $label ) . '</label>';
		}
		echo '<div class="wkc-image-picker' . ( $url ? ' has-image' : '' ) . '">';
		echo '<input type="hidden" class="wkc-image-id" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ? $value : '' ) . '">';
		echo '<div class="wkc-image-preview">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '' ) . '</div>';
		echo '<button type="button" class="button wkc-image-select">' . esc_html__( 'Scegli immagine', 'wookit-configurator' ) . '</button> ';
		echo '<button type="button" class="button-link wkc-image-remove">' . esc_html__( 'Rimuovi', 'wookit-configurator' ) . '</button>';
		echo '</div></div>';
	}

	protected static function toggle( $key, $label, $value ) {
		echo '<div class="wkc-field wkc-toggle"><label><input type="checkbox" name="' . esc_attr( self::name( $key ) ) . '" value="yes" ' . checked( 'yes', $value, false ) . '> ' . esc_html( $label ) . '</label></div>';
	}

	protected static function category_options( $selected ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return '';
		}
		$by_parent = array();
		foreach ( $terms as $t ) {
			$by_parent[ $t->parent ][] = $t;
		}
		$html = '<option value="">' . esc_html__( '— Seleziona categoria —', 'wookit-configurator' ) . '</option>';
		$walk = function ( $parent, $depth ) use ( &$walk, &$html, $by_parent, $selected ) {
			if ( empty( $by_parent[ $parent ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent ] as $t ) {
				$html .= '<option value="' . esc_attr( $t->term_id ) . '" ' . selected( (int) $selected, (int) $t->term_id, false ) . '>' . str_repeat( '&nbsp;&nbsp;&nbsp;', $depth ) . esc_html( $t->name ) . ' (' . (int) $t->count . ')</option>';
				$walk( $t->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $html;
	}

	protected static function category_row( $i, $row ) {
		$row = wp_parse_args(
			(array) $row,
			array(
				'term_id'  => '',
				'label'    => '',
				'subtitle' => '',
				'image_id' => '',
			)
		);
		$base = 'wkc[categories][' . $i . ']';
		ob_start();
		?>
		<div class="wkc-row">
			<span class="wkc-row-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Trascina per ordinare', 'wookit-configurator' ); ?>"></span>
			<div class="wkc-row-body wkc-grid-4">
				<div class="wkc-field">
					<label><?php esc_html_e( 'Categoria prodotto', 'wookit-configurator' ); ?></label>
					<select name="<?php echo esc_attr( $base ); ?>[term_id]"><?php echo self::category_options( $row['term_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
				</div>
				<div class="wkc-field">
					<label><?php esc_html_e( 'Titolo linea (opz.)', 'wookit-configurator' ); ?></label>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Es. HAND SOAP – vuoto = nome categoria', 'wookit-configurator' ); ?>">
				</div>
				<div class="wkc-field">
					<label><?php esc_html_e( 'Sottotitolo (opz.)', 'wookit-configurator' ); ?></label>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[subtitle]" value="<?php echo esc_attr( $row['subtitle'] ); ?>" placeholder="<?php esc_attr_e( 'Es. Sapone liquido mani', 'wookit-configurator' ); ?>">
				</div>
				<?php self::image_picker( $base . '[image_id]', $row['image_id'], __( 'Immagine linea (opz.)', 'wookit-configurator' ) ); ?>
			</div>
			<button type="button" class="button-link wkc-row-remove" title="<?php esc_attr_e( 'Rimuovi', 'wookit-configurator' ); ?>"><span class="dashicons dashicons-trash"></span></button>
		</div>
		<?php
		return ob_get_clean();
	}

	protected static function slot_row( $i, $row ) {
		$row  = wp_parse_args(
			(array) $row,
			array(
				'title'       => '',
				'description' => '',
			)
		);
		$base = 'wkc[slots][' . $i . ']';
		ob_start();
		?>
		<div class="wkc-row">
			<span class="wkc-row-handle dashicons dashicons-menu"></span>
			<div class="wkc-row-body wkc-grid-2">
				<div class="wkc-field">
					<label><?php esc_html_e( 'Titolo step', 'wookit-configurator' ); ?></label>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[title]" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="<?php esc_attr_e( 'Es. 1. Scegli il primo prodotto', 'wookit-configurator' ); ?>">
				</div>
				<div class="wkc-field">
					<label><?php esc_html_e( 'Descrizione step', 'wookit-configurator' ); ?></label>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[description]" value="<?php echo esc_attr( $row['description'] ); ?>">
				</div>
			</div>
			<button type="button" class="button-link wkc-row-remove"><span class="dashicons dashicons-trash"></span></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Meta box
	 * ------------------------------------------------------------------- */

	public static function box_config( $post ) {
		$c = WKC_Kit::get_config( $post->ID );
		wp_nonce_field( 'wkc_save_kit', 'wkc_nonce' );
		$box     = $c['box_product'] ? wc_get_product( $c['box_product'] ) : null;
		$is_new  = ! metadata_exists( 'post', $post->ID, WKC_Kit::META . 'slots' );
		?>
		<div class="wkc-section">
			<h3><?php esc_html_e( 'Confezione (sempre inclusa)', 'wookit-configurator' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Prodotto WooCommerce che rappresenta la scatola/packaging: viene aggiunto sempre al carrello insieme ai prodotti scelti. Suggerimento: imposta la sua visibilità catalogo su "Nascosto".', 'wookit-configurator' ); ?></p>
			<select class="wc-product-search" style="width:100%;max-width:520px" name="wkc[box_product]" data-placeholder="<?php esc_attr_e( 'Cerca il prodotto confezione…', 'wookit-configurator' ); ?>" data-action="woocommerce_json_search_products" data-allow_clear="true">
				<?php if ( $box ) : ?>
					<option value="<?php echo esc_attr( $box->get_id() ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $box->get_formatted_name() ) ); ?></option>
				<?php endif; ?>
			</select>
		</div>

		<div class="wkc-section">
			<h3><?php esc_html_e( 'Prodotti da scegliere (step)', 'wookit-configurator' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Ogni riga è un prodotto che il cliente sceglie (predefinito: 2). Per ciascuno il cliente seleziona prima la linea (categoria) e poi la fragranza.', 'wookit-configurator' ); ?></p>
			<div class="wkc-repeater" data-template="wkc-tpl-slot">
				<div class="wkc-rows">
					<?php
					foreach ( $c['slots'] as $i => $slot ) {
						echo self::slot_row( $i, $slot ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
				<button type="button" class="button wkc-row-add"><?php esc_html_e( '+ Aggiungi prodotto al kit', 'wookit-configurator' ); ?></button>
				<script type="text/html" id="wkc-tpl-slot"><?php echo self::slot_row( '__INDEX__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
			</div>
		</div>

		<div class="wkc-section">
			<h3><?php esc_html_e( 'Linee prodotto (categorie selezionabili)', 'wookit-configurator' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Categorie WooCommerce tra cui il cliente sceglie la linea. Titolo, sottotitolo e immagine sono facoltativi: se vuoti si usano nome, descrizione e immagine della categoria.', 'wookit-configurator' ); ?></p>
			<div class="wkc-repeater" data-template="wkc-tpl-cat">
				<div class="wkc-rows">
					<?php
					foreach ( $c['categories'] as $i => $row ) {
						echo self::category_row( $i, $row ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
				<button type="button" class="button wkc-row-add"><?php esc_html_e( '+ Aggiungi linea prodotto', 'wookit-configurator' ); ?></button>
				<script type="text/html" id="wkc-tpl-cat"><?php echo self::category_row( '__INDEX__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
			</div>
			<?php self::toggle( 'include_children', __( 'Includi i prodotti delle sottocategorie', 'wookit-configurator' ), $c['include_children'] ); ?>
		</div>

		<div class="wkc-section wkc-grid-2">
			<div>
				<?php self::toggle( 'allow_duplicates', __( 'Consenti di scegliere lo stesso prodotto in più step', 'wookit-configurator' ), $c['allow_duplicates'] ); ?>
				<div class="wkc-field">
					<label for="wkc_preselect"><?php esc_html_e( 'Preselezione all\'apertura', 'wookit-configurator' ); ?></label>
					<select id="wkc_preselect" name="wkc[preselect]">
						<option value="none" <?php selected( 'none', $c['preselect'] ); ?>><?php esc_html_e( 'Nessuna', 'wookit-configurator' ); ?></option>
						<option value="category" <?php selected( 'category', $c['preselect'] ); ?>><?php esc_html_e( 'Prima linea prodotto', 'wookit-configurator' ); ?></option>
						<option value="product" <?php selected( 'product', $c['preselect'] ); ?>><?php esc_html_e( 'Prima linea e prima fragranza disponibile', 'wookit-configurator' ); ?></option>
					</select>
				</div>
			</div>
			<div class="wkc-field">
				<label for="wkc_after_add"><?php esc_html_e( 'Dopo "Aggiungi al carrello"', 'wookit-configurator' ); ?></label>
				<select id="wkc_after_add" name="wkc[after_add]">
					<option value="stay" <?php selected( 'stay', $c['after_add'] ); ?>><?php esc_html_e( 'Resta sulla pagina (messaggio + aggiornamento mini-carrello)', 'wookit-configurator' ); ?></option>
					<option value="cart" <?php selected( 'cart', $c['after_add'] ); ?>><?php esc_html_e( 'Vai al carrello', 'wookit-configurator' ); ?></option>
					<option value="checkout" <?php selected( 'checkout', $c['after_add'] ); ?>><?php esc_html_e( 'Vai al checkout', 'wookit-configurator' ); ?></option>
				</select>
			</div>
		</div>
		<?php if ( $is_new ) : ?>
			<p class="description"><em><?php esc_html_e( 'I testi sono precompilati con l\'esempio Christmas Gift Set: modificali a piacere.', 'wookit-configurator' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	public static function box_content( $post ) {
		$c = WKC_Kit::get_config( $post->ID );
		?>
		<div class="wkc-tabs">
			<nav class="wkc-tab-nav">
				<a href="#wkc-tab-hero" class="is-active"><?php esc_html_e( '1 · Hero', 'wookit-configurator' ); ?></a>
				<a href="#wkc-tab-config"><?php esc_html_e( '2 · Configuratore', 'wookit-configurator' ); ?></a>
				<a href="#wkc-tab-summary"><?php esc_html_e( '3 · Riepilogo', 'wookit-configurator' ); ?></a>
				<a href="#wkc-tab-story"><?php esc_html_e( '4 · Sezione finale', 'wookit-configurator' ); ?></a>
			</nav>

			<div id="wkc-tab-hero" class="wkc-tab is-active">
				<?php self::toggle( 'show_hero', __( 'Mostra sezione hero', 'wookit-configurator' ), $c['show_hero'] ); ?>
				<div class="wkc-grid-2">
					<div>
						<?php
						self::field_text( 'hero_title', __( 'Titolo', 'wookit-configurator' ), $c['hero_title'], array( 'textarea' => true, 'rows' => 2 ) );
						self::field_text( 'hero_text', __( 'Testo', 'wookit-configurator' ), $c['hero_text'], array( 'textarea' => true, 'rows' => 4, 'desc' => __( 'Consentito HTML semplice (grassetto, link).', 'wookit-configurator' ) ) );
						?>
					</div>
					<?php self::image_picker( 'wkc[hero_image]', $c['hero_image'], __( 'Immagine hero (sinistra)', 'wookit-configurator' ) ); ?>
				</div>
			</div>

			<div id="wkc-tab-config" class="wkc-tab">
				<p class="description"><?php esc_html_e( 'Titoli e descrizioni dei singoli step si impostano in "Composizione del kit".', 'wookit-configurator' ); ?></p>
				<div class="wkc-grid-3">
					<?php
					self::field_text( 'label_line', __( 'Etichetta scelta linea', 'wookit-configurator' ), $c['label_line'] );
					self::field_text( 'label_fragrance', __( 'Etichetta scelta fragranza', 'wookit-configurator' ), $c['label_fragrance'] );
					self::field_text( 'label_soldout', __( 'Etichetta esaurito', 'wookit-configurator' ), $c['label_soldout'] );
					?>
				</div>
			</div>

			<div id="wkc-tab-summary" class="wkc-tab">
				<div class="wkc-grid-2">
					<div>
						<?php
						self::field_text( 'summary_title', __( 'Titolo riepilogo', 'wookit-configurator' ), $c['summary_title'] );
						self::field_text( 'box_label', __( 'Etichetta confezione', 'wookit-configurator' ), $c['box_label'], array( 'placeholder' => 'GIFT SET' ) );
						self::field_text( 'box_name', __( 'Nome confezione', 'wookit-configurator' ), $c['box_name'], array( 'placeholder' => __( 'Vuoto = nome del prodotto confezione', 'wookit-configurator' ) ) );
						self::field_text( 'label_empty', __( 'Testo step non ancora scelto', 'wookit-configurator' ), $c['label_empty'] );
						self::field_text( 'label_edit', __( 'Link modifica', 'wookit-configurator' ), $c['label_edit'] );
						self::field_text( 'label_subtotal', __( 'Etichetta subtotale', 'wookit-configurator' ), $c['label_subtotal'] );
						self::field_text( 'button_label', __( 'Testo pulsante', 'wookit-configurator' ), $c['button_label'] );
						self::field_text( 'label_added', __( 'Messaggio aggiunto al carrello', 'wookit-configurator' ), $c['label_added'] );
						self::field_text( 'label_view_cart', __( 'Link al carrello', 'wookit-configurator' ), $c['label_view_cart'] );
						?>
					</div>
					<?php self::image_picker( 'wkc[summary_image]', $c['summary_image'], __( 'Immagine accanto al riepilogo', 'wookit-configurator' ) ); ?>
				</div>
			</div>

			<div id="wkc-tab-story" class="wkc-tab">
				<?php self::toggle( 'show_story', __( 'Mostra sezione finale', 'wookit-configurator' ), $c['show_story'] ); ?>
				<div class="wkc-grid-2">
					<div>
						<?php
						self::field_text( 'story_title', __( 'Titolo', 'wookit-configurator' ), $c['story_title'], array( 'textarea' => true, 'rows' => 3 ) );
						self::field_text( 'story_text', __( 'Testo', 'wookit-configurator' ), $c['story_text'], array( 'textarea' => true, 'rows' => 5 ) );
						?>
					</div>
					<?php self::image_picker( 'wkc[story_image]', $c['story_image'], __( 'Immagine (destra)', 'wookit-configurator' ) ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function box_colors( $post ) {
		$c      = WKC_Kit::get_config( $post->ID );
		$colors = array(
			'color_accent'     => __( 'Sfondo sezioni (hero/finale)', 'wookit-configurator' ),
			'color_accent_txt' => __( 'Testo sezioni', 'wookit-configurator' ),
			'color_selected'   => __( 'Bordo selezione', 'wookit-configurator' ),
			'color_button'     => __( 'Sfondo pulsante', 'wookit-configurator' ),
			'color_button_txt' => __( 'Testo pulsante', 'wookit-configurator' ),
		);
		foreach ( $colors as $key => $label ) {
			echo '<div class="wkc-field"><label>' . esc_html( $label ) . '</label><input type="text" class="wkc-color" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $c[ $key ] ) . '" data-default-color="' . esc_attr( WKC_Kit::defaults()[ $key ] ) . '"></div>';
		}
	}

	public static function box_publish( $post ) {
		echo '<p>' . esc_html__( 'Shortcode da inserire in una pagina o in un widget Elementor:', 'wookit-configurator' ) . '</p>';
		echo '<input type="text" readonly class="widefat code" onclick="this.select()" value="' . esc_attr( '[wookit_kit id="' . $post->ID . '"]' ) . '">';
		if ( 'publish' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Oppure usa la landing page dedicata:', 'wookit-configurator' ) . '<br><a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank">' . esc_html( get_permalink( $post ) ) . '</a></p>';
		}
		$c = WKC_Kit::get_config( $post->ID );
		if ( empty( $c['box_product'] ) ) {
			echo '<p class="wkc-warn">' . esc_html__( 'Attenzione: seleziona il prodotto confezione.', 'wookit-configurator' ) . '</p>';
		}
		if ( empty( $c['categories'] ) ) {
			echo '<p class="wkc-warn">' . esc_html__( 'Attenzione: aggiungi almeno una linea prodotto.', 'wookit-configurator' ) . '</p>';
		} else {
			$lines = WKC_Kit::get_lines_data( $post->ID );
			echo '<ul class="wkc-stats">';
			foreach ( $lines as $line ) {
				/* translators: 1: line name 2: number of products */
				echo '<li>' . esc_html( sprintf( __( '%1$s: %2$d fragranze', 'wookit-configurator' ), $line['label'], count( $line['products'] ) ) ) . '</li>';
			}
			echo '</ul>';
		}
	}

	/* ---------------------------------------------------------------------
	 * Salvataggio
	 * ------------------------------------------------------------------- */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['wkc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wkc_nonce'] ) ), 'wkc_save_kit' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in       = isset( $_POST['wkc'] ) ? wp_unslash( $_POST['wkc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$in       = is_array( $in ) ? $in : array();
		$defaults = WKC_Kit::defaults();
		$m        = WKC_Kit::META;

		$plain   = array( 'label_line', 'label_fragrance', 'label_soldout', 'summary_title', 'box_label', 'box_name', 'label_empty', 'label_edit', 'label_subtotal', 'button_label', 'label_added', 'label_view_cart' );
		$rich    = array( 'hero_title', 'hero_text', 'story_title', 'story_text' );
		$images  = array( 'hero_image', 'summary_image', 'story_image' );
		$toggles = array( 'include_children', 'allow_duplicates', 'show_hero', 'show_story' );
		$colors  = array( 'color_accent', 'color_accent_txt', 'color_button', 'color_button_txt', 'color_selected' );

		foreach ( $plain as $k ) {
			update_post_meta( $post_id, $m . $k, isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '' );
		}
		foreach ( $rich as $k ) {
			update_post_meta( $post_id, $m . $k, isset( $in[ $k ] ) ? wp_kses_post( $in[ $k ] ) : '' );
		}
		foreach ( $images as $k ) {
			update_post_meta( $post_id, $m . $k, isset( $in[ $k ] ) ? absint( $in[ $k ] ) : 0 );
		}
		foreach ( $toggles as $k ) {
			update_post_meta( $post_id, $m . $k, ( isset( $in[ $k ] ) && 'yes' === $in[ $k ] ) ? 'yes' : 'no' );
		}
		foreach ( $colors as $k ) {
			$color = isset( $in[ $k ] ) ? sanitize_hex_color( $in[ $k ] ) : '';
			update_post_meta( $post_id, $m . $k, $color ? $color : $defaults[ $k ] );
		}

		update_post_meta( $post_id, $m . 'box_product', isset( $in['box_product'] ) ? absint( $in['box_product'] ) : 0 );
		$preselect = isset( $in['preselect'] ) && in_array( $in['preselect'], array( 'none', 'category', 'product' ), true ) ? $in['preselect'] : 'category';
		update_post_meta( $post_id, $m . 'preselect', $preselect );
		$after = isset( $in['after_add'] ) && in_array( $in['after_add'], array( 'stay', 'cart', 'checkout' ), true ) ? $in['after_add'] : 'stay';
		update_post_meta( $post_id, $m . 'after_add', $after );

		// Categorie.
		$cats = array();
		if ( ! empty( $in['categories'] ) && is_array( $in['categories'] ) ) {
			foreach ( $in['categories'] as $row ) {
				$term_id = isset( $row['term_id'] ) ? absint( $row['term_id'] ) : 0;
				if ( ! $term_id ) {
					continue;
				}
				$cats[] = array(
					'term_id'  => $term_id,
					'label'    => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
					'subtitle' => isset( $row['subtitle'] ) ? sanitize_text_field( $row['subtitle'] ) : '',
					'image_id' => isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0,
				);
			}
		}
		update_post_meta( $post_id, $m . 'categories', $cats );

		// Step.
		$slots = array();
		if ( ! empty( $in['slots'] ) && is_array( $in['slots'] ) ) {
			foreach ( $in['slots'] as $row ) {
				$slots[] = array(
					'title'       => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'description' => isset( $row['description'] ) ? sanitize_text_field( $row['description'] ) : '',
				);
			}
		}
		if ( empty( $slots ) ) {
			$slots = $defaults['slots'];
		}
		update_post_meta( $post_id, $m . 'slots', $slots );

		wp_cache_delete( 'wkc_box_ids', 'wkc' );
	}

	/* ---------------------------------------------------------------------
	 * Colonne lista
	 * ------------------------------------------------------------------- */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['wkc_box']       = __( 'Confezione', 'wookit-configurator' );
				$new['wkc_slots']     = __( 'Prodotti', 'wookit-configurator' );
				$new['wkc_shortcode'] = __( 'Shortcode', 'wookit-configurator' );
			}
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		$c = WKC_Kit::get_config( $post_id );
		switch ( $col ) {
			case 'wkc_box':
				$p = $c['box_product'] ? wc_get_product( $c['box_product'] ) : null;
				echo $p ? esc_html( $p->get_name() ) : '—';
				break;
			case 'wkc_slots':
				/* translators: 1: number of products 2: number of lines */
				echo esc_html( sprintf( __( '%1$d prodotti · %2$d linee', 'wookit-configurator' ), count( $c['slots'] ), count( $c['categories'] ) ) );
				break;
			case 'wkc_shortcode':
				echo '<code>[wookit_kit id="' . (int) $post_id . '"]</code>';
				break;
		}
	}
}
