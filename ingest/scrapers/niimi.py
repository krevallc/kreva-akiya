"""新見市空き家情報バンク（city.niimi.okayama.jp）アダプタ。

新見市の移住定住サイト「え〜くらし新見」の空き家検索ページ（customer_search）から収集。
robots.txt は無し（許可扱い）。1ページに全物件が並び、各カードの「詳しく見る」リンクが
2系統に分かれる:
  - 住まいる岡山（ok-smile.jp/property/detail?p_no=…）… ok_smile アダプタで詳細取得（出典=住まいる岡山）。
    住まいる岡山の空き家バンク特集一覧に載らない新見市物件も拾えるため、網羅性が上がる。
  - 新見市の自前詳細ページ（/akurashi/customer/customer_detail/index/<id>.html）… 本アダプタでパース（純増）。

新見市の自前詳細は th/td テーブル。座標は地図スクリプト内の緯度経度を採用し、
取れない場合は所在地を国土地理院ジオコーディング。物件写真はHTMLに無い（地図サムネfallback）。

出典表記：新見市空き家情報バンク。
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from wp_client import AkiyaRecord  # noqa: E402
from scrapers.http import PoliteSession  # noqa: E402
from scrapers import ok_smile  # noqa: E402
from scrapers.ok_smile import parse_yen  # noqa: E402
from scrapers.takahashi import wareki_to_year  # noqa: E402
from geocode import geocode  # noqa: E402

BASE = "https://www.city.niimi.okayama.jp"
LIST_URL = f"{BASE}/akurashi/customer/customer_search"
SOURCE_NAME = "新見市空き家情報バンク"

# 岡山県内に収まる「緯度,経度」ペア（地図スクリプト埋め込み）を1件だけ拾う
COORD_RE = re.compile(r"(3[0-9]\.\d{4,})\s*,\s*(13[0-9]\.\d{4,})")

# 全角英数→半角（価格・面積の表記ゆれ対策）
_ZEN = "０１２３４５６７８９．，－ｍ"
_HAN = "0123456789.,-m"
_Z2H = str.maketrans(_ZEN, _HAN)


def _z2h(text: str) -> str:
    return (text or "").translate(_Z2H)


def parse_area_jp(text: str) -> float | None:
    """'約655平方メートル' / '172平方ｍ' / '30㎡' → 数値(㎡)。"""
    if not text:
        return None
    t = _z2h(text).replace(",", "").replace("約", "")
    m = re.search(r"([0-9]+(?:\.[0-9]+)?)\s*(?:平方メートル|平方m|㎡|m2|m²|平米)", t)
    return float(m.group(1)) if m else None


def collect_detail_links(html: str) -> tuple[list[str], list[str]]:
    """一覧HTMLから (住まいる岡山のp_no, 新見自前の詳細ID) を重複なしで返す。"""
    p_nos: list[str] = []
    for m in re.findall(r"ok-smile\.jp/property/detail\?p_no=([0-9]+)", html):
        if m not in p_nos:
            p_nos.append(m)
    niimi_ids: list[str] = []
    for m in re.findall(r"/akurashi/customer/customer_detail/index/([0-9]+)\.html", html):
        if m not in niimi_ids:
            niimi_ids.append(m)
    return p_nos, niimi_ids


def parse_niimi_detail(session: PoliteSession, niimi_id: str) -> AkiyaRecord | None:
    url = f"{BASE}/akurashi/customer/customer_detail/index/{niimi_id}.html"
    r = session.get(url)
    if r is None:
        return None
    html = r.text
    soup = BeautifulSoup(html, "html.parser")

    # 物件詳細テーブル（th→td）
    pairs: dict[str, str] = {}
    for th in soup.select("table th"):
        td = th.find_next_sibling("td")
        k = th.get_text(" ", strip=True)
        if k and k not in pairs:
            pairs[k] = td.get_text(" ", strip=True) if td else ""

    body_text = soup.get_text(" ", strip=True)
    pm = re.search(r"販売価格\s*([0-9０-９,，][^\s]*)", body_text)
    price = parse_yen(_z2h(pm.group(1))) if pm else None
    rm = re.search(r"登録番号\s*([0-9]+)", body_text)
    reg_no = rm.group(1) if rm else niimi_id

    town = (pairs.get("所在地") or "").strip()
    town = re.sub(r"^新見市", "", town).strip()
    address = f"岡山県新見市{town}" if town else "岡山県新見市"

    land_area = parse_area_jp(pairs.get("土地面積", ""))
    building_area = parse_area_jp(pairs.get("建物面積", ""))
    layout = pairs.get("間取り") or None
    structure = pairs.get("建物構造") or None
    build_year = wareki_to_year(pairs.get("築年月", ""))

    # 座標：地図スクリプトの緯度経度 → 無ければ住所ジオコーディング
    lat = lng = None
    cm = COORD_RE.search(html)
    if cm:
        lat, lng = float(cm.group(1)), float(cm.group(2))
    if (lat is None or lng is None) and town:
        g = geocode(address)
        if g:
            lat, lng = g.lat, g.lon
    if lat is None or lng is None:
        print(f"[niimi] 座標取得できずスキップ: {url}")
        return None

    # 定性情報を説明文に（購入者価値の高い項目）
    desc_keys = ["交通", "間取り内訳", "駐車場", "庭の有無", "上水道", "下水道", "備考"]
    desc = [f"{k}：{pairs[k]}" for k in desc_keys if pairs.get(k) and pairs[k] not in ("-", "")]
    content = "<br>".join(desc)

    ptype = "土地" if (building_area is None and land_area) else "戸建"
    title = f"新見市{town}の空き家（登録番号{reg_no}）"

    meta = {
        "price": price,
        "land_area": land_area,
        "building_area": building_area,
        "layout": layout,
        "structure": structure,
        "build_year": build_year,
        "address": address,
        "lat": lat, "lng": lng,
        "source_name": SOURCE_NAME,
        "source_url": url,
        "source_id": str(reg_no),
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title,
        content=content,
        taxonomies={"pref": "岡山県", "city": "新見市", "type": ptype, "status": "空き家バンク"},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    r = session.get(LIST_URL)
    if r is None:
        return []
    p_nos, niimi_ids = collect_detail_links(r.text)
    if limit:
        p_nos = p_nos[:limit]
        niimi_ids = niimi_ids[:limit]
    print(f"[niimi] 新見市: 住まいる岡山 {len(p_nos)}件 + 市自前 {len(niimi_ids)}件")

    records: list[AkiyaRecord] = []
    for p_no in p_nos:
        rec = ok_smile.parse_detail(session, p_no)
        if rec:
            records.append(rec)
    for nid in niimi_ids:
        rec = parse_niimi_detail(session, nid)
        if rec:
            records.append(rec)
    return records
