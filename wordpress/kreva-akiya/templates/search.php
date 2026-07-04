<?php
/**
 * 空き家 検索・一覧テンプレート（CPT archive）。
 * 地図(Leaflet+地理院タイル+ハザードトグル) ＋ 絞り込み ＋ カード一覧。
 * データは REST /kreva-akiya/v1/search を JS(akiya-search.js) が取得して描画。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// フィルタ用のタクソノミー選択肢
$prefs  = get_terms( array( 'taxonomy' => 'akiya_pref', 'hide_empty' => false ) );
$cities = get_terms( array( 'taxonomy' => 'akiya_city', 'hide_empty' => false ) );
$types  = get_terms( array( 'taxonomy' => 'akiya_type', 'hide_empty' => false ) );
?>
<div class="kakiya-page">
	<header class="kakiya-hero">
		<h1>岡山県の空き家検索</h1>
		<p>岡山県内の空き家バンク等の物件を、<strong>市街化調整区域・災害ハザード・地価</strong>など最新の周辺情報つきで表示します。</p>
	</header>

	<div class="kakiya-layout">
		<aside class="kakiya-filters" aria-label="絞り込み">
			<div class="kakiya-field">
				<label for="f-pref">都道府県</label>
				<select id="f-pref" data-filter="pref">
					<option value="">すべて</option>
					<?php foreach ( (array) $prefs as $t ) : ?>
						<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="kakiya-field">
				<label for="f-city">市区町村</label>
				<select id="f-city" data-filter="city">
					<option value="">すべて</option>
					<?php foreach ( (array) $cities as $t ) : ?>
						<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="kakiya-field">
				<label for="f-type">物件種別</label>
				<select id="f-type" data-filter="type">
					<option value="">すべて</option>
					<?php foreach ( (array) $types as $t ) : ?>
						<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="kakiya-field kakiya-price">
				<label>価格帯（万円）</label>
				<div class="kakiya-row">
					<input type="number" id="f-price-min" data-filter="price_min_man" placeholder="下限" min="0" />
					<span>〜</span>
					<input type="number" id="f-price-max" data-filter="price_max_man" placeholder="上限" min="0" />
				</div>
			</div>
			<div class="kakiya-field kakiya-checks">
				<label><input type="checkbox" data-filter="kuiki" value="exclude_chosei" /> 市街化調整区域を除外</label>
				<label><input type="checkbox" data-filter="hazard_free" value="1" /> 災害該当を除外</label>
				<label><input type="checkbox" data-filter="kreva_only" value="1" /> KREVAの物件のみ</label>
			</div>
			<button type="button" id="kakiya-apply" class="kakiya-btn">この条件で検索</button>
			<p class="kakiya-count"><span id="kakiya-count">-</span> 件</p>
		</aside>

		<div class="kakiya-main">
			<div id="kakiya-map" role="application" aria-label="物件地図"></div>
			<div id="kakiya-cards" class="kakiya-cards" aria-live="polite"></div>
		</div>
	</div>

	<section class="kakiya-external">
		<h2>外部の空き家バンクでも探す</h2>
		<p>掲載外・他エリアの物件は、以下の全国版バンク／集約サイトでも探せます（新しいタブで開きます）。</p>
		<ul class="kakiya-external-list">
			<?php foreach ( kreva_akiya_external_banks() as $bank ) : ?>
				<li>
					<a href="<?php echo esc_url( $bank['url'] ); ?>" target="_blank" rel="noopener nofollow">
						<?php echo esc_html( $bank['label'] ); ?> ↗
					</a>
					<span class="kakiya-note"><?php echo esc_html( $bank['note'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<footer class="kakiya-cta">
		<h2>岡山で空き家をお探し・売却したい方へ</h2>
		<p>KRÈVA LLC は岡山・周辺で空き家を購入し、リノベーション・民泊・再販を行っています。物件のご相談・売却のご相談はお気軽に。</p>
		<a class="kakiya-btn kakiya-btn-primary" href="mailto:info@kreva.co.jp">KREVAに相談する（info@kreva.co.jp）</a>
	</footer>

	<p class="kakiya-disclaimer">
		掲載情報は各出典を集約したものです。<strong>最新・正確な内容は必ず元の掲載元（自治体・空き家バンク等）でご確認ください。</strong>
		地図の規制・地価は国土交通省 不動産情報ライブラリ、災害ハザードは国土地理院 重ねるハザードマップに基づきます。
	</p>
</div>
<?php
get_footer();
