"""吉備中央町空き家バンク（town.kibichuo.lg.jp）アダプタ。

吉備中央町は住まいる岡山に非参加のため純増ソース。robots.txt は無し（許可扱い）。
1ページ（/site/teijyu/1576.html）に全物件が <td> ブロックで並ぶ。各ブロックに
管理番号【NNN】・掲載日・所在地(町名)・売却/賃貸価格・延床面積・構造・詳細PDFリンク。

詳細は本文HTMLに構造化されており（詳細リンクは物件PDF）、一覧ページのみで必要情報が揃う。
座標は掲載が無いため所在地を国土地理院ジオコーディング（エリア単位）。写真はPDF内のみ（地図サムネfallback）。

出典表記：吉備中央町空き家バンク。
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from wp_client import AkiyaRecord  # noqa: E402
from scrapers.http import PoliteSession  # noqa: E402
from scrapers.ok_smile import parse_yen  # noqa: E402
from geocode import geocode  # noqa: E402

BASE = "https://www.town.kibichuo.lg.jp"
LIST_URL = f"{BASE}/site/teijyu/1576.html"
SOURCE_NAME = "吉備中央町空き家バンク"

_Z2H = str.maketrans("０１２３４５６７８９．，－", "0123456789.,-")


def _z2h(text: str) -> str:
    return (text or "").translate(_Z2H)


def parse_block(td, session: PoliteSession) -> AkiyaRecord | None:
    text = td.get_text(" ", strip=True)
    lines = [ln.strip() for ln in td.get_text("\n").split("\n") if ln.strip()]

    m = re.search(r"【\s*([0-9A-Za-z\-]+)\s*】", text)
    if not m:
        return None
    reg_no = m.group(1)

    tm = re.search(r"吉備中央町([^\s　0-9０-９売賃延木鉄軽]+)", text)
    town = tm.group(1) if tm else ""
    address = f"岡山県加賀郡吉備中央町{town}" if town else "岡山県加賀郡吉備中央町"

    # 価格：売却を優先。売却が無ければ賃貸のみ＝価格なし扱い
    price = None
    pm = re.search(r"売却\s*([0-9０-９,，]+\s*(?:万円|円))", text)
    if pm:
        price = parse_yen(_z2h(pm.group(1)))

    # 延床面積
    building_area = None
    am = re.search(r"延床\s*([0-9０-９.．]+)\s*平方", text)
    if am:
        try:
            building_area = float(_z2h(am.group(1)))
        except ValueError:
            building_area = None

    # 構造（「…造…階建」を含む行）
    structure = None
    for ln in lines:
        if "造" in ln and "延床" not in ln and "構造" not in ln and len(ln) <= 30:
            structure = ln
            break

    # 詳細PDF（出所URL）
    pdf = td.find("a", href=re.compile(r"\.pdf"))
    source_url = (BASE + pdf.get("href")) if pdf and pdf.get("href", "").startswith("/") else (
        pdf.get("href") if pdf else LIST_URL
    )

    # 座標：所在地ジオコーディング（掲載座標なし）
    lat = lng = None
    g = geocode(address)
    if g:
        lat, lng = g.lat, g.lon
    if lat is None or lng is None:
        print(f"[kibichuo] 座標取得できずスキップ: 【{reg_no}】{address}")
        return None

    ptype = "土地" if "土地" in text and building_area is None else "戸建"
    price_label = f"{int(price / 10000):,}万円" if price else "価格応談"
    title = f"吉備中央町{town}の空き家（{price_label}）"

    meta = {
        "price": price,
        "building_area": building_area,
        "structure": structure,
        "address": address,
        "lat": lat, "lng": lng,
        "source_name": SOURCE_NAME,
        "source_url": source_url,
        "source_id": str(reg_no),
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title,
        content="",
        taxonomies={"pref": "岡山県", "city": "吉備中央町", "type": ptype, "status": "空き家バンク"},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    r = session.get(LIST_URL)
    if r is None:
        return []
    soup = BeautifulSoup(r.text, "html.parser")

    blocks = []
    for a in soup.find_all("a"):
        if a.get_text(strip=True) == "詳細":
            td = a.find_parent("td")
            if td is not None and td not in blocks:
                blocks.append(td)
    if limit:
        blocks = blocks[:limit]
    if blocks:
        print(f"[kibichuo] 吉備中央町: {len(blocks)}件")

    records: list[AkiyaRecord] = []
    for td in blocks:
        rec = parse_block(td, session)
        if rec:
            records.append(rec)
    return records
