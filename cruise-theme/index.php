<?php get_header(); ?>
<main id="main" class="container content-page">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <?php $english_page = cruise_is_english() && is_page() && function_exists('c365_english_page')
        ? c365_english_page(get_post_field('post_name', get_the_ID())) : null; ?>
    <article <?php post_class(); ?>>
        <?php if ($english_page) : ?>
            <h1><?php echo esc_html($english_page[0]); ?></h1>
            <div class="entry-content">
                <?php foreach ($english_page[1] as $paragraph) : ?><p><?php echo esc_html($paragraph); ?></p><?php endforeach; ?>
                <?php if (is_page('contact')) { echo do_shortcode('[cruise_contact]'); } ?>
            </div>
        <?php else : ?>
        <h1><?php if (is_singular()) { the_title(); } else { ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php } ?></h1>
        <div class="entry-content"><?php if (is_singular()) { the_content(); wp_link_pages(); } else { the_excerpt(); } ?></div>
        <?php endif; ?>
    </article>
<?php endwhile; the_posts_pagination(); else : ?>
    <h1><?php cruise_output('ページが見つかりませんでした。', 'Page not found'); ?></h1>
    <a class="button" href="<?php echo esc_url(cruise_local_url(home_url('/'))); ?>"><?php cruise_output('トップページへ戻る', 'Back to the homepage'); ?></a>
<?php endif; ?>
</main>
<?php get_footer(); ?>
