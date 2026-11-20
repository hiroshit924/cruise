<?php
if (!defined('ABSPATH')) { exit; }

function c365_import_launch_events() {
    $plans = array(
        array('private-cruise', 'みなとみらい貸切クルーズ', 15000, 'l00006D5C4', '赤レンガ倉庫やベイブリッジ、ランドマークタワーを海から。ご家族やご友人と、横浜の景色を貸切の船で楽しめます。'),
        array('dog-cruise', '愛犬と貸切ドッグクルーズ', 18000, 'l00006D5C3', '愛犬と潮風を感じながら、みなとみらいへ。赤レンガ倉庫やハンマーヘッドを眺め、海の上で記念の一枚を。'),
        array('dog-night-cruise', '愛犬と貸切ナイトクルーズ', 18000, 'l00006D5C5', '灯りがともるみなとみらいを、愛犬と一緒に海から。横浜の夜景を眺めながら、誕生日や記念日をお過ごしください。'),
    );
    $ids = array();
    foreach ($plans as $order => list($slug, $name, $price, $jalan, $description)) {
        $day_departures = array('private-cruise', 'dog-cruise');
        $schedule = in_array($slug, $day_departures, true) ? 'day' : 'night';
        $existing = get_page_by_path($slug, OBJECT, 'c365_plan');
        if ($existing) {
            add_post_meta($existing->ID, '_c365_jalan_price', $price, true);
            add_post_meta($existing->ID, '_c365_schedule', $schedule, true);
            $ids[] = $existing->ID;
            continue;
        }

        $body = '<p class="event-lead">' . esc_html($description) . '</p>'
            . '<h2>乗船案内</h2><p>運航は60分。1組につき最大5名でご乗船いただけます。対象は小学生以上（6歳以上）です。乗船用ライフジャケットのレンタルが含まれます。</p>'
            . '<p>出航30分前にC365へお越しください。波しぶきがかかることがあるため、濡れてもよい服装でお越しください。防寒着もご用意ください。</p>';
        if ($slug !== 'private-cruise') {
            $body .= '<h2>愛犬と乗船される方へ</h2><p>飛び出し防止のため、ケージ・バッグに入れるか、リードを着用してください。マナーパンツの着用をお願いします。ペット用ライフジャケットは必要に応じてご持参ください。</p>';
        }
        $body .= '<h2>ドリンク・軽食</h2><p>飲食物はお持ち込みいただけます。お持ち込みの際は1ドリンクのご注文をお願いします。C365特製クレープ、各種ドリンク、乾杯用スパークリングワイン（1名1,000円）などを有料でご用意できます。</p>'
            . '<h2>天候による中止</h2><p>天候や海況により中止する場合は、前日18時までにご連絡します。主催者都合の中止に伴うキャンセル料はかかりません。</p>';
        $id = wp_insert_post(array(
            'post_type' => 'c365_plan', 'post_status' => 'publish', 'post_name' => $slug,
            'post_title' => $name, 'post_excerpt' => $description, 'post_content' => $body, 'menu_order' => $order,
        ), true);
        if (is_wp_error($id)) { continue; }
        update_post_meta($id, '_c365_charter_price', $price);
        update_post_meta($id, '_c365_jalan_price', $price);
        update_post_meta($id, '_c365_schedule', $schedule);
        update_post_meta($id, '_c365_image', $slug);
        update_post_meta($id, '_c365_jalan', 'https://www.jalan.net/kankou/spt_guide000000226734/activity/' . $jalan . '/');
        $ids[] = $id;
    }
    return $ids;
}
