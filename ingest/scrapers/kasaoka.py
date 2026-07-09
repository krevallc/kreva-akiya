"""笠岡市 空き家・空き地バンク アダプタ。

笠岡市公式サイト内の検索（search.php）。robots.txt は無し（許可扱い）。
一覧: /teiju-akiya/search/search.php?search=1&page=N （10件/ページ）
詳細: /teiju-akiya/teijyuu/<ページID>.html

一覧には「物件情報（売買：〇〇）No.NNN」と「土地情報（〇〇）No.N」が混在する。
空き家検索の対象は建物なので **土地情報（空き地）は除外** する。
また取引形態が「賃貸」のみの物件は売買価格を持たないため既定では除外する
（--kinds に rent を含めた場合のみ取り込む）。他アダプタが kinds=buy 既定なのに合わせている。

座標は詳細ページに無い（地図はGoogle埋め込みで住所検索）ため geocode.py で付与する。
所在地欄は「笠岡市神島」のように **市名を含む** ので、県市を前置する際は
重複しないよう正規化する（井原Lifeで住所二重化→市中心に全件集約した不具合の再発防止）。

出典表記：笠岡市空き家・空き地バンク。
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
from scrapers.ok_smile import parse_yen  # noqa: E402
from geocode import geocode  # noqa: E402

BASE = "https://www.city.kasaoka.okayama.jp"
LIST_URL = f"{BASE}/teiju-akiya/search/search.php"
SOURCE_NAME = "笠岡市空き家・空き地バンク"

# 「物件情報（売買：神島）No.712」/「物件情報（売買・賃貸：山口）No.715」
TITLE_RE = re.compile(r"物件情報（([^：]+)：([^）]+)）No\.([0-9]+)")

_ERA = (("令和", 2018), ("平成", 1988), ("昭和", 1925))


def _html(r) -> str:
    """レスポンスをUTF-8で明示デコード。

    詳細ページは Content-Type が `text/html`（charset無し）で返るため、requests が
    RFC既定の latin-1 とみなして日本語が化ける。結果 h3 ラベル一致が全滅して
    価格・面積・構造などが全て None になっていた（玉野と同じ罠）。
    実体は meta charset=utf-8 なので UTF-8 を明示する。
    """
    try:
        return r.content.decode("utf-8")
    except UnicodeDecodeError:
        return r.content.decode(r.apparent_encoding or "cp932", errors="replace")


def _build_year(text: str) -> int | None:
    """'昭和51年' / '平成26年' / '2003年' → 西暦。不明なら None。"""
    if not text:
        return None
    t = text.strip()
    for era, base in _ERA:
        m = re.search(rf"{era}\s*([0-9]+)\s*年", t)
        if m:
            return base + int(m.group(1))
        if re.search(rf"{era}\s*元\s*年", t):
            return base + 1
    m = re.search(r"(19[0-9]{2}|20[0-9]{2})\s*年?", t)
    return int(m.group(1)) if m else None


def _area(text: str) -> float | None:
    """'174.51平方メートル（52.78坪）' → 174.51。

    ok_smile.parse_area は ㎡/m2/平米 のみ対応で「平方メートル」を拾えないため笠岡用に用意。
    先頭の数値のみを見る（括弧内の坪数を誤って拾わないよう単位を必須にする）。
    """
    if not text:
        return None
    m = re.search(r"([0-9]+(?:\.[0-9]+)?)\s*(?:平方メートル|㎡|m2|m²|平米)", text)
    return float(m.group(1)) if m else None


def _normalize_town(shozaichi: str) -> str | None:
    """'笠岡市神島' / '岡山県笠岡市神島' → '神島'（県市を剥がす）。"""
    if not shozaichi:
        return None
    t = re.sub(r"^\s*(岡山県)?\s*(笠岡市)?\s*", "", shozaichi.strip())
    return t or None


def _field(soup: BeautifulSoup, label: str) -> str | None:
    """h3ラベルの直後の div（akiya-*）のテキスト。"""
    for h3 in soup.find_all("h3"):
        if h3.get_text(strip=True) == label:
            nxt = h3.find_next_sibling()
            if nxt:
                v = nxt.get_text(" ", strip=True)
                return v if v and v not in ("－", "-", "―") else None
            break
    return None


def list_detail_urls(session: PoliteSession, max_pages: int = 25) -> list[tuple[str, str, str, str]]:
    """全ページを辿り (詳細URL, 取引形態, 町名, 登録No) を返す。土地情報は除外。"""
    out: list[tuple[str, str, str, str]] = []
    seen: set[str] = set()
    total: int | None = None
    for page in range(1, max_pages + 1):
        r = session.get(f"{LIST_URL}?search=1&page={page}")
        if r is None:
            break
        html = _html(r)
        if total is None:
            m = re.search(r"([0-9]+)件中", html)
            if m:
                total = int(m.group(1))
                print(f"[kasaoka] 一覧総数（空き地含む）: {total}件")
        soup = BeautifulSoup(html, "html.parser")
        anchors = soup.select("div.result_list_box a[href]")
        if not anchors:
            break
        for a in anchors:
            text = a.get_text(strip=True)
            m = TITLE_RE.search(text)
            if not m:
                continue  # 土地情報など
            href = a["href"]
            url = href if href.startswith("http") else BASE + href
            if url in seen:
                continue
            seen.add(url)
            out.append((url, m.group(1), m.group(2), m.group(3)))
    print(f"[kasaoka] 笠岡市: 空き家 {len(out)}件（土地情報は除外）")
    return out


def parse_detail(
    session: PoliteSession, url: str, torihiki: str, town_hint: str, reg_no: str
) -> AkiyaRecord | None:
    r = session.get(url)
    if r is None:
        return None
    soup = BeautifulSoup(_html(r), "html.parser")

    town = _normalize_town(_field(soup, "所在地") or "") or town_hint
    address = f"岡山県笠岡市{town}"

    kakaku = _field(soup, "価格・賃料") or ""
    # 「売買：300万円 賃料30,000円」→ 万円表記を優先して拾う（parse_yenが億/万を先に見る）
    price = parse_yen(kakaku)

    structure = _field(soup, "構造")
    build_year = _build_year(_field(soup, "建築年度") or "")
    land_area = _area(_field(soup, "敷地面積") or "")
    building_area = _area(_field(soup, "延床面積") or "")

    # 座標（住所→ジオコーディング）
    g = geocode(address)
    if not g:
        print(f"[kasaoka] 座標取得できずスキップ: {url}")
        return None
    lat, lng = g.lat, g.lon

    # 物件写真（/uploaded/ 配下・最大8枚）
    image_urls: list[str] = []
    for img in soup.select("img[src]"):
        src = img["src"]
        if "/uploaded/" not in src:
            continue
        full = src if src.startswith("http") else BASE + src
        if full not in image_urls:
            image_urls.append(full)
        if len(image_urls) >= 8:
            break
    image_url = image_urls[0] if image_urls else None

    # 説明文（購入検討に効く定性情報）
    lines: list[str] = []
    # オススメポイントは akiya-osusume-point1, -point2 … と1項目1divで並ぶ
    pts = [
        el.get_text(" ", strip=True)
        for el in soup.select('[class*="akiya-osusume-point"]')
    ]
    pts = [p for p in pts if p]
    if pts:
        lines.append("オススメポイント：" + "／".join(pts))
    for label in ("水道", "下水道", "トイレ", "風呂", "ガス", "駐車場", "テレビ", "インターネット"):
        v = _field(soup, label)
        if v:
            lines.append(f"{label}：{v}")
    shuzen = soup.select_one(".akiya-shuzenkasho")
    if shuzen:
        s = shuzen.get_text(" ", strip=True)
        if s:
            lines.append(f"修繕箇所・状態：{s}")
    for label in ("総合病院", "スーパー", "笠岡IC", "笠岡駅", "保育園", "小学校", "中学校"):
        v = _field(soup, label)
        if v:
            lines.append(f"{label}：{v}")
    if kakaku:
        lines.append(f"価格・賃料（原文）：{kakaku}")
    content = "<br>".join(lines)

    price_label = f"{price // 10000:,}万円" if price else "価格応談"
    title = f"笠岡市{town}の空き家（{price_label}）"

    meta = {
        "price": price,
        "land_area": land_area,
        "building_area": building_area,
        "structure": structure,
        "build_year": build_year,
        "address": address,
        "lat": lat, "lng": lng,
        "image_url": image_url,
        "image_urls": json.dumps(image_urls, ensure_ascii=False) if image_urls else None,
        "source_name": SOURCE_NAME,
        "source_url": url,
        "source_id": reg_no,
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title,
        content=content,
        taxonomies={"pref": "岡山県", "city": "笠岡市", "type": "戸建", "status": "空き家バンク"},
        meta=meta,
    )


def scrape(
    session: PoliteSession | None = None,
    *,
    limit: int | None = None,
    kinds: tuple[str, ...] = ("buy",),
) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    entries = list_detail_urls(session)

    if "rent" not in kinds:
        before = len(entries)
        entries = [e for e in entries if "売買" in e[1]]
        skipped = before - len(entries)
        if skipped:
            print(f"[kasaoka] 賃貸のみの物件を除外: {skipped}件（kinds={kinds}）")

    if limit:
        entries = entries[:limit]

    records: list[AkiyaRecord] = []
    for url, torihiki, town, reg_no in entries:
        rec = parse_detail(session, url, torihiki, town, reg_no)
        if rec:
            records.append(rec)
    return records
