<?php
defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) continue;
	$d       = sb_price_data( $product );
	$avail   = sb_product_available( $product );
	$colors  = sb_product_colors( $product );
	$specs   = sb_product_specs( $product );
	$brand   = sb_product_brand( $product );
	$model   = $product->get_meta( '_sb_model' );
	$dims    = $product->get_meta( '_sb_dimensions' );
	$weight  = $product->get_weight();
	$images  = array_values( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );
	$wished  = is_user_logged_in() && sb_in_wishlist( $product->get_id() );
	$cats    = wc_get_product_terms( $product->get_id(), 'product_cat' );
	$max     = $product->managing_stock() && ! $product->backorders_allowed() ? max( 1, (int) $product->get_stock_quantity() ) : 99;
	$related = array_slice( wc_get_related_products( $product->get_id(), 8 ), 0, 4 );
	?>
	<div class="sb-container sb-pdp">
		<?php sbt_breadcrumbs(); ?>

		<div class="sb-pdp-grid">
			<div class="sb-gallery" data-sb-gallery>
				<div class="sb-gallery-main">
					<?php if ( $images ) : foreach ( $images as $i => $img_id ) : ?>
						<figure class="<?php echo 0 === $i ? 'is-active' : ''; ?>" data-i="<?php echo (int) $i; ?>">
							<?php echo wp_get_attachment_image( $img_id, 'woocommerce_single', false, array( 'loading' => 0 === $i ? 'eager' : 'lazy', 'alt' => esc_attr( $product->get_name() ) ) ); ?>
						</figure>
					<?php endforeach; else : ?>
						<figure class="is-active"><img src="<?php echo esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ); ?>" alt=""></figure>
					<?php endif; ?>
				</div>
				<?php if ( count( $images ) > 1 ) : ?>
					<div class="sb-gallery-thumbs">
						<?php foreach ( $images as $i => $img_id ) : ?>
							<button type="button" class="<?php echo 0 === $i ? 'is-active' : ''; ?>" data-i="<?php echo (int) $i; ?>" aria-label="Photo <?php echo (int) $i + 1; ?>"><?php echo wp_get_attachment_image( $img_id, 'woocommerce_gallery_thumbnail' ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="sb-pdp-info">
				<div class="sb-pdp-meta">
					<?php if ( $brand ) : ?><span class="sb-card-brand"><?php echo esc_html( $brand ); ?></span><?php endif; ?>
					<?php if ( $model ) : ?><span class="sb-muted">Model: <?php echo esc_html( $model ); ?></span><?php endif; ?>
				</div>
				<h1 class="sb-pdp-title"><?php the_title(); ?></h1>

				<div class="sb-pdp-status">
					<?php if ( $avail ) : ?>
						<span class="sb-stock sb-stock-in"><?php echo sb_icon( 'check', 15 ); ?> In stock<?php echo $product->managing_stock() && $product->get_stock_quantity() <= 5 ? ' · only ' . (int) $product->get_stock_quantity() . ' left' : ''; ?></span>
					<?php else : ?>
						<span class="sb-stock sb-stock-out"><?php echo $d['web'] ? 'Currently out of stock' : 'Price coming soon'; ?></span>
					<?php endif; ?>
					<?php if ( $avail && $d['pct'] ) : ?><span class="sb-chip sb-chip-off">Save <?php echo (int) $d['pct']; ?>%</span><?php endif; ?>
				</div>

				<?php if ( $avail ) echo sb_festive_banner( $product ); // phpcs:ignore ?>

				<div class="sb-pdp-price">
					<?php echo sb_price_html( $product, 'lg' ); // phpcs:ignore ?>
					<?php if ( $d['on_sale'] && $product->get_date_on_sale_to() ) : ?>
						<span class="sb-offer-ends"><?php echo sb_icon( 'clock', 15 ); ?> Offer ends <?php echo esc_html( $product->get_date_on_sale_to()->date_i18n( 'j M Y' ) ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( $product->get_short_description() ) : ?>
					<div class="sb-pdp-short"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
				<?php endif; ?>

				<form class="sb-buy" data-sb-buy="<?php echo (int) $product->get_id(); ?>">
					<?php if ( $colors ) : ?>
						<div class="sb-buy-row">
							<span class="sb-buy-label">Color: <strong data-sb-color-label><?php echo esc_html( $colors[0]['name'] ); ?></strong></span>
							<div class="sb-swatches" role="radiogroup" aria-label="Color">
								<?php foreach ( $colors as $i => $c ) : ?>
									<label class="sb-swatch">
										<input type="radio" name="color" value="<?php echo esc_attr( $c['name'] ); ?>" <?php checked( 0, $i ); ?>>
										<span style="--c:<?php echo esc_attr( $c['hex'] ); ?>"></span><em><?php echo esc_html( $c['name'] ); ?></em>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $avail ) : ?>
						<div class="sb-buy-row sb-buy-actions">
							<div class="sb-qty" data-sb-qty>
								<button type="button" data-step="-1" aria-label="Less"><?php echo sb_icon( 'minus', 16 ); ?></button>
								<input type="number" name="qty" value="1" min="1" max="<?php echo (int) $max; ?>" aria-label="Quantity">
								<button type="button" data-step="1" aria-label="More"><?php echo sb_icon( 'plus', 16 ); ?></button>
							</div>
							<button type="submit" class="sb-btn sb-btn-primary sb-btn-lg sb-grow" data-action="add"><?php echo sb_icon( 'cart', 19 ); ?> Add to cart</button>
							<button type="submit" class="sb-btn sb-btn-dark sb-btn-lg sb-grow" data-action="buy">Buy now</button>
						</div>
					<?php else : ?>
						<div class="sb-buy-row sb-buy-actions">
							<button type="button" class="sb-btn sb-btn-primary sb-btn-lg sb-grow" data-sb-notify="<?php echo (int) $product->get_id(); ?>"><?php echo sb_icon( 'bell', 19 ); ?> Notify me when available</button>
						</div>
					<?php endif; ?>
					<button type="button" class="sb-wish-line<?php echo $wished ? ' is-on' : ''; ?>" data-sb-wish="<?php echo (int) $product->get_id(); ?>"><?php echo sb_icon( 'heart', 18 ); ?><span><?php echo $wished ? 'Saved to wishlist' : 'Save to wishlist'; ?></span></button>
				</form>

				<div class="sb-pdp-perks">
					<div><?php echo sb_icon( 'truck', 20 ); ?><span><strong>Delivery all over Bangladesh</strong>Charge shown at checkout based on your district</span></div>
					<div><?php echo sb_icon( 'shield', 20 ); ?><span><strong>Secure payment</strong>SSL-protected online checkout</span></div>
					<div><?php echo sb_icon( 'badge', 20 ); ?><span><strong>Genuine product</strong>Sourced from trusted suppliers</span></div>
				</div>
			</div>
		</div>

		<div class="sb-pdp-tabs" data-sb-tabs>
			<div class="sb-tabs" role="tablist">
				<?php if ( $product->get_description() ) : ?><button type="button" class="sb-tab is-active" data-tab="desc">Description</button><?php endif; ?>
				<?php if ( $specs ) : ?><button type="button" class="sb-tab<?php echo ! $product->get_description() ? ' is-active' : ''; ?>" data-tab="specs">Specifications</button><?php endif; ?>
				<?php if ( $dims || $weight || $colors ) : ?><button type="button" class="sb-tab<?php echo ! $product->get_description() && ! $specs ? ' is-active' : ''; ?>" data-tab="size">Size & colors</button><?php endif; ?>
			</div>
			<?php if ( $product->get_description() ) : ?>
				<div class="sb-tab-panel sb-content is-active" data-panel="desc"><?php echo wp_kses_post( wpautop( $product->get_description() ) ); ?></div>
			<?php endif; ?>
			<?php if ( $specs ) : ?>
				<div class="sb-tab-panel<?php echo ! $product->get_description() ? ' is-active' : ''; ?>" data-panel="specs">
					<table class="sb-specs"><tbody>
						<?php if ( $brand ) : ?><tr><th>Brand</th><td><?php echo esc_html( $brand ); ?></td></tr><?php endif; ?>
						<?php if ( $model ) : ?><tr><th>Model</th><td><?php echo esc_html( $model ); ?></td></tr><?php endif; ?>
						<?php foreach ( $specs as $s ) : ?><tr><th><?php echo esc_html( $s['label'] ); ?></th><td><?php echo esc_html( $s['value'] ); ?></td></tr><?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>
			<?php if ( $dims || $weight || $colors ) : ?>
				<div class="sb-tab-panel<?php echo ! $product->get_description() && ! $specs ? ' is-active' : ''; ?>" data-panel="size">
					<table class="sb-specs"><tbody>
						<?php if ( $dims ) : ?><tr><th>Dimensions</th><td><?php echo esc_html( $dims ); ?></td></tr><?php endif; ?>
						<?php if ( $weight ) : ?><tr><th>Weight</th><td><?php echo esc_html( wc_format_localized_decimal( $weight ) ); ?> kg</td></tr><?php endif; ?>
						<?php if ( $colors ) : ?><tr><th>Colors</th><td><div class="sb-color-list"><?php foreach ( $colors as $c ) : ?><span><i style="--c:<?php echo esc_attr( $c['hex'] ); ?>"></i><?php echo esc_html( $c['name'] ); ?></span><?php endforeach; ?></div></td></tr><?php endif; ?>
					</tbody></table>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $related ) : ?>
			<section class="sb-section">
				<?php sbt_section_head( 'You may also like', '', $cats ? get_term_link( $cats[0] ) : '' ); ?>
				<?php echo sb_product_grid( $related, 'sb-grid-4' ); // phpcs:ignore ?>
			</section>
		<?php endif; ?>
	</div>

	<?php if ( $avail ) : ?>
	<div class="sb-sticky-buy" data-sb-sticky-buy>
		<div><strong><?php echo esc_html( $product->get_name() ); ?></strong><?php echo sb_price_html( $product, 'sm' ); // phpcs:ignore ?></div>
		<button type="button" class="sb-btn sb-btn-primary" data-sb-sticky-add><?php echo sb_icon( 'cart', 18 ); ?> Add to cart</button>
	</div>
	<?php endif; ?>

	<script type="application/ld+json"><?php echo wp_json_encode( array(
		'@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->get_name(),
		'image' => $images ? wp_get_attachment_image_url( $images[0], 'large' ) : '', 'sku' => $product->get_sku(),
		'brand' => $brand ? array( '@type' => 'Brand', 'name' => $brand ) : null,
		'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
		'offers' => array( '@type' => 'Offer', 'priceCurrency' => 'BDT', 'price' => $d['web'], 'availability' => $avail ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => get_permalink() ),
	) ); ?></script>
	<?php
endwhile;
get_footer();
