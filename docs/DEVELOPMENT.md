# C365 CRUISE 開発引き継ぎ

## 開発を再開する

リポジトリのルートで `scripts/start-local.sh` を実行します。Docker Compose の WordPress・MariaDB が起動し、3プラン、固定ページ、使用船舶5隻が登録されます。初回生成の管理者パスワードはコマンドの出力に表示されます。ローカルサイトは `http://localhost:8080/`、WordPress 管理画面は `/wp-admin/` です。

ローカルのデータは Docker の名前付きボリュームに保存されます。コード変更はバインドマウント経由で反映されます。管理画面で編集した内容は Git のソースに自動では反映されません。初期コンテンツ登録を再実行しても既存プラン・固定ページ・船舶の入力値を保持するため、既存環境のコンテンツ変更は WordPress 側でも適用してください。

## 変更箇所

| 変更内容 | 主なファイル |
| --- | --- |
| トップ、集合場所の地図と店舗写真 | `cruise-theme/front-page.php`、`cruise-theme/style.css` |
| プラン詳細、デイ・ナイトの出航案内 | `cruise-theme/single-c365_plan.php`、`cruise-booking/includes/events.php` |
| 新規サイトのプラン名、本文、初期料金 | `cruise-booking/includes/seed.php` |
| 予約 API、貸切料金、重複枠の排除、キャンセル料 | `cruise-booking/includes/api.php`、`cruise-booking/includes/store.php` |
| Square 決済・返金・署名付き Webhook | `cruise-booking/includes/square.php` |
| Microsoft 365 の確認メール・問い合わせキュー | `cruise-booking/includes/mail.php`、`cruise-booking/includes/forms.php` |
| 事業登録・安全情報、船舶と写真 | `cruise-booking/includes/safety.php`、`cruise-theme/footer.php` |

WordPress の「クルーズプラン」で内容・価格、「出航枠」で実際の販売日時、「使用船舶」で船舶情報・写真を管理します。船舶の写真には WordPress のアイキャッチ画像を使います。登録・安全情報は固定ページ `/safety-information/` で編集できます。

予約は船1隻を前提にしています。別プランでも航行時間帯が重なる枠を同時に確定できないよう、DB のロックと予約済み時間帯を照合します。料金は貸切1組分。1名利用でも2名利用と同額、最大5名です。取消料は日本時間の暦日で判定し、予約時点の規定を保存します。Square 返金は取消料を控除した額を記録してから依頼し、同じ依頼を再試行しても返金額を変更しません。

## 検証と配布

ローカル WordPress 7.1.5・PHP 8.3・MariaDB 11.4 で、3プランとじゃらんへのリンク、PC・スマートフォンでの集合場所の地図と写真、新規の使用船舶と写真の表示を確認済みです。予約は競合する別プランへの並行リクエストを含めて動作確認し、決済・返金・Webhook・Microsoft 365 送信は外部応答を模擬して確認しました。

変更後は以下を実行します。

```sh
scripts/start-local.sh
docker compose exec -T wordpress sh -c 'set -e; for f in wp-content/themes/cruise-theme/*.php wp-content/plugins/cruise-booking/*.php wp-content/plugins/cruise-booking/includes/*.php; do php -l "$f"; done'
node --check cruise-theme/assets/main.js
node --check cruise-booking/assets/forms.js
curl -fsS http://localhost:8080/
curl -fsS http://localhost:8080/safety-information/
```

起動スクリプトで初期コンテンツ登録を繰り返しても重複がないことを確認します。フォーム変更時は予約・問い合わせの送信まで確認します。 From設定を変更した場合は、Graphの呼び出し先が送信元メールボックスのまま、指定値がメッセージのFromとして渡されることを確認します。決済変更時は Square Sandbox で決済成功・失敗、署名付き Webhook、全額および一部返金、再試行を確認します。

配布する場合は、変更したテーマの `style.css` またはプラグインの `cruise-booking.php` のバージョンを確認し、リポジトリルートから ZIP を作り直します。

```sh
package_dir=$(mktemp -d)
zip -q -r "$package_dir/c365-cruise-theme.zip" cruise-theme
zip -q -r "$package_dir/c365-cruise-booking.zip" cruise-booking
unzip -tq "$package_dir/c365-cruise-theme.zip"
unzip -tq "$package_dir/c365-cruise-booking.zip"
mv "$package_dir/"*.zip releases/
rmdir "$package_dir"
```

配布 ZIP にはテーマまたはプラグインのディレクトリを1つだけ含めます。WordPress のデータベース、管理者パスワード、顧客データ、Square・Microsoft 365 の認証情報は運用環境で管理します。

## 公開前に残っている項目

- 公開予定は `https://cruise.c365.jp`。開発中のアクセス確認では HTTP 503。本番への導入、決済およびメールの実接続は未実施です。
- 通常貸切の直販料金と、各プランの販売曜日・期間を確定します。通常貸切のじゃらん表示は15,000円〜、本文は18,000円です。確定後に実日付の出航枠を作成します。現在は直接予約を停止し、じゃらんに案内しています。じゃらんと在庫は自動連携しません。
- `info@c365.jp` を Microsoft 365 の送信元・問い合わせ受信先に使います。Graph の送信権限と実際の受信箱への到達を確認し、Square の本番アプリと署名付き Webhook を接続します。
- 安全情報は最新事業年度末の値と船客傷害賠償責任保険の契約内容を確認して掲載します。初期登録の使用船舶は Sea365、ブルーコーナーⅡ、ブルーコーナーⅢ、Slow Knots、スラブ255WAKE です。船舶写真は未登録です。通信設備は携帯電話1台、衛星電話なし、業務用無線設備なし。登録番号は関東一不 第65号です。 [関東運輸局の登録簿](https://wwwtb.mlit.go.jp/kanto/content/000380743.pdf)・[国土交通省の公表・報告案内](https://www.mlit.go.jp/maritime/maritime_fr6_000060.html)
