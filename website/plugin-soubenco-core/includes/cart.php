<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Soubenco persistent cart.
 * - Saved in our own table, so it is never auto-emptied.
 * - Items that become unavailable stay in the cart (shown greyed out).
 * - Prices are always read live from the product, so price changes show instantly.
 * - Only the customer can remove an item (purchased items are cleared after a successful order).
 */

const SB_GUEST_COOKIE = 'sb_gk';

function sb_cart_owner( $create_guest = false ) {
	$uid = get_current_user_id();
	if ( $uid ) return array( 'user_id' => $uid, 'guest_key' => '' );
	$gk = isset( $_COOKIE[ SB_GUEST_COOKIE ] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $_COOKIE[ SB_GUEST_COOKIE ] ) : '';
	if ( ! $gk && $create_guest ) {
		$gk = wp_generate_password( 32, false, false );
		$_COOKIE[ SB_GUEST_COOKIE ] = $gk;
		if ( ! headers_sent() ) {
			setcookie( SB_GUEST_COOKIE, $gk, array( 'expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}
	return array( 'user_id' => 0, 'guest_key' => $gk );
}

function sb_cart_where( $owner ) {
	global $wpdb;
	if ( $owner['user_id'] ) return $wpdb->prepare( 'user_id = %d', $owner['user_id'] );
	if ( $owner['guest_key'] ) return $wpdb->prepare( 'user_id = 0 AND guest_key = %s', $owner['guest_key'] );
	return '1 = 0';
}

function sb_cart_rows( $owner = null ) {
	global $wpdb;
	$owner = $owner ?: sb_cart_owner();
	return $wpdb->get_results( 'SELECT * FROM ' . sb_table( 'cart' ) . ' WHERE ' . sb_cart_where( $owner ) . ' ORDER BY created ASC', ARRAY_A ) ?: array();
}

function sb_cart_add( $product_id, $qty = 1, $color = '' ) {
	global $wpdb;
	$product = wc_get_product( $product_id );
	if ( ! $product || 'publish' !== $product->get_status() ) return new WP_Error( 'sb_no_product', 'This product is not available.' );
	if ( '' === $product->get_price() ) return new WP_Error( 'sb_noprice', 'The price for this product is coming soon. Tap "Notify me" and we will tell you when it is available.' );
	if ( ! sb_product_available( $product ) ) return new WP_Error( 'sb_unavailable', 'This product is out of stock right now. Add it to your wishlist and we will notify you.' );
	$qty   = max( 1, (int) $qty );
	$color = sanitize_text_field( $color );
	$colors = sb_product_colors( $product );
	if ( $colors && ! $color ) $color = $colors[0]['name'];
	$owner = sb_cart_owner( true );
	$table = sb_table( 'cart' );
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE " . sb_cart_where( $owner ) . ' AND product_id = %d AND color = %s', $product_id, $color ), ARRAY_A );
	$now   = current_time( 'mysql' );
	$price = sb_effective_price( $product );
	$max   = $product->managing_stock() && ! $product->backorders_allowed() ? max( 1, (int) $product->get_stock_quantity() ) : 99;
	if ( $existing ) {
		$wpdb->update( $table, array( 'qty' => min( $max, (int) $existing['qty'] + $qty ), 'updated' => $now ), array( 'id' => $existing['id'] ) );
		return (int) $existing['id'];
	}
	$wpdb->insert( $table, array(
		'user_id' => $owner['user_id'], 'guest_key' => $owner['guest_key'], 'product_id' => $product_id, 'color' => $color,
		'qty' => min( $max, $qty ), 'price_at_add' => $price, 'price_seen' => $price, 'created' => $now, 'updated' => $now,
	) );
	return (int) $wpdb->insert_id;
}

function sb_cart_set_qty( $line_id, $qty ) {
	global $wpdb;
	$owner = sb_cart_owner();
	$qty   = max( 1, min( 99, (int) $qty ) );
	$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sb_table( 'cart' ) . ' WHERE ' . sb_cart_where( $owner ) . ' AND id = %d', $line_id ), ARRAY_A );
	if ( ! $row ) return false;
	$product = wc_get_product( $row['product_id'] );
	if ( $product && $product->managing_stock() && ! $product->backorders_allowed() ) $qty = min( $qty, max( 1, (int) $product->get_stock_quantity() ) );
	return false !== $wpdb->update( sb_table( 'cart' ), array( 'qty' => $qty, 'updated' => current_time( 'mysql' ) ), array( 'id' => (int) $line_id ) );
}

function sb_cart_remove( $line_id ) {
	global $wpdb;
	$owner = sb_cart_owner();
	return (bool) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . sb_table( 'cart' ) . ' WHERE ' . sb_cart_where( $owner ) . ' AND id = %d', $line_id ) );
}

function sb_cart_count( $owner = null ) {
	global $wpdb;
	$owner = $owner ?: sb_cart_owner();
	return (int) $wpdb->get_var( 'SELECT COALESCE(SUM(qty),0) FROM ' . sb_table( 'cart' ) . ' WHERE ' . sb_cart_where( $owner ) );
}

