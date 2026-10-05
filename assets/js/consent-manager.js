/**
 * GioCookies frontend.
 *
 * Cookies (format kept stable for integrations):
 *   giocookies_consent     = accepted | declined | custom
 *   giocookies_preferences = {"necessary":true,"analytics":bool,"marketing":bool,"version":"...","id":"uuid"}
 *
 * Public API: window.GioCookies.open(), .getConsent(), .hasConsent(category), .activateScripts()
 * DOM event:  document 'giocookies:consent' (detail = preferences) after every decision.
 */
(function () {
	'use strict';

	var config = window.giocookies_vars || {};
	var CURRENT_VERSION = typeof config.version === 'string' ? config.version : '';
	var MAX_AGE = Math.max(1, parseInt(config.cookie_days, 10) || 365) * 86400;

	// WP Consent API: declare opt-in consent as early as possible.
	window.wp_consent_type = 'optin';
	try {
		document.dispatchEvent(new CustomEvent('wp_consent_type_defined'));
	} catch (e) { /* old browsers */ }

	var readCookie = function (name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		if (!match) return null;
		try {
			return decodeURIComponent(match[1]);
		} catch (e) {
			return match[1];
		}
	};

	var writeCookie = function (name, value) {
		var secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = name + '=' + value + '; path=/; max-age=' + MAX_AGE + '; SameSite=Lax' + secure;
	};

	var readStored = function () {
		var raw = readCookie('giocookies_preferences');
		if (!raw) return null;
		try {
			var prefs = JSON.parse(raw);
			return prefs && typeof prefs === 'object' ? prefs : null;
		} catch (e) {
			return null;
		}
	};

	var storedVersion = function (prefs) {
		return prefs && typeof prefs.version === 'string' ? prefs.version : '';
	};

	/** Valid stored decision: consent cookie present and same consent version. */
	var getConsent = function () {
		var prefs = readStored();
		if (readCookie('giocookies_consent') === null || storedVersion(prefs) !== CURRENT_VERSION) {
			return null;
		}
		return {
			necessary: true,
			analytics: !!(prefs && prefs.analytics),
			marketing: !!(prefs && prefs.marketing)
		};
	};

	var hasConsent = function (category) {
		if (category === 'necessary') return true;
		var consent = getConsent();
		return !!(consent && consent[category]);
	};

	var uuid4 = function () {
		var c = window.crypto || window.msCrypto;
		if (c && typeof c.randomUUID === 'function') return c.randomUUID();
		var b = new Uint8Array(16);
		if (c && c.getRandomValues) {
			c.getRandomValues(b);
		} else {
			for (var i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256);
		}
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		var h = [];
		for (var j = 0; j < 16; j++) h.push((b[j] + 0x100).toString(16).slice(1));
		return h.slice(0, 4).join('') + '-' + h.slice(4, 6).join('') + '-' + h.slice(6, 8).join('') + '-' + h.slice(8, 10).join('') + '-' + h.slice(10).join('');
	};

	var consentId = function () {
		var prefs = readStored();
		var id = prefs && typeof prefs.id === 'string' ? prefs.id : '';
		return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id) ? id : uuid4();
	};

	/* ---------- Script activation ---------- */

	var activating = false;
	var activateQueued = false;

	/**
	 * Turn <script type="text/plain" data-giocookies-category="..."> into real scripts,
	 * in document order, waiting for each external script before the next one.
	 */
	var activateScripts = function () {
		if (activating) {
			activateQueued = true;
			return;
		}
		var nodes = Array.prototype.slice.call(document.querySelectorAll('script[type="text/plain"][data-giocookies-category]'))
			.filter(function (node) {
				return hasConsent(node.getAttribute('data-giocookies-category'));
			});
		if (!nodes.length) return;

		activating = true;
		var next = function () {
			var node = nodes.shift();
			if (!node) {
				activating = false;
				if (activateQueued) {
					activateQueued = false;
					activateScripts();
				}
				return;
			}
			var script = document.createElement('script');
			for (var i = 0; i < node.attributes.length; i++) {
				var attr = node.attributes[i];
				if (attr.name === 'type' || attr.name === 'data-giocookies-type') continue;
				script.setAttribute(attr.name, attr.value);
			}
			var type = node.getAttribute('data-giocookies-type');
			if (type) script.type = type;
			script.setAttribute('data-giocookies-activated', '');

			if (node.src) {
				script.async = false;
				script.onload = script.onerror = next;
				node.parentNode.replaceChild(script, node);
			} else {
				script.text = node.text;
				node.parentNode.replaceChild(script, node);
				next();
			}
		};
		next();
	};

	/* ---------- Integrations ---------- */

	var pushToDataLayer = function (preferences) {
		window.dataLayer = window.dataLayer || [];
		function gtag() { window.dataLayer.push(arguments); }

		gtag('consent', 'update', {
			'analytics_storage': preferences.analytics ? 'granted' : 'denied',
			'ad_storage': preferences.marketing ? 'granted' : 'denied',
			'ad_user_data': preferences.marketing ? 'granted' : 'denied',
			'ad_personalization': preferences.marketing ? 'granted' : 'denied'
		});

		window.dataLayer.push({
			event: 'cookie_consent_update',
			cookie_consent: {
				necessary: true,
				analytics: preferences.analytics,
				marketing: preferences.marketing
			}
		});
	};

	/** WP Consent API (no-op when the plugin is not installed). */
	var syncConsentApi = function (preferences, onlyIfChanged) {
		if (typeof window.wp_set_consent !== 'function') return;
		var map = {
			functional: true,
			preferences: false,
			statistics: !!preferences.analytics,
			'statistics-anonymous': !!preferences.analytics,
			marketing: !!preferences.marketing
		};
		Object.keys(map).forEach(function (category) {
			var value = map[category] ? 'allow' : 'deny';
			if (onlyIfChanged && typeof window.wp_has_consent === 'function' && window.wp_has_consent(category) === map[category]) {
				return;
			}
			window.wp_set_consent(category, value);
		});
	};

	/* ---------- Banner ---------- */

	var GioConsentManager = function () {
		var banner = document.getElementById('giocookies-banner');
		var bubble = document.getElementById('giocookies-bubble');
		var title = document.getElementById('giocookies-title');
		var preferencesPanel = document.getElementById('giocookies-preferences');
		var acceptBtn = document.getElementById('giocookies-accept');
		var acceptSelectedBtn = document.getElementById('giocookies-accept-selected');
		var declineBtn = document.getElementById('giocookies-decline');
		var customizeBtn = document.getElementById('giocookies-customize');
		var analyticsCheckbox = document.getElementById('giocookies-analytics');
		var marketingCheckbox = document.getElementById('giocookies-marketing');
		var lastOpener = null;

		var loadStoredPreferences = function () {
			var consent = getConsent();
			if (analyticsCheckbox) analyticsCheckbox.checked = !!(consent && consent.analytics);
			if (marketingCheckbox) marketingCheckbox.checked = !!(consent && consent.marketing);
		};

		var setCustomizing = function (on) {
			if (!banner || !preferencesPanel) return;
			preferencesPanel.hidden = !on;
			banner.classList.toggle('is-customizing', on);
			if (acceptSelectedBtn) acceptSelectedBtn.hidden = !on;
			if (customizeBtn) {
				customizeBtn.hidden = on;
				customizeBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
			}
		};

		var setExpanded = function (on) {
			if (bubble) bubble.setAttribute('aria-expanded', on ? 'true' : 'false');
			Array.prototype.forEach.call(document.querySelectorAll('[data-giocookies-open]'), function (el) {
				el.setAttribute('aria-expanded', on ? 'true' : 'false');
			});
		};

		var showBanner = function (focus) {
			if (!banner) return;
			loadStoredPreferences();
			banner.classList.add('is-visible');
			setExpanded(true);
			if (focus && title) title.focus();
		};

		var hideBanner = function () {
			if (!banner) return;
			banner.classList.remove('is-visible');
			setCustomizing(false);
			setExpanded(false);
		};

		var returnFocus = function () {
			var target = (lastOpener && document.body.contains(lastOpener) && lastOpener.offsetParent !== null) ? lastOpener : bubble;
			if (target) target.focus({ preventScroll: true });
			lastOpener = null;
		};

		var handleDecision = function (type, customPrefs) {
			var preferences = { necessary: true, analytics: false, marketing: false };

			if (type === 'accepted') {
				// "Accept all" grants only the categories enabled in the settings.
				var cats = (window.giocookies_vars && giocookies_vars.categories) || { analytics: true, marketing: true };
				preferences.analytics = !!cats.analytics;
				preferences.marketing = !!cats.marketing;
			} else if (type === 'custom' && customPrefs) {
				preferences.analytics = !!customPrefs.analytics;
				preferences.marketing = !!customPrefs.marketing;
			}

			var id = consentId();
			var stored = {
				necessary: true,
				analytics: preferences.analytics,
				marketing: preferences.marketing,
				version: CURRENT_VERSION,
				id: id
			};

			writeCookie('giocookies_consent', type);
			writeCookie('giocookies_preferences', JSON.stringify(stored));

			hideBanner();
			returnFocus();

			pushToDataLayer(preferences);
			syncConsentApi(preferences, false);
			activateScripts();

			try {
				document.dispatchEvent(new CustomEvent('giocookies:consent', { detail: { decision: type, preferences: preferences, id: id } }));
			} catch (e) { /* old browsers */ }

			if (config.log && config.ajax_url && window.fetch) {
				window.fetch(config.ajax_url, {
					method: 'POST',
					credentials: 'same-origin',
					keepalive: true,
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'giocookies_save_consent',
						giocookies_nonce: config.nonce || '',
						decision: type,
						analytics: preferences.analytics,
						marketing: preferences.marketing,
						consent_id: id,
						version: CURRENT_VERSION
					})
				}).catch(function (error) {
					if (window.console) window.console.error('GioCookies Error:', error);
				});
			}
		};

		var open = function (opener) {
			lastOpener = opener || null;
			showBanner(true);
		};

		if (bubble && banner) {
			bubble.addEventListener('click', function () {
				if (banner.classList.contains('is-visible')) {
					hideBanner();
				} else {
					open(bubble);
				}
			});
		}

		// Any element with data-giocookies-open (e.g. the [giocookies_preferences] shortcode) reopens the banner.
		document.addEventListener('click', function (e) {
			var opener = e.target && e.target.closest ? e.target.closest('[data-giocookies-open]') : null;
			if (!opener || !banner) return;
			e.preventDefault();
			open(opener);
		});

		// Esc closes the panel only once a choice has been made.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && banner && banner.classList.contains('is-visible') && getConsent()) {
				hideBanner();
				returnFocus();
			}
		});

		if (customizeBtn) {
			customizeBtn.addEventListener('click', function () {
				setCustomizing(true);
				if (analyticsCheckbox) analyticsCheckbox.focus();
			});
		}

		if (acceptBtn) {
			acceptBtn.addEventListener('click', function () { handleDecision('accepted'); });
		}

		if (acceptSelectedBtn) {
			acceptSelectedBtn.addEventListener('click', function () {
				handleDecision('custom', {
					analytics: analyticsCheckbox ? analyticsCheckbox.checked : false,
					marketing: marketingCheckbox ? marketingCheckbox.checked : false
				});
			});
		}

		if (declineBtn) {
			declineBtn.addEventListener('click', function () { handleDecision('declined'); });
		}

		window.GioCookies.open = function () { open(document.activeElement); };

		var consent = getConsent();
		loadStoredPreferences();
		if (consent) {
			syncConsentApi(consent, true);
			activateScripts();
		} else {
			// No choice yet, or the consent version changed: deny in the WP Consent API and ask.
			syncConsentApi({ analytics: false, marketing: false }, true);
			showBanner(false);
		}
	};

	window.GioCookies = {
		open: function () {},
		getConsent: getConsent,
		hasConsent: hasConsent,
		activateScripts: activateScripts
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', GioConsentManager);
	} else {
		GioConsentManager();
	}
}());
