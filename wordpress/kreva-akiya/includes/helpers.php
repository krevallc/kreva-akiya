<?php
/**
 * 共通ヘルパー：メタキー定義・整形・ハザードタイル定義。
 * メタキーは単一の真実の源としてここに集約する。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 物件メタのキー一覧と型。register_post_meta と REST の入出力で共用。
 * type は REST スキーマ用（number/string/boolean）。
 */
function kreva_akiya_meta_schema() {
	return array(
		// 基本
		'price'          => array( 'type' => 'number', 'label' => '価格(円)' ),
		'land_area'      => array( 'type' => 'number', 'label' => '土地面積(m²)' ),
		'building_area'  => array( 'type' => 'number', 'label' => '建物面積(m²)' ),
		'build_year'     => array( 'type' => 'number', 'label' => '築年(西暦)' ),
		'layout'         => array( 'type' => 'string', 'label' => '間取り' ),
		'structure'      => array( 'type' => 'string', 'label' => '構造' ),
		'address'        => array( 'type' => 'string', 'label' => '所在地(表示用)' ),
		'lat'            => array( 'type' => 'number', 'label' => '緯度' ),
		'lng'            => array( 'type' => 'number', 'label' => '経度' ),
		// 規制（不動産情報ライブラリAPIで付加）
		'kuiki_kubun'    => array( 'type' => 'string', 'label' => '区域区分' ),
		'youto_chiiki'   => array( 'type' => 'string', 'label' => '用途地域' ),
		'kenpei'         => array( 'type' => 'number', 'label' => '建蔽率(%)' ),
		'yoseki'         => array( 'type' => 'number', 'label' => '容積率(%)' ),
		// 災害（該当フラグ＋任意で想定浸水深等）
		'hazard_flood'      => array( 'type' => 'boolean', 'label' => '洪水浸水想定' ),
		'hazard_flood_depth'=> array( 'type' => 'string',  'label' => '洪水想定浸水深' ),
		'hazard_landslide'  => array( 'type' => 'boolean', 'label' => '土砂災害警戒' ),
		'hazard_tsunami'    => array( 'type' => 'boolean', 'label' => '津波浸水想定' ),
		'hazard_hightide'   => array( 'type' => 'boolean', 'label' => '高潮浸水想定' ),
		// 相場
		'chika_price'    => array( 'type' => 'number', 'label' => '最寄り地価(円/m²)' ),
		'chika_dist_m'   => array( 'type' => 'number', 'label' => '最寄り地価点まで(m)' ),
		// 生活・周辺（不動産情報ライブラリAPIで自動付加）
		'school_elem'         => array( 'type' => 'string', 'label' => '小学校区' ),
		'school_junior'       => array( 'type' => 'string', 'label' => '中学校区' ),
		'nearest_hospital'    => array( 'type' => 'string', 'label' => '最寄り医療機関' ),
		'nearest_hospital_m'  => array( 'type' => 'number', 'label' => '最寄り医療機関まで(m)' ),
		'nearest_daycare'     => array( 'type' => 'string', 'label' => '最寄り保育/幼稚園' ),
		'nearest_daycare_m'   => array( 'type' => 'number', 'label' => '最寄り保育/幼稚園まで(m)' ),
		'nearest_shelter'     => array( 'type' => 'string', 'label' => '最寄り指定緊急避難場所' ),
		'nearest_shelter_m'   => array( 'type' => 'number', 'label' => '最寄り避難場所まで(m)' ),
		'liquefaction'        => array( 'type' => 'string', 'label' => '液状化傾向' ),
		'future_pop'          => array( 'type' => 'string', 'label' => '将来推計人口(参考)' ),
		// 画像（元サイトの写真URL・直リンク表示用）
		'image_url'      => array( 'type' => 'string', 'label' => '物件画像URL(外部)' ),
		'image_urls'     => array( 'type' => 'string', 'label' => '物件画像URL一覧(JSON配列)' ),
		// 出所
		'source_name'    => array( 'type' => 'string', 'label' => '出典名' ),
		'source_url'     => array( 'type' => 'string', 'label' => '元ページURL' ),
		'source_id'      => array( 'type' => 'string', 'label' => 'ソース物件ID' ),
		'last_checked'   => array( 'type' => 'string', 'label' => '最終確認日(YYYY-MM-DD)' ),
		// 自社
		'is_kreva'       => array( 'type' => 'boolean', 'label' => 'KREVA自社物件' ),
		'kreva_use'      => array( 'type' => 'string', 'label' => 'KREVA用途(リノベ/民泊/転売)' ),
	);
}

