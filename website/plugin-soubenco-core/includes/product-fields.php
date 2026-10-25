<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Extra Soubenco fields inside the standard WooCommerce product editor
 * (the Product Studio is the easy way; this keeps the classic editor in sync).
 */
add_action( 'woocommerce_product_options_pricing', function () {
	global $post;
	$p = wc_get_product( $post->ID );
	echo '<div class="options_group sb-fields">';
	woocommerce_wp_text_input( array(
		'id' => '_sb_app_price', 'label' => 'App-only price (৳)', 'data_type' => 'price',
		'desc_tip' => true, 'description' => 'Shown only inside the Soubenco app. Must be lower than the website price.',
		'value' => $p ? $p->get_meta( '_sb_app_price' ) : '',
	) );
	$opts = array( '' => '— No festive tag —' );
	foreach ( sb_festive_options() as $k => $o ) $opts[ $k ] = $o['label'];
	woocommerce_wp_select( array(
		'id' => '_sb_festive', 'label' => 'Festive occasion', 'options' => $opts,
		'value' => $p ? $p->get_meta( '_sb_festive' ) : '',
		'desc_tip' => true, 'description' => 'Shows a festive offer design while a discount is running.',
	) );
	woocommerce_wp_text_input( array(
		'id' => '_sb_festive_text', 'label' => 'Festive note (optional)',
		'placeholder' => 'e.g. Eid special — limited stock',
		'value' => $p ? $p->get_meta( '_sb_festive_text' ) : '',
	) );
	echo '</div>';
} );

add_action( 'woocommerce_product_options_general_product_data', function () {
	global $post;
	$p = wc_get_product( $post->ID );
	if ( ! $p ) return;
	$colors = array_map( function ( $c ) { return $c['name'] . ( $c['hex'] ? ':' . $c['hex'] : '' ); }, sb_product_colors( $p ) );
	$specs  = array_map( function ( $s ) { return $s['label'] . ': ' . $s['value']; }, sb_product_specs( $p ) );
	echo '<div class="options_group">';
	woocommerce_wp_text_input( array( 'id' => '_sb_model', 'label' => 'Model', 'value' => $p->get_meta( '_sb_model' ) ) );
	woocommerce_wp_text_input( array( 'id' => '_sb_dimensions', 'label' => 'Dimensions', 'placeholder' => 'e.g. 16.1 x 7.7 x 0.8 cm', 'value' => $p->get_meta( '_sb_dimensions' ) ) );
	woocommerce_wp_textarea_input( array( 'id' => '_sb_colors_text', 'label' => 'Colors (one per line)', 'placeholder' => "Midnight Black:#111111\nIce Blue:#BFD9FF", 'value' => implode( "\n", $colors ) ) );
	woocommerce_wp_textarea_input( array( 'id' => '_sb_specs_text', 'label' => 'Specifications (one per line)', 'placeholder' => "Display: 6.7\" AMOLED\nBattery: 5000 mAh", 'value' => implode( "\n", $specs ) ) );
	echo '</div>';
} );

add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
	if ( isset( $_POST['_sb_app_price'] ) ) $product->update_meta_data( '_sb_app_price', wc_format_decimal( wp_unslash( $_POST['_sb_app_price'] ) ) );
	if ( isset( $_POST['_sb_festive'] ) ) $product->update_meta_data( '_sb_festive', sanitize_key( wp_unslash( $_POST['_sb_festive'] ) ) );
	if ( isset( $_POST['_sb_festive_text'] ) ) $product->update_meta_data( '_sb_festive_text', sanitize_text_field( wp_unslash( $_POST['_sb_festive_text'] ) ) );
	if ( isset( $_POST['_sb_model'] ) ) $product->update_meta_data( '_sb_model', sanitize_text_field( wp_unslash( $_POST['_sb_model'] ) ) );
	if ( isset( $_POST['_sb_dimensions'] ) ) $product->update_meta_data( '_sb_dimensions', sanitize_text_field( wp_unslash( $_POST['_sb_dimensions'] ) ) );
	if ( isset( $_POST['_sb_colors_text'] ) ) $product->update_meta_data( '_sb_colors', wp_json_encode( sb_parse_colors_text( wp_unslash( $_POST['_sb_colors_text'] ) ) ) );
	if ( isset( $_POST['_sb_specs_text'] ) ) $product->update_meta_data( '_sb_specs', wp_json_encode( sb_parse_specs_text( wp_unslash( $_POST['_sb_specs_text'] ) ) ) );
} );

function sb_parse_colors_text( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n|,/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) continue;
		$parts = array_map( 'trim', explode( ':', $line, 2 ) );
		$out[] = array( 'name' => sanitize_text_field( $parts[0] ), 'hex' => isset( $parts[1] ) ? ( sanitize_hex_color( $parts[1] ) ?: '' ) : sb_guess_color_hex( $parts[0] ) );
	}
	return $out;
}

function sb_parse_specs_text( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || false === strpos( $line, ':' ) ) continue;
		list( $l, $v ) = array_map( 'trim', explode( ':', $line, 2 ) );
		if ( $l !== '' && $v !== '' ) $out[] = array( 'label' => sanitize_text_field( $l ), 'value' => sanitize_text_field( $v ) );
	}
	return $out;
}

/** Best-effort color swatch from a color name. */
function sb_guess_color_hex( $name ) {
	$n = strtolower( $name );
	$map = array(
		'black' => '#16151a', 'midnight' => '#1c1f2b', 'graphite' => '#3b3d43', 'gray' => '#8a8d93', 'grey' => '#8a8d93', 'silver' => '#c9ccd1',
		'white' => '#f6f6f6', 'starlight' => '#ece4d6', 'cream' => '#efe6d2', 'gold' => '#d4af6a', 'rose' => '#e8b7b0', 'pink' => '#f2a7c3',
		'red' => '#d6333a', 'orange' => '#f08a24', 'yellow' => '#f3d14a', 'green' => '#3aa76d', 'mint' => '#a8e6cf', 'olive' => '#7a7d3a',
		'blue' => '#2f6fe4', 'navy' => '#1f2f5c', 'sky' => '#8cc6f2', 'teal' => '#1e9c9c', 'purple' => '#7b3fe4', 'violet' => '#6014ea',
		'lavender' => '#c9b6f2', 'titanium' => '#a39e94', 'brown' => '#7a5232', 'bronze' => '#a46a3a', 'copper' => '#b8733b', 'beige' => '#d9c7a8',
	);
	foreach ( $map as $k => $hex ) if ( false !== strpos( $n, $k ) ) return $hex;
	return '';
}
