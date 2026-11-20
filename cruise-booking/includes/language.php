<?php
if (!defined('ABSPATH')) { exit; }

function c365_is_english() {
    return isset($_GET['cruise_lang']) && $_GET['cruise_lang'] === 'en';
}

function c365_text($japanese, $english) {
    return c365_is_english() ? $english : $japanese;
}

function c365_local_url($url) {
    return c365_is_english() ? add_query_arg('cruise_lang', 'en', $url) : $url;
}

function c365_english_plan($plan) {
    $copy = array(
        'private-cruise' => array('Private Minato Mirai Cruise', 'See the Red Brick Warehouse, Yokohama Bay Bridge and Landmark Tower from a private boat with family or friends.'),
        'dog-cruise' => array('Private Day Cruise with Your Dog', 'Take in the sea breeze and the sights of Minato Mirai with your dog on a private daytime cruise.'),
        'dog-night-cruise' => array('Private Night Cruise with Your Dog', 'Enjoy the lights of Yokohama from the water with your dog. A memorable setting for a birthday or anniversary.'),
    );
    $default = $copy[$plan->post_name] ?? array(get_the_title($plan), 'A 60-minute private cruise on the Yokohama waterfront.');
    return array(
        get_post_meta($plan->ID, '_c365_english_title', true) ?: $default[0],
        get_post_meta($plan->ID, '_c365_english_description', true) ?: $default[1],
    );
}

function c365_english_page($slug) {
    $operator = 'The operator is NEXUS Co., Ltd. Contact us at ' . c365_contact_email() . ' or ' . c365_setting('phone', '045-297-6375') . '.';
    $pages = array(
        'contact' => array('Contact', array()),
        'company' => array('Cruise operator', array(
            'C365 CRUISE operates private cruises in Yokohama and Minato Mirai.',
            $operator,
            'Address: 44-2 Fukutomichō Nishidōri, Naka Ward, Yokohama, Kanagawa 231-0042, Japan.',
        )),
        'privacy-policy' => array('Privacy policy', array(
            'We collect the lead passenger’s name, phone number and email address, the number of passengers, age and consent confirmations, reservation and payment status, and inquiries. We use this information to manage reservations, cruises, payments, cancellations and refunds, to contact you, to comply with legal obligations and to keep passengers safe.',
            'Square processes advance card payments. We do not store card details on this website. We use Microsoft 365 to send email. Cookies and session storage help protect forms, prevent duplicate reservations and check payment status.',
            'We may share information with service providers as needed to deliver the service or where required by law. We retain information while it is needed for the booking, legal or accounting purposes. You may contact the operator about access to, correction of or deletion of your information.',
            $operator,
        )),
        'booking-policy' => array('Booking and cancellation policy', array(
            'Each cruise is a 60-minute private charter for up to five passengers. A solo passenger pays the same charter rate as two passengers. Please arrive at C365 at least 30 minutes before departure. All times are Japan Standard Time.',
            'You may pay at the meeting point on the day, or pay by card through Square when advance payment is available. On-site payment bookings are confirmed when the website accepts the reservation. Advance payment bookings are confirmed when payment is complete.',
            c365_english_cancellation_policy(),
            'To cancel, email ' . c365_contact_email() . ' with your reservation number, name and departure date and time.',
        )),
        'commercial-disclosure' => array('Commercial disclosure', array(
            $operator,
            'Business address: 44-2 Fukutomichō Nishidōri, Naka Ward, Yokohama, Kanagawa 231-0042, Japan.',
            'The tax-inclusive charter price is shown on each plan and confirmed before booking. Optional refreshments are charged separately.',
            'Pay at the meeting point on the day or by card through Square at booking, when available. The cruise takes place on the booked date and time.',
            c365_english_cancellation_policy(),
        )),
    );
    return $pages[$slug] ?? null;
}

function c365_english_cancellation_policy() {
    return 'Cancellation is free up to three calendar days before departure. Two days before departure: 40%. One day before: 70%. On the day or no-show: 100%. Dates are in Japan Standard Time. If the operator cancels, you receive a full refund.';
}

function c365_public_error_message($code, $message) {
    if (!c365_is_english()) { return $message; }
    $messages = array(
        'not_open' => 'Online bookings are currently unavailable. Please contact us.',
        'rate' => 'Please wait a moment and try again.',
        'input' => 'Check the required fields and try again.',
        'spam' => 'Reload the form and check your details before submitting.',
        'origin' => 'Please submit using the form on this website.',
        'nonce' => 'Reload the page and try again.',
        'size' => 'The submission is too large.',
        'closed' => 'This departure is closed to bookings.',
        'price_missing' => 'Contact us to confirm the price.',
        'full' => 'This departure is sold out. Please choose another time.',
        'price_changed' => 'The price changed. Please review it before booking.',
        'busy' => 'The booking system is busy. Please try again.',
        'request' => 'This request could not be verified. Reload the page and try again.',
        'not_found' => 'We could not find this booking.',
        'token' => 'Your card details could not be processed.',
        'method' => 'The payment method does not match this booking.',
        'square_config' => 'Advance card payment is currently unavailable. Choose payment at the meeting point.',
        'square_settings_changed' => 'The payment settings changed. Reload the page to continue.',
        'square_unknown' => 'Payment is being checked. Use the payment status button before starting a new booking.',
        'mail_unavailable' => 'The contact form is unavailable. Please email us directly.',
        'rest_no_route' => 'This request could not be completed. Reload the page and try again.',
    );
    return $messages[$code] ?? 'We could not complete your request. Please try again or contact us.';
}
