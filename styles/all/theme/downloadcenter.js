(function () {
	'use strict';

	document.documentElement.classList.add('downloadcenter-js');

	/*
	 * Follow the board style: when its page background is dark, switch the
	 * extension to its dark colour tokens (see downloadcenter.css).
	 */
	function detectScheme() {
		var node = document.querySelector('.downloadcenter-wrap');
		var color = '';
		var probe = node ? node.parentElement : document.body;

		while (probe) {
			color = window.getComputedStyle(probe).backgroundColor;
			if (color && color !== 'transparent' && !/rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\s*\)/.test(color)) {
				break;
			}
			probe = probe.parentElement;
		}

		var match = /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/.exec(color || '');
		if (!match) {
			return;
		}

		var luminance = (0.299 * match[1] + 0.587 * match[2] + 0.114 * match[3]) / 255;
		document.documentElement.setAttribute('data-dc-scheme', luminance < 0.45 ? 'dark' : 'light');
	}

	/*
	 * Follow the board style's palette: render a hidden sample of standard phpBB
	 * elements (category bar, forum rows, panel, heading, link) next to the
	 * Download Center, read the colours the style gives them and map them to the
	 * --dc-* tokens. Because the sample uses the style's own classes, anything that
	 * recolours the forum (the style itself, a child style or an extension such as
	 * prosilver SE Appearance) recolours the Download Center as well.
	 * Every colour is checked for contrast; a value that would be unreadable keeps
	 * the built-in token instead.
	 */
	function parseColor(value) {
		// color-mix() values (used by prosilver SE Appearance) compute to color(srgb r g b [/ a]) with 0-1 channels.
		var srgb = /color\(\s*srgb\s+([\d.e-]+)\s+([\d.e-]+)\s+([\d.e-]+)(?:\s*\/\s*([\d.]+%?))?\s*\)/.exec(value || '');
		if (srgb) {
			var srgbAlpha = typeof srgb[4] === 'undefined' ? 1 : parseFloat(srgb[4]) / (srgb[4].indexOf('%') > -1 ? 100 : 1);
			return srgbAlpha < 0.95 ? null : [1, 2, 3].map(function (i) {
				return Math.max(0, Math.min(255, Math.round(parseFloat(srgb[i]) * 255)));
			});
		}
		var match = /rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,\/]+([\d.]+%?))?\s*\)/.exec(value || '');
		if (!match) {
			return null;
		}
		var alpha = typeof match[4] === 'undefined' ? 1 : parseFloat(match[4]) / (match[4].indexOf('%') > -1 ? 100 : 1);
		if (alpha < 0.95) {
			return null;
		}
		return [Math.round(+match[1]), Math.round(+match[2]), Math.round(+match[3])];
	}

	function luminance(rgb) {
		var channels = rgb.map(function (channel) {
			var value = channel / 255;
			return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
	}

	function contrast(a, b) {
		var la = luminance(a);
		var lb = luminance(b);
		return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
	}

	function mix(a, b, weight) {
		return [0, 1, 2].map(function (i) {
			return Math.round(a[i] * weight + b[i] * (1 - weight));
		});
	}

	function same(a, b) {
		return a && b && a[0] === b[0] && a[1] === b[1] && a[2] === b[2];
	}

	function hex(rgb) {
		return '#' + rgb.map(function (channel) {
			return ('0' + channel.toString(16)).slice(-2);
		}).join('').toUpperCase();
	}

	// Text colour for a filled background: the style's own text colour when readable, otherwise black or white.
	function onColor(background, preferred) {
		if (preferred && contrast(background, preferred) >= 4.5) {
			return preferred;
		}
		return contrast(background, [255, 255, 255]) >= contrast(background, [0, 0, 0]) ? [255, 255, 255] : [0, 0, 0];
	}

	// Background of an element; for gradients (prosilver header and category bars) the solid
	// fallback colour is preferred, otherwise the middle stop by luminance.
	function backgroundOf(element) {
		var style = window.getComputedStyle(element);
		var solid = parseColor(style.backgroundColor);
		var image = style.backgroundImage || '';

		if (solid || image.indexOf('gradient') === -1) {
			return solid;
		}

		var stops = (image.match(/(?:rgba?|color)\([^)]*\)/g) || []).map(parseColor).filter(Boolean);
		if (!stops.length) {
			return null;
		}
		stops.sort(function (a, b) {
			return luminance(a) - luminance(b);
		});
		return stops[Math.floor(stops.length / 2)];
	}

	function opaqueBackground(element) {
		while (element) {
			var color = backgroundOf(element);
			if (color) {
				return color;
			}
			element = element.parentElement;
		}
		return null;
	}

	function applyStylePalette() {
		var wrap = document.querySelector('.downloadcenter-wrap');
		if (!wrap || !wrap.parentElement || document.documentElement.hasAttribute('data-dc-palette')) {
			return;
		}

		var probe = document.createElement('div');
		probe.setAttribute('aria-hidden', 'true');
		probe.style.cssText = 'position:absolute;left:-10000px;top:0;width:600px;visibility:hidden;pointer-events:none;';
		probe.innerHTML = '<div class="forabg"><div class="inner"><ul class="topiclist"><li class="header"><dl class="row-item"><dt><div class="list-inner"><a href="#">a</a></div></dt></dl></li></ul>'
			+ '<ul class="topiclist forums"><li class="row bg1"><dl class="row-item"><dt>a</dt></dl></li><li class="row bg2"><dl class="row-item"><dt>a</dt></dl></li></ul></div></div>'
			+ '<div class="panel"><div class="inner"><h3>a</h3></div></div>'
			+ '<h2>a</h2><p class="dc-probe-body">a</p><a href="#">a</a>'
			+ '<div class="postbody"><div class="content">a</div></div>';
		wrap.parentElement.insertBefore(probe, wrap);

		var get = function (selector) {
			return probe.querySelector(selector);
		};
		var colorOf = function (selector) {
			var node = get(selector);
			return node ? parseColor(window.getComputedStyle(node).color) : null;
		};

		var surface = opaqueBackground(wrap.parentElement);
		var brand = backgroundOf(get('.forabg'));
		var brandText = colorOf('li.header dt') || colorOf('li.header a');
		var rowA = backgroundOf(get('li.row.bg1'));
		var rowB = backgroundOf(get('li.row.bg2'));
		var panel = backgroundOf(get('.panel'));
		var ink = colorOf('.postbody .content');
		var bodyText = colorOf('.dc-probe-body');
		var heading = colorOf('h2');
		var accent = colorOf('.panel h3');
		var link = colorOf('.dc-probe-body + a');

		probe.parentNode.removeChild(probe);

		if (!surface) {
			return;
		}

		var tokens = {};
		var set = function (name, value) {
			if (value) {
				tokens[name] = hex(value);
			}
		};
		var readable = function (color, minimum) {
			return color && contrast(color, surface) >= minimum ? color : null;
		};

		ink = readable(ink, 4.5) || readable(bodyText, 4.5);
		link = readable(link, 4.5);
		set('--dc-surface', surface);
		set('--dc-ink', ink);
		set('--dc-link', link);
		set('--dc-heading', readable(heading, 4.5) || ink);
		set('--dc-accent', readable(accent, 4.5) || link);

		if (ink) {
			var muted = readable(bodyText, 4.5);
			if (!muted || same(muted, ink)) {
				muted = readable(mix(ink, surface, 0.72), 4.5) || ink;
			}
			set('--dc-muted', muted);
			set('--dc-line', mix(ink, surface, 0.22));
			set('--dc-control-line', mix(ink, surface, 0.32));
		}

		if (brand && contrast(brand, surface) >= 1.5) {
			set('--dc-brand', brand);
			set('--dc-on-brand', onColor(brand, brandText));
		}
		if (link) {
			set('--dc-on-link', onColor(link, same(link, brand) ? brandText : null));
		}

		// Tinted surfaces: the forum row colours; panels follow the style's panel colour.
		var tintBase = rowA || rowB;
		if (tintBase && ink && contrast(tintBase, ink) >= 4.5) {
			var tintAlt = rowB && !same(rowB, tintBase) && contrast(rowB, ink) >= 4.5 ? rowB : mix(tintBase, brand || ink, 0.9);
			set('--dc-tint', tintBase);
			set('--dc-tint-alt', tintAlt);
			set('--dc-tint-strong', mix(tintBase, brand || ink, 0.7));
			set('--dc-line-soft', mix(tintBase, ink, 0.85));
		}
		if (panel && ink && !same(panel, surface) && contrast(panel, ink) >= 4.5) {
			set('--dc-panel', panel);
		} else if (tokens['--dc-tint']) {
			tokens['--dc-panel'] = tokens['--dc-tint'];
		}

		var declarations = Object.keys(tokens).map(function (name) {
			return name + ':' + tokens[name] + ';';
		}).join('');
		if (!declarations) {
			return;
		}

		var sheet = document.createElement('style');
		sheet.id = 'downloadcenter-style-palette';
		sheet.textContent = 'html[data-dc-palette="style"] .downloadcenter-wrap,html[data-dc-palette="style"] .downloadcenter-lightbox{' + declarations + '}';
		document.head.appendChild(sheet);
		document.documentElement.setAttribute('data-dc-palette', 'style');
	}

	function initPalette() {
		detectScheme();
		applyStylePalette();
	}

	initPalette();
	document.addEventListener('DOMContentLoaded', initPalette);

	function activateTab(tabName, focusTab) {
		var tabs = document.querySelectorAll('[data-downloadcenter-tab]');
		var panels = document.querySelectorAll('[data-downloadcenter-panel]');

		tabs.forEach(function (tab) {
			var isActive = tab.getAttribute('data-downloadcenter-tab') === tabName;
			tab.classList.toggle('is-active', isActive);
			tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
			tab.setAttribute('tabindex', isActive ? '0' : '-1');
			if (isActive && focusTab) {
				tab.focus();
			}
		});

		panels.forEach(function (panel) {
			var isActive = panel.getAttribute('data-downloadcenter-panel') === tabName;
			panel.classList.toggle('is-active', isActive);
			panel.hidden = !isActive;
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var tabs = document.querySelectorAll('[data-downloadcenter-tab]');

		if (tabs.length) {
			var activeTab = document.querySelector('[data-downloadcenter-tab].is-active') || tabs[0];
			activateTab(activeTab.getAttribute('data-downloadcenter-tab'), false);
		}

		tabs.forEach(function (tab, index) {
			tab.addEventListener('click', function () {
				activateTab(tab.getAttribute('data-downloadcenter-tab'), false);
			});

			tab.addEventListener('keydown', function (event) {
				var targetIndex;
				if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
					targetIndex = (index + 1) % tabs.length;
				} else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
					targetIndex = (index - 1 + tabs.length) % tabs.length;
				} else if (event.key === 'Home') {
					targetIndex = 0;
				} else if (event.key === 'End') {
					targetIndex = tabs.length - 1;
				}

				if (typeof targetIndex !== 'undefined') {
					event.preventDefault();
					activateTab(tabs[targetIndex].getAttribute('data-downloadcenter-tab'), true);
				}
			});
		});
	});
}());


