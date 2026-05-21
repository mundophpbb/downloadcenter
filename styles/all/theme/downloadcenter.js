(function () {
    'use strict';

    document.documentElement.classList.add('downloadcenter-js');

    function activateTab(tabName) {
        var tabs = document.querySelectorAll('[data-downloadcenter-tab]');
        var panels = document.querySelectorAll('[data-downloadcenter-panel]');

        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-downloadcenter-tab') === tabName);
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-downloadcenter-panel') === tabName);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tabs = document.querySelectorAll('[data-downloadcenter-tab]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activateTab(tab.getAttribute('data-downloadcenter-tab'));
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
        box.innerHTML = '<button type="button" class="downloadcenter-lightbox-close" aria-label="Fechar">×</button><div class="downloadcenter-lightbox-inner"><img src="" alt=""><div class="downloadcenter-lightbox-caption"></div></div>';
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
