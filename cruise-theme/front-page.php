<?php
get_header();
$hero = get_theme_mod('cruise_hero') ?: cruise_event_image(0);
$plans = function_exists('c365_plans') ? c365_plans() : array();
?>
<main id="main">
    <section class="hero" aria-labelledby="hero-title">
        <img class="hero-image" src="<?php echo esc_url($hero); ?>" alt="" fetchpriority="high">
        <div class="hero-shade"></div>
        <div class="container hero-content">
            <p class="hero-kicker"><span class="little-line"></span> YOKOHAMA MINATOMIRAI</p>
            <h1 id="hero-title">横浜・みなとみらい<br>貸切クルーズ</h1>
            <p class="hero-description">海から眺める、横浜の景色を貸切の船で。<br>ご家族やご友人、愛犬と過ごす60分。</p>
            <a class="button hero-button" href="#events">クルーズプランを見る <?php echo cruise_icon('arrow'); ?></a>
        </div>
    </section>
    <section class="intro" id="about">
        <div class="container intro-layout">
            <div data-intro-reveal><span class="eyebrow">ABOUT THE CRUISE</span><h2>横浜を、<br>海の上から。</h2></div>
            <div class="intro-copy" data-intro-reveal>
                <p>日ノ出桟橋から大岡川を抜け、みなとみらいへ。赤レンガ倉庫やベイブリッジ、ランドマークタワーなど、横浜を代表する景色を水上から楽しめます。</p>
                <p>1組2〜5名の貸切で、運航は60分。おひとりでも2名分の料金でご利用いただけます。愛犬と一緒に楽しめるプランや、みなとみらいの灯りを眺めるナイトクルーズもご用意しています。</p>
            </div>
        </div>
    </section>
    <section class="events-section" id="events">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">CRUISE PLANS</span><h2>クルーズプラン</h2></div></div>
            <div class="event-grid">
                <?php foreach ($plans as $plan) :
                    $url = get_permalink($plan);
                ?>
                <article class="event-card">
                    <a class="event-image" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($plan->post_title); ?>">
                        <img src="<?php echo esc_url(cruise_event_image($plan->ID)); ?>" alt="" loading="lazy">
                    </a>
                    <div class="event-body">
                        <p class="cruise-card-info">貸切60分 · 2〜5名（1名でも利用可）</p>
                        <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($plan->post_title); ?></a></h3>
                        <p><?php echo esc_html(get_the_excerpt($plan)); ?></p>
                        <p class="cruise-card-price"><?php echo esc_html(cruise_plan_price_label($plan->ID)); ?></p>
                        <a class="text-link cruise-card-link" href="<?php echo esc_url($url); ?>">プラン詳細を見る <?php echo cruise_icon('arrow'); ?></a>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php if (!$plans) : ?><p>現在掲載中のプランはありません。</p><?php endif; ?>
            </div>
        </div>
    </section>
    <section class="guide-section" id="guide">
        <div class="container guide-layout">
            <div class="cruise-meeting-copy">
                <span class="eyebrow">MEETING POINT</span>
                <h2>出航30分前にC365へ。</h2>
                <p>〒231-0042 神奈川県横浜市中区福富町西通44-2<br>京急 日ノ出町駅から徒歩5分、JR 桜木町駅から徒歩10分。</p>
                <a class="text-link" href="<?php echo esc_url(add_query_arg(array('api' => '1', 'query' => rawurlencode('C365 神奈川県横浜市中区福富町西通44-2')), 'https://www.google.com/maps/search/')); ?>">Google マップで開く <?php echo cruise_icon('arrow'); ?></a>
            </div>
            <iframe class="cruise-meeting-map" title="集合場所 C365 の Google マップ" src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d13001.489473867217!2d139.6293609!3d35.4455747!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x60185d889da82bed%3A0x2d8f9279c1e62189!2sC365!5e0!3m2!1sja!2sjp!4v1731303735238!5m2!1sja!2sjp" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            <img class="cruise-meeting" src="<?php echo esc_url(get_template_directory_uri() . '/assets/meeting-point.jpg'); ?>" alt="集合場所のカフェ C365" loading="lazy" width="1280" height="960">
        </div>
    </section>
    <section class="faq-section" id="faq">
        <div class="container faq-layout">
            <div><span class="eyebrow">FAQ</span><h2>よくあるご質問</h2></div>
            <div class="faq-list">
                <?php foreach (cruise_faq_items() as $question => $answer) : ?>
                    <details><summary><?php echo esc_html($question); ?><i aria-hidden="true">+</i></summary><p><?php echo esc_html($answer); ?></p></details>
                <?php endforeach; ?>
                <a class="text-link" href="<?php echo esc_url(cruise_page_url('contact')); ?>">お問い合わせ <?php echo cruise_icon('arrow'); ?></a>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>
