<?php
/**
 * カスタム投稿タイプ akiya（空き家物件）＋タクソノミー＋メタ登録＋管理画面の入力欄。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KREVA_Akiya_CPT {

	const POST_TYPE = 'akiya';

	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
	}

	public function register_post_type() {
		$labels = array(
			'name'          => '空き家物件',
			'singular_name' => '空き家物件',
			'add_new_item'  => '物件を追加',
			'edit_item'     => '物件を編集',
			'search_items'  => '物件を検索',
			'not_found'     => '物件がありません',
		);
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true, // /akiya/ が一覧（検索）ページ
				'menu_icon'    => 'dashicons-admin-home',
				'menu_position'=> 24,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'rewrite'      => array( 'slug' => 'akiya', 'with_front' => false ),
				'show_in_rest' => true, // ブロックエディタ＆REST
			)
		);
	}

	public function register_taxonomies() {
		$common = array(
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		);
		register_taxonomy( 'akiya_pref', self::POST_TYPE, $common + array(
			'label'   => '都道府県',
			'rewrite' => array( 'slug' => 'akiya/pref' ),
		) );
		register_taxonomy( 'akiya_city', self::POST_TYPE, $common + array(
			'label'   => '市区町村',
			'rewrite' => array( 'slug' => 'akiya/city' ),
		) );
		register_taxonomy( 'akiya_type', self::POST_TYPE, $common + array(
			'label'   => '物件種別',
			'rewrite' => array( 'slug' => 'akiya/type' ),
		) );
		register_taxonomy( 'akiya_status', self::POST_TYPE, $common + array(
			'label'   => 'ステータス', // 自社 / バンク / 成約済 等
			'rewrite' => array( 'slug' => 'akiya/status' ),
		) );
	}

	public function register_meta() {
		foreach ( kreva_akiya_meta_schema() as $key => $def ) {
			register_post_meta(
				self::POST_TYPE,
				kreva_akiya_meta_key( $key ),
				array(
					'type'         => $def['type'],
					'single'       => true,
					'show_in_rest' => true,
					'auth_callback'=> function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	public function add_meta_box() {
		add_meta_box(
			'kreva_akiya_details',
			'空き家 詳細情報',
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'kreva_akiya_save', 'kreva_akiya_nonce' );
		$schema = kreva_akiya_meta_schema();
		echo '<style>.kakiya-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px 20px}.kakiya-grid label{display:block;font-weight:600;font-size:12px;margin-bottom:2px}.kakiya-grid input[type=text],.kakiya-grid input[type=number]{width:100%}</style>';
		echo '<div class="kakiya-grid">';
		foreach ( $schema as $key => $def ) {
			$mk    = kreva_akiya_meta_key( $key );
			$val   = get_post_meta( $post->ID, $mk, true );
			$field = 'kakiya_' . $key;
			echo '<div>';
			echo '<label for="' . esc_attr( $field ) . '">' . esc_html( $def['label'] ) . '</label>';
			if ( 'boolean' === $def['type'] ) {
				echo '<input type="checkbox" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="1" ' . checked( $val, '1', false ) . ' />';
			} elseif ( 'number' === $def['type'] ) {
				echo '<input type="number" step="any" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $val ) . '" />';
			} else {
				echo '<input type="text" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $val ) . '" />';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '<p style="margin-top:12px;color:#666">緯度経度・区域区分・災害等は取り込みバッチ(ingest)が自動入力。自社物件は最低限「価格・所在地・緯度経度・KREVA自社物件」を入力してください。</p>';
	}

	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['kreva_akiya_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kreva_akiya_nonce'] ), 'kreva_akiya_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( kreva_akiya_meta_schema() as $key => $def ) {
			$field = 'kakiya_' . $key;
			$mk    = kreva_akiya_meta_key( $key );
			if ( 'boolean' === $def['type'] ) {
				update_post_meta( $post_id, $mk, isset( $_POST[ $field ] ) ? '1' : '' );
				continue;
			}
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $field ] );
			if ( 'number' === $def['type'] ) {
				update_post_meta( $post_id, $mk, ( '' === $raw ) ? '' : ( 0 + $raw ) );
			} else {
				update_post_meta( $post_id, $mk, sanitize_text_field( $raw ) );
			}
		}
	}
}
