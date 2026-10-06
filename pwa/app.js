/* ANX HR employee PWA - vanilla ES2017, no build step. */
'use strict';
(function () {
	// Dolibarr root = path before /custom/anxhr/pwa/
	var ROOT = location.pathname.replace(/\/custom\/anxhr\/pwa\/.*$/, '');
	var API = ROOT + '/api/index.php';
	var LS_KEY = 'anxhr_pwa_key', LS_QUEUE = 'anxhr_pwa_queue', LS_LAST = 'anxhr_pwa_last';

	var I18N = {
		de: {
			AppTitle: 'ANX HR Zeiterfassung', Login: 'Benutzername', Password: 'Passwort', SignIn: 'Anmelden',
			UseApiKey: 'Mit API-Schlüssel anmelden (SSO)', ApiKey: 'API-Schlüssel',
			SsoHint: 'Bei Anmeldung über SSO: API-Schlüssel aus der eigenen Benutzerkarte in Dolibarr verwenden.',
			Logout: 'Abmelden', AnxhrClockIn: 'Kommen', AnxhrClockOut: 'Gehen', AnxhrStartBreak: 'Pause beginnen', AnxhrEndBreak: 'Pause beenden',
			HomeOffice: 'Homeoffice', WorkedToday: 'Heute gearbeitet', BalanceMonth: 'Saldo (Monat)', Today: 'Heute', Last7: 'Letzte 7 Tage',
			Documents: 'Meine Dokumente', DocsHint: 'Dokumente öffnen sich in Dolibarr (Anmeldung im Browser erforderlich).',
			TabTime: 'Zeit', TabDocs: 'Dokumente', StateIn: 'Anwesend', StateBreak: 'Pause', StateOut: 'Abwesend',
			SinceIn: 'seit Kommen', SinceBreak: 'Pause seit', NotClocked: 'Nicht eingestempelt', Target: 'Soll',
			Offline: 'Offline - Buchungen werden später übertragen', Queued: 'Wartende Buchungen: ', NoEntries: 'Noch keine Buchungen',
			NoDocs: 'Keine Dokumente', LoginFailed: 'Anmeldung fehlgeschlagen', LoginApiDisabled: 'Login-API ist deaktiviert. Bitte API-Schlüssel verwenden.',
			ActionNotAllowed: 'Aktion im aktuellen Status nicht erlaubt', DayLocked: 'Tag ist gesperrt', AlreadyRecorded: 'Bereits gebucht',
			Recorded: 'Gebucht', in: 'Kommen', out: 'Gehen', break_start: 'Pause Beginn', break_end: 'Pause Ende', New: 'Neu'
		},
		en: {
			AppTitle: 'ANX HR Time', Login: 'Login', Password: 'Password', SignIn: 'Sign in',
			UseApiKey: 'Sign in with API key (SSO)', ApiKey: 'API key',
			SsoHint: 'SSO users: use the API key from your Dolibarr user card.',
			Logout: 'Log out', AnxhrClockIn: 'Clock in', AnxhrClockOut: 'Clock out', AnxhrStartBreak: 'Start break', AnxhrEndBreak: 'End break',
			HomeOffice: 'Home office', WorkedToday: 'Worked today', BalanceMonth: 'Balance (month)', Today: 'Today', Last7: 'Last 7 days',
			Documents: 'My documents', DocsHint: 'Documents open in Dolibarr (browser login required).',
			TabTime: 'Time', TabDocs: 'Documents', StateIn: 'Present', StateBreak: 'On break', StateOut: 'Absent',
			SinceIn: 'since clock in', SinceBreak: 'break since', NotClocked: 'Not clocked in', Target: 'Target',
			Offline: 'Offline - actions will be sent later', Queued: 'Queued actions: ', NoEntries: 'No entries yet',
			NoDocs: 'No documents', LoginFailed: 'Login failed', LoginApiDisabled: 'Login API is disabled. Please use your API key.',
			ActionNotAllowed: 'Action not allowed in the current state', DayLocked: 'Day is locked', AlreadyRecorded: 'Already recorded',
			Recorded: 'Recorded', in: 'Clock in', out: 'Clock out', break_start: 'Break start', break_end: 'Break end', New: 'New'
		}
	};
	var lang = (navigator.language || 'de').slice(0, 2) === 'en' ? 'en' : 'de';
	function t(k) { return (I18N[lang] && I18N[lang][k]) || I18N.en[k] || k; }

	var $ = function (id) { return document.getElementById(id); };
	var data = null, timerHandle = null, clockOffset = 0;

	function lsGet(k, def) { try { var v = localStorage.getItem(k); return v === null ? def : JSON.parse(v); } catch (e) { return def; } }
	function lsSet(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* ignore */ } }

	function applyI18n() {
		document.documentElement.lang = lang;
		document.querySelectorAll('[data-i18n]').forEach(function (el) { el.textContent = t(el.getAttribute('data-i18n')); });
		document.querySelectorAll('[data-i18n-aria]').forEach(function (el) { el.setAttribute('aria-label', t(el.getAttribute('data-i18n-aria'))); });
	}

	function fmtMin(m, signed) {
		var s = m < 0 ? '-' : (signed && m > 0 ? '+' : '');
		m = Math.abs(Math.round(m));
		return s + Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2);
	}
	function pad(n) { return ('0' + n).slice(-2); }
	function localStamp(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }

	async function api(method, path, body) {
		var key = lsGet(LS_KEY, '');
		var res = await fetch(API + path, {
			method: method,
			headers: Object.assign({ 'Accept': 'application/json' }, key ? { 'DOLAPIKEY': key } : {}, body ? { 'Content-Type': 'application/json' } : {}),
			body: body ? JSON.stringify(body) : undefined,
			credentials: 'omit'
		});
		var json = null;
		try { json = await res.json(); } catch (e) { /* empty */ }
		if (!res.ok) {
			var err = new Error((json && json.error && json.error.message) || ('HTTP ' + res.status));
			err.status = res.status;
			throw err;
		}
		return json;
	}

	/* Login */
	function showLogin(msg) {
		$('app').hidden = true; $('login').hidden = false;
		$('login-err').textContent = msg || '';
		$('f-login').focus();
	}
	$('loginform').addEventListener('submit', async function (e) {
		e.preventDefault();
		$('login-err').textContent = '';
		var key = $('f-key').value.trim();
		if (!key) {
			try {
				var r = await api('POST', '/login', { login: $('f-login').value.trim(), password: $('f-pass').value });
				key = r && r.success && r.success.token;
			} catch (err) {
				$('login-err').textContent = /disabled/i.test(err.message) ? t('LoginApiDisabled') : t('LoginFailed');
				return;
			}
		}
		if (!key) { $('login-err').textContent = t('LoginFailed'); return; }
		lsSet(LS_KEY, key);
		$('f-pass').value = '';
		start();
	});
	$('btn-logout').addEventListener('click', function () {
		try { localStorage.removeItem(LS_KEY); localStorage.removeItem(LS_LAST); } catch (e) { /* ignore */ }
		data = null; showLogin('');
	});

	/* Tabs */
	function selectTab(which) {
		['time', 'docs'].forEach(function (n) {
			$('t-' + n).setAttribute('aria-selected', String(n === which));
			$('tab-' + n).hidden = n !== which;
		});
		if (which === 'docs') { loadDocs(); }
	}
	$('t-time').addEventListener('click', function () { selectTab('time'); });
	$('t-docs').addEventListener('click', function () { selectTab('docs'); });

	/* Offline queue */
	function queue() { return lsGet(LS_QUEUE, []); }
	function updateOnline() {
		var q = queue();
		$('offline').hidden = navigator.onLine;
		$('offline').textContent = t('Offline');
		$('queue-info').textContent = q.length ? t('Queued') + q.length : '';
	}
	async function flushQueue() {
		var q = queue();
		while (q.length && navigator.onLine) {
			var item = q[0];
			try {
				data = await api('POST', '/anxhr/clock', item);
			} catch (err) {
				if (!err.status) { break; } // still offline
				// 4xx: action rejected by the server (state changed meanwhile), drop it
			}
			q.shift(); lsSet(LS_QUEUE, q);
		}
		updateOnline();
	}
	window.addEventListener('online', function () { flushQueue().then(refresh); });
	window.addEventListener('offline', updateOnline);

	/* Clock */
	function predictState(type) { return type === 'in' || type === 'break_end' ? 'in' : (type === 'break_start' ? 'break' : 'out'); }
	async function doClock(type) {
		var item = { type: type, homeoffice: $('homeoffice').checked ? 1 : 0, client_time: localStamp(new Date()) };
		$('btn-primary').disabled = true; $('btn-break').disabled = true;
		try {
			if (!navigator.onLine) { throw Object.assign(new Error('offline'), { status: 0 }); }
			delete item.client_time;
			data = await api('POST', '/anxhr/clock', item);
			lsSet(LS_LAST, data);
		} catch (err) {
			if (!err.status) {
				item.client_time = item.client_time || localStamp(new Date());
				var q = queue(); q.push(item); lsSet(LS_QUEUE, q);
				if (data) {
					data.state = predictState(type);
					data.last_ts = Math.floor(Date.now() / 1000);
					data.allowed = data.state === 'in' ? ['break_start', 'out'] : (data.state === 'break' ? ['break_end', 'out'] : ['in']);
					data.today_entries.push({ type: type, time: item.client_time, homeoffice: item.homeoffice, source: 'queued' });
				}
			} else if (err.status === 401) {
				showLogin(t('LoginFailed')); return;
			} else {
				alert(t(err.message.replace(/^.*:\s*/, '')));
			}
		}
		updateOnline(); render();
	}
	$('btn-primary').addEventListener('click', function () { doClock(this.getAttribute('data-type')); });
	$('btn-break').addEventListener('click', function () { doClock(this.getAttribute('data-type')); });

	function render() {
		if (!data) { return; }
		$('u-name').textContent = data.name || data.login;
		$('u-date').textContent = new Date().toLocaleDateString(lang === 'de' ? 'de-AT' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
		var st = data.state || 'out', allowed = data.allowed || ['in'];
		var badge = $('state-badge');
		badge.className = 'badge ' + st;
		badge.textContent = t(st === 'in' ? 'StateIn' : (st === 'break' ? 'StateBreak' : 'StateOut'));

		var p = $('btn-primary'), b = $('btn-break');
		var pType = st === 'out' ? 'in' : 'out';
		p.setAttribute('data-type', pType);
		p.textContent = t(pType === 'in' ? 'AnxhrClockIn' : 'AnxhrClockOut');
		p.setAttribute('aria-label', p.textContent);
		p.className = 'btn primary big' + (pType === 'out' ? ' out' : '');
		p.disabled = allowed.indexOf(pType) < 0;
		var bType = st === 'break' ? 'break_end' : 'break_start';
		b.setAttribute('data-type', bType);
		b.textContent = t(bType === 'break_end' ? 'AnxhrEndBreak' : 'AnxhrStartBreak');
		b.setAttribute('aria-label', b.textContent);
		b.hidden = st === 'out';
		b.disabled = allowed.indexOf(bType) < 0;

		$('k-worked').textContent = fmtMin(data.worked_today_min || 0);
		$('k-target').textContent = t('Target') + ' ' + fmtMin(data.target_today_min || 0);
		var bal = $('k-balance');
		bal.textContent = fmtMin(data.balance_month_min || 0, true);
		bal.className = 'val ' + ((data.balance_month_min || 0) < 0 ? 'neg' : 'pos');

		var tl = $('timeline'); tl.innerHTML = '';
		(data.today_entries || []).forEach(function (e) {
			var li = document.createElement('li');
			var left = document.createElement('span'); left.textContent = t(e.type);
			var right = document.createElement('span');
			right.textContent = String(e.time || '').slice(11, 16);
			if (e.homeoffice) { var h = document.createElement('span'); h.className = 'tag'; h.textContent = 'HO'; left.appendChild(document.createTextNode(' ')); left.appendChild(h); }
			if (e.source === 'queued') { var q = document.createElement('span'); q.className = 'tag'; q.textContent = '...'; right.appendChild(q); }
			li.appendChild(left); li.appendChild(right); tl.appendChild(li);
		});
		if (!tl.children.length) { var li0 = document.createElement('li'); li0.className = 'sub'; li0.textContent = t('NoEntries'); tl.appendChild(li0); }

		var dl = $('days'); dl.innerHTML = '';
		(data.last7 || []).forEach(function (d) {
			var li = document.createElement('li');
			var left = document.createElement('span');
			left.textContent = new Date(d.day + 'T12:00:00').toLocaleDateString(lang === 'de' ? 'de-AT' : 'en-GB', { weekday: 'short', day: '2-digit', month: '2-digit' });
			(d.violations || []).forEach(function (c) { var s = document.createElement('span'); s.className = 'tag bad'; s.textContent = c; left.appendChild(document.createTextNode(' ')); left.appendChild(s); });
			var right = document.createElement('span');
			right.innerHTML = '';
			right.appendChild(document.createTextNode(fmtMin(d.worked) + ' / ' + fmtMin(d.target) + ' '));
			var df = document.createElement('strong'); df.className = d.diff < 0 ? 'neg' : 'pos'; df.textContent = fmtMin(d.diff, true);
			right.appendChild(df);
			li.appendChild(left); li.appendChild(right); dl.appendChild(li);
		});
		tickTimer();
	}

	function tickTimer() {
		if (!data) { return; }
		var el = $('timer'), lab = $('timer-label');
		if (data.state === 'out' || !data.last_ts) {
			el.textContent = fmtMin(data.worked_today_min || 0);
			lab.textContent = data.state === 'out' ? t('NotClocked') : '';
			return;
		}
		var nowS = Date.now() / 1000 + clockOffset;
		if (data.state === 'break') {
			el.textContent = fmtMin((nowS - data.last_ts) / 60);
			lab.textContent = t('SinceBreak');
		} else {
			// worked_today_min is live at server_ts; add the time elapsed since.
			el.textContent = fmtMin((data.worked_today_min || 0) + Math.max(0, nowS - (data.server_ts || nowS)) / 60);
			lab.textContent = t('WorkedToday');
		}
	}

	/* Documents */
	async function loadDocs() {
		var ul = $('docs');
		try {
			var docs = await api('GET', '/anxhr/vault');
			ul.innerHTML = '';
			var unread = 0;
			docs.forEach(function (d) {
				if (d.unread) { unread++; }
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = ROOT + (d.open_url || '/custom/anxhr/vault.php'); a.target = '_blank'; a.rel = 'noopener';
				var l1 = document.createElement('div'); l1.textContent = d.label || d.filename;
				var l2 = document.createElement('div'); l2.className = 'sub'; l2.textContent = [d.category, d.period, Math.round(d.size / 1024) + ' KB'].filter(Boolean).join(' · ');
				a.appendChild(l1); a.appendChild(l2); li.appendChild(a);
				if (d.unread) { var dot = document.createElement('span'); dot.className = 'dot'; dot.setAttribute('aria-label', t('New')); dot.setAttribute('role', 'img'); li.appendChild(dot); }
				ul.appendChild(li);
			});
			if (!docs.length) { var li0 = document.createElement('li'); li0.className = 'sub'; li0.textContent = t('NoDocs'); ul.appendChild(li0); }
			$('unread').hidden = !unread; $('unread').textContent = String(unread);
		} catch (e) { /* offline or no right: keep previous list */ }
	}

	async function refresh() {
		try {
			data = await api('GET', '/anxhr/me');
			clockOffset = data.server_ts ? data.server_ts - Date.now() / 1000 : 0;
			lsSet(LS_LAST, data);
		} catch (err) {
			if (err.status === 401 || err.status === 403) { showLogin(t('LoginFailed')); return false; }
			data = data || lsGet(LS_LAST, null);
		}
		render();
		return true;
	}

	async function start() {
		$('login').hidden = true; $('app').hidden = false;
		updateOnline();
		await flushQueue();
		if (await refresh()) { loadDocs(); }
		if (timerHandle) { clearInterval(timerHandle); }
		timerHandle = setInterval(tickTimer, 15000);
	}

	applyI18n();
	if ('serviceWorker' in navigator) { navigator.serviceWorker.register('sw.js').catch(function () { /* ignore */ }); }
	if (lsGet(LS_KEY, '')) { start(); } else { showLogin(''); }
	document.addEventListener('visibilitychange', function () { if (!document.hidden && lsGet(LS_KEY, '')) { flushQueue().then(refresh); } });
})();
