<?php
/**
 * フロント資産の読み込み。空き家の一覧(archive)・詳細(single)ページでのみ Leaflet と自前JS/CSSを読む。
 * Leaflet はローカル同梱（vendor/）を優先し、無ければCDNにフォールバック。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KREVA_Akiya_Assets {

	const LEAFLET_VER = '1.9.4';

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	private function is_akiya_context() {
		return is_post_type_archive( KREVA_Akiya_CPT::POST_TYPE )
			|| is_singular( KREVA_Akiya_CPT::POST_TYPE )
			|| is_tax( array( 'akiya_pref', 'akiya_city', 'akiya_type', 'akiya_status' ) );
	}

	public function enqueue() {
		if ( ! $this->is_akiya_context() ) {
			return;
		}

		// Leaflet（同梱があれば同梱、無ければCDN）
		$vendor_css = KREVA_AKIYA_DIR . 'vendor/leaflet/leaflet.css';
		if ( file_exists( $vendor_css ) ) {
			$leaflet_css = KREVA_AKIYA_URL . 'vendor/leaflet/leaflet.css';
			$leaflet_js  = KREVA_AKIYA_URL . 'vendor/leaflet/leaflet.js';
		} else {
			$leaflet_css = 'https://unpkg.com/leaflet@' . self::LEAFLET_VER . '/dist/leaflet.css';
			$leaflet_js  = 'https://unpkg.com/leaflet@' . self::LEAFLET_VER . '/dist/leaflet.js';
		}
		wp_enqueue_style( 'leaflet', $leaflet_css, array(), self::LEAFLET_VER );
		wp_enqueue_script( 'leaflet', $leaflet_js, array(), self::LEAFLET_VER, true );

		// デザイン指定フォント（価格・見出しの weight 900 を使用）
		wp_enqueue_style(
			'kreva-akiya-font',
			'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&display=swap',
			array(),
			null
		);
		wp_enqueue_style( 'kreva-akiya', KREVA_AKIYA_URL . 'assets/css/akiya.css', array( 'leaflet', 'kreva-akiya-font' ), KREVA_AKIYA_VERSION );

		$config = kreva_akiya_map_config();
		$config['restSearch'] = esc_url_raw( rest_url( KREVA_Akiya_REST::NS . '/search' ) );

		if ( is_singular( KREVA_Akiya_CPT::POST_TYPE ) ) {
			wp_enqueue_script( 'kreva-akiya-single', KREVA_AKIYA_URL . 'assets/js/akiya-single.js', array( 'leaflet' ), KREVA_AKIYA_VERSION, true );
			$post_id = get_queried_object_id();
			$config['property'] = kreva_akiya_get_meta( $post_id );
			$config['postId']   = $post_id;
			wp_localize_script( 'kreva-akiya-single', 'KREVA_AKIYA', $config );
		} else {
			wp_enqueue_script( 'kreva-akiya-search', KREVA_AKIYA_URL . 'assets/js/akiya-search.js', array( 'leaflet' ), KREVA_AKIYA_VERSION, true );
			wp_localize_script( 'kreva-akiya-search', 'KREVA_AKIYA', $config );
		}
	}
}
