<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'sb_account', 'sb_render_account' );

function sb_render_account() {
	ob_start();
	echo '<div class="sb-account-wrap">';
	if ( ! is_user_logged_in() ) {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
			echo '<div class="sb-panel sb-auth-panel sb-woo-panel"><h1>Reset your password</h1>';
			WC_Shortcode_My_Account::lost_password();
			echo '</div>';
		} else {
			sb_render_auth();
		}
	} else {
		$woo_endpoints = array( 'view-order', 'order-pay', 'edit-address', 'payment-methods', 'add-payment-method' );
		$is_woo = false;
		foreach ( $woo_endpoints as $ep ) if ( is_wc_endpoint_url( $ep ) ) $is_woo = true;
		sb_render_dashboard( $is_woo );
	}
	echo '</div>';
	return ob_get_clean();
}

function sb_render_auth() {
	$mode = isset( $_GET['signup'] ) ? 'signup' : 'login';
	$redirect = isset( $_GET['redirect'] ) ? esc_url( wp_unslash( $_GET['redirect'] ) ) : '';
	?>
	<div class="sb-auth">
		<div class="sb-auth-side">
			<img class="sb-auth-logo" src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-stacked.png' ) ); ?>" alt="Soubenco">
			<h2>Gadgets you love, delivered across Bangladesh.</h2>
			<ul class="sb-auth-perks">
				<li><?php echo sb_icon( 'heart', 18 ); ?> Save favourites to your wishlist</li>
				<li><?php echo sb_icon( 'cart', 18 ); ?> Your cart is saved — it never empties on its own</li>
				<li><?php echo sb_icon( 'bell', 18 ); ?> Alerts for price drops and restocks</li>
				<li><?php echo sb_icon( 'box', 18 ); ?> Track every order in one place</li>
			</ul>
		</div>
		<div class="sb-auth-main">
			<div class="sb-tabs" role="tablist">
				<button type="button" class="sb-tab<?php echo 'login' === $mode ? ' is-active' : ''; ?>" data-sb-auth-tab="login">Sign in</button>
				<button type="button" class="sb-tab<?php echo 'signup' === $mode ? ' is-active' : ''; ?>" data-sb-auth-tab="signup">Create account</button>
			</div>

			<form class="sb-form" data-sb-auth="login" <?php echo 'login' !== $mode ? 'hidden' : ''; ?>>
				<input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect ); ?>">
				<label class="sb-field"><span>Mobile number or email</span><input name="login" autocomplete="username" required placeholder="01XXXXXXXXX"></label>
				<label class="sb-field"><span>Password</span><input name="password" type="password" autocomplete="current-password" required></label>
				<div class="sb-form-row-between"><a href="<?php echo esc_url( wc_lostpassword_url() ); ?>" class="sb-link">Forgot password?</a></div>
				<div class="sb-form-msg" data-sb-msg></div>
				<button class="sb-btn sb-btn-primary sb-btn-block sb-btn-lg" type="submit">Sign in</button>
			</form>

			<form class="sb-form" data-sb-auth="signup" enctype="multipart/form-data" <?php echo 'signup' !== $mode ? 'hidden' : ''; ?>>
				<div class="sb-photo-pick">
					<label class="sb-photo-circle" data-sb-photo-preview>
						<?php echo sb_icon( 'camera', 26 ); ?>
						<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-sb-photo-input>
					</label>
					<div><strong>Profile photo</strong><span class="sb-muted">Optional · JPG or PNG, up to 5 MB</span></div>
				</div>
				<div class="sb-form-grid">
					<label class="sb-field"><span>Full name *</span><input name="name" autocomplete="name" required></label>
					<label class="sb-field"><span>Mobile number *</span><input name="phone" type="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required></label>
					<label class="sb-field sb-col-2"><span>Email address</span><input name="email" type="email" autocomplete="email" placeholder="you@example.com"></label>
					<label class="sb-field"><span>District *</span><?php sb_district_select( '' ); ?></label>
					<label class="sb-field"><span>Area / Thana</span><input name="city" autocomplete="address-level2" placeholder="e.g. Dhanmondi"></label>
					<label class="sb-field sb-col-2"><span>Delivery address *</span><textarea name="address" rows="2" autocomplete="street-address" required placeholder="House, road, area"></textarea></label>
					<label class="sb-field sb-col-2"><span>Password *</span><input name="password" type="password" minlength="6" autocomplete="new-password" required></label>
				</div>
				<div class="sb-form-msg" data-sb-msg></div>
				<button class="sb-btn sb-btn-primary sb-btn-block sb-btn-lg" type="submit">Create account</button>
				<p class="sb-muted sb-small">By creating an account you agree to our terms and privacy policy.</p>
			</form>
		</div>
	</div>
	<?php
}

