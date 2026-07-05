<?php
/**
 * REST API：
 *  - GET  /kreva-akiya/v1/search  … 公開。地図＋カード用に物件を絞り込み返却
 *  - POST /kreva-akiya/v1/items   … 認証。取り込みバッチからの upsert（source_name+source_id で冪等）
 *
 * 認証は WordPress のアプリケーションパスワード（Basic）を想定。permission は edit_posts。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KREVA_Akiya_REST {

	const NS = 'kreva-akiya/v1';

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/items',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upsert_item' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * 公開検索。パラメータはすべて任意。
	 * pref, city, type（スラッグ）/ price_min, price_max / kuiki(=調整区域除外なら "exclude_chosei") /
	 * hazard_free(1=災害該当を除外) / kreva_only(1) / bbox=minLng,minLat,maxLng,maxLat / per_page
	 */
	public function search( WP_REST_Request $req ) {
		$meta_query = array( 'relation' => 'AND' );
		$tax_query  = array( 'relation' => 'AND' );

		foreach ( array( 'pref' => 'akiya_pref', 'city' => 'akiya_city', 'type' => 'akiya_type' ) as $param => $tax ) {
			$slug = sanitize_title( (string) $req->get_param( $param ) );
			if ( $slug ) {
				$tax_query[] = array(
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $slug,
				);
			}
		}

		$pmin = $req->get_param( 'price_min' );
		$pmax = $req->get_param( 'price_max' );
		if ( is_numeric( $pmin ) ) {
			$meta_query[] = array(
				'key'     => kreva_akiya_meta_key( 'price' ),
				'value'   => (float) $pmin,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}
		if ( is_numeric( $pmax ) ) {
			$meta_query[] = array(
				'key'     => kreva_akiya_meta_key( 'price' ),
				'value'   => (float) $pmax,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		if ( 'exclude_chosei' === $req->get_param( 'kuiki' ) ) {
			$meta_query[] = array(
				'key'     => kreva_akiya_meta_key( 'kuiki_kubun' ),
				'value'   => '市街化調整区域',
				'compare' => 'NOT LIKE',
			);
		}

		if ( '1' === (string) $req->get_param( 'hazard_free' ) ) {
			foreach ( array( 'hazard_flood', 'hazard_landslide', 'hazard_tsunami', 'hazard_hightide' ) as $hz ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => kreva_akiya_meta_key( $hz ), 'value' => '1', 'compare' => '!=' ),
					array( 'key' => kreva_akiya_meta_key( $hz ), 'compare' => 'NOT EXISTS' ),
				);
			}
		}

		if ( '1' === (string) $req->get_param( 'kreva_only' ) ) {
			$meta_query[] = array(
				'key'   => kreva_akiya_meta_key( 'is_kreva' ),
				'value' => '1',
			);
		}

		$args = array(
			'post_type'      => KREVA_Akiya_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => min( 500, max( 1, (int) ( $req->get_param( 'per_page' ) ?: 200 ) ) ),
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
			// 自社物件の先頭化は取得後にPHPで（meta_keyでJOINすると未設定物件が除外されるため）
		);
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query;
		}

		$bbox = $this->parse_bbox( $req->get_param( 'bbox' ) );

		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$m = kreva_akiya_get_meta( $post->ID );
			if ( null === $m['lat'] || null === $m['lng'] ) {
				continue; // 地図に出せない
			}
			if ( $bbox && ! $this->in_bbox( $m['lng'], $m['lat'], $bbox ) ) {
				continue;
			}
			$items[] = array(
				'id'          => $post->ID,
				'title'       => get_the_title( $post ),
				'permalink'   => get_permalink( $post ),
				'lat'         => $m['lat'],
				'lng'         => $m['lng'],
				'price'       => $m['price'],
				'price_label' => kreva_akiya_format_price( $m['price'] ),
				'city'        => $this->first_term_name( $post->ID, 'akiya_city' ),
				'type'        => $this->first_term_name( $post->ID, 'akiya_type' ),
				'is_kreva'    => $m['is_kreva'],
				'kuiki_kubun' => $m['kuiki_kubun'],
				'thumb'       => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: ( $m['image_url'] ?: null ),
				'source_name' => $m['source_name'],
			);
		}

		// 自社物件を先頭へ（安定ソート）
		usort( $items, function ( $a, $b ) {
			return ( $b['is_kreva'] ? 1 : 0 ) - ( $a['is_kreva'] ? 1 : 0 );
		} );

		return new WP_REST_Response(
			array( 'count' => count( $items ), 'items' => $items ),
			200
		);
	}

	/**
	 * 取り込みバッチからの upsert。source_name + source_id をキーに既存を探し、なければ新規。
	 * body: { title, content?, status?, taxonomies:{pref,city,type,status}, meta:{...} }
	 */
	public function upsert_item( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		if ( empty( $body ) || ! is_array( $body ) ) {
			return new WP_Error( 'kakiya_bad_body', 'JSON body が必要です', array( 'status' => 400 ) );
		}
		$meta   = isset( $body['meta'] ) && is_array( $body['meta'] ) ? $body['meta'] : array();
		$source = isset( $meta['source_name'] ) ? (string) $meta['source_name'] : '';
		$sid    = isset( $meta['source_id'] ) ? (string) $meta['source_id'] : '';

		$existing = 0;
		if ( $source && $sid ) {
			$found = get_posts( array(
				'post_type'   => KREVA_Akiya_CPT::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array(
					'relation' => 'AND',
					array( 'key' => kreva_akiya_meta_key( 'source_name' ), 'value' => $source ),
					array( 'key' => kreva_akiya_meta_key( 'source_id' ), 'value' => $sid ),
				),
			) );
			if ( $found ) {
				$existing = (int) $found[0];
			}
		}

		$postarr = array(
			'post_type'    => KREVA_Akiya_CPT::POST_TYPE,
			'post_title'   => isset( $body['title'] ) ? sanitize_text_field( $body['title'] ) : '（無題の空き家）',
			'post_content' => isset( $body['content'] ) ? wp_kses_post( $body['content'] ) : '',
			'post_status'  => isset( $body['status'] ) ? sanitize_key( $body['status'] ) : 'publish',
		);
		if ( $existing ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// メタ保存（スキーマにあるキーのみ）
		$schema = kreva_akiya_meta_schema();
		foreach ( $meta as $key => $val ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}
			$mk = kreva_akiya_meta_key( $key );
			if ( 'boolean' === $schema[ $key ]['type'] ) {
				update_post_meta( $post_id, $mk, $val ? '1' : '' );
			} elseif ( 'number' === $schema[ $key ]['type'] ) {
				update_post_meta( $post_id, $mk, ( '' === $val || null === $val ) ? '' : ( 0 + $val ) );
			} else {
				update_post_meta( $post_id, $mk, sanitize_text_field( (string) $val ) );
			}
		}

		// タクソノミー
		if ( isset( $body['taxonomies'] ) && is_array( $body['taxonomies'] ) ) {
			$map = array( 'pref' => 'akiya_pref', 'city' => 'akiya_city', 'type' => 'akiya_type', 'status' => 'akiya_status' );
			foreach ( $map as $k => $tax ) {
				if ( ! empty( $body['taxonomies'][ $k ] ) ) {
					wp_set_object_terms( $post_id, sanitize_text_field( $body['taxonomies'][ $k ] ), $tax, false );
				}
			}
		}

		return new WP_REST_Response(
			array(
				'ok'        => true,
				'post_id'   => $post_id,
				'action'    => $existing ? 'updated' : 'created',
				'permalink' => get_permalink( $post_id ),
			),
			$existing ? 200 : 201
		);
	}

	private function first_term_name( $post_id, $tax ) {
		$terms = get_the_terms( $post_id, $tax );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			return $terms[0]->name;
		}
		return null;
	}

	private function parse_bbox( $raw ) {
		if ( ! $raw ) {
			return null;
		}
		$p = array_map( 'floatval', explode( ',', (string) $raw ) );
		return ( count( $p ) === 4 ) ? $p : null; // [minLng, minLat, maxLng, maxLat]
	}

	private function in_bbox( $lng, $lat, $b ) {
		return ( $lng >= $b[0] && $lng <= $b[2] && $lat >= $b[1] && $lat <= $b[3] );
	}
}
