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
	}

	public function template_include( $template ) {
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
