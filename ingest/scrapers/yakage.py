"""矢掛町空き家情報バンク（矢掛町移住支援サイト YAKAGE LIFE）アダプタ。

矢掛町は住まいる岡山の空き家バンク特集に載らない純増ソース。robots.txt は無し（許可扱い）。
※サイトは HTTP 専用（HTTPS は証明書エラー）。取り込み（requests）は HTTP で問題なし。
　ただし物件写真も HTTP 配信のため、HTTPS の kreva.co.jp では混在コンテンツでブロックされる。
　→ 写真は取り込まず地図サムネにフォールバック（将来リホスト/プロキシで対応可）。

一覧: /ijyu/vacant-house/house.html?page=N に物件番号リンク（/ijyu/vacant-house/<番号>.html）。
詳細: th/td テーブル（地区・所在地・売貸別・構造・設備）。座標なし→所在地ジオコーディング。

出典表記：矢掛町空き家情報バンク（YAKAGE LIFE）。
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

BASE = "http://www.town.yakage.okayama.jp"
LIST_URL = f"{BASE}/ijyu/vacant-house/house.html"
SOURCE_NAME = "矢掛町空き家情報バンク"

_Z2H = str.maketrans("０１２３４５６７８９，", "0123456789,")


def _z2h(text: str) -> str:
    return (text or "").translate(_Z2H)


def collect_ids(session: PoliteSession, max_pages: int = 15) -> list[str]:
    """ページ送りしながら詳細番号を重複なしで収集。"""
    ids: list[str] = []
    for page in range(1, max_pages + 1):
        url = LIST_URL if page == 1 else f"{LIST_URL}?page={page}"
        r = session.get(url)
        if r is None:
            break
        found = re.findall(r"/ijyu/vacant-house/(\d+)\.html", r.text)
        new = [i for i in dict.fromkeys(found) if i not in ids]
        if not new:
            break
        ids.extend(new)
    return ids


def parse_detail(session: PoliteSession, prop_id: str) -> AkiyaRecord | None:
    url = f"{BASE}/ijyu/vacant-house/{prop_id}.html"
    r = session.get(url)
    if r is None:
        return None
    soup = BeautifulSoup(r.text, "html.parser")

    pairs: dict[str, str] = {}
    for th in soup.select("table th"):
        td = th.find_next_sibling("td")
        k = th.get_text(" ", strip=True)
        if k and k not in pairs:
            pairs[k] = td.get_text(" ", strip=True) if td else ""

    baibai = pairs.get("売・貸の別", "")
    town = (pairs.get("所在地") or "").strip()
    town = re.sub(r"^矢掛町", "", town).strip()
    address = f"岡山県小田郡矢掛町{town}" if town else "岡山県小田郡矢掛町"

    # 価格：売却を優先。売却が無ければ賃貸のみ＝価格なし
    price = None
    pm = re.search(r"売却[（(]\s*([0-9０-９,，]+)\s*万円", baibai)
    if pm:
        price = parse_yen(_z2h(pm.group(1)) + "万円")

    structure = re.sub(r"\s*外\s*$", "", pairs.get("構造", "")).strip() or None

    desc_parts = []
    if pairs.get("設備"):
        desc_parts.append(f"設備：{pairs['設備']}")
    # タイトル冒頭の紹介文（テーブル外の説明）も拾えれば付ける
    content = "<br>".join(desc_parts)

    # 座標：所在地ジオコーディング（掲載座標なし）
    lat = lng = None
    g = geocode(address)
    if g:
        lat, lng = g.lat, g.lon
    if lat is None or lng is None:
        print(f"[yakage] 座標取得できずスキップ: {prop_id} {address}")
        return None

    body_text = soup.get_text(" ", strip=True)
    status = "成約済" if re.search(r"成約|商談中|契約済|募集終了", body_text) else "空き家バンク"

    price_label = f"{int(price / 10000):,}万円" if price else "価格応談"
    title = f"矢掛町{town}の空き家（{price_label}）"

    meta = {
        "price": price,
        "structure": structure,
        "address": address,
        "lat": lat, "lng": lng,
        "source_name": SOURCE_NAME,
        "source_url": url,
        "source_id": str(prop_id),
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title,
        content=content,
        taxonomies={"pref": "岡山県", "city": "矢掛町", "type": "戸建", "status": status},
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    ids = collect_ids(session)
    if limit:
        ids = ids[:limit]
    if ids:
        print(f"[yakage] 矢掛町: {len(ids)}件")
    records: list[AkiyaRecord] = []
    for pid in ids:
        rec = parse_detail(session, pid)
        if rec:
            records.append(rec)
    return records
