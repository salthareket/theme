/**
 * SHModal — Shared Modal Utility
 *
 * Bootbox varsa onu kullanır, yoksa native overlay ile basit modal açar.
 * Tüm app'lerde ortak kullanılabilir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-13 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Bootbox varsa:
 * SHModal.open({
 *   title: 'Download',
 *   content: '<form>...</form>',
 *   size: 'md',
 *   onOpen: function(modal) { console.log('opened', modal); },
 *   onClose: function() { console.log('closed'); }
 * });
 *
 * // Bootbox yoksa native overlay:
 * SHModal.open({
 *   title: 'Download',
 *   content: '<form>...</form>',
 *   onClose: function() { console.log('closed'); }
 * });
 *
 * ──────────────────────────────────────────────────────────
 */

(function (window) {
  'use strict';

  var SHModal = {

    /**
     * Modal aç.
     *
     * @param {Object} options
     * @param {string} options.title       Modal başlığı
     * @param {string} options.content     Modal içeriği (HTML)
     * @param {string} options.size        'sm'|'md'|'lg' (sadece bootbox)
     * @param {function} options.onOpen    Modal açıldığında çağrılır (modal element ile)
     * @param {function} options.onClose   Modal kapandığında çağrılır
     * @return {Object}  Modal wrapper object { element, close() }
     */
    open: function (options) {
      options = options || {};

      var title   = options.title   || '';
      var content = options.content || '';
      var size    = options.size    || 'md';
      var onOpen  = options.onOpen  || null;
      var onClose = options.onClose || null;

      // Bootbox varsa kullan
      if (typeof bootbox !== 'undefined') {
        return this._openBootbox(title, content, size, onOpen, onClose);
      }

      // Native overlay
      return this._openNative(title, content, onOpen, onClose);
    },

    /**
     * Bootbox ile modal aç.
     */
    _openBootbox: function (title, content, size, onOpen, onClose) {
      var dialog = bootbox.dialog({
        className: 'modal-page modal-form',
        title: '<div>' + title + '</div>',
        message: '<div>' + content + '</div>',
        closeButton: true,
        size: size,
        centerVertical: true,
        animate: false,
        backdrop: true,
        buttons: {}
      });

      // Close button styling
      dialog.find('.bootbox-close-button').addClass('btn-close').empty();

      // onOpen callback
      if (onOpen) {
        setTimeout(function () { onOpen(dialog); }, 50);
      }

      // onClose callback
      if (onClose) {
        dialog.on('hidden.bs.modal', onClose);
      }

      return {
        element: dialog,
        close: function () {
          dialog.modal('hide');
        }
      };
    },

    /**
     * Native overlay ile modal aç.
     */
    _openNative: function (title, content, onOpen, onClose) {
      var overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;';

      var box = document.createElement('div');
      box.style.cssText = 'background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;position:relative;';

      var closeBtn = document.createElement('button');
      closeBtn.innerHTML = '&times;';
      closeBtn.style.cssText = 'position:absolute;top:12px;right:12px;background:none;border:none;font-size:20px;cursor:pointer;line-height:1;';

      if (title) {
        var titleEl = document.createElement('h3');
        titleEl.style.cssText = 'margin:0 0 16px;';
        titleEl.innerHTML = title;
        box.appendChild(titleEl);
      }

      var contentEl = document.createElement('div');
      contentEl.innerHTML = content;
      box.appendChild(contentEl);
      box.appendChild(closeBtn);

      overlay.appendChild(box);
      document.body.appendChild(overlay);

      // Close handlers
      var closeModal = function () {
        if (overlay.parentNode) {
          overlay.parentNode.removeChild(overlay);
        }
        if (onClose) onClose();
      };

      closeBtn.addEventListener('click', closeModal);
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
      });

      // onOpen callback
      if (onOpen) {
        setTimeout(function () {
          onOpen({
            find: function (sel) {
              return window.jQuery ? window.jQuery(box).find(sel) : box.querySelectorAll(sel);
            },
            modal: function (cmd) {
              if (cmd === 'hide') closeModal();
            }
          });
        }, 50);
      }

      return {
        element: overlay,
        close: closeModal
      };
    }

  };

  // Global export
  window.SHModal = SHModal;

})(window);
