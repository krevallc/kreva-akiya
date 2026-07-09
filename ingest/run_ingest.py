"""取り込みオーケストレータ：スクレイプ → （エンリッチ）→ （WP投入）。

例:
    # まずは無害なドライラン（住まいる岡山・優先5市・各3件、JSON出力のみ）
    python run_ingest.py --source ok_smile --cities priority --limit 3 --dry-run

    # APIキー設定後：周辺情報を付加して確認
    python run_ingest.py --source ok_smile --cities priority --limit 3 --enrich --dry-run

    # WordPress へ投入（WP_* 設定済み前提）
    python run_ingest.py --source ok_smile --cities all --enrich --push

安全既定：--push を付けない限りWordPressへは書き込まない（out/ にJSONを出すだけ）。
"""
from __future__ import annotations

import argparse
import json
import pathlib
import sys

import poc  # _load_dotenv を借用
from wp_client import WPClient, AkiyaRecord
from reinfolib import ReinfolibClient
from scrapers.http import PoliteSession
from scrapers import ok_smile, ibaragurashi, takahashi, niimi, kibichuo, yakage, misaki, shinjo, tamano, kasaoka

OUT_DIR = pathlib.Path(__file__).parent / "out"


def enrich_records(records: list[AkiyaRecord]) -> None:
    """各レコードの座標から周辺情報を付加（APIキーがある場合のみ）。API値を優先してマージ。"""
    client = ReinfolibClient()
    if not client.has_key:
        print("※ REINFOLIB_API_KEY 未設定 → エンリッチをスキップ（規制/災害/学区等は未付加）。")
        return
    for i, rec in enumerate(records, 1):
        lat, lng = rec.meta.get("lat"), rec.meta.get("lng")
        if lat is None or lng is None:
            continue
        e = client.enrich(lat, lng)
        api_meta = e.to_meta()
        # スクレイプ値を優先（物件固有の区域区分/用途/面積等はサイト掲載値が正確）、
        # APIは未取得項目の穴埋め＋学区/災害/地価/施設などの付加に使う。
        rec.meta = {**api_meta, **rec.meta}
        if i % 10 == 0:
            print(f"  …エンリッチ {i}/{len(records)}")


def stamp_last_checked(records: list[AkiyaRecord], date_str: str) -> None:
    for rec in records:
        rec.meta.setdefault("last_checked", date_str)


# 市町村名→ローマ字（URLスラッグ用）。政令区は「岡山市」に丸められている前提
CITY_ROMAJI = {
    "岡山市": "okayama", "倉敷市": "kurashiki", "総社市": "soja", "井原市": "ibara",
    "高梁市": "takahashi", "津山市": "tsuyama", "玉野市": "tamano", "笠岡市": "kasaoka",
    "新見市": "niimi", "備前市": "bizen", "瀬戸内市": "setouchi", "赤磐市": "akaiwa",
    "真庭市": "maniwa", "美作市": "mimasaka", "浅口市": "asakuchi",
    "和気町": "wake", "早島町": "hayashima", "里庄町": "satosho", "矢掛町": "yakage",
    "新庄村": "shinjo", "鏡野町": "kagamino", "勝央町": "shoo", "奈義町": "nagi",
    "西粟倉村": "nishiawakura", "久米南町": "kumenan", "美咲町": "misaki",
    "吉備中央町": "kibichuo",
}


def make_slug(rec: AkiyaRecord) -> str:
    """`<市町村ローマ字>-<ソースID>` 形式のユニークスラッグ（例: soja-1855332, ibara-b0296）。"""
    import re as _re
    city = rec.taxonomies.get("city", "")
    m = _re.match(r"(?:.+郡)?(.+?[市町村])$", city)
    base = CITY_ROMAJI.get(m.group(1) if m else city, "okayama-pref")
    sid = str(rec.meta.get("source_id", "")).lower()
    sid = _re.sub(r"[^a-z0-9]+", "-", sid).strip("-")
    sid = _re.sub(r"^0+", "", sid) or sid  # 先頭ゼロを除去（000001855332→1855332）
    return f"{base}-{sid}" if sid else ""


def apply_slugs(records: list[AkiyaRecord]) -> None:
    for rec in records:
        if not rec.slug:
            rec.slug = make_slug(rec)


