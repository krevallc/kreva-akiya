<?php
/**
 * 空き家 検索・一覧テンプレート（CPT archive）— Zillow型スプリットビュー。
 * 左: 地図（sticky・価格ピン）／右: カードグリッド。上部: フィルタピルバー（自動適用）。
 * モバイル(<=820px): 地図⇔リスト切替ボタン。
 * デザイン: design/Akiya Search.dc.html（Claude Design）のトークンに準拠。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

kreva_akiya_header();

$cities = get_terms( array( 'taxonomy' => 'akiya_city', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$types  = get_terms( array( 'taxonomy' => 'akiya_type', 'hide_empty' => true ) );
?>
<div class="kakiya-page2">

	<!-- フィルタピルバー -->
	<div class="kakiya-fbar" role="search">
		<select id="f-city" class="kakiya-pill kakiya-pill-select" data-filter="city" aria-label="市区町村">
			<option value="">市区町村</option>
			<?php foreach ( (array) $cities as $t ) : ?>
				<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?>（<?php echo (int) $t->count; ?>）</option>
			<?php endforeach; ?>
		</select>
		<select id="f-price" class="kakiya-pill kakiya-pill-select" aria-label="価格帯">
			<option value="">価格帯</option>
			<option value="0-1000000">〜100万円</option>
			<option value="0-3000000">〜300万円</option>
			<option value="0-5000000">〜500万円</option>
			<option value="0-10000000">〜1,000万円</option>
			<option value="10000000-">1,000万円〜</option>
		</select>
		<select id="f-type" class="kakiya-pill kakiya-pill-select" data-filter="type" aria-label="物件種別">
			<option value="">種別</option>
			<?php foreach ( (array) $types as $t ) : ?>
				<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<span class="kakiya-vr" aria-hidden="true"></span>
		<button type="button" class="kakiya-pill" data-toggle="newonly">新着のみ</button>
		<button type="button" class="kakiya-pill" data-toggle="has_photo">写真あり</button>
		<button type="button" class="kakiya-pill" data-toggle="kuiki">調整区域を除外</button>
		<button type="button" class="kakiya-pill" data-toggle="hazard_free">災害該当を除外</button>
	</div>

	<!-- スプリットビュー -->
	<div class="kakiya-split" id="kakiya-split">
		<div class="kakiya-splitmap">
			<div id="kakiya-map" role="application" aria-label="物件地図"></div>
			<button type="button" id="kakiya-bbox" class="kakiya-mchip" hidden>この範囲で再検索</button>
		</div>
		<main class="kakiya-results">
			<div class="kakiya-rhead">
				<div>
					<div class="kakiya-rtitle">岡山県の空き家・空き家バンク物件</div>
					<div class="kakiya-rsub"><span id="kakiya-count">-</span> 件｜自治体空き家バンクを毎週自動集約</div>
				</div>
				<select id="f-sort" class="kakiya-pill kakiya-pill-select" aria-label="並び替え">
					<option value="new">新着順</option>
					<option value="price_asc">価格が安い順</option>
					<option value="price_desc">価格が高い順</option>
					<option value="land_desc">土地が広い順</option>
				</select>
			</div>
			<div id="kakiya-cards" class="kakiya-cards" aria-live="polite"></div>
		</main>
	</div>

	<button type="button" id="kakiya-vtoggle" class="kakiya-mtab">地図で見る</button>

	<!-- SEOテキスト・リンク・CTA -->
	<section class="kakiya-seo">
		<div class="kakiya-wrapn">
			<h1 class="kakiya-h2">岡山県の空き家を探す</h1>
			<p class="kakiya-p">岡山県内の自治体が運営する空き家バンクの掲載物件を一括で検索できます。地図と写真から物件を比較できるほか、各物件には<strong>災害想定区域・市街化調整区域の該当有無、学区、周辺の地価</strong>といった独自の付加情報を掲載。移住・二拠点生活・投資など、目的に合った空き家探しをサポートします。</p>
			<p class="kakiya-p">気になる物件が見つかりましたら、掲載元の空き家バンクへのお問い合わせ方法もあわせてご案内しています。物件選び・現地確認・購入後の活用まで、岡山の不動産会社 KREVA が伴走します。</p>

			<h2 class="kakiya-h3">市区町村から探す</h2>
			<div class="kakiya-links">
				<?php foreach ( (array) $cities as $t ) : ?>
					<a class="kakiya-lnk" href="<?php echo esc_url( add_query_arg( 'city', $t->slug, get_post_type_archive_link( KREVA_Akiya_CPT::POST_TYPE ) ) ); ?>"><?php echo esc_html( $t->name ); ?>の空き家（<?php echo (int) $t->count; ?>）</a>
				<?php endforeach; ?>
			</div>

			<h2 class="kakiya-h3">外部の空き家バンクでも探す</h2>
			<div class="kakiya-links">
				<?php foreach ( kreva_akiya_external_banks() as $bank ) : ?>
					<a class="kakiya-lnk" href="<?php echo esc_url( $bank['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $bank['label'] ); ?> ↗</a>
				<?php endforeach; ?>
			</div>

			<div class="kakiya-ctab">
				<div>
					<h2 class="kakiya-ctah">空き家探し、プロに相談しませんか</h2>
					<p class="kakiya-ctap">物件の見極めから現地確認・お手続きまで、岡山の空き家に詳しいスタッフが無料でご相談を承ります。</p>
				</div>
				<a class="kakiya-cta2" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームへ</a>
			</div>

			<p class="kakiya-note2">出典：各市町村空き家バンク・住まいる岡山ほか（掲載情報は取得時点のものです。最新の状況は必ず各掲載元でご確認ください）。災害想定区域は国土交通省 不動産情報ライブラリ・国土地理院 重ねるハザードマップ等の公開情報に基づく参考情報であり、安全性を保証するものではありません。市街化調整区域の該当有無・学区・地価も同様に公開データに基づく参考情報です。</p>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
