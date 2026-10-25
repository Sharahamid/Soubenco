<?php
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SBT_VERSION', '1.0.0' );

require_once get_template_directory() . '/inc/categories.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 600,
		'single_image_width'    => 1000,
		'product_grid'          => array( 'default_columns' => 4 ),
	) );
	register_nav_menus( array( 'footer_help' => 'Footer — Help links' ) );
} );

/** Square, uncropped product thumbnails keep gadgets fully visible. */
add_filter( 'woocommerce_get_image_size_thumbnail', function () { return array( 'width' => 600, 'height' => 600, 'crop' => 0 ); } );
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', function () { return array( 'width' => 200, 'height' => 200, 'crop' => 0 ); } );

/** Our own styling replaces WooCommerce's default look. */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

add_action( 'wp_enqueue_scripts', function () {
	$uri = get_template_directory_uri();
	wp_enqueue_style( 'sbt-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@500;600;700;800&display=swap', array(), null );
	// Tiny font file containing only the Taka sign (৳) so prices look crisp.
	wp_enqueue_style( 'sbt-taka', 'https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@500;700&text=%E0%A7%B3&display=swap', array(), null );
	wp_enqueue_style( 'sbt-main', $uri . '/assets/css/main.css', array(), (string) filemtime( get_template_directory() . '/assets/css/main.css' ) );
	wp_enqueue_script( 'sbt-main', $uri . '/assets/js/main.js', array(), (string) filemtime( get_template_directory() . '/assets/js/main.js' ), true );
	wp_localize_script( 'sbt-main', 'SB', array(
		'rest'      => esc_url_raw( rest_url( 'sb/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'ajax'      => admin_url( 'admin-ajax.php' ),
		'cart'      => function_exists( 'sb_page_url' ) ? sb_page_url( 'cart' ) : home_url( '/cart/' ),
		'account'   => function_exists( 'sb_account_url' ) ? sb_account_url() : home_url( '/account/' ),
		'notes'     => function_exists( 'sb_account_url' ) ? sb_account_url( 'notifications' ) : '',
		'shop'      => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
		'loggedIn'  => is_user_logged_in(),
	) );
	// Remove block-library CSS on shop pages that don't use blocks.
	if ( ! is_singular( 'post' ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-blocks-style' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}
}, 20 );

add_action( 'wp_head', function () {
	echo '<meta name="theme-color" content="#6014EA">' . "\n";
	if ( ! has_site_icon() ) {
		echo '<link rel="icon" type="image/png" href="' . esc_url( get_template_directory_uri() . '/assets/img/favicon.png' ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( get_template_directory_uri() . '/assets/img/favicon.png' ) . '">' . "\n";
	}
	// Mark the app before first paint so app-only UI never flashes.
	echo "<script>if(/SoubencoApp/i.test(navigator.userAgent))document.documentElement.classList.add('sb-app');</script>\n";
}, 2 );

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/** Pages that are personal must never be cached. */
add_action( 'template_redirect', function () {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) nocache_headers();
} );

/* ---------- Shop filters (category, brand, price, availability, offers) ---------- */

add_filter( 'loop_shop_per_page', function () { return 24; } );

add_action( 'woocommerce_product_query', function ( $q ) {
	$tax = (array) $q->get( 'tax_query' );
	$meta = (array) $q->get( 'meta_query' );
	if ( ! empty( $_GET['brand'] ) && taxonomy_exists( 'product_brand' ) ) {
		$tax[] = array( 'taxonomy' => 'product_brand', 'field' => 'slug', 'terms' => array_map( 'sanitize_title', (array) $_GET['brand'] ) );
	}
	if ( ! empty( $_GET['instock'] ) ) {
		$meta[] = array( 'key' => '_stock_status', 'value' => 'instock' );
	}
	if ( ! empty( $_GET['festive'] ) ) {
		$meta[] = array( 'key' => '_sb_festive', 'value' => '', 'compare' => '!=' );
		$q->set( 'post__in', array_merge( array( 0 ), wc_get_product_ids_on_sale() ) );
	}
	if ( ! empty( $_GET['sale'] ) ) {
		$q->set( 'post__in', array_merge( array( 0 ), wc_get_product_ids_on_sale() ) );
	}
	$q->set( 'tax_query', $tax );
	$q->set( 'meta_query', $meta );
} );

/** Products that have a price (ready to buy) always list before "price coming soon" ones. */
add_filter( 'posts_clauses', function ( $clauses, $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! ( $q->get( 'wc_query' ) === 'product_query' ) ) return $clauses;
	global $wpdb;
	if ( false === strpos( $clauses['join'], 'sb_lk' ) ) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} sb_lk ON sb_lk.product_id = {$wpdb->posts}.ID ";
	}
	$has_img = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} sb_pm WHERE sb_pm.post_id = {$wpdb->posts}.ID AND sb_pm.meta_key = '_thumbnail_id' AND sb_pm.meta_value > 0)";
	$first = "(COALESCE(sb_lk.max_price,0) > 0 AND sb_lk.stock_status <> 'outofstock') DESC, (COALESCE(sb_lk.max_price,0) > 0) DESC, $has_img DESC";
	$clauses['orderby'] = $first . ( $clauses['orderby'] ? ', ' . $clauses['orderby'] : '' );
	return $clauses;
}, 20, 2 );

