/* KREVA 空き家 検索ページ（Zillow型スプリットビュー）
   左:地図（価格ピン・カードとホバー連動・範囲再検索）／右:カードグリッド。
   フィルタピルは自動適用。設定は window.KREVA_AKIYA。 */
(function () {
	'use strict';
	var CFG = window.KREVA_AKIYA || {};
	if (!window.L || !document.getElementById('kakiya-map')) return;

	var PAGE_SIZE = 12;
	var map, markersLayer, byId = {}, rawItems = [], allItems = [], shownCount = 0;
	var toggles = { newonly: false, has_photo: false, kuiki: false, hazard_free: false };
	var bboxMode = null; // 「この範囲で再検索」時の bbox 文字列

	/* ---------- 地図 ---------- */
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
			center: CFG.defaultCenter || [34.85, 133.85],
			zoom: CFG.defaultZoom || 9,
			layers: [first]
		});
		L.control.layers(bases, hazardLayers(), { collapsed: true }).addTo(map);
		L.control.attribution({ prefix: false }).addAttribution(CFG.attribution || '').addTo(map);
		markersLayer = L.layerGroup().addTo(map);
		// 地図を動かしたら「この範囲で再検索」を表示
		map.on('moveend zoomend', function () {
			var chip = document.getElementById('kakiya-bbox');
			if (chip) chip.hidden = false;
		});
	}

	function shortPrice(p) {
		if (p == null) return '応談';
		if (p >= 100000000) return (Math.round(p / 10000000) / 10) + '億';
		if (p >= 10000) return Math.round(p / 10000) + '万';
		return p + '円';
	}

	function priceIcon(it, state) {
		var cls = 'kakiya-pin' + (state ? ' ' + state : '') + (it.is_kreva ? ' kv' : '');
		return L.divIcon({
			className: 'kakiya-pin-wrap',
			html: '<div class="' + cls + '" data-pid="' + it.id + '">' + esc(shortPrice(it.price)) + '</div>',
			iconSize: null,
			iconAnchor: [0, 0]
		});
	}

	function setPinState(id, state) {
		var mk = byId[id];
		if (!mk || !mk._icon) return;
		var el = mk._icon.querySelector('.kakiya-pin');
		if (el) {
			el.classList.remove('hot');
			if (state) el.classList.add(state);
		}
	}

	/* ---------- データ取得・表示 ---------- */
	function collectFilters() {
		var params = { per_page: 500 };
		document.querySelectorAll('select[data-filter]').forEach(function (el) {
			if (el.value !== '') params[el.getAttribute('data-filter')] = el.value;
		});
		var pr = document.getElementById('f-price');
		if (pr && pr.value) {
			var mm = pr.value.split('-');
			if (mm[0]) params.price_min = mm[0];
			if (mm[1]) params.price_max = mm[1];
		}
		if (toggles.has_photo) params.has_photo = 1;
		if (toggles.kuiki) params.kuiki = 'exclude_chosei';
		if (toggles.hazard_free) params.hazard_free = 1;
		if (bboxMode) params.bbox = bboxMode;
		return params;
	}

	function fetchItems() {
		var params = collectFilters();
		var qs = Object.keys(params).map(function (k) {
			return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
		}).join('&');
		setStatus('…');
		fetch(CFG.restSearch + '?' + qs, { headers: { 'Accept': 'application/json' } })
			.then(function (r) { return r.json(); })
			.then(function (data) { rawItems = data.items || []; applyView(); })
			.catch(function () { setStatus('取得エラー'); });
	}

	function applyView() {
		var items = rawItems.slice();
		if (toggles.newonly) {
			var limit = Date.now() - 30 * 86400000;
			items = items.filter(function (it) { return it.date && Date.parse(it.date) >= limit; });
		}
		var mode = (document.getElementById('f-sort') || {}).value || 'new';
		items.sort(function (a, b) {
			if (mode === 'price_asc' || mode === 'price_desc') {
				if (a.price == null && b.price == null) return 0;
				if (a.price == null) return 1;
				if (b.price == null) return -1;
				return mode === 'price_asc' ? a.price - b.price : b.price - a.price;
			}
			if (mode === 'land_desc') return (b.land_area || 0) - (a.land_area || 0);
			var d = String(b.date || '').localeCompare(String(a.date || ''));
			return d !== 0 ? d : (b.is_kreva ? 1 : 0) - (a.is_kreva ? 1 : 0);
		});
		setCount(items.length);
		render(items);
	}

	function render(items) {
		allItems = items;
		shownCount = 0;
		markersLayer.clearLayers();
		byId = {};
		var cards = document.getElementById('kakiya-cards');
		cards.innerHTML = '';
		if (!items.length) {
			updateMoreBtn();
			cards.innerHTML = '<p class="kakiya-empty">条件に合う物件がありません。</p>';
			return;
		}
		var bounds = [];
		items.forEach(function (it) {
			var mk = L.marker([it.lat, it.lng], { icon: priceIcon(it), riseOnHover: true });
			mk.bindPopup(popupHtml(it), { minWidth: 236, maxWidth: 236, className: 'kakiya-popup' });
			mk.addTo(markersLayer);
			byId[it.id] = mk;
			bounds.push([it.lat, it.lng]);
		});
		appendCards();
		if (bounds.length && !bboxMode) {
			map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
			var chip = document.getElementById('kakiya-bbox');
			if (chip) chip.hidden = true; // fitBoundsによるmoveendでチップが出るのを防ぐ
			setTimeout(function () { if (chip) chip.hidden = true; }, 300);
		}
	}

	function appendCards() {
		var cards = document.getElementById('kakiya-cards');
		allItems.slice(shownCount, shownCount + PAGE_SIZE).forEach(function (it) {
			cards.appendChild(cardEl(it));
		});
		shownCount = Math.min(shownCount + PAGE_SIZE, allItems.length);
		updateMoreBtn();
	}

	function updateMoreBtn() {
		var old = document.getElementById('kakiya-more');
		if (old) old.remove();
		if (shownCount < allItems.length) {
			var b = document.createElement('button');
			b.type = 'button';
			b.id = 'kakiya-more';
			b.className = 'kakiya-more2';
			b.textContent = 'さらに' + Math.min(PAGE_SIZE, allItems.length - shownCount) + '件を表示（全' + allItems.length + '件）';
			b.addEventListener('click', appendCards);
			var cards = document.getElementById('kakiya-cards');
			cards.parentNode.appendChild(b);
		}
	}

	/* ---------- ポップアップ・カード ---------- */
	function popupHtml(it) {
		var img = it.thumb
			? '<img class="kakiya-pop-img" referrerpolicy="no-referrer" src="' + esc(it.thumb) + '" alt="" onerror="this.style.display=\'none\'">'
			: '';
		return '<a class="kakiya-pop-card" href="' + esc(it.permalink) + '">' + img +
			'<span class="kakiya-pop-price">' + esc(it.price_label || '') + '</span>' +
			'<span class="kakiya-pop-title">' + esc(it.title) + '</span>' +
			'<span class="kakiya-pop-link">詳細を見る →</span></a>';
	}

	// 写真なし物件用：物件位置中心の地理院タイルサムネ
	function mapThumbHtml(lat, lng) {
		var z = 15, n = Math.pow(2, z);
		var px = (lng + 180) / 360 * n * 256;
		var latRad = lat * Math.PI / 180;
		var py = (1 - Math.asinh(Math.tan(latRad)) / Math.PI) / 2 * n * 256;
		var half = 210, halfH = 160; // 4:3カード（最大約420×315px）をカバー
		var tiles = '';
		for (var tx = Math.floor((px - half) / 256); tx <= Math.floor((px + half) / 256); tx++) {
			for (var ty = Math.floor((py - halfH) / 256); ty <= Math.floor((py + halfH) / 256); ty++) {
				tiles += '<img src="https://cyberjapandata.gsi.go.jp/xyz/pale/' + z + '/' + tx + '/' + ty + '.png" ' +
					'style="position:absolute;left:' + (tx * 256 - px) + 'px;top:' + (ty * 256 - py) + 'px;width:256px;height:256px;max-width:none" alt="" loading="lazy">';
			}
		}
		return '<div class="kakiya-mapthumb-inner">' + tiles + '</div>' +
			'<span class="kakiya-mapthumb-pin"></span>' +
			'<span class="kakiya-mapthumb-credit">地図: 地理院タイル</span>';
	}

	function cardEl(it) {
		var a = document.createElement('a');
		a.className = 'kakiya-card2';
		a.href = it.permalink;
		a.id = 'card-' + it.id;
		var tags = '';
		if (it.is_new) tags += '<span class="kakiya-b b-new">NEW</span>';
		if (it.is_kreva) tags += '<span class="kakiya-b b-kv">KREVA</span>';
		if (it.price == null) tags += '<span class="kakiya-b b-ask">価格応談</span>';
		if (it.kuiki_kubun && it.kuiki_kubun.indexOf('調整') >= 0) tags += '<span class="kakiya-b b-adj">調整区域</span>';
		if (it.hazard_any) tags += '<span class="kakiya-b b-haz">災害想定</span>';

		var photo;
		if (it.thumb) {
			photo = '<img class="kakiya-ph1" loading="lazy" referrerpolicy="no-referrer" alt="" src="' + esc(it.thumb) + '">' +
				(it.thumb2 ? '<img class="kakiya-ph2" loading="lazy" referrerpolicy="no-referrer" alt="" src="' + esc(it.thumb2) + '">' : '');
		} else {
			photo = mapThumbHtml(it.lat, it.lng);
		}
		var meta = [it.city, it.type].filter(Boolean).join('・');
		var extra = [];
		if (it.land_area) extra.push('土地' + Math.round(it.land_area) + '㎡');
		a.innerHTML =
			'<div class="kakiya-ph">' + photo + '<div class="kakiya-bdg">' + tags + '</div></div>' +
			'<div class="kakiya-cb">' +
			'<div class="kakiya-price2">' + esc(it.price_label || '') + '</div>' +
			'<div class="kakiya-ttl2">' + esc(it.title) + '</div>' +
			'<div class="kakiya-meta2">' + esc(meta + (extra.length ? '｜' + extra.join('・') : '')) + '</div>' +
			'</div>';
		// カード⇔ピンのホバー連動
		a.addEventListener('mouseenter', function () { setPinState(it.id, 'hot'); });
		a.addEventListener('mouseleave', function () { setPinState(it.id, null); });
		// 外部画像エラー時は地図サムネへ
		var t1 = a.querySelector('.kakiya-ph1');
		if (t1) {
			t1.addEventListener('error', function () {
				var ph = a.querySelector('.kakiya-ph');
				if (ph) ph.innerHTML = mapThumbHtml(it.lat, it.lng) + '<div class="kakiya-bdg">' + tags + '</div>';
			});
		}
		return a;
	}

	/* ---------- UI状態 ---------- */
	function setCount(n) { var el = document.getElementById('kakiya-count'); if (el) el.textContent = n; }
	function setStatus(s) { var el = document.getElementById('kakiya-count'); if (el) el.textContent = s; }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initMap();
		// セレクトは変更で即適用
		['f-city', 'f-price', 'f-type'].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.addEventListener('change', function () { bboxMode = null; fetchItems(); });
		});
		var sort = document.getElementById('f-sort');
		if (sort) sort.addEventListener('change', applyView);
		// トグルピル
		document.querySelectorAll('.kakiya-pill[data-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.getAttribute('data-toggle');
				toggles[key] = !toggles[key];
				btn.classList.toggle('on', toggles[key]);
				if (key === 'newonly') applyView(); else fetchItems();
			});
		});
		// この範囲で再検索
		var chip = document.getElementById('kakiya-bbox');
		if (chip) {
			chip.addEventListener('click', function () {
				bboxMode = map.getBounds().toBBoxString();
				chip.hidden = true;
				fetchItems();
			});
		}
		// モバイル：地図⇔リスト切替
		var vt = document.getElementById('kakiya-vtoggle');
		if (vt) {
			vt.addEventListener('click', function () {
				var split = document.getElementById('kakiya-split');
				var mapMode = split.classList.toggle('vmap');
				vt.textContent = mapMode ? 'リストで見る' : '地図で見る';
				if (mapMode) setTimeout(function () { map.invalidateSize(); }, 60);
			});
		}
		// URLパラメータ（市区町村ファセット等からの遷移）を初期フィルタに反映
		try {
			var usp = new URLSearchParams(window.location.search);
			[['city', 'f-city'], ['type', 'f-type'], ['price', 'f-price']].forEach(function (pair) {
				var v = usp.get(pair[0]);
				if (!v) return;
				var el = document.getElementById(pair[1]);
				if (el && [].some.call(el.options, function (o) { return o.value === v; })) {
					el.value = v;
				}
			});
		} catch (e) {}
		fetchItems();
	});
})();