function sb_district_select( $current, $name = 'district' ) {
	echo '<select name="' . esc_attr( $name ) . '" required><option value="">Select district</option>';
	foreach ( sb_districts() as $code => $label ) {
		echo '<option value="' . esc_attr( $code ) . '"' . selected( $current, $code, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
}

function sb_render_dashboard( $woo_view = false ) {
	$user = wp_get_current_user();
	$uid  = $user->ID;
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
	if ( $woo_view ) $tab = 'orders';
	$tabs = array(
		'overview'      => array( 'Overview', 'home' ),
		'orders'        => array( 'My orders', 'box' ),
		'wishlist'      => array( 'Wishlist', 'heart' ),
		'notifications' => array( 'Notifications', 'bell' ),
		'history'       => array( 'Recently viewed', 'clock' ),
		'profile'       => array( 'Profile & address', 'user' ),
	);
	if ( ! isset( $tabs[ $tab ] ) ) $tab = 'overview';
	$avatar = sb_avatar_url( $uid );
	$unread = sb_unread_count( $uid );
	?>
	<div class="sb-dash">
		<aside class="sb-dash-nav">
			<div class="sb-dash-user">
				<span class="sb-avatar sb-avatar-lg"><?php echo $avatar ? '<img src="' . esc_url( $avatar ) . '" alt="">' : esc_html( mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) ) ); ?></span>
				<div><strong><?php echo esc_html( $user->display_name ); ?></strong><span class="sb-muted"><?php echo esc_html( get_user_meta( $uid, 'billing_phone', true ) ?: $user->user_email ); ?></span></div>
			</div>
			<nav>
				<?php foreach ( $tabs as $k => $t ) : ?>
					<a href="<?php echo esc_url( sb_account_url( $k ) ); ?>" class="<?php echo $tab === $k ? 'is-active' : ''; ?>"><?php echo sb_icon( $t[1], 18 ); ?><span><?php echo esc_html( $t[0] ); ?></span><?php if ( 'notifications' === $k && $unread ) : ?><em class="sb-count"><?php echo (int) $unread; ?></em><?php endif; ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php echo sb_icon( 'logout', 18 ); ?><span>Sign out</span></a>
			</nav>
		</aside>
		<section class="sb-dash-main">
			<?php
			if ( $woo_view ) {
				echo '<div class="sb-panel sb-woo-panel"><a class="sb-link" href="' . esc_url( sb_account_url( 'orders' ) ) . '">← All orders</a>';
				woocommerce_account_content();
				echo '</div>';
			} else {
				call_user_func( 'sb_tab_' . $tab, $user );
			}
			?>
		</section>
	</div>
	<?php
}

function sb_customer_orders( $uid, $limit = -1 ) {
	return wc_get_orders( array( 'customer_id' => $uid, 'limit' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'type' => 'shop_order' ) );
}

