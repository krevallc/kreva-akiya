<?php
/**
 * テンプレート差し込み。テーマにテンプレートが無くても動くよう、プラグイン同梱テンプレートを
 * template_include で読み込む。テーマ側で archive-akiya.php / single-akiya.php を用意すれば
 * そちらが優先される（WordPress標準の階層）。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KREVA_Akiya_Templates {

	public function hooks() {
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_action( 'admin_init', array( $this, 'register_home_setting' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_until_launch' ) );
	}

	/**
	 * 空き家検索の正式公開（設定→一般のチェック）まで、/akiya/ 配下を noindex にする。
	 * 公開スイッチをONにすると自動で解除される。
	 */
	public function noindex_until_launch( $robots ) {
		if ( get_option( 'kreva_akiya_home_live', 0 ) ) {
			return $robots; // 公開後はインデックス許可
		}
		$is_akiya = is_singular( KREVA_Akiya_CPT::POST_TYPE )
			|| is_post_type_archive( KREVA_Akiya_CPT::POST_TYPE )
			|| is_tax( array( 'akiya_pref', 'akiya_city', 'akiya_type', 'akiya_status' ) );
		if ( $is_akiya ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * 設定 → 一般 に「HOMEに空き家検索を表示」チェックボックスを追加。
	 * 空き家検索サービスの正式公開時にONにすると、HOMEのヒーローCTA・実績チップ・
	 * フラッグシップセクション・事業カードに空き家検索が表示される。
	 */
	public function register_home_setting() {
		register_setting( 'general', 'kreva_akiya_home_live', array(
			'type'              => 'boolean',
			'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
			'default'           => 0,
		) );
		add_settings_field(
			'kreva_akiya_home_live',
			'空き家検索の公開',
			function () {
				$v = get_option( 'kreva_akiya_home_live', 0 );
				echo '<label><input type="checkbox" name="kreva_akiya_home_live" value="1" ' . checked( $v, 1, false ) . '> HOMEに「岡山県の空き家検索」を表示する（正式公開時にON）</label>';
			},
			'general'
		);
	}

	public function template_include( $template ) {
		// フロントページはプラグインのHOMEデザインでレンダリング
		// （無効化したい場合: add_filter( 'kreva_akiya_render_home', '__return_false' );）
		if ( is_front_page() && apply_filters( 'kreva_akiya_render_home', true ) ) {
			return KREVA_AKIYA_DIR . 'templates/home.php';
		}
		// テーマが用意していればそれを尊重
		if ( is_post_type_archive( KREVA_Akiya_CPT::POST_TYPE ) || is_tax( array( 'akiya_pref', 'akiya_city', 'akiya_type', 'akiya_status' ) ) ) {
			$theme = locate_template( array( 'archive-akiya.php' ) );
			if ( $theme ) {
				return $theme;
			}
			return KREVA_AKIYA_DIR . 'templates/search.php';
		}
		if ( is_singular( KREVA_Akiya_CPT::POST_TYPE ) ) {
			$theme = locate_template( array( 'single-akiya.php' ) );
			if ( $theme ) {
				return $theme;
			}
			return KREVA_AKIYA_DIR . 'templates/single-akiya.php';
		}
		return $template;
	}
}
