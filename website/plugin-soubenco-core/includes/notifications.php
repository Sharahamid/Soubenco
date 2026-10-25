<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function sb_notify( $user_id, $type, $title, $body = '', $link = '', $product_id = 0, $email = true ) {
	global $wpdb;
	if ( ! $user_id ) return;
	$wpdb->insert( sb_table( 'notifications' ), array(
		'user_id' => (int) $user_id, 'type' => $type, 'product_id' => (int) $product_id,
		'title' => $title, 'body' => $body, 'link' => $link, 'is_read' => 0, 'created' => current_time( 'mysql' ),
	) );
	if ( $email && 'yes' === get_option( 'sb_email_notifications', 'yes' ) ) {
		$user = get_userdata( $user_id );
		if ( $user && is_email( $user->user_email ) && false === strpos( $user->user_email, '@phone.soubenco' ) ) {
			sb_send_email( $user->user_email, $title, $body, $link );
		}
	}
}

function sb_send_email( $to, $title, $body, $link = '' ) {
	$logo = get_theme_file_uri( 'assets/img/logo-header.png' );
	$btn  = $link ? '<p style="margin:24px 0 0"><a href="' . esc_url( $link ) . '" style="background:#6014EA;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:600;display:inline-block">View now</a></p>' : '';
	$html = '<div style="background:#F6F4FB;padding:32px 12px;font-family:Arial,Helvetica,sans-serif"><div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:28px;border:1px solid #ECE9F3">'
		. '<img src="' . esc_url( $logo ) . '" alt="Soubenco" style="height:34px;margin-bottom:20px">'
		. '<h2 style="margin:0 0 10px;color:#14111F;font-size:20px">' . esc_html( $title ) . '</h2>'
		. '<p style="margin:0;color:#4A4659;font-size:15px;line-height:1.6">' . esc_html( $body ) . '</p>' . $btn
		. '</div><p style="text-align:center;color:#9A96AA;font-size:12px;margin-top:16px">Soubenco · Delivery all over Bangladesh</p></div>';
	wp_mail( $to, $title . ' — Soubenco', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

function sb_unread_count( $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) return 0;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . sb_table( 'notifications' ) . ' WHERE user_id = %d AND is_read = 0', $user_id ) );
}

/** Customers who have this product in their cart or wishlist. */
function sb_product_watchers( $product_id ) {
	global $wpdb;
	$a = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM ' . sb_table( 'cart' ) . ' WHERE product_id = %d AND user_id > 0', $product_id ) );
	$b = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM ' . sb_table( 'wishlist' ) . ' WHERE product_id = %d', $product_id ) );
	return array_unique( array_map( 'intval', array_merge( $a, $b ) ) );
}

/* ---------- Detect price and availability changes ----------
 * Each product remembers its last known state (price, app price, availability).
 * Whenever it is saved or its stock changes, we compare against that and notify watchers.
 */

function sb_snapshot( $product ) {
	$d = sb_price_data( $product );
	return array( 'web' => $d ? (float) $d['web'] : 0, 'app' => $d ? $d['app'] : null, 'avail' => sb_product_available( $product ) );
}

function sb_check_product_change( $id ) {
	static $running = array();
	if ( ! $id || ! empty( $running[ $id ] ) ) return;
	$running[ $id ] = true;
	clean_post_cache( $id );
	if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients( $id );
	$product = wc_get_product( $id );
	if ( $product && 'auto-draft' !== $product->get_status() ) {
		$now    = sb_snapshot( $product );
		$before = get_post_meta( $id, '_sb_last_state', true );
		update_post_meta( $id, '_sb_last_state', $now );
		// Products never seen before count as "not yet available" (true for the imported catalogue, which has no prices yet).
		if ( ! is_array( $before ) ) $before = array( 'web' => 0, 'app' => null, 'avail' => false );
		if ( $before != $now ) sb_compare_and_notify( $product, $before, $now );
	}
	unset( $running[ $id ] );
}

add_action( 'woocommerce_after_product_object_save', function ( $product ) { sb_check_product_change( $product->get_id() ); }, 20 );
add_action( 'woocommerce_product_set_stock_status', function ( $id ) { sb_check_product_change( $id ); }, 20 );
add_action( 'woocommerce_product_set_stock', function ( $product ) { sb_check_product_change( $product->get_id() ); }, 20 );
// Scheduled sale start/end (WooCommerce runs these daily) also changes prices.
add_action( 'wc_after_products_starting_sales', function ( $ids ) { foreach ( (array) $ids as $id ) sb_check_product_change( $id ); } );
add_action( 'wc_after_products_ending_sales', function ( $ids ) { foreach ( (array) $ids as $id ) sb_check_product_change( $id ); } );

function sb_compare_and_notify( $product, $before, $after ) {
	if ( ! $before['avail'] && $after['avail'] ) {
		if ( ! $before['web'] ) {
			sb_notify_watchers( $product, 'back', 'Now available: ' . $product->get_name(), $product->get_name() . ' is now available for ' . sb_money( $after['web'] ) . '. Order it before stock runs out.' );
		} else {
			sb_notify_back_in_stock( $product );
		}
		return;
	}
	if ( ! $after['avail'] ) return;
	if ( abs( $before['web'] - $after['web'] ) >= 0.01 ) {
		$title =$after['web'] < $before['web'] ? 'Price drop: ' . $product->get_name() : 'Price update: ' . $product->get_name();
		$body  = sprintf( '%s is now %s (was %s).', $product->get_name(), sb_money( $after['web'] ), sb_money( $before['web'] ) );
		sb_notify_watchers( $product, 'price', $title, $body );
	} elseif ( $after['app'] && $before['app'] !== $after['app'] ) {
		sb_notify_watchers( $product, 'app_price', 'New app price: ' . $product->get_name(), 'There is a new app-only price on ' . $product->get_name() . '. Open the Soubenco app to see it.' );
	}
}

function sb_notify_back_in_stock( $product ) {
	sb_notify_watchers( $product, 'back', 'Back in stock: ' . $product->get_name(), $product->get_name() . ' is available again. It is waiting in your cart or wishlist.' );
}

function sb_notify_watchers( $product, $type, $title, $body ) {
	$key = 'sb_n_' . $type . '_' . $product->get_id() . '_' . md5( $body );
	if ( get_transient( $key ) ) return;
	set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
	foreach ( sb_product_watchers( $product->get_id() ) as $uid ) {
		sb_notify( $uid, $type, $title, $body, get_permalink( $product->get_id() ), $product->get_id() );
	}
}

/** Order updates. */
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	$uid = $order->get_customer_id();
	if ( ! $uid ) return;
	$labels = array(
		'processing' => 'We received your order and are preparing it.',
		'on-hold'    => 'Your order is on hold. We will contact you shortly.',
		'completed'  => 'Your order has been delivered. Thank you for shopping with Soubenco!',
		'cancelled'  => 'Your order was cancelled.',
		'refunded'   => 'Your order was refunded.',
		'failed'     => 'Payment for your order did not go through.',
	);
	if ( ! isset( $labels[ $to ] ) ) return;
	sb_notify( $uid, 'order', 'Order #' . $order->get_order_number() . ' — ' . wc_get_order_status_name( $to ), $labels[ $to ], sb_account_url( 'orders' ), 0, false );
}, 10, 4 );
