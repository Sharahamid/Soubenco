<?php get_header(); ?>
<?php
$is_app_page = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
while ( have_posts() ) : the_post(); ?>
	<div class="sb-container <?php echo $is_app_page ? 'sb-page-app' : 'sb-page'; ?>">
		<?php if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) : ?>
			<div class="sb-checkout-head">
				<h1>Checkout</h1>
				<ol class="sb-steps"><li class="is-done">Cart</li><li class="is-active">Details & payment</li><li>Done</li></ol>
			</div>
		<?php elseif ( ! $is_app_page ) : ?>
			<header class="sb-page-head"><h1><?php the_title(); ?></h1></header>
		<?php endif; ?>
		<div class="sb-content"><?php the_content(); ?></div>
	</div>
<?php endwhile; ?>
<?php get_footer(); ?>
