<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Normalise a Bangladeshi mobile number to 01XXXXXXXXX. */
function sb_normalize_phone( $phone ) {
	$d = preg_replace( '/\D+/', '', (string) $phone );
	if ( 0 === strpos( $d, '880' ) ) $d = substr( $d, 2 );
	if ( 10 === strlen( $d ) && '1' === $d[0] ) $d = '0' . $d;
	return preg_match( '/^01[3-9]\d{8}$/', $d ) ? $d : '';
}

function sb_user_by_phone( $phone ) {
	$phone = sb_normalize_phone( $phone );
	if ( ! $phone ) return false;
	$u = get_user_by( 'login', $phone );
	if ( $u ) return $u;
	$q = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1 ) );
	return $q ? $q[0] : false;
}

function sb_districts() {
	$states = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( 'BD' ) : array();
	if ( $states ) asort( $states );
	return $states ?: array();
}

function sb_rate_limited( $action, $max = 10 ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'x';
	$key = 'sb_rl_' . $action . '_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) return true;
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}

/** Save an uploaded profile photo for a user; returns attachment id or WP_Error. */
function sb_save_avatar_upload( $user_id, $file_key = 'photo' ) {
	if ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['tmp_name'] ) ) return 0;
	$f = $_FILES[ $file_key ];
	if ( $f['size'] > 5 * MB_IN_BYTES ) return new WP_Error( 'sb_big', 'Photo must be under 5 MB.' );
	$check = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
	if ( empty( $check['type'] ) ) return new WP_Error( 'sb_type', 'Please upload a JPG, PNG or WEBP photo.' );
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_upload( $file_key, 0, array( 'post_author' => $user_id, 'post_title' => 'Profile photo' ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
	if ( is_wp_error( $id ) ) return $id;
	$old = (int) get_user_meta( $user_id, 'sb_avatar', true );
	update_user_meta( $user_id, 'sb_avatar', $id );
	if ( $old && $old !== $id && (int) get_post_field( 'post_author', $old ) === (int) $user_id ) wp_delete_attachment( $old, true );
	return $id;
}

add_filter( 'pre_get_avatar_data', function ( $args, $id_or_email ) {
	$uid = 0;
	if ( is_numeric( $id_or_email ) ) $uid = (int) $id_or_email;
	elseif ( $id_or_email instanceof WP_User ) $uid = $id_or_email->ID;
	elseif ( $id_or_email instanceof WP_Comment ) $uid = (int) $id_or_email->user_id;
	elseif ( is_string( $id_or_email ) && ( $u = get_user_by( 'email', $id_or_email ) ) ) $uid = $u->ID;
	if ( $uid && ( $att = (int) get_user_meta( $uid, 'sb_avatar', true ) ) ) {
		$url = wp_get_attachment_image_url( $att, 'thumbnail' );
		if ( $url ) { $args['url'] = $url; $args['found_avatar'] = true; }
	}
	return $args;
}, 10, 2 );

function sb_avatar_url( $user_id ) {
	$att = (int) get_user_meta( $user_id, 'sb_avatar', true );
	return $att ? wp_get_attachment_image_url( $att, 'thumbnail' ) : '';
}

/** Allow signing in with a phone number in any login form. */
add_filter( 'authenticate', function ( $user, $username, $password ) {
	if ( $user instanceof WP_User || ! $username ) return $user;
	$byphone = sb_user_by_phone( $username );
	if ( $byphone ) return wp_authenticate_username_password( null, $byphone->user_login, $password );
	return $user;
}, 25, 3 );

/** Collect & validate profile fields from a request. */
function sb_profile_fields_from_request( $r, $require = true ) {
	$f = array(
		'name'     => sanitize_text_field( (string) $r->get_param( 'name' ) ),
		'phone'    => sb_normalize_phone( $r->get_param( 'phone' ) ),
		'email'    => sanitize_email( (string) $r->get_param( 'email' ) ),
		'address'  => sanitize_textarea_field( (string) $r->get_param( 'address' ) ),
		'district' => sanitize_text_field( (string) $r->get_param( 'district' ) ),
		'city'     => sanitize_text_field( (string) $r->get_param( 'city' ) ),
	);
	$errors = array();
	if ( $require || null !== $r->get_param( 'name' ) ) if ( strlen( $f['name'] ) < 2 ) $errors['name'] = 'Please enter your full name.';
	if ( $require || null !== $r->get_param( 'phone' ) ) if ( ! $f['phone'] ) $errors['phone'] = 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).';
	if ( $r->get_param( 'email' ) && ! is_email( $f['email'] ) ) $errors['email'] = 'That email address does not look right.';
	if ( $require && strlen( $f['address'] ) < 5 ) $errors['address'] = 'Please enter your delivery address.';
	$d = sb_districts();
	if ( $f['district'] && $d && ! isset( $d[ $f['district'] ] ) ) $errors['district'] = 'Please choose your district.';
	if ( $require && ! $f['district'] ) $errors['district'] = 'Please choose your district.';
	return array( $f, $errors );
}

function sb_apply_profile( $user_id, $f ) {
	$parts = preg_split( '/\s+/', trim( $f['name'] ), 2 );
	$first = $parts[0]; $last = isset( $parts[1] ) ? $parts[1] : '';
	wp_update_user( array( 'ID' => $user_id, 'display_name' => $f['name'], 'first_name' => $first, 'last_name' => $last ) );
	$map = array(
		'billing_first_name' => $first, 'billing_last_name' => $last, 'billing_phone' => $f['phone'],
		'billing_address_1' => $f['address'], 'billing_city' => $f['city'], 'billing_state' => $f['district'], 'billing_country' => 'BD',
		'shipping_first_name' => $first, 'shipping_last_name' => $last, 'shipping_address_1' => $f['address'],
		'shipping_city' => $f['city'], 'shipping_state' => $f['district'], 'shipping_country' => 'BD',
	);
	if ( $f['email'] ) $map['billing_email'] = $f['email'];
	foreach ( $map as $k => $v ) if ( null !== $v && '' !== $v ) update_user_meta( $user_id, $k, $v );
}

