(function () {
    'use strict';

    document.documentElement.classList.add('downloadcenter-js');

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

    function ensureLightbox() {
        var box = document.querySelector('.downloadcenter-lightbox');
        if (box) {
            return box;
        }
        box = document.createElement('div');
        box.className = 'downloadcenter-lightbox';
        box.innerHTML = '<button type="button" class="downloadcenter-lightbox-close" aria-label="Fechar">×</button><div class="downloadcenter-lightbox-inner" role="dialog" aria-modal="true"><img src="" alt=""><div class="downloadcenter-lightbox-caption"></div></div>';
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
            var box = ensureLightbox();
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
