<?php
/**
 * Plugin Name: WP Error Notify
 * Description: Notifies website errors via Discord or Slack. WordPressのエラーをDiscordやSlackで通知します。
 * Version: 1.0.1
 * Author: Ponpaku
 * Text Domain: wp-error-notify
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// プラグインのバージョン
define( 'WP_ERROR_NOTIFY_VERSION', '1.0.1' );
// プラグインのパス
define( 'WP_ERROR_NOTIFY_PATH', plugin_dir_path( __FILE__ ) );
// プラグインのURL
define( 'WP_ERROR_NOTIFY_URL', plugin_dir_url( __FILE__ ) );
// 設定オプションキー
define( 'WP_ERROR_NOTIFY_SETTINGS_KEY', 'wp_error_notify_settings' );

// wp-config.php で定義する定数のキー
define( 'WP_ERROR_NOTIFY_CONFIG_DISCORD_WEBHOOK_URL', 'WP_ERROR_NOTIFY_WEBHOOK_URL_DISCORD' );
define( 'WP_ERROR_NOTIFY_CONFIG_DISCORD_USERNAME', 'WP_ERROR_NOTIFY_USERNAME_DISCORD' );
define( 'WP_ERROR_NOTIFY_CONFIG_DISCORD_AVATAR_URL', 'WP_ERROR_NOTIFY_AVATAR_URL_DISCORD' );

define( 'WP_ERROR_NOTIFY_CONFIG_SLACK_WEBHOOK_URL', 'WP_ERROR_NOTIFY_WEBHOOK_URL_SLACK' );
define( 'WP_ERROR_NOTIFY_CONFIG_SLACK_USERNAME', 'WP_ERROR_NOTIFY_USERNAME_SLACK' );
define( 'WP_ERROR_NOTIFY_CONFIG_SLACK_AVATAR_URL', 'WP_ERROR_NOTIFY_AVATAR_URL_SLACK' ); // Slackでは通常アイコン絵文字またはアプリ設定

// 必要なファイルを読み込み
require_once WP_ERROR_NOTIFY_PATH . 'includes/class-wp-error-notify-settings.php';
require_once WP_ERROR_NOTIFY_PATH . 'includes/interface-wp-error-notify-sender.php';
require_once WP_ERROR_NOTIFY_PATH . 'includes/class-wp-error-notify-sender-discord.php';
require_once WP_ERROR_NOTIFY_PATH . 'includes/class-wp-error-notify-sender-slack.php';
require_once WP_ERROR_NOTIFY_PATH . 'includes/class-wp-error-notify-handler.php';
require_once WP_ERROR_NOTIFY_PATH . 'includes/class-wp-error-notify-main.php';

if ( is_admin() ) {
	require_once WP_ERROR_NOTIFY_PATH . 'includes/class-wp-error-notify-admin.php';
}

// プラグインの初期化
function wp_error_notify_init() {
	WP_Error_Notify_Main::get_instance();
	if ( is_admin() ) {
		WP_Error_Notify_Admin::get_instance();
	}
}
add_action( 'plugins_loaded', 'wp_error_notify_init', 1);

// 翻訳利用可否を判定するユーティリティ
function wp_error_notify_is_translation_ready(): bool {
    static $ready = null;

    if ( true === $ready ) {
        return true;
    }

    if ( function_exists( 'is_textdomain_loaded' ) && is_textdomain_loaded( 'wp-error-notify' ) ) {
        $ready = true;
        return true;
    }

    return false;
}

// 翻訳テキストを安全に取得するラッパー
function wp_error_notify__( string $text ): string {
    if ( wp_error_notify_is_translation_ready() ) {
        return __( $text, 'wp-error-notify' );
    }

    return $text;
}

// esc_html__ 相当の安全なラッパー
function wp_error_notify_esc_html__( string $text ): string {
    if ( wp_error_notify_is_translation_ready() ) {
        return esc_html__( $text, 'wp-error-notify' );
    }

    return esc_html( $text );
}

// 国際化対応: init 以降でテキストドメインを読み込む
add_action( 'init', function () {
    load_plugin_textdomain(
        'wp-error-notify',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );

    wp_error_notify_is_translation_ready(); // キャッシュ更新目的で呼び出し
}, 0);


// アンインストール時の処理
register_uninstall_hook( __FILE__, 'wp_error_notify_uninstall' );
function wp_error_notify_uninstall() {
	require_once WP_ERROR_NOTIFY_PATH . 'uninstall.php';
	wp_error_notify_execute_uninstall();
}