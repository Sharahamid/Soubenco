<?php
get_header();
$shop     = wc_get_page_permalink( 'shop' );
$cats     = sbt_shop_categories();
$ready    = function ( $ids ) { return array_values( array_filter( $ids, function ( $id ) { $p = wc_get_product( $id ); return $p && sb_product_available( $p ) && $p->get_image_id(); } ) ); };
$featured = array_slice( $ready( sbt_product_ids( array( 'featured' => true, 'limit' => 20 ) ) ), 0, 5 );
if ( ! $featured ) $featured = array_slice( $ready( sbt_product_ids( array( 'limit' => 60, 'orderby' => 'date', 'order' => 'DESC' ) ) ), 0, 4 );
$sale_ids = wc_get_product_ids_on_sale();
$on_sale  = $sale_ids ? sbt_product_ids( array( 'include' => $sale_ids, 'limit' => 40, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
$festive  = array_slice( array_values( array_filter( $on_sale, function ( $id ) { $d = sb_price_data( $id ); return $d && $d['festive']; } ) ), 0, 8 );
$deals    = array_slice( array_values( array_diff( $on_sale, $festive ) ), 0, 8 );
$recent   = sbt_product_ids( array( 'limit' => 120, 'orderby' => 'date', 'order' => 'DESC' ) );
$newest   = array_slice( $ready( $recent ), 0, 8 );
if ( count( $newest ) < 4 ) {
	// Until prices are added, show the newest products that already have a photo.
	$with_img = array_values( array_filter( $recent, function ( $id ) { $p = wc_get_product( $id ); return $p && $p->get_image_id(); } ) );
	$newest   = array_slice( array_values( array_unique( array_merge( $newest, $with_img ) ) ), 0, 8 );
}
$total    = array_sum( wp_list_pluck( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) ) ?: array(), 'count' ) );
$brands   = taxonomy_exists( 'product_brand' ) ? get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => true, 'number' => 12, 'orderby' => 'count', 'order' => 'DESC', 'exclude' => sbt_hidden_brand_ids() ) ) : array();

// Group festive products by occasion so the heading matches the festival.
$fest_key = '';
if ( $festive ) {
	$d = sb_price_data( $festive[0] );
	$fest_key = $d && $d['festive'] ? $d['festive']['key'] : 'custom';
}
$fest_opts = sb_festive_options();
?>

<section class="sb-hero">
	<div class="sb-container sb-hero-grid">
		<div class="sb-hero-copy">
			<span class="sb-kicker"><?php echo sb_icon( 'sparkle', 15 ); ?> Electronics &amp; home appliances</span>
			<h1>Better tech for<br><span class="sb-grad-text">every home.</span></h1>
			<p>TVs, fridges, ACs, fans, kitchen appliances and gadgets from trusted brands — genuine products delivered anywhere in Bangladesh.</p>
			<div class="sb-hero-cta">
				<a class="sb-btn sb-btn-primary sb-btn-lg" href="<?php echo esc_url( $shop ); ?>">Shop now <?php echo sb_icon( 'chevron', 18 ); ?></a>
				<a class="sb-btn sb-btn-light sb-btn-lg" href="<?php echo esc_url( add_query_arg( 'sale', 1, $shop ) ); ?>"><?php echo sb_icon( 'tag', 18 ); ?> Today's deals</a>
			</div>
			<ul class="sb-hero-points">
				<li><?php echo sb_icon( 'truck', 18 ); ?> 64-district delivery</li>
				<li><?php echo sb_icon( 'shield', 18 ); ?> Secure checkout</li>
				<li><?php echo sb_icon( 'bell', 18 ); ?> Price-drop alerts</li>
			</ul>
		</div>

		<div class="sb-hero-visual">
			<?php if ( $featured ) : ?>
				<div class="sb-hero-slider" data-sb-slider>
					<?php foreach ( $featured as $i => $fid ) :
						$p = wc_get_product( $fid ); if ( ! $p ) continue; $pd = sb_price_data( $p ); ?>
						<article class="sb-hero-slide<?php echo 0 === $i ? ' is-active' : ''; ?>">
							<a class="sb-hero-media" href="<?php echo esc_url( get_permalink( $fid ) ); ?>"><?php echo $p->get_image( 'large', array( 'loading' => 0 === $i ? 'eager' : 'lazy' ) ); // phpcs:ignore ?></a>
							<div class="sb-hero-info">
								<div>
									<span class="sb-card-brand"><?php echo esc_html( sb_product_brand( $p ) ?: 'Featured' ); ?></span>
									<h3><a href="<?php echo esc_url( get_permalink( $fid ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a></h3>
									<?php echo sb_price_html( $p ); // phpcs:ignore ?>
								</div>
								<a class="sb-btn sb-btn-dark" href="<?php echo esc_url( get_permalink( $fid ) ); ?>">View</a>
							</div>
						</article>
					<?php endforeach; ?>
					<?php if ( count( $featured ) > 1 ) : ?>
						<div class="sb-dots"><?php foreach ( $featured as $i => $fid ) : ?><button type="button" class="<?php echo 0 === $i ? 'is-active' : ''; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button><?php endforeach; ?></div>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="sb-hero-art" aria-hidden="true">
					<div class="sb-art-ring"></div>
					<div class="sb-art-tile sb-art-1"><?php echo sbt_category_icon( 'television', 54 ); ?></div>
					<div class="sb-art-tile sb-art-2"><?php echo sbt_category_icon( 'refrigerator', 54 ); ?></div>
					<div class="sb-art-tile sb-art-3"><?php echo sbt_category_icon( 'ceiling-fan', 54 ); ?></div>
					<div class="sb-art-tile sb-art-4"><?php echo sbt_category_icon( 'phone', 54 ); ?></div>
					<img class="sb-art-mark" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/logo-mark.png' ); ?>" alt="">
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if ( $cats ) : ?>
<section class="sb-section">
	<div class="sb-container">
		<?php sbt_section_head( 'Shop by category', 'Find exactly what you are looking for.', $shop, 'All products' ); ?>
		<div class="sb-cats">
			<?php foreach ( $cats as $c ) : ?>
				<a class="sb-cat" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
					<span class="sb-cat-ic"><?php echo sbt_category_icon( $c->slug, 30 ); ?></span>
					<strong><?php echo esc_html( $c->name ); ?></strong>
					<small><?php echo esc_html( $c->count . ' ' . _n( 'item', 'items', $c->count ) ); ?></small>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $festive ) : ?>
