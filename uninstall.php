<?php
// 直接アクセスされた場合は何もしない
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 設定オプションキー (メインプラグインファイルと合わせる)
define( 'WP_ERROR_NOTIFY_SETTINGS_KEY_UNINSTALL', 'wp_error_notify_settings' );

/**
 * アンインストール処理を実行します。
 */
function wp_error_notify_execute_uninstall() {
	// データベースから設定を削除
	delete_option( WP_ERROR_NOTIFY_SETTINGS_KEY_UNINSTALL );

	// その他、追加したカスタムテーブルや一時データなどがあればここで削除
}

// アンインストール処理の呼び出し (uninstall.php が直接呼び出された場合のフォールバック)
// ただし、 register_uninstall_hook を使っていれば通常はこちらは不要。
// wp_error_notify_execute_uninstall();