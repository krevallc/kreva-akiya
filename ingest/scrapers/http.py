"""丁寧なHTTPセッション（スクレイピング ガードレール）。

- User-Agent に連絡先を明示
- リクエスト間に最低間隔（既定2.0秒）を空ける＝低頻度・並列しない
- 429/5xx はバックオフして数回リトライ
- robots.txt をチェックし、Disallow のパスは取得しない（既定で尊重）

設計書 §7 のガードレールに対応。
"""
from __future__ import annotations

import time
import urllib.robotparser
from urllib.parse import urlparse

import requests

DEFAULT_UA = (
    "Mozilla/5.0 (compatible; KREVA-akiya-ingest/0.1; "
    "+https://kreva.co.jp; contact info@kreva.co.jp)"
)


class PoliteSession:
    def __init__(self, *, min_interval: float = 2.0, ua: str = DEFAULT_UA, respect_robots: bool = True):
        self.min_interval = min_interval
        self.respect_robots = respect_robots
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": ua})
        self.ua = ua
        self._last = 0.0
        self._robots: dict[str, urllib.robotparser.RobotFileParser | None] = {}

    def _throttle(self) -> None:
        wait = self.min_interval - (time.time() - self._last)
        if wait > 0:
            time.sleep(wait)
        self._last = time.time()

    def _robots_ok(self, url: str) -> bool:
        if not self.respect_robots:
            return True
        parts = urlparse(url)
        base = f"{parts.scheme}://{parts.netloc}"
        if base not in self._robots:
            rp = urllib.robotparser.RobotFileParser()
            rp.set_url(base + "/robots.txt")
            try:
                rp.read()
                self._robots[base] = rp
            except Exception:
                self._robots[base] = None  # robots取得不可＝許可扱い（存在しない等）
        rp = self._robots[base]
        if rp is None:
            return True
        return rp.can_fetch(self.ua, url)

    def get(self, url: str, *, timeout: float = 40.0, retries: int = 3) -> requests.Response | None:
        if not self._robots_ok(url):
            print(f"[robots] 取得不可（Disallow）: {url}")
            return None
        for attempt in range(retries):
            self._throttle()
            try:
                r = self.session.get(url, timeout=timeout, allow_redirects=True)
                if r.status_code in (429, 500, 502, 503, 504):
                    time.sleep(min_backoff(attempt))
                    continue
                r.raise_for_status()
                return r
            except requests.RequestException as e:
                if attempt == retries - 1:
                    print(f"[http] 失敗: {url}: {e}")
                    return None
                time.sleep(min_backoff(attempt))
        return None


def min_backoff(attempt: int) -> float:
    return 2.0 * (attempt + 1)
