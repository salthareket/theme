<?php

namespace SaltHareket\PageSettings\Admin;

use SaltHareket\PageSettings\PageSettings;

/**
 * PageSettingsAdmin
 *
 * Registers custom metabox for page settings on post edit screens,
 * term edit screens, and post type archive list pages.
 *
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-08-26
 *     - Add: Term edit page settings (renderTermFields + saveTerm)
 *     - Add: Archive sidebar on edit.php (renderArchiveSidebar + ajaxSaveArchive)
 *   1.0.0 - 2026-08-26
 *     - Initial: Post edit metabox with Layout/Components/Access tabs
 */
class PageSettingsAdmin
{
    public static function register(): void
    {
        add_action( 'add_meta_boxes', [ self::class, 'addMetaBox' ] );
        add_action( 'save_post', [ self::class, 'savePost' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );

        // Term edit hooks — init'ten sonra bağla: WooCommerce vb. taxonomy'ler
        // bootstrap çalışırken henüz register edilmemiş olabilir (variables.php aşaması çok erken).
        add_action( 'init', [ self::class, 'registerTermHooks' ], 99 );

        // Post type list (edit.php) sidebar — archive page settings
        add_action( 'in_admin_header', [ self::class, 'renderArchiveSidebar' ] );
        add_action( 'wp_ajax_sh_ps_save_archive', [ self::class, 'ajaxSaveArchive' ] );

        // Taxonomy list (edit-tags.php) sidebar — taxonomy archive page settings
        add_action( 'in_admin_header', [ self::class, 'renderTaxonomySidebar' ] );
        add_action( 'wp_ajax_sh_ps_save_taxonomy', [ self::class, 'ajaxSaveTaxonomy' ] );
    }

    /**
     * Tüm public taxonomy'lerin term edit hook'larını bağlar.
     * init priority 99'da çağrılır — WooCommerce vb. custom taxonomy'ler bu noktada hazır.
     */
    public static function registerTermHooks(): void
    {
        $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
        foreach ( $taxonomies as $tax ) {
            add_action( $tax . '_edit_form_fields', [ self::class, 'renderTermFields' ], 99, 2 );
            add_action( 'edited_' . $tax, [ self::class, 'saveTerm' ], 10, 2 );
        }
    }

    public static function addMetaBox(): void
    {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        unset( $post_types['attachment'], $post_types['template'] );

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'sh-page-settings',
                'Page Settings',
                [ self::class, 'renderMetaBox' ],
                $pt,
                'side',
                'high'
            );
        }
    }

    public static function enqueueAssets( string $hook ): void
    {
        $is_post_edit = in_array( $hook, [ 'post.php', 'post-new.php' ], true );
        $is_term_edit = $hook === 'term.php';
        $is_post_list = $hook === 'edit.php';
        $is_tax_list  = $hook === 'edit-tags.php';

        if ( ! $is_post_edit && ! $is_term_edit && ! $is_post_list && ! $is_tax_list ) return;

        if ( $is_post_edit ) {
            global $post;
            if ( ! $post || $post->post_type === 'template' ) return;
        }

        if ( $is_post_list ) {
            global $typenow;
            if ( $typenow === 'template' ) return;
        }

        $apps_url = trailingslashit( SH_INCLUDES_URL ) . 'apps/';
        $apps_dir = dirname( __DIR__, 2 );
        $base_url = trailingslashit( SH_INCLUDES_URL ) . 'apps/page-settings/';
        $base_dir = dirname( __DIR__ );

        $sel_css = $apps_dir . '/sh-select.css';
        if ( file_exists( $sel_css ) ) {
            wp_enqueue_style( 'sh-select', $apps_url . 'sh-select.css', [], filemtime( $sel_css ) );
        }
        $sel_js = $apps_dir . '/sh-select.js';
        if ( file_exists( $sel_js ) ) {
            wp_enqueue_script( 'sh-select', $apps_url . 'sh-select.js', [ 'jquery' ], filemtime( $sel_js ), true );
        }

        $css = $base_dir . '/page-settings.css';
        if ( file_exists( $css ) ) {
            wp_enqueue_style( 'sh-page-settings', $base_url . 'page-settings.css', [], filemtime( $css ) );
        }

        $js = $base_dir . '/page-settings.js';
        if ( file_exists( $js ) ) {
            wp_enqueue_script( 'sh-page-settings', $base_url . 'page-settings.js', [ 'jquery' ], filemtime( $js ), true );
        }
    }

    // ─── Post Edit Metabox ────────────────────────────────────────────────────

    public static function renderMetaBox( \WP_Post $post ): void
    {
        $enabled = PageSettings::isEnabled( $post->ID );
        $ps      = $enabled ? PageSettings::get( $post->ID ) : PageSettings::defaults();
        $choices = PageSettings::choices();
        $sync    = PageSettings::isSyncLangs( $post->ID );

        wp_nonce_field( 'sh_page_settings_save', 'sh_page_settings_nonce' );
        ?>
        <div id="sh-ps-wrap">
            <div class="sh-ps-header-toggle">
                <label class="sh-ps-toggle">
                    <input type="checkbox" name="sh_ps_enabled" value="1" <?php checked( $enabled ); ?>>
                    <span class="sh-ps-toggle-slider"></span>
                </label>
            </div>
            <div class="sh-ps-panel" style="<?php echo $enabled ? '' : 'display:none'; ?>">
                <?php self::renderTabs( $ps, $choices ); ?>
                <?php self::renderLangSyncField( $sync ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * "Apply to all languages" switch'i. Sadece Polylang/WPML aktifse gosterilir.
     * Acikken save'de ayarlar tum dil versiyonlarina kopyalanir (kalici sync).
     */
    private static function renderLangSyncField( bool $sync ): void
    {
        if ( ! self::isMultilangActive() ) return;
        ?>
        <div class="sh-ps-field sh-ps-langsync-field" style="margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f1;">
            <label class="sh-ps-toggle">
                <input type="checkbox" name="sh_ps_sync_langs" value="1" <?php checked( $sync ); ?>>
                <span class="sh-ps-toggle-slider"></span>
            </label>
            <span class="sh-ps-field-label"><?php echo esc_html( trans( 'Apply to all languages' ) ); ?></span>
            <p style="margin:4px 0 0;font-size:11px;color:#8a8f94;line-height:1.4;">
                <?php echo esc_html( trans( 'Copies these settings to every language version on save.' ) ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Polylang veya WPML aktif mi?
     */
    private static function isMultilangActive(): bool
    {
        return function_exists( 'pll_get_post_translations' )
            || ( function_exists( 'icl_object_id' ) && function_exists( 'icl_get_languages' ) );
    }

    public static function savePost( int $post_id, \WP_Post $post ): void
    {
        if ( ! isset( $_POST['sh_page_settings_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['sh_page_settings_nonce'], 'sh_page_settings_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( $post->post_type === 'template' ) return;

        $enabled = ! empty( $_POST['sh_ps_enabled'] );
        $sync    = ! empty( $_POST['sh_ps_sync_langs'] );

        if ( $enabled && isset( $_POST['sh_ps'] ) ) {
            PageSettings::save( $post_id, $_POST['sh_ps'], $sync );
        } else {
            PageSettings::disable( $post_id );
        }

        // Gutenberg'de bu handler metabox POST'unda (meta-box-loader) calisir ve meta
        // simdi tazedir. Ama PAE (asset extractor) bu istekte on_post_published guard'i
        // ile erken return eder → CSS eski HTML ile uretilir (container-md class'i CSS'e
        // yansimaz, ancak 2. save'de yansirdi). Meta yazildiktan sonra PAE'yi force ile
        // burada tetikleyerek dogru CSS'i ILK save'de urettiriyoruz.
        self::triggerAssetRebuild( $post_id, $post );
    }

    /**
     * page_settings kaydedildikten sonra PAE'yi force_rebuild ile calistirir.
     * Meta bu noktada taze oldugu icin fetch edilen HTML dogru container/sidebar
     * class'larini icerir ve CSS ilk save'de dogru uretilir.
     */
    private static function triggerAssetRebuild( int $post_id, \WP_Post $post ): void
    {
        if ( ! class_exists( 'PageAssetsExtractor' ) ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( $post->post_status !== 'publish' ) return;
        if ( $post->post_type === 'template' ) return;

        $ex = \PageAssetsExtractor::get_instance();
        // Sonsuz dongu / cifte fetch koruma: bu istekte bir kez calis
        static $done = [];
        if ( isset( $done[ $post_id ] ) ) return;
        $done[ $post_id ] = true;

        $ex->force_rebuild = true;
        $ex->on_save_post( $post_id, $post, true );
    }

    // ─── Term Edit Screen ─────────────────────────────────────────────────────

    public static function renderTermFields( \WP_Term $term, string $taxonomy ): void
    {
        $enabled = PageSettings::isEnabledForTerm( $term->term_id );
        $ps      = $enabled ? PageSettings::getForTerm( $term->term_id ) : PageSettings::defaults();
        $choices = PageSettings::choices();
        $sync    = PageSettings::isSyncLangsForTerm( $term->term_id );

        wp_nonce_field( 'sh_page_settings_term_save', 'sh_page_settings_term_nonce' );
        ?>
        <tr class="form-field sh-ps-term-row">
            <th scope="row" colspan="2" style="padding:0;border:0;"></th>
        </tr>
        <?php
        // Term edit sayfasında sağ sidebar — form-table flex layout ile
        // Page Settings panelini sağa taşıyoruz (post list sidebar ile aynı yaklaşım).
        // Gerçek HTML buraya inject edilir, JS ile layout oluşturulur.
        ?>
        <div id="sh-ps-term-sidebar" style="display:none;">
            <div id="sh-ps-wrap">
                <div class="postbox" style="margin:0;">
                    <div class="postbox-header" style="padding:8px 12px;display:flex;align-items:center;justify-content:space-between;">
                        <h2 style="margin:0;font-size:13px;font-weight:600;">Page Settings</h2>
                        <div class="sh-ps-header-toggle">
                            <label class="sh-ps-toggle">
                                <input type="checkbox" name="sh_ps_enabled" value="1" <?php checked( $enabled ); ?>>
                                <span class="sh-ps-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="inside" style="padding:10px 12px;">
                        <div class="sh-ps-panel" style="<?php echo $enabled ? '' : 'display:none'; ?>">
                            <?php self::renderTabs( $ps, $choices ); ?>
                            <?php self::renderLangSyncField( $sync ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #sh-ps-term-sidebar { margin-bottom: 16px; }
            #sh-ps-term-sidebar .sh-ps-select { width:100%; }
            #sh-ps-term-sidebar .sh-ps-field { margin-bottom:10px; }
            #sh-ps-term-sidebar .sh-ps-label { display:block;font-size:11px;font-weight:600;margin-bottom:4px;color:#50575e; }
            #sh-ps-term-sidebar .sh-ps-tabs { display:flex;gap:0;margin-bottom:12px;border-bottom:1px solid #ddd; }
            #sh-ps-term-sidebar .sh-ps-tab { padding:6px 10px;font-size:11px;font-weight:600;border:none;background:none;cursor:pointer;color:#50575e;border-bottom:2px solid transparent; }
            #sh-ps-term-sidebar .sh-ps-tab.active { color:#2271b1;border-bottom-color:#2271b1; }
            #sh-ps-term-sidebar .sh-ps-tab-content { display:none; }
            #sh-ps-term-sidebar .sh-ps-tab-content.active { display:block; }
            #sh-ps-term-sidebar .sh-ps-section-title { font-size:11px;font-weight:700;color:#1d2327;margin-bottom:6px; }
            #sh-ps-term-sidebar .sh-ps-component-box { margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f0f0f1; }
        </style>
        <script>
        jQuery(function($){
            var $sidebar = $('#sh-ps-term-sidebar');
            if (!$sidebar.length) return;

            // WP term edit sayfasında native sağ kolon: .edit-tag-actions
            // Buraya append et — "Güncelle" butonunun üstüne
            var $nativeSide = $('.edit-tag-actions');
            if ($nativeSide.length) {
                $nativeSide.prepend($sidebar);
                $sidebar.show();
            } else {
                // Fallback: submit butonunun üstüne
                var $submit = $('p.submit').first();
                if ($submit.length) {
                    $submit.before($sidebar);
                } else {
                    $('form#edittag').append($sidebar);
                }
                $sidebar.show();
            }

            var $wrap = $('#sh-ps-wrap');

            // Enable toggle
            $wrap.find('input[name="sh_ps_enabled"]').on('change', function(){
                $wrap.find('.sh-ps-panel').toggle(this.checked);
            });

            // Tabs
            $wrap.on('click', '.sh-ps-tab', function(){
                var tab = $(this).data('tab');
                $wrap.find('.sh-ps-tab').removeClass('active');
                $(this).addClass('active');
                $wrap.find('.sh-ps-tab-content').removeClass('active');
                $wrap.find('.sh-ps-tab-content[data-tab="'+tab+'"]').addClass('active');
            });

            // Conditional toggles
            $wrap.on('change', 'input[name="sh_ps[full_width]"]', function(){
                $wrap.find('.sh-ps-container-field').toggle(!this.checked);
            });
            $wrap.on('change', 'input[name="sh_ps[login_required]"]', function(){
                $wrap.find('.sh-ps-roles-field').toggle(this.checked);
            });
            $wrap.on('change', 'select[name="sh_ps[modal][template]"]', function(){
                $wrap.find('.sh-ps-modal-opts').toggle(parseInt(this.value) > 0);
            });
            $wrap.on('change', 'select[name="sh_ps[offcanvas][template]"]', function(){
                $wrap.find('.sh-ps-offcanvas-opts').toggle(parseInt(this.value) > 0);
            });
            $wrap.on('change', 'select[name="sh_ps[offcanvas][visibility]"]', function(){
                $wrap.find('.sh-ps-sidebar-opts').toggle(this.value !== '' && this.value !== 'offcanvas');
            });
            $wrap.on('change', 'select[name="sh_ps[modal][view]"]', function(){
                $wrap.find('.sh-ps-modal-time').toggle(this.value === 'scheduled');
            });

            // sh-select init
            if (window.shSelect) {
                $wrap.find('select[data-sh-select]').each(function(){
                    window.shSelect.init(this, { placeholder: 'Select...' });
                });
            }
        });
        </script>
        <?php
    }

    public static function saveTerm( int $term_id, int $tt_id ): void
    {
        if ( ! isset( $_POST['sh_page_settings_term_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['sh_page_settings_term_nonce'], 'sh_page_settings_term_save' ) ) return;
        if ( ! current_user_can( 'manage_categories' ) ) return;

        $enabled = ! empty( $_POST['sh_ps_enabled'] );
        $sync    = ! empty( $_POST['sh_ps_sync_langs'] );

        if ( $enabled && isset( $_POST['sh_ps'] ) ) {
            PageSettings::saveForTerm( $term_id, $_POST['sh_ps'], $sync );
        } else {
            delete_term_meta( $term_id, PageSettings::META_KEY );
            delete_term_meta( $term_id, '_page_settings_enabled' );
            delete_term_meta( $term_id, '_page_settings_sync_langs' );
        }
    }

    // ─── Archive Sidebar (edit.php) ───────────────────────────────────────────

    public static function renderArchiveSidebar(): void
    {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'edit' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $post_type = $screen->post_type;
        if ( ! $post_type || $post_type === 'template' ) return;

        $enabled = PageSettings::isEnabledForArchive( $post_type );
        $apply_all = (bool) get_option( '_page_settings_apply_all_singles_' . $post_type, false );
        $ps      = $enabled ? PageSettings::getForArchive( $post_type ) : PageSettings::defaults();
        $choices = PageSettings::choices();
        $nonce   = wp_create_nonce( 'sh_ps_archive_nonce' );
        ?>
        <div id="sh-ps-archive-sidebar" style="display:none;">
            <div class="postbox">
                <div class="postbox-header" style="padding:8px 12px;">
                    <h2 style="margin:0;font-size:13px;font-weight:600;">Page Settings</h2>
                </div>
                <div class="inside" style="padding:10px 12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:12px;color:#50575e;">Custom Settings</span>
                        <label class="sh-ps-toggle">
                            <input type="checkbox" id="sh-ps-archive-enable" <?php checked( $enabled ); ?>>
                            <span class="sh-ps-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="sh-ps-panel" id="sh-ps-archive-panel" style="<?php echo $enabled ? '' : 'display:none'; ?>">
                        <?php self::renderTabs( $ps, $choices ); ?>

                        <?php /* Apply to all singles switch */ ?>
                        <?php
                        $pt_obj    = get_post_type_object( $post_type );
                        $pt_label  = $pt_obj ? $pt_obj->labels->singular_name : $post_type;
                        ?>
                        <div class="sh-ps-field" style="margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f1;">
                            <label class="sh-ps-toggle">
                                <input type="checkbox" id="sh-ps-apply-all-singles" <?php checked( $apply_all ); ?>>
                                <span class="sh-ps-toggle-slider"></span>
                            </label>
                            <span class="sh-ps-field-label">
                                <?php echo esc_html( sprintf( trans( 'Apply to all %s singles' ), $pt_label ) ); ?>
                            </span>
                            <p style="margin:4px 0 0;font-size:11px;color:#8a8f94;line-height:1.4;">
                                <?php echo esc_html( trans( 'Use these settings as default for all single pages of this post type (overridden by individual post settings).' ) ); ?>
                            </p>
                        </div>

                        <div style="margin-top:12px;">
                            <button type="button" id="sh-ps-archive-save" class="button button-primary" style="width:100%;">Update</button>
                            <span id="sh-ps-archive-msg" style="font-size:11px;color:#15803d;display:none;margin-top:6px;text-align:center;">Saved</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #sh-ps-archive-sidebar .postbox { margin:0; }
            #sh-ps-archive-sidebar .sh-ps-select { width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px; }
            #sh-ps-archive-sidebar .sh-ps-field { margin-bottom:10px; }
            #sh-ps-archive-sidebar .sh-ps-label { display:block;font-size:11px;font-weight:600;margin-bottom:4px;color:#50575e; }
            #sh-ps-archive-sidebar .sh-ps-tabs { display:flex;gap:0;margin-bottom:12px;border-bottom:1px solid #ddd; }
            #sh-ps-archive-sidebar .sh-ps-tab { padding:6px 10px;font-size:11px;font-weight:600;border:none;background:none;cursor:pointer;color:#50575e;border-bottom:2px solid transparent; }
            #sh-ps-archive-sidebar .sh-ps-tab.active { color:#2271b1;border-bottom-color:#2271b1; }
            #sh-ps-archive-sidebar .sh-ps-tab-content { display:none; }
            #sh-ps-archive-sidebar .sh-ps-tab-content.active { display:block; }
            #sh-ps-archive-sidebar .sh-ps-section-title { font-size:11px;font-weight:700;color:#1d2327;margin-bottom:6px; }
            #sh-ps-archive-sidebar .sh-ps-component-box { margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f0f0f1; }
            .sh-ps-archive-layout { display:flex;gap:20px; }
            .sh-ps-archive-layout .sh-ps-archive-main { flex:1;min-width:0; }
            .sh-ps-archive-layout .sh-ps-archive-side { width:280px;flex-shrink:0; }
            @media (max-width: 1200px) {
                .sh-ps-archive-layout { flex-direction:column; }
                .sh-ps-archive-layout .sh-ps-archive-side { width:100%; }
            }
        </style>
        <script>
        jQuery(function($){
            var $sidebar = $('#sh-ps-archive-sidebar');
            var $panel   = $('#sh-ps-archive-panel');

            // ACF-style: wrap table content + sidebar in flex layout
            var $wrap = $('body.post-type-<?php echo esc_js($post_type); ?> .wrap');
            if (!$wrap.length) return;

            // Find the main content (everything after .wp-header-end or subsubsub)
            var $headerEnd = $wrap.find('.wp-header-end');
            if (!$headerEnd.length) $headerEnd = $wrap.find('.subsubsub').first();

            // Create flex container
            var $layout = $('<div class="sh-ps-archive-layout"></div>');
            var $main = $('<div class="sh-ps-archive-main"></div>');
            var $side = $('<div class="sh-ps-archive-side"></div>');

            // Move everything after header-end into main
            var $afterHeader = $headerEnd.length ? $headerEnd.nextAll() : $wrap.children().not('.page-title-action, h1, .wp-heading-inline, .subsubsub');
            $main.append($afterHeader);
            $side.append($sidebar);
            $layout.append($main).append($side);

            if ($headerEnd.length) {
                $headerEnd.after($layout);
            } else {
                $wrap.append($layout);
            }

            $sidebar.show();

            // Enable toggle
            $('#sh-ps-archive-enable').on('change', function(){
                $panel.toggle(this.checked);
            });

            // Tabs
            $sidebar.on('click', '.sh-ps-tab', function(){
                var tab = $(this).data('tab');
                $sidebar.find('.sh-ps-tab').removeClass('active');
                $(this).addClass('active');
                $sidebar.find('.sh-ps-tab-content').removeClass('active');
                $sidebar.find('.sh-ps-tab-content[data-tab="'+tab+'"]').addClass('active');
            });

            // Full width toggle
            $sidebar.on('change', 'input[name="sh_ps[full_width]"]', function(){
                $sidebar.find('.sh-ps-container-field').toggle(!this.checked);
            });

            // Login required toggle
            $sidebar.on('change', 'input[name="sh_ps[login_required]"]', function(){
                $sidebar.find('.sh-ps-roles-field').toggle(this.checked);
            });

            // Modal/Offcanvas template toggle
            $sidebar.on('change', 'select[name="sh_ps[modal][template]"]', function(){
                $sidebar.find('.sh-ps-modal-opts').toggle(parseInt(this.value) > 0);
            });
            $sidebar.on('change', 'select[name="sh_ps[offcanvas][template]"]', function(){
                $sidebar.find('.sh-ps-offcanvas-opts').toggle(parseInt(this.value) > 0);
            });
            // Sidebar override alanlari: sadece sidebar tipi visibility'de goster (offcanvas-{bp})
            $sidebar.on('change', 'select[name="sh_ps[offcanvas][visibility]"]', function(){
                var v = this.value;
                var isSidebar = (v !== '' && v !== 'offcanvas');
                $sidebar.find('.sh-ps-sidebar-opts').toggle(isSidebar);
            });
            $sidebar.on('change', 'select[name="sh_ps[modal][view]"]', function(){
                $sidebar.find('.sh-ps-modal-time').toggle(this.value === 'scheduled');
            });

            // Init sh-select
            if (window.shSelect) {
                $sidebar.find('select[data-sh-select]').each(function(){
                    window.shSelect.init(this, { placeholder: 'Select...' });
                });
            }

            // Save
            $('#sh-ps-archive-save').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true).text('Saving...');
                var data = {
                    action: 'sh_ps_save_archive',
                    nonce: '<?php echo esc_js($nonce); ?>',
                    post_type: '<?php echo esc_js($post_type); ?>',
                    enabled: $('#sh-ps-archive-enable').is(':checked') ? 1 : 0,
                    apply_to_all_singles: $('#sh-ps-apply-all-singles').is(':checked') ? 1 : 0
                };
                $sidebar.find('[name^="sh_ps"]').each(function(){
                    var $el = $(this);
                    var name = $el.attr('name');
                    if ($el.attr('type') === 'checkbox') {
                        data[name] = $el.is(':checked') ? '1' : '0';
                    } else if ($el.attr('multiple')) {
                        data[name] = $el.val() || [];
                    } else {
                        data[name] = $el.val() || '';
                    }
                });
                $.post(ajaxurl, data, function(res){
                    btn.prop('disabled', false).text('Update');
                    var $m = $('#sh-ps-archive-msg');
                    $m.text(res.success ? 'Saved' : 'Error').css('color', res.success ? '#15803d' : '#dc2626').show();
                    setTimeout(function(){ $m.hide(); }, 2500);
                });
            });
        });
        </script>
        <?php
    }

    public static function ajaxSaveArchive(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_ps_archive_nonce', 'nonce' );

        $post_type = sanitize_key( $_POST['post_type'] ?? '' );
        if ( ! $post_type ) wp_send_json_error( 'Invalid post type' );

        $enabled     = ! empty( $_POST['enabled'] );
        $apply_all   = ! empty( $_POST['apply_to_all_singles'] );

        if ( $enabled ) {
            $ps_data = isset( $_POST['sh_ps'] ) && is_array( $_POST['sh_ps'] )
                ? $_POST['sh_ps']
                : [];
            PageSettings::saveForArchive( $post_type, $ps_data );
        } else {
            delete_option( PageSettings::META_KEY . '_' . $post_type );
            delete_option( '_page_settings_enabled_' . $post_type );
        }

        // Apply to all singles flag
        update_option( '_page_settings_apply_all_singles_' . $post_type, $apply_all ? 1 : 0 );

        if ( function_exists( 'rocket_clean_domain' ) ) rocket_clean_domain();
        wp_send_json_success( 'Saved' );
    }

    // ─── Taxonomy Archive Sidebar (edit-tags.php) ─────────────────────────────

    public static function renderTaxonomySidebar(): void
    {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'edit-tags' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $taxonomy = $screen->taxonomy;
        if ( ! $taxonomy ) return;

        $tax_obj  = get_taxonomy( $taxonomy );
        $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $taxonomy;

        $enabled   = PageSettings::isEnabledForTaxonomy( $taxonomy );
        $apply_all = (bool) get_option( '_page_settings_apply_all_terms_' . $taxonomy, false );
        $ps        = $enabled ? PageSettings::getForTaxonomy( $taxonomy ) : PageSettings::defaults();
        $choices   = PageSettings::choices();
        $nonce     = wp_create_nonce( 'sh_ps_taxonomy_nonce' );
        ?>
        <div id="sh-ps-taxonomy-sidebar" style="display:none;">
            <div class="postbox">
                <div class="postbox-header" style="padding:8px 12px;">
                    <h2 style="margin:0;font-size:13px;font-weight:600;">Page Settings</h2>
                </div>
                <div class="inside" style="padding:10px 12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:12px;color:#50575e;">Custom Settings</span>
                        <label class="sh-ps-toggle">
                            <input type="checkbox" id="sh-ps-taxonomy-enable" <?php checked( $enabled ); ?>>
                            <span class="sh-ps-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="sh-ps-panel" id="sh-ps-taxonomy-panel" style="<?php echo $enabled ? '' : 'display:none'; ?>">
                        <?php self::renderTabs( $ps, $choices ); ?>

                        <?php /* Apply to all terms switch */ ?>
                        <div class="sh-ps-field" style="margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f1;">
                            <label class="sh-ps-toggle">
                                <input type="checkbox" id="sh-ps-apply-all-terms" <?php checked( $apply_all ); ?>>
                                <span class="sh-ps-toggle-slider"></span>
                            </label>
                            <span class="sh-ps-field-label">
                                <?php echo esc_html( sprintf( trans( 'Apply to all %s terms' ), $tax_label ) ); ?>
                            </span>
                            <p style="margin:4px 0 0;font-size:11px;color:#8a8f94;line-height:1.4;">
                                <?php echo esc_html( trans( 'Use these settings as default for all term pages in this taxonomy (overridden by individual term settings).' ) ); ?>
                            </p>
                        </div>

                        <div style="margin-top:12px;">
                            <button type="button" id="sh-ps-taxonomy-save" class="button button-primary" style="width:100%;">Update</button>
                            <span id="sh-ps-taxonomy-msg" style="font-size:11px;color:#15803d;display:none;margin-top:6px;text-align:center;">Saved</span>
                        </div>
                    </div>
                    <div id="sh-ps-taxonomy-disabled-msg" style="<?php echo $enabled ? 'display:none' : ''; ?>">
                        <div style="margin-top:12px;">
                            <button type="button" id="sh-ps-taxonomy-save" class="button button-primary" style="width:100%;">Update</button>
                            <span id="sh-ps-taxonomy-msg" style="font-size:11px;color:#15803d;display:none;margin-top:6px;text-align:center;">Saved</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #sh-ps-taxonomy-sidebar .postbox { margin:0; }
            #sh-ps-taxonomy-sidebar .sh-ps-select { width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px; }
            #sh-ps-taxonomy-sidebar .sh-ps-field { margin-bottom:10px; }
            #sh-ps-taxonomy-sidebar .sh-ps-label { display:block;font-size:11px;font-weight:600;margin-bottom:4px;color:#50575e; }
            #sh-ps-taxonomy-sidebar .sh-ps-tabs { display:flex;gap:0;margin-bottom:12px;border-bottom:1px solid #ddd; }
            #sh-ps-taxonomy-sidebar .sh-ps-tab { padding:6px 10px;font-size:11px;font-weight:600;border:none;background:none;cursor:pointer;color:#50575e;border-bottom:2px solid transparent; }
            #sh-ps-taxonomy-sidebar .sh-ps-tab.active { color:#2271b1;border-bottom-color:#2271b1; }
            #sh-ps-taxonomy-sidebar .sh-ps-tab-content { display:none; }
            #sh-ps-taxonomy-sidebar .sh-ps-tab-content.active { display:block; }
            #sh-ps-taxonomy-sidebar .sh-ps-section-title { font-size:11px;font-weight:700;color:#1d2327;margin-bottom:6px; }
            #sh-ps-taxonomy-sidebar .sh-ps-component-box { margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f0f0f1; }
            .sh-ps-tags-layout { display:flex;gap:20px; }
            .sh-ps-tags-layout .sh-ps-tags-main { flex:1;min-width:0; }
            .sh-ps-tags-layout .sh-ps-tags-side { width:280px;flex-shrink:0; }
            @media (max-width: 1200px) {
                .sh-ps-tags-layout { flex-direction:column; }
                .sh-ps-tags-layout .sh-ps-tags-side { width:100%; }
            }
        </style>
        <script>
        jQuery(function($){
            var $sidebar = $('#sh-ps-taxonomy-sidebar');
            var $panel   = $('#sh-ps-taxonomy-panel');
            var $disabledMsg = $('#sh-ps-taxonomy-disabled-msg');

            // Flex layout — taxonomy list sayfasına sidebar ekle
            var $wrap = $('.wrap');
            if (!$wrap.length) return;

            var $layout = $('<div class="sh-ps-tags-layout"></div>');
            var $main = $('<div class="sh-ps-tags-main"></div>');
            var $side = $('<div class="sh-ps-tags-side"></div>');

            var $headerEnd = $wrap.find('.wp-header-end');
            var $afterHeader = $headerEnd.length ? $headerEnd.nextAll() : $wrap.children().not('h1, .wp-heading-inline, .page-title-action');
            $main.append($afterHeader);
            $side.append($sidebar);
            $layout.append($main).append($side);

            if ($headerEnd.length) {
                $headerEnd.after($layout);
            } else {
                $wrap.append($layout);
            }
            $sidebar.show();

            // Enable toggle
            $('#sh-ps-taxonomy-enable').on('change', function(){
                $panel.toggle(this.checked);
                $disabledMsg.toggle(!this.checked);
            });

            // Tabs
            $sidebar.on('click', '.sh-ps-tab', function(){
                var tab = $(this).data('tab');
                $sidebar.find('.sh-ps-tab').removeClass('active');
                $(this).addClass('active');
                $sidebar.find('.sh-ps-tab-content').removeClass('active');
                $sidebar.find('.sh-ps-tab-content[data-tab="'+tab+'"]').addClass('active');
            });

            // Conditional field toggles
            $sidebar.on('change', 'input[name="sh_ps[full_width]"]', function(){
                $sidebar.find('.sh-ps-container-field').toggle(!this.checked);
            });
            $sidebar.on('change', 'input[name="sh_ps[login_required]"]', function(){
                $sidebar.find('.sh-ps-roles-field').toggle(this.checked);
            });
            $sidebar.on('change', 'select[name="sh_ps[modal][template]"]', function(){
                $sidebar.find('.sh-ps-modal-opts').toggle(parseInt(this.value) > 0);
            });
            $sidebar.on('change', 'select[name="sh_ps[offcanvas][template]"]', function(){
                $sidebar.find('.sh-ps-offcanvas-opts').toggle(parseInt(this.value) > 0);
            });
            $sidebar.on('change', 'select[name="sh_ps[offcanvas][visibility]"]', function(){
                var isSidebar = (this.value !== '' && this.value !== 'offcanvas');
                $sidebar.find('.sh-ps-sidebar-opts').toggle(isSidebar);
            });
            $sidebar.on('change', 'select[name="sh_ps[modal][view]"]', function(){
                $sidebar.find('.sh-ps-modal-time').toggle(this.value === 'scheduled');
            });

            if (window.shSelect) {
                $sidebar.find('select[data-sh-select]').each(function(){
                    window.shSelect.init(this, { placeholder: 'Select...' });
                });
            }

            // Save
            $('#sh-ps-taxonomy-save').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true).text('Saving...');
                var data = {
                    action: 'sh_ps_save_taxonomy',
                    nonce: '<?php echo esc_js($nonce); ?>',
                    taxonomy: '<?php echo esc_js($taxonomy); ?>',
                    enabled: $('#sh-ps-taxonomy-enable').is(':checked') ? 1 : 0,
                    apply_to_all_terms: $('#sh-ps-apply-all-terms').is(':checked') ? 1 : 0
                };
                $sidebar.find('[name^="sh_ps"]').each(function(){
                    var $el = $(this);
                    var name = $el.attr('name');
                    if ($el.attr('type') === 'checkbox') {
                        data[name] = $el.is(':checked') ? '1' : '0';
                    } else if ($el.attr('multiple')) {
                        data[name] = $el.val() || [];
                    } else {
                        data[name] = $el.val() || '';
                    }
                });
                $.post(ajaxurl, data, function(res){
                    btn.prop('disabled', false).text('Update');
                    var $m = $('#sh-ps-taxonomy-msg');
                    $m.text(res.success ? 'Saved' : 'Error').css('color', res.success ? '#15803d' : '#dc2626').show();
                    setTimeout(function(){ $m.hide(); }, 2500);
                });
            });
        });
        </script>
        <?php
    }

    public static function ajaxSaveTaxonomy(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_ps_taxonomy_nonce', 'nonce' );

        $taxonomy = sanitize_key( $_POST['taxonomy'] ?? '' );
        if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) wp_send_json_error( 'Invalid taxonomy' );

        $enabled   = ! empty( $_POST['enabled'] );
        $apply_all = ! empty( $_POST['apply_to_all_terms'] );

        if ( $enabled ) {
            $ps_data = isset( $_POST['sh_ps'] ) && is_array( $_POST['sh_ps'] )
                ? $_POST['sh_ps']
                : [];
            PageSettings::saveForTaxonomy( $taxonomy, $ps_data );
        } else {
            delete_option( PageSettings::META_KEY . '_tax_' . $taxonomy );
            delete_option( '_page_settings_enabled_tax_' . $taxonomy );
        }

        // Apply to all terms flag
        update_option( '_page_settings_apply_all_terms_' . $taxonomy, $apply_all ? 1 : 0 );

        if ( function_exists( 'rocket_clean_domain' ) ) rocket_clean_domain();
        wp_send_json_success( 'Saved' );
    }

    // ─── Shared UI Helpers ────────────────────────────────────────────────────

    private static function renderTabs( array $ps, array $choices ): void
    {
        ?>
        <div class="sh-ps-tabs">
            <button type="button" class="sh-ps-tab active" data-tab="layout">Layout</button>
            <button type="button" class="sh-ps-tab" data-tab="components">Components</button>
            <button type="button" class="sh-ps-tab" data-tab="access">Access</button>
        </div>

        <div class="sh-ps-tab-content active" data-tab="layout">
            <?php self::renderSelect( 'header_template', 'Header', $choices['headers'], $ps['header_template'] ); ?>
            <?php self::renderSelect( 'footer_template', 'Footer', $choices['footers'], $ps['footer_template'] ); ?>
            <div class="sh-ps-field">
                <label class="sh-ps-toggle">
                    <input type="checkbox" name="sh_ps[full_width]" value="1" <?php checked( $ps['full_width'] ); ?>>
                    <span class="sh-ps-toggle-slider"></span>
                </label>
                <span class="sh-ps-field-label">Full Width</span>
            </div>
            <div class="sh-ps-field sh-ps-container-field" style="<?php echo $ps['full_width'] ? 'display:none' : ''; ?>">
                <?php self::renderSelect( 'container', 'Container', $choices['containers'], $ps['container'] ); ?>
            </div>
            <?php self::renderMultiSelect( 'body_classes', 'Body Classes', $choices['bodyClasses'], $ps['body_classes'] ); ?>
            <?php self::renderMultiSelect( 'main_classes', 'Main Classes', $choices['mainClasses'], $ps['main_classes'] ); ?>
        </div>

        <div class="sh-ps-tab-content" data-tab="components">
            <div class="sh-ps-component-box">
                <div class="sh-ps-section-title">Modal</div>
                <?php self::renderSelect( 'modal[template]', '', $choices['modals'], $ps['modal']['template'] ); ?>
                <div class="sh-ps-modal-opts" style="<?php echo $ps['modal']['template'] ? '' : 'display:none'; ?>">
                    <div class="sh-ps-field">
                        <label class="sh-ps-label">Title <span style="font-weight:400;color:#8a8f94;">(optional)</span></label>
                        <input type="text" name="sh_ps[modal][title]" value="<?php echo esc_attr( $ps['modal']['title'] ?? '' ); ?>" class="sh-ps-input" placeholder="Template title">
                    </div>
                    <?php self::renderSelect( 'modal[view]', 'Display', [
                        'always'    => 'Show every visit',
                        'scheduled' => 'Show once per interval',
                    ], $ps['modal']['view'] ?? 'always' ); ?>
                    <div class="sh-ps-modal-time" style="<?php echo ( $ps['modal']['view'] ?? '' ) === 'scheduled' ? '' : 'display:none'; ?>">
                        <div class="sh-ps-field">
                            <label class="sh-ps-label">Interval (minutes)</label>
                            <input type="number" name="sh_ps[modal][view_time]" value="<?php echo (int) ( $ps['modal']['view_time'] ?? 0 ); ?>" class="sh-ps-input" min="0" placeholder="0">
                        </div>
                    </div>
                    <?php self::renderSelect( 'modal[size]', 'Size', \SaltHareket\FieldRegistry\FieldChoices::modalSizes( true ), $ps['modal']['size'] ?? '' ); ?>
                </div>
            </div>
            <div class="sh-ps-component-box">
                <div class="sh-ps-section-title">Offcanvas</div>
                <?php self::renderSelect( 'offcanvas[template]', '', $choices['offcanvases'], $ps['offcanvas']['template'] ); ?>
                <div class="sh-ps-offcanvas-opts" style="<?php echo $ps['offcanvas']['template'] ? '' : 'display:none'; ?>">
                    <?php self::renderSelect( 'offcanvas[position]', 'Position', $choices['positions'], $ps['offcanvas']['position'] ); ?>
                    <?php self::renderSelect( 'offcanvas[visibility]', 'Visibility', [
                        '' => 'Template Default', 'offcanvas' => 'Always Offcanvas',
                        'offcanvas-sm' => 'On sm', 'offcanvas-md' => 'On md',
                        'offcanvas-lg' => 'On lg', 'offcanvas-xl' => 'On xl', 'offcanvas-xxl' => 'On xxl',
                    ], $ps['offcanvas']['visibility'] ); ?>

                    <?php
                    // Sidebar override alanlari — SADECE sidebar tipi visibility'de gorunur
                    // (offcanvas-{bp}). 'Always Offcanvas' ve 'Template Default' sidebar degil.
                    $vis        = $ps['offcanvas']['visibility'] ?? '';
                    $is_sidebar = ( $vis !== '' && $vis !== 'offcanvas' );
                    ?>
                    <div class="sh-ps-sidebar-opts" style="<?php echo $is_sidebar ? '' : 'display:none'; ?>">
                        <div class="sh-ps-field">
                            <label class="sh-ps-label">Title <span style="font-weight:400;color:#8a8f94;">(optional)</span></label>
                            <input type="text" name="sh_ps[offcanvas][title]" value="<?php echo esc_attr( $ps['offcanvas']['title'] ?? '' ); ?>" class="sh-ps-input" placeholder="Template title">
                        </div>
                        <?php self::renderSelect( 'offcanvas[width]', 'Sidebar Width (columns)', $choices['sidebarWidths'], (string) ( $ps['offcanvas']['width'] ?? '' ) ); ?>
                        <?php self::renderSelect( 'offcanvas[gap]', 'Gap', $choices['sidebarGaps'], $ps['offcanvas']['gap'] ?? '' ); ?>
                        <div class="sh-ps-field">
                            <label class="sh-ps-label">Toggler Icon <span style="font-weight:400;color:#8a8f94;">(optional)</span></label>
                            <input type="text" name="sh_ps[offcanvas][toggler_icon]" value="<?php echo esc_attr( $ps['offcanvas']['toggler_icon'] ?? '' ); ?>" class="sh-ps-input" placeholder="light:bars">
                        </div>
                        <div class="sh-ps-field">
                            <label class="sh-ps-label">Toggler Label <span style="font-weight:400;color:#8a8f94;">(optional)</span></label>
                            <input type="text" name="sh_ps[offcanvas][toggler_label]" value="<?php echo esc_attr( $ps['offcanvas']['toggler_label'] ?? '' ); ?>" class="sh-ps-input" placeholder="Menu">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sh-ps-tab-content" data-tab="access">
            <div class="sh-ps-field">
                <label class="sh-ps-toggle">
                    <input type="checkbox" name="sh_ps[login_required]" value="1" <?php checked( $ps['login_required'] ); ?>>
                    <span class="sh-ps-toggle-slider"></span>
                </label>
                <span class="sh-ps-field-label">Login Required</span>
            </div>
            <div class="sh-ps-roles-field" style="<?php echo $ps['login_required'] ? '' : 'display:none'; ?>">
                <label class="sh-ps-label">Allowed Roles</label>
                <select name="sh_ps[allowed_roles][]" class="sh-ps-select" multiple data-sh-select style="width:100%">
                    <?php foreach ( $choices['roles'] as $slug => $name ) : ?>
                    <option value="<?php echo esc_attr( $slug ); ?>" <?php echo in_array( $slug, $ps['allowed_roles'], true ) ? 'selected' : ''; ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }

    private static function renderSelect( string $name, string $label, array $choices, $current ): void
    {
        if ( strpos( $name, '[' ) !== false ) {
            $parts = explode( '[', str_replace( ']', '', $name ) );
            $field_name = 'sh_ps[' . implode( '][', $parts ) . ']';
        } else {
            $field_name = "sh_ps[$name]";
        }
        ?>
        <div class="sh-ps-field">
            <?php if ( $label ) : ?><label class="sh-ps-label"><?php echo esc_html( $label ); ?></label><?php endif; ?>
            <select name="<?php echo esc_attr( $field_name ); ?>" class="sh-ps-select">
                <?php foreach ( $choices as $val => $lbl ) : ?>
                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (string) $current, (string) $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    private static function renderMultiSelect( string $name, string $label, array $choices, array $current ): void
    {
        ?>
        <div class="sh-ps-field">
            <label class="sh-ps-label"><?php echo esc_html( $label ); ?></label>
            <select name="sh_ps[<?php echo esc_attr( $name ); ?>][]" class="sh-ps-select sh-ps-multiselect" multiple data-sh-select style="width:100%">
                <?php foreach ( $choices as $val => $lbl ) : ?>
                <option value="<?php echo esc_attr( $val ); ?>" <?php echo in_array( $val, $current, true ) ? 'selected' : ''; ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
}
