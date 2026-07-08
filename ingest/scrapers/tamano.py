"""玉野市空き家情報（city.tamano.lg.jp）アダプタ。

玉野市の公式空き家情報。住まいる岡山非参加＝純増。物件リストは1つのPDF
（「空き家情報（令和◯年◯月◯日）」）に集約され、1物件1ページの定型レイアウト。
robots は PoliteSession が確認。PDFは pdfplumber でテキスト抽出してパースする。

親ページ /soshiki/20/2387.html から最新PDFのリンクを動的に取得（添付番号は更新で変わる）。
所在地は町名のみのため国土地理院ジオコーディング。写真はPDF内のみ（地図サムネfallback）。
用途地域から市街化調整区域を拾って kuiki_kubun に反映。

出典表記：玉野市空き家情報（玉野市建設部都市計画課）。
"""
from __future__ import annotations

import io
import re
import sys
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from wp_client import AkiyaRecord  # noqa: E402
from scrapers.http import PoliteSession  # noqa: E402
from geocode import geocode  # noqa: E402

BASE = "https://www.city.tamano.lg.jp"
LIST_PAGE = f"{BASE}/soshiki/20/2387.html"
SOURCE_NAME = "玉野市空き家情報"

_GENGO = {"令和": 2018, "平成": 1988, "昭和": 1925, "大正": 1911}


def _wareki(text: str) -> int | None:
    if not text:
        return None
    m = re.search(r"(令和|平成|昭和|大正)\s*(\d+)", text)
    if m:
        return _GENGO[m.group(1)] + int(m.group(2))
    m = re.search(r"(1[89]\d{2}|20\d{2})", text)
    return int(m.group(1)) if m else None


def _yen(text: str) -> int | None:
    m = re.search(r"([0-9,]+)\s*万円", text or "")
    return int(m.group(1).replace(",", "")) * 10000 if m else None


def _area(text: str) -> float | None:
    m = re.search(r"([0-9]+(?:\.[0-9]+)?)\s*(?:㎡|m2|平方メートル)", text or "")
    return float(m.group(1)) if m else None


_EXCLUDE = re.compile(r"申込|申請|届出|Q&A|ＱＡ|パンフ|変更")


def _norm(href: str) -> str:
    return href if href.startswith("http") else BASE + href


def _pick_pdf(html: str) -> str | None:
    """HTMLから物件リストPDF（アンカー文言に「空き家情報（…）」を含む）のhrefを返す。
    アンカー全体を取ってタグ除去後に部分一致で判定（内部マークアップに左右されない）。"""
    cands: list[tuple[str, str]] = []
    for m in re.finditer(r'<a\b[^>]*?href="([^"]+?\.pdf)"[^>]*>(.*?)</a>', html, re.I | re.S):
        href = m.group(1)
        text = re.sub(r"<[^>]+>", "", m.group(2))
        text = re.sub(r"\s+", " ", text).strip()
        cands.append((href, text))
        if "空き家情報" in text and "（" in text and not _EXCLUDE.search(text):
            return _norm(href)
    # 予備：BeautifulSoup（部分一致で判定）
    soup = BeautifulSoup(html, "html.parser")
    for a in soup.find_all("a"):
        href = a.get("href", "")
        txt = a.get_text(" ", strip=True)
        if href.lower().endswith(".pdf") and "空き家情報" in txt and "（" in txt and not _EXCLUDE.search(txt):
            return _norm(href)
    if cands:
        print("[tamano] PDF候補:", [(h.rsplit("/", 1)[-1], t[:28]) for h, t in cands][:8])
    return None


def find_pdf_url(session: PoliteSession) -> str | None:
    """親ページから物件リストPDF（「空き家情報（令和…）」）のURLを取得。"""
    r = session.get(LIST_PAGE)
    if r is None:
        print("[tamano] 親ページ取得失敗（robots/接続）")
        return None
    url = _pick_pdf(r.text)
    if url:
        return url
    n_pdf = r.text.lower().count(".pdf")
    print(f"[tamano] PDFリンク未検出（HTML {len(r.text)}B・pdf出現 {n_pdf}）")
    return None


