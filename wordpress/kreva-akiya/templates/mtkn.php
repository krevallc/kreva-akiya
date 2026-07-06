<?php
/**
 * MTKN事業紹介ページ（/mtkn/）— Claude Design（Mtkn Page）準拠。
 * ヒーロー（ダーク）／コンセプト／事業の仕組み3ステップ／取扱いブランド／
 * 現地での活動／メーカー様向け提携CTA／mtkn.nlストアCTA。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 写真（フィルタで差し替え可能）。空文字にするとパターンプレースホルダ表示
$img_hero      = apply_filters( 'kreva_mtkn_img_hero', 'https://kreva.co.jp/wp-content/uploads/2026/06/IMG_2039-scaled.jpg' );
$img_concept   = apply_filters( 'kreva_mtkn_img_concept', 'https://kreva.co.jp/wp-content/uploads/2026/07/denim-days-mtkn.jpg' );
$img_denimdays = apply_filters( 'kreva_mtkn_img_denimdays', 'https://kreva.co.jp/wp-content/uploads/2026/06/IMG_2039-scaled.jpg' );
$img_kingpins  = apply_filters( 'kreva_mtkn_img_kingpins', '' );
$img_hod       = apply_filters( 'kreva_mtkn_img_hod', '' );
$img_store     = apply_filters( 'kreva_mtkn_img_store', 'https://kreva.co.jp/wp-content/uploads/2026/07/mtkn-top-capture.jpg' );

$act_photo = function ( $url, $pos = 'center' ) {
	if ( $url ) {
		echo '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" style="object-position:' . esc_attr( $pos ) . '">';
	} else {
		echo '<span class="km-phempty" aria-hidden="true"></span>';
	}
};

kreva_akiya_header();
?>
<div class="kreva-mtkn">

	<section class="km-hero km-dark">
		<div class="kh-wrap km-hgrid">
			<div>
				<div class="kh-kicker kh-kicker-l">MTKN — AMSTERDAM</div>
				<h1 class="km-h1">日本のデニムを、<br>欧州へ。</h1>
				<div class="kh-mchips"><span class="kh-mc">日本製ヴィンテージ</span><span class="kh-mc">国産デニム</span><span class="kh-mc">アムステルダム拠点</span></div>
				<p class="km-lead">MTKNは、日本製ヴィンテージと国産デニムを中心とする日本製衣料を、アムステルダムから欧州へ届けるプロジェクトです。日本人兄弟2人が、大阪・岡山とアムステルダムの2拠点で運営しています。</p>
				<a class="kh-ghost" href="https://mtkn.nl" target="_blank" rel="noopener">mtkn.nl を見る ↗</a>
			</div>
			<div class="km-photo km-photo-hero"><?php $act_photo( $img_hero ); ?></div>
		</div>
	</section>

	<section class="km-sec" style="background:var(--bg)">
		<div class="kh-wrap km-cgrid">
			<div>
				<div class="kh-kicker">CONCEPT</div>
				<h2 class="kh-h2" style="margin-bottom:18px">岡山デニムの魅力を、世界へ。</h2>
				<p class="km-p">岡山の児島・井原は、国産デニムの聖地と呼ばれる土地です。ジーンズの国内生産発祥の地として知られ、カイハラやクロキといった世界的なデニム生地の産地とも地続きの文化圏に、確かなものづくりが息づいています。</p>
				<p class="km-p">MTKNは、この産地の縁を背景に、日本製ヴィンテージと国産デニムをキュレーション。1本1本の状態を確かめ、つくり手の仕事が伝わるかたちで、ヨーロッパのデニムファンへ届けています。</p>
			</div>
			<div class="km-photo km-photo-concept"><?php $act_photo( $img_concept, 'center 30%' ); ?></div>
		</div>
	</section>

	<section class="km-sec">
		<div class="kh-wrap">
			<div class="km-shead">
				<div class="kh-kicker">HOW IT WORKS</div>
				<h2 class="kh-h2">事業の仕組み</h2>
				<p class="kh-sdesc">日本での買付から欧州のお客様の手元まで、2拠点で一貫して運営しています。</p>
			</div>
			<div class="km-steps">
				<div class="km-step"><span class="km-sno">STEP 01 — JAPAN</span><h3 class="km-st">日本で買付・キュレーション</h3><p class="km-sp">大阪・岡山を拠点に、日本製ヴィンテージと国産デニムを1点ずつ選定。状態確認とメンテナンスを行います。</p><span class="km-arrow" aria-hidden="true">→</span></div>
				<div class="km-step"><span class="km-sno">STEP 02 — AMSTERDAM</span><h3 class="km-st">アムステルダム拠点へ</h3><p class="km-sp">欧州側の拠点アムステルダムで在庫を管理。現地のデニムコミュニティとつながりながら販売の場を広げています。</p><span class="km-arrow" aria-hidden="true">→</span></div>
				<div class="km-step"><span class="km-sno">STEP 03 — EUROPE</span><h3 class="km-st">欧州へ販売</h3><p class="km-sp">自社オンラインストア mtkn.nl を中心に、複数のマーケットプレイスで欧州全域のお客様へ販売しています。</p><div class="km-schips"><span class="km-sc">mtkn.nl</span><span class="km-sc">eBay</span><span class="km-sc">Grailed</span><span class="km-sc">Vinted</span></div></div>
			</div>
		</div>
	</section>

	<section class="km-sec" style="background:var(--bg);padding-top:0">
		<div class="kh-wrap">
			<div class="km-shead">
				<div class="kh-kicker">BRANDS</div>
				<h2 class="kh-h2">取扱いブランド</h2>
				<p class="kh-sdesc">中古・ヴィンテージを含む取扱実績の一例です。</p>
			</div>
			<div class="km-brands"><span class="km-bc">DENIM BRIDGE</span><span class="km-bc">HANDS-ON</span><span class="km-bc">MEPSE &amp; Co.</span><span class="km-bc">WASEW</span><span class="km-bc">Naturals.</span></div>
			<p class="km-bnote">ほか、日本ブランドを多数取り扱っています。在庫は時期により変動します。</p>
		</div>
	</section>

	<section class="km-sec">
		<div class="kh-wrap">
			<div class="km-shead">
				<div class="kh-kicker">COMMUNITY</div>
				<h2 class="kh-h2">現地での活動</h2>
				<p class="kh-sdesc">アムステルダムのデニムコミュニティに根ざして活動しています。</p>
			</div>
			<div class="km-acts">
				<article class="km-act"><div class="km-aph"><?php $act_photo( $img_denimdays, 'right 70%' ); ?></div><div class="km-ab"><p class="km-am">DENIM DAYS AMSTERDAM</p><h3 class="km-at">マーケットブース出展</h3><p class="km-ap">アムステルダムのデニムの祭典 Denim Days にマーケットブースを出展。現地のファンと直接交流しています。</p></div></article>
				<article class="km-act"><div class="km-aph"><?php $act_photo( $img_kingpins ); ?></div><div class="km-ab"><p class="km-am">KINGPINS SHOW</p><h3 class="km-at">デニム見本市への参加</h3><p class="km-ap">世界最大級のデニム見本市 Kingpins Show に参加。生地・トレンドの最前線から知見を持ち帰っています。</p></div></article>
				<article class="km-act"><div class="km-aph"><?php $act_photo( $img_hod ); ?></div><div class="km-ab"><p class="km-am">HOUSE OF DENIM</p><h3 class="km-at">財団・コミュニティとの交流</h3><p class="km-ap">House of Denim財団をはじめ、アムステルダムのデニム関係者との交流を通じて活動を広げています。</p></div></article>
			</div>
		</div>
	</section>

	<section class="km-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kakiya-ctab">
				<div>
					<div class="kh-kicker" style="margin-bottom:10px">FOR MAKERS</div>
					<h2 class="kakiya-ctah" style="font-size:22px">日本のメーカー様へ</h2>
					<p class="kakiya-ctap" style="max-width:38em">海外販路をお探しの衣料製造企業様へ。欧州での販売代行・テスト販売・現地コミュニティへの橋渡しなど、提携のご相談を承っています。まずはお気軽にお問い合わせください。</p>
				</div>
				<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">提携のご相談はこちら</a>
			</div>
		</div>
	</section>

	<section class="km-sec km-dark">
		<div class="kh-wrap km-sgrid">
			<a class="km-photo km-photo-store" href="https://mtkn.nl" target="_blank" rel="noopener" aria-label="mtkn.nl を見る"><?php $act_photo( $img_store, 'top center' ); ?></a>
			<div>
				<div class="km-surl">MTKN.NL — ONLINE STORE</div>
				<h2 class="kh-h2 kh-h2-w" style="margin-bottom:16px">オンラインストアを見る</h2>
				<p class="km-lead" style="margin-bottom:24px">最新の入荷アイテムは自社オンラインストアでご覧いただけます。欧州全域への配送に対応しています。</p>
				<a class="kh-ghost" href="https://mtkn.nl" target="_blank" rel="noopener">mtkn.nl へ ↗</a>
			</div>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
