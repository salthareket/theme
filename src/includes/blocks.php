<?php

if(is_admin()){

    /**
     * [ADMIN] Blok Kategorisi Ekleme
     */
    function wp_block_category_logic( $categories, $post ) {
        if ( ! is_admin() ) return $categories;

        // Header template edit sayfasında headerblocks önce gelsin
        $is_header_template = false;
        if ( $post && get_post_type( $post ) === 'template' ) {
            $terms = wp_get_post_terms( $post->ID, 'template-types', [ 'fields' => 'slugs' ] );
            $term  = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : ( $_GET['template-types'] ?? '' );
            $is_header_template = ( $term === 'header' );
        }

        $main_categories = [
            [
                'slug'  => 'saltblocks',
                'title' => 'Salt Blocks',
                'icon'  => 'dashicons-admin-generic'
            ],
            [
                'slug'  => 'headerblocks',
                'title' => 'Header Blocks',
                'icon'  => 'dashicons-align-center'
            ]
        ];

        // Header template'de headerblocks önce, saltblocks sonra
        if ( $is_header_template ) {
            $main_categories = array_reverse( $main_categories );
        }

        $my_custom_categories = array_merge( $main_categories, Data::get('block_categories') ?? [] );
        return array_merge( $my_custom_categories, $categories );
    }
    add_filter( 'block_categories_all', 'wp_block_category_logic', 10, 2 );

    /**
     * [ADMIN] Pattern Kategorisi Kaydı
     */
    function wp_block_pattern_categories_init() {
        if ( ! is_admin() || ! function_exists( 'register_block_pattern_category' ) ) {
            return;
        }

        $site_name     = get_bloginfo( 'name' );
        $kategori_slug = sanitize_title( $site_name );

        // register_block_pattern_category idempotent — zaten varsa sessizce geçer
        register_block_pattern_category(
            $kategori_slug,
            [
                'label'       => $site_name,
                'description' => 'Patterns for ' . $site_name . ' web site.',
            ]
        );
    }
    add_action( 'init', 'wp_block_pattern_categories_init' );

    /**
     * [ADMIN] Pattern Kaydedilirken Otomatik Kategori Atama
     */
    function wp_block_pattern_on_save_logic( $post_id, $post, $update ) {
        // Sadece admin panelinde ve 'wp_block' tipinde çalış
        if ( ! is_admin() || $post->post_type !== 'wp_block' ) {
            return;
        }

        $tax = 'wp_pattern_category';
        $post_categories = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'ids' ] );

        if ( empty( $post_categories ) ) {
            $site_name = get_bloginfo( 'name' );
            $kategori_slug = sanitize_title( $site_name );
            
            if ( get_term_by( 'slug', $kategori_slug, $tax ) ) {
                wp_set_object_terms( $post_id, $kategori_slug, $tax );
            }
        }
    }
    add_action( 'save_post', 'wp_block_pattern_on_save_logic', 10, 3 );

    function wp_block_editor_width() {
        echo '<style>
            /* Editor max width */
            @media (min-width: 1200px) {
                .wp-block,
                .wp-block .container-xxl,
                .wp-block .container {
                    max-width: 1140px !important;
                }
            }
            .wp-block[data-align="full"] { max-width: none !important; }

            /* Column block 100% genişlik */
            .wp-block[data-type="acf/column"]:not(.has-blocks) .is-preview,
            .wp-block[data-type="acf/column"]:not(.has-blocks) .acf-innerblocks-container {
                width: 100% !important;
            }

            /* Column Widths: varsayilan gizli, sadece row_cols=false oldugunda gosterilir */
            .acf-block-form-modal .acf-field-salt-single-col-widths {
                display: none !important;
            }
            .acf-block-form-modal.salt-show-col-widths .acf-field-salt-single-col-widths {
                display: block !important;
            }
            .wp-block[data-type="acf/columns"] .is-preview.block-columns {
                display: block !important;
                flex-direction: unset !important;
                justify-content: unset !important;
                align-items: unset !important;
            }

            /* acf/column wp-block margin sifirla - editor-styles-wrapper override */
            .wp-block[data-type="acf/columns"] .acf-block-preview .wp-block,
            .wp-block[data-type="acf/columns"] .acf-innerblocks-container .wp-block,
            .wp-block-acf-column .wp-block,
            .wp-block-acf-column > * {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            /* column row inner blocks margin sifirla */
            .wp-block[data-type="acf/columns"] .acf-innerblocks-container.row > .wp-block {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            /* column innerblocks container full width */
            .block-column .acf-innerblocks-container,
            .wp-block-acf-column .acf-innerblocks-container {
                width: 100% !important;
            }

            /* Admin preview: col-* class JS ile wrapper a kopyalanıyor.
               Bootstrap media query adminde calismiyor.
               col-md-6 gibi classlar, wrappera kopyalaninca genislik dogru olur. */
            .block-salt-theme.is-preview.block-columns .acf-innerblocks-container.row > [data-type="acf/column"] {
                min-width: 0;
            }
        </style>';
    }
    add_action('admin_head', 'wp_block_editor_width');

    /**
     * ACF Blocks V3 — Expanded Editor (offcanvas) içinde modal overflow fix.
     * Modal, components-modal__screen-overlay.acf-expanded-editor-panel-overlay
     * içine render ediliyor. Bu div overflow:hidden olduğu için modal kırpılıyor.
     */
    function salt_acf_expanded_editor_overflow_fix() {
        echo '<style>
            /* ACF Expanded Editor panel overlay — modal taşabilsin */
            .acf-expanded-editor-panel-overlay,
            .components-modal__screen-overlay.acf-expanded-editor-panel-overlay {
                overflow: visible !important;
            }

            /* Modal frame tam ekran olarak panel overlay üstüne çıksın */
            .acf-expanded-editor-panel-overlay .components-modal__frame,
            .acf-expanded-editor-panel-overlay .acf-block-form-modal,
            .components-modal__frame.is-full-screen.acf-block-form-modal {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                z-index: 999999 !important;
                overflow: auto !important;
            }

            /* Modal content scroll edilebilsin */
            .acf-expanded-editor-panel-overlay .components-modal__content,
            .acf-expanded-editor-panel-overlay .components-modal__content.is-scrollable {
                overflow-y: auto !important;
                max-height: 100vh !important;
            }
        </style>';
    }
    add_action( 'admin_head', 'salt_acf_expanded_editor_overflow_fix' );

    /**
     * ACF Blocks V3 — Expanded Editor'a "İptal" butonu ekler.
     * Modal açılınca block'un tüm attributes'ını snapshot'a alır (image, repeater dahil her şey).
     * İptal'e basılınca wp.data ile block attributes restore edilir, preview güncellenir.
     *
     * Devre dışı bırakmak için: define('SALT_ACF_EDITOR_CANCELABLE', false);
     */
    if ( ! defined( 'SALT_ACF_EDITOR_CANCELABLE' ) ) {
        define( 'SALT_ACF_EDITOR_CANCELABLE', true );
    }

    add_action( 'enqueue_block_editor_assets', 'salt_block_editor_scripts' );
    function salt_block_editor_scripts() {

        // Header template edit sayfasında headerblocks kategorisini en üste taşı
        $is_header_template = false;
        $post_id = absint( $_GET['post'] ?? 0 );
        if ( $post_id && get_post_type( $post_id ) === 'template' ) {
            $terms = wp_get_post_terms( $post_id, 'template-types', [ 'fields' => 'slugs' ] );
            $term  = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : ( $_GET['template-types'] ?? '' );
            $is_header_template = ( $term === 'header' );
        } elseif ( isset( $_GET['template-types'] ) && $_GET['template-types'] === 'header' ) {
            $is_header_template = true;
        }

        if ( $is_header_template ) {
            wp_add_inline_script( 'wp-blocks', '
(function() {
    var unsub = wp.data.subscribe(function() {
        var cats = wp.data.select("core/blocks").getCategories();
        if (!cats || !cats.length) return;
        var headerIdx = cats.findIndex(function(c) { return c.slug === "headerblocks"; });
        if (headerIdx <= 0) { unsub(); return; }
        var reordered = cats.slice();
        var header = reordered.splice(headerIdx, 1)[0];
        reordered.unshift(header);
        wp.data.dispatch("core/blocks").setCategories(reordered);
        unsub();
    });
})();
            ' );
        }
        // iframe icinde de calisan editor CSS — column margin fix
        wp_add_inline_style( 'wp-block-editor', '
            .wp-block[data-type="acf/columns"] .acf-block-preview .wp-block,
            .wp-block[data-type="acf/columns"] .acf-innerblocks-container .wp-block,
            .wp-block-acf-column .wp-block,
            .wp-block-acf-column > * {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .wp-block[data-type="acf/columns"] .acf-innerblocks-container.row > .wp-block {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            /* Block preview icindeki linklerin navigate etmesini engelle.
               JS blockEditorLinks() ile de engelleniyor ama CSS fallback olarak da koy. */
            .is-preview a[href]:not([href="#"]):not([href^="javascript:"]):not([data-bs-toggle]):not([data-bs-target]) {
                pointer-events: none !important;
                cursor: default !important;
            }
        ' );        $js = <<<'SALTJS'
(function() {
    'use strict';

    // ── has-blocks class ───────────────────────────────────────────────────
    function updateColumnHasBlocks(doc) {
        doc = doc || document;
        doc.querySelectorAll('[data-type="acf/column"]').forEach(function(el) {
            var container = el.querySelector('.acf-innerblocks-container');
            if (!container) return;
            var real = container.querySelectorAll('.wp-block:not(.block-list-appender):not(.block-editor-default-block-appender)');
            el.classList.toggle('has-blocks', real.length > 0);
        });
    }

    // ── Column ekleme ───────────────────────────────────────────────────────
    function saltAddColumn(btn, wpRef) {
        var select   = wpRef.data.select('core/block-editor');
        var dispatch = wpRef.data.dispatch('core/block-editor');

        // iframe'de veya parent'ta [data-type="acf/columns"] ara
        var colsWrapper = btn.closest('[data-type="acf/columns"]');

        // iframe'de bulunamazsa parent document'te ara
        if (!colsWrapper) {
            try {
                var parentDoc = window.parent ? window.parent.document : null;
                if (parentDoc) {
                    // Secili block'u bul
                    colsWrapper = parentDoc.querySelector('[data-type="acf/columns"].is-selected')
                               || parentDoc.querySelector('[data-type="acf/columns"]:focus-within')
                               || parentDoc.querySelector('[data-type="acf/columns"]');
                }
            } catch(err) {}
        }

        var clientId = colsWrapper ? colsWrapper.getAttribute('data-block') : null;

        // wp.data store'dan bul
        if (!clientId) {
            (function find(blocks) {
                for (var i = 0; i < blocks.length; i++) {
                    if (blocks[i].name === 'acf/columns') { clientId = blocks[i].clientId; return; }
                    if (blocks[i].innerBlocks && blocks[i].innerBlocks.length) find(blocks[i].innerBlocks);
                }
            })(select.getBlocks());
        }

        console.log('[salt-columns] clientId:', clientId);
        if (!clientId) { console.warn('[salt-columns] clientId bulunamadi'); return; }

        var newBlock   = wpRef.blocks.createBlock('acf/column', {});
        var innerOrder = select.getBlockOrder(clientId);
        dispatch.insertBlock(newBlock, innerOrder.length, clientId, false);
    }

    // ── Col wrapper class fix — block-column class'larini Gutenberg wrapper'a kopyala ──
    function saltFixColumnWrappers(doc) {
        doc = doc || document;
        var rows = doc.querySelectorAll(
            '.block-salt-theme.is-preview.block-columns .row'
        );
        rows.forEach(function(rowDiv) {
            Array.from(rowDiv.children).filter(function(el) {
                return el.dataset && el.dataset.type === 'acf/column';
            }).forEach(function(wrapper) {
                var colDiv = wrapper.querySelector('.block-salt-theme.is-preview.block-column');
                if (!colDiv) return;
                Array.from(colDiv.classList).filter(function(c) {
                    return /^col(-[a-z]+)?(-\d+)?$/.test(c);
                }).forEach(function(c) {
                    if (!wrapper.classList.contains(c)) wrapper.classList.add(c);
                });
            });
        });
    }

    // ── Bir document'e click listener ekle ─────────────────────────────────
    function bindClickHandler(doc, wpRef) {
        doc.addEventListener('click', function(e) {
            var btn = e.target.closest('.salt-add-column-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            saltAddColumn(btn, wpRef);
        }, true);
    }

    // ── Editor iframe içindeki linklerin navigate etmesini engelle ──────────
    function blockEditorLinks(doc) {
        if (!doc || doc._saltLinksBlocked) return;
        doc._saltLinksBlocked = true;
        doc.addEventListener('click', function(e) {
            var link = e.target.closest('a[href]');
            if (!link) return;
            var href = link.getAttribute('href');
            // Sadece gerçek navigasyon linkleri — # ve javascript: hariç
            if (!href || href === '#' || href.startsWith('javascript:')) return;
            // Fragment (#id) linkleri serbest bırak — accordion/collapse için
            if (href.startsWith('#')) return;
            // data-bs-toggle olan bootstrap trigger'lara dokunma
            if (link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-bs-target')) return;
            e.preventDefault();
            e.stopPropagation();
        }, true);
    }

    // ── Ana frame ──────────────────────────────────────────────────────────
    function saltInitMainFrame() {
        if (!window.wp || !window.wp.data || !window.wp.blocks) return;
        var target = document.body || document.documentElement;
        if (!target) { setTimeout(saltInitMainFrame, 50); return; }
        bindClickHandler(document, window.wp);
        blockEditorLinks(document);
        try {
            new MutationObserver(function() {
                updateColumnHasBlocks(document);
                saltFixColumnWrappers(document);
            }).observe(target, { childList: true, subtree: true });
        } catch(e) {}
        updateColumnHasBlocks(document);
        saltFixColumnWrappers(document);
    }
    document.addEventListener('DOMContentLoaded', saltInitMainFrame);
    if (document.readyState !== 'loading') saltInitMainFrame();

    // ── Editor iframe ───────────────────────────────────────────────────────
    // Gutenberg iframe'i gecikmeyle olusturuluyor, MutationObserver ile bekle
    function tryBindIframe() {
        var iframe = document.querySelector('iframe[name="editor-canvas"]');
        if (!iframe) iframe = document.querySelector('iframe.editor-canvas__iframe');
        if (!iframe || !iframe.contentDocument) return;

        var iDoc = iframe.contentDocument;
        var iWin = iframe.contentWindow;

        // wp objesi iframe'de yoksa parent'tan al
        var wpRef = (iWin.wp && iWin.wp.data && iWin.wp.blocks) ? iWin.wp : window.wp;
        if (!wpRef || !wpRef.data || !wpRef.blocks) return;

        if (iDoc._saltBound) {
            // Zaten bind edildi ama class'lari yenile
            saltFixColumnWrappers(iDoc);
            return;
        }
        iDoc._saltBound = true;

        bindClickHandler(iDoc, wpRef);
        blockEditorLinks(iDoc);

        try {
            new MutationObserver(function() {
                updateColumnHasBlocks(iDoc);
                saltFixColumnWrappers(iDoc);
            }).observe(iDoc.body || iDoc.documentElement, { childList: true, subtree: true });
        } catch(e) {}
        updateColumnHasBlocks(iDoc);
        saltFixColumnWrappers(iDoc);
        setTimeout(function() { saltFixColumnWrappers(iDoc); }, 300);

        console.log('[salt-columns] iframe bind edildi');
    }

    // iframe DOM'a eklenince baglaniyor
    try {
        new MutationObserver(function() {
            tryBindIframe();
        }).observe(document.body || document.documentElement, { childList: true, subtree: true });
    } catch(e) {
        document.addEventListener('DOMContentLoaded', function() {
            new MutationObserver(function() { tryBindIframe(); })
                .observe(document.body, { childList: true, subtree: true });
        });
    }

    // Sayfa yuklendikten sonra da dene
    document.addEventListener('DOMContentLoaded', function() { tryBindIframe(); });
    setTimeout(tryBindIframe, 500);
    setTimeout(tryBindIframe, 1500);
    setTimeout(tryBindIframe, 3000);

})();
SALTJS;
        wp_add_inline_script( 'wp-blocks', $js );
    }

    if ( SALT_ACF_EDITOR_CANCELABLE ) {
        add_action( 'admin_head', 'salt_acf_expanded_editor_cancel_button' );
    }

    /**
     * ACF Column block — row_cols aktifse col_widths field'ını gizle.
     * usesContext çalışmadığı için acf/pre_render_block ile context inject ediyoruz.
     */
    /**
     * render_block filter — columns block HTML'inde row_cols aktifse
     * column div'lerindeki col-* breakpoint class'larını 'col' ile değiştir.
     * Frontend'de inner block'lar parent'tan önce render edildiği için
     * global değişken yaklaşımı çalışmıyor — post-process gerekiyor.
     */
    add_filter( 'render_block', function( $block_content, $block ) {
        if ( $block['blockName'] !== 'acf/columns' ) return $block_content;

        $attrs = $block['attrs'] ?? [];
        $data  = $attrs['data'] ?? [];
        $row_cols = ! empty( $data['row_cols'] );

        if ( ! $row_cols ) return $block_content;

        // column div'lerindeki col-* class'larını 'col' ile değiştir
        // Pattern: class="col-X col-bp-X ... block-column ..."
        $block_content = preg_replace_callback(
            '/(<div[^>]+class=")([^"]*block-column[^"]*)(")/i',
            function( $matches ) {
                $classes = $matches[2];
                // col-N ve col-bp-N class'larını kaldır
                $classes = preg_replace( '/\bcol-(?:\w+-)?(?:\d+|auto)\b/', '', $classes );
                // Fazla boşlukları temizle
                $classes = preg_replace( '/\s+/', ' ', trim( $classes ) );
                // Başa 'col' ekle
                $classes = 'col ' . $classes;
                return $matches[1] . $classes . $matches[3];
            },
            $block_content
        );

        return $block_content;
    }, 10, 2 );

    /**
     * acf/column block - row_cols aktifse col_widths field'ini gizle
     * acf/prepare_field filter her field render edilmeden once calisir
     */
    add_filter( 'acf/prepare_field/key=field_salt_single_col_widths', function( $field ) {
        // Global fallback — columns render.php set ediyor
        global $salt_columns_row_cols_active;
        if ( ! empty( $salt_columns_row_cols_active ) ) {
            return false;
        }

        // AJAX block fetch sirasinda $_POST'tan context oku
        if ( defined('DOING_AJAX') && DOING_AJAX ) {
            $query = isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : '';
            if ( is_string( $query ) ) {
                $decoded = json_decode( $query, true );
                if ( is_array( $decoded ) ) {
                    $query = $decoded;
                }
            }
            if ( is_array( $query ) ) {
                $context = $query['context'] ?? [];
                if ( is_string( $context ) ) {
                    $context = json_decode( $context, true ) ?: [];
                }
                $row_cols_raw = $context['acf/columns/row_cols'] ?? null;
                if ( $row_cols_raw ) {
                    if ( is_string( $row_cols_raw ) ) {
                        $decoded2 = json_decode( $row_cols_raw, true );
                        $row_cols_active = ! empty( $decoded2['row_cols'] );
                    } elseif ( is_array( $row_cols_raw ) ) {
                        $row_cols_active = ! empty( $row_cols_raw['row_cols'] );
                    } else {
                        $row_cols_active = (bool) $row_cols_raw;
                    }
                    if ( $row_cols_active ) return false;
                }
            }
        }

        return $field;
    } );

    function salt_acf_expanded_editor_cancel_button() {
        ?>
        <style>
            .salt-acf-cancel-btn {
                z-index: 1000000;
                background: transparent;
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: 6px 14px;
                font-size: 13px;
                cursor: pointer;
                color: #555;
                line-height: 1.4;
                margin-right: 8px;
            }
            .salt-acf-cancel-btn:hover {
                background: #f0f0f0;
                border-color: #999;
                color: #333;
            }
        </style>
        <script>
        (function($) {
            // clientId → snapshot map
            var _snapshots = {};

            function getClientIdFromModal($modal) {
                // ACF modal'ında data-client-id veya içindeki form'dan clientId bul
                var clientId = $modal.attr('data-client-id') || $modal.data('clientId');
                if (clientId) return clientId;

                // ACF form'undaki hidden input'tan bul
                var $clientInput = $modal.find('input[name="clientId"], input[name="client_id"]');
                if ($clientInput.length) return $clientInput.val();

                // wp.data'dan aktif seçili block'u al
                if (window.wp && wp.data) {
                    var selected = wp.data.select('core/block-editor').getSelectedBlockClientId();
                    if (selected) return selected;
                    // Expanded editor açıkken seçili olmayabilir — editing block'u dene
                    var editing = wp.data.select('core/block-editor').getBlockEditingMode
                        ? null
                        : null;
                    // Son çare: tüm block'ları tara, ACF block'u bul
                    var blocks = wp.data.select('core/block-editor').getBlocks();
                    // Recursive olarak ACF block'u bul
                    function findAcfBlock(blocks) {
                        for (var i = 0; i < blocks.length; i++) {
                            var b = blocks[i];
                            if (b.name && b.name.indexOf('acf/') === 0) return b.clientId;
                            if (b.innerBlocks && b.innerBlocks.length) {
                                var found = findAcfBlock(b.innerBlocks);
                                if (found) return found;
                            }
                        }
                        return null;
                    }
                    return findAcfBlock(blocks);
                }
                return null;
            }

            function takeSnapshot(clientId) {
                if (!clientId || !window.wp || !wp.data) return;
                var attrs = wp.data.select('core/block-editor').getBlockAttributes(clientId);
                if (attrs) {
                    // Deep clone
                    _snapshots[clientId] = JSON.parse(JSON.stringify(attrs));
                }
            }

            function restoreSnapshot(clientId) {
                if (!clientId || !_snapshots[clientId] || !window.wp || !wp.data) return;
                wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, _snapshots[clientId]);
                delete _snapshots[clientId];
            }

            function addCancelButton($modal, clientId) {
                if ($modal.find('.salt-acf-cancel-btn').length) return;
                var $header = $modal.find('.components-modal__header');
                if (!$header.length) return;

                // "Tamamlandı" butonunu bul — onun yanına ekle
                var $doneBtn = $header.find('button.is-primary, .acf-block-form-modal__done, button[type="submit"]').first();

                var $btn = $('<button type="button" class="salt-acf-cancel-btn">İptal</button>');
                $btn.on('click', function() {
                    var snapshotAttrs = _snapshots[clientId] ? JSON.parse(JSON.stringify(_snapshots[clientId])) : null;

                    if (snapshotAttrs && snapshotAttrs.data && clientId) {
                        // Form input'larını snapshot data'sından restore et
                        // ACF form input name pattern: acf-block_[clientId][field_key]
                        var prefix = 'acf-block_' + clientId;
                        
                        function restoreInputs($container, data, parentKey) {
                            $.each(data, function(key, value) {
                                var inputName = parentKey ? parentKey + '[' + key + ']' : prefix + '[' + key + ']';
                                if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
                                    restoreInputs($container, value, inputName);
                                } else {
                                    var $input = $container.find('[name="' + inputName + '"]');
                                    if ($input.length) {
                                        $input.val(value).trigger('change');
                                    }
                                }
                            });
                        }
                        
                        restoreInputs($modal, snapshotAttrs.data, null);
                        
                        // wp.data'yı da güncelle
                        if (window.wp && wp.data) {
                            wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, snapshotAttrs);
                        }
                    }

                    delete _snapshots[clientId];

                    // Done'a bas — modal kapansın
                    setTimeout(function() {
                        $doneBtn.trigger('click');
                    }, 50);
                });

                // Done butonunun hemen önüne ekle
                if ($doneBtn.length) {
                    $doneBtn.before($btn);
                } else {
                    $header.append($btn);
                }
            }

            // MutationObserver ile modal açılışını izle
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType !== 1) return;
                        var $node = $(node);
                        var $modal = $node.hasClass('acf-block-form-modal') ? $node : $node.find('.acf-block-form-modal');
                        if (!$modal.length) return;

                        // clientId'yi HEMEN al — selection henüz kaybolmadı
                        var clientId = null;
                        if (window.wp && wp.data) {
                            clientId = wp.data.select('core/block-editor').getSelectedBlockClientId();
                        }
                        if (!clientId) clientId = getClientIdFromModal($modal);

                        // Snapshot'ı HEMEN al — modal açılmadan önceki attributes
                        if (clientId) {
                            takeSnapshot(clientId);
                        }

                        // Butonu eklemek için DOM render'ı bekle
                        setTimeout(function() {
                            // clientId hâlâ yoksa tekrar dene
                            if (!clientId && window.wp && wp.data) {
                                clientId = wp.data.select('core/block-editor').getSelectedBlockClientId();
                            }
                            if (!clientId) clientId = getClientIdFromModal($modal);
                            // Snapshot yoksa şimdi al (geç de olsa)
                            if (clientId && !_snapshots[clientId]) {
                                takeSnapshot(clientId);
                            }
                            addCancelButton($modal, clientId);

                            // row_cols aktifse Column Widths field'ini gizle
                            if (clientId && window.wp && wp.data) {
                                var select = wp.data.select('core/block-editor');
                                var blockName = select.getBlockName(clientId);
                                console.log('[salt-col-widths] modal clientId:', clientId, 'blockName:', blockName);
                                if (blockName === 'acf/column') {
                                    var parentId = select.getBlockRootClientId(clientId);
                                    console.log('[salt-col-widths] parentId:', parentId);
                                    if (parentId) {
                                        var parentAttrs = select.getBlockAttributes(parentId);
                                        var rowColsActive = !!(parentAttrs && parentAttrs.data && (
                                            parentAttrs.data.row_cols == '1' ||
                                            parentAttrs.data.row_cols === true ||
                                            parentAttrs.data.field_salt_col_row_cols_switch == '1'
                                        ));
                                        console.log('[salt-col-widths] rowColsActive:', rowColsActive);
                                        if (rowColsActive) {
                                            // row_cols aktif — col widths zaten CSS ile gizli
                                        } else {
                                            // row_cols pasif — col widths goster
                                            $modal.addClass('salt-show-col-widths');
                                        }
                                    }
                                }
                            }
                        }, 150);
                    });
                });
            });

            $(document).ready(function() {
                observer.observe(document.body, { childList: true, subtree: true });

                // column block'larına has-blocks class'ı ekle/kaldır
                function updateColumnHasBlocks() {
                    document.querySelectorAll('[data-type="acf/column"]').forEach(function(el) {
                        var container = el.querySelector('.acf-innerblocks-container');
                        if (!container) return;
                        var hasBlocks = container.querySelector('.wp-block') !== null;
                        el.classList.toggle('has-blocks', hasBlocks);
                    });
                }

                var columnObserver = new MutationObserver(updateColumnHasBlocks);
                columnObserver.observe(document.body, { childList: true, subtree: true });
                updateColumnHasBlocks();
            });
        })(jQuery);
        </script>
        <?php
    }
    
}





