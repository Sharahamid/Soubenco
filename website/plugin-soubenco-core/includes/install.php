<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function sb_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'sb_' . $name;
}

function sb_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE " . sb_table( 'cart' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		guest_key varchar(64) NOT NULL DEFAULT '',
		product_id bigint(20) unsigned NOT NULL,
		color varchar(80) NOT NULL DEFAULT '',
		qty int(11) NOT NULL DEFAULT 1,
		price_at_add decimal(14,2) NOT NULL DEFAULT 0,
		price_seen decimal(14,2) NOT NULL DEFAULT 0,
		created datetime NOT NULL,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY guest_key (guest_key),
		KEY product_id (product_id)
	) $c;" );

	dbDelta( "CREATE TABLE " . sb_table( 'wishlist' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		product_id bigint(20) unsigned NOT NULL,
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY product_id (product_id)
	) $c;" );

	dbDelta( "CREATE TABLE " . sb_table( 'notifications' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		type varchar(30) NOT NULL DEFAULT '',
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(255) NOT NULL DEFAULT '',
		body text NULL,
		link varchar(500) NOT NULL DEFAULT '',
		is_read tinyint(1) NOT NULL DEFAULT 0,
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY is_read (is_read)
	) $c;" );

	sb_add_roles();
	sb_setup_pages();
	update_option( 'sb_db_version', SB_DB_VERSION );
}

/** The employee role: can add/edit products and upload photos, nothing else. */
function sb_add_roles() {
	$caps = array(
		'read'                      => true,
		'upload_files'              => true,
		'edit_products'             => true,
		'edit_others_products'      => true,
		'edit_published_products'   => true,
		'publish_products'          => true,
		'delete_products'           => true,
		'delete_published_products' => true,
		'delete_others_products'    => true,
		'read_private_products'     => true,
		'edit_private_products'     => true,
		'manage_product_terms'      => true,
		'edit_product_terms'        => true,
		'assign_product_terms'      => true,
		'sb_studio'                 => true,
	);
	remove_role( 'sb_product_manager' );
	add_role( 'sb_product_manager', 'Product Uploader', $caps );
	foreach ( array( 'administrator', 'shop_manager' ) as $r ) {
		$role = get_role( $r );
		if ( $role ) $role->add_cap( 'sb_studio' );
	}
}

function sb_setup_pages() {
	$pages = array(
		'account' => array( 'title' => 'My Account', 'slug' => 'account', 'content' => '[sb_account]', 'woo' => 'woocommerce_myaccount_page_id' ),
		'cart'    => array( 'title' => 'Cart',       'slug' => 'cart',    'content' => '[sb_cart]',    'woo' => 'woocommerce_cart_page_id' ),
		'checkout'=> array( 'title' => 'Checkout',   'slug' => 'checkout','content' => '[woocommerce_checkout]', 'woo' => 'woocommerce_checkout_page_id' ),
		'shop'    => array( 'title' => 'Shop',       'slug' => 'shop',    'content' => '',             'woo' => 'woocommerce_shop_page_id' ),
	);
	foreach ( $pages as $key => $pg ) {
		$id = (int) get_option( 'sb_page_' . $key );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			$id = (int) get_option( $pg['woo'] );
		}
		if ( ! $id || ! get_post( $id ) || 'trash' === get_post_status( $id ) ) {
			$existing = get_page_by_path( $pg['slug'] );
			$id = $existing ? $existing->ID : 0;
		}
		$data = array(
			'post_title'   => $pg['title'],
			'post_name'    => $pg['slug'],
			'post_content' => $pg['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);
		if ( $id ) {
			$data['ID'] = $id;
			wp_update_post( $data );
		} else {
			$id = wp_insert_post( $data );
		}
		// Clear any page-builder data so the page renders our content.
		delete_post_meta( $id, '_elementor_edit_mode' );
		delete_post_meta( $id, '_elementor_data' );
		delete_post_meta( $id, '_wpb_vc_js_status' );
		update_option( 'sb_page_' . $key, $id );
		update_option( $pg['woo'], $id );
	}
	flush_rewrite_rules( false );
}

/** Brand taxonomy (WooCommerce 9.6+ ships one; register ours only if missing). */
add_action( 'init', function () {
	if ( taxonomy_exists( 'product_brand' ) ) return;
	register_taxonomy( 'product_brand', array( 'product' ), array(
		'label'             => 'Brands',
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'brand' ),
		'show_in_rest'      => true,
		'capabilities'      => array(
			'manage_terms' => 'manage_product_terms',
			'edit_terms'   => 'edit_product_terms',
			'delete_terms' => 'manage_product_terms',
			'assign_terms' => 'assign_product_terms',
		),
	) );
}, 20 );