(function () {
	function insertAtCursor(textarea, before, after) {
		if (!textarea) {
			return;
		}
		var start = textarea.selectionStart || 0;
		var end = textarea.selectionEnd || 0;
		var value = textarea.value;
		var selected = value.substring(start, end);
		textarea.value = value.substring(0, start) + before + selected + after + value.substring(end);
		textarea.focus();
		var cursor = start + before.length + selected.length + after.length;
		textarea.setSelectionRange(cursor, cursor);
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-downloadcenter-bbcode-toolbar] button[data-bbcode]');
		if (!button) {
			return;
		}
		event.preventDefault();
		var toolbar = button.closest('[data-downloadcenter-bbcode-toolbar]');
		var textarea = toolbar ? toolbar.parentNode.querySelector('textarea') : null;
		var tag = button.getAttribute('data-bbcode');
		var map = {
			b: ['[b]', '[/b]'],
			i: ['[i]', '[/i]'],
			u: ['[u]', '[/u]'],
			s: ['[s]', '[/s]'],
			quote: ['[quote]', '[/quote]'],
			code: ['[code]', '[/code]'],
			url: ['[url]', '[/url]'],
			list: ['[list]\n[*]', '\n[/list]']
		};
		if (map[tag]) {
			insertAtCursor(textarea, map[tag][0], map[tag][1]);
		}
	});

	document.addEventListener('change', function (event) {
		var select = event.target && event.target.matches('[data-bbcode-size]') ? event.target : null;
		if (!select || !select.value) {
			return;
		}
		var toolbar = select.closest('[data-downloadcenter-bbcode-toolbar]');
		var textarea = toolbar ? toolbar.parentNode.querySelector('textarea') : null;
		insertAtCursor(textarea, '[size=' + select.value + ']', '[/size]');
		select.value = '';
	});

	document.addEventListener('change', function (event) {
		var input = event.target && event.target.matches('[data-bbcode-color]') ? event.target : null;
		if (!input || !input.value) {
			return;
		}
		var toolbar = input.closest('[data-downloadcenter-bbcode-toolbar]');
		var textarea = toolbar ? toolbar.parentNode.querySelector('textarea') : null;
		insertAtCursor(textarea, '[color=' + input.value + ']', '[/color]');
	});

	function formatSize(bytes) {
		bytes = Number(bytes) || 0;
		if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
		if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
		if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
		return bytes + ' B';
	}

	document.addEventListener('change', function (event) {
		if (!event.target || event.target.type !== 'file') {
			return;
		}
		var preview = event.target.parentNode.querySelector('[data-downloadcenter-file-size-preview]');
		if (!preview) {
			return;
		}
		if (event.target.files && event.target.files.length) {
			preview.textContent = preview.textContent.replace(/:.*/, '') + ': ' + formatSize(event.target.files[0].size);
		}
	});
})();


