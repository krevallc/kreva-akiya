<?php
/**
 * kreva.co.jp HOME — Claude Design（Kreva Home）準拠。
 * ヒーロー（実績チップ・地図ビジュアル）／4事業カード／空き家フラッグシップ（新着3件）／
 * MTKNダークセクション／会社概要サマリ／お問い合わせCTA。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 掲載統計（成約済を除く公開物件数・自治体数）
$stat_q = new WP_Query( array(
	'post_type'      => 'akiya',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'tax_query'      => array( array( 'taxonomy' => 'akiya_status', 'field' => 'name', 'terms' => array( '成約済' ), 'operator' => 'NOT IN' ) ),
) );
$akiya_count = (int) $stat_q->found_posts;
$city_count  = (int) wp_count_terms( array( 'taxonomy' => 'akiya_city', 'hide_empty' => true ) );
$archive_url = get_post_type_archive_link( 'akiya' );

// 新着3件（写真あり・成約済を除く）
$new_q = new WP_Query( array(
	'post_type'      => 'akiya',
	'post_status'    => 'publish',
	'posts_per_page' => 3,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'meta_query'     => array( array( 'key' => kreva_akiya_meta_key( 'image_url' ), 'value' => '', 'compare' => '!=' ) ),
	'tax_query'      => array( array( 'taxonomy' => 'akiya_status', 'field' => 'name', 'terms' => array( '成約済' ), 'operator' => 'NOT IN' ) ),
) );

// タイトル一致でページURLを引く（無ければ null）
$page_url = function ( $title ) {
	$q = new WP_Query( array( 'post_type' => 'page', 'title' => $title, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	return $q->posts ? get_permalink( $q->posts[0] ) : null;
};
$url_fudosan = $page_url( '不動産事業' );
$url_web     = $page_url( 'Web制作・システム開発' );
$url_company = $page_url( '会社概要' ) ?: home_url( '/company/' );

// ヒーロー用：岡山県の地図タイル（地理院・商用可）
$hero_tiles = function () {
	$lat = 34.75; $lng = 133.78; $z = 9;
	$n  = pow( 2, $z );
	$px = ( $lng + 180 ) / 360 * $n * 256;
	$py = ( 1 - asinh( tan( deg2rad( $lat ) ) ) / M_PI ) / 2 * $n * 256;
	$half_w = 330; $half_h = 250;
	$out = '';
	for ( $tx = (int) floor( ( $px - $half_w ) / 256 ); $tx <= floor( ( $px + $half_w ) / 256 ); $tx++ ) {
		for ( $ty = (int) floor( ( $py - $half_h ) / 256 ); $ty <= floor( ( $py + $half_h ) / 256 ); $ty++ ) {
			$out .= '<img src="https://cyberjapandata.gsi.go.jp/xyz/pale/' . $z . '/' . $tx . '/' . $ty . '.png" alt="" loading="lazy" style="position:absolute;left:' . ( $tx * 256 - $px ) . 'px;top:' . ( $ty * 256 - $py ) . 'px;width:256px;height:256px;max-width:none">';
		}
	}
	return $out;
};

kreva_akiya_header();
?>
<div class="kakiya-home">

	<section class="kh-hero">
		<div class="kh-wrap kh-hgrid">
			<div>
				<div class="kh-kicker">KREVA LLC — OSAKA / OKAYAMA / AMSTERDAM</div>
				<h1 class="kh-h1">大阪と岡山の不動産、<br>日本のものづくりを世界へ。</h1>
				<p class="kh-lead">合同会社クレバは、大阪府下の不動産運営管理と岡山県の空き家再生を軸に、自治体空き家バンクを集約した検索サービスの運営、国産デニムの欧州輸出、AIを活用したWeb制作までを手がける会社です。</p>
				<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( $archive_url ); ?>">空き家を検索する →</a>
				<div class="kh-stats">
					<span class="kh-stat"><span class="kh-sv"><?php echo esc_html( number_format( $akiya_count ) ); ?></span>件の掲載空き家</span>
					<span class="kh-stat"><span class="kh-sv"><?php echo esc_html( $city_count ); ?></span>自治体を毎週集約</span>
					<span class="kh-stat"><span class="kh-sv">2018</span>年設立</span>
				</div>
			</div>
			<a class="kh-heromap" href="<?php echo esc_url( $archive_url ); ?>" aria-label="岡山県の空き家検索へ">
				<div class="kh-heromap-inner"><?php echo $hero_tiles(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<span class="kh-heromap-label">岡山県の空き家マップを見る →</span>
				<span class="kakiya-mapthumb-credit">地図: 地理院タイル</span>
			</a>
		</div>
	</section>

	<section class="kh-sec" id="kh-biz">
		<div class="kh-wrap">
			<div class="kh-shead"><div><h2 class="kh-h2">事業内容</h2><p class="kh-sdesc">不動産を軸に、テクノロジーとものづくりの4事業を展開しています。</p></div></div>
			<div class="kh-biz">
				<a class="kh-bcard" href="<?php echo esc_url( $url_fudosan ?: $url_company ); ?>">
					<span class="kh-bno">01 — REAL ESTATE</span>
					<span class="kh-bt">不動産投資・運営管理／空き家再生</span>
					<p class="kh-bp">大阪府下の不動産投資と所有物件の運営管理。岡山県では空き家を購入し、リノベーション・民泊・再販による再生に取り組んでいます。</p>
					<span class="kh-bgo">詳しく見る →</span>
				</a>
				<a class="kh-bcard" href="<?php echo esc_url( $archive_url ); ?>">
					<span class="kh-bno">02 — AKIYA SEARCH</span>
					<span class="kh-bt">岡山県の空き家検索サービス</span>
					<p class="kh-bp">自治体空き家バンクを毎週自動集約。災害リスク・市街化調整区域・学区・地価の独自情報つきで公開します。</p>
					<span class="kh-bgo">空き家を検索する →</span>
				</a>
				<a class="kh-bcard" href="#kh-mtkn">
					<span class="kh-bno">03 — MTKN</span>
					<span class="kh-bt">日本製ヴィンテージ・デニム輸出</span>
					<p class="kh-bp">日本製ヴィンテージと国産デニムを、アムステルダム拠点から欧州へ輸出販売。日本のものづくり文化を発信します。</p>
					<span class="kh-bgo">MTKNについて →</span>
				</a>
				<a class="kh-bcard" href="<?php echo esc_url( $url_web ?: home_url( '/contact/' ) ); ?>">
					<span class="kh-bno">04 — WEB / SYSTEM</span>
					<span class="kh-bt">Web制作・システム開発</span>
					<p class="kh-bp">AIを活用し、ホームページ制作・EC・在庫管理システムを安価かつ迅速に構築。中小事業者のデジタル化を支援します。</p>
					<span class="kh-bgo">詳しく見る →</span>
				</a>
			</div>
		</div>
	</section>

	<section class="kh-sec kh-flag">
		<div class="kh-wrap">
			<div class="kh-shead">
				<div>
					<div class="kh-kicker">FLAGSHIP SERVICE</div>
					<h2 class="kh-h2">岡山県の空き家検索</h2>
					<p class="kh-sdesc">自治体の空き家バンク<?php echo esc_html( $city_count ); ?>自治体・<?php echo esc_html( number_format( $akiya_count ) ); ?>物件をひとつの地図で。移住・二拠点・投資の物件探しを、独自データで支えます。</p>
				</div>
				<a class="kh-alink" href="<?php echo esc_url( $archive_url ); ?>">空き家を検索する →</a>
			</div>
			<div class="kh-feats">
				<div class="kh-feat"><h3 class="kh-fh">毎週自動で最新に</h3><p class="kh-fp">県内<?php echo esc_html( $city_count ); ?>自治体の空き家バンクを毎週自動集約。掲載終了もすみやかに反映されます。</p></div>
				<div class="kh-feat"><h3 class="kh-fh">物件ごとの独自情報</h3><p class="kh-fp">災害リスク・市街化調整区域の該当有無・学区・周辺地価を全物件に付与。検討の判断材料を揃えました。</p></div>
				<div class="kh-feat"><h3 class="kh-fh">地図でそのまま探せる</h3><p class="kh-fp">価格つきピンの地図と写真カードで直感的に比較。条件フィルタで絞り込みもできます。</p></div>
			</div>
			<?php if ( $new_q->have_posts() ) : ?>
				<div class="kh-cards3">
					<?php
					while ( $new_q->have_posts() ) :
						$new_q->the_post();
						$hm     = kreva_akiya_get_meta( get_the_ID() );
						$hcity  = get_the_terms( get_the_ID(), 'akiya_city' );
						$hcity  = ( is_array( $hcity ) && $hcity ) ? $hcity[0]->name : '';
						$is_new = ( time() - get_post_time( 'U', true ) ) < 14 * DAY_IN_SECONDS;
						?>
						<a class="kakiya-card2" href="<?php the_permalink(); ?>">
							<div class="kakiya-ph">
								<img class="kakiya-ph1" loading="lazy" referrerpolicy="no-referrer" alt="" src="<?php echo esc_url( $hm['image_url'] ); ?>" onerror="this.style.display='none'">
								<div class="kakiya-bdg">
									<?php if ( $is_new ) : ?><span class="kakiya-b b-new">NEW</span><?php endif; ?>
									<?php if ( ! empty( $hm['is_kreva'] ) ) : ?><span class="kakiya-b b-kv">KREVA</span><?php endif; ?>
								</div>
							</div>
							<div class="kakiya-cb">
								<div class="kakiya-price2" style="font-size:20px"><?php echo esc_html( kreva_akiya_format_price( $hm['price'] ) ); ?></div>
								<div class="kakiya-ttl2"><?php the_title(); ?></div>
								<div class="kakiya-meta2"><?php echo esc_html( $hcity . ( $hm['land_area'] ? '｜土地' . round( $hm['land_area'] ) . '㎡' : '' ) . ( $hm['build_year'] ? '・築' . $hm['build_year'] . '年' : '' ) ); ?></div>
							</div>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php endif; ?>
			<div class="kh-fcta"><a class="kakiya-cta2 kh-big" href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( number_format( $akiya_count ) ); ?>件から空き家を検索する →</a></div>
		</div>
	</section>

	<section class="kh-sec kh-mtkn" id="kh-mtkn">
		<div class="kh-wrap kh-mgrid">
			<div class="kh-mph" aria-hidden="true"></div>
			<div>
				<div class="kh-kicker kh-kicker-l">MTKN — AMSTERDAM</div>
				<h2 class="kh-h2 kh-h2-w">日本のデニムを、欧州へ。</h2>
				<div class="kh-mchips"><span class="kh-mc">日本製ヴィンテージ</span><span class="kh-mc">国産デニム</span><span class="kh-mc">アムステルダム拠点</span></div>
				<p class="kh-mp">MTKNは、日本製ヴィンテージと国産デニムをアムステルダムを拠点に欧州へ輸出販売するプロジェクトです。長く受け継がれてきた日本のものづくり文化を、確かな品質とともにヨーロッパのファンへ届けています。</p>
				<a class="kh-ghost" href="https://mtkn.nl" target="_blank" rel="noopener">mtkn.nl を見る ↗</a>
			</div>
		</div>
	</section>

	<section class="kh-sec" id="kh-about">
		<div class="kh-wrap kh-about">
			<div>
				<h2 class="kh-h2">会社概要</h2>
				<p class="kh-sdesc">大阪・岡山・アムステルダムの3拠点で事業を展開しています。</p>
				<p style="margin-top:20px"><a class="kh-alink" href="<?php echo esc_url( $url_company ); ?>">会社概要を見る →</a></p>
			</div>
			<div>
				<div class="kakiya-tr"><span class="kakiya-tl">社名</span><span class="kakiya-tv">合同会社クレバ（KREVA LLC）</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">設立</span><span class="kakiya-tv">2018年</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">本社</span><span class="kakiya-tv">大阪府高槻市</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">拠点</span><span class="kakiya-tv">大阪（高槻）・岡山・アムステルダム</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">事業内容</span><span class="kakiya-tv">不動産投資・運営管理／空き家の再生・検索サービス運営／日本製ヴィンテージ・国産デニムの輸出販売（MTKN）／Web制作・システム開発</span></div>
			</div>
		</div>
	</section>

	<section class="kh-sec" style="padding-top:0">
		<div class="kh-wrap">
			<div class="kakiya-ctab">
				<div>
					<h2 class="kakiya-ctah" style="font-size:22px">お問い合わせ</h2>
					<p class="kakiya-ctap">事業に関するご相談・ご質問は、お問い合わせフォームよりお気軽にご連絡ください。</p>
				</div>
				<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせフォームへ</a>
			</div>
		</div>
	</section>
</div>
<?php
kreva_akiya_footer();
