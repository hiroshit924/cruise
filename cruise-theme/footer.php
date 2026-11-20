<footer class="site-footer">
    <div class="container footer-main">
        <div><?php cruise_brand(); ?></div>
        <nav aria-label="フッターナビゲーション">
            <a href="<?php echo esc_url(home_url('/#events')); ?>">プラン一覧</a>
            <a href="<?php echo esc_url(home_url('/#guide')); ?>">集合場所</a>
            <a href="<?php echo esc_url(home_url('/#faq')); ?>">よくあるご質問</a>
            <a href="<?php echo esc_url(cruise_page_url('contact')); ?>">お問い合わせ</a>
            <a href="<?php echo esc_url(cruise_page_url('company')); ?>">運営会社</a>
            <?php $safety = get_page_by_path('safety-information'); ?>
            <?php if ($safety && $safety->post_status === 'publish' && !$safety->post_password) : ?>
                <a href="<?php echo esc_url(get_permalink($safety)); ?>">事業登録・安全情報</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="container footer-bottom">
        <?php if (has_nav_menu('footer')) { wp_nav_menu(array('theme_location' => 'footer', 'container' => false, 'fallback_cb' => false, 'depth' => 1)); } else { ?>
        <ul>
            <li><a href="<?php echo esc_url(cruise_page_url('privacy-policy')); ?>">プライバシーポリシー</a></li>
            <li><a href="<?php echo esc_url(cruise_page_url('booking-policy')); ?>">予約・キャンセル規定</a></li>
            <li><a href="<?php echo esc_url(cruise_page_url('commercial-disclosure')); ?>">特定商取引法に基づく表記</a></li>
        </ul>
        <?php } ?>
        <span>© <?php echo esc_html(wp_date('Y')); ?> C365 CRUISE / 株式会社NEXUS</span>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
