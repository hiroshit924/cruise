<?php
/** C365 Cruise theme setup. */
if (!defined('ABSPATH')) { exit; }
function cruise_setup() {
    load_theme_textdomain('cruise', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array('height' => 100, 'width' => 420, 'flex-height' => true, 'flex-width' => true));
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('responsive-embeds');
    add_theme_support('automatic-feed-links');
    register_nav_menus(array('footer' => 'フッターメニュー（規約・プライバシーなど）'));
}
add_action('after_setup_theme', 'cruise_setup');
function cruise_assets() {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('cruise', get_stylesheet_uri(), array(), $version);
    wp_enqueue_script('cruise', get_template_directory_uri() . '/assets/main.js', array(), $version, true);
}
add_action('wp_enqueue_scripts', 'cruise_assets');
function cruise_favicon_url() {
    return get_site_icon_url(192) ?: get_template_directory_uri() . '/assets/c365-cruise-favicon.png';
}
add_action('wp_head', function () {
    if (!has_site_icon()) {
        echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url(cruise_favicon_url()) . '">' . "\n";
    }
});
add_action('customize_register', function ($customizer) {
    $customizer->add_section('cruise_options', array('title' => 'クルーズ設定', 'priority' => 30));
    $customizer->add_setting('cruise_hero', array('sanitize_callback' => 'esc_url_raw'));
    $customizer->add_control(new WP_Customize_Image_Control($customizer, 'cruise_hero', array(
        'label' => 'メイン写真', 'section' => 'cruise_options',
    )));
});
function cruise_icon($name, $class = '') {
    $paths = array('arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>', 'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>', 'heart' => '<path d="m12 21-8-8C-3 6 6-2 12 5c6-7 15 1 8 8Z"/>', 'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a6 6 0 0 1 3 5v2"/>', 'star' => '<path d="m12 2 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1Z"/>', 'cup' => '<path d="M4 3h12v9a6 6 0 0 1-12 0ZM16 5h2a3 3 0 0 1 0 6h-2M2 21h17"/>', 'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/>', 'check' => '<path d="m5 12 4 4L19 6"/>');
    return '<svg class="icon ' . esc_attr($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['arrow']) . '</svg>';
}
function cruise_brand() {
    if (has_custom_logo()) { the_custom_logo(); return; }
    $logo = get_template_directory_uri() . '/assets/c365-cruise-logo.png';
    echo '<a class="brand" href="' . esc_url(cruise_local_url(home_url('/'))) . '"><img width="1000" height="333" src="' . esc_url($logo) . '" alt="' . esc_attr(cruise_text('C365クルーズ ホーム', 'C365 Cruise home')) . '"></a>';
}
function cruise_is_english() {
    return isset($_GET['cruise_lang']) && $_GET['cruise_lang'] === 'en';
}

function cruise_text($japanese, $english) {
    return cruise_is_english() ? $english : $japanese;
}

function cruise_output($japanese, $english) {
    echo esc_html(cruise_text($japanese, $english));
}

function cruise_local_url($url) {
    return cruise_is_english() ? add_query_arg('cruise_lang', 'en', $url) : $url;
}

function cruise_locale_switch() {
    $url = is_singular() ? get_permalink() : home_url('/');
    foreach (array('booking_month', 'booking_date', 'departure') as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            $url = add_query_arg($key, urlencode(wp_unslash($_GET[$key])), $url);
        }
    }
    $english = cruise_is_english();
    if (!$english) { $url = add_query_arg('cruise_lang', 'en', $url); }
    echo '<a class="cruise-language" href="' . esc_url($url) . '" hreflang="' . ($english ? 'ja' : 'en') . '" lang="' . ($english ? 'ja' : 'en') . '">' . ($english ? '日本語' : 'English') . '</a>';
}

function cruise_plan_copy($plan) {
    if (cruise_is_english() && function_exists('c365_english_plan')) { return c365_english_plan($plan); }
    return array(get_the_title($plan), get_the_excerpt($plan));
}

