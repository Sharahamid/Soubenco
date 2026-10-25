/* Soubenco storefront */
(function () {
	'use strict';
	var S = window.SB || {};
	var IS_APP = /SoubencoApp/i.test(navigator.userAgent);
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
	var money = function (n) { return '৳' + Math.round(Number(n) || 0).toLocaleString('en-US'); };
	var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
	var ICON = {
		check: '<svg class="sb-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>',
		alert: '<svg class="sb-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
		trash: '<svg class="sb-ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>',
		minus: '<svg class="sb-ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>',
		plus: '<svg class="sb-ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
		phone: '<svg class="sb-ic" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg>',
		truck: '<svg class="sb-ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg>',
		bell: '<svg class="sb-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2Z"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>',
		cart: '<svg class="sb-ic" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>'
	};

	/* ---------- API ---------- */
	function refreshNonce() {
		return fetch(S.ajax + '?action=sb_nonce', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { S.nonce = d.nonce; });
	}
	function api(path, opts, retried) {
		opts = opts || {};
		var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': S.nonce } };
		if (opts.form) { init.body = opts.form; }
		else if (opts.body) { init.body = JSON.stringify(opts.body); init.headers['Content-Type'] = 'application/json'; }
		return fetch(S.rest + path, init).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (data) {
				if (!r.ok && !retried && data && data.code === 'rest_cookie_invalid_nonce') {
					return refreshNonce().then(function () { return api(path, opts, true); });
				}
				if (!r.ok) { var e = new Error((data && data.message) || 'Something went wrong. Please try again.'); e.data = data; e.status = r.status; throw e; }
				return data;
			});
		});
	}

	/* ---------- Toasts ---------- */
	function toast(msg, type, link) {
		var box = $('[data-sb-toasts]'); if (!box) return;
		var t = document.createElement('div');
		t.className = 'sb-toast' + (type === 'error' ? ' is-error' : '');
		t.innerHTML = (type === 'error' ? ICON.alert : ICON.check) + '<span>' + esc(msg) + '</span>' + (link ? '<a href="' + link.href + '">' + esc(link.text) + '</a>' : '');
		box.appendChild(t);
		setTimeout(function () { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 300); }, 3200);
	}

	/* ---------- Header counters ---------- */
	function setCartCount(n) {
		$$('[data-sb-cart-count]').forEach(function (el) { el.textContent = n; el.hidden = !n; });
	}
	function setUnread(n) {
		$$('[data-sb-unread]').forEach(function (el) { el.textContent = n; el.hidden = !n; });
	}
	function setWishlist(ids) {
		ids = (ids || []).map(Number);
		$$('[data-sb-wish]').forEach(function (b) {
			var on = ids.indexOf(Number(b.getAttribute('data-sb-wish'))) > -1;
			b.classList.toggle('is-on', on);
			var lbl = b.querySelector('span'); if (lbl && b.classList.contains('sb-wish-line')) lbl.textContent = on ? 'Saved to wishlist' : 'Save to wishlist';
		});
		$$('[data-sb-wish-count]').forEach(function (el) { el.textContent = ids.length; el.hidden = !ids.length; });
	}
	function loadState() {
		return api('state').then(function (s) { setCartCount(s.cart); setUnread(s.unread); setWishlist(s.wishlist); }).catch(function () {});
	}

	/* ---------- App-only prices (only the app receives them) ---------- */
	function applyAppPrices(root) {
		if (!IS_APP) return;
		var els = $$('[data-sb-price][data-pid]', root).filter(function (e) { return !e.dataset.sbAppDone; });
		if (!els.length) return;
		var ids = els.map(function (e) { return e.dataset.pid; }).filter(function (v, i, a) { return a.indexOf(v) === i; });
		api('app-prices?ids=' + ids.join(',')).then(function (map) {
			els.forEach(function (el) {
				el.dataset.sbAppDone = '1';
				var p = map && map[el.dataset.pid]; if (!p) return;
				el.classList.add('is-app');
				el.innerHTML = '<span class="sb-price-app-label">' + ICON.phone + ' App price</span><span class="sb-price-now">' + esc(p.app_fmt) + '</span><del class="sb-price-was">' + esc(p.web_fmt) + '</del>';
			});
		}).catch(function () {});
	}

	/* ---------- Add to cart / wishlist ---------- */
	function addToCart(pid, qty, color, btn) {
		if (btn) btn.classList.add('is-loading');
		return api('cart', { method: 'POST', body: { product_id: pid, qty: qty || 1, color: color || '' } }).then(function (r) {
			setCartCount(r.count);
			toast('Added to your cart', 'ok', { href: S.cart, text: 'View cart' });
			return r;
		}).catch(function (e) { toast(e.message, 'error'); throw e; })
			.finally(function () { if (btn) btn.classList.remove('is-loading'); });
	}
	function needLogin(msg) {
		toast(msg || 'Please sign in first', 'error', { href: S.account + (S.account.indexOf('?') > -1 ? '&' : '?') + 'redirect=' + encodeURIComponent(location.href), text: 'Sign in' });
	}
	function toggleWish(pid, btn, quiet) {
		if (!S.loggedIn) { needLogin('Sign in to save items to your wishlist'); return Promise.reject(); }
		if (btn) { btn.classList.add('is-pop'); setTimeout(function () { btn.classList.remove('is-pop'); }, 220); }
		return api('wishlist/toggle', { method: 'POST', body: { product_id: pid } }).then(function (r) {
			setWishlist(r.wishlist);
			if (!quiet) toast(r.saved ? 'Saved to your wishlist' : 'Removed from your wishlist');
			return r;
		}).catch(function (e) { if (e && e.message) toast(e.message, 'error'); });
	}

	document.addEventListener('click', function (e) {
		var add = e.target.closest('[data-sb-add]');
		if (add) { e.preventDefault(); addToCart(add.getAttribute('data-sb-add'), 1, '', add); return; }
		var w = e.target.closest('[data-sb-wish]');
		if (w) { e.preventDefault(); toggleWish(w.getAttribute('data-sb-wish'), w); return; }
		var n = e.target.closest('[data-sb-notify]');
		if (n) {
			e.preventDefault();
			if (!S.loggedIn) { needLogin('Sign in so we can notify you when it is back'); return; }
			var pid = n.getAttribute('data-sb-notify');
			var already = $('[data-sb-wish="' + pid + '"].is-on');
			if (already) { toast('You are on the list — we will notify you when it is back'); return; }
			toggleWish(pid, null, true).then(function () { toast('Done — we will notify you when it is back in stock'); });
		}
	});

	/* ---------- Product page ---------- */
	function initProduct() {
		var g = $('[data-sb-gallery]');
		if (g) {
			var figs = $$('.sb-gallery-main figure', g), th = $$('.sb-gallery-thumbs button', g), cur = 0;
			var show = function (i) {
				cur = (i + figs.length) % figs.length;
				figs.forEach(function (f, k) { f.classList.toggle('is-active', k === cur); });
				th.forEach(function (b, k) { b.classList.toggle('is-active', k === cur); });
			};
			th.forEach(function (b) { b.addEventListener('click', function () { show(+b.dataset.i); }); });
			var x0 = null, main = $('.sb-gallery-main', g);
			main.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
			main.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1)); x0 = null; });
		}
		var form = $('[data-sb-buy]');
		if (form) {
			$$('input[name=color]', form).forEach(function (r) {
				r.addEventListener('change', function () { var l = $('[data-sb-color-label]'); if (l) l.textContent = r.value; });
			});
			var submitter = 'add';
			$$('button[type=submit]', form).forEach(function (b) { b.addEventListener('click', function () { submitter = b.dataset.action; }); });
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var pid = form.getAttribute('data-sb-buy');
				var qty = parseInt(($('input[name=qty]', form) || {}).value, 10) || 1;
				var c = $('input[name=color]:checked', form);
				var btn = $('button[data-action="' + submitter + '"]', form);
				addToCart(pid, qty, c ? c.value : '', btn).then(function () { if (submitter === 'buy') location.href = S.cart; });
			});
			var sticky = $('[data-sb-sticky-buy]');
			if (sticky) {
				$('[data-sb-sticky-add]', sticky).addEventListener('click', function () { submitter = 'add'; form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); });
				var io = new IntersectionObserver(function (en) { sticky.classList.toggle('is-show', !en[0].isIntersecting && en[0].boundingClientRect.top < 0); });
				io.observe(form);
			}
		}
		$$('[data-sb-qty]').forEach(initQty);
		var tabs = $('[data-sb-tabs]');
		if (tabs) {
			$$('.sb-tab', tabs).forEach(function (t) {
				t.addEventListener('click', function () {
					$$('.sb-tab', tabs).forEach(function (x) { x.classList.toggle('is-active', x === t); });
					$$('.sb-tab-panel', tabs).forEach(function (p) { p.classList.toggle('is-active', p.dataset.panel === t.dataset.tab); });
				});
			});
		}
	}
	function initQty(q, onChange) {
		var input = $('input', q);
		$$('button', q).forEach(function (b) {
			b.addEventListener('click', function () {
				var max = parseInt(input.max, 10) || 99, v = (parseInt(input.value, 10) || 1) + parseInt(b.dataset.step, 10);
				input.value = Math.max(1, Math.min(max, v));
				if (onChange) onChange(+input.value);
			});
		});
		input.addEventListener('change', function () {
			var max = parseInt(input.max, 10) || 99;
			input.value = Math.max(1, Math.min(max, parseInt(input.value, 10) || 1));
			if (onChange) onChange(+input.value);
		});
	}

	/* ---------- Cart page (live) ---------- */
	function initCart() {
		var root = $('[data-sb-cart]'); if (!root) return;
		var list = $('[data-sb-cart-list]', root), sum = $('[data-sb-cart-summary]', root), busy = false, sig = '';
		function render(c) {
			setCartCount(c.count);
			var s = JSON.stringify(c.items.map(function (i) { return [i.line, i.qty, i.price, i.available, i.web]; }));
			if (s === sig && list.dataset.ready) return; sig = s; list.dataset.ready = '1';
			if (!c.items.length) {
				list.innerHTML = '<div class="sb-empty"><div class="sb-empty-ic">' + ICON.cart + '</div><h3>Your cart is empty</h3><p class="sb-muted">Add products you like — they will wait here until you are ready.</p><a class="sb-btn sb-btn-primary" href="' + S.shop + '">Start shopping</a></div>';
				sum.innerHTML = ''; sum.hidden = true; return;
			}
			sum.hidden = false;
			list.innerHTML = c.items.map(function (i) {
				var off = !i.available;
				var diff = '';
				if (!off && i.price_diff) diff = '<span class="sb-pchange ' + (i.price_diff < 0 ? 'is-down' : 'is-up') + '">' + (i.price_diff < 0 ? 'Price dropped ' + money(-i.price_diff) : 'Price went up ' + money(i.price_diff)) + '</span>';
				var color = i.color ? '<span><i class="sb-dot" style="--c:' + esc(((i.colors || []).filter(function (x) { return x.name === i.color; })[0] || {}).hex || '#ccc') + '"></i>' + esc(i.color) + '</span>' : '';
				var price = i.app ? '<div class="sb-price is-app"><span class="sb-price-app-label">' + ICON.phone + ' App price</span><span class="sb-price-now">' + money(i.price) + '</span><del class="sb-price-was">' + money(i.web) + '</del></div>'
					: '<div class="sb-price"><span class="sb-price-now">' + money(i.price) + '</span>' + (i.on_sale ? '<del class="sb-price-was">' + money(i.regular) + '</del>' : '') + '</div>';
				return '<div class="sb-cart-item' + (off ? ' is-off' : '') + '" data-line="' + i.line + '">' +
					'<a class="sb-cart-item-media" href="' + esc(i.url || '#') + '"><img src="' + esc(i.image) + '" alt=""></a>' +
					'<div><h3><a href="' + esc(i.url || '#') + '">' + esc(i.name) + '</a></h3>' +
					'<div class="sb-cart-item-meta">' + (i.brand ? '<span>' + esc(i.brand) + '</span>' : '') + color + diff + '</div>' +
					'<div class="sb-cart-item-row">' + (off
						? '<span class="sb-off-note">' + ICON.bell + (i.removed ? 'No longer listed' : 'Currently unavailable — we will notify you') + '</span>'
						: '<div class="sb-qty sb-qty-sm" data-sb-qty><button type="button" data-step="-1" aria-label="Less">' + ICON.minus + '</button><input type="number" value="' + i.qty + '" min="1" max="' + (i.stock || 99) + '" aria-label="Quantity"><button type="button" data-step="1" aria-label="More">' + ICON.plus + '</button></div>') +
					'</div></div>' +
					'<div class="sb-cart-item-right">' + price + (off ? '' : '<strong>' + money(i.line_total) + '</strong>') +
					'<button type="button" class="sb-remove" data-remove="' + i.line + '">' + ICON.trash + 'Remove</button></div></div>';
			}).join('');
			$$('.sb-cart-item', list).forEach(function (row) {
				var q = $('[data-sb-qty]', row);
				if (q) initQty(q, function (v) { update('cart/update', { line: row.dataset.line, qty: v }); });
			});
			var offCount = c.items.filter(function (i) { return !i.available; }).length;
			sum.innerHTML = '<h2>Order summary</h2>' +
				(c.is_app ? '<div class="sb-sum-app">' + ICON.phone + ' App prices applied</div>' : '') +
				'<div class="sb-sum-row"><span>Items (' + c.buyable + ')</span><span>' + money(c.subtotal + c.saving) + '</span></div>' +
				(c.saving > 0 ? '<div class="sb-sum-row is-save"><span>You save</span><span>-' + money(c.saving) + '</span></div>' : '') +
				'<div class="sb-sum-row"><span>Delivery</span><span class="sb-muted">At checkout</span></div>' +
				'<div class="sb-sum-total"><span>Subtotal</span><strong>' + money(c.subtotal) + '</strong></div>' +
				'<div class="sb-sum-note">' + ICON.truck + '<span>Delivery all over Bangladesh. The charge for your district is added at checkout.</span></div>' +
				(offCount ? '<p class="sb-muted sb-small">' + offCount + ' unavailable ' + (offCount > 1 ? 'items stay' : 'item stays') + ' in your cart and ' + (offCount > 1 ? 'are' : 'is') + ' not included.</p>' : '') +
				(c.buyable ? '<a class="sb-btn sb-btn-primary sb-btn-lg sb-btn-block" href="' + c.checkout + '">Proceed to checkout</a>' : '<button class="sb-btn sb-btn-lg sb-btn-block" disabled>Nothing available to check out</button>') +
				'<a class="sb-btn sb-btn-ghost sb-btn-block" style="margin-top:8px" href="' + S.shop + '">Continue shopping</a>' +
				'<div class="sb-live">Prices update live</div>';
		}
		function load() { if (busy) return; return api('cart').then(render).catch(function () {}); }
		function update(path, body) {
			busy = true;
			return api(path, { method: 'POST', body: body }).then(function (c) { sig = ''; render(c); }).catch(function (e) { toast(e.message, 'error'); }).finally(function () { busy = false; });
		}
		list.addEventListener('click', function (e) {
			var r = e.target.closest('[data-remove]');
			if (r) { var row = r.closest('.sb-cart-item'); row.style.opacity = '.4'; update('cart/remove', { line: r.dataset.remove }).then(function () { toast('Removed from cart'); }); }
		});
		load();
		setInterval(function () { if (!document.hidden) load(); }, 20000);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) load(); });
	}

	/* ---------- Live search ---------- */
	function initSearch() {
		$$('[data-sb-search]').forEach(function (form) {
			var input = $('input[type=search]', form), pop = $('[data-sb-search-pop]', form), t, last = '';
			input.addEventListener('input', function () {
				clearTimeout(t);
				var q = input.value.trim();
				if (q.length < 2) { pop.hidden = true; return; }
				t = setTimeout(function () {
					last = q;
					api('search?q=' + encodeURIComponent(q)).then(function (r) {
						if (q !== last) return;
						var html = (r.cats || []).map(function (c) { return '<a class="sb-sr-cat" href="' + esc(c.url) + '">Category: <strong>' + esc(c.name) + '</strong></a>'; }).join('');
						html += (r.items || []).map(function (i) { return '<a class="sb-sr-item' + (i.available ? '' : ' is-out') + '" href="' + esc(i.url) + '"><img src="' + esc(i.image) + '" alt=""><span>' + esc(i.name) + '</span><strong>' + esc(i.price) + '</strong></a>'; }).join('');
						if (!html) html = '<div class="sb-sr-none">No matches for “' + esc(q) + '”</div>';
						else html += '<a class="sb-sr-all" href="' + esc(r.all) + '">See all results</a>';
						pop.innerHTML = html; pop.hidden = false;
					}).catch(function () {});
				}, 220);
			});
			input.addEventListener('focus', function () { if (pop.innerHTML && input.value.trim().length > 1) pop.hidden = false; });
			document.addEventListener('click', function (e) { if (!form.contains(e.target)) pop.hidden = true; });
			input.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.hidden = true; });
		});
	}

	/* ---------- Notifications dropdown ---------- */
	function initBell() {
		var btn = $('[data-sb-bell]'), pop = $('[data-sb-bell-pop]'); if (!btn || !pop) return;
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			pop.hidden = !pop.hidden;
			if (pop.hidden) return;
			api('notifications').then(function (r) {
				var l = $('[data-sb-bell-list]');
				l.innerHTML = r.items.length ? r.items.map(function (n) {
					return '<a class="sb-pop-note' + (+n.is_read ? '' : ' is-new') + '" href="' + esc(n.link || S.notes) + '"><span class="sb-note-ic sb-note-' + esc(n.type) + '" style="width:34px;height:34px;border-radius:10px">' + ICON.bell + '</span><div><strong>' + esc(n.title) + '</strong><p>' + esc(n.body) + '</p><small>' + esc(n.ago) + '</small></div></a>';
				}).join('') : '<div class="sb-pop-empty">No notifications yet. We will let you know about price drops, restocks and order updates.</div>';
				if (r.unread) api('notifications/read', { method: 'POST' }).then(function () { setUnread(0); });
			}).catch(function () {});
		});
		document.addEventListener('click', function (e) { if (!pop.contains(e.target)) pop.hidden = true; });
		setInterval(function () { if (!document.hidden) loadState(); }, 60000);
	}

	/* ---------- Auth & profile forms ---------- */
	function showErrors(form, err) {
		$$('.sb-field-err', form).forEach(function (x) { x.remove(); });
		$$('.sb-field.is-error', form).forEach(function (x) { x.classList.remove('is-error'); });
		var fields = err && err.data && err.data.data && err.data.data.fields;
		if (fields) Object.keys(fields).forEach(function (k) {
			var input = form.querySelector('[name="' + k + '"]'); if (!input) return;
			var f = input.closest('.sb-field') || input.parentNode;
			f.classList.add('is-error');
			var m = document.createElement('span'); m.className = 'sb-field-err'; m.textContent = fields[k]; f.appendChild(m);
		});
	}
	function msg(form, text, ok) { var m = $('[data-sb-msg]', form); if (m) { m.className = 'sb-form-msg ' + (ok ? 'is-ok' : 'is-error'); m.textContent = text; } }
	function initForms() {
		$$('[data-sb-auth-tab]').forEach(function (t) {
			t.addEventListener('click', function () {
				$$('[data-sb-auth-tab]').forEach(function (x) { x.classList.toggle('is-active', x === t); });
				$$('[data-sb-auth]').forEach(function (f) { f.hidden = f.dataset.sbAuth !== t.dataset.sbAuthTab; });
			});
		});
		$$('[data-sb-photo-input]').forEach(function (inp) {
			inp.addEventListener('change', function () {
				var f = inp.files && inp.files[0]; if (!f) return;
				if (f.size > 5 * 1024 * 1024) { toast('Photo must be under 5 MB', 'error'); inp.value = ''; return; }
				var c = inp.closest('[data-sb-photo-preview]');
				c.style.backgroundImage = 'url(' + URL.createObjectURL(f) + ')'; c.classList.add('has-img');
			});
		});
		$$('[data-sb-auth]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var btn = $('button[type=submit]', form); btn.classList.add('is-loading');
				var path = form.dataset.sbAuth === 'login' ? 'auth/login' : 'auth/register';
				api(path, { method: 'POST', form: new FormData(form) }).then(function (r) {
					msg(form, form.dataset.sbAuth === 'login' ? 'Signed in. Taking you there…' : 'Account created! Welcome to Soubenco.', true);
					location.href = r.redirect || S.account;
				}).catch(function (err) { showErrors(form, err); msg(form, err.message); btn.classList.remove('is-loading'); });
			});
		});
		var pf = $('[data-sb-profile]');
		if (pf) pf.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = $('button[type=submit]', pf); btn.classList.add('is-loading');
			api('profile', { method: 'POST', form: new FormData(pf) }).then(function () {
				showErrors(pf, null); msg(pf, 'Your profile has been saved.', true); toast('Profile saved');
				$$('input[type=password]', pf).forEach(function (x) { x.value = ''; });
			}).catch(function (err) { showErrors(pf, err); msg(pf, err.message); }).finally(function () { btn.classList.remove('is-loading'); });
		});
	}

	/* ---------- Drawer, filters, hero slider ---------- */
	function initUI() {
		var drawer = $('[data-sb-drawer]');
		$$('[data-sb-drawer-open]').forEach(function (b) { b.addEventListener('click', function () { drawer.hidden = false; document.body.style.overflow = 'hidden'; }); });
		$$('[data-sb-drawer-close]').forEach(function (b) { b.addEventListener('click', function () { drawer.hidden = true; document.body.style.overflow = ''; }); });
		var fl = $('[data-sb-filters]'), fbg = $('.sb-filters-bg');
		$$('[data-sb-filters-open]').forEach(function (b) { b.addEventListener('click', function () { fl.classList.add('is-open'); fbg.classList.add('is-open'); }); });
		$$('[data-sb-filters-close]').forEach(function (b) { b.addEventListener('click', function () { fl.classList.remove('is-open'); fbg.classList.remove('is-open'); }); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if (drawer) { drawer.hidden = true; document.body.style.overflow = ''; } if (fl) { fl.classList.remove('is-open'); fbg.classList.remove('is-open'); } } });
		$$('[data-sb-slider]').forEach(function (s) {
			var slides = $$('.sb-hero-slide', s), dots = $$('.sb-dots button', s), i = 0, timer;
			if (slides.length < 2) return;
			var go = function (n) { i = (n + slides.length) % slides.length; slides.forEach(function (x, k) { x.classList.toggle('is-active', k === i); }); dots.forEach(function (x, k) { x.classList.toggle('is-active', k === i); }); };
			var play = function () { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 5000); };
			dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); play(); }); });
			s.addEventListener('mouseenter', function () { clearInterval(timer); });
			s.addEventListener('mouseleave', play);
			play();
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (IS_APP) document.documentElement.classList.add('sb-app');
		initUI(); initSearch(); initBell(); initProduct(); initCart(); initForms();
		applyAppPrices(document);
		loadState();
	});
})();