/**
 * メタキーに接頭辞を付ける（DB上の実キー）。
 */
function kreva_akiya_meta_key( $key ) {
	return '_kakiya_' . $key;
}

/**
 * 物件の全メタを配列で取得（キーは接頭辞なし）。
 */
function kreva_akiya_get_meta( $post_id ) {
	$out = array();
	foreach ( kreva_akiya_meta_schema() as $key => $def ) {
		$val = get_post_meta( $post_id, kreva_akiya_meta_key( $key ), true );
		if ( 'number' === $def['type'] ) {
			$out[ $key ] = ( '' === $val || null === $val ) ? null : ( 0 + $val );
		} elseif ( 'boolean' === $def['type'] ) {
			$out[ $key ] = (bool) $val;
		} else {
			$out[ $key ] = ( '' === $val ) ? null : $val;
		}
	}
	return $out;
}

/**
 * 円を「1,280万円」等の表示用に整形。
 */
function kreva_akiya_format_price( $yen ) {
	if ( null === $yen || '' === $yen ) {
		return '価格応談';
	}
	$yen = (float) $yen;
	if ( $yen >= 100000000 ) {
		return rtrim( rtrim( number_format( $yen / 100000000, 2 ), '0' ), '.' ) . '億円';
	}
	if ( $yen >= 10000 ) {
		return number_format( $yen / 10000 ) . '万円';
	}
	return number_format( $yen ) . '円';
}

/**
 * 築年（西暦）から新耐震/旧耐震を推定。1981年6月〜の建築確認が新耐震だが、
 * 築年しか無いため「建築年ベースの推定」として表示する。1981年は境界のため要確認扱い。
 * 戻り値: array( label, note ) または null（築年不明）。
 */
function kreva_akiya_shinsaishin( $build_year ) {
	if ( ! $build_year ) {
		return null;
	}
	$y = (int) $build_year;
	if ( $y >= 1982 ) {
		return array( 'label' => '新耐震（推定）', 'level' => 'ok', 'note' => '1981年6月の新耐震基準以降の築年' );
	}
	if ( 1981 === $y ) {
		return array( 'label' => '新旧境目（要確認）', 'level' => 'warn', 'note' => '1981年築。建築確認日で新旧が分かれるため要確認' );
	}
	return array( 'label' => '旧耐震（推定）', 'level' => 'warn', 'note' => '1981年5月以前の旧耐震基準の可能性。耐震診断・改修を検討' );
}

/**
 * 土地分の固定資産税「ごく粗い概算」。
 * 近傍地価(円/m²)×0.7(評価額目安)×住宅用地特例(小規模1/6)×税率1.4% × 土地面積。
 * 建物分・都市計画税・各種条件は含まない。あくまで桁感の目安。null なら概算不可。
 */
function kreva_akiya_fixed_tax_land_est( $chika_price, $land_area ) {
	if ( ! $chika_price || ! $land_area ) {
		return null;
	}
	$assessed = (float) $chika_price * 0.7;            // 評価額目安/㎡
	$taxable  = $assessed * ( 1 / 6 );                 // 住宅用地(小規模)特例
	$tax      = $taxable * $land_area * 0.014;          // 標準税率1.4%
	return (int) round( $tax / 100 ) * 100;            // 100円丸め
}

/**
 * 重ねるハザードマップ（国土地理院 disaportal）ラスタタイル。商用可・出典表記条件。
 * PoC(ingest/poc.py)で疎通確認済みのURL。フロントの地図で共用。
 */
