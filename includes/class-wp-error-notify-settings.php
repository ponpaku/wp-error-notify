<?php
// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Error Notify プラグインの設定値管理クラス。
 * DBオプションまたは wp-config.php の定数から設定値を取得する。
 */
class WP_Error_Notify_Settings {

	private $options; // DBから読み込んだ設定オプション
	private $db_accessible = true; // DBアクセス可否フラグ

	/**
	 * コンストラクタ。
	 * DBからオプションを読み込む。DBエラー時はフォールバック処理を get_setting 等で行う。
	 */
	public function __construct() {
		// DBからオプション読み込み試行。
		// get_option はDBエラー時 false を返すか、期待通り動作しない可能性あり。
		// そのため、ここでは $this->db_accessible は true のままにし、
		// 値取得メソッド内でフォールバックを考慮する。
		$this->options = get_option( WP_ERROR_NOTIFY_SETTINGS_KEY );
	}

	/**
	 * 指定キーの設定値を取得する。
	 * DBから取得不可時は wp-config.php の定数をフォールバックとして使用。
	 *
	 * @param string $service サービス名 (discord, slack 等)
	 * @param string $key     設定キー (webhook_url, username, avatar_url)
	 * @return mixed 設定値。存在しない場合は null。
	 */
	public function get_setting( $service, $key ) {
		$option_key = "{$service}_{$key}"; // オプション配列内のキー (例: discord_webhook_url)

		// 1. DBオプションからの取得試行 (DBがアクセス可能な場合)
		if ( $this->db_accessible && isset( $this->options[$option_key] ) && ! empty( $this->options[$option_key] ) ) {
			return $this->options[$option_key];
		}

		// 2. wp-config.php 定数からの取得試行
		// メインプラグインファイルで定義された定数名形式 (WP_ERROR_NOTIFY_CONFIG_SERVICE_KEY) に基づき検索。
		// (旧形式の WP_ERROR_NOTIFY_KEY_SERVICE は廃止)
		$config_const_key = '';
		switch(strtoupper($service)) {
			case 'DISCORD':
				if (strtoupper($key) === 'WEBHOOK_URL') $config_const_key = WP_ERROR_NOTIFY_CONFIG_DISCORD_WEBHOOK_URL;
				if (strtoupper($key) === 'USERNAME')    $config_const_key = WP_ERROR_NOTIFY_CONFIG_DISCORD_USERNAME;
				if (strtoupper($key) === 'AVATAR_URL')  $config_const_key = WP_ERROR_NOTIFY_CONFIG_DISCORD_AVATAR_URL;
				break;
			case 'SLACK':
				if (strtoupper($key) === 'WEBHOOK_URL') $config_const_key = WP_ERROR_NOTIFY_CONFIG_SLACK_WEBHOOK_URL;
				if (strtoupper($key) === 'USERNAME')    $config_const_key = WP_ERROR_NOTIFY_CONFIG_SLACK_USERNAME;
				if (strtoupper($key) === 'AVATAR_URL')  $config_const_key = WP_ERROR_NOTIFY_CONFIG_SLACK_AVATAR_URL;
				break;
		}

		if ( !empty($config_const_key) && defined( $config_const_key ) ) {
			return constant( $config_const_key );
		}

		return null; // DBにも定数にも該当設定なし
	}

	/**
	 * 通知が有効なサービス（Webhook URLが設定されているサービス）のリストを取得する。
	 * @return array 有効なサービスのスラッグ配列 (例: ['discord', 'slack'])
	 */
	public function get_enabled_services() {
		$enabled_db_services = [];
		// DBオプションから有効なサービスを取得
		if ( $this->db_accessible && isset( $this->options['enabled_services'] ) && is_array( $this->options['enabled_services'] ) ) {
			$enabled_db_services = $this->options['enabled_services'];
		}

		// wp-config.php の定数でWebhook URLが設定されていれば、そのサービスも有効とみなす
		$config_services = [];
		if ( $this->get_setting('discord', 'webhook_url') ) {
			$config_services[] = 'discord';
		}
		if ( $this->get_setting('slack', 'webhook_url') ) {
			$config_services[] = 'slack';
		}

		// DB設定とwp-config設定をマージし、重複削除して返す
		// DBで明示的に有効化され、かつWebhookが設定されているものを優先する
		$final_enabled_services = [];
		foreach ($enabled_db_services as $service_slug) {
			if ($this->get_setting($service_slug, 'webhook_url')) {
				$final_enabled_services[] = $service_slug;
			}
		}
		// wp-configのみで設定されているサービスを追加
		foreach ($config_services as $service_slug) {
			if (!in_array($service_slug, $final_enabled_services, true) && $this->get_setting($service_slug, 'webhook_url')) {
				$final_enabled_services[] = $service_slug;
			}
		}
		return array_unique($final_enabled_services);
	}

