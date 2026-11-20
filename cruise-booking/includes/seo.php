<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_head', function () {
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) { return; }
    if (!is_front_page() && !is_singular('c365_plan')) { return; }
    $plan = is_singular('c365_plan') ? get_queried_object() : null;
    $description = $plan ? wp_strip_all_tags(get_the_excerpt($plan)) : '横浜・みなとみらいを海から楽しむC365の貸切クルーズ。愛犬と乗れるクルーズやナイトクルーズもご案内します。';
    $url = $plan ? get_permalink($plan) : home_url('/');
    ?>
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo esc_attr(wp_get_document_title()); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <?php if (function_exists('cruise_event_image')) : ?>
        <meta property="og:image" content="<?php echo esc_url(cruise_event_image($plan ? $plan->ID : 0)); ?>">
    <?php endif;
}, 5);
