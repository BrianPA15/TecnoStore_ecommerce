<?php get_header(); ?>

<div class="container content-area">
    <?php if (have_posts()): ?>
        <div class="posts-grid">
            <?php while (have_posts()): the_post(); ?>
                <article <?php post_class('post-card'); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="post-excerpt"><?php the_excerpt(); ?></div>
                </article>
            <?php endwhile; ?>
        </div>
        <?php the_posts_navigation(); ?>
    <?php else: ?>
        <p class="no-content">No se encontraron resultados.</p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
