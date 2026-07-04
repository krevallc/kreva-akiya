"""住所 → 緯度経度（ジオコーディング）。

国土地理院の住所検索API（キー不要・無料）を使用。
https://msearch.gsi.go.jp/address-search/AddressSearch?q=<住所>
返り値は GeoJSON FeatureCollection で、最上位候補の座標を採用する。
"""
from __future__ import annotations

import time
from dataclasses import dataclass

import requests

GSI_ENDPOINT = "https://msearch.gsi.go.jp/address-search/AddressSearch"
USER_AGENT = "KREVA-akiya-poc/0.1 (+https://kreva.co.jp; contact: info@kreva.co.jp)"


@dataclass
class GeocodeResult:
    address_query: str
    matched_title: str
    lat: float
    lon: float
    raw: dict


def geocode(address: str, *, timeout: float = 10.0, retries: int = 2) -> GeocodeResult | None:
    """住所文字列を緯度経度に変換。見つからなければ None。"""
    params = {"q": address}
    headers = {"User-Agent": USER_AGENT}
    last_err: Exception | None = None
    for attempt in range(retries + 1):
        try:
            resp = requests.get(GSI_ENDPOINT, params=params, headers=headers, timeout=timeout)
            resp.raise_for_status()
            data = resp.json()
            if not data:
                return None
            top = data[0]
            lon, lat = top["geometry"]["coordinates"]  # GeoJSON は [経度, 緯度]
            return GeocodeResult(
                address_query=address,
                matched_title=top.get("properties", {}).get("title", ""),
                lat=float(lat),
                lon=float(lon),
                raw=top,
            )
        except (requests.RequestException, ValueError, KeyError, IndexError) as e:
            last_err = e
            if attempt < retries:
                time.sleep(1.0 * (attempt + 1))
    if last_err:
        print(f"[geocode] 失敗: {address}: {last_err}")
    return None


if __name__ == "__main__":
    import sys

    q = sys.argv[1] if len(sys.argv) > 1 else "岡山県総社市中央一丁目1-1"
    r = geocode(q)
    if r:
        print(f"{q}\n  → {r.matched_title}  lat={r.lat}, lon={r.lon}")
    else:
        print(f"見つかりませんでした: {q}")
