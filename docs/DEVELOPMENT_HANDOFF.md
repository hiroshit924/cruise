# C365 CRUISE 開発引き継ぎ（2026年11月20日・日本時間）

## Git と再開地点

- リポジトリは `hiroshit924/cruise`。引き継ぎブランチは `handoff/2026-11-20`。
- 本日の最終実装コミットは `13c16a6e8d87279f1e3511c1d256c9c690e1d9fc`。同コミットは `main` に保存済み。このメモは専用ブランチの先頭コミットにあります。正確な先頭コミット ID は、チェックアウト後に `git rev-parse HEAD` で確認できます。
- 本日の履歴は `06194a4`（サイト・予約・安全情報・配布一式）、`b9b4eb9`（使用船舶を計5隻に拡充）、`13c16a6`（Microsoft 365 の From 設定追加）。作業箇所と運用手順は [開発資料](DEVELOPMENT.md) と [README](../README.md) にあります。

## 完了した作業

- VINCROSS をベースに、デイの貸切、愛犬同乗、愛犬同乗ナイトの3プランを掲載。じゃらんの掲載写真、集合場所の店舗写真、Google マップを追加しました。デイは13:15・14:30・15:45・17:00、ナイトは19:00に出航。運航は60分、集合は30分前です。
- 1組2〜5名で、1名でも2名と同じ貸切料金で申し込めます。船1隻を前提に重複枠を排除。出航日の3日前までは無料、2日前40%、前日70%、当日・無連絡100%の取消料を、日本時間の受信日で判定します。事前カード決済は該当額を控除して Square に返金依頼します。
- 登録番号「関東一不 第65号」、株式会社NEXUSの運営情報、使用船舶5隻を掲載。Sea365 は衛星電話なし。WordPress の「使用船舶」から追加・編集でき、「船舶写真を設定」で画像を掲載できます。
- Microsoft 365 の問い合わせ・確認メールに加え、送信元メールボックスとは別の任意の From を管理画面で保存できるようにしました。問い合わせ受信先と送信元の初期値は `info@c365.jp` です。
- 配布版はテーマ **1.0.1**、予約プラグイン **1.0.4**。ZIP は `releases/` にあります。

## 構成と開発環境

WordPress テーマは `cruise-theme/`、予約・管理機能は `cruise-booking/`。予約と送信キューは WordPress の DB、メール送信は WordPress cron と Microsoft Graph、決済は Square Web Payments SDK・Payments API・Webhook を使います。予約情報は Git に入りません。WordPress 管理画面で編集した情報は、その環境の DB とメディアに保存されます。

新しい Codex 環境でリポジトリを開いてから次を実行します。

```sh
git fetch origin
git switch --track origin/handoff/2026-11-20
git rev-parse HEAD
scripts/start-local.sh
```

すでにローカルの引き継ぎブランチがある場合は `git switch handoff/2026-11-20` を使用します。必要なツールは Git、Bash、Docker Engine と Docker Compose v2、curl、Python 3。PHP・WordPress・MariaDB・WP-CLI は Compose で起動します。検証時は WordPress 7.1.5、PHP 8.3、MariaDB 11.4。JavaScript の構文確認には Node.js、配布 ZIP の作り直しには zip/unzip を使います。

ローカルサイトは `http://localhost:8080/`。初回の管理者名は `cruise-admin` で、WP-CLI が生成するパスワードは初回実行の出力にだけ表示されます。本番データや本番の認証情報なしで、プラン3件と船舶5隻を確認できます。Compose の DB は開発専用の名前付きボリュームに保存し、ホストへの DB ポート公開はありません。ホストポートを変更する場合は、初回起動時に `CRUISE_PORT` を指定してください。

### 本番・ステージングの設定

