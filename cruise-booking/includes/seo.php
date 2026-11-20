<?php
if (!defined('ABSPATH')) { exit; }

function c365_uses_seo_plugin() {
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
}

add_filter('pre_get_document_title', function ($title) {
    if (c365_uses_seo_plugin()) { return $title; }
    if (is_front_page()) { return '横浜・みなとみらいの貸切クルーズ｜C365 CRUISE'; }
    if (is_singular('c365_plan')) {
        return trim(wp_strip_all_tags(get_the_title())) . '｜横浜の貸切クルーズ C365';
    }
    return $title;
});

add_filter('wp_robots', function ($robots) {
    if (is_singular('c365_plan') && (isset($_GET['booking_month']) || isset($_GET['booking_date']) || isset($_GET['departure']))) {
        $robots['noindex'] = true;
    }
    return $robots;
});

add_action('wp_head', function () {
    if (c365_uses_seo_plugin() || (!is_front_page() && !is_singular('c365_plan'))) { return; }
    $plan = is_singular('c365_plan') ? get_queried_object() : null;
    $description = $plan
        ? trim(wp_strip_all_tags(get_the_excerpt($plan))) . ' 横浜・みなとみらいの60分の貸切クルーズ。1組最大5名。'
        : '横浜・みなとみらいを海から巡る60分の貸切クルーズ。1組最大5名。日ノ出桟橋発で、愛犬と乗れるデイクルーズやナイトクルーズも選べます。料金・集合場所・予約方法をご案内します。';
    $url = $plan ? get_permalink($plan) : home_url('/');
    $image = '';
    if (function_exists('cruise_event_image')) {
        $image = $plan ? cruise_event_image($plan->ID) : (get_theme_mod('cruise_hero') ?: cruise_event_image(0));
    }
    ?>
    <?php if (is_front_page() && !is_singular()) : ?>
        <link rel="canonical" href="<?php echo esc_url($url); ?>">
    <?php endif; ?>
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ja_JP">
    <meta property="og:title" content="<?php echo esc_attr(wp_get_document_title()); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <?php if ($image) : ?>
        <meta property="og:image" content="<?php echo esc_url($image); ?>">
    <?php endif;
    $graph = c365_search_graph($plan, $url, $description, $image);
    echo '<script type="application/ld+json">' . wp_json_encode(array(
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 5);

function c365_search_graph($plan, $url, $description, $image) {
    $home = home_url('/');
    $provider = array('@id' => $home . '#cruise-operator');
    $organization = array(
        '@type' => 'Organization',
        '@id' => $provider['@id'],
        'name' => 'C365 CRUISE',
        'legalName' => '株式会社NEXUS',
        'url' => $home,
        'telephone' => c365_setting('phone', '045-297-6375'),
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => '中区福富町西通44-2',
            'addressLocality' => '横浜市',
            'addressRegion' => '神奈川県',
            'postalCode' => '231-0042',
            'addressCountry' => 'JP',
        ),
    );
    if (function_exists('cruise_favicon_url')) { $organization['logo'] = cruise_favicon_url(); }
    if ($plan) {
        $service = array(
            '@type' => 'Service',
            '@id' => $url . '#cruise-service',
            'name' => wp_strip_all_tags(get_the_title($plan)),
            'description' => $description,
            'url' => $url,
            'serviceType' => '横浜・みなとみらいの貸切クルーズ',
            'areaServed' => array('横浜', 'みなとみらい'),
            'provider' => $provider,
        );
        if ($image) { $service['image'] = $image; }
        return array($organization, $service);
    }

    $graph = array($organization, array(
        '@type' => 'WebSite',
        '@id' => $home . '#website',
        'name' => 'C365 CRUISE',
        'url' => $home,
        'inLanguage' => 'ja',
        'publisher' => $provider,
    ));
    $items = array();
    foreach (c365_plans() as $published_plan) {
        $items[] = array(
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'url' => get_permalink($published_plan),
            'name' => wp_strip_all_tags(get_the_title($published_plan)),
        );
    }
    if ($items) { $graph[] = array('@type' => 'ItemList', 'name' => '貸切クルーズプラン', 'itemListElement' => $items); }
    if (function_exists('cruise_faq_items')) {
        $questions = array();
        foreach (cruise_faq_items() as $question => $answer) {
            $questions[] = array(
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => $answer),
            );
        }
        $graph[] = array('@type' => 'FAQPage', '@id' => $home . '#faq', 'inLanguage' => 'ja', 'mainEntity' => $questions);
    }
    return $graph;
}