<section class="sb-section">
	<div class="sb-container">
		<div class="sb-festive-head sb-fest-<?php echo esc_attr( $fest_key ); ?>">
			<span class="sb-festive-head-art"><?php echo sb_festive_art( $fest_key ); // phpcs:ignore ?></span>
			<div>
				<span class="sb-festive-kicker"><?php echo esc_html( isset( $fest_opts[ $fest_key ] ) ? $fest_opts[ $fest_key ]['label'] : 'Special offers' ); ?></span>
				<h2>Festive offers</h2>
				<p>Celebrate with special prices for a limited time.</p>
			</div>
			<a class="sb-btn sb-btn-light" href="<?php echo esc_url( add_query_arg( 'festive', 1, $shop ) ); ?>">See all offers</a>
		</div>
		<?php echo sb_product_grid( $festive, 'sb-grid-4' ); // phpcs:ignore ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $deals ) : ?>
<section class="sb-section">
	<div class="sb-container">
		<?php sbt_section_head( 'Hot deals', 'Big savings on top gadgets.', add_query_arg( 'sale', 1, $shop ) ); ?>
		<?php echo sb_product_grid( $deals, 'sb-grid-4' ); // phpcs:ignore ?>
	</div>
</section>
<?php endif; ?>

<section class="sb-section sb-hide-app">
	<div class="sb-container">
		<div class="sb-appband">
			<div class="sb-appband-copy">
				<span class="sb-kicker sb-kicker-light"><?php echo sb_icon( 'phone', 15 ); ?> Soubenco app</span>
				<h2>Exclusive prices, only in the app</h2>
				<p>Get app-only deals, instant price-drop alerts and one-tap reorders. Your cart and wishlist stay in sync everywhere.</p>
				<?php if ( get_option( 'sb_app_link' ) ) : ?>
					<a class="sb-btn sb-btn-white sb-btn-lg" href="<?php echo esc_url( get_option( 'sb_app_link' ) ); ?>">Get it on Google Play</a>
				<?php else : ?>
					<span class="sb-soon">Coming soon to Google Play</span>
				<?php endif; ?>
			</div>
			<div class="sb-appband-phone" aria-hidden="true">
				<div class="sb-phone">
					<div class="sb-phone-notch"></div>
					<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/logo-mark.png' ); ?>" alt="">
					<div class="sb-phone-card"><span></span><span></span></div>
					<div class="sb-phone-card"><span></span><span></span></div>
					<div class="sb-phone-pill">App price</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="sb-section">
	<div class="sb-container">
		<?php sbt_section_head( 'New arrivals', 'Fresh in at Soubenco.', add_query_arg( 'orderby', 'date', $shop ) ); ?>
		<?php if ( $newest ) : ?>
			<?php echo sb_product_grid( $newest, 'sb-grid-4' ); // phpcs:ignore ?>
		<?php else : ?>
			<div class="sb-empty sb-empty-wide">
				<div class="sb-empty-ic"><?php echo sb_icon( 'box', 34 ); ?></div>
				<h3>Our shelves are being stocked</h3>
				<p class="sb-muted">New products are landing very soon. Create an account to get notified about launches and deals.</p>
				<a class="sb-btn sb-btn-primary" href="<?php echo esc_url( add_query_arg( 'signup', 1, sb_account_url() ) ); ?>">Create an account</a>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( $brands && ! is_wp_error( $brands ) ) : ?>
<section class="sb-section">
	<div class="sb-container">
		<?php sbt_section_head( 'Top brands' ); ?>
		<div class="sb-brands">
			<?php foreach ( $brands as $b ) : ?><a href="<?php echo esc_url( add_query_arg( 'brand', $b->slug, $shop ) ); ?>"><?php echo esc_html( $b->name ); ?></a><?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
