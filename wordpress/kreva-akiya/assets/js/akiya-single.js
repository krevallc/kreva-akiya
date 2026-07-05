/* KREVA 空き家 詳細ページ：
   1) 周辺地図（Leaflet・ハザード切替・アクセント色「この物件」ピン）
   2) フォトギャラリーのライトボックス（矢印/←→/ホイール/スワイプ・カウンター・サムネ帯・ループ）
   3) 近くの物件3件（距離順）
   設定は window.KREVA_AKIYA（property=全メタ, postId, restSearch）。 */
(function () {
	'use strict';
	var CFG = window.KREVA_AKIYA || {};
	var p = CFG.property || {};

	/* ---------- 周辺地図 ---------- */
	function initMap() {
		var el = document.getElementById('kakiya-map-single');
		if (!window.L || !el) return;
		if (p.lat == null || p.lng == null) {
			el.innerHTML = '<p style="padding:1em;color:#6f6a63">位置情報が未設定のため地図を表示できません。</p>';
			return;
		}
		var t = (CFG.baseTiles && CFG.baseTiles.pale) || { url: 'https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png', attr: '地理院タイル' };
		var base = L.tileLayer(t.url, { attribution: t.attr, maxZoom: 18 });
		var map = L.map('kakiya-map-single', { center: [p.lat, p.lng], zoom: 15, layers: [base] });
		var overlays = {};
		(CFG.hazardTiles || []).forEach(function (h) {
			overlays[h.label] = L.tileLayer(h.url, { opacity: 0.6, maxNativeZoom: 17, maxZoom: 18 });
		});
		L.control.layers(null, overlays, { collapsed: true }).addTo(map);
		L.control.attribution({ prefix: false }).addAttribution(CFG.attribution || '').addTo(map);
		L.marker([p.lat, p.lng], {
			icon: L.divIcon({ className: 'kakiya-pin-wrap', html: '<div class="kakiya-pin sel">この物件</div>', iconSize: null, iconAnchor: [0, 0] })
		}).addTo(map);
	}

	/* ---------- ライトボックス ---------- */
	var photos = [];
	try {
		if (p.image_urls) photos = JSON.parse(p.image_urls) || [];
	} catch (e) { photos = []; }
	if (!photos.length && p.image_url) photos = [p.image_url];

	var lb = null, lbIdx = 0, wheelT = 0, touchX = null;

	function lbOpen(i) {
		lbIdx = ((i || 0) + photos.length) % photos.length;
		if (!lb) buildLb();
		document.body.style.overflow = 'hidden';
		lb.style.display = 'flex';
		lbUpdate();
	}
	function lbClose() {
		if (!lb) return;
		lb.style.display = 'none';
		document.body.style.overflow = '';
	}
	function lbStep(d) {
		lbIdx = (lbIdx + d + photos.length) % photos.length;
		lbUpdate();
	}
	function lbUpdate() {
		lb.querySelector('.kakiya-lbcnt').textContent = (lbIdx + 1) + ' / ' + photos.length;
		lb.querySelector('.kakiya-lbimg').src = photos[lbIdx];
		lb.querySelectorAll('.kakiya-lbt').forEach(function (t, i) {
			t.classList.toggle('on', i === lbIdx);
		});
	}
	function buildLb() {
		lb = document.createElement('div');
		lb.className = 'kakiya-lb';
		lb.innerHTML =
			'<div class="kakiya-lbtop"><span class="kakiya-lbcnt"></span><button type="button" class="kakiya-lbx" aria-label="閉じる">✕</button></div>' +
			'<div class="kakiya-lbmain">' +
			'<button type="button" class="kakiya-lbar lft" aria-label="前へ">←</button>' +
			'<img class="kakiya-lbimg" alt="" referrerpolicy="no-referrer">' +
			'<button type="button" class="kakiya-lbar rgt" aria-label="次へ">→</button>' +
			'</div>' +
			'<div class="kakiya-lbth">' + photos.map(function (u, i) {
				return '<button type="button" class="kakiya-lbt" data-i="' + i + '"><img src="' + u.replace(/"/g, '&quot;') + '" alt="" referrerpolicy="no-referrer" loading="lazy"></button>';
			}).join('') + '</div>';
		document.body.appendChild(lb);

		lb.querySelector('.kakiya-lbx').addEventListener('click', lbClose);
		lb.querySelector('.lft').addEventListener('click', function (e) { e.stopPropagation(); lbStep(-1); });
		lb.querySelector('.rgt').addEventListener('click', function (e) { e.stopPropagation(); lbStep(1); });
		// 背景（画像・ボタン以外）クリックで閉じる
		lb.querySelector('.kakiya-lbmain').addEventListener('click', function (e) {
			if (e.target === e.currentTarget) lbClose();
		});
		// サムネジャンプ
		lb.querySelectorAll('.kakiya-lbt').forEach(function (t) {
			t.addEventListener('click', function () { lbIdx = parseInt(t.getAttribute('data-i'), 10); lbUpdate(); });
		});
		// ホイール（250msスロットル）
		lb.addEventListener('wheel', function (e) {
			e.preventDefault();
			var now = Date.now();
			if (now - wheelT < 250 || Math.abs(e.deltaY) < 8) return;
			wheelT = now;
			lbStep(e.deltaY > 0 ? 1 : -1);
		}, { passive: false });
		// スワイプ（40px閾値）
		lb.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
		lb.addEventListener('touchend', function (e) {
			if (touchX == null) return;
			var dx = e.changedTouches[0].clientX - touchX;
			touchX = null;
			if (Math.abs(dx) > 40) lbStep(dx < 0 ? 1 : -1);
		}, { passive: true });
	}
	// キーボード
	document.addEventListener('keydown', function (e) {
		if (!lb || lb.style.display !== 'flex') return;
		if (e.key === 'ArrowRight') lbStep(1);
		else if (e.key === 'ArrowLeft') lbStep(-1);
		else if (e.key === 'Escape') lbClose();
	});

	/* ---------- 近くの物件 ---------- */
	function haversine(lat1, lon1, lat2, lon2) {
		var r = 6371, toR = Math.PI / 180;
		var dLat = (lat2 - lat1) * toR, dLon = (lon2 - lon1) * toR;
		var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
			Math.cos(lat1 * toR) * Math.cos(lat2 * toR) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
		return 2 * r * Math.asin(Math.sqrt(a));
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function nearbyCard(it) {
		var tags = '';
		if (it.is_new) tags += '<span class="kakiya-b b-new">NEW</span>';
		if (it.is_kreva) tags += '<span class="kakiya-b b-kv">KREVA</span>';
		if (it.price == null) tags += '<span class="kakiya-b b-ask">価格応談</span>';
		var photo = it.thumb
			? '<img class="kakiya-ph1" loading="lazy" referrerpolicy="no-referrer" alt="" src="' + esc(it.thumb) + '" onerror="this.style.display=\'none\'">'
			: '';
		var meta = [it.city, it.type].filter(Boolean).join('・') + (it.land_area ? '｜土地' + Math.round(it.land_area) + '㎡' : '');
		return '<a class="kakiya-card2" href="' + esc(it.permalink) + '">' +
			'<div class="kakiya-ph">' + photo + '<div class="kakiya-bdg">' + tags + '</div></div>' +
			'<div class="kakiya-cb"><div class="kakiya-price2" style="font-size:20px">' + esc(it.price_label || '') + '</div>' +
			'<div class="kakiya-ttl2">' + esc(it.title) + '</div>' +
			'<div class="kakiya-meta2">' + esc(meta) + '</div></div></a>';
	}
	function loadNearby() {
		var box = document.getElementById('kakiya-nearby');
		if (!box || !CFG.restSearch || p.lat == null) return;
		fetch(CFG.restSearch + '?per_page=500', { headers: { 'Accept': 'application/json' } })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var items = (data.items || [])
					.filter(function (it) { return it.id !== CFG.postId; })
					.map(function (it) { it._d = haversine(p.lat, p.lng, it.lat, it.lng); return it; })
					.sort(function (a, b) { return a._d - b._d; })
					.slice(0, 3);
				if (!items.length) { box.closest('.kakiya-nearwrap').style.display = 'none'; return; }
				box.innerHTML = items.map(nearbyCard).join('');
			})
			.catch(function () { /* 近くの物件は補助要素のため失敗時は非表示のまま */ });
	}

	document.addEventListener('DOMContentLoaded', function () {
		initMap();
		if (photos.length) {
			document.querySelectorAll('#kakiya-gal .kakiya-gt, #kakiya-gal .kakiya-gall').forEach(function (btn) {
				btn.addEventListener('click', function () {
					lbOpen(parseInt(btn.getAttribute('data-idx') || '0', 10));
				});
			});
		}
		loadNearby();
	});
})();
