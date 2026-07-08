"""井原Life（ibaragurashi.jp）アダプタ。

井原市の空き家・空き農地バンク。robots.txt は無し（許可扱い）。
一覧 /house/ から詳細 /house-detail/akiya/<hash>.html を集めてパース。
住所は町名のみのため geocode.py（国土地理院）で緯度経度を付与する。

項目は少なめ（住所・価格・構造・築年数・設備・補修）だが、井原市の唯一の一次ソース。
設備（上水道/汲み取り 等）や補修要否は購入者価値が高いので説明文に含める。

出典表記：井原Life（井原市 空き家・空き農地バンク）。
"""
from __future__ import annotations

import datetime
import json
import re
import sys
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from wp_client import AkiyaRecord  # noqa: E402
from scrapers.http import PoliteSession  # noqa: E402
from scrapers.ok_smile import parse_yen  # 価格パーサを共用  # noqa: E402
from geocode import geocode  # noqa: E402

BASE = "https://ibaragurashi.jp"
LIST_URL = f"{BASE}/house/"
SOURCE_NAME = "井原Life"


def list_detail_urls(session: PoliteSession) -> list[str]:
    r = session.get(LIST_URL)
    if r is None:
        return []
    urls = re.findall(r'https://ibaragurashi\.jp/house-detail/akiya/[a-f0-9]+\.html', r.text)
    # 順序を保ちつつ重複除去
    seen, out = set(), []
    for u in urls:
        if u not in seen:
            seen.add(u)
            out.append(u)
    return out


def _approx_build_year(chikunensu: str) -> int | None:
    """'築約69年' → 概算築年（本日基準）。"""
    if not chikunensu:
        return None
    m = re.search(r"([0-9]+)\s*年", chikunensu)
    if not m:
        return None
    return datetime.date.today().year - int(m.group(1))


def parse_detail(session: PoliteSession, url: str) -> AkiyaRecord | None:
    r = session.get(url)
    if r is None:
        return None
    soup = BeautifulSoup(r.text, "html.parser")
    html = r.text

    # 登録No（source_id）
    m = re.search(r"登録No[.．]?([A-Z]?[0-9]+)", html)
    reg_no = m.group(1) if m else None

    # 所在地（shoulder-txt「所在地」の直後の place-txt）
    town = None
    for sh in soup.select("p.house-detail__shoulder-txt"):
        if sh.get_text(strip=True) == "所在地":
            nxt = sh.find_next_sibling("p")
            if nxt:
                town = nxt.get_text(strip=True)
            break
    # 所在地欄は「西江原町」の場合と「井原市西江原町」等 市名込みの場合が混在する。
    # 常に県市を前置するため、重複しないよう先頭の 岡山県／井原市 を剥がして正規化。
    if town:
        town = re.sub(r"^\s*(岡山県)?\s*(井原市)?\s*", "", town).strip()
        town = town or None
    address = f"岡山県井原市{town}" if town else None

    # 価格
    price = None
    price_el = soup.select_one("p.house-detail__price-txt")
    if price_el:
        price = parse_yen(price_el.get_text(strip=True))  # 「要相談」は None

    # house-info テーブル（構造・築年数・設備・補修 等）
    info: dict[str, str] = {}
    for th in soup.select("th.house-info__table-ttl"):
        td = th.find_next_sibling("td")
        key = th.get_text(strip=True)
        if key and key not in info:
            info[key] = td.get_text(" ", strip=True) if td else ""

    structure = info.get("建築構造") or None
    build_year = _approx_build_year(info.get("築年数", ""))

    # 座標（住所→ジオコーディング）
    lat = lng = None
    if address:
        g = geocode(address)
        if g:
            lat, lng = g.lat, g.lon
    if lat is None or lng is None:
        print(f"[ibaragurashi] 座標取得できずスキップ: {url}")
        return None

    # 物件写真（/upload/ 配下・最大8枚）
    image_url = None
    image_urls: list[str] = []
    for ph in re.findall(r"https://ibaragurashi\.jp/upload/[^\"']+\.(?:jpe?g|png|webp)", r.text):
        if ph not in image_urls:
            image_urls.append(ph)
        if len(image_urls) >= 8:
            break
    if image_urls:
        image_url = image_urls[0]

    # 設備・補修等を説明文に（購入者価値が高い定性情報）
    desc_keys = ["物件の設備", "補修", "付帯施設", "空き家になった時", "その他"]
    desc_lines = [f"{k}：{info[k]}" for k in desc_keys if info.get(k)]
    content = "<br>".join(desc_lines)

    title = f"井原市{town or ''}の空き家" + (f"（{reg_no}）" if reg_no else "")

    meta = {
        "price": price,
        "address": address,
        "lat": lat, "lng": lng,
        "structure": structure,
        "build_year": build_year,
        "image_url": image_url,
        "image_urls": json.dumps(image_urls, ensure_ascii=False) if image_urls else None,
        "source_name": SOURCE_NAME,
        "source_url": url,
        "source_id": reg_no or url.rsplit("/", 1)[-1],
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title,
        content=content,
        taxonomies={"pref": "岡山県", "city": "井原市", "type": "戸建", "status": "空き家バンク"},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    urls = list_detail_urls(session)
    if limit:
        urls = urls[:limit]
    if urls:
        print(f"[ibaragurashi] 井原市: {len(urls)}件")
    records = []
    for u in urls:
        rec = parse_detail(session, u)
        if rec:
            records.append(rec)
    return records
