"""新庄村空き家情報バンク（vill.shinjo.okayama.jp 定住促進サイト）アダプタ。

新庄村（真庭郡）の自前 空き家情報バンク。住まいる岡山非参加＝純増。robots無し（許可扱い）。
※サイトは HTTP 専用。写真も HTTP 配信のため HTTPS の kreva.co.jp では混在コンテンツで
　ブロックされる → 写真は取り込まず地図サムネにフォールバック。

一覧: index.php?id=100 の「情報提供中の物件」に 物件番号NN リンク（index.php?id=<詳細id>）。
詳細: 2列テーブル（td=ラベル / td=値）。所在地の掲載が無いため村中心をジオコーディング。
　　　物件は小規模（数件）。件数が増えても一覧から自動追従する。

出典表記：新庄村空き家情報バンク。
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from wp_client import AkiyaRecord  # noqa: E402
from scrapers.http import PoliteSession  # noqa: E402
from scrapers.ok_smile import parse_yen, parse_area  # noqa: E402
from geocode import geocode  # noqa: E402

BASE = "http://www.vill.shinjo.okayama.jp"
LIST_URL = f"{BASE}/index.php?id=100"
SOURCE_NAME = "新庄村空き家情報バンク"


def collect_detail(session: PoliteSession) -> list[tuple[str, str]]:
    """一覧から (物件番号, 詳細URL) を収集。"""
    r = session.get(LIST_URL)
    if r is None:
        return []
    soup = BeautifulSoup(r.text, "html.parser")
    out: list[tuple[str, str]] = []
    seen: set[str] = set()
    for a in soup.find_all("a"):
        txt = a.get_text(strip=True)
        m = re.search(r"物件番号\s*(\d+)", txt)
        if not m:
            continue
        href = a.get("href", "")
        idm = re.search(r"[?&]id=(\d+)", href)
        if not idm:
            continue
        detail_id = idm.group(1)
        if detail_id in seen:
            continue
        seen.add(detail_id)
        out.append((m.group(1), f"{BASE}/index.php?id={detail_id}"))
    return out


def _pairs(soup: BeautifulSoup) -> dict:
    """2列テーブル（td ラベル / td 値、1行に複数ペアあり）を辞書化。"""
    out: dict[str, str] = {}
    for tr in soup.select("article tr"):
        cells = [c.get_text(" ", strip=True) for c in tr.find_all(["td", "th"])]
        cells = [c for c in cells if c]
        for i in range(0, len(cells) - 1, 2):
            k, v = cells[i], cells[i + 1]
            if k and k not in out:
                out[k] = v
    return out


def parse_detail(session: PoliteSession, reg_no: str, url: str) -> AkiyaRecord | None:
    r = session.get(url)
    if r is None:
        return None
    soup = BeautifulSoup(r.text, "html.parser")
    p = _pairs(soup)
    text = soup.get_text(" ", strip=True)

    # 価格：売却 金額（「金額要相談」は None）
    price = None
    pm = re.search(r"売却\s*([0-9,０-９，]+\s*万円|[0-9,]+\s*円)", text)
    if pm:
        price = parse_yen(pm.group(1).translate(str.maketrans("０１２３４５６７８９，", "0123456789,")))

    land_area = parse_area(p.get("土地面積", ""))
    building_area = parse_area(p.get("延床面積", ""))
    layout = p.get("間取り") or None
    structure = p.get("構造") or None

    ptype = "土地" if ("土地" in p.get("種目", "") and building_area is None) else "戸建"

    # 所在地の掲載が無い → 村中心をジオコーディング
    address = "岡山県真庭郡新庄村"
    lat = lng = None
    g = geocode(address)
    if g:
        lat, lng = g.lat, g.lon
    if lat is None or lng is None:
        print(f"[shinjo] 座標取得できずスキップ: 物件番号{reg_no}")
        return None

    desc_keys = ["設備等", "主要施設までの距離", "備考"]
    desc = [f"{k}：{p[k]}" for k in desc_keys if p.get(k) and p[k] not in ("-", "ー", "")]
    content = "<br>".join(desc)

    status = "成約済" if re.search(r"成約|契約済|売約", text) else "空き家バンク"
    price_label = f"{int(price / 10000):,}万円" if price else "価格応談"
    title = f"新庄村の空き家（No.{reg_no}・{price_label}）"

    meta = {
        "price": price,
        "land_area": land_area,
        "building_area": building_area,
        "layout": layout,
        "structure": structure,
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
        taxonomies={"pref": "岡山県", "city": "新庄村", "type": ptype, "status": status},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    items = collect_detail(session)
    if limit:
        items = items[:limit]
    if items:
        print(f"[shinjo] 新庄村: {len(items)}件")
    records = []
    for reg_no, url in items:
        rec = parse_detail(session, reg_no, url)
        if rec:
            records.append(rec)
    return records
