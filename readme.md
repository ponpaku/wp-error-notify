# WP Error Notify

**WordPressのエラーをDiscordやSlackでリアルタイムに通知します。**
Notifies website errors via Discord or Slack.

---
サイトの問題を迅速に把握し対応するため、エラーをDiscordやSlackに通知するプラグインです。

## 主な機能

* PHPエラー（警告、通知、致命的エラー等）および `wp_die` によるWordPressエラーを検知。
* DiscordおよびSlackへの通知に対応。
* 管理画面で通知サービス、エラーレベル、Webhook URL等を設定可能。
* **重要:** `wp-config.php` に定数を設定することで、データベースエラー時も通知を送信（推奨）。
* エラータイプ、メッセージ、発生箇所、サイトURLを含む詳細な通知。
* 短時間での重複エラー通知を抑制。
* 日本語・英語対応。

## 動作環境

* WordPress: 5.0 以上
* PHP: 7.2 以上

## 設定方法

有効化後、管理画面の「設定」>「エラー通知 (Error Notify)」から設定します。

### 1. 基本設定

* **通知サービスを有効化:** DiscordやSlackを選択。
* **通知するエラーレベル:** 通知対象のPHPエラーレベルを選択。
* **各サービス設定:**
    * **Webhook URL (必須):** 通知先のWebhook URL。
    * ユーザー名 (任意)
    * アバターURL / アイコン (任意)

### 2. `wp-config.php` でのフォールバック設定 (推奨)

データベースエラー等で上記設定が読めない場合でも通知を送るため、`wp-config.php` に以下のように定数を定義することをおすすめします。

```php
// Discord用
define('WP_ERROR_NOTIFY_WEBHOOK_URL_DISCORD', 'YOUR_DISCORD_WEBHOOK_URL');
// define('WP_ERROR_NOTIFY_USERNAME_DISCORD', 'BotName'); // 任意
// define('WP_ERROR_NOTIFY_AVATAR_URL_DISCORD', 'BotAvatarURL'); // 任意

// Slack用
define('WP_ERROR_NOTIFY_WEBHOOK_URL_SLACK', 'YOUR_SLACK_WEBHOOK_URL');
// define('WP_ERROR_NOTIFY_USERNAME_SLACK', 'BotName'); // 任意
// define('WP_ERROR_NOTIFY_AVATAR_URL_SLACK', ':icon_emoji: or BotAvatarURL'); // 任意