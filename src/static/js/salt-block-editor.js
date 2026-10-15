(function() {
    'use strict';

    // ── has-blocks class güncel tut ──────────────────────────────────────────
    function updateColumnHasBlocks() {
        document.querySelectorAll('[data-type="acf/column"]').forEach(function(el) {
            var container = el.querySelector('.acf-innerblocks-container');
            if (!container) return;
            var realBlocks = container.querySelectorAll('.wp-block:not(.block-list-appender):not(.block-editor-default-block-appender)');
            el.classList.toggle('has-blocks', realBlocks.length > 0);
        });
    }
    var columnObserver = new MutationObserver(updateColumnHasBlocks);
    document.addEventListener('DOMContentLoaded', function() {
        columnObserver.observe(document.body, { childList: true, subtree: true });
        updateColumnHasBlocks();
    });

    // ── Kolon Ekle butonu ────────────────────────────────────────────────────
    // capture:true ile Gutenberg'den ÖNCE yakala
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.salt-add-column-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        if (!window.wp || !wp.blocks || !wp.data) return;

        var select   = wp.data.select('core/block-editor');
        var dispatch = wp.data.dispatch('core/block-editor');

        // Gutenberg [data-type="acf/columns"] wrapper'ina data-block="clientId" yazar
        var colsWrapper = btn.closest('[data-type="acf/columns"]');
        var clientId = colsWrapper ? colsWrapper.getAttribute('data-block') : null;

        console.log('[salt-columns] colsWrapper:', colsWrapper, 'clientId:', clientId);

        // Fallback: wp.data'dan ilk acf/columns block'unu bul
        if (!clientId) {
            function findColumnsBlock(blocks) {
                for (var i = 0; i < blocks.length; i++) {
                    if (blocks[i].name === 'acf/columns') return blocks[i].clientId;
                    if (blocks[i].innerBlocks && blocks[i].innerBlocks.length) {
                        var found = findColumnsBlock(blocks[i].innerBlocks);
                        if (found) return found;
                    }
                }
                return null;
            }
            clientId = findColumnsBlock(select.getBlocks());
            console.log('[salt-columns] clientId from store:', clientId);
        }

        if (!clientId) {
            console.warn('[salt-columns] clientId bulunamadi');
            return;
        }

        var newBlock   = wp.blocks.createBlock('acf/column', {});
        var innerOrder = select.getBlockOrder(clientId);
        dispatch.insertBlock(newBlock, innerOrder.length, clientId, false);

    }, true); // capture phase - Gutenberg'den once yakala

})();
