<?php

namespace SaltHareket\PluginManager\Admin;

use SaltHareket\PluginManager\PluginManager;

/**
 * PluginManagerAdmin
 *
 * Admin UI — sh-wrap / sh-toolbar layout (membership / asset-manager ile aynı yapı).
 *
 * @version 2.0.0
 */
class PluginManagerAdmin
{
    public static function register(): void
    {
        // add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 20 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'wp_ajax_pm_process_plugin', [ self::class, 'ajaxProcessPlugin' ] );
        add_action( 'wp_ajax_pm_bulk_action',    [ self::class, 'ajaxBulkAction' ] );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-plugin-manager' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '🔌 Plugin Manager',
            '🔌 Plugin Manager',
            'manage_options',
            'sh-plugin-manager',
            [ self::class, 'renderPage' ]
        );
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( $hook !== 'theme-settings_page_sh-plugin-manager' ) return;

        // sh-admin.css — tüm SH uygulamalarının ortak admin stili
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }

        wp_enqueue_script(
            'sh-plugin-manager',
            get_template_directory_uri() . '/vendor/salthareket/theme/src/js/plugin-manager.js',
            [ 'jquery' ],
            '2.0.0',
            true
        );
        wp_localize_script( 'sh-plugin-manager', 'pmAjax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pm_nonce' ),
        ] );
    }

    // ─── Render Ana Sayfa ─────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $plugins_data = self::buildPluginsData();
        $summary      = self::buildSummary( $plugins_data );
        $categories   = self::getCategories( $plugins_data );
        $active_filter= sanitize_key( $_GET['pmfilter'] ?? 'all' );

        // 3 grup — her biri alfabetik
        $g_active  = array_values( array_filter( $plugins_data, fn($p) => $p['installed'] && $p['active'] && !$p['update_available'] ) );
        $g_inactive= array_values( array_filter( $plugins_data, fn($p) => $p['installed'] && (!$p['active'] || $p['update_available']) ) );
        $g_missing = array_values( array_filter( $plugins_data, fn($p) => !$p['installed'] ) );
        usort( $g_active,   fn($a,$b) => strcmp($a['name'],$b['name']) );
        usort( $g_inactive, fn($a,$b) => strcmp($a['name'],$b['name']) );
        usort( $g_missing,  fn($a,$b) => strcmp($a['name'],$b['name']) );
        ?>
        <div class="sh-wrap" id="sh-pm-page">

        <!-- ─── STICKY TOOLBAR (badge'ler) ─────────────────────────────── -->
        <div class="sh-toolbar" style="position:static;top:auto;margin-bottom:0">
            <h1 style="display:inline-flex;align-items:center;gap:8px;margin:0">
                🔌 Plugin Manager
            </h1>
            <div class="sh-toolbar-right" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                <?php
                $badges = [
                    [ $summary['total'],         '#555',    'Toplam' ],
                    [ $summary['active'],        '#15803d', 'Aktif' ],
                    [ $summary['updates'],       '#b91c1c', 'Güncelleme' ],
                    [ $summary['not_installed'], '#b91c1c', 'Yüklü Değil' ],
                    [ $summary['inactive'],      '#6b7280', 'Pasif' ],
                ];
                foreach ( $badges as [$cnt, $bg, $lbl] ):
                ?>
                <span class="sh-badge" style="background:<?php echo $bg; ?>;color:#fff;font-size:11px;padding:4px 12px;border-radius:14px;font-weight:700"><?php echo $lbl; ?>: <?php echo $cnt; ?></span>
                <?php endforeach; ?>
            </div>
        </div><!-- .sh-toolbar -->

        <!-- Toast -->
        <div id="pm-toast" style="display:none;position:fixed;top:32px;right:20px;z-index:9999;min-width:260px;padding:10px 18px;border-radius:8px;font-weight:600;font-size:13px;box-shadow:0 4px 16px rgba(0,0,0,.2)"></div>

        <!-- ─── BEYAZ KUTU: filtre bar + tablo ──────────────────────────── -->
        <div class="sh-card" style="overflow:hidden;border-radius:0;margin-bottom:0">

            <!-- İç toolbar: selectler + bulk buton -->
            <div class="sh-toolbar" style="position:static;top:auto;border-radius:0;border-bottom:1px solid #e5e7eb;padding:10px 16px;margin-bottom:0">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;width:100%">
                    <!-- Kaynak -->
                    <select id="pm-filter-source" style="height:32px;line-height:32px;border-radius:6px;border:1px solid #ddd;padding:0 28px 0 10px;font-size:12px;appearance:auto;-webkit-appearance:auto">
                        <option value="all">Tüm Kaynaklar</option>
                        <option value="local">📦 Local</option>
                        <option value="wp">🌐 WP.org</option>
                    </select>

                    <!-- Kategori -->
                    <select id="pm-filter-cat" style="height:32px;line-height:32px;border-radius:6px;border:1px solid #ddd;padding:0 28px 0 10px;font-size:12px;appearance:auto;-webkit-appearance:auto">
                        <option value="all">Tüm Kategoriler</option>
                        <?php foreach ( $categories as $cat ): ?>
                        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html(ucfirst($cat)); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Bulk butonlar sağa -->
                    <div style="margin-left:auto;display:flex;gap:6px">
                        <?php if ( $summary['not_installed'] > 0 ): ?>
                        <button id="pm-install-missing" class="button button-primary" style="height:32px;font-size:12px;border-radius:6px">⬇ Eksikleri Yükle</button>
                        <?php endif; ?>
                        <?php if ( $summary['updates'] > 0 ): ?>
                        <button id="pm-update-all" class="button" style="height:32px;font-size:12px;border-radius:6px;background:#b91c1c;color:#fff;border-color:#b91c1c">⬆ Güncelle (<?php echo $summary['updates']; ?>)</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- iç toolbar -->

        <!-- ─── TABLO ────────────────────────────────────────────────────── -->
        <div class="sh-card" style="padding:0;overflow:hidden;margin-top:16px">
        <table id="pm-table" style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#1d2327;color:#fff">
                    <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600">Plugin</th>
                    <th style="padding:10px;text-align:left;font-size:12px;font-weight:600">Kaynak</th>
                    <th style="padding:10px;text-align:left;font-size:12px;font-weight:600">Kategoriler</th>
                    <th style="padding:10px;text-align:left;font-size:12px;font-weight:600">Versiyon</th>
                    <th style="padding:10px;text-align:left;font-size:12px;font-weight:600">Durum</th>
                    <th style="padding:10px;text-align:left;font-size:12px;font-weight:600">Aksiyon</th>
                </tr>
            </thead>
            <tbody>
                <?php self::renderGroup( 'active',       '✅ Yüklü & Aktif',             $g_active );   ?>
                <?php self::renderGroup( 'not_active',   '⏸ Yüklü / Pasif & Güncelleme', $g_inactive ); ?>
                <?php self::renderGroup( 'not_installed','❌ Yüklü Değil',               $g_missing );  ?>
            </tbody>
        </table>
        </div><!-- .sh-card -->

        </div><?php /* .sh-wrap */
        self::renderStyles();
    }

    // ─── Render helpers ───────────────────────────────────────────────────────

    private static function renderGroup( string $group, string $label, array $rows ): void
    {
        if ( empty( $rows ) ) return;
        ?>
        <tr class="pm-group-hdr" data-group="<?php echo esc_attr($group); ?>">
            <td colspan="6" style="padding:8px 16px;background:#f0f0f1;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#555;border-top:2px solid #e2e4e7">
                <?php echo $label; ?> <span style="font-weight:400;color:#888">(<?php echo count($rows); ?>)</span>
            </td>
        </tr>
        <?php
        foreach ( $rows as $p ) self::renderRow( $p, $group );
    }

    private static function renderRow( array $p, string $group ): void
    {
        $src = $p['is_local']
            ? '<span style="color:#b45309;font-size:12px" title="Local ZIP">📦 Local</span>'
            : '<span style="color:#2271b1;font-size:12px" title="WordPress.org">🌐 WP.org</span>';

        $ver = $p['installed']
            ? ( $p['update_available']
                ? '<span style="color:#b91c1c;font-size:12px">' . esc_html($p['installed_version']) . ' → <strong>' . esc_html($p['current_version']) . '</strong></span>'
                : '<span style="font-size:12px">' . esc_html($p['installed_version']) . '</span>'
            )
            : '<span style="color:#d1d5db;font-size:12px">—</span>';

        $status = match(true) {
            !$p['installed']        => '<span class="pm-badge" style="background:#fee2e2;color:#991b1b">Yüklü Değil</span>',
            $p['update_available']  => '<span class="pm-badge" style="background:#fef3c7;color:#92400e">Güncelleme Var</span>',
            $p['active']            => '<span class="pm-badge" style="background:#d1fae5;color:#065f46">Aktif</span>',
            default                 => '<span class="pm-badge" style="background:#f3f4f6;color:#6b7280">Pasif</span>',
        };

        $action = match(true) {
            !$p['installed']       => sprintf(
                '<button class="button button-primary pm-btn" data-slug="%s" data-local="%s" data-act="install" style="font-size:11px;height:28px;border-radius:5px">⬇ Yükle</button>',
                esc_attr($p['slug']), $p['is_local']?'1':'0'
            ),
            $p['update_available'] => sprintf(
                '<button class="button pm-btn" data-slug="%s" data-local="1" data-act="update" style="font-size:11px;height:28px;border-radius:5px;background:#d97706;color:#fff;border-color:#d97706">⬆ Güncelle</button>',
                esc_attr($p['slug'])
            ),
            !$p['active']          => sprintf(
                '<button class="button pm-btn" data-slug="%s" data-local="%s" data-act="activate" style="font-size:11px;height:28px;border-radius:5px;background:#15803d;color:#fff;border-color:#15803d">▶ Aktifleştir</button>',
                esc_attr($p['slug']), $p['is_local']?'1':'0'
            ),
            default                => sprintf(
                '<button class="button pm-btn" data-slug="%s" data-local="%s" data-act="deactivate" style="font-size:11px;height:28px;border-radius:5px;background:#dc2626;color:#fff;border-color:#dc2626">⏹ Deaktif</button>',
                esc_attr($p['slug']), $p['is_local']?'1':'0'
            ),
        };

        $status_key = !$p['installed'] ? 'not_installed'
            : ($p['update_available'] ? 'update'
            : ($p['active'] ? 'active' : 'not_active'));

        printf(
            '<tr class="pm-row" data-group="%s" data-status="%s" data-source="%s" data-cats="%s">
                <td style="padding:9px 16px;font-size:13px;font-weight:600;border-bottom:1px solid #f0f0f1">%s</td>
                <td style="padding:9px 10px;border-bottom:1px solid #f0f0f1">%s</td>
                <td style="padding:9px 10px;font-size:11px;color:#6b7280;border-bottom:1px solid #f0f0f1">%s</td>
                <td style="padding:9px 10px;border-bottom:1px solid #f0f0f1">%s</td>
                <td style="padding:9px 10px;border-bottom:1px solid #f0f0f1">%s</td>
                <td style="padding:9px 10px;border-bottom:1px solid #f0f0f1">%s</td>
            </tr>',
            esc_attr($group),
            esc_attr($status_key),
            esc_attr($p['is_local']?'local':'wp'),
            esc_attr(implode(',',$p['type'])),
            esc_html($p['name']),
            $src,
            esc_html(implode(', ',$p['type'])),
            $ver, $status, $action
        );
    }

    private static function renderStyles(): void
    {
        echo '<style>
        .pm-badge{display:inline-block;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700}
        .pm-btn:disabled{opacity:.5;cursor:not-allowed}
        #pm-table tbody .pm-row:hover{background:#f0f7ff}
        </style>';
    }

    // ─── AJAX ─────────────────────────────────────────────────────────────────

    public static function ajaxProcessPlugin(): void
    {
        ob_start();
        try {
            if ( !current_user_can('manage_options') ) wp_send_json_error(['message'=>'Yetki yok.']);
            if ( !check_ajax_referer('pm_nonce','_pm_nonce',false) ) wp_send_json_error(['message'=>'Güvenlik hatası.']);

            $slug  = sanitize_text_field($_POST['plugin_slug']??'');
            $act   = sanitize_key($_POST['action_type']??'');
            $local = ($_POST['local']??'0')==='1';

            if (!$slug||!$act) wp_send_json_error(['message'=>'Eksik parametre.']);

            $ok = match($act) {
                'install','update' => self::doInstall($slug,$local),
                'activate'         => (activate_plugin($slug)===null||!is_wp_error(activate_plugin($slug))),
                'deactivate'       => (deactivate_plugins($slug)===null),
                default            => false,
            };
            ob_end_clean();
            $ok ? wp_send_json_success(['message'=>ucfirst($act).' başarılı.'])
                : wp_send_json_error(['message'=>ucfirst($act).' başarısız.']);
        } catch (\Throwable $e) {
            ob_end_clean();
            wp_send_json_error(['message'=>$e->getMessage()]);
        }
    }

    public static function ajaxBulkAction(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error();
        if (!check_ajax_referer('pm_nonce','_pm_nonce',false)) wp_send_json_error();

        $type = sanitize_key($_POST['bulk_type']??'');
        $done = 0;
        if ($type==='install_missing') {
            foreach (\Data::get('plugins',[]) as $p) {
                if (!PluginManager::is_plugin_installed($p['name'])) { PluginManager::install_plugin_from_wp_repo($p['name']); $done++; }
            }
            foreach (\Data::get('plugins_local',[]) as $p) {
                if (!PluginManager::is_plugin_installed($p['name'])) { PluginManager::install_local_plugin($p); $done++; }
            }
        } elseif ($type==='update_all') {
            foreach (\Data::get('plugins_local',[]) as $p) {
                if (PluginManager::is_version_outdated($p['v'],$p['name'])) {
                    PluginManager::remove_plugin($p['file']);
                    PluginManager::install_local_plugin($p); $done++;
                }
            }
        }
        wp_send_json_success(['message'=>"{$done} plugin işlendi."]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private static function doInstall(string $slug, bool $local): bool
    {
        if ($local) {
            $info = current(array_filter(\Data::get('plugins_local',[]),fn($p)=>$p['name']===$slug));
            if (!$info) return false;
            PluginManager::remove_plugin($info['file']);
            return PluginManager::install_local_plugin($info);
        }
        return PluginManager::install_plugin_from_wp_repo($slug);
    }

    private static function buildPluginsData(): array
    {
        $data=[];
        foreach (\Data::get('plugins',[]) as $p) {
            $path=WP_PLUGIN_DIR.'/'.$p['name'];
            $pd=file_exists($path)?get_plugin_data($path,false,false):[];
            $iv=$pd['Version']??null;
            $data[]=['name'=>$pd['Name']??self::slugToName($p['name']),'slug'=>$p['name'],
                'type'=>$p['type'],'installed_version'=>$iv??'—','current_version'=>'',
                'is_local'=>false,'installed'=>$iv!==null,'active'=>is_plugin_active($p['name']),'update_available'=>false];
        }
        foreach (\Data::get('plugins_local',[]) as $p) {
            $path=WP_PLUGIN_DIR.'/'.$p['name'];
            $pd=file_exists($path)?get_plugin_data($path,false,false):[];
            $iv=$pd['Version']??null;
            $upd=$iv!==null&&version_compare($iv,$p['v'],'<');
            $data[]=['name'=>$pd['Name']??$p['file'],'slug'=>$p['name'],
                'type'=>$p['type'],'installed_version'=>$iv??'—','current_version'=>$p['v'],
                'is_local'=>true,'installed'=>$iv!==null,'active'=>is_plugin_active($p['name']),'update_available'=>$upd];
        }
        return $data;
    }

    private static function buildSummary(array $data): array
    {
        $s=['total'=>count($data),'active'=>0,'inactive'=>0,'not_installed'=>0,'updates'=>0];
        foreach ($data as $p) {
            if (!$p['installed'])         $s['not_installed']++;
            elseif($p['update_available'])$s['updates']++;
            elseif($p['active'])          $s['active']++;
            else                          $s['inactive']++;
        }
        return $s;
    }

    private static function getCategories(array $data): array
    {
        $cats=[];
        foreach ($data as $p) foreach ($p['type'] as $t) $cats[$t]=true;
        $keys=array_keys($cats);
        usort($keys,fn($a,$b)=>$a==='main'?-1:($b==='main'?1:strcmp($a,$b)));
        return $keys;
    }

    private static function slugToName(string $slug): string
    {
        return ucwords(str_replace(['-','_'],' ',explode('/',$slug)[0]));
    }
}
