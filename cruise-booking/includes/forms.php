<?php
if (!defined('ABSPATH')) { exit; }
add_action('wp_enqueue_scripts',function () {
    if (is_singular('c365_plan') || (is_singular() && (has_shortcode(get_post()->post_content ?? '', 'cruise_contact')))) {
        wp_enqueue_style('vc-forms',C365_BOOKING_URL.'assets/forms.css',array(),C365_BOOKING_VERSION);
        wp_enqueue_script('vc-forms',C365_BOOKING_URL.'assets/forms.js',array(),C365_BOOKING_VERSION,true);
        $square=c365_square_config();
        wp_localize_script('vc-forms','CruiseConfig',array('api'=>rest_url('cruise/v1/'),'appId'=>$square['app_id'],'locationId'=>$square['location_id'],'mode'=>c365_square_mode(),'squareReady'=>(bool)c365_square_ready()));
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
        nocache_headers();
    }
});
function c365_form_page_url($slug) {
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page) : home_url('/' . $slug . '/');
}
function c365_consent_html() {
    return '<label class="vc-consent"><input name="consent" type="checkbox" required><span><a href="'.esc_url(c365_form_page_url('privacy-policy')).'" target="_blank">プライバシーポリシー</a>・<a href="'.esc_url(c365_form_page_url('booking-policy')).'" target="_blank">予約・キャンセル規定</a>・<a href="'.esc_url(c365_form_page_url('commercial-disclosure')).'" target="_blank">特商法表記</a>を確認し、同意します。</span></label>';
}
function c365_plan_booking($plan) {
    $jalan = get_post_meta($plan, '_c365_jalan', true);
    $mode = c365_plan_booking_mode($plan);
    if (!c365_plan_accepts_bookings($plan)) {
        if ($mode !== 'direct' && $jalan) {
            return '<p>最新の空き状況は、じゃらんの掲載ページでご確認いただけます。</p>'
                . '<a class="button" href="' . esc_url($jalan) . '">じゃらんで空き状況を見る</a>';
        }
        return '<p>現在、このプランの予約受付は準備中です。</p>';
    }
    $plan_url = get_permalink($plan);
    $departure = isset($_GET['departure']) && is_scalar($_GET['departure']) ? absint($_GET['departure']) : 0;
    $requested_date = isset($_GET['booking_date']) && is_string($_GET['booking_date']) ? wp_unslash($_GET['booking_date']) : '';
    $requested_month = isset($_GET['booking_month']) && is_string($_GET['booking_month']) ? wp_unslash($_GET['booking_month']) : '';
    $days = array();
    $departure_date = null;
    foreach (c365_departures($plan) as $slot) {
        if (is_wp_error(c365_price($slot->ID))) { continue; }
        $timestamp = c365_event_time($slot->ID);
        $date = c365_date('Y-m-d', $timestamp);
        $days[$date][$slot->ID] = $timestamp;
        if ($departure === $slot->ID) { $departure_date = $date; }
    }
    $selected_date = $departure_date ?? (isset($days[$requested_date]) ? $requested_date : '');
    $current_month = c365_date('Y-m', time());
    $first_month = $days ? substr(array_key_first($days), 0, 7) : $current_month;
    $last_month = $days ? substr(array_key_last($days), 0, 7) : $current_month;
    $month_key = $selected_date ? substr($selected_date, 0, 7) : $first_month;
    if (!$selected_date && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $requested_month)
        && $requested_month >= $current_month && $requested_month <= $last_month) {
        $month_key = $requested_month;
    }
    $month = new DateTimeImmutable($month_key . '-01', c365_tokyo());
    $availability = array();
    foreach ($days as $date => $slots) {
        if (substr($date, 0, 7) !== $month_key) { continue; }
        foreach (array_keys($slots) as $id) {
            $availability[$date][$id] = c365_available($id);
            if ($date !== $selected_date && $availability[$date][$id]) { break; }
        }
    }
    ob_start();
    c365_booking_calendar($plan_url, $month, $current_month, $last_month, $selected_date, $availability);
    if (!$availability) : ?><p>この月にご案内できる出航枠はありません。</p><?php endif; ?>
    <?php if ($selected_date) : ?>
        <section class="vc-slot-picker" id="booking-times" aria-labelledby="booking-times-heading">
            <h3 id="booking-times-heading"><?php echo esc_html(c365_date('n月j日', reset($days[$selected_date]))); ?>の出航時間</h3>
            <ul class="vc-slot-list">
                <?php foreach ($days[$selected_date] as $id => $timestamp) :
                    $time = c365_date('H:i', $timestamp);
                    $is_current = $departure_date && $departure === $id;
                    ?>
                    <li>
                        <?php if ($availability[$selected_date][$id]) :
                            $url = add_query_arg('departure', $id, $plan_url) . '#booking-form';
                            ?>
                            <a class="vc-slot-option" href="<?php echo esc_url($url); ?>" <?php if ($is_current) { echo 'aria-current="true"'; } ?>><?php echo esc_html($time); ?></a>
                        <?php else : ?>
                            <span class="vc-slot-option vc-slot-full"><?php echo esc_html($time); ?> <span>満席</span></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    <?php if ($departure_date) : ?>
        <div id="booking-form"><?php echo c365_booking_form($departure); ?></div>
    <?php endif; ?>
    <?php if ($mode === 'auto' && $jalan) : ?><p><a class="text-link" href="<?php echo esc_url($jalan); ?>">じゃらんの掲載ページを見る</a></p><?php endif; ?>
    <?php return ob_get_clean();
}

