/**
 * GioCookies settings screen: tabs, live preview, "use the default text" links and color pickers.
 * Without JavaScript the page still works: every panel is shown and saved with one form.
 */
jQuery(function ($) {
	// Settings screen only: the consent log shares the wrapper class but has no tabs.
	var root = document.querySelector('.giocookies-admin');
	if (!root || !root.querySelector('.giocookies-tabs')) {
		return;
	}
	root.classList.add('giocookies-js');

	var layout = root.querySelector('.giocookies-layout');
	var frame = root.querySelector('.giocookies-preview__frame');
	var banner = root.querySelector('.giocookies-preview__banner');
	var tabs = root.querySelectorAll('.giocookies-tabs [data-tab]');
	var panels = root.querySelectorAll('.giocookies-panel');
	var referer = root.querySelector('input[name="_wp_http_referer"]');

	/* ---------- Tabs ---------- */

	var activate = function (slug, focus) {
		var found = false;
		panels.forEach(function (panel) {
			var on = panel.getAttribute('data-panel') === slug;
			panel.hidden = !on;
			if (on) {
				found = true;
				layout.classList.toggle('has-preview', panel.getAttribute('data-preview') === '1');
			}
		});
		if (!found) {
			return;
		}
		tabs.forEach(function (tab) {
			var on = tab.getAttribute('data-tab') === slug;
			tab.classList.toggle('nav-tab-active', on);
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.setAttribute('tabindex', on ? '0' : '-1');
			if (on && focus) {
				tab.focus();
			}
		});
		if (frame) {
			frame.classList.toggle('is-customizing', slug === 'categories');
		}
		root.setAttribute('data-current-tab', slug);

		// Keep the tab in the URL and in the redirect after saving.
		var url = new URL(window.location.href);
		url.searchParams.set('tab', slug);
		url.searchParams.delete('settings-updated');
		window.history.replaceState(null, '', url.toString());
		if (referer) {
			var back = new URL(referer.value, window.location.origin);
			back.searchParams.set('tab', slug);
			back.searchParams.delete('settings-updated');
			referer.value = back.pathname + back.search;
		}
	};

	tabs.forEach(function (tab, index) {
		tab.addEventListener('click', function (event) {
			event.preventDefault();
			activate(tab.getAttribute('data-tab'), false);
		});
		tab.addEventListener('keydown', function (event) {
			var next = null;
			if (event.key === 'ArrowRight') {
				next = tabs[(index + 1) % tabs.length];
			} else if (event.key === 'ArrowLeft') {
				next = tabs[(index - 1 + tabs.length) % tabs.length];
			} else if (event.key === 'Home') {
				next = tabs[0];
			} else if (event.key === 'End') {
				next = tabs[tabs.length - 1];
			}
			if (next) {
				event.preventDefault();
				activate(next.getAttribute('data-tab'), true);
			}
		});
	});

	root.querySelectorAll('[data-tab-link]').forEach(function (link) {
		link.addEventListener('click', function (event) {
			event.preventDefault();
			activate(link.getAttribute('data-tab-link'), true);
			window.scrollTo(0, 0);
		});
	});

	activate(root.getAttribute('data-current-tab') || 'overview', false);

	/* ---------- Live preview ---------- */

	var stripTags = function (html) {
		var box = document.createElement('div');
		box.innerHTML = html;
		return box.textContent || '';
	};

	var syncText = function (input) {
		var name = input.getAttribute('data-gc-in');
		var value = input.value.trim();
		var text = value === '' ? input.getAttribute('placeholder') || '' : value;
		if (banner) {
			banner.querySelectorAll('[data-gc-out="' + name + '"]').forEach(function (out) {
				out.textContent = name === 'giocookies_message' ? stripTags(text) : text;
			});
		}
		var reset = root.querySelector('[data-gc-reset="' + name + '"]');
		if (reset) {
			reset.hidden = value === '';
		}
	};

	root.querySelectorAll('[data-gc-in]').forEach(function (input) {
		input.addEventListener('input', function () {
			syncText(input);
		});
	});

	root.querySelectorAll('[data-gc-reset]').forEach(function (button) {
		button.addEventListener('click', function () {
			var input = document.getElementById(button.getAttribute('data-gc-reset'));
			if (input) {
				input.value = '';
				syncText(input);
				input.focus();
			}
		});
	});

	root.querySelectorAll('[data-gc-category]').forEach(function (input) {
		var sync = function () {
			var key = input.getAttribute('data-gc-category');
			var card = root.querySelector('.giocookies-category[data-category="' + key + '"]');
			if (card) {
				card.classList.toggle('is-disabled', !input.checked);
			}
			if (banner) {
				var row = banner.querySelector('[data-gc-category-row="' + key + '"]');
				if (row) {
					row.hidden = !input.checked;
				}
			}
		};
		input.addEventListener('change', sync);
		sync();
	});

	root.querySelectorAll('[data-gc-position]').forEach(function (input) {
		input.addEventListener('change', function () {
			if (frame && input.checked) {
				frame.classList.remove('is-bottom-left', 'is-bottom-right', 'is-bottom-bar');
				frame.classList.add('is-' + input.value);
			}
		});
	});

	/* ---------- Colors ---------- */

	var setColor = function (name, color) {
		if (!banner) {
			return;
		}
		if (name === 'giocookies_accent_color') {
			banner.style.setProperty('--giocookies-accent', color || '');
			banner.style.setProperty('--giocookies-accent-hover', color || '');
		} else {
			banner.style.setProperty('--giocookies-accent-text', color || '');
		}
	};

	if ($.fn.wpColorPicker) {
		$('.giocookies-color').each(function () {
			var name = this.getAttribute('data-gc-color');
			$(this).wpColorPicker({
				change: function (event, ui) {
					setColor(name, ui.color.toString());
				},
				clear: function () {
					setColor(name, '');
				}
			});
		});
	}
});
