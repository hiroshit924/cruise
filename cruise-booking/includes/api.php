<?php
if (!defined('ABSPATH')) { exit; }
add_action('rest_api_init', function () {
    register_rest_route('cruise/v1','/session',array('methods'=>'GET','callback'=>'c365_api_session','permission_callback'=>'__return_true'));
    register_rest_route('cruise/v1','/quote',array('methods'=>'GET','callback'=>'c365_api_quote','permission_callback'=>'__return_true'));
    register_rest_route('cruise/v1','/reservations',array('methods'=>'POST','callback'=>'c365_api_reserve','permission_callback'=>'c365_public_guard'));
    register_rest_route('cruise/v1','/reservations/(?P<id>[a-f0-9-]{36})',array('methods'=>'GET','callback'=>'c365_api_status','permission_callback'=>'__return_true'));
    register_rest_route('cruise/v1','/reservations/(?P<id>[a-f0-9-]{36})/pay',array('methods'=>'POST','callback'=>'c365_api_pay','permission_callback'=>'c365_public_guard'));
    register_rest_route('cruise/v1','/reservations/(?P<id>[a-f0-9-]{36})/reconcile',array('methods'=>'POST','callback'=>'c365_api_reconcile','permission_callback'=>'c365_public_guard'));
    register_rest_route('cruise/v1','/contact',array('methods'=>'POST','callback'=>'c365_api_contact','permission_callback'=>'c365_public_guard'));
    register_rest_route('cruise/v1','/square-webhook',array('methods'=>'POST','callback'=>'c365_square_webhook','permission_callback'=>'__return_true'));
    register_rest_route('cruise/v1','/square-webhook-sandbox',array('methods'=>'POST','callback'=>function($request){return c365_square_webhook($request,'sandbox');},'permission_callback'=>'__return_true'));
});
function c365_api_response($data, $status = 200) { $r=new WP_REST_Response($data,$status); $r->header('Cache-Control','no-store, private, max-age=0'); $r->header('X-Robots-Tag','noindex, nofollow'); return $r; }
add_filter('rest_post_dispatch', function ($result,$server,$request) {
    if (strpos($request->get_route(),'/cruise/v1/')===0 && $result instanceof WP_HTTP_Response) { $result->header('Cache-Control','no-store, private, max-age=0'); $result->header('X-Robots-Tag','noindex, nofollow'); }
    return $result;
},10,3);
function c365_api_session() { $time=time(); return c365_api_response(array('nonce'=>wp_create_nonce('c365_public'),'issued'=>$time,'proof'=>hash_hmac('sha256',(string)$time,wp_salt('nonce')))); }
function c365_same_origin($value) {
    $actual=wp_parse_url($value); $expected=wp_parse_url(home_url('/'));
    if (!$actual || !$expected || empty($actual['host']) || empty($actual['scheme'])) { return false; }
    return strtolower($actual['host'])===strtolower($expected['host']) && strtolower($actual['scheme'])===strtolower($expected['scheme']) && ($actual['port']??($actual['scheme']==='https'?443:80))===($expected['port']??($expected['scheme']==='https'?443:80));
}
function c365_public_guard($request) {
    if (strlen($request->get_body())>16384) { return c365_error('size','送信内容が大きすぎます。',413); }
    if (!c365_same_origin($request->get_header('origin') ?: $request->get_header('referer'))) { return c365_error('origin','このサイトのフォームから送信してください。',403); }
    if (!wp_verify_nonce($request->get_header('x-vc-nonce'),'c365_public')) { return c365_error('nonce','ページを再読み込みしてお試しください。',403); }
    return true;
}
function c365_rate_limit($category, $limit, $window) {
    $address=$_SERVER['REMOTE_ADDR']??'unknown';
    $key='c365_rate_' . hash_hmac('sha256',$category.':'.$address,wp_salt('nonce'));
    $count=(int)get_transient($key);
    if ($count >= $limit) { return false; }
    set_transient($key,$count+1,$window); return true;
}
function c365_string($data,$key,$max=1000) {
    if (!isset($data[$key]) || !is_string($data[$key])) { return ''; }
    return sanitize_text_field(mb_substr(trim($data[$key]),0,$max));
}
function c365_antispam($data) {
    $issued=isset($data['issued']) && is_numeric($data['issued'])?(int)$data['issued']:0;
    $proof=c365_string($data,'proof',128);
    return c365_string($data,'website')==='' && $issued<=time()-2 && $issued>=time()-2*HOUR_IN_SECONDS && hash_equals(hash_hmac('sha256',(string)$issued,wp_salt('nonce')),$proof);
}
function c365_api_quote($request) {
    $event = absint($request->get_param('event'));
    $quote = c365_price($event);
    if (is_wp_error($quote)) { return $quote; }
    $plan = (int) get_post_meta($event, '_c365_plan', true);
    $accepting = c365_plan_accepts_bookings($plan);
    $quote['full'] = $accepting && !c365_available($event);
    $quote['available'] = $accepting && !$quote['full'];
    return c365_api_response($quote);
}
function c365_api_reserve($request) {
    global $wpdb;
    if (!c365_site_ready()) { return c365_error('not_open','予約受付は準備中です。お問い合わせからご連絡ください。',503); }
    if (!c365_rate_limit('reservation',12,MINUTE_IN_SECONDS)) { return c365_error('rate','少し時間をおいて再度お試しください。',429); }
    $d=$request->get_json_params(); if (!is_array($d)) { return c365_error('input','入力内容をご確認ください。'); }
    if (!c365_antispam($d)) { return c365_error('spam','フォームを開き直して、入力内容をご確認ください。',400); }
    $event=isset($d['event'])&&is_numeric($d['event'])?absint($d['event']):0;
    $name = c365_string($d, 'name', 160);
    $email = strtolower(c365_string($d, 'email', 254));
    $phone = c365_string($d, 'phone', 32);
    $method = c365_string($d, 'method', 12);
    $passengers = $d['passenger_count'] ?? null;
    if (!$name || !is_email($email) || !preg_match('/^[0-9+()\s-]{8,24}$/', $phone)
        || !is_int($passengers) || $passengers < 1 || $passengers > 5
        || !in_array($method, array('cash', 'square'), true)
        || ($d['consent'] ?? false) !== true || ($d['age_confirmed'] ?? false) !== true) {
        return c365_error('input', '連絡先・乗船人数・対象年齢・同意事項を確認してください。');
    }
    if ($method==='square' && !c365_square_ready()) { return c365_error('square_config','事前決済は現在ご利用いただけません。当日現地決済をお選びください。',503); }
    $square=c365_square_config();
    if ($method==='square' && (c365_string($d,'square_mode',12)!==c365_square_mode() || c365_string($d,'square_app_id',192)!==$square['app_id'] || c365_string($d,'square_location_id',192)!==$square['location_id'])) { return c365_error('square_settings_changed','決済設定が変更されました。ページを再読み込みしてからお申し込みください。',409); }
    $request_id=c365_string($d,'request_id',36); $token=c365_string($d,'token',128);
    if (!preg_match('/^[a-f0-9-]{36}$/',$request_id) || !preg_match('/^[a-zA-Z0-9_-]{32,128}$/',$token)) { return c365_error('request','予約識別子が無効です。ページを再読み込みしてください。'); }
    $hash=hash_hmac('sha256',$token,wp_salt('auth'));
    if (!c365_lock()) { return c365_error('busy','予約が混み合っています。再度お試しください。',409); }
    try {
        $existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.c365_table().' WHERE request_id=%s',$request_id));
        if ($existing) {
            if (!hash_equals($existing->token_hash,$hash)) { return c365_error('request','予約識別子が一致しません。',403); }
            return c365_api_response(c365_public_booking($existing));
        }
        $price = c365_price($event);
        if (is_wp_error($price)) { return $price; }
        $plan = (int) get_post_meta($event, '_c365_plan', true);
        if (!c365_is_english() && c365_plan_booking_mode($plan) === 'jalan') {
            return c365_error('not_open', 'このプランはじゃらんの掲載ページからお申し込みください。', 503);
        }
        c365_expire_holds($event);
        $total = $price['amount'];
        if (($d['expected_amount'] ?? null) !== $total) {
            return c365_error('price_changed', '料金が変わりました。貸切料金を確認してください。', 409);
        }
        if (!c365_available($event)) {
            return c365_error('full', '選択した出航枠は満席です。別の時間をお選びください。', 409);
        }

        $now = gmdate('Y-m-d H:i:s');
        $start = c365_event_time($event);
        $row = array(
            'public_id' => wp_generate_uuid4(), 'request_id' => $request_id, 'token_hash' => $hash, 'event_id' => $event,
            'customer_name' => $name, 'email' => $email, 'phone' => $phone, 'passenger_count' => $passengers,
            'departure_at' => gmdate('Y-m-d H:i:s', $start), 'arrival_at' => gmdate('Y-m-d H:i:s', $start + HOUR_IN_SECONDS),
            'cancellation_policy' => 'calendar_v1', 'amount' => $total,
            'payment_method' => $method, 'square_mode' => c365_square_mode(), 'location_id' => $square['location_id'],
            'status' => $method === 'cash' ? 'reserved' : 'pending_payment',
            'policy_text' => c365_policy_text(), 'consent_at' => $now,
            'expires_at' => $method === 'square' ? gmdate('Y-m-d H:i:s', min($start - 30 * MINUTE_IN_SECONDS, time() + 15 * MINUTE_IN_SECONDS)) : null,
            'created_at' => $now, 'updated_at' => $now,
        );
        if (!$wpdb->insert(c365_table(),$row)) { return c365_error('database','予約を保存できませんでした。再度お試しください。',503); }
        $r=c365_booking($wpdb->insert_id); c365_audit('reservation_created',$r->id);
        if ($method==='cash') { c365_booking_notice($r); }
        return c365_api_response(c365_public_booking($r),201);
    } finally { c365_unlock(); }
}
function c365_auth_booking($request) {
    global $wpdb;
    $id=$request->get_param('id'); $token=$request->get_header('x-vc-booking-token');
    if (!is_string($id) || !is_string($token) || strlen($token)<32 || strlen($token)>128) { return c365_error('not_found','予約情報を確認できません。',404); }
    $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.c365_table().' WHERE public_id=%s',$id));
    if (!$r || !hash_equals($r->token_hash,hash_hmac('sha256',$token,wp_salt('auth')))) { return c365_error('not_found','予約情報を確認できません。',404); }
    return $r;
}
function c365_api_status($request) { $r=c365_auth_booking($request); if(is_wp_error($r)){return $r;} if($r->status==='pending_payment'){c365_expire_holds($r->event_id);$r=c365_booking($r->id);}return c365_api_response(c365_public_booking($r)); }
function c365_api_pay($request) {
    $r=c365_auth_booking($request); if (is_wp_error($r)) { return $r; }
    if ($r->payment_method!=='square') { return c365_error('method','決済方法が一致しません。',409); }
    $d=$request->get_json_params(); $source=is_array($d)?c365_string($d,'source',512):'';
    if (!$source) { return c365_error('token','カード情報の処理が完了していません。'); }
    $result=c365_square_pay($r,$source); return is_wp_error($result)?$result:c365_api_response($result);
}
function c365_api_reconcile($request) {
    $r=c365_auth_booking($request); if (is_wp_error($r)) { return $r; }
    if (!c365_rate_limit('reconcile',6,MINUTE_IN_SECONDS)) { return c365_error('rate','少し待って再確認してください。',429); }
    if ($r->payment_method!=='square') { return c365_api_response(c365_public_booking($r)); }
    $result=c365_square_reconcile($r); return is_wp_error($result)?$result:c365_api_response(c365_public_booking(c365_booking($r->id)));
}
function c365_api_contact($request) {
    if (wp_get_environment_type() !== 'local' && !c365_mail_ready()) {
        return c365_error('mail_unavailable', 'フォーム送信の準備中です。掲載のメールアドレスへ直接お問い合わせください。', 503);
    }
    $d=$request->get_json_params();
    if (!is_array($d) || !c365_antispam($d)) { return c365_error('input','入力内容を確認してもう一度お試しください。'); }
    if (!c365_rate_limit('contact',5,HOUR_IN_SECONDS)) { return c365_error('rate','送信回数の上限です。時間をおいてお試しください。',429); }
    $name=c365_string($d,'name',160); $email=c365_string($d,'email',254); $subject=c365_string($d,'subject',180);
    $message=isset($d['message'])&&is_string($d['message'])?sanitize_textarea_field(mb_substr($d['message'],0,5000)):'';
    if (!$name || !is_email($email) || !$subject || mb_strlen($message)<10 || ($d['consent']??false)!==true) { return c365_error('input','必須項目と10文字以上の問い合わせ内容を入力し、個人情報の取り扱いに同意してください。'); }
    $body="C365クルーズへのお問い合わせ\n\n氏名：".$name."\nメール：".$email."\n件名：".$subject."\n\n".$message."\n\n受信日時：".c365_date('Y-m-d H:i',time())."（日本時間）";
    $request_id=c365_string($d,'request_id',36);
    if (!preg_match('/^[a-f0-9-]{36}$/',$request_id)) { return c365_error('request','送信識別子が無効です。'); }
    if (!c365_queue_mail(c365_contact_email(),'【C365クルーズお問い合わせ】'.$subject,$body,'contact_'.$request_id)) { return c365_error('save','お問い合わせを保存できませんでした。時間をおいてお試しください。',503); }
    return c365_api_response(array('accepted'=>true,'message'=>c365_text('お問い合わせを受け付けました。運営からの返信をお待ちください。', 'We received your message and will reply by email.')));
}
