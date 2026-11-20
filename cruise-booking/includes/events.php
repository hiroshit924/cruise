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
    ));
}

function c365_plans() {
    return get_posts(array(
        'post_type' => 'c365_plan', 'post_status' => 'publish', 'numberposts' => -1,
        'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'),
    ));
}

function c365_standard_schedule($plan) {
    $schedules = array(
        'day' => array('label' => 'デイクルーズ', 'times' => array('13:15', '14:30', '15:45', '17:00')),
        'night' => array('label' => 'ナイトクルーズ', 'times' => array('19:00')),
    );
    $schedule = get_post_meta($plan, '_c365_schedule', true);
    return $schedules[$schedule] ?? null;
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
    return c365_plan_booking_mode($plan) !== 'jalan' && c365_site_ready();
}

add_action('add_meta_boxes', function () {
    add_meta_box('c365_departure', '出航設定（日本時間・運航60分）', 'c365_departure_fields', 'c365_event');
    add_meta_box('c365_plan_settings', 'プラン料金・予約方法', 'c365_plan_fields', 'c365_plan');
});

function c365_plan_fields($post) {
    wp_nonce_field('c365_save_plan', 'c365_plan_nonce');
    $price = get_post_meta($post->ID, '_c365_charter_price', true);
    $mode = c365_plan_booking_mode($post->ID);
    $jalan = get_post_meta($post->ID, '_c365_jalan', true);
    ?>
    <p><label>貸切1組あたり（税込・円） <input type="number" min="1" step="1" name="c365_charter_price" value="<?php echo esc_attr($price); ?>"></label></p>
    <p><label>予約方法
        <select name="c365_booking_mode">
            <option value="auto" <?php selected($mode, 'auto'); ?>>サイト全体の受付設定に従う（既定）</option>
            <option value="direct" <?php selected($mode, 'direct'); ?>>自社サイトで予約する</option>
            <option value="jalan" <?php selected($mode, 'jalan'); ?>>じゃらんで予約する</option>
        </select>
    </label></p>
    <p><label for="c365-jalan-url">じゃらん掲載URL</label><br>
        <input id="c365-jalan-url" class="large-text" type="url" name="c365_jalan_url" value="<?php echo esc_attr($jalan); ?>">
    </p>
    <p class="description">自社サイトで予約するには、サイト全体の受付を有効にし、出航枠を公開してください。</p>
    <?php
}

add_action('save_post_c365_plan', function ($id) {
    if (!c365_may_edit($id, 'c365_plan_nonce', 'c365_save_plan')) { return; }
    if (isset($_POST['c365_charter_price']) && is_string($_POST['c365_charter_price'])) {
        update_post_meta($id, '_c365_charter_price', max(0, (int) $_POST['c365_charter_price']));
    }
    $mode = $_POST['c365_booking_mode'] ?? null;
    if (in_array($mode, array('auto', 'direct', 'jalan'), true)) {
        update_post_meta($id, '_c365_booking_mode', $mode);
    }
    if (isset($_POST['c365_jalan_url']) && is_string($_POST['c365_jalan_url'])) {
        $url = trim(wp_unslash($_POST['c365_jalan_url']));
        if ($url === '') {
            delete_post_meta($id, '_c365_jalan');
        } elseif (wp_http_validate_url($url)) {
            update_post_meta($id, '_c365_jalan', esc_url_raw($url, array('http', 'https')));
        }
    }
});

function c365_departure_fields($post) {
    wp_nonce_field('c365_save_departure', 'c365_departure_nonce');
    $date = get_post_meta($post->ID, '_c365_date', true);
    $plan_id = (int) get_post_meta($post->ID, '_c365_plan', true);
    $locked = c365_has_reservations($post->ID);
    ?>
    <p><label>出航日時 <input type="datetime-local" name="c365_date" value="<?php echo esc_attr($date); ?>" <?php disabled($locked); ?>></label></p>
    <p><label>プラン <select name="c365_plan" <?php disabled($locked); ?>><option value="">選択してください</option>
        <?php foreach (c365_plans() as $plan) : ?>
            <option value="<?php echo esc_attr($plan->ID); ?>" <?php selected($plan_id, $plan->ID); ?>><?php echo esc_html($plan->post_title); ?></option>
        <?php endforeach; ?>
    </select></label></p>
    <p>デイクルーズの通常出航時刻は13:15・14:30・15:45・17:00、ナイトクルーズの通常出航時刻は19:00です。公開する日付と時刻を入力してください。</p>
    <p><?php echo $locked ? '予約履歴がある出航枠の日時・プランは固定されます。' : 'じゃらんと予約枠は共有されません。自社サイト用に確保した枠を登録してください。'; ?></p>
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

add_action('save_post_c365_event', function ($id) {
    if (!c365_may_edit($id, 'c365_departure_nonce', 'c365_save_departure')
        || !isset($_POST['c365_date'], $_POST['c365_plan']) || !is_string($_POST['c365_date']) || !is_string($_POST['c365_plan'])) { return; }
    $value = sanitize_text_field(wp_unslash($_POST['c365_date']));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, c365_tokyo());
    $plan = absint($_POST['c365_plan']);
    if (!$date || $date->format('Y-m-d\TH:i') !== $value || get_post_type($plan) !== 'c365_plan' || !c365_lock()) { return; }
    try {
        if (!c365_has_reservations($id)) {
            update_post_meta($id, '_c365_plan', $plan);
            update_post_meta($id, '_c365_date', $value);
        }
    } finally { c365_unlock(); }
});

add_filter('pre_delete_post', function ($delete, $post) {
    if ($post->post_type === 'c365_event' && c365_has_reservations($post->ID)) { return false; }
    return $delete;
}, 10, 2);
