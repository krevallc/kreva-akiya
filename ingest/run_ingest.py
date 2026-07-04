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
from scrapers import ok_smile, ibaragurashi, takahashi

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


def main() -> int:
    ap = argparse.ArgumentParser(description="KREVA 空き家 取り込み")
    ap.add_argument("--source", default="ok_smile", choices=["ok_smile", "ibaragurashi", "takahashi", "all"], help="取り込み元")
    ap.add_argument("--cities", default="priority", help="priority | all | カンマ区切りコード")
    ap.add_argument("--kinds", default="buy", help="buy,rent（カンマ区切り）")
    ap.add_argument("--limit", type=int, default=None, help="自治体あたりの最大件数（動作確認用）")
    ap.add_argument("--enrich", action="store_true", help="不動産情報ライブラリで周辺情報を付加")
    ap.add_argument("--push", action="store_true", help="WordPress に投入（無指定はドライラン）")
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
    print(f"  → {len(records)} 件 収集")

    # last_checked
    if args.date:
        date_str = args.date
    else:
        import datetime
        date_str = datetime.date.today().isoformat()
    stamp_last_checked(records, date_str)

    # エンリッチ
    if args.enrich:
        print("■ エンリッチ（周辺情報付加）")
        enrich_records(records)

    # 出力 or 投入
    OUT_DIR.mkdir(exist_ok=True)
    dump = [
        {"title": r.title, "taxonomies": r.taxonomies, "content": r.content, "meta": r.meta}
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
    else:
        print("（--push 未指定のため WordPress へは書き込みません＝ドライラン）")

    return 0


if __name__ == "__main__":
    sys.exit(main())
