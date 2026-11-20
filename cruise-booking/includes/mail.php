<?php
if (!defined('ABSPATH')) { exit; }
function c365_mail_ready() {
    return c365_secret('C365_M365_TENANT_ID') && c365_secret('C365_M365_CLIENT_ID') && c365_secret('C365_M365_CLIENT_SECRET') && is_email(c365_setting('sender_email', 'info@c365.jp'));
}
function c365_queue_mail($to, $subject, $body, $batch, $booking = 0) {
    global $wpdb;
    if (!is_email($to)) { return false; }
    $now = gmdate('Y-m-d H:i:s');
    $result = $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . c365_table('mail') . ' (booking_id,batch_id,recipient,subject,body,status,attempts,last_error,available_at,created_at,updated_at) VALUES (%d,%s,%s,%s,%s,%s,0,%s,%s,%s,%s)', $booking, substr($batch,0,80), $to, sanitize_text_field($subject), $body, 'pending', '', $now, $now, $now));
    if ($result === false) { return false; }
    if ($result > 0) {
        if (!wp_next_scheduled('c365_process_mail_now')) { wp_schedule_single_event(time(), 'c365_process_mail_now'); }
        if (!has_action('shutdown', 'c365_spawn_mail_cron')) { add_action('shutdown', 'c365_spawn_mail_cron', 999); }
    }
    return true;
}
function c365_spawn_mail_cron() { if (!defined('DOING_CRON') && !defined('WP_CLI')) { spawn_cron(); } }
function c365_graph_send($to, $subject, $body) {
    if (!c365_mail_ready()) { return new WP_Error('not_configured', 'Microsoft 365 未設定'); }
    $tenant = c365_secret('C365_M365_TENANT_ID'); $client = c365_secret('C365_M365_CLIENT_ID');
    if (!preg_match('/^[a-f0-9-]{36}$/i', $tenant) || !preg_match('/^[a-f0-9-]{36}$/i', $client)) { return new WP_Error('invalid_config', 'Microsoft 365 ID形式エラー'); }
    $cache_key = 'c365_graph_' . substr(hash('sha256', $tenant . $client . c365_secret('C365_M365_CLIENT_SECRET')), 0, 20);
    $token = get_transient($cache_key);
    if (!$token) {
        $response = wp_remote_post('https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token', array('timeout' => 25, 'body' => array('client_id' => $client, 'client_secret' => c365_secret('C365_M365_CLIENT_SECRET'), 'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials')));
        if (is_wp_error($response)) { return new WP_Error('token_network', 'Microsoft 365 認証通信エラー'); }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200 || empty($data['access_token'])) { return new WP_Error('token_auth', 'Microsoft 365 認証・権限を確認してください'); }
        $token = $data['access_token']; set_transient($cache_key, $token, max(60, (int) ($data['expires_in'] ?? 3600) - 120));
    }
    $sender = sanitize_email(c365_setting('sender_email', 'info@c365.jp'));
    $payload = array('message' => array('subject' => $subject, 'body' => array('contentType' => 'Text', 'content' => $body), 'toRecipients' => array(array('emailAddress' => array('address' => $to)))), 'saveToSentItems' => true);
    $r = wp_remote_post('https://graph.microsoft.com/v1.0/users/' . rawurlencode($sender) . '/sendMail', array('timeout' => 30, 'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'), 'body' => wp_json_encode($payload)));
    if (is_wp_error($r)) { return new WP_Error('delivery_uncertain', '送信結果不明。送信済みフォルダーを確認してから再送してください。'); }
    $status = wp_remote_retrieve_response_code($r);
    if ($status === 202) { return true; }
    if ($status === 401) { delete_transient($cache_key); }
    if ($status === 429) { return new WP_Error('rate_limit', 'Microsoft 365 送信制限', array('retry_after' => min(3600, max(60, (int) wp_remote_retrieve_header($r, 'retry-after'))))); }
    if ($status >= 500) { return new WP_Error('delivery_uncertain', 'Microsoft 365 応答エラー。送信済みフォルダーを確認してください。'); }
    return new WP_Error('delivery_rejected', 'Microsoft 365 が送信を受理しませんでした（HTTP ' . $status . '）');
}
function c365_process_mail_queue() {
    global $wpdb;
    if (!c365_mail_ready()) { return; }
    $lock = 'c365_mail_' . md5(c365_table());
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $lock)) !== 1) { return; }
    try {
        $table = c365_table('mail');
        $wpdb->query($wpdb->prepare("UPDATE $table SET status='uncertain',last_error='処理中に中断。送信済みフォルダーを確認してください。' WHERE status='processing' AND updated_at < %s", gmdate('Y-m-d H:i:s',time()-600)));
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status='pending' AND available_at<=%s ORDER BY id LIMIT 10", gmdate('Y-m-d H:i:s')));
        foreach ($items as $mail) {
            $wpdb->update($table, array('status'=>'processing','attempts'=>$mail->attempts+1,'updated_at'=>gmdate('Y-m-d H:i:s')), array('id'=>$mail->id));
            $r = c365_graph_send($mail->recipient, $mail->subject, $mail->body);
            $update = array('status'=>'sent','last_error'=>'','updated_at'=>gmdate('Y-m-d H:i:s'));
            if (is_wp_error($r)) {
                $code = $r->get_error_code();
                $retryable = in_array($code,array('token_network','rate_limit'),true) && $mail->attempts < 3;
                $update['status'] = $code === 'delivery_uncertain' ? 'uncertain' : ($retryable ? 'pending' : 'failed');
                $update['last_error'] = $r->get_error_message();
                $delay = $code === 'rate_limit' ? ($r->get_error_data()['retry_after'] ?? 120) : 120;
                $update['available_at'] = gmdate('Y-m-d H:i:s', time() + $delay);
            }
            $wpdb->update($table,$update,array('id'=>$mail->id));
        }
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}
function c365_booking_notice($r) {
    $plan = (int) get_post_meta($r->event_id, '_c365_plan', true);
    $title = c365_departure_title($r->event_id);
    $start = strtotime($r->departure_at . ' UTC');
    $payment = $r->payment_method === 'cash' ? '当日現地決済' : 'Square事前決済完了';
    $body = implode("\n", array(
        $r->customer_name . ' 様', '', 'C365クルーズのご予約ありがとうございます。', '',
        '予約番号：' . $r->public_id,
        'プラン：' . $title,
        '出航：' . c365_date('Y年n月j日 H:i', $start) . '（日本時間）',
        '集合：' . c365_date('H:i', $start - 30 * MINUTE_IN_SECONDS) . 'までにC365（横浜市中区福富町西通44-2）',
        '乗船人数：' . $r->passenger_count . '名',
        '貸切料金：' . number_format($r->amount) . '円',
        'お支払い：' . $payment, '',
        'キャンセル条件：' . $r->policy_text,
        'キャンセルは予約番号、代表者名、出航日時を記載してメールでご連絡ください。',
        'お問い合わせ：' . c365_contact_email(),
        get_permalink($plan), '', '株式会社NEXUS / C365クルーズ',
    ));
    c365_queue_mail($r->email, '【C365クルーズ】ご予約確認：' . $title, $body, 'confirm_' . $r->public_id, $r->id);
    c365_queue_mail(c365_contact_email(), '【C365クルーズ運営】新しい予約：' . $title, $body, 'admin_confirm_' . $r->public_id, $r->id);
}
