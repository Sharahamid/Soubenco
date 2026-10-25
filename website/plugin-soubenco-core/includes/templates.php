<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Website price block. In the app, JavaScript swaps in the app price (never sent to website visitors). */
function sb_price_html( $product, $size = 'md' ) {
	$d = sb_price_data( $product );
	if ( ! $d || ! $d['web'] ) return '<div class="sb-price sb-price-' . esc_attr( $size ) . '"><span class="sb-price-soon">Price coming soon</span></div>';
	$html  = '<div class="sb-price sb-price-' . esc_attr( $size ) . '" data-sb-price data-pid="' . (int) $product->get_id() . '">';
	$html .= '<span class="sb-price-now">' . sb_money( $d['web'] ) . '</span>';
	if ( $d['on_sale'] ) $html .= '<del class="sb-price-was">' . sb_money( $d['regular'] ) . '</del>';
	$html .= '</div>';
	return $html;
}

function sb_festive_chip( $f, $class = '' ) {
	if ( ! $f ) return '';
	return '<span class="sb-festive-chip sb-fest-' . esc_attr( $f['key'] ) . ' ' . esc_attr( $class ) . '"><i>' . sb_festive_art( $f['key'] ) . '</i>' . esc_html( $f['tag'] ) . '</span>';
}

/** Big festive banner used on the product page. */
function sb_festive_banner( $product ) {
	$d = sb_price_data( $product );
	if ( ! $d || ! $d['festive'] ) return '';
	$f = $d['festive'];
	$save = $d['regular'] - $d['web'];
	ob_start(); ?>
	<div class="sb-festive-banner sb-fest-<?php echo esc_attr( $f['key'] ); ?>">
		<div class="sb-festive-art"><?php echo sb_festive_art( $f['key'] ); // phpcs:ignore ?></div>
		<div class="sb-festive-copy">
			<span class="sb-festive-kicker"><?php echo esc_html( $f['label'] ); ?></span>
			<strong><?php echo esc_html( $f['note'] ? $f['note'] : $f['tag'] ); ?></strong>
			<span class="sb-festive-save">Save <?php echo esc_html( sb_money( $save ) ); ?> · <?php echo (int) $d['pct']; ?>% off</span>
		</div>
		<div class="sb-festive-pattern" aria-hidden="true"></div>
	</div>
	<?php
	return ob_get_clean();
}

/** Product card used everywhere (home, shop, wishlist, related). */
function sb_product_card( $product ) {
	if ( is_numeric( $product ) ) $product = wc_get_product( $product );
	if ( ! $product ) return '';
	$d      = sb_price_data( $product );
	$avail  = sb_product_available( $product );
	$colors = sb_product_colors( $product );
	$brand  = sb_product_brand( $product );
	$wished = is_user_logged_in() && sb_in_wishlist( $product->get_id() );
	$img    = $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'alt' => esc_attr( $product->get_name() ) ) ) : '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="" loading="lazy">';
	ob_start(); ?>
	<article class="sb-card<?php echo $avail ? '' : ( $d && $d['web'] ? ' is-out' : ' is-soon' ); ?>" data-pid="<?php echo (int) $product->get_id(); ?>">
		<div class="sb-card-top">
			<div class="sb-card-tags">
				<?php if ( ! $avail ) : ?><span class="sb-chip <?php echo $d && $d['web'] ? 'sb-chip-out' : 'sb-chip-soon'; ?>"><?php echo $d && $d['web'] ? 'Out of stock' : 'Coming soon'; ?></span><?php endif; ?>
				<?php if ( $avail && $d['pct'] > 0 ) : ?><span class="sb-chip sb-chip-off">-<?php echo (int) $d['pct']; ?>%</span><?php endif; ?>
				<?php if ( $avail ) echo sb_festive_chip( $d['festive'] ); // phpcs:ignore ?>
			</div>
			<button type="button" class="sb-wish<?php echo $wished ? ' is-on' : ''; ?>" data-sb-wish="<?php echo (int) $product->get_id(); ?>" aria-label="Save to wishlist"><?php echo sb_icon( 'heart', 18 ); // phpcs:ignore ?></button>
		</div>
		<a class="sb-card-media" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo $img; // phpcs:ignore ?></a>
		<div class="sb-card-body">
			<?php if ( $brand ) : ?><span class="sb-card-brand"><?php echo esc_html( $brand ); ?></span><?php endif; ?>
			<h3 class="sb-card-title"><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
			<?php if ( $colors ) : ?>
				<div class="sb-card-colors" aria-label="Available colors">
					<?php foreach ( array_slice( $colors, 0, 5 ) as $c ) : ?><span style="--c:<?php echo esc_attr( $c['hex'] ); ?>" title="<?php echo esc_attr( $c['name'] ); ?>"></span><?php endforeach; ?>
					<?php if ( count( $colors ) > 5 ) : ?><em>+<?php echo count( $colors ) - 5; ?></em><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php echo sb_price_html( $product ); // phpcs:ignore ?>
			<div class="sb-card-actions">
				<?php if ( $avail ) : ?>
					<button type="button" class="sb-btn sb-btn-primary sb-btn-block" data-sb-add="<?php echo (int) $product->get_id(); ?>"><?php echo sb_icon( 'cart', 17 ); // phpcs:ignore ?><span>Add to cart</span></button>
				<?php else : ?>
					<button type="button" class="sb-btn sb-btn-ghost sb-btn-block" data-sb-notify="<?php echo (int) $product->get_id(); ?>"><?php echo sb_icon( 'bell', 17 ); // phpcs:ignore ?><span>Notify me</span></button>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

function sb_product_grid( $ids, $class = '' ) {
	if ( ! $ids ) return '';
	$out = '<div class="sb-grid ' . esc_attr( $class ) . '">';
	foreach ( $ids as $id ) $out .= sb_product_card( $id );
	return $out . '</div>';
}

/** Track recently viewed products for signed-in customers (profile history). */
add_action( 'template_redirect', function () {
	if ( ! is_singular( 'product' ) || ! is_user_logged_in() ) return;
	$uid = get_current_user_id();
	$ids = (array) get_user_meta( $uid, 'sb_recent', true );
	$pid = get_queried_object_id();
	$ids = array_values( array_diff( array_map( 'intval', $ids ), array( $pid ) ) );
	array_unshift( $ids, $pid );
	update_user_meta( $uid, 'sb_recent', array_slice( $ids, 0, 24 ) );
} );
