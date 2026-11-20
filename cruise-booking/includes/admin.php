<?php
if (!defined('ABSPATH')) { exit; }
add_action('admin_menu',function () {
    add_menu_page('C365クルーズ 予約管理','C365クルーズ予約','manage_options','cruise-bookings','c365_admin_bookings','dashicons-groups',26);
    add_submenu_page('cruise-bookings','接続・受付設定','接続・受付設定','manage_options','cruise-settings','c365_admin_settings');
    add_submenu_page('cruise-bookings','メール送信状況','メール送信状況','manage_options','cruise-mail','c365_admin_mail');
});
function c365_admin_header($title) {
    if (!current_user_can('manage_options')) { wp_die('権限がありません。'); }
    echo '<div class="wrap"><h1>'.esc_html($title).'</h1><style>.vc-admin-grid{display:flex;gap:15px;flex-wrap:wrap;margin:20px 0}.vc-admin-card{background:white;border:1px solid #ccd0d4;padding:18px;min-width:220px}.vc-admin-card h3{margin:0 0 10px}.vc-admin-form{max-width:850px;background:#fff;padding:22px;margin:20px 0;border:1px solid #ccd0d4}.vc-admin-form input[type=text],.vc-admin-form input[type=email],.vc-admin-form textarea{width:100%}.vc-admin-form label{display:block;margin:15px 0}.vc-admin-table{overflow:auto}.vc-status{font-weight:600}.vc-small{font-size:11px;color:#646970}</style>';
    if (isset($_GET['c365_notice'])) { $notice=get_transient('c365_notice_'.get_current_user_id());if($notice){echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';delete_transient('c365_notice_'.get_current_user_id());} }
}
function c365_admin_form_start($action) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('c365_admin_'.$action);echo '<input type="hidden" name="action" value="c365_admin"><input type="hidden" name="c365_action" value="'.esc_attr($action).'">'; }
function c365_admin_events() { return get_posts(array('post_type'=>'c365_event','posts_per_page'=>-1,'post_status'=>array('publish','draft','future','private'),'meta_key'=>'_c365_date','orderby'=>'meta_value','order'=>'ASC')); }
function c365_status_label($status) { $labels=array('reserved'=>'予約確定・現地未収','paid'=>'支払済','pending_payment'=>'カード入力待ち','payment_processing'=>'Square確認中','payment_failed'=>'決済失敗','expired'=>'仮予約期限切れ','cancelled'=>'キャンセル','refund_pending'=>'返金処理中','refunded'=>'返金済');return $labels[$status]??$status; }
function c365_admin_bookings() {
    global $wpdb;c365_admin_header('C365クルーズ 予約管理');$events=c365_admin_events();$selected=isset($_GET['event'])?absint($_GET['event']):0;
    echo '<p>貸切は1枠1組、最大5名です。同じ船を使用する他プランの重複枠も、仮予約・確定済みの予約がある間は受付を停止します。</p><div class="vc-admin-grid">';
    foreach($events as $event){
        $totals=$wpdb->get_row($wpdb->prepare('SELECT COALESCE(SUM(passenger_count),0) AS passengers,COUNT(*) AS bookings FROM '.c365_table()." WHERE event_id=%d AND status IN ('reserved','paid')",$event->ID));$available=c365_available($event->ID);
        echo '<a class="vc-admin-card" href="'.esc_url(admin_url('admin.php?page=cruise-bookings&event='.$event->ID)).'"><h3>'.esc_html(c365_date('n/j（D） H:i',c365_event_time($event->ID))).'</h3><strong>'.esc_html(c365_departure_title($event->ID)).'</strong><p>確定：'.esc_html($totals->bookings).'組・'.esc_html($totals->passengers).'名<br>空き状況：'.($available?'空きあり':'受付不可').'</p></a>';
    }echo '</div>';
    $lookup = isset($_GET['booking_number']) && is_string($_GET['booking_number'])
        ? sanitize_text_field(wp_unslash($_GET['booking_number'])) : '';
    echo '<form method="get"><input type="hidden" name="page" value="cruise-bookings">';
    echo '<label>出航枠を選択 <select name="event"><option value="0">すべて</option>';
    foreach ($events as $event) {
        echo '<option value="'.esc_attr($event->ID).'" '.selected($selected,$event->ID,false).'>'.esc_html(c365_date('n/j H:i',c365_event_time($event->ID)).' '.c365_departure_title($event->ID)).'</option>';
    }
    echo '</select></label> <label>予約番号 <input type="search" name="booking_number" placeholder="C365-000123" value="' . esc_attr($lookup) . '"></label> <button class="button">検索</button></form>';
    $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $where = " WHERE status<>'sandbox_deleted'";
    if ($selected) { $where .= $wpdb->prepare(' AND event_id=%d', $selected); }
    if (preg_match('/^(?:C365-)?([0-9]{1,20})$/iD', $lookup, $matches)) {
        $where .= $wpdb->prepare(' AND id=CAST(%s AS UNSIGNED)', $matches[1]);
    } elseif (wp_is_uuid($lookup)) {
        $where .= $wpdb->prepare(' AND public_id=%s', $lookup);
    } elseif ($lookup !== '') {
        $where .= ' AND 1=0';
    }
    $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.c365_table().$where);
    $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.c365_table().$where.' ORDER BY id DESC LIMIT %d OFFSET %d',50,($page-1)*50));
    echo '<h2>予約一覧（'.esc_html($total).'件）</h2><div class="vc-admin-table"><table class="widefat striped"><thead><tr><th>予約・出航枠</th><th>代表者・連絡先</th><th>乗船人数</th><th>合計・決済</th><th>状態</th><th>操作</th></tr></thead><tbody>';
    foreach($rows as $r){
        echo '<tr><td><code>'.esc_html(c365_booking_number($r)).'</code><br>'.esc_html(c365_departure_title($r->event_id)).'<br><small>'.esc_html(c365_date('Y-m-d H:i',strtotime($r->departure_at.' UTC'))).'</small></td><td>'.esc_html($r->customer_name).'<br><a href="'.esc_url('mailto:'.$r->email).'">'.esc_html($r->email).'</a><br>'.esc_html($r->phone).'</td><td>'.esc_html($r->passenger_count).'名</td><td>'.esc_html(number_format($r->amount)).'円<br>'.esc_html($r->payment_method==='cash'?'当日現地決済':(c365_is_sandbox_booking($r) ? 'Square Sandbox（テスト）' : 'Square '.$r->square_mode)).($r->cancellation_fee?'<br>取消料：'.esc_html(number_format($r->cancellation_fee)).'円':'').'<br><small>'.esc_html($r->payment_id).'</small></td><td class="vc-status">'.esc_html(c365_status_label($r->status)).'<br><small>'.esc_html($r->refund_status).'</small></td><td><a class="button" href="'.esc_url(admin_url('admin.php?page=cruise-bookings&event='.$r->event_id.'&booking='.$r->id.'#vc-compose')).'">個別メッセージ</a>';
        if($r->status==='reserved'&&$r->payment_method==='cash'){c365_admin_form_start('cash_paid');echo '<input type="hidden" name="booking" value="'.esc_attr($r->id).'">';submit_button('現地入金を記録','small','submit',false);echo '</form>';}
        if(in_array($r->status,array('payment_processing','pending_payment'),true)&&$r->payment_method==='square'){c365_admin_form_start('reconcile');echo '<input type="hidden" name="booking" value="'.esc_attr($r->id).'">';submit_button('Square照合','small','submit',false);echo '</form>';}
        if(in_array($r->status,array('reserved','paid','pending_payment','refund_pending'),true)){
            c365_admin_form_start('cancel');echo '<input type="hidden" name="booking" value="'.esc_attr($r->id).'"><select name="reason"><option value="customer">お客様からの申出</option><option value="no_show">無連絡キャンセル</option><option value="organizer">主催者都合（全額返金）</option></select><br><label>取消連絡の受信日時（日本時間）<input type="datetime-local" name="cancel_received_at"></label><label><input type="checkbox" name="confirm" value="yes" required> 取消・返金条件を確認済み</label>';submit_button('取消・必要な返金','small','submit',false);echo '</form>';
        }
        if (c365_is_sandbox_booking($r)) {
            c365_admin_form_start('delete_sandbox');
            echo '<input type="hidden" name="booking" value="' . esc_attr($r->id) . '">';
            echo '<input type="hidden" name="event" value="' . esc_attr($selected) . '">';
            echo '<label><input type="checkbox" name="confirm" value="yes" required> このテスト予約を削除する</label>';
            submit_button('テスト予約を削除', 'small', 'submit', false);
            echo '</form>';
        }
        echo '</td></tr>';
    }
    if (!$rows) { echo '<tr><td colspan="6">該当する予約はありません。</td></tr>'; }
    echo '</tbody></table></div>';echo wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$page,'total'=>max(1,(int)ceil($total/50)))));
    if($selected){$target=isset($_GET['booking'])?absint($_GET['booking']):0;$r=$target?c365_booking($target):null;if($r&&((int)$r->event_id!==$selected||$r->status==='sandbox_deleted')){$r=null;$target=0;}
        echo '<div class="vc-admin-form" id="vc-compose"><h2>'.($r?esc_html($r->customer_name).'様へ個別送信':'この出航枠の予約代表者へ一斉送信').'</h2><p>メールは代表者ごとに個別配送され、他の予約者のアドレスは公開されません。同行者への共有は代表者にご依頼ください。</p>';c365_admin_form_start('message');echo '<input type="hidden" name="event" value="'.esc_attr($selected).'"><input type="hidden" name="booking" value="'.esc_attr($target).'"><input type="hidden" name="batch" value="'.esc_attr(wp_generate_uuid4()).'"><label>件名<input name="subject" type="text" maxlength="180" required></label><label>本文<textarea name="message" rows="9" maxlength="10000" required></textarea></label><label><input type="checkbox" name="confirm" value="yes" required> 対象者・内容を確認して送信します</label>';submit_button('メール送信キューに登録');echo '</form></div>';
    }echo '</div>';
}
add_action('admin_enqueue_scripts',function(){if(isset($_GET['page'])&&$_GET['page']==='cruise-settings'){wp_enqueue_script('vc-admin-settings',C365_BOOKING_URL.'assets/admin-settings.js',array(),C365_BOOKING_VERSION,true);}});
function c365_admin_settings(){
    c365_admin_header('C365クルーズ 接続・受付設定');$s=c365_settings();
    $mode = c365_square_mode();
    $mode_label = $mode === 'sandbox' ? 'Sandbox（テスト専用）' : 'Production（本番）';
    $issues = c365_square_configuration_issues($mode);
    $square_status = $issues ? '設定を確認してください' : '設定済み（実通信テストは別途必要）';
    echo '<div class="vc-admin-form"><h2>接続状況（設定の有無）</h2><p>Square認証情報（保存済みの環境：'
        . esc_html($mode_label) . '）：' . esc_html($square_status) . '</p>';
    if ($mode === 'sandbox') {
        echo '<p>SandboxではSquareのテストカードを使用します。実際の請求は発生しません。テスト予約はこのサイトの出航枠を使用するので、検証後は予約一覧からテスト予約を削除できます。</p>';
    }
    if ($issues) {
        echo '<ul>';
        foreach ($issues as $issue) { echo '<li>' . esc_html($issue) . '</li>'; }
        echo '</ul>';
    }
    echo '<p>Microsoft 365：'.(c365_mail_ready()?'設定済み（テストメールで確認してください）':'未設定').'</p><p>Sandbox通知URL：<br><code>'.esc_html(rest_url('cruise/v1/square-webhook-sandbox')).'</code><br>Production通知URL：<br><code>'.esc_html(rest_url('cruise/v1/square-webhook')).'</code></p><p>秘密情報は管理画面やリポジトリに保存せず、サーバーの wp-config.php に定数で設定してください。詳しくは付属の導入手順をご覧ください。</p>';
    c365_admin_form_start('settings');echo '<label><input type="checkbox" name="enabled" value="1" '.checked(!empty($s['enabled']),true,false).'> 予約受付を有効にする（接続・法定表記・確認テストの完了後）</label><label>Square環境 <select id="vc-square-mode" name="square_mode" aria-controls="vc-square-sandbox vc-square-production"><option value="sandbox" '.selected(c365_square_mode(),'sandbox',false).'>Sandbox（テスト専用）</option><option value="production" '.selected(c365_square_mode(),'production',false).'>Production（本番）</option></select></label>';
    echo '<p id="vc-square-selection" aria-live="polite">各環境のIDを別々に保存します。</p>';
    foreach(array('sandbox'=>'Sandbox（テスト専用）','production'=>'Production（本番）') as $mode=>$label){
        $config=c365_square_config($mode);
        echo '<fieldset id="vc-square-'.esc_attr($mode).'" data-vc-square-mode="'.esc_attr($mode).'" style="padding:15px;border:1px solid #ccd0d4"><legend>'.esc_html($label).'</legend>';
        foreach(array('app_id'=>'Application ID（公開用）','location_id'=>'Location ID') as $key=>$field){echo '<label>'.esc_html($label.' '.$field).'<input type="text" name="square_'.esc_attr($mode.'_'.$key).'" value="'.esc_attr($config[$key]).'" autocomplete="off"></label>';}
        echo '</fieldset>';
    }
    echo '<p>環境を切り替えて両方のIDを入力し、最後に保存してください。非表示側の入力も保持されます。Access Token・Webhook署名キーは従来どおり環境別のwp-config.php定数を使用します。</p>';
    if(!empty($s['square_app_id'])||!empty($s['square_location_id'])){echo '<details><summary>旧版の共通ID（確認用）</summary><p>Application ID：<code>'.esc_html($s['square_app_id']??'').'</code><br>Location ID：<code>'.esc_html($s['square_location_id']??'').'</code><br>旧版では最後に保存した1組だけが残っています。両環境のIDをSquare管理画面で再確認してください。</p></details>';}
    foreach(array('sender_email'=>'Microsoft 365送信元メールアドレス','from_email'=>'Fromアドレス（任意）','phone'=>'電話番号','responsible_person'=>'運営責任者の氏名') as $key=>$label){echo '<label>'.esc_html($label).'<input type="'.(in_array($key,array('sender_email','from_email'),true)?'email':'text').'" name="'.esc_attr($key).'" value="'.esc_attr($s[$key]??($key==='responsible_person'?'平丸聖弥':'')).'"></label>';}
    echo '<p>Fromは受信者に表示される差出人アドレスです。空欄の場合は送信元メールアドレスのまま送信します。指定したアドレスから送信できるよう、Exchange Online側の権限・設定をご確認ください。</p>';
    echo '<label>お問い合わせ受信先<input type="email" name="contact_email" required value="'.esc_attr(c365_contact_email()).'"></label>';
    echo '<p>キャンセル条件：'.esc_html(c365_policy_text()).'</p>';submit_button('設定を保存');echo '</form></div><div class="vc-admin-form"><h2>Microsoft 365 テストメール</h2>';c365_admin_form_start('test_mail');echo '<p>'.esc_html(c365_contact_email()).' 宛てのテストメールを1通キューへ登録します。</p>';submit_button('テストメールを登録','secondary');echo '</form></div></div>';
}
function c365_admin_mail(){
    global $wpdb;c365_admin_header('C365クルーズ メール送信状況');
    echo '<p>pending＝送信待ち / processing＝処理中 / sent＝Microsoft 365が受理 / failed＝失敗 / uncertain＝結果不明。sentは相手の受信箱への到達を保証しません。uncertainはMicrosoft 365の送信済みフォルダーを確認してから再送してください。</p>';
    c365_admin_form_start('process_mail');submit_button('送信キューを処理（最大10通）','secondary');echo '</form>';
    $page=max(1,isset($_GET['paged'])?absint($_GET['paged']):1);$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.c365_table('mail').' ORDER BY id DESC LIMIT 50 OFFSET %d',($page-1)*50));
    echo '<table class="widefat striped"><thead><tr><th>宛先</th><th>件名・本文</th><th>状態</th><th>操作</th></tr></thead><tbody>';foreach($rows as $r){echo '<tr><td>'.esc_html($r->recipient).'</td><td>'.esc_html($r->subject).'<details><summary>本文</summary><pre style="white-space:pre-wrap">'.esc_html($r->body).'</pre></details></td><td>'.esc_html($r->status).' / 試行'.esc_html($r->attempts).'<br>'.esc_html($r->last_error).'</td><td>';if(in_array($r->status,array('failed','uncertain'),true)){c365_admin_form_start('retry_mail');echo '<input type="hidden" name="mail" value="'.esc_attr($r->id).'"><label><input name="confirm" type="checkbox" value="yes" required>未送信を確認</label>';submit_button('再送登録','small','submit',false);echo '</form>';}echo '</td></tr>';}echo '</tbody></table>'; $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.c365_table('mail'));echo wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%'),'current'=>$page,'total'=>max(1,(int)ceil($total/50)))));echo '</div>';
}
add_action('admin_post_c365_admin','c365_admin_action');
function c365_admin_action(){
    global $wpdb;
    if(!current_user_can('manage_options')){wp_die('権限がありません。',403);}
    $action=isset($_POST['c365_action'])&&is_string($_POST['c365_action'])?sanitize_key($_POST['c365_action']):'';check_admin_referer('c365_admin_'.$action);
    $notice='処理しました。';$event=isset($_POST['event'])?absint($_POST['event']):0;$booking=isset($_POST['booking'])?absint($_POST['booking']):0;$r=$booking?c365_booking($booking):null;
    if($action==='settings'){
        $settings = c365_square_update_fields(c365_settings(), $_POST);
        $from = $_POST['from_email'] ?? '';
        $from = is_string($from) ? trim(wp_unslash($from)) : null;
        if (is_wp_error($settings)) {
            $notice = $settings->get_error_message();
        } elseif ($from === null || ($from !== '' && !is_email($from))) {
            $notice = 'Fromアドレスに有効なメールアドレスを入力するか、空欄にしてください。';
        } else {
            foreach (array('phone', 'responsible_person') as $key) {
                $settings[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            }
            $settings['sender_email'] = sanitize_email(wp_unslash($_POST['sender_email'] ?? ''));
            $settings['contact_email'] = sanitize_email(wp_unslash($_POST['contact_email'] ?? '')) ?: c365_contact_email();
            if (array_key_exists('from_email', $_POST)) { $settings['from_email'] = $from; }
            $settings['square_mode'] = ($_POST['square_mode'] ?? '') === 'production' ? 'production' : 'sandbox';
            $settings['enabled'] = isset($_POST['enabled']) ? 1 : 0;
            update_option('c365_settings', $settings, false);
            $notice = '設定を保存しました。認証情報はテストで確認してください。';
        }
    }elseif($action==='message'){
        if(($_POST['confirm']??'')!=='yes'||!$event){wp_die('送信対象を確認してください。');}
        $subject=sanitize_text_field(wp_unslash($_POST['subject']??''));$body=sanitize_textarea_field(wp_unslash($_POST['message']??''));$batch=sanitize_text_field(wp_unslash($_POST['batch']??''));
        if(!$subject||!$body||!preg_match('/^[a-f0-9-]{36}$/',$batch)){wp_die('件名・本文を確認してください。');}
        if($booking){$recipients=$r&&(int)$r->event_id===$event?array($r):array();}else{$recipients=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.c365_table()." WHERE event_id=%d AND status IN ('reserved','paid')",$event));}
        $count=0;foreach($recipients as $recipient){if(c365_queue_mail($recipient->email,'【C365クルーズ】'.$subject,$body."\n\nC365クルーズ / 株式会社NEXUS\n".c365_contact_email()."\nキャンセルは予約番号・お名前・出航日時を記載してメールでご連絡ください。",'message_'.$event.'_'.$batch,$recipient->id)){$count++;}}
        $notice=$count.'通を送信キューに登録しました。';c365_audit('admin_message_queued',$booking);
    }elseif($action==='test_mail'){c365_queue_mail(c365_contact_email(),'【C365クルーズ】Microsoft 365接続テスト','C365クルーズからのテスト送信です。', 'test_'.wp_generate_uuid4());$notice='テストメールを登録しました。メール送信状況をご確認ください。';
    }elseif($action==='process_mail'){c365_process_mail_queue();$notice=c365_mail_ready()?'送信キューを処理しました。結果を一覧で確認してください。':'Microsoft 365の設定が不足しています。送信していません。';
    }elseif($action==='retry_mail'){
        if(($_POST['confirm']??'')!=='yes'){wp_die('未送信を確認してください。');}$id=absint($_POST['mail']??0);$wpdb->query($wpdb->prepare('UPDATE '.c365_table('mail')." SET status='pending',attempts=0,last_error='',available_at=%s WHERE id=%d AND status IN ('failed','uncertain')",gmdate('Y-m-d H:i:s'),$id));c365_audit('admin_mail_retry');
    } elseif ($action === 'delete_sandbox') {
        if (($_POST['confirm'] ?? '') !== 'yes') { wp_die('削除するテスト予約を確認してください。'); }
        $result = c365_delete_sandbox_booking($booking);
        $notice = is_wp_error($result) ? $result->get_error_message() : 'テスト予約を削除しました。';
    }elseif($r && $r->status !== 'sandbox_deleted'){
        $event=(int)$r->event_id;
        if($action==='reconcile'){$res=c365_square_reconcile($r);$notice=is_wp_error($res)?$res->get_error_message():'Squareの決済状態を確認しました。';}
        elseif(in_array($action,array('cash_paid','cancel'),true)){
            if(!c365_lock()){wp_die('処理が混み合っています。再試行してください。');}
            try{
                $r=c365_booking($r->id);
                if($action==='cash_paid'&&$r->payment_method==='cash'&&$r->status==='reserved'){$wpdb->update(c365_table(),array('status'=>'paid','payment_status'=>'CASH_RECEIVED','updated_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$r->id));c365_audit('cash_received',$r->id);}
                elseif($action==='cancel'&&($_POST['confirm']??'')==='yes'&&in_array($r->status,array('reserved','paid','pending_payment','refund_pending'),true)){
                    $reason = $_POST['reason'] ?? '';
                    if (!in_array($reason, array('customer', 'no_show', 'organizer'), true)) { wp_die('取消理由を確認してください。'); }
                    $organizer = $reason === 'organizer';
                    $received_at = isset($_POST['cancel_received_at']) && is_string($_POST['cancel_received_at']) ? wp_unslash($_POST['cancel_received_at']) : '';
                    $received = $received_at === '' ? new DateTimeImmutable('now', c365_tokyo()) : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $received_at, c365_tokyo());
                    if (!$received || ($received_at !== '' && $received->format('Y-m-d\TH:i') !== $received_at)
                        || $received->getTimestamp() > time() || ($reason === 'no_show' && time() < strtotime($r->departure_at . ' UTC'))) {
                        wp_die('取消連絡の受信日時または出航日時を確認してください。');
                    }
                    $fee = $r->status === 'refund_pending' ? (int) $r->cancellation_fee : c365_cancellation_fee($r, $received, $reason);
                    $refund = (int) $r->amount - $fee;
                    if ($r->payment_method === 'square' && in_array($r->status, array('paid', 'refund_pending'), true) && $refund > 0) {
                        $res = c365_square_refund($r, $fee);
                        $notice = is_wp_error($res) ? $res->get_error_message()
                            : '予約を取り消しました。取消料：' . number_format($fee) . '円。Squareへの返金依頼額：' . number_format($refund) . '円。';
                    } else {
                        $saved = $wpdb->update(c365_table(), array('status'=>'cancelled','cancellation_fee'=>$fee,'updated_at'=>gmdate('Y-m-d H:i:s')), array('id'=>$r->id));
                        if ($saved === false) { wp_die('予約の取消を保存できませんでした。'); }
                        $notice = 'キャンセルしました。キャンセル料：' . number_format($fee) . '円。';
                        if ($fee && $r->status !== 'paid') { $notice .= 'キャンセル料のお支払い方法は運営からご案内します。'; }
                    }
                    c365_audit($organizer?'organizer_cancelled':'customer_cancelled',$r->id);
                    if(!isset($res)||!is_wp_error($res)){c365_queue_mail($r->email,'【C365クルーズ】キャンセルのお手続きについて', $r->customer_name." 様\n予約番号：".c365_booking_number($r)."\nプラン：".c365_departure_title($r->event_id)."\n\n".$notice."\nカードへの返金はカード会社の処理により反映まで日数がかかる場合があります。\n\n".c365_contact_email()." / C365クルーズ",'cancel_'.$r->public_id,$r->id);}
                }else{$notice='現在の予約状態では操作できません。Square処理中の場合は先に照合してください。';}
            }finally{c365_unlock();}
        }
    }
    set_transient('c365_notice_'.get_current_user_id(),$notice,120);
    $page=in_array($action,array('settings','test_mail'),true)?'cruise-settings':(in_array($action,array('process_mail','retry_mail'),true)?'cruise-mail':'cruise-bookings');
    wp_safe_redirect(add_query_arg(array('page'=>$page,'c365_notice'=>1,'event'=>$event),admin_url('admin.php')));exit;
}
