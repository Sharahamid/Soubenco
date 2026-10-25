<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function sb_wishlist_ids( $user_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) return array();
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT product_id FROM ' . sb_table( 'wishlist' ) . ' WHERE user_id = %d ORDER BY created DESC', $user_id ) ) );
}

function sb_wishlist_toggle( $product_id ) {
	global $wpdb;
	$uid = get_current_user_id();
	if ( ! $uid ) return new WP_Error( 'sb_login', 'Please sign in to use your wishlist.' );
	$table = sb_table( 'wishlist' );
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d AND product_id = %d", $uid, $product_id ) );
	if ( $exists ) {
		$wpdb->delete( $table, array( 'id' => $exists ) );
		return false;
	}
	if ( ! wc_get_product( $product_id ) ) return new WP_Error( 'sb_no_product', 'Product not found.' );
	$wpdb->insert( $table, array( 'user_id' => $uid, 'product_id' => (int) $product_id, 'created' => current_time( 'mysql' ) ) );
	return true;
}

function sb_in_wishlist( $product_id ) {
	static $ids = null;
	if ( null === $ids ) $ids = sb_wishlist_ids();
	return in_array( (int) $product_id, $ids, true );
}