- WordPress の「C365クルーズ予約 → 接続・受付設定」で Square の Application ID・Location ID、それぞれの環境、Microsoft 365 送信元・From・問い合わせ受信先を設定します。
- Square Developer Console の Webhooks → Subscriptions では、WordPress が表示した通知 URL を、クエリ文字列も含めてそのまま環境別に使用します。API Version は `2025-01-23`。Events は `payment.created`、`payment.updated`、`refund.created`、`refund.updated`。Sandbox のカード入力は `WP_ENVIRONMENT_TYPE` が `staging` または `local` の環境で確認します。
- Square の `C365_SQUARE_SANDBOX_TOKEN`、`C365_SQUARE_SANDBOX_WEBHOOK_KEY`、`C365_SQUARE_PRODUCTION_TOKEN`、`C365_SQUARE_PRODUCTION_WEBHOOK_KEY` は、使う環境の2つをサーバーの `wp-config.php` に設定します。
- Microsoft 365 の `C365_M365_TENANT_ID`、`C365_M365_CLIENT_ID`、`C365_M365_CLIENT_SECRET` も、サーバーの `wp-config.php` に設定します。シークレットは Microsoft Entra の「値」を使用します。Graph の送信権限と、任意の From を使用する場合はそのアドレスからの送信権限を確認します。
- 認証情報を含む `wp-config.php`、パスワード、DB・顧客データのバックアップは運用環境で管理します。このメモには設定名のみを記載しています。

## 発見した不具合と現在の状態

- 本番のトップでプランが空だった時点は、公開済みプランが0件でした。その後、公開サイトに3件が表示され、現行の詳細リンクとじゃらんへの案内が開くことを確認済みです。配布 ZIP を入れた直後に同じ状態の場合は、「3プランと固定ページを登録」を実行します。
- 本番の `/plans/private-cruise/`、`/contact/`、`/safety-information/`、`/wp-json/` は確認時に HTTP 404。一方、実際に出力されるクエリ形式のプラン詳細と REST API は応答しました。WordPress のパーマリンク設定とサーバーの書き換え設定を確認する必要があります。
- 本番で使用中の予約プラグインのバージョン、Square・Microsoft 365 の設定値や稼働状態は管理画面で未確認です。Codex からの本番管理 URL へのアクセスは HTTP 403 でした。

## 検証と未確認項目

- PHP・JavaScript・起動スクリプトの構文、テーマ・プラグイン ZIP とソースの一致を確認しました。
- 新規 WordPress で配布 ZIP を導入し、管理画面から初期登録すると3プランが表示され、詳細を開けることを確認しました。使用船舶5隻、既存の Sea365 の保持、船舶写真の表示、初期登録の再実行に伴う重複防止も確認しました。
- ローカルで、1名と2名の同額料金、最大5名、予約枠競合の排除、Webhook の署名照合と再送、キャンセル料、部分返金、問い合わせキューを模擬検証しました。From の保存・消去、不正値の拒否と、Graph のリクエスト内容も確認済みです。
- Square Sandbox の実決済・Webhook 到達・実返金、Microsoft Graph の実送信・受信者に表示される From、本番の設定や実取引は未検証です。

## 次回最初に取り組むこと

1. 本番 WordPress の「設定 → パーマリンク」で現在の設定を確認します。投稿名形式を保存してサーバー側の URL 書き換えを確認し、プラン詳細・問い合わせ・安全情報・REST が開くことを検証します。Square の購読 URL が変わった場合は、新しい URL を Square に反映し、署名検証を再確認します。
2. 本番プラグインが 1.0.4 か確認し、安全情報に5隻が表示されることを確認します。ステージングで Square Sandbox の決済・返金、Microsoft 365 の確認メールと From を実際に検証します。
3. 通常貸切の直販料金と各プランの販売曜日・期間を確定するまでは、直販予約をオフにします。じゃらんの通常貸切は料金欄に15,000円〜、本文に18,000円の記載があります。安全情報は最新年度末の値と船客傷害賠償責任保険の契約内容を確認し、実船の写真を入手したら WordPress から掲載します。