function get_cached_blocks( $post_id ) {
    static $blocks_cache = [];

    if ( isset( $blocks_cache[ $post_id ] ) ) {
        return $blocks_cache[ $post_id ];
    }

    $content = get_post_field( 'post_content', $post_id );
    if ( empty( $content ) || ! has_blocks( $content ) ) {
        return $blocks_cache[ $post_id ] = [];
    }

    return $blocks_cache[ $post_id ] = parse_blocks( $content );
}

function get_blocks($post_id){
    return get_cached_blocks( $post_id ) ?: false;
}

function get_block( $post_id, $block_name, $render = false ) {
    $blocks = get_cached_blocks( $post_id );
    if ( ! $blocks ) return false;

    foreach ( $blocks as $block ) {
        if ( isset( $block['blockName'] ) && $block['blockName'] === $block_name ) {
            return $render ? render_block( $block ) : $block;
        }
    }

    return false;
}

function get_field_from_block( $selector, $post_id, $block_id ) {
    $blocks = get_cached_blocks( $post_id );
    if ( ! $blocks ) return false;

    foreach ( $blocks as $block ) {
        if ( isset( $block['attrs']['id'] ) && $block['attrs']['id'] === $block_id ) {
            return $block['attrs']['data'][ $selector ] ?? false;
        }
    }

    return false;
}

