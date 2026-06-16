/**
 * SHModal — Shared lightweight modal utility
 *
 * Bootbox bağımlılığı olmadan çalışır.
 * Bootbox varsa onu kullanır, yoksa kendi overlay'ini açar.
 *
 * @version 1.0.0
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Basit içerik modal'ı:
 * SHModal.open({ title: 'Başlık', content: '<p>İçerik</p>' });
 *
 * // Kapatma callback'i ile:
 * SHModal.open({ title: 'Form', content: formHtml, onClose: function() { ... } });
 *
 * // Programatik kapatma:
 * var modal = SHModal.open({ title: '...', content: '...' });
 * modal.close();
 *
 * // Mevcut modal'ı kapat:
 * SHModal.close();
 *
 * ──────────────────────────────────────────────────────────
 */
(function (global) {
    'use strict';

    var _current = null;

    var SHModal = {

        /**
         * Modal aç.
         * @param {Object} opts
         * @param {string} opts.title
         * @param {string} opts.content   HTML string
         * @param {string} [opts.size]    'sm' | 'md' (default) | 'lg'
         * @param {Function} [opts.onClose]
         * @param {Function} [opts.onOpen]  Modal DOM'a eklendikten sonra çağrılır
         * @returns {{ close: Function, el: Element }}
         */
        open: function (opts) {
            opts = opts || {};

            // Mevcut modal'ı kapat
            if (_current) _current.close();

            // Bootbox varsa onu kullan
            if (typeof bootbox !== 'undefined') {
                return this._openBootbox(opts);
            }

            return this._openNative(opts);
        },

        /** Mevcut açık modal'ı kapat */
        close: function () {
            if (_current) {
                _current.close();
                _current = null;
            }
        },

        // ── Native overlay ───────────────────────────────────────────────────

        _openNative: function (opts) {
            var size = opts.size || 'md';
            var maxW = size === 'sm' ? '420px' : size === 'lg' ? '800px' : '560px';

            var $overlay = document.createElement('div');
            $overlay.className = 'sh-modal-overlay';
            $overlay.style.cssText = [
                'position:fixed', 'inset:0', 'background:rgba(0,0,0,.5)',
                'z-index:99999', 'display:flex', 'align-items:center',
                'justify-content:center', 'padding:16px',
            ].join(';');

            var $box = document.createElement('div');
            $box.className = 'sh-modal-box';
            $box.style.cssText = [
                'background:#fff', 'border-radius:8px', 'padding:24px',
                'max-width:' + maxW, 'width:100%', 'max-height:90vh',
                'overflow-y:auto', 'position:relative',
                'box-shadow:0 20px 60px rgba(0,0,0,.3)',
            ].join(';');

            // Header
            if (opts.title) {
                var $header = document.createElement('div');
                $header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;';
                var $title = document.createElement('h3');
                $title.style.cssText = 'margin:0;font-size:16px;font-weight:600;';
                $title.textContent = opts.title;
                var $closeBtn = this._makeCloseBtn();
                $header.appendChild($title);
                $header.appendChild($closeBtn);
                $box.appendChild($header);
            } else {
                var $closeBtn = this._makeCloseBtn();
                $closeBtn.style.cssText += 'position:absolute;top:12px;right:12px;';
                $box.appendChild($closeBtn);
            }

            // Content
            var $content = document.createElement('div');
            $content.className = 'sh-modal-content';
            $content.innerHTML = opts.content || '';
            $box.appendChild($content);

            $overlay.appendChild($box);
            document.body.appendChild($overlay);

            // Kapatma fonksiyonu
            var closed = false;
            var instance = {
                el: $overlay,
                close: function () {
                    if (closed) return;
                    closed = true;
                    if ($overlay.parentNode) $overlay.parentNode.removeChild($overlay);
                    if (typeof opts.onClose === 'function') opts.onClose();
                    if (_current === instance) _current = null;
                },
                find: function (sel) {
                    return $box.querySelector(sel);
                },
                findAll: function (sel) {
                    return $box.querySelectorAll(sel);
                },
            };

            // Close buton event
            $closeBtn.addEventListener('click', function () { instance.close(); });

            // Overlay tıklama ile kapat
            $overlay.addEventListener('click', function (e) {
                if (e.target === $overlay) instance.close();
            });

            // ESC ile kapat
            var onKeyDown = function (e) {
                if (e.key === 'Escape') {
                    instance.close();
                    document.removeEventListener('keydown', onKeyDown);
                }
            };
            document.addEventListener('keydown', onKeyDown);

            _current = instance;

            if (typeof opts.onOpen === 'function') opts.onOpen($box);

            return instance;
        },

        _makeCloseBtn: function () {
            var $btn = document.createElement('button');
            $btn.type = 'button';
            $btn.setAttribute('aria-label', 'Kapat');
            $btn.style.cssText = [
                'background:none', 'border:none', 'cursor:pointer',
                'font-size:20px', 'line-height:1', 'color:#6b7280',
                'padding:4px', 'display:flex', 'align-items:center',
            ].join(';');
            $btn.innerHTML = '&times;';
            return $btn;
        },

        // ── Bootbox ──────────────────────────────────────────────────────────

        _openBootbox: function (opts) {
            var dialog = bootbox.dialog({
                className:      'sh-modal modal-page',
                title:          opts.title ? '<div>' + opts.title + '</div>' : '',
                message:        opts.content || '<div></div>',
                closeButton:    true,
                size:           opts.size === 'lg' ? 'large' : opts.size === 'sm' ? 'small' : 'medium',
                centerVertical: true,
                animate:        false,
                backdrop:       true,
                buttons:        {},
                onEscape:       true,
            });

            var instance = {
                el: dialog[0],
                close: function () {
                    dialog.modal('hide');
                    if (typeof opts.onClose === 'function') opts.onClose();
                    if (_current === instance) _current = null;
                },
                find: function (sel) {
                    return dialog[0].querySelector(sel);
                },
                findAll: function (sel) {
                    return dialog[0].querySelectorAll(sel);
                },
            };

            dialog.on('hidden.bs.modal', function () {
                if (typeof opts.onClose === 'function') opts.onClose();
                if (_current === instance) _current = null;
            });

            _current = instance;

            if (typeof opts.onOpen === 'function') {
                opts.onOpen(dialog[0]);
            }

            return instance;
        },
    };

    global.SHModal = SHModal;

})(window);
