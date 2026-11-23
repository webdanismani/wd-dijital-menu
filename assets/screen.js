/* WD Dijital Menü – TV ekranı · Web Danışmanı · webdanismani.com */
(function () {
	'use strict';

	var root = document.body;
	var wrap = document.querySelector('[data-menu]');
	var api = root.getAttribute('data-api');
	var CACHE = 'wdmenu-' + (root.getAttribute('data-key') || '');
	var state = null;
	var skew = 0, tzOffset = 10800, lastOk = Date.now();
	var page = 0, pages = 1, pageTimer = null;
	var slide = -1, slideTimer = null, activePromos = [];
	var pollTimer = null;

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function $(s) { return wrap.querySelector(s); }

	/* ---------- Simgeler ---------- */
	var CAT = {
		breakfast: '<circle cx="11" cy="13" r="7"/><circle cx="11" cy="13" r="2.6" fill="currentColor"/><path d="M18 13h4"/>',
		coffee: '<path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Z"/><path d="M16 10.5h1.5a2.5 2.5 0 0 1 0 5H16M8 3.5c0 1.3 1 1.7 1 3M12 3.5c0 1.3 1 1.7 1 3"/>',
		tea: '<path d="M8 5h8l-1.2 4.5a4 4 0 0 0 0 2L16 16a3 3 0 0 1-3 4h-2a3 3 0 0 1-3-4l1.2-4.5a4 4 0 0 0 0-2Z"/><path d="M6 21h12"/>',
		drink: '<path d="M6 7h12l-1.5 13h-9Z"/><path d="M14 7l2-4h3"/><path d="M7 11h10"/>',
		dessert: '<path d="M3.5 18h17v-5.5L12 7l-8.5 5.5Z"/><path d="M3.5 13.5h17M12 4v3"/>',
		grill: '<path d="M4 20 20 4"/><rect x="8" y="8.5" width="4" height="4" rx="1" transform="rotate(45 10 10.5)"/><rect x="12.5" y="4" width="4" height="4" rx="1" transform="rotate(45 14.5 6)"/><circle cx="7" cy="15" r="2"/>',
		burger: '<path d="M4 11a8 6 0 0 1 16 0Z"/><path d="M3.5 14h17M5 17h14a1.5 1.5 0 0 1-1.5 2.5h-11A1.5 1.5 0 0 1 5 17Z"/>',
		pizza: '<path d="M12 21 3.5 6.5a17 17 0 0 1 17 0Z"/><path d="M5 9a14 14 0 0 1 14 0"/><circle cx="11" cy="12" r="1" fill="currentColor"/><circle cx="13.5" cy="15.5" r="1" fill="currentColor"/>',
		salad: '<path d="M3 12h18a9 9 0 0 1-18 0Z"/><path d="M8 12c0-3 2-5 4-5M12 12c0-2.5 1.5-5 4.5-5.5M14 7.5C13 5 10.5 4 9 4.5"/>',
		soup: '<path d="M3 11h18a9 9 0 0 1-18 0Z"/><path d="M8 7c0-1.5 1-2 1-3.5M12 7c0-1.5 1-2 1-3.5M16 7c0-1.5 1-2 1-3.5"/>',
		fish: '<path d="M3 12c3-5 9-6 13-3l4-3v12l-4-3c-4 3-10 2-13-3Z"/><circle cx="8" cy="11" r=".9" fill="currentColor"/>',
		pasta: '<path d="M3 12h18a9 9 0 0 1-18 0Z"/><path d="M7 12c1-3 3-3 4 0s3 3 4 0M9 4l2 8M15 4l-2 8"/>'
	};
	function catIcon(name) {
		if (!CAT[name]) return '';
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + CAT[name] + '</svg>';
	}
	var BADGE = {
		yeni: ['Yeni', ''],
		sef: ['Şefin önerisi', '<path d="M7 14a4 4 0 1 1 1.5-7.7A4.5 4.5 0 0 1 16 6.3 4 4 0 1 1 17 14v5H7Z"/>'],
		acili: ['Acı', '<path d="M14 6c2-1 4 0 4 0-1 1-1.5 1.5-1.5 3 0 6-5 11-11 10 4-2 6-6 6-9 0-2 1-3.5 2.5-4Z"/><path d="M14 6c0-1 .5-2 1.5-3"/>'],
		vejetaryen: ['Vejetaryen', '<path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14Z"/><path d="M5 19 13 11"/>'],
		vegan: ['Vegan', '<path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14Z"/><path d="M5 19 13 11"/>'],
		glutensiz: ['Glutensiz', '<path d="M12 21V8M12 8c-2-1-3-3-3-5 2 1 3 3 3 5Zm0 0c2-1 3-3 3-5-2 1-3 3-3 5Zm0 5c-2-1-3-3-3-5 2 1 3 3 3 5Zm0 0c2-1 3-3 3-5-2 1-3 3-3 5Z"/><path d="M4 4l16 16"/>']
	};
	function badge(b) {
		var d = BADGE[b];
		if (!d) return '';
		return '<span class="m-badge b-' + b + '">' + (d[1] ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d[1] + '</svg>' : '') + esc(d[0]) + '</span>';
	}
	var MARK = '<svg class="m-mark" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M24 5l5.2 10.6 11.6 1.7-8.4 8.2 2 11.6L24 31.6l-10.4 5.5 2-11.6-8.4-8.2 11.6-1.7Z"/></svg>';

	/* ---------- Menü ---------- */
	function item(it, sizes) {
		var multi = sizes.length > 1 && it.prices.length > 1;
		var prices = it.soldout
			? '<span class="m-out">Tükendi</span>'
			: (it.old ? '<s>' + esc(it.old) + '</s>' : '') + it.prices.map(function (p) { return '<b>' + esc(p) + '</b>'; }).join('');
		return '<div class="m-item' + (it.image ? ' has-img' : '') + (it.soldout ? ' is-soldout' : '') + '">' +
			(it.image ? '<img class="m-img" src="' + esc(it.image) + '" alt="" loading="eager">' : '<span class="m-img"></span>') +
			'<div class="m-txt"><div class="m-name">' + esc(it.name) + it.badges.map(badge).join('') + '</div>' +
			(it.desc ? '<p class="m-desc">' + esc(it.desc) + '</p>' : '') + '</div>' +
			'<div class="m-prices' + (multi ? ' multi' : '') + '">' + prices + '</div></div>';
	}

	function cat(c) {
		var multi = c.sizes.length > 1 && c.items.some(function (i) { return i.prices.length > 1; });
		return '<section class="m-cat">' +
			'<header class="m-cat-head"><span class="m-ci">' + catIcon(c.icon) + '</span>' +
			'<h2>' + esc(c.name) + (c.note ? '<small>' + esc(c.note) + '</small>' : '') + '</h2>' +
			(multi ? '<div class="m-sizes">' + c.sizes.slice(0, 3).map(function (s) { return '<span>' + esc(s) + '</span>'; }).join('') + '</div>' : '<span></span>') +
			'</header>' + c.items.map(function (i) { return item(i, multi ? c.sizes : []); }).join('') + '</section>';
	}

	function render(d) {
		var sc = d.screen;
		root.className = 'scr theme-' + sc.theme + ' layout-' + sc.layout + (isPortrait() ? ' is-portrait' : '');
		var title = $('.m-head h1');
		title.textContent = sc.title || '';
		var logo = $('.m-logo');
		if (sc.logo) { logo.src = sc.logo; logo.hidden = false; } else { logo.hidden = true; }
		$('.m-foot-text').textContent = sc.footer || '';

		var cols = $('.m-cols');
		cols.innerHTML = d.categories.length ? d.categories.map(cat).join('') : '<div class="m-empty">Menüde gösterilecek ürün yok.</div>';
		layout();
		promos(true);
	}

	function isPortrait() { return window.innerHeight > window.innerWidth * 1.05; }

	/* Sütun sayısı ekran genişliğine göre; sığmayan menü sayfalara bölünür ve sırayla gösterilir.
	   Az taşan menüler sayfaya bölünmek yerine bir miktar küçültülür. */
	function layout() {
		var sc = state.screen;
		var menu = $('.m-menu'), cols = $('.m-cols');
		var n = sc.columns || (sc.layout === 'menu_promo' ? 2 : 3);
		if (isPortrait()) n = Math.min(n, sc.columns ? sc.columns : 2);
		cols.style.setProperty('--cols', n);
		cols.style.columnFill = 'auto';
		cols.style.transform = '';
		priceWidth(cols);
		var scale = 1;
		cols.style.setProperty('--scale', 1);
		pages = count(menu, cols);
		if (pages > 1) {
			for (var s = .96; s >= .8; s -= .04) {
				cols.style.setProperty('--scale', s);
				if (count(menu, cols) === 1) { scale = s; break; }
			}
			if (scale === 1) cols.style.setProperty('--scale', 1);
			pages = count(menu, cols);
		}
		// Ekranın çoğu boş kalıyorsa yazılar büyütülür (uzaktan okunurluk)
		if (pages === 1 && scale === 1) {
			for (var g = 1.05; g <= 1.4; g += .05) {
				cols.style.setProperty('--scale', g);
				if (count(menu, cols) > 1) { cols.style.setProperty('--scale', g - .05); break; }
			}
			pages = count(menu, cols);
		}
		// Tek sayfaya sığan menü sütunlara dengeli dağıtılır
		if (pages === 1) cols.style.columnFill = 'balance';
		page = Math.min(page, pages - 1);
		dots();
		go(page);
		clearInterval(pageTimer);
		if (pages > 1) pageTimer = setInterval(function () { go((page + 1) % pages); }, sc.page * 1000);
	}

	function count(menu, cols) {
		var w = menu.clientWidth;
		var gap = parseFloat(getComputedStyle(cols).columnGap) || 0;
		return Math.max(1, Math.ceil((cols.scrollWidth + gap) / (w + gap) - 0.02));
	}

	/* Boy seçenekli fiyat sütunları aynı genişlikte olsun */
	function priceWidth(cols) {
		cols.style.removeProperty('--pw');
		var max = 0;
		cols.querySelectorAll('.m-prices.multi b, .m-sizes span').forEach(function (b) {
			b.style.width = 'auto';
			max = Math.max(max, b.getBoundingClientRect().width);
			b.style.width = '';
		});
		if (max) cols.style.setProperty('--pw', Math.ceil(max + 14) + 'px');
	}

	function go(i) {
		page = i;
		var menu = $('.m-menu'), cols = $('.m-cols');
		var gap = parseFloat(getComputedStyle(cols).columnGap) || 0;
		cols.style.transform = 'translateX(' + (-i * (menu.clientWidth + gap)) + 'px)';
		var ds = wrap.querySelectorAll('.m-pages i');
		ds.forEach(function (d, k) { d.classList.toggle('is-on', k === i); });
	}

	function dots() {
		var el = $('.m-pages');
		el.innerHTML = pages > 1 ? new Array(pages + 1).join('<i></i>') : '';
	}

	/* ---------- Kampanyalar (gün ve saat aralığına göre) ---------- */
	function local() { return new Date(Date.now() + skew + tzOffset * 1000); }

	function activeNow(p) {
		var t = local();
		var day = t.getUTCDay() === 0 ? 7 : t.getUTCDay();
		if (p.days && p.days.indexOf(String(day)) === -1) return false;
		var date = t.toISOString().slice(0, 10);
		if (p.dfrom && date < p.dfrom) return false;
		if (p.dto && date > p.dto) return false;
		var hm = ('0' + t.getUTCHours()).slice(-2) + ':' + ('0' + t.getUTCMinutes()).slice(-2);
		if (p.from && p.to) {
			if (p.from <= p.to) { if (hm < p.from || hm >= p.to) return false; }
			else if (hm < p.from && hm >= p.to) return false; // gece yarısını aşan aralık
		}
		return true;
	}

	function promos(force) {
		var box = $('.m-promo');
		if (!state || state.screen.layout === 'menu') return;
		var list = (state.promos || []).filter(activeNow);
		var sig = list.map(function (p) { return p.id; }).join(',');
		if (!force && sig === activePromos.sig) return;
		activePromos = list;
		activePromos.sig = sig;
		if (!list.length) {
			box.innerHTML = '<div class="m-slide c-dark is-on">' + MARK.replace('m-mark', 'm-mark is-big') + '<h3>' + esc(state.screen.title || '') + '</h3><p>Afiyet olsun</p></div>';
			clearInterval(slideTimer);
			return;
		}
		box.innerHTML = list.map(function (p) {
			return '<div class="m-slide c-' + esc(p.color) + (p.image ? ' has-img' : '') + '">' +
				(p.image ? '<img src="' + esc(p.image) + '" alt="">' : MARK.replace('m-mark', 'm-mark is-big')) +
				'<h3>' + esc(p.title) + '</h3>' + (p.subtitle ? '<p>' + esc(p.subtitle) + '</p>' : '') +
				(p.price ? '<div class="m-pp"><b>' + esc(p.price) + '</b>' + (p.old ? '<s>' + esc(p.old) + '</s>' : '') + '</div>' : '') +
				'</div>';
		}).join('') + (list.length > 1 ? '<div class="m-dots">' + new Array(list.length + 1).join('<i></i>') + '</div>' : '');
		slide = -1;
		next();
		clearInterval(slideTimer);
		if (list.length > 1) slideTimer = setInterval(next, state.screen.promo * 1000);
	}

	function next() {
		var s = wrap.querySelectorAll('.m-slide');
		if (!s.length) return;
		slide = (slide + 1) % s.length;
		s.forEach(function (el, i) { el.classList.toggle('is-on', i === slide); });
		var d = wrap.querySelectorAll('.m-dots i');
		d.forEach(function (el, i) { el.classList.toggle('is-on', i === slide); });
		// Slaytın rengine göre noktaların rengi
		var dots = $('.m-dots');
		if (dots) dots.style.color = getComputedStyle(s[slide]).color;
	}

	/* ---------- Saat ---------- */
	function clock() {
		var t = local();
		$('.m-clock').textContent = ('0' + t.getUTCHours()).slice(-2) + ':' + ('0' + t.getUTCMinutes()).slice(-2);
	}

	/* ---------- Veri ---------- */
	function apply(d) {
		if (d.now) skew = d.now * 1000 - Date.now();
		if (typeof d.tz_offset === 'number') tzOffset = d.tz_offset;
		state = d;
		render(d);
		clock();
	}

	function offline(on) {
		var el = document.querySelector('.m-offline');
		if (on && !el) { el = document.createElement('div'); el.className = 'm-offline'; document.body.appendChild(el); }
		if (el) {
			if (on) {
				var t = new Date(lastOk + skew + tzOffset * 1000);
				el.textContent = 'Bağlantı yok · son güncelleme ' + ('0' + t.getUTCHours()).slice(-2) + ':' + ('0' + t.getUTCMinutes()).slice(-2);
			} else el.remove();
		}
	}

	function poll() {
		var x = new XMLHttpRequest();
		var v = state ? encodeURIComponent(state.version) : '';
		x.open('GET', api + '&v=' + v + '&_=' + Date.now());
		x.timeout = 15000;
		x.onload = function () {
			var d = null;
			try { d = JSON.parse(x.responseText); } catch (e) {}
			if (x.status === 200 && d && d.ok) {
				lastOk = Date.now();
				offline(false);
				if (d.now) skew = d.now * 1000 - Date.now();
				if (!d.same) {
					apply(d);
					try { localStorage.setItem(CACHE, JSON.stringify(d)); } catch (e) {}
				}
			} else offline(true);
			schedule();
		};
		x.onerror = x.ontimeout = function () { offline(true); schedule(); };
		x.send();
	}
	function schedule() { clearTimeout(pollTimer); pollTimer = setTimeout(poll, 30000); }

	var initial = null;
	try { initial = JSON.parse(document.getElementById('initial').textContent); } catch (e) {}
	if (!initial || !initial.ok) { try { initial = JSON.parse(localStorage.getItem(CACHE)); } catch (e) {} }
	if (initial && initial.ok) apply(initial);
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { if (state) layout(); });
	// Görseller yüklenince yükseklik değişebilir
	window.addEventListener('load', function () { if (state) layout(); });
	window.addEventListener('resize', function () { if (state) render(state); });
	setInterval(clock, 1000);
	setInterval(function () { promos(false); }, 30000);
	schedule();
	setInterval(function () {
		var t = local();
		if (t.getUTCHours() === 4 && t.getUTCMinutes() === 0 && Date.now() - lastOk < 600000) location.reload();
	}, 60000);
})();
