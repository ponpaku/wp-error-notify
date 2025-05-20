<?php
// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Error Notify プラグインの管理画面設定を管理するクラス。
 */
class WP_Error_Notify_Admin {

	private static $instance; // シングルトンインスタンス
	// private $settings_api; // Settings APIラッパー用 (現在は未使用)
	private $settings;       // WP_Error_Notify_Settings インスタンス

	/**
	 * シングルトンインスタンス取得。
	 * @return WP_Error_Notify_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ。
	 * WordPressのアクションフックに関数を登録する。
	 */
	private function __construct() {
		$this->settings = new WP_Error_Notify_Settings(); // 設定読み込み/アクセス用
		add_action( 'admin_menu', [ $this, 'admin_menu' ] ); // 管理メニュー追加
		add_action( 'admin_init', [ $this, 'admin_init' ] ); // 設定初期化
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] ); // CSS/JS読み込み
	}

	/**
	 * 管理画面用スクリプト・スタイルをエンキューする。
	 * @param string $hook_suffix 現在の管理画面ページのフックサフィックス
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		// 本プラグイン設定ページ以外では読み込まない
		if ( 'settings_page_wp-error-notify' !== $hook_suffix ) {
			return;
		}
		// 必要であればCSSやJSをここでエンキューする
		// wp_enqueue_style( 'wp-error-notify-admin', WP_ERROR_NOTIFY_URL . 'admin/css/admin-style.css', [], WP_ERROR_NOTIFY_VERSION );
	}


	/**
	 * WordPress管理メニューにプラグイン設定ページを追加する。
	 */
	public function admin_menu() {
		add_options_page(
			__( 'Error Notify Settings', 'wp-error-notify' ), // ページタイトル
			__( 'Error Notify', 'wp-error-notify' ),          // メニュータイトル
			'manage_options',                                 // 表示・操作に必要な権限
			'wp-error-notify',                                // メニュースラッグ (URL)
			[ $this, 'settings_page_content' ]                // 設定ページ表示用コールバック関数
		);
	}

	/**
	 * WordPress設定APIを使用して設定項目を登録・初期化する。
	 */
	public function admin_init() {
		// 設定オプションを登録
		register_setting(
			'wp_error_notify_options_group', // オプショングループ名
			WP_ERROR_NOTIFY_SETTINGS_KEY,    // オプション名 (DB保存時のキー)
			[ $this, 'sanitize_settings' ]   // 保存時のサニタイズ処理コールバック
		);

		// --- 全般設定セクション ---
		add_settings_section(
			'wp_error_notify_general_section',            // セクションID
			__( 'General Settings', 'wp-error-notify' ),  // セクションタイトル
			[ $this, 'general_section_callback' ],        // セクション説明表示用コールバック
			'wp-error-notify'                             // 表示するページのスラッグ
		);

		// 有効化サービス選択フィールド
		add_settings_field(
			'enabled_services',                                       // フィールドID
			__( 'Enable Notification Services', 'wp-error-notify' ),  // フィールドラベル
			[ $this, 'enabled_services_render' ],                     // フィールド表示用コールバック
			'wp-error-notify',                                        // 表示ページスラッグ
			'wp_error_notify_general_section'                         // 所属セクションID
		);

		// 通知エラーレベル選択フィールド
		add_settings_field(
			'error_levels',                                           // フィールドID
			__( 'Notify Error Levels', 'wp-error-notify' ),           // フィールドラベル
			[ $this, 'error_levels_render' ],                         // フィールド表示用コールバック
			'wp-error-notify',                                        // 表示ページスラッグ
			'wp_error_notify_general_section'                         // 所属セクションID
		);


		// --- Discord設定セクション ---
		add_settings_section(
			'wp_error_notify_discord_section',                   // セクションID
			__( 'Discord Settings', 'wp-error-notify' ),         // セクションタイトル
			[ $this, 'service_section_callback' ],               // セクション説明表示用コールバック
			'wp-error-notify'                                    // 表示ページスラッグ
		);
		// Discord用設定フィールド追加
		$this->add_service_settings_fields( 'discord', 'wp_error_notify_discord_section' );


		// --- Slack設定セクション ---
		add_settings_section(
			'wp_error_notify_slack_section',                     // セクションID
			__( 'Slack Settings', 'wp-error-notify' ),           // セクションタイトル
			[ $this, 'service_section_callback' ],               // セクション説明表示用コールバック
			'wp-error-notify'                                    // 表示ページスラッグ
		);
		// Slack用設定フィールド追加
		$this->add_service_settings_fields( 'slack', 'wp_error_notify_slack_section' );

	}

	/**
	 * 各サービス (Discord, Slack) の設定フィールドを動的に追加する。
	 * @param string $service_slug サービスのスラッグ (discord, slack)
	 * @param string $section_id   所属させるセクションのID
	 */
	private function add_service_settings_fields( string $service_slug, string $section_id ) {
		$service_name = ucfirst( $service_slug ); // 表示用サービス名 (Discord, Slack)

		// Webhook URL フィールド
		add_settings_field(
			"{$service_slug}_webhook_url", // フィールドID (例: discord_webhook_url)
			sprintf( __( '%s Webhook URL', 'wp-error-notify' ), $service_name ),
			[ $this, 'webhook_url_render' ],
			'wp-error-notify',
			$section_id,
			[ 'service' => $service_slug ] // コールバック関数に渡す引数
		);
		// ユーザー名フィールド
		add_settings_field(
			"{$service_slug}_username",
			sprintf( __( '%s Username (Optional)', 'wp-error-notify' ), $service_name ),
			[ $this, 'username_render' ],
			'wp-error-notify',
			$section_id,
			[ 'service' => $service_slug ]
		);
		// アバターURL/アイコンフィールド
		add_settings_field(
			"{$service_slug}_avatar_url",
			sprintf( __( '%s Avatar URL / Icon (Optional)', 'wp-error-notify' ), $service_name ),
			[ $this, 'avatar_url_render' ],
			'wp-error-notify',
			$section_id,
			[ 'service' => $service_slug ]
		);
	}


	/**
	 * 一般設定セクションの導入部分を表示する。
	 * wp-config.php での定数設定に関する重要な注意書きを含む。
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure general settings for error notifications.', 'wp-error-notify' ) . '</p>';
		// DBエラー時用wp-config.php設定推奨メッセージ
		echo '<p style="color:red; font-weight:bold;">' .
			 wp_kses_post(
				 __( 'The Webhook URLs and other settings below are saved in the database. Therefore, errors that occur when the database is unavailable (e.g., database connection errors) may not be notified using these settings.', 'wp-error-notify') .
				 '<br>' .
				 __( 'If you want to send notifications even for database errors, please add the following constants to your <code>wp-config.php</code> file:', 'wp-error-notify')
			 ) .
			 '</p>';
		// Discord用定数例
		echo '<p><strong>' . esc_html__('For Discord:', 'wp-error-notify') . '</strong><br>' .
			 '<code>define(\'' . esc_html(WP_ERROR_NOTIFY_CONFIG_DISCORD_WEBHOOK_URL) . '\', \'YOUR_DISCORD_WEBHOOK_URL\');</code><br>' .
			 '<code>define(\'' . esc_html(WP_ERROR_NOTIFY_CONFIG_DISCORD_USERNAME) . '\', \'Discord Bot Name (Optional)\');</code><br>' .
			 '<code>define(\'' . esc_html(WP_ERROR_NOTIFY_CONFIG_DISCORD_AVATAR_URL) . '\', \'Discord Bot Avatar URL (Optional)\');</code></p>';
		// Slack用定数例
		echo '<p><strong>' . esc_html__('For Slack:', 'wp-error-notify') . '</strong><br>' .
			 '<code>define(\'' . esc_html(WP_ERROR_NOTIFY_CONFIG_SLACK_WEBHOOK_URL) . '\', \'YOUR_SLACK_WEBHOOK_URL\');</code><br>' .
			 '<code>define(\'' . esc_html(WP_ERROR_NOTIFY_CONFIG_SLACK_USERNAME) . '\', \'Slack Bot Name (Optional)\');</code><br>' .
			 '<code>define(\'' . esc_html(WP_ERROR_NOTIFY_CONFIG_SLACK_AVATAR_URL) . '\', \'Slack Icon Emoji (e.g., :robot_face:) or Icon URL (Optional)\');</code></p>' .
			 '<p>' . esc_html__( 'If constants are defined in `wp-config.php`, they will be used as a fallback or override the settings below if the database is inaccessible or the DB field is empty.', 'wp-error-notify' ) . '</p>';
	}

	/**
	 * 各サービス設定セクション (Discord, Slack) の導入部分を表示する。
	 * @param array $args セクション登録時に渡された引数 (セクションID等を含む)
	 */
	public function service_section_callback( $args ) {
		// $args['id'] にセクションID (例: wp_error_notify_discord_section) が格納
		$service_name = str_replace( ['wp_error_notify_', '_section'], '', $args['id'] ); // 'discord' or 'slack' を抽出
		printf( '<p>' . esc_html__( 'Configure settings for %s notifications.', 'wp-error-notify' ) . '</p>', esc_html( ucfirst( $service_name ) ) );
	}

	/**
	 * 「有効化サービス」選択用チェックボックスを表示する。
	 */
	public function enabled_services_render() {
		$options = (array) get_option( WP_ERROR_NOTIFY_SETTINGS_KEY );
		$enabled_services = isset( $options['enabled_services'] ) && is_array( $options['enabled_services'] ) ? $options['enabled_services'] : [];
		$services = [ // 利用可能なサービス定義
			'discord' => __( 'Discord', 'wp-error-notify' ),
			'slack'   => __( 'Slack', 'wp-error-notify' ),
		];
		foreach ( $services as $slug => $name ) {
			echo '<label style="margin-right: 10px;">';
			echo '<input type="checkbox" name="' . esc_attr( WP_ERROR_NOTIFY_SETTINGS_KEY ) . '[enabled_services][]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $enabled_services, true ), true, false ) . '> ';
			echo esc_html( $name );
			echo '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Select the services you want to use for notifications.', 'wp-error-notify' ) . '</p>';
	}

	/**
	 * 「通知エラーレベル」選択用チェックボックスを表示する。
	 */
	public function error_levels_render() {
		$options = (array) get_option( WP_ERROR_NOTIFY_SETTINGS_KEY );
		// $selected_levels: 現在選択されているエラーレベル。DBに保存されていなければ全レベルをデフォルトとする。
		// array_map('intval', ...) はDBから取得した値が文字列である可能性を考慮。

		if (isset( $options['error_levels'] ) && is_array( $options['error_levels'] )) {
			$current_selected_levels = array_map('intval', $options['error_levels']);
		} else {
			// DBに設定がない場合、全エラーレベルのキーをデフォルト選択とする (要件「デフォは全部」)。
			$current_selected_levels = array_keys(WP_Error_Notify_Settings::get_all_error_levels());
		}

		$all_levels = WP_Error_Notify_Settings::get_all_error_levels(); // 全エラーレベル定義取得

		// スクロール可能なチェックボックスリストとして表示
		echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">';
		foreach ( $all_levels as $code => $description ) {
			echo '<label style="display: block;">';
			echo '<input type="checkbox" name="' . esc_attr( WP_ERROR_NOTIFY_SETTINGS_KEY ) . '[error_levels][]" value="' . esc_attr( $code ) . '" ' . checked( in_array( (int)$code, $current_selected_levels, true ), true, false ) . '> ';
			echo esc_html( $description );
			echo '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Select the PHP error levels you want to be notified about. It is recommended to keep fatal errors selected.', 'wp-error-notify' ) . '</p>';
	}

	/**
	 * Webhook URL入力フィールドを表示する。
	 * @param array $args フィールド登録時に渡された引数 (サービススラッグ等を含む)
	 */
	public function webhook_url_render( $args ) {
		$service = $args['service']; // 'discord' or 'slack'
		$options = (array) get_option( WP_ERROR_NOTIFY_SETTINGS_KEY );
		$value = isset( $options["{$service}_webhook_url"] ) ? $options["{$service}_webhook_url"] : ''; // DB保存値
		// wp-config.php での定義も考慮した現在の有効値を取得
		$config_value = $this->settings->get_setting($service, 'webhook_url');

		echo '<input type="url" class="regular-text" name="' . esc_attr( WP_ERROR_NOTIFY_SETTINGS_KEY ) . '[' . esc_attr( $service ) . '_webhook_url]" value="' . esc_attr( $value ) . '">';
		// wp-config.php の値がDB保存値と異なり、かつ実際に定数として定義されている場合、その旨を通知
		if ($config_value && $config_value !== $value && defined('WP_ERROR_NOTIFY_CONFIG_' . strtoupper($service) . '_WEBHOOK_URL') && constant('WP_ERROR_NOTIFY_CONFIG_' . strtoupper($service) . '_WEBHOOK_URL') === $config_value) {
			// 定数名を表示 (例: WP_ERROR_NOTIFY_WEBHOOK_URL_DISCORD)
			// 注意: ここで表示する定数名は実際の定数名と一致させる必要がある
			$const_name_to_display = 'WP_ERROR_NOTIFY_CONFIG_' . strtoupper($service) . '_WEBHOOK_URL';
			// $const_name_to_display = ($service === 'discord') ? WP_ERROR_NOTIFY_CONFIG_DISCORD_WEBHOOK_URL : WP_ERROR_NOTIFY_CONFIG_SLACK_WEBHOOK_URL; // より正確にはこうするべきだが、文字列直接指定でも可
			echo '<p class="description" style="color: green;">' . sprintf( esc_html__( 'Currently using value from wp-config.php: %s', 'wp-error-notify' ), '<code>' . esc_html( constant($const_name_to_display) ) . '</code> (Constant: <code>' . $const_name_to_display . '</code>)' ) . '</p>';
		}
	}

	/**
	 * ユーザー名入力フィールドを表示する。
	 * @param array $args フィールド登録時に渡された引数
	 */
	public function username_render( $args ) {
		$service = $args['service'];
		$options = (array) get_option( WP_ERROR_NOTIFY_SETTINGS_KEY );
		$value = isset( $options["{$service}_username"] ) ? $options["{$service}_username"] : '';
		echo '<input type="text" class="regular-text" name="' . esc_attr( WP_ERROR_NOTIFY_SETTINGS_KEY ) . '[' . esc_attr( $service ) . '_username]" value="' . esc_attr( $value ) . '">';
	}

	/**
	 * アバターURL/アイコン入力フィールドを表示する。
	 * @param array $args フィールド登録時に渡された引数
	 */
	public function avatar_url_render( $args ) {
		$service = $args['service'];
		$options = (array) get_option( WP_ERROR_NOTIFY_SETTINGS_KEY );
		$value = isset( $options["{$service}_avatar_url"] ) ? $options["{$service}_avatar_url"] : '';
		echo '<input type="url" class="regular-text" name="' . esc_attr( WP_ERROR_NOTIFY_SETTINGS_KEY ) . '[' . esc_attr( $service ) . '_avatar_url]" value="' . esc_attr( $value ) . '">';
		// Slackの場合、絵文字利用可能である旨を説明
		if ($service === 'slack') {
			echo '<p class="description">' . esc_html__( 'For Slack, you can also use an emoji like :robot_face:', 'wp-error-notify' ) . '</p>';
		}
	}


	/**
	 * 設定ページのメインコンテンツを表示する。
	 * `admin/views/settings-page.php` を読み込み使用する。
	 */
	public function settings_page_content() {
		// 権限チェック
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// ビューファイルを読み込む
		require_once WP_ERROR_NOTIFY_PATH . 'admin/views/settings-page.php';
	}

	/**
	 * 設定値保存時に値をサニタイズ (無害化) する。
	 * @param array $input ユーザーが入力した設定値の配列
	 * @return array サニタイズ後の設定値の配列
	 */
	public function sanitize_settings( $input ) {
		$new_input = [];

		// 有効化サービス: キーとしてサニタイズ
		if ( isset( $input['enabled_services'] ) && is_array( $input['enabled_services'] ) ) {
			$new_input['enabled_services'] = array_map( 'sanitize_key', $input['enabled_services'] );
		} else {
			$new_input['enabled_services'] = [];
		}

		// エラーレベル: 整数値に変換
		if ( isset( $input['error_levels'] ) && is_array( $input['error_levels'] ) ) {
			$new_input['error_levels'] = array_map( 'intval', $input['error_levels'] );
		} else {
			// 未選択の場合、全エラーレベルをデフォルトとする
			$new_input['error_levels'] = array_keys(WP_Error_Notify_Settings::get_all_error_levels());
		}

		$services = [ 'discord', 'slack' ]; // 対象サービス
		foreach ( $services as $service ) {
			// Webhook URL: URLとしてサニタイズ
			if ( isset( $input["{$service}_webhook_url"] ) ) {
				$new_input["{$service}_webhook_url"] = esc_url_raw( trim( $input["{$service}_webhook_url"] ) );
			}
			// Username: 通常のテキストとしてサニタイズ
			if ( isset( $input["{$service}_username"] ) ) {
				$new_input["{$service}_username"] = sanitize_text_field( trim( $input["{$service}_username"] ) );
			}
			// Avatar URL / Icon:
			if ( isset( $input["{$service}_avatar_url"] ) ) {
				$avatar_input = trim( $input["{$service}_avatar_url"] );
				// Slackの場合で、入力が絵文字形式 (例: :robot_face:) ならそのまま許可
				if ( $service === 'slack' && preg_match( '/^:[a-zA-Z0-9_+-]+:$/', $avatar_input ) ) {
					$new_input["{$service}_avatar_url"] = $avatar_input;
				} else {
					// それ以外はURLとしてサニタイズ
					$new_input["{$service}_avatar_url"] = esc_url_raw( $avatar_input );
				}
			}
		}
		return $new_input;
	}
}