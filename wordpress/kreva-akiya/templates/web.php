<?php
/**
 * Web制作・システム開発ページ（/web/）— Claude Design（Kreva Web）準拠。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

kreva_akiya_header();
?>
<div class="kreva-corp">

	<section class="kp-hero kp-hero-bg">
		<div class="kh-wrap">
			<div class="kh-kicker">WEB &amp; SYSTEMS</div>
			<h1 class="kp-h1">AIを活用して、安価かつ迅速に。<br>Webサイトから業務システムまで。</h1>
			<p class="kp-lead">ホームページ制作からEコマース、在庫管理システムまで。AIを活用した開発により、小規模事業者・ショップの皆さまにも手の届く価格とスピードでご提供します。</p>
		</div>
	</section>

	<section class="kp-sec">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">SERVICES</div><h2 class="kh-h2">サービス</h2></div>
			<div class="kp-g3">
				<article class="kp-svc"><div class="kp-ico">W</div><h3 class="kp-st">ホームページ制作</h3><p class="kp-sp">デザインから公開まで一貫して対応。事業の魅力が伝わるサイトを、更新しやすいかたちで構築します。</p></article>
				<article class="kp-svc"><div class="kp-ico">E</div><h3 class="kp-st">Eコマース</h3><p class="kp-sp">オンラインストアの構築・運用。商品登録から決済・発送の仕組みまで、販売に必要な一式を整えます。</p></article>
				<article class="kp-svc"><div class="kp-ico">S</div><h3 class="kp-st">在庫管理システム</h3><p class="kp-sp">在庫・受発注などの日々の業務を効率化するシステムを、業務の実態に合わせて開発します。</p></article>
			</div>
		</div>
	</section>

	<section class="kp-sec kp-dark">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">WHY KREVA</div><h2 class="kh-h2">AI活用による、低コスト・短納期</h2></div>
			<div class="kp-g3">
				<div><span class="kp-pnum">AI</span><h3 class="kp-pt">AIを開発の中心に</h3><p class="kp-pp">設計・実装・テストの各工程でAIを活用し、少人数でも高い品質を保ちます。</p></div>
				<div><span class="kp-pnum">−</span><h3 class="kp-pt">従来より低コスト</h3><p class="kp-pp">工数の圧縮をそのまま価格に反映。小規模事業者の予算でも現実的なご提案が可能です。</p></div>
				<div><span class="kp-pnum">→</span><h3 class="kp-pt">短納期</h3><p class="kp-pp">初回のご相談から公開まで、従来の開発より大幅に短い期間でお届けします。</p></div>
			</div>
		</div>
	</section>

	<section class="kp-sec">
		<div class="kh-wrap">
			<div class="kp-shead"><div class="kh-kicker">PROCESS</div><h2 class="kh-h2">制作の流れ</h2></div>
			<div class="kp-flow">
				<div class="kp-fstep"><span class="kp-fno">STEP 01</span><h3 class="kp-ft" style="font-size:14.5px">ヒアリング</h3><p class="kp-fp" style="font-size:12px">目的・予算・スケジュールを伺い、最適な構成をご提案します。</p><span class="kp-arr" aria-hidden="true">→</span></div>
				<div class="kp-fstep"><span class="kp-fno">STEP 02</span><h3 class="kp-ft" style="font-size:14.5px">お見積り・ご契約</h3><p class="kp-fp" style="font-size:12px">範囲と費用を明確にしたお見積りをご提示します。</p><span class="kp-arr" aria-hidden="true">→</span></div>
				<div class="kp-fstep"><span class="kp-fno">STEP 03</span><h3 class="kp-ft" style="font-size:14.5px">制作・開発</h3><p class="kp-fp" style="font-size:12px">AIを活用しながら制作。途中経過を確認いただきつつ進めます。</p><span class="kp-arr" aria-hidden="true">→</span></div>
				<div class="kp-fstep"><span class="kp-fno">STEP 04</span><h3 class="kp-ft" style="font-size:14.5px">公開・運用</h3><p class="kp-fp" style="font-size:12px">公開後の更新・保守もご相談いただけます。</p></div>
			</div>
		</div>
	</section>

	<section class="kp-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kakiya-ctab">
				<div><h2 class="kakiya-ctah" style="font-size:22px">お問い合わせ</h2><p class="kakiya-ctap">Web制作・システム開発に関するご相談・ご質問は、お問い合わせフォームよりお気軽にご連絡ください。</p></div>
				<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームへ</a>
			</div>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