function sb_rest_register( WP_REST_Request $r ) {
	if ( is_user_logged_in() ) return new WP_Error( 'sb_in', 'You are already signed in.', array( 'status' => 400 ) );
	if ( sb_rate_limited( 'reg', 8 ) ) return new WP_Error( 'sb_rl', 'Too many attempts. Please wait a few minutes.', array( 'status' => 429 ) );
	list( $f, $errors ) = sb_profile_fields_from_request( $r, true );
	$pass = (string) $r->get_param( 'password' );
	if ( strlen( $pass ) < 6 ) $errors['password'] = 'Password must be at least 6 characters.';
	if ( $f['phone'] && sb_user_by_phone( $f['phone'] ) ) $errors['phone'] = 'This number already has an account. Please sign in.';
	if ( $f['email'] && email_exists( $f['email'] ) ) $errors['email'] = 'This email already has an account. Please sign in.';
	if ( $errors ) return new WP_Error( 'sb_invalid', 'Please check the highlighted fields.', array( 'status' => 400, 'fields' => $errors ) );

	$email = $f['email'] ? $f['email'] : $f['phone'] . '@phone.soubenco';
	$uid = wp_insert_user( array( 'user_login' => $f['phone'], 'user_pass' => $pass, 'user_email' => $email, 'role' => 'customer', 'display_name' => $f['name'] ) );
	if ( is_wp_error( $uid ) ) return new WP_Error( 'sb_fail', $uid->get_error_message(), array( 'status' => 400 ) );
	sb_apply_profile( $uid, $f );
	update_user_meta( $uid, 'sb_signup_channel', sb_is_app() ? 'app' : 'website' );
	$photo = sb_save_avatar_upload( $uid );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true, is_ssl() );
	do_action( 'wp_login', $f['phone'], get_userdata( $uid ) );
	if ( $f['email'] ) sb_send_email( $f['email'], 'Welcome to Soubenco, ' . $f['name'], 'Your account is ready. Save products to your wishlist, keep items in your cart as long as you like, and get notified about price drops and restocks.', home_url( '/' ) );
	return array( 'ok' => true, 'photo_error' => is_wp_error( $photo ) ? $photo->get_error_message() : null, 'redirect' => sb_account_url() );
}

function sb_rest_login( WP_REST_Request $r ) {
	if ( sb_rate_limited( 'login', 12 ) ) return new WP_Error( 'sb_rl', 'Too many attempts. Please wait a few minutes.', array( 'status' => 429 ) );
	$login = trim( (string) $r->get_param( 'login' ) );
	$pass  = (string) $r->get_param( 'password' );
	if ( $p = sb_user_by_phone( $login ) ) $login = $p->user_login;
	elseif ( is_email( $login ) && ( $u = get_user_by( 'email', $login ) ) ) $login = $u->user_login;
	$user = wp_signon( array( 'user_login' => $login, 'user_password' => $pass, 'remember' => true ), is_ssl() );
	if ( is_wp_error( $user ) ) return new WP_Error( 'sb_bad', 'Phone/email or password is incorrect.', array( 'status' => 401 ) );
	return array( 'ok' => true, 'redirect' => $r->get_param( 'redirect' ) ? esc_url_raw( $r->get_param( 'redirect' ) ) : sb_account_url() );
}

function sb_rest_profile( WP_REST_Request $r ) {
	$uid = get_current_user_id();
	list( $f, $errors ) = sb_profile_fields_from_request( $r, false );
	$user = get_userdata( $uid );
	if ( $f['phone'] ) {
		$other = sb_user_by_phone( $f['phone'] );
		if ( $other && (int) $other->ID !== $uid ) $errors['phone'] = 'This number is used by another account.';
	}
	if ( $f['email'] ) {
		$other = email_exists( $f['email'] );
		if ( $other && (int) $other !== $uid ) $errors['email'] = 'This email is used by another account.';
	}
	$newpass = (string) $r->get_param( 'new_password' );
	if ( $newpass ) {
		if ( strlen( $newpass ) < 6 ) $errors['new_password'] = 'New password must be at least 6 characters.';
		elseif ( ! wp_check_password( (string) $r->get_param( 'current_password' ), $user->user_pass, $uid ) ) $errors['current_password'] = 'Current password is incorrect.';
	}
	if ( $errors ) return new WP_Error( 'sb_invalid', 'Please check the highlighted fields.', array( 'status' => 400, 'fields' => $errors ) );
	if ( ! $f['name'] ) $f['name'] = $user->display_name;
	sb_apply_profile( $uid, $f );
	if ( $f['email'] && $f['email'] !== $user->user_email ) wp_update_user( array( 'ID' => $uid, 'user_email' => $f['email'] ) );
	$photo = sb_save_avatar_upload( $uid );
	if ( is_wp_error( $photo ) ) return new WP_Error( 'sb_photo', $photo->get_error_message(), array( 'status' => 400, 'fields' => array( 'photo' => $photo->get_error_message() ) ) );
	if ( $newpass ) {
		wp_set_password( $newpass, $uid );
		wp_set_auth_cookie( $uid, true, is_ssl() );
	}
	return array( 'ok' => true, 'avatar' => sb_avatar_url( $uid ) );
}
