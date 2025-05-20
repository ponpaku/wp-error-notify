<?php
// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WP_Error_Notify_Sender_Interface {
	/**
	 * 通知を送信する
	 *
	 * @param string $title 通知のタイトル
	 * @param string $message 通知の本文 (Markdown形式を想定)
	 * @return bool 送信成功でtrue、失敗でfalse
	 */
	public function send( string $title, string $message ): bool;
}