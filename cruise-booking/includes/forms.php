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
function c365_consent_html() {
    return '<label class="vc-consent"><input name="consent" type="checkbox" required><span><a href="'.esc_url(home_url('/privacy-policy/')).'" target="_blank">プライバシーポリシー</a>・<a href="'.esc_url(home_url('/booking-policy/')).'" target="_blank">予約・キャンセル規定</a>・<a href="'.esc_url(home_url('/commercial-disclosure/')).'" target="_blank">特商法表記</a>を確認し、同意します。</span></label>';
}
function c365_plan_booking($plan) {
    $jalan = get_post_meta($plan, '_c365_jalan', true);
    if (!c365_site_ready()) {
        return '<p>最新の空き状況は、じゃらんの掲載ページでご確認いただけます。</p>'
            . ($jalan ? '<a class="button" href="' . esc_url($jalan) . '">じゃらんで空き状況を見る</a>' : '');
    }
    $selected = isset($_GET['departure']) ? absint($_GET['departure']) : 0;
    $slots = c365_departures($plan);
    ob_start(); ?>
    <ul class="cruise-departures">
        <?php foreach ($slots as $slot) :
            if (is_wp_error(c365_price($slot->ID))) { continue; }
            $time = c365_date('Y年n月j日 H:i', c365_event_time($slot->ID));
            $url = add_query_arg('departure', $slot->ID, get_permalink($plan)) . '#booking';
        ?>
            <li><a href="<?php echo esc_url($url); ?>" <?php if ($selected === $slot->ID) { echo 'aria-current="true"'; } ?>><?php echo esc_html($time); ?></a><?php if (!c365_available($slot->ID)) { echo '（受付状況を確認）'; } ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if (!$slots) : ?><p>現在ご案内できる出航枠はありません。</p><?php endif; ?>
    <?php if ($jalan) : ?><p><a class="text-link" href="<?php echo esc_url($jalan); ?>">じゃらんの掲載ページを見る</a></p><?php endif; ?>
    <?php
    if ($selected && (int) get_post_meta($selected, '_c365_plan', true) === $plan && !is_wp_error(c365_price($selected))) {
        echo c365_booking_form($selected);
    }
    return ob_get_clean();
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
    <form class="vc-form vc-contact-form"><p>クルーズやご予約についてのご質問をお送りください。<br>お問い合わせ先：<?php echo esc_html(c365_contact_email()); ?> / <?php echo esc_html(c365_setting('phone', '045-297-6375')); ?></p><label>お名前 <b>必須</b><input name="name" autocomplete="name" maxlength="100" required></label><label>メールアドレス <b>必須</b><input name="email" type="email" autocomplete="email" maxlength="254" required></label><label>件名 <b>必須</b><input name="subject" maxlength="180" required></label><label>お問い合わせ内容 <b>必須</b><textarea name="message" rows="7" minlength="10" maxlength="5000" required></textarea></label><label class="vc-consent"><input name="consent" type="checkbox" required><span><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank">プライバシーポリシー</a>に同意します。</span></label><label class="vc-honey" aria-hidden="true">ウェブサイト<input name="website" tabindex="-1" autocomplete="off"></label><button class="vc-submit" type="submit">お問い合わせを送信する →</button><p class="vc-form-status" role="status" aria-live="polite"></p></form>
    <?php return ob_get_clean();
});
