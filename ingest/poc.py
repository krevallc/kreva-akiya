"""エンリッチ層 PoC の入口。

使い方:
    python poc.py "岡山県総社市中央一丁目1-1"
    python poc.py "岡山県井原市七日市町..." --zoom 15

処理:
    1) 住所 → 緯度経度（国土地理院・キー不要）
    2) 緯度経度 → 区域区分/用途地域/地価/災害（不動産情報ライブラリ・要APIキー）
    3) ハザードタイルを重ねた Leaflet 地図HTMLを出力（out/ に保存）

APIキー未設定でも 1) と 3)（地図＋ハザードタイル）は動作し、2) は「未取得」と表示する。
.env に REINFOLIB_API_KEY を設定すると 2) が有効化される。
"""
from __future__ import annotations

import argparse
import json
import os
import pathlib
import sys

from geocode import geocode
from reinfolib import ReinfolibClient, Enrichment

OUT_DIR = pathlib.Path(__file__).parent / "out"

# 重ねるハザードマップ（国土地理院 disaportal）ラスタタイル。商用可・出典表記条件。
HAZARD_TILES = {
    "flood": (
        "洪水浸水想定（想定最大規模）",
        "https://disaportaldata.gsi.go.jp/raster/01_flood_l2_shinsuishin_data/{z}/{x}/{y}.png",
    ),
    "dosekiryu": (
        "土砂災害警戒区域（土石流）",
        "https://disaportaldata.gsi.go.jp/raster/05_dosekiryukeikaikuiki/{z}/{x}/{y}.png",
    ),
    "kyukeisha": (
        "土砂災害警戒区域（急傾斜地の崩壊）",
        "https://disaportaldata.gsi.go.jp/raster/05_kyukeishakeikaikuiki/{z}/{x}/{y}.png",
    ),
    "tsunami": (
        "津波浸水想定",
        "https://disaportaldata.gsi.go.jp/raster/04_tsunami_newlegend_data/{z}/{x}/{y}.png",
    ),
    "hightide": (
        "高潮浸水想定区域",
        "https://disaportaldata.gsi.go.jp/raster/03_hightide_l2_shinsuishin_data/{z}/{x}/{y}.png",
    ),
}


def _load_dotenv() -> None:
    """依存を増やさず .env を最小パース（KEY=VALUE 行のみ）。"""
    env_path = pathlib.Path(__file__).parent / ".env"
    if not env_path.exists():
        return
    for line in env_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        os.environ.setdefault(k.strip(), v.strip())


def summarize(e: Enrichment) -> str:
    lines = [
        f"緯度経度        : {e.lat:.6f}, {e.lon:.6f}",
        f"区域区分        : {e.kuiki_kubun or '（未取得）'}",
        f"用途地域        : {e.youto_chiiki or '（未取得）'}",
        f"建蔽率/容積率   : {fmt_pct(e.kenpei_ritsu)} / {fmt_pct(e.yoseki_ritsu)}",
    ]
    if e.chika_nearest:
        p = e.chika_nearest.get("properties", {})
        price = p.get("価格") or p.get("u_current_years_price_ja") or p.get("価格(円/m²)") or "?"
        lines.append(
            f"最寄り地価      : {price}（約{e.chika_nearest.get('distance_m')}m先）"
        )
    else:
        lines.append("最寄り地価      : （未取得）")
    if e.hazards:
        hz = "、".join(_hazard_label(k) for k in e.hazards)
        lines.append(f"災害リスク該当  : {hz}")
    else:
        lines.append("災害リスク該当  : 該当データなし/未取得")
    if e.notes:
        lines.append("備考            : " + " / ".join(sorted(set(e.notes))))
    if e.sources:
        lines.append("出典            : " + " ; ".join(e.sources))
    return "\n".join(lines)


def _hazard_label(key: str) -> str:
    return {
        "flood": "洪水浸水想定", "landslide": "土砂災害警戒区域",
        "tsunami": "津波浸水想定", "hightide": "高潮浸水想定",
    }.get(key, key)


def fmt_pct(v):
    return f"{v:.0f}%" if isinstance(v, (int, float)) else "（未取得）"