function kreva_akiya_hazard_tiles() {
	return array(
		array(
			'key'   => 'flood',
			'label' => '洪水浸水想定（想定最大規模）',
			'url'   => 'https://disaportaldata.gsi.go.jp/raster/01_flood_l2_shinsuishin_data/{z}/{x}/{y}.png',
		),
		array(
			'key'   => 'dosekiryu',
			'label' => '土砂災害警戒（土石流）',
			'url'   => 'https://disaportaldata.gsi.go.jp/raster/05_dosekiryukeikaikuiki/{z}/{x}/{y}.png',
		),
		array(
			'key'   => 'kyukeisha',
			'label' => '土砂災害警戒（急傾斜地の崩壊）',
			'url'   => 'https://disaportaldata.gsi.go.jp/raster/05_kyukeishakeikaikuiki/{z}/{x}/{y}.png',
		),
		array(
			'key'   => 'tsunami',
			'label' => '津波浸水想定',
			'url'   => 'https://disaportaldata.gsi.go.jp/raster/04_tsunami_newlegend_data/{z}/{x}/{y}.png',
		),
		array(
			'key'   => 'hightide',
			'label' => '高潮浸水想定',
			'url'   => 'https://disaportaldata.gsi.go.jp/raster/03_hightide_l2_shinsuishin_data/{z}/{x}/{y}.png',
		),
	);
}

/**
 * テーマの header/footer テンプレートパートを「wp_head より前に」レンダリングして保持する。
 * ブロックのレイアウト用CSS（wp-container-* 等）は描画時に収集され wp_head で出力されるため、
 * 描画を先に済ませないとナビの右寄せ等が崩れる。
 */
function kreva_akiya_block_parts() {
	static $parts = null;
	if ( null === $parts ) {
		$parts = array(
			'header' => do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ),
			'footer' => do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ),
		);
	}
	return $parts;
}

/**
 * テーマ種別に応じたヘッダー出力。
 * ブロックテーマ（Twenty Twenty-Five等）では get_header() がテーマのヘッダーを
 * 出力しないため、HTML骨格＋テーマの header テンプレートパートを直接描画する。
 */
function kreva_akiya_header() {
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		$parts = kreva_akiya_block_parts(); // wp_head 前にレンダリング（レイアウトCSS収集のため）
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
		wp_body_open();
		echo '<div class="wp-site-blocks">';
		echo $parts['header']; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		get_header();
	}
}

/**
 * テーマ種別に応じたフッター出力（kreva_akiya_header と対）。
 */
function kreva_akiya_footer() {
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		$parts = kreva_akiya_block_parts();
		echo $parts['footer']; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		wp_footer();
		echo '</body></html>';
	} else {
		get_footer();
	}
}

/**
 * 外部の空き家バンクへの誘導リンク（岡山県）。
 * 全国版バンク（アットホーム/LIFULL）は規約上データ流用不可のため、
 * リンク誘導のみで参照する（各社リンクポリシーに準拠）。スクレイプ非対象の自治体
 * （例：高梁市）の穴埋めにも使う。city は任意（表示メッセージの調整用）。
 */
function kreva_akiya_external_banks( $city = '' ) {
	return array(
		array(
			'label' => 'アットホーム 空き家バンク（岡山県）',
			'url'   => 'https://www.akiya-athome.jp/buy/33/',
			'note'  => '国交省指定の全国版空き家バンク',
		),
		array(
			'label' => "LIFULL HOME'S 空き家バンク（岡山県）",
			'url'   => 'https://www.homes.co.jp/akiyabank/okayama/',
			'note'  => '国交省指定の全国版空き家バンク',
		),
		array(
			'label' => '住まいる岡山（岡山県空き家情報流通システム）',
			'url'   => 'https://www.ok-smile.jp/vacant-house',
			'note'  => '岡山県宅建協会・不動産協会の集約サイト',
		),
	);
}

/**
 * 市区町村ごとの移住・定住情報リンク（岡山県公式移住ポータル「おかやま晴れの国ぐらし」）。
 * 検索を市区町村で絞ったとき、その自治体の移住相談窓口・主な支援制度（空き家改修/購入補助等）・
 * お知らせへ誘導するカードを表示するために使う。ポータル各ページに自治体公式サイトへのリンクや
 * 最新の支援制度がまとまっており、県が維持管理するため情報が古くなりにくい。
 * キーは akiya_city タームの「名称」（例: 高梁市）。
 */
