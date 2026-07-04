# KREVA 空き家検索 プラグイン（kreva-akiya）

kreva.co.jp（WordPress）に **全国空き家検索** を追加するプラグイン。
物件はカスタム投稿タイプ `akiya`、フロントは Leaflet＋地理院タイル＋ハザード重畳。
取り込みは REST 経由（`ingest/` の Python パイプラインから投入）。

## 何ができるか
- `/akiya/` … 検索・一覧ページ（地図＋絞り込み＋カード）。自社物件を上位表示。
- `/akiya/{slug}/` … 物件詳細（スペック・**区域区分/用途地域**・**災害ハザード地図**・地価・出所・KREVA相談CTA）。
- 管理画面から自社物件を手入力（価格・所在地・緯度経度・KREVA自社物件フラグ 等）。
- REST で外部物件を upsert（`source_name`+`source_id` で冪等）。

## 導入手順（mixhost / WordPress）
1. `kreva-akiya/` フォルダを `wp-content/plugins/` にアップロード。
2. 管理画面 → プラグイン → **「KREVA 空き家検索」を有効化**（有効化時にパーマリンクが再生成される）。
3. 「設定 → パーマリンク」を一度開いて保存（念のためリライト反映）。
4. `/akiya/` にアクセスして地図が出れば成功。
5. （任意）Leaflet をローカル同梱にする場合は `vendor/leaflet/` に `leaflet.css` / `leaflet.js` を置く（無ければCDN=unpkgを使用）。

## データ投入（REST）
- エンドポイント：`POST /wp-json/kreva-akiya/v1/items`
- 認証：**アプリケーションパスワード**（ユーザー → プロフィール → アプリケーションパスワードを発行）。Basic認証で送信。
- 権限：`edit_posts` 以上。
- Body例：
```json
{
  "title": "総社市〇〇の空き家",
  "content": "説明文（任意・HTML可）",
  "status": "publish",
  "taxonomies": { "pref": "岡山県", "city": "総社市", "type": "戸建", "status": "空き家バンク" },
  "meta": {
    "price": 3800000, "land_area": 180.5, "building_area": 95.2,
    "address": "岡山県総社市〇〇", "lat": 34.6731, "lng": 133.7471,
    "kuiki_kubun": "市街化調整区域", "youto_chiiki": "—",
    "hazard_flood": true, "hazard_flood_depth": "0.5〜3.0m",
    "source_name": "住まいる岡山", "source_url": "https://www.ok-smile.jp/...",
    "source_id": "ok-12345", "last_checked": "2026-07-02", "is_kreva": false
  }
}
```
- 応答：`{ ok, post_id, action: created|updated, permalink }`

## 公開検索エンドポイント（フロントが使用）
`GET /wp-json/kreva-akiya/v1/search`
- 任意パラメータ：`pref` `city` `type`（スラッグ）／`price_min` `price_max`（円）／
  `kuiki=exclude_chosei`／`hazard_free=1`／`kreva_only=1`／`bbox=minLng,minLat,maxLng,maxLat`／`per_page`
- 応答：`{ count, items:[{id,title,permalink,lat,lng,price,price_label,city,type,is_kreva,kuiki_kubun,thumb,source_name}] }`

## 構成
```
kreva-akiya/
  kreva-akiya.php          メイン（ブートストラップ・有効化フック）
  includes/
    helpers.php            メタ定義・整形・ハザード/地図設定（単一の真実の源）
    class-cpt.php          CPT akiya＋タクソノミー＋メタ＋管理入力欄
    class-rest.php         REST（/search 公開・/items upsert認証）
    class-assets.php       Leaflet＋自前JS/CSS読み込み
    class-templates.php    テンプレート差し込み（テーマ優先）
  templates/
    search.php             検索・一覧（archive）
    single-akiya.php       詳細（single）
  assets/css/akiya.css
  assets/js/akiya-search.js   検索地図＋絞り込み＋カード
  assets/js/akiya-single.js   詳細地図＋ハザード重畳
  vendor/leaflet/          （任意）Leaflet同梱先
```

## 出典・ライセンス
- 地図：国土地理院タイル、重ねるハザードマップ（商用可・出典表記）。
- 規制/地価：国土交通省 不動産情報ライブラリ。
- 各物件の情報は元の掲載元（自治体・空き家バンク等）に帰属。詳細ページに出典・最終確認日を表示。

## 注意
- 物件データのスクレイピングは規約順守・低頻度・出典明記のガードレール下で（設計書 §7 参照）。
- 全国版空き家バンク（LIFULL/アットホーム）のデータは流用せずリンク誘導のみ。
