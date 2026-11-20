<footer class="site-footer">
    <div class="container footer-main">
        <div><?php cruise_brand(); ?></div>
        <nav aria-label="<?php cruise_output('フッターナビゲーション', 'Footer navigation'); ?>">
            <a href="<?php echo esc_url(cruise_local_url(home_url('/')) . '#events'); ?>"><?php cruise_output('プラン一覧', 'Cruises'); ?></a>
            <a href="<?php echo esc_url(cruise_local_url(home_url('/')) . '#guide'); ?>"><?php cruise_output('集合場所', 'Meeting point'); ?></a>
            <a href="<?php echo esc_url(cruise_local_url(home_url('/')) . '#faq'); ?>"><?php cruise_output('よくあるご質問', 'FAQ'); ?></a>
            <a href="<?php echo esc_url(cruise_page_url('contact')); ?>"><?php cruise_output('お問い合わせ', 'Contact'); ?></a>
            <a href="<?php echo esc_url(cruise_page_url('company')); ?>"><?php cruise_output('運営会社', 'Cruise operator'); ?></a>
            <?php $safety = get_page_by_path('safety-information'); ?>
            <?php if ($safety && $safety->post_status === 'publish' && !$safety->post_password) : ?>
                <a href="<?php echo esc_url(get_permalink($safety)); ?>"><?php cruise_output('事業登録・安全情報', 'Safety information (Japanese)'); ?></a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="container footer-bottom">
        <?php if (!cruise_is_english() && has_nav_menu('footer')) { wp_nav_menu(array('theme_location' => 'footer', 'container' => false, 'fallback_cb' => false, 'depth' => 1)); } else { ?>
        <ul>
            <li><a href="<?php echo esc_url(cruise_page_url('privacy-policy')); ?>"><?php cruise_output('プライバシーポリシー', 'Privacy policy'); ?></a></li>
            <li><a href="<?php echo esc_url(cruise_page_url('booking-policy')); ?>"><?php cruise_output('予約・キャンセル規定', 'Booking and cancellation'); ?></a></li>
            <li><a href="<?php echo esc_url(cruise_page_url('commercial-disclosure')); ?>"><?php cruise_output('特定商取引法に基づく表記', 'Commercial disclosure'); ?></a></li>
        </ul>
        <?php } ?>
        <span>© <?php echo esc_html(wp_date('Y')); ?> C365 CRUISE / <?php cruise_output('株式会社NEXUS', 'NEXUS Co., Ltd.'); ?></span>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
