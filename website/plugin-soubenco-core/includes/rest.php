<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
	$ns = 'sb/v1';
	$open = '__return_true';
	$auth = function () { return is_user_logged_in(); };

	register_rest_route( $ns, '/state', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function () {
		return array(
			'cart'      => sb_cart_count(),
			'wishlist'  => sb_wishlist_ids(),
			'unread'    => sb_unread_count(),
			'logged_in' => is_user_logged_in(),
		);
	} ) );

	register_rest_route( $ns, '/cart', array(
		array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function () { return sb_cart_payload(); } ),
		array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
			$res = sb_cart_add( (int) $r->get_param( 'product_id' ), (int) ( $r->get_param( 'qty' ) ?: 1 ), (string) $r->get_param( 'color' ) );
			if ( is_wp_error( $res ) ) return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 400 ) );
			return array( 'ok' => true, 'count' => sb_cart_count() );
		} ),
	) );
	register_rest_route( $ns, '/cart/update', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		sb_cart_set_qty( (int) $r->get_param( 'line' ), (int) $r->get_param( 'qty' ) );
		return sb_cart_payload();
	} ) );
	register_rest_route( $ns, '/cart/remove', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		sb_cart_remove( (int) $r->get_param( 'line' ) );
		return sb_cart_payload();
	} ) );

	register_rest_route( $ns, '/wishlist/toggle', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$res = sb_wishlist_toggle( (int) $r->get_param( 'product_id' ) );
		if ( is_wp_error( $res ) ) return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 401 ) );
		return array( 'ok' => true, 'saved' => $res, 'wishlist' => sb_wishlist_ids() );
	} ) );

	register_rest_route( $ns, '/notifications', array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => function () {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, title, body, link, is_read, created FROM ' . sb_table( 'notifications' ) . ' WHERE user_id = %d ORDER BY created DESC LIMIT 8', get_current_user_id() ), ARRAY_A );
		foreach ( $rows as &$n ) $n['ago'] = human_time_diff( strtotime( $n['created'] ), current_time( 'timestamp' ) ) . ' ago';
		return array( 'items' => $rows, 'unread' => sb_unread_count() );
	} ) );
	register_rest_route( $ns, '/notifications/read', array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => function () {
		global $wpdb;
		$wpdb->update( sb_table( 'notifications' ), array( 'is_read' => 1 ), array( 'user_id' => get_current_user_id(), 'is_read' => 0 ) );
		return array( 'ok' => true );
	} ) );

	/* App-only prices are only ever sent to the app. */
	register_rest_route( $ns, '/app-prices', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		if ( ! sb_is_app() ) return array();
		$out = array();
		foreach ( array_slice( array_filter( array_map( 'intval', explode( ',', (string) $r->get_param( 'ids' ) ) ) ), 0, 100 ) as $id ) {
			$d = sb_price_data( $id );
			if ( $d && $d['app'] ) $out[ $id ] = array( 'app' => $d['app'], 'web' => $d['web'], 'app_fmt' => sb_money( $d['app'] ), 'web_fmt' => sb_money( $d['web'] ) );
		}
		return $out;
	} ) );

	register_rest_route( $ns, '/search', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$q = sanitize_text_field( (string) $r->get_param( 'q' ) );
		if ( strlen( $q ) < 2 ) return array( 'items' => array() );
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 's' => $q, 'posts_per_page' => 6, 'fields' => 'ids' ) );
		$items = array();
		foreach ( $ids as $id ) {
			$j = sb_product_json( $id );
			if ( $j ) $items[] = array( 'name' => $j['name'], 'url' => $j['url'], 'image' => $j['image'], 'price' => sb_money( $j['price'] ), 'available' => $j['available'] );
		}
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'name__like' => $q, 'number' => 3, 'hide_empty' => true ) );
		return array(
			'items' => $items,
			'cats'  => is_wp_error( $cats ) ? array() : array_map( function ( $t ) { return array( 'name' => $t->name, 'url' => get_term_link( $t ) ); }, $cats ),
			'all'   => add_query_arg( array( 's' => $q, 'post_type' => 'product' ), home_url( '/' ) ),
		);
	} ) );

	register_rest_route( $ns, '/auth/register', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => 'sb_rest_register' ) );
	register_rest_route( $ns, '/auth/login', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => 'sb_rest_login' ) );
	register_rest_route( $ns, '/profile', array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => 'sb_rest_profile' ) );
} );

/** Fresh REST nonce (used when a page was open for a long time). */
add_action( 'wp_ajax_sb_nonce', 'sb_ajax_nonce' );
add_action( 'wp_ajax_nopriv_sb_nonce', 'sb_ajax_nonce' );
function sb_ajax_nonce() {
	nocache_headers();
	wp_send_json( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
}

/** Never cache personalised responses. */
add_filter( 'rest_post_dispatch', function ( $res, $server, $req ) {
	if ( 0 === strpos( $req->get_route(), '/sb/v1' ) ) {
		$res->header( 'Cache-Control', 'no-store, private' );
		$res->header( 'Vary', 'User-Agent, Cookie' );
	}
	return $res;
}, 10, 3 );

/* ---------- App notifications (phone alerts) ----------
 * Inside the app, signed-in customers get a private token. The app uses it in the background
 * to check for new alerts and show them as phone notifications. Read-only; one token per user.
 */
function sb_app_token( $user_id ) {
	$t = get_user_meta( $user_id, 'sb_app_token', true );
	if ( ! $t ) {
		$t = wp_generate_password( 40, false, false );
		update_user_meta( $user_id, 'sb_app_token', $t );
	}
	return $t;
}

add_action( 'wp_footer', function () {
	if ( ! sb_is_app() ) return;
	$token = is_user_logged_in() ? sb_app_token( get_current_user_id() ) : '';
	echo '<script>try{if(window.SoubencoAndroid){SoubencoAndroid.setToken(' . wp_json_encode( $token ) . ');}}catch(e){}</script>';
}, 99 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'sb/v1', '/app-feed', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			global $wpdb;
			$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $r->get_header( 'x-sb-token' ) );
			if ( strlen( $token ) < 30 ) return new WP_Error( 'sb_token', 'Missing token', array( 'status' => 401 ) );
			$users = get_users( array( 'meta_key' => 'sb_app_token', 'meta_value' => $token, 'number' => 1, 'fields' => 'ID' ) );
			if ( ! $users ) return new WP_Error( 'sb_token', 'Invalid token', array( 'status' => 401 ) );
			$after = max( 0, (int) $r->get_param( 'after' ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, title, body, link FROM ' . sb_table( 'notifications' ) . ' WHERE user_id = %d AND id > %d ORDER BY id ASC LIMIT 10', (int) $users[0], $after ), ARRAY_A );
			$last  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM ' . sb_table( 'notifications' ) . ' WHERE user_id = %d', (int) $users[0] ) );
			return array( 'items' => $rows, 'last' => $last );
		},
	) );
} );

/** Signing out of the app clears the token, so a shared phone stops receiving that person's alerts. */
add_action( 'wp_logout', function ( $user_id ) {
	if ( sb_is_app() && $user_id ) delete_user_meta( $user_id, 'sb_app_token' );
} );
