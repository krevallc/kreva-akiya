"""不動産情報ライブラリ API クライアント（国土交通省）。

ベースURL: https://www.reinfolib.mlit.go.jp/ex-api/external/{API識別子}
認証ヘッダ: Ocp-Apim-Subscription-Key: <APIキー>
GIS系: response_format=geojson&z=<zoom>&x=<tileX>&y=<tileY>

APIキーは無料申請: https://www.reinfolib.mlit.go.jp/api/request/

このモジュールは「緯度経度 → 各種GISデータの該当値」を返すことに専念する。
- ポリゴン系（区域区分/用途地域/災害）: 対象タイルを取得し、点を内包する feature を抽出。
- ポイント系（地価公示・地価調査）: 対象タイルの点群から最近傍を抽出。
"""
from __future__ import annotations

import os
import re
import time
from dataclasses import dataclass, field

import requests

POINT_ZOOM = 13  # ポイント系（地価・施設）は広めのタイルで最寄りを拾う（農村部対策）

from tiles import deg2num, features_containing_point, haversine_m

BASE = "https://www.reinfolib.mlit.go.jp/ex-api/external"
USER_AGENT = "KREVA-akiya-poc/0.1 (+https://kreva.co.jp; contact: info@kreva.co.jp)"

# 各GIS APIの識別子と、リクエストに使うズーム（各APIの提供ズーム範囲 11–15 の範囲で最も詳細なもの）
API = {
    # 規制
    "kuiki_kubun": ("XKT001", "都市計画決定GIS（区域区分：市街化区域/市街化調整区域）"),
    "youto_chiiki": ("XKT002", "都市計画決定GIS（用途地域）"),
    # 相場
    "chika_point": ("XPT002", "地価公示・地価調査ポイント"),
    # 災害
    "flood": ("XKT026", "洪水浸水想定区域"),
    "hightide": ("XKT027", "高潮浸水想定区域"),
    "tsunami": ("XKT028", "津波浸水想定"),
    "landslide": ("XKT029", "土砂災害警戒区域"),
    "liquefaction": ("XKT025", "液状化傾向図"),
    # 生活・周辺（自動付加）
    "school_elem": ("XKT004", "小学校区"),
    "school_junior": ("XKT005", "中学校区"),
    "hospital": ("XKT010", "医療機関"),
    "daycare": ("XKT007", "保育園・幼稚園等"),
    "shelter": ("XGT001", "指定緊急避難場所"),
    "future_pop": ("XKT013", "将来推計人口250mメッシュ"),
}
DEFAULT_ZOOM = 15  # 提供範囲 11–15 のうち最も詳細


class ReinfolibError(RuntimeError):
    pass


@dataclass
class Enrichment:
    """1地点ぶんのエンリッチ結果。値が取れない項目は None のまま。"""
    lat: float
    lon: float
    kuiki_kubun: str | None = None          # 例: 市街化区域 / 市街化調整区域 / 非線引き
    youto_chiiki: str | None = None          # 例: 第一種低層住居専用地域
    kenpei_ritsu: float | None = None        # 建蔽率(%)
    yoseki_ritsu: float | None = None        # 容積率(%)
    chika_nearest: dict | None = None        # 最近傍の地価ポイント(要約)
    hazards: dict = field(default_factory=dict)  # {flood: {...}, landslide: {...}, ...}
    flood_depth: str | None = None           # 洪水想定浸水深（該当時）
    liquefaction: str | None = None          # 液状化傾向
    # 生活・周辺
    school_elem: str | None = None           # 小学校区
    school_junior: str | None = None         # 中学校区
    nearest_hospital: str | None = None
    nearest_hospital_m: float | None = None
    nearest_daycare: str | None = None
    nearest_daycare_m: float | None = None
    nearest_shelter: str | None = None
    nearest_shelter_m: float | None = None
    future_pop: str | None = None            # 将来推計人口(参考)
    sources: list[str] = field(default_factory=list)
    notes: list[str] = field(default_factory=list)

    def to_meta(self) -> dict:
        """kreva-akiya プラグインの meta キーへマッピング（WP投入用）。None は除外。"""
        chika_price = None
        if self.chika_nearest:
            p = self.chika_nearest.get("properties", {})
            for k in ("u_current_years_price_ja", "価格", "価格(円/m²)", "L01_006"):
                raw = p.get(k)
                if raw not in (None, "", "-"):
                    digits = re.sub(r"[^0-9.]", "", str(raw))  # 単位・カンマを除去
                    if digits:
                        try:
                            chika_price = float(digits)
                        except ValueError:
                            pass
                    break
        m = {
            "lat": self.lat, "lng": self.lon,
            "kuiki_kubun": self.kuiki_kubun, "youto_chiiki": self.youto_chiiki,
            "kenpei": self.kenpei_ritsu, "yoseki": self.yoseki_ritsu,
            "chika_price": chika_price,
            "chika_dist_m": self.chika_nearest.get("distance_m") if self.chika_nearest else None,
            "hazard_flood": "flood" in self.hazards,
            "hazard_flood_depth": self.flood_depth,
            "hazard_landslide": "landslide" in self.hazards,
            "hazard_tsunami": "tsunami" in self.hazards,
            "hazard_hightide": "hightide" in self.hazards,
            "liquefaction": self.liquefaction,
            "school_elem": self.school_elem, "school_junior": self.school_junior,
            "nearest_hospital": self.nearest_hospital, "nearest_hospital_m": self.nearest_hospital_m,
            "nearest_daycare": self.nearest_daycare, "nearest_daycare_m": self.nearest_daycare_m,
            "nearest_shelter": self.nearest_shelter, "nearest_shelter_m": self.nearest_shelter_m,
            "future_pop": self.future_pop,
        }
        return {k: v for k, v in m.items() if v is not None}


