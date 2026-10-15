<?php

/**
 * PAE Admin UI Trait
 *
 * PAE admin tablosu, AJAX handler'lar ve admin menü kaydı.
 * display_page_assets_table(), pae_clear_cache_ajax(), pae_toggle_dual_fetch_ajax() içerir.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor
 * @version    1.0.0
 * @since      1.9.7
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrıldı
 *     - Add: CODING_PRINCIPLES uyumlu dokümantasyon
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sınıfı içinde kullanılır.
 *   Admin menüde Theme Settings → Page Assets altında görünür.
 *   URL: /wp-admin/admin.php?page=page-assets-update
 *
 * @example Admin sayfasını render et:
 *   // Otomatik — register_admin_page() hook'u ile kayıtlı
 *   // Manuel:
 *   PageAssetsExtractor::get_instance()->display_page_assets_table();
 *
 * @example Cache temizle (AJAX):
 *   jQuery.post(ajaxurl, {
 *       action: 'pae_clear_cache',
 *       _ajax_nonce: paeClearNonce,
 *       reset_meta: 0
 *   });
 *
 * @example Dual fetch toggle (AJAX):
 *   jQuery.post(ajaxurl, {
 *       action: 'pae_toggle_dual_fetch',
 *       _ajax_nonce: paeClearNonce,
 *       id: 8428,
 *       enable: 1
 *   });
 *
 * @example ACF field render (otomatik):
 *   // acf/render_field/name=page_assets hook'u ile tetiklenir
 */
trait PageAssetsAdmin {

