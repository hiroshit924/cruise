<?php get_header(); ?>
<main id="main" class="container content-page">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article <?php post_class(); ?>>
        <h1><?php if (is_singular()) { the_title(); } else { ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php } ?></h1>
        <div class="entry-content"><?php if (is_singular()) { the_content(); wp_link_pages(); } else { the_excerpt(); } ?></div>
    </article>
<?php endwhile; the_posts_pagination(); else : ?>
    <h1>ページが見つかりませんでした。</h1>
    <a class="button" href="<?php echo esc_url(home_url('/')); ?>">トップページへ戻る</a>
<?php endif; ?>
</main>
<?php get_footer(); ?>