function sb_order_card( $order ) {
	$status = $order->get_status();
	$items  = $order->get_items();
	$url    = $order->get_view_order_url();
	ob_start(); ?>
	<div class="sb-order">
		<div class="sb-order-head">
			<div><strong>Order #<?php echo esc_html( $order->get_order_number() ); ?></strong><span class="sb-muted"><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'j M Y, g:i a' ) ); ?></span></div>
			<span class="sb-status sb-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
		</div>
		<div class="sb-order-items">
			<?php foreach ( array_slice( $items, 0, 4 ) as $item ) :
				$p = $item->get_product(); ?>
				<div class="sb-order-item">
					<?php echo $p ? $p->get_image( 'thumbnail' ) : ''; // phpcs:ignore ?>
					<div><span><?php echo esc_html( $item->get_name() ); ?></span><span class="sb-muted">Qty <?php echo (int) $item->get_quantity(); ?><?php echo $item->get_meta( 'Color' ) ? ' · ' . esc_html( $item->get_meta( 'Color' ) ) : ''; ?></span></div>
				</div>
			<?php endforeach; ?>
			<?php if ( count( $items ) > 4 ) : ?><span class="sb-muted">+<?php echo count( $items ) - 4; ?> more</span><?php endif; ?>
		</div>
		<div class="sb-order-foot">
			<span>Total <strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></span>
			<a class="sb-btn sb-btn-ghost sb-btn-sm" href="<?php echo esc_url( $url ); ?>">View details</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function sb_tab_overview( $user ) {
	$uid = $user->ID;
	$orders = sb_customer_orders( $uid );
	$first = explode( ' ', $user->display_name )[0];
	?>
	<div class="sb-dash-hello">
		<h1>Hi, <?php echo esc_html( $first ); ?></h1>
		<p class="sb-muted">Here is everything in your Soubenco account.</p>
	</div>
	<div class="sb-stats">
		<a href="<?php echo esc_url( sb_account_url( 'orders' ) ); ?>" class="sb-stat"><?php echo sb_icon( 'box', 22 ); ?><strong><?php echo count( $orders ); ?></strong><span>Orders</span></a>
		<a href="<?php echo esc_url( sb_page_url( 'cart' ) ); ?>" class="sb-stat"><?php echo sb_icon( 'cart', 22 ); ?><strong><?php echo sb_cart_count(); ?></strong><span>In cart</span></a>
		<a href="<?php echo esc_url( sb_account_url( 'wishlist' ) ); ?>" class="sb-stat"><?php echo sb_icon( 'heart', 22 ); ?><strong><?php echo count( sb_wishlist_ids( $uid ) ); ?></strong><span>Wishlist</span></a>
		<a href="<?php echo esc_url( sb_account_url( 'notifications' ) ); ?>" class="sb-stat"><?php echo sb_icon( 'bell', 22 ); ?><strong><?php echo sb_unread_count( $uid ); ?></strong><span>New alerts</span></a>
	</div>
	<div class="sb-panel">
		<div class="sb-panel-head"><h2>Recent orders</h2><a class="sb-link" href="<?php echo esc_url( sb_account_url( 'orders' ) ); ?>">See all</a></div>
		<?php if ( $orders ) { foreach ( array_slice( $orders, 0, 2 ) as $o ) echo sb_order_card( $o ); } else { echo '<div class="sb-empty-sm">No orders yet. <a class="sb-link" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Start shopping</a></div>'; } ?>
	</div>
	<?php
}

function sb_tab_orders( $user ) {
	$orders = sb_customer_orders( $user->ID );
	echo '<div class="sb-dash-hello"><h1>My orders</h1><p class="sb-muted">Your complete order history.</p></div>';
	if ( ! $orders ) {
		echo '<div class="sb-empty"><div class="sb-empty-ic">' . sb_icon( 'box', 34 ) . '</div><h3>No orders yet</h3><p class="sb-muted">When you place an order it will appear here.</p><a class="sb-btn sb-btn-primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Browse products</a></div>';
		return;
	}
	echo '<div class="sb-orders">';
	foreach ( $orders as $o ) echo sb_order_card( $o );
	echo '</div>';
}

function sb_tab_wishlist( $user ) {
	$ids = array_filter( sb_wishlist_ids( $user->ID ), function ( $id ) { $p = wc_get_product( $id ); return $p && 'trash' !== $p->get_status(); } );
	echo '<div class="sb-dash-hello"><h1>Wishlist</h1><p class="sb-muted">Saved for later. We will tell you when prices drop or items come back in stock.</p></div>';
	if ( ! $ids ) {
		echo '<div class="sb-empty"><div class="sb-empty-ic">' . sb_icon( 'heart', 34 ) . '</div><h3>Your wishlist is empty</h3><p class="sb-muted">Tap the heart on any product to save it here.</p><a class="sb-btn sb-btn-primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Discover products</a></div>';
		return;
	}
	echo sb_product_grid( $ids, 'sb-grid-3' ); // phpcs:ignore
}

function sb_tab_history( $user ) {
	$ids = array_filter( array_map( 'intval', (array) get_user_meta( $user->ID, 'sb_recent', true ) ), function ( $id ) { $p = $id ? wc_get_product( $id ) : null; return $p && 'publish' === $p->get_status(); } );
	echo '<div class="sb-dash-hello"><h1>Recently viewed</h1><p class="sb-muted">Products you looked at recently.</p></div>';
	if ( ! $ids ) {
		echo '<div class="sb-empty"><div class="sb-empty-ic">' . sb_icon( 'clock', 34 ) . '</div><h3>Nothing here yet</h3><p class="sb-muted">Products you view will show up here.</p></div>';
		return;
	}
	echo sb_product_grid( array_slice( $ids, 0, 12 ), 'sb-grid-3' ); // phpcs:ignore
}

function sb_tab_notifications( $user ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . sb_table( 'notifications' ) . ' WHERE user_id = %d ORDER BY created DESC LIMIT 60', $user->ID ) );
	$wpdb->update( sb_table( 'notifications' ), array( 'is_read' => 1 ), array( 'user_id' => $user->ID, 'is_read' => 0 ) );
	echo '<div class="sb-dash-hello"><h1>Notifications</h1><p class="sb-muted">Price drops, restocks and order updates.</p></div>';
	if ( ! $rows ) {
		echo '<div class="sb-empty"><div class="sb-empty-ic">' . sb_icon( 'bell', 34 ) . '</div><h3>You are all caught up</h3><p class="sb-muted">Add items to your cart or wishlist and we will alert you about changes.</p></div>';
		return;
	}
	$icons = array( 'price' => 'tag', 'app_price' => 'phone', 'back' => 'refresh', 'order' => 'box' );
	echo '<div class="sb-notes">';
	foreach ( $rows as $n ) {
		$ic = isset( $icons[ $n->type ] ) ? $icons[ $n->type ] : 'bell';
		$tag = $n->link ? 'a href="' . esc_url( $n->link ) . '"' : 'div';
		echo '<' . $tag . ' class="sb-note' . ( $n->is_read ? '' : ' is-new' ) . '"><span class="sb-note-ic sb-note-' . esc_attr( $n->type ) . '">' . sb_icon( $ic, 18 ) . '</span><div><strong>' . esc_html( $n->title ) . '</strong><p>' . esc_html( $n->body ) . '</p><span class="sb-muted sb-small">' . esc_html( human_time_diff( strtotime( $n->created ), current_time( 'timestamp' ) ) ) . ' ago</span></div></' . ( $n->link ? 'a' : 'div' ) . '>'; // phpcs:ignore
	}
	echo '</div>';
}

