<?php
if (!defined('ABSPATH')) { exit; }

function c365_register_vessels() {
    register_post_type('c365_vessel', array(
        'labels' => array(
            'name' => '使用船舶',
            'singular_name' => '使用船舶',
            'add_new_item' => '使用船舶を追加',
            'edit_item' => '使用船舶を編集',
            'featured_image' => '船舶写真',
            'set_featured_image' => '船舶写真を設定',
            'remove_featured_image' => '船舶写真を削除',
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-admin-site-alt3',
        'supports' => array('title', 'thumbnail'),
    ));
}
add_action('init', 'c365_register_vessels', 5);

function c365_vessel_fields() {
    return array(
        'number' => '船舶番号',
        'tonnage' => '総トン数',
        'passengers' => '船舶の旅客定員',
        'lifesaving' => '救命設備',
        'radio' => '通信・無線設備',
        'certificate' => '船舶検査証書の交付年月日',
        'source' => '公表資料URL',
    );
}

add_action('add_meta_boxes_c365_vessel', function () {
    add_meta_box('c365_vessel_details', '使用船舶情報', 'c365_render_vessel_fields', 'c365_vessel', 'normal');
});

function c365_render_vessel_fields($post) {
    wp_nonce_field('c365_save_vessel', 'c365_vessel_nonce');
    foreach (c365_vessel_fields() as $key => $label) {
        $name = 'c365_vessel_' . $key;
        $value = get_post_meta($post->ID, '_' . $name, true);
        $type = $key === 'source' ? 'url' : 'text';
        printf(
            '<p><label for="%1$s">%2$s</label><br><input class="widefat" id="%1$s" name="%1$s" type="%3$s" value="%4$s"></p>',
            esc_attr($name), esc_html($label), esc_attr($type), esc_attr($value)
        );
    }
}

add_action('save_post_c365_vessel', function ($id) {
    if (!c365_may_edit($id, 'c365_vessel_nonce', 'c365_save_vessel')) { return; }
    foreach (c365_vessel_fields() as $key => $label) {
        $name = 'c365_vessel_' . $key;
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) { continue; }
        $raw = wp_unslash($_POST[$name]);
        $value = $key === 'source' ? esc_url_raw($raw, array('https', 'http')) : sanitize_text_field($raw);
        update_post_meta($id, '_' . $name, $value);
    }
});

add_shortcode('cruise_vessels', function () {
    $vessels = get_posts(array(
        'post_type' => 'c365_vessel', 'post_status' => 'publish', 'numberposts' => -1,
        'orderby' => 'title', 'order' => 'ASC',
    ));
    if (!$vessels) { return ''; }

    ob_start();
    foreach ($vessels as $vessel) :
        $thumbnail_id = get_post_thumbnail_id($vessel->ID);
        $source = get_post_meta($vessel->ID, '_c365_vessel_source', true);
        ?>
        <section class="cruise-vessel">
            <h3><?php echo esc_html($vessel->post_title); ?></h3>
            <?php if ($thumbnail_id) :
                $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                echo wp_get_attachment_image($thumbnail_id, 'large', false, array(
                    'class' => 'cruise-vessel-photo',
                    'alt' => $alt ?: $vessel->post_title . 'の船舶写真',
                ));
            endif; ?>
            <table>
                <tbody>
                <?php foreach (c365_vessel_fields() as $key => $label) :
                    if ($key === 'source') { continue; }
                    $value = get_post_meta($vessel->ID, '_c365_vessel_' . $key, true);
                    if ($value === '') { continue; }
                    ?>
                    <tr><th scope="row"><?php echo esc_html($label); ?></th><td><?php echo esc_html($value); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($source) : ?><p><a href="<?php echo esc_url($source); ?>">船舶情報の公表資料</a></p><?php endif; ?>
        </section>
    <?php endforeach;
    return ob_get_clean();
});

function c365_import_safety_page() {
    if (!get_page_by_path('sea365', OBJECT, 'c365_vessel')) {
        $id = wp_insert_post(array(
            'post_type' => 'c365_vessel', 'post_status' => 'publish', 'post_name' => 'sea365', 'post_title' => 'Sea365',
        ), true);
        if (!is_wp_error($id)) {
            $data = array(
                'number' => '第241-21426号',
                'tonnage' => '2.9トン',
                'passengers' => '9名',
                'lifesaving' => '救命胴衣10着（株式会社NEXUS公表資料）、救命いかだ0基、救命浮器1基。国土交通省公表情報には子ども用救命胴衣2着の記載があります。',
                'radio' => '携帯電話1台、衛星電話なし、業務用無線設備なし。',
                'certificate' => '2025年（令和7年）4月3日',
                'source' => 'https://chain-nexus.co.jp/archives/788',
            );
            foreach ($data as $key => $value) { update_post_meta($id, '_c365_vessel_' . $key, $value); }
        }
    }

    if (get_page_by_path('safety-information')) { return; }
    $body = <<<'HTML'
<h2>事業者・登録情報</h2>
<p>株式会社NEXUSは、一般不定期航路事業として、関東運輸局の登録を受けています。</p>
<table><tbody>
<tr><th scope="row">事業の種類</th><td>内航一般不定期航路事業</td></tr>
<tr><th scope="row">登録番号</th><td>関東一不 第65号</td></tr>
<tr><th scope="row">登録年月日</th><td>2025年（令和7年）9月3日</td></tr>
<tr><th scope="row">旧制度での届出年度</th><td>2021年（令和3年）</td></tr>
</tbody></table>
[cruise_operator]
<p><a href="https://chain-nexus.co.jp/">株式会社NEXUS 公式サイト</a></p>

<h2>使用船舶</h2>
<p>本サイトでご案内するクルーズの乗船人数は1組最大5名です。</p>
[cruise_vessels]

<h2>安全への取り組み</h2>
<p>出航前の定期点検と年2回の乗務員訓練を実施しています。2026年1月16日付の株式会社NEXUSの公表資料では、地域旅客船安全協議会への加入はありません。</p>
<p>同資料における株式会社NEXUSの船舶保有数は5隻です。</p>

<h2>事故・行政処分情報</h2>
<p>2026年1月16日付の株式会社NEXUSの公表資料では、過去5年間の事故件数は0件です。行政処分情報は、国土交通省の事業者詳細をご参照ください。</p>

<h2>船客傷害賠償責任保険</h2>
<p>保険契約の公表情報は確認中です。</p>

<h2>公表資料</h2>
<ul>
<li><a href="https://wwwtb.mlit.go.jp/kanto/content/000380743.pdf">関東運輸局 一般不定期航路事業者登録簿（PDF）</a></li>
<li><a href="https://www.mlit.go.jp/senpaku/anzen/ssi_details.cgi?g=3014">国土交通省 旅客船事業者の安全情報（株式会社NEXUS）</a></li>
<li><a href="https://chain-nexus.co.jp/archives/788">株式会社NEXUS 海上運送法に則る安全情報の公開</a></li>
</ul>
HTML;
    wp_insert_post(array(
        'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'safety-information',
        'post_title' => '一般不定期航路事業の登録・安全情報', 'post_content' => $body,
    ));
}
