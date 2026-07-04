"""タイル座標変換と点-ポリゴン判定のユーティリティ。

不動産情報ライブラリの GIS 系 API は XYZ タイル（z/x/y）でデータを取得するため、
緯度経度 → タイル座標の変換と、取得した GeoJSON ポリゴンに対する
点内包判定（point-in-polygon）をここに集約する。外部依存は標準ライブラリのみ。
"""
from __future__ import annotations

import math
from typing import Iterable


def deg2num(lat: float, lon: float, z: int) -> tuple[int, int]:
    """緯度経度(度) → XYZ タイル座標 (x, y)。標準の Web メルカトル(slippy map)方式。"""
    lat_rad = math.radians(lat)
    n = 2 ** z
    x = int((lon + 180.0) / 360.0 * n)
    y = int((1.0 - math.asinh(math.tan(lat_rad)) / math.pi) / 2.0 * n)
    # 端の丸め対策でタイル範囲にクランプ
    x = min(max(x, 0), n - 1)
    y = min(max(y, 0), n - 1)
    return x, y


def num2deg(x: int, y: int, z: int) -> tuple[float, float]:
    """XYZ タイル座標 → そのタイル北西角の緯度経度(度)。デバッグ/検証用。"""
    n = 2 ** z
    lon = x / n * 360.0 - 180.0
    lat_rad = math.atan(math.sinh(math.pi * (1 - 2 * y / n)))
    return math.degrees(lat_rad), lon


def _point_in_ring(lon: float, lat: float, ring: list) -> bool:
    """レイキャスティング法。ring は [[lon, lat], ...]（GeoJSON 座標順）。"""
    inside = False
    j = len(ring) - 1
    for i in range(len(ring)):
        xi, yi = ring[i][0], ring[i][1]
        xj, yj = ring[j][0], ring[j][1]
        intersect = ((yi > lat) != (yj > lat)) and (
            lon < (xj - xi) * (lat - yi) / ((yj - yi) or 1e-15) + xi
        )
        if intersect:
            inside = not inside
        j = i
    return inside


def _point_in_polygon(lon: float, lat: float, polygon: list) -> bool:
    """polygon は GeoJSON Polygon の座標配列: [outer_ring, hole1, hole2, ...]。"""
    if not polygon:
        return False
    if not _point_in_ring(lon, lat, polygon[0]):
        return False
    # 穴（内側リング）に入っていれば外側とみなす
    for hole in polygon[1:]:
        if _point_in_ring(lon, lat, hole):
            return False
    return True


def point_in_geometry(lon: float, lat: float, geometry: dict) -> bool:
    """GeoJSON geometry（Polygon / MultiPolygon）に点が含まれるか。"""
    if not geometry:
        return False
    gtype = geometry.get("type")
    coords = geometry.get("coordinates")
    if gtype == "Polygon":
        return _point_in_polygon(lon, lat, coords)
    if gtype == "MultiPolygon":
        return any(_point_in_polygon(lon, lat, poly) for poly in coords)
    return False


def features_containing_point(
    lon: float, lat: float, features: Iterable[dict]
) -> list[dict]:
    """GeoJSON Feature 群のうち、点を内包するものを返す。"""
    hits = []
    for feat in features:
        if point_in_geometry(lon, lat, feat.get("geometry", {})):
            hits.append(feat)
    return hits


def haversine_m(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """2点間の距離(メートル)。ポイント系API（地価等）の最近傍探索に使う。"""
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp = math.radians(lat2 - lat1)
    dl = math.radians(lon2 - lon1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))
