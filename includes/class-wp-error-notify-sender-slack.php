<?php
// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Error_Notify_Sender_Slack implements WP_Error_Notify_Sender_Interface {

	private $webhook_url;
	private $username;
	private $icon_emoji_or_url; // Slack は icon_emoji または icon_url

	public function __construct( string $webhook_url, ?string $username = null, ?string $icon_emoji_or_url = null ) {
		$this->webhook_url       = $webhook_url;
		$this->username          = $username;
		$this->icon_emoji_or_url = $icon_emoji_or_url;
	}

	public function send( string $title, string $message ): bool {
		if ( empty( $this->webhook_url ) ) {
			return false;
		}

		// SlackのBlock Kitペイロードを構築 (よりリッチな表示が可能)
		// DiscordのMarkdownをSlackのmrkdwnに一部変換
		$slack_message = str_replace( '**', '*', $message ); // 太字の変換

		$payload_data = [
			'blocks' => [
				[
					'type' => 'header',
					'text' => [
						'type' => 'plain_text',
						'text' => $title,
						'emoji' => true,
					],
				],
				[
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => $slack_message,
					],
				],
				[
					'type' => 'context',
					'elements' => [
						[
							'type' => 'mrkdwn',
							'text' => sprintf( '%s | WP Error Notify | %s', get_bloginfo( 'name' ), current_time( 'Y-m-d H:i:s T' ) ),
						],
					],
				],
			],
		];

		// シンプルなテキストのみの通知の場合
		// $payload_data = ['text' => $title . "\n" . $message];

		if ( ! empty( $this->username ) ) {
			$payload_data['username'] = $this->username;
		}

		if ( ! empty( $this->icon_emoji_or_url ) ) {
			if ( filter_var( $this->icon_emoji_or_url, FILTER_VALIDATE_URL ) ) {
				$payload_data['icon_url'] = $this->icon_emoji_or_url;
			} elseif ( preg_match( '/^:[a-zA-Z0-9_+-]+:$/', $this->icon_emoji_or_url ) ) {
				$payload_data['icon_emoji'] = $this->icon_emoji_or_url;
			}
		}


		$payload = json_encode( $payload_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $payload ) {
			error_log('[WP Error Notify] Failed to encode Slack payload: ' . json_last_error_msg());
			return false;
		}

		$args = [
			'body'        => $payload,
			'headers'     => [
				'Content-Type' => 'application/json',
			],
			'timeout'     => 10,
			'redirection' => 5,
			'blocking'    => true,
			'httpversion' => '1.0',
			'sslverify'   => apply_filters( 'wp_error_notify_sslverify', true ),
			'data_format' => 'body',
		];

		$response = wp_remote_post( $this->webhook_url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( '[WP Error Notify] Slack notification failed: ' . $response->get_error_message() );
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		// Slackは成功時 "ok" という文字列を返す
		if ( strtolower( trim( $response_body ) ) !== 'ok' ) {
			$response_code = wp_remote_retrieve_response_code( $response );
			error_log( '[WP Error Notify] Slack notification sent, but received unexpected response. Code: ' . $response_code . ' Body: ' . $response_body );
			return false;
		}

		return true;
	}
}