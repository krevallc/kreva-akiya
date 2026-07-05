"""住まいる岡山（ok-smile.jp）アダプタ。

岡山県空き家情報流通システム。県内18+自治体の空き家バンク物件を集約。
robots.txt は全許可（Disallow 空）。空き家バンク特集の一覧から p_no を集め、
詳細ページ /property/detail?p_no=<12桁> をパースして正規化する。

出典表記：住まいる岡山（岡山県宅地建物取引業協会／岡山県不動産協会）。
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

BASE = "https://www.ok-smile.jp"
SOURCE_NAME = "住まいる岡山"

# 岡山県の市区町村コード（JIS）。空き家バンク非参加・0件の自治体はスキップされる。
OKAYAMA_CODES = {
    "33101": "岡山市北区", "33102": "岡山市中区", "33103": "岡山市東区", "33104": "岡山市南区",
    "33202": "倉敷市", "33203": "津山市", "33204": "玉野市", "33205": "笠岡市",
    "33207": "井原市", "33208": "総社市", "33209": "高梁市", "33210": "新見市",
    "33211": "備前市", "33212": "瀬戸内市", "33213": "赤磐市", "33214": "真庭市",
    "33215": "美作市", "33216": "浅口市", "33346": "和気町", "33423": "早島町",
    "33445": "里庄町", "33461": "矢掛町", "33586": "新庄村", "33606": "鏡野町",
    "33622": "勝央町", "33623": "奈義町", "33643": "西粟倉村", "33663": "久米南町",
    "33666": "美咲町", "33681": "吉備中央町",
}

# 優先着手5市（KREVA稼働エリア）
PRIORITY_CODES = ["33101", "33102", "33103", "33104", "33202", "33208", "33207", "33209"]


# ---- パース用ヘルパー ---------------------------------------------------

def parse_yen(text: str) -> int | None:
    """'330万円' / '1,280万円' / '1億2800万円' / '2億円' → 円(int)。"""
    if not text:
        return None
    t = text.replace(",", "").replace("　", "")
    total = 0
    m_oku = re.search(r"([0-9]+(?:\.[0-9]+)?)億", t)
    m_man = re.search(r"([0-9]+(?:\.[0-9]+)?)万", t)
    if m_oku:
        total += float(m_oku.group(1)) * 100000000
    if m_man:
        total += float(m_man.group(1)) * 10000
    if not m_oku and not m_man:
        m_yen = re.search(r"([0-9]+)円", t)
        if m_yen:
            total = float(m_yen.group(1))
    return int(total) if total > 0 else None


def parse_area(text: str) -> float | None:
    """'30086.02㎡(約9101.02坪)' → 30086.02。"""
    if not text:
        return None
    m = re.search(r"([0-9]+(?:\.[0-9]+)?)\s*(?:㎡|m2|m²|平米)", text)
    return float(m.group(1)) if m else None


def parse_build_year(text: str) -> int | None:
    """'1976年07月' / '1976年' → 1976。"""
    if not text:
        return None
    m = re.search(r"(1[89]\d{2}|20\d{2})\s*年", text)
    return int(m.group(1)) if m else None


def parse_kenpei_yoseki(text: str):
    """'60%/200%' や '60% ・ 200%' → (60.0, 200.0)。取れない側は None。"""
    if not text:
        return None, None
    nums = re.findall(r"([0-9]+(?:\.[0-9]+)?)\s*%", text)
    kenpei = float(nums[0]) if len(nums) >= 1 else None
    yoseki = float(nums[1]) if len(nums) >= 2 else None
    return kenpei, yoseki


def city_from_address(addr: str) -> str | None:
    """'岡山県総社市中央…' → '総社市'（政令市は '岡山市' に丸める）。"""
    if not addr:
        return None
    m = re.search(r"岡山県(岡山市)", addr)
    if m:
        return "岡山市"
    m = re.search(r"岡山県([^\s0-9]+?[市町村])", addr)
    return m.group(1) if m else None


def type_from_title(title: str) -> str:
    if not title:
        return "戸建"
    if "土地" in title:
        return "土地"
    if "マンション" in title:
        return "マンション"
    if "店舗" in title or "事業" in title:
        return "事業用"
    return "戸建"


# ---- 一覧 → p_no 収集 ---------------------------------------------------

def list_ids(session: PoliteSession, city_code: str, kind: str = "buy", max_pages: int = 20) -> list[str]:
    """空き家バンク特集の一覧から p_no を収集（ページ送り対応）。"""
    ids: list[str] = []
    page = 1
    while page <= max_pages:
        url = (
            f"{BASE}/property/highlight/vacant-house/{kind}/area/list"
            f"?okayama_akiya_bank%5B%5D=FTTG01&m_adr%5B%5D={city_code}&page={page}"
        )
        r = session.get(url)
        if r is None:
            break
        found = sorted(set(re.findall(r"p_no=([0-9]{6,})", r.text)))
        new = [p for p in found if p not in ids]
        ids.extend(new)
        # 次ページの有無：pagination に自分より大きいページ番号があるか
        pages = [int(n) for n in re.findall(r"page=([0-9]+)", r.text)]
        if not new or not pages or max(pages) <= page:
            break
        page += 1
    return ids


# ---- 詳細 → 正規化レコード ----------------------------------------------

def _labelled_values(soup: BeautifulSoup) -> dict:
    """info-label/info-val, under-label/under-val, card-label/card-value を辞書化。"""
    out: dict[str, str] = {}
    for lab_cls, val_cls, tag in (
        ("info-label", "info-val", "td"),
        ("under-label", "under-val", "td"),
        ("card-label", "card-value", "div"),
    ):
        for lab in soup.select(f"{tag}.{lab_cls}"):
            val = lab.find_next_sibling(tag)
            if val is None and tag == "div":
                # card 構造は親内の card-value を探す
                parent = lab.parent
                val = parent.find(class_=val_cls) if parent else None
            key = lab.get_text(strip=True)
            if key and key not in out:
                out[key] = val.get_text(" ", strip=True) if val else ""
    return out


def parse_detail(session: PoliteSession, p_no: str, kind: str = "buy") -> AkiyaRecord | None:
    url = f"{BASE}/property/detail?p_no={p_no}"
    r = session.get(url)
    if r is None:
        return None
    soup = BeautifulSoup(r.text, "html.parser")

    title = (soup.title.get_text(strip=True) if soup.title else "").split(" - ")[0].strip()

    # 価格
    price = None
    price_el = soup.select_one(".price")
    if price_el:
        price = parse_yen(price_el.get_text(strip=True))

    labels = _labelled_values(soup)

    # 所在地・緯度経度（所在地セル内の Google Maps リンク q=lat,lng）
    address = labels.get("所在地", "") or None
    lat = lng = None
    for a in soup.select("a[href*='maps?q=']"):
        m = re.search(r"maps\?q=([0-9.]+),([0-9.]+)", a.get("href", ""))
        if m:
            lat, lng = float(m.group(1)), float(m.group(2))
            break
    if address:
        # info-val にはリンク文言（mapを見る / 地図）が混じるので除去
        address = re.sub(r"\s*(mapを見る|地図で見る|地図を見る|map|地図).*$", "", address, flags=re.I).strip()

    land_area = parse_area(labels.get("土地面積", ""))
    building_area = parse_area(labels.get("建物面積", ""))
    layout = labels.get("間取り") or None
    build_year = parse_build_year(labels.get("築年月", "") or labels.get("築年", ""))
    structure = labels.get("構造") or None
    youto = labels.get("用途地域")
    youto = None if youto in (None, "", "無指定", "指定なし") else youto
    kenpei, yoseki = parse_kenpei_yoseki(labels.get("建ぺい率・容積率", ""))
    # 都市計画欄に市街化区域/調整区域が入ることがある（APIエンリッチで上書きされる想定の補完値）
    toshi = labels.get("都市計画", "")
    kuiki = None
    if "調整区域" in toshi:
        kuiki = "市街化調整区域"
    elif "市街化区域" in toshi:
        kuiki = "市街化区域"

    if lat is None or lng is None:
        # 座標が取れない物件は地図に出せない。スキップ（ログ）
        print(f"[ok_smile] 座標なしのためスキップ: p_no={p_no}")
        return None

    # 物件写真（subcenter.jp 配信）。タイトル中の表示用物件番号に一致する写真を優先
    image_url = None
    image_urls: list[str] = []
    photos = re.findall(r"https://[a-z0-9.]+\.subcenter\.jp/[^\"']+\.jpe?g", r.text)
    if photos:
        m_disp = re.search(r"物件詳細\((\d+)\)", title)
        own = [p for p in photos if m_disp and m_disp.group(1) in p]
        pool = own or photos
        seen_ph: set[str] = set()
        for ph in pool:
            if ph not in seen_ph:
                seen_ph.add(ph)
                image_urls.append(ph)
            if len(image_urls) >= 8:
                break
        image_url = image_urls[0]

    city = city_from_address(address or "")
    ptype = type_from_title(title)

    meta = {
        "price": price,
        "land_area": land_area,
        "building_area": building_area,
        "layout": layout,
        "build_year": build_year,
        "structure": structure,
        "address": address,
        "lat": lat, "lng": lng,
        "youto_chiiki": youto,
        "kenpei": kenpei, "yoseki": yoseki,
        "kuiki_kubun": kuiki,
        "image_url": image_url,
        "image_urls": json.dumps(image_urls, ensure_ascii=False) if image_urls else None,
        "source_name": SOURCE_NAME,
        "source_url": url,
        "source_id": p_no,
    }
    meta = {k: v for k, v in meta.items() if v is not None}

    return AkiyaRecord(
        title=title or f"{city or '岡山県'}の空き家（{p_no}）",
        content="",
        taxonomies={
            "pref": "岡山県",
            "city": city or "",
            "type": ptype,
            "status": "空き家バンク",
        },
        meta=meta,
    )


def scrape(session: PoliteSession | None = None, *, city_codes: list[str] | None = None,
           kinds=("buy",), limit_per_city: int | None = None) -> list[AkiyaRecord]:
    """指定自治体×種別の空き家バンク物件を収集して AkiyaRecord のリストを返す。"""
    session = session or PoliteSession()
    codes = city_codes or list(OKAYAMA_CODES.keys())
    records: list[AkiyaRecord] = []
    for code in codes:
        for kind in kinds:
            ids = list_ids(session, code, kind)
            if limit_per_city:
                ids = ids[:limit_per_city]
            if ids:
                print(f"[ok_smile] {OKAYAMA_CODES.get(code, code)} {kind}: {len(ids)}件")
            for p_no in ids:
                rec = parse_detail(session, p_no, kind)
                if rec:
                    records.append(rec)
    return records