function get_block_from_page($block_name, $source_page_id = null, $args = []) {
    $source_page_id = $source_page_id ?: get_option('page_on_front');
    $blocks = get_cached_blocks($source_page_id); // Artık cache'den geliyor

    if ( empty($blocks) ) return '';

    foreach ($blocks as $block) {
        if ( isset($block['blockName']) && $block['blockName'] === $block_name ) {
            if ( ! empty($args) ) {
                $block['attrs']["data"] = array_merge($block['attrs']["data"] ?? [], $args);
            }
            return render_block($block);
        }
    }
    return '';
}

//plyr video player
function add_player_class_to_embed_block($block_content, $block) {
    // Sadece video ve ses bloklarında işlem yap
    if ( ! in_array($block['blockName'], ['core/embed', 'core/audio']) ) {
        return $block_content;
    }

    if ( $block['blockName'] === 'core/embed' && isset($block["attrs"]["type"]) && $block["attrs"]["type"] === "video" ) {
        return sprintf(
            '<div class="player plyr__video-embed init-me"><iframe class="video" src="%s" allowfullscreen allowtransparency allow="autoplay"></iframe></div>',
            esc_url($block["attrs"]["url"])
        );
    }

    if ( $block['blockName'] === 'core/audio' ) {
        return str_replace('<audio', '<audio class="player init-me"', $block_content);
    }

    return $block_content;
}
//add_filter('render_block', 'add_player_class_to_embed_block', 10, 2);



