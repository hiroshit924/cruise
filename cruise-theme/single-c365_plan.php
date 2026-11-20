<?php get_header(); ?>
<main id="main">
<?php while (have_posts()) : the_post();
    $id = get_the_ID();
    list($plan_title, $plan_description) = cruise_plan_copy(get_post());
    $schedule = function_exists('c365_standard_schedule') ? c365_standard_schedule($id) : null;
?>
    <section class="event-detail-hero">
        <img src="<?php echo esc_url(cruise_event_image($id)); ?>" alt="" fetchpriority="high">
        <div class="container">
            <span class="eyebrow">YOKOHAMA MINATOMIRAI</span>
            <h1><?php echo esc_html($plan_title); ?></h1>
            <p class="cruise-summary"><span><?php cruise_output('貸切60分', 'Private 60-minute cruise'); ?></span><span><?php cruise_output('2〜5名（1名でも利用可）', 'Up to five guests'); ?></span><span><?php echo esc_html(cruise_plan_price_label($id)); ?></span></p>
        </div>
    </section>
    <article class="container content-page">
        <p><a class="text-link" href="<?php echo esc_url(cruise_local_url(home_url('/')) . '#events'); ?>"><?php cruise_output('← プラン一覧へ', '← All cruises'); ?></a></p>
        <div class="entry-content cruise-plan-content">
            <?php if (cruise_is_english()) : ?>
                <p class="event-lead"><?php echo esc_html($plan_description); ?></p>
                <h2>Before boarding</h2>
                <p>Meet at C365 at least 30 minutes before departure. Passenger life jackets are included. Please wear a warm layer and clothes you do not mind getting wet.</p>
                <?php if (in_array(get_post_field('post_name', $id), array('dog-cruise', 'dog-night-cruise'), true)) : ?>
                    <h2>Bringing your dog</h2>
                    <p>Please bring a carrier or leash and a dog diaper. Bring a dog life jacket if needed.</p>
                <?php endif; ?>
            <?php else : ?>
                <?php the_content(); ?>
            <?php endif; ?>
            <h2><?php cruise_output('乗船人数', 'Group size'); ?></h2>
            <p><?php cruise_output('1組2〜5名。おひとりでも2名分の料金でご利用いただけます。', 'The boat takes up to five passengers. Solo guests pay the same charter rate as two passengers.'); ?></p>
            <?php if ($schedule) : ?>
                <h2><?php cruise_output($schedule['label'] . 'の時間枠', 'Usual departure times'); ?></h2>
                <table>
                    <thead><tr><th scope="col"><?php cruise_output('集合時間', 'Meeting time'); ?></th><th scope="col"><?php cruise_output('体験時間', 'Cruise time'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($schedule['times'] as $time) :
                        $departure = new DateTimeImmutable($time, c365_tokyo());
                        $meeting = $departure->modify('-30 minutes')->format('H:i');
                        $return = $departure->modify('+60 minutes')->format('H:i');
                    ?>
                        <tr><td><?php echo esc_html($meeting); ?></td><td><?php echo esc_html($time . ' 〜 ' . $return); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><?php cruise_output('時刻は日本時間です。実施日と空き状況は予約案内でご確認ください。', 'All times are Japan Standard Time. Check the booking calendar for dates and availability.'); ?></p>
            <?php endif; ?>
        </div>
        <section id="booking" aria-labelledby="booking-title">
            <h2 id="booking-title"><?php cruise_output('空き状況・ご予約', 'Availability & booking'); ?></h2>
            <?php if (function_exists('c365_plan_booking')) { echo c365_plan_booking($id); } ?>
        </section>
    </article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
