<?php
// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Error_Notify_Sender_Discord implements WP_Error_Notify_Sender_Interface {

	private $webhook_url;
	private $username;
	private $avatar_url;

	public function __construct( string $webhook_url, ?string $username = null, ?string $avatar_url = null ) {
		$this->webhook_url = $webhook_url;
		$this->username    = $username;
		$this->avatar_url  = $avatar_url;
	}

	public function send( string $title, string $message ): bool {
		if ( empty( $this->webhook_url ) ) {
			return false;
		}

		$payload_data = [
			'content' => '', // 通常メッセージ本文はembeds内で表現
			'tts'     => false,
			'embeds'  => [
				[
					'title'       => $title,
					'description' => $message,
					'color'       => 15158332, // 赤色 (例: #e74c3c)
					'timestamp'   => current_time( 'c', true ), // ISO8601 形式のタイムスタンプ
					'footer'      => [
						'text' => sprintf( '%s | WP Error Notify', get_bloginfo( 'name' ) ),
					],
				],
			],
			'components' => [],
			'flags'      => 0,
		];

		if ( ! empty( $this->username ) ) {
			$payload_data['username'] = $this->username;
		}
		if ( ! empty( $this->avatar_url ) ) {
			$payload_data['avatar_url'] = $this->avatar_url;
		}

		$payload = json_encode( $payload_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $payload ) {
			error_log('[WP Error Notify] Failed to encode Discord payload: ' . json_last_error_msg());
			return false;
		}

		$args = [
			'body'        => $payload,
			'headers'     => [
				'Content-Type' => 'application/json',
			],
			'timeout'     => 10, // タイムアウトを少し長めに
			'redirection' => 5,
			'blocking'    => true, // true にするとレスポンスを待つ
			'httpversion' => '1.0',
			'sslverify'   => apply_filters( 'wp_error_notify_sslverify', true ), // 本番環境では true を推奨
			'data_format' => 'body',
		];

		$response = wp_remote_post( $this->webhook_url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( '[WP Error Notify] Discord notification failed: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		// Discordは成功時 204 No Content または 200 OK を返す
		if ( $response_code !== 204 && $response_code !== 200 ) {
			error_log( '[WP Error Notify] Discord notification sent, but received unexpected status code: ' . $response_code . ' Body: ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		return true;
	}
}