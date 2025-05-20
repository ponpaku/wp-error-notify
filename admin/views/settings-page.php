<?php
// 直接アクセスを禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'wp_error_notify_options_group' ); // オプショングループ名
		do_settings_sections( 'wp-error-notify' );          // ページスラッグ
		submit_button();
		?>
	</form>
</div>