function kreva_akiya_city_iju() {
	return array(
		'portal_base' => 'https://www.okayama-iju.jp/info-municipality/',
		'news_url'    => 'https://www.okayama-iju.jp/municipality/news.html',
		// キーは akiya_city タームの名称。page=県ポータルの該当ページ、subsidies=主な支援制度の要点。
		// 要点は岡山県移住ポータル掲載情報の抜粋（年度で改定され得るため詳細・最新は各ページで確認）。
		'cities'      => array(
			'岡山市'     => array( 'page' => '01okayama.html', 'subsidies' => array(
				'中古住宅の購入・リフォーム補助（県外からの移住・50歳未満）',
				'民間賃貸の家賃補助（入居後 最大4カ月分）',
				'就職面接・インターンの交通費 半額補助',
			) ),
			'倉敷市'     => array( 'page' => '02kurashiki.html', 'subsidies' => array(
				'お試し住宅（1泊1,000円・まち中／せとうち古民家）',
				'移住の面接・住まい探しの交通費補助',
				'移住就労サポートデスク（転職・就職相談 無料）',
			) ),
			'津山市'     => array( 'page' => '03tsuyama.html', 'subsidies' => array(
				'空き家活用定住補助 ― 購入 上限30万／改修 上限60万',
				'トライアルステイ（お試し住宅・最大2万円）',
				'移住コンシェルジュのオーダーメイド移住ツアー',
			) ),
			'玉野市'     => array( 'page' => '04tamano.html', 'subsidies' => array(
				'認定移住者向け お試し滞在助成',
				'就職活動の交通費助成',
			) ),
			'笠岡市'     => array( 'page' => '05kasaoka.html', 'subsidies' => array(
				'空き家バンク（90件以上を公開）',
				'オーダーメイド移住体感ツアー・お試し住宅',
			) ),
			'井原市'     => array( 'page' => '06ibara.html', 'subsidies' => array(
				'移住者の住宅新築等補助 上限100万',
				'中古住宅活用補助 ― 購入 上限100万／改修 上限100万／賃借 上限24万',
				'お試し住宅（1日1,000円）',
			) ),
			'総社市'     => array( 'page' => '07soja.html', 'subsidies' => array(
				'空き家リフォーム助成（1/2・上限30万）',
				'空き家改修の起業支援補助（1/2・上限50万）',
			) ),
			'高梁市'     => array( 'page' => '08takahashi.html', 'subsidies' => array(
				'空き家情報バンク活用助成 ― 購入 上限80万／改修 上限100万／家財処分 上限20万',
				'お試し暮らし助成（滞在の宿泊費等）',
				'移住コンシェルジュ・地域サポート団体が伴走',
			) ),
			'新見市'     => array( 'page' => '09niimi.html', 'subsidies' => array(
				'空き家活用推進補助（購入・改修・家財整理）',
				'お試し暮らし（1泊2,000円・上限30泊/年度）',
				'移住交流支援センター（アドバイザー常駐）',
			) ),
			'備前市'     => array( 'page' => '10bizen.html', 'subsidies' => array(
				'空家活用促進補助 ― 購入費の1/10・上限50万',
				'家賃補助（月5万・最長36カ月）',
				'0〜5歳児の保育料 無料',
			) ),
			'瀬戸内市'   => array( 'page' => '11setouchi.html', 'subsidies' => array(
				'定住促進補助（分譲宅地購入 価格の3割）',
				'リモートワーク体験プラン（交通費補助）',
			) ),
			'赤磐市'     => array( 'page' => '12akaiwa.html', 'subsidies' => array(
				'空き家改修費補助（1/2）',
				'新婚世帯の家賃補助（月1万・最大12カ月）',
				'高校生まで医療費 無料',
			) ),
			'真庭市'     => array( 'page' => '13maniwa.html', 'subsidies' => array(
				'空き家の購入・改修補助（1/3・上限100万）',
				'空き家バンク物件の家財撤去補助（3/4・上限20万）',
				'新婚さんバックアップ（住宅取得 上限100万 ほか）',
			) ),
			'美作市'     => array( 'page' => '14mimasaka.html', 'subsidies' => array(
				'移住定住住宅補助 ― 新築 上限130万／中古 上限50万',
				'新婚さんバックアップ（上限60万）',
				'新規就農補助（上限150万・最大3年）',
			) ),
			'浅口市'     => array( 'page' => '15asakuchi.html', 'subsidies' => array(
				'空き家情報バンク（市サイト・移住ポータルで公開）',
				'満18歳まで 子ども医療費 全額助成',
				'保育料無償化ほか 子育て支援',
			) ),
			'和気町'     => array( 'page' => '16wake.html', 'subsidies' => array(
				'移住相談員が常駐・現地ガイド',
				'移住下見の宿泊費補助（2/3・上限4,000円/泊）',
				'移住活動用の自動車を無料貸出',
			) ),
			'早島町'     => array( 'page' => '17hayashima.html', 'subsidies' => array(
				'空き家利活用助成（改修・家財処分 最大50万）',
				'起業家支援（最大40万）',
				'満18歳まで 子ども医療費助成',
			) ),
			'里庄町'     => array( 'page' => '18satosho.html', 'subsidies' => array(
				'幼稚園・保育園の保育料 無料化',
				'学童保育・預かり保育',
				'満18歳まで 子ども医療費助成',
			) ),
			'矢掛町'     => array( 'page' => '19yakage.html', 'subsidies' => array(
				'空き家改修補助（1/2・上限100万）',
				'定住促進助成（新築 建築費の1/10・最大200万）',
				'結婚新生活支援（最大60万）',
			) ),
			'新庄村'     => array( 'page' => '20shinjo.html', 'subsidies' => array(
				'保育料・給食費 無料（保育園〜中学）',
				'高校卒業まで 医療費 無料',
				'転入奨励金10万・引越費助成 上限10万',
			) ),
			'鏡野町'     => array( 'page' => '21kagamino.html', 'subsidies' => array(
				'定住促進 空き家改修補助（1/2・上限50万）',
				'町産材の家づくり補助（新築 最大200万）',
				'新卒等ふるさと就職奨励金 10万',
			) ),
			'勝央町'     => array( 'page' => '22shoo.html', 'subsidies' => array(
				'定住促進補助 ― 改修 最大70万／購入 最大100万',
				'移住支援金（就業・起業 世帯100万・個人60万）',
				'お試し住宅・宿泊助成',
			) ),
			'奈義町'     => array( 'page' => '23nagi.html', 'subsidies' => array(
				'空家購入補助 最大100万',
				'新築住宅補助 最大100万',
				'高校卒業まで 医療費 無料',
			) ),
			'西粟倉村'   => array( 'page' => '24nishiawakura.html', 'subsidies' => array(
				'ローカルベンチャー（起業型 地域おこし協力隊）',
				'教育移住のサポート',
				'地域おこし協力隊（企業研修型・行政連携型）',
			) ),
			'久米南町'   => array( 'page' => '25kumenan.html', 'subsidies' => array(
				'空き家流動化補助 ― 購入 上限20万／改修 上限50万（若者要件で上限100万）',
				'民間賃貸の家賃助成（4割・上限1.5万/月）',
				'就農支援（研修・奨励金・補助）',
			) ),
			'美咲町'     => array( 'page' => '26misaki.html', 'subsidies' => array(
				'お試し暮らし住宅（1日1,000円）',
				'高校生まで医療費無料・出産祝金・水道基本料助成',
			) ),
			'吉備中央町' => array( 'page' => '27kibichuo.html', 'subsidies' => array(
				'空き家活用支援制度（奨励金・補助金）',
				'住みたいまち 定住奨励金',
				'お試し暮らし支援制度',
			) ),
		),
	);
}

/**
 * 地図フロントに渡す共通設定（地理院タイル・初期表示・問い合わせ先）。
 */
function kreva_akiya_map_config() {
	return array(
		'baseTiles'    => array(
			'pale' => array(
				'label' => '淡色地図',
				'url'   => 'https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png',
				'attr'  => '地理院タイル',
			),
			'std'  => array(
				'label' => '標準地図',
				'url'   => 'https://cyberjapandata.gsi.go.jp/xyz/std/{z}/{x}/{y}.png',
				'attr'  => '地理院タイル',
			),
		),
		'hazardTiles'  => kreva_akiya_hazard_tiles(),
		// Phase1 初期表示：岡山県全域が収まる中心・ズーム
		'defaultCenter'=> array( 34.85, 133.85 ),
		'defaultZoom'  => 9,
		'attribution'  => 'ハザード: 国土地理院 重ねるハザードマップ ／ 規制・地価: 国土交通省 不動産情報ライブラリ',
		'contactEmail' => 'info@kreva.co.jp',
	);
}
