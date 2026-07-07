"""美咲町空き家バンク（misaki-akiyabank.com）アダプタ。

美咲町の独立した空き家バンクサイト（WordPress）。住まいる岡山/アットホームとは別の一次ソース＝純増。
robots.txt は /wp-admin/ のみ Disallow＝取得可。takahashi と同構造（bank CPT・
wp-sitemap-posts-bank-1.xml で物件URL /bank/<番号>/ を列挙）。情報が豊富。

詳細は th/td テーブル（用途・構造・間取り・敷地/延床面積・建築時期・設備・周辺施設・特記）。
座標は Google マップ埋め込み !2d<経度>!3d<緯度>（※エリア単位）。写真は wp-content/uploads（HTTPS）。

出典表記：美咲町空き家バンク。
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from wp_client import AkiyaRecord  # noqa: E402
from scrapers.http import PoliteSession  # noqa: E402
from scrapers.ok_smile import parse_yen, parse_area  # noqa: E402
from scrapers.takahashi import wareki_to_year  # noqa: E402

BASE = "https://misaki-akiyabank.com"
SITEMAP = f"{BASE}/wp-sitemap-posts-bank-1.xml"
SOURCE_NAME = "美咲町空き家バンク"


def list_detail_urls(session: PoliteSession) -> list[str]:
    r = session.get(SITEMAP)
    if r is None:
        return []
    urls = re.findall(r"https://misaki-akiyabank\.com/bank/[a-z0-9\-]+/", r.text)
    seen, out = set(), []
    for u in urls:
        if u not in seen:
            seen.add(u)
            out.append(u)
    return out


def _pairs(soup: BeautifulSoup) -> dict:
    out: dict[str, str] = {}
    for th in soup.select("table th"):
        td = th.find_next_sibling("td")
        k = th.get_text(" ", strip=True)
        if k and k not in out:
            out[k] = td.get_text(" ", strip=True) if td else ""
    return out


def _type_from(youto: str, structure: str) -> str:
    s = f"{youto} {structure}"
    if "土地" in s:
        return "土地"
    if "店舗" in s or "事業" in s or "工場" in s:
        return "事業用"
    return "戸建"


def parse_detail(session: PoliteSession, url: str) -> AkiyaRecord | None:
    r = session.get(url)
    if r is None:
        return None
    html = r.text
    soup = BeautifulSoup(html, "html.parser")
    p = _pairs(soup)
    text = soup.get_text(" ", strip=True)

    m = re.search(r"登録番号\s*([A-Za-z0-9]+)", text)
    reg_no = m.group(1) if m else url.rstrip("/").rsplit("/", 1)[-1].upper()

    m = re.search(r"所在地\s*(美咲町[^\s　0-9０-９]+)", text)
    town = m.group(1) if m else "美咲町"
    town_only = re.sub(r"^美咲町", "", town)
    address = f"岡山県久米郡美咲町{town_only}" if town_only else "岡山県久米郡美咲町"

    # 価格（売買）。無ければ賃貸のみ扱いで価格なし
    price = None
    pm = re.search(r"売買\s*([0-9,０-９，]+\s*(?:万円|円))", text)
    if pm:
        price = parse_yen(pm.group(1).translate(str.maketrans("０１２３４５６７８９，", "0123456789,")))

    land_area = parse_area(p.get("敷地面積", ""))
    building_area = parse_area(p.get("延べ床面積", "") or p.get("延床面積", "") or p.get("建築面積", ""))
    layout_full = p.get("間取り", "")
    layout = layout_full.split()[0] if layout_full else None
    structure = p.get("構造") or None
    build_year = wareki_to_year(p.get("建築時期", ""))
    youto = p.get("用途", "")
    ptype = _type_from(youto, structure or "")

    # 座標（Googleマップ埋め込み !2d<lng>!3d<lat>）
    lat = lng = None
    cm = re.search(r"!2d([0-9.]+)!3d([0-9.]+)", html)
    if cm:
        lng, lat = float(cm.group(1)), float(cm.group(2))
    if lat is None or lng is None:
        print(f"[misaki] 座標なしスキップ: {url}")
        return None

    # 写真（wp-content/uploads・HTTPS）。リサイズ版（-330x248等）より原寸優先
    image_url = None
    image_urls: list[str] = []
    photos = re.findall(r"https://misaki-akiyabank\.com/wp-content/uploads/[^\"']+\.(?:jpe?g|png|webp)", html)
    if photos:
        pool = [ph for ph in photos if not re.search(r"-\d+x\d+\.", ph)] or photos
        seen_ph: set[str] = set()
        for ph in pool:
            if ph not in seen_ph:
                seen_ph.add(ph)
                image_urls.append(ph)
            if len(image_urls) >= 8:
                break
        image_url = image_urls[0]

    # 定性情報を説明文に（購入者価値の高い項目）
    desc_keys = ["電気", "ガス", "水道", "風呂", "トイレ", "キッチン", "駐車スペース",
                 "補修の要否", "空き家になった時期", "付帯物件", "交通", "周辺施設", "特記事項"]
    desc = [f"{k}：{p[k]}" for k in desc_keys if p.get(k) and p[k] not in ("-", "")]
    content = "<br>".join(desc)

    status = "成約済" if re.search(r"成約|売約|契約済", text) else "空き家バンク"
    title = f"美咲町{town_only}の空き家（No.{reg_no}）"

    meta = {
        "price": price,
        "land_area": land_area,
        "building_area": building_area,
        "layout": layout,
        "structure": structure,
        "build_year": build_year,
        "address": address,
        "lat": lat, "lng": lng,
        "image_url": image_url,
        "image_urls": json.dumps(image_urls, ensure_ascii=False) if image_urls else None,
        "source_name": SOURCE_NAME,
        "source_url": url,
        "source_id": str(reg_no),
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title,
        content=content,
        taxonomies={"pref": "岡山県", "city": "美咲町", "type": ptype, "status": status},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    urls = list_detail_urls(session)
    if limit:
        urls = urls[:limit]
    if urls:
        print(f"[misaki] 美咲町: {len(urls)}件")
    records = []
    for u in urls:
        rec = parse_detail(session, u)
        if rec:
            records.append(rec)
    return records