def build_map_html(title: str, lat: float, lon: float, e: Enrichment, zoom: int = 16) -> str:
    overlays = []
    for _k, (label, url) in HAZARD_TILES.items():
        overlays.append(
            f'"{label}": L.tileLayer("{url}", {{opacity: 0.6, maxNativeZoom: 17, maxZoom: 18}})'
        )
    overlay_js = ",\n        ".join(overlays)
    popup = (
        f"<b>{_esc(title)}</b><br>"
        f"区域区分: {_esc(e.kuiki_kubun or '—')}<br>"
        f"用途地域: {_esc(e.youto_chiiki or '—')}<br>"
        f"建蔽率/容積率: {fmt_pct(e.kenpei_ritsu)} / {fmt_pct(e.yoseki_ritsu)}"
    )
    return f"""<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KREVA 空き家PoC 地図 — {_esc(title)}</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  html,body{{margin:0;height:100%}} #map{{height:100%}}
  .info{{position:absolute;z-index:1000;top:10px;right:10px;max-width:300px;
    background:#fff;padding:10px 12px;border-radius:8px;font:13px/1.6 sans-serif;
    box-shadow:0 1px 6px rgba(0,0,0,.3)}}
  .info h1{{font-size:14px;margin:0 0 6px}}
</style>
</head>
<body>
<div id="map"></div>
<div class="info">
  <h1>{_esc(title)}</h1>
  区域区分: {_esc(e.kuiki_kubun or '—')}<br>
  用途地域: {_esc(e.youto_chiiki or '—')}<br>
  建蔽率/容積率: {fmt_pct(e.kenpei_ritsu)} / {fmt_pct(e.yoseki_ritsu)}<br>
  <small>右上のレイヤ切替でハザードを重ねられます</small>
</div>
<script>
  var pale = L.tileLayer("https://cyberjapandata.gsi.go.jp/xyz/pale/{{z}}/{{x}}/{{y}}.png",
    {{attribution:"地理院タイル", maxZoom:18}});
  var std = L.tileLayer("https://cyberjapandata.gsi.go.jp/xyz/std/{{z}}/{{x}}/{{y}}.png",
    {{attribution:"地理院タイル", maxZoom:18}});
  var map = L.map("map", {{center:[{lat},{lon}], zoom:{zoom}, layers:[pale]}});
  var overlays = {{
        {overlay_js}
  }};
  L.control.layers({{"淡色地図":pale, "標準地図":std}}, overlays, {{collapsed:false}}).addTo(map);
  L.marker([{lat},{lon}]).addTo(map).bindPopup("{popup}").openPopup();
  L.control.attribution({{prefix:false}})
    .addAttribution("ハザード: 国土地理院 重ねるハザードマップ / 規制: 国土交通省 不動産情報ライブラリ")
    .addTo(map);
</script>
</body>
</html>"""


def _esc(s: str) -> str:
    return (str(s).replace("&", "&amp;").replace("<", "&lt;")
            .replace(">", "&gt;").replace('"', "&quot;"))


def main() -> int:
    ap = argparse.ArgumentParser(description="KREVA 空き家 エンリッチ層 PoC")
    ap.add_argument("address", help="対象住所（例: 岡山県総社市中央一丁目1-1）")
    ap.add_argument("--zoom", type=int, default=15, help="GIS取得ズーム(11-15, 既定15)")
    ap.add_argument("--json", action="store_true", help="結果をJSONでも出力")
    args = ap.parse_args()

    _load_dotenv()

    print(f"■ ジオコーディング: {args.address}")
    g = geocode(args.address)
    if not g:
        print("  住所が見つかりませんでした。表記を調整して再試行してください。")
        return 1
    print(f"  → {g.matched_title}  ({g.lat:.6f}, {g.lon:.6f})\n")

    client = ReinfolibClient()
    if not client.has_key:
        print("※ REINFOLIB_API_KEY 未設定 → 規制/地価/災害は未取得。地図とハザードタイルは表示されます。")
        print("  申請: https://www.reinfolib.mlit.go.jp/api/request/  → .env に設定\n")

    e = client.enrich(g.lat, g.lon)
    print("■ エンリッチ結果")
    print(summarize(e))

    OUT_DIR.mkdir(exist_ok=True)
    out_html = OUT_DIR / f"map_{abs(hash(args.address)) % 10**8}.html"
    out_html.write_text(build_map_html(g.matched_title or args.address, g.lat, g.lon, e), encoding="utf-8")
    print(f"\n■ 地図HTMLを出力: {out_html}")
    print("  ブラウザで開き、右上のレイヤ切替でハザードを重ねて確認できます。")

    if args.json:
        out_json = OUT_DIR / f"enrich_{abs(hash(args.address)) % 10**8}.json"
        payload = {
            "address": args.address, "matched": g.matched_title,
            "lat": g.lat, "lon": g.lon,
            "kuiki_kubun": e.kuiki_kubun, "youto_chiiki": e.youto_chiiki,
            "kenpei_ritsu": e.kenpei_ritsu, "yoseki_ritsu": e.yoseki_ritsu,
            "chika_nearest": e.chika_nearest, "hazards": list(e.hazards.keys()),
            "sources": e.sources, "notes": e.notes,
        }
        out_json.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"■ JSONを出力: {out_json}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