	/**
	 * 通知対象のエラーレベル定数の配列を取得する。
	 * DB設定がない場合は全エラーレベルをデフォルトとする。
	 * @return array 通知対象エラーレベル定数 (int) の配列
	 */
	public function get_error_levels() {
		// DBオプションから取得試行
		if ( $this->db_accessible && isset( $this->options['error_levels'] ) && is_array( $this->options['error_levels'] ) ) {
			// 保存値は数値文字列の可能性あるため整数変換
			return array_map('intval', $this->options['error_levels']);
		}
		// DB設定がない場合、全エラーレベルをデフォルトとする
		// wp-config.phpでのエラーレベル設定機能は実装しない
		return array_keys(self::get_all_error_levels());
	}

	/**
	 * 利用可能な全PHPエラーレベルとその説明(翻訳対応)のリストを取得する(静的メソッド)。
	 * @return array エラーレベルコード => 説明文字列 の連想配列
	 */
	public static function get_all_error_levels() {
		return [
			E_ERROR             => wp_error_notify__( 'Fatal run-time errors. (E_ERROR)' ),
			E_WARNING           => wp_error_notify__( 'Run-time warnings (non-fatal errors). (E_WARNING)' ),
			E_PARSE             => wp_error_notify__( 'Compile-time parse errors. (E_PARSE)' ),
			E_NOTICE            => wp_error_notify__( 'Run-time notices. (E_NOTICE)' ),
			E_CORE_ERROR        => wp_error_notify__( 'Fatal errors that occur during PHP\'s initial startup. (E_CORE_ERROR)' ),
			E_CORE_WARNING      => wp_error_notify__( 'Warnings (non-fatal errors) that occur during PHP\'s initial startup. (E_CORE_WARNING)' ),
			E_COMPILE_ERROR     => wp_error_notify__( 'Fatal compile-time errors. (E_COMPILE_ERROR)' ),
			E_COMPILE_WARNING   => wp_error_notify__( 'Compile-time warnings (non-fatal errors). (E_COMPILE_WARNING)' ),
			E_USER_ERROR        => wp_error_notify__( 'User-generated error message. (E_USER_ERROR)' ),
			E_USER_WARNING      => wp_error_notify__( 'User-generated warning message. (E_USER_WARNING)' ),
			E_USER_NOTICE       => wp_error_notify__( 'User-generated notice message. (E_USER_NOTICE)' ),
			E_STRICT            => wp_error_notify__( 'Enable to have PHP suggest changes to your code which will ensure the best interoperability and forward compatibility of your code. (E_STRICT)' ),
			E_RECOVERABLE_ERROR => wp_error_notify__( 'Catchable fatal error. (E_RECOVERABLE_ERROR)' ),
			E_DEPRECATED        => wp_error_notify__( 'Run-time notices. Enable to receive warnings about code that will not work in future versions. (E_DEPRECATED)' ),
			E_USER_DEPRECATED   => wp_error_notify__( 'User-generated warning message. (E_USER_DEPRECATED)' ),
		];
	}

	/**
	 * エラータイプコードを人間可読な文字列(翻訳対応)に変換する。
	 * @param int $type エラータイプコード (PHP定数 E_* )
	 * @return string エラータイプの名称文字列
	 */
	public function get_error_type_name( int $type ): string {
		$levels = self::get_all_error_levels();
		return isset( $levels[$type] ) ? $levels[$type] : sprintf( wp_error_notify__( 'Unknown error type (%d)' ), $type );
	}

	/**
	 * DBアクセスが失敗したとマークする。
	 * wp_dieハンドラ等でDBエラー発生時に呼び出されることを想定。
	 * これにより、以降の設定値取得はwp-config.phpの定数を優先する。
	 */
	public function mark_db_inaccessible() {
		$this->db_accessible = false;
		// DBオプション再読み込みを試みず、wp-config.phpの値を確実に優先させるため、
		// メモリ上の$this->optionsをクリアする。
		$this->options = [];
	}

	/**
	 * 現時点でDBがアクセス可能と見なされているか確認する。
	 * 実際のDB接続状態を保証するものではなく、あくまでフラグと簡易チェックに基づく。
	 * @return bool DBアクセス可能と見なされていればtrue
	 */
	public function is_db_accessible(): bool {
		// 既にDBアクセス不可とマークされていればfalse
		if (!$this->db_accessible) {
			return false;
		}

		// $wpdbグローバル変数の状態による簡易チェック
		// 注意: エラーハンドリング中の呼び出しを考慮し、DBクエリ発行等の重い処理は避ける。
		global $wpdb;
		if ( ! is_object($wpdb) || empty( $wpdb->dbh ) || ! empty( $wpdb->error ) || ( function_exists('mysqli_connect_errno') && mysqli_connect_errno() ) ) {
			// $wpdbが無効、またはエラー状態を示している場合はDBアクセス不可と判断
			$this->mark_db_inaccessible(); // 状態を更新
			return false;
		}
		return $this->db_accessible; // 通常はtrue
	}
}