class ReinfolibClient:
    def __init__(self, api_key: str | None = None, *, sleep: float = 0.3):
        self.api_key = api_key or os.environ.get("REINFOLIB_API_KEY", "")
        self.sleep = sleep
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": USER_AGENT})

    @property
    def has_key(self) -> bool:
        return bool(self.api_key)

    def _get_tile_features(self, api_id: str, z: int, x: int, y: int, extra: dict | None = None) -> list[dict]:
        if not self.has_key:
            raise ReinfolibError("REINFOLIB_API_KEY が未設定です（無料申請が必要）。")
        url = f"{BASE}/{api_id}"
        params = {"response_format": "geojson", "z": z, "x": x, "y": y}
        if extra:
            params.update(extra)
        headers = {"Ocp-Apim-Subscription-Key": self.api_key}
        for attempt in range(3):
            try:
                r = self.session.get(url, params=params, headers=headers, timeout=15)
                if r.status_code == 429:  # レート制限
                    time.sleep(2.0 * (attempt + 1))
                    continue
                r.raise_for_status()
                time.sleep(self.sleep)  # 低頻度アクセス
                data = r.json()
                return data.get("features", []) if isinstance(data, dict) else []
            except requests.RequestException as e:
                if attempt == 2:
                    raise ReinfolibError(f"{api_id} 取得失敗: {e}") from e
                time.sleep(1.0 * (attempt + 1))
        return []

    def _polygon_hit(self, key: str, lat: float, lon: float, z: int = DEFAULT_ZOOM, extra: dict | None = None):
        api_id, _label = API[key]
        x, y = deg2num(lat, lon, z)
        feats = self._get_tile_features(api_id, z, x, y, extra)
        hits = features_containing_point(lon, lat, feats)
        return hits[0]["properties"] if hits else None

    def _nearest_point(self, key: str, lat: float, lon: float, z: int = DEFAULT_ZOOM, extra: dict | None = None):
        api_id, _label = API[key]
        x, y = deg2num(lat, lon, z)
        feats = self._get_tile_features(api_id, z, x, y, extra)
        best, best_d = None, float("inf")
        for f in feats:
            g = f.get("geometry", {})
            if g.get("type") != "Point":
                continue
            plon, plat = g["coordinates"][0], g["coordinates"][1]
            d = haversine_m(lat, lon, plat, plon)
            if d < best_d:
                best, best_d = f, d
        if best is None:
            return None
        return {"distance_m": round(best_d, 1), "properties": best.get("properties", {})}

    @staticmethod
    def _first(props: dict, *keys):
        """プロパティ名の揺れに強い取り出し（候補キーを順に試す）。"""
        for k in keys:
            if k in props and props[k] not in (None, "", "-"):
                return props[k]
        return None

    def enrich(self, lat: float, lon: float) -> Enrichment:
        """1地点をエンリッチ。個々の項目は失敗しても全体は返す（notes に記録）。"""
        e = Enrichment(lat=lat, lon=lon)

        # 区域区分（市街化区域 / 市街化調整区域）
        try:
            props = self._polygon_hit("kuiki_kubun", lat, lon)
            if props:
                e.kuiki_kubun = self._first(props, "area_classification_ja")
                e.sources.append("区域区分: 国土交通省 不動産情報ライブラリ(XKT001)")
        except ReinfolibError as ex:
            e.notes.append(str(ex))

        # 用途地域・建蔽率・容積率
        try:
            props = self._polygon_hit("youto_chiiki", lat, lon)
            if props:
                e.youto_chiiki = self._first(props, "use_area_ja")
                e.kenpei_ritsu = _to_float(self._first(props, "u_building_coverage_ratio_ja"))
                e.yoseki_ritsu = _to_float(self._first(props, "u_floor_area_ratio_ja"))
                e.sources.append("用途地域: 国土交通省 不動産情報ライブラリ(XKT002)")
        except ReinfolibError as ex:
            e.notes.append(str(ex))

        # 地価（最近傍の地価公示/調査ポイント）※year 必須。最新年から順に試す
        try:
            for yr in (2025, 2024, 2023):
                near = self._nearest_point("chika_point", lat, lon, z=POINT_ZOOM, extra={"year": yr})
                if near:
                    e.chika_nearest = near
                    e.sources.append(f"地価: 国土交通省 不動産情報ライブラリ(XPT002/{yr})")
                    break
        except ReinfolibError as ex:
            e.notes.append(str(ex))

        # 災害（洪水・土砂・津波・高潮）: 内包すれば該当プロパティを格納
        for key in ("flood", "landslide", "tsunami", "hightide"):
            try:
                props = self._polygon_hit(key, lat, lon)
                if props:
                    e.hazards[key] = props
            except ReinfolibError as ex:
                e.notes.append(str(ex))
        if e.hazards:
            e.sources.append("災害: 国土交通省 不動産情報ライブラリ(XKT026-029)")
        # 洪水想定浸水深（該当時、A31a_205 の浸水深ランクコードから）
        if "flood" in e.hazards:
            e.flood_depth = _flood_depth_label(self._first(e.hazards["flood"], "A31a_205"))

        # 液状化傾向
        try:
            props = self._polygon_hit("liquefaction", lat, lon)
            if props:
                level = self._first(props, "liquefaction_tendency_level")
                topo = self._first(props, "topographic_classification_name_ja")
                parts = [s for s in (topo, (f"傾向レベル{level}" if level else None)) if s]
                e.liquefaction = " / ".join(parts) or None
                e.sources.append("液状化: 国土交通省 不動産情報ライブラリ(XKT025)")
        except ReinfolibError as ex:
            e.notes.append(str(ex))

        # 学区（小学校区 / 中学校区）
        try:
            p = self._polygon_hit("school_elem", lat, lon)
            if p:
                e.school_elem = self._first(p, "A27_004_ja", "A27_003")
            p = self._polygon_hit("school_junior", lat, lon)
            if p:
                e.school_junior = self._first(p, "A32_004_ja", "A32_003")
            if e.school_elem or e.school_junior:
                e.sources.append("学区: 国土交通省 不動産情報ライブラリ(XKT004/005)")
        except ReinfolibError as ex:
            e.notes.append(str(ex))

        # 最寄り施設（医療機関 / 保育・幼稚園 / 指定緊急避難場所）
        for key, dst_name, dst_m, name_keys in (
            ("hospital", "nearest_hospital", "nearest_hospital_m", ("P04_002_ja", "P04_001_name_ja")),
            ("daycare", "nearest_daycare", "nearest_daycare_m", ("preSchoolName_ja",)),
            ("shelter", "nearest_shelter", "nearest_shelter_m", ("facility_name_ja",)),
        ):
            try:
                near = self._nearest_point(key, lat, lon, z=POINT_ZOOM)
                if near:
                    setattr(e, dst_name, self._first(near["properties"], *name_keys))
                    setattr(e, dst_m, near["distance_m"])
            except ReinfolibError as ex:
                e.notes.append(str(ex))
        if e.nearest_hospital_m is not None or e.nearest_shelter_m is not None:
            e.sources.append("周辺施設: 国土交通省 不動産情報ライブラリ(XKT010/007, XGT001)")

        # 将来推計人口（250mメッシュ・参考）PTN_<年> が総人口
        try:
            p = self._polygon_hit("future_pop", lat, lon)
            if p:
                pop_base = self._first(p, "PTN_2020", "PT00_2025")
                pop_2050 = self._first(p, "PTN_2050", "PT00_2050")
                if pop_base and pop_2050:
                    try:
                        ratio = (float(pop_2050) / float(pop_base) - 1.0) * 100
                        e.future_pop = f"2050年に約{ratio:+.0f}%（250mメッシュ推計）"
                    except (TypeError, ValueError, ZeroDivisionError):
                        e.future_pop = None
                e.sources.append("将来人口: 国土交通省 不動産情報ライブラリ(XKT013)")
        except ReinfolibError as ex:
            e.notes.append(str(ex))

        return e


def _to_float(v):
    try:
        return float(str(v).replace("%", "").strip())
    except (TypeError, ValueError):
        return None


# 洪水浸水想定区域（想定最大規模）の浸水深ランクコード → 表示
_FLOOD_RANK = {
    "1": "0.5m未満", "2": "0.5〜3.0m未満", "3": "3.0〜5.0m未満",
    "4": "5.0〜10.0m未満", "5": "10.0〜20.0m未満", "6": "20.0m以上",
}


def _flood_depth_label(code):
    if code in (None, ""):
        return None
    return _FLOOD_RANK.get(str(code).strip(), None)
