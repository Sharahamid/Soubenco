<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** True when the request comes from the Soubenco mobile app (its user agent contains "SoubencoApp"). */
function sb_is_app() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	return stripos( $ua, 'SoubencoApp' ) !== false;
}

/** Festive occasions offered in the Product Studio. */
function sb_festive_options() {
	return apply_filters( 'sb_festive_options', array(
		'eid'         => array( 'label' => 'Eid Mubarak',      'tag' => 'Eid Offer' ),
		'puja'        => array( 'label' => 'Shubho Puja',      'tag' => 'Puja Offer' ),
		'newyear'     => array( 'label' => 'Happy New Year',   'tag' => 'New Year Offer' ),
		'boishakh'    => array( 'label' => 'Shubho Noboborsho','tag' => 'Boishakh Offer' ),
		'victory'     => array( 'label' => 'Victory Day',      'tag' => 'Victory Day Offer' ),
		'christmas'   => array( 'label' => 'Merry Christmas',  'tag' => 'Christmas Offer' ),
		'blackfriday' => array( 'label' => 'Black Friday',     'tag' => 'Black Friday Deal' ),
		'anniversary' => array( 'label' => 'Soubenco Anniversary', 'tag' => 'Anniversary Deal' ),
		'custom'      => array( 'label' => 'Special Offer',    'tag' => 'Special Offer' ),
	) );
}

function sb_money( $amount ) {
	if ( $amount === '' || $amount === null ) return '';
	return '৳' . number_format( (float) $amount, 0, '.', ',' );
}

/** Is a product currently buyable (visible, in stock, has a price)? */
function sb_product_available( $product ) {
	if ( ! $product ) return false;
	if ( 'publish' !== $product->get_status() ) return false;
	if ( ! $product->is_in_stock() ) return false;
	if ( '' === $product->get_price() ) return false;
	return true;
}

/** All price facts for a product, in one place. */
function sb_price_data( $product ) {
	if ( is_numeric( $product ) ) $product = wc_get_product( $product );
	if ( ! $product ) return null;
	$regular = (float) $product->get_regular_price();
	$sale    = $product->get_sale_price();
	$on_sale = $product->is_on_sale() && $sale !== '';
	$web     = $on_sale ? (float) $sale : $regular;
	$app_raw = $product->get_meta( '_sb_app_price' );
	$app     = ( $app_raw !== '' && (float) $app_raw > 0 && (float) $app_raw < $web ) ? (float) $app_raw : null;
	$pct     = ( $on_sale && $regular > 0 ) ? (int) round( ( $regular - $web ) / $regular * 100 ) : 0;
	$festive = (string) $product->get_meta( '_sb_festive' );
	$opts    = sb_festive_options();
	$festive_data = null;
	if ( $festive && isset( $opts[ $festive ] ) && $on_sale ) {
		$text = trim( (string) $product->get_meta( '_sb_festive_text' ) );
		$festive_data = array(
			'key'   => $festive,
			'label' => $opts[ $festive ]['label'],
			'tag'   => $text ? $text : $opts[ $festive ]['tag'],
			'note'  => $text,
		);
	}
	return array(
		'regular'  => $regular,
		'web'      => $web,
		'on_sale'  => $on_sale,
		'pct'      => $pct,
		'app'      => $app,
		'festive'  => $festive_data,
	);
}

/** The price the current visitor actually pays (app price inside the app). */
function sb_effective_price( $product, $is_app = null ) {
	$d = sb_price_data( $product );
	if ( ! $d ) return 0;
	if ( null === $is_app ) $is_app = sb_is_app();
	return ( $is_app && $d['app'] ) ? $d['app'] : $d['web'];
}

/** Colors saved on a product: [ [name, hex], ... ] */
function sb_product_colors( $product ) {
	$raw = $product->get_meta( '_sb_colors' );
	$arr = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $arr ) ) return array();
	$out = array();
	foreach ( $arr as $c ) {
		if ( empty( $c['name'] ) ) continue;
		$out[] = array( 'name' => sanitize_text_field( $c['name'] ), 'hex' => sanitize_hex_color( isset( $c['hex'] ) ? $c['hex'] : '' ) ?: '#cccccc' );
	}
	return $out;
}

function sb_product_specs( $product ) {
	$raw = $product->get_meta( '_sb_specs' );
	$arr = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $arr ) ) return array();
	return array_values( array_filter( $arr, function ( $s ) { return ! empty( $s['label'] ) && isset( $s['value'] ) && $s['value'] !== ''; } ) );
}

function sb_product_brand( $product ) {
	$terms = taxonomy_exists( 'product_brand' ) ? get_the_terms( $product->get_id(), 'product_brand' ) : false;
	$name = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : (string) $product->get_meta( '_sb_brand' );
	return in_array( strtolower( trim( $name ) ), array( 'n/a', 'na', 'none', '-' ), true ) ? '' : $name;
}

