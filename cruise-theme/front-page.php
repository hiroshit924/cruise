<?php
get_header();
$custom_hero = get_theme_mod('cruise_hero');
$hero = $custom_hero ?: add_query_arg('ver', wp_get_theme()->get('Version'), cruise_event_image(0));
$plans = function_exists('c365_plans') ? c365_plans() : array();
?>
<main id="main">
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-content">
            <p class="hero-kicker"><span class="little-line" aria-hidden="true"></span> YOKOHAMA MINATO MIRAI</p>
            <h1 id="hero-title"><?php cruise_output('横浜・みなとみらい', 'A private cruise'); ?><br><em><?php cruise_output('貸切クルーズ', 'in Yokohama'); ?></em></h1>
            <p class="hero-description"><?php cruise_output('海から眺める、横浜の景色を貸切の船で。', 'See Minato Mirai from the water on a private boat.'); ?><br><?php cruise_output('ご家族やご友人、愛犬と過ごす60分。', 'Spend 60 minutes on the bay with family, friends or your dog.'); ?></p>
            <a class="button hero-button" href="#events"><?php cruise_output('クルーズプランを見る', 'Explore cruises'); ?> <?php echo cruise_icon('arrow'); ?></a>
        </div>
        <div class="hero-visual">
            <img class="hero-image" src="<?php echo esc_url($hero); ?>" alt="<?php echo esc_attr($custom_hero ? '' : cruise_text('船上で夕暮れのみなとみらいを眺める乗客', 'Passengers enjoying the Minato Mirai waterfront at dusk')); ?>" fetchpriority="high">
        </div>
    </section>
    <section class="intro" id="about">
        <div class="container intro-layout">
            <div data-intro-reveal><span class="eyebrow">ABOUT THE CRUISE</span><h2><?php cruise_output('横浜を、', 'Yokohama,'); ?><br><?php cruise_output('海の上から。', 'from the water.'); ?></h2></div>
            <div class="intro-copy" data-intro-reveal>
                <p><?php cruise_output('日ノ出桟橋から大岡川を抜け、みなとみらいへ。赤レンガ倉庫やベイブリッジ、ランドマークタワーなど、横浜を代表する景色を水上から楽しめます。', 'Set out from Hinode Pier and follow the Ōoka River to Minato Mirai. See Yokohama’s Red Brick Warehouse, Bay Bridge and Landmark Tower from the water.'); ?></p>
                <p><?php cruise_output('1組2〜5名の貸切で、運航は60分。おひとりでも2名分の料金でご利用いただけます。愛犬と一緒に楽しめるプランや、みなとみらいの灯りを眺めるナイトクルーズもご用意しています。', 'Charter the boat for 60 minutes for up to five passengers. Solo guests pay the same charter rate as two passengers. Choose a dog-friendly cruise or see Minato Mirai illuminated after dark.'); ?></p>
            </div>
        </div>
    </section>
    <section class="events-section" id="events">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">CRUISE PLANS</span><h2><?php cruise_output('クルーズプラン', 'Cruise plans'); ?></h2></div></div>
            <div class="event-grid">
                <?php foreach ($plans as $plan) :
                    $url = cruise_local_url(get_permalink($plan));
                    list($plan_title, $plan_description) = cruise_plan_copy($plan);
                ?>
                <article class="event-card">
                    <a class="event-image" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($plan_title); ?>">
                        <img src="<?php echo esc_url(cruise_event_image($plan->ID)); ?>" alt="" loading="lazy">
                    </a>
                    <div class="event-body">
                        <p class="cruise-card-info"><?php cruise_output('貸切60分 · 2〜5名（1名でも利用可）', 'Private 60-minute cruise · Up to five guests'); ?></p>
                        <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($plan_title); ?></a></h3>
                        <p><?php echo esc_html($plan_description); ?></p>
                        <p class="cruise-card-price"><?php echo esc_html(cruise_plan_price_label($plan->ID)); ?></p>
                        <a class="text-link cruise-card-link" href="<?php echo esc_url($url); ?>"><?php cruise_output('プラン詳細を見る', 'View cruise details'); ?> <?php echo cruise_icon('arrow'); ?></a>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php if (!$plans) : ?><p><?php cruise_output('現在掲載中のプランはありません。', 'There are no published cruises at the moment.'); ?></p><?php endif; ?>
            </div>
        </div>
    </section>
    <section class="guide-section" id="guide">
        <div class="container guide-layout">
            <div class="cruise-meeting-copy">
                <span class="eyebrow">MEETING POINT</span>
                <h2><?php cruise_output('出航30分前にC365へ。', 'Meet at C365 30 minutes before departure.'); ?></h2>
                <p><?php cruise_output('〒231-0042 神奈川県横浜市中区福富町西通44-2', '44-2 Fukutomichō Nishidōri, Naka Ward, Yokohama 231-0042'); ?><br><?php cruise_output('京急 日ノ出町駅から徒歩5分、JR 桜木町駅から徒歩10分。', 'Five minutes on foot from Hinodechō Station or ten minutes from Sakuragichō Station.'); ?></p>
                <a class="text-link" href="<?php echo esc_url(add_query_arg(array('api' => '1', 'query' => rawurlencode('C365 神奈川県横浜市中区福富町西通44-2')), 'https://www.google.com/maps/search/')); ?>"><?php cruise_output('Google マップで開く', 'Open in Google Maps'); ?> <?php echo cruise_icon('arrow'); ?></a>
            </div>
            <iframe class="cruise-meeting-map" title="<?php echo esc_attr(cruise_text('集合場所 C365 の Google マップ', 'Google Maps location of the C365 meeting point')); ?>" src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d13001.489473867217!2d139.6293609!3d35.4455747!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x60185d889da82bed%3A0x2d8f9279c1e62189!2sC365!5e0!3m2!1sja!2sjp!4v1731303735238!5m2!1sja!2sjp" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            <img class="cruise-meeting" src="<?php echo esc_url(get_template_directory_uri() . '/assets/meeting-point.jpg'); ?>" alt="<?php echo esc_attr(cruise_text('集合場所のカフェ C365', 'Café C365, the meeting point')); ?>" loading="lazy" width="1280" height="960">
        </div>
    </section>
    <section class="faq-section" id="faq">
        <div class="container faq-layout">
            <div><span class="eyebrow">FAQ</span><h2><?php cruise_output('よくあるご質問', 'Frequently asked questions'); ?></h2></div>
            <div class="faq-list">
                <?php foreach (cruise_faq_items() as $question => $answer) : ?>
                    <details><summary><?php echo esc_html($question); ?><i aria-hidden="true">+</i></summary><p><?php echo esc_html($answer); ?></p></details>
                <?php endforeach; ?>
                <a class="text-link" href="<?php echo esc_url(cruise_page_url('contact')); ?>"><?php cruise_output('お問い合わせ ', 'Contact us '); ?><?php echo cruise_icon('arrow'); ?></a>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>