def parse_pdf(data: bytes, *, date_str: str | None = None) -> list[AkiyaRecord]:
    import pdfplumber  # 遅延import（依存が無い環境でのモジュール読込を妨げない）

    records: list[AkiyaRecord] = []
    with pdfplumber.open(io.BytesIO(data)) as pdf:
        for page in pdf.pages:
            t = page.extract_text() or ""
            if "登録番号" not in t or "物件所在地" not in t:
                continue  # 1ページ目（要約）等はスキップ
            m = re.search(r"登録番号\s*([A-Za-z]?[0-9]+-[0-9]+[A-Za-z]?)\s*物件所在地\s*([^\n]+)", t)
            if not m:
                continue
            reg_no = m.group(1)
            town = re.sub(r"\s+", "", m.group(2))
            address = f"岡山県玉野市{town}" if town else "岡山県玉野市"

            structure = (re.search(r"構造\s*(.+?)\s*用途地域", t) or [None, None])[1]
            youto = (re.search(r"用途地域\s*(.+?)\s*(?:[A-Za-z]?[0-9]+-[0-9]+|\n)", t) or [None, None])[1]
            kuiki = "市街化調整区域" if youto and "調整区域" in youto else None
            youto_clean = None if not youto or "調整区域" in youto else youto

            build_year = _wareki((re.search(r"建築年\s*([^\n]+)", t) or [None, ""])[1])
            pm = re.search(r"売買\s*([0-9,]+\s*万円)", t)
            price = _yen(pm.group(1)) if pm else None
            land_area = _area((re.search(r"敷地面積\s*([0-9.]+\s*㎡)", t) or [None, ""])[1])

            # 建物面積：面積・間取り欄の各階㎡を合算
            seg = (re.search(r"面積・間取り等\s*(.+?)\s*(?:希望賃貸料|敷地面積)", t, re.S) or [None, ""])[1]
            floors = [float(x) for x in re.findall(r"([0-9]+(?:\.[0-9]+)?)\s*㎡", seg)]
            building_area = round(sum(floors), 2) if floors else None

            # 定性情報を説明文へ。設備系（電気/水道/トイレ/学区）は面積欄の同語と衝突するため
            # 「備考」以降の末尾ブロックから拾う。利用状況・補修・駐車場は上部から。
            desc = []
            riyou = (re.search(r"利用状況\s*(.+?)\s*補修", t) or [None, None])[1]
            if riyou:
                desc.append(f"利用状況：{riyou.strip()}")
            hoshu = (re.search(r"補修の要否\s*([^\n]+)", t) or [None, None])[1]
            if hoshu:
                desc.append(f"補修：{re.split(r'  +', hoshu.strip())[0]}")
            park = (re.search(r"駐車場\s*([^\n]+)", t) or [None, None])[1]
            if park:
                desc.append(f"駐車場：{re.split(r'  +', park.strip())[0][:30]}")
            tail = t[t.find("備考"):] if "備考" in t else ""
            for key in ("電気", "水道", "トイレ", "学区"):
                mm = re.search(key + r"\s*([^\n]+)", tail)
                if mm:
                    val = re.split(r"  +", mm.group(1).strip())[0][:40]
                    if val and val not in ("－", "-"):
                        desc.append(f"{key}：{val}")
            content = "<br>".join(desc)

            lat = lng = None
            g = geocode(address)
            if g:
                lat, lng = g.lat, g.lon
            if lat is None or lng is None:
                print(f"[tamano] 座標取得できずスキップ: 登録番号{reg_no} {address}")
                continue

            price_label = f"{int(price / 10000):,}万円" if price else "価格応談"
            title = f"玉野市{town}の空き家（{price_label}）"

            meta = {
                "price": price,
                "land_area": land_area,
                "building_area": building_area,
                "structure": structure,
                "build_year": build_year,
                "youto_chiiki": youto_clean,
                "kuiki_kubun": kuiki,
                "address": address,
                "lat": lat, "lng": lng,
                "source_name": SOURCE_NAME,
                "source_url": LIST_PAGE,
                "source_id": str(reg_no),
            }
            if date_str:
                meta["last_checked"] = date_str
            meta = {k: v for k, v in meta.items() if v is not None}

            records.append(AkiyaRecord(
                title=title,
                content=content,
                taxonomies={"pref": "岡山県", "city": "玉野市", "type": "戸建", "status": "空き家バンク"},
                meta=meta,
            ))
    return records


def scrape(session: PoliteSession | None = None, *, limit: int | None = None) -> list[AkiyaRecord]:
    session = session or PoliteSession()
    # 連絡先明示のUAは維持しつつ、WAF等が期待する標準ヘッダを補う（取得失敗対策）
    session.session.headers.setdefault("Accept", "text/html,application/xhtml+xml,application/pdf,*/*")
    session.session.headers.setdefault("Accept-Language", "ja,en;q=0.8")
    url = find_pdf_url(session)
    if not url:
        print("[tamano] 物件リストPDFのリンクが見つかりませんでした。")
        return []
    r = session.get(url)
    if r is None:
        return []
    records = parse_pdf(r.content)
    if limit:
        records = records[:limit]
    if records:
        print(f"[tamano] 玉野市: {len(records)}件")
    return records
