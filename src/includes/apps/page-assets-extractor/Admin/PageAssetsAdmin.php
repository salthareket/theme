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
        add_submenu_page(
            'theme-settings',
            '🗂️ Page Assets',
            '🗂️ Page Assets',
            'manage_options',
            'page-assets-update',
            [$this, 'display_page_assets_table']
        );
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
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_archive_%_assets' OR option_name LIKE '%_dynamic_%_assets'");
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

        $data = $this->fetch($url, $id, $type);

        $grouped_count = 0;
        if ($grouped && $data && !empty($row['post_type']) && !empty($row['context'])) {
            $source_id = is_numeric($id) ? (int) $id : 0;
            if ($source_id > 0) {
                $grouped_count = $this->grouped_apply_assets($source_id, $row['post_type'], $row['context']);
            }
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
            $real_total += (isset($r['count']) && $r['count'] > 1) ? (int) $r['count'] : 1;
        }

        $message = $grand_total
            ? "JS &amp; CSS Extraction process completed with <strong>{$grand_total}" . ($real_total > $grand_total ? " (total: {$real_total})" : "") . " default-language pages.</strong>"
            : "Not found any pages to extract process.";

        $nonce_clear = wp_create_nonce('pae_clear_cache_nonce');

        // ── Styles ───────────────────────────────────────────────────────────
        echo '<style>
        .pae-sticky-toolbar {
            position: sticky; top: 32px; z-index: 999;
            background: #fff; border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 10px 16px 0 16px;
            margin: -16px -16px 16px -16px;
            display: flex; flex-direction: column; gap: 0;
        }
        .pae-toolbar-main { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding-bottom:10px; }
        .pae-toolbar-left  { display:flex; align-items:center; gap:10px; }
        .pae-toolbar-right { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .pae-toolbar-title { font-size:15px; font-weight:700; color:#1e293b; margin:0; }
        .pae-toolbar-count { font-size:12px; color:#64748b; background:#f1f5f9; padding:3px 8px; border-radius:20px; }
        .pae-progress-wrap { display:none; padding:8px 0 10px 0; border-top:1px solid #e2e8f0; }
        .pae-progress-wrap.active { display:block; }
        .pae-progress-label { font-size:12px; color:#475569; margin-bottom:4px; }
        body .notice:not(.pae-inline-notice), body .updated:not(.pae-inline-notice), body .error:not(.pae-inline-notice) { display:none !important; }
        .pae-clear-dropdown { position:relative; display:inline-block; }
        .pae-clear-panel {
            display:none; position:absolute; right:0; top:calc(100% + 4px);
            background:#fff; border:1px solid #e2e8f0; border-radius:8px;
            box-shadow:0 8px 24px rgba(0,0,0,0.12); padding:16px; min-width:260px; z-index:1000;
        }
        .pae-clear-panel.open { display:block; }
        .pae-clear-panel label { display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer; margin-bottom:12px; }
        /* Dual fetch column */
        .pae-dual-col { width:120px; text-align:center; }
        .pae-dual-forced { font-size:18px; cursor:default; }
        .pae-dual-toggle { width:16px; height:16px; cursor:pointer; }
        </style>';

        echo '<div class="wrap"><div class="pae-wrap">';

        // ── Sticky Toolbar ────────────────────────────────────────────────────
        echo '<div class="pae-sticky-toolbar">';
        echo '  <div class="pae-toolbar-main">';
        echo '    <div class="pae-toolbar-left">';
        echo '      <span class="pae-toolbar-title">🗂 Page Assets Extractor</span>';
        $count_label = esc_html($grand_total);
        if ($real_total > $grand_total) {
            $count_label .= ' <span style="color:#94a3b8;font-weight:400;">/ ' . esc_html($real_total) . ' total</span>';
        }
        echo '      <span class="pae-toolbar-count">' . $count_label . ' pages</span>';
        echo '    </div>';
        echo '    <div class="pae-toolbar-right">';
        echo '      <a href="#" class="btn-page-assets-update btn btn-success btn-sm px-3"><i class="fa-solid fa-play me-1"></i>Start Mass Update</a>';
        echo '      <div class="pae-clear-dropdown">';
        echo '        <button type="button" class="btn btn-outline-danger btn-sm px-3" id="pae-clear-toggle"><i class="fa-solid fa-trash-can me-1"></i>Clear Cache</button>';
        echo '        <div class="pae-clear-panel" id="pae-clear-panel">';
        echo '          <label><input type="checkbox" id="pae-reset-meta" value="1"> Meta\'yı da resetle <small class="text-muted d-block ms-4" style="font-size:11px;">CSS/JS path\'leri DB\'den temizler</small></label>';
        echo '          <button type="button" class="btn btn-danger btn-sm w-100" id="pae-clear-confirm"><i class="fa-solid fa-trash-can me-1"></i>Temizle</button>';
        echo '        </div>';
        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="pae-progress-wrap" id="pae-progress-wrap">';
        echo '    <div class="pae-progress-label" id="pae-progress-label">Hazırlanıyor...</div>';
        echo '    <div class="progress" role="progressbar" style="height:6px;">';
        echo '      <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="pae-progress-bar" style="width:0%"></div>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>'; // .pae-sticky-toolbar

        echo '<div class="bg-white rounded-3 p-3 shadow-sm">';
        echo '<div class="mb-3 text-muted" style="font-size:13px;">' . $message . '</div>';

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

            $woo_icon = '';
            if (!empty($row['icon']) && $row['icon'] === 'woo') {
                $woo_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:6px;fill:#7f54b3;"><path d="M2.3 5.3c-.5.6-.8 1.5-.8 2.6v8.2c0 1.1.3 2 .8 2.6.5.6 1.3.9 2.2.9h15c.9 0 1.7-.3 2.2-.9.5-.6.8-1.5.8-2.6V7.9c0-1.1-.3-2-.8-2.6-.5-.6-1.3-.9-2.2-.9h-15c-.9 0-1.7.3-2.2.9zm3.2 2.1c.4-.5.9-.7 1.5-.7s1.1.2 1.4.6c.3.4.4 1 .3 1.8-.2 1.5-.5 2.7-1 3.5-.5.8-1 1.2-1.6 1.2-.4 0-.7-.2-.9-.5-.2-.4-.3-.9-.2-1.5l.6-3c.1-.6.2-1 .4-1.4h-.5zm5.4 0c.4-.5.9-.7 1.5-.7s1.1.2 1.4.6c.3.4.4 1 .3 1.8-.2 1.5-.5 2.7-1 3.5-.5.8-1 1.2-1.6 1.2-.4 0-.7-.2-.9-.5-.2-.4-.3-.9-.2-1.5l.6-3c.1-.6.2-1 .4-1.4h-.5zm5 .5c.2-.4.5-.6.9-.6.3 0 .5.1.7.4.1.3.2.6.1 1l-.3 1.5c-.1.5-.1.8 0 1 .1.2.2.3.4.3.3 0 .6-.2.9-.7.3-.5.5-1 .6-1.7.1-.9 0-1.6-.3-2.1-.3-.5-.8-.7-1.4-.7-.8 0-1.5.3-2.1 1-.6.7-1 1.6-1.1 2.7-.1.8 0 1.4.3 1.9.3.5.8.7 1.4.7.5 0 1-.1 1.4-.4l-.2.8c-.4.2-.9.4-1.4.4-.9 0-1.5-.3-2-.9-.4-.6-.6-1.4-.5-2.5.1-1.2.5-2.2 1.1-3z"/></svg>';
            }

            echo '<tr id="' . esc_attr($row['type'] . '_' . $row['id']) . '" data-index="' . $actual_index . '" style="vertical-align:middle;">';
            echo '<td data-id="' . esc_attr($row['id']) . '" style="padding:10px;border-bottom:1px solid #ddd;">' . esc_html($row['id']) . '</td>';

            // Type cell — label + role badges inline
            $role_badges = $row['role_badges'] ?? [];
            echo '<td data-type="' . esc_attr($row['type']) . '" style="padding:10px;border-bottom:1px solid #ddd;white-space:nowrap;">';
            echo $woo_icon . esc_html($label);
            if (empty($woo_icon) && !empty($role_badges)) {
                foreach ($role_badges as $badge) {
                    $bg     = esc_attr($badge['color'] ?? '#64748b');
                    $blabel = esc_html($badge['label'] ?? '');
                    $bicon  = $badge['icon'] ?? '';
                    echo ' <span style="display:inline-flex;align-items:center;gap:3px;background:' . $bg . ';color:#fff;font-size:10px;font-weight:600;padding:1px 6px;border-radius:20px;vertical-align:middle;white-space:nowrap;">' . $bicon . ' ' . $blabel . '</span>';
                }
            }
            echo '</td>';

            // URL cell
            echo '<td data-url="' . esc_attr($row['url']) . '" style="padding:10px;border-bottom:1px solid #ddd;overflow:hidden;text-overflow:ellipsis;max-width:900px;">';
            if ($is_grouped) {
                echo '<span class="badge" style="background-color:#fff3cd;color:#664d03;font-size:13px;font-weight:600;padding:5px 10px;"><i class="fa-solid fa-layer-group me-1"></i>' . esc_html($count) . ' ' . esc_html($label) . '</span>';
            } else {
                echo '<span style="white-space:nowrap;">' . esc_html($row['url_short']) . ' <a href="' . esc_attr($row['url']) . '" target="_blank"><i class="fa-solid fa-link"></i></a></span>';
            }
            echo '</td>';

            // Dual Fetch column
            echo '<td class="pae-dual-col" style="padding:10px;border-bottom:1px solid #ddd;">';
            if ($is_forced) {
                echo '<span class="pae-dual-forced" title="Zorunlu — logged/unlogged her zaman fetch edilir">🔥</span>';
            } else {
                $chk = $is_dual_on ? 'checked' : '';
                echo '<input type="checkbox" class="pae-dual-toggle" data-id="' . esc_attr($row['id']) . '" ' . $chk . ' title="Dual fetch — logged + unlogged">';
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
            echo '<th class="pae-dual-col" style="padding:10px;border-bottom:1px solid #ddd;" title="Dual Fetch: Hem logged hem unlogged state için fetch yapılır">Dual Fetch</th>';
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
            echo '<th class="pae-dual-col" style="padding:10px;border-bottom:1px solid #ddd;" title="Dual Fetch: Hem logged hem unlogged state için fetch yapılır">Dual Fetch</th>';
            echo '<th style="padding:10px;border-bottom:1px solid #ddd;">Actions</th>';
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
            var urlCounts   = urls.map(function(u){ return (u.count && u.count > 1) ? parseInt(u.count, 10) : 1; });
            var realTotal   = <?php echo (int) $real_total; ?>;
            var fetchedTotal = 0;

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
                    $(this).addClass("disabled");
                    $(".btn-page-assets-single").addClass("disabled");
                    fetchedTotal = 0;
                    $("#pae-progress-wrap").addClass("active");
                    $("#pae-progress-label").text("Hazırlanıyor...");
                    $("#pae-progress-bar").css("width", "0%");
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
                                var $msg = $('<div class="alert alert-success alert-dismissible py-2 px-3 mb-0" style="font-size:13px;">✅ ' + (res.data.message || "Cache temizlendi") + ' <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>');
                                $(".pae-sticky-toolbar").after($msg);
                                setTimeout(function(){ $msg.fadeOut(400, function(){ $(this).remove(); }); }, 4000);
                            } else {
                                alert("Hata: " + (res.data || "Bilinmeyen hata"));
                                $btn.removeClass("disabled").text("Temizle");
                            }
                        },
                        error: function() { alert("AJAX hatası"); $btn.removeClass("disabled").text("Temizle"); }
                    });
                });
            });

            function page_assets_update(i, single) {
                var $row = jQuery(".table-page-assets, .table-page-assets-woo").find("tr[data-index='" + i + "']");
                $row.find(".actions").empty().addClass("loading loading-xs position-relative");
                jQuery.ajax({
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
                        var msg = "OK";
                        if (res.grouped_count > 0) msg = "OK (" + res.grouped_count + " applied)";
                        $row.find("td").addClass("bg-success text-white");
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong>" + msg + "</strong>");
                        if (!single) {
                            fetchedTotal += urlCounts[i];
                            var percent = realTotal > 0 ? (fetchedTotal / realTotal) * 100 : ((i + 1) / urls.length * 100);
                            jQuery("#pae-progress-bar").css("width", Math.min(percent, 100) + "%");
                            jQuery("#pae-progress-label").text(fetchedTotal + " / " + realTotal + " (" + Math.round(percent) + "%)");
                            if (i < urls.length - 1) {
                                page_assets_update(i + 1, false);
                            } else {
                                jQuery("#pae-progress-wrap").removeClass("active");
                                jQuery("#pae-progress-label").text("Hazırlanıyor...");
                                jQuery("#pae-progress-bar").css("width", "0%");
                                jQuery(".btn-page-assets-update, .btn-page-assets-single").removeClass("disabled");
                                var $done = jQuery('<div class="alert alert-success py-2 px-3 mb-0" style="font-size:13px;">✅ Tüm sayfalar güncellendi! (' + fetchedTotal + ' item)</div>');
                                jQuery(".pae-sticky-toolbar").after($done);
                                setTimeout(function(){ $done.fadeOut(400, function(){ jQuery(this).remove(); }); }, 5000);
                            }
                        } else {
                            jQuery(".btn-page-assets-single").removeClass("disabled");
                        }
                    },
                    error: function(xhr, st, err) {
                        console.error("AJAX Error: " + st + " - " + err);
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong class='text-danger'>ERR</strong>");
                        if (!single) {
                            if (i < urls.length - 1) { page_assets_update(i + 1, false); }
                            else { jQuery(".btn-page-assets-update, .btn-page-assets-single").removeClass("disabled"); }
                        }
                    }
                });
            }
        </script>
        <?php
    }
}
