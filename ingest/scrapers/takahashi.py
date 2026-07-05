"""高梁市空き家バンク（takahashi-akiyabank.com）アダプタ。

高梁市の公式空き家バンク（WordPress）。robots.txt は /wp-admin/ のみ Disallow＝取得可。
wp-sitemap-posts-bank-1.xml で物件URL（/bank/<id>/）を列挙し、詳細をパース。
情報が非常に豊富（敷地/延床面積・間取り・ライフライン・周辺施設距離・修繕要否）。
座標は Google マップ埋め込み（!2d<経度>!3d<緯度>）から取得（※所在地は概ねエリア単位）。

出典表記：高梁市空き家バンク。
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
from scrapers.ok_smile import parse_yen, parse_area  # 共用  # noqa: E402

BASE = "https://takahashi-akiyabank.com"
SITEMAP = f"{BASE}/wp-sitemap-posts-bank-1.xml"
SOURCE_NAME = "高梁市空き家バンク"

# 和暦→西暦の元号開始年（元年 = 開始年）
GENGO = {"令和": 2018, "平成": 1988, "昭和": 1925, "大正": 1911, "明治": 1867}


def wareki_to_year(text: str) -> int | None:
    """'昭和12年頃' / '令和3年' → 西暦。西暦表記(1998年)もそのまま拾う。"""
    if not text:
        return None
    m = re.search(r"(令和|平成|昭和|大正|明治)\s*(\d+)", text)
    if m:
        return GENGO[m.group(1)] + int(m.group(2))
    m = re.search(r"(1[89]\d{2}|20\d{2})\s*年", text)
    return int(m.group(1)) if m else None


def list_detail_urls(session: PoliteSession) -> list[str]:
    r = session.get(SITEMAP)
    if r is None:
        return []
    urls = re.findall(r"https://takahashi-akiyabank\.com/bank/[0-9a-z-]+/", r.text)
    seen, out = set(), []
    for u in urls:
        if u not in seen:
            seen.add(u)
            out.append(u)
    return out


def _pairs(soup: BeautifulSoup) -> dict:
    """dt/dd と th.detail__*/td を辞書化。"""
    out: dict[str, str] = {}
    for dt in soup.find_all("dt"):
        dd = dt.find_next_sibling("dd")
        k = dt.get_text(" ", strip=True)
        if k and k not in out:
            out[k] = dd.get_text(" ", strip=True) if dd else ""
    for th in soup.select("th.detail__summary-th, th.detail__environment-th, th[class*='detail__']"):
        td = th.find_next_sibling("td")
        k = th.get_text(" ", strip=True)
        if k and k not in out:
            out[k] = td.get_text(" ", strip=True) if td else ""
    return out


def _type_from(kind: str) -> str:
    if not kind:
        return "戸建"
    if "土地" in kind:
        return "土地"
    if "店舗" in kind or "事業" in kind or "工場" in kind:
        return "事業用"
    return "戸建"


def parse_detail(session: PoliteSession, url: str) -> AkiyaRecord | None:
    r = session.get(url)
    if r is None:
        return None
    html = r.text
    soup = BeautifulSoup(html, "html.parser")
    p = _pairs(soup)

    reg_no = p.get("物件管理番号") or url.rstrip("/").rsplit("/", 1)[-1]

    town = p.get("所在地") or ""
    town = re.sub(r"^高梁市", "", town).strip()
    address = f"岡山県高梁市{town}" if town else "岡山県高梁市"

    # 価格：売買を優先、無ければ賃貸は価格なし扱い
    price = parse_yen(p.get("売買", "")) if p.get("売買") not in (None, "", "-") else None

    land_area = parse_area(p.get("敷地面積", ""))
    building_area = parse_area(p.get("延床面積", "") or p.get("建築面積", ""))
    layout = p.get("間取り") or None
    structure = p.get("構造") or None
    build_year = wareki_to_year(p.get("築年数", ""))
    ptype = _type_from(p.get("種類", ""))

    # 座標（Googleマップ埋め込み !2d<lng>!3d<lat>）
    lat = lng = None
    m = re.search(r"!2d([0-9.]+)!3d([0-9.]+)", html)
    if m:
        lng, lat = float(m.group(1)), float(m.group(2))
    if lat is None or lng is None:
        print(f"[takahashi] 座標なしスキップ: {url}")
        return None

    # 物件写真（wp-content/uploads）。リサイズ版（-330x248等）より原寸を優先
    image_url = None
    image_urls: list[str] = []
    photos = re.findall(
        r"https://takahashi-akiyabank\.com/wp-content/uploads/[^\"']+\.(?:jpe?g|png|webp)", html
    )
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

    # 定性情報を説明文に（ライフライン・修繕・周辺施設・特記など＝購入者価値が高い）
    desc_keys = ["水道", "電気", "ガス", "トイレ", "風呂", "キッチン", "駐車場",
                 "修繕の要否", "修繕の負担", "空き家年数", "付帯物件",
                 "交通", "周辺施設", "特記事項"]
    desc = [f"{k}：{p[k]}" for k in desc_keys if p.get(k) and p[k] != "-"]
    content = "<br>".join(desc)

    nego = p.get("交渉状況", "")
    title = f"高梁市{town}の空き家（No.{reg_no}）"

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

    status = "成約済" if nego and ("成約" in nego or "終了" in nego) else "空き家バンク"

    return AkiyaRecord(
        title=title,
        content=content,
        taxonomies={"pref": "岡山県", "city": "高梁市", "type": ptype, "status": status},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    urls = list_detail_urls(session)
    if limit:
        urls = urls[:limit]
    if urls:
        print(f"[takahashi] 高梁市: {len(urls)}件")
    records = []
    for u in urls:
        rec = parse_detail(session, u)
        if rec:
            records.append(rec)
    return records
