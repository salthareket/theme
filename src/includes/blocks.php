<?php

if(is_admin()){

    /**
     * [ADMIN] Blok Kategorisi Ekleme
     */
    function wp_block_category_logic( $categories, $post ) {
        if ( ! is_admin() ) return $categories;

        $main_category = [
            'slug'  => 'saltblocks',
            'title' => 'Salt Blocks',
            'icon'  => 'dashicons-admin-generic'
        ];

        // Önce kendi kategorilerini birleştir
        // $main_category'i [ ] içine alıyoruz ki tek bir eleman olarak eklensin
        $my_custom_categories = array_merge( [ $main_category ], Data::get('block_categories') ?? [] );

        // Bunları mevcut kategorilerin en başına ekle
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
            /* Standart CSS formatına çekildi */
            @media (min-width: 1200px) {
                .wp-block, 
                .wp-block .container-xxl, 
                .wp-block .container { 
                    max-width: 1140px !important; 
                }
            }
            .wp-block[data-align="full"] { max-width: none !important; }

            /* ── Column block editor genişlik fix ── */
            /* column block içindeki is-preview ve acf-innerblocks-container 100% genişlik alsın */
            .wp-block[data-type="acf/column"]:not(.has-blocks) .is-preview,
            .wp-block[data-type="acf/column"]:not(.has-blocks) .acf-innerblocks-container {
                width: 100% !important;
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
        define( 'SALT_ACF_EDITOR_CANCELABLE', false );
    }

    //add_action( 'enqueue_block_editor_assets', 'salt_block_editor_scripts' );
    function salt_block_editor_scripts() {
        wp_add_inline_script( 'wp-blocks', '
            (function() {
                function updateColumnHasBlocks() {
                    document.querySelectorAll(\'[data-type="acf/column"]\').forEach(function(el) {
                        var container = el.querySelector(\'.acf-innerblocks-container\');
                        if (!container) return;
                        // block-list-appender hariç gerçek blockları say
                        var realBlocks = container.querySelectorAll(\'.wp-block:not(.block-list-appender):not(.block-editor-default-block-appender)\');
                        var hasBlocks = realBlocks.length > 0;
                        el.classList.toggle(\'has-blocks\', hasBlocks);
                    });
                }
                var columnObserver = new MutationObserver(updateColumnHasBlocks);
                document.addEventListener(\'DOMContentLoaded\', function() {
                    columnObserver.observe(document.body, { childList: true, subtree: true });
                    updateColumnHasBlocks();
                });
            })();
        ' );
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