add_filter('language_attributes', function ($attributes) {
    return cruise_is_english() ? 'lang="en"' : $attributes;
});

function cruise_page_url($slug) {
    $page = get_page_by_path($slug);
    return cruise_local_url($page ? get_permalink($page) : home_url('/' . $slug . '/'));
}
function cruise_event_image($id) {
    if ($id && has_post_thumbnail($id)) { return get_the_post_thumbnail_url($id, 'large'); }
    $name = $id ? get_post_meta($id, '_c365_image', true) : '';
    if (!in_array($name, array('private-cruise', 'dog-cruise', 'dog-night-cruise'), true)) { $name = 'hero'; }
    return get_template_directory_uri() . '/assets/' . $name . '.jpg';
}

function cruise_plan_price_label($plan) {
    $direct = function_exists('c365_plan_accepts_bookings')
        ? c365_plan_accepts_bookings($plan)
        : (function_exists('c365_site_ready') && c365_site_ready());
    $price_key = $direct || cruise_is_english() ? '_c365_charter_price' : '_c365_jalan_price';
    $price = (int) get_post_meta($plan, $price_key, true);
    if (!$price) { return cruise_text('料金はお問い合わせください', 'Contact us for pricing'); }
    if (cruise_is_english()) { return '¥' . number_format_i18n($price) . ' per charter (tax included)'; }
    $amount = '1組 ' . number_format_i18n($price) . '円';
    return $direct ? $amount . '（税込）' : 'じゃらん掲載 ' . $amount . '〜';
}

function cruise_faq_items() {
    if (cruise_is_english()) {
        return array(
            'How long is the private cruise?' => 'The cruise takes 60 minutes, departing from Hinode Pier and heading along the Ōoka River toward Minato Mirai.',
            'Where do we meet?' => 'Meet at C365, 44-2 Fukutomichō Nishidōri, Naka Ward, Yokohama, at least 30 minutes before departure. It is a five-minute walk from Hinodechō Station or a ten-minute walk from Sakuragichō Station.',
            'Can I book for one person?' => 'Yes. A solo passenger pays the same charter rate as two passengers. The boat takes up to five passengers.',
            'Can children join?' => 'Children aged six and over who are in elementary school or older may join.',
            'Can I bring my dog?' => 'Dogs can join either dog-friendly cruise. Please bring a carrier or leash and a dog diaper.',
            'What should I wear?' => 'Bring a warm layer and clothes you do not mind getting wet. Passenger life jackets are included.',
            'What if the weather is bad?' => 'If conditions require cancellation, we will contact you by 6:00 p.m. the day before departure. Operator cancellations are refunded in full.',
        );
    }
    return array(
        '横浜・みなとみらいの貸切クルーズは何分ですか？' => '運航は60分です。日ノ出桟橋から大岡川を抜け、みなとみらいへ向かいます。',
        'クルーズの集合場所はどこですか？' => '出航30分前までにC365（神奈川県横浜市中区福富町西通44-2）へお越しください。京急 日ノ出町駅から徒歩5分、JR 桜木町駅から徒歩10分です。',
        '1名でも利用できますか？' => 'はい。おひとりでも2名分の料金でご利用いただけます。1組の乗船人数は最大5名です。',
        '子どもは乗船できますか？' => '小学生以上（6歳以上）のお子さまはご乗船いただけます。1組につき最大5名です。',
        '愛犬と一緒に乗船できますか？' => 'ドッグクルーズ2プランで同乗いただけます。ケージ・バッグまたはリードをご用意いただき、マナーパンツを着用してください。',
        'どんな服装で行けばよいですか？' => '波しぶきがかかることがあります。濡れてもよい服装と防寒着をご用意ください。人用のライフジャケットは料金に含まれます。',
        '天候が悪い場合はどうなりますか？' => '天候や海況により中止する場合は、前日18時までにご連絡します。主催者都合による中止の場合、キャンセル料はかかりません。',
    );
}
