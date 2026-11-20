<?php
if (!defined('ABSPATH')) { exit; }
add_shortcode('cruise_policy', function () {
    return '<p>' . esc_html(c365_policy_text()) . '</p><p>じゃらんで予約された方は、じゃらんの予約案内に沿ってお手続きください。</p>'
        . '<p>自社サイト予約の変更・キャンセルは、予約番号・代表者名・出航日時を添えて、<a href="'
        . esc_url('mailto:' . c365_contact_email()) . '">' . esc_html(c365_contact_email()) . '</a> までご連絡ください。キャンセル料は取消連絡の受信日（日本時間）で判定します。'
        . '事前カード決済の場合は、キャンセル料を差し引いた額をお支払いに使用したカードへ返金します。</p>';
});
add_shortcode('cruise_operator', function () {
    $email = c365_contact_email();
    $details = array(
        '販売事業者・運営会社' => '株式会社NEXUS',
        '運営責任者' => c365_setting('responsible_person', '平丸聖弥'),
        '所在地' => '神奈川県横浜市中区福富町西通44-2 Speranza Sakuragicho 1F',
        '電話番号' => c365_setting('phone', '045-297-6375'),
    );
    $html = '<table><tbody>';
    foreach ($details as $label => $value) {
        $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
    return $html . '<tr><th scope="row">メール</th><td><a href="' . esc_url('mailto:' . $email) . '">' . esc_html($email) . '</a></td></tr>'
        . '<tr><th scope="row">URL</th><td><a href="' . esc_url(home_url('/')) . '">' . esc_html(home_url('/')) . '</a></td></tr></tbody></table>';
});
function c365_import_pages() {
    $payment = '<p>現地決済は当日集合場所で、事前カード決済はSquareで予約時にお支払いいただきます。現地決済は受付完了時、事前決済は決済完了時に予約が確定します。</p>';
    $pages = array(
        'contact' => array('お問い合わせ', '[cruise_contact]'),
        'company' => array('運営会社', '<p>横浜みなとみらいの貸切クルーズを運営しています。</p>[cruise_operator]'),
        'privacy-policy' => array('プライバシーポリシー',
            '<p>株式会社NEXUSは、個人情報保護法その他の関連法令に従い、お客様の情報を取り扱います。</p>'
            . '<h2>取得する情報と利用目的</h2><p>代表者の氏名・電話番号・メールアドレス、乗船人数、対象年齢の確認、予約・決済状態、同意日時、お問い合わせ内容を、予約、運航、決済・返金、会計、連絡、安全確保のために利用します。迷惑送信対策のため、アクセス元情報を一時的なハッシュとして処理します。</p>'
            . '<h2>外部サービス</h2><p>事前決済にSquareを利用します。カード情報はSquareが処理し、当サイトでは保存しません。メール送信にはMicrosoft 365 / Microsoft Graphを利用します。サービス提供に必要な業務を委託する場合や法令に基づく場合を除き、本人の同意なく第三者に個人情報を提供しません。</p>'
            . '<h2>保存と開示等のご請求</h2><p>利用目的や法令に必要な期間保存し、不要となった情報は削除または匿名化します。開示・訂正・削除等のご請求は本人確認のうえ対応します。フォーム保護にCookie、予約重複防止と決済状態の確認にセッションストレージを使用します。</p>[cruise_operator]'),
        'booking-policy' => array('予約・キャンセル規定',
            '<h2>自社サイトでの予約</h2><p>1枠につき1組、2〜5名の貸切です。1名でも2名分の料金でご利用いただけます。料金は1組単位です。受付は出航30分前まで。事前決済の仮予約は最長15分です。決済確認中は二重予約せず、運営にご連絡ください。</p>' . $payment
            . '<h2>キャンセル・返金</h2>[cruise_policy]'),
        'commercial-disclosure' => array('特定商取引法に基づく表記',
            '[cruise_operator]<h2>販売価格と追加費用</h2><p>貸切1組の税込料金は各プランと予約画面に表示します。集合場所までの交通費、通信費、任意の有料オプションはお客様のご負担です。</p>'
            . '<h2>お支払い方法・時期</h2>' . $payment
            . '<h2>サービス提供時期</h2><p>予約した日本時間の出航日時から60分間です。集合は出航30分前です。</p><h2>キャンセル・返金</h2>[cruise_policy]'),
    );
    foreach ($pages as $slug => list($title, $body)) {
        $existing = get_page_by_path($slug);
        $data = array('post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $body);
        if (!$existing) {
            wp_insert_post($data);
        } elseif ($slug === 'privacy-policy' && $existing->post_status === 'draft'
            && $existing->post_date_gmt === $existing->post_modified_gmt
            && strpos($existing->post_content, 'privacy-policy-tutorial') !== false) {
            wp_update_post(array('ID' => $existing->ID) + $data);
        }
    }
    c365_import_safety_page();
    $privacy = get_page_by_path('privacy-policy');
    if ($privacy && !get_option('wp_page_for_privacy_policy')) {
        update_option('wp_page_for_privacy_policy', $privacy->ID);
    }
}
add_filter('wp_privacy_personal_data_exporters',function($exporters){$exporters['cruise']=array('exporter_friendly_name'=>'C365クルーズ 予約情報','callback'=>'c365_privacy_export');return $exporters;});
function c365_privacy_export($email,$page=1){
    global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.c365_table().' WHERE email=%s ORDER BY id LIMIT 50 OFFSET %d',$email,($page-1)*50));$data=array();
    foreach($rows as $r){$values=array('予約番号'=>$r->public_id,'プラン'=>c365_departure_title($r->event_id),'代表者'=>$r->customer_name,'電話番号'=>$r->phone,'メール'=>$r->email,'乗船人数'=>$r->passenger_count,'合計金額'=>$r->amount,'状態'=>$r->status,'同意条件'=>$r->policy_text,'同意日時（UTC）'=>$r->consent_at);$items=array();foreach($values as $name=>$value){$items[]=array('name'=>$name,'value'=>$value);}$data[]=array('group_id'=>'cruise-booking','group_label'=>'C365クルーズ 予約','item_id'=>'booking-'.$r->id,'data'=>$items);}
    return array('data'=>$data,'done'=>count($rows)<50);
}
add_filter('wp_privacy_personal_data_erasers',function($erasers){$erasers['cruise']=array('eraser_friendly_name'=>'C365クルーズ 予約情報','callback'=>function($email){global $wpdb;$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.c365_table().' WHERE email=%s',$email));return array('items_removed'=>false,'items_retained'=>$count>0,'messages'=>$count?array('予約・決済・会計記録の保存義務と進行中の予約を確認するため、C365クルーズ運営が個別に対応します。自動削除は行っていません。'):array(),'done'=>true);});return $erasers;});
