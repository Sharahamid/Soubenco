<?php get_header(); ?>
<?php while ( have_posts() ) : the_post(); ?>
<article class="sb-container sb-page sb-article">
	<header class="sb-page-head"><h1><?php the_title(); ?></h1><p class="sb-muted"><?php echo esc_html( get_the_date() ); ?></p></header>
	<?php if ( has_post_thumbnail() ) : ?><div class="sb-article-media"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
	<div class="sb-content"><?php the_content(); ?></div>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
