# 取り込みアダプタ（scrapers/）

空き家データを各サイトから収集し、共通の `AkiyaRecord` へ正規化する。
ガードレール（robots尊重・低頻度・出典明記）は `http.PoliteSession` で担保。

## ガードレール（`http.py`）
- robots.txt を確認し Disallow を尊重（既定ON）
- リクエスト間 **最低2.0秒**・並列しない・UAに連絡先明示
- 429/5xx はバックオフ再試行
- 各レコードに `source_name` / `source_url` / `source_id` / `last_checked` を必ず付与

## 実装済みアダプタ

### ok_smile（住まいる岡山 / ok-smile.jp）★主軸
- 岡山県空き家情報流通システム。**岡山市・倉敷市・総社市ほか県内参加自治体**の空き家バンク物件を集約。
- robots.txt 全許可。空き家バンク特集の一覧から `p_no` を収集 → 詳細 `/property/detail?p_no=` をパース。
- **住所欄のGoogleマップリンクから緯度経度を直接取得**（ジオコーディング不要）。
- 取得項目：価格・所在地・緯度経度・土地/建物面積・間取り・築年・構造・用途地域・建ぺい/容積・区域区分（都市計画欄）。
- 検証済み（2026-07-03）：総社/倉敷/岡山市で実データ取得OK。

#### カバレッジ確認結果
| 自治体 | 空き家バンク(買) | 対応 |
|---|---|---|
| 岡山市北区 | 15件 | ok_smile ✅ |
| 倉敷市 | 5件 | ok_smile ✅ |
| 総社市 | 8件 | ok_smile ✅ |
| **井原市** | **0件** | → ibaragurashi（井原Life）✅ |
| **高梁市** | **0件** | → takahashi（公式バンク・118件）✅ |

### ibaragurashi（井原Life / ibaragurashi.jp）★井原市
- robots.txt 無し（許可扱い）。一覧 `/house/` から詳細 `/house-detail/akiya/<hash>.html` を収集してパース。
- 住所が町名のみ → `geocode.py`（国土地理院・キー不要）で緯度経度を付与。
- 取得項目：所在地(町名)・価格・構造・築年数(概算)・設備/補修等（説明文へ）・登録No(source_id)。
- 検証済み（2026-07-03）：B0296/B0295 で座標・構造・築年・出典取得OK。項目は ok-smile より少なめ（井原Lifeの掲載仕様）。

### takahashi（高梁市空き家バンク / takahashi-akiyabank.com）★高梁市
- 高梁市公式の空き家バンク（WordPress）。robots は /wp-admin/ のみ Disallow ＝取得可。
- `wp-sitemap-posts-bank-1.xml` で物件URL（`/bank/<id>/`）を列挙（**118件**）→ 詳細をパース。
- **情報が最も豊富**：敷地/延床面積・間取り・構造・ライフライン（電気/水道/ガス/トイレ/風呂）・修繕要否・周辺施設距離・特記事項。
- 座標は Google マップ埋め込み `!2d<経度>!3d<緯度>` から取得（※所在地は概ねエリア単位）。築年は**和暦→西暦変換**（昭和/平成/令和）。
- 検証済み（2026-07-03）：No.136/147 取得OK。

### niimi（新見市空き家情報バンク / city.niimi.okayama.jp）★新見市
- robots.txt 無し（許可扱い）。検索ページ `/akurashi/customer/customer_search` に全物件が並ぶ（29件）。
- 各カードの「詳しく見る」が **2系統**：
  - **住まいる岡山**（`ok-smile.jp/property/detail?p_no=`）… `ok_smile.parse_detail` を再利用（出典＝住まいる岡山）。**住まいる岡山の特集一覧に載らない新見市物件**まで拾えるため網羅性↑（特集一覧の4件に対しここでは18件）。
  - **新見市の自前詳細**（`/akurashi/customer/customer_detail/index/<id>.html`, 11件）… 本アダプタで th/td テーブルをパース（**純増**）。
- 自前詳細：価格・所在地・間取り・土地/建物面積(平方メートル)・築年月(和暦)・構造・ライフライン(上下水道)・備考。座標は地図スクリプトの緯度経度→無ければ `geocode.py`。写真はHTMLに無く地図サムネfallback。source_id＝登録番号。
- 検証（2026-07-06・Chrome実データ）：登録番号251で座標(34.886,133.322)・面積・築年(昭和51→1976)取得OK。

### kibichuo（吉備中央町空き家バンク / town.kibichuo.lg.jp）★吉備中央町（住まいる岡山**未参加＝純増**）
- robots.txt 無し（許可扱い）。1ページ `/site/teijyu/1576.html` の `<td>` ブロックに全物件（22件）。詳細リンクは物件PDF＝**一覧のみで必要情報が揃う**。
- 取得項目：管理番号【NNN】(source_id)・所在地(町名)・売却/賃貸価格・延床面積(平方ｍ)・構造・PDF(source_url)。座標掲載なし → `geocode.py`（エリア単位）。写真はPDF内のみ＝地図サムネfallback。
- 検証（2026-07-06・Chrome実データ）：【305】上竹 売却800万円/延床170.98㎡/木造瓦葺2階建、賃貸のみ【4-2】＝価格応談 の分岐OK。

## 5市カバレッジ（達成）
| 市 | ソース | 方式 |
|---|---|---|
| 岡山市・倉敷市・総社市 | 住まいる岡山 | 取り込み✅ |
| 井原市 | 井原Life | 取り込み✅ |
| 高梁市 | 高梁市公式バンク(118件) | 取り込み✅ |

全国版バンク（アットホーム/LIFULL）は規約によりリンク誘導のみ（プラグイン側の外部誘導パネル）。

## 使い方
`ingest/run_ingest.py` から呼ぶ（オーケストレータ）。単体の動作確認：
```bash
python -c "from scrapers.http import PoliteSession; from scrapers import ok_smile; \
s=PoliteSession(); print(len(ok_smile.list_ids(s,'33208','buy')),'件')"
```
