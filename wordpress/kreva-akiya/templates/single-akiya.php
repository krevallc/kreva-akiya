<?php
/**
 * 空き家 詳細テンプレート（single）。
 * スペック表・規制(調整区域/用途地域)・災害ハザード地図・相場(地価)・出所・KREVA相談CTA。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

kreva_akiya_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$m       = kreva_akiya_get_meta( $post_id );

	$hazard_labels = array(
		'hazard_flood'     => '洪水浸水想定',
		'hazard_landslide' => '土砂災害警戒区域',
		'hazard_tsunami'   => '津波浸水想定',
		'hazard_hightide'  => '高潮浸水想定',
	);
	$hazards_hit = array();
	foreach ( $hazard_labels as $k => $label ) {
		if ( ! empty( $m[ $k ] ) ) {
			$hazards_hit[] = $label;
		}
	}
	?>
	<article class="kakiya-single">
		<?php if ( ! empty( $m['is_kreva'] ) ) : ?>
			<div class="kakiya-badge">KREVA セレクト物件<?php echo $m['kreva_use'] ? '（' . esc_html( $m['kreva_use'] ) . '）' : ''; ?></div>
		<?php endif; ?>

		<h1 class="kakiya-title"><?php the_title(); ?></h1>
		<p class="kakiya-address"><?php echo esc_html( $m['address'] ?: '' ); ?></p>
		<p class="kakiya-price-lg"><?php echo esc_html( kreva_akiya_format_price( $m['price'] ) ); ?></p>

		<?php
		// 画像ギャラリー（詳細情報の上）。image_urls(JSON) → 無ければ image_url 単発
		$gallery = array();
		if ( ! empty( $m['image_urls'] ) ) {
			$decoded = json_decode( $m['image_urls'], true );
			if ( is_array( $decoded ) ) {
				$gallery = array_values( array_filter( array_map( 'esc_url_raw', $decoded ) ) );
			}
		}
		if ( ! $gallery && ! empty( $m['image_url'] ) ) {
			$gallery = array( $m['image_url'] );
		}
		?>
		<?php if ( $gallery ) : ?>
			<div class="kakiya-gallery">
				<?php foreach ( $gallery as $gi => $gurl ) : ?>
					<a class="kakiya-gallery-item<?php echo 0 === $gi ? ' is-main' : ''; ?>"
						href="<?php echo esc_url( $gurl ); ?>" target="_blank" rel="noopener nofollow">
						<img src="<?php echo esc_url( $gurl ); ?>" alt="<?php the_title_attribute(); ?> 写真<?php echo (int) $gi + 1; ?>"
							loading="lazy" referrerpolicy="no-referrer"
							onerror="this.closest('.kakiya-gallery-item').style.display='none'">
					</a>
				<?php endforeach; ?>
			</div>
			<p class="kakiya-note kakiya-gallery-note">画像出典：<?php echo esc_html( $m['source_name'] ?: '掲載元' ); ?>（元ページより直接表示・クリックで拡大）</p>
		<?php endif; ?>

		<div class="kakiya-single-grid">
			<div class="kakiya-single-left">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="kakiya-photo"><?php the_post_thumbnail( 'large' ); ?></div>
				<?php endif; ?>

				<section>
					<h2>物件概要</h2>
					<table class="kakiya-spec">
						<tbody>
							<tr><th>価格</th><td><?php echo esc_html( kreva_akiya_format_price( $m['price'] ) ); ?></td></tr>
							<tr><th>所在地</th><td><?php echo esc_html( $m['address'] ?: '—' ); ?></td></tr>
							<tr><th>土地面積</th><td><?php echo $m['land_area'] ? esc_html( $m['land_area'] ) . ' m²' : '—'; ?></td></tr>
							<tr><th>建物面積</th><td><?php echo $m['building_area'] ? esc_html( $m['building_area'] ) . ' m²' : '—'; ?></td></tr>
							<tr><th>間取り</th><td><?php echo esc_html( $m['layout'] ?: '—' ); ?></td></tr>
							<tr><th>構造</th><td><?php echo esc_html( $m['structure'] ?: '—' ); ?></td></tr>
							<tr><th>築年</th><td><?php echo $m['build_year'] ? esc_html( $m['build_year'] ) . ' 年' : '—'; ?></td></tr>
							<?php $shin = kreva_akiya_shinsaishin( $m['build_year'] ); ?>
							<?php if ( $shin ) : ?>
								<tr><th>耐震(推定)</th><td>
									<span class="kakiya-<?php echo 'ok' === $shin['level'] ? 'hazard-none' : 'warn'; ?>"><?php echo esc_html( $shin['label'] ); ?></span>
									<span class="kakiya-note"><?php echo esc_html( $shin['note'] ); ?></span>
								</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</section>

				<section>
					<h2>用途・規制</h2>
					<table class="kakiya-spec">
						<tbody>
							<tr><th>区域区分</th><td><?php echo esc_html( $m['kuiki_kubun'] ?: '—' ); ?>
								<?php if ( $m['kuiki_kubun'] && false !== strpos( $m['kuiki_kubun'], '調整' ) ) : ?>
									<span class="kakiya-warn">※市街化調整区域：原則新築・建替に制限。要確認</span>
								<?php endif; ?>
							</td></tr>
							<tr><th>用途地域</th><td><?php echo esc_html( $m['youto_chiiki'] ?: '—' ); ?></td></tr>
							<tr><th>建蔽率/容積率</th><td>
								<?php echo $m['kenpei'] ? esc_html( $m['kenpei'] ) . '%' : '—'; ?> /
								<?php echo $m['yoseki'] ? esc_html( $m['yoseki'] ) . '%' : '—'; ?>
							</td></tr>
						</tbody>
					</table>
				</section>

				<section>
					<h2>災害リスク</h2>
					<?php if ( $hazards_hit ) : ?>
						<p class="kakiya-hazard-hit">該当あり：<strong><?php echo esc_html( implode( '、', $hazards_hit ) ); ?></strong>
							<?php echo $m['hazard_flood_depth'] ? '（洪水想定浸水深：' . esc_html( $m['hazard_flood_depth'] ) . '）' : ''; ?>
						</p>
					<?php else : ?>
						<p class="kakiya-hazard-none">主要ハザードの指定区域に該当なし（下の地図で周辺も確認できます）。</p>
					<?php endif; ?>
					<?php if ( $m['liquefaction'] ) : ?>
						<p>液状化傾向：<strong><?php echo esc_html( $m['liquefaction'] ); ?></strong></p>
					<?php endif; ?>
					<p class="kakiya-note">地図右上のレイヤ切替で 洪水／土砂／津波／高潮 を重ねて確認できます。</p>
				</section>

				<section>
					<h2>生活・周辺</h2>
					<table class="kakiya-spec">
						<tbody>
							<tr><th>学区</th><td>
								<?php
								$sch = array_filter( array(
									$m['school_elem'] ? '小: ' . $m['school_elem'] : '',
									$m['school_junior'] ? '中: ' . $m['school_junior'] : '',
								) );
								echo $sch ? esc_html( implode( '／', $sch ) ) : '—';
								?>
							</td></tr>
							<tr><th>最寄り医療機関</th><td>
								<?php echo $m['nearest_hospital'] ? esc_html( $m['nearest_hospital'] ) : '—'; ?>
								<?php echo $m['nearest_hospital_m'] ? '（約' . esc_html( round( $m['nearest_hospital_m'] ) ) . 'm）' : ''; ?>
							</td></tr>
							<tr><th>最寄り保育/幼稚園</th><td>
								<?php echo $m['nearest_daycare'] ? esc_html( $m['nearest_daycare'] ) : '—'; ?>
								<?php echo $m['nearest_daycare_m'] ? '（約' . esc_html( round( $m['nearest_daycare_m'] ) ) . 'm）' : ''; ?>
							</td></tr>
							<tr><th>最寄り避難場所</th><td>
								<?php echo $m['nearest_shelter'] ? esc_html( $m['nearest_shelter'] ) : '—'; ?>
								<?php echo $m['nearest_shelter_m'] ? '（約' . esc_html( round( $m['nearest_shelter_m'] ) ) . 'm）' : ''; ?>
							</td></tr>
							<?php if ( $m['future_pop'] ) : ?>
								<tr><th>将来人口(参考)</th><td><?php echo esc_html( $m['future_pop'] ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
					<p class="kakiya-note">学区・周辺施設・将来人口は国土交通省 不動産情報ライブラリのデータに基づく参考値です。</p>
				</section>

				<?php if ( get_the_content() ) : ?>
					<section><h2>説明</h2><div class="kakiya-body"><?php the_content(); ?></div></section>
				<?php endif; ?>
			</div>

			<aside class="kakiya-single-right">
				<div id="kakiya-map-single" role="application" aria-label="物件周辺の地図とハザード"></div>

				<div class="kakiya-relard">
					<h3>相場の目安</h3>
					<?php if ( $m['chika_price'] ) : ?>
						<p>最寄りの地価（公示/調査）：<strong><?php echo esc_html( number_format( $m['chika_price'] ) ); ?> 円/m²</strong>
							<?php echo $m['chika_dist_m'] ? '（約' . esc_html( round( $m['chika_dist_m'] ) ) . 'm先）' : ''; ?></p>
					<?php else : ?>
						<p>周辺の地価データは準備中です。</p>
					<?php endif; ?>
					<?php $tax = kreva_akiya_fixed_tax_land_est( $m['chika_price'], $m['land_area'] ); ?>
					<?php if ( $tax ) : ?>
						<p>固定資産税（土地分の概算）：<strong>年 約<?php echo esc_html( number_format( $tax ) ); ?> 円</strong>
							<span class="kakiya-note">住宅用地特例1/6・標準税率1.4%で試算。建物分・都市計画税は含まず、桁感の目安です。</span></p>
					<?php endif; ?>
				</div>

				<div class="kakiya-cta-box">
					<h3>この物件についてKREVAに相談</h3>
					<p>購入・見学・活用（リノベ/民泊）のご相談を承ります。</p>
					<a class="kakiya-btn kakiya-btn-primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームで相談する</a>
				</div>

				<?php if ( $m['source_name'] || $m['source_url'] ) : ?>
					<div class="kakiya-source">
						<h3>出典・最終確認</h3>
						<p>
							出典：<?php echo esc_html( $m['source_name'] ?: '—' ); ?><br>
							<?php if ( $m['source_url'] ) : ?>
								<a href="<?php echo esc_url( $m['source_url'] ); ?>" target="_blank" rel="noopener nofollow"><strong>物件の写真・最新情報を元ページで見る ↗</strong></a><br>
							<?php endif; ?>
							<?php echo $m['last_checked'] ? '最終確認日：' . esc_html( $m['last_checked'] ) : ''; ?>
						</p>
						<p class="kakiya-note">最新・正確な情報は必ず元の掲載元でご確認ください。</p>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</article>
	<?php
endwhile;

kreva_akiya_footer();