function c365_booking_calendar($plan_url, $month, $first_month, $last_month, $selected_date, $availability) {
    $month_key = $month->format('Y-m');
    $previous_url = add_query_arg('booking_month', $month->modify('-1 month')->format('Y-m'), $plan_url) . '#booking';
    $next_url = add_query_arg('booking_month', $month->modify('+1 month')->format('Y-m'), $plan_url) . '#booking';
    $offset = (int) $month->format('w');
    $days_in_month = (int) $month->format('t');
    ?>
    <div class="vc-booking-calendar">
        <div class="vc-calendar-header">
            <?php if ($month_key > $first_month) : ?>
                <a class="vc-calendar-prev" href="<?php echo esc_url($previous_url); ?>">‹ 前月</a>
            <?php endif; ?>
            <h3 id="booking-calendar-month"><?php echo esc_html($month->format('Y年n月')); ?></h3>
            <?php if ($month_key < $last_month) : ?>
                <a class="vc-calendar-next" href="<?php echo esc_url($next_url); ?>">翌月 ›</a>
            <?php endif; ?>
        </div>
        <table class="vc-calendar-grid" aria-labelledby="booking-calendar-month">
            <thead><tr>
                <?php foreach (array('日', '月', '火', '水', '木', '金', '土') as $weekday) : ?>
                    <th scope="col"><?php echo esc_html($weekday); ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php for ($week = 0; $week * 7 < $offset + $days_in_month; $week++) : ?>
                <tr>
                <?php for ($weekday = 0; $weekday < 7; $weekday++) :
                    $day = $week * 7 + $weekday - $offset + 1;
                    ?>
                    <td>
                        <?php if ($day >= 1 && $day <= $days_in_month) :
                            $date = $month_key . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                            if (isset($availability[$date])) :
                                $available = in_array(true, $availability[$date], true);
                                $url = add_query_arg('booking_date', $date, $plan_url) . '#booking-times';
                                $label = $month->format('n月') . $day . '日' . ($available ? '、空きあり' : '');
                                ?>
                                <a class="vc-calendar-day<?php if ($available) { echo ' vc-calendar-day-available'; } ?>" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($label); ?>" <?php if ($date === $selected_date) { echo 'aria-current="date"'; } ?>><?php echo esc_html($day); ?></a>
                            <?php else : ?>
                                <span class="vc-calendar-day"><?php echo esc_html($day); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
        <?php if ($availability) : ?>
            <p class="vc-calendar-note"><span aria-hidden="true">●</span> 空きのある日。日付を選ぶと出航時間を確認できます。</p>
        <?php endif; ?>
    </div>
    <?php
}

