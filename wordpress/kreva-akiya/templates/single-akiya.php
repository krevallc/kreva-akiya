<?php
/**
 * 空き家 詳細テンプレート — Claude Design（Akiya Detail）準拠。
 * パンくず／ヘッダー（タイトル・バッジ・大価格）／ギャラリー（1+4グリッド・ライトボックス）／
 * 2カラム（概要・規制・災害・生活・説明・地図｜相場・CTA・出典）／近くの物件。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

kreva_akiya_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$m       = kreva_akiya_get_meta( $post_id );

	// ギャラリー（image_urls JSON → 無ければ image_url 単発）
	$gallery = array();
	if ( ! empty( $m['image_urls'] ) ) {
		$decoded = json_decode( $m['image_urls'], true );
		if ( is_array( $decoded ) ) {
			$gallery = array_values( array_filter( array_map( 'esc_url_raw', $decoded ) ) );
		}
	}
	if ( ! $gallery && ! empty( $m['image_url'] ) ) {
		$gallery = array( esc_url_raw( $m['image_url'] ) );
	}

	// バッジ
	$is_new     = ( time() - get_post_time( 'U', true ) ) < 14 * DAY_IN_SECONDS;
	$is_chosei  = $m['kuiki_kubun'] && false !== strpos( $m['kuiki_kubun'], '調整' );
	$hazard_any = ! empty( $m['hazard_flood'] ) || ! empty( $m['hazard_landslide'] ) || ! empty( $m['hazard_tsunami'] ) || ! empty( $m['hazard_hightide'] );

	// 市区町村（パンくず・近くの物件用）
	$city_terms = get_the_terms( $post_id, 'akiya_city' );
	$city_term  = ( is_array( $city_terms ) && $city_terms ) ? $city_terms[0] : null;

	// 価格の数値部と単位を分割（例: 350万円 → 350 + 万円）
	$price_label = kreva_akiya_format_price( $m['price'] );
	$price_num   = $price_label;
	$price_unit  = '';
	if ( preg_match( '/^([\d,\.]+)(.*)$/u', $price_label, $pm ) ) {
		$price_num  = $pm[1];
		$price_unit = $pm[2];
	}

	$shin = kreva_akiya_shinsaishin( $m['build_year'] );
	$tax  = kreva_akiya_fixed_tax_land_est( $m['chika_price'], $m['land_area'] );

	$fmt_dist = function ( $mval ) {
		if ( ! $mval ) {
			return '';
		}
		return $mval >= 1000 ? '（約' . round( $mval / 1000, 1 ) . 'km）' : '（約' . round( $mval ) . 'm）';
	};
	?>
	<div class="kakiya-detailbg">
	<article class="kakiya-detail">

		<nav class="kakiya-bc" aria-label="パンくず">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'akiya' ) ); ?>">空き家検索</a> ›
			<?php if ( $city_term ) : ?>
				<a href="<?php echo esc_url( get_term_link( $city_term ) ); ?>"><?php echo esc_html( $city_term->name ); ?></a> ›
			<?php endif; ?>
			<span><?php the_title(); ?></span>
		</nav>

		<div class="kakiya-thead">
			<div>
				<h1 class="kakiya-dh1"><?php the_title(); ?></h1>
				<div class="kakiya-dsub">
					<span class="kakiya-daddr"><?php echo esc_html( $m['address'] ?: '' ); ?></span>
					<span class="kakiya-dbadges">
						<?php if ( $is_new ) : ?><span class="kakiya-b b-new">NEW</span><?php endif; ?>
						<?php if ( ! empty( $m['is_kreva'] ) ) : ?><span class="kakiya-b b-kv">KREVA</span><?php endif; ?>
						<?php if ( null === $m['price'] ) : ?><span class="kakiya-b b-ask">価格応談</span><?php endif; ?>
						<?php if ( $is_chosei ) : ?><span class="kakiya-b b-adj">調整区域</span><?php endif; ?>
						<?php if ( $hazard_any ) : ?><span class="kakiya-b b-haz">災害想定</span><?php endif; ?>
					</span>
				</div>
			</div>
			<div>
				<div class="kakiya-plabel">価格</div>
				<div class="kakiya-pbig"><?php echo esc_html( $price_num ); ?><span class="kakiya-pyen"><?php echo esc_html( $price_unit ); ?></span></div>
			</div>
		</div>

		<?php if ( $gallery ) : ?>
			<div class="kakiya-gal<?php echo count( $gallery ) < 2 ? ' solo' : ''; ?>" id="kakiya-gal">
				<button type="button" class="kakiya-gt" data-idx="0">
					<img src="<?php echo esc_url( $gallery[0] ); ?>" alt="<?php the_title_attribute(); ?> 写真1" referrerpolicy="no-referrer" loading="eager">
				</button>
				<?php if ( count( $gallery ) > 1 ) : ?>
					<div class="kakiya-gsub">
						<?php foreach ( array_slice( $gallery, 1, 4 ) as $gi => $gurl ) : ?>
							<button type="button" class="kakiya-gt" data-idx="<?php echo (int) $gi + 1; ?>">
								<img src="<?php echo esc_url( $gurl ); ?>" alt="<?php the_title_attribute(); ?> 写真<?php echo (int) $gi + 2; ?>" referrerpolicy="no-referrer" loading="lazy">
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<button type="button" class="kakiya-gall" data-idx="0">すべての写真（<?php echo count( $gallery ); ?>）</button>
			</div>
			<p class="kakiya-cap">画像出典：<?php echo esc_html( $m['source_name'] ?: '掲載元' ); ?>（元ページより直接表示）</p>
		<?php endif; ?>

		<div class="kakiya-cols">
			<main class="kakiya-dmain">

				<section class="kakiya-sec">
					<h2 class="kakiya-sh">物件概要</h2>
					<div class="kakiya-tr"><span class="kakiya-tl">価格</span><span class="kakiya-tv" style="font-weight:700"><?php echo esc_html( $price_label ); ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">所在地</span><span class="kakiya-tv"><?php echo esc_html( $m['address'] ?: '—' ); ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">土地面積</span><span class="kakiya-tv"><?php echo $m['land_area'] ? esc_html( $m['land_area'] ) . '㎡（' . esc_html( round( $m['land_area'] * 0.3025, 1 ) ) . '坪）' : '—'; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">建物面積</span><span class="kakiya-tv"><?php echo $m['building_area'] ? esc_html( $m['building_area'] ) . '㎡（' . esc_html( round( $m['building_area'] * 0.3025, 1 ) ) . '坪）' : '—'; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">間取り</span><span class="kakiya-tv"><?php echo esc_html( $m['layout'] ?: '—' ); ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">構造</span><span class="kakiya-tv"><?php echo esc_html( $m['structure'] ?: '—' ); ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">築年</span><span class="kakiya-tv"><?php echo $m['build_year'] ? esc_html( $m['build_year'] ) . '年' : '—'; ?></span></div>
					<?php if ( $shin ) : ?>
						<div class="kakiya-tr"><span class="kakiya-tl">耐震（推定）</span><span class="kakiya-tv">
							<span class="kakiya-chip <?php echo 'ok' === $shin['level'] ? 'c-ok' : 'c-warn'; ?>"><?php echo esc_html( $shin['label'] ); ?></span>
							<span class="kakiya-anote2"><?php echo esc_html( $shin['note'] ); ?></span>
						</span></div>
					<?php endif; ?>
				</section>

				<section class="kakiya-sec">
					<h2 class="kakiya-sh">用途・規制</h2>
					<div class="kakiya-tr"><span class="kakiya-tl">区域区分</span><span class="kakiya-tv"><?php echo esc_html( $m['kuiki_kubun'] ?: '—' ); ?>
						<?php if ( $is_chosei ) : ?> <span class="kakiya-chip c-warn">要注意</span><?php endif; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">用途地域</span><span class="kakiya-tv"><?php echo esc_html( $m['youto_chiiki'] ?: '指定なし' ); ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">建蔽率／容積率</span><span class="kakiya-tv"><?php echo $m['kenpei'] ? esc_html( $m['kenpei'] ) . '％' : '—'; ?>／<?php echo $m['yoseki'] ? esc_html( $m['yoseki'] ) . '％' : '—'; ?></span></div>
					<?php if ( $is_chosei ) : ?>
						<div class="kakiya-wnote">この物件は市街化調整区域内にあります。建て替え・増改築・用途変更に許可が必要となる場合があります。ご検討の際は事前に自治体の担当窓口または当社までご相談ください。</div>
					<?php endif; ?>
				</section>

				<section class="kakiya-sec">
					<h2 class="kakiya-sh">災害リスク</h2>
					<div class="kakiya-tr"><span class="kakiya-tl">洪水浸水想定</span><span class="kakiya-tv">
						<?php if ( ! empty( $m['hazard_flood'] ) ) : ?><span class="kakiya-chip c-ng">該当<?php echo $m['hazard_flood_depth'] ? '（想定浸水深 ' . esc_html( $m['hazard_flood_depth'] ) . '）' : ''; ?></span>
						<?php else : ?><span class="kakiya-chip c-ok">該当なし</span><?php endif; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">土砂災害警戒区域</span><span class="kakiya-tv">
						<?php echo ! empty( $m['hazard_landslide'] ) ? '<span class="kakiya-chip c-ng">該当</span>' : '<span class="kakiya-chip c-ok">該当なし</span>'; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">津波・高潮</span><span class="kakiya-tv">
						<?php echo ( ! empty( $m['hazard_tsunami'] ) || ! empty( $m['hazard_hightide'] ) ) ? '<span class="kakiya-chip c-ng">該当</span>' : '<span class="kakiya-chip c-ok">該当なし</span>'; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">液状化傾向</span><span class="kakiya-tv"><?php echo esc_html( $m['liquefaction'] ?: '—' ); ?></span></div>
				</section>

				<section class="kakiya-sec">
					<h2 class="kakiya-sh">生活・周辺</h2>
					<div class="kakiya-tr"><span class="kakiya-tl">学区</span><span class="kakiya-tv">
						<?php
						$sch = array_filter( array( $m['school_elem'] ? $m['school_elem'] . '区' : '', $m['school_junior'] ? $m['school_junior'] . '区' : '' ) );
						echo $sch ? esc_html( implode( '・', $sch ) ) : '—';
						?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">最寄り医療機関</span><span class="kakiya-tv"><?php echo $m['nearest_hospital'] ? esc_html( $m['nearest_hospital'] . $fmt_dist( $m['nearest_hospital_m'] ) ) : '—'; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">保育園・幼稚園</span><span class="kakiya-tv"><?php echo $m['nearest_daycare'] ? esc_html( $m['nearest_daycare'] . $fmt_dist( $m['nearest_daycare_m'] ) ) : '—'; ?></span></div>
					<div class="kakiya-tr"><span class="kakiya-tl">避難場所</span><span class="kakiya-tv"><?php echo $m['nearest_shelter'] ? esc_html( $m['nearest_shelter'] . $fmt_dist( $m['nearest_shelter_m'] ) ) : '—'; ?></span></div>
					<?php if ( $m['future_pop'] ) : ?>
						<div class="kakiya-tr"><span class="kakiya-tl">将来人口（推計）</span><span class="kakiya-tv"><?php echo esc_html( $m['future_pop'] ); ?></span></div>
					<?php endif; ?>
				</section>

				<?php if ( get_the_content() ) : ?>
					<section class="kakiya-sec"><h2 class="kakiya-sh">説明</h2><p class="kakiya-dp"><?php echo wp_kses_post( get_the_content() ); ?></p></section>
				<?php endif; ?>

				<section class="kakiya-sec">
					<h2 class="kakiya-sh">周辺地図</h2>
					<div id="kakiya-map-single" role="application" aria-label="物件周辺の地図とハザード"></div>
					<p class="kakiya-anote2" style="margin-top:8px">地図右上のレイヤ切替で 洪水／土砂／津波／高潮 のハザードを重ねて確認できます。所在地は概ねの位置です。</p>
				</section>
			</main>

			<aside class="kakiya-daside">
				<div class="kakiya-sec kakiya-sec-sm">
					<h2 class="kakiya-sh">相場の目安</h2>
					<div class="kakiya-tr"><span class="kakiya-tl">最寄り地価</span><span class="kakiya-aval">
						<?php echo $m['chika_price'] ? esc_html( round( $m['chika_price'] / 10000, 1 ) ) . '<span class="kakiya-aunit">万円/㎡</span>' : '—'; ?></span></div>
					<?php if ( $tax ) : ?>
						<div class="kakiya-tr"><span class="kakiya-tl">固定資産税（概算）</span><span class="kakiya-aval">約<?php echo esc_html( round( $tax / 10000, 1 ) ); ?><span class="kakiya-aunit">万円/年</span></span></div>
					<?php endif; ?>
					<p class="kakiya-anote2">最寄りの地価公示・都道府県地価調査からの概算です（土地分のみ・住宅用地特例1/6・税率1.4%で試算）。実際の税額は自治体の課税明細でご確認ください。</p>
				</div>

				<div class="kakiya-actab">
					<h2 class="kakiya-sh" style="margin-bottom:6px">この物件について相談する</h2>
					<p class="kakiya-dp" style="font-size:12.5px;margin-bottom:14px">現地確認の代行・調整区域の手続き・リフォーム見積りなど、岡山の空き家に詳しいスタッフが無料でご相談を承ります。</p>
					<a class="kakiya-cta2" style="display:block;text-align:center" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームへ</a>
				</div>

				<?php if ( $m['source_name'] || $m['source_url'] ) : ?>
					<div class="kakiya-sec kakiya-sec-sm">
						<h2 class="kakiya-sh" style="font-size:14px">出典・最終確認</h2>
						<p class="kakiya-anote2" style="margin-top:0">出典：<?php echo esc_html( $m['source_name'] ?: '—' ); ?><?php echo $m['source_id'] ? '（No.' . esc_html( $m['source_id'] ) . '）' : ''; ?><br>
						<?php echo $m['last_checked'] ? '最終確認日：' . esc_html( $m['last_checked'] ) . '<br>' : ''; ?>掲載情報は取得時点のものです。</p>
						<?php if ( $m['source_url'] ) : ?>
							<a class="kakiya-slink" href="<?php echo esc_url( $m['source_url'] ); ?>" target="_blank" rel="noopener nofollow">元ページを見る ↗</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<section class="kakiya-nearwrap">
			<h2 class="kakiya-dh1" style="font-size:18px">近くの物件</h2>
			<div id="kakiya-nearby" class="kakiya-near3"></div>
		</section>
	</article>
	</div>
	<?php
endwhile;

kreva_akiya_footer();