/*
add_filter('register_block_type_args', function ($args, $name) {
    $supports = [
        "color" => [
                "gradients" => true,
                "link" => true,
                "__experimentalDefaultControls" => [
                    "background" => true,
                    "text" => true,
                    "link" => true
                ]
        ]
    ];
    if (strpos($name, 'acf/hero') === 0) {
        //$args['supports']['color'] = $supports["color"];
    }
    return $args;

}, 10, 2);*/


// ACF Blocks V3'e geçildiği için iframe hack'i kaldırıldı.
// ACF 6.6+ Expanded Editor (slide-out panel) iframe sorununu native çözüyor.

/*
// Blok kayıtlarını havada yakalayıp hepsini v3 yapıyoruz
add_filter( 'block_type_metadata_settings', function( $settings, $metadata ) {
    // Eğer blok bir ACF bloğuysa (ismini kontrol et)
    if ( isset( $metadata['name'] ) && strpos( $metadata['name'], 'acf/' ) === 0 ) {
        $settings['api_version'] = 3;
    }
    return $settings;
}, 99, 2 );

// Alternatif olarak ACF'in kendi filtresini de zorlayalım
add_filter( 'acf/register_block_type_args', function( $args ) {
    $args['apiVersion'] = 3;
    return $args;
}, 99 );
*/


