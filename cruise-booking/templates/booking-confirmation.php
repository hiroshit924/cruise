<?php
if (!defined('ABSPATH')) { exit; }

$plan = get_queried_object_id();
$event = is_string($_GET['booking_complete']) && ctype_digit($_GET['booking_complete'])
    ? absint($_GET['booking_complete']) : 0;
if (get_post_type($event) !== 'c365_event' || (int) get_post_meta($event, '_c365_plan', true) !== $plan) {
    $event = 0;
}
$plan_url = c365_local_url(get_permalink($plan)) . '#booking';
get_header();
?>
<main id="main" class="container content-page">
    <section class="vc-confirmation" data-event="<?php echo esc_attr($event); ?>" aria-labelledby="vc-confirmation-title">
        <h1 id="vc-confirmation-title"><?php c365_output('ご予約の確認', 'Booking confirmation'); ?></h1>
        <p class="vc-confirmation-status vc-loading" role="status"><?php c365_output('予約状況を確認しています…', 'Checking your booking…'); ?></p>
        <div class="vc-result vc-confirmation-details" aria-live="polite" hidden></div>
        <noscript><p><?php c365_output('予約状況を表示するにはJavaScriptを有効にしてください。', 'Enable JavaScript to check your booking status.'); ?></p></noscript>
        <p id="booking"><a class="button" href="<?php echo esc_url($plan_url); ?>"><?php c365_output('プランに戻る', 'Back to the cruise'); ?></a></p>
    </section>
</main>
<?php get_footer(); ?>
