/* KREVA 空き家 詳細ページ：物件地点を中心にハザードを重ねられる地図。設定は window.KREVA_AKIYA。 */
(function () {
	'use strict';
	var CFG = window.KREVA_AKIYA || {};
	var el = document.getElementById('kakiya-map-single');
	if (!window.L || !el) return;

	var p = CFG.property || {};
	if (p.lat == null || p.lng == null) {
		el.innerHTML = '<p style="padding:1em;color:#666">位置情報が未設定のため地図を表示できません。</p>';
		return;
	}

	function baseLayer() {
		var t = (CFG.baseTiles && CFG.baseTiles.pale) || { url: 'https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png', attr: '地理院タイル' };
		return L.tileLayer(t.url, { attribution: t.attr, maxZoom: 18 });
	}
	function hazardLayers() {
		var out = {};
		(CFG.hazardTiles || []).forEach(function (h) {
			out[h.label] = L.tileLayer(h.url, { opacity: 0.6, maxNativeZoom: 17, maxZoom: 18 });
		});
		return out;
	}

	document.addEventListener('DOMContentLoaded', function () {
		var map = L.map('kakiya-map-single', {
			center: [p.lat, p.lng], zoom: 16, layers: [baseLayer()]
		});
		L.control.layers(null, hazardLayers(), { collapsed: false }).addTo(map);
		L.control.attribution({ prefix: false }).addAttribution(CFG.attribution || '').addTo(map);
		var popup = '<strong>' + esc(p.address || '') + '</strong>' +
			(p.kuiki_kubun ? '<br>区域区分: ' + esc(p.kuiki_kubun) : '') +
			(p.youto_chiiki ? '<br>用途地域: ' + esc(p.youto_chiiki) : '');
		L.marker([p.lat, p.lng]).addTo(map).bindPopup(popup).openPopup();
	});

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
		return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
})();