// ── Header Builder: Salt Block Settings Override + Nav Item Wrap ──────────────
// header-builder icindeki salt block'lara:
// 1. block_settings.container=no, hero=false, sticky_top=false, height=auto/100%
// 2. header-tools icindeki salt block'lar nav-item wrapper alir
//
// Yontem: pre_render_block filter ile header-builder render baslayinca
// render_block_data filter'i aktiflesir. Inner block'lar render edilirken
// bu filter onlarin data'sini override eder.
// header-tools block'u render edilirken global flag set edilir,
// inner salt block'lari nav-item ile wrap edilir.
// header-builder render bitince tum filter'lar kaldirilir.

add_filter( 'pre_render_block', function ( $pre_render, array $block ) {

    if ( ( $block['blockName'] ?? '' ) !== 'acf/header-builder' ) {
        return $pre_render;
    }

    // Header-tools icine ne eklenirse eklensin block_settings override alinir.
    // acf/ prefix'li olmayan core block'lar (core/paragraph vb.) override edilmez.
    $is_acf_block = function ( string $name ): bool {
        return strpos( $name, 'acf/' ) === 0;
    };

    // 1. Block settings override filter — acf/ block'lara uygulanir
    $settings_filter = function ( array $parsed_block ) use ( $is_acf_block ): array {
        if ( ! $is_acf_block( $parsed_block['blockName'] ?? '' ) ) {
            return $parsed_block;
        }
        // header-builder kendi block'larini etkilemesin
        $header_blocks = [
            'acf/header-builder', 'acf/header-row', 'acf/header-column',
            'acf/header-tools', 'acf/header-logo',
            'acf/header-tool-cart', 'acf/header-tool-favorites',
            'acf/header-tool-search', 'acf/header-tool-search-field',
            'acf/header-tool-messages', 'acf/header-tool-notifications',
            'acf/header-tool-user-menu', 'acf/header-tool-login',
            'acf/header-tool-custom',
        ];
        if ( in_array( $parsed_block['blockName'], $header_blocks, true ) ) {
            return $parsed_block;
        }
        if ( isset( $parsed_block['attrs']['data'] ) ) {
            $parsed_block['attrs']['data']['block_settings_container']  = 'no';
            $parsed_block['attrs']['data']['block_settings_hero']       = false;
            $parsed_block['attrs']['data']['block_settings_sticky_top'] = false;
            $h = $parsed_block['attrs']['data']['block_settings_height'] ?? '';
            if ( ! in_array( $h, [ 'auto', '100%' ], true ) ) {
                $parsed_block['attrs']['data']['block_settings_height'] = 'auto';
            }
        }
        return $parsed_block;
    };

    // 2. nav-item wrap filter — header-tools icindeyken gelen her acf/ block sarilir.
    // header-tool-* kendi wrapper'larini zaten uretir, sarmalanmaz.
    $wrap_filter = function ( string $content, array $inner_block ) use ( $is_acf_block ): string {
        if ( empty( $GLOBALS['_salt_in_header_tools'] ) ) {
            return $content;
        }
        $name = $inner_block['blockName'] ?? '';
        if ( ! $is_acf_block( $name ) ) {
            return $content;
        }
        // header-tool-* block'lari zaten kendi wraper'larina sahip, sarmalama
        if ( strpos( $name, 'acf/header-tool' ) === 0 || $name === 'acf/header-tools' ) {
            return $content;
        }
        $slug = str_replace( 'acf/', '', $name );
        return '<div class="nav-item nav-' . esc_attr( $slug ) . '">' . $content . '</div>';
    };

    // 3. header-tools start tracking
    $tools_pre_filter = function ( $pre, array $b ) {
        if ( ( $b['blockName'] ?? '' ) === 'acf/header-tools' ) {
            $GLOBALS['_salt_in_header_tools'] = true;
        }
        return $pre;
    };

    // 4. header-tools end tracking
    $tools_end_filter = function ( string $content, array $b ) {
        if ( ( $b['blockName'] ?? '' ) === 'acf/header-tools' ) {
            unset( $GLOBALS['_salt_in_header_tools'] );
        }
        return $content;
    };

    add_filter( 'render_block_data', $settings_filter, 5 );
    add_filter( 'pre_render_block',  $tools_pre_filter, 6, 2 );
    add_filter( 'render_block',      $tools_end_filter, 5, 2 );
    add_filter( 'render_block',      $wrap_filter,      6, 2 );

    // 5. header-builder bitince tum filter'lari kaldir
    add_filter( 'render_block', function ( string $c, array $b ) use (
        $settings_filter, $tools_pre_filter, $tools_end_filter, $wrap_filter
    ) {
        if ( ( $b['blockName'] ?? '' ) !== 'acf/header-builder' ) return $c;
        remove_filter( 'render_block_data', $settings_filter, 5 );
        remove_filter( 'pre_render_block',  $tools_pre_filter, 6 );
        remove_filter( 'render_block',      $tools_end_filter, 5 );
        remove_filter( 'render_block',      $wrap_filter,      6 );
        unset( $GLOBALS['_salt_in_header_tools'] );
        return $c;
    }, 999, 2 );

    return $pre_render;

}, 5, 2 );
