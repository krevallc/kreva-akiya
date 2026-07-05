<?php
/**
 * Plugin Name:       KREVA 空き家検索
 * Plugin URI:        https://kreva.co.jp/akiya/
 * Description:        全国の空き家情報を検索・表示する機能。物件(CPT akiya)＋地図(Leaflet/地理院タイル)＋ハザード重畳＋規制/地価表示＋自社導線。取り込みはREST経由。
 * Version:           0.4.0
 * Author:            KREVA LLC
 * Text Domain:       kreva-akiya
 * License:           GPL-2.0-or-later
 *
 * 設計書: Website/KREVA JP Website改修/空き家検索機能/00_設計書_空き家検索機能.md
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセス禁止
}

define( 'KREVA_AKIYA_VERSION', '0.4.0' );
define( 'KREVA_AKIYA_FILE', __FILE__ );
define( 'KREVA_AKIYA_DIR', plugin_dir_path( __FILE__ ) );
define( 'KREVA_AKIYA_URL', plugin_dir_url( __FILE__ ) );

require_once KREVA_AKIYA_DIR . 'includes/helpers.php';
require_once KREVA_AKIYA_DIR . 'includes/class-cpt.php';
require_once KREVA_AKIYA_DIR . 'includes/class-rest.php';
require_once KREVA_AKIYA_DIR . 'includes/class-assets.php';
require_once KREVA_AKIYA_DIR . 'includes/class-templates.php';

/**
 * 各コンポーネントの初期化。
 */
function kreva_akiya_bootstrap() {
	( new KREVA_Akiya_CPT() )->hooks();
	( new KREVA_Akiya_REST() )->hooks();
	( new KREVA_Akiya_Assets() )->hooks();
	( new KREVA_Akiya_Templates() )->hooks();
}
add_action( 'plugins_loaded', 'kreva_akiya_bootstrap' );

/**
 * 有効化時：CPT/タクソノミーを登録してからパーマリンクを再生成。
 */
function kreva_akiya_activate() {
	require_once KREVA_AKIYA_DIR . 'includes/class-cpt.php';
	$cpt = new KREVA_Akiya_CPT();
	$cpt->register_post_type();
	$cpt->register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'kreva_akiya_activate' );

/**
 * 無効化時：パーマリンクを元に戻す。
 */
function kreva_akiya_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'kreva_akiya_deactivate' );
