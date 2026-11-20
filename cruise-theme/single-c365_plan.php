<?php get_header(); ?>
<main id="main">
<?php while (have_posts()) : the_post();
    $id = get_the_ID();
    $schedule = function_exists('c365_standard_schedule') ? c365_standard_schedule($id) : null;
?>
    <section class="event-detail-hero">
        <img src="<?php echo esc_url(cruise_event_image($id)); ?>" alt="" fetchpriority="high">
        <div class="container">
            <span class="eyebrow">YOKOHAMA MINATOMIRAI</span>
            <h1><?php the_title(); ?></h1>
            <p class="cruise-summary"><span>貸切60分</span><span>2〜5名（1名でも利用可）</span><span><?php echo esc_html(cruise_plan_price_label($id)); ?></span></p>
        </div>
    </section>
    <article class="container content-page">
        <p><a class="text-link" href="<?php echo esc_url(home_url('/#events')); ?>">← プラン一覧へ</a></p>
        <div class="entry-content cruise-plan-content">
            <?php the_content(); ?>
            <h2>乗船人数</h2>
            <p>1組2〜5名。おひとりでも2名分の料金でご利用いただけます。</p>
            <?php if ($schedule) : ?>
                <h2><?php echo esc_html($schedule['label']); ?>の時間枠</h2>
                <table>
                    <thead><tr><th scope="col">集合時間</th><th scope="col">体験時間</th></tr></thead>
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
                <p>時刻は日本時間です。実施日と空き状況は予約案内でご確認ください。</p>
            <?php endif; ?>
        </div>
        <section id="booking" aria-labelledby="booking-title">
            <h2 id="booking-title">空き状況・ご予約</h2>
            <?php if (function_exists('c365_plan_booking')) { echo c365_plan_booking($id); } ?>
        </section>
    </article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
