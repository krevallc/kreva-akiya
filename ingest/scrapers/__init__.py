"""空き家データの取り込みアダプタ群。

各サイトごとにアダプタを置き、共通の AkiyaRecord（wp_client）へ正規化する。
ガードレール（robots尊重・低頻度・出典明記）は http.PoliteSession で担保。
"""