function c365_booking_form($id) {
    ob_start(); ?>
    <form class="vc-form vc-booking-form" data-event="<?php echo esc_attr($id); ?>">
    <p class="vc-form-note">出航 <?php echo esc_html(c365_date('Y年n月j日 H:i', c365_event_time($id))); ?>（日本時間）。30分前までにC365にお越しください。</p>
    <label>お名前 <b>必須</b><input name="name" autocomplete="name" maxlength="100" required></label>
    <label>メールアドレス <b>必須</b><input name="email" type="email" autocomplete="email" maxlength="254" required></label>
    <label>電話番号 <b>必須</b><input name="phone" type="tel" autocomplete="tel" maxlength="24" required></label>
    <label>乗船人数 <b>必須</b><input name="passenger_count" type="number" min="1" max="5" step="1" value="2" aria-describedby="cruise-passenger-note" required></label>
    <p id="cruise-passenger-note" class="vc-form-note">1組2〜5名。1名でのご利用は2名分の料金です。</p>
    <label class="vc-consent"><input type="checkbox" name="age_confirmed" required><span>乗船する全員が小学生以上（6歳以上）です。</span></label>
    <div class="vc-quote" aria-live="polite">貸切料金を確認しています。</div>
    <fieldset><legend>お支払い方法</legend><label class="vc-radio"><input type="radio" name="method" value="cash" checked>当日現地決済</label><label class="vc-radio"><input type="radio" name="method" value="square" <?php disabled(!c365_square_ready()); ?>>事前カード決済（Square）</label><?php if (!c365_square_ready()) : ?><small>事前決済は接続設定後にご利用いただけます。</small><?php endif; ?></fieldset>
    <div class="vc-card-section" hidden><?php if (c365_square_mode()==='sandbox') : ?><p class="vc-notice">テスト決済モード：実際のカードでは決済しないでください。</p><?php endif; ?><div class="vc-square-card"></div><p class="vc-form-note">カード情報はSquareが安全に処理し、当サイトでは保存しません。</p></div>
    <div class="vc-policy-note"><?php echo esc_html(c365_policy_text()); ?></div>
    <?php echo c365_consent_html(); ?><label class="vc-honey" aria-hidden="true">ウェブサイト<input name="website" tabindex="-1" autocomplete="off"></label>
    <button type="submit" class="vc-submit">内容を確認して予約する →</button><button type="button" class="vc-reconcile" hidden>決済状況を再確認する</button>
    <p class="vc-form-status" role="status" aria-live="polite"></p><div class="vc-result" hidden></div>
    <p class="vc-form-note">確認メールが届かない場合は、迷惑メールフォルダーをご確認のうえ、<?php echo esc_html(c365_contact_email()); ?> へお問い合わせください。</p>
    </form><?php return ob_get_clean();
}
add_shortcode('cruise_contact',function () {
    ob_start(); ?>
    <form class="vc-form vc-contact-form"><p>クルーズやご予約についてのご質問をお送りください。<br>お問い合わせ先：<?php echo esc_html(c365_contact_email()); ?> / <?php echo esc_html(c365_setting('phone', '045-297-6375')); ?></p><label>お名前 <b>必須</b><input name="name" autocomplete="name" maxlength="100" required></label><label>メールアドレス <b>必須</b><input name="email" type="email" autocomplete="email" maxlength="254" required></label><label>件名 <b>必須</b><input name="subject" maxlength="180" required></label><label>お問い合わせ内容 <b>必須</b><textarea name="message" rows="7" minlength="10" maxlength="5000" required></textarea></label><label class="vc-consent"><input name="consent" type="checkbox" required><span><a href="<?php echo esc_url(c365_form_page_url('privacy-policy')); ?>" target="_blank">プライバシーポリシー</a>に同意します。</span></label><label class="vc-honey" aria-hidden="true">ウェブサイト<input name="website" tabindex="-1" autocomplete="off"></label><button class="vc-submit" type="submit">お問い合わせを送信する →</button><p class="vc-form-status" role="status" aria-live="polite"></p></form>
    <?php return ob_get_clean();
});
