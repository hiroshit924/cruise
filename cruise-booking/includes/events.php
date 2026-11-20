<?php
if (!defined('ABSPATH')) { exit; }

function c365_register_events() {
    register_post_type('c365_plan', array(
        'labels' => array('name' => 'クルーズプラン', 'singular_name' => 'クルーズプラン'),
        'public' => true, 'show_in_rest' => true, 'rewrite' => array('slug' => 'plans'),
        'menu_icon' => 'dashicons-palmtree', 'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
    ));
    register_post_type('c365_event', array(
        'labels' => array('name' => '出航枠', 'singular_name' => '出航枠', 'add_new_item' => '出航枠を追加'),
        'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-calendar-alt', 'supports' => array('title'),
        'capabilities' => array('create_posts' => 'do_not_allow'),
    ));
}

function c365_plans() {
    return get_posts(array(
        'post_type' => 'c365_plan', 'post_status' => 'publish', 'numberposts' => -1,
        'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'),
    ));
}

function c365_standard_schedules() {
    return array(
        'day' => array('label' => 'デイクルーズ', 'times' => array('13:15', '14:30', '15:45', '17:00')),
        'night' => array('label' => 'ナイトクルーズ', 'times' => array('19:00')),
    );
}

function c365_standard_schedule($plan) {
    $schedules = c365_standard_schedules();
    $schedule = get_post_meta($plan, '_c365_schedule', true);
    return is_string($schedule) && isset($schedules[$schedule]) ? $schedules[$schedule] : $schedules['day'];
}

function c365_departures($plan) {
    return get_posts(array(
        'post_type' => 'c365_event', 'post_status' => 'publish', 'numberposts' => -1,
        'meta_key' => '_c365_date', 'orderby' => 'meta_value', 'order' => 'ASC',
        'meta_query' => array(
            array('key' => '_c365_plan', 'value' => $plan, 'type' => 'NUMERIC'),
            array('key' => '_c365_date', 'value' => c365_date('Y-m-d\TH:i', time() + 30 * MINUTE_IN_SECONDS), 'compare' => '>'),
        ),
    ));
}

function c365_plan_booking_mode($plan) {
    $mode = get_post_meta($plan, '_c365_booking_mode', true);
    return in_array($mode, array('direct', 'jalan'), true) ? $mode : 'auto';
}

function c365_plan_accepts_bookings($plan) {
    return c365_site_ready() && (c365_is_english() || c365_plan_booking_mode($plan) !== 'jalan');
}

add_action('add_meta_boxes', function () {
    add_meta_box('c365_departure', '出航設定（日本時間・運航60分）', 'c365_departure_fields', 'c365_event');
    add_meta_box('c365_plan_settings', 'プラン料金・予約方法・じゃらんURL', 'c365_plan_fields', 'c365_plan');
});

add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'c365_plan' && current_user_can('edit_post', $post->ID)) {
        $actions['c365_booking_settings'] = '<a href="' . esc_url(c365_plan_settings_url($post->ID)) . '">予約方法・じゃらんURL</a>';
    }
    return $actions;
}, 10, 2);

function c365_plan_settings_url($plan = 0) {
    $args = array('post_type' => 'c365_plan', 'page' => 'c365-plan-booking');
    if ($plan) { $args['plan'] = $plan; }
    return add_query_arg($args, admin_url('edit.php'));
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=c365_plan', '予約方法・じゃらんURL', '予約方法・じゃらんURL',
        'edit_posts', 'c365-plan-booking', 'c365_plan_settings_screen'
    );
});

