<?php
// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Error_Notify_Handler {

	private $settings;
	private $senders = [];
	private $notified_errors = []; // 通知済エラー記録用 (重複通知防止)

	/**
	 * 通知を無視するUser-Agentのキーワードリスト。
	 * User-Agent文字列にこれらのキーワードを含むリクエストで発生したエラーは通知しない。
	 * 主にクローラーやボットによるアクセスが原因のエラー通知抑制に使用。
	 * @var array
	 */
	private $ignored_user_agents = [
		'Slackbot-LinkExpanding', // Slackリンク展開プレビューボット
		'Discordbot',             // Discordボット (リンク展開等)
		'Twitterbot',             // Twitterクローラー
		'facebookexternalhit',    // Facebookクローラー
		'Googlebot',              // Googleクローラー
		'Bingbot',                // Bingクローラー
		'AhrefsBot',              // Ahrefs SEOクローラー
		'SemrushBot',             // SEMrush SEOクローラー
		'MJ12bot',                // Majestic SEOクローラー
		// 他ボットやクローラーのキーワードを必要に応じ追加
	];


	public function __construct( WP_Error_Notify_Settings $settings ) {
		$this->settings = $settings;
		$this->load_senders();
		// 無視User-Agentリストを外部変更可能にするフィルターフック（現コメントアウト）
		// add_filter('wp_error_notify_ignored_user_agents', [$this, 'get_filtered_ignored_user_agents']);
	}

	// /**
	//  * フィルターフック経由で無視User-Agentリストを加工する例。
	//  * @param array $agents 現無視リスト
	//  * @return array 加工後無視リスト
	//  */
	// public function get_filtered_ignored_user_agents(array $agents): array {
	//     // 例: DBオプションからユーザー定義無視リストをマージ
	//     // $user_defined_agents = get_option('wp_error_notify_user_ignored_agents', []);
	//     // return array_merge($agents, $user_defined_agents);
	//     return $agents;
	// }


	/**
	 * 設定に基づき利用可能な通知送信クラス（センダー）をロードする。
	 */
	private function load_senders() {
		$this->senders = []; // センダー初期化
		$enabled_services = $this->settings->get_enabled_services();

		if ( in_array( 'discord', $enabled_services, true ) ) {
			$webhook_url = $this->settings->get_setting( 'discord', 'webhook_url' );
			if ( ! empty( $webhook_url ) ) {
				$username    = $this->settings->get_setting( 'discord', 'username' );
				$avatar_url  = $this->settings->get_setting( 'discord', 'avatar_url' );
				$this->senders['discord'] = new WP_Error_Notify_Sender_Discord( $webhook_url, $username, $avatar_url );
			}
		}

		if ( in_array( 'slack', $enabled_services, true ) ) {
			$webhook_url = $this->settings->get_setting( 'slack', 'webhook_url' );
			if ( ! empty( $webhook_url ) ) {
				$username    = $this->settings->get_setting( 'slack', 'username' );
				$avatar_url  = $this->settings->get_setting( 'slack', 'avatar_url' );
				$this->senders['slack'] = new WP_Error_Notify_Sender_Slack( $webhook_url, $username, $avatar_url );
			}
		}
	}

	/**
	 * 現リクエスト情報をMarkdown形式で取得する。
	 * @return string リクエスト情報Markdown文字列
	 */
	private function get_request_details_markdown(): string {
		// CLIまたはWP-CLI経由実行時は専用情報を返す
		if ( php_sapi_name() === 'cli' || ( defined('WP_CLI') && WP_CLI ) ) {
			return sprintf(
				"**%s**\n%s\n\n",
				wp_error_notify__( 'Request Information' ),
				wp_error_notify__( 'N/A (CLI Request or System Process)' )
			);
		}

		$details = [];
		// プロトコル (http/https) 取得
		$protocol = ( !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ) ? "https://" : "http://";
		$host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
		$uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		// URLまたはURI取得
		if (!empty($host)) {
			$full_url = $protocol . $host . $uri;
			$details[wp_error_notify__( 'URL' )] = '`' . $full_url . '`';
		} elseif (!empty($uri)) {
			$details[wp_error_notify__( 'Request URI' )] = '`' . $uri . '`';
		} else {
			$details[wp_error_notify__( 'URL' )] = wp_error_notify__( 'N/A' );
		}

		// リクエストメソッド
		$details[wp_error_notify__( 'Method' )] = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : wp_error_notify__( 'N/A' );
		// リファラ
		$referer_url = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : null;
		$details[wp_error_notify__( 'Referer' )] = $referer_url ? '`' . $referer_url . '`' : wp_error_notify__( 'N/A' );
		// User Agent
		$details[wp_error_notify__( 'User Agent' )] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : wp_error_notify__( 'N/A' );
		// IPアドレス
		$details[wp_error_notify__( 'IP Address' )] = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : wp_error_notify__( 'N/A' );

		// Markdown形式整形
		$markdown = "**" . wp_error_notify__( 'Request Information' ) . "**\n";
		foreach ( $details as $label => $value ) {
			$markdown .= "{$label}: {$value}\n";
		}
		return $markdown . "\n";
	}

	/**
	 * カスタムエラーハンドラー (set_error_handlerで使用)。
	 * PHPエラーを捕捉し、通知処理へ渡す。
	 */
	public function custom_error_handler( $errno, $errstr, $errfile, $errline ) {
		// @で抑制されたエラーは処理しない
		if ( ! ( error_reporting() & $errno ) ) {
			return false; // PHP標準エラーハンドラに処理継続
		}
		$this->process_error( $errno, $errstr, $errfile, $errline, null );
		return false; // PHP標準エラーハンドラに処理継続
	}

	/**
	 * wp_die_handlerフィルターに登録するWordPress致命的エラーハンドラー関数を返す。
	 * @return array 呼び出し可能ハンドラ関数
	 */
	public function custom_wp_die_handler_filter(): array {
		return [ $this, 'custom_wp_die_function' ];
	}

	/**
	 * カスタムwp_die処理関数。
	 * wp_die呼び出し時にエラー情報を整形し通知処理へ渡す。
	 *
	 * @param string|WP_Error $message エラーメッセージまたはWP_Errorオブジェクト
	 * @param string $title エラータイトル
	 * @param array $args 引数
	 */
	public function custom_wp_die_function( $message, $title = '', $args = [] ) {
		// DB接続エラーの可能性時、設定をDBからでなくwp-config.phpから読むよう切替
		if ( (is_string($message) && stripos($message, 'Error establishing a database connection') !== false) ||
			 ($message instanceof WP_Error && $message->get_error_code() === 'db_connect_fail') ) {
			$this->settings->mark_db_inaccessible(); // DBアクセス不可とマーク
			$this->load_senders();                   // センダー再読み込み (wp-config.phpの値を使用)
		}

		$error_detail = error_get_last(); // wp_die直前に発生した可能性のあるPHPエラー取得
		$error_type_for_check = E_ERROR;  // wp_die起因の場合、デフォルトで致命的エラー扱い
		$error_message_content = '';      // 通知用エラーメッセージ本文
		$error_file_content = 'N/A (wp_die)'; // エラー発生ファイル (wp_die起因時は特定困難)
		$error_line_content = 'N/A (wp_die)'; // エラー発生行 (wp_die起因時は特定困難)
		$preformatted_core_error_details = null; // 事前整形済コアエラー情報

		if ( $error_detail && isset( $error_detail['type'] ) ) {
			// error_get_last()でPHPエラー詳細が取れた場合
			$error_type_for_check = $error_detail['type'];
			$error_type_name = $this->settings->get_error_type_name( $error_detail['type'] );
			$error_message_content = $error_detail['message'];
			$error_file_content = $error_detail['file'];
			$error_line_content = $error_detail['line'];

			$preformatted_core_error_details = sprintf(
				"**%s**\n%s: %s\n\n**%s**\n%s on line %d",
				wp_error_notify__( 'Error Content' ),
				$error_type_name,
				$error_message_content,
				wp_error_notify__( 'Error Location' ),
				$error_file_content,
				$error_line_content
			);
		} else {
			// error_get_last()で詳細が取れない場合 (wp_dieが直接エラーメッセージと共に呼ばれた等)
			$error_message_content = is_wp_error( $message ) ? $message->get_error_message() : strip_tags( (string) $message );
			if ( empty( $error_message_content ) && ! empty( $title ) ) {
				$error_message_content = strip_tags( (string) $title );
			} elseif ( empty( $error_message_content ) ) {
				$error_message_content = wp_error_notify__( 'An unknown error occurred via wp_die.' );
			}

			$preformatted_core_error_details = sprintf(
				"**%s**\n%s\n\n**%s**\n%s",
				wp_error_notify__( 'Error Content' ),
				wp_error_notify__( 'A critical error occurred. Details from wp_die:' ), // wp_die経由を明記
				wp_error_notify__( 'Message' ),
				$error_message_content
			);
		}

		// エラー情報を元に通知処理実行
		$this->process_error(
			$error_type_for_check,
			$error_message_content,
			$error_file_content,
			$error_line_content,
			$preformatted_core_error_details // 整形済コアエラー情報受渡し
		);

		// WordPress標準wp_die処理続行
		if ( function_exists( '_default_wp_die_handler' ) ) {
			_default_wp_die_handler( $message, $title, $args );
		} else {
			// フォールバック (通常は_default_wp_die_handlerが存在)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$output = is_wp_error( $message ) ? $message->get_error_message() : $message;
				if ( $title ) {
					$output = $title . ': ' . $output;
				}
				die( (string) $output );
			} else {
				die( wp_error_notify__( 'A critical error has occurred on your site.' ) );
			}
		}
	}

	/**
	 * シャットダウンハンドラ (register_shutdown_functionで使用)。
	 * 主にset_error_handlerで捕捉不可な致命的エラー (Fatal error等) を処理する。
	 */
	public function custom_shutdown_handler() {
		$error = error_get_last(); // スクリプト終了時最後のエラー取得
		// エラーが存在し、それが致命的エラータイプの場合
		if ( $error && $this->is_fatal_error( $error['type'] ) ) {
			// DB接続エラーの可能性時、設定読込方法を切替
			if ( $this->is_db_connection_error_context($error) ) {
				$this->settings->mark_db_inaccessible(); // DBアクセス不可とマーク
				$this->load_senders();                   // センダー再読み込み
			}
			$this->process_error( $error['type'], $error['message'], $error['file'], $error['line'], null );
		}
	}

	/**
	 * 指定エラータイプが致命的か判定する。
	 * @param int $type PHPエラータイプ定数
	 * @return bool 致命的エラーならtrue
	 */
	private function is_fatal_error( int $type ): bool {
		return in_array( $type, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ], true );
	}

	/**
	 * 現エラーコンテキストがDB接続エラーの可能性が高いか簡易判定する。
	 * @param array $error error_get_last() で取得したエラー情報
	 * @return bool DB接続エラーの可能性が高ければtrue
	 */
	private function is_db_connection_error_context(array $error): bool {
		if (isset($error['message'])) {
			$db_error_keywords = ['mysql', 'database connection', 'SQLSTATE', 'wpdb'];
			foreach ($db_error_keywords as $keyword) {
				if (stripos($error['message'], $keyword) !== false) {
					return true;
				}
			}
		}
		// エラー発生ファイルパスにwp-db.phpが含まれる場合もDBエラーの可能性が高いと判断
		if (isset($error['file']) && stripos($error['file'], 'wp-db.php') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * エラーを処理し、設定に基づき通知を送信する。
	 *
	 * @param int $errno エラー番号
	 * @param string $errstr エラーメッセージ
	 * @param string $errfile エラー発生ファイル
	 * @param int $errline エラー発生行番号
	 * @param string|null $preformatted_core_error_details 事前整形済エラーコア部分 (エラー内容と箇所)。wp_die等から受渡し。
	 */
	private function process_error( $errno, $errstr, $errfile, $errline, $preformatted_core_error_details = null ) {
		// --- User-Agentによる通知フィルタリング ---
		if ( isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT']) ) {
			$current_user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
			// 無視リスト取得 (将来的にフィルターフックで拡張可能)
			$ignored_agents_list = apply_filters('wp_error_notify_ignored_user_agents', $this->ignored_user_agents);

			if (is_array($ignored_agents_list)) {
				foreach ( $ignored_agents_list as $ignored_ua_keyword ) {
					if ( stripos( $current_user_agent, $ignored_ua_keyword ) !== false ) {
						// 無視リストのキーワードがUser-Agentに含まれる場合、通知せず処理終了
						return;
					}
				}
			}
		}
		// --- User-Agentフィルタリングここまで ---

		// 通知対象エラーレベルか確認
		$selected_levels = $this->settings->get_error_levels();
		if ( ! in_array( $errno, $selected_levels, true ) ) {
			return; // 通知対象外エラーレベル
		}

		// 重複通知防止 (ファイル名、行番号、エラーメッセージで簡易判断)
		$error_signature = md5( $errfile . $errline . $errstr );
		if ( isset( $this->notified_errors[$error_signature] ) ) {
			return; // 本リクエスト内で通知済 (または短期間に通知済)
		}
		$this->notified_errors[$error_signature] = time(); // 通知エラーとして記録

		// エラーコア情報をMarkdown形式で準備
		$core_error_details_markdown = '';
		if ( null === $preformatted_core_error_details ) {
			// 通常PHPエラーの場合 (set_error_handler, register_shutdown_functionから)
			$error_type_name = $this->settings->get_error_type_name( $errno );
			$core_error_details_markdown = sprintf(
				"**%s**\n%s: %s\n\n**%s**\n%s on line %d",
				wp_error_notify__( 'Error Content' ),
				$error_type_name,
				$errstr,
				wp_error_notify__( 'Error Location' ),
				$errfile,
				$errline
			);
		} else {
			// wp_dieハンドラから整形済情報が渡された場合
			$core_error_details_markdown = $preformatted_core_error_details;
		}

		// リクエスト情報取得
		$request_info_markdown = $this->get_request_details_markdown();
		// 最終通知メッセージ本文作成 (エラーコア情報 + リクエスト情報)
		$description_message = $core_error_details_markdown . "\n\n" . $request_info_markdown;
		// 通知タイトル
		$title = wp_error_notify__( 'An error has occurred on your site.' );

		// 有効な各センダーで通知送信
		foreach ( $this->senders as $service_name => $sender ) {
			if ( $sender instanceof WP_Error_Notify_Sender_Interface ) {
				try {
					$sender->send( $title, $description_message );
				} catch ( Exception $e ) {
					// 通知送信失敗時はサーバーエラーログに記録 (無限ループ防止のため再通知せず)
					error_log( "[WP Error Notify] Failed to send notification via {$service_name}: " . $e->getMessage() );
				}
			}
		}
	}
}