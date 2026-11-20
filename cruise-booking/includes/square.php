<?php
if (!defined('ABSPATH')) { exit; }
function c365_square_mode() { return c365_setting('square_mode', 'sandbox') === 'production' ? 'production' : 'sandbox'; }
function c365_square_token($mode) { return c365_secret($mode === 'production' ? 'C365_SQUARE_PRODUCTION_TOKEN' : 'C365_SQUARE_SANDBOX_TOKEN'); }
function c365_square_config($mode = null) {
    $mode = $mode ?? c365_square_mode();
    if (!in_array($mode,array('sandbox','production'),true)) { return array('app_id'=>'','location_id'=>''); }
    return array('app_id'=>(string)c365_setting('square_'.$mode.'_app_id'),'location_id'=>(string)c365_setting('square_'.$mode.'_location_id'));
}
function c365_square_app_matches_mode($app,$mode) { return $mode==='sandbox'?strpos($app,'sandbox-')===0:strpos($app,'sq0idp-')===0; }
function c365_square_ready() {
    $mode = c365_square_mode();
    if ($mode === 'sandbox' && wp_get_environment_type() === 'production') { return false; }
    $config = c365_square_config($mode);
    return c365_square_token($mode) && $config['app_id'] && $config['location_id']
        && c365_square_app_matches_mode($config['app_id'], $mode);
}
function c365_square_update_fields($settings,$data) {
    foreach(array('sandbox','production') as $mode){
        $app_key='square_'.$mode.'_app_id';$location_key='square_'.$mode.'_location_id';
        if(!array_key_exists($app_key,$data)&&!array_key_exists($location_key,$data)){continue;}
        if(!isset($data[$app_key],$data[$location_key])||!is_string($data[$app_key])||!is_string($data[$location_key])){return c365_error('square_fields','各環境のApplication IDとLocation IDを確認してください。');}
        $app=trim(wp_unslash($data[$app_key]));$location=trim(wp_unslash($data[$location_key]));
        if(($app==='')!==($location==='')){return c365_error('square_fields','各環境のApplication IDとLocation IDは両方入力するか、両方空欄にしてください。');}
        if($app!==''&&(!preg_match('/^[A-Za-z0-9_-]{1,192}$/',$app)||!c365_square_app_matches_mode($app,$mode)||!preg_match('/^[A-Za-z0-9_-]{1,192}$/',$location))){return c365_error('square_fields',($mode==='sandbox'?'Sandbox':'Production').'用のIDか確認してください。別環境のApplication IDは保存できません。');}
        $settings[$app_key]=$app;$settings[$location_key]=$location;
    }
    return $settings;
}
add_action('admin_init','c365_migrate_square_settings');
function c365_migrate_square_settings() {
    if (get_option('c365_square_split_version')==='1.0.4') { return; }
    $s=c365_settings();$mode=c365_square_mode();$app=(string)($s['square_app_id']??'');$location=(string)($s['square_location_id']??'');
    $matches=$mode==='sandbox'?strpos($app,'sandbox-')===0:strpos($app,'sq0idp-')===0;
    // Only retain the legacy pair when its App ID clearly matches the saved mode.
    // Keep original fields for reference; never copy one pair to both environments.
    if($matches&&$location&&!array_key_exists('square_'.$mode.'_app_id',$s)&&!array_key_exists('square_'.$mode.'_location_id',$s)){
        $s['square_'.$mode.'_app_id']=$app;$s['square_'.$mode.'_location_id']=$location;update_option('c365_settings',$s,false);
    }
    update_option('c365_square_split_version','1.0.4',false);
}
function c365_square_request($method, $path, $body = null, $mode = null) {
    $mode = $mode ?? c365_square_mode(); $token = c365_square_token($mode);
    if (!$token) { return c365_error('square_config', 'Squareの認証設定が未完了です。', 503); }
    $host = $mode === 'production' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
    $args = array('method' => $method, 'timeout' => 40, 'headers' => array('Authorization' => 'Bearer ' . $token, 'Square-Version' => '2025-01-23', 'Content-Type' => 'application/json'));
    if ($body !== null) { $args['body'] = wp_json_encode($body); }
    $response = wp_remote_request($host . $path, $args);
    if (is_wp_error($response)) { return c365_error('square_unknown', '決済結果を確認中です。繰り返し新規予約せず、同じ画面で再確認するか運営へお問い合わせください。', 503); }
    $data = json_decode(wp_remote_retrieve_body($response), true); $status = wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $code = isset($data['errors'][0]['code']) ? sanitize_key($data['errors'][0]['code']) : 'api_error';
        // Do not return provider detail strings, tokens or raw payloads to clients/logs.
        $unknown = $status >= 500 || in_array($status, array(408,429), true);
        return c365_error($unknown ? 'square_unknown' : 'square_' . $code, $unknown ? '決済の処理状況を確認中です。同じ予約で再確認してください。' : 'Squareで処理を完了できませんでした。入力内容または決済設定をご確認ください。', $unknown ? 503 : 422);
    }
    return $data;
}
function c365_square_apply_payment($payment, $mode) {
    global $wpdb;
    if (!is_array($payment) || empty($payment['reference_id'])) { return false; }
    $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . c365_table() . ' WHERE public_id=%s', $payment['reference_id']));
    if (!$r || $r->square_mode !== $mode || $r->payment_method !== 'square' || ($payment['location_id'] ?? '') !== $r->location_id || (int) ($payment['amount_money']['amount'] ?? -1) !== (int) $r->amount || ($payment['amount_money']['currency'] ?? '') !== 'JPY' || empty($payment['id'])) { return false; }
    if (!c365_lock()) { return false; }
    try {
        $r = c365_booking($r->id); $status = $payment['status'] ?? '';
        if ($r->payment_id && $r->payment_id !== $payment['id']) { return false; }
        // Out-of-order callbacks must never undo a completed payment/refund.
        if (in_array($r->status, array('paid','refund_pending','refunded','cancelled'), true)) { return true; }
        if ($r->status !== 'payment_processing') { return false; }
        if (!in_array($status, array('COMPLETED','FAILED','CANCELED','APPROVED','PENDING'), true)) { return false; }
        $update = array('payment_id' => sanitize_text_field($payment['id']), 'payment_status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s'));
        if ($status === 'COMPLETED') { $update['status'] = 'paid'; $update['expires_at'] = null; }
        elseif (in_array($status,array('FAILED','CANCELED'),true)) { $update['status'] = 'payment_failed'; }
        else { $update['status'] = 'payment_processing'; }
        if ($wpdb->update(c365_table(),$update,array('id'=>$r->id)) === false) { return false; }
        if ($status === 'COMPLETED') { c365_booking_notice(c365_booking($r->id)); c365_audit('payment_completed',$r->id); }
        return true;
    } finally { c365_unlock(); }
}
function c365_square_pay($r, $source) {
    global $wpdb;
    if (!c365_lock()) { return c365_error('busy','処理中です。少し待って再確認してください。',409); }
    try {
        $r = c365_booking($r->id);
        $was_processing = $r->status === 'payment_processing';
        if ($r->status === 'paid') { return c365_public_booking($r); }
        if (!in_array($r->status,array('pending_payment','payment_processing'),true) || ($r->status === 'pending_payment' && strtotime($r->expires_at . ' UTC') < time())) { return c365_error('expired','予約の決済期限が終了しました。予約状況を確認してからお申し込みください。',409); }
        if ($wpdb->update(c365_table(),array('status'=>'payment_processing','updated_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$r->id)) === false) {
            return c365_error('database', '決済処理を開始できませんでした。再度お試しください。', 503);
        }
    } finally { c365_unlock(); }
    // No card data touches this server. Only a one-time token from the Square SDK.
    $response = c365_square_request('POST','/v2/payments',array('source_id'=>$source,'idempotency_key'=>'vc-' . $r->public_id,'amount_money'=>array('amount'=>(int)$r->amount,'currency'=>'JPY'),'location_id'=>$r->location_id,'autocomplete'=>true,'reference_id'=>$r->public_id,'buyer_email_address'=>$r->email,'note'=>'C365 Cruise reservation ' . $r->public_id),$r->square_mode);
    if (is_wp_error($response)) {
        if ($response->get_error_code() !== 'square_unknown' && !$was_processing) {
            // Explicit failure: keep the original idempotency key immutable.
            $wpdb->query($wpdb->prepare('UPDATE ' . c365_table() . " SET status='payment_failed',payment_status='FAILED',updated_at=%s WHERE id=%d AND status='payment_processing' AND payment_id=''",gmdate('Y-m-d H:i:s'),$r->id));
        }
        return $response;
    }
    if (empty($response['payment']) || !c365_square_apply_payment($response['payment'],$r->square_mode)) { return c365_error('square_unknown','決済の照合に時間がかかっています。運営へお問い合わせください。',503); }
    return c365_public_booking(c365_booking($r->id));
}
function c365_square_reconcile($r) {
    if ($r->payment_id) {
        $result = c365_square_request('GET','/v2/payments/' . rawurlencode($r->payment_id),null,$r->square_mode);
        if (is_wp_error($result)) { return $result; }
        return !empty($result['payment']) && c365_square_apply_payment($result['payment'],$r->square_mode);
    }
    $query = array('begin_time'=>gmdate('Y-m-d\TH:i:s\Z',strtotime($r->created_at . ' UTC')-60),'location_id'=>$r->location_id,'sort_order'=>'ASC','limit'=>100);
    for ($i=0;$i<5;$i++) {
        $result = c365_square_request('GET','/v2/payments?' . http_build_query($query),null,$r->square_mode);
        if (is_wp_error($result)) { return $result; }
        foreach (($result['payments'] ?? array()) as $payment) { if (($payment['reference_id'] ?? '') === $r->public_id) { return c365_square_apply_payment($payment,$r->square_mode); } }
        if (empty($result['cursor'])) { break; } $query['cursor']=$result['cursor'];
    }
    return c365_error('not_found','Squareで決済をまだ確認できません。Square管理画面も確認してください。',409);
}
function c365_square_apply_refund($booking, $refund) {
    global $wpdb;
    $status = $refund['status'] ?? '';
    $amount = (int) $booking->amount - (int) $booking->cancellation_fee;
    if ($amount <= 0 || empty($refund['id']) || ($refund['payment_id'] ?? '') !== $booking->payment_id
        || (int) ($refund['amount_money']['amount'] ?? -1) !== $amount || ($refund['amount_money']['currency'] ?? '') !== 'JPY'
        || !in_array($status, array('PENDING', 'COMPLETED', 'FAILED', 'REJECTED'), true)) {
        return false;
    }

    if ($booking->status === 'refunded') { return $booking->refund_id === $refund['id']; }
    if ($booking->status !== 'refund_pending') { return false; }
    $previous_failed = in_array($booking->refund_status, array('FAILED', 'REJECTED'), true);
    if ($booking->refund_id && $booking->refund_id !== $refund['id'] && !$previous_failed) { return false; }

    return $wpdb->update(c365_table(), array(
        'refund_id' => $refund['id'], 'refund_status' => $status,
        'status' => $status === 'COMPLETED' ? 'refunded' : 'refund_pending',
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ), array('id' => $booking->id)) !== false;
}

function c365_square_refund($booking, $fee) {
    global $wpdb;
    if (!$booking->payment_id || !in_array($booking->status, array('paid', 'refund_pending'), true)) {
        return c365_error('refund_state', '返金対象の決済がありません。', 409);
    }
    if ($booking->status === 'paid') {
        $saved = $wpdb->update(c365_table(), array(
            'status' => 'refund_pending', 'refund_status' => 'REQUESTED',
            'cancellation_fee' => $fee, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ), array('id' => $booking->id));
        if ($saved === false) { return c365_error('database', '返金依頼を保存できませんでした。', 503); }
        $booking = c365_booking($booking->id);
    }
    $amount = (int) $booking->amount - (int) $booking->cancellation_fee;
    if ($amount <= 0) { return c365_error('refund_amount', '返金対象額がありません。', 409); }

    // The fee is saved before calling Square, so retries use the original amount and key.
    if ($booking->refund_id) {
        $response = c365_square_request('GET', '/v2/refunds/' . rawurlencode($booking->refund_id), null, $booking->square_mode);
    } else {
        $response = c365_square_request('POST', '/v2/refunds', array(
            'idempotency_key' => 'vcr-' . $booking->public_id, 'payment_id' => $booking->payment_id,
            'amount_money' => array('amount' => $amount, 'currency' => 'JPY'),
            'reason' => 'Cancellation of reservation ' . $booking->public_id,
        ), $booking->square_mode);
    }

    if (is_wp_error($response)) { return $response; }
    $refund = $response['refund'] ?? array();
    if (!c365_square_apply_refund($booking, $refund)) {
        return c365_error('refund_unknown', '返金結果の照合が必要です。', 503);
    }
    if (!in_array($refund['status'], array('PENDING', 'COMPLETED'), true)) {
        return c365_error('refund_failed', '返金を完了できませんでした。Square管理画面で確認してください。', 409);
    }
    c365_audit('refund_requested', $booking->id);
    return true;
}
function c365_square_webhook($request, $mode = 'production') {
    $signature = $request->get_header('x-square-hmacsha256-signature'); $raw = $request->get_body();
    if (strlen($raw)>262144 || !$signature) { return c365_error('signature','Invalid signature',401); }
    if (!in_array($mode,array('sandbox','production'),true)) { return c365_error('signature','Invalid environment',401); }
    $url = rest_url('cruise/v1/' . ($mode==='sandbox'?'square-webhook-sandbox':'square-webhook'));
    $key=c365_secret($mode==='production'?'C365_SQUARE_PRODUCTION_WEBHOOK_KEY':'C365_SQUARE_SANDBOX_WEBHOOK_KEY');
    if (!$key || !hash_equals(base64_encode(hash_hmac('sha256',$url.$raw,$key,true)),$signature)) { return c365_error('signature','Invalid signature',401); }
    $data = json_decode($raw,true);
    if (!is_array($data)) { return c365_error('payload','Invalid payload'); }
    $type=$data['type']??'';
    if (in_array($type,array('payment.created','payment.updated'),true)) {
        $payment=$data['data']['object']['payment']??array();
        // Signature authenticates the sender. Re-read authoritative state to handle reordering.
        if (!empty($payment['id'])) {
            $result=c365_square_request('GET','/v2/payments/'.rawurlencode($payment['id']),null,$mode);
            if (is_wp_error($result)) { return c365_error('retry','Retry later',503); }
            if (!c365_square_apply_payment($result['payment']??array(),$mode)) {
                global $wpdb;
                $known = $wpdb->get_var($wpdb->prepare('SELECT id FROM '.c365_table().' WHERE public_id=%s', $result['payment']['reference_id'] ?? ''));
                if ($known) { return c365_error('retry','Reconciliation pending',503); }
            }
        }
    }
    if (in_array($type, array('refund.updated', 'refund.created'), true)) {
        global $wpdb;
        $id = $data['data']['object']['refund']['id'] ?? '';
        if (is_string($id) && $id !== '') {
            $remote = c365_square_request('GET', '/v2/refunds/' . rawurlencode($id), null, $mode);
            if (is_wp_error($remote)) { return c365_error('retry', 'Retry later', 503); }
            $refund = $remote['refund'] ?? array();
            $booking = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . c365_table() . " WHERE payment_id=%s AND square_mode=%s AND status IN ('refund_pending','refunded')",
                $refund['payment_id'] ?? '', $mode
            ));
            if ($booking && ($refund['id'] ?? '') === $id) {
                if (!c365_lock()) { return c365_error('retry', 'Retry later', 503); }
                try {
                    $booking = c365_booking($booking->id);
                    if ($booking->status === 'refund_pending' && !c365_square_apply_refund($booking, $refund)) {
                        return c365_error('retry', 'Reconciliation pending', 503);
                    }
                } finally { c365_unlock(); }
            }
        }
    }
    return new WP_REST_Response(array('received'=>true),200);
}
