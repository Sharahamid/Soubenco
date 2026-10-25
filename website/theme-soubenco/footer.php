</main>
<?php
$img    = get_template_directory_uri() . '/assets/img/';
$shop   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$acc    = function_exists( 'sb_account_url' ) ? sb_account_url() : home_url( '/' );
$cart   = function_exists( 'sb_page_url' ) ? sb_page_url( 'cart' ) : home_url( '/' );
$cats   = array_slice( sbt_shop_categories(), 0, 7 );
$social = array_filter( array(
	'Facebook'  => get_option( 'sb_facebook' ),
	'Instagram' => get_option( 'sb_instagram' ),
	'YouTube'   => get_option( 'sb_youtube' ),
) );
$wa = preg_replace( '/\D/', '', (string) get_option( 'sb_whatsapp' ) ); $addr = get_option( 'sb_address' ); $email = get_option( 'sb_email' ) ?: get_option( 'admin_email' );
?>
<section class="sb-trust sb-hide-app">
	<div class="sb-container sb-trust-in">
		<div><span><?php echo sb_icon( 'truck', 24 ); ?></span><div><strong>Nationwide delivery</strong><small>To all 64 districts</small></div></div>
		<div><span><?php echo sb_icon( 'badge', 24 ); ?></span><div><strong>Genuine products</strong><small>Sourced from trusted suppliers</small></div></div>
		<div><span><?php echo sb_icon( 'shield', 24 ); ?></span><div><strong>Secure payment</strong><small>SSL-protected checkout</small></div></div>
		<div><span><?php echo sb_icon( 'bell', 24 ); ?></span><div><strong>Price-drop alerts</strong><small>Never miss a deal</small></div></div>
	</div>
</section>

<footer class="sb-footer">
	<div class="sb-container sb-footer-grid">
		<div class="sb-footer-brand">
			<img src="<?php echo esc_url( $img . 'logo-header-white.png' ); ?>" alt="Soubenco" width="186" height="47">
			<p>Electronics and gadgets, online and in store. Delivered to your door anywhere in Bangladesh.</p>
			<?php if ( $social ) : ?>
				<div class="sb-social">
					<?php foreach ( $social as $name => $url ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $name ); ?></a><?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<div>
			<h4>Shop</h4>
			<a href="<?php echo esc_url( $shop ); ?>">All products</a>
			<?php foreach ( $cats as $c ) : ?><a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a><?php endforeach; ?>
			<a href="<?php echo esc_url( add_query_arg( 'sale', 1, $shop ) ); ?>">Deals & offers</a>
		</div>
		<div>
			<h4>My Soubenco</h4>
			<a href="<?php echo esc_url( $acc ); ?>">My account</a>
			<a href="<?php echo esc_url( function_exists( 'sb_account_url' ) ? sb_account_url( 'orders' ) : $acc ); ?>">Track my orders</a>
			<a href="<?php echo esc_url( function_exists( 'sb_account_url' ) ? sb_account_url( 'wishlist' ) : $acc ); ?>">Wishlist</a>
			<a href="<?php echo esc_url( $cart ); ?>">Cart</a>
			<?php if ( get_privacy_policy_url() ) : ?><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Privacy policy</a><?php endif; ?>
		</div>
		<div>
			<h4>Contact</h4>
			<?php if ( $wa ) : ?><a href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D/', '', $wa ) ); ?>" target="_blank" rel="noopener"><?php echo sb_icon( 'whatsapp', 16 ); ?> WhatsApp <?php echo esc_html( sbt_format_bd_number( $wa ) ); ?></a><?php endif; ?>
			<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo sb_icon( 'mail', 16 ); ?> <?php echo esc_html( $email ); ?></a>
			<?php if ( $addr ) : ?><span class="sb-footer-addr"><?php echo sb_icon( 'pin', 16 ); ?> <?php echo esc_html( $addr ); ?></span><?php endif; ?>
		</div>
	</div>
	<div class="sb-container sb-footer-bottom">
		<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Soubenco. All rights reserved.</span>
		<span class="sb-pay-chips"><em>bKash</em><em>Nagad</em><em>Visa</em><em>Mastercard</em></span>
	</div>
</footer>

<nav class="sb-tabbar" aria-label="Quick navigation">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="<?php echo is_front_page() ? 'is-active' : ''; ?>"><?php echo sb_icon( 'home', 22 ); ?><span>Home</span></a>
	<button type="button" data-sb-drawer-open><?php echo sb_icon( 'grid', 22 ); ?><span>Categories</span></button>
	<a href="<?php echo esc_url( $cart ); ?>" class="<?php echo function_exists( 'is_cart' ) && is_cart() ? 'is-active' : ''; ?>"><span class="sb-tab-ic"><?php echo sb_icon( 'cart', 22 ); ?><em class="sb-badge" data-sb-cart-count hidden></em></span><span>Cart</span></a>
	<a href="<?php echo esc_url( function_exists( 'sb_account_url' ) ? ( is_user_logged_in() ? sb_account_url( 'wishlist' ) : $acc ) : $acc ); ?>"><?php echo sb_icon( 'heart', 22 ); ?><span>Wishlist</span></a>
	<a href="<?php echo esc_url( $acc ); ?>" class="<?php echo function_exists( 'is_account_page' ) && is_account_page() ? 'is-active' : ''; ?>"><?php echo sb_icon( 'user', 22 ); ?><span>Account</span></a>
</nav>

<div class="sb-toasts" data-sb-toasts aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
