<?php
/**
 * お問い合わせページ（/contact/）— Claude Design（Kreva Contact）準拠。
 * フォームは既存ページ内の Contact Form 7 ショートコードをそのまま描画（スタイルはCSSで適用）。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 既存の /contact/ ページ本文から CF7 ショートコードを抽出して描画
$form_html = '';
$cpage     = get_page_by_path( 'contact' );
if ( $cpage && preg_match( '/\[contact-form-7[^\]]*\]/', $cpage->post_content, $mm ) ) {
	$form_html = do_shortcode( $mm[0] );
}

kreva_akiya_header();
?>
<div class="kreva-corp">

	<section class="kp-hero kp-hero-bg">
		<div class="kh-wrap">
			<div class="kh-kicker">CONTACT</div>
			<h1 class="kp-h1">お問い合わせ</h1>
			<p class="kp-lead">不動産・MTKN（衣料輸出）・Web制作に関するご質問・ご依頼は、以下のフォームよりお送りください。内容を確認のうえ、担当者よりご連絡いたします。</p>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:56px">
		<div class="kh-wrap kp-cols">
			<div class="kp-formcard">
				<?php
				if ( $form_html ) {
					echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput -- CF7出力
				} else {
					echo '<p class="kp-np">フォームを読み込めませんでした。お手数ですがサイトのお問い合わせ窓口よりご連絡ください。</p>';
				}
				?>
			</div>
			<aside class="kp-aside">
				<div class="kp-note"><h2 class="kp-nh">ご返信について</h2><p class="kp-np">内容を確認のうえ、通常2〜3営業日以内に担当者よりメールでご連絡いたします。お急ぎの場合はその旨をご記入ください。</p></div>
				<div class="kp-note"><h2 class="kp-nh">お問い合わせの例</h2><p class="kp-np">・不動産の運営・管理に関するご相談<br>・MTKN（衣料輸出）の提携・卸のご相談<br>・ホームページ制作・システム開発のお見積り</p></div>
			</aside>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
