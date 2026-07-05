"""WordPress への物件 upsert クライアント。

kreva-akiya プラグインの REST エンドポイント
    POST /wp-json/kreva-akiya/v1/items
に、正規化＋エンリッチ済みの物件を投入する。認証はアプリケーションパスワード(Basic)。

環境変数（.env）:
    WP_BASE_URL=https://kreva.co.jp
    WP_USER=<WordPressユーザー名>
    WP_APP_PASSWORD=<アプリケーションパスワード（空白は入れたままでOK）>
"""
from __future__ import annotations

import os
from dataclasses import dataclass, field, asdict

import requests

USER_AGENT = "KREVA-akiya-ingest/0.1 (+https://kreva.co.jp; contact: info@kreva.co.jp)"


@dataclass
class AkiyaRecord:
    """WPへ送る1物件。meta のキーはプラグインの kreva_akiya_meta_schema と一致させる。"""
    title: str
    meta: dict
    content: str = ""
    status: str = "publish"
    taxonomies: dict = field(default_factory=dict)  # {pref, city, type, status}
    slug: str = ""  # ユニークな英数スラッグ（例: soja-1855332）。空ならWP側で自動生成

    def to_body(self) -> dict:
        body = {
            "title": self.title,
            "content": self.content,
            "status": self.status,
            "taxonomies": self.taxonomies,
            "meta": self.meta,
        }
        if self.slug:
            body["slug"] = self.slug
        return body


class WPClient:
    def __init__(self, base_url: str | None = None, user: str | None = None, app_password: str | None = None):
        self.base_url = (base_url or os.environ.get("WP_BASE_URL", "")).rstrip("/")
        self.user = user or os.environ.get("WP_USER", "")
        self.app_password = (app_password or os.environ.get("WP_APP_PASSWORD", "")).replace(" ", "")
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": USER_AGENT})

    @property
    def configured(self) -> bool:
        return bool(self.base_url and self.user and self.app_password)

    def upsert(self, record: AkiyaRecord, *, timeout: float = 20.0) -> dict:
        if not self.configured:
            raise RuntimeError("WP_BASE_URL / WP_USER / WP_APP_PASSWORD が未設定です。")
        url = f"{self.base_url}/wp-json/kreva-akiya/v1/items"
        r = self.session.post(
            url,
            json=record.to_body(),
            auth=(self.user, self.app_password),
            timeout=timeout,
        )
        r.raise_for_status()
        return r.json()

    def upsert_many(self, records: list[AkiyaRecord]) -> list[dict]:
        out = []
        for rec in records:
            try:
                out.append(self.upsert(rec))
            except requests.RequestException as e:
                print(f"[wp] 投入失敗: {rec.title}: {e}")
                out.append({"ok": False, "title": rec.title, "error": str(e)})
        return out


if __name__ == "__main__":
    # 疎通テスト（.env設定済みなら1件ダミー投入）
    import poc  # noqa: F401 -- _load_dotenv を借用
    poc._load_dotenv()
    c = WPClient()
    print("configured:", c.configured, "base:", c.base_url)
    if c.configured:
        demo = AkiyaRecord(
            title="【テスト】総社市サンプル空き家",
            content="投入テスト。確認後は削除してください。",
            taxonomies={"pref": "岡山県", "city": "総社市", "type": "戸建", "status": "テスト"},
            meta={
                "price": 3800000, "address": "岡山県総社市中央一丁目1-1",
                "lat": 34.673176, "lng": 133.747147,
                "source_name": "ingest-test", "source_id": "demo-1",
                "last_checked": "2026-07-02", "is_kreva": False,
            },
        )
        print(c.upsert(demo))
