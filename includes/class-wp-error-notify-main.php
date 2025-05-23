<?php
// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Error_Notify_Main {

	private static $instance;
	private $error_handler;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$settings = new WP_Error_Notify_Settings();
		$this->error_handler = new WP_Error_Notify_Handler( $settings );

		// カスタムエラーハンドラーを登録
		set_error_handler( [ $this->error_handler, 'custom_error_handler' ] );
		// WordPress の致命的なエラーのハンドラー
		add_filter( 'wp_die_handler', [ $this->error_handler, 'custom_wp_die_handler_filter' ] );

		// シャットダウン時にもエラーを確認 (致命的なエラーなどで set_error_handler が呼ばれない場合のため)
		register_shutdown_function( [ $this->error_handler, 'custom_shutdown_handler' ] );
	}
}