(function () {
	'use strict';

	function ensureLightbox(closeLabel) {
		var box = document.querySelector('.downloadcenter-lightbox');
		if (box) {
			return box;
		}
		box = document.createElement('div');
		box.className = 'downloadcenter-lightbox';
		box.innerHTML = '<button type="button" class="downloadcenter-lightbox-close" aria-label="' + closeLabel.replace(/"/g, '&quot;') + '">×</button><div class="downloadcenter-lightbox-inner" role="dialog" aria-modal="true"><img src="" alt=""><div class="downloadcenter-lightbox-caption"></div></div>';
		document.body.appendChild(box);
		return box;
	}

	function closeLightbox() {
		var box = document.querySelector('.downloadcenter-lightbox');
		if (box) {
			box.classList.remove('is-open');
			box.querySelector('img').setAttribute('src', '');
		}
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-downloadcenter-lightbox="screenshot"]');
		if (link) {
			event.preventDefault();
			var box = ensureLightbox(link.getAttribute('data-close-label') || 'Close');
			var img = box.querySelector('img');
			var caption = box.querySelector('.downloadcenter-lightbox-caption');
			img.setAttribute('src', link.getAttribute('href'));
			img.setAttribute('alt', link.getAttribute('data-caption') || '');
			caption.textContent = link.getAttribute('data-caption') || '';
			box.classList.add('is-open');
			return;
		}

		if (event.target.closest('.downloadcenter-lightbox-close') || event.target.classList.contains('downloadcenter-lightbox')) {
			closeLightbox();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLightbox();
		}
	});
}());

(function () {
	'use strict';

	function updateSourcePanel(select) {
		var panel = select ? select.closest('[data-downloadcenter-source-panel]') : null;
		if (!panel) {
			return;
		}
		var value = select.value || 'external';
		panel.querySelectorAll('[data-downloadcenter-source-card]').forEach(function (card) {
			card.classList.toggle('is-active', card.getAttribute('data-downloadcenter-source-card') === value);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-downloadcenter-source-select]').forEach(updateSourcePanel);
	});

	document.addEventListener('change', function (event) {
		if (event.target && event.target.matches('[data-downloadcenter-source-select]')) {
			updateSourcePanel(event.target);
		}
	});
}());
