<?php
/**
 * Impostazioni globali del plugin (WooCommerce → Kit Configurator).
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

class WKC_Settings {

	const OPTION = 'wkc_settings';

	/**
	 * Valori predefiniti.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'meta_fragrance_name'    => 'nome_fragranza',
			'meta_fragrance_image'   => 'anteprima_fragranza',
			'meta_fragrance_notes'   => 'note_fragranza',
			'hide_without_fragrance' => 'yes',
			'box_only_in_kit'        => 'yes',
			'slug'                   => 'kit',
			'heading_font'           => '',
			'full_width'             => 'yes',
		);
	}

	/**
	 * Restituisce una impostazione.
	 *
	 * @param string $key Chiave.
	 * @return mixed
	 */
	public static function get( $key ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'maybe_flush' ), 10, 2 );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . WKC_Kit::POST_TYPE,
			__( 'Impostazioni Kit', 'wookit-configurator' ),
			__( 'Impostazioni', 'wookit-configurator' ),
			'manage_woocommerce',
			'wkc-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			'wkc_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitizzazione impostazioni.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		$out   = self::defaults();
		foreach ( array( 'meta_fragrance_name', 'meta_fragrance_image', 'meta_fragrance_notes' ) as $k ) {
			$out[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( wp_unslash( $input[ $k ] ) ) : '';
		}
		foreach ( array( 'hide_without_fragrance', 'box_only_in_kit', 'full_width' ) as $k ) {
			$out[ $k ] = ! empty( $input[ $k ] ) ? 'yes' : 'no';
		}
		$out['slug']         = ! empty( $input['slug'] ) ? sanitize_title( wp_unslash( $input['slug'] ) ) : 'kit';
		$out['heading_font'] = isset( $input['heading_font'] ) ? preg_replace( '/[^a-zA-Z0-9 ,\'"\-]/', '', wp_unslash( $input['heading_font'] ) ) : '';
		return $out;
	}

	public static function maybe_flush( $old, $new ) {
		if ( ( $old['slug'] ?? '' ) !== ( $new['slug'] ?? '' ) ) {
			update_option( 'wkc_flush_rewrite', 1 );
		}
	}

	/**
	 * Chiavi meta presenti sui prodotti (per suggerimento nel campo).
	 *
	 * @return string[]
	 */
	protected static function detected_meta_keys() {
		global $wpdb;
		$keys = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type IN ('product','product_variation')
			 AND pm.meta_key NOT LIKE '\\_%'
			 ORDER BY pm.meta_key ASC LIMIT 300"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $keys ) ? $keys : array();
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o    = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$keys = self::detected_meta_keys();
		$name = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kit Configurator – Impostazioni', 'wookit-configurator' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wkc_settings_group' ); ?>
				<datalist id="wkc-meta-keys">
					<?php foreach ( $keys as $k ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"></option>
					<?php endforeach; ?>
				</datalist>

				<h2><?php esc_html_e( 'Campi personalizzati della fragranza', 'wookit-configurator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Nel configuratore i prodotti vengono mostrati tramite la loro fragranza (nome + anteprima) letti da campi personalizzati del prodotto, non tramite nome e foto del prodotto. Compatibile con meta standard e campi ACF (immagine come ID, URL o array).', 'wookit-configurator' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wkc_mfn"><?php esc_html_e( 'Meta key – Nome fragranza', 'wookit-configurator' ); ?></label></th>
						<td><input id="wkc_mfn" type="text" class="regular-text code" list="wkc-meta-keys" name="<?php echo esc_attr( $name ); ?>[meta_fragrance_name]" value="<?php echo esc_attr( $o['meta_fragrance_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wkc_mfi"><?php esc_html_e( 'Meta key – Anteprima fragranza (immagine)', 'wookit-configurator' ); ?></label></th>
						<td><input id="wkc_mfi" type="text" class="regular-text code" list="wkc-meta-keys" name="<?php echo esc_attr( $name ); ?>[meta_fragrance_image]" value="<?php echo esc_attr( $o['meta_fragrance_image'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wkc_mfd"><?php esc_html_e( 'Meta key – Note olfattive (facoltativo)', 'wookit-configurator' ); ?></label></th>
						<td><input id="wkc_mfd" type="text" class="regular-text code" list="wkc-meta-keys" name="<?php echo esc_attr( $name ); ?>[meta_fragrance_notes]" value="<?php echo esc_attr( $o['meta_fragrance_notes'] ); ?>">
						<p class="description"><?php esc_html_e( 'Es. "Agrumata - Luminosa", mostrato sotto il nome della fragranza. Lascia vuoto per non mostrarlo.', 'wookit-configurator' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prodotti senza fragranza', 'wookit-configurator' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_without_fragrance]" value="1" <?php checked( 'yes', $o['hide_without_fragrance'] ); ?>> <?php esc_html_e( 'Escludi dal configuratore i prodotti che non hanno nome fragranza valorizzato', 'wookit-configurator' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Carrello e confezione', 'wookit-configurator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Confezione vendibile solo nel kit', 'wookit-configurator' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[box_only_in_kit]" value="1" <?php checked( 'yes', $o['box_only_in_kit'] ); ?>> <?php esc_html_e( 'Impedisci l\'acquisto separato dei prodotti usati come confezione nei kit pubblicati', 'wookit-configurator' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Landing page', 'wookit-configurator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wkc_slug"><?php esc_html_e( 'Slug URL dei kit', 'wookit-configurator' ); ?></label></th>
						<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="wkc_slug" type="text" class="small-text code" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( $o['slug'] ); ?>"><code>/christmas-gift-set/</code></td>
					</tr>
					<tr>
						<th scope="row"><label for="wkc_font"><?php esc_html_e( 'Font titoli', 'wookit-configurator' ); ?></label></th>
						<td><input id="wkc_font" type="text" class="regular-text" placeholder="Montserrat, sans-serif" name="<?php echo esc_attr( $name ); ?>[heading_font]" value="<?php echo esc_attr( $o['heading_font'] ); ?>">
						<p class="description"><?php esc_html_e( 'Vuoto = eredita il font del tema.', 'wookit-configurator' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sezioni a tutta larghezza', 'wookit-configurator' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[full_width]" value="1" <?php checked( 'yes', $o['full_width'] ); ?>> <?php esc_html_e( 'Estendi hero e sezione finale a tutta la larghezza dello schermo anche dentro contenitori del tema/Elementor', 'wookit-configurator' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
