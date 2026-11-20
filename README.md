# C365 CRUISE

横浜・みなとみらいで提供する貸切クルーズの WordPress サイトです。VINCROSS のネイビーとオレンジを基調としたテーマ、Square 事前カード決済、現地決済、Microsoft 365 の予約確認・問い合わせ送信キューを継承しています。クルーズ用の予約データと設定は独立しています。

公開先は [cruise.c365.jp](https://cruise.c365.jp/) です。

## WordPress への導入

WordPress 6.5 以上、PHP 7.4 以上、MySQL または MariaDB を使用します。公開環境には HTTPS と WordPress cron が必要です。

1. [テーマ ZIP](releases/c365-cruise-theme.zip) を「外観 → テーマ」からアップロードして有効化します。
2. [予約プラグイン ZIP](releases/c365-cruise-booking.zip) を「プラグイン」からアップロードして有効化します。
3. 「設定 → 一般」でサイトタイトルを設定し、サイト URL が `https://cruise.c365.jp` であることを確認します。その後、タイムゾーンを「東京」にします。「設定 → パーマリンク」で「投稿名」を保存します。
4. 新規サイトで初期コンテンツが必要な場合は、WordPress の設置先で `wp eval 'c365_import_launch_events(); c365_import_pages();'` を実行します。「クルーズプラン」で本文・料金・画像・予約方法・じゃらん掲載URLを編集できます。「外観 → カスタマイズ」でロゴとトップの写真を変更できます。

VINCROSS の既存イベントと固定ページを保持するため、クルーズ専用の WordPress インストールを使用してください。公開済みの固定ページと同じ URL がある場合、初期登録は既存ページを優先します。

## 事業登録・安全情報

初期コンテンツ登録で、固定ページ「一般不定期航路事業の登録・安全情報」（`/safety-information/`）と使用船舶5隻（Sea365、ブルーコーナーⅡ、ブルーコーナーⅢ、Slow Knots、スラブ255WAKE）が作成されます。初期コンテンツ登録を再実行する場合は、WordPress の設置先で `wp eval 'c365_import_launch_events(); c365_import_pages();'` を実行してください。登録済み船舶の情報や写真は保持されます。公開済みのページにはフッターからアクセスできます。事業情報は「固定ページ」で編集します。

船舶は「使用船舶 → 使用船舶を追加」で登録します。船名と船舶情報を入力し、編集画面の「船舶写真を設定」でメディアライブラリから画像を選択して公開します。公開した船舶だけが安全情報ページに表示されます。Sea365 の写真を掲載するには、同じ手順で実船の写真を設定してください。

関東運輸局の登録簿に記載された番号は「関東一不 第65号」、登録日は2025年9月3日です。公開前に、最新事業年度末時点の安全情報、船客傷害賠償責任保険の契約内容を確認し、ページを更新してください。安全情報は毎事業年度終了後100日以内に公表し、公表後は関東運輸局へ遅滞なく報告します。［[登録簿](https://wwwtb.mlit.go.jp/kanto/content/000380743.pdf)・[国土交通省の公表・報告案内](https://www.mlit.go.jp/maritime/maritime_fr6_000060.html)］

## 予約の公開

初期状態は自社サイトの直接予約をオフにし、各プランからじゃらんの掲載ページへ案内します。「クルーズプラン → プラン料金・予約方法・じゃらんURL」で、プランごとに日本語版の予約方法を自社予約またはじゃらんに指定できます。英語版は英語のプラン名・紹介を表示し、自社サイトで予約を受け付けます。

- 通常貸切は、じゃらんの料金欄の **15,000円〜** を参考表示しています。本文には **18,000円** の記載もあるため、自社サイトの販売額を確定してから設定してください。ドッグクルーズ2種のじゃらん掲載料金は **18,000円〜** です。
- 「出航枠 → 出航枠を追加」で開始日・終了日と対象プラン（全プラン共通または個別）を指定して公開します。時間はプランの所定の出航時間帯から自動設定されます。すべて日本時間です。出航30分前に受付を締め切り、運航は60分です。同じ船を前提に、他プランと時間が重なる場合も、同時に成立する予約は1組までです。じゃらんとの在庫連携はないため、直販枠はじゃらんの販売枠と運用上分けてください。
- 乗船は1組2〜5名。1名で利用する場合は2名分の料金です。固定の貸切料金なので1名と2名の合計額は同じです。
- デイの通常出航時刻は13:15・14:30・15:45・17:00、ナイトは19:00です。プラン詳細に30分前の集合時刻と60分の体験時間を表示します。販売する日付は管理画面で登録してください。
- 直販のキャンセル料は出航日の3日前まで無料、2日前40%、前日70%、当日・無連絡100%です（日本時間の暦日で判定）。主催者都合の中止は全額返金です。Square で事前決済した予約は、管理画面で取消を実行するとキャンセル料を差し引いて返金します。未払いのキャンセル料は運営から個別にご案内します。
- 事業者情報の初期値は株式会社NEXUS。問い合わせ受信先と Microsoft 365 の送信元は、いずれも `info@c365.jp` を使用します。送信元メールボックスに Microsoft Graph の送信権限を設定してください。

### Square・Microsoft 365

「接続・受付設定」で Square の Application ID・Location ID を環境別に入力します。管理画面に表示された Webhook URL を Square に登録し、`payment.created`、`payment.updated`、`refund.created`、`refund.updated` を購読します。公開先の Production 用 URL は `https://cruise.c365.jp/wp-json/cruise/v1/square-webhook` です。ステージング側の Sandbox は、そのステージング環境の管理画面に表示された `square-webhook-sandbox` の URL を使います。

| 用途 | `wp-config.php` の定数 |
|---|---|
| Square Sandbox | `C365_SQUARE_SANDBOX_TOKEN`、`C365_SQUARE_SANDBOX_WEBHOOK_KEY` |
| Square Production | `C365_SQUARE_PRODUCTION_TOKEN`、`C365_SQUARE_PRODUCTION_WEBHOOK_KEY` |
| Microsoft 365 | `C365_M365_TENANT_ID`、`C365_M365_CLIENT_ID`、`C365_M365_CLIENT_SECRET` |

WordPress を `staging` に設定したステージング環境で Square Sandbox の決済・Webhook・返金を確認します。Microsoft 365 は VINCROSS と同様に Graph の `Mail.Send` を持つアプリを構成し、実際に利用するメールボックスへ送信権限を付与します。「Microsoft 365 テストメール」で実際の受信箱まで確認してください。送信キューの処理には WordPress cron を使用します。

送信元メールボックスとは別に受信者へ表示するアドレスを指定するには、「接続・受付設定」の「Fromアドレス（任意）」に入力します。空欄なら送信元のアドレスを使います。別アドレスを指定する場合は、Exchange Online側でそのアドレスからの送信が許可されていることを確認し、テストメールの受信時に実際のFromを確認してください。 [Microsoft Graph の差出人設定](https://learn.microsoft.com/ja-jp/graph/outlook-send-mail-from-other-user)

公開先では Square を Production に切り替え、予約・メールを確認したうえで「予約受付を有効にする」を選びます。キャッシュを使用する場合は `/plans/*`、`/contact/`、`/wp-json/cruise/v1/*` を対象外にします。

## ローカル開発

`scripts/start-local.sh` で Docker Compose の WordPress と MariaDB を起動し、プラン3件を登録します。URL は `http://localhost:8080/`。初回の管理者パスワードは WP-CLI が生成します。既存の環境のポートを変える場合は、WordPress の `home` と `siteurl` も変更してください。公開用 ZIP にはローカル環境とDBを含めません。

実装の構成、検証、ZIP の作成手順、公開前の確認事項は [開発引き継ぎメモ](docs/DEVELOPMENT.md) に記載しています。

## 参考掲載・写真

- [みなとみらい貸切クルーズ](https://www.jalan.net/kankou/spt_guide000000226734/activity/l00006D5C4/)
- [愛犬と貸切ドッグクルーズ](https://www.jalan.net/kankou/spt_guide000000226734/activity/l00006D5C3/)
- [愛犬と貸切ナイトクルーズ](https://www.jalan.net/kankou/spt_guide000000226734/activity/l00006D5C5/)

テーマ・予約プラグインのベースは [VINCROSS](https://github.com/hiroshit924/vincross/tree/671f3cdc92bc639af080e2da643907fd71a162ac) です。
