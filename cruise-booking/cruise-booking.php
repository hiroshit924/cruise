<?php
/**
 * Plugin Name: C365 Cruise Booking
 * Description: C365の貸切クルーズ予約・Square決済・Microsoft 365メール・予約管理・検索向け設定。
 * Version: 1.0.2
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: C365
 * License: GPL-2.0-or-later
 * Text Domain: cruise-booking
 */
if (!defined('ABSPATH')) { exit; }
define('C365_BOOKING_VERSION', '1.0.2');
define('C365_BOOKING_PATH', plugin_dir_path(__FILE__));
define('C365_BOOKING_URL', plugin_dir_url(__FILE__));
foreach (array('store', 'events', 'mail', 'square', 'api', 'forms', 'admin', 'seo', 'privacy', 'safety', 'seed') as $file) { require_once C365_BOOKING_PATH . 'includes/' . $file . '.php'; }
register_activation_hook(__FILE__, function () {
    c365_install_tables(); c365_register_events();
    if (!get_option('c365_settings')) { add_option('c365_settings', array('enabled' => 0, 'square_mode' => 'sandbox', 'phone' => '045-297-6375', 'contact_email' => 'info@c365.jp', 'sender_email' => 'info@c365.jp'), '', false); }
    if (!wp_next_scheduled('c365_process_mail')) { wp_schedule_event(time() + 60, 'c365_every_minute', 'c365_process_mail'); }
    if (!wp_next_scheduled('c365_expire_holds')) { wp_schedule_event(time() + 120, 'hourly', 'c365_expire_holds'); }
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('c365_process_mail'); wp_clear_scheduled_hook('c365_process_mail_now'); wp_clear_scheduled_hook('c365_expire_holds'); flush_rewrite_rules(); });
add_filter('cron_schedules', function ($s) { $s['c365_every_minute'] = array('interval' => 60, 'display' => 'C365クルーズ 毎分'); return $s; });
add_action('c365_process_mail', 'c365_process_mail_queue');
add_action('c365_process_mail_now', 'c365_process_mail_queue');
add_action('c365_expire_holds', 'c365_expire_holds');
add_action('init', 'c365_register_events', 5);
add_action('init', function () {
    if (get_option('c365_db_version') !== C365_BOOKING_VERSION) { c365_install_tables(); }
    if (!get_option('c365_cruise_contact_updated')) {
        $settings = c365_settings();
        foreach (array('contact_email', 'sender_email') as $key) {
            if (empty($settings[$key]) || $settings[$key] === 'vincross@c365.jp') {
                $settings[$key] = 'info@c365.jp';
            }
        }
        update_option('c365_settings', $settings, false);
        update_option('c365_cruise_contact_updated', 1, false);
    }
}, 4);
function c365_settings() { return (array) get_option('c365_settings', array()); }
function c365_setting($key, $default = '') { $s = c365_settings(); return $s[$key] ?? $default; }
/** Secret constants override saved values; no secret values are exposed in HTML. */
function c365_secret($name) { return defined($name) ? (string) constant($name) : ''; }
function c365_tokyo() { return new DateTimeZone('Asia/Tokyo'); }
function c365_event_time($id, $key = 'date') {
    $date = get_post_meta($id, '_c365_' . $key, true);
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date)) { return 0; }
    try { return (new DateTimeImmutable($date, c365_tokyo()))->getTimestamp(); } catch (Exception $e) { return 0; }
}
function c365_date($format, $timestamp) { return wp_date($format, $timestamp, c365_tokyo()); }
function c365_error($code, $message, $status = 400) { return new WP_Error($code, $message, array('status' => $status)); }
function c365_site_ready() {
    $local = wp_get_environment_type() === 'local';
    if (!c365_setting('enabled') || (!$local && (!is_ssl() || !c365_mail_ready()))) { return false; }
    foreach (array('privacy-policy','booking-policy','commercial-disclosure') as $slug) {
        $page=get_page_by_path($slug);
        if (!$page || $page->post_status!=='publish' || $page->post_password!=='') { return false; }
    }
    return true;
}
function c365_contact_email() { return sanitize_email(c365_setting('contact_email', 'info@c365.jp')); }
function c365_departure_title($event) {
    $plan = (int) get_post_meta($event, '_c365_plan', true);
    return $plan && get_post($plan) ? get_the_title($plan) : get_the_title($event);
}
