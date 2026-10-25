<?php get_header(); ?>
<div class="sb-container sb-page">
	<div class="sb-empty sb-empty-wide">
		<div class="sb-empty-ic"><?php echo sb_icon( 'search', 34 ); ?></div>
		<h3>We could not find that page</h3>
		<p class="sb-muted">It may have moved. Try searching or browse our latest products.</p>
		<a class="sb-btn sb-btn-primary" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">Browse products</a>
	</div>
</div>
<?php get_footer(); ?>
