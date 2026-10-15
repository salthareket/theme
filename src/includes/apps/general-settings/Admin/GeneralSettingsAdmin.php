<?php

namespace SaltHareket\GeneralSettings\Admin;

use SaltHareket\FieldRegistry\FieldRegistry;
use SaltHareket\FieldRegistry\FieldChoices;

/**
 * GeneralSettingsAdmin
 *
 * @version 1.0.0
 */
class GeneralSettingsAdmin
{
    const SLUG = 'sh-general-settings';

    public static function register(): void
    {
        if ( ! is_admin() ) return;
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'wp_ajax_sh_gs_save_menu_locations',  [ self::class, 'ajaxSaveMenuLocations' ] );
        add_action( 'wp_ajax_sh_gs_save_menu_populate',   [ self::class, 'ajaxSaveMenuPopulate' ] );
        add_action( 'wp_ajax_sh_gs_get_taxonomies',       [ self::class, 'ajaxGetTaxonomies' ] );
        add_action( 'wp_ajax_sh_gs_save_woo',              [ self::class, 'ajaxSaveWoo' ] );
        add_action( 'wp_ajax_sh_gs_save_social_share', [ '\SaltHareket\SocialShare\Admin\SocialShareAdmin', 'ajaxSave' ] );
        add_action( 'wp_ajax_sh_gs_save_tease',            [ self::class, 'ajaxSaveTease' ] );
        add_action( 'wp_ajax_sh_gs_save_gallery',          [ self::class, 'ajaxSaveGallery' ] );
        add_action( 'wp_ajax_sh_gs_save_qty_stepper',      [ self::class, 'ajaxSaveQtyStepper' ] );
        add_action( 'wp_ajax_sh_gs_save_sticky_atc',       [ self::class, 'ajaxSaveStickyAtc' ] );
        add_action( 'wp_ajax_sh_gs_save_product_lists',    [ self::class, 'ajaxSaveProductLists' ] );
        add_action( 'wp_ajax_sh_gs_save_cart_lists',        [ self::class, 'ajaxSaveCartLists' ] );
        add_action( 'wp_ajax_sh_gs_save_product_tabs',     [ self::class, 'ajaxSaveProductTabs' ] );
        add_action( 'wp_ajax_sh_gs_save_translations',     [ self::class, 'ajaxSaveTranslations' ] );
        add_action( 'wp_ajax_sh_gs_extract_translations', [ self::class, 'ajaxExtractTranslations' ] );
        add_action( 'wp_ajax_sh_gs_save_breadcrumb',       [ self::class, 'ajaxSaveBreadcrumb' ] );
        add_action( 'wp_ajax_sh_gs_save_general',          [ self::class, 'ajaxSaveGeneral' ] );
        add_action( 'wp_ajax_sh_gs_save_pagination',       [ self::class, 'ajaxSavePagination' ] );
        add_action( 'wp_ajax_sh_gs_save_defaults',         [ self::class, 'ajaxSaveDefaults' ] );
        add_action( 'wp_ajax_sh_gs_save_maps',             [ self::class, 'ajaxSaveMaps' ] );
        add_action( 'wp_ajax_sh_gs_save_logos',            [ self::class, 'ajaxSaveLogos' ] );
        add_action( 'wp_ajax_sh_security_save',            [ '\SaltHareket\Security\Admin\SecurityAdmin', 'ajaxSave' ] );
        add_action( 'wp_ajax_sh_security_wph',             [ '\SaltHareket\Security\Admin\SecurityAdmin', 'ajaxWphApply' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page( 
            'theme-settings', 
            '⚙️ General Settings', 
            '⚙️ General Settings',
            'manage_options', 
            self::SLUG, 
            [ self::class, 'renderPage' ] 
        );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== self::SLUG ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important}</style>';
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, self::SLUG ) === false ) return;
        $base     = dirname( __DIR__, 2 );
        $base_url = trailingslashit( SH_INCLUDES_URL ) . 'apps/';

        $css_path = $base . '/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $base_url . 'sh-admin.css', [], filemtime( $css_path ) );
        }
        $sel_css = $base . '/sh-select.css';
        if ( file_exists( $sel_css ) ) {
            wp_enqueue_style( 'sh-select', $base_url . 'sh-select.css', [], filemtime( $sel_css ) );
        }
        $sel_js = $base . '/sh-select.js';
        if ( file_exists( $sel_js ) ) {
            wp_enqueue_script( 'sh-select', $base_url . 'sh-select.js', [ 'jquery' ], filemtime( $sel_js ), true );
        }

        // SH Icon Picker
        $ip_css = SH_INCLUDES_PATH . 'apps/sh-icon-picker.css';
        $ip_js  = SH_INCLUDES_PATH . 'apps/sh-icon-picker.js';
        if ( file_exists( $ip_css ) ) {
            wp_enqueue_style( 'sh-icon-picker', SH_INCLUDES_URL . 'apps/sh-icon-picker.css', [], filemtime( $ip_css ) );
        }
        if ( file_exists( $ip_js ) ) {
            wp_enqueue_script( 'sh-icon-picker', SH_INCLUDES_URL . 'apps/sh-icon-picker.js', ['jquery'], filemtime( $ip_js ), true );
        }

        // TinyMCE — Product Tabs HTML içerik editörü için
        wp_enqueue_editor();
    }

    // ---------------- Helpers --------------------------------------------------

    private static function getMenuLocations(): array
    {
        $val = get_option( 'options_menu_locations', [] );
        return is_array( $val ) ? $val : [];
    }

    private static function getMenuPopulate(): array
    {
        $val = get_option( 'options_menu_populate', [] );
        return is_array( $val ) ? $val : [];
    }

    // ---------------- AJAX -----------------------------------------------------

    public static function ajaxSaveMenuLocations(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );
        $rows  = $_POST['rows'] ?? [];
        $clean = [];
        foreach ( (array) $rows as $row ) {
            $name = sanitize_text_field( $row['name'] ?? '' );
            if ( $name ) $clean[] = [ 'name' => $name ];
        }
        // ACF bypass — doğrudan wp_options'a array olarak kaydet
        update_option( 'options_menu_locations', $clean );
        if ( class_exists( 'QueryCache' ) ) {
            \QueryCache::invalidate_options_bulk();
        }
        wp_send_json_success();
    }

    public static function ajaxSaveMenuPopulate(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );
        $rows  = $_POST['rows'] ?? [];
        $clean = [];
        foreach ( (array) $rows as $row ) {
            if ( empty( $row['menu'] ) ) continue;
            $clean[] = [
                'menu'                => sanitize_key( $row['menu'] ?? '' ),
                'menu_item_post_type' => sanitize_key( $row['menu_item_post_type'] ?? '' ),
                'replace'             => ! empty( $row['replace'] ),
                'orderby_post_type'   => sanitize_key( $row['orderby_post_type'] ?? 'none' ),
                'order_post_type'     => in_array( $row['order_post_type'] ?? '', [ 'ASC', 'DESC' ] ) ? $row['order_post_type'] : 'ASC',
                'all_post_type'       => ! empty( $row['all_post_type'] ),
                'post_per_page'       => (int) ( $row['post_per_page'] ?? 0 ),
                'menu_item_taxonomy'  => sanitize_key( $row['menu_item_taxonomy'] ?? '' ),
                'orderby_taxonomy'    => sanitize_key( $row['orderby_taxonomy'] ?? 'name' ),
                'order_taxonomy'      => in_array( $row['order_taxonomy'] ?? '', [ 'ASC', 'DESC' ] ) ? $row['order_taxonomy'] : 'ASC',
                'all_taxonomy'        => ! empty( $row['all_taxonomy'] ),
                'number'              => (int) ( $row['number'] ?? 1 ),
                'hide_empty'          => ! empty( $row['hide_empty'] ),
                'show_posts'          => ! empty( $row['show_posts'] ),
            ];
        }
        // ACF bypass — doğrudan wp_options'a array olarak kaydet
        update_option( 'options_menu_populate', $clean );
        if ( class_exists( 'QueryCache' ) ) {
            \QueryCache::invalidate_options_bulk();
        }
        // bric_create_custom_menu transient'larını temizle
        // Bu transient'lar QueryCache prefix'i kullanmadığı için ayrıca silinmeli
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_bric_mp_%'
                OR option_name LIKE '_transient_timeout_bric_mp_%'"
        );
        wp_send_json_success();
    }

    public static function ajaxGetTaxonomies(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        $pt   = sanitize_key( $_POST['post_type'] ?? '' );

        // FieldChoices üzerinden — internal taxonomy'ler zaten filtreli
        $choices = $pt ? FieldChoices::taxonomiesForPostType( $pt ) : [];

        // Format: ['Label (slug)' => 'slug'] -> JS tarafı bunu kullanıyor
        // Ama FieldChoices::taxonomiesForPostType() zaten ['slug' => 'Label (slug)'] döndürüyor
        // JS'in beklediği format: { 'Label (slug)': 'slug' } — flip edelim
        $out = [];
        foreach ( $choices as $slug => $label ) {
            $out[ $label ] = $slug;
        }
        wp_send_json_success( $out );
    }

    // ---------------- UI Helpers -----------------------------------------------

    private static function sw( string $name, bool $checked, string $label = '', string $extra_cls = '', string $input_class = '' ): string
    {
        $out  = '<span class="sh-inline-flex sh-items-center" style="cursor:pointer;gap:10px">';
        $out .= '<label class="sh-toggle" style="flex-shrink:0"><input type="checkbox" class="sh-gs-field' . ( $input_class ? ' ' . esc_attr( $input_class ) : '' ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked' : '' ) . '><span class="sh-toggle-slider"></span></label>';
        if ( $label ) $out .= '<span class="sh-text-xs sh-text-label sh-whitespace-nowrap">' . esc_html( $label ) . '</span>';
        $out .= '</span>';
        return $out;
    }

    private static function swTpl( string $name, string $label = '', string $cls = '', string $input_class = '' ): string
    {
        $out  = '<span class="sh-inline-flex sh-items-center" style="cursor:pointer;gap:10px">';
        $out .= '<label class="sh-toggle" style="flex-shrink:0"><input type="checkbox" class="sh-gs-field' . ( $input_class ? ' ' . esc_attr($input_class) : '' ) . '" name="' . esc_attr( $name ) . '" value="1"><span class="sh-toggle-slider"></span></label>';
        if ( $label ) $out .= '<span class="sh-text-xs sh-text-label sh-whitespace-nowrap">' . esc_html( $label ) . '</span>';
        $out .= '</span>';
        return $out;
    }

    // ---------------- Render Page ----------------------------------------------

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        $tab   = sanitize_key( $_GET['tab'] ?? 'menu' );
        $tabs  = [ 'menu' => '📌 Menu' ];
        $nonce = wp_create_nonce( 'sh_gs_nonce' );
        $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
        $sub_tab = sanitize_key( $_GET['sub'] ?? 'general' );

        $all_tabs = [ 'general' => 'General', 'menu' => 'Menu', 'defaults' => 'Defaults' ];
        if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) {
            $all_tabs['woocommerce'] = 'WooCommerce';
        }
        $all_tabs['social'] = '🔗 Social Share';
        if ( function_exists( 'pll_languages_list' ) ) {
            $all_tabs['translations'] = 'Translations';
        }
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
            $all_tabs['breadcrumb'] = 'Breadcrumb';
        }
        $all_tabs['security'] = '🔒 Security';
        if ( ! array_key_exists( $sub_tab, $all_tabs ) ) $sub_tab = 'menu';

        // TinyMCE bootstrap — gizli dummy editor, wp.editor'ün yüklenmesini garantiler
        // Product Tabs HTML içerik alanları lazy init için gerekli
        ob_start();
        wp_editor( '', 'sh_gs_dummy_mce', [
            'tinymce'      => true,
            'quicktags'    => true,
            'media_buttons'=> false,
        ] );
        ob_end_clean();
        ?>
        <div class="sh-wrap" id="sh-general-settings-page">

        <!-- Toolbar -->
        <div class="sh-toolbar" style="position:sticky;top:32px">
            <h1>⚙️ General Settings</h1>
        </div>

        <!-- Toast -->
        <div id="sh-gs-toast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.2)"></div>

        <!-- Tab Content -->
        <?php
        try {
            self::renderMainCard( $sub_tab, $nonce, $ajax, $all_tabs );
        } catch ( \Throwable $e ) {
            echo '<div class="sh-card sh-card-body" style="color:#dc2626;font-size:12px"><strong>Hata:</strong> ' . esc_html( $e->getMessage() ) . ' <br>File: ' . esc_html( $e->getFile() ) . ':' . (int) $e->getLine() . '</div>';
        }
        ?>

        </div><!-- /.sh-wrap -->
        <?php
    }

    private static function renderMainCard( string $sub_tab, string $nonce, string $ajax, array $all_tabs ): void
    {
        ?>
        <!-- ANA KART -->
        <div class="sh-card sh-mt-sm">

            <!-- sh-card-header: tab nav -->
            <div class="sh-card-header">
                <div class="sh-tab-group">
                    <?php foreach ( $all_tabs as $slug => $label ) : ?>
                    <button type="button"
                        class="sh-tab-btn sh-stab-btn<?php echo $slug === $sub_tab ? ' active' : ''; ?>"
                        data-tab="<?php echo esc_attr( $slug ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- sh-card-body -->
            <div class="sh-card-body">

                <!-- TAB: Menu -->
                <div class="sh-stab-content" id="sh-stab-menu" style="<?php echo $sub_tab !== 'menu' ? 'display:none' : ''; ?>">
                    <?php self::renderMenuTab( $nonce, $ajax ); ?>
                </div>

                <!-- TAB: General -->
                <div class="sh-stab-content" id="sh-stab-general" style="<?php echo $sub_tab !== 'general' ? 'display:none' : ''; ?>">
                    <?php self::renderGeneralTab( $nonce, $ajax ); ?>
                </div>

                <?php if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) : ?>
                <!-- TAB: WooCommerce -->
                <div class="sh-stab-content" id="sh-stab-woocommerce" style="<?php echo $sub_tab !== 'woocommerce' ? 'display:none' : ''; ?>">
                    <?php self::renderWooTab( $nonce, $ajax ); ?>
                </div>
                <?php endif; ?>

                <?php if ( function_exists( 'pll_languages_list' ) ) : ?>
                <!-- TAB: Translations -->
                <div class="sh-stab-content" id="sh-stab-translations" style="<?php echo $sub_tab !== 'translations' ? 'display:none' : ''; ?>">
                    <?php self::renderTranslationsTab( $nonce, $ajax ); ?>
                </div>
                <?php endif; ?>

                <?php if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) : ?>
                <!-- TAB: Breadcrumb -->
                <div class="sh-stab-content" id="sh-stab-breadcrumb" style="<?php echo $sub_tab !== 'breadcrumb' ? 'display:none' : ''; ?>">
                    <?php self::renderBreadcrumbTab( $nonce, $ajax ); ?>
                </div>
                <?php endif; ?>

                <!-- TAB: Defaults -->
                <div class="sh-stab-content" id="sh-stab-defaults" style="<?php echo $sub_tab !== 'defaults' ? 'display:none' : ''; ?>">
                    <?php self::renderDefaultsTab( $nonce, $ajax ); ?>
                </div>

                <!-- TAB: Security -->
                <div class="sh-stab-content" id="sh-stab-security" style="<?php echo $sub_tab !== 'security' ? 'display:none' : ''; ?>">
                    <?php
                    if ( class_exists( '\SaltHareket\Security\Admin\SecurityAdmin' ) ) {
                        \SaltHareket\Security\Admin\SecurityAdmin::renderTab( $nonce, $ajax );
                    }
                    ?>
                </div>

                <!-- TAB: Social Share -->
                <div class="sh-stab-content" id="sh-stab-social" style="<?php echo $sub_tab !== 'social' ? 'display:none' : ''; ?>">
                    <?php
                    if ( class_exists( '\SaltHareket\SocialShare\Admin\SocialShareAdmin' ) ) {
                        \SaltHareket\SocialShare\Admin\SocialShareAdmin::renderTab( $nonce, $ajax );
                    }
                    ?>
                </div>

            </div><!-- /.sh-card-body -->
        </div><!-- /.sh-card ana -->

        <script>
        (function(){
            var btns = document.querySelectorAll('#sh-general-settings-page .sh-stab-btn');
            var tabs = document.querySelectorAll('#sh-general-settings-page .sh-stab-content');

            // Select init — data-sh-select attribute'lu tüm select'leri init et
            function initSelects(container) {
                if (!container || typeof window.shSelect === 'undefined') return;
                container.querySelectorAll('select[data-sh-select]').forEach(function(sel) {
                    if (!sel.dataset.shSelectInit) {
                        window.shSelect.init(sel, { placeholder: sel.dataset.placeholder || 'Seçiniz...' });
                    }
                });
            }

            // Sayfa yüklenince tüm tab'lardaki select'leri init et
            // sh-select.js jQuery tabanlı — DOM ready sonrası çalıştır
            jQuery(document).ready(function(){
                tabs.forEach(function(t) { initSelects(t); });
            });

            btns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var target = this.dataset.tab;
                    btns.forEach(function(b){ b.classList.remove('active'); });
                    this.classList.add('active');
                    tabs.forEach(function(t){
                        var isActive = t.id === 'sh-stab-' + target;
                        t.style.display = isActive ? '' : 'none';
                        if (isActive) initSelects(t);
                    });
                });
            });
        })();
        </script>
        <?php
    }

    private static function renderMenuTab( string $nonce, string $ajax ): void
    {
        $locations  = self::getMenuLocations();
        $populate   = self::getMenuPopulate();
        $skip_pt    = [ 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'template' ];

        $menu_slugs = FieldRegistry::choices('menu-names');
        $post_types_raw = FieldChoices::postTypes();
        $post_types     = [];
        foreach ( $post_types_raw as $slug => $label ) {
            if ( in_array( $slug, $skip_pt, true ) ) continue;
            $post_types[ $slug ] = $label;
        }
        if ( empty( $menu_slugs ) ) {
            $menus_raw = get_terms( [ 'taxonomy' => 'nav_menu', 'hide_empty' => false ] ) ?: [];
            foreach ( $menus_raw as $m ) $menu_slugs[ $m->slug ] = $m->name;
        }
        $orderby_choices     = [ 'none', 'ID', 'author', 'title', 'name', 'type', 'date', 'modified', 'parent', 'rand', 'comment_count', 'menu_order' ];
        $orderby_tax_choices = [ 'name', 'slug', 'term_group', 'term_id', 'count' ];
        ?>                <!-- CARD: MENU LOCATIONS -->
                <div class="sh-card">
                    <div class="sh-card-header--col">
                        <span class="sh-card-title">📌 Menu Locations</span>
                        <span class="sh-card-desc">Varsayılan <code>header-menu</code> ve <code>footer-menu</code>'ya ek özel lokasyonlar tanımla.</span>
                    </div>
                    <div class="sh-card-body">
                        <table id="sh-gs-locations-table" class="sh-w-full sh-mb-sm" style="border-collapse:collapse">
                            <thead><tr class="sh-bg-gray">
                                <th style="padding:8px 12px;text-align:left" class="sh-text-sm sh-font-semibold sh-text-label">Location Name</th>
                                <th style="padding:8px 12px;text-align:left" class="sh-text-sm sh-font-semibold sh-text-label">Slug (otomatik)</th>
                                <th style="width:40px"></th>
                            </tr></thead>
                            <tbody id="sh-gs-locations-body">
                            <?php foreach ( $locations as $i => $row ) : ?>
                            <tr class="sh-gs-loc-row" data-index="<?php echo $i; ?>">
                                <td style="padding:6px 12px"><input type="text" name="loc_name" value="<?php echo esc_attr( $row['name'] ); ?>"
                                    class="sh-input sh-gs-loc-name sh-w-full" placeholder="Örn: sidebar-menu"
                                    oninput="shGsUpdateSlug(this)"></td>
                                <td style="padding:6px 12px"><code class="sh-gs-loc-slug sh-text-xs sh-text-muted"><?php echo esc_html( sanitize_title( $row['name'] ) ); ?></code></td>
                                <td style="padding:6px 12px;text-align:center"><button type="button" class="sh-btn sh-btn-danger sh-gs-remove-loc" style="padding:3px 10px;font-size:12px">✖</button></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="sh-flex sh-gap-sm sh-mt-sm">
                            <button type="button" id="sh-gs-add-loc" class="sh-btn sh-btn-ghost">+ Lokasyon Ekle</button>
                            <button type="button" id="sh-gs-save-loc" class="sh-btn sh-btn-primary">Kaydet</button>
                        </div>
                    </div><!-- /.sh-card-body -->
                </div><!-- /.sh-card Menu Locations -->

                <!-- ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ CARD: POPULATE MENU ITEMS ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ÃÂ¢Ã¢â¬ÂÃ¢âÂ¬ -->
                <div class="sh-card sh-mb-0">
                    <div class="sh-card-header--col">
                        <span class="sh-card-title">📌 Populate Menu Items</span>
                        <span class="sh-card-desc">Menü item'larının altına post type veya taxonomy bazlı dinamik alt öğeler ekle.</span>
                    </div>
                    <div class="sh-card-body">
                        <div id="sh-gs-pop-rows">
                        <?php foreach ( $populate as $i => $row ) : ?>
                        <?php self::renderPopulateRow( $i, $row, $menu_slugs, $post_types, $skip_pt, $orderby_choices, $orderby_tax_choices ); ?>
                        <?php endforeach; ?>
                        </div>
                        <div class="sh-flex sh-gap-sm sh-mt-sm">
                            <button type="button" id="sh-gs-add-pop" class="sh-btn sh-btn-ghost">+ Kural Ekle</button>
                            <button type="button" id="sh-gs-save-pop" class="sh-btn sh-btn-primary">Kaydet</button>
                        </div>
                    </div><!-- /.sh-card-body -->
                </div><!-- /.sh-card Populate -->

        <!-- Templates (hidden) -->
        <template id="sh-gs-loc-tpl">
            <tr class="sh-gs-loc-row">
                <td style="padding:6px 12px"><input type="text" name="loc_name" value="" class="sh-input sh-gs-loc-name sh-w-full" placeholder="Örn: sidebar-menu" oninput="shGsUpdateSlug(this)"></td>
                <td style="padding:6px 12px"><code class="sh-gs-loc-slug sh-text-xs sh-text-muted"></code></td>
                <td style="padding:6px 12px;text-align:center"><button type="button" class="sh-btn sh-btn-danger sh-gs-remove-loc" style="padding:3px 10px;font-size:12px">✖</button></td>
            </tr>
        </template>

        <?php self::renderMenuTabJS( $nonce, $ajax, $menu_slugs, $post_types, $skip_pt, $orderby_choices, $orderby_tax_choices ); ?>
        <?php
    }

    private static function renderPopulateRow( int $i, array $row, array $menu_slugs, array $post_types, array $skip_pt, array $orderby_choices, array $orderby_tax_choices ): void
    {
        $sel_menu = $row['menu'] ?? '';
        $sel_pt   = $row['menu_item_post_type'] ?? '';
        $sel_tax  = $row['menu_item_taxonomy'] ?? '';
        $has_pt   = ! empty( $sel_pt );
        $has_tax  = ! empty( $sel_tax );
        $inp      = '';
        ?>
        <div class="sh-gs-pop-row sh-card-inset" data-index="<?php echo $i; ?>">

            <!-- ROW 1: Menü + Kaldır -->
            <div class="sh-flex sh-items-center sh-justify-between sh-pb-xs sh-mb-xs" style="border-bottom:1px solid #f0f0f1">
                <div class="sh-flex sh-items-center sh-gap-sm">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-whitespace-nowrap">Menü</label>
                    <select class="sh-gs-field sh-fw-menu" name="menu">
                        <?php foreach ( $menu_slugs as $slug => $name ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $sel_menu, $slug ); ?>><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="sh-gs-remove-pop sh-btn sh-btn-danger sh-text-sm">✖ Kaldır</button>
            </div>

            <!-- ROW 2: Post Type + fields -->
            <div class="sh-flex sh-items-end sh-gap-md sh-py-xs" style="border-bottom:1px solid #f5f5f5">
                <div>
                    <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Post Type</label>
                    <select class="sh-gs-field sh-gs-pt-select sh-fw-pt" name="menu_item_post_type">
                        <option value="">— Seç —</option>
                        <?php foreach ( $post_types as $pt_slug => $pt_label ) : ?>
                        <option value="<?php echo esc_attr( $pt_slug ); ?>" <?php selected( $sel_pt, $pt_slug ); ?>><?php echo esc_html( $pt_label ); ?> (<?php echo esc_html( $pt_slug ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sh-gs-pt-fields sh-flex sh-items-end sh-gap-md" style="display:<?php echo $has_pt ? 'flex' : 'none'; ?>!important">
                    <div>
                        <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Orderby</label>
                        <select class="sh-gs-field sh-fw-ob" name="orderby_post_type">
                            <?php foreach ( $orderby_choices as $ob ) : ?>
                            <option value="<?php echo $ob; ?>" <?php selected( $row['orderby_post_type'] ?? 'none', $ob ); ?>><?php echo $ob; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Order</label>
                        <select class="sh-gs-field sh-fw-ord" name="order_post_type">
                            <option value="ASC" <?php selected( $row['order_post_type'] ?? 'ASC', 'ASC' ); ?>>ASC</option>
                            <option value="DESC" <?php selected( $row['order_post_type'] ?? 'ASC', 'DESC' ); ?>>DESC</option>
                        </select>
                    </div>
                    <div class="sh-pb-xs" style="min-width:120px"><?php echo self::sw( 'all_post_type', ! empty( $row['all_post_type'] ), 'Tümü', '', 'sh-gs-all-pt' ); ?></div>
                    <div class="sh-gs-count-pt-wrap" style="min-width:80px;<?php echo ! empty( $row['all_post_type'] ) ? 'display:none' : ''; ?>">
                        <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Adet</label>
                        <input type="number" class="sh-gs-field sh-fw-num" name="post_per_page" value="<?php echo (int) ( $row['post_per_page'] ?? 0 ); ?>" min="0">
                    </div>
                    <div class="sh-pb-xs" style="min-width:120px"><?php echo self::sw( 'replace', ! empty( $row['replace'] ), 'Replace' ); ?></div>
                </div>
            </div>

            <!-- ROW 3: Taxonomy + fields -->
            <div class="sh-gs-tax-row sh-flex sh-items-end sh-gap-md sh-pt-xs" style="display:<?php echo $has_pt ? 'flex' : 'none'; ?>!important">
                <div>
                    <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Taxonomy</label>
                    <select class="sh-gs-field sh-gs-tax-select" name="menu_item_taxonomy" style="<?php echo $inp; ?>;width:200px;flex-shrink:0">
                        <option value="">— Taxonomy Yok —</option>
                        <?php if ( $sel_pt ) :
                            $taxs = get_object_taxonomies( $sel_pt, 'objects' );
                            foreach ( $taxs as $t ) : ?>
                            <option value="<?php echo esc_attr( $t->name ); ?>" <?php selected( $sel_tax, $t->name ); ?>><?php echo esc_html( $t->label ); ?> (<?php echo esc_html( $t->name ); ?>)</option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <div class="sh-gs-tax-fields sh-flex sh-items-end sh-gap-md" style="display:<?php echo $has_tax ? 'flex' : 'none'; ?>!important">
                    <div>
                        <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Orderby</label>
                        <select class="sh-gs-field" name="orderby_taxonomy" style="<?php echo $inp; ?>;min-width:120px">
                            <?php foreach ( $orderby_tax_choices as $ob ) : ?>
                            <option value="<?php echo $ob; ?>" <?php selected( $row['orderby_taxonomy'] ?? 'name', $ob ); ?>><?php echo $ob; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Order</label>
                        <select class="sh-gs-field sh-select" name="order_taxonomy" style="min-width:80px">
                            <option value="ASC" <?php selected( $row['order_taxonomy'] ?? 'ASC', 'ASC' ); ?>>ASC</option>
                            <option value="DESC" <?php selected( $row['order_taxonomy'] ?? 'ASC', 'DESC' ); ?>>DESC</option>
                        </select>
                    </div>
                    <div class="sh-pb-xs" style="min-width:120px"><?php echo self::sw( 'all_taxonomy', ! empty( $row['all_taxonomy'] ), 'Tümü', '', 'sh-gs-all-tax' ); ?></div>
                    <div class="sh-gs-count-tax-wrap" style="min-width:80px;<?php echo ! empty( $row['all_taxonomy'] ) ? 'display:none' : ''; ?>">
                        <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Adet</label>
                        <input type="number" class="sh-gs-field sh-input" name="number" value="<?php echo (int) ( $row['number'] ?? 1 ); ?>" min="1" style="min-width:80px;width:80px">
                    </div>
                    <div class="sh-pb-xs" style="min-width:120px"><?php echo self::sw( 'hide_empty', ! empty( $row['hide_empty'] ), 'Hide Empty' ); ?></div>
                    <div class="sh-pb-xs" style="min-width:120px"><?php echo self::sw( 'show_posts', ! empty( $row['show_posts'] ), 'Show Posts' ); ?></div>
                </div>
            </div>

        </div>
        <?php
    }

    private static function renderSeparator(): void
    {
        echo '<hr class="sh-divider">';
    }

    private static function renderMenuTabJS( string $nonce, string $ajax, array $menu_slugs, array $post_types, array $skip_pt, array $orderby_choices, array $orderby_tax_choices ): void
    {
        $menu_options_html = '';
        foreach ( $menu_slugs as $slug => $name ) {
            $menu_options_html .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</option>';
        }
        $pt_options_html = '<option value="">— Seç —</option>';
        foreach ( $post_types as $pt_slug => $pt_label ) {
            $pt_options_html .= '<option value="' . esc_attr( $pt_slug ) . '">' . esc_html( $pt_label ) . ' (' . esc_html( $pt_slug ) . ')</option>';
        }
        $ob_options     = implode( '', array_map( fn( $o ) => "<option value=\"{$o}\">{$o}</option>", $orderby_choices ) );
        $ob_tax_options = implode( '', array_map( fn( $o ) => "<option value=\"{$o}\">{$o}</option>", $orderby_tax_choices ) );
        ?>
        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;

            function toast(msg, ok) {
                var t = document.getElementById('sh-gs-toast');
                t.style.background = ok ? '#15803d' : '#dc2626';
                t.textContent = msg;
                t.style.display = 'block';
                setTimeout(function(){ t.style.display='none'; }, 2800);
            }

            // ─── Location helpers ───
            window.shGsUpdateSlug = function(inp) {
                var map = {'ı':'i','İ':'i','ğ':'g','Ğ':'g','ü':'u','Ü':'u','ş':'s','Ş':'s','ö':'o','Ö':'o','ç':'c','Ç':'c'};
                var val = inp.value;
                val = val.split('').map(function(c){ return map[c] || c; }).join('');
                var slug = val.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
                var row = inp.closest('tr');
                row.querySelector('.sh-gs-loc-slug').textContent = slug;
            };

            document.querySelector('#sh-gs-add-loc').addEventListener('click', function(){
                var tpl = document.querySelector('#sh-gs-loc-tpl').content.cloneNode(true);
                document.querySelector('#sh-gs-locations-body').appendChild(tpl);
            });

            document.querySelector('#sh-gs-locations-body').addEventListener('click', function(e){
                if (e.target.classList.contains('sh-gs-remove-loc')) {
                    e.target.closest('tr').remove();
                }
            });

            document.querySelector('#sh-gs-save-loc').addEventListener('click', function(){
                var rows = [];
                document.querySelectorAll('.sh-gs-loc-row').forEach(function(tr){
                    var name = tr.querySelector('.sh-gs-loc-name').value.trim();
                    if (name) rows.push({name: name});
                });
                var fd = new FormData();
                fd.append('action','sh_gs_save_menu_locations');
                fd.append('nonce', NONCE);
                rows.forEach(function(r,i){ fd.append('rows['+i+'][name]', r.name); });
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    toast(res.success ? '✔ Menu Locations kaydedildi.' : '✖ Hata.', res.success);
                });
            });

            // ─── Populate helpers ───
            var popIdx = <?php echo count( self::getMenuPopulate() ); ?>;

            function bindPopRow(row) {
                var ptSel    = row.querySelector('.sh-gs-pt-select');
                var taxSel   = row.querySelector('.sh-gs-tax-select');
                var ptFields = row.querySelector('.sh-gs-pt-fields');
                var taxRow   = row.querySelector('.sh-gs-tax-row');
                var taxFields = row.querySelector('.sh-gs-tax-fields');

                ptSel.addEventListener('change', function(){
                    var has = !!this.value;
                    ptFields.style.display = has ? 'flex' : 'none';
                    taxRow.style.display   = has ? 'flex' : 'none';
                    if (taxSel) {
                        taxSel.innerHTML = '<option value="">— Taxonomy Yok —</option>';
                        taxFields.style.display = 'none';
                    }
                    if (!has) return;
                    var fd2 = new FormData();
                    fd2.append('action','sh_gs_get_taxonomies');
                    fd2.append('nonce',NONCE);
                    fd2.append('post_type',this.value);
                    fetch(AJAX,{method:'POST',body:fd2}).then(r=>r.json()).then(function(res){
                        if (res.success) {
                            Object.entries(res.data).forEach(function([label,val]){
                                var opt = document.createElement('option');
                                opt.value = val; opt.textContent = label;
                                taxSel.appendChild(opt);
                            });
                        }
                    });
                });

                if (taxSel) {
                    taxSel.addEventListener('change', function(){
                        taxFields.style.display = this.value ? 'flex' : 'none';
                    });
                }

                // Tümü toggle → Adet hide/show (document delegation)
                document.addEventListener('change', function(e) {
                    if (e.target.classList.contains('sh-gs-all-pt')) {
                        var ptFields = e.target.closest('.sh-gs-pt-fields');
                        if (ptFields) {
                            var wrap = ptFields.querySelector('.sh-gs-count-pt-wrap');
                            console.log('PT wrap found:', wrap, 'checked:', e.target.checked);
                            if (wrap) wrap.style.display = e.target.checked ? 'none' : 'block';
                        } else {
                            // closest çalışmıyorsa parentElement ile dene
                            var parent = e.target.parentElement && e.target.parentElement.parentElement;
                            if (parent) {
                                var wrap2 = parent.querySelector('.sh-gs-count-pt-wrap');
                                console.log('PT wrap2 found via parent:', wrap2);
                                if (wrap2) wrap2.style.display = e.target.checked ? 'none' : 'block';
                            }
                        }
                    }
                    if (e.target.classList.contains('sh-gs-all-tax')) {
                        var taxFields = e.target.closest('.sh-gs-tax-fields');
                        if (taxFields) {
                            var wrap = taxFields.querySelector('.sh-gs-count-tax-wrap');
                            if (wrap) wrap.style.display = e.target.checked ? 'none' : 'block';
                        } else {
                            var parent = e.target.parentElement && e.target.parentElement.parentElement;
                            if (parent) {
                                var wrap2 = parent.querySelector('.sh-gs-count-tax-wrap');
                                if (wrap2) wrap2.style.display = e.target.checked ? 'none' : 'block';
                            }
                        }
                    }
                });

                row.querySelector('.sh-gs-remove-pop').addEventListener('click', function(){
                    row.remove();
                });
            }

            document.querySelectorAll('.sh-gs-pop-row').forEach(bindPopRow);

            // Tümü toggle → Adet — sayfa yüklenince tek sefer, #sh-gs-pop-rows üzerinde delegation
            document.getElementById('sh-gs-pop-rows').addEventListener('change', function(e) {
                if (e.target.name === 'all_post_type') {
                    var ptFields = e.target.closest('.sh-gs-pt-fields');
                    if (ptFields) {
                        var wrap = ptFields.querySelector('.sh-gs-count-pt-wrap');
                        if (wrap) wrap.style.display = e.target.checked ? 'none' : 'block';
                    }
                }
                if (e.target.name === 'all_taxonomy') {
                    var taxFields = e.target.closest('.sh-gs-tax-fields');
                    if (taxFields) {
                        var wrap = taxFields.querySelector('.sh-gs-count-tax-wrap');
                        if (wrap) wrap.style.display = e.target.checked ? 'none' : 'block';
                    }
                }
            });

            document.querySelector('#sh-gs-add-pop').addEventListener('click', function(){
                var idx = popIdx++;
                var div = document.createElement('div');
                div.innerHTML = <?php echo wp_json_encode( self::getEmptyPopRowHtml( $menu_options_html, $pt_options_html, $ob_options, $ob_tax_options ) ); ?>.replace(/\{IDX\}/g, idx);
                var row = div.firstElementChild;
                document.querySelector('#sh-gs-pop-rows').appendChild(row);
                bindPopRow(row);
            });

            document.querySelector('#sh-gs-save-pop').addEventListener('click', function(){
                var rows = [];
                document.querySelectorAll('.sh-gs-pop-row').forEach(function(row){
                    var obj = {};
                    row.querySelectorAll('.sh-gs-field').forEach(function(f){
                        if (f.type==='checkbox') { obj[f.name] = f.checked ? '1' : '0'; }
                        else { obj[f.name] = f.value; }
                    });
                    rows.push(obj);
                });
                var fd = new FormData();
                fd.append('action','sh_gs_save_menu_populate');
                fd.append('nonce',NONCE);
                rows.forEach(function(r,i){
                    Object.entries(r).forEach(function([k,v]){ fd.append('rows['+i+']['+k+']',v); });
                });
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    toast(res.success ? '✔ Populate kuralları kaydedildi.' : '✖ Hata.', res.success);
                });
            });
        })();
        </script>
        <?php
    }

    private static function getEmptyPopRowHtml( string $menu_opts, string $pt_opts, string $ob_opts, string $ob_tax_opts ): string
    {
        $sw_all_pt  = self::swTpl( 'all_post_type', 'Tümü', 'sh-gs-all-pt' );
        $sw_replace = self::swTpl( 'replace', 'Replace' );
        $sw_all_tax = self::swTpl( 'all_taxonomy', 'Tümü', 'sh-gs-all-tax' );
        $sw_hide    = self::swTpl( 'hide_empty', 'Hide Empty' );
        $sw_posts   = self::swTpl( 'show_posts', 'Show Posts' );
        return
            '<div class="sh-gs-pop-row sh-card-inset" data-index="{IDX}">'
            . '<!-- ROW 1 -->'
            . '<div class="sh-flex sh-items-center sh-justify-between sh-pb-xs sh-mb-xs" style="border-bottom:1px solid #f0f0f1">'
            .   '<div class="sh-flex sh-items-center sh-gap-sm">'
            .     '<label class="sh-text-sm sh-font-semibold sh-text-label sh-whitespace-nowrap">Menü</label>'
            .     '<select class="sh-gs-field sh-fw-menu" name="menu">' . $menu_opts . '</select>'
            .   '</div>'
            .   '<button type="button" class="sh-gs-remove-pop sh-btn sh-btn-danger" style="padding:3px 10px;font-size:12px">✖ Kaldır</button>'
            . '</div>'
            . '<!-- ROW 2: Post Type + fields -->'
            . '<div class="sh-flex sh-items-end sh-gap-md sh-py-xs" style="border-bottom:1px solid #f5f5f5">'
            .   '<div>'
            .     '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Post Type</label>'
            .     '<select class="sh-gs-field sh-gs-pt-select sh-fw-pt" name="menu_item_post_type">' . $pt_opts . '</select>'
            .   '</div>'
            .   '<div class="sh-gs-pt-fields sh-flex sh-items-end sh-gap-md" style="display:none!important">'
            .     '<div>'
            .       '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Orderby</label>'
            .       '<select class="sh-gs-field sh-fw-ob" name="orderby_post_type">' . $ob_opts . '</select>'
            .     '</div>'
            .     '<div>'
            .       '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Order</label>'
            .       '<select class="sh-gs-field sh-fw-ord" name="order_post_type"><option value="ASC">ASC</option><option value="DESC">DESC</option></select>'
            .     '</div>'
            .     '<div class="sh-pb-xs" style="min-width:120px">' . $sw_all_pt . '</div>'
            .     '<div class="sh-gs-count-pt-wrap" style="min-width:80px">'
            .       '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Adet</label>'
            .       '<input type="number" class="sh-gs-field sh-fw-num" name="post_per_page" value="0" min="0">'
            .     '</div>'
            .     '<div class="sh-pb-xs" style="min-width:120px">' . $sw_replace . '</div>'
            .   '</div>'
            . '</div>'
            . '<!-- ROW 3: Taxonomy + fields -->'
            . '<div class="sh-gs-tax-row sh-flex sh-items-end sh-gap-md sh-pt-xs" style="display:none!important">'
            .   '<div>'
            .     '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Taxonomy</label>'
            .     '<select class="sh-gs-field sh-gs-tax-select sh-fw-pt" name="menu_item_taxonomy"><option value="">— Taxonomy Yok —</option></select>'
            .   '</div>'
            .   '<div class="sh-gs-tax-fields sh-flex sh-items-end sh-gap-md" style="display:none!important">'
            .     '<div>'
            .       '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Orderby</label>'
            .       '<select class="sh-gs-field sh-fw-ob" name="orderby_taxonomy">' . $ob_tax_opts . '</select>'
            .     '</div>'
            .     '<div>'
            .       '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Order</label>'
            .       '<select class="sh-gs-field sh-fw-ord" name="order_taxonomy"><option value="ASC">ASC</option><option value="DESC">DESC</option></select>'
            .     '</div>'
            .     '<div class="sh-pb-xs" style="min-width:120px">' . $sw_all_tax . '</div>'
            .     '<div class="sh-gs-count-tax-wrap" style="min-width:80px">'
            .       '<label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Adet</label>'
            .       '<input type="number" class="sh-gs-field sh-fw-num" name="number" value="1" min="1">'
            .     '</div>'
            .     '<div class="sh-pb-xs" style="min-width:120px">' . $sw_hide . '</div>'
            .     '<div class="sh-pb-xs" style="min-width:120px">' . $sw_posts . '</div>'
            .   '</div>'
            . '</div>'
            . '</div>';
    }

    // ─── AJAX: General ───

    public static function ajaxSaveGeneral(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $taxonomy_prefix_remove = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['taxonomy_prefix_remove'] ?? [] ) ) ) );
        $exclude_from_search    = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['exclude_from_search']    ?? [] ) ) ) );
        $add_sticky_support     = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['add_sticky_support']     ?? [] ) ) ) );
        $contact_post_types     = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['contact_post_types']     ?? [] ) ) ) );

        update_option( 'options_taxonomy_prefix_remove', $taxonomy_prefix_remove, false );
        update_option( 'options_exclude_from_search',    $exclude_from_search,    false );
        update_option( 'options_add_sticky_support',     $add_sticky_support,     false );
        update_option( 'options_contact_post_types',     $contact_post_types,     false );

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        // Taxonomy prefix değişince bir sonraki sayfa yükleniminde rewrite flush edilsin
        // AJAX context'te flush_rewrite_rules() çağrılmamalı (site_url() undefined hatası)
        update_option( 'sh_gs_needs_rewrite_flush', 1 );

        wp_send_json_success( 'General ayarları kaydedildi.' );
    }

    // ─── Render: General Tab ───
    private static function renderGeneralTab( string $nonce, string $ajax ): void
    {
        $taxonomy_prefix_remove = (array) get_option( 'options_taxonomy_prefix_remove', [] );
        $exclude_from_search    = (array) get_option( 'options_exclude_from_search',    [] );
        $add_sticky_support     = (array) get_option( 'options_add_sticky_support',     [] );
        $contact_post_types     = (array) get_option( 'options_contact_post_types',     [] );

        // FieldRegistry üzerinden veri çek
        $taxonomies  = FieldChoices::taxonomies();
        $post_types  = FieldChoices::postTypes();

        // ─── Logos Card ───
        self::renderLogosCard( $nonce, $ajax );
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">⚙️ General</span>
                <span class="sh-card-desc">Genel site ayarları — arama, yapışkan içerik, iletişim post type'ları.</span>
            </div>
            <div class="sh-card-body">

                <!-- taxonomy_prefix_remove -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Remove Taxonomy URL Prefix</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Seçilen taxonomy'lerin URL'inden prefix kaldırılır. Örn: <code>/category/</code> → <code>/</code></p>
                    <select id="sh-gen-tax-prefix" name="taxonomy_prefix_remove[]" multiple data-sh-select data-placeholder="Taxonomy seç..." style="width:100%">
                        <?php foreach ( $taxonomies as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected( in_array($slug,$taxonomy_prefix_remove,true) ); ?>><?php echo esc_html($label); ?> (<?php echo esc_html($slug); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- exclude_from_search -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Exclude from Search</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Seçilen post type'lar WP aramasından hariç tutulur. <code>EXCLUDE_FROM_SEARCH</code> tanımlar.</p>
                    <select id="sh-gen-exclude-search" name="exclude_from_search[]" multiple data-sh-select data-placeholder="Post type seç..." style="width:100%">
                        <?php foreach ( $post_types as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected( in_array($slug,$exclude_from_search,true) ); ?>><?php echo esc_html($label); ?> (<?php echo esc_html($slug); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- add_sticky_support -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Add Sticky Support</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Seçilen post type'lara "yapışkan içerik" (sticky) desteği eklenir.</p>
                    <select id="sh-gen-sticky" name="add_sticky_support[]" multiple data-sh-select data-placeholder="Post type seç..." style="width:100%">
                        <?php foreach ( $post_types as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected( in_array($slug,$add_sticky_support,true) ); ?>><?php echo esc_html($label); ?> (<?php echo esc_html($slug); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- contact_post_types -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Contact Post Types</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">ACF iletişim field group'unun ek olarak atanacağı post type'lar.</p>
                    <select id="sh-gen-contact-pt" name="contact_post_types[]" multiple data-sh-select data-placeholder="Post type seç..." style="width:100%">
                        <?php foreach ( $post_types as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected( in_array($slug,$contact_post_types,true) ); ?>><?php echo esc_html($label); ?> (<?php echo esc_html($slug); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm">
                    <button type="button" id="sh-gs-save-general" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-general-msg" class="sh-text-sm" style="display:none;color:#15803d">✔ Kaydedildi</span>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
            function getMultiple(id){ return Array.from(document.getElementById(id).selectedOptions).map(function(o){return o.value;}); }
            var _shSaveEl=document.getElementById('sh-gs-save-general'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var fd = new FormData(); fd.append('action','sh_gs_save_general'); fd.append('nonce',NONCE);
                getMultiple('sh-gen-tax-prefix').forEach(function(v){ fd.append('taxonomy_prefix_remove[]',v); });
                getMultiple('sh-gen-exclude-search').forEach(function(v){ fd.append('exclude_from_search[]',v); });
                getMultiple('sh-gen-sticky').forEach(function(v){ fd.append('add_sticky_support[]',v); });
                getMultiple('sh-gen-contact-pt').forEach(function(v){ fd.append('contact_post_types[]',v); });
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    var m=document.getElementById('sh-gs-general-msg');
                    m.textContent=res.success?'✔ Kaydedildi':'✖ Hata';
                    m.style.display='inline'; setTimeout(function(){m.style.display='none';},2800);
                });
            });
        })();
        </script>
        <?php

        // ─── Pagination Card ───
        self::renderPaginationCard( $nonce, $ajax );

        // ─── Maps Card ───
        self::renderMapsCard( $nonce, $ajax );
    }

    public static function ajaxSaveWoo(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );
        $enable_cart         = ! empty( $_POST['enable_cart'] );
        $enable_api          = ! empty( $_POST['enable_woo_api'] );
        $disable_frontend    = ! empty( $_POST['disable_wc_frontend'] );
        update_option( 'options_enable_cart',          $enable_cart,       false );
        update_option( 'options_enable_woo_api',       $enable_api,        false );
        update_option( 'options_disable_wc_frontend',  $disable_frontend,  false );
        $local = [
            'client'        => sanitize_text_field( $_POST['woo_api_local_client']  ?? '' ),
            'client_secret' => sanitize_text_field( $_POST['woo_api_local_secret']  ?? '' ),
        ];
        $live = [
            'client'        => sanitize_text_field( $_POST['woo_api_live_client']   ?? '' ),
            'client_secret' => sanitize_text_field( $_POST['woo_api_live_secret']   ?? '' ),
        ];
        update_option( 'options_woo_api_local', $local, false );
        update_option( 'options_woo_api_live',  $live,  false );

        // Single product ayarları
        $woo_single = [
            'auto_select_first_variation' => ! empty( $_POST['auto_select_first_variation'] ) && $_POST['auto_select_first_variation'] === '1',
        ];
        update_option( 'sh_woo_single', $woo_single, false );
        // Eski options_ key'i temizle (migration)
        delete_option( 'options_woo_single' );

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();
        wp_send_json_success( 'WooCommerce ayarları kaydedildi.' );
    }

    /// ─── AJAX: Product Card (tease) ───

    public static function ajaxSaveTease(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $bool_fields = [ 'multiple_images', 'show_variations', 'swatch_tooltip', 'show_oos_swatches',
                         'show_sale_badge', 'show_feat_badge', 'show_oos_badge', 'show_category', 'show_price' ];
        $tease = [];
        foreach ( $bool_fields as $f ) {
            $tease[ $f ] = ! empty( $_POST[ $f ] ) && $_POST[ $f ] === '1';
        }
        $tease['swatch_limit']  = max( 1, min( 999, (int) ( $_POST['swatch_limit'] ?? 115 ) ) );
        $tease['img_ratio']     = in_array( $_POST['img_ratio']    ?? '', [ '1x1','4x3','3x4','16x9' ], true )
                                    ? sanitize_key( $_POST['img_ratio'] ) : '1x1';
        $tease['default_size']  = in_array( $_POST['default_size'] ?? '', [ 'xs','sm','md','lg' ], true )
                                    ? sanitize_key( $_POST['default_size'] ) : 'md';        $tease['bundle_cta']    = in_array( $_POST['bundle_cta']   ?? '', [ 'view','cart' ], true )
                                    ? sanitize_key( $_POST['bundle_cta'] ) : 'view';

        update_option( 'options_tease_settings', $tease, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();
        wp_send_json_success( 'Product Card ayarları kaydedildi.' );
    }

    /// ─── AJAX: Product Gallery ───

    public static function ajaxSaveGallery(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $gallery = [];

        // Tip
        $gallery['type'] = in_array( $_POST['gallery_type'] ?? '', [ 'single','swiper','stack','grid' ], true )
            ? sanitize_key( $_POST['gallery_type'] ) : 'swiper';

        // Görsel
        $gallery['ratio']      = in_array( $_POST['gallery_ratio'] ?? '', [ '1x1','4x3','3x4','16x9','original' ], true )
            ? sanitize_key( $_POST['gallery_ratio'] ) : '4x3';
        $gallery['object_fit'] = in_array( $_POST['gallery_object_fit'] ?? '', [ 'cover','contain' ], true )
            ? sanitize_key( $_POST['gallery_object_fit'] ) : 'cover';

        // Hover efekti
        $gallery['hover'] = in_array( $_POST['gallery_hover'] ?? '', [ 'none','zoom','lightbox','both' ], true )
            ? sanitize_key( $_POST['gallery_hover'] ) : 'lightbox';

        // Boolean alanlar
        $bool_fields = [ 'lightbox', 'navigation', 'loop', 'thumbs', 'variation_update' ];
        foreach ( $bool_fields as $f ) {
            $gallery[ $f ] = ! empty( $_POST[ 'gallery_' . $f ] ) && $_POST[ 'gallery_' . $f ] === '1';
        }

        // Swiper ayarları
        $gallery['pagination'] = in_array( $_POST['gallery_pagination'] ?? '', [ 'none','bullets','fraction','progressbar' ], true )
            ? sanitize_key( $_POST['gallery_pagination'] ) : 'bullets';
        $gallery['slides_per_view'] = max( 1, min( 5, (int) ( $_POST['gallery_slides_per_view'] ?? 1 ) ) );
        $gallery['thumb_count']     = max( 2, min( 10, (int) ( $_POST['gallery_thumb_count'] ?? 4 ) ) );
        $gallery['thumb_nav']       = ! empty( $_POST['gallery_thumb_nav'] ) && $_POST['gallery_thumb_nav'] === '1';
        $gallery['stack_gap']       = max( 0, min( 64, (int) ( $_POST['gallery_stack_gap'] ?? 8 ) ) );

        update_option( 'options_gallery_settings', $gallery, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        // Gallery settings değişince PAE'de en zengin product'ı yeniden fetch et
        // Böylece data-required-js güncellenip zoom/lightgallery anında aktif olur
        if ( class_exists('PageAssetsExtractor') ) {
            try {
                $extractor = \PageAssetsExtractor::get_instance();
                $extractor->force_rebuild = true;

                // En zengin product'ı bul — find_richest_post() mantığıyla aynı:
                // Önce en fazla galeri resmi olan simple product (redirect riski yok)
                // Sonra en fazla varyasyonu olan variable product
                global $wpdb;
                $product_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT p.ID
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->posts} v ON v.post_parent = p.ID AND v.post_type = 'product_variation'
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_product_image_gallery'
                     WHERE p.post_type = %s AND p.post_status = 'publish'
                       AND v.ID IS NULL
                       AND pm.meta_value != ''
                     ORDER BY CHAR_LENGTH(pm.meta_value) DESC
                     LIMIT 1",
                    'product'
                ) );

                if ( ! $product_id ) {
                    // Simple product yoksa en fazla varyasyonu olan variable product
                    $product_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT p.ID FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->posts} v ON v.post_parent = p.ID
                           AND v.post_type = 'product_variation' AND v.post_status = 'publish'
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                         GROUP BY p.ID ORDER BY COUNT(v.ID) DESC LIMIT 1",
                        'product'
                    ) );
                }

                if ( ! $product_id ) {
                    $product_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts}
                         WHERE post_type = %s AND post_status = 'publish'
                         ORDER BY ID DESC LIMIT 1",
                        'product'
                    ) );
                }

                error_log('[Gallery Settings] product_id=' . $product_id . ' (simple product with gallery)' );

                if ( $product_id ) {
                    $fetch_result = $extractor->fetch_post_url( $product_id );
                    error_log('[Gallery Settings] fetch_result=' . json_encode( is_array($fetch_result) ? array_keys($fetch_result) : $fetch_result ) );

                    // Fetch sonrası meta'yı oku — gallery plugin'leri yoksa en fazla plugin'li product'ı kaynak seç
                    $source_meta      = \PageAssetsExtractor::get_post_assets_safe( $product_id );
                    $gallery_plugins  = [ 'swiper', 'lightgallery', 'jquery-zoom' ];
                    $existing_plugins = is_array( $source_meta ) ? ( $source_meta['plugins'] ?? [] ) : [];
                    $missing          = array_diff( $gallery_plugins, $existing_plugins );

                    if ( ! empty( $missing ) ) {
                        // Bu ürünün HTML'inde gallery yok — meta'sında en fazla plugin'i olan product'ı bul
                        $candidate_ids = $wpdb->get_col( $wpdb->prepare(
                            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND ID != %d LIMIT 100",
                            'product', $product_id
                        ) );
                        $best_count = count( $existing_plugins );
                        foreach ( $candidate_ids as $pid ) {
                            $pmeta  = \PageAssetsExtractor::get_post_assets_safe( (int) $pid );
                            $pcount = is_array($pmeta) ? count( $pmeta['plugins'] ?? [] ) : 0;
                            if ( $pcount > $best_count ) {
                                $best_count = $pcount;
                                $product_id = (int) $pid;
                            }
                        }
                        error_log('[Gallery Settings] switched to best source product_id=' . $product_id . ' plugin_count=' . $best_count );
                    }

                    // Seçilen kaynak ürünün CSS/JS değerlerini logla
                    $src = \PageAssetsExtractor::get_post_assets_safe( $product_id );
                    error_log('[Gallery Settings] source css_page=' . ( $src['css_page'] ?? 'EMPTY' ) . ' plugin_css=' . ( $src['plugin_css'] ?? 'EMPTY' ) . ' plugin_js=' . ( $src['plugin_js'] ?? 'EMPTY' ) . ' plugins=' . implode(',', $src['plugins'] ?? [] ) );

                    // plugin_css boşsa kaynak ürünü de yeniden fetch et
                    if ( empty( $src['plugin_css'] ?? '' ) ) {
                        error_log('[Gallery Settings] plugin_css empty — re-fetching source #' . $product_id );
                        $extractor->force_rebuild = true;
                        $extractor->fetch_post_url( $product_id );
                        $src = \PageAssetsExtractor::get_post_assets_safe( $product_id );
                        error_log('[Gallery Settings] re-fetch done: plugin_css=' . ( $src['plugin_css'] ?? 'EMPTY' ) );
                    }

                    $apply_count = $extractor->grouped_apply_assets( $product_id, 'product', 'post' );
                    error_log('[Gallery Settings] grouped_apply_assets count=' . $apply_count );
                }
            } catch ( \Throwable $e ) {
                error_log('[Gallery Settings] ERROR: ' . $e->getMessage() );
            }
        } else {
            error_log('[Gallery Settings] PageAssetsExtractor class not found');
        }

        wp_send_json_success( 'Product Gallery ayarları kaydedildi.' );
    }

    /// ─── AJAX: Qty Stepper ───

    public static function ajaxSaveQtyStepper(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $valid_sizes    = [ 'sm', 'md', 'lg' ];
        $valid_variants = [ '', 'pill', 'filled', 'pill filled' ];

        $qty = [
            'size'    => in_array( $_POST['qty_size']    ?? 'md', $valid_sizes,    true ) ? sanitize_key( $_POST['qty_size'] )          : 'md',
            'variant' => in_array( $_POST['qty_variant'] ?? '',   $valid_variants, true ) ? sanitize_text_field( $_POST['qty_variant'] ) : '',
        ];

        update_option( 'options_qty_stepper', $qty, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        wp_send_json_success( 'Qty Stepper ayarları kaydedildi.' );
    }

    /// ─── AJAX: Sticky ATC ───

    public static function ajaxSaveStickyAtc(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $cfg = [
            'enabled'         => ! empty( $_POST['sticky_atc_enabled'] ) && $_POST['sticky_atc_enabled'] === '1',
            'manual_placement'=> ! empty( $_POST['sticky_atc_manual'] )  && $_POST['sticky_atc_manual']  === '1',
            'position'        => in_array( $_POST['sticky_atc_position'] ?? 'bottom', [ 'top', 'bottom' ], true )
                                    ? sanitize_key( $_POST['sticky_atc_position'] ) : 'bottom',
            'show_image'      => ! empty( $_POST['sticky_atc_show_image'] ) && $_POST['sticky_atc_show_image'] === '1',
            'show_price'      => ! empty( $_POST['sticky_atc_show_price'] ) && $_POST['sticky_atc_show_price'] === '1',
            'show_reactions'  => ! empty( $_POST['sticky_atc_show_reactions'] ) && $_POST['sticky_atc_show_reactions'] === '1',
            'threshold'       => max( 0, min( 2000, (int) ( $_POST['sticky_atc_threshold'] ?? 300 ) ) ),
            'btn_text'        => sanitize_text_field( $_POST['sticky_atc_btn_text'] ?? '' ),
        ];

        update_option( 'options_sticky_atc', $cfg, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        wp_send_json_success( 'Sticky ATC ayarları kaydedildi.' );
    }

    /// ─── AJAX: Product Lists (Related/Upsell/Cross-sell/Recently Viewed) ───

    public static function ajaxSaveProductLists(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $valid_layouts    = [ 'grid', 'carousel' ];
        $valid_sizes      = [ 'sm', 'md', 'lg' ];
        $valid_sources    = [ 'wc_related', 'same_category', 'same_tag', 'manual' ];
        $valid_crosssell  = [ 'product_page', 'cart_page', 'both' ];
        $valid_nav        = [ 'none', 'arrows', 'dots', 'scrollbar', 'arrows_dots' ];

        // Tüm global breakpoint'leri al — xs,sm,md,lg,xl,xxl,xxxl
        $all_bps = array_keys( (array) \Data::get('breakpoints') );
        if ( empty( $all_bps ) ) {
            $all_bps = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
        }

        $default_spv  = [ 'xs'=>1.5, 'sm'=>2, 'md'=>3, 'lg'=>4, 'xl'=>4, 'xxl'=>5, 'xxxl'=>5 ];
        $default_gaps = [ 'xs'=>10, 'sm'=>12, 'md'=>15, 'lg'=>20, 'xl'=>20, 'xxl'=>24, 'xxxl'=>24 ];

        // Breakpoints helper — her breakpoint için spv ve gap parse et, boşsa default kullan
        $parse_bp = function( string $prefix ) use ( $all_bps, $default_spv, $default_gaps ) : array {
            $spv  = [];
            $gaps = [];
            foreach ( $all_bps as $bp ) {
                $spv_val = isset( $_POST[ $prefix . '_spv_' . $bp ] ) && $_POST[ $prefix . '_spv_' . $bp ] !== ''
                    ? (float) $_POST[ $prefix . '_spv_' . $bp ]
                    : ( $default_spv[ $bp ] ?? 1 );
                $gap_val = isset( $_POST[ $prefix . '_gap_' . $bp ] ) && $_POST[ $prefix . '_gap_' . $bp ] !== ''
                    ? (int) $_POST[ $prefix . '_gap_' . $bp ]
                    : ( $default_gaps[ $bp ] ?? 15 );
                $spv[ $bp ]  = min( 12, max( 0.5, $spv_val ) );
                $gaps[ $bp ] = min( 60, max( 0, $gap_val ) );
            }
            return [ 'spv' => $spv, 'gaps' => $gaps ];
        };

        $lists = [
            'related' => [
                'enabled'     => ! empty( $_POST['related_enabled'] ) && $_POST['related_enabled'] === '1',
                'title'       => sanitize_text_field( $_POST['related_title']  ?? '' ),
                'count'       => max( 2, min( 24, (int) ( $_POST['related_count'] ?? 4 ) ) ),
                'layout'      => in_array( $_POST['related_layout'] ?? 'carousel', $valid_layouts, true ) ? $_POST['related_layout'] : 'carousel',
                'size'        => in_array( $_POST['related_size']   ?? 'md', $valid_sizes, true ) ? $_POST['related_size'] : 'md',
                'source'      => in_array( $_POST['related_source'] ?? 'wc_related', $valid_sources, true ) ? $_POST['related_source'] : 'wc_related',
                'nav_desktop' => in_array( $_POST['related_nav_desktop'] ?? 'arrows', $valid_nav, true ) ? $_POST['related_nav_desktop'] : 'arrows',
                'nav_mobile'  => in_array( $_POST['related_nav_mobile']  ?? 'dots',   $valid_nav, true ) ? $_POST['related_nav_mobile']  : 'dots',
                'breakpoints' => $parse_bp('related'),
            ],
            'upsell' => [
                'enabled'     => ! empty( $_POST['upsell_enabled'] ) && $_POST['upsell_enabled'] === '1',
                'title'       => sanitize_text_field( $_POST['upsell_title']  ?? '' ),
                'count'       => max( 2, min( 24, (int) ( $_POST['upsell_count'] ?? 4 ) ) ),
                'layout'      => in_array( $_POST['upsell_layout'] ?? 'carousel', $valid_layouts, true ) ? $_POST['upsell_layout'] : 'carousel',
                'size'        => in_array( $_POST['upsell_size']   ?? 'md', $valid_sizes, true ) ? $_POST['upsell_size'] : 'md',
                'nav_desktop' => in_array( $_POST['upsell_nav_desktop'] ?? 'arrows', $valid_nav, true ) ? $_POST['upsell_nav_desktop'] : 'arrows',
                'nav_mobile'  => in_array( $_POST['upsell_nav_mobile']  ?? 'dots',   $valid_nav, true ) ? $_POST['upsell_nav_mobile']  : 'dots',
                'breakpoints' => $parse_bp('upsell'),
            ],
            'crosssell' => [
                'enabled'     => ! empty( $_POST['crosssell_enabled'] ) && $_POST['crosssell_enabled'] === '1',
                'title'       => sanitize_text_field( $_POST['crosssell_title']  ?? '' ),
                'count'       => max( 2, min( 24, (int) ( $_POST['crosssell_count'] ?? 4 ) ) ),
                'layout'      => in_array( $_POST['crosssell_layout'] ?? 'carousel', $valid_layouts, true ) ? $_POST['crosssell_layout'] : 'carousel',
                'size'        => in_array( $_POST['crosssell_size']   ?? 'md', $valid_sizes, true ) ? $_POST['crosssell_size'] : 'md',
                'location'    => in_array( $_POST['crosssell_location'] ?? 'both', $valid_crosssell, true ) ? $_POST['crosssell_location'] : 'both',
                'nav_desktop' => in_array( $_POST['crosssell_nav_desktop'] ?? 'arrows', $valid_nav, true ) ? $_POST['crosssell_nav_desktop'] : 'arrows',
                'nav_mobile'  => in_array( $_POST['crosssell_nav_mobile']  ?? 'dots',   $valid_nav, true ) ? $_POST['crosssell_nav_mobile']  : 'dots',
                'breakpoints' => $parse_bp('crosssell'),
            ],
            'recently_viewed' => [
                'enabled'         => ! empty( $_POST['rv_enabled'] ) && $_POST['rv_enabled'] === '1',
                'title'           => sanitize_text_field( $_POST['rv_title']  ?? '' ),
                'count'           => max( 2, min( 24, (int) ( $_POST['rv_count'] ?? 6 ) ) ),
                'layout'          => in_array( $_POST['rv_layout'] ?? 'carousel', $valid_layouts, true ) ? $_POST['rv_layout'] : 'carousel',
                'size'            => in_array( $_POST['rv_size']   ?? 'md', $valid_sizes, true ) ? $_POST['rv_size'] : 'md',
                'exclude_current' => ! empty( $_POST['rv_exclude_current'] ) && $_POST['rv_exclude_current'] === '1',
                'nav_desktop'     => in_array( $_POST['rv_nav_desktop'] ?? 'arrows', $valid_nav, true ) ? $_POST['rv_nav_desktop'] : 'arrows',
                'nav_mobile'      => in_array( $_POST['rv_nav_mobile']  ?? 'dots',   $valid_nav, true ) ? $_POST['rv_nav_mobile']  : 'dots',
                'breakpoints'     => $parse_bp('rv'),
            ],
        ];

        update_option( 'options_product_lists', $lists, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        wp_send_json_success( 'Product Lists ayarları kaydedildi.' );
    }

    /// ─── AJAX: Product Tabs ───

    public static function ajaxSaveProductTabs(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $mode       = in_array( $_POST['tabs_mode'] ?? 'tab', [ 'tab', 'collapse' ], true )
                        ? sanitize_key( $_POST['tabs_mode'] ) : 'tab';
        $convert_bp = sanitize_key( $_POST['tabs_convert_bp'] ?? 'none' );
        $valid_bps  = [ 'none', 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
        if ( ! in_array( $convert_bp, $valid_bps, true ) ) $convert_bp = 'none';

        // Eğer tab modu ve convert_bp seçildiyse → aslında tab-collapse
        $effective_mode = ( $mode === 'tab' && $convert_bp !== 'none' ) ? 'tab-collapse' : $mode;

        // Tab listesi — JSON olarak gelir
        $tabs_raw = json_decode( wp_unslash( $_POST['tabs_list'] ?? '[]' ), true );
        $valid_types = [ 'wc_description', 'wc_additional', 'sh_reviews', 'custom_field', 'html' ];
        $tabs = [];

        if ( is_array( $tabs_raw ) ) {
            foreach ( $tabs_raw as $i => $tab ) {
                $type = in_array( $tab['type'] ?? '', $valid_types, true ) ? $tab['type'] : 'wc_description';
                $tabs[] = [
                    'id'           => sanitize_key( $tab['id'] ?? 'tab_' . $i ),
                    'title'        => sanitize_text_field( $tab['title'] ?? '' ),
                    'type'         => $type,
                    'field_id'     => $type === 'custom_field' ? sanitize_key( $tab['field_id'] ?? '' ) : '',
                    'html_content' => $type === 'html' ? wp_kses_post( $tab['html_content'] ?? '' ) : '',
                    'enabled'      => ! empty( $tab['enabled'] ),
                    'default_open' => ! empty( $tab['default_open'] ),
                    'icon'         => sanitize_text_field( $tab['icon'] ?? '' ),
                    'order'        => (int) ( $tab['order'] ?? $i ),
                ];
            }
            // Sıralamayı koru
            usort( $tabs, fn($a, $b) => $a['order'] <=> $b['order'] );
        }

        $cfg = [
            'mode'       => $effective_mode,
            'raw_mode'   => $mode,
            'convert_bp' => $convert_bp,
            'tabs'       => $tabs,
        ];

        update_option( 'options_product_tabs', $cfg, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        // ML: Tab başlıklarını Polylang string translation'a register et
        // Loco Translate / Polylang → "Product Tabs" group'unda görünür
        if ( function_exists( 'pll_register_string' ) ) {
            foreach ( $tabs as $tab ) {
                if ( ! empty( $tab['title'] ) ) {
                    pll_register_string(
                        'product_tab_' . $tab['id'],
                        $tab['title'],
                        'Product Tabs'
                    );
                }
            }
        }

        wp_send_json_success( 'Product Tabs ayarları kaydedildi.' );
    }

    /// ─── AJAX: Translations ───

    public static function ajaxSaveTranslations(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );
        $untrans_tax  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['untranslatable_taxonomies']   ?? [] ) ) ) );
        $untrans_pt   = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['untranslatable_post_types']   ?? [] ) ) ) );
        $sync_pages   = array_values( array_filter( array_map( 'absint',        (array) ( $_POST['synchronized_pages']          ?? [] ) ) ) );
        $sync_tax     = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['sync_taxonomies_to_language'] ?? [] ) ) ) );
        update_option( 'options_untranslatable_taxonomies',      $untrans_tax,  false );
        update_option( 'options_untranslatable_post_types',       $untrans_pt,   false );
        update_option( 'options_synchronized_pages',              $sync_pages,   false );
        update_option( 'options_sync_taxonomies_to_language',     $sync_tax,     false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();
        wp_send_json_success( 'Translations ayarları kaydedildi.' );
    }

    // ─── AJAX: Breadcrumb ───
    public static function ajaxExtractTranslations(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );
        $is_local = function_exists( 'isLocalhost' ) ? isLocalhost() : false;
        if ( ! $is_local ) { wp_send_json_error( 'Bu islem sadece local development ortaminda calisir.' ); }
        $result = self::extractTranslations();
        update_option( 'sh_last_extract_translations', current_time( 'timestamp' ), false );
        wp_send_json_success( $result );
    }



    /**
     * Tema dosyalarindan translate() cagrilarini tarayip PHP ve JSON dosyalarina yazar.
     *
     * @return array{success: bool, total: int, strings: array, messages: array}
     */
    private static function extractTranslations(): array
    {
        if ( function_exists( 'rocket_clean_minify' ) ) {
            rocket_clean_minify();
        }

        $theme       = wp_get_theme();
        $text_domain = $theme->get( 'TextDomain' );
        $theme_path  = get_template_directory();
        $output_dir  = $theme_path . '/theme/static/data';
        $output_file = $output_dir . '/translates.php';

        if ( ! file_exists( $output_dir ) ) {
            mkdir( $output_dir, 0755, true );
        }

        $exclude_folders = [ 'assets', 'node_modules', 'static', 'languages', 'acf-json' ];

        $scan_folder = function( string $folder, array $ex_folders, array $formats = ['php','twig'] ): array {
            $files = [];
            if ( ! is_dir( $folder ) ) return $files;
            $dir      = new \RecursiveDirectoryIterator( $folder, \RecursiveDirectoryIterator::SKIP_DOTS );
            $iterator = new \RecursiveIteratorIterator( $dir );
            $fmt_str  = implode( '|', $formats );
            $regex    = new \RegexIterator( $iterator, "/^.+\\.({$fmt_str})$/i", \RecursiveRegexIterator::GET_MATCH );
            foreach ( $regex as $file ) {
                $path    = str_replace( '\\', '/', $file[0] );
                $exclude = false;
                foreach ( $ex_folders as $ef ) {
                    if ( strpos( $path, "/$ef/" ) !== false ) { $exclude = true; break; }
                }
                if ( ! $exclude ) $files[] = $path;
            }
            return $files;
        };

        // PHP: tema root (vendor hariç), vendor/theme/src/includes, theme/woocommerce
        $files = $scan_folder( $theme_path, array_merge( $exclude_folders, ['vendor'] ), ['php'] );
        $extra_php_paths = [
            $theme_path . '/vendor/salthareket/theme/src/includes',
            $theme_path . '/theme/woocommerce',
        ];
        // Admin/ klasörlerini exclude et — admin-only string'ler frontend JSON'a girmesin
        $exclude_folders_php = array_merge( $exclude_folders, [ 'Admin', 'admin' ] );
        foreach ( $extra_php_paths as $p ) {
            $files = array_merge( $files, $scan_folder( $p, $exclude_folders_php, ['php'] ) );
        }

        // Twig: templates klasorleri
        $twig_paths = [
            $theme_path . '/templates',
            $theme_path . '/theme/templates',
            $theme_path . '/vendor/salthareket/theme/src/templates',
        ];
        foreach ( $twig_paths as $p ) {
            $files = array_merge( $files, $scan_folder( $p, [], ['twig'] ) );
        }

        // JS: static/js
        $js_path = $theme_path . '/static/js';
        if ( is_dir( $js_path ) ) {
            $files = array_merge( $files, $scan_folder( $js_path, [], ['js'] ) );
        }

        $files = array_values( array_unique( $files ) );

        $is_valid = function( $value ): bool {
            if ( ! is_string( $value ) ) return false;
            $f = $value[0]; $l = $value[ strlen($value) - 1 ];
            if ( ! ( ($f === '"' && $l === '"') || ($f === "'" && $l === "'") || ($f === '`' && $l === '`') ) ) return false;
            $s = substr( $value, 1, -1 );
            if ( strlen( $s ) < 3 || is_numeric( $s ) ) return false;
            // Sadece noktalama/sembol/boşluk içerenleri atla
            if ( preg_match( '/^[\s\p{P}\p{S}]+$/u', $s ) ) return false;
            return true;
        };

        $extract = function( $content ): array {
            $content = str_replace( ["\r","\n","\t"], ' ', $content );
            preg_match_all( '/translate\(([^)]+)\)/', $content, $tm );
            preg_match_all( '/translate_n_noop\(([^)]+)\)/', $content, $nm );
            return [ 'translate' => $tm, 'translate_n_noop' => $nm ];
        };

        $translations = [ 'translate' => [], 'translate_n_noop' => [] ];

        foreach ( $files as $file ) {
            $content = @file_get_contents( $file );
            if ( ! $content ) continue;
            $matches = $extract( $content );
            foreach ( $matches['translate'][0] as $i => $_ ) {
                $args = array_map( 'trim', explode( ',', $matches['translate'][1][$i] ) );
                if ( count($args) === 1 && $is_valid($args[0]) ) $translations['translate'][] = $args[0];
            }
            foreach ( $matches['translate_n_noop'][0] as $i => $_ ) {
                $args = array_map( 'trim', explode( ',', $matches['translate_n_noop'][1][$i] ) );
                if ( count($args) === 2 && $is_valid($args[0]) && $is_valid($args[1]) ) $translations['translate_n_noop'][] = $args;
            }
        }

        $newsletter_forms = get_option( 'newsletter_htmlforms' );
        if ( $newsletter_forms ) {
            foreach ( $newsletter_forms as $form ) {
                if ( empty($form) ) continue;
                $matches = $extract( $form );
                foreach ( $matches['translate'][0] as $i => $_ ) {
                    $args = array_map( 'trim', explode( ',', $matches['translate'][1][$i] ) );
                    if ( count($args) === 1 ) $translations['translate'][] = $args[0];
                }
            }
        }

        $translations['translate']       = array_values( array_unique( $translations['translate'] ) );
        $translations['translate_n_noop'] = array_values( array_unique( $translations['translate_n_noop'], SORT_REGULAR ) );

        // Trim + deduplicate for clean list
        $clean_translate = [];
        foreach ( $translations['translate'] as $t ) {
            $s = trim($t, "\"'`");
            if ( ! in_array($s, $clean_translate, true) ) $clean_translate[] = $s;
        }
        $clean_noop = [];
        foreach ( $translations['translate_n_noop'] as $pair ) {
            $s = trim($pair[0], "\"'`");
            $p = trim($pair[1], "\"'`");
            $key = "$s|$p";
            if ( ! isset($clean_noop[$key]) ) $clean_noop[$key] = [$s, $p];
        }

        if ( function_exists( 'get_field_default' ) ) {
            $upt = get_field_default( 'untranslatable_post_types', 'option' );
            if ( $upt && is_array( $upt ) ) {
                foreach ( $upt as $pt ) {
                    $posts = get_posts( ['post_type' => $pt, 'posts_per_page' => -1, 'post_status' => 'publish', 'suppress_filters' => true] );
                    foreach ( $posts as $p ) $translations['translate'][] = "'{$p->post_title}'";
                }
            }
            $utx = get_field_default( 'untranslatable_taxonomies', 'option' );
            if ( $utx && is_array( $utx ) ) {
                foreach ( $utx as $tx ) {
                    $terms = get_terms( ['taxonomy' => $tx, 'hide_empty' => false, 'fields' => 'objects', 'lang' => '', 'number' => 0] );
                    if ( ! is_wp_error($terms) ) foreach ( $terms as $term ) $translations['translate'][] = "'{$term->name}'";
                }
            }
        }

        // ── Page Settings dinamik label/title metinleri ────────────────────────
        // Kullanici admin'de girdigi toggler label / offcanvas title / modal title
        // serbest metinler. Loco kaynak koddan bulamaz (degisken uzerinden basiliyor).
        // Bu yuzden DB'deki tum page-settings kayitlarini tarayip extract listesine
        // ekliyoruz — post title'lar gibi. Boylece translate.php'ye yazilir ve
        // Loco sync'te silmez.
        $ps_collect_strings = function( $ps ) use ( &$translations ) {
            if ( ! is_array( $ps ) ) return;
            $candidates = [
                $ps['offcanvas']['title']         ?? '',
                $ps['offcanvas']['toggler_label'] ?? '',
                $ps['modal']['title']             ?? '',
            ];
            foreach ( $candidates as $str ) {
                $str = is_string( $str ) ? trim( $str ) : '';
                if ( $str === '' ) continue;
                // Tek tirnak icindeki tirnaklari escape et
                $esc = str_replace( "'", "\\'", $str );
                $translations['translate'][] = "'{$esc}'";
            }
        };

        global $wpdb;
        $ps_key      = \SaltHareket\PageSettings\PageSettings::META_KEY;         // _page_settings
        $ps_flag_key = $ps_key . '_enabled';                                    // _page_settings_enabled

        // 1) Post meta — SADECE enabled=true olanlar (JOIN ile _page_settings_enabled)
        $ps_metas = $wpdb->get_col( $wpdb->prepare(
            "SELECT m.meta_value
               FROM {$wpdb->postmeta} m
               INNER JOIN {$wpdb->postmeta} f
                       ON f.post_id = m.post_id AND f.meta_key = %s AND f.meta_value = '1'
              WHERE m.meta_key = %s",
            $ps_flag_key, $ps_key
        ) );
        // 2) Term meta — SADECE enabled=true olanlar
        $ps_term_metas = $wpdb->get_col( $wpdb->prepare(
            "SELECT m.meta_value
               FROM {$wpdb->termmeta} m
               INNER JOIN {$wpdb->termmeta} f
                       ON f.term_id = m.term_id AND f.meta_key = %s AND f.meta_value = '1'
              WHERE m.meta_key = %s",
            $ps_flag_key, $ps_key
        ) );
        // 3) Options (archive + taxonomy) — sadece _page_settings_enabled_* = 1 olanlarin veri option'i.
        //    Enabled option adi: _page_settings_enabled_{suffix}, veri option adi: _page_settings_{suffix}
        $enabled_opts = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value = '1'",
            $wpdb->esc_like( $ps_flag_key . '_' ) . '%'
        ) );
        $ps_options = [];
        foreach ( (array) $enabled_opts as $en_name ) {
            // _page_settings_enabled_{suffix} → _page_settings_{suffix}
            $suffix        = substr( $en_name, strlen( $ps_flag_key . '_' ) );
            $data_opt_name = $ps_key . '_' . $suffix;
            $val = get_option( $data_opt_name, null );
            if ( $val !== null ) $ps_options[] = $val;
        }

        foreach ( array_merge( (array) $ps_metas, (array) $ps_term_metas ) as $raw ) {
            $ps_collect_strings( maybe_unserialize( $raw ) );
        }
        foreach ( $ps_options as $val ) {
            $ps_collect_strings( $val ); // get_option zaten unserialize eder
        }

        // PHP dosyasi + JSON dosyasi — filter sonrasında yazılır (dinamik string'ler dahil)
        // Önce $strings'i hazırla, filter uygula, sonra her ikisini yaz

        // JSON dosyasi yaz
        $json_file = $output_dir . '/translates.json';

        // Eski listeyi oku (yeni eklenen string'leri tespit icin)
        $old_strings = file_exists( $json_file ) ? json_decode( file_get_contents( $json_file ), true ) : [];
        if ( ! is_array( $old_strings ) ) $old_strings = [];

        $strings   = $clean_translate;
        // Noop'ları da ekle (singular + plural)
        foreach ( $clean_noop as $pair ) {
            if ( ! in_array($pair[0], $strings, true) ) $strings[] = $pair[0];
            if ( ! in_array($pair[1], $strings, true) ) $strings[] = $pair[1];
        }
        $collator  = new \Collator( 'tr_TR' );
        $collator->sort( $strings );

        // ── Dışarıdan ek string'ler eklenebilir — app'ler DB'deki dinamik değerleri inject eder ──
        // Kullanım: add_filter('sh_extract_translations', function(array $strings): array { ... })
        $strings = (array) apply_filters( 'sh_extract_translations', $strings );
        // Filtreden sonra tekrar sort + unique
        $strings = array_values( array_unique( $strings ) );
        $collator->sort( $strings );

        // PHP dosyasını filter sonrası yaz — dinamik string'ler (kriter label'ları vb.) dahil edilsin
        $fh = fopen( $output_file, 'w' );
        fwrite( $fh, "<?php\n" );
        foreach ( $strings as $s ) fwrite( $fh, "__(\"{$s}\", \"{$text_domain}\");\n" );
        foreach ( $clean_noop as $pair ) fwrite( $fh, "_n_noop(\"{$pair[0]}\", \"{$pair[1]}\", \"{$text_domain}\");\n" );
        fwrite( $fh, "?>" );
        fclose( $fh );

        file_put_contents( $json_file, json_encode( $strings, JSON_UNESCAPED_UNICODE ) );

        $total       = count( $strings );
        $new_strings = array_values( array_diff( $strings, $old_strings ) );

        return [
            'success'     => true,
            'total'       => $total,
            'new_strings' => $new_strings,
        ];
    }

    public static function ajaxSaveBreadcrumb(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );
        update_option( 'options_breadcrumb_remove_home',          ! empty( $_POST['breadcrumb_remove_home'] ),          false );
        update_option( 'options_breadcrumb_remove_current',       ! empty( $_POST['breadcrumb_remove_current'] ),       false );
        update_option( 'options_breadcrumb_from_menu',            ! empty( $_POST['breadcrumb_from_menu'] ),            false );
        update_option( 'options_breadcrumb_add_product_brand',    ! empty( $_POST['breadcrumb_add_product_brand'] ),    false );
        update_option( 'options_breadcrumb_add_product_taxonomy', ! empty( $_POST['breadcrumb_add_product_taxonomy'] ), false );
        $rows  = (array) ( $_POST['breadcrumb_add_page_path'] ?? [] );
        $clean = [];
        foreach ( $rows as $row ) {
            $pt   = sanitize_key( $row['post_type'] ?? '' );
            $page = absint( $row['page'] ?? 0 );
            if ( $pt && $page ) $clean[] = [ 'post_type' => $pt, 'page' => $page ];
        }
        update_option( 'options_breadcrumb_add_page_path', $clean, false );
        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();
        wp_send_json_success( 'Breadcrumb ayarları kaydedildi.' );
    }


    // ─── Render: WooCommerce Tab ───

    private static function renderWooTab( string $nonce, string $ajax ): void
    {
        $woo_tab = sanitize_key( $_GET['woo_tab'] ?? 'general' );
        if ( ! in_array( $woo_tab, [ 'general', 'tease', 'single', 'cart' ], true ) ) $woo_tab = 'general';

        $single_section = sanitize_key( $_GET['woo_single_section'] ?? 'gallery' );
        if ( ! in_array( $single_section, [ 'gallery', 'qty', 'sticky', 'lists', 'tabs' ], true ) ) $single_section = 'gallery';

        $current_url_base = add_query_arg( [ 'page' => 'sh-general-settings', 'sub' => 'woocommerce' ], admin_url( 'admin.php' ) );

        $woo_tab_defs = [ 'general' => '🛒 General', 'tease' => '🃏 Tease', 'single' => '📦 Single', 'cart' => '🛒 Cart' ];
        $single_section_defs = [
            'gallery' => '🖼 Gallery', 'qty' => '🔢 Quantity',
            'sticky'  => '📌 Sticky ATC', 'lists' => '🔗 Product Lists', 'tabs' => '📑 Product Tabs',
        ];
        ?>
        <!-- WooCommerce Alt-Tab Bar -->
        <script>
        // ── Shared helpers for all Woo sub-sections ──────────────────────
        if (typeof window.shGsToast === 'undefined') {
            window.shGsToast = function(msg, ok) {
                if (typeof ok === 'undefined') ok = msg.indexOf('✔') !== -1;
                if (typeof Toastify === 'function') {
                    Toastify({ text: msg, duration: 3000, gravity: 'bottom', position: 'right',
                        style: { background: ok ? '#16a34a' : '#dc2626', borderRadius: '6px', fontSize: '13px', fontWeight: '500' }
                    }).showToast();
                } else {
                    var t = document.createElement('div');
                    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:500;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:opacity .3s;background:' + (ok ? '#16a34a' : '#dc2626');
                    t.textContent = msg;
                    document.body.appendChild(t);
                    setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.remove(); }, 300); }, 3000);
                }
            };
        }
        if (!document.getElementById('sh-spin-style')) {
            var s = document.createElement('style');
            s.id = 'sh-spin-style';
            s.textContent = '@keyframes sh-spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(s);
        }
        </script>
        <div style="display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:20px">
            <?php foreach ( $woo_tab_defs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'woo_tab', $slug, $current_url_base ) ); ?>"
               style="padding:8px 16px;font-size:12px;font-weight:600;text-decoration:none;border-radius:6px 6px 0 0;border:1px solid <?php echo $woo_tab===$slug?'#e5e7eb':'transparent'; ?>;margin-bottom:-2px;background:<?php echo $woo_tab===$slug?'#fff':'transparent'; ?>;color:<?php echo $woo_tab===$slug?'#1d4ed8':'#6b7280'; ?>">
                <?php echo $label; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php
        if ( $woo_tab === 'general' )  { self::renderWooGeneralSection( $nonce, $ajax ); }
        if ( $woo_tab === 'tease' )    { self::renderWooTeaseSection( $nonce, $ajax ); }
        if ( $woo_tab === 'single' ) {
            ?>
            <div style="display:flex;gap:0;align-items:flex-start">
                <div style="width:160px;flex-shrink:0;border-right:1px solid #e5e7eb;padding-right:0;margin-right:20px">
                    <?php foreach ( $single_section_defs as $sec_slug => $sec_label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 'woo_tab' => 'single', 'woo_single_section' => $sec_slug ], $current_url_base ) ); ?>"
                       style="display:block;padding:8px 12px;font-size:12px;font-weight:<?php echo $single_section===$sec_slug?'600':'400'; ?>;text-decoration:none;border-radius:6px 0 0 6px;margin-bottom:2px;border-right:<?php echo $single_section===$sec_slug?'3px solid #1d4ed8':'3px solid transparent'; ?>;background:<?php echo $single_section===$sec_slug?'#eff6ff':'transparent'; ?>;color:<?php echo $single_section===$sec_slug?'#1d4ed8':'#374151'; ?>">
                        <?php echo $sec_label; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div style="flex:1;min-width:0">
                    <?php
                    if ( $single_section === 'gallery' ) { self::renderWooGallerySection( $nonce, $ajax ); }
                    if ( $single_section === 'qty' )     { self::renderWooQtySection( $nonce, $ajax ); }
                    if ( $single_section === 'sticky' )  { self::renderWooStickySection( $nonce, $ajax ); }
                    if ( $single_section === 'lists' )   { self::renderWooListsSection( $nonce, $ajax ); }
                    if ( $single_section === 'tabs' )    { self::renderWooTabsSection( $nonce, $ajax ); }
                    ?>
                </div>
            </div>
            <?php
        }
        if ( $woo_tab === 'cart' ) { self::renderWooCartSection( $nonce, $ajax ); }
    }

    private static function renderWooGeneralSection( string $nonce, string $ajax ): void
    {
        $woo_enable_cart        = (bool) get_option( 'options_enable_cart', false );
        $woo_enable_api         = (bool) get_option( 'options_enable_woo_api', false );
        $woo_disable_frontend   = (bool) get_option( 'options_disable_wc_frontend', false );
        $woo_api_local          = (array) get_option( 'options_woo_api_local', [] );
        $woo_api_live           = (array) get_option( 'options_woo_api_live', [] );
        $woo_single             = (array) get_option( 'sh_woo_single', [] );
        $auto_first_var         = (bool) ( $woo_single['auto_select_first_variation'] ?? false );
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🛒 WooCommerce</span>
                <span class="sh-card-desc">Sepet ve WooCommerce API ayarları.</span>
            </div>
            <div class="sh-card-body">

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md" style="padding:12px;background:#fef9c3;border:1px solid #fde047;border-radius:8px">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-woo-disable-frontend" <?php checked( $woo_disable_frontend ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-woo-disable-frontend" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">⚠️ Disable WooCommerce Frontend</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">WooCommerce plugin aktif kalır ama tema frontend'i devre dışı olur. <code>ENABLE_ECOMMERCE = false</code> yapar. Test/staging amaçlı.</p>
                    </div>
                </div>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-woo-enable-cart" <?php checked( $woo_enable_cart ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-woo-enable-cart" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Enable Cart</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Sepet özelliğini aktif eder. <code>ENABLE_CART</code> sabitini tanımlar.</p>
                    </div>
                </div>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-woo-enable-api" <?php checked( $woo_enable_api ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-woo-enable-api" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Enable WooCommerce API</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">WooCommerce REST API kimlik doğrulamasını aktif eder.</p>
                    </div>
                </div>
                <div id="sh-woo-api-wrap" style="<?php echo $woo_enable_api ? 'display:grid' : 'display:none'; ?>;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                    <div class="sh-card-inset">
                        <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px">Local</div>
                        <div class="sh-mb-sm">
                            <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Client ID</label>
                            <input type="text" id="sh-woo-api-local-client" value="<?php echo esc_attr( $woo_api_local['client'] ?? '' ); ?>" class="sh-input sh-w-full">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Client Secret</label>
                            <input type="password" id="sh-woo-api-local-secret" value="<?php echo esc_attr( $woo_api_local['client_secret'] ?? '' ); ?>" class="sh-input sh-w-full">
                        </div>
                    </div>
                    <div class="sh-card-inset">
                        <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px">Live</div>
                        <div class="sh-mb-sm">
                            <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Client ID</label>
                            <input type="text" id="sh-woo-api-live-client" value="<?php echo esc_attr( $woo_api_live['client'] ?? '' ); ?>" class="sh-input sh-w-full">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-muted sh-block sh-mb-xs">Client Secret</label>
                            <input type="password" id="sh-woo-api-live-secret" value="<?php echo esc_attr( $woo_api_live['client_secret'] ?? '' ); ?>" class="sh-input sh-w-full">
                        </div>
                    </div>
                </div>

                <!-- Single Product ayarları -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm sh-mt-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px">Single Product</div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-woo-auto-first-var" <?php checked( $auto_first_var ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-woo-auto-first-var" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">İlk Variation'ı Otomatik Seç</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Variable ürün sayfasına girildiğinde ilk variation URL'sine redirect eder. Galeri ve başlık o variation'a göre gelir.</p>
                    </div>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm">
                    <button type="button" id="sh-gs-save-woo" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-woo-msg" class="sh-text-sm sh-text-success" style="display:none">✔ Kaydedildi</span>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
            var apiWrap = document.getElementById('sh-woo-api-wrap');
            var apiToggle = document.getElementById('sh-woo-enable-api');
            if (apiToggle) apiToggle.addEventListener('change', function(){
                if (apiWrap) apiWrap.style.display = this.checked ? 'grid' : 'none';
            });
            var _shSaveEl = document.getElementById('sh-gs-save-woo');
            if (_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action','sh_gs_save_woo'); fd.append('nonce',NONCE);
                var chk = function(id){ var el=document.getElementById(id); return (el&&el.checked)?'1':'0'; };
                var val = function(id){ var el=document.getElementById(id); return el?el.value:''; };
                fd.append('enable_cart',                 chk('sh-woo-enable-cart'));
                fd.append('disable_wc_frontend',         chk('sh-woo-disable-frontend'));
                fd.append('enable_woo_api',              chk('sh-woo-enable-api'));
                fd.append('woo_api_local_client',        val('sh-woo-api-local-client'));
                fd.append('woo_api_local_secret',        val('sh-woo-api-local-secret'));
                fd.append('woo_api_live_client',         val('sh-woo-api-live-client'));
                fd.append('woo_api_live_secret',         val('sh-woo-api-live-secret'));
                fd.append('auto_select_first_variation', chk('sh-woo-auto-first-var'));
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled = false; btn.textContent = 'Kaydet';
                    if(typeof shGsToast==='function') shGsToast(res.success ? '✔ WooCommerce ayarları kaydedildi' : '✖ Hata');
                }).catch(function(){ btn.disabled=false; btn.textContent='Kaydet'; });
            });
        })();
        </script>
        <?php
    }

    private static function renderWooTeaseSection( string $nonce, string $ajax ): void
    {
        // ── Değişkenler ──────────────────────────────────────────────────────
        $tease = (array) get_option( 'options_tease_settings', [] );
        $tease_multiple_images  = (bool)   ( $tease['multiple_images']  ?? false );
        $tease_show_variations  = (bool)   ( $tease['show_variations']  ?? false );
        $tease_swatch_tooltip   = (bool)   ( $tease['swatch_tooltip']   ?? false );
        $tease_show_oos_swatches= (bool)   ( $tease['show_oos_swatches']?? false );
        $tease_swatch_limit     = (int)    ( $tease['swatch_limit']     ?? 115   );
        $tease_show_category    = (bool)   ( $tease['show_category']    ?? true  );
        $tease_show_price       = (bool)   ( $tease['show_price']       ?? true  );
        $tease_show_sale_badge  = (bool)   ( $tease['show_sale_badge']  ?? true  );
        $tease_show_feat_badge  = (bool)   ( $tease['show_feat_badge']  ?? true  );
        $tease_show_oos_badge   = (bool)   ( $tease['show_oos_badge']   ?? true  );
        $tease_img_ratio        = sanitize_key( $tease['img_ratio']     ?? '1x1' );
        $tease_default_size     = sanitize_key( $tease['default_size']  ?? 'md'  );
        $tease_bundle_cta       = in_array( $tease['bundle_cta'] ?? '', ['view','cart'], true ) ? $tease['bundle_cta'] : 'view';
        $ratio_options = [ '1x1' => '1:1 (Kare)', '4x3' => '4:3', '3x4' => '3:4 (Dikey)', '16x9' => '16:9' ];
        $size_options  = [ 'xs' => 'XS', 'sm' => 'SM', 'md' => 'MD', 'lg' => 'LG' ];
        $active_plugins_list = [];
        foreach ( (array) get_option( 'active_plugins', [] ) as $p ) {
            $slug = strpos( $p, '/' ) !== false ? explode( '/', $p )[0] : basename( $p, '.php' );
            $active_plugins_list[ $slug ] = true;
        }
        ?>

        <!-- ── Kart 2: Product Card Ayarları ─────────────────────────────── -->
        <div class="sh-card sh-mt-sm">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🃏 Product Card (tease.twig)</span>
                <span class="sh-card-desc">Shop loop'undaki ürün kartının görsel ve davranış ayarları.</span>
            </div>
            <div class="sh-card-body">

                <!-- Görsel -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px">Görsel</div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-multiple-images" <?php checked( $tease_multiple_images ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-multiple-images" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Çoklu Görsel (Variation Slider)</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Variable product'ta her variation'ın görselini hover ile geçişli göster. WooCommerce 11.1+ ile bu özellik core'a dahil edildi — ek plugin gerekmez.</p>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Görsel Oranı</label>
                        <select id="sh-tease-img-ratio" class="sh-input sh-w-full">
                            <?php foreach ( $ratio_options as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected( $tease_img_ratio, $val ); ?>><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">Kart görseli aspect ratio'su.</p>
                    </div>
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Varsayılan Kart Boyutu</label>
                        <select id="sh-tease-default-size" class="sh-input sh-w-full">
                            <?php foreach ( $size_options as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected( $tease_default_size, $val ); ?>><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">XS/SM'de kategori ve bazı detaylar gizlenir.</p>
                    </div>
                </div>

                <!-- Swatches -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">Variation Swatches</div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-show-variations" <?php checked( $tease_show_variations ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-show-variations" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Swatch'ları Göster</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Renk/beden swatch'larını kart üzerinde göster. <em>Woo Variation Swatches</em> plugin gerekli.</p>
                    </div>
                </div>

                <div id="sh-tease-swatch-wrap" style="<?php echo $tease_show_variations ? 'display:block' : 'display:none'; ?>">
                    <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md" style="margin-left:8px">
                        <label class="sh-toggle">
                            <input type="checkbox" id="sh-tease-swatch-tooltip" <?php checked( $tease_swatch_tooltip ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <div>
                            <label for="sh-tease-swatch-tooltip" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Swatch Tooltip</label>
                            <p class="sh-text-sm sh-text-muted sh-m-0">Hover'da swatch ismi tooltip'i.</p>
                        </div>
                    </div>
                    <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md" style="margin-left:8px">
                        <label class="sh-toggle">
                            <input type="checkbox" id="sh-tease-show-oos-swatches" <?php checked( $tease_show_oos_swatches ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <div>
                            <label for="sh-tease-show-oos-swatches" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Tükenmiş Swatch'ları Göster</label>
                            <p class="sh-text-sm sh-text-muted sh-m-0">Stokta olmayan variation'ların swatch'larını da listele.</p>
                        </div>
                    </div>
                    <div style="margin-left:8px;margin-bottom:16px;max-width:200px">
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Maksimum Swatch Sayısı</label>
                        <input type="number" id="sh-tease-swatch-limit" value="<?php echo esc_attr( $tease_swatch_limit ); ?>" min="1" max="999" class="sh-input sh-w-full">
                        <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">Limit aşılırsa "+N" olarak gösterilir.</p>
                    </div>
                </div>

                <!-- Rozetler -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">Rozetler (Badges)</div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-show-sale-badge" <?php checked( $tease_show_sale_badge ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-show-sale-badge" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">İndirim Rozeti</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">İndirimli ürünlerde "Discount" badge'i göster.</p>
                    </div>
                </div>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-show-feat-badge" <?php checked( $tease_show_feat_badge ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-show-feat-badge" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Öne Çıkan Rozeti</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Featured ürünlerde "New" badge'i göster.</p>
                    </div>
                </div>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-show-oos-badge" <?php checked( $tease_show_oos_badge ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-show-oos-badge" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Tükendi Rozeti</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Stokta olmayan ürünlerde "Out of Stock" badge'i göster.</p>
                    </div>
                </div>

                <!-- Kart İçerik -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">Kart İçerik</div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-show-category" <?php checked( $tease_show_category ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-show-category" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Kategori Adı</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Başlık üzerinde primary kategori adını göster.</p>
                    </div>
                </div>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-tease-show-price" <?php checked( $tease_show_price ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="sh-tease-show-price" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Fiyat</label>
                        <p class="sh-text-sm sh-text-muted sh-m-0">Kart üzerinde fiyat alanını göster.</p>
                    </div>
                </div>

                <?php if ( isset( $active_plugins_list['woocommerce-product-bundles'] ) || defined('WC_PB_VERSION') ) : ?>
                <!-- Product Bundles -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">Product Bundles</div>
                <div style="max-width:320px;margin-bottom:16px">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Bundle CTA Davranışı</label>
                    <select id="sh-tease-bundle-cta" class="sh-input sh-w-full">
                        <option value="view"  <?php selected( $tease_bundle_cta, 'view'  ); ?>>View Bundle (ürün sayfasına git)</option>
                        <option value="cart"  <?php selected( $tease_bundle_cta, 'cart'  ); ?>>Add to Cart (sepete ekle)</option>
                    </select>
                    <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">Bundle tipindeki ürünlerde CTA butonunun davranışı.</p>
                </div>
                <?php endif; ?>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md">
                    <button type="button" id="sh-gs-save-tease" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-tease-msg" class="sh-text-sm sh-text-success" style="display:none">✔ Kaydedildi</span>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;

            // Swatch alt-ayarlar toggle
            var swatchWrap = document.getElementById('sh-tease-swatch-wrap');
            var swatchToggle = document.getElementById('sh-tease-show-variations');
            if (swatchToggle) swatchToggle.addEventListener('change', function(){
                if (swatchWrap) swatchWrap.style.display = this.checked ? 'block' : 'none';
            });

        })();
        </script>

        <?php /* renderWooTeaseSection JS */ ?>
        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;

            // Swatch alt-ayarlar toggle
            var swatchWrap = document.getElementById('sh-tease-swatch-wrap');
            var swatchToggle = document.getElementById('sh-tease-show-variations');
            if (swatchToggle) swatchToggle.addEventListener('change', function(){
                if (swatchWrap) swatchWrap.style.display = this.checked ? 'block' : 'none';
            });
            var _shSaveEl=document.getElementById('sh-gs-save-tease'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action','sh_gs_save_tease'); fd.append('nonce',NONCE);
                var chk = function(id){ var el=document.getElementById(id); return (el&&el.checked)?'1':'0'; };
                var val = function(id){ var el=document.getElementById(id); return el?el.value:''; };
                fd.append('multiple_images',   chk('sh-tease-multiple-images'));
                fd.append('show_variations',   chk('sh-tease-show-variations'));
                fd.append('swatch_tooltip',    chk('sh-tease-swatch-tooltip'));
                fd.append('show_oos_swatches', chk('sh-tease-show-oos-swatches'));
                fd.append('show_sale_badge',   chk('sh-tease-show-sale-badge'));
                fd.append('show_feat_badge',   chk('sh-tease-show-feat-badge'));
                fd.append('show_oos_badge',    chk('sh-tease-show-oos-badge'));
                fd.append('show_category',     chk('sh-tease-show-category'));
                fd.append('show_price',        chk('sh-tease-show-price'));
                fd.append('swatch_limit',      val('sh-tease-swatch-limit'));
                fd.append('img_ratio',         val('sh-tease-img-ratio'));
                fd.append('default_size',      val('sh-tease-default-size'));
                var bundleCta = document.getElementById('sh-tease-bundle-cta');
                if (bundleCta) fd.append('bundle_cta', bundleCta.value);
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled = false; btn.textContent = 'Kaydet';
                    shGsToast(res.success ? '✔ Product Card ayarları kaydedildi' : '✖ Hata');
                }).catch(function(){ btn.disabled=false; btn.textContent='Kaydet'; });
            });
        })();
        </script>
        <?php
    }

    private static function renderWooGallerySection( string $nonce, string $ajax ): void
    {
        $gallery = (array) get_option( 'options_gallery_settings', [] );
        $g_type       = $gallery['type']           ?? 'swiper';
        $g_ratio      = $gallery['ratio']          ?? '4x3';
        $g_fit        = $gallery['object_fit']     ?? 'cover';
        $g_hover      = $gallery['hover']          ?? 'lightbox';
        $g_lightbox   = (bool) ( $gallery['lightbox']         ?? true );
        $g_nav        = (bool) ( $gallery['navigation']       ?? true );
        $g_loop       = (bool) ( $gallery['loop']             ?? false );
        $g_thumbs     = (bool) ( $gallery['thumbs']           ?? true );
        $g_thumb_nav  = (bool) ( $gallery['thumb_nav']        ?? true );
        $g_var_update = (bool) ( $gallery['variation_update'] ?? true );
        $g_pagination = $gallery['pagination']     ?? 'bullets';
        $g_spv        = (int)  ( $gallery['slides_per_view']  ?? 1 );
        $g_thumb_cnt  = (int)  ( $gallery['thumb_count']      ?? 4 );
        $g_stack_gap  = (int)  ( $gallery['stack_gap']        ?? 8 );
        ?>
        <div class="sh-card sh-mt-sm">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🖼 Product Gallery (single-product)</span>
                <span class="sh-card-desc">Ürün detay sayfasındaki galeri görünüm ve davranış ayarları.</span>
            </div>
            <div class="sh-card-body">

                <!-- Galeri Tipi -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px">Galeri Tipi</div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px">
                    <?php foreach ( [ 'single' => '🖼 Tek Görsel', 'swiper' => '🎠 Swiper Slider', 'stack' => '📋 Alt Alta', 'grid' => '▦ Grid' ] as $val => $lbl ) : ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:10px 12px;border:2px solid <?php echo $g_type === $val ? '#2271b1' : '#e5e7eb'; ?>;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;background:<?php echo $g_type === $val ? '#eff6ff' : '#fff'; ?>">
                        <input type="radio" name="sh_gallery_type" value="<?php echo $val; ?>" <?php checked( $g_type, $val ); ?> style="margin:0">
                        <?php echo $lbl; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Görsel Ayarları -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px">Görsel</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Görsel Oranı</label>
                        <select id="sh-gallery-ratio" class="sh-input sh-w-full">
                            <?php foreach ( [ '1x1'=>'1:1 (Kare)', '4x3'=>'4:3', '3x4'=>'3:4 (Dikey)', '16x9'=>'16:9', 'original'=>'Orijinal' ] as $v => $l ) : ?>
                            <option value="<?php echo $v; ?>" <?php selected( $g_ratio, $v ); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Object Fit</label>
                        <select id="sh-gallery-fit" class="sh-input sh-w-full">
                            <option value="cover" <?php selected($g_fit,'cover'); ?>>Cover (kırp)</option>
                            <option value="contain" <?php selected($g_fit,'contain'); ?>>Contain (tam sığdır)</option>
                        </select>
                    </div>
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Hover / Tıklama Efekti</label>
                        <select id="sh-gallery-hover" class="sh-input sh-w-full">
                            <option value="none" <?php selected($g_hover,'none'); ?>>Yok</option>
                            <option value="zoom" <?php selected($g_hover,'zoom'); ?>>Zoom (hover)</option>
                            <option value="lightbox" <?php selected($g_hover,'lightbox'); ?>>Lightbox (tıklama)</option>
                            <option value="both" <?php selected($g_hover,'both'); ?>>Zoom + Lightbox</option>
                        </select>
                    </div>
                </div>

                <!-- Swiper Ayarları -->
                <div id="sh-gallery-swiper-wrap" style="<?php echo $g_type !== 'swiper' ? 'display:none' : ''; ?>">
                    <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px">Swiper Ayarları</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
                        <div>
                            <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Pagination</label>
                            <select id="sh-gallery-pagination" class="sh-input sh-w-full">
                                <option value="none" <?php selected($g_pagination,'none'); ?>>Yok</option>
                                <option value="bullets" <?php selected($g_pagination,'bullets'); ?>>Bullets (nokta)</option>
                                <option value="fraction" <?php selected($g_pagination,'fraction'); ?>>Fraction (1/5)</option>
                                <option value="progressbar" <?php selected($g_pagination,'progressbar'); ?>>Progress bar</option>
                            </select>
                        </div>
                        <div>
                            <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Slide Sayısı (görünen)</label>
                            <input type="number" id="sh-gallery-spv" class="sh-input sh-w-full" value="<?php echo $g_spv; ?>" min="1" max="5">
                            <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">1 = tek görsel, 2+ = yan yana</p>
                        </div>
                    </div>

                    <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                        <label class="sh-toggle"><input type="checkbox" id="sh-gallery-navigation" <?php checked($g_nav); ?>><span class="sh-toggle-slider"></span></label>
                        <div><label for="sh-gallery-navigation" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Navigasyon Okları</label><p class="sh-text-sm sh-text-muted sh-m-0">Önceki/sonraki ok butonları.</p></div>
                    </div>

                    <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                        <label class="sh-toggle"><input type="checkbox" id="sh-gallery-loop" <?php checked($g_loop); ?>><span class="sh-toggle-slider"></span></label>
                        <div><label for="sh-gallery-loop" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Loop</label><p class="sh-text-sm sh-text-muted sh-m-0">Sonsuz döngü.</p></div>
                    </div>

                    <!-- Thumbnail Nav -->
                    <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                        <label class="sh-toggle"><input type="checkbox" id="sh-gallery-thumbs" <?php checked($g_thumbs); ?> onchange="document.getElementById('sh-gallery-thumbs-wrap').style.display=this.checked?'block':'none'"><span class="sh-toggle-slider"></span></label>
                        <div><label for="sh-gallery-thumbs" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Thumbnail Navigation (alt strip)</label><p class="sh-text-sm sh-text-muted sh-m-0">Altta küçük thumbnail strip Swiper'ı.</p></div>
                    </div>

                    <div id="sh-gallery-thumbs-wrap" style="margin-left:8px;<?php echo !$g_thumbs ? 'display:none' : ''; ?>">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                            <div>
                                <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Görünen Thumb Sayısı</label>
                                <input type="number" id="sh-gallery-thumb-count" class="sh-input sh-w-full" value="<?php echo $g_thumb_cnt; ?>" min="2" max="10">
                            </div>
                        </div>
                        <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                            <label class="sh-toggle"><input type="checkbox" id="sh-gallery-thumb-nav" <?php checked($g_thumb_nav); ?>><span class="sh-toggle-slider"></span></label>
                            <div><label for="sh-gallery-thumb-nav" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Thumb Strip Okları</label><p class="sh-text-sm sh-text-muted sh-m-0">Thumb swiper'ında navigasyon okları (20+ görsel için).</p></div>
                        </div>
                    </div>
                </div>

                <!-- Stack Gap -->
                <div id="sh-gallery-stack-wrap" style="<?php echo $g_type !== 'stack' ? 'display:none' : ''; ?>">
                    <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">Stack Ayarları</div>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;max-width:200px">
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs" style="white-space:nowrap">Görsel Arası Boşluk</label>
                        <input type="number" id="sh-gallery-stack-gap" class="sh-input sh-w-full" value="<?php echo $g_stack_gap; ?>" min="0" max="64">
                        <span class="sh-text-sm sh-text-muted">px</span>
                    </div>
                </div>

                <!-- Variation -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">Variation</div>                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-gallery-variation-update" <?php checked($g_var_update); ?>><span class="sh-toggle-slider"></span></label>
                    <div><label for="sh-gallery-variation-update" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Variation Değişince Galeriyi Güncelle</label><p class="sh-text-sm sh-text-muted sh-m-0">Farklı bir varyasyon seçildiğinde görseller o varyasyonun görselleriyle değişir.</p></div>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md">
                    <button type="button" id="sh-gs-save-gallery" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-gallery-msg" class="sh-text-sm sh-text-success" style="display:none">✔ Kaydedildi</span>
                </div>

            </div>
        </div>

        <script>
        (function(){
            // Spinner keyframe — ilk kez burada tanımla
            if (!document.getElementById('sh-spin-style')) {
                var s = document.createElement('style');
                s.id = 'sh-spin-style';
                s.textContent = '@keyframes sh-spin{to{transform:rotate(360deg)}}';
                document.head.appendChild(s);
            }
            // Galeri tipi radio → swiper ayarlarını toggle et
            document.querySelectorAll('input[name="sh_gallery_type"]').forEach(function(r){
                r.addEventListener('change', function(){
                    var wrap = document.getElementById('sh-gallery-swiper-wrap');
                    if(wrap) wrap.style.display = this.value === 'swiper' ? 'block' : 'none';
                    var stackWrap = document.getElementById('sh-gallery-stack-wrap');
                    if(stackWrap) stackWrap.style.display = this.value === 'stack' ? 'block' : 'none';
                    // Radio label renklerini güncelle
                    document.querySelectorAll('input[name="sh_gallery_type"]').forEach(function(x){
                        var lbl = x.closest('label');
                        lbl.style.borderColor = x.checked ? '#2271b1' : '#e5e7eb';
                        lbl.style.background  = x.checked ? '#eff6ff' : '#fff';
                    });
                });
            });
            // ── Save: Product Gallery ──────────────────────────────────────
            var NONCE_G = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_G  = <?php echo wp_json_encode( $ajax ); ?>;
            var _shSaveEl=document.getElementById('sh-gs-save-gallery'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action','sh_gs_save_gallery'); fd.append('nonce',NONCE_G);
                var chk = function(id){ var el=document.getElementById(id); return (el&&el.checked)?'1':'0'; };
                var val = function(id){ var el=document.getElementById(id); return el?el.value:''; };
                fd.append('gallery_type',            document.querySelector('input[name="sh_gallery_type"]:checked')?.value||'swiper');
                fd.append('gallery_ratio',           val('sh-gallery-ratio'));
                fd.append('gallery_object_fit',      val('sh-gallery-fit'));
                fd.append('gallery_hover',           val('sh-gallery-hover'));
                fd.append('gallery_lightbox',        chk('sh-gallery-lightbox'));
                fd.append('gallery_navigation',      chk('sh-gallery-navigation'));
                fd.append('gallery_loop',            chk('sh-gallery-loop'));
                fd.append('gallery_thumbs',          chk('sh-gallery-thumbs'));
                fd.append('gallery_thumb_nav',       chk('sh-gallery-thumb-nav'));
                fd.append('gallery_variation_update',chk('sh-gallery-variation-update'));
                fd.append('gallery_pagination',      val('sh-gallery-pagination'));
                fd.append('gallery_slides_per_view', val('sh-gallery-spv'));
                fd.append('gallery_thumb_count',     val('sh-gallery-thumb-count'));
                fd.append('gallery_stack_gap',       val('sh-gallery-stack-gap') || '8');
                fetch(AJAX_G,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled = false;
                    btn.textContent = 'Kaydet';
                    // Toast notification
                    shGsToast(res.success ? '✔ Gallery ayarları kaydedildi' : '✖ Hata: ' + (res.data || 'Bilinmeyen hata'));
                }).catch(function(){
                    btn.disabled = false;
                    btn.textContent = 'Kaydet';
                });
            });
        })();
        </script>
        <?php
    }

    private static function renderWooQtySection( string $nonce, string $ajax ): void
    {
        $qty_cfg      = (array) get_option( 'options_qty_stepper', [] );
        $qty_size     = $qty_cfg['size']    ?? 'md';
        $qty_variant  = $qty_cfg['variant'] ?? '';
        ?>
        <div class="sh-card sh-mt-sm">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🔢 Quantity Stepper</span>
                <span class="sh-card-desc">Ürün sayfasındaki miktar seçici görünüm ayarları.</span>
            </div>
            <div class="sh-card-body sh-pt-0">

                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">BOYUT</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
                    <?php foreach ( [ 'sm' => '🔹 Small', 'md' => '▪ Normal', 'lg' => '🔷 Large' ] as $val => $lbl ) : ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:10px 14px;border:2px solid <?php echo $qty_size === $val ? '#2271b1' : '#e5e7eb'; ?>;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;background:<?php echo $qty_size === $val ? '#eff6ff' : '#fff'; ?>">
                        <input type="radio" name="sh_qty_size" value="<?php echo $val; ?>" <?php checked( $qty_size, $val ); ?> style="margin:0">
                        <?php echo $lbl; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:4px">GÖRÜNÜM (VARYANt)</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
                    <?php foreach ( [ '' => '⬜ Default', 'pill' => '💊 Pill', 'filled' => '🎨 Filled', 'pill filled' => '💊🎨 Pill + Filled' ] as $val => $lbl ) : ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:10px 14px;border:2px solid <?php echo $qty_variant === $val ? '#2271b1' : '#e5e7eb'; ?>;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;background:<?php echo $qty_variant === $val ? '#eff6ff' : '#fff'; ?>">
                        <input type="radio" name="sh_qty_variant" value="<?php echo esc_attr($val); ?>" <?php checked( $qty_variant, $val ); ?> style="margin:0">
                        <?php echo $lbl; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md">
                    <button type="button" id="sh-gs-save-qty-stepper" class="sh-btn sh-btn-primary">Kaydet</button>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var NONCE_Q = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_Q  = <?php echo wp_json_encode( $ajax ); ?>;

            // Radio label renk toggle — size
            document.querySelectorAll('input[name="sh_qty_size"]').forEach(function(r){
                r.addEventListener('change', function(){
                    document.querySelectorAll('input[name="sh_qty_size"]').forEach(function(x){
                        var lbl = x.closest('label');
                        lbl.style.borderColor = x.checked ? '#2271b1' : '#e5e7eb';
                        lbl.style.background  = x.checked ? '#eff6ff' : '#fff';
                    });
                });
            });
            // Radio label renk toggle — variant
            document.querySelectorAll('input[name="sh_qty_variant"]').forEach(function(r){
                r.addEventListener('change', function(){
                    document.querySelectorAll('input[name="sh_qty_variant"]').forEach(function(x){
                        var lbl = x.closest('label');
                        lbl.style.borderColor = x.checked ? '#2271b1' : '#e5e7eb';
                        lbl.style.background  = x.checked ? '#eff6ff' : '#fff';
                    });
                });
            });

            var _shSaveEl=document.getElementById('sh-gs-save-qty-stepper'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action', 'sh_gs_save_qty_stepper');
                fd.append('nonce', NONCE_Q);
                fd.append('qty_size',    document.querySelector('input[name="sh_qty_size"]:checked')?.value    || 'md');
                fd.append('qty_variant', document.querySelector('input[name="sh_qty_variant"]:checked')?.value || '');
                fetch(AJAX_Q, {method:'POST', body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled = false;
                    btn.textContent = 'Kaydet';
                    shGsToast(res.success ? '✔ Stepper ayarları kaydedildi' : '✖ Hata');
                }).catch(function(){ btn.disabled = false; btn.textContent = 'Kaydet'; });
            });
        })();
        </script>
        <?php
    }

    private static function renderWooStickySection( string $nonce, string $ajax ): void
    {
        $satc         = (array) get_option( 'options_sticky_atc', [] );
        $satc_enabled = (bool) ( $satc['enabled']          ?? false );
        $satc_manual  = (bool) ( $satc['manual_placement'] ?? false );
        $satc_pos       = $satc['position']       ?? 'bottom';
        $satc_img       = (bool) ( $satc['show_image']       ?? true );
        $satc_price     = (bool) ( $satc['show_price']       ?? true );
        $satc_reactions = (bool) ( $satc['show_reactions']   ?? true );
        $satc_thr       = (int)  ( $satc['threshold']        ?? 300 );
        $satc_btn       = $satc['btn_text']   ?? '';
        ?>
        <div class="sh-card sh-mt-sm">
            <div class="sh-card-header--col">
                <span class="sh-card-title">📌 Sticky Add to Cart</span>
                <span class="sh-card-desc">Ürün sayfasında scroll edilince görünen sabit sepete ekle çubuğu.</span>
            </div>
            <div class="sh-card-body sh-pt-0">

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-satc-enabled" <?php checked($satc_enabled); ?>><span class="sh-toggle-slider"></span></label>
                    <div><label for="sh-satc-enabled" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Aktif</label><p class="sh-text-sm sh-text-muted sh-m-0">Sticky ATC barını etkinleştirir.</p></div>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-satc-manual" <?php checked($satc_manual); ?>><span class="sh-toggle-slider"></span></label>
                    <div><label for="sh-satc-manual" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Manuel Yerleştirme</label><p class="sh-text-sm sh-text-muted sh-m-0">Twig'de <code>{% include 'woo/partials/sticky-atc.twig' %}</code> veya <code>{% do action('sh_woo_sticky_atc') %}</code> ile manuel ekle. Bu açıksa varsayılan konum (sayfa altı) devre dışı.</p></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Pozisyon</label>
                        <select id="sh-satc-position" class="sh-input sh-w-full">
                            <option value="bottom" <?php selected($satc_pos,'bottom'); ?>>⬇ Alt (bottom)</option>
                            <option value="top"    <?php selected($satc_pos,'top'); ?>>⬆ Üst (top)</option>
                        </select>
                    </div>
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Scroll Eşiği (px)</label>
                        <input type="number" id="sh-satc-threshold" class="sh-input sh-w-full" value="<?php echo $satc_thr; ?>" min="0" max="2000" step="50">
                        <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">Kaç px scroll sonrası görünsün.</p>
                    </div>
                </div>

                <div style="margin-bottom:12px">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Buton Metni <span style="opacity:.5">(boşsa varsayılan çeviri kullanılır)</span></label>
                    <input type="text" id="sh-satc-btn-text" class="sh-input sh-w-full" value="<?php echo esc_attr($satc_btn); ?>" placeholder="Sepete Ekle">
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-satc-show-image" <?php checked($satc_img); ?>><span class="sh-toggle-slider"></span></label>
                    <div><label for="sh-satc-show-image" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Ürün Görseli</label><p class="sh-text-sm sh-text-muted sh-m-0">Bar'da küçük ürün thumbnailini göster.</p></div>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-satc-show-price" <?php checked($satc_price); ?>><span class="sh-toggle-slider"></span></label>
                    <div><label for="sh-satc-show-price" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Fiyat</label><p class="sh-text-sm sh-text-muted sh-m-0">Bar'da ürün fiyatını göster.</p></div>
                </div>

                <?php if ( defined('ENABLE_REACTIONS') && ENABLE_REACTIONS ) : ?>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-satc-show-reactions" <?php checked($satc_reactions); ?>><span class="sh-toggle-slider"></span></label>
                    <div><label for="sh-satc-show-reactions" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Reactions (Favori, Beğeni)</label><p class="sh-text-sm sh-text-muted sh-m-0">Bar'da reaction butonlarını göster.</p></div>
                </div>
                <?php endif; ?>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md">
                    <button type="button" id="sh-gs-save-sticky-atc" class="sh-btn sh-btn-primary">Kaydet</button>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var NONCE_S = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_S  = <?php echo wp_json_encode( $ajax ); ?>;
            var chk = function(id){ var el=document.getElementById(id); return (el&&el.checked)?'1':'0'; };
            var val = function(id){ var el=document.getElementById(id); return el?el.value:''; };

            var _shSaveEl=document.getElementById('sh-gs-save-sticky-atc'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action','sh_gs_save_sticky_atc'); fd.append('nonce',NONCE_S);
                fd.append('sticky_atc_enabled',    chk('sh-satc-enabled'));
                fd.append('sticky_atc_manual',     chk('sh-satc-manual'));
                fd.append('sticky_atc_position',   val('sh-satc-position'));
                fd.append('sticky_atc_threshold',  val('sh-satc-threshold'));
                fd.append('sticky_atc_btn_text',   val('sh-satc-btn-text'));
                fd.append('sticky_atc_show_image', chk('sh-satc-show-image'));
                fd.append('sticky_atc_show_price', chk('sh-satc-show-price'));
                fd.append('sticky_atc_show_reactions', chk('sh-satc-show-reactions'));
                fetch(AJAX_S,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled = false; btn.textContent = 'Kaydet';
                    shGsToast(res.success ? '✔ Sticky ATC ayarları kaydedildi' : '✖ Hata');
                }).catch(function(){ btn.disabled=false; btn.textContent='Kaydet'; });
            });
        })();
        </script>
        <?php
    }

    private static function renderWooListsSection( string $nonce, string $ajax ): void
    {
        $pl        = (array) get_option( 'options_product_lists', [] );
        $pl_r      = (array) ( $pl['related']        ?? [] );
        $pl_u      = (array) ( $pl['upsell']          ?? [] );
        $pl_c      = (array) ( $pl['crosssell']       ?? [] );
        $pl_rv     = (array) ( $pl['recently_viewed'] ?? [] );

        $layout_opts = [ 'carousel' => '🎠 Carousel', 'grid' => '▦ Grid' ];
        $size_opts   = [ 'sm' => 'Small', 'md' => 'Normal', 'lg' => 'Large' ];

        // Helper — tek bir list section render et (2 kolon: sol=ayarlar, sağ=include kodu)
        $render_list_section = function( string $key, array $cfg, string $title_default, string $extra_fields = '' ) use ( $layout_opts, $size_opts ) {
            $enabled     = (bool) ( $cfg['enabled']     ?? false );
            $title       = $cfg['title']      ?? '';
            $count       = (int) ( $cfg['count']        ?? 4 );
            $layout      = $cfg['layout']     ?? 'carousel';
            $size        = $cfg['size']        ?? 'md';
            $nav_desktop = $cfg['nav_desktop'] ?? 'arrows';
            $nav_mobile  = $cfg['nav_mobile']  ?? 'dots';
            $bps         = (array) ( $cfg['breakpoints']['spv']  ?? [] );
            $gaps        = (array) ( $cfg['breakpoints']['gaps'] ?? [] );
            $all_bps_keys = array_keys( (array) \Data::get('breakpoints') );
            if ( empty( $all_bps_keys ) ) $all_bps_keys = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
            $bp_keys = array_combine( $all_bps_keys, array_map( 'strtoupper', $all_bps_keys ) );
            $bp_defaults_spv  = [ 'xs'=>1.5, 'sm'=>2, 'md'=>3, 'lg'=>4, 'xl'=>4, 'xxl'=>5, 'xxxl'=>5 ];
            $bp_defaults_gaps = [ 'xs'=>10, 'sm'=>12, 'md'=>15, 'lg'=>20, 'xl'=>20, 'xxl'=>24, 'xxxl'=>24 ];

            // Include code — title varsa translate ile, yoksa boş
            $title_code = ! empty( $title )
                ? "\n    'title': translate('" . esc_js( $title ) . "')"
                : '';
            $code = "{% include 'woo/partials/product-list.twig' with {'list_type':'{$key}'{$title_code}} %}";
            // Breakpoint defaults (JS'e de gönderilecek)
            $bp_defs_spv_json  = wp_json_encode( $bp_defaults_spv );
            $bp_defs_gaps_json = wp_json_encode( $bp_defaults_gaps );
            ?>
            <div class="sh-card sh-mt-sm sh-pl-section" data-key="<?php echo esc_attr($key); ?>">
                <!-- HEADER: tıklanabilir collapse + aktif switch -->
                <div class="sh-card-header" style="display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:default">
                    <!-- Aktif Toggle — click propagation'ı engelle -->
                    <label class="sh-toggle" onclick="event.stopPropagation()" style="flex-shrink:0">
                        <input type="checkbox"
                               id="sh-pl-<?php echo $key; ?>-enabled"
                               <?php checked($enabled); ?>
                               onchange="shPlToggleSection(this, '<?php echo esc_js($key); ?>')">
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <!-- Başlık + özet — tıklayınca body aç/kapa (sadece aktifse) -->
                    <div style="flex:1;cursor:pointer;user-select:none"
                         onclick="shPlHeaderClick('<?php echo esc_js($key); ?>')">
                        <span class="sh-card-title" style="display:block"><?php echo $title_default; ?></span>
                        <span class="sh-pl-desc-<?php echo $key; ?> sh-card-desc sh-text-muted sh-text-sm">
                            <?php echo ucfirst($layout); ?> | Size: <?php echo $size; ?>
                        </span>
                    </div>
                </div>
                <!-- BODY: aktif değilse başlangıçta kapalı -->
                <div class="sh-card-body sh-pt-0 sh-pl-body-<?php echo $key; ?>"
                     style="<?php echo $enabled ? '' : 'display:none'; ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

                        <!-- SOL: Ayarlar -->
                        <div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                                <div>
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Başlık</label>
                                    <input type="text" id="sh-pl-<?php echo $key; ?>-title" class="sh-input sh-w-full" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo $title_default; ?>">
                                </div>
                                <div>
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Kaç Ürün</label>
                                    <input type="number" id="sh-pl-<?php echo $key; ?>-count" class="sh-input sh-w-full" value="<?php echo $count; ?>" min="2" max="24">
                                </div>
                            </div>

                            <div style="margin-bottom:10px">
                                <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Tease Size</label>
                                <select id="sh-pl-<?php echo $key; ?>-size" class="sh-input sh-w-full">
                                    <?php foreach ( $size_opts as $v => $l ) : ?>
                                    <option value="<?php echo $v; ?>" <?php selected($size,$v); ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display:flex;gap:8px;margin-bottom:10px">
                                <?php foreach ( $layout_opts as $v => $l ) : ?>
                                <label style="display:flex;align-items:center;gap:5px;padding:6px 10px;border:2px solid <?php echo $layout===$v?'#2271b1':'#e5e7eb'; ?>;border-radius:6px;cursor:pointer;font-size:11px;font-weight:500;background:<?php echo $layout===$v?'#eff6ff':'#fff'; ?>">
                                    <input type="radio" name="sh_pl_<?php echo $key; ?>_layout" value="<?php echo $v; ?>" <?php checked($layout,$v); ?> style="margin:0"> <?php echo $l; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <?php
                            // Navigation seçenekleri — sadece carousel layout'ta anlamlı
                            $nav_opts = [
                                'none'       => '✕ Yok',
                                'arrows'     => '‹› Oklar',
                                'dots'       => '● Dots',
                                'scrollbar'  => '━ Scrollbar',
                                'arrows_dots'=> '‹●› Oklar + Dots',
                            ];
                            ?>
                            <div class="sh-pl-nav-wrap-<?php echo $key; ?>" style="<?php echo $layout !== 'carousel' ? 'display:none' : ''; ?>">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                                    <div>
                                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">🖥 Desktop Nav</label>
                                        <select name="<?php echo $key; ?>_nav_desktop" class="sh-input sh-w-full sh-pl-nav-desktop">
                                            <?php foreach ( $nav_opts as $v => $l ) : ?>
                                            <option value="<?php echo $v; ?>" <?php selected($nav_desktop,$v); ?>><?php echo $l; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">📱 Mobile Nav</label>
                                        <select name="<?php echo $key; ?>_nav_mobile" class="sh-input sh-w-full sh-pl-nav-mobile">
                                            <?php foreach ( $nav_opts as $v => $l ) : ?>
                                            <option value="<?php echo $v; ?>" <?php selected($nav_mobile,$v); ?>><?php echo $l; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <?php echo $extra_fields; ?>

                            <!-- Breakpoints -->
                            <div style="margin-top:10px">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <label class="sh-text-sm sh-font-semibold sh-text-label">Breakpoints (SPV / Gap px)</label>
                                    <button type="button"
                                            class="sh-pl-bp-reset"
                                            data-key="<?php echo esc_attr($key); ?>"
                                            data-defaults-spv='<?php echo $bp_defs_spv_json; ?>'
                                            data-defaults-gaps='<?php echo $bp_defs_gaps_json; ?>'
                                            style="font-size:11px;padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;background:#fff;cursor:pointer;color:#6b7280;white-space:nowrap">
                                        ↺ Reset to Defaults
                                    </button>
                                </div>
                                <table style="width:100%;border-collapse:collapse;font-size:12px">
                                    <thead><tr>
                                        <th style="text-align:left;padding:3px 4px;color:#666">BP</th>
                                        <th style="padding:3px 4px;color:#666">Slide/View</th>
                                        <th style="padding:3px 4px;color:#666">Gap px</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php foreach ( $bp_keys as $bp => $bp_label ) : ?>
                                    <tr>
                                        <td style="padding:3px 4px;font-weight:600"><?php echo $bp_label; ?></td>
                                        <td style="padding:3px 4px"><input type="number" name="<?php echo $key; ?>_spv_<?php echo $bp; ?>" class="sh-input sh-w-full sh-pl-bp-spv" data-bp="<?php echo $bp; ?>" style="padding:2px 4px;min-height:0;height:28px" value="<?php echo esc_attr( $bps[$bp] ?? $bp_defaults_spv[$bp] ); ?>" min="0.5" max="12" step="0.5" placeholder="<?php echo $bp_defaults_spv[$bp] ?? 1; ?>"></td>
                                        <td style="padding:3px 4px"><input type="number" name="<?php echo $key; ?>_gap_<?php echo $bp; ?>" class="sh-input sh-w-full sh-pl-bp-gap" data-bp="<?php echo $bp; ?>" style="padding:2px 4px;min-height:0;height:28px" value="<?php echo esc_attr( $gaps[$bp] ?? $bp_defaults_gaps[$bp] ); ?>" min="0" max="60" placeholder="<?php echo $bp_defaults_gaps[$bp] ?? 15; ?>"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- SAĞ: Include kodu -->
                        <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                                <span class="sh-text-sm sh-font-semibold sh-text-label">Twig Include Kodu</span>
                                <button type="button"
                                        onclick="var t=document.getElementById('sh-pl-code-<?php echo $key; ?>');navigator.clipboard.writeText(t.value);this.textContent='✅ Kopyalandı';setTimeout(()=>this.textContent='📋 Kopyala',2000)"
                                        style="font-size:11px;padding:3px 8px;border:1px solid #ddd;border-radius:4px;background:#fff;cursor:pointer">📋 Kopyala</button>
                            </div>
                            <textarea id="sh-pl-code-<?php echo $key; ?>"
                                      readonly
                                      onclick="this.select()"
                                      style="width:100%;height:60px;font-family:monospace;font-size:11px;padding:6px;border:1px solid #ddd;border-radius:4px;background:#fff;resize:none;color:#333"><?php echo esc_textarea( $code ); ?></textarea>
                            <p class="sh-text-sm sh-text-muted sh-m-0">Bu kodu <code>single-product.twig</code>'de istediğin yere yapıştır. Title override için:</p>
                            <textarea readonly
                                      onclick="this.select()"
                                      style="width:100%;height:80px;font-family:monospace;font-size:11px;padding:6px;border:1px solid #ddd;border-radius:4px;background:#fff;resize:none;color:#666"><?php echo esc_textarea( "{% include 'woo/partials/product-list.twig' with {\n    'list_type':'{$key}',\n    'title':'Özel Başlık'\n} %}" ); ?></textarea>
                        </div>

                    </div>
                </div>
            </div>
            <?php
        };

        // Related
        $related_source = $pl_r['source'] ?? 'wc_related';
        ob_start();
        ?><div style="margin-bottom:12px">
            <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Kaynak</label>
            <select id="sh-pl-related-source" class="sh-input sh-w-full">
                <option value="wc_related" <?php selected($related_source,'wc_related'); ?>>WC Related (varsayılan)</option>
                <option value="same_category" <?php selected($related_source,'same_category'); ?>>Aynı Kategori</option>
                <option value="same_tag" <?php selected($related_source,'same_tag'); ?>>Aynı Etiket</option>
            </select>
        </div><?php
        $render_list_section( 'related', $pl_r, '🔗 Related Products', ob_get_clean() );

        // Upsell
        $render_list_section( 'upsell', $pl_u, '⬆ Upsell Products' );

        // Cross-sell
        $cs_location = $pl_c['location'] ?? 'both';
        ob_start();
        ?><div style="margin-bottom:12px">
            <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Gösterim Yeri</label>
            <select id="sh-pl-crosssell-location" class="sh-input sh-w-full">
                <option value="product_page" <?php selected($cs_location,'product_page'); ?>>Sadece Ürün Sayfası</option>
                <option value="cart_page" <?php selected($cs_location,'cart_page'); ?>>Sadece Sepet</option>
                <option value="both" <?php selected($cs_location,'both'); ?>>Her İkisi</option>
            </select>
        </div><?php
        $render_list_section( 'crosssell', $pl_c, '↔ Cross-sell Products', ob_get_clean() );

        // Recently Viewed
        $rv_exclude = (bool)($pl_rv['exclude_current'] ?? true);
        ob_start();
        ?><div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
            <label class="sh-toggle"><input type="checkbox" id="sh-pl-rv-exclude-current" <?php checked($rv_exclude); ?>><span class="sh-toggle-slider"></span></label>
            <div><label for="sh-pl-rv-exclude-current" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Mevcut ürünü listeden çıkar</label></div>
        </div><?php
        $render_list_section( 'recently_viewed', $pl_rv, '🕐 Recently Viewed', ob_get_clean() );
        ?>

        <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md" style="padding:0 16px 16px">
            <button type="button" id="sh-gs-save-product-lists" class="sh-btn sh-btn-primary">Tüm Liste Ayarlarını Kaydet</button>
        </div>

        <script>
        (function(){
            var NONCE_PL = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_PL  = <?php echo wp_json_encode( $ajax ); ?>;
            var chk = function(id){ var el=document.getElementById(id); return (el&&el.checked)?'1':'0'; };
            var val = function(id){ var el=document.getElementById(id); return el?el.value:''; };
            var rad = function(name){ var el=document.querySelector('input[name="'+name+'"]:checked'); return el?el.value:''; };

            // ── Section header collapse / aktif toggle helpers ──────────────

            // Aktif switch değişince: pasifse body kapat, aktifse aç
            window.shPlToggleSection = function(checkbox, key) {
                var body = document.querySelector('.sh-pl-body-' + key);
                if (!body) return;
                body.style.display = checkbox.checked ? '' : 'none';
            };

            // Header tıklanınca: sadece aktifse aç/kapat
            window.shPlHeaderClick = function(key) {
                var cb   = document.getElementById('sh-pl-' + key + '-enabled');
                var body = document.querySelector('.sh-pl-body-' + key);
                if (!cb || !body || !cb.checked) return; // pasifse aç
                body.style.display = body.style.display === 'none' ? '' : 'none';
            };

            // ── Reset to Defaults butonları ─────────────────────────────────
            document.querySelectorAll('.sh-pl-bp-reset').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var key        = btn.dataset.key;
                    var defSpv     = JSON.parse(btn.dataset.defaultsSpv  || '{}');
                    var defGaps    = JSON.parse(btn.dataset.defaultsGaps || '{}');
                    var section    = btn.closest('.sh-pl-section');
                    if (!section) return;
                    section.querySelectorAll('.sh-pl-bp-spv').forEach(function(inp) {
                        var bp = inp.dataset.bp;
                        if (defSpv[bp] !== undefined) inp.value = defSpv[bp];
                    });
                    section.querySelectorAll('.sh-pl-bp-gap').forEach(function(inp) {
                        var bp = inp.dataset.bp;
                        if (defGaps[bp] !== undefined) inp.value = defGaps[bp];
                    });
                    // Görsel feedback
                    btn.textContent = '✅ Sıfırlandı';
                    btn.style.color = '#16a34a';
                    btn.style.borderColor = '#16a34a';
                    setTimeout(function() {
                        btn.textContent = '↺ Reset to Defaults';
                        btn.style.color = '#6b7280';
                        btn.style.borderColor = '#d1d5db';
                    }, 2000);
                });
            });

            // Radio label toggle
            ['related','upsell','crosssell','recently_viewed'].forEach(function(k){
                var rname = 'sh_pl_'+k+'_layout';
                document.querySelectorAll('input[name="'+rname+'"]').forEach(function(r){
                    r.addEventListener('change', function(){
                        document.querySelectorAll('input[name="'+rname+'"]').forEach(function(x){
                            var lbl = x.closest('label');
                            if(lbl){ lbl.style.borderColor=x.checked?'#2271b1':'#e5e7eb'; lbl.style.background=x.checked?'#eff6ff':'#fff'; }
                        });
                        // Nav seçeneklerini layout'a göre göster/gizle
                        var navWrap = document.querySelector('.sh-pl-nav-wrap-'+k);
                        if (navWrap) navWrap.style.display = this.value === 'carousel' ? '' : 'none';
                    });
                });
            });

            var _shSaveEl=document.getElementById('sh-gs-save-product-lists'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this; btn.disabled=true;
                btn.innerHTML='<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action','sh_gs_save_product_lists'); fd.append('nonce',NONCE_PL);
                // Related
                fd.append('related_enabled', chk('sh-pl-related-enabled'));
                fd.append('related_title',   val('sh-pl-related-title'));
                fd.append('related_count',   val('sh-pl-related-count'));
                fd.append('related_layout',  rad('sh_pl_related_layout'));
                fd.append('related_size',    val('sh-pl-related-size'));
                fd.append('related_source',  val('sh-pl-related-source'));
                // Upsell
                fd.append('upsell_enabled',  chk('sh-pl-upsell-enabled'));
                fd.append('upsell_title',    val('sh-pl-upsell-title'));
                fd.append('upsell_count',    val('sh-pl-upsell-count'));
                fd.append('upsell_layout',   rad('sh_pl_upsell_layout'));
                fd.append('upsell_size',     val('sh-pl-upsell-size'));
                // Cross-sell
                fd.append('crosssell_enabled',  chk('sh-pl-crosssell-enabled'));
                fd.append('crosssell_title',    val('sh-pl-crosssell-title'));
                fd.append('crosssell_count',    val('sh-pl-crosssell-count'));
                fd.append('crosssell_layout',   rad('sh_pl_crosssell_layout'));
                fd.append('crosssell_size',     val('sh-pl-crosssell-size'));
                fd.append('crosssell_location', val('sh-pl-crosssell-location'));
                // Recently Viewed
                fd.append('rv_enabled',          chk('sh-pl-recently_viewed-enabled'));
                fd.append('rv_title',            val('sh-pl-recently_viewed-title'));
                fd.append('rv_count',            val('sh-pl-recently_viewed-count'));
                fd.append('rv_layout',           rad('sh_pl_recently_viewed_layout'));
                fd.append('rv_size',             val('sh-pl-recently_viewed-size'));
                fd.append('rv_exclude_current',  chk('sh-pl-rv-exclude-current'));

                // Breakpoints — tüm section'lardaki SPV/Gap inputlarını toplu gönder
                // Input name'leri: {key}_spv_{bp} / {key}_gap_{bp}
                document.querySelectorAll('.sh-pl-bp-spv, .sh-pl-bp-gap').forEach(function(inp) {
                    if (inp.name) fd.append(inp.name, inp.value);
                });
                // Navigation seçenekleri
                ['related','upsell','crosssell','rv'].forEach(function(k) {
                    var deskSel = document.querySelector('[name="'+k+'_nav_desktop"]');
                    var mobSel  = document.querySelector('[name="'+k+'_nav_mobile"]');
                    if (deskSel) fd.append(k+'_nav_desktop', deskSel.value);
                    if (mobSel)  fd.append(k+'_nav_mobile',  mobSel.value);
                });

                fetch(AJAX_PL,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled=false; btn.textContent='Tüm Liste Ayarlarını Kaydet';
                    var toast=document.createElement('div');
                    toast.style.cssText='position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:500;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:opacity .3s;'+(res.success?'background:#16a34a':'background:#dc2626');
                    toast.textContent=res.success?'✔ Liste ayarları kaydedildi':'✖ Hata';
                    document.body.appendChild(toast);
                    setTimeout(function(){ toast.style.opacity='0'; setTimeout(function(){ toast.remove(); },300); },3000);
                }).catch(function(){ btn.disabled=false; btn.textContent='Tüm Liste Ayarlarını Kaydet'; });
            });
        })();
        </script>
        <?php
    }

    // ─── Cart Tab ────────────────────────────────────────────────────────────

    private static function renderWooCartSection( string $nonce, string $ajax ): void
    {
        $pl        = (array) get_option( 'options_product_lists', [] );
        $pl_nis    = (array) ( $pl['new_in_store'] ?? [] );

        $layout_opts = [ 'carousel' => '🎠 Carousel', 'grid' => '▦ Grid' ];
        $size_opts   = [ 'sm' => 'Small', 'md' => 'Normal', 'lg' => 'Large' ];

        $render_list_section = function( string $key, array $cfg, string $title_default, string $extra_fields = '' ) use ( $layout_opts, $size_opts ) {
            $enabled     = (bool) ( $cfg['enabled']     ?? false );
            $title       = $cfg['title']      ?? '';
            $count       = (int) ( $cfg['count']        ?? 8 );
            $layout      = $cfg['layout']     ?? 'carousel';
            $size        = $cfg['size']        ?? 'md';
            $nav_desktop = $cfg['nav_desktop'] ?? 'arrows';
            $nav_mobile  = $cfg['nav_mobile']  ?? 'arrows';
            $bps         = (array) ( $cfg['breakpoints']['spv']  ?? [] );
            $gaps        = (array) ( $cfg['breakpoints']['gaps'] ?? [] );
            $all_bps_keys = array_keys( (array) \Data::get('breakpoints') );
            if ( empty( $all_bps_keys ) ) $all_bps_keys = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
            $bp_keys = array_combine( $all_bps_keys, array_map( 'strtoupper', $all_bps_keys ) );
            $bp_defaults_spv  = [ 'xs'=>1.5, 'sm'=>2, 'md'=>3, 'lg'=>4, 'xl'=>4, 'xxl'=>5, 'xxxl'=>5 ];
            $bp_defaults_gaps = [ 'xs'=>10, 'sm'=>12, 'md'=>15, 'lg'=>20, 'xl'=>20, 'xxl'=>24, 'xxxl'=>24 ];

            $title_code = ! empty( $title )
                ? "\n    'title': translate('" . esc_js( $title ) . "')"
                : '';
            $code = "{% include 'woo/partials/product-list.twig' with {'list_type':'{$key}'{$title_code}} %}";
            $bp_defs_spv_json  = wp_json_encode( $bp_defaults_spv );
            $bp_defs_gaps_json = wp_json_encode( $bp_defaults_gaps );
            ?>
            <div class="sh-card sh-mt-sm sh-pl-section" data-key="<?php echo esc_attr($key); ?>">
                <div class="sh-card-header" style="display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:default">
                    <label class="sh-toggle" onclick="event.stopPropagation()" style="flex-shrink:0">
                        <input type="checkbox"
                               id="sh-pl-<?php echo $key; ?>-enabled"
                               <?php checked($enabled); ?>
                               onchange="shPlCartToggleSection(this, '<?php echo esc_js($key); ?>')">
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div style="flex:1;cursor:pointer;user-select:none"
                         onclick="shPlCartHeaderClick('<?php echo esc_js($key); ?>')">
                        <span class="sh-card-title" style="display:block"><?php echo $title_default; ?></span>
                        <span class="sh-pl-desc-<?php echo $key; ?> sh-card-desc sh-text-muted sh-text-sm">
                            <?php echo ucfirst($layout); ?> | Size: <?php echo $size; ?>
                        </span>
                    </div>
                </div>
                <div class="sh-card-body sh-pt-0 sh-pl-body-<?php echo $key; ?>"
                     style="<?php echo $enabled ? '' : 'display:none'; ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                        <div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                                <div>
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Başlık</label>
                                    <input type="text" id="sh-pl-<?php echo $key; ?>-title" class="sh-input sh-w-full" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo $title_default; ?>">
                                </div>
                                <div>
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Kaç Ürün</label>
                                    <input type="number" id="sh-pl-<?php echo $key; ?>-count" class="sh-input sh-w-full" value="<?php echo $count; ?>" min="2" max="24">
                                </div>
                            </div>
                            <div style="margin-bottom:10px">
                                <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Tease Size</label>
                                <select id="sh-pl-<?php echo $key; ?>-size" class="sh-input sh-w-full">
                                    <?php foreach ( $size_opts as $v => $l ) : ?>
                                    <option value="<?php echo $v; ?>" <?php selected($size,$v); ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display:flex;gap:8px;margin-bottom:10px">
                                <?php foreach ( $layout_opts as $v => $l ) : ?>
                                <label style="display:flex;align-items:center;gap:5px;padding:6px 10px;border:2px solid <?php echo $layout===$v?'#2271b1':'#e5e7eb'; ?>;border-radius:6px;cursor:pointer;font-size:11px;font-weight:500;background:<?php echo $layout===$v?'#eff6ff':'#fff'; ?>">
                                    <input type="radio" name="sh_pl_cart_<?php echo $key; ?>_layout" value="<?php echo $v; ?>" <?php checked($layout,$v); ?> style="margin:0"> <?php echo $l; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php
                            $nav_opts = [
                                'none'       => '✕ Yok',
                                'arrows'     => '‹› Oklar',
                                'dots'       => '● Dots',
                                'scrollbar'  => '━ Scrollbar',
                                'arrows_dots'=> '‹●› Oklar + Dots',
                            ];
                            ?>
                            <div class="sh-pl-cart-nav-wrap-<?php echo $key; ?>" style="<?php echo $layout !== 'carousel' ? 'display:none' : ''; ?>">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                                    <div>
                                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">🖥 Desktop Nav</label>
                                        <select name="cart_<?php echo $key; ?>_nav_desktop" class="sh-input sh-w-full sh-pl-cart-nav-desktop">
                                            <?php foreach ( $nav_opts as $v => $l ) : ?>
                                            <option value="<?php echo $v; ?>" <?php selected($nav_desktop,$v); ?>><?php echo $l; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">📱 Mobile Nav</label>
                                        <select name="cart_<?php echo $key; ?>_nav_mobile" class="sh-input sh-w-full sh-pl-cart-nav-mobile">
                                            <?php foreach ( $nav_opts as $v => $l ) : ?>
                                            <option value="<?php echo $v; ?>" <?php selected($nav_mobile,$v); ?>><?php echo $l; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php echo $extra_fields; ?>
                            <!-- Breakpoints -->
                            <div style="margin-top:10px">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <label class="sh-text-sm sh-font-semibold sh-text-label">Breakpoints (SPV / Gap px)</label>
                                    <button type="button"
                                            class="sh-pl-cart-bp-reset"
                                            data-key="<?php echo esc_attr($key); ?>"
                                            data-defaults-spv='<?php echo $bp_defs_spv_json; ?>'
                                            data-defaults-gaps='<?php echo $bp_defs_gaps_json; ?>'
                                            style="font-size:11px;padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;background:#fff;cursor:pointer;color:#6b7280;white-space:nowrap">
                                        ↺ Reset to Defaults
                                    </button>
                                </div>
                                <table style="width:100%;border-collapse:collapse;font-size:12px">
                                    <thead><tr>
                                        <th style="text-align:left;padding:3px 4px;color:#666">BP</th>
                                        <th style="padding:3px 4px;color:#666">Slide/View</th>
                                        <th style="padding:3px 4px;color:#666">Gap px</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php foreach ( $bp_keys as $bp => $bp_label ) : ?>
                                    <tr>
                                        <td style="padding:3px 4px;font-weight:600"><?php echo $bp_label; ?></td>
                                        <td style="padding:3px 4px"><input type="number" name="cart_<?php echo $key; ?>_spv_<?php echo $bp; ?>" class="sh-input sh-w-full sh-pl-cart-bp-spv" data-bp="<?php echo $bp; ?>" style="padding:2px 4px;min-height:0;height:28px" value="<?php echo esc_attr( $bps[$bp] ?? $bp_defaults_spv[$bp] ); ?>" min="0.5" max="12" step="0.5"></td>
                                        <td style="padding:3px 4px"><input type="number" name="cart_<?php echo $key; ?>_gap_<?php echo $bp; ?>" class="sh-input sh-w-full sh-pl-cart-bp-gap" data-bp="<?php echo $bp; ?>" style="padding:2px 4px;min-height:0;height:28px" value="<?php echo esc_attr( $gaps[$bp] ?? $bp_defaults_gaps[$bp] ); ?>" min="0" max="60"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- SAĞ: Include kodu -->
                        <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                                <span class="sh-text-sm sh-font-semibold sh-text-label">Twig Include Kodu</span>
                                <button type="button"
                                        onclick="var t=document.getElementById('sh-pl-cart-code-<?php echo $key; ?>');navigator.clipboard.writeText(t.value);this.textContent='✅ Kopyalandı';setTimeout(()=>this.textContent='📋 Kopyala',2000)"
                                        style="font-size:11px;padding:3px 8px;border:1px solid #ddd;border-radius:4px;background:#fff;cursor:pointer">📋 Kopyala</button>
                            </div>
                            <textarea id="sh-pl-cart-code-<?php echo $key; ?>"
                                      readonly onclick="this.select()"
                                      style="width:100%;height:60px;font-family:monospace;font-size:11px;padding:6px;border:1px solid #ddd;border-radius:4px;background:#fff;resize:none;color:#333"><?php echo esc_textarea( $code ); ?></textarea>
                            <p class="sh-text-sm sh-text-muted sh-m-0">Boş sepet sayfasında otomatik render edilir. <code>template-cart.php</code> üzerinden yönetilir.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        };

        $render_list_section( 'new_in_store', $pl_nis, '🆕 New in Store' );
        ?>
        <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md" style="padding:0 16px 16px">
            <button type="button" id="sh-gs-save-cart-lists" class="sh-btn sh-btn-primary">Kaydet</button>
        </div>
        <script>
        (function(){
            var NONCE_CART = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_CART  = <?php echo wp_json_encode( $ajax ); ?>;
            var chk = function(id){ var el=document.getElementById(id); return (el&&el.checked)?'1':'0'; };
            var val = function(id){ var el=document.getElementById(id); return el?el.value:''; };
            var rad = function(name){ var el=document.querySelector('input[name="'+name+'"]:checked'); return el?el.value:''; };

            window.shPlCartToggleSection = function(checkbox, key) {
                var body = document.querySelector('.sh-pl-body-' + key);
                if (!body) return;
                body.style.display = checkbox.checked ? '' : 'none';
            };
            window.shPlCartHeaderClick = function(key) {
                var cb   = document.getElementById('sh-pl-' + key + '-enabled');
                var body = document.querySelector('.sh-pl-body-' + key);
                if (!cb || !body || !cb.checked) return;
                body.style.display = body.style.display === 'none' ? '' : 'none';
            };

            document.querySelectorAll('.sh-pl-cart-bp-reset').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var key    = btn.dataset.key;
                    var defSpv  = JSON.parse(btn.dataset.defaultsSpv  || '{}');
                    var defGaps = JSON.parse(btn.dataset.defaultsGaps || '{}');
                    var section = btn.closest('.sh-pl-section');
                    if (!section) return;
                    section.querySelectorAll('.sh-pl-cart-bp-spv').forEach(function(inp){ var bp=inp.dataset.bp; if(defSpv[bp]!==undefined) inp.value=defSpv[bp]; });
                    section.querySelectorAll('.sh-pl-cart-bp-gap').forEach(function(inp){ var bp=inp.dataset.bp; if(defGaps[bp]!==undefined) inp.value=defGaps[bp]; });
                    btn.textContent='✅ Sıfırlandı'; btn.style.color='#16a34a'; btn.style.borderColor='#16a34a';
                    setTimeout(function(){ btn.textContent='↺ Reset to Defaults'; btn.style.color='#6b7280'; btn.style.borderColor='#d1d5db'; }, 2000);
                });
            });

            // Layout radio → nav görünürlük
            ['new_in_store'].forEach(function(k){
                var rname = 'sh_pl_cart_'+k+'_layout';
                document.querySelectorAll('input[name="'+rname+'"]').forEach(function(r){
                    r.addEventListener('change', function(){
                        document.querySelectorAll('input[name="'+rname+'"]').forEach(function(x){
                            var lbl = x.closest('label');
                            if(lbl){ lbl.style.borderColor=x.checked?'#2271b1':'#e5e7eb'; lbl.style.background=x.checked?'#eff6ff':'#fff'; }
                        });
                        var navWrap = document.querySelector('.sh-pl-cart-nav-wrap-'+k);
                        if (navWrap) navWrap.style.display = this.value === 'carousel' ? '' : 'none';
                    });
                });
            });

            var _cartSaveEl = document.getElementById('sh-gs-save-cart-lists');
            if (_cartSaveEl) _cartSaveEl.addEventListener('click', function(){
                var btn = this; btn.disabled=true;
                btn.innerHTML='<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';
                var fd = new FormData();
                fd.append('action','sh_gs_save_cart_lists');
                fd.append('nonce', NONCE_CART);
                // new_in_store
                fd.append('nis_enabled', chk('sh-pl-new_in_store-enabled'));
                fd.append('nis_title',   val('sh-pl-new_in_store-title'));
                fd.append('nis_count',   val('sh-pl-new_in_store-count'));
                fd.append('nis_layout',  rad('sh_pl_cart_new_in_store_layout'));
                fd.append('nis_size',    val('sh-pl-new_in_store-size'));
                // Breakpoints
                document.querySelectorAll('.sh-pl-cart-bp-spv, .sh-pl-cart-bp-gap').forEach(function(inp){
                    if (inp.name) fd.append(inp.name, inp.value);
                });
                // Nav
                var deskSel = document.querySelector('[name="cart_new_in_store_nav_desktop"]');
                var mobSel  = document.querySelector('[name="cart_new_in_store_nav_mobile"]');
                if (deskSel) fd.append('nis_nav_desktop', deskSel.value);
                if (mobSel)  fd.append('nis_nav_mobile',  mobSel.value);

                fetch(AJAX_CART,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled=false; btn.textContent='Kaydet';
                    if (typeof window.shGsToast === 'function') {
                        window.shGsToast(res.success ? '✔ Cart ayarları kaydedildi' : '✖ Hata', res.success);
                    }
                }).catch(function(){ btn.disabled=false; btn.textContent='Kaydet'; });
            });
        })();
        </script>
        <?php
    }

    public static function ajaxSaveCartLists(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $valid_layouts = [ 'grid', 'carousel' ];
        $valid_sizes   = [ 'sm', 'md', 'lg' ];
        $valid_nav     = [ 'none', 'arrows', 'dots', 'scrollbar', 'arrows_dots' ];

        $all_bps = array_keys( (array) \Data::get('breakpoints') );
        if ( empty( $all_bps ) ) $all_bps = [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl', 'xxxl' ];
        $default_spv  = [ 'xs'=>1.5, 'sm'=>2, 'md'=>3, 'lg'=>4, 'xl'=>4, 'xxl'=>5, 'xxxl'=>5 ];
        $default_gaps = [ 'xs'=>10, 'sm'=>12, 'md'=>15, 'lg'=>20, 'xl'=>20, 'xxl'=>24, 'xxxl'=>24 ];

        $parse_bp = function( string $prefix ) use ( $all_bps, $default_spv, $default_gaps ) : array {
            $spv = []; $gaps = [];
            foreach ( $all_bps as $bp ) {
                $spv_val  = isset($_POST["cart_{$prefix}_spv_{$bp}"]) && $_POST["cart_{$prefix}_spv_{$bp}"] !== '' ? (float)$_POST["cart_{$prefix}_spv_{$bp}"] : ($default_spv[$bp] ?? 1);
                $gap_val  = isset($_POST["cart_{$prefix}_gap_{$bp}"]) && $_POST["cart_{$prefix}_gap_{$bp}"] !== '' ? (int)$_POST["cart_{$prefix}_gap_{$bp}"]   : ($default_gaps[$bp] ?? 15);
                $spv[$bp] = min(12, max(0.5, $spv_val));
                $gaps[$bp] = min(60, max(0, $gap_val));
            }
            return [ 'spv' => $spv, 'gaps' => $gaps ];
        };

        // Mevcut listeyi al, sadece new_in_store güncelle — diğerleri korunur
        $lists = (array) get_option( 'options_product_lists', [] );

        $lists['new_in_store'] = [
            'enabled'     => ! empty( $_POST['nis_enabled'] ) && $_POST['nis_enabled'] === '1',
            'title'       => sanitize_text_field( $_POST['nis_title']  ?? '' ),
            'count'       => max( 2, min( 24, (int) ( $_POST['nis_count'] ?? 8 ) ) ),
            'layout'      => in_array( $_POST['nis_layout'] ?? 'carousel', $valid_layouts, true ) ? $_POST['nis_layout'] : 'carousel',
            'size'        => in_array( $_POST['nis_size']   ?? 'md', $valid_sizes, true ) ? $_POST['nis_size'] : 'md',
            'nav_desktop' => in_array( $_POST['nis_nav_desktop'] ?? 'arrows', $valid_nav, true ) ? $_POST['nis_nav_desktop'] : 'arrows',
            'nav_mobile'  => in_array( $_POST['nis_nav_mobile']  ?? 'arrows', $valid_nav, true ) ? $_POST['nis_nav_mobile']  : 'arrows',
            'breakpoints' => $parse_bp('new_in_store'),
        ];

        update_option( 'options_product_lists', $lists, false );
        if ( class_exists( '\SaltHareket\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        wp_send_json_success( 'Cart liste ayarları kaydedildi.' );
    }

    private static function renderWooTabsSection( string $nonce, string $ajax ): void
    {
        $pt_cfg      = (array) get_option( 'options_product_tabs', [] );
        $pt_mode     = $pt_cfg['raw_mode']   ?? 'tab';
        $pt_cvt_bp   = $pt_cfg['convert_bp'] ?? 'none';
        $pt_tabs     = (array) ( $pt_cfg['tabs'] ?? [] );

        // Default tabs — hiç ayar yoksa
        if ( empty( $pt_tabs ) ) {
            $pt_tabs = [
                [ 'id'=>'description',  'title'=>'Description',    'type'=>'wc_description', 'enabled'=>true,  'default_open'=>true,  'icon'=>'', 'order'=>0, 'field_id'=>'', 'html_content'=>'' ],
                [ 'id'=>'additional',   'title'=>'Additional Info', 'type'=>'wc_additional',  'enabled'=>true,  'default_open'=>false, 'icon'=>'', 'order'=>1, 'field_id'=>'', 'html_content'=>'' ],
                [ 'id'=>'reviews',      'title'=>'Reviews',         'type'=>'sh_reviews',     'enabled'=>true,  'default_open'=>false, 'icon'=>'', 'order'=>2, 'field_id'=>'', 'html_content'=>'' ],
            ];
        }

        // Custom Product Fields listesi
        $cpf_fields = [];
        if ( function_exists('wc_get_product_meta_fields') ) {
            foreach ( wc_get_product_meta_fields() as $field_id => $field ) {
                $cpf_fields[ $field_id ] = $field['label'] ?? $field_id;
            }
        }

        $valid_bps = [ 'none'=>'Yok', 'xs'=>'XS', 'sm'=>'SM', 'md'=>'MD', 'lg'=>'LG', 'xl'=>'XL', 'xxl'=>'XXL' ];
        $tab_types = [
            'wc_description' => '📄 WC Açıklama',
            'wc_additional'  => '📦 WC Ek Bilgiler',
            'sh_reviews'     => '⭐ Reviews (Salt)',
            'custom_field'   => '🔧 Custom Field',
            'html'           => '🖊 HTML',
        ];
        ?>
        <div class="sh-card sh-mt-sm">
            <div class="sh-card-header--col">
                <span class="sh-card-title">📑 Product Tabs</span>
                <span class="sh-card-desc">Ürün sayfası tab/accordion ayarları.</span>
            </div>
            <div class="sh-card-body sh-pt-0">

                <!-- Mod -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px">MOD</div>
                <div style="display:flex;gap:10px;margin-bottom:12px">
                    <?php foreach(['tab'=>'📑 Tab','collapse'=>'🪗 Collapse/Accordion'] as $v=>$l): ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:10px 14px;border:2px solid <?php echo $pt_mode===$v?'#2271b1':'#e5e7eb'; ?>;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;background:<?php echo $pt_mode===$v?'#eff6ff':'#fff'; ?>">
                        <input type="radio" name="sh_pt_mode" value="<?php echo $v; ?>" <?php checked($pt_mode,$v); ?> style="margin:0"> <?php echo $l; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Tab modu — convert_bp -->
                <div id="sh-pt-convert-wrap" style="<?php echo $pt_mode!=='tab'?'display:none':''; ?>;margin-bottom:12px">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Convert to Collapse on:</label>
                    <select id="sh-pt-convert-bp" class="sh-input" style="max-width:180px">
                        <?php foreach($valid_bps as $v=>$l): ?>
                        <option value="<?php echo $v; ?>" <?php selected($pt_cvt_bp,$v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sh-text-sm sh-text-muted sh-m-0 sh-mt-xs">Seçilen breakpoint'in altında accordion'a dönüşür (bs-tab-collapse).</p>
                </div>

                <!-- Tab Listesi -->
                <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin-bottom:12px;margin-top:8px">TAB LİSTESİ</div>

                <div id="sh-pt-tabs-list" style="margin-bottom:12px">
                    <?php foreach($pt_tabs as $i=>$tab):
                        $is_first = ($i === 0);
                    ?>
                    <div class="sh-pt-tab-item" data-index="<?php echo $i; ?>"
                         draggable="true"
                         style="border:1px solid #e5e7eb;border-radius:6px;margin-bottom:8px;background:#fff;cursor:default">
                        <!-- Header -->
                        <div class="sh-pt-tab-header" style="display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;user-select:none">
                            <span class="sh-pt-drag-handle" style="cursor:grab;color:#aaa;font-size:16px;padding:0 2px" title="Sürükle">⣿</span>
                            <label class="sh-toggle" onclick="event.stopPropagation()">
                                <input type="checkbox" class="sh-pt-enabled" <?php checked(!empty($tab['enabled'])); ?>>
                                <span class="sh-toggle-slider"></span>
                            </label>
                            <span class="sh-pt-tab-title-preview" style="flex:1;font-weight:500;font-size:13px"><?php echo esc_html($tab['title'] ?: $tab['type']); ?></span>
                            <span style="font-size:11px;color:#999;background:#f3f4f6;padding:2px 6px;border-radius:3px"><?php echo $tab_types[$tab['type']] ?? $tab['type']; ?></span>
                            <button type="button" onclick="event.stopPropagation();this.closest('.sh-pt-tab-item').remove()" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:14px;padding:0 4px">🗑</button>
                        </div>
                        <!-- Body -->
                        <div class="sh-pt-tab-body" style="padding:12px;border-top:1px solid #f3f4f6;<?php echo $is_first ? '' : 'display:none'; ?>">
                            <input type="hidden" class="sh-pt-id" value="<?php echo esc_attr($tab['id']); ?>">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                                <div>
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Başlık</label>
                                    <input type="text" class="sh-pt-title sh-input sh-w-full" value="<?php echo esc_attr($tab['title']); ?>" placeholder="Tab başlığı">
                                </div>
                                <div>
                                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">İçerik Tipi</label>
                                    <select class="sh-pt-type sh-input sh-w-full">
                                        <?php foreach($tab_types as $v=>$l): ?>
                                        <option value="<?php echo $v; ?>" <?php selected($tab['type']??'',$v); ?>><?php echo $l; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Custom Field seçimi -->
                            <div class="sh-pt-field-wrap" style="<?php echo ($tab['type']??'')==='custom_field'?'':'display:none'; ?>;margin-bottom:10px">
                                <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Custom Field ID</label>
                                <select class="sh-pt-field-id sh-input sh-w-full">
                                    <option value="">-- Seçin --</option>
                                    <?php foreach($cpf_fields as $fid=>$flabel): ?>
                                    <option value="<?php echo esc_attr($fid); ?>" <?php selected($tab['field_id']??'',$fid); ?>><?php echo esc_html($flabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- HTML içerik -->
                            <div class="sh-pt-html-wrap" style="<?php echo ($tab['type']??'')==='html'?'':'display:none'; ?>;margin-bottom:10px">
                                <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">HTML İçerik <span style="font-size:11px;color:#9ca3af;font-weight:400">(tıklayarak editörü açabilirsiniz)</span></label>
                                <?php $mce_id = 'sh_pt_html_' . $i; ?>
                                <textarea id="<?php echo esc_attr($mce_id); ?>" name="sh_pt_html" class="sh-pt-html sh-input sh-w-full" rows="5" style="font-family:monospace;font-size:12px;width:100%;cursor:text"><?php echo esc_textarea($tab['html_content']??''); ?></textarea>
                            </div>
                            <div class="sh-flex sh-items-center sh-gap-sm">
                                <label class="sh-toggle">
                                    <input type="checkbox" class="sh-pt-default-open" <?php checked(!empty($tab['default_open'])); ?>>
                                    <span class="sh-toggle-slider"></span>
                                </label>
                                <span class="sh-text-sm sh-text-label">Varsayılan açık</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="sh-pt-add-tab" class="sh-btn sh-btn-ghost sh-mb-md">+ Tab Ekle</button>

                <!-- 2 kolon: sol=ayarlar, sağ=twig kodu -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:8px">
                    <div>
                        <div class="sh-flex sh-items-center sh-gap-sm">
                            <button type="button" id="sh-gs-save-product-tabs" class="sh-btn sh-btn-primary">Kaydet</button>
                        </div>
                    </div>
                    <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                            <span class="sh-text-sm sh-font-semibold sh-text-label">Twig Include Kodu</span>
                            <button type="button"
                                    onclick="navigator.clipboard.writeText(`{% include 'woo/partials/product-tabs.twig' %}`);this.textContent='✅ Kopyalandı';setTimeout(()=>this.textContent='📋 Kopyala',2000)"
                                    style="font-size:11px;padding:3px 8px;border:1px solid #ddd;border-radius:4px;background:#fff;cursor:pointer">📋 Kopyala</button>
                        </div>
                        <textarea readonly onclick="this.select()" style="width:100%;height:40px;font-family:monospace;font-size:11px;padding:6px;border:1px solid #ddd;border-radius:4px;background:#fff;resize:none">{% include 'woo/partials/product-tabs.twig' %}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var NONCE_PT = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_PT  = <?php echo wp_json_encode( $ajax ); ?>;

            // Mod toggle — convert_bp göster/gizle
            document.querySelectorAll('input[name="sh_pt_mode"]').forEach(function(r){
                r.addEventListener('change', function(){
                    var wrap = document.getElementById('sh-pt-convert-wrap');
                    if(wrap) wrap.style.display = this.value === 'tab' ? '' : 'none';
                    document.querySelectorAll('input[name="sh_pt_mode"]').forEach(function(x){
                        var lbl = x.closest('label');
                        if(lbl){ lbl.style.borderColor=x.checked?'#2271b1':'#e5e7eb'; lbl.style.background=x.checked?'#eff6ff':'#fff'; }
                    });
                });
            });

            // ── İçerik tipi değişince ilgili alanı göster ───────────────
            document.getElementById('sh-pt-tabs-list').addEventListener('change', function(e){
                // İçerik tipi
                if(e.target.classList.contains('sh-pt-type')) {
                    var item = e.target.closest('.sh-pt-tab-item');
                    item.querySelector('.sh-pt-field-wrap').style.display = e.target.value==='custom_field' ? '' : 'none';
                    item.querySelector('.sh-pt-html-wrap').style.display  = e.target.value==='html' ? '' : 'none';
                }
                // default_open max 1
                if(e.target.classList.contains('sh-pt-default-open') && e.target.checked) {
                    document.querySelectorAll('#sh-pt-tabs-list .sh-pt-default-open').forEach(function(cb) {
                        if (cb !== e.target) cb.checked = false;
                    });
                }
                // enabled toggle → body aç/kapat
                if(e.target.classList.contains('sh-pt-enabled')) {
                    var item = e.target.closest('.sh-pt-tab-item');
                    var body = item.querySelector('.sh-pt-tab-body');
                    if (body) {
                        body.style.display = e.target.checked ? '' : 'none';
                        if (e.target.checked) shPtMaybeInitMce(item);
                    }
                }
            });

            // Başlık değişince preview güncelle
            document.getElementById('sh-pt-tabs-list').addEventListener('input', function(e){
                if(!e.target.classList.contains('sh-pt-title')) return;
                var item = e.target.closest('.sh-pt-tab-item');
                var preview = item.querySelector('.sh-pt-tab-title-preview');
                if(preview) preview.textContent = e.target.value || '(başlıksız)';
            });

            // Tab ekle
            document.getElementById('sh-pt-add-tab').addEventListener('click', function(){
                var list = document.getElementById('sh-pt-tabs-list');
                var idx  = list.children.length;
                var types = <?php echo wp_json_encode( $tab_types ); ?>;
                var tHtml = '<div class="sh-pt-tab-item" data-index="'+idx+'" draggable="true" style="border:1px solid #e5e7eb;border-radius:6px;margin-bottom:8px;background:#fff">';
                tHtml += '<div class="sh-pt-tab-header" style="display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;user-select:none">';
                tHtml += '<span class="sh-pt-drag-handle" style="cursor:grab;color:#aaa;font-size:16px;padding:0 2px" title="Sürükle">⣿</span>';
                tHtml += '<label class="sh-toggle" onclick="event.stopPropagation()"><input type="checkbox" class="sh-pt-enabled" checked><span class="sh-toggle-slider"></span></label>';
                tHtml += '<span class="sh-pt-tab-title-preview" style="flex:1;font-weight:500;font-size:13px">(başlıksız)</span>';
                tHtml += '<span style="font-size:11px;color:#999;background:#f3f4f6;padding:2px 6px;border-radius:3px">📄 WC Açıklama</span>';
                tHtml += '<button type="button" onclick="event.stopPropagation();this.closest(\'.sh-pt-tab-item\').remove()" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:14px;padding:0 4px">🗑</button>';
                tHtml += '</div>';
                tHtml += '<div class="sh-pt-tab-body" style="padding:12px;border-top:1px solid #f3f4f6;display:none">';
                tHtml += '<input type="hidden" class="sh-pt-id" value="tab_'+idx+'">';
                tHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">';
                tHtml += '<div><label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Başlık</label><input type="text" class="sh-pt-title sh-input sh-w-full" placeholder="Tab başlığı"></div>';
                tHtml += '<div><label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">İçerik Tipi</label><select class="sh-pt-type sh-input sh-w-full">';
                Object.keys(types).forEach(function(v){ tHtml += '<option value="'+v+'">'+types[v]+'</option>'; });
                tHtml += '</select></div></div>';
                tHtml += '<div class="sh-pt-field-wrap" style="display:none;margin-bottom:10px"><label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Custom Field ID</label><input type="text" class="sh-pt-field-id sh-input sh-w-full" placeholder="field_id"></div>';
                var newMceId = 'sh_pt_html_new_' + idx;
                tHtml += '<div class="sh-pt-html-wrap" style="display:none;margin-bottom:10px">';
                tHtml += '<label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">HTML İçerik <span style="font-size:11px;color:#9ca3af;font-weight:400">(tıklayarak editörü açabilirsiniz)</span></label>';
                tHtml += '<textarea id="'+newMceId+'" name="sh_pt_html" class="sh-pt-html sh-input sh-w-full" rows="5" style="font-family:monospace;font-size:12px;width:100%;cursor:text"></textarea></div>';
                tHtml += '<div class="sh-flex sh-items-center sh-gap-sm"><label class="sh-toggle"><input type="checkbox" class="sh-pt-default-open"><span class="sh-toggle-slider"></span></label><span class="sh-text-sm sh-text-label">Varsayılan açık</span></div>';
                tHtml += '</div></div>';
                list.insertAdjacentHTML('beforeend', tHtml);

                // jQuery sortable refresh
                if(typeof $ !== 'undefined' && $('#sh-pt-tabs-list').sortable) {
                    try { $('#sh-pt-tabs-list').sortable('refresh'); } catch(e){}
                }
            });

            // ── MCE Lazy Init — body açılınca veya textarea tıklanınca ──
            function shPtMaybeInitMce(item) {
                var type = item.querySelector('.sh-pt-type');
                if (!type || type.value !== 'html') return;
                var ta = item.querySelector('.sh-pt-html');
                if (!ta || !ta.id || ta.dataset.mceInit === '1') return;
                if (typeof wp === 'undefined' || !wp.editor) return;
                // display:none iken init yapma — kısa gecikmeyle bekle
                setTimeout(function() {
                    if (!ta.offsetParent) return; // hâlâ gizli
                    wp.editor.initialize(ta.id, {
                        tinymce: {
                            wpautop: true,
                            toolbar1: 'formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link unlink fullscreen',
                            toolbar2: '',
                            plugins: 'charmap hr lists paste tabfocus textcolor wordpress wpautoresize wpeditimage wpgallery wplink wptextpattern',
                            height: 260
                        },
                        quicktags: true,
                        mediaButtons: false
                    });
                    ta.dataset.mceInit = '1';
                }, 50);
            }

            // ── MCE Lazy Init — textarea'ya tıklayınca da çalış (fallback) ──
            document.addEventListener('click', function(e) {
                var ta = e.target;
                if (!ta || ta.tagName !== 'TEXTAREA' || !ta.classList.contains('sh-pt-html')) return;
                if (!ta.id || ta.dataset.mceInit === '1') return;
                var item = ta.closest('.sh-pt-tab-item');
                if (item) shPtMaybeInitMce(item);
            });

            // ── Product Tabs default_open: max 1 ── (change handler'a taşındı)

            // ── Header tıklayınca body aç/kapat ─────────────────────────
            document.getElementById('sh-pt-tabs-list').addEventListener('click', function(e) {
                if (e.target.closest('.sh-pt-drag-handle')) return;
                if (e.target.closest('.sh-toggle'))        return;
                if (e.target.closest('button'))            return;
                if (e.target.closest('input,select,textarea')) return;

                var header = e.target.closest('.sh-pt-tab-header');
                if (!header) return;

                var item = header.closest('.sh-pt-tab-item');
                var body = item.querySelector('.sh-pt-tab-body');
                if (!body) return;

                var isOpen = body.style.display !== 'none';
                // Accordion: diğerlerini kapat
                document.querySelectorAll('#sh-pt-tabs-list .sh-pt-tab-body').forEach(function(b) {
                    b.style.display = 'none';
                });
                body.style.display = isOpen ? 'none' : '';
                if (!isOpen) shPtMaybeInitMce(item);
            });

            // ── HTML5 Drag & Drop ────────────────────────────────────────
            (function() {
                var list = document.getElementById('sh-pt-tabs-list');
                var dragSrc = null;

                // mousedown handle'a basılınca item'ı draggable yap,
                // mouseup'ta kapat — bu sayede handle dışından drag olmaz
                list.addEventListener('mousedown', function(e) {
                    var handle = e.target.closest('.sh-pt-drag-handle');
                    var item   = handle && handle.closest('.sh-pt-tab-item');
                    // Tüm item'ları önce false yap
                    list.querySelectorAll('.sh-pt-tab-item').forEach(function(i) {
                        i.setAttribute('draggable', 'false');
                    });
                    if (item) item.setAttribute('draggable', 'true');
                });
                document.addEventListener('mouseup', function() {
                    list.querySelectorAll('.sh-pt-tab-item').forEach(function(i) {
                        i.setAttribute('draggable', 'false');
                    });
                });

                list.addEventListener('dragstart', function(e) {
                    var item = e.target.closest('.sh-pt-tab-item');
                    if (!item || item.getAttribute('draggable') !== 'true') {
                        e.preventDefault(); return;
                    }
                    dragSrc = item;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', '');
                    setTimeout(function() { if(dragSrc) dragSrc.style.opacity = '0.4'; }, 0);
                });

                list.addEventListener('dragend', function() {
                    list.querySelectorAll('.sh-pt-tab-item').forEach(function(i) {
                        i.style.opacity = '';
                        i.classList.remove('sh-pt-drag-over');
                        i.setAttribute('draggable', 'false');
                    });
                    dragSrc = null;
                });

                list.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    if (!dragSrc) return;
                    var target = e.target.closest('.sh-pt-tab-item');
                    if (!target || target === dragSrc) return;
                    list.querySelectorAll('.sh-pt-tab-item').forEach(function(i) { i.classList.remove('sh-pt-drag-over'); });
                    target.classList.add('sh-pt-drag-over');
                });

                list.addEventListener('dragleave', function(e) {
                    if (!e.relatedTarget || !list.contains(e.relatedTarget)) {
                        list.querySelectorAll('.sh-pt-tab-item').forEach(function(i) { i.classList.remove('sh-pt-drag-over'); });
                    }
                });

                list.addEventListener('drop', function(e) {
                    e.preventDefault();
                    if (!dragSrc) return;
                    var target = e.target.closest('.sh-pt-tab-item');
                    if (!target || target === dragSrc) return;
                    target.classList.remove('sh-pt-drag-over');
                    var items  = Array.from(list.querySelectorAll('.sh-pt-tab-item'));
                    var srcIdx = items.indexOf(dragSrc);
                    var dstIdx = items.indexOf(target);
                    if (srcIdx < dstIdx) list.insertBefore(dragSrc, target.nextSibling);
                    else                 list.insertBefore(dragSrc, target);
                });
            })();
            // drag-over görsel stili
            var dragStyle = document.createElement('style');
            dragStyle.textContent = '.sh-pt-drag-over { outline: 2px dashed #2271b1 !important; outline-offset: 2px; }';
            document.head.appendChild(dragStyle);

            // Kaydet
            var _shSaveEl=document.getElementById('sh-gs-save-product-tabs'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this; btn.disabled=true;
                btn.innerHTML='<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Kaydediliyor...';

                // Önce kapalı body'leri geçici aç — böylece input/textarea değerleri okunabilir
                var closedBodies = [];
                document.querySelectorAll('#sh-pt-tabs-list .sh-pt-tab-item').forEach(function(item) {
                    var body = item.querySelector('.sh-pt-tab-body');
                    if (body && body.style.display === 'none') {
                        body.style.display = '';
                        closedBodies.push(body);
                    }
                });

                // Tüm aktif TinyMCE instance'larını textarea'ya sync et
                if (typeof tinymce !== 'undefined') {
                    tinymce.editors.forEach(function(ed) {
                        try { ed.save(); } catch(e) {}
                    });
                }

                // Tab listesini JSON'a dönüştür
                var tabs = [];
                document.querySelectorAll('#sh-pt-tabs-list .sh-pt-tab-item').forEach(function(item, i){
                    var ta = item.querySelector('.sh-pt-html');
                    var htmlContent = '';
                    if (ta) {
                        if (ta.id && typeof tinymce !== 'undefined' && tinymce.get(ta.id)) {
                            htmlContent = tinymce.get(ta.id).getContent();
                        } else {
                            htmlContent = ta.value || '';
                        }
                    }
                    var titleEl    = item.querySelector('.sh-pt-title');
                    var titleVal   = (titleEl && titleEl.value && titleEl.value.trim()) ? titleEl.value.trim()
                                     : (item.querySelector('.sh-pt-tab-title-preview')?.textContent?.trim() || '');
                    tabs.push({
                        id:           item.querySelector('.sh-pt-id')?.value || 'tab_'+i,
                        title:        titleVal,
                        type:         item.querySelector('.sh-pt-type')?.value || 'wc_description',
                        field_id:     item.querySelector('.sh-pt-field-id')?.value || '',
                        html_content: htmlContent,
                        enabled:      item.querySelector('.sh-pt-enabled')?.checked ? 1 : 0,
                        default_open: item.querySelector('.sh-pt-default-open')?.checked ? 1 : 0,
                        icon:         '',
                        order:        i
                    });
                });

                // Geçici açılan body'leri tekrar kapat
                closedBodies.forEach(function(b) { b.style.display = 'none'; });

                var fd = new FormData();
                fd.append('action','sh_gs_save_product_tabs'); fd.append('nonce',NONCE_PT);
                fd.append('tabs_mode',       document.querySelector('input[name="sh_pt_mode"]:checked')?.value || 'tab');
                fd.append('tabs_convert_bp', document.getElementById('sh-pt-convert-bp')?.value || 'none');
                fd.append('tabs_list',       JSON.stringify(tabs));

                fetch(AJAX_PT,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    btn.disabled=false; btn.textContent='Kaydet';
                    var toast=document.createElement('div');
                    toast.style.cssText='position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:500;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:opacity .3s;'+(res.success?'background:#16a34a':'background:#dc2626');
                    toast.textContent=res.success?'✔ Product Tabs ayarları kaydedildi':'✖ Hata';
                    document.body.appendChild(toast);
                    setTimeout(function(){ toast.style.opacity='0'; setTimeout(function(){ toast.remove(); },300); },3000);
                }).catch(function(){ btn.disabled=false; btn.textContent='Kaydet'; });
            });
        })();
        </script>
        <?php
    }


    private static function renderTranslationsTab( string $nonce, string $ajax ): void
    {
        $tr_untrans_tax = (array) get_option( 'options_untranslatable_taxonomies', [] );
        $tr_untrans_pt  = (array) get_option( 'options_untranslatable_post_types',  [] );
        $tr_sync_pages  = (array) get_option( 'options_synchronized_pages',         [] );
        $tr_sync_tax    = (array) get_option( 'options_sync_taxonomies_to_language', [] );

        $untrans_tax_options = [];
        $untrans_pt_options  = [];
        $sync_tax_options    = [];
        if ( function_exists( 'pll_languages_list' ) ) {
            foreach ( get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' ) as $slug => $obj ) {
                $untrans_tax_options[ $slug ] = $obj->label . ' (' . $slug . ')';
            }
            $pt_excluded = [ 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' ];
            foreach ( get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' ) as $slug => $obj ) {
                if ( ! in_array( $slug, $pt_excluded, true ) ) $untrans_pt_options[ $slug ] = $obj->label . ' (' . $slug . ')';
            }
            foreach ( get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'names' ) as $tax ) {
                if ( function_exists('pll_is_translated_taxonomy') && pll_is_translated_taxonomy( $tax ) ) {
                    $obj = get_taxonomy( $tax );
                    $sync_tax_options[ $tax ] = ( $obj ? $obj->label : $tax ) . ' (' . $tax . ')';
                }
            }
        }
        $default_lang = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        $pages_query  = [ 'post_type' => 'page', 'posts_per_page' => 300, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
        if ( $default_lang ) $pages_query['lang'] = $default_lang;
        $all_pages = get_posts( $pages_query );
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🌐 Translations</span>
                <span class="sh-card-desc">Polylang çeviri ayarları — çevrilmez içerikler, senkronizasyon.</span>
            </div>
            <div class="sh-card-body">

                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Untranslatable Taxonomies</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Seçilen taxonomy'ler çevrilmez — tüm dillerde aynı liste.</p>
                    <select id="sh-tr-untrans-tax" name="untranslatable_taxonomies[]" multiple data-sh-select data-placeholder="Taxonomy seç..." style="width:100%">
                        <?php foreach ( $untrans_tax_options as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected( in_array($val,$tr_untrans_tax,true) ); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Untranslatable Post Types</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Seçilen post type'lar çevrilmez — dil değişse de aynı içerik.</p>
                    <select id="sh-tr-untrans-pt" name="untranslatable_post_types[]" multiple data-sh-select data-placeholder="Post type seç..." style="width:100%">
                        <?php foreach ( $untrans_pt_options as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected( in_array($val,$tr_untrans_pt,true) ); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Sync Pages Content</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Seçilen sayfalar bir dilde düzenlenince diğer dillere de kopyalanır.</p>
                    <select id="sh-tr-sync-pages" name="synchronized_pages[]" multiple data-sh-select data-placeholder="Sayfa seç..." style="width:100%">
                        <?php foreach ( $all_pages as $p ) : ?>
                        <option value="<?php echo (int)$p->ID; ?>" <?php selected( in_array($p->ID,$tr_sync_pages,false) ); ?>><?php echo esc_html($p->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Sync Taxonomies to Current Language</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-xs">Bu taxonomy'lerin term'leri sorgu sırasında otomatik mevcut dile çevrilir.</p>
                    <select id="sh-tr-sync-tax" name="sync_taxonomies_to_language[]" multiple data-sh-select data-placeholder="Taxonomy seç..." style="width:100%">
                        <?php foreach ( (array) $sync_tax_options as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected( in_array($val,$tr_sync_tax,true) ); ?>><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm">
                    <button type="button" id="sh-gs-save-translations" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-translations-msg" class="sh-text-sm" style="display:none;color:#15803d">✔ Kaydedildi</span>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
            function getMultiple(id){ return Array.from(document.getElementById(id).selectedOptions).map(function(o){return o.value;}); }
            var _shSaveEl=document.getElementById('sh-gs-save-translations'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var fd = new FormData(); fd.append('action','sh_gs_save_translations'); fd.append('nonce',NONCE);
                getMultiple('sh-tr-untrans-tax').forEach(function(v){ fd.append('untranslatable_taxonomies[]',v); });
                getMultiple('sh-tr-untrans-pt').forEach(function(v){ fd.append('untranslatable_post_types[]',v); });
                getMultiple('sh-tr-sync-pages').forEach(function(v){ fd.append('synchronized_pages[]',v); });
                getMultiple('sh-tr-sync-tax').forEach(function(v){ fd.append('sync_taxonomies_to_language[]',v); });
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    var m = document.getElementById('sh-gs-translations-msg');
                    m.textContent = res.success ? 'ÃÂ¢ÃâÃ¢â¬Å Kaydedildi' : 'ÃÂ¢ÃÂÃâ Hata';
                    m.style.display = 'inline';
                    setTimeout(function(){ m.style.display='none'; }, 2800);
                });
            });

        })();
        </script>

        <?php if ( function_exists( 'isLocalhost' ) && isLocalhost() ) : ?>
        <div class="sh-card sh-mt-md">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🔤 Extract Translations</span>
                <span class="sh-card-desc">Twig ve PHP dosyalarından <code>translate()</code> stringlerini tarar, translations.json dosyasını günceller.</span>
            </div>
            <div class="sh-card-body">
                <p class="sh-text-sm sh-text-muted sh-mb-sm">Sadece local development ortamında çalışır.</p>
                <div class="sh-flex sh-items-center sh-gap-sm">
                    <button type="button" id="sh-gs-extract-translations" class="sh-btn sh-btn-secondary">&#x1F504; Çeviri Tarama</button>
                    <span id="sh-gs-extract-msg" class="sh-text-sm" style="display:none"></span>
                </div>
                <div id="sh-gs-extract-log" style="display:none;margin-top:12px;background:#f8f8f8;border:1px solid #e5e5e5;border-radius:4px;padding:10px;max-height:200px;overflow-y:auto;font-size:12px;font-family:monospace;white-space:pre-wrap;"></div>
            </div>
        </div>
        <script>
        (function(){
            var NONCE_EX = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX_EX  = <?php echo wp_json_encode( $ajax ); ?>;
            document.getElementById('sh-gs-extract-translations').addEventListener('click', function(){
                var btn = this; var msg = document.getElementById("sh-gs-extract-msg"); var log = document.getElementById("sh-gs-extract-log");
                btn.disabled = true; btn.textContent = "Taranıyor...";
                msg.style.display = "none"; log.style.display = "none";
                var fd = new FormData();
                fd.append("action", "sh_gs_extract_translations");
                fd.append("nonce", NONCE_EX);
                fetch(AJAX_EX, {method:"POST", body:fd}).then(function(r){ return r.json(); }).then(function(res){
                    btn.disabled = false; btn.textContent = "Çeviri Tarama";
                    log.innerHTML = ""; log.style.cssText = "background:transparent;border:none;padding:0;overflow:visible;margin-top:0;display:block";
                    if (res.success) {
                        var total      = res.data.total || 0;
                        var newStrings = res.data.new_strings || [];
                        msg.style.display = "none";
                        var header = document.createElement("div");
                        header.style.cssText = "padding:8px 12px;border-radius:6px;margin-top:8px;font-size:13px;font-weight:600;";
                        if (newStrings.length) {
                            header.style.cssText += "background:#fffbeb;border:1px solid #fde68a;color:#b45309;cursor:pointer;user-select:none;display:flex;align-items:center;justify-content:space-between";
                            header.innerHTML = "<span>" + newStrings.length + " new strings found <span style='font-weight:400;color:#92400e'>(" + total + " total)</span></span><span>\u25bc</span>";
                            var body = document.createElement("div");
                            body.style.cssText = "display:none;border:1px solid #fde68a;border-top:none;border-radius:0 0 6px 6px;background:#fff";
                            newStrings.forEach(function(s, idx){
                                var row = document.createElement("div");
                                row.style.cssText = "padding:4px 12px;font-size:12px;font-family:monospace;border-bottom:1px solid #f0f0f0;color:#b45309";
                                row.textContent = (idx+1) + ". " + s;
                                body.appendChild(row);
                            });
                            log.appendChild(header);
                            log.appendChild(body);
                            header.addEventListener("click", function(){
                                var open = body.style.display !== "none";
                                body.style.display = open ? "none" : "block";
                                header.querySelector("span:last-child").textContent = open ? "\u25bc" : "\u25b2";
                            });
                        } else {
                            header.style.cssText += "background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d";
                            header.textContent = "\u2714 No new strings found (" + total + " already added)";
                            log.appendChild(header);
                        }
                    } else {
                        msg.textContent = "\u2716 " + (typeof res.data === "string" ? res.data : "Hata");
                        msg.style.color = "#dc2626";
                        msg.style.display = "inline";
                    }
                }).catch(function(){ btn.disabled = false; btn.textContent = "Çeviri Tarama"; msg.textContent = "\u2716 Ba\u011flant\u0131 hatas\u0131"; msg.style.color = "#dc2626"; msg.style.display = "inline"; });
            });
        })();
        </script>
        <?php endif; ?>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Render: Breadcrumb Tab
    |--------------------------------------------------------------------------
    */
    private static function renderBreadcrumbTab( string $nonce, string $ajax ): void
    {
        $bc_remove_home    = (bool) get_option( 'options_breadcrumb_remove_home',         false );
        $bc_remove_current = (bool) get_option( 'options_breadcrumb_remove_current',      false );
        $bc_from_menu      = (bool) get_option( 'options_breadcrumb_from_menu',           false );
        $bc_add_brand      = (bool) get_option( 'options_breadcrumb_add_product_brand',    false );
        $bc_add_tax        = (bool) get_option( 'options_breadcrumb_add_product_taxonomy', false );
        $bc_page_path      = (array) get_option( 'options_breadcrumb_add_page_path',      [] );

        $bc_pt_options = [];
        foreach ( get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' ) as $pt ) {
            $bc_pt_options[ $pt->name ] = $pt->label;
        }
        $bc_default_lang = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        $bc_pages_query  = [ 'post_type' => 'page', 'posts_per_page' => 200, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
        if ( $bc_default_lang ) $bc_pages_query['lang'] = $bc_default_lang;
        $bc_pages_list = get_posts( $bc_pages_query );
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🍞 Breadcrumb</span>
                <span class="sh-card-desc">Yoast SEO breadcrumb ayarları.</span>
            </div>
            <div class="sh-card-body">

                <?php
                $toggles = [
                    'sh-bc-remove-home'    => [ 'Remove "home" from breadcrumb',   'Breadcrumb\'ın başındaki "Home" linkini kaldırır.',                    $bc_remove_home    ],
                    'sh-bc-remove-current' => [ 'Remove current from breadcrumb',   'Mevcut sayfa adını breadcrumb\'dan kaldırır.',                        $bc_remove_current ],
                    'sh-bc-from-menu'      => [ 'Create breadcrumb from menu',      'Breadcrumb\'ı sayfa hiyerarşisinden değil menü yapısından oluşturur.',$bc_from_menu      ],
                ];
                if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) {
                    $toggles['sh-bc-add-brand'] = [ 'Add brand to breadcrumb',     'Ürün sayfalarında marka (product brand) ekler.',                      $bc_add_brand      ];
                    $toggles['sh-bc-add-tax']   = [ 'Add product taxonomy',         'Ürün taxonomy\'sini breadcrumb yoluna ekler.',                        $bc_add_tax        ];
                }
                foreach ( $toggles as $id => $item ) : ?>
                <div class="sh-flex sh-items-start sh-gap-sm sh-mb-md">
                    <label class="sh-toggle" style="margin-top:2px">
                        <input type="checkbox" id="<?php echo $id; ?>" <?php checked( $item[2] ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <label for="<?php echo $id; ?>" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer"><?php echo esc_html( $item[0] ); ?></label>
                        <p class="sh-text-sm sh-text-muted sh-m-0"><?php echo esc_html( $item[1] ); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Add Page Path repeater -->
                <div class="sh-mb-md">
                    <div class="sh-flex sh-items-center sh-justify-between sh-mb-sm">
                        <div>
                            <label class="sh-text-sm sh-font-semibold sh-text-label sh-block">Add Page Path</label>
                            <p class="sh-text-sm sh-text-muted sh-m-0">Post type bazlı breadcrumb'a sanal sayfa yolu ekler.</p>
                        </div>
                        <button type="button" id="sh-bc-add-row" class="sh-btn sh-btn-ghost sh-text-sm">+ Ekle</button>
                    </div>
                    <div id="sh-bc-rows">
                        <?php foreach ( $bc_page_path as $bc_row ) :
                            $bc_pt_val   = $bc_row['post_type'] ?? '';
                            $bc_page_val = isset( $bc_row['page'] ) ? (int) $bc_row['page'] : 0; ?>
                        <div class="sh-bc-row sh-card-inset sh-mb-xs" style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end">
                            <div>
                                <label class="sh-text-xs sh-font-semibold sh-text-muted sh-block sh-mb-xs" style="text-transform:uppercase">Post Type</label>
                                <select class="sh-bc-pt sh-input sh-w-full">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pt_options as $v => $l ) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($bc_pt_val,$v); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="sh-text-xs sh-font-semibold sh-text-muted sh-block sh-mb-xs" style="text-transform:uppercase">Page</label>
                                <select class="sh-bc-page sh-input sh-w-full">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pages_list as $p ) : ?>
                                    <option value="<?php echo (int)$p->ID; ?>" <?php selected($bc_page_val,$p->ID); ?>><?php echo esc_html($p->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="sh-bc-remove sh-btn sh-btn-danger" style="padding:4px 8px">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Template -->
                    <div id="sh-bc-row-tpl" style="display:none">
                        <div class="sh-bc-row sh-card-inset sh-mb-xs" style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end">
                            <div>
                                <label class="sh-text-xs sh-font-semibold sh-text-muted sh-block sh-mb-xs" style="text-transform:uppercase">Post Type</label>
                                <select class="sh-bc-pt sh-input sh-w-full">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pt_options as $v => $l ) : ?>
                                    <option value="<?php echo esc_attr($v); ?>"><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="sh-text-xs sh-font-semibold sh-text-muted sh-block sh-mb-xs" style="text-transform:uppercase">Page</label>
                                <select class="sh-bc-page sh-input sh-w-full">
                                    <option value="">— Seçin —</option>
                                    <?php foreach ( $bc_pages_list as $p ) : ?>
                                    <option value="<?php echo (int)$p->ID; ?>"><?php echo esc_html($p->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="sh-bc-remove sh-btn sh-btn-danger" style="padding:4px 8px">✕</button>
                        </div>
                    </div>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm">
                    <button type="button" id="sh-gs-save-bc" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-bc-msg" class="sh-text-sm" style="display:none;color:#15803d">✔ Kaydedildi</span>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
            document.getElementById('sh-bc-add-row').addEventListener('click', function(){
                var tpl = document.getElementById('sh-bc-row-tpl').querySelector('.sh-bc-row').cloneNode(true);
                document.getElementById('sh-bc-rows').appendChild(tpl);
                tpl.querySelector('.sh-bc-remove').addEventListener('click', function(){ tpl.remove(); });
            });
            document.getElementById('sh-bc-rows').addEventListener('click', function(e){
                if (e.target.classList.contains('sh-bc-remove')) e.target.closest('.sh-bc-row').remove();
            });
            var _shSaveEl=document.getElementById('sh-gs-save-bc'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var fd = new FormData(); fd.append('action','sh_gs_save_breadcrumb'); fd.append('nonce',NONCE);
                var ids = ['sh-bc-remove-home','sh-bc-remove-current','sh-bc-from-menu','sh-bc-add-brand','sh-bc-add-tax'];
                ids.forEach(function(id){ var el=document.getElementById(id); if(el&&el.checked) fd.append(el.id.replace('sh-bc-','breadcrumb_').replace(/-/g,'_'),'1'); });
                document.querySelectorAll('#sh-bc-rows .sh-bc-row').forEach(function(row,i){
                    var pt = row.querySelector('.sh-bc-pt').value;
                    var pg = row.querySelector('.sh-bc-page').value;
                    if(pt && pg){ fd.append('breadcrumb_add_page_path['+i+'][post_type]',pt); fd.append('breadcrumb_add_page_path['+i+'][page]',pg); }
                });
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    var m=document.getElementById('sh-gs-bc-msg');
                    m.textContent=res.success?'✔ Kaydedildi':'❌ Hata';
                    m.style.display='inline'; setTimeout(function(){m.style.display='none';},2800);
                });
            });
        })();
        </script>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX: Pagination
    |--------------------------------------------------------------------------
    */

    public static function ajaxSavePagination(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        // Post Pagination repeater
        $rows  = (array) ( $_POST['post_pagination'] ?? [] );
        $clean = [];
        foreach ( $rows as $row ) {
            $pt = sanitize_key( $row['post_type'] ?? '' );
            if ( ! $pt ) continue;
            $clean[] = [
                'post_type'       => $pt,
                'paged'           => filter_var( $row['paged'] ?? false, FILTER_VALIDATE_BOOLEAN ),
                'type'            => sanitize_key( $row['type'] ?? 'default' ),
                'catalog_rows'    => (int) ( $row['catalog_rows']    ?? 1 ),
                'catalog_columns' => (int) ( $row['catalog_columns'] ?? 1 ),
            ];
        }
        update_option( 'options_post_pagination', $clean, false );

        // Search Pagination group
        $search = [
            'paged'           => filter_var( $_POST['search_paged'] ?? false, FILTER_VALIDATE_BOOLEAN ),
            'type'            => sanitize_key( $_POST['search_type']            ?? 'default' ),
            'catalog_rows'    => (int) ( $_POST['search_catalog_rows']    ?? 1 ),
            'catalog_columns' => (int) ( $_POST['search_catalog_columns'] ?? 1 ),
        ];
        update_option( 'options_search_pagination', $search, false );

        // WooCommerce catalog settings (post_pagination'dan product row var ise)
        foreach ( $clean as $item ) {
            if ( $item['post_type'] === 'product' && $item['paged'] ) {
                update_option( 'woocommerce_catalog_rows',    $item['catalog_rows'],    false );
                update_option( 'woocommerce_catalog_columns', $item['catalog_columns'], false );
            }
        }

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();
        wp_send_json_success( 'Pagination ayarları kaydedildi.' );
    }

    /*
    |--------------------------------------------------------------------------
    | Render: Pagination Card
    |--------------------------------------------------------------------------
    */

    private static function renderPaginationCard( string $nonce, string $ajax ): void
    {
        $post_pagination   = (array) get_option( 'options_post_pagination',   [] );
        $search_pagination = (array) get_option( 'options_search_pagination', [] );

        $post_types = FieldChoices::postTypes();
        $type_opts  = [ 'default' => 'Default', 'button' => 'Load More Button', 'scroll' => 'Load on Scroll' ];
        $inp = 'height:28px;border-radius:4px;border:1px solid #ddd;padding:0 6px;font-size:12px';

        $sp_paged   = ! empty( $search_pagination['paged'] );
        $sp_type    = $search_pagination['type']            ?? 'default';
        $sp_rows    = $search_pagination['catalog_rows']    ?? 1;
        $sp_cols    = $search_pagination['catalog_columns'] ?? 1;
        ?>
        <div class="sh-card sh-mt-md">
            <div class="sh-card-header--col">
                <span class="sh-card-title">📖 Pagination</span>
                <span class="sh-card-desc">Post ve arama sonuçları için sayfalama ayarları.</span>
            </div>
            <div class="sh-card-body">

                <!-- -------------------------------------------------------------------------- Post Pagination Repeater -------------------------------------------------------------------------- -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-sm">Post Pagination</label>
                    <div id="sh-pag-post-rows">
                    <?php foreach ( $post_pagination as $i => $row ) :
                        $paged = ! empty( $row['paged'] );
                        $pt    = $row['post_type'] ?? '';
                        $type  = $row['type'] ?? 'default';
                        $crows = $row['catalog_rows'] ?? 1;
                        $ccols = $row['catalog_columns'] ?? 1;
                    ?>
                    <div class="sh-pag-row sh-card-inset sh-mb-xs sh-flex sh-items-end sh-gap-md">
                        <div>
                            <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Post Type</label>
                            <select class="sh-pag-pt" style="<?php echo $inp; ?>;width:160px">
                                <option value="">— Seç —</option>
                                <?php foreach ( $post_types as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($pt,$slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sh-pb-xs sh-inline-flex sh-items-center" style="gap:10px">
                            <label class="sh-toggle" style="flex-shrink:0"><input type="checkbox" name="paged" class="sh-gs-field sh-pag-switch" data-pag-toggle="1" <?php echo $paged ? 'checked' : ''; ?>><span class="sh-toggle-slider"></span></label>
                            <span class="sh-text-xs sh-text-label sh-whitespace-nowrap">Paged</span>
                        </div>
                        <div class="sh-pag-fields sh-flex sh-items-end sh-gap-md" style="<?php echo $paged ? '' : 'display:none!important'; ?>">
                            <div>
                                <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Type</label>
                                <select class="sh-pag-type" style="<?php echo $inp; ?>;min-width:150px">
                                    <?php foreach ( $type_opts as $v => $l ) : ?>
                                    <option value="<?php echo $v; ?>" <?php selected($type,$v); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Rows</label>
                                <input type="number" class="sh-pag-rows" value="<?php echo (int)$crows; ?>" min="1" style="<?php echo $inp; ?>;width:70px">
                            </div>
                            <div>
                                <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Columns</label>
                                <input type="number" class="sh-pag-cols" value="<?php echo (int)$ccols; ?>" min="1" style="<?php echo $inp; ?>;width:70px">
                            </div>
                        </div>
                        <button type="button" class="sh-pag-remove sh-btn sh-btn-danger sh-mb-xs" style="padding:3px 8px;font-size:12px;margin-left:auto">✕</button>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <button type="button" id="sh-pag-add-row" class="sh-btn sh-btn-ghost sh-text-sm sh-mt-xs">+ Satır Ekle</button>
                </div>

                <hr class="sh-divider">

                <!-- -------------------------------------------------------------------------- Search Pagination Group -------------------------------------------------------------------------- -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-sm">Search Pagination</label>
                    <div class="sh-flex sh-items-end sh-gap-md">
                        <div class="sh-pb-xs sh-inline-flex sh-items-center" style="gap:10px">
                            <label class="sh-toggle" style="flex-shrink:0"><input type="checkbox" id="sh-sp-switch" class="sh-gs-field" <?php echo $sp_paged ? 'checked' : ''; ?>><span class="sh-toggle-slider"></span></label>
                            <span class="sh-text-xs sh-text-label sh-whitespace-nowrap">Paged</span>
                        </div>
                        <div class="sh-sp-fields sh-flex sh-items-end sh-gap-md" style="<?php echo $sp_paged ? '' : 'display:none!important'; ?>">
                            <div>
                                <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Type</label>
                                <select id="sh-sp-type" style="<?php echo $inp; ?>;min-width:150px">
                                    <?php foreach ( $type_opts as $v => $l ) : ?>
                                    <option value="<?php echo $v; ?>" <?php selected($sp_type,$v); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Rows</label>
                                <input type="number" id="sh-sp-rows" value="<?php echo (int)$sp_rows; ?>" min="1" style="<?php echo $inp; ?>;width:70px">
                            </div>
                            <div>
                                <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Columns</label>
                                <input type="number" id="sh-sp-cols" value="<?php echo (int)$sp_cols; ?>" min="1" style="<?php echo $inp; ?>;width:70px">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm sh-mt-md">
                    <button type="button" id="sh-gs-save-pagination" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-pag-msg" class="sh-text-sm" style="display:none;color:#15803d">ÃÂ¢ÃâÃ¢â¬Å Kaydedildi</span>
                </div>
            </div>
        </div>

        <!-- Post Type row template (hidden) -->
        <div id="sh-pag-row-tpl" style="display:none">
            <div class="sh-pag-row sh-card-inset sh-mb-xs sh-flex sh-items-end sh-gap-md">
                <div>
                    <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Post Type</label>
                    <select class="sh-pag-pt" style="<?php echo $inp; ?>;width:160px">
                        <option value="">— Seç —</option>
                        <?php foreach ( $post_types as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sh-pb-xs sh-inline-flex sh-items-center" style="gap:10px">
                    <label class="sh-toggle" style="flex-shrink:0"><input type="checkbox" name="paged" class="sh-gs-field sh-pag-switch" data-pag-toggle="1"><span class="sh-toggle-slider"></span></label>
                    <span class="sh-text-xs sh-text-label sh-whitespace-nowrap">Paged</span>
                </div>
                <div class="sh-pag-fields sh-flex sh-items-end sh-gap-md" style="display:none!important">
                    <div>
                        <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Type</label>
                        <select class="sh-pag-type" style="<?php echo $inp; ?>;min-width:150px">
                            <?php foreach ( $type_opts as $v => $l ) : ?>
                            <option value="<?php echo $v; ?>"><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Rows</label>
                        <input type="number" class="sh-pag-rows" value="1" min="1" style="<?php echo $inp; ?>;width:70px">
                    </div>
                    <div>
                        <label class="sh-text-xs sh-text-muted sh-block sh-mb-xs">Columns</label>
                        <input type="number" class="sh-pag-cols" value="1" min="1" style="<?php echo $inp; ?>;width:70px">
                    </div>
                </div>
                <button type="button" class="sh-pag-remove sh-btn sh-btn-danger sh-mb-xs" style="padding:3px 8px;font-size:12px;margin-left:auto">✕</button>
            </div>
        </div>

        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;

            function bindPagRow(row) {
                // sw() ile input_class olarak sh-pag-switch verildi → input.sh-pag-switch
                var sw     = row.querySelector('input.sh-pag-switch');
                var fields = row.querySelector('.sh-pag-fields');
                if (sw && fields) {
                    fields.style.setProperty('display', sw.checked ? 'flex' : 'none', sw.checked ? '' : 'important');
                    sw.addEventListener('change', function(){
                        fields.style.setProperty('display', this.checked ? 'flex' : 'none', this.checked ? '' : 'important');
                    });
                }
                var rem = row.querySelector('.sh-pag-remove');
                if (rem) rem.addEventListener('click', function(){ row.remove(); });
            }

            document.querySelectorAll('.sh-pag-row').forEach(bindPagRow);

            document.getElementById('sh-pag-add-row').addEventListener('click', function(){
                var tpl = document.getElementById('sh-pag-row-tpl').querySelector('.sh-pag-row').cloneNode(true);
                document.getElementById('sh-pag-post-rows').appendChild(tpl);
                bindPagRow(tpl);
            });

            // Search Pagination switch — id ile
            var spSwitch = document.getElementById('sh-sp-switch');
            var spFields = document.querySelector('.sh-sp-fields');
            if (spSwitch && spFields) {
                spFields.style.setProperty('display', spSwitch.checked ? 'flex' : 'none', spSwitch.checked ? '' : 'important');
                spSwitch.addEventListener('change', function(){
                    spFields.style.setProperty('display', this.checked ? 'flex' : 'none', this.checked ? '' : 'important');
                });
            }

            var _shSaveEl=document.getElementById('sh-gs-save-pagination'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var fd = new FormData(); fd.append('action','sh_gs_save_pagination'); fd.append('nonce',NONCE);
                document.querySelectorAll('#sh-pag-post-rows .sh-pag-row').forEach(function(row,i){
                    var pt = row.querySelector('.sh-pag-pt').value; if(!pt) return;
                    var sw = row.querySelector('input.sh-pag-switch');
                    fd.append('post_pagination['+i+'][post_type]', pt);
                    fd.append('post_pagination['+i+'][paged]', (sw && sw.checked) ? '1' : '0');
                    fd.append('post_pagination['+i+'][type]', row.querySelector('.sh-pag-type').value);
                    fd.append('post_pagination['+i+'][catalog_rows]', row.querySelector('.sh-pag-rows').value);
                    fd.append('post_pagination['+i+'][catalog_columns]', row.querySelector('.sh-pag-cols').value);
                });
                var spSw = document.getElementById('sh-sp-switch');
                if (spSw) fd.append('search_paged', spSw.checked ? '1' : '0');
                fd.append('search_type', document.getElementById('sh-sp-type').value);
                fd.append('search_catalog_rows', document.getElementById('sh-sp-rows').value);
                fd.append('search_catalog_columns', document.getElementById('sh-sp-cols').value);
                fetch(AJAX,{method:'POST',body:fd}).then(r=>r.json()).then(function(res){
                    var m=document.getElementById('sh-gs-pag-msg');
                    m.textContent = res.success ? '✔ Kaydedildi' : '❌ Hata';
                    m.style.display='inline'; setTimeout(function(){m.style.display='none';},2800);
                });
            });
        })();
        </script>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX: Defaults
    |--------------------------------------------------------------------------
    */

    public static function ajaxSaveDefaults(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $fields = [
            'options_default_container'       => 'sanitize_key',
            'options_default_margin_top'      => 'sanitize_key',
            'options_default_margin_bottom'   => 'sanitize_key',
            'options_default_margin_left'     => 'sanitize_key',
            'options_default_margin_right'    => 'sanitize_key',
            'options_default_padding_top'     => 'sanitize_key',
            'options_default_padding_bottom'  => 'sanitize_key',
            'options_default_padding_left'    => 'sanitize_key',
            'options_default_padding_right'   => 'sanitize_key',
            'options_default_ratio'           => 'sanitize_text_field',
            'options_default_title_size'      => 'sanitize_key',
            'options_default_text_size'       => 'sanitize_key',
            'options_default_footer_template' => 'sanitize_text_field',
            'options_default_header_template' => 'sanitize_text_field',
            'options_default_icon_cart'          => 'sanitize_text_field',
            'options_default_icon_notifications' => 'sanitize_text_field',
            'options_default_icon_messages'      => 'sanitize_text_field',
            'options_default_icon_reviews'       => 'sanitize_text_field',
            'options_default_icon_empty'         => 'sanitize_text_field',
            'options_default_icon_search'        => 'sanitize_text_field',
            'options_default_sidebar_width'        => 'absint',
            'options_default_sidebar_gap'          => 'sanitize_text_field',
            'options_default_sidebar_toggler_icon' => 'sanitize_text_field',
            'options_default_sidebar_toggler_label'=> 'sanitize_text_field',
        ];

        foreach ( $fields as $key => $sanitizer ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_option( $key, $sanitizer( $_POST[ $key ] ), false );
            }
        }

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        // DEPRECATED: header_footer_opts transient'ları kaldırıldı
        // global $wpdb;
        // $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_header_footer_opts_%'" );
        // $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_header_footer_opts_%'" );

        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }

        if ( class_exists( '\\SaltHareket\\AssetManager\\AssetManager' ) ) {
            $am = \SaltHareket\AssetManager\AssetManager::getInstance();
            if ( method_exists( $am, 'purgeTwigCache' ) ) {
                $am->purgeTwigCache();
            }
        }

        wp_send_json_success( 'Defaults kaydedildi.' );
    }

    /*
    |--------------------------------------------------------------------------
    | Render: Defaults Tab
    |--------------------------------------------------------------------------
    */

    private static function renderDefaultsTab( string $nonce, string $ajax ): void
    {
        // Mevcut değerleri oku
        $container      = get_option( 'options_default_container',       '' );
        $margin_top     = get_option( 'options_default_margin_top',      '' );
        $margin_bottom  = get_option( 'options_default_margin_bottom',   '' );
        $margin_left    = get_option( 'options_default_margin_left',     '' );
        $margin_right   = get_option( 'options_default_margin_right',    '' );
        $padding_top    = get_option( 'options_default_padding_top',     '' );
        $padding_bottom = get_option( 'options_default_padding_bottom',  '' );
        $padding_left   = get_option( 'options_default_padding_left',    '' );
        $padding_right  = get_option( 'options_default_padding_right',   '' );
        $ratio          = get_option( 'options_default_ratio',           '' );
        $title_size     = get_option( 'options_default_title_size',      'default' );
        $text_size      = get_option( 'options_default_text_size',       'default' );
        $footer_tpl     = get_option( 'options_default_footer_template', 'static' );
        $header_tpl     = get_option( 'options_default_header_template', 'static' );

        /// Choices — ACF bağımlılığı yok, hepsi PHP
        $container_choices = [
            'no'  => 'No Container',
            ''    => 'XXXLarge',
            'xxl' => 'XXLarge',
            'xl'  => 'XLarge',
            'lg'  => 'Large',
            'md'  => 'Medium',
            'sm'  => 'Small',
            'xs'  => 'XSmall',
        ];

        $mp_choices = [
            ''     => 'None',
            'auto' => 'auto',
        ];
        foreach ( range( 0, 12 ) as $n ) $mp_choices[ (string)$n ] = (string)$n;

        $ratio_choices = [
            ''      => 'Default',
            '1x1'   => '1:1 Square',
            '3x2'   => '3:2 35mm Movie',
            '3x4'   => '3:4 Vertical',
            '4x3'   => '4:3 TV / Tablet',
            '5x4'   => '5:4 Large Format',
            '9x16'  => '9:16 Vertical Video',
            '16x9'  => '16:9 Widescreen TV, Monitor',
            '21x9'  => '21:9 Cinemascope',
            '32x9'  => '32:9 Super Ultrawide',
        ];

        $title_size_choices = [ 'title-fluid' => 'Fluid' ];
        $text_size_choices  = [ 'text-fluid'  => 'Fluid' ];
        foreach ( FieldChoices::breakpoints() as $k => $v ) {
            $title_size_choices[ 'title-' . $k ] = 'Title ' . $k;
            $text_size_choices[  'text-'  . $k ] = 'Text '  . $k;
        }

        $footer_tpl_posts = FieldChoices::templatePosts( 'footer' );
        $footer_choices   = array_merge(
            [ 'static' => 'Static' ],
            array_combine(
                array_map( 'strval', array_keys( $footer_tpl_posts ) ),
                array_values( $footer_tpl_posts )
            ) ?: []
        );

        $header_tpl_posts = FieldChoices::templatePosts( 'header' );
        $header_choices   = array_merge(
            [ 'static' => 'Static' ],
            array_combine(
                array_map( 'strval', array_keys( $header_tpl_posts ) ),
                array_values( $header_tpl_posts )
            ) ?: []
        );

        // Helper: select HTML
        $select = function ( string $id, string $name, array $choices, string $current, string $style = '' ) {
            $out = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"'
                . ( $style ? ' style="' . esc_attr($style) . '"' : '' ) . '>';
            foreach ( $choices as $val => $label ) {
                $out .= '<option value="' . esc_attr($val) . '"' . selected( $current, (string)$val, false ) . '>'
                    . esc_html( $label ) . '</option>';
            }
            $out .= '</select>';
            return $out;
        };

        $input_style = 'width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box';
        $sel_style   = 'width:100%;padding:6px 4px;border:none;font-size:12px;text-align:center;background:transparent;outline:none';
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🎬 Defaults</span>
                <span class="sh-card-desc">Block'lar ve içerikler için global varsayılan değerler.</span>
            </div>
            <div class="sh-card-body">
            <div style="max-width:600px;display:grid;grid-template-columns:1fr 1fr;gap:16px 12px;align-items:start">

                <!-- Container — sol kolon -->
                <div>
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Container</label>
                    <select id="sh-def-container" name="options_default_container" style="<?php echo $input_style; ?>">
                        <?php foreach ( $container_choices as $v => $l ) : ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($container, (string)$v); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Aspect Ratio — sağ kolon -->
                <div>
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Aspect Ratio</label>
                    <select id="sh-def-ratio" name="options_default_ratio" style="<?php echo $input_style; ?>">
                        <?php foreach ( $ratio_choices as $v => $l ) : ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($ratio, (string)$v); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Margin — iki kolon tam genişlik -->
                <div style="grid-column:1 / -1">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Margin</label>
                    <div style="display:flex;border:1px solid #d1d5db;border-radius:6px;overflow:hidden">
                        <?php foreach ( ['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left'] as $side => $lbl ) :
                            $val = get_option( 'options_default_margin_' . $side, '' );
                            $is_last = $side === 'left';
                        ?>
                        <div style="flex:1;<?php echo $is_last ? '' : 'border-right:1px solid #d1d5db;'; ?>">
                            <select id="sh-def-margin-<?php echo $side; ?>" style="<?php echo $sel_style; ?>">
                                <?php foreach ( $mp_choices as $v => $mp_l ) : ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($val, (string)$v); ?>><?php echo esc_html($mp_l); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="text-align:center;font-size:10px;color:#9ca3af;padding:3px 0;border-top:1px solid #f3f4f6;text-transform:uppercase;letter-spacing:.5px"><?php echo $lbl; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Padding — iki kolon tam genişlik -->
                <div style="grid-column:1 / -1">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Padding</label>
                    <div style="display:flex;border:1px solid #d1d5db;border-radius:6px;overflow:hidden">
                        <?php foreach ( ['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left'] as $side => $lbl ) :
                            $val = get_option( 'options_default_padding_' . $side, '' );
                            $is_last = $side === 'left';
                        ?>
                        <div style="flex:1;<?php echo $is_last ? '' : 'border-right:1px solid #d1d5db;'; ?>">
                            <select id="sh-def-padding-<?php echo $side; ?>" style="<?php echo $sel_style; ?>">
                                <?php foreach ( $mp_choices as $v => $mp_l ) : ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($val, (string)$v); ?>><?php echo esc_html($mp_l); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="text-align:center;font-size:10px;color:#9ca3af;padding:3px 0;border-top:1px solid #f3f4f6;text-transform:uppercase;letter-spacing:.5px"><?php echo $lbl; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Title Size — sol kolon -->
                <div>
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Title Size</label>
                    <select id="sh-def-title-size" style="<?php echo $input_style; ?>">
                        <?php foreach ( $title_size_choices as $v => $l ) : ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($title_size, (string)$v); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Text Size — sağ kolon -->
                <div>
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Text Size</label>
                    <select id="sh-def-text-size" style="<?php echo $input_style; ?>">
                        <?php foreach ( $text_size_choices as $v => $l ) : ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($text_size, (string)$v); ?>><?php echo esc_html($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Header Template — sol kolon -->
                <div>
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Header Template</label>
                    <select id="sh-def-header-tpl" style="<?php echo $input_style; ?>">
                        <option value="none" <?php selected($header_tpl, 'none'); selected($header_tpl, ''); ?>>— None —</option>
                        <option value="static" <?php selected($header_tpl, 'static'); ?>>Static</option>
                        <?php foreach ( $header_tpl_posts as $hid => $htitle ) : ?>
                        <option value="<?php echo (int)$hid; ?>" <?php selected((string)$header_tpl, (string)$hid); ?>><?php echo esc_html($htitle); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Footer Template — sol kolon -->
                <div>
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Footer Template</label>
                    <select id="sh-def-footer-tpl" style="<?php echo $input_style; ?>">
                        <option value="none" <?php selected($footer_tpl, 'none'); selected($footer_tpl, ''); ?>>— None —</option>
                        <option value="static" <?php selected($footer_tpl, 'static'); ?>>Static</option>
                        <?php foreach ( $footer_tpl_posts as $post_id => $post_title ) : ?>
                        <option value="<?php echo (int)$post_id; ?>" <?php selected((string)$footer_tpl, (string)$post_id); ?>><?php echo esc_html($post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Icons — iki kolon tam genişlik -->
                <div style="grid-column:1 / -1;margin-top:8px;padding-top:16px;border-top:1px solid #e5e7eb">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs" style="font-size:14px;">🎨 Icons</label>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">Site genelinde kullanılan varsayılan ikonlar. JS tarafında da erişilebilir.</p>
                    <?php
                    $icon_cart    = get_option( 'options_default_icon_cart',          'light:cart-shopping' );
                    $icon_notif   = get_option( 'options_default_icon_notifications', 'light:bell' );
                    $icon_msg     = get_option( 'options_default_icon_messages',      'light:envelope' );
                    $icon_reviews = get_option( 'options_default_icon_reviews',       'light:star' );
                    $icon_empty   = get_option( 'options_default_icon_empty',         'regular:face-smile' );
                    $icon_search  = get_option( 'options_default_icon_search',        'light:magnifying-glass' );
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px 12px;">
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Cart</label>
                            <input type="hidden" id="sh-def-icon-cart" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $icon_cart ); ?>">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Notifications</label>
                            <input type="hidden" id="sh-def-icon-notifications" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $icon_notif ); ?>">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Messages</label>
                            <input type="hidden" id="sh-def-icon-messages" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $icon_msg ); ?>">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Reviews</label>
                            <input type="hidden" id="sh-def-icon-reviews" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $icon_reviews ); ?>">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Empty State</label>
                            <input type="hidden" id="sh-def-icon-empty" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $icon_empty ); ?>">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Search</label>
                            <input type="hidden" id="sh-def-icon-search" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $icon_search ); ?>">
                        </div>
                    </div>
                </div>

                <!-- Sidebar — iki kolon tam genişlik -->
                <div style="grid-column:1 / -1;margin-top:8px;padding-top:16px;border-top:1px solid #e5e7eb">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs" style="font-size:14px;">📐 Sidebar</label>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">Page Settings offcanvas sidebar modunda kullanılan varsayılan değerler.</p>
                    <?php
                    $sb_width  = (int) get_option( 'options_default_sidebar_width', 3 );
                    $sb_gap    = get_option( 'options_default_sidebar_gap', 'gx-lg-5' );
                    $sb_ic     = get_option( 'options_default_sidebar_toggler_icon', 'light:bars' );
                    $sb_lbl    = get_option( 'options_default_sidebar_toggler_label', 'Menu' );
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 12px;">
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Sidebar Width (columns)</label>
                            <select id="sh-def-sidebar-width" style="<?php echo $input_style; ?>">
                                <?php foreach ( range( 2, 6 ) as $col ) : ?>
                                <option value="<?php echo $col; ?>" <?php selected( $sb_width, $col ); ?>><?php echo $col; ?> / 12</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Gap</label>
                            <select id="sh-def-sidebar-gap" style="<?php echo $input_style; ?>">
                                <?php foreach ( ['g-0'=>'None','gx-3'=>'Small','gx-lg-5'=>'Medium','gx-lg-6'=>'Large'] as $gv => $gl ) : ?>
                                <option value="<?php echo esc_attr($gv); ?>" <?php selected( $sb_gap, $gv ); ?>><?php echo esc_html($gl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Toggler Icon</label>
                            <input type="hidden" id="sh-def-sidebar-toggler-icon" data-sh-icon-picker data-placeholder="İkon seç..." value="<?php echo esc_attr( $sb_ic ); ?>">
                        </div>
                        <div>
                            <label class="sh-text-sm sh-text-label sh-block sh-mb-xs">Toggler Label</label>
                            <input type="text" id="sh-def-sidebar-toggler-label" style="<?php echo $input_style; ?>" value="<?php echo esc_attr( $sb_lbl ); ?>" placeholder="Menu">
                        </div>
                    </div>
                </div>

                <!-- Save — iki kolon tam -->
                <div style="grid-column:1 / -1;display:flex;align-items:center;gap:12px;margin-top:8px">
                    <button type="button" id="sh-def-save" class="sh-btn sh-btn-primary">💾 Save Defaults</button>
                    <span id="sh-def-msg" style="font-size:13px;color:#15803d;display:none">✔ Kaydedildi</span>
                </div>

            </div><!-- /.grid -->
            </div><!-- /.sh-card-body -->
        </div><!-- /.sh-card -->

        <script>
        (function(){
            document.getElementById('sh-def-save').addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                btn.textContent = 'Kaydediliyor...';

                var data = {
                    action: 'sh_gs_save_defaults',
                    nonce:  '<?php echo esc_js( $nonce ); ?>',
                    options_default_container:       document.getElementById('sh-def-container').value,
                    options_default_margin_top:      document.getElementById('sh-def-margin-top').value,
                    options_default_margin_bottom:   document.getElementById('sh-def-margin-bottom').value,
                    options_default_margin_left:     document.getElementById('sh-def-margin-left').value,
                    options_default_margin_right:    document.getElementById('sh-def-margin-right').value,
                    options_default_padding_top:     document.getElementById('sh-def-padding-top').value,
                    options_default_padding_bottom:  document.getElementById('sh-def-padding-bottom').value,
                    options_default_padding_left:    document.getElementById('sh-def-padding-left').value,
                    options_default_padding_right:   document.getElementById('sh-def-padding-right').value,
                    options_default_ratio:           document.getElementById('sh-def-ratio').value,
                    options_default_title_size:      document.getElementById('sh-def-title-size').value,
                    options_default_text_size:       document.getElementById('sh-def-text-size').value,
                    options_default_footer_template: document.getElementById('sh-def-footer-tpl').value,
                    options_default_header_template: document.getElementById('sh-def-header-tpl').value,
                    options_default_icon_cart:          document.getElementById('sh-def-icon-cart').value,
                    options_default_icon_notifications: document.getElementById('sh-def-icon-notifications').value,
                    options_default_icon_messages:      document.getElementById('sh-def-icon-messages').value,
                    options_default_icon_reviews:       document.getElementById('sh-def-icon-reviews').value,
                    options_default_icon_empty:         document.getElementById('sh-def-icon-empty').value,
                    options_default_icon_search:        document.getElementById('sh-def-icon-search').value,
                    options_default_sidebar_width:         document.getElementById('sh-def-sidebar-width').value,
                    options_default_sidebar_gap:           document.getElementById('sh-def-sidebar-gap').value,
                    options_default_sidebar_toggler_icon:  document.getElementById('sh-def-sidebar-toggler-icon').value,
                    options_default_sidebar_toggler_label: document.getElementById('sh-def-sidebar-toggler-label').value,
                };

                var body = Object.keys(data).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');

                fetch('<?php echo esc_js( $ajax ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                }).then(function(r){ return r.json(); }).then(function(res){
                    btn.disabled = false;
                    btn.textContent = '💾 Save Defaults';
                    var m = document.getElementById('sh-def-msg');
                    m.textContent = res.success ? '✔ Kaydedildi' : '❌ Hata';
                    m.style.color = res.success ? '#15803d' : '#dc2626';
                    m.style.display = 'inline';
                    setTimeout(function(){ m.style.display = 'none'; }, 2800);
                }).catch(function(){
                    btn.disabled = false;
                    btn.textContent = '💾 Save Defaults';
                });
            });
        })();
        </script>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX: Logos
    |--------------------------------------------------------------------------
    */

    public static function ajaxSaveLogos(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $logo_fields = [ 'logo', 'logo_affix', 'logo_footer', 'logo_mobile', 'logo_icon', 'logo_marker', 'logo_not_found' ];

        foreach ( $logo_fields as $field ) {
            $id  = absint( $_POST[ $field . '_id' ]  ?? 0 );
            $url = esc_url_raw( $_POST[ $field . '_url' ] ?? '' );
            if ( $id ) {
                $meta = wp_get_attachment_metadata( $id );
                update_option( 'options_' . $field, [
                    'ID'     => $id,
                    'id'     => $id,
                    'url'    => $url,
                    'width'  => (int) ( $meta['width']  ?? 0 ),
                    'height' => (int) ( $meta['height'] ?? 0 ),
                ], false );
            } else {
                update_option( 'options_' . $field, '', false );
            }
        }

        update_option( 'options_logo_mobile_breakpoint', sanitize_key( $_POST['logo_mobile_breakpoint'] ?? 'md' ), false );

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();
        wp_send_json_success( 'Logos kaydedildi.' );
    }


    // --- Render: Logos Card ---
    private static function renderLogosCard( string $nonce, string $ajax ): void
    {
        $breakpoints = FieldChoices::breakpoints();

        $logos = [
            'logo'           => [ 'label' => 'Logo',            'desc' => 'Ana logo — header\'da gösterilir' ],
            'logo_affix'     => [ 'label' => 'Logo Affix',      'desc' => 'Sticky / scroll-down header için alternatif logo' ],
            'logo_footer'    => [ 'label' => 'Logo Footer',     'desc' => 'Footer\'da gösterilir' ],
            'logo_mobile'    => [ 'label' => 'Logo Mobile',     'desc' => 'Mobilde gösterilir (breakpoint\'e göre)' ],
            'logo_icon'      => [ 'label' => 'Icon',            'desc' => 'Favicon veya küçük ikon' ],
            'logo_marker'    => [ 'label' => 'Map Marker',      'desc' => 'Site\'nin kendi konumu için özel marker (logo marker)' ],
            'logo_not_found' => [ 'label' => 'Not Found Logo',  'desc' => '404 sayfasında gösterilir' ],
        ];

        $current = [];
        foreach ( array_keys( $logos ) as $key ) {
            $val = get_option( 'options_' . $key, '' );
            $current[ $key ] = is_array( $val ) ? $val : [];
        }
        $mobile_bp = get_option( 'options_logo_mobile_breakpoint', 'md' );
        ?>
        <div class="sh-card sh-mb-sm">
            <div class="sh-card-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f3f4f6">
                <div>
                    <strong style="font-size:15px">🖼️ Logos</strong>
                    <span style="font-size:12px;color:#9ca3af;margin-left:8px">Site logolarını ve ikonlarını yönet</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <button type="button" id="sh-logos-save" class="sh-btn sh-btn-primary">💾 Save Logos</button>
                    <span id="sh-logos-msg" style="font-size:13px;color:#15803d;display:none">✔ Kaydedildi</span>
                </div>
            </div>

            <div style="padding:20px">

                <!-- Logo grid — 3+3 veya auto-fill -->
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:20px">
                    <?php foreach ( $logos as $key => $meta ) :
                        $img_id  = (int) ( $current[$key]['ID']  ?? 0 );
                        $img_url = (string) ( $current[$key]['url'] ?? '' );
                        $img_w   = (int) ( $current[$key]['width']  ?? 0 );
                        $img_h   = (int) ( $current[$key]['height'] ?? 0 );
                    ?>
                    <div class="sh-logo-slot" data-key="<?php echo esc_attr($key); ?>" style="position:relative;border:2px dashed #e5e7eb;border-radius:12px;overflow:hidden;transition:border-color .2s;cursor:pointer;background:#fafafa"
                        onmouseenter="this.style.borderColor='#6366f1'" onmouseleave="this.style.borderColor=this.querySelector('.sh-logo-img')&&this.querySelector('.sh-logo-img').src?'#6366f1':'#e5e7eb'">

                        <!-- Görsel alanı -->
                        <div style="height:120px;display:flex;align-items:center;justify-content:center;padding:12px;position:relative">
                            <?php if ( $img_url ) : ?>
                            <img class="sh-logo-img" src="<?php echo esc_url($img_url); ?>"
                                style="max-width:100%;max-height:96px;object-fit:contain;border-radius:6px">
                            <?php else : ?>
                            <div class="sh-logo-empty" style="display:flex;flex-direction:column;align-items:center;gap:6px;color:#d1d5db">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                <span style="font-size:11px">Görsel yok</span>
                            </div>
                            <?php endif; ?>

                            <!-- Hover overlay -->
                            <div class="sh-logo-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;gap:8px;opacity:0;transition:opacity .2s;border-radius:10px"
                                onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0">
                                <button type="button" class="sh-logo-pick" data-key="<?php echo esc_attr($key); ?>"
                                    style="background:#fff;color:#374151;border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <?php echo $img_url ? 'Değiştir' : 'Yükle'; ?>
                                </button>
                                <?php if ( $img_url ) : ?>
                                <button type="button" class="sh-logo-remove" data-key="<?php echo esc_attr($key); ?>"
                                    style="background:#ef4444;color:#fff;border:none;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer">✔</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Label + boyut -->
                        <div style="padding:0 12px 12px;border-top:1px solid #f3f4f6;padding-top:8px">
                            <div style="font-size:13px;font-weight:600;color:#374151"><?php echo esc_html($meta['label']); ?></div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px"><?php echo esc_html($meta['desc']); ?></div>
                            <?php if ( $img_w && $img_h ) : ?>
                            <div style="font-size:10px;color:#d1d5db;margin-top:4px"><?php echo $img_w; ?>×<?php echo $img_h; ?>px</div>
                            <?php endif; ?>
                        </div>

                        <!-- Hidden inputs -->
                        <input type="hidden" class="sh-logo-id"  data-key="<?php echo esc_attr($key); ?>" value="<?php echo $img_id; ?>">
                        <input type="hidden" class="sh-logo-url" data-key="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($img_url); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Mobile breakpoint -->
                <div style="display:flex;align-items:center;gap:12px;padding-top:16px;border-top:1px solid #f3f4f6">
                    <label style="font-size:13px;font-weight:600;color:#374151;white-space:nowrap">Logo Mobile Breakpoint</label>
                    <select id="sh-logo-mobile-bp" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:120px">
                        <?php foreach ( $breakpoints as $val => $label ) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($mobile_bp, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span style="font-size:12px;color:#9ca3af">Bu breakpoint'ten küçük ekranlarda Logo Mobile gösterilir</span>
                </div>

            </div>
        </div>

        <style>
        .sh-logo-slot:hover .sh-logo-overlay { opacity: 1 !important; }
        .sh-logo-slot { border-color: <?php /* highlight if set */ ?> }
        </style>

        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;

            // WP Media frame'leri key başına cache
            var mediaFrames = {};

            // Pick butonları
            document.querySelectorAll('.sh-logo-pick').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var key = this.dataset.key;
                    if (!mediaFrames[key]) {
                        mediaFrames[key] = wp.media({ title: this.closest('.sh-logo-slot').querySelector('.sh-logo-slot > div > div')?.textContent?.trim() || key, button: { text: 'Seç' }, multiple: false });
                        mediaFrames[key].on('select', function(){
                            var att = mediaFrames[key].state().get('selection').first().toJSON();
                            updateSlot(key, att.id, att.url, att.width, att.height);
                        });
                    }
                    mediaFrames[key].open();
                });
            });

            // Slot'a tıkla = pick
            document.querySelectorAll('.sh-logo-slot').forEach(function(slot){
                slot.addEventListener('click', function(){
                    var btn = this.querySelector('.sh-logo-pick');
                    if (btn) btn.click();
                });
            });

            // Remove butonları — event delegation
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.sh-logo-remove');
                if (!btn) return;
                e.stopPropagation();
                updateSlot(btn.dataset.key, 0, '', 0, 0);
            });

            function updateSlot(key, id, url, w, h) {
                var slot = document.querySelector('.sh-logo-slot[data-key="'+key+'"]');
                if (!slot) return;
                // hidden inputs
                slot.querySelector('.sh-logo-id').value  = id  || 0;
                slot.querySelector('.sh-logo-url').value = url || '';
                // görsel alanı
                var imgArea = slot.querySelector('.sh-logo-img, .sh-logo-empty');
                if (imgArea && url) {
                    imgArea.outerHTML = '<img class="sh-logo-img" src="'+url+'" style="max-width:100%;max-height:96px;object-fit:contain;border-radius:6px">';
                } else if (imgArea && !url) {
                    imgArea.outerHTML = '<div class="sh-logo-empty" style="display:flex;flex-direction:column;align-items:center;gap:6px;color:#d1d5db"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg><span style="font-size:11px">Görsel yok</span></div>';
                }
                // boyut label
                var sizeEl = slot.querySelector('.sh-logo-slot > div:last-child > div:last-child');
                if (sizeEl) sizeEl.textContent = (w && h) ? w+'×'+h+'px' : '';
                // pick buton metni
                var pickBtn = slot.querySelector('.sh-logo-pick');
                if (pickBtn) pickBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> ' + (url ? 'Değiştir' : 'Yükle');
                // remove buton
                var overlay = slot.querySelector('.sh-logo-overlay');
                if (overlay) {
                    var remBtn = overlay.querySelector('.sh-logo-remove');
                    if (url && !remBtn) {
                        var nb = document.createElement('button');
                        nb.type = 'button'; nb.className = 'sh-logo-remove'; nb.dataset.key = key;
                        nb.style.cssText = 'background:#ef4444;color:#fff;border:none;border-radius:8px;padding:6px 10px;font-size:12px;cursor:pointer';
                        nb.textContent = 'ÃÂ¢ÃâÃ¢â¬Â¢';
                        overlay.appendChild(nb);
                    } else if (!url && remBtn) {
                        remBtn.remove();
                    }
                }
                // border rengi
                slot.style.borderColor = url ? '#6366f1' : '#e5e7eb';
            }

            // Save
            document.getElementById('sh-logos-save').addEventListener('click', function(){
                var btn = this; btn.disabled = true; btn.textContent = 'Kaydediliyor...';
                var fd = new FormData();
                fd.append('action', 'sh_gs_save_logos');
                fd.append('nonce', NONCE);
                document.querySelectorAll('.sh-logo-slot').forEach(function(slot){
                    var key = slot.dataset.key;
                    fd.append(key + '_id',  slot.querySelector('.sh-logo-id').value || 0);
                    fd.append(key + '_url', slot.querySelector('.sh-logo-url').value || '');
                });
                fd.append('logo_mobile_breakpoint', document.getElementById('sh-logo-mobile-bp').value);
                fetch(AJAX, { method:'POST', body:fd }).then(r=>r.json()).then(function(res){
                    btn.disabled = false; btn.textContent = '💾 Save Logos';
                    var m = document.getElementById('sh-logos-msg');
                    m.textContent = res.success ? '✔ Kaydedildi' : '❌ Hata';
                    m.style.color = res.success ? '#15803d' : '#dc2626';
                    m.style.display = 'inline';
                    setTimeout(function(){ m.style.display='none'; }, 2800);
                });
            });

            // Mevcut set olan slotların border'ını purple yap
            document.querySelectorAll('.sh-logo-slot').forEach(function(slot){
                if (slot.querySelector('.sh-logo-img')) slot.style.borderColor = '#6366f1';
            });
        })();
        </script>
        <?php
    }

    // --- AJAX: Maps ---
    public static function ajaxSaveMaps(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        // Fallback: api_key yoksa googleÃÂ¢Ã¢â¬Â Ã¢â¬â¢leaflet'e dön
        $map_service = sanitize_key( $_POST['map_service'] ?? '' );
        $map_view    = sanitize_key( $_POST['map_view']    ?? '' );
        $api_key     = sanitize_text_field( $_POST['google_maps_api_key'] ?? '' );
        if ( $map_service === 'google' && $map_view === 'js' && empty( $api_key ) ) {
            $map_service = 'leaflet';
        }

        // Önceki değerleri al — değiştiyse PAE refetch tetikle
        $prev_service = get_option( 'options_map_service', '' );
        $prev_view    = get_option( 'options_map_view',    '' );

        update_option( 'options_map_service',         $map_service, false );
        update_option( 'options_map_view',            $map_view,    false );
        update_option( 'options_google_maps_api_key', $api_key,     false );
        update_option( 'options_google_maps_style',   sanitize_textarea_field( $_POST['google_maps_style'] ?? '' ), false );

        $marker_id  = absint( $_POST['map_marker_id'] ?? 0 );
        $marker_url = esc_url_raw( $_POST['map_marker_url'] ?? '' );
        if ( $marker_id ) {
            $meta = wp_get_attachment_metadata( $marker_id );
            update_option( 'options_map_marker', [
                'ID'     => $marker_id,
                'url'    => $marker_url,
                'width'  => (int) ( $meta['width']  ?? 0 ),
                'height' => (int) ( $meta['height'] ?? 0 ),
            ], false );
        } else {
            update_option( 'options_map_marker', '', false );
        }

        if ( class_exists( '\\SaltHareket\\SaltConfig' ) ) \SaltHareket\SaltConfig::generate();

        // map_service veya map_view değiştiyse — harita içeren post'ları PAE ile refetch et
        if ( ( $map_service !== $prev_service || $map_view !== $prev_view ) && class_exists( 'PageAssetsExtractor' ) ) {
            $posts = get_posts([
                'post_type'      => array_keys( get_post_types(['public' => true]) ),
                'posts_per_page' => -1,
                'meta_query'     => [
                    'relation' => 'OR',
                    [ 'key' => 'assets', 'value' => 'leaflet',          'compare' => 'LIKE' ],
                    [ 'key' => 'assets', 'value' => 'markerclusterer',  'compare' => 'LIKE' ],
                    [ 'key' => 'has_map','value' => 1,                  'compare' => '='    ],
                ],
            ]);
            if ( $posts ) {
                $extractor = \PageAssetsExtractor::get_instance();
                foreach ( $posts as $post ) {
                    $extractor->on_save_post( $post->ID, $post, true );
                }
            }
        }

        wp_send_json_success( 'Map ayarları kaydedildi.' );
    }

    /*
    |--------------------------------------------------------------------------
    | Render: Maps Card
    |--------------------------------------------------------------------------
    */

    private static function renderMapsCard( string $nonce, string $ajax ): void
    {
        $map_service    = get_option( 'options_map_service',         'leaflet' );
        $map_view       = get_option( 'options_map_view',            'js' );
        $google_api_key = get_option( 'options_google_maps_api_key', '' );
        $google_style   = get_option( 'options_google_maps_style',   '' );
        $map_marker     = get_option( 'options_map_marker',          '' );
        $marker_id      = is_array( $map_marker ) ? (int) ( $map_marker['ID']  ?? 0 ) : 0;
        $marker_url     = is_array( $map_marker ) ? (string) ( $map_marker['url'] ?? '' ) : '';

        $inp = 'padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box;width:100%';
        ?>
        <div class="sh-card sh-mt-sm">
            <div class="sh-card-header"><strong>🗺️ Maps</strong></div>
            <div class="sh-card-body">
            <div style="max-width:600px">

                <!-- Map Service -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Map Service on frontend</label>
                    <select id="sh-map-service" style="<?php echo $inp; ?>">
                        <option value="leaflet" <?php selected($map_service,'leaflet'); ?>>Leaflet (OpenStreetMap)</option>
                        <option value="google"  <?php selected($map_service,'google');  ?>>Google Maps</option>
                    </select>
                </div>

                <!-- Map View -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Map View</label>
                    <p class="sh-field-hint" style="margin-bottom:6px">"Embed Iframe" doesn't support multiple locations.</p>
                    <div style="display:flex;gap:16px">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                            <input type="radio" name="sh_map_view" value="js"    <?php checked($map_view,'js');    ?>> Custom JS Map
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                            <input type="radio" name="sh_map_view" value="embed" <?php checked($map_view,'embed'); ?>> Embed Iframe
                        </label>
                    </div>
                </div>

                <!-- Google Maps API Key — service=google AND view=js -->
                <div id="sh-map-api-wrap" class="sh-mb-md" style="<?php echo ($map_service !== 'google' || $map_view !== 'js') ? 'display:none' : ''; ?>">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Google Maps Api Key</label>
                    <input type="text" id="sh-map-api-key" value="<?php echo esc_attr($google_api_key); ?>" placeholder="AIza..." style="<?php echo $inp; ?>">
                </div>

                <!-- Google Maps Style — service=google AND view=js AND api_key not empty -->
                <div id="sh-map-style-wrap" class="sh-mb-md" style="<?php echo ($map_service !== 'google' || $map_view !== 'js' || empty($google_api_key)) ? 'display:none' : ''; ?>">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Google Maps Style <span style="font-weight:400;color:#6b7280">(Optional)</span></label>
                    <p class="sh-field-hint" style="margin-bottom:6px">You may find any style on <a href="https://snazzymaps.com/" target="_blank" style="color:#2271b1">snazzymaps.com</a></p>
                    <textarea id="sh-map-style" rows="5" style="<?php echo $inp; ?>;font-family:Consolas,monospace;font-size:12px" placeholder="[ { &quot;featureType&quot;: ... } ]"><?php echo esc_textarea($google_style); ?></textarea>
                </div>

               <!-- Map Marker - view=js -->
                <div id="sh-map-marker-wrap" class="sh-mb-md" style="<?php echo $map_view !== 'js' ? 'display:none' : ''; ?>">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Map Marker <span style="font-weight:400;color:#6b7280">(Tüm location'lar için genel marker)</span></label>
                    <div style="display:flex;align-items:center;gap:10px">
                        <?php if ( $marker_url ) : ?>
                        <img id="sh-map-marker-preview" src="<?php echo esc_url($marker_url); ?>" style="width:48px;height:48px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;padding:4px">
                        <?php else : ?>
                        <div id="sh-map-marker-preview" style="width:48px;height:48px;border:1px dashed #d1d5db;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:10px">Yok</div>
                        <?php endif; ?>
                        <input type="hidden" id="sh-map-marker-id"  value="<?php echo $marker_id; ?>">
                        <input type="hidden" id="sh-map-marker-url" value="<?php echo esc_attr($marker_url); ?>">
                        <button type="button" id="sh-map-marker-btn" class="sh-btn sh-btn-sm">Görsel seç</button>
                        <button type="button" id="sh-map-marker-remove" class="sh-btn sh-btn-sm" style="color:#dc2626;border-color:#dc2626;<?php echo !$marker_id ? 'display:none' : ''; ?>">Kaldır</button>
                    </div>
                </div>

                <!-- Save -->
                <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
                    <button type="button" id="sh-gs-save-maps" class="sh-btn sh-btn-primary">💾 Save Maps</button>
                    <span id="sh-gs-maps-msg" style="font-size:13px;color:#15803d;display:none">✔ Kaydedildi</span>
                </div>

            </div>
            </div>
        </div>

        <script>
        (function(){
            var NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;

            function updateConditionals() {
                var service = document.getElementById('sh-map-service').value;
                var viewEl  = document.querySelector('input[name="sh_map_view"]:checked');
                var view    = viewEl ? viewEl.value : 'js';
                var apiKey  = (document.getElementById('sh-map-api-key') ? document.getElementById('sh-map-api-key').value : '').trim();
                var isGoogle = service === 'google';
                var isJs     = view === 'js';
                document.getElementById('sh-map-api-wrap').style.display    = (isGoogle && isJs) ? '' : 'none';
                document.getElementById('sh-map-style-wrap').style.display  = (isGoogle && isJs && apiKey.length > 0) ? '' : 'none';
                document.getElementById('sh-map-marker-wrap').style.display = isJs ? '' : 'none';
            }

            document.getElementById('sh-map-service').addEventListener('change', updateConditionals);
            document.querySelectorAll('input[name="sh_map_view"]').forEach(function(r){ r.addEventListener('change', updateConditionals); });
            var apiEl = document.getElementById('sh-map-api-key');
            if (apiEl) apiEl.addEventListener('input', updateConditionals);

            document.getElementById('sh-map-marker-btn').addEventListener('click', function(){
                if (typeof wp === 'undefined' || !wp.media) return;
                var frame = wp.media({ title: 'Map Marker Görseli', button: { text: 'Seç' }, multiple: false });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    document.getElementById('sh-map-marker-id').value  = att.id;
                    document.getElementById('sh-map-marker-url').value = att.url;
                    var prev = document.getElementById('sh-map-marker-preview');
                    prev.outerHTML = '<img id="sh-map-marker-preview" src="' + att.url + '" style="width:48px;height:48px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;padding:4px">';
                    document.getElementById('sh-map-marker-remove').style.display = '';
                });
                frame.open();
            });

            document.getElementById('sh-map-marker-remove').addEventListener('click', function(){
                document.getElementById('sh-map-marker-id').value  = 0;
                document.getElementById('sh-map-marker-url').value = '';
                var prev = document.getElementById('sh-map-marker-preview');
                prev.outerHTML = '<div id="sh-map-marker-preview" style="width:48px;height:48px;border:1px dashed #d1d5db;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:10px">Yok</div>';
                this.style.display = 'none';
            });

            var _shSaveEl=document.getElementById('sh-gs-save-maps'); if(_shSaveEl) _shSaveEl.addEventListener('click', function(){
                var btn = this; btn.disabled = true; btn.textContent = 'Kaydediliyor...';
                var viewEl = document.querySelector('input[name="sh_map_view"]:checked');
                var fd = new FormData();
                fd.append('action', 'sh_gs_save_maps');
                fd.append('nonce', NONCE);
                fd.append('map_service',        document.getElementById('sh-map-service').value);
                fd.append('map_view',           viewEl ? viewEl.value : 'js');
                fd.append('google_maps_api_key', document.getElementById('sh-map-api-key') ? document.getElementById('sh-map-api-key').value : '');
                fd.append('google_maps_style',   document.getElementById('sh-map-style')   ? document.getElementById('sh-map-style').value   : '');
                fd.append('map_marker_id',       document.getElementById('sh-map-marker-id').value  || 0);
                fd.append('map_marker_url',      document.getElementById('sh-map-marker-url').value || '');
                
                fetch(AJAX, { method:'POST', body:fd }).then(r=>r.json()).then(function(res){
                    btn.disabled = false; btn.textContent = '💾 Save Maps';
                    var m = document.getElementById('sh-gs-maps-msg');
                    m.textContent = res.success ? '✔ Kaydedildi' : '❌ Hata';
                    m.style.color = res.success ? '#15803d' : '#dc2626';
                    m.style.display = 'inline';
                    setTimeout(function(){ m.style.display='none'; }, 2800);
                    updateConditionals();
                });
            });
        })();
        </script>
        <?php
    }
}