/** Branded placeholder when a product has no photo yet. */
add_filter( 'woocommerce_placeholder_img_src', function () { return get_template_directory_uri() . '/assets/img/placeholder.png'; } );

/** WooCommerce's own wrappers are replaced by our templates. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/** Simple helpers for templates. */
function sbt_container_open( $class = '' ) { echo '<div class="sb-container ' . esc_attr( $class ) . '">'; }
function sbt_container_close() { echo '</div>'; }

function sbt_section_head( $title, $sub = '', $link = '', $link_text = 'View all' ) {
	echo '<div class="sb-sec-head"><div><h2>' . esc_html( $title ) . '</h2>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div>';
	if ( $link ) echo '<a class="sb-link-arrow" href="' . esc_url( $link ) . '">' . esc_html( $link_text ) . ' ' . sb_icon( 'chevron', 16 ) . '</a>'; // phpcs:ignore
	echo '</div>';
}

function sbt_product_ids( $args ) {
	$defaults = array( 'status' => 'publish', 'limit' => 8, 'return' => 'ids', 'visibility' => 'catalog' );
	return wc_get_products( array_merge( $defaults, $args ) );
}

function sbt_breadcrumbs() {
	if ( function_exists( 'woocommerce_breadcrumb' ) ) {
		woocommerce_breadcrumb( array(
			'delimiter'   => '<span class="sb-bc-sep">/</span>',
			'wrap_before' => '<nav class="sb-breadcrumb" aria-label="Breadcrumb">',
			'wrap_after'  => '</nav>',
			'home'        => 'Home',
		) );
	}
}

/** The theme needs the Soubenco Core plugin; show a friendly holding page if it is switched off. */
add_action( 'template_redirect', function () {
	if ( function_exists( 'sb_product_card' ) && function_exists( 'WC' ) ) return;
	status_header( 503 );
	echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Soubenco</title><body style="margin:0;display:grid;place-items:center;min-height:100vh;font-family:system-ui;background:#F7F6FB;color:#15121F;text-align:center"><div><img src="' . esc_url( get_template_directory_uri() . '/assets/img/logo-stacked.png' ) . '" alt="Soubenco" style="width:220px"><p>We are setting things up. Please check back shortly.</p></div></body>';
	exit;
}, 0 );

/** 8801947377575 -> +880 1947-377575 */
function sbt_format_bd_number( $digits ) {
	$d = preg_replace( '/\D/', '', (string) $digits );
	if ( 13 === strlen( $d ) && 0 === strpos( $d, '880' ) ) return '+880 ' . substr( $d, 3, 4 ) . '-' . substr( $d, 7 );
	return $d ? '+' . $d : '';
}
