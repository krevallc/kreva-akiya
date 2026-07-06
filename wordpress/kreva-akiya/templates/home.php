<?php
/**
 * kreva.co.jp HOME — Claude Design（Kreva Home）準拠。
 *
 * 空き家検索は正式公開まで非表示（既定）。設定 → 一般 →「空き家検索の公開」をONにすると
 * ヒーローCTA・実績チップ・フラッグシップセクション・事業カード02が表示される。
 * 非公開時は従来の3事業（不動産管理業／欧州向け衣料輸出業／Web制作・システム開発）のみ。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$akiya_live = (bool) apply_filters( 'kreva_home_akiya_live', get_option( 'kreva_akiya_home_live', 0 ) );
$hide_ams   = (bool) apply_filters( 'kreva_hide_amsterdam', false ); // アムステルダム表記の非表示スイッチ

$archive_url = get_post_type_archive_link( 'akiya' );
$akiya_count = 0;
$city_count  = 0;
$new_q       = null;

if ( $akiya_live ) {
	$stat_q = new WP_Query( array(
		'post_type'      => 'akiya',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'tax_query'      => array( array( 'taxonomy' => 'akiya_status', 'field' => 'name', 'terms' => array( '成約済' ), 'operator' => 'NOT IN' ) ),
	) );
	$akiya_count = (int) $stat_q->found_posts;
	$city_count  = (int) wp_count_terms( array( 'taxonomy' => 'akiya_city', 'hide_empty' => true ) );

	$new_q = new WP_Query( array(
		'post_type'      => 'akiya',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( array( 'key' => kreva_akiya_meta_key( 'image_url' ), 'value' => '', 'compare' => '!=' ) ),
		'tax_query'      => array( array( 'taxonomy' => 'akiya_status', 'field' => 'name', 'terms' => array( '成約済' ), 'operator' => 'NOT IN' ) ),
	) );
}

// タイトル一致でページURLを引く（無ければ null）
$page_url = function ( $title ) {
	$q = new WP_Query( array( 'post_type' => 'page', 'title' => $title, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	return $q->posts ? get_permalink( $q->posts[0] ) : null;
};
$url_fudosan = $page_url( '不動産事業' );
$url_web     = $page_url( 'Web制作・システム開発' );
$url_company = $page_url( '会社概要' ) ?: home_url( '/company/' );

// ヒーロー用：地図タイル（地理院・商用可）※公開時のみ使用
$hero_tiles = function () {
	$lat = 34.75; $lng = 133.78; $z = 9;
	$n  = pow( 2, $z );
	$px = ( $lng + 180 ) / 360 * $n * 256;
	$py = ( 1 - asinh( tan( deg2rad( $lat ) ) ) / M_PI ) / 2 * $n * 256;
	$out = '';
	for ( $tx = (int) floor( ( $px - 330 ) / 256 ); $tx <= floor( ( $px + 330 ) / 256 ); $tx++ ) {
		for ( $ty = (int) floor( ( $py - 250 ) / 256 ); $ty <= floor( ( $py + 250 ) / 256 ); $ty++ ) {
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
				<?php if ( $akiya_live ) : ?>
					<div class="kh-kicker">KREVA LLC — OSAKA / OKAYAMA / AMSTERDAM</div>
					<h1 class="kh-h1">大阪と岡山の不動産、<br>日本のものづくりを世界へ。</h1>
					<p class="kh-lead">合同会社クレバは、大阪府下の不動産運営管理と岡山県の空き家再生を軸に、自治体空き家バンクを集約した検索サービスの運営、国産デニムの欧州輸出、AIを活用したWeb制作までを手がける会社です。</p>
					<a class="kakiya-cta2 kh-big" href="<?php echo esc_url( $archive_url ); ?>">空き家を検索する →</a>
					<div class="kh-stats">
						<span class="kh-stat"><span class="kh-sv"><?php echo esc_html( number_format( $akiya_count ) ); ?></span>件の掲載空き家</span>
						<span class="kh-stat"><span class="kh-sv"><?php echo esc_html( $city_count ); ?></span>自治体を毎週集約</span>
						<span class="kh-stat"><span class="kh-sv">2018</span>年設立</span>
					</div>
				<?php else : ?>
					<div class="kh-kicker">KREVA LLC — OSAKA / OKAYAMA<?php echo $hide_ams ? '' : ' / AMSTERDAM'; ?></div>
					<h1 class="kh-h1">大阪の不動産と、<br>日本のものづくりを世界へ。</h1>
					<p class="kh-lead">合同会社クレバは、大阪府下における不動産投資・所有物件の運営管理を軸に、日本製衣料の欧州輸出（MTKN）、AIを活用したWeb制作・システム開発を手がける会社です。</p>
					<div class="kh-stats">
						<span class="kh-stat"><span class="kh-sv">2018</span>年設立</span>
						<span class="kh-stat"><span class="kh-sv"><?php echo $hide_ams ? '2' : '3'; ?></span>拠点（大阪・岡山<?php echo $hide_ams ? '' : '・アムステルダム'; ?>）</span>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( $akiya_live ) : ?>
				<a class="kh-heromap" href="<?php echo esc_url( $archive_url ); ?>" aria-label="岡山県の空き家検索へ">
					<div class="kh-heromap-inner"><?php echo $hero_tiles(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<span class="kh-heromap-label">岡山県の空き家マップを見る →</span>
					<span class="kakiya-mapthumb-credit">地図: 地理院タイル</span>
				</a>
			<?php else : ?>
				<?php
				// ヒーロー写真（Denim Days・アムステルダムのMTKNブース）。差し替えは下記フィルタで:
				// add_filter( 'kreva_home_hero_image', fn() => 'https://.../photo.jpg' );
				$hero_img = apply_filters( 'kreva_home_hero_image', 'https://kreva.co.jp/wp-content/uploads/2026/07/denim-days-mtkn.jpg' );
				?>
				<div class="kh-heroph"><img src="<?php echo esc_url( $hero_img ); ?>" alt="Denim Days Amsterdam — MTKNブース"></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="kh-sec" id="kh-biz">
		<div class="kh-wrap">
			<div class="kh-shead"><div><h2 class="kh-h2">事業内容</h2><p class="kh-sdesc"><?php echo $akiya_live ? '不動産を軸に、テクノロジーとものづくりの4事業を展開しています。' : '不動産・輸出・Web開発の3事業を展開しています。'; ?></p></div></div>
			<div class="<?php echo $akiya_live ? 'kh-biz' : 'kh-biz kh-biz3'; ?>">
				<a class="kh-bcard" href="<?php echo esc_url( $url_fudosan ?: $url_company ); ?>">
					<span class="kh-bno">01 — REAL ESTATE</span>
					<span class="kh-bt">不動産管理業</span>
					<p class="kh-bp">大阪府下での不動産投資及び所有物件の運営・管理を行っております。</p>
					<span class="kh-bgo">詳しく見る →</span>
				</a>
				<?php if ( $akiya_live ) : ?>
					<a class="kh-bcard" href="<?php echo esc_url( $archive_url ); ?>">
						<span class="kh-bno">02 — AKIYA SEARCH</span>
						<span class="kh-bt">岡山県の空き家検索サービス</span>
						<p class="kh-bp">自治体空き家バンクを毎週自動集約。災害リスク・市街化調整区域・学区・地価の独自情報つきで公開します。</p>
						<span class="kh-bgo">空き家を検索する →</span>
					</a>
				<?php endif; ?>
				<a class="kh-bcard" href="#kh-mtkn">
					<span class="kh-bno"><?php echo $akiya_live ? '03' : '02'; ?> — MTKN</span>
					<span class="kh-bt">欧州向け衣料輸出業</span>
					<p class="kh-bp">ヨーロッパ諸国における日本製衣料製品の輸出及び販売。日本各地の衣料製造企業と提携し、日本発祥の関連情報や文化の発信を行っています。</p>
					<span class="kh-bgo">MTKNについて →</span>
				</a>
				<a class="kh-bcard" href="<?php echo esc_url( $url_web ?: home_url( '/contact/' ) ); ?>">
					<span class="kh-bno"><?php echo $akiya_live ? '04' : '03'; ?> — WEB / SYSTEM</span>
					<span class="kh-bt">Web制作・システム開発</span>
					<p class="kh-bp">AIを活用し、ホームページ制作・EC・在庫管理システムを安価かつ迅速に構築。中小事業者のデジタル化を支援します。</p>
					<span class="kh-bgo">詳しく見る →</span>
				</a>
			</div>
		</div>
	</section>

	<?php if ( $akiya_live ) : ?>
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
			<?php if ( $new_q && $new_q->have_posts() ) : ?>
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
	<?php endif; ?>

	<section class="kh-sec kh-mtkn" id="kh-mtkn">
		<div class="kh-wrap kh-mgrid">
			<?php $mtkn_img = apply_filters( 'kreva_home_mtkn_image', 'https://kreva.co.jp/wp-content/uploads/2026/07/mtkn-top-capture.jpg' ); ?>
			<a class="kh-mph" href="https://mtkn.nl" target="_blank" rel="noopener" aria-label="mtkn.nl を見る">
				<img src="<?php echo esc_url( $mtkn_img ); ?>" alt="mtkn.nl — Japanese Selvedge Denim & Vintage" loading="lazy">
			</a>
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
				<p class="kh-sdesc"><?php echo $hide_ams ? '大阪・岡山を拠点に事業を展開しています。' : '大阪・岡山・アムステルダムの3拠点で事業を展開しています。'; ?></p>
				<p style="margin-top:20px"><a class="kh-alink" href="<?php echo esc_url( $url_company ); ?>">会社概要を見る →</a></p>
			</div>
			<div>
				<div class="kakiya-tr"><span class="kakiya-tl">社名</span><span class="kakiya-tv">合同会社クレバ（KREVA LLC）</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">設立</span><span class="kakiya-tv">2018年</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">本社</span><span class="kakiya-tv">大阪府高槻市</span></div>
				<div class="kakiya-tr"><span class="kakiya-tl">拠点</span><span class="kakiya-tv">大阪（高槻）・岡山<?php echo $hide_ams ? '' : '・アムステルダム'; ?></span></div>
				<?php if ( $akiya_live ) : ?>
					<div class="kakiya-tr"><span class="kakiya-tl">事業内容</span><span class="kakiya-tv">不動産投資・運営管理／空き家の再生・検索サービス運営／日本製ヴィンテージ・国産デニムの輸出販売（MTKN）／Web制作・システム開発</span></div>
				<?php else : ?>
					<div class="kakiya-tr"><span class="kakiya-tl">事業内容</span><span class="kakiya-tv">不動産の売買及び賃貸業／海外向け衣料輸出業（MTKN）／Web制作・システム開発</span></div>
				<?php endif; ?>
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