def main() -> int:
    ap = argparse.ArgumentParser(description="KREVA 空き家 取り込み")
    ap.add_argument("--source", default="ok_smile",
                    choices=["ok_smile", "ibaragurashi", "takahashi", "niimi", "kibichuo", "yakage", "misaki", "shinjo", "tamano", "kasaoka", "all"],
                    help="取り込み元")
    ap.add_argument("--cities", default="priority", help="priority | all | カンマ区切りコード")
    ap.add_argument("--kinds", default="buy", help="buy,rent（カンマ区切り）")
    ap.add_argument("--limit", type=int, default=None, help="自治体あたりの最大件数（動作確認用）")
    ap.add_argument("--enrich", action="store_true", help="不動産情報ライブラリで周辺情報を付加")
    ap.add_argument("--push", action="store_true", help="WordPress に投入（無指定はドライラン）")
    ap.add_argument("--reconcile", action="store_true",
                    help="投入後、元サイトから消えた物件を『掲載終了』にする（フル取得ソースのみ）")
    ap.add_argument("--dry-run", action="store_true", help="JSON出力のみ（明示用）")
    ap.add_argument("--date", default=None, help="last_checked に使う日付 YYYY-MM-DD（既定は本日）")
    args = ap.parse_args()

    poc._load_dotenv()

    # 対象自治体コード
    if args.cities == "priority":
        codes = ok_smile.PRIORITY_CODES
    elif args.cities == "all":
        codes = list(ok_smile.OKAYAMA_CODES.keys())
    else:
        codes = [c.strip() for c in args.cities.split(",") if c.strip()]
    kinds = tuple(k.strip() for k in args.kinds.split(",") if k.strip())

    # 収集
    print(f"■ 収集: source={args.source} cities={args.cities}({len(codes)}) kinds={kinds} limit={args.limit}")
    session = PoliteSession(min_interval=2.0)
    records: list[AkiyaRecord] = []
    if args.source in ("ok_smile", "all"):
        records += ok_smile.scrape(session, city_codes=codes, kinds=kinds, limit_per_city=args.limit)
    if args.source in ("ibaragurashi", "all"):
        records += ibaragurashi.scrape(session, limit=args.limit)
    if args.source in ("takahashi", "all"):
        records += takahashi.scrape(session, limit=args.limit)
    if args.source in ("niimi", "all"):
        records += niimi.scrape(session, limit=args.limit)
    if args.source in ("kibichuo", "all"):
        records += kibichuo.scrape(session, limit=args.limit)
    if args.source in ("yakage", "all"):
        records += yakage.scrape(session, limit=args.limit)
    if args.source in ("misaki", "all"):
        records += misaki.scrape(session, limit=args.limit)
    if args.source in ("shinjo", "all"):
        records += shinjo.scrape(session, limit=args.limit)
    if args.source in ("tamano", "all"):
        records += tamano.scrape(session, limit=args.limit)
    if args.source in ("kasaoka", "all"):
        records += kasaoka.scrape(session, limit=args.limit, kinds=kinds)
    print(f"  → {len(records)} 件 収集")

    # last_checked
    if args.date:
        date_str = args.date
    else:
        import datetime
        date_str = datetime.date.today().isoformat()
    stamp_last_checked(records, date_str)
    apply_slugs(records)

    # エンリッチ
    if args.enrich:
        print("■ エンリッチ（周辺情報付加）")
        enrich_records(records)

    # 出力 or 投入
    OUT_DIR.mkdir(exist_ok=True)
    dump = [
        {"title": r.title, "slug": r.slug, "taxonomies": r.taxonomies, "content": r.content, "meta": r.meta}
        for r in records
    ]
    out_json = OUT_DIR / f"ingest_{args.source}_{args.cities}.json"
    out_json.write_text(json.dumps(dump, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"■ JSON出力: {out_json}（{len(records)}件）")

    if args.push:
        client = WPClient()
        if not client.configured:
            print("✗ WP_* 未設定のため投入できません（.env を設定）。ドライランに留めます。")
            return 1
        print(f"■ WordPress へ投入: {client.base_url}")
        results = client.upsert_many(records)
        ok = sum(1 for x in results if x.get("ok", True) is not False)
        print(f"  → {ok}/{len(results)} 投入完了")

        if args.reconcile:
            reconcile_sources(client, records, args)
    else:
        print("（--push 未指定のため WordPress へは書き込みません＝ドライラン）")
        if args.reconcile:
            print("※ --reconcile は --push と併用してください（今回はスキップ）。")

    return 0


# フル取得＝そのソースの全物件を今回収集したと言える条件。
# 部分取得（priority指定/limit）で照合すると、取得しなかった物件を誤って掲載終了に
# してしまうため、フル取得のソースだけを照合対象にする。
def fully_scraped_sources(args) -> set[str]:
    if args.limit is not None:
        return set()  # 件数制限中は全件を見ていない
    out: set[str] = set()
    # 住まいる岡山は複数自治体にまたがるため、全自治体を回した時のみフル
    if args.source in ("ok_smile", "all") and args.cities == "all":
        out.add(ok_smile.SOURCE_NAME)
    if args.source in ("ibaragurashi", "all"):
        out.add(ibaragurashi.SOURCE_NAME)
    if args.source in ("takahashi", "all"):
        out.add(takahashi.SOURCE_NAME)
    # 新見の自前バンクは niimi のフル取得でのみ照合対象（住まいる岡山分は ok_smile 側の判定に従う）
    if args.source in ("niimi", "all"):
        out.add(niimi.SOURCE_NAME)
    if args.source in ("kibichuo", "all"):
        out.add(kibichuo.SOURCE_NAME)
    if args.source in ("yakage", "all"):
        out.add(yakage.SOURCE_NAME)
    if args.source in ("misaki", "all"):
        out.add(misaki.SOURCE_NAME)
    if args.source in ("shinjo", "all"):
        out.add(shinjo.SOURCE_NAME)
    if args.source in ("tamano", "all"):
        out.add(tamano.SOURCE_NAME)
    return out


def reconcile_sources(client: WPClient, records: list[AkiyaRecord], args) -> None:
    """フル取得したソースについて、今回見つからなかった既存物件を掲載終了にする。"""
    targets = fully_scraped_sources(args)
    if not targets:
        print("■ 在庫照合：フル取得したソースが無いためスキップ（priority/limit指定時など）。")
        return
    active: dict[str, set[str]] = {}
    for r in records:
        sn = r.meta.get("source_name")
        sid = r.meta.get("source_id")
        if sn and sid is not None:
            active.setdefault(sn, set()).add(str(sid))
    print("■ 在庫照合（掲載終了の自動アーカイブ）")
    for sn in sorted(targets):
        ids = sorted(active.get(sn, set()))
        if not ids:
            print(f"  {sn}: 収集0件のため照合スキップ（安全弁）。")
            continue
        try:
            res = client.reconcile(sn, ids)
            print(f"  {sn}: 照合{res.get('checked')}件 → 掲載終了 {res.get('archived')}件"
                  f"（現存{res.get('active')} / 既終端{res.get('skipped')}）")
        except Exception as e:  # noqa: BLE001
            print(f"  {sn}: 照合失敗: {e}")


if __name__ == "__main__":
    sys.exit(main())
