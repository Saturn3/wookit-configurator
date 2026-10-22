<?php
/**
 * Template landing page del kit.
 * Sovrascrivibile copiandolo in: tuo-tema/wookit/landing.php
 *
 * Variabili disponibili: $kit_id, $post, $config, $lines, $box, $uid, $js, $style, $classes.
 *
 * @package WooKitConfigurator
 */

defined( 'ABSPATH' ) || exit;

$wkc_slots_n = count( $config['slots'] );
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" data-wkc>
	<script type="application/json" class="wkc-data"><?php echo wp_json_encode( $js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>

	<?php if ( 'yes' === $config['show_hero'] ) : ?>
		<section class="wkc-hero wkc-band">
			<div class="wkc-hero__media">
				<?php
				if ( $config['hero_image'] ) {
					echo wp_get_attachment_image(
						(int) $config['hero_image'],
						'full',
						false,
						array(
							'class'         => 'wkc-hero__img',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'sizes'         => '(max-width: 768px) 100vw, 55vw',
						)
					);
				}
				?>
			</div>
			<div class="wkc-hero__content">
				<div class="wkc-hero__inner">
					<?php if ( $config['hero_title'] ) : ?>
						<h1 class="wkc-hero__title"><?php echo WKC_Frontend::rich( $config['hero_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h1>
					<?php endif; ?>
					<?php if ( $config['hero_text'] ) : ?>
						<div class="wkc-hero__text"><?php echo WKC_Frontend::rich( $config['hero_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="wkc-builder">
		<div class="wkc-container">
			<div class="wkc-slots wkc-slots--<?php echo (int) min( $wkc_slots_n, 3 ); ?>">
				<?php foreach ( $config['slots'] as $i => $slot ) : ?>
					<div class="wkc-slot" data-slot="<?php echo (int) $i; ?>" id="<?php echo esc_attr( $uid . '-slot-' . $i ); ?>">
						<?php if ( ! empty( $slot['title'] ) ) : ?>
							<h2 class="wkc-slot__title"><?php echo esc_html( $slot['title'] ); ?></h2>
						<?php endif; ?>
						<?php if ( ! empty( $slot['description'] ) ) : ?>
							<p class="wkc-slot__desc"><?php echo esc_html( $slot['description'] ); ?></p>
						<?php endif; ?>

						<div class="wkc-label"><?php echo esc_html( $config['label_line'] ); ?></div>
						<div class="wkc-lines" role="radiogroup" aria-label="<?php echo esc_attr( $config['label_line'] ); ?>">
							<?php foreach ( $lines as $line ) : ?>
								<div class="wkc-line" role="radio" tabindex="0" aria-checked="false" data-line="<?php echo (int) $line['id']; ?>">
									<span class="wkc-line__media"><img src="<?php echo esc_url( $line['image'] ); ?>" alt="" loading="lazy"></span>
									<span class="wkc-line__name"><?php echo esc_html( $line['label'] ); ?></span>
									<?php if ( $line['subtitle'] ) : ?>
										<span class="wkc-line__sub"><?php echo esc_html( $line['subtitle'] ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="wkc-fragrance-block" hidden>
							<div class="wkc-label"><?php echo esc_html( $config['label_fragrance'] ); ?></div>
							<div class="wkc-carousel">
								<span role="button" tabindex="0" class="wkc-carousel__nav wkc-carousel__prev" aria-label="<?php esc_attr_e( 'Precedente', 'wookit-configurator' ); ?>" hidden>
									<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
								</span>
								<div class="wkc-fragrances" role="radiogroup" aria-label="<?php echo esc_attr( $config['label_fragrance'] ); ?>"></div>
								<span role="button" tabindex="0" class="wkc-carousel__nav wkc-carousel__next" aria-label="<?php esc_attr_e( 'Successivo', 'wookit-configurator' ); ?>" hidden>
									<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
								</span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="wkc-summary">
				<div class="wkc-summary__main">
					<?php if ( $config['summary_title'] ) : ?>
						<h2 class="wkc-summary__title"><?php echo esc_html( $config['summary_title'] ); ?></h2>
					<?php endif; ?>
					<ul class="wkc-summary__list"></ul>
					<div class="wkc-summary__total">
						<span><?php echo esc_html( $config['label_subtotal'] ); ?></span>
						<span class="wkc-summary__amount">—</span>
					</div>
					<button type="button" class="wkc-add" disabled><?php echo esc_html( $config['button_label'] ); ?></button>
					<div class="wkc-notice" role="status" aria-live="polite" hidden></div>
				</div>
				<div class="wkc-summary__media">
					<?php
					echo WKC_Frontend::image( $config['summary_image'], 'large', 'wkc-summary__img' ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
			</div>
		</div>
	</section>

	<?php if ( 'yes' === $config['show_story'] ) : ?>
		<section class="wkc-story wkc-band">
			<div class="wkc-story__content">
				<div class="wkc-story__inner">
					<?php if ( $config['story_title'] ) : ?>
						<h2 class="wkc-story__title"><?php echo WKC_Frontend::rich( $config['story_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></h2>
					<?php endif; ?>
					<?php if ( $config['story_text'] ) : ?>
						<div class="wkc-story__text"><?php echo WKC_Frontend::rich( $config['story_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php endif; ?>
				</div>
			</div>
			<div class="wkc-story__media">
				<?php echo WKC_Frontend::image( $config['story_image'], 'full', 'wkc-story__img' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</section>
	<?php endif; ?>
</div>
