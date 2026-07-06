<?php
/**
 * 会社概要ページ（/company/）— Claude Design（Kreva Company）準拠。
 * アムステルダム表記は kreva_hide_amsterdam フィルタで非表示化可能（既定は表示）。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hide_ams = (bool) apply_filters( 'kreva_hide_amsterdam', false );

$page_url = function ( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? get_permalink( $p ) : home_url( '/' . $slug . '/' );
};

kreva_akiya_header();
?>
<div class="kreva-corp">

	<section class="kp-hero kp-hero-bg">
		<div class="kh-wrap">
			<div class="kh-kicker">COMPANY</div>
			<h1 class="kp-h1">会社概要</h1>
			<p class="kp-lead">合同会社クレバ（KRÈVA LLC）は、大阪府高槻市を拠点に、不動産事業、衣料品輸出事業（MTKN）、Web制作・システム開発の三本柱で事業を展開しています。</p>
		</div>
	</section>

	<section class="kp-sec">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">PROFILE</div><h2 class="kh-h2">会社情報</h2></div>
			<div class="kp-tbl">
				<div class="kp-tr"><span class="kp-tl">会社名</span><span class="kp-tv">合同会社クレバ（KRÈVA LLC）</span></div>
				<div class="kp-tr"><span class="kp-tl">所在地</span><span class="kp-tv">大阪府高槻市日吉台六番町18-12</span></div>
				<div class="kp-tr"><span class="kp-tl">設立日</span><span class="kp-tv">2018年3月23日</span></div>
				<div class="kp-tr"><span class="kp-tl">資本金</span><span class="kp-tv">100万円</span></div>
				<div class="kp-tr"><span class="kp-tl">事業内容</span><span class="kp-tv">不動産の売買及び賃貸業<br>海外向け衣料輸出業（MTKN）<br>Web制作・システム開発</span></div>
			</div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">BUSINESS</div><h2 class="kh-h2">事業</h2></div>
			<div class="kp-g3">
				<a class="kh-bcard" href="<?php echo esc_url( $page_url( 'realestate' ) ); ?>"><span class="kh-bno">REAL ESTATE</span><span class="kh-bt">不動産事業</span><p class="kh-bp">大阪府下を中心とした所有物件の賃貸・運営・管理。地域に根ざした管理で資産価値の維持・向上に取り組みます。</p><span class="kh-bgo">詳しく見る →</span></a>
				<a class="kh-bcard" href="<?php echo esc_url( $page_url( 'mtkn' ) ); ?>"><span class="kh-bno">MTKN</span><span class="kh-bt">衣料品輸出事業</span><p class="kh-bp"><?php echo $hide_ams ? '日本製ヴィンテージ・国産デニムを欧州へ輸出・販売。日本のものづくり文化を発信しています。' : '日本製ヴィンテージ・国産デニムをアムステルダム拠点から欧州へ。日本のものづくり文化を発信しています。'; ?></p><span class="kh-bgo">詳しく見る →</span></a>
				<a class="kh-bcard" href="<?php echo esc_url( $page_url( 'web' ) ); ?>"><span class="kh-bno">WEB &amp; SYSTEMS</span><span class="kh-bt">Web制作・システム開発</span><p class="kh-bp">AIを活用したホームページ制作・EC・在庫管理システムを、安価かつ迅速に構築します。</p><span class="kh-bgo">詳しく見る →</span></a>
			</div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">LOCATIONS</div><h2 class="kh-h2">拠点</h2></div>
			<div class="kp-locs">
				<div class="kp-loc"><span class="kp-lk">OSAKA — JAPAN</span><h3 class="kp-lt">大阪（本社）</h3><p class="kp-lp">大阪府高槻市日吉台六番町18-12<br>不動産事業・Web制作・システム開発の拠点</p></div>
				<?php if ( ! $hide_ams ) : ?>
					<div class="kp-loc"><span class="kp-lk">AMSTERDAM — NL</span><h3 class="kp-lt">アムステルダム</h3><p class="kp-lp">衣料品輸出事業（MTKN）の欧州拠点<br>在庫管理・販売・現地コミュニティ活動</p></div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kakiya-ctab">
				<div><h2 class="kakiya-ctah" style="font-size:22px">お問い合わせ</h2><p class="kakiya-ctap">事業に関するご相談・ご質問は、お問い合わせフォームよりお気軽にご連絡ください。</p></div>
				<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームへ</a>
			</div>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