function sb_tab_profile( $user ) {
	$uid = $user->ID;
	$m = function ( $k ) use ( $uid ) { return get_user_meta( $uid, $k, true ); };
	$email = false !== strpos( $user->user_email, '@phone.soubenco' ) ? '' : $user->user_email;
	$avatar = sb_avatar_url( $uid );
	?>
	<div class="sb-dash-hello"><h1>Profile & address</h1><p class="sb-muted">Used to fill in checkout automatically.</p></div>
	<form class="sb-panel sb-form" data-sb-profile enctype="multipart/form-data">
		<div class="sb-photo-pick">
			<label class="sb-photo-circle<?php echo $avatar ? ' has-img' : ''; ?>" data-sb-photo-preview <?php echo $avatar ? 'style="background-image:url(' . esc_url( $avatar ) . ')"' : ''; ?>>
				<?php echo sb_icon( 'camera', 26 ); ?>
				<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-sb-photo-input>
			</label>
			<div><strong>Profile photo</strong><span class="sb-muted">Tap to change</span></div>
		</div>
		<div class="sb-form-grid">
			<label class="sb-field"><span>Full name</span><input name="name" value="<?php echo esc_attr( $user->display_name ); ?>" required></label>
			<label class="sb-field"><span>Mobile number</span><input name="phone" type="tel" value="<?php echo esc_attr( $m( 'billing_phone' ) ); ?>" required></label>
			<label class="sb-field sb-col-2"><span>Email address</span><input name="email" type="email" value="<?php echo esc_attr( $email ); ?>"></label>
			<label class="sb-field"><span>District</span><?php sb_district_select( $m( 'billing_state' ) ); ?></label>
			<label class="sb-field"><span>Area / Thana</span><input name="city" value="<?php echo esc_attr( $m( 'billing_city' ) ); ?>"></label>
			<label class="sb-field sb-col-2"><span>Delivery address</span><textarea name="address" rows="2"><?php echo esc_textarea( $m( 'billing_address_1' ) ); ?></textarea></label>
		</div>
		<details class="sb-details">
			<summary>Change password</summary>
			<div class="sb-form-grid">
				<label class="sb-field"><span>Current password</span><input name="current_password" type="password" autocomplete="current-password"></label>
				<label class="sb-field"><span>New password</span><input name="new_password" type="password" minlength="6" autocomplete="new-password"></label>
			</div>
		</details>
		<div class="sb-form-msg" data-sb-msg></div>
		<button class="sb-btn sb-btn-primary sb-btn-lg" type="submit">Save changes</button>
	</form>
	<?php
}