    /**
     * Admin menüsüne "Page Assets" sayfasını theme-settings altına kaydeder.
     * ACF bağımsız — native WordPress add_submenu_page() kullanır.
     *
     * @return void
     *
     * @example
     *   // Otomatik — constructor'da add_action('admin_menu', ...) ile kayıtlı
     */
    public function register_admin_page(): void {
        // PAE artık Asset Manager'ın page_assets tab'ında — ayrı menü yok.
        // Eski URL'e gelen istekleri yönlendir.
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'page-assets-update' ) {
            wp_redirect( admin_url( 'admin.php?page=sh-asset-manager&tab=page_assets' ), 301 );
            exit;
        }
    }

    /**
     * ACF field render callback — display_page_assets_table()'ı çağırır.
     *
     * @param  array $field
     * @return array
     *
     * @example
     *   // Otomatik — acf/render_field/name=page_assets hook'u ile tetiklenir
     */
    public function update_page_assets_message_field(array $field): array {
        $this->display_page_assets_table();
        return $field;
    }

    /**
     * AJAX: Orphan dosya sayısını döndürür.
     */
    public function pae_get_orphan_count_ajax(): void {
        check_ajax_referer( 'pae_clear_cache_nonce', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $orphans = $this->get_orphan_files();
        wp_send_json_success( [
            'css'        => count( $orphans['css'] ),
            'js'         => count( $orphans['js'] ),
            'total'      => count( $orphans['css'] ) + count( $orphans['js'] ),
            'css_files'  => array_map( 'basename', $orphans['css'] ),
            'js_files'   => array_map( 'basename', $orphans['js'] ),
        ] );
    }

    /**
     * AJAX: Orphan dosyaları temizler.
     */
    public function pae_clean_orphans_ajax(): void {
        check_ajax_referer( 'pae_clear_cache_nonce', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $result = $this->clean_orphan_files();

        wp_send_json_success( [
            'message' => sprintf(
                '%d orphan dosya silindi (CSS: %d, JS: %d).',
                $result['deleted'],
                $result['css'],
                $result['js']
            ),
            'deleted' => $result['deleted'],
            'css'     => $result['css'],
            'js'      => $result['js'],
            'errors'  => $result['errors'],
        ] );
    }

    /**
     * AJAX: Cache temizle.
     * JS/CSS cache dosyaları + manifest siler. Opsiyonel: meta resetle.
     *
     * @return void
     *
     * @example JS:
     *   jQuery.post(ajaxurl, {
     *       action: 'pae_clear_cache',
     *       _ajax_nonce: paeClearNonce,
     *       reset_meta: 1
     *   }, function(res) { console.log(res.data.message); });
     */
    public function pae_clear_cache_ajax(): void {
        check_ajax_referer('pae_clear_cache_nonce', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $reset_meta = !empty($_POST['reset_meta']) && $_POST['reset_meta'] === '1';
        $deleted    = 0;
        $errors     = [];

        // JS cache
        $js_cache = rtrim(STATIC_PATH, '/') . '/js/cache/';
        if (is_dir($js_cache)) {
            foreach (glob($js_cache . '*.js') ?: [] as $f) {
                if (@unlink($f)) $deleted++;
                else $errors[] = basename($f);
            }
        }

        // CSS cache
        $css_cache = rtrim(STATIC_PATH, '/') . '/css/cache/';
        if (is_dir($css_cache)) {
            foreach (glob($css_cache . '*.css') ?: [] as $f) {
                if (@unlink($f)) $deleted++;
                else $errors[] = basename($f);
            }
        }

        // Manifest
        $manifest_file = rtrim(STATIC_PATH, '/') . '/cache-manifest/assets-manifest.json';
        if (file_exists($manifest_file)) {
            if (@unlink($manifest_file)) $deleted++;
            else $errors[] = 'assets-manifest.json';
        }
        $this->manifest = [];

        // Font transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_font_inline_cache_%' OR option_name LIKE '_transient_timeout_font_inline_cache_%'");

        // Meta reset (opsiyonel)
        $meta_count = 0;
        if ($reset_meta) {
            $post_ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '" . self::META_KEY . "'");
            foreach ($post_ids as $pid) { delete_post_meta((int) $pid, self::META_KEY); $meta_count++; }
            $term_ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = '" . self::META_KEY . "'");
            foreach ($term_ids as $tid) { delete_term_meta((int) $tid, self::META_KEY); $meta_count++; }

            // Archive / dynamic / woo_account option'ları — hepsi `{id}_assets` formatında
            // Güvenli pattern: _assets ile biten, sistem option'larını (sh_, options_, pae_, _transient) dışarıda tutan
            $dyn_opts = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE '%_assets'
                 AND option_name NOT LIKE '\\_transient%'
                 AND option_name NOT LIKE 'options\\_%'
                 AND option_name NOT LIKE 'sh\\_%'
                 AND option_name NOT LIKE 'pae\\_%'
                 AND option_name NOT LIKE 'woocommerce\\_%'
                 AND option_name NOT LIKE 'wp\\_%'"
            );
            foreach ( $dyn_opts as $opt_name ) {
                // PAE formatı: lowercase + underscore + tire + _assets sonu
                // Örnekler: woo_account_edit-address_en_assets, search_assets, product_archive_tr_assets
                if ( preg_match( '/^[a-z0-9][a-z0-9_\-]*_assets$/', $opt_name ) ) {
                    delete_option( $opt_name );
                    $meta_count++;
                }
            }

            wp_cache_flush();
        }

        if (function_exists('rocket_clean_domain')) rocket_clean_domain();

        $msg = "{$deleted} dosya silindi.";
        if ($reset_meta) $msg .= " {$meta_count} meta resetlendi.";
        if (!empty($errors)) $msg .= " Silinemeyenler: " . implode(', ', array_slice($errors, 0, 3));
        if (function_exists('rocket_clean_domain')) $msg .= " WP Rocket cache temizlendi.";

        $this->error_log("[PAE] clear_cache: {$msg}");
        wp_send_json_success(['message' => $msg, 'deleted' => $deleted, 'meta_reset' => $meta_count]);
    }

    /**
     * AJAX: Dual fetch toggle.
     * Zorunlu sayfalar (cart, checkout, myaccount) değiştirilemez.
     *
     * @return void
     *
     * @example JS:
     *   jQuery.post(ajaxurl, {
     *       action: 'pae_toggle_dual_fetch',
     *       _ajax_nonce: paeClearNonce,
     *       id: 999,
     *       enable: 1
     *   }, function(res) { console.log(res.data); });
     */
    public function pae_toggle_dual_fetch_ajax(): void {
        check_ajax_referer('pae_clear_cache_nonce', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id     = (int) ($_POST['id'] ?? 0);
        $enable = filter_var($_POST['enable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$id) {
            wp_send_json_error('Invalid ID');
        }

        if (in_array($id, $this->get_forced_dual_fetch_ids(), true)) {
            wp_send_json_success(['forced' => true, 'enabled' => true]);
            return;
        }

        $this->set_dual_fetch($id, $enable);
        wp_send_json_success(['forced' => false, 'enabled' => $enable]);
    }

    /**
     * AJAX: Sayfa asset'lerini güncelle.
     *
     * @return void
     *
     * @example JS:
     *   jQuery.post(ajaxurl, {
     *       action: 'page_assets_update',
     *       _ajax_nonce: paeNonce,
     *       data: { id: 123, type: 'post', url: '...', mass: false, grouped: true }
     *   });
     */
    public function page_assets_update(): void {
        check_ajax_referer('page_assets_nonce', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $row     = isset($_POST['data']) ? (array) $_POST['data'] : [];
        $id      = $row['id']   ?? 0;
        $type    = $row['type'] ?? 'post';
        $url     = $row['url']  ?? '';
        $index   = (int) ($row['index'] ?? 0);
        $total   = (int) ($row['total'] ?? 0);
        $mass    = isset($row['mass'])    ? filter_var($row['mass'],    FILTER_VALIDATE_BOOLEAN) : false;
        $grouped = isset($row['grouped']) ? filter_var($row['grouped'], FILTER_VALIDATE_BOOLEAN) : false;

        $this->type = $type;
        $this->mass = $mass;

        if (!empty($row['post_type']) && $row['post_type'] === 'woo_account') {
            $this->auth_cookies = $this->get_admin_cookies();
        }

        if ($mass) {
            $this->mass_index = $index;
            $this->mass_total = $total;
        }

        // css_page meta'da var ama fiziksel dosya yoksa force_rebuild tetikle
        $existing_meta = $this->meta_get( $type, $id );
        if ( ! empty( $existing_meta['css_page'] ) ) {
            $css_abs = rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $existing_meta['css_page'], '/' );
            if ( ! file_exists( $css_abs ) ) {
                $this->force_rebuild = true;
            }
        }

        $data = $this->fetch($url, $id, $type);

        $grouped_count = 0;
        if ($grouped && $data && !empty($row['post_type']) && !empty($row['context'])) {
            $source_id = is_numeric($id) ? (int) $id : 0;
            if ($source_id > 0) {
                $grouped_count = $this->grouped_apply_assets($source_id, $row['post_type'], $row['context']);
            }
        }

        // fetch() false döndürdüyse (timeout, parse hatası vb.) error bildir
        if ( $data === false ) {
            wp_send_json( [
                'error'   => true,
                'message' => 'Fetch failed — timeout or parse error. Please retry.',
                'data'    => null,
            ] );
        }

        wp_send_json([
            'error'         => false,
            'message'       => $grouped_count > 0 ? "Fetched + applied to {$grouped_count} items" : '',
            'html'          => '',
            'data'          => $data,
            'grouped_count' => $grouped_count,
        ]);
    }

    /**
     * PAE admin tablosunu render eder.
     * Tüm sayfaları listeler, dual fetch toggle, mass update ve cache temizleme içerir.
     *
     * @return void
     *
     * @example
     *   // Otomatik — register_admin_page() callback'i olarak çağrılır
     *   // Manuel:
     *   PageAssetsExtractor::get_instance()->display_page_assets_table();
     */

    /**
     * Tablo verilerini döndürür — Asset Manager entegrasyonu için.
     * rows, woo_pages, real_total, grand_total, nonce_clear
     */
    public function get_table_data(): array {
        $this->grouped_fetch = true;
        $raw = $this->get_grouped_urls();
        $rows = [];
        $woo_pages = [];
        foreach ( $raw as $item ) {
            if ( isset( $item['post_type'] ) && in_array( $item['post_type'], [ 'woo_account', 'woo_page' ], true ) ) {
                $woo_pages[] = $item;
            } elseif ( ! empty( $item['woo_section'] ) ) {
                $woo_pages[] = $item;
            } else {
                $rows[] = $item;
            }
        }
        $grand_total = count( $rows ) + count( $woo_pages );
        $real_total  = 0;
        foreach ( array_merge( $rows, $woo_pages ) as $r ) {
            $real_total += isset( $r['real_count'] ) ? (int) $r['real_count'] : ( ( isset( $r['count'] ) && $r['count'] > 1 ) ? (int) $r['count'] : 1 );
        }
        return [
            'rows'        => $rows,
            'woo_pages'   => $woo_pages,
            'grand_total' => $grand_total,
            'real_total'  => $real_total,
            'nonce_clear' => wp_create_nonce( 'pae_clear_cache_nonce' ),
            'nonce'       => wp_create_nonce( 'page_assets_nonce' ),
        ];
    }

    public function display_page_assets_table(): void {
        $this->grouped_fetch = true;
        $raw  = $this->get_grouped_urls();
        $rows = [];

        // WooCommerce sayfalarını ayır
        $woo_pages = [];
        foreach ($raw as $item) {
            if (isset($item['post_type']) && in_array($item['post_type'], ['woo_account', 'woo_page'], true)) {
                $woo_pages[] = $item;
            } elseif (!empty($item['woo_section'])) {
                $woo_pages[] = $item;
            } else {
                $rows[] = $item;
            }
        }

        $total       = count($rows);
        $woo_total   = count($woo_pages);
        $grand_total = $total + $woo_total;

        // Grouped içindeki gerçek item sayısı
        $real_total = 0;
        foreach (array_merge($rows, $woo_pages) as $r) {
            // real_count varsa onu kullan (diller dahil toplam), yoksa count
            $real_total += isset($r['real_count']) ? (int)$r['real_count'] : ( (isset($r['count']) && $r['count'] > 1) ? (int)$r['count'] : 1 );
        }

        $message = $grand_total
            ? "<strong>{$grand_total} jobs</strong>" . ($real_total > $grand_total ? " covering <strong>{$real_total} pages</strong>" : "") . " ready to extract."
            : "No pages found to extract.";

        $nonce_clear = wp_create_nonce('pae_clear_cache_nonce');

        // ── Styles ───────────────────────────────────────────────────────────
        echo '<style>
        .pae-sticky-toolbar {
            position: sticky; top: 32px; z-index: 999;
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 8px 14px 0 14px;
            margin: -16px -16px 12px -16px;
            display: flex; flex-direction: column; gap: 0;
        }
        .pae-toolbar-main { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; padding-bottom:8px; }
        .pae-toolbar-left  { display:flex; align-items:center; gap:8px; }
        .pae-toolbar-right { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .pae-toolbar-title { font-size:12px; font-weight:600; color:#374151; margin:0; }
        .pae-toolbar-count { font-size:11px; color:#64748b; background:#f1f5f9; padding:2px 6px; border-radius:10px; }
        .pae-progress-wrap { display:none; padding:6px 0 8px 0; border-top:1px solid #e2e8f0; }
        .pae-progress-wrap.active { display:block; }
        .pae-progress-label { font-size:11px; color:#475569; margin-bottom:3px; }
        body .notice:not(.pae-inline-notice), body .updated:not(.pae-inline-notice), body .error:not(.pae-inline-notice) { display:none !important; }
        .pae-clear-dropdown { position:relative; display:inline-block; }
        .pae-clear-panel {
            display:none; position:absolute; right:0; top:calc(100% + 4px);
            background:#fff; border:1px solid #e2e8f0; border-radius:6px;
            box-shadow:0 6px 20px rgba(0,0,0,0.10); padding:12px; min-width:240px; z-index:1000;
        }
        .pae-clear-panel.open { display:block; }
        .pae-clear-panel label { display:flex; align-items:center; gap:6px; font-size:12px; color:#374151; cursor:pointer; margin-bottom:10px; }
        /* Dual fetch column */
        .pae-dual-col { width:100px; text-align:center; }
        .pae-dual-forced { font-size:15px; cursor:default; }
        .pae-dual-toggle { width:14px; height:14px; cursor:pointer; }
        /* Tablo minimal */
        .table-page-assets th { font-size:10px !important; padding:7px 10px !important; }
        .table-page-assets td { font-size:12px !important; padding:7px 10px !important; }
        .table-page-assets-woo th { font-size:10px !important; padding:7px 10px !important; }
        .table-page-assets-woo td { font-size:12px !important; padding:7px 10px !important; }
        /* Sarı grouped badge */
        .table-page-assets .badge, .table-page-assets-woo .badge { font-size:10px !important; }
        /* actions column */
        .table-page-assets .actions,
        .table-page-assets-woo .actions {
            width: 80px;
            height: 40px;
            white-space: nowrap;
            vertical-align: middle;
        }
        /* row sabit yukseklik */
        .table-page-assets tr,
        .table-page-assets-woo tr { height: 40px; }
        </style>';

        echo '<div class="wrap"><div class="pae-wrap">';

        // ── Info + Buttons bar ────────────────────────────────────────────────
        echo '<div class="bg-white rounded-3 p-3 shadow-sm">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">';
        echo '  <span style="font-size:12px;color:#6b7280;">' . $message . '</span>';
        echo '  <div style="display:flex;align-items:center;gap:6px;">';
        echo '    <a href="#" class="btn-page-assets-update sh-btn sh-btn-primary sh-btn-sm">&#9654; Start Mass Update</a>';
        echo '    <div class="pae-clear-dropdown">';
        echo '      <button type="button" class="sh-btn sh-btn-sm" id="pae-clear-toggle" style="border:1px solid #fca5a5;color:#dc2626;background:#fff">&#128465; Clear Cache</button>';
        echo '      <div class="pae-clear-panel" id="pae-clear-panel">';
        echo '        <label><input type="checkbox" id="pae-reset-meta" value="1"> Meta reset <small style="display:block;font-size:11px;color:#9ca3af;">CSS/JS path\'leri DB\'den temizler</small></label>';
        echo '        <button type="button" class="sh-btn sh-btn-sm" id="pae-clear-confirm" style="width:100%;background:#dc2626;color:#fff;border:none;">&#128465; Temizle</button>';
        echo '      </div>';
        echo '    </div>';
        echo '    <button type="button" class="sh-btn sh-btn-sm" id="pae-clean-orphans" style="border:1px solid #f59e0b;color:#92400e;background:#fff" title="Manifest\'te referansı olmayan CSS/JS dosyalarını sil">';
        echo '      &#9851; Orphans <span id="pae-orphan-badge" style="font-size:10px;background:#f59e0b;color:#fff;padding:1px 5px;border-radius:8px;margin-left:3px">...</span>';
        echo '    </button>';
        echo '  </div>';
        echo '</div>';

        $forced_dual_ids   = $this->get_forced_dual_fetch_ids();
        $optional_dual_ids = $this->get_optional_dual_fetch_ids();

        // ── Yardımcı: tablo satırı render ────────────────────────────────────
        $render_row = function(array $row, int $actual_index) use ($forced_dual_ids, $optional_dual_ids): void {
            $label      = $row['label']    ?? $row['post_type'];
            $count      = $row['count']    ?? 1;
            $context    = $row['context']  ?? $row['type'];
            $is_grouped = ($count > 1 && !in_array($context, ['page', 'dynamic', 'archive'], true));
            $row_id     = is_numeric($row['id']) ? (int) $row['id'] : 0;
            $is_forced  = $row_id > 0 && in_array($row_id, $forced_dual_ids, true);
            $is_dual_on = $is_forced || ($row_id > 0 && in_array($row_id, $optional_dual_ids, true));

            // Meta: css, css_page, js, plugins
            $meta     = null;
            $row_type = $row['type'] ?? 'post';
            if ( in_array( $row_type, [ 'archive', 'dynamic' ], true ) ) {
                // archive ve dynamic: WP option olarak saklanır (string ID)
                $meta = get_option( $row['id'] . '_assets' );
            } elseif ( $is_grouped && $row_id > 0 ) {
                // Grouped: richest item'ın meta'sını oku (row_id zaten richest item'ın ID'si)
                if ( $row_type === 'term' ) {
                    $meta = get_term_meta( $row_id, 'assets', true );
                } else {
                    $meta = get_post_meta( $row_id, 'assets', true );
                }
            } elseif ( $row_id > 0 ) {
                switch ( $row_type ) {
                    case 'term':    $meta = get_term_meta( $row_id, 'assets', true ); break;
                    case 'user':    $meta = get_user_meta( $row_id, 'assets', true ); break;
                    case 'comment': $meta = get_comment_meta( $row_id, 'assets', true ); break;
                    default:        $meta = get_post_meta( $row_id, 'assets', true ); break;
                }
            }

            $has_css      = ! empty( $meta['css'] );
            $has_css_page = ! empty( $meta['css_page'] ) && file_exists( rtrim( str_replace('\\','/',STATIC_PATH), '/' ) . '/' . ltrim( str_replace('\\','/',$meta['css_page']), '/' ) );
            $has_css_meta = ! empty( $meta['css_page'] );
            $has_js       = ! empty( $meta['js'] );
            $plugins_list = [];
            if ( ! empty( $meta['plugins'] ) && is_array( $meta['plugins'] ) ) {
                foreach ( $meta['plugins'] as $ph ) {
                    if ( is_string( $ph ) ) {
                        $plugins_list[] = basename( $ph, '.css' );
                    }
                }
            }

            $woo_icon = '';
            if (!empty($row['icon']) && $row['icon'] === 'woo') {
                $woo_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 503.81 299.89" width="26.88" height="16" style="vertical-align:middle;margin-right:6px;""><path class="cls-1" style="fill:#7f54b3;" d="M46.75,0H456.84a46.94,46.94,0,0,1,47,47V203.5a46.94,46.94,0,0,1-47,47H309.78L330,299.89l-88.78-49.43H47a46.94,46.94,0,0,1-47-47V47A46.77,46.77,0,0,1,46.76,0Z"/><path class="cls-2" style="fill:#fff;" d="M28.69,42.8c2.86-3.89,7.16-5.94,12.9-6.35Q57.25,35.24,59.41,51.2,68.94,115.4,80.09,160l44.85-85.4q6.15-11.67,15.36-12.29c9-.61,14.54,5.12,16.8,17.2,5.12,27.24,11.67,50.38,19.45,70q8-78,27-112.64c3.07-5.73,7.57-8.6,13.51-9A17.8,17.8,0,0,1,230,32a16,16,0,0,1,6.35,11.67,17.79,17.79,0,0,1-2,9.83c-8,14.75-14.55,39.53-19.87,73.93-5.12,33.39-7,59.4-5.73,78a24.29,24.29,0,0,1-2.46,13.52c-2.46,4.51-6.15,7-10.86,7.37-5.32.41-10.85-2.05-16.17-7.57Q150.64,189.54,134,131.48q-20,39.32-29.49,59c-12.09,23.14-22.33,35-30.93,35.64C68,226.51,63.3,221.8,59.2,212Q43.54,171.72,25.41,56.52A17.44,17.44,0,0,1,28.69,42.8ZM468.81,75C461.43,62.05,450.58,54.27,436,51.2A53.72,53.72,0,0,0,425,50c-19.66,0-35.63,10.24-48.13,30.72a108.52,108.52,0,0,0-16,57.75q0,23.66,9.83,40.55c7.37,12.91,18.23,20.69,32.77,23.76A53.64,53.64,0,0,0,414.54,204c19.86,0,35.83-10.24,48.12-30.72a109.73,109.73,0,0,0,16-58C478.84,99.33,475.36,86,468.81,75ZM443,131.69c-2.86,13.51-8,23.55-15.56,30.31-5.94,5.32-11.47,7.57-16.59,6.55-4.92-1-9-5.32-12.08-13.31a52,52,0,0,1-3.69-18.64,71.48,71.48,0,0,1,1.43-14.95,66.29,66.29,0,0,1,10.86-24.37c6.76-10,13.92-14.13,21.3-12.7,4.91,1,9,5.33,12.08,13.31a52,52,0,0,1,3.69,18.64A71.47,71.47,0,0,1,443,131.69ZM340.6,75c-7.37-12.91-18.43-20.69-32.76-23.76A53.79,53.79,0,0,0,296.78,50c-19.66,0-35.64,10.24-48.13,30.72a108.52,108.52,0,0,0-16,57.75q0,23.66,9.83,40.55c7.37,12.91,18.22,20.69,32.76,23.76A53.72,53.72,0,0,0,286.33,204c19.87,0,35.84-10.24,48.13-30.72a109.72,109.72,0,0,0,16-58C350.43,99.33,347.16,86,340.6,75Zm-26,56.73c-2.86,13.51-8,23.55-15.56,30.31-5.94,5.32-11.47,7.57-16.59,6.55-4.91-1-9-5.32-12.08-13.31a52,52,0,0,1-3.69-18.64,71.48,71.48,0,0,1,1.43-14.95A66.29,66.29,0,0,1,279,97.28c6.76-10,13.92-14.13,21.3-12.7,4.91,1,9,5.33,12.08,13.31A52,52,0,0,1,316,116.53a60.45,60.45,0,0,1-1.44,15.16Z"/></svg>';
            }

            echo '<tr id="' . esc_attr($row['type'] . '_' . $row['id']) . '" data-index="' . $actual_index . '" style="vertical-align:middle;">';
            echo '<td data-id="' . esc_attr($row['id']) . '" style="padding:10px;border-bottom:1px solid #ddd;">' . esc_html($row['id']) . '</td>';

            // Type cell
            $role_badges = $row['role_badges'] ?? [];
            echo '<td data-type="' . esc_attr($row['type']) . '" style="padding:10px;border-bottom:1px solid #ddd;white-space:nowrap;color:#7f54b3;">';
            echo $woo_icon . esc_html($label);
            if (empty($woo_icon) && !empty($role_badges)) {
                foreach ($role_badges as $badge) {
                    $bg = esc_attr($badge['color'] ?? '#64748b');
                    echo ' <span style="display:inline-flex;align-items:center;gap:3px;background:' . $bg . ';color:#fff;font-size:10px;font-weight:600;padding:1px 6px;border-radius:20px;vertical-align:middle;white-space:nowrap;">' . ($badge['icon'] ?? '') . ' ' . esc_html($badge['label'] ?? '') . '</span>';
                }
            }
            echo '</td>';

            // URL cell
            echo '<td data-url="' . esc_attr($row['url']) . '" style="padding:10px;border-bottom:1px solid #ddd;overflow:hidden;text-overflow:ellipsis;max-width:500px;">';
            if ($is_grouped) {
                echo '<span class="badge" style="background-color:#fff3cd;color:#664d03;font-size:13px;font-weight:600;padding:5px 10px;"><i class="fa-solid fa-layer-group me-1"></i>' . esc_html($count) . ' ' . esc_html($label) . '</span>';
            } else {
                echo '<span style="white-space:nowrap;">' . esc_html($row['url_short']) . ' <a href="' . esc_attr($row['url']) . '" target="_blank"><i class="fa-solid fa-link"></i></a></span>';
            }
            echo '</td>';

            // CACHE column — css_page (CSS) + plugin_js (JS) fiziksel dosya durumu
            $css_page_val  = str_replace('\\', '/', $meta['css_page'] ?? '');
            $plugin_js_val = str_replace('\\', '/', $meta['plugin_js'] ?? '');
            $static_base   = rtrim( str_replace('\\','/',STATIC_PATH), '/' ) . '/';

            $css_abs    = $css_page_val ? $static_base . ltrim($css_page_val, '/') : '';
            $css_exists = $css_abs && file_exists($css_abs);
            $css_meta   = ! empty($meta['css_page']);

            $js_abs    = $plugin_js_val ? $static_base . ltrim($plugin_js_val, '/') : '';
            $js_exists = $js_abs && file_exists($js_abs);
            $js_meta   = ! empty($meta['plugin_js']);

            // CSS badge
            if ( $css_meta && ! $css_exists ) {
                $cache_css = '<span style="font-size:10px;font-weight:700;color:#ea580c;" title="Meta var ama dosya yok: ' . esc_attr($css_page_val) . '">CSS</span>';
            } elseif ( $css_exists ) {
                $cache_css = '<span style="font-size:10px;font-weight:700;color:#16a34a;" title="' . esc_attr($css_page_val) . '">CSS</span>';
            } else {
                $cache_css = '<span style="font-size:10px;color:#d1d5db;" title="CSS yok">CSS</span>';
            }
            // JS badge
            if ( $js_meta && ! $js_exists ) {
                $cache_js = '<span style="font-size:10px;font-weight:700;color:#ea580c;" title="Meta var ama dosya yok: ' . esc_attr($plugin_js_val) . '">JS</span>';
            } elseif ( $js_exists ) {
                $cache_js = '<span style="font-size:10px;font-weight:700;color:#16a34a;" title="' . esc_attr($plugin_js_val) . '">JS</span>';
            } else {
                $cache_js = '<span style="font-size:10px;color:#d1d5db;" title="JS yok">JS</span>';
            }
            echo '<td class="pae-col-cssfile" style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:65px;white-space:nowrap;">'
                . $cache_css . ' <span style="color:#e2e8f0;">/</span> ' . $cache_js
                . '</td>';

            // CRITICAL column — css_critical fiziksel dosya durumu
            $css_critical_val  = str_replace('\\', '/', $meta['css_critical'] ?? '');
            $abs_critical      = $css_critical_val ? $static_base . ltrim($css_critical_val, '/') : '';
            $critical_exists   = $abs_critical && file_exists($abs_critical);
            $critical_title    = $css_critical_val ? 'critical=' . esc_attr($css_critical_val) . ' | exists=' . ($critical_exists?'YES':'NO') : 'Critical CSS yok';
            if ( $css_critical_val && ! $critical_exists ) {
                // meta var ama dosya yok — turuncu X
                $critical_icon = '<span style="color:#ea580c;font-size:13px;" title="' . $critical_title . '">&#8856;</span>';
            } elseif ( $critical_exists ) {
                // dosya var — yeşil tik
                $critical_icon = '<span style="color:#16a34a;font-size:13px;" title="' . $critical_title . '">&#10003;</span>';
            } else {
                // hiç yok — gri boş
                $critical_icon = '<span style="color:#d1d5db;font-size:12px;" title="' . $critical_title . '">&#8709;</span>';
            }
            echo '<td class="pae-col-critical" style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:65px;">' . $critical_icon . '</td>';

            // LCP column — desktop + mobile LCP verisi var mı
            $lcp_data     = is_array($meta['lcp'] ?? null) ? $meta['lcp'] : [];
            $lcp_desktop  = ! empty( $lcp_data['desktop']['type'] );
            $lcp_mobile   = ! empty( $lcp_data['mobile']['type'] );

            // Tooltip: type + dosya adı + id varsa
            $d = $lcp_data['desktop'] ?? [];
            $m = $lcp_data['mobile']  ?? [];
            $lcp_d_tooltip = $lcp_desktop
                ? 'Desktop: ' . strtoupper( $d['type'] ?? '' )
                  . ( ! empty($d['id'])  ? ' · id=' . esc_attr($d['id'])  : '' )
                  . ( ! empty($d['url']) ? ' · ' . esc_attr( basename( parse_url($d['url'], PHP_URL_PATH) ?: '' ) ) : '' )
                : 'Desktop LCP yok';
            $lcp_m_tooltip = $lcp_mobile
                ? 'Mobile: ' . strtoupper( $m['type'] ?? '' )
                  . ( ! empty($m['id'])  ? ' · id=' . esc_attr($m['id'])  : '' )
                  . ( ! empty($m['url']) ? ' · ' . esc_attr( basename( parse_url($m['url'], PHP_URL_PATH) ?: '' ) ) : '' )
                : 'Mobile LCP yok';
            // Desktop SVG (monitor)
            $svg_desktop_on  = '<span title="' . $lcp_d_tooltip . '" style="cursor:help;display:inline-flex">'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="14" height="14" style="vertical-align:middle;fill:#16a34a;"><path d="M2 3a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h6l-1 2H6a1 1 0 0 0 0 2h8a1 1 0 0 0 0-2h-1l-1-2h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H2zm1 2h14v7H3V5z"/></svg>'
                . '</span>';
            $svg_desktop_off = '<span title="' . $lcp_d_tooltip . '" style="cursor:help;display:inline-flex">'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="14" height="14" style="vertical-align:middle;fill:#d1d5db;"><path d="M2 3a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h6l-1 2H6a1 1 0 0 0 0 2h8a1 1 0 0 0 0-2h-1l-1-2h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H2zm1 2h14v7H3V5z"/></svg>'
                . '</span>';
            // Mobile SVG (telefon)
            $svg_mobile_on   = '<span title="' . $lcp_m_tooltip . '" style="cursor:help;display:inline-flex">'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="12" height="12" style="vertical-align:middle;fill:#16a34a;"><path d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H6zm4 13a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
                . '</span>';
            $svg_mobile_off  = '<span title="' . $lcp_m_tooltip . '" style="cursor:help;display:inline-flex">'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="12" height="12" style="vertical-align:middle;fill:#d1d5db;"><path d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H6zm4 13a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
                . '</span>';
            echo '<td class="pae-col-lcp" style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:55px;white-space:nowrap;">';
            echo ( $lcp_desktop ? $svg_desktop_on : $svg_desktop_off );
            echo '<span style="color:#e2e8f0;font-size:10px;margin:0 2px;">|</span>';
            echo ( $lcp_mobile ? $svg_mobile_on : $svg_mobile_off );
            echo '</td>';

            // INLINE column — css ikon + js ikon yan yana
            $inline_css_icon = $has_css
                ? '<span style="color:#16a34a;font-size:11px;font-weight:700;" title="Inline CSS var">CSS</span>'
                : '<span style="color:#d1d5db;font-size:11px;" title="Inline CSS yok">CSS</span>';
            $inline_js_icon  = $has_js
                ? '<span style="color:#16a34a;font-size:11px;font-weight:700;" title="Inline JS var">JS</span>'
                : '<span style="color:#d1d5db;font-size:11px;" title="Inline JS yok">JS</span>';
            echo '<td class="pae-col-inline" style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:60px;white-space:nowrap;">'
                . $inline_css_icon . ' <span style="color:#e2e8f0;">/</span> ' . $inline_js_icon
                . '</td>';

            // Plugins column
            $plugins_html = ! empty( $plugins_list )
                ? '<span style="font-size:10px;color:#6b7280;">' . esc_html( implode( ', ', $plugins_list ) ) . '</span>'
                : '<span style="color:#d1d5db;font-size:12px;">&#8709;</span>';
            echo '<td class="pae-col-plugins" style="padding:10px;border-bottom:1px solid #ddd;font-size:10px;color:#6b7280;max-width:200px;overflow:hidden;text-overflow:ellipsis;">' . $plugins_html . '</td>';

            // Dual Fetch column
            echo '<td class="pae-dual-col" style="padding:10px;border-bottom:1px solid #ddd;">';
            if ($is_forced) {
                echo '<span class="pae-dual-forced" title="Zorunlu">🔥</span>';
            } else {
                echo '<input type="checkbox" class="pae-dual-toggle" data-id="' . esc_attr($row['id']) . '" ' . ($is_dual_on ? 'checked' : '') . ' title="Dual fetch">';
            }
            echo '</td>';

            echo '<td class="actions" style="width:80px;padding:10px;border-bottom:1px solid #ddd;"><a href="#" class="btn-page-assets-single btn btn-success btn-sm">Fetch</a></td>';
            echo '</tr>';
        };

        // ── Ana tablo ─────────────────────────────────────────────────────────
        if ($rows) {
            echo '<table class="table-page-assets table table-sm table-hover table-striped" style="width:100%;border-collapse:collapse;background:#fff;">';
            echo '<thead><tr style="background:#f2f2f2;text-align:left;">';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">ID / Key</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">Type</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">URL</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:55px;" title="Purified CSS cache dosyası">CACHE</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:65px;" title="Critical CSS dosyası var mı">CRITICAL</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:55px;" title="LCP verisi: desktop | mobile">LCP</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:60px;" title="Inline CSS / Inline JS">INLINE</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;max-width:200px;">PLUGINS</th>';
            echo '<th class="pae-dual-col" style="padding:10px;border-bottom:1px solid #ddd;" title="Dual Fetch">Dual Fetch</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">Actions</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $i => $row) {
                $render_row($row, $i);
            }
            echo '</tbody></table>';
        }

        // ── WooCommerce tablosu ───────────────────────────────────────────────
        if (!empty($woo_pages) && defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE) {
            $woo_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="vertical-align:middle;margin-right:8px;fill:#7f54b3;"><path d="M2.3 5.3c-.5.6-.8 1.5-.8 2.6v8.2c0 1.1.3 2 .8 2.6.5.6 1.3.9 2.2.9h15c.9 0 1.7-.3 2.2-.9.5-.6.8-1.5.8-2.6V7.9c0-1.1-.3-2-.8-2.6-.5-.6-1.3-.9-2.2-.9h-15c-.9 0-1.7.3-2.2.9zm3.2 2.1c.4-.5.9-.7 1.5-.7s1.1.2 1.4.6c.3.4.4 1 .3 1.8-.2 1.5-.5 2.7-1 3.5-.5.8-1 1.2-1.6 1.2-.4 0-.7-.2-.9-.5-.2-.4-.3-.9-.2-1.5l.6-3c.1-.6.2-1 .4-1.4h-.5zm5.4 0c.4-.5.9-.7 1.5-.7s1.1.2 1.4.6c.3.4.4 1 .3 1.8-.2 1.5-.5 2.7-1 3.5-.5.8-1 1.2-1.6 1.2-.4 0-.7-.2-.9-.5-.2-.4-.3-.9-.2-1.5l.6-3c.1-.6.2-1 .4-1.4h-.5zm5 .5c.2-.4.5-.6.9-.6.3 0 .5.1.7.4.1.3.2.6.1 1l-.3 1.5c-.1.5-.1.8 0 1 .1.2.2.3.4.3.3 0 .6-.2.9-.7.3-.5.5-1 .6-1.7.1-.9 0-1.6-.3-2.1-.3-.5-.8-.7-1.4-.7-.8 0-1.5.3-2.1 1-.6.7-1 1.6-1.1 2.7-.1.8 0 1.4.3 1.9.3.5.8.7 1.4.7.5 0 1-.1 1.4-.4l-.2.8c-.4.2-.9.4-1.4.4-.9 0-1.5-.3-2-.9-.4-.6-.6-1.4-.5-2.5.1-1.2.5-2.2 1.1-3z"/></svg>';

            echo '<div class="mt-4">';
            echo '<h4 class="mb-3" style="color:#7f54b3;border-bottom:2px solid #7f54b3;padding-bottom:8px;">' . $woo_svg . 'WooCommerce Pages</h4>';
            echo '<table class="table-page-assets-woo table table-sm table-hover table-striped" style="width:100%;border-collapse:collapse;background:#fff;">';
            echo '<thead><tr style="background:#f8f4ff;text-align:left;">';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">ID / Key</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">Type</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">URL</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:55px;">CACHE</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:65px;">CRITICAL</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:55px;" title="LCP verisi: desktop | mobile">LCP</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;text-align:center;width:60px;">INLINE</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;max-width:200px;">PLUGINS</th>';
            echo '<th class="pae-dual-col" style="padding:10px;border-bottom:1px solid #ddd;" title="Dual Fetch">Dual Fetch</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">Actions</th>';
            echo '</tr></thead><tbody>';
            echo '</tr></thead><tbody>';
            $woo_start = count($rows);
            foreach ($woo_pages as $i => $row) {
                $render_row($row, $woo_start + $i);
            }
            echo '</tbody></table>';
            echo '</div>';
        }

        if (!$rows && empty($woo_pages)) {
            echo '<p>No data found.</p>';
        }

        echo '</div>'; // .bg-white
        echo '</div>'; // .pae-wrap
        echo '</div>'; // .wrap
        ?>
        <script type="text/javascript">
            var urls        = <?php echo json_encode(array_values(array_merge($rows, $woo_pages))); ?>;
            var paeNonce    = '<?php echo wp_create_nonce('page_assets_nonce'); ?>';
            var paeClearNonce = '<?php echo esc_js($nonce_clear); ?>';
            var urlCounts   = urls.map(function(u){ return (u.real_count && u.real_count > 1) ? parseInt(u.real_count, 10) : ( (u.count && u.count > 1) ? parseInt(u.count, 10) : 1 ); });
            var realTotal   = <?php echo (int) $real_total; ?>;
            var fetchedTotal = 0;
            var paeAborted   = false;
            var currentXhr   = null;

            jQuery(function($) {

                // ── Dual fetch toggle ─────────────────────────────────────────
                $(".pae-dual-toggle").on("change", function(){
                    var $el = $(this), id = $el.data("id"), enabled = $el.is(":checked");
                    $.post(ajaxurl, { action: "pae_toggle_dual_fetch", _ajax_nonce: paeClearNonce, id: id, enable: enabled ? 1 : 0 });
                });

                // ── Single fetch ──────────────────────────────────────────────
                $(".btn-page-assets-single").on("click", function(e){
                    e.preventDefault();
                    var $row = $(this).closest("tr");
                    var idx  = parseInt($row.attr("data-index"), 10) || 0;
                    $(this).addClass("disabled");
                    page_assets_update(idx, true);
                });

                // ── Mass update ───────────────────────────────────────────────
                $(".btn-page-assets-update").on("click", function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    // Stop modunda — abort yap
                    if ($btn.hasClass("pae-is-running")) {
                        paeAborted = true;
                        if (currentXhr) { currentXhr.abort(); currentXhr = null; }
                        paeFinish(true);
                        return;
                    }
                    // Start
                    paeAborted = false;
                    $btn.addClass("pae-is-running")
                        .html("&#9632; Stop")
                        .css({"background":"#dc2626","border-color":"#dc2626"});
                    $(".btn-page-assets-single").addClass("disabled");
                    $("#pae-clear-toggle").prop("disabled", true).css("opacity","0.5");
                    fetchedTotal = 0;
                    $("#pae-progress-wrap").css("display","block");
                    $("#pae-progress-bar").css("width","0%");
                    $("#pae-progress-label").text("Hazırlanıyor...");
                    page_assets_update(0, false);
                });

                // ── Clear cache dropdown ──────────────────────────────────────
                $("#pae-clear-toggle").on("click", function(e){
                    e.stopPropagation();
                    $("#pae-clear-panel").toggleClass("open");
                });
                $(document).on("click", function(e){
                    if (!$(e.target).closest(".pae-clear-dropdown").length) {
                        $("#pae-clear-panel").removeClass("open");
                    }
                });

                // ── Orphan Clean ──────────────────────────────────────────────
                // Sayfa yüklenince orphan sayısını çek
                $.ajax({
                    url: ajaxurl, type: "post", dataType: "json",
                    data: { action: "pae_get_orphan_count", _ajax_nonce: paeClearNonce },
                    success: function(res) {
                        if (res.success && res.data) {
                            var total = res.data.total;
                            var badge = $("#pae-orphan-badge");
                            badge.text("CSS:" + res.data.css + " JS:" + res.data.js);
                            if (total > 0) {
                                badge.css("background", "#f59e0b");
                                $("#pae-clean-orphans").css("border-color", "#f59e0b");
                            } else {
                                badge.css("background", "#10b981").text("✓ Temiz");
                                $("#pae-clean-orphans").css({"border-color":"#6ee7b7","color":"#065f46"});
                            }
                        }
                    }
                });

                $("#pae-clean-orphans").on("click", function(){
                    var total = parseInt($("#pae-orphan-badge").text()) || 0;
                    var $btn  = $(this).prop("disabled", true).css("opacity","0.6");
                    $("#pae-orphan-badge").text("...");
                    $.ajax({
                        url: ajaxurl, type: "post", dataType: "json",
                        data: { action: "pae_clean_orphans", _ajax_nonce: paeClearNonce },
                        success: function(res) {
                            $btn.prop("disabled", false).css("opacity","1");
                            if (res.success) {
                                $("#pae-orphan-badge").text("✓ " + res.data.deleted + " silindi").css("background","#10b981");
                                $btn.css({"border-color":"#6ee7b7","color":"#065f46"});
                                var $msg = $('<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:8px 14px;font-size:12px;color:#15803d;margin-bottom:10px;">&#9851; ' + res.data.message + '</div>');
                                $(".pae-wrap").prepend($msg);
                                setTimeout(function(){ $msg.fadeOut(400, function(){ $(this).remove(); }); }, 4000);
                            } else {
                                $("#pae-orphan-badge").text("!");
                                alert("Hata: " + (res.data || "Bilinmeyen hata"));
                            }
                        },
                        error: function(){ $btn.prop("disabled",false).css("opacity","1"); alert("AJAX hatası"); }
                    });
                });

                // ── Retry butonu ─────────────────────────────────────────────
                jQuery(document).on("click", ".pae-retry-btn", function() {
                    var idx = parseInt(jQuery(this).data("index"), 10);
                    retryCount[idx] = 0; // Manuel retry'da sayacı sıfırla
                    page_assets_update(idx, true);
                });

                // ── Clear cache confirm ───────────────────────────────────────
                $("#pae-clear-confirm").on("click", function(){
                    var resetMeta = $("#pae-reset-meta").is(":checked") ? 1 : 0;
                    var $btn = $(this).addClass("disabled").text("Temizleniyor...");
                    $.ajax({
                        url: ajaxurl, type: "post", dataType: "json",
                        data: { action: "pae_clear_cache", _ajax_nonce: paeClearNonce, reset_meta: resetMeta },
                        success: function(res) {
                            if (res.success) {
                                $("#pae-clear-panel").removeClass("open");
                                $btn.removeClass("disabled").text("Temizle");
                                // Sayfayı reload et — PHP file_exists kontrolü doğru durumu gösterir
                                var $msg = $('<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:8px 14px;font-size:12px;color:#15803d;margin-bottom:10px;">&#10003; ' + (res.data.message || "Cache temizlendi") + ' — Sayfa yenileniyor...</div>');
                                $(".pae-wrap").prepend($msg);
                                setTimeout(function(){ location.reload(); }, 1200);
                                // Meta reset yapıldıysa asset kolonlarını temizle
                                if (resetMeta) {
                                    var $empty = '<span style="color:#d1d5db;font-size:12px;">&#8709;</span>';
                                    $(".pae-col-cssfile").html($empty);
                                    $(".pae-col-inline").html('<span style="color:#d1d5db;font-size:11px;">CSS</span> <span style="color:#e2e8f0;">/</span> <span style="color:#d1d5db;font-size:11px;">JS</span>');
                                    $(".pae-col-plugins").html($empty);
                                    $("tr[data-index]").find("td").removeClass("bg-success text-white bg-danger bg-opacity-10").css("background","");
                                }
                            } else {
                                alert("Hata: " + (res.data || "Bilinmeyen hata"));
                                $btn.removeClass("disabled").text("Temizle");
                            }
                        },
                        error: function() { alert("AJAX hatası"); $btn.removeClass("disabled").text("Temizle"); }
                    });
                });
            });

            var retryCount = {}; // her index için retry sayacı
            var MAX_RETRY  = 3;

            function page_assets_update(i, single) {
                if (!single && paeAborted) return;
                var $row = jQuery(".table-page-assets, .table-page-assets-woo").find("tr[data-index='" + i + "']");
                $row.find(".actions").empty().addClass("loading loading-xs position-relative");

                // Retry sayacını başlat
                if (!retryCount[i]) retryCount[i] = 0;

                currentXhr = jQuery.ajax({
                    url: ajaxurl, type: "post", dataType: "json",
                    data: {
                        action: "page_assets_update",
                        _ajax_nonce: paeNonce,
                        data: {
                            id:        urls[i].id,
                            type:      urls[i].type,
                            post_type: urls[i].post_type || "",
                            context:   urls[i].context  || urls[i].type,
                            url:       urls[i].url,
                            index:     (i + 1),
                            total:     urls.length,
                            mass:      single ? false : true,
                            grouped:   true
                        }
                    },
                    success: function(res) {
                        if (res.error) {
                            retryCount[i]++;
                            if (!single && retryCount[i] < MAX_RETRY) {
                                // Otomatik retry — kısa bekleme ile
                                $row.find(".actions").removeClass("loading loading-xs").html(
                                    '<span style="font-size:11px;color:#d97706">↻ retry ' + retryCount[i] + '/' + MAX_RETRY + '</span>'
                                );
                                $row.find("td").removeClass("bg-success text-white").css("background","#fffbeb");
                                setTimeout(function() {
                                    page_assets_update(i, false);
                                }, 1500);
                                return;
                            }
                            // MAX_RETRY doldu veya single — kırmızı FAILED
                            $row.find("td").removeClass("bg-success text-white").addClass("bg-danger bg-opacity-10").css("background","");
                            var retryLabel = retryCount[i] >= MAX_RETRY ? ' (' + MAX_RETRY + ' attempts)' : '';
                            $row.find(".actions").removeClass("loading loading-xs").html(
                                '<button class="btn btn-danger btn-sm pae-retry-btn" data-index="' + i + '" style="font-size:12px;">✗ FAILED' + retryLabel + ' ↻ Retry</button>'
                            );
                            if (!single) {
                                fetchedTotal += urlCounts[i];
                                if (i < urls.length - 1) {
                                    page_assets_update(i + 1, false);
                                } else {
                                    paeFinish(false);
                                }
                            }
                            return;
                        }
                        // Başarılı — retry sayacını sıfırla
                        retryCount[i] = 0;
                        var msg = "OK";
                        if (res.grouped_count > 0) msg = "OK (" + res.grouped_count + " applied)";
                        $row.find("td").removeClass("bg-danger bg-opacity-10").css("background","").addClass("bg-success text-white");
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong>" + msg + "</strong>");

                        // Asset kolonlarını güncelle — bg-success zaten eklendi, isGreen=true
                        if (res.data) {
                            var d = res.data;
                            var hasCss     = d.css && (Array.isArray(d.css) ? d.css.length > 0 : !!d.css);
                            var hasCssPage = !!d.css_page;
                            var hasJs      = d.js && (Array.isArray(d.js) ? d.js.length > 0 : !!d.js);
                            var plugins    = [];
                            if (d.plugins && Array.isArray(d.plugins)) {
                                d.plugins.forEach(function(p) {
                                    if (typeof p === 'string') {
                                        var name = p.replace(/.*\//, '').replace(/\.css$/, '');
                                        plugins.push(name);
                                    }
                                });
                            }
                            // Row artık kesinlikle yeşil — sabit beyaz renkler kullan
                            var okColor    = '#ffffff';
                            var emptyColor = 'rgba(255,255,255,0.35)';
                            var pluginColor= 'rgba(255,255,255,0.85)';
                            var divColor   = 'rgba(255,255,255,0.3)';

                            // CACHE kolonu — CSS / JS badge
                            var cssExists = !!d.css_page;
                            var jsExists  = !!(d.plugin_js);
                            var cssBadge, jsBadge;
                            if (cssExists) {
                                cssBadge = '<span style="color:'+okColor+';font-size:10px;font-weight:700;" title="' + (d.css_page||'') + '">CSS</span>';
                            } else {
                                cssBadge = '<span style="color:'+emptyColor+';font-size:10px;" title="CSS yok">CSS</span>';
                            }
                            if (jsExists) {
                                jsBadge = '<span style="color:'+okColor+';font-size:10px;font-weight:700;" title="' + (d.plugin_js||'') + '">JS</span>';
                            } else {
                                jsBadge = '<span style="color:'+emptyColor+';font-size:10px;" title="JS yok">JS</span>';
                            }
                            $row.find(".pae-col-cssfile").html(cssBadge + ' <span style="color:'+divColor+';">/</span> ' + jsBadge);

                            // INLINE kolonu
                            var inlineCss = hasCss
                                ? '<span style="color:'+okColor+';font-size:11px;font-weight:700;" title="Inline CSS var">CSS</span>'
                                : '<span style="color:'+emptyColor+';font-size:11px;" title="Inline CSS yok">CSS</span>';
                            var inlineJs  = hasJs
                                ? '<span style="color:'+okColor+';font-size:11px;font-weight:700;" title="Inline JS var">JS</span>'
                                : '<span style="color:'+emptyColor+';font-size:11px;" title="Inline JS yok">JS</span>';
                            $row.find(".pae-col-inline").html(inlineCss + ' <span style="color:'+divColor+';">/</span> ' + inlineJs);

                            $row.find(".pae-col-plugins").html(plugins.length > 0
                                ? '<span style="font-size:10px;color:'+pluginColor+';">' + plugins.join(', ') + '</span>'
                                : '<span style="color:'+emptyColor+';font-size:12px;">&#8709;</span>');
                        }
                        if (!single) {
                            fetchedTotal += urlCounts[i];
                            var percent = realTotal > 0 ? (fetchedTotal / realTotal) * 100 : ((i + 1) / urls.length * 100);
                            jQuery("#pae-progress-bar").css("width", Math.min(percent, 100) + "%");
                            jQuery("#pae-progress-label").text(fetchedTotal + " / " + realTotal + " (" + Math.round(percent) + "%)");
                            if (i < urls.length - 1) {
                                page_assets_update(i + 1, false);
                            } else {
                                paeFinish(false);
                                var $done = jQuery('<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:8px 14px;font-size:12px;color:#15803d;margin-bottom:10px;">All pages updated! (' + fetchedTotal + ' items)</div>');
                                jQuery(".pae-wrap").prepend($done);
                                setTimeout(function(){ $done.fadeOut(400, function(){ jQuery(this).remove(); }); }, 5000);
                            }
                        } else {
                            jQuery(".btn-page-assets-single").removeClass("disabled");
                        }
                    },
                    error: function(xhr, st, err) {
                        console.error("AJAX Error: " + st + " - " + err);
                        retryCount[i]++;
                        if (!single && retryCount[i] < MAX_RETRY) {
                            $row.find(".actions").removeClass("loading loading-xs").html(
                                '<span style="font-size:11px;color:#d97706">↻ retry ' + retryCount[i] + '/' + MAX_RETRY + '</span>'
                            );
                            setTimeout(function() { page_assets_update(i, false); }, 1500);
                            return;
                        }
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong class='text-danger'>ERR (" + retryCount[i] + " attempts)</strong>");
                        if (!single) {
                            if (i < urls.length - 1) { page_assets_update(i + 1, false); }
                            else { paeFinish(false); }
                        }
                    }
                });
            }

            function paeFinish(aborted) {
                currentXhr = null;
                jQuery("#pae-progress-wrap").css("display","none");
                jQuery("#pae-progress-bar").css("width","0%");
                jQuery("#pae-progress-label").text("Hazırlanıyor...");
                // Start Mass Update butonunu eski haline getir
                jQuery(".btn-page-assets-update")
                    .removeClass("pae-is-running disabled")
                    .html("&#9654; Start Mass Update")
                    .css({"background":"","border-color":""});
                jQuery(".btn-page-assets-single").removeClass("disabled");
                // Clear Cache'i tekrar aktif et
                jQuery("#pae-clear-toggle").prop("disabled", false).css("opacity","");
                if (aborted) {
                    var $msg = jQuery('<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:8px 14px;font-size:12px;color:#856404;margin-bottom:10px;">Process stopped.</div>');
                    jQuery(".pae-wrap").prepend($msg);
                    setTimeout(function(){ $msg.fadeOut(400, function(){ jQuery(this).remove(); }); }, 3000);
                }
            }
        </script>
        <?php
    }
}
