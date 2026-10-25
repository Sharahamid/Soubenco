<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$img      = get_template_directory_uri() . '/assets/img/';
$user     = wp_get_current_user();
$logged   = is_user_logged_in();
$avatar   = $logged && function_exists( 'sb_avatar_url' ) ? sb_avatar_url( $user->ID ) : '';
$cart_n   = function_exists( 'sb_cart_count' ) ? sb_cart_count() : 0;
$unread   = $logged && function_exists( 'sb_unread_count' ) ? sb_unread_count() : 0;
$cats     = sbt_shop_categories();
$shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$acc      = function_exists( 'sb_account_url' ) ? sb_account_url() : home_url( '/' );
$announce = get_option( 'sb_announcement', 'Delivery all over Bangladesh · Genuine products · Secure payment' );
$wa_num   = preg_replace( '/\D/', '', (string) get_option( 'sb_whatsapp' ) );
?>
<a class="sb-skip" href="#sb-main">Skip to content</a>

<?php if ( $announce ) : ?>
<div class="sb-announce sb-hide-app">
	<div class="sb-container sb-announce-in">
		<span><?php echo esc_html( $announce ); ?></span>
		<span class="sb-announce-right">
			<?php if ( $wa_num ) : ?><a href="https://wa.me/<?php echo esc_attr( $wa_num ); ?>" target="_blank" rel="noopener"><?php echo sb_icon( 'whatsapp', 14 ); ?> WhatsApp <?php echo esc_html( sbt_format_bd_number( $wa_num ) ); ?></a><?php endif; ?>
			<?php if ( get_option( 'sb_app_link' ) ) : ?><a href="<?php echo esc_url( get_option( 'sb_app_link' ) ); ?>">Get the app</a><?php endif; ?>
		</span>
	</div>
</div>
<?php endif; ?>

<header class="sb-header" data-sb-header>
	<div class="sb-container sb-header-in">
		<button class="sb-icon-btn sb-menu-btn" type="button" data-sb-drawer-open aria-label="Open menu"><?php echo sb_icon( 'menu', 22 ); ?></button>
		<a class="sb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Soubenco home">
			<img src="<?php echo esc_url( $img . 'logo-header.png' ); ?>" alt="Soubenco" width="186" height="47">
		</a>

		<form class="sb-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-sb-search>
			<span class="sb-search-ic"><?php echo sb_icon( 'search', 19 ); ?></span>
			<input type="search" name="s" placeholder="Search TVs, fridges, fans, gadgets…" autocomplete="off" aria-label="Search products" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
			<button type="submit" class="sb-btn sb-btn-primary sb-search-go">Search</button>
			<div class="sb-search-pop" data-sb-search-pop hidden></div>
		</form>

		<div class="sb-actions">
			<a class="sb-icon-btn sb-hide-sm" href="<?php echo esc_url( $logged ? sb_account_url( 'wishlist' ) : add_query_arg( 'redirect', urlencode( sb_account_url( 'wishlist' ) ), $acc ) ); ?>" aria-label="Wishlist">
				<?php echo sb_icon( 'heart', 22 ); ?><em class="sb-badge" data-sb-wish-count hidden></em>
			</a>
			<?php if ( $logged ) : ?>
			<div class="sb-pop-wrap">
				<button class="sb-icon-btn" type="button" data-sb-bell aria-label="Notifications"><?php echo sb_icon( 'bell', 22 ); ?><em class="sb-badge" data-sb-unread <?php echo $unread ? '' : 'hidden'; ?>><?php echo (int) $unread; ?></em></button>
				<div class="sb-pop sb-bell-pop" data-sb-bell-pop hidden>
					<div class="sb-pop-head"><strong>Notifications</strong><a href="<?php echo esc_url( sb_account_url( 'notifications' ) ); ?>">See all</a></div>
					<div class="sb-pop-body" data-sb-bell-list><div class="sb-pop-empty">Loading…</div></div>
				</div>
			</div>
			<?php endif; ?>
			<a class="sb-account-btn" href="<?php echo esc_url( $acc ); ?>">
				<span class="sb-avatar"><?php echo $avatar ? '<img src="' . esc_url( $avatar ) . '" alt="">' : ( $logged ? esc_html( mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) ) ) : sb_icon( 'user', 19 ) ); ?></span>
				<span class="sb-account-txt sb-hide-md"><small><?php echo $logged ? 'Hello,' : 'Welcome'; ?></small><strong><?php echo $logged ? esc_html( explode( ' ', $user->display_name )[0] ) : 'Sign in'; ?></strong></span>
			</a>
			<a class="sb-cart-btn" href="<?php echo esc_url( function_exists( 'sb_page_url' ) ? sb_page_url( 'cart' ) : '#' ); ?>" aria-label="Cart">
				<?php echo sb_icon( 'cart', 22 ); ?><em class="sb-badge" data-sb-cart-count <?php echo $cart_n ? '' : 'hidden'; ?>><?php echo (int) $cart_n; ?></em>
				<span class="sb-hide-md">Cart</span>
			</a>
		</div>
	</div>

	<nav class="sb-catnav" aria-label="Categories">
		<div class="sb-container sb-catnav-in">
			<a href="<?php echo esc_url( $shop ); ?>" class="sb-catnav-all"><?php echo sb_icon( 'grid', 17 ); ?> All products</a>
			<?php foreach ( $cats as $c ) : ?>
				<a href="<?php echo esc_url( get_term_link( $c ) ); ?>" class="<?php echo is_product_category( $c->slug ) ? 'is-active' : ''; ?>"><?php echo esc_html( $c->name ); ?></a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url( add_query_arg( 'sale', 1, $shop ) ); ?>" class="sb-catnav-deal"><?php echo sb_icon( 'tag', 16 ); ?> Deals</a>
		</div>
	</nav>
