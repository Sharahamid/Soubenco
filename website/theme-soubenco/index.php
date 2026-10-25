<?php get_header(); ?>
<div class="sb-container sb-page">
	<header class="sb-page-head"><h1><?php echo is_search() ? 'Search: ' . esc_html( get_search_query() ) : ( is_home() ? 'Blog' : wp_kses_post( get_the_archive_title() ) ); ?></h1></header>
	<?php if ( have_posts() ) : ?>
		<div class="sb-posts">
		<?php while ( have_posts() ) : the_post(); ?>
			<article class="sb-post-card">
				<?php if ( has_post_thumbnail() ) : ?><a href="<?php the_permalink(); ?>" class="sb-post-media"><?php the_post_thumbnail( 'medium_large' ); ?></a><?php endif; ?>
				<div><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p class="sb-muted"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p></div>
			</article>
		<?php endwhile; ?>
		</div>
		<div class="sb-pagination"><?php echo paginate_links(); ?></div>
	<?php else : ?>
		<div class="sb-empty"><h3>Nothing found</h3><p class="sb-muted">Try a different search.</p></div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