function c365_plan_settings_screen() {
    $id = isset($_GET['plan']) && is_string($_GET['plan']) ? absint($_GET['plan']) : 0;
    $plan = $id && get_post_type($id) === 'c365_plan' && current_user_can('edit_post', $id) ? get_post($id) : null;
    ?>
    <div class="wrap">
        <h1>予約方法・じゃらんURL</h1>
        <?php if ($plan) : ?>
            <h2><?php echo esc_html(get_the_title($plan)); ?></h2>
            <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success"><p>設定を保存しました。</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="c365_update_plan_booking">
                <input type="hidden" name="plan" value="<?php echo esc_attr($id); ?>">
                <?php c365_plan_fields($plan); submit_button('設定を保存'); ?>
            </form>
        <?php endif; ?>
        <p>設定するプランを選んでください。</p>
        <ul>
            <?php foreach (c365_plans() as $published_plan) :
                if (!current_user_can('edit_post', $published_plan->ID)) { continue; }
                ?>
                <li><a href="<?php echo esc_url(c365_plan_settings_url($published_plan->ID)); ?>"><?php echo esc_html(get_the_title($published_plan)); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

add_action('admin_post_c365_update_plan_booking', function () {
    $id = isset($_POST['plan']) && is_string($_POST['plan']) ? absint($_POST['plan']) : 0;
    if (!$id || get_post_type($id) !== 'c365_plan' || !current_user_can('edit_post', $id)) {
        wp_die('このプランを編集する権限がありません。', '', array('response' => 403));
    }
    check_admin_referer('c365_save_plan', 'c365_plan_nonce');
    c365_save_plan_fields($id);
    wp_safe_redirect(add_query_arg('updated', 1, c365_plan_settings_url($id)));
    exit;
});

function c365_plan_fields($post) {
    wp_nonce_field('c365_save_plan', 'c365_plan_nonce');
    $price = get_post_meta($post->ID, '_c365_charter_price', true);
    $mode = c365_plan_booking_mode($post->ID);
    $jalan = get_post_meta($post->ID, '_c365_jalan', true);
    $schedules = c365_standard_schedules();
    $schedule = get_post_meta($post->ID, '_c365_schedule', true);
    $schedule = is_string($schedule) && isset($schedules[$schedule]) ? $schedule : 'day';
    ?>
    <p><label>貸切1組あたり（税込・円） <input type="number" min="1" step="1" name="c365_charter_price" value="<?php echo esc_attr($price); ?>"></label></p>
    <p><label>予約方法
        <select name="c365_booking_mode">
            <option value="auto" <?php selected($mode, 'auto'); ?>>サイト全体の受付設定に従う（既定）</option>
            <option value="direct" <?php selected($mode, 'direct'); ?>>自社サイトで予約する</option>
            <option value="jalan" <?php selected($mode, 'jalan'); ?>>じゃらんで予約する</option>
        </select>
    </label></p>
    <p><label>所定の出航時間帯
        <select name="c365_schedule">
            <?php foreach ($schedules as $key => $options) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($schedule, $key); ?>><?php echo esc_html($options['label'] . '（' . implode('・', $options['times']) . '）'); ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p class="description">時間帯の変更は今後追加する出航枠に適用されます。</p>
    <p><label for="c365-jalan-url">じゃらん掲載URL</label><br>
        <input id="c365-jalan-url" class="large-text" type="url" name="c365_jalan_url" value="<?php echo esc_attr($jalan); ?>">
        <?php if ($jalan) : ?>
            <a href="<?php echo esc_url($jalan); ?>" target="_blank" rel="noopener">現在のじゃらん掲載ページを開く</a>
        <?php endif; ?>
    </p>
    <p><label for="c365-english-title">英語のプラン名</label><br>
        <input id="c365-english-title" class="large-text" type="text" name="c365_english_title" value="<?php echo esc_attr(c365_english_plan($post)[0]); ?>">
    </p>
    <p><label for="c365-english-description">英語のプラン紹介</label><br>
        <textarea id="c365-english-description" class="large-text" rows="3" name="c365_english_description"><?php echo esc_textarea(c365_english_plan($post)[1]); ?></textarea>
    </p>
    <p class="description">この予約方法は日本語版で使用します。英語版は自社予約です。自社予約を受け付けるには、サイト全体の受付を有効にし、出航枠を公開してください。</p>
    <?php
}

add_action('save_post_c365_plan', 'c365_save_plan_fields');
function c365_save_plan_fields($id) {
    if (!c365_may_edit($id, 'c365_plan_nonce', 'c365_save_plan')) { return; }
    if (isset($_POST['c365_charter_price']) && is_string($_POST['c365_charter_price'])) {
        update_post_meta($id, '_c365_charter_price', max(0, (int) $_POST['c365_charter_price']));
    }
    $mode = $_POST['c365_booking_mode'] ?? null;
    if (in_array($mode, array('auto', 'direct', 'jalan'), true)) {
        update_post_meta($id, '_c365_booking_mode', $mode);
    }
    $schedule = $_POST['c365_schedule'] ?? null;
    if (is_string($schedule) && isset(c365_standard_schedules()[$schedule])) {
        update_post_meta($id, '_c365_schedule', $schedule);
    }
    foreach (array('title' => 'sanitize_text_field', 'description' => 'sanitize_textarea_field') as $field => $sanitize) {
        $key = 'c365_english_' . $field;
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            update_post_meta($id, '_' . $key, $sanitize(wp_unslash($_POST[$key])));
        }
    }
    if (isset($_POST['c365_jalan_url']) && is_string($_POST['c365_jalan_url'])) {
        $url = trim(wp_unslash($_POST['c365_jalan_url']));
        if ($url === '') {
            delete_post_meta($id, '_c365_jalan');
        } elseif (wp_http_validate_url($url)) {
            update_post_meta($id, '_c365_jalan', esc_url_raw($url, array('http', 'https')));
        }
    }
}

function c365_departure_fields($post) {
    $time = c365_event_time($post->ID);
    $plan = (int) get_post_meta($post->ID, '_c365_plan', true);
    ?>
    <p>出航日時 <?php echo esc_html($time ? c365_date('Y年n月j日 H:i', $time) : '未設定'); ?></p>
    <p>プラン <?php echo esc_html($plan ? get_the_title($plan) : '未設定'); ?></p>
    <?php if (c365_has_reservations($post->ID)) : ?><p>予約履歴がある出航枠です。</p><?php endif; ?>
    <?php
}

function c365_may_edit($id, $field, $action) {
    return isset($_POST[$field]) && is_string($_POST[$field])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$field])), $action)
        && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        && current_user_can('edit_post', $id) && !wp_is_post_revision($id);
}

function c365_has_reservations($event) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . c365_table() . ' WHERE event_id=%d LIMIT 1', $event));
}

add_filter('pre_delete_post', function ($delete, $post) {
    if ($post->post_type === 'c365_event' && c365_has_reservations($post->ID)) { return false; }
    return $delete;
}, 10, 2);