</header>

<div class="sb-drawer" data-sb-drawer hidden>
	<div class="sb-drawer-bg" data-sb-drawer-close></div>
	<div class="sb-drawer-panel" role="dialog" aria-label="Menu">
		<div class="sb-drawer-head">
			<img src="<?php echo esc_url( $img . 'logo-header.png' ); ?>" alt="Soubenco" height="36">
			<button class="sb-icon-btn" type="button" data-sb-drawer-close aria-label="Close menu"><?php echo sb_icon( 'close', 22 ); ?></button>
		</div>
		<div class="sb-drawer-body">
			<p class="sb-drawer-label">Shop by category</p>
			<a href="<?php echo esc_url( $shop ); ?>"><span class="sb-drawer-ic"><?php echo sb_icon( 'grid', 20 ); ?></span>All products</a>
			<?php foreach ( $cats as $c ) : ?>
				<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><span class="sb-drawer-ic"><?php echo sbt_category_icon( $c->slug, 20 ); ?></span><?php echo esc_html( $c->name ); ?></a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url( add_query_arg( 'sale', 1, $shop ) ); ?>"><span class="sb-drawer-ic"><?php echo sb_icon( 'tag', 20 ); ?></span>Deals & offers</a>
			<p class="sb-drawer-label">My account</p>
			<a href="<?php echo esc_url( $acc ); ?>"><span class="sb-drawer-ic"><?php echo sb_icon( 'user', 20 ); ?></span><?php echo $logged ? 'My account' : 'Sign in / Create account'; ?></a>
			<?php if ( $logged ) : ?>
				<a href="<?php echo esc_url( sb_account_url( 'orders' ) ); ?>"><span class="sb-drawer-ic"><?php echo sb_icon( 'box', 20 ); ?></span>My orders</a>
				<a href="<?php echo esc_url( sb_account_url( 'wishlist' ) ); ?>"><span class="sb-drawer-ic"><?php echo sb_icon( 'heart', 20 ); ?></span>Wishlist</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<main id="sb-main" class="sb-main">