/** Compact product data for JSON (cards, cart, search). */
function sb_product_json( $product, $is_app = null ) {
	if ( is_numeric( $product ) ) $product = wc_get_product( $product );
	if ( ! $product ) return null;
	if ( null === $is_app ) $is_app = sb_is_app();
	$p   = sb_price_data( $product );
	$img = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
	return array(
		'id'        => $product->get_id(),
		'name'      => $product->get_name(),
		'url'       => get_permalink( $product->get_id() ),
		'image'     => $img ? $img : wc_placeholder_img_src(),
		'brand'     => sb_product_brand( $product ),
		'available' => sb_product_available( $product ),
		'stock'     => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
		'regular'   => $p['regular'],
		'web'       => $p['web'],
		'on_sale'   => $p['on_sale'],
		'pct'       => $p['pct'],
		'app'       => $is_app ? $p['app'] : null,
		'price'     => ( $is_app && $p['app'] ) ? $p['app'] : $p['web'],
		'festive'   => $p['festive'],
		'colors'    => sb_product_colors( $product ),
	);
}

/** Page URL helpers. */
function sb_page_url( $key ) {
	$id = (int) get_option( 'sb_page_' . $key );
	return $id ? get_permalink( $id ) : home_url( '/' );
}
function sb_account_url( $tab = '' ) {
	$url = sb_page_url( 'account' );
	return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
}

/** Simple SVG icon set used across the site. */
function sb_icon( $name, $size = 20 ) {
	$p = array(
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'heart'   => '<path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 6a5 5 0 0 1 9.2 5C19 15.6 12 20 12 20Z"/>',
		'cart'    => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'bell'    => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2Z"/><path d="M10 21a2 2 0 0 0 4 0"/>',
		'home'    => '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/>',
		'grid'    => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
		'truck'   => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
		'shield'  => '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
		'badge'   => '<circle cx="12" cy="9" r="6"/><path d="m9 14-1 7 4-2 4 2-1-7"/>',
		'refresh' => '<path d="M20 11a8 8 0 0 0-14.6-4.5L3 9"/><path d="M3 4v5h5"/><path d="M4 13a8 8 0 0 0 14.6 4.5L21 15"/><path d="M21 20v-5h-5"/>',
		'phone'   => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
		'trash'   => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
		'plus'    => '<path d="M12 5v14M5 12h14"/>',
		'minus'   => '<path d="M5 12h14"/>',
		'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
		'chevron' => '<path d="m9 6 6 6-6 6"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'check'   => '<path d="m5 12 5 5 9-10"/>',
		'logout'  => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
		'box'     => '<path d="M3 7l9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
		'pin'     => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'camera'  => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
		'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
		'tag'     => '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'filter'  => '<path d="M4 5h16l-6 8v6l-4-2v-4L4 5Z"/>',
		'eye'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'star'    => '<path d="m12 3 2.8 5.8 6.2.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.2-.9L12 3Z"/>',
	);
	$d = isset( $p[ $name ] ) ? $p[ $name ] : $p['box'];
	return '<svg class="sb-ic sb-ic-' . esc_attr( $name ) . '" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** Festive ornament art (small SVG) for each occasion. */
function sb_festive_art( $key ) {
	switch ( $key ) {
		case 'eid':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M24 6a14 14 0 1 0 10 22A12 12 0 1 1 24 6Z" fill="currentColor"/><path d="m30 9 1.2 2.6 2.8.3-2.1 1.9.6 2.8-2.5-1.4-2.5 1.4.6-2.8-2.1-1.9 2.8-.3Z" fill="currentColor"/></svg>';
		case 'puja':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 6c3 5 5 8 5 11a5 5 0 0 1-10 0c0-3 2-6 5-11Z" fill="currentColor"/><path d="M6 26h28c-2 5-7 8-14 8S8 31 6 26Z" fill="currentColor"/></svg>';
		case 'newyear':
		case 'anniversary':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 4l2.6 9.4L32 16l-9.4 2.6L20 28l-2.6-9.4L8 16l9.4-2.6Z" fill="currentColor"/><path d="M31 26l1.2 3.8L36 31l-3.8 1.2L31 36l-1.2-3.8L26 31l3.8-1.2Z" fill="currentColor"/></svg>';
		case 'boishakh':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="5" fill="currentColor"/><g fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="20" cy="20" r="11"/><path d="M20 4v6M20 30v6M4 20h6M30 20h6M8.7 8.7l4.2 4.2M27.1 27.1l4.2 4.2M8.7 31.3l4.2-4.2M27.1 12.9l4.2-4.2"/></g></svg>';
		case 'victory':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="5" y="9" width="30" height="22" rx="3" fill="currentColor" opacity=".35"/><circle cx="18" cy="20" r="6" fill="currentColor"/></svg>';
		case 'christmas':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><g stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M20 4v32M6 12l28 16M6 28l28-16"/><path d="m16 7 4 3 4-3M16 33l4-3 4 3"/></g></svg>';
		case 'blackfriday':
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M6 18V7h11l17 17-11 11L6 18Z" fill="currentColor"/><circle cx="12" cy="13" r="2.5" fill="#000"/></svg>';
		default:
			return '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 4l4.7 10.3L36 16l-8 7.7L29.9 35 20 29.6 10.1 35 12 23.7 4 16l11.3-1.7Z" fill="currentColor"/></svg>';
	}
}
