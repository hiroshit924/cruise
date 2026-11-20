<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function () {
    $screen = add_submenu_page(
        'edit.php?post_type=c365_event',
        '出航枠を追加',
        '出航枠を追加',
        'publish_posts',
        'c365-departure-dates',
        'c365_departure_dates_screen'
    );
    if ($screen) {
        add_action('load-' . $screen, function () {
            wp_enqueue_script('c365-departure-dates', C365_BOOKING_URL . 'assets/departure-dates.js', array(), C365_BOOKING_VERSION, true);
        });
    }
});

function c365_departure_dates_screen() {
    $plans = c365_plans();
    $today = c365_date('Y-m-d', time());
    ?>
    <div class="wrap">
        <h1>出航枠を追加</h1>
        <p>開始日と終了日を含む期間に、各プランの所定の出航時間で枠を公開します。1日だけ追加する場合は開始日と終了日を同じ日にしてください。</p>
        <p>日付は日本時間です。じゃらんと予約枠は共有されません。</p>
        <?php if ($plans) : ?>
            <form id="c365-departure-dates" method="post">
                <?php wp_nonce_field('c365_add_departure_dates', 'nonce', false); ?>
                <input type="hidden" name="action" value="c365_add_departure_dates">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="c365-start-date">開始日</label></th>
                        <td><input id="c365-start-date" type="date" name="start_date" min="<?php echo esc_attr($today); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="c365-end-date">終了日</label></th>
                        <td><input id="c365-end-date" type="date" name="end_date" min="<?php echo esc_attr($today); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="c365-departure-plan">対象プラン</label></th>
                        <td><select id="c365-departure-plan" name="plan">
                            <option value="all">全プラン共通</option>
                            <?php foreach ($plans as $plan) : ?>
                                <option value="<?php echo esc_attr($plan->ID); ?>"><?php echo esc_html($plan->post_title); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                </table>
                <?php submit_button('出航枠を追加して公開'); ?>
                <p id="c365-departure-result" role="status"></p>
            </form>
        <?php else : ?>
            <p>先にクルーズプランを公開してください。</p>
        <?php endif; ?>
    </div>
    <?php
}

function c365_read_departure_date($value) {
    if (!is_string($value)) { return null; }
    $value = wp_unslash($value);
    if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) { return null; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, c365_tokyo());
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}

add_action('wp_ajax_c365_add_departure_dates', function () {
    if (!current_user_can('publish_posts') || !check_ajax_referer('c365_add_departure_dates', 'nonce', false)) {
        wp_send_json_error('ページを開き直してから操作してください。', 403);
    }
    $start = c365_read_departure_date($_POST['start_date'] ?? null);
    $end = c365_read_departure_date($_POST['end_date'] ?? null);
    if (!$start || !$end || $start > $end) {
        wp_send_json_error('開始日と終了日を確認してください。', 400);
    }
    $scope = $_POST['plan'] ?? '';
    $plans = array();
    foreach (c365_plans() as $plan) {
        if ($scope === 'all' || $scope === (string) $plan->ID) {
            $plans[$plan->ID] = $plan;
        }
    }
    if (!$plans) { wp_send_json_error('公開済みのクルーズプランを選択してください。', 400); }

    // Limit each request to one week so longer periods can continue in a new request.
    $batch_end = min($start->modify('+6 days'), $end);
    $result = c365_add_departure_dates($start, $batch_end, $plans);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 503);
    }
    $result['next_date'] = $batch_end < $end ? $batch_end->modify('+1 day')->format('Y-m-d') : null;
    wp_send_json_success($result);
});

function c365_add_departure_dates($start, $end, $plans) {
    $result = array('created' => 0, 'existing' => 0, 'closed' => 0, 'failed' => 0);
    if (!c365_lock()) {
        return new WP_Error('busy', '処理が混み合っています。同じ期間で再実行してください。');
    }
    try {
        $existing = array();
        $posts = get_posts(array(
            'post_type' => 'c365_event',
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'numberposts' => -1,
            'meta_query' => array(
                array('key' => '_c365_plan', 'value' => array_keys($plans), 'compare' => 'IN', 'type' => 'NUMERIC'),
                array('key' => '_c365_date', 'value' => array($start->format('Y-m-d') . 'T00:00', $end->format('Y-m-d') . 'T23:59'), 'compare' => 'BETWEEN'),
            ),
        ));
        foreach ($posts as $post) {
            $plan_id = (int) get_post_meta($post->ID, '_c365_plan', true);
            $date = get_post_meta($post->ID, '_c365_date', true);
            if (is_string($date)) { $existing[$plan_id][$date] = true; }
        }
        foreach ($plans as $plan_id => $plan) {
            $schedule = c365_standard_schedule($plan_id);
            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
                foreach ($schedule['times'] as $time) {
                    $date = $day->format('Y-m-d') . 'T' . $time;
                    if (isset($existing[$plan_id][$date])) {
                        $result['existing']++;
                        continue;
                    }
                    $departure = new DateTimeImmutable($date, c365_tokyo());
                    if ($departure->getTimestamp() <= time() + 30 * MINUTE_IN_SECONDS) {
                        $result['closed']++;
                        continue;
                    }
                    $title = $day->format('Y-m-d') . ' ' . $time . ' ' . $plan->post_title;
                    $id = wp_insert_post(wp_slash(array(
                        'post_type' => 'c365_event',
                        'post_status' => 'draft',
                        'post_title' => $title,
                        'meta_input' => array('_c365_plan' => $plan_id, '_c365_date' => $date),
                    )), true);
                    if (is_wp_error($id)) {
                        $result['failed']++;
                        continue;
                    }
                    if ((int) get_post_meta($id, '_c365_plan', true) !== $plan_id || get_post_meta($id, '_c365_date', true) !== $date
                        || is_wp_error(wp_update_post(array('ID' => $id, 'post_status' => 'publish'), true))) {
                        wp_delete_post($id, true);
                        $result['failed']++;
                        continue;
                    }
                    $result['created']++;
                }
            }
        }
        return $result;
    } finally {
        c365_unlock();
    }
}
