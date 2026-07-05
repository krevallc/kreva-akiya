/* KREVA 空き家 検索ページ：地図(Leaflet+地理院タイル+ハザード)＋絞り込み＋カード。
   データは REST /kreva-akiya/v1/search から取得。設定は window.KREVA_AKIYA。 */
(function () {
	'use strict';
	var CFG = window.KREVA_AKIYA || {};
	if (!window.L || !document.getElementById('kakiya-map')) return;

	var map, markersLayer, byId = {};

	function baseLayers() {
		var out = {};
		Object.keys(CFG.baseTiles || {}).forEach(function (k) {
			var t = CFG.baseTiles[k];
			out[t.label] = L.tileLayer(t.url, { attribution: t.attr, maxZoom: 18 });
		});
		return out;
	}

	function hazardLayers() {
		var out = {};
		(CFG.hazardTiles || []).forEach(function (h) {
			out[h.label] = L.tileLayer(h.url, { opacity: 0.6, maxNativeZoom: 17, maxZoom: 18 });
		});
		return out;
	}

	function initMap() {
		var bases = baseLayers();
		var first = bases[Object.keys(bases)[0]];
		map = L.map('kakiya-map', {
			center: CFG.defaultCenter || [34.66, 133.75],
			zoom: CFG.defaultZoom || 10,
			layers: [first]
		});
		L.control.layers(bases, hazardLayers(), { collapsed: true }).addTo(map);
		L.control.attribution({ prefix: false }).addAttribution(CFG.attribution || '').addTo(map);
		markersLayer = L.layerGroup().addTo(map);
	}

	function collectFilters() {
		var params = { per_page: 500 }; // 全件表示（既定200では県全域をカバーできない）
		document.querySelectorAll('[data-filter]').forEach(function (el) {
			var key = el.getAttribute('data-filter');
			if (el.type === 'checkbox') {
				if (el.checked) params[key] = el.value;
			} else if (el.value !== '') {
				params[key] = el.value;
			}
		});
		// 万円 → 円
		if (params.price_min_man) { params.price_min = (parseFloat(params.price_min_man) || 0) * 10000; delete params.price_min_man; }
		if (params.price_max_man) { params.price_max = (parseFloat(params.price_max_man) || 0) * 10000; delete params.price_max_man; }
		return params;
	}

	function fetchItems() {
		var params = collectFilters();
		var qs = Object.keys(params).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
		}).join('&');
		var url = CFG.restSearch + (qs ? '?' + qs : '');
		setStatus('検索中…');
		fetch(url, { headers: { 'Accept': 'application/json' } })
			.then(function (r) { return r.json(); })
			.then(function (data) { render(data.items || []); setCount(data.count || 0); })
			.catch(function () { setStatus('取得に失敗しました'); });
	}

	function render(items) {
		markersLayer.clearLayers();
		byId = {};
		var cards = document.getElementById('kakiya-cards');
		cards.innerHTML = '';
		if (!items.length) { cards.innerHTML = '<p class="kakiya-empty">条件に合う物件がありません。</p>'; return; }

		var bounds = [];
		items.forEach(function (it) {
			var m = L.marker([it.lat, it.lng], it.is_kreva ? { title: it.title, riseOnHover: true } : { title: it.title });
			m.bindPopup(popupHtml(it));
			m.on('click', function () { highlightCard(it.id); });
			m.addTo(markersLayer);
			byId[it.id] = m;
			bounds.push([it.lat, it.lng]);
			cards.appendChild(cardEl(it));
		});
		if (bounds.length) {
			// 左側の絞り込みオーバーレイに隠れないよう左余白を広めに
			var wideLayout = window.innerWidth > 700;
			map.fitBounds(bounds, {
				paddingTopLeft: [wideLayout ? 310 : 30, 30],
				paddingBottomRight: [30, 30],
				maxZoom: 14
			});
		}
	}

	function popupHtml(it) {
		var badge = it.is_kreva ? '<span class="kakiya-pin-badge">KREVA</span>' : '';
		return '<div class="kakiya-pop">' + badge +
			'<strong>' + esc(it.title) + '</strong><br>' +
			esc(it.price_label || '') + (it.city ? ' / ' + esc(it.city) : '') + '<br>' +
			'<a href="' + esc(it.permalink) + '">詳細を見る →</a></div>';
	}

	// 写真が無い物件用：物件位置を中心にした地理院タイルの地図サムネを生成
	function mapThumbHtml(lat, lng) {
		var z = 15, n = Math.pow(2, z);
		var px = (lng + 180) / 360 * n * 256;
		var latRad = lat * Math.PI / 180;
		var py = (1 - Math.asinh(Math.tan(latRad)) / Math.PI) / 2 * n * 256;
		var half = 170, halfH = 65; // 想定最大カード幅340px・高さ130pxぶんのタイルを敷く
		var tiles = '';
		var x0 = Math.floor((px - half) / 256), x1 = Math.floor((px + half) / 256);
		var y0 = Math.floor((py - halfH) / 256), y1 = Math.floor((py + halfH) / 256);
		for (var tx = x0; tx <= x1; tx++) {
			for (var ty = y0; ty <= y1; ty++) {
				tiles += '<img src="https://cyberjapandata.gsi.go.jp/xyz/pale/' + z + '/' + tx + '/' + ty + '.png" ' +
					'style="position:absolute;left:' + (tx * 256 - px) + 'px;top:' + (ty * 256 - py) + 'px;width:256px;height:256px;max-width:none" alt="" loading="lazy">';
			}
		}
		return '<div class="kakiya-card-thumb kakiya-card-mapthumb">' +
			'<div class="kakiya-mapthumb-inner">' + tiles + '</div>' +
			'<span class="kakiya-mapthumb-pin"></span>' +
			'<span class="kakiya-mapthumb-credit">地図: 地理院タイル</span></div>';
	}

	function cardEl(it) {
		var a = document.createElement('a');
		a.className = 'kakiya-card' + (it.is_kreva ? ' is-kreva' : '');
		a.href = it.permalink;
		a.id = 'card-' + it.id;
		a.innerHTML =
			(it.thumb ? '<div class="kakiya-card-thumb"><img class="kakiya-thumb-img" loading="lazy" referrerpolicy="no-referrer" alt="" src="' + esc(it.thumb) + '"></div>'
				: mapThumbHtml(it.lat, it.lng)) +
			'<div class="kakiya-card-body">' +
			(it.is_kreva ? '<span class="kakiya-tag">KREVA</span>' : '') +
			'<div class="kakiya-card-price">' + esc(it.price_label || '') + '</div>' +
			'<div class="kakiya-card-title">' + esc(it.title) + '</div>' +
			'<div class="kakiya-card-meta">' + esc([it.city, it.type].filter(Boolean).join(' / ')) +
			(it.kuiki_kubun ? ' ・' + esc(it.kuiki_kubun) : '') + '</div>' +
			'</div>';
		a.addEventListener('mouseenter', function () { var mk = byId[it.id]; if (mk) mk.openPopup(); });
		// 外部画像が読めない場合は地図サムネへ自動フォールバック
		var timg = a.querySelector('.kakiya-thumb-img');
		if (timg) {
			timg.addEventListener('error', function () {
				var holder = timg.closest ? timg.closest('.kakiya-card-thumb') : timg.parentNode;
				if (holder) holder.outerHTML = mapThumbHtml(it.lat, it.lng);
			});
		}
		return a;
	}

	function highlightCard(id) {
		var el = document.getElementById('card-' + id);
		if (!el) return;
		document.querySelectorAll('.kakiya-card.active').forEach(function (n) { n.classList.remove('active'); });
		el.classList.add('active');
		el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function setCount(n) { var el = document.getElementById('kakiya-count'); if (el) el.textContent = n; }
	function setStatus(s) { var el = document.getElementById('kakiya-count'); if (el) el.textContent = s; }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
		return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	document.addEventListener('DOMContentLoaded', function () {
		initMap();
		var btn = document.getElementById('kakiya-apply');
		if (btn) btn.addEventListener('click', fetchItems);
		// 県を選ぶと市の選択肢を（簡易に）フィルタ：ここでは全件取得後の初期表示のみ
		fetchItems();
	});
})();