/** Full cart with live product data. */
function sb_cart_payload() {
	$is_app = sb_is_app();
	$items = array();
	$subtotal = 0; $saving = 0; $available_count = 0;
	foreach ( sb_cart_rows() as $row ) {
		$product = wc_get_product( $row['product_id'] );
		if ( ! $product || 'trash' === $product->get_status() ) {
			$items[] = array(
				'line' => (int) $row['id'], 'id' => (int) $row['product_id'], 'name' => 'Product no longer listed',
				'available' => false, 'qty' => (int) $row['qty'], 'color' => $row['color'], 'image' => wc_placeholder_img_src(),
				'url' => '', 'price' => 0, 'line_total' => 0, 'removed' => true,
			);
			continue;
		}
		$j = sb_product_json( $product, $is_app );
		$price = (float) $j['price'];
		$then  = (float) $row['price_at_add'];
		$item  = array_merge( $j, array(
			'line'       => (int) $row['id'],
			'qty'        => (int) $row['qty'],
			'color'      => $row['color'],
			'line_total' => $price * (int) $row['qty'],
			'price_then' => $then,
			'price_diff' => $then > 0 ? round( $price - $then, 2 ) : 0,
		) );
		if ( $j['available'] ) {
			$available_count += (int) $row['qty'];
			$subtotal += $item['line_total'];
			$saving   += max( 0, ( $j['regular'] - $price ) ) * (int) $row['qty'];
		}
		$items[] = $item;
	}
	return array(
		'items'     => $items,
		'count'     => array_sum( wp_list_pluck( $items, 'qty' ) ),
		'buyable'   => $available_count,
		'subtotal'  => $subtotal,
		'saving'    => $saving,
		'is_app'    => $is_app,
		'checkout'  => wc_get_checkout_url(),
		'logged_in' => is_user_logged_in(),
	);
}

/** When a guest signs in, move their cart into their account. */
add_action( 'wp_login', function ( $login, $user ) {
	global $wpdb;
	$gk = isset( $_COOKIE[ SB_GUEST_COOKIE ] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $_COOKIE[ SB_GUEST_COOKIE ] ) : '';
	if ( ! $gk ) return;
	$table = sb_table( 'cart' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = 0 AND guest_key = %s", $gk ), ARRAY_A );
	foreach ( $rows as $r ) {
		$dup = $wpdb->get_row( $wpdb->prepare( "SELECT id, qty FROM $table WHERE user_id = %d AND product_id = %d AND color = %s", $user->ID, $r['product_id'], $r['color'] ), ARRAY_A );
		if ( $dup ) {
			$wpdb->update( $table, array( 'qty' => max( (int) $dup['qty'], (int) $r['qty'] ) ), array( 'id' => $dup['id'] ) );
			$wpdb->delete( $table, array( 'id' => $r['id'] ) );
		} else {
			$wpdb->update( $table, array( 'user_id' => $user->ID, 'guest_key' => '' ), array( 'id' => $r['id'] ) );
		}
	}
}, 10, 2 );

/* ---------- Bridge to WooCommerce checkout ---------- */

/** On every checkout page load, rebuild WooCommerce's cart from the Soubenco cart (available items only). */
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) return;
	if ( 'GET' !== $_SERVER['REQUEST_METHOD'] ) return;
	sb_sync_wc_cart();
	if ( WC()->cart->is_empty() ) {
		wp_safe_redirect( sb_page_url( 'cart' ) );
		exit;
	}
}, 5 );

function sb_sync_wc_cart() {
	if ( ! WC()->cart ) return;
	$GLOBALS['sb_syncing'] = true;
	WC()->cart->empty_cart( false );
	foreach ( sb_cart_rows() as $row ) {
		$product = wc_get_product( $row['product_id'] );
		if ( ! sb_product_available( $product ) ) continue;
		$data = array( 'sb_line' => (int) $row['id'] );
		if ( $row['color'] ) $data['sb_color'] = $row['color'];
		WC()->cart->add_to_cart( (int) $row['product_id'], (int) $row['qty'], 0, array(), $data );
	}
	$GLOBALS['sb_syncing'] = false;
	wc_clear_notices();
}

/** Anything added through a normal WooCommerce add-to-cart link also lands in the Soubenco cart. */
add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty, $variation_id, $variation, $data ) {
	if ( ! empty( $GLOBALS['sb_syncing'] ) || ! empty( $data['sb_line'] ) ) return;
	sb_cart_add( $product_id, $qty, isset( $data['sb_color'] ) ? $data['sb_color'] : '' );
}, 10, 6 );

/** Use the app price for app customers, the website price for everyone else. */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['data'] ) ) continue;
		$item['data']->set_price( sb_effective_price( $item['data'] ) );
	}
}, 20 );

add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	if ( ! empty( $item['sb_color'] ) ) $data[] = array( 'key' => 'Color', 'value' => esc_html( $item['sb_color'] ) );
	return $data;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function ( $line, $key, $values ) {
	if ( ! empty( $values['sb_color'] ) ) $line->add_meta_data( 'Color', $values['sb_color'] );
	if ( ! empty( $values['sb_line'] ) ) $line->add_meta_data( '_sb_line', (int) $values['sb_line'] );
	if ( sb_is_app() ) $line->add_meta_data( '_sb_app_order', 1 );
}, 10, 3 );

add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	$order->update_meta_data( '_sb_channel', sb_is_app() ? 'app' : 'website' );
} );

/** After a successful order, clear only the items that were bought. */
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	global $wpdb;
	$order = wc_get_order( $order_id );
	if ( ! $order ) return;
	foreach ( $order->get_items() as $item ) {
		$line = (int) $item->get_meta( '_sb_line' );
		if ( $line ) $wpdb->delete( sb_table( 'cart' ), array( 'id' => $line ) );
	}
}, 20 );

/** [sb_cart] — the cart page shell (filled live by JavaScript). */
add_shortcode( 'sb_cart', function () {
	ob_start(); ?>
	<div class="sb-cart" data-sb-cart>
		<div class="sb-cart-head">
			<h1>Your cart</h1>
			<p class="sb-muted">Items stay here until you remove them. Prices update live.</p>
		</div>
		<div class="sb-cart-grid">
			<div class="sb-cart-list" data-sb-cart-list>
				<div class="sb-skeleton"></div><div class="sb-skeleton"></div>
			</div>
			<aside class="sb-cart-summary" data-sb-cart-summary></aside>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );
