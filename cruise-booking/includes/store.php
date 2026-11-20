<?php
if (!defined('ABSPATH')) { exit; }
function c365_table($suffix = 'bookings') { global $wpdb; return $wpdb->prefix . 'c365_' . $suffix; }
function c365_install_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate(); $bookings = c365_table(); $mail = c365_table('mail'); $audit = c365_table('audit');
    dbDelta("CREATE TABLE $bookings (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      public_id varchar(36) NOT NULL,
      request_id varchar(36) NOT NULL,
      token_hash varchar(64) NOT NULL,
      event_id bigint unsigned NOT NULL,
      customer_name varchar(160) NOT NULL,
      email varchar(254) NOT NULL,
      phone varchar(32) NOT NULL,
      passenger_count smallint unsigned NOT NULL,
      departure_at datetime NOT NULL,
      arrival_at datetime NOT NULL,
      cancellation_days smallint unsigned NOT NULL DEFAULT 7,
      cancellation_policy varchar(20) NOT NULL DEFAULT 'legacy',
      cancellation_fee int unsigned NOT NULL DEFAULT 0,
      amount int unsigned NOT NULL,
      payment_method varchar(12) NOT NULL,
      square_mode varchar(12) NOT NULL DEFAULT 'sandbox',
      location_id varchar(192) NOT NULL DEFAULT '',
      status varchar(32) NOT NULL,
      payment_id varchar(192) NOT NULL DEFAULT '',
      payment_status varchar(32) NOT NULL DEFAULT '',
      refund_id varchar(192) NOT NULL DEFAULT '',
      refund_status varchar(32) NOT NULL DEFAULT '',
      policy_text text NOT NULL,
      consent_at datetime NOT NULL,
      expires_at datetime DEFAULT NULL,
      created_at datetime NOT NULL,
      updated_at datetime NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY public_id (public_id),
      UNIQUE KEY request_id (request_id),
      KEY event_status (event_id,status),
      KEY sailing_window (departure_at,arrival_at),
      KEY email (email(160)),
      KEY payment_id (payment_id(160))
    ) $charset;");
    dbDelta("CREATE TABLE $mail (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      booking_id bigint unsigned NOT NULL DEFAULT 0,
      batch_id varchar(80) NOT NULL,
      recipient varchar(254) NOT NULL,
      subject varchar(255) NOT NULL,
      body longtext NOT NULL,
      status varchar(20) NOT NULL DEFAULT 'pending',
      attempts smallint NOT NULL DEFAULT 0,
      last_error varchar(255) NOT NULL DEFAULT '',
      available_at datetime NOT NULL,
      created_at datetime NOT NULL,
      updated_at datetime NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY recipient_batch (batch_id,recipient(160)),
      KEY queue_status (status,available_at)
    ) $charset;");
    dbDelta("CREATE TABLE $audit (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      booking_id bigint unsigned NOT NULL DEFAULT 0,
      user_id bigint unsigned NOT NULL DEFAULT 0,
      action varchar(100) NOT NULL,
      created_at datetime NOT NULL,
      PRIMARY KEY  (id),
      KEY booking_id (booking_id)
    ) $charset;");
    update_option('c365_db_version', C365_BOOKING_VERSION, false);
}
function c365_audit($action, $booking = 0) { global $wpdb; $wpdb->insert(c365_table('audit'), array('booking_id' => $booking, 'user_id' => get_current_user_id(), 'action' => sanitize_key($action), 'created_at' => gmdate('Y-m-d H:i:s'))); }
function c365_policy_text() {
    return '出航日の3日前までは無料。2日前は利用料金の40％、前日は70％、当日・無連絡キャンセルは100％。日付は日本時間。主催者都合による中止は全額返金。';
}

function c365_cancellation_fee($booking, DateTimeImmutable $received, $reason) {
    $amount = (int) $booking->amount;
    if ($reason === 'organizer') { return 0; }
    if ($reason === 'no_show') { return $amount; }
    $departure = (new DateTimeImmutable($booking->departure_at, new DateTimeZone('UTC')))->setTimezone(c365_tokyo());
    if ($booking->cancellation_policy !== 'calendar_v1') {
        $deadline = $departure->getTimestamp() - $booking->cancellation_days * DAY_IN_SECONDS;
        return $received->getTimestamp() <= $deadline ? 0 : $amount;
    }

    $received_day = $received->setTimezone(c365_tokyo())->setTime(0, 0);
    $days = (int) $received_day->diff($departure->setTime(0, 0))->format('%r%a');
    if ($days >= 3) { return 0; }
    $percent = array(2 => 40, 1 => 70);
    return intdiv($amount * ($percent[$days] ?? 100), 100);
}

