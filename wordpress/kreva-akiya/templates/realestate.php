<?php
/**
 * 不動産事業ページ（/realestate/）— Claude Design（Kreva Realestate）準拠。
 * ※空き家検索サービスへの言及なし（未公開方針）。文言はドライに（「家族所有」「日々の〜」不使用）。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$img_soja = apply_filters( 'kreva_re_img_soja', 'https://kreva.co.jp/wp-content/uploads/2026/06/okayama_soja_1.jpeg' );

kreva_akiya_header();
?>
<div class="kreva-corp">

	<section class="kp-hero">
		<div class="kh-wrap kp-hgrid">
			<div>
				<div class="kh-kicker">REAL ESTATE</div>
				<h1 class="kp-h1">地域に根ざした、<br>物件の運営と管理。</h1>
				<p class="kp-lead">KREVAは大阪府下を中心に、所有物件の賃貸・運営・管理を行っています。</p>
			</div>
			<div class="kp-heroimg"><img src="<?php echo esc_url( $img_soja ); ?>" alt="" loading="eager"></div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">APPROACH</div><h2 class="kh-h2">事業の特徴</h2></div>
			<div class="kp-g3">
				<div class="kp-feat"><span class="kp-fno">01</span><h3 class="kp-ft">所有物件の直接運営</h3><p class="kp-fp">所有者として物件と向き合うからこそ、入居者と建物の双方に目の届く運営ができます。</p></div>
				<div class="kp-feat"><span class="kp-fno">02</span><h3 class="kp-ft">地域に根ざした管理</h3><p class="kp-fp">大阪・岡山それぞれの地域特性を踏まえ、現地に足を運びながら物件を管理しています。</p></div>
				<div class="kp-feat"><span class="kp-fno">03</span><h3 class="kp-ft">資産価値の維持・向上</h3><p class="kp-fp">修繕・リフォームの計画的な実施により、建物の価値を長く保つことを重視しています。</p></div>
			</div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">PROPERTIES</div><h2 class="kh-h2">管理物件</h2><p class="kh-sdesc">現在運営・管理している物件の一例です。</p></div>
			<div class="kp-g3">
				<article class="kp-card"><div class="kp-ph"></div><div class="kp-cb"><p class="kp-cl">OSAKA</p><h3 class="kp-ct">大阪市東淀川区の居宅</h3><p class="kp-cm">種別：居宅（賃貸運営）<br>構造：鉄骨造2階建</p></div></article>
				<article class="kp-card"><div class="kp-ph"></div><div class="kp-cb"><p class="kp-cl">OSAKA</p><h3 class="kp-ct">大阪市住吉区の戸建住宅</h3><p class="kp-cm">種別：戸建住宅（賃貸運営）<br>構造：木造</p></div></article>
				<article class="kp-card"><div class="kp-ph"><img src="<?php echo esc_url( $img_soja ); ?>" alt="" loading="lazy"></div><div class="kp-cb"><p class="kp-cl">OKAYAMA</p><h3 class="kp-ct">岡山県総社市の母屋・離れ</h3><p class="kp-cm">種別：母屋＋離れ<br>離れは今後の活用を構想中</p></div></article>
			</div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kakiya-ctab">
				<div><h2 class="kakiya-ctah" style="font-size:22px">お問い合わせ</h2><p class="kakiya-ctap">不動産事業に関するご相談・ご質問は、お問い合わせフォームよりお気軽にご連絡ください。</p></div>
				<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームへ</a>
			</div>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
