<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** A short, Bangladesh-friendly checkout form. */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$b = $fields['billing'];
	$keep = array();
	$keep['billing_first_name'] = array_merge( $b['billing_first_name'], array( 'label' => 'Full name', 'class' => array( 'form-row-wide' ), 'priority' => 10 ) );
	$keep['billing_phone']      = array_merge( $b['billing_phone'], array( 'label' => 'Mobile number', 'placeholder' => '01XXXXXXXXX', 'required' => true, 'class' => array( 'form-row-first' ), 'priority' => 20 ) );
	$keep['billing_email']      = array_merge( $b['billing_email'], array( 'label' => 'Email (for order updates)', 'required' => false, 'class' => array( 'form-row-last' ), 'priority' => 30 ) );
	$keep['billing_country']    = array_merge( $b['billing_country'], array( 'default' => 'BD', 'class' => array( 'sb-hidden' ), 'priority' => 35 ) );
	$keep['billing_state']      = array_merge( $b['billing_state'], array( 'label' => 'District', 'required' => true, 'class' => array( 'form-row-first' ), 'priority' => 40 ) );
	$keep['billing_city']       = array_merge( $b['billing_city'], array( 'label' => 'Area / Thana', 'class' => array( 'form-row-last' ), 'priority' => 50 ) );
	$keep['billing_address_1']  = array_merge( $b['billing_address_1'], array( 'label' => 'Delivery address', 'placeholder' => 'House, road, area', 'class' => array( 'form-row-wide' ), 'priority' => 60 ) );
	$fields['billing'] = $keep;
	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label'] = 'Delivery note';
		$fields['order']['order_comments']['placeholder'] = 'e.g. Call before delivery';
	}
	return $fields;
}, 50 );

add_filter( 'woocommerce_billing_fields', function ( $f ) {
	if ( isset( $f['billing_email'] ) ) $f['billing_email']['required'] = false;
	if ( isset( $f['billing_postcode'] ) ) $f['billing_postcode']['required'] = false;
	if ( isset( $f['billing_last_name'] ) ) $f['billing_last_name']['required'] = false;
	return $f;
}, 50 );

add_filter( 'default_checkout_billing_country', function () { return 'BD'; } );
add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
add_filter( 'woocommerce_enable_order_notes_field', '__return_true' );

/** Validate the mobile number. */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( ! empty( $data['billing_phone'] ) && ! sb_normalize_phone( $data['billing_phone'] ) ) {
		$errors->add( 'billing_phone', 'Please enter a valid Bangladeshi mobile number (01XXXXXXXXX).' );
	}
}, 10, 2 );

/** Pre-fill the full name for signed-in customers. */
add_filter( 'woocommerce_checkout_get_value', function ( $value, $input ) {
	if ( null !== $value && '' !== $value ) return $value;
	if ( ! is_user_logged_in() ) return $value;
	$u = wp_get_current_user();
	if ( 'billing_first_name' === $input ) return $u->display_name;
	if ( 'billing_email' === $input && false === strpos( $u->user_email, '@phone.soubenco' ) ) return $u->user_email;
	return $value;
}, 10, 2 );

/** Keep the full name together (we only ask for one name field). */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	if ( ! $order->get_shipping_first_name() ) {
		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_address_1( $order->get_billing_address_1() );
		$order->set_shipping_city( $order->get_billing_city() );
		$order->set_shipping_state( $order->get_billing_state() );
		$order->set_shipping_country( 'BD' );
		$order->set_shipping_phone( $order->get_billing_phone() );
	}
}, 10, 2 );

add_action( 'woocommerce_review_order_before_payment', function () {
	echo '<div class="sb-checkout-note">' . sb_icon( 'truck', 18 ) . '<span>We deliver all over Bangladesh. The delivery charge is based on your district.</span></div>'; // phpcs:ignore
} );

/** Show where the order came from in the admin order screen. */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	$ch = $order->get_meta( '_sb_channel' );
	if ( $ch ) echo '<p><strong>Ordered from:</strong> ' . esc_html( 'app' === $ch ? 'Soubenco app' : 'Website' ) . '</p>';
} );

/** Signed-in customers keep their account details in sync with the latest checkout. */
add_action( 'woocommerce_checkout_update_user_meta', function ( $uid, $data ) {
	if ( ! empty( $data['billing_first_name'] ) ) wp_update_user( array( 'ID' => $uid, 'display_name' => $data['billing_first_name'] ) );
}, 10, 2 );

/** Bangladesh field order: name, phone, email, district, area, address. */
add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
	$locale['BD'] = array_merge( isset( $locale['BD'] ) ? $locale['BD'] : array(), array(
		'state'     => array( 'label' => 'District', 'required' => true, 'priority' => 40 ),
		'city'      => array( 'label' => 'Area / Thana', 'required' => true, 'priority' => 50 ),
		'address_1' => array( 'label' => 'Delivery address', 'required' => true, 'priority' => 60 ),
		'postcode'  => array( 'required' => false, 'hidden' => true ),
	) );
	return $locale;
} );

/** Friendlier wording on the checkout page. */
add_filter( 'gettext', function ( $t, $text, $domain ) {
	if ( 'woocommerce' !== $domain ) return $t;
	$map = array(
		'Billing details'           => 'Delivery details',
		'Billing &amp; Shipping'    => 'Delivery details',
		'Have a coupon?'            => 'Have a promo code?',
		'Click here to enter your code' => 'Tap to enter it',
		'Place order'               => 'Confirm order',
	);
	return isset( $map[ $text ] ) ? $map[ $text ] : $t;
}, 20, 3 );
add_filter( 'woocommerce_shipping_package_name', function () { return 'Delivery'; } );