function c365_price($event) {
    $start = c365_event_time($event);
    $plan = (int) get_post_meta($event, '_c365_plan', true);
    if (!$start || $start <= time() + 30 * MINUTE_IN_SECONDS || get_post_status($event) !== 'publish'
        || get_post_type($event) !== 'c365_event' || get_post_status($plan) !== 'publish' || get_post_type($plan) !== 'c365_plan') {
        return c365_error('closed', 'この出航枠の予約受付は終了しました。', 409);
    }
    $price = (int) get_post_meta($plan, '_c365_charter_price', true);
    if ($price <= 0) { return c365_error('price_missing', '料金はお問い合わせください。', 409); }
    return array('amount' => $price, 'currency' => 'JPY');
}

/** All plans use the same boat, so capacity checks and payment transitions share one lock. */
function c365_lock() {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', 'cruise_boat_' . md5(DB_NAME . ':' . c365_table()))) === 1;
}
function c365_unlock() {
    global $wpdb;
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'cruise_boat_' . md5(DB_NAME . ':' . c365_table())));
}
function c365_expire_holds($event = 0) {
    global $wpdb; $table = c365_table();
    // Payment-processing holds are deliberately kept until Square reconciliation.
    $query = "UPDATE $table SET status='expired', updated_at=%s WHERE status='pending_payment' AND expires_at < %s";
    $args = array(gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'));
    if ($event) { $query .= ' AND event_id=%d'; $args[] = $event; }
    $wpdb->query($wpdb->prepare($query, $args));
}
function c365_available($event) {
    global $wpdb;
    $start = c365_event_time($event);
    if (!$start) { return false; }
    $table = c365_table();
    $conflict = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE departure_at < %s AND arrival_at > %s
        AND (status IN ('reserved','paid','payment_processing','refund_pending') OR (status='pending_payment' AND expires_at >= %s)) LIMIT 1",
        gmdate('Y-m-d H:i:s', $start + HOUR_IN_SECONDS), gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s')
    ));
    return !$conflict && !$wpdb->last_error;
}
function c365_booking($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . c365_table() . ' WHERE id=%d', $id)); }
function c365_booking_number($booking) {
    return 'C365-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT);
}
function c365_is_sandbox_booking($booking) {
    return $booking && $booking->payment_method === 'square' && $booking->square_mode === 'sandbox';
}
function c365_delete_sandbox_booking($id) {
    global $wpdb;
    $busy = '処理が混み合っています。もう一度お試しください。';
    if (!c365_lock()) { return new WP_Error('busy', $busy); }
    try {
        $booking = c365_booking($id);
        if (!c365_is_sandbox_booking($booking)) {
            return new WP_Error('not_sandbox', 'Square Sandboxのテスト予約のみ削除できます。');
        }
        if ($booking->status === 'sandbox_deleted') { return true; }
        $mail_lock = c365_mail_queue_lock_name();
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $mail_lock)) !== 1) {
            return new WP_Error('busy', $busy);
        }
        try {
            $error = new WP_Error('database', 'テスト予約を削除できませんでした。もう一度お試しください。');
            if ($wpdb->query('START TRANSACTION') === false) { return $error; }
            // Retain the request identifier so a delayed payment or retry cannot recreate the test booking.
            $updated = $wpdb->update(c365_table(), array(
                'status' => 'sandbox_deleted', 'customer_name' => '', 'email' => '', 'phone' => '',
                'expires_at' => null, 'updated_at' => gmdate('Y-m-d H:i:s'),
            ), array('id' => $id, 'payment_method' => 'square', 'square_mode' => 'sandbox'));
            if ($updated !== 1 || $wpdb->delete(c365_table('mail'), array('booking_id' => $id)) === false
                || $wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return $error;
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $mail_lock));
        }
        c365_audit('sandbox_booking_deleted', $id);
        return true;
    } finally {
        c365_unlock();
    }
}
function c365_public_booking($r) {
    return array(
        'id' => $r->public_id, 'status' => $r->status, 'amount' => (int) $r->amount,
        'booking_number' => c365_booking_number($r),
        'payment_method' => $r->payment_method,
        'square_mode' => $r->payment_method === 'square' ? $r->square_mode : null,
        'expires_at' => $r->expires_at ? $r->expires_at . 'Z' : null,
    );
}
