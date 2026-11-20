<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php cruise_output('コンテンツへスキップ', 'Skip to content'); ?></a>
<?php
$home = cruise_local_url(home_url('/'));
$nav = array(
    'about' => array('クルーズについて', 'About the cruise'),
    'events' => array('プラン一覧', 'Cruises'),
    'guide' => array('集合場所', 'Meeting point'),
);
?>
<header class="site-header">
    <div class="header-inner">
        <?php cruise_brand(); ?>
        <nav class="desktop-nav" aria-label="<?php echo esc_attr(cruise_text('メインナビゲーション', 'Main navigation')); ?>">
            <?php foreach ($nav as $anchor => $label) : ?>
                <a href="<?php echo esc_url($home . '#' . $anchor); ?>"><?php cruise_output(...$label); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php cruise_locale_switch(); ?>
        <a class="button header-join" href="<?php echo esc_url($home . '#events'); ?>"><?php cruise_output('プランを見る', 'View cruises'); ?> <?php echo cruise_icon('arrow'); ?></a>
        <button class="menu-toggle" type="button" aria-label="<?php echo esc_attr(cruise_text('メニューを開く', 'Open menu')); ?>" data-close-label="<?php echo esc_attr(cruise_text('メニューを閉じる', 'Close menu')); ?>" aria-expanded="false" aria-controls="mobile-nav"><span></span><span></span></button>
    </div>
    <nav id="mobile-nav" class="mobile-nav" aria-label="<?php echo esc_attr(cruise_text('モバイルナビゲーション', 'Mobile navigation')); ?>" hidden>
        <?php foreach ($nav + array('faq' => array('よくあるご質問', 'FAQ')) as $anchor => $label) : ?>
            <a href="<?php echo esc_url($home . '#' . $anchor); ?>"><?php cruise_output(...$label); ?></a>
        <?php endforeach; ?>
    </nav>
</header>
