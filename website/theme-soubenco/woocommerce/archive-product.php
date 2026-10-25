<?php
defined( 'ABSPATH' ) || exit;
get_header();

$shop    = wc_get_page_permalink( 'shop' );
$term    = is_product_taxonomy() ? get_queried_object() : null;
$title   = is_search() ? 'Results for “' . get_search_query() . '”' : ( $term ? $term->name : 'All products' );
if ( ! empty( $_GET['sale'] ) && ! $term ) $title = 'Deals & offers';
if ( ! empty( $_GET['festive'] ) && ! $term ) $title = 'Festive offers';
global $wp_query;
$found   = (int) $wp_query->found_posts;
$base    = $term ? get_term_link( $term ) : $shop;
$cur     = function ( $k ) { return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; };
$orderby = $cur( 'orderby' );
$brands  = taxonomy_exists( 'product_brand' ) ? get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => true, 'exclude' => sbt_hidden_brand_ids() ) ) : array();
$subcats = $term && 'product_cat' === $term->taxonomy ? get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true ) ) : array();
$keep    = array_filter( array( 's' => get_search_query(), 'post_type' => is_search() ? 'product' : '' ) );
?>
<div class="sb-container sb-shop">
	<?php sbt_breadcrumbs(); ?>
	<header class="sb-shop-head">
		<div>
			<h1><?php echo esc_html( $title ); ?></h1>
			<p class="sb-muted"><?php echo $found ? esc_html( $found . ' ' . _n( 'product', 'products', $found ) ) : 'No products yet'; ?><?php if ( $term && $term->description ) echo ' · ' . esc_html( wp_strip_all_tags( $term->description ) ); ?></p>
		</div>
		<?php if ( $subcats && ! is_wp_error( $subcats ) ) : ?>
			<div class="sb-chips">
				<?php foreach ( $subcats as $s ) : ?><a class="sb-chip-link" href="<?php echo esc_url( get_term_link( $s ) ); ?>"><?php echo esc_html( $s->name ); ?></a><?php endforeach; ?>
			</div>
		<?php endif; ?>
	</header>

	<div class="sb-shop-layout">
		<aside class="sb-filters" data-sb-filters>
			<div class="sb-filters-head"><strong><?php echo sb_icon( 'filter', 18 ); ?> Filters</strong><button type="button" class="sb-icon-btn sb-show-md" data-sb-filters-close aria-label="Close filters"><?php echo sb_icon( 'close', 20 ); ?></button></div>
			<form method="get" action="<?php echo esc_url( $base ); ?>">
				<?php foreach ( $keep as $k => $v ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>"><?php endforeach; ?>
				<?php if ( $orderby ) : ?><input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>"><?php endif; ?>

				<?php if ( ! $term || 'product_cat' !== $term->taxonomy ) : ?>
				<div class="sb-filter-block">
					<h4>Category</h4>
					<div class="sb-filter-links">
						<?php foreach ( sbt_shop_categories() as $c ) : if ( ! $c->count ) continue; ?>
							<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?><em><?php echo (int) $c->count; ?></em></a>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<div class="sb-filter-block">
					<h4>Price (৳)</h4>
					<div class="sb-price-range">
						<input type="number" name="min_price" min="0" placeholder="Min" value="<?php echo esc_attr( $cur( 'min_price' ) ); ?>">
						<span>–</span>
						<input type="number" name="max_price" min="0" placeholder="Max" value="<?php echo esc_attr( $cur( 'max_price' ) ); ?>">
					</div>
				</div>

				<?php if ( $brands && ! is_wp_error( $brands ) ) : $sel = (array) ( $_GET['brand'] ?? array() ); ?>
				<div class="sb-filter-block">
					<h4>Brand</h4>
					<div class="sb-filter-checks">
						<?php foreach ( $brands as $b ) : ?>
							<label><input type="checkbox" name="brand[]" value="<?php echo esc_attr( $b->slug ); ?>" <?php checked( in_array( $b->slug, $sel, true ) ); ?>><span><?php echo esc_html( $b->name ); ?></span><em><?php echo (int) $b->count; ?></em></label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<div class="sb-filter-block">
					<h4>Show only</h4>
					<div class="sb-filter-checks">
						<label><input type="checkbox" name="instock" value="1" <?php checked( $cur( 'instock' ), '1' ); ?>><span>In stock</span></label>
						<label><input type="checkbox" name="sale" value="1" <?php checked( $cur( 'sale' ), '1' ); ?>><span>On discount</span></label>
						<label><input type="checkbox" name="festive" value="1" <?php checked( $cur( 'festive' ), '1' ); ?>><span>Festive offers</span></label>
					</div>
				</div>

				<div class="sb-filter-actions">
					<button class="sb-btn sb-btn-primary sb-btn-block" type="submit">Apply filters</button>
					<a class="sb-btn sb-btn-ghost sb-btn-block" href="<?php echo esc_url( is_search() ? add_query_arg( $keep, home_url( '/' ) ) : $base ); ?>">Clear</a>
				</div>
			</form>
		</aside>
		<div class="sb-filters-bg" data-sb-filters-close></div>

		<div class="sb-shop-main">
			<div class="sb-shop-toolbar">
				<button type="button" class="sb-btn sb-btn-light sb-show-md" data-sb-filters-open><?php echo sb_icon( 'filter', 17 ); ?> Filters</button>
				<form method="get" class="sb-sort">
					<?php foreach ( $_GET as $k => $v ) : if ( 'orderby' === $k || 'paged' === $k ) continue;
						foreach ( (array) $v as $vv ) : ?><input type="hidden" name="<?php echo esc_attr( is_array( $v ) ? $k . '[]' : $k ); ?>" value="<?php echo esc_attr( wp_unslash( $vv ) ); ?>"><?php endforeach; endforeach; ?>
					<label><span class="sb-hide-sm">Sort by</span>
						<select name="orderby" onchange="this.form.submit()">
							<?php foreach ( array( '' => 'Featured', 'date' => 'Newest', 'price' => 'Price: low to high', 'price-desc' => 'Price: high to low', 'popularity' => 'Best selling' ) as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $orderby, $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</form>
			</div>

			<?php if ( have_posts() ) : ?>
				<div class="sb-grid sb-grid-shop">
					<?php while ( have_posts() ) : the_post(); echo sb_product_card( get_the_ID() ); endwhile; // phpcs:ignore ?>
				</div>
				<div class="sb-pagination">
					<?php echo paginate_links( array( 'total' => $wp_query->max_num_pages, 'current' => max( 1, get_query_var( 'paged' ) ), 'prev_text' => '‹', 'next_text' => '›' ) ); // phpcs:ignore ?>
				</div>
			<?php else : ?>
				<div class="sb-empty">
					<div class="sb-empty-ic"><?php echo sb_icon( 'search', 34 ); ?></div>
					<h3>No products found</h3>
					<p class="sb-muted"><?php echo is_search() ? 'Try a different word, or check the spelling.' : 'New products are added regularly — please check back soon.'; ?></p>
					<a class="sb-btn sb-btn-primary" href="<?php echo esc_url( $shop ); ?>">See all products</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php get_footer(); ?>
