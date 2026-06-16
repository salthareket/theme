<?php
namespace SaltHareket\Reactions\Admin;
use SaltHareket\Reactions\Reactions;
use SaltHareket\Reactions\ReactionsSettings;
use SaltHareket\Reactions\ReactionsAppSettings;

/**
 * ReactionsAdmin
 * Reactions admin sayfasi — Types, Generator, Analytics, Info tablari.
 *
 * @version 1.4.0
 * @changelog
 *   1.4.0 - 2026-06-16
 *     - Add: Enable Reactions toggle box — Types tab ustunde, ReactionsAppSettings ile entegre
 *     - Add: ajaxSaveAppToggle() — sh_reactions_save_app_toggle AJAX handler
 *   1.3.0 - 2026-05-08
 *     - Add: Analytics tab — ReactionsAnalytics::renderTab() entegrasyonu
 *     - Add: Info tab — extend metodlari, global Twig fonksiyonlari, copy butonlu kod ornekleri
 *     - Add: ajaxSaveButton() — edit modu (key ile guncelleme), duplicate check duzeltildi
 *     - Change: renderTypeCard() — Toggleable switch kaldirildi, Interaction Mode dropdown eklendi
 *     - Change: renderTypeCard() — Cumulative secilince Limit per user input gozukur
 *     - Change: ajaxSaveType() — toggle kaldirildi, mode + limit kaydediliyor
 *     - Remove: Placements tab — renderPlacementsTab, renderPlacementCard, savePlacements kaldirildi
 *     - Remove: buildPreviewButton() — kullanilmiyordu
 *     - Change: renderButtonCard() — Twig/PHP tab + Customize params checkbox ile yeni kod alani
 *     - Change: shGenAddCard() JS — yeni kod alani ile guncellendi
 *     - Change: shGenUpdateCard() JS — tum code elementlerini gunceller
 *   1.2.0 - 2026-05-07
 *     - Add: Generator tab — Button Generator, saved buttons listesi
 *     - Add: renderButtonCard() — active/passive toggle, edit form, twig kodu
 *   1.1.0 - 2026-05-07
 *     - Add: Placements tab (sonradan kaldirildi)
 *   1.0.0 - 2026-05-06 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Admin URL: /wp-admin/admin.php?page=salt-reactions
 * // Tablar: types | generator | analytics | info
 *
 * // Otomatik yuklenir — bootstrap.php'de hook'lar kayitli
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Types tab: reaction tiplerini tanimla
 *   // ?page=salt-reactions&tab=types
 *
 * @example
 *   // Generator tab: button olustur, Twig kodu uret
 *   // ?page=salt-reactions&tab=generator
 *
 * @example
 *   // Analytics tab: istatistikler, grafik, top content
 *   // ?page=salt-reactions&tab=analytics
 *
 * @example
 *   // Info tab: extend metodlari, Twig ornekleri, copy butonlari
 *   // ?page=salt-reactions&tab=info
 *
 * @example
 *   // AJAX: type kaydet
 *   // action: sh_reactions_save_type
 *
 * @package SaltHareket\Reactions\Admin
 */
class ReactionsAdmin {
    public static function register(): void
    {
        // Hook'lar bootstrap.php'de kayitli — bu metod geriye donuk uyumluluk icin tutulur
    }

    public static function enqueueAssets( string $hook ): void {
        if ( strpos( $hook, 'salt-reactions' ) === false ) return;

        // Chart.js — analytics tab icin
        add_action( 'admin_head', function() {
            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>' . "\n";
        }, 1 );

        // FontAwesome — icon preview icin (theme'in kendi icons.css'i)
        wp_enqueue_style( 'sh-fa-icons', get_template_directory_uri() . '/static/css/icons.css', [], '6.5.1' );

        // Shared CSS kit
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
        wp_enqueue_style( 'wp-color-picker' );

        // WP Media Library
        wp_enqueue_media();

        // Admin JS
        $js_path = __DIR__ . '/reactions-admin.js';
        $js_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/reactions/Admin/reactions-admin.js';
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script( 'sh-reactions-admin', $js_url, ['jquery','wp-color-picker','jquery-ui-sortable','media-editor'], filemtime( $js_path ), true );
            wp_localize_script( 'sh-reactions-admin', 'shReactionsAdmin', [
                'nonce'   => wp_create_nonce('sh_reactions_nonce'),
                'ajax'    => admin_url('admin-ajax.php'),
                'palette' => self::getThemePalette(),
            ]);
        }
    }
    public static function addMenuPage(): void {
        add_submenu_page('theme-settings','⚡ Reactions','⚡ Reactions','manage_options','salt-reactions',[self::class,'renderPage']);
    }
    public static function hideNotices(): void {
        if(($_GET['page']??'')!=='salt-reactions')return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }
    public static function saveTypes(): void {
        check_admin_referer('sh_reactions_save_types');
        if(!current_user_can('manage_options'))wp_die('Unauthorized');
        $raw=(array)($_POST['types']??[]);$types=[];
        foreach($raw as $key=>$cfg){$key=sanitize_key($key);if(!$key)continue;
            $types[$key]=['label'=>sanitize_text_field($cfg['label']??''),'label_on'=>sanitize_text_field($cfg['label_on']??''),'icon_off'=>sanitize_text_field($cfg['icon_off']??''),'icon_on'=>sanitize_text_field($cfg['icon_on']??''),'color'=>sanitize_hex_color($cfg['color']??'')?:'#2271b1','notify_event'=>sanitize_key($cfg['notify_event']??''),'toggle'=>!empty($cfg['toggle']),'exclusive'=>!empty($cfg['exclusive']),'enabled'=>!empty($cfg['enabled'])];}
        ReactionsSettings::saveTypes($types);
        wp_redirect(add_query_arg(['page'=>'salt-reactions','tab'=>'types','saved'=>'1'],admin_url('admin.php')));exit;
    }
    public static function ajaxSaveType(): void {
        check_ajax_referer('sh_reactions_nonce','nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
        $key = sanitize_key($_POST['key'] ?? '');
        if ( ! $key ) wp_send_json_error('Invalid key');
        $types = ReactionsSettings::getTypes();

        // Toggle only — sadece enabled durumunu degistir, tam save yapmaz
        if ( ! empty( $_POST['toggle_enabled'] ) ) {
            $current = isset( $types[$key]['enabled'] ) ? (bool) $types[$key]['enabled'] : true;
            $types[$key]['enabled'] = ! $current;
            ReactionsSettings::saveTypes($types);
            wp_send_json_success(['key' => $key, 'enabled' => ! $current]);
        }

        $types[$key] = [
            'label'        => sanitize_text_field($_POST['label']        ?? ''),
            'label_on'     => sanitize_text_field($_POST['label_on']     ?? ''),
            'icon_off'     => sanitize_text_field($_POST['icon_off']     ?? ''),
            'icon_on'      => sanitize_text_field($_POST['icon_on']      ?? ''),
            'color'        => sanitize_hex_color($_POST['color']         ?? '') ?: '#2271b1',
            'notify_event' => sanitize_key($_POST['notify_event']        ?? ''),
            'mode'         => in_array($_POST['mode'] ?? 'toggle', ['toggle','additive','cumulative']) ? $_POST['mode'] : 'toggle',
            'limit'        => (int) ($_POST['limit'] ?? 0),
            'enabled'      => ! empty($_POST['enabled']),
        ];
        ReactionsSettings::saveTypes($types);
        ob_start();
        self::renderTypeCard($key, $types[$key]);
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html, 'key' => $key]);
    }

    public static function ajaxDeleteType(): void {
        check_ajax_referer('sh_reactions_nonce','nonce');
        if(!current_user_can('manage_options'))wp_send_json_error('Unauthorized');
        $key = sanitize_key($_POST['key']??'');
        if(!$key)wp_send_json_error('Invalid key');
        $types = ReactionsSettings::getTypes();
        unset($types[$key]);
        ReactionsSettings::saveTypes($types);
        wp_send_json_success(['key'=>$key]);
    }

    public static function renderPage(): void {
        if(!current_user_can('manage_options'))wp_die('Unauthorized');
        $tab=sanitize_key($_GET['tab']??'types');
        $types=ReactionsSettings::getTypes();$styles=ReactionsSettings::getButtonStyles();
        $tc=count($types);
        $reactions_enabled = (bool) ReactionsAppSettings::getSetting('enable_reactions');
        $nonce = wp_create_nonce('sh_reactions_nonce');
        $ajax_url = esc_url(admin_url('admin-ajax.php'));
        echo '<div class="wrap sh-wrap" id="sh-reactions-page">';
        self::renderStyles();
        echo '<div class="sh-toolbar"><h1>&#9889; Reactions</h1>';
        echo '<span class="sh-badge sh-badge-blue">'.$tc.' type'.($tc!==1?'s':'').'</span>';
        echo '<div class="sh-toolbar-right">';
        foreach(['types'=>'Types','generator'=>'&#128736; Generator','analytics'=>'&#128200; Analytics','info'=>'Info'] as $t=>$l)
            echo '<a href="?page=salt-reactions&tab='.$t.'" class="sh-tab-btn '.($tab===$t?'active':'').'">'.$l.'</a>';
        echo '</div></div>';
        // ── Enable Reactions toggle box (Notifications admin style) ──────────
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
            <strong style="font-size:13px;color:#1d2327;">Enable Reactions</strong>
            <label class="sh-toggle">
                <input type="checkbox" id="sh-reactions-app-toggle"
                       <?php checked( $reactions_enabled ); ?>
                       onchange="shReactionsAppToggle(this.checked)">
                <span class="sh-toggle-slider"></span>
            </label>
            <span id="sh-reactions-app-label" style="font-size:12px;color:<?php echo $reactions_enabled ? '#00a32a' : '#9ca3af'; ?>">
                <?php echo $reactions_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
            <span id="sh-reactions-app-saving" style="display:none;font-size:12px;color:#9ca3af;margin-left:4px;">
                <span style="display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#2271b1;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:4px;"></span>Saving...
            </span>
        </div>
        <script>
        function shReactionsAppToggle(enabled) {
            var label   = document.getElementById('sh-reactions-app-label');
            var saving  = document.getElementById('sh-reactions-app-saving');
            var toggle  = document.getElementById('sh-reactions-app-toggle');
            if (saving)  saving.style.display  = 'inline-flex';
            if (label)   label.style.display   = 'none';
            var fd = new FormData();
            fd.append('action',  'sh_reactions_save_app_toggle');
            fd.append('nonce',   <?php echo wp_json_encode( $nonce ); ?>);
            fd.append('enabled', enabled ? '1' : '0');
            fetch(<?php echo wp_json_encode( $ajax_url ); ?>, { method:'POST', body:fd, credentials:'same-origin' })
                .then(r => r.json())
                .then(function(res) {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = 'inline';
                    if (res.success) {
                        label.textContent = enabled ? 'Enabled' : 'Disabled';
                        label.style.color = enabled ? '#00a32a' : '#9ca3af';
                        if (typeof window.shShowToast === 'function') window.shShowToast(enabled ? 'Reactions enabled' : 'Reactions disabled', 'success');
                    } else {
                        toggle.checked = !enabled;
                        label.textContent = !enabled ? 'Enabled' : 'Disabled';
                        label.style.color = !enabled ? '#00a32a' : '#9ca3af';
                        if (typeof window.shShowToast === 'function') window.shShowToast('Save failed', 'error');
                    }
                })
                .catch(function() {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = 'inline';
                    toggle.checked = !enabled;
                });
        }
        </script>
        <?php
        $titles=['types'=>['title'=>'Reaction Types','desc'=>'Define reaction types and their icons, colors, labels.'],'generator'=>['title'=>'Button Generator','desc'=>'Generate Twig code for reaction buttons. Saved buttons are tracked here.'],'analytics'=>['title'=>'Analytics','desc'=>'Reaction statistics, trends, top content and user activity.'],'info'=>['title'=>'Info & Usage','desc'=>'Twig helpers, PHP functions, system info.']];
        $cur=$titles[$tab]??$titles['types'];
        echo '<div class="sh-section-title"><h2>'.esc_html($cur['title']).'</h2><p>'.esc_html($cur['desc']).'</p></div>';
        if(isset($_GET['saved']))echo '<div class="sh-notice sh-notice-success sh-inline">&#10003; Saved.</div>';
        if($tab==='generator')self::renderGeneratorTab($types,$styles);
        elseif($tab==='analytics'){\SaltHareket\Reactions\Admin\ReactionsAnalytics::renderTab();}
        elseif($tab==='info')self::renderInfoTab();
        else self::renderTypesTab($types);
        echo '<div id="sh-toast"></div></div>';
        self::renderScripts($types,$styles);
    }

    public static function ajaxSaveAppToggle(): void {
        check_ajax_referer('sh_reactions_nonce', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
        $enabled = filter_var( $_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
        ReactionsAppSettings::save( ['enable_reactions' => $enabled] );
        wp_send_json_success( ['enabled' => $enabled] );
    }
    private static function renderTypesTab(array $types): void {
        $nonce = wp_create_nonce('sh_reactions_nonce');
        $ajax  = esc_url(admin_url('admin-ajax.php'));
        $tc    = count($types);
        ?>
        <div class="sh-layout">
        <div class="sh-main">

            <div class="sh-filter-bar">
                <span class="sh-count-label"><?php echo $tc; ?> type<?php echo $tc !== 1 ? 's' : ''; ?></span>
                <button type="button" class="sh-btn sh-btn-primary" id="sh-add-type-btn" style="margin-left:auto">+ Add Type</button>
            </div>

            <div id="sh-types-list">
            <?php foreach ($types as $key => $def) : ?>
                <?php self::renderTypeCard($key, $def); ?>
            <?php endforeach; ?>
            <?php if (empty($types)) : ?>
                <div class="sh-empty-box">
                    <p style="margin:0 0 4px;font-weight:500;color:#50575e">No reaction types yet</p>
                    <button type="button" class="sh-btn sh-btn-primary" id="sh-add-type-btn2">+ Add Type</button>
                </div>
            <?php endif; ?>
            </div>

        </div>
        <div class="sh-sidebar">
            <div class="sh-card">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Built-in Types</h3>
                <?php foreach (['like','follow','favorite','bookmark'] as $bt) : ?>
                    <div style="margin-bottom:6px;font-size:12px;"><code style="background:#f0f0f1;padding:2px 6px;border-radius:3px;"><?php echo $bt; ?></code></div>
                <?php endforeach; ?>
                <p style="font-size:11px;color:#6b7280;margin:8px 0 0;">Custom types can be added freely.</p>
            </div>
            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Notify Event</h3>
                <p style="font-size:12px;color:#6b7280;margin:0;">Link to a Notifications event slug. Leave empty to skip notification on this reaction.</p>
            </div>
        </div>
        </div>
        <?php
    }

    private static function renderTypeCard(string $key, array $def): void {
        $enabled   = !isset($def['enabled']) || !empty($def['enabled']);
        $icon_off  = $def['icon_off'] ?? 'far fa-circle';
        $icon_on   = $def['icon_on']  ?? 'fas fa-circle';
        $label     = $def['label']    ?? ucfirst($key);
        $label_on  = $def['label_on'] ?? '';
        $color     = $def['color']    ?? '#2271b1';
        $notify    = $def['notify_event'] ?? '';
        $mode      = $def['mode']     ?? ( !empty($def['toggle']) ? 'toggle' : 'toggle' );
        $limit     = (int) ($def['limit'] ?? 0);

        $mode_labels = [ 'toggle' => 'Toggle', 'additive' => 'Additive', 'cumulative' => 'Cumulative' ];
        $mode_colors = [ 'toggle' => '#2271b1', 'additive' => '#16a34a', 'cumulative' => '#e11d48' ];
        $mode_label  = $mode_labels[$mode] ?? $mode;
        $mode_color  = $mode_colors[$mode] ?? '#6b7280';
        ?>
        <div class="sh-rule-card <?php echo $enabled ? '' : 'sh-rule-inactive'; ?>" data-key="<?php echo esc_attr($key); ?>">
            <div class="sh-rule-header">

                <label class="sh-rule-switch" title="<?php echo $enabled ? 'Enabled' : 'Disabled'; ?>">
                    <input type="checkbox" class="sh-rule-switch-input sh-type-enabled-toggle" <?php echo $enabled ? 'checked' : ''; ?> data-key="<?php echo esc_attr($key); ?>">
                    <span class="sh-rule-switch-slider"></span>
                </label>

                <div class="sh-rule-meta">
                    <span style="display:inline-flex;align-items:center;gap:8px;">
                        <span style="width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($color); ?>22;display:inline-flex;align-items:center;justify-content:center;">
                            <?php echo self::adminIconHtml( $icon_off, $color, 14 ); ?>
                        </span>
                        <strong style="font-size:14px;"><?php echo esc_html($label); ?></strong>
                        <code style="background:#f0f0f1;padding:2px 8px;border-radius:10px;font-size:11px;color:#6b7280;"><?php echo esc_html($key); ?></code>
                        <span style="font-size:10px;background:<?php echo esc_attr($mode_color); ?>18;color:<?php echo esc_attr($mode_color); ?>;border:1px solid <?php echo esc_attr($mode_color); ?>44;padding:2px 8px;border-radius:10px;font-weight:600;"><?php echo esc_html($mode_label); ?></span>
                        <?php if ($mode === 'cumulative' && $limit > 0) : ?>
                            <span style="font-size:10px;color:#9ca3af;">limit: <?php echo $limit; ?></span>
                        <?php endif; ?>
                        <?php if ($notify) : ?>
                            <span class="sh-ch-badge sh-ch-alert active" style="font-size:10px;">notify: <?php echo esc_html($notify); ?></span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="sh-rule-actions">
                    <div class="sh-rule-btns">
                        <button type="button" class="sh-rule-btn sh-rule-btn-edit sh-type-edit-btn" title="Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="sh-rule-btn sh-rule-btn-delete sh-type-delete-btn" title="Delete" data-key="<?php echo esc_attr($key); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sh-rule-form" style="display:none">
                <div class="sh-form-row">
                    <div class="sh-form-col">
                        <label>Key</label>
                        <input type="text" class="sh-input sh-type-key" value="<?php echo esc_attr($key); ?>" readonly style="background:#f9fafb;color:#6b7280;">
                    </div>
                    <div class="sh-form-col">
                        <label>Label</label>
                        <input type="text" class="sh-input sh-type-label" value="<?php echo esc_attr($label); ?>" placeholder="Like">
                    </div>
                    <div class="sh-form-col">
                        <label>Label (Active)</label>
                        <input type="text" class="sh-input sh-type-label-on" value="<?php echo esc_attr($label_on); ?>" placeholder="Liked">
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Color</label>
                        <input type="text" class="sh-input sh-type-color sh-color-picker-inline" value="<?php echo esc_attr($color); ?>" style="width:100px;">
                    </div>
                </div>
                <div class="sh-form-row">
                    <div class="sh-form-col">
                        <label>Icon Off <span style="font-size:11px;color:#9ca3af;">(inactive)</span></label>
                        <div class="sh-icon-field" style="display:flex;align-items:center;gap:6px;">
                            <input type="text" class="sh-input sh-type-icon-off" value="<?php echo esc_attr($icon_off); ?>" placeholder="far fa-heart" style="max-width:140px;">
                            <button type="button" class="sh-media-pick-btn sh-btn sh-btn-secondary" style="padding:5px 8px;font-size:12px;" title="Pick from Media Library">&#128247;</button>
                            <button type="button" class="sh-icon-clear-btn sh-icon-btn sh-icon-delete" title="Clear" style="font-size:12px;">&#10005;</button>
                            <span class="sh-icon-preview">
                                <?php if ( $icon_off && is_numeric($icon_off) ) :
                                    $url = wp_get_attachment_url( (int) $icon_off );
                                    if ( $url ) : ?>
                                        <img src="<?php echo esc_url($url); ?>" style="width:22px;height:22px;object-fit:contain;border-radius:3px;" alt="">
                                    <?php endif;
                                elseif ( $icon_off ) : ?>
                                    <i class="<?php echo esc_attr($icon_off); ?>" style="font-size:18px;color:#9ca3af;"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="sh-form-col">
                        <label>Icon On <span style="font-size:11px;color:#9ca3af;">(active)</span></label>
                        <div class="sh-icon-field" style="display:flex;align-items:center;gap:6px;">
                            <input type="text" class="sh-input sh-type-icon-on" value="<?php echo esc_attr($icon_on); ?>" placeholder="fas fa-heart" style="max-width:140px;">
                            <button type="button" class="sh-media-pick-btn sh-btn sh-btn-secondary" style="padding:5px 8px;font-size:12px;" title="Pick from Media Library">&#128247;</button>
                            <button type="button" class="sh-icon-clear-btn sh-icon-btn sh-icon-delete" title="Clear" style="font-size:12px;">&#10005;</button>
                            <span class="sh-icon-preview">
                                <?php if ( $icon_on && is_numeric($icon_on) ) :
                                    $url = wp_get_attachment_url( (int) $icon_on );
                                    if ( $url ) : ?>
                                        <img src="<?php echo esc_url($url); ?>" style="width:22px;height:22px;object-fit:contain;border-radius:3px;" alt="">
                                    <?php endif;
                                elseif ( $icon_on ) : ?>
                                    <i class="<?php echo esc_attr($icon_on); ?>" style="font-size:18px;color:<?php echo esc_attr($color); ?>;"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="sh-form-col">
                        <label>Notify Event</label>
                        <input type="text" class="sh-input sh-type-notify" value="<?php echo esc_attr($notify); ?>" placeholder="new-follower">
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Interaction Mode</label>
                        <select class="sh-select sh-type-mode">
                            <option value="toggle"     <?php selected($mode,'toggle'); ?>>Toggle (like/unlike)</option>
                            <option value="additive"   <?php selected($mode,'additive'); ?>>Additive (sadece ekle)</option>
                            <option value="cumulative" <?php selected($mode,'cumulative'); ?>>Cumulative (clap)</option>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm sh-type-limit-wrap" style="<?php echo $mode !== 'cumulative' ? 'display:none;' : ''; ?>">
                        <label>Limit <span style="font-size:11px;color:#9ca3af;">(per user)</span></label>
                        <input type="number" class="sh-input sh-type-limit" value="<?php echo esc_attr($limit ?: 50); ?>" min="1" max="9999" style="width:80px;">
                    </div>
                </div>
                <div class="sh-form-footer">
                    <button type="button" class="sh-btn sh-btn-primary sh-type-save-btn">Save</button>
                    <button type="button" class="sh-btn sh-btn-ghost sh-type-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Icon degerini HTML'e cevir — FA class veya attachment ID destekler.
     * Admin preview icin kullanilir (inline, kucuk boyut).
     */
    private static function adminIconHtml( string $value, string $color = '#6b7280', int $size = 14 ): string {
        if ( is_numeric( $value ) && (int) $value > 0 ) {
            $url = wp_get_attachment_url( (int) $value );
            if ( $url ) {
                return '<img src="' . esc_url( $url ) . '" style="width:' . $size . 'px;height:' . $size . 'px;object-fit:contain;vertical-align:middle;" alt="">';
            }
            return '';
        }
        if ( $value ) {
            return '<i class="' . esc_attr( $value ) . '" style="color:' . esc_attr( $color ) . ';font-size:' . $size . 'px;"></i>';
        }
        return '';
    }

    private static function buildPreviewButtonStatic(string $style, string $icon_off, string $icon_on = '', string $label = '', string $label_on = '', string $color = '#6b7280'): string {
        // icon_off/on: FA class veya attachment ID
        if ( is_numeric( $icon_off ) && (int) $icon_off > 0 ) {
            $url  = wp_get_attachment_url( (int) $icon_off );
            $icon = $url ? '<img src="' . esc_url($url) . '" style="width:1em;height:1em;object-fit:contain;" alt="">' : '<i class="far fa-circle"></i>';
        } else {
            $icon = '<i class="' . esc_attr( $icon_off ?: 'far fa-circle' ) . '" style="pointer-events:none;"></i>';
        }
        $cnt  = '<span style="font-size:12px;font-weight:600;">42</span>';
        $lbl  = '<span style="font-size:12px;">' . esc_html($label) . '</span>';
        $base = 'display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;border:none;background:none;cursor:default;font-size:13px;color:#374151;';
        switch ($style) {
            case 'icon-only':  return '<span style="' . $base . '">' . $icon . '</span>';
            case 'icon-count': return '<span style="' . $base . '">' . $icon . ' ' . $cnt . '</span>';
            case 'icon-text':  return '<span style="' . $base . '">' . $icon . ' ' . $lbl . '</span>';
            case 'text-only':  return '<span style="' . $base . '">' . $lbl . '</span>';
            case 'pill':       return '<span style="' . $base . 'border:1.5px solid ' . esc_attr($color) . ';border-radius:999px;color:' . esc_attr($color) . ';">' . $icon . ' ' . $lbl . '</span>';
            case 'pill-count': return '<span style="' . $base . 'border:1.5px solid ' . esc_attr($color) . ';border-radius:999px;color:' . esc_attr($color) . ';">' . $icon . ' ' . $cnt . '</span>';
            default:           return '<span style="' . $base . '">' . $icon . ' ' . $cnt . '</span>';
        }
    }

    public static function ajaxSaveButton(): void {
        check_ajax_referer('sh_reactions_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $existing_key = sanitize_text_field($_POST['key'] ?? '');
        $obj_type     = sanitize_key($_POST['object_type']   ?? 'post');
        $subtype      = sanitize_key($_POST['subtype']       ?? '');
        $type         = sanitize_key($_POST['type']          ?? '');
        $style        = sanitize_key($_POST['style']         ?? 'icon-count');
        $class        = sanitize_text_field($_POST['class']  ?? '');
        $show_count   = isset($_POST['show_count'])   ? (bool) $_POST['show_count']   : true;
        $req_login    = filter_var( $_POST['require_login'] ?? false, FILTER_VALIDATE_BOOLEAN );

        if (!$type) wp_send_json_error('Invalid type');

        $buttons = get_option('sh_reactions_buttons', []);
        $is_edit = !empty($existing_key) && strpos($existing_key, 'new_') !== 0;

        if ($is_edit) {
            // Mevcut kaydi guncelle — key ile bul
            $found = false;
            foreach ($buttons as &$btn) {
                if (($btn['key'] ?? '') === $existing_key) {
                    $btn['object_type']   = $obj_type;
                    $btn['subtype']       = $subtype;
                    $btn['type']          = $type;
                    $btn['style']         = $style;
                    $btn['class']         = $class;
                    $btn['show_count']    = $show_count;
                    $btn['require_login'] = $req_login;
                    $found = true;
                    break;
                }
            }
            unset($btn);
            if ($found) {
                update_option('sh_reactions_buttons', $buttons, false);
                wp_send_json_success(['key' => $existing_key, 'count' => count($buttons)]);
            }
            // Key bulunamadi — asagida yeni kayit olarak devam et (eski format key'ler icin)
        }

        // Yeni kayit — duplicate check (sadece object_type + subtype + type kombinasyonu)
        foreach ($buttons as $btn) {
            if ($btn['object_type'] === $obj_type && $btn['subtype'] === $subtype && $btn['type'] === $type) {
                // Edit modunda ayni kombinasyon varsa guncelle
                if ($is_edit) {
                    foreach ($buttons as &$b) {
                        if ($b['object_type'] === $obj_type && $b['subtype'] === $subtype && $b['type'] === $type) {
                            $b['style']         = $style;
                            $b['class']         = $class;
                            $b['show_count']    = $show_count;
                            $b['require_login'] = $req_login;
                            $found_key = $b['key'] ?? $existing_key;
                            break;
                        }
                    }
                    unset($b);
                    update_option('sh_reactions_buttons', $buttons, false);
                    wp_send_json_success(['key' => $found_key ?? $existing_key, 'count' => count($buttons)]);
                }
                wp_send_json_error('Already saved');
            }
        }

        $key = $obj_type . '_' . $subtype . '_' . $type . '_' . time();
        $buttons[] = [
            'key'           => $key,
            'object_type'   => $obj_type,
            'subtype'       => $subtype,
            'type'          => $type,
            'style'         => $style,
            'class'         => $class,
            'show_count'    => $show_count,
            'require_login' => $req_login,
            'active'        => true,
        ];
        update_option('sh_reactions_buttons', $buttons, false);
        wp_send_json_success(['key' => $key, 'count' => count($buttons)]);
    }

    public static function ajaxToggleButton(): void {
        check_ajax_referer('sh_reactions_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $key     = sanitize_text_field($_POST['key'] ?? '');
        $buttons = get_option('sh_reactions_buttons', []);
        foreach ($buttons as &$btn) {
            if (($btn['key'] ?? '') === $key) {
                $btn['active'] = !($btn['active'] ?? true);
                break;
            }
        }
        unset($btn);
        update_option('sh_reactions_buttons', $buttons, false);
        wp_send_json_success();
    }

    public static function ajaxDeleteButton(): void {
        check_ajax_referer('sh_reactions_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $key     = sanitize_text_field($_POST['key'] ?? '');
        $buttons = get_option('sh_reactions_buttons', []);
        $buttons = array_values(array_filter($buttons, fn($b) => ($b['key'] ?? '') !== $key));
        update_option('sh_reactions_buttons', $buttons, false);
        wp_send_json_success(['count' => count($buttons)]);
    }

    private static function renderInfoTab(): void {
        $types = ReactionsSettings::getTypes();

        // Kod bloklari: [baslik, aciklama, twig_kodu]
        $sections = [
            'Post / Product Extend Metodlari' => [
                ['reaction_count', 'Kac kisi like yapti?', "{{ post.reaction_count('like') }}"],
                ['has_reaction', 'Mevcut kullanici like yapti mi?', "{% if post.has_reaction('like') %}...{% endif %}"],
                ['reaction_counts', 'Tum tiplerin sayilari (array)', "{% set counts = post.reaction_counts %}\n{{ counts.like }} like, {{ counts.favorite }} favorite"],
                ['reaction_button', 'Like butonu render et', "{{ post.reaction_button('like', {'style': 'pill'})|raw }}"],
                ['reaction_button', 'Favorite butonu icon-only', "{{ post.reaction_button('favorite', {'style': 'icon-only'})|raw }}"],
            ],
            'User Extend Metodlari' => [
                ['reaction_count', 'Kac kisi follow etti?', "{{ user.reaction_count('follow') }}"],
                ['has_reaction', 'Mevcut kullanici follow yapti mi?', "{% if user.has_reaction('follow') %}...{% endif %}"],
                ['reaction_button', 'Follow butonu render et', "{{ user.reaction_button('follow', {'style': 'pill'})|raw }}"],
                ['reaction_ids', 'Kullanicinin favori post ID listesi', "{% set fav_ids = user.reaction_ids('favorite', 'post') %}"],
                ['reaction_ids', 'Kullanicinin follow ettigi user ID listesi', "{% set following = user.reaction_ids('follow', 'user') %}"],
            ],
            'Term Extend Metodlari' => [
                ['reaction_count', 'Kac kisi follow etti?', "{{ term.reaction_count('follow') }}"],
                ['has_reaction', 'Mevcut kullanici follow yapti mi?', "{% if term.has_reaction('follow') %}...{% endif %}"],
                ['reaction_button', 'Follow butonu render et', "{{ term.reaction_button('follow', {'style': 'pill'})|raw }}"],
            ],
            'Review / Comment Extend Metodlari' => [
                ['reaction_count', 'Kac kisi like yapti?', "{{ review.reaction_count('like') }}"],
                ['has_reaction', 'Mevcut kullanici like yapti mi?', "{% if review.has_reaction('like') %}...{% endif %}"],
                ['reaction_button', 'Like butonu render et', "{{ review.reaction_button('like', {'style': 'icon-count'})|raw }}"],
            ],
            'Global Twig Fonksiyonlari' => [
                ['salt_reaction_count', 'Post like sayisi', "{{ function('salt_reaction_count', post.ID, 'post', 'like') }}"],
                ['salt_has_reaction', 'Kullanici like yapti mi?', "{{ function('salt_has_reaction', post.ID, 'post', 'like') }}"],
                ['salt_reaction_ids', 'Kullanicinin favori ID listesi', "{% set favs = function('salt_reaction_ids', user.ID, 'post', 'favorite') %}"],
                ['salt_reactions_for', 'Post icin tanimli reactionlar', "{% set reactions = function('salt_reactions_for', 'post', post.post_type) %}"],
            ],
            'Faydali Kullanim Ornekleri' => [
                ['Favori listesi', 'Kullanicinin favori postlarini cek', "{% set fav_ids = user.reaction_ids('favorite', 'post') %}\n{% set fav_posts = fav_ids ? get_posts({'post__in': fav_ids, 'post_type': 'any'}) : [] %}\n{% for p in fav_posts %}{{ p.title }}{% endfor %}"],
                ['Reaction durumu badge', 'Aktif/pasif duruma gore class', "<div class=\"reaction-wrap {% if post.has_reaction('like') %}is-liked{% endif %}\">\n    {{ post.reaction_button('like', {'style': 'icon-count'})|raw }}\n</div>"],
                ['Coklu reaction', 'Birden fazla reaction butonu', "{{ post.reaction_button('like', {'style': 'icon-count'})|raw }}\n{{ post.reaction_button('favorite', {'style': 'icon-only'})|raw }}\n{{ post.reaction_button('bookmark', {'style': 'icon-only'})|raw }}"],
                ['Follow sayaci', 'Kullanicinin follower sayisi', "<span>{{ user.reaction_count('follow') }} takipci</span>\n{{ user.reaction_button('follow', {'style': 'pill'})|raw }}"],
            ],
        ];
        ?>
        <div class="sh-layout">
        <div class="sh-main">
        <?php foreach ( $sections as $section_title => $items ) : ?>
            <div class="sh-card" style="margin-bottom:16px;">
                <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:var(--ts-gray-900);"><?php echo esc_html($section_title); ?></h3>
                <table class="sh-table">
                    <thead><tr>
                        <th style="width:160px;">Metod / Fonksiyon</th>
                        <th style="width:200px;">Aciklama</th>
                        <th>Twig Kodu</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td><code style="background:var(--ts-gray-100);padding:2px 7px;border-radius:3px;font-size:11px;color:var(--ts-primary);"><?php echo esc_html($item[0]); ?></code></td>
                        <td style="font-size:12px;color:var(--ts-gray-600);"><?php echo esc_html($item[1]); ?></td>
                        <td colspan="2">
                            <div class="sh-twig-wrap" style="display:flex;align-items:flex-start;gap:8px;padding:4px 0;">
                                <code class="sh-twig-code" style="background:var(--ts-gray-100);padding:8px 12px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;white-space:pre;display:block;flex:1;line-height:1.7;"><?php echo esc_html($item[2]); ?></code>
                                <button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;margin-top:4px;">&#128203;</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="sh-sidebar">
            <div class="sh-card">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Reaction Tipleri</h3>
                <?php foreach ( $types as $key => $def ) :
                    $enabled = !isset($def['enabled']) || !empty($def['enabled']);
                    $color   = $def['color'] ?? '#6b7280';
                ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;<?php echo !$enabled ? 'opacity:.45;' : ''; ?>">
                    <span style="width:22px;height:22px;border-radius:50%;background:<?php echo esc_attr($color); ?>22;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;">
                        <?php
                        $io = $def['icon_off'] ?? '';
                        if ( is_numeric($io) && (int)$io > 0 ) {
                            $iu = wp_get_attachment_url((int)$io);
                            if ($iu) echo '<img src="'.esc_url($iu).'" style="width:12px;height:12px;object-fit:contain;" alt="">';
                        } else {
                            echo '<i class="'.esc_attr($io ?: 'far fa-circle').'" style="color:'.esc_attr($color).';font-size:11px;"></i>';
                        }
                        ?>
                    </span>
                    <code style="font-size:11px;background:var(--ts-gray-100);padding:2px 6px;border-radius:3px;"><?php echo esc_html($key); ?></code>
                    <span style="font-size:11px;color:var(--ts-gray-500);"><?php echo esc_html($def['label'] ?? $key); ?></span>
                    <?php if (!$enabled) echo '<span style="font-size:10px;color:#9ca3af;">(pasif)</span>'; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Style Secenekleri</h3>
                <?php foreach ( ReactionsSettings::getButtonStyles() as $sv => $sl ) : ?>
                <div style="margin-bottom:5px;font-size:12px;display:flex;justify-content:space-between;gap:8px;">
                    <code style="background:var(--ts-gray-100);padding:2px 6px;border-radius:3px;font-size:11px;"><?php echo esc_html($sv); ?></code>
                    <span style="color:var(--ts-gray-500);"><?php echo esc_html($sl); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div>
        <?php
    }

    private static function renderStyles(): void {
        // Direkt link tag — enqueue'ya bagimli degil, kesinlikle yuklenir
        $css_url = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        echo '<link rel="stylesheet" href="' . esc_url( $css_url ) . '" media="all">' . "\n";
        // wp-color-picker CSS — wp-admin/css/ altinda
        echo '<link rel="stylesheet" href="' . esc_url( admin_url( 'css/color-picker.min.css' ) ) . '" media="all">' . "\n";
    }


    private static function renderScripts(array $types, array $styles): void {
        // JS enqueueAssets() ile wp_enqueue_script uzerinden yukleniyor.
        // Burada sadece shReactionsAdmin global'ini tanimliyoruz (wp_localize_script'e fallback).
        $palette = self::getThemePalette();
        $nonce   = wp_create_nonce('sh_reactions_nonce');
        $ajax    = admin_url('admin-ajax.php');
        ?>
        <script>
        window.shReactionsAdmin = window.shReactionsAdmin || {
            nonce:   <?php echo wp_json_encode( $nonce ); ?>,
            ajax:    <?php echo wp_json_encode( $ajax ); ?>,
            palette: <?php echo wp_json_encode( $palette ); ?>
        };

        function shReactionsToggleEnable(checked) {
            var $toggle  = document.getElementById('sh-reactions-enable-toggle');
            var $label   = document.getElementById('sh-reactions-enable-label');
            var $saving  = document.getElementById('sh-reactions-enable-saving');
            if ($saving)  $saving.style.display  = 'inline-flex';
            if ($label)   $label.style.display   = 'none';
            var data = new FormData();
            data.append('action',  'sh_reactions_save_enable_toggle');
            data.append('nonce',   window.shReactionsAdmin.nonce);
            data.append('enabled', checked ? '1' : '0');
            fetch(window.shReactionsAdmin.ajax, { method: 'POST', body: data })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if ($saving) $saving.style.display = 'none';
                    if ($label)  $label.style.display  = 'inline';
                    if (res.success) {
                        var on = res.data.enabled;
                        if ($label)  { $label.textContent = on ? 'Enabled' : 'Disabled'; $label.style.color = on ? '#00a32a' : '#9ca3af'; }
                        if (typeof window.shShowToast === 'function') window.shShowToast(on ? 'Reactions enabled' : 'Reactions disabled', 'success');
                    } else {
                        // Rollback
                        if ($toggle) $toggle.checked = !checked;
                        if ($label)  { $label.textContent = !checked ? 'Enabled' : 'Disabled'; $label.style.color = !checked ? '#00a32a' : '#9ca3af'; }
                        if (typeof window.shShowToast === 'function') window.shShowToast('Save failed', 'error');
                    }
                })
                .catch(function() {
                    if ($saving) $saving.style.display = 'none';
                    if ($label)  $label.style.display  = 'inline';
                    if ($toggle) $toggle.checked = !checked;
                    if (typeof window.shShowToast === 'function') window.shShowToast('Network error', 'error');
                });
        }
        </script>
        <?php
    }

    private static function getThemePalette(): array {
        $json_file = get_template_directory() . '/theme/static/data/theme-styles-new/latest.json';
        $data = [];
        if ( file_exists( $json_file ) ) {
            $raw = file_get_contents( $json_file );
            if ( $raw ) $data = json_decode( $raw, true ) ?: [];
        }
        if ( empty( $data ) ) {
            $data = get_option( 'theme_styles_new_data', [] );
        }
        $palette = [];
        $cols    = $data['colors'] ?? [];
        foreach ( ['primary','secondary','tertiary','quaternary'] as $k ) {
            if ( ! empty( $cols[$k] ) ) $palette[] = $cols[$k];
        }
        if ( ! empty( $cols['custom'] ) && is_array( $cols['custom'] ) ) {
            foreach ( $cols['custom'] as $c ) {
                if ( ! empty( $c['color'] ) ) $palette[] = $c['color'];
            }
        }
        if ( empty( $palette ) ) {
            $palette = ['#e11d48','#2271b1','#f59e0b','#6366f1','#10b981','#f97316','#8b5cf6','#ec4899','#14b8a6','#64748b'];
        }
        return $palette;
    }

    private static function renderGeneratorTab(array $types, array $styles): void {
        $saved_buttons = get_option('sh_reactions_buttons', []);
        $pub_pts  = get_post_types(['public' => true], 'objects');
        $pub_taxs = get_taxonomies(['public' => true], 'objects');
        global $wp_roles;
        $bc = count($saved_buttons);
        // Subtype options JSON for JS
        $subtype_opts = ['post' => [], 'user' => [], 'comment' => [['value'=>'','label'=>'— All —'],['value'=>'comment','label'=>'Comment'],['value'=>'review','label'=>'Review']], 'term' => []];
        $subtype_opts['post'][] = ['value'=>'','label'=>'— All —'];
        foreach ($pub_pts as $pt) $subtype_opts['post'][] = ['value'=>$pt->name,'label'=>$pt->label];
        $subtype_opts['user'][] = ['value'=>'','label'=>'— All —'];
        foreach ($wp_roles->roles as $slug=>$role) $subtype_opts['user'][] = ['value'=>$slug,'label'=>$role['name']];
        $subtype_opts['term'][] = ['value'=>'','label'=>'— All —'];
        foreach ($pub_taxs as $tax) $subtype_opts['term'][] = ['value'=>$tax->name,'label'=>$tax->label];
        $types_json = wp_json_encode($types);
        $styles_json = wp_json_encode($styles);
        $subtypes_json = wp_json_encode($subtype_opts);
        // icon URL'lerini de ekle (attachment ID ise URL'e cevir)
        $types_for_js = [];
        foreach ( $types as $tk => $td ) {
            $entry = $td;
            $io_off = $td['icon_off'] ?? '';
            $io_on  = $td['icon_on']  ?? '';
            $entry['icon_off_url'] = ( is_numeric($io_off) && (int)$io_off > 0 ) ? (wp_get_attachment_url((int)$io_off) ?: '') : '';
            $entry['icon_on_url']  = ( is_numeric($io_on)  && (int)$io_on  > 0 ) ? (wp_get_attachment_url((int)$io_on)  ?: '') : '';
            $types_for_js[$tk] = $entry;
        }
        $types_json    = wp_json_encode($types_for_js);
        ?>
        <script>
        var SH_GEN_TYPES   = <?php echo $types_json; ?>;
        var SH_GEN_STYLES  = <?php echo $styles_json; ?>;
        var SH_GEN_SUBTYPES = <?php echo $subtypes_json; ?>;
        </script>

        <div class="sh-layout">
        <div class="sh-main">

            <!-- Filter bar -->
            <div class="sh-filter-bar">
                <span class="sh-count-label"><?php echo $bc; ?> button<?php echo $bc !== 1 ? 's' : ''; ?></span>
                <button type="button" class="sh-btn sh-btn-primary" id="sh-gen-add-btn" style="margin-left:auto;">+ Add Button</button>
            </div>

            <!-- Saved buttons list -->
            <div id="sh-saved-buttons-list">
            <?php foreach ($saved_buttons as $i => $btn) :
                echo self::renderButtonCard($btn, $types, $styles, $i);
            endforeach; ?>
            <?php if (empty($saved_buttons)) : ?>
                <div class="sh-empty-box" id="sh-gen-empty">
                    <p style="margin:0 0 4px;font-weight:500;color:var(--ts-gray-700);">No saved buttons yet</p>
                    <p style="margin:0 0 16px;font-size:12px;color:var(--ts-gray-400);">Click "+ Add Button" to create your first button</p>
                    <button type="button" class="sh-btn sh-btn-primary" id="sh-gen-add-btn2">+ Add Button</button>
                </div>
            <?php endif; ?>
            </div>

        </div>
        <div class="sh-sidebar">
            <div class="sh-card">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">ID Variables</h3>
                <?php foreach (['post'=>'post.ID','user'=>'user.ID','comment'=>'comment.ID','term'=>'term.term_id'] as $ot=>$idv) : ?>
                    <div style="margin-bottom:6px;font-size:12px;display:flex;justify-content:space-between;gap:8px;">
                        <code style="background:var(--ts-gray-100);padding:2px 6px;border-radius:3px;"><?php echo $ot; ?></code>
                        <code style="color:var(--ts-primary);"><?php echo $idv; ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Options</h3>
                <div style="font-size:12px;color:var(--ts-gray-600);line-height:2;">
                    <code>'class': 'my-class'</code><br>
                    <code>'show_count': false</code><br>
                    <code>'require_login': false</code>
                </div>
            </div>
        </div>
        </div>
        <?php
    }

    private static function renderButtonCard(array $btn, array $types, array $styles, $i = 0): string {
        $btn_type    = $btn['type']          ?? 'like';
        $btn_obj     = $btn['object_type']   ?? 'post';
        $btn_subtype = $btn['subtype']       ?? '';
        $btn_style   = $btn['style']         ?? 'icon-count';
        $btn_key     = $btn['key']           ?? 'btn_' . $i;
        $btn_active  = $btn['active']        ?? true;
        $btn_class   = $btn['class']         ?? '';
        $btn_sc      = $btn['show_count']    ?? true;
        $btn_rl      = $btn['require_login'] ?? true;
        $type_def    = $types[$btn_type]     ?? [];
        $icon_off    = $type_def['icon_off'] ?? 'far fa-circle';
        $icon_on     = $type_def['icon_on']  ?? 'fas fa-circle';
        $color       = $type_def['color']    ?? '#6b7280';
        $label       = $type_def['label']    ?? $btn_type;
        $label_on    = $type_def['label_on'] ?? $label;
        $id_var      = match($btn_obj) { 'user'=>'user.ID','comment'=>'comment.ID','term'=>'term.term_id',default=>'post.ID' };
        $opts        = ["'style': '{$btn_style}'"];
        if (!empty($btn_class)) $opts[] = "'class': '{$btn_class}'";
        if (!$btn_sc)           $opts[] = "'show_count': false";
        if (!$btn_rl)           $opts[] = "'require_login': false";
        $twig        = "{{ function('salt_reaction_button', {$id_var}, '{$btn_obj}', '{$btn_type}', {" . implode(', ', $opts) . "}) }}";
        $preview     = self::buildPreviewButtonStatic($btn_style, $icon_off, $icon_on, $label, $label_on, $color);
        $obj_label   = $btn_obj . ($btn_subtype ? ':' . $btn_subtype : '');

        ob_start(); ?>
        <div class="sh-rule-card <?php echo $btn_active ? '' : 'sh-rule-inactive'; ?>" data-key="<?php echo esc_attr($btn_key); ?>">
            <div class="sh-rule-header">
                <label class="sh-rule-switch">
                    <input type="checkbox" class="sh-rule-switch-input sh-btn-active-toggle" <?php echo $btn_active ? 'checked' : ''; ?> data-key="<?php echo esc_attr($btn_key); ?>">
                    <span class="sh-rule-switch-slider"></span>
                </label>
                <div class="sh-rule-meta">
                    <span style="display:inline-flex;align-items:center;gap:8px;">
                        <span style="width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($color); ?>22;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <?php echo self::adminIconHtml( $icon_off, $color, 14 ); ?>
                        </span>
                        <strong><?php echo esc_html($label); ?></strong>
                        <code style="background:var(--ts-gray-100);padding:2px 8px;border-radius:10px;font-size:11px;color:var(--ts-gray-600);"><?php echo esc_html($obj_label); ?></code>
                        <span style="font-size:11px;background:var(--ts-gray-100);padding:2px 8px;border-radius:10px;"><?php echo esc_html($btn_style); ?></span>
                        <?php if ( !empty($btn_class) ) : ?>
                            <code style="font-size:11px;background:#f0f7ff;color:#2271b1;padding:2px 8px;border-radius:10px;">.<?php echo esc_html($btn_class); ?></code>
                        <?php endif; ?>
                        <?php if ( $btn_rl ) : ?>
                            <span class="sh-ch-badge" style="font-size:10px;background:#fff7ed;color:#c2410c;border-color:#fed7aa;">&#128274; login req</span>
                        <?php else : ?>
                            <span class="sh-ch-badge" style="font-size:10px;background:#f0fdf4;color:#16a34a;border-color:#bbf7d0;">&#128275; public</span>
                        <?php endif; ?>
                        <?php if ( !$btn_sc ) : ?>
                            <span style="font-size:10px;background:var(--ts-gray-100);color:var(--ts-gray-500);padding:2px 8px;border-radius:10px;">no count</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="sh-rule-actions">
                    <div class="sh-rule-btns">
                        <button type="button" class="sh-rule-btn sh-rule-btn-edit sh-btn-expand-btn" title="Edit / Twig">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="sh-rule-btn sh-rule-btn-delete sh-gen-delete-btn" data-key="<?php echo esc_attr($btn_key); ?>" title="Delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="sh-rule-form" style="display:none;">
                <div class="sh-form-row">
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Object Type</label>
                        <select class="sh-select sh-btn-obj-type" data-key="<?php echo esc_attr($btn_key); ?>">
                            <?php foreach (['post'=>'Post','user'=>'User','comment'=>'Comment','term'=>'Term'] as $ov=>$ol) : ?>
                                <option value="<?php echo $ov; ?>" <?php selected($btn_obj,$ov); ?>><?php echo $ol; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Subtype</label>
                        <select class="sh-select sh-btn-subtype" data-current="<?php echo esc_attr($btn_subtype); ?>">
                            <option value="">— All —</option>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Reaction</label>
                        <select class="sh-select sh-btn-reaction">
                            <?php foreach ($types as $tk => $td) :
                                $io_off = $td['icon_off'] ?? 'far fa-circle';
                                $io_on  = $td['icon_on']  ?? 'fas fa-circle';
                                $io_off_url = ( is_numeric($io_off) && (int)$io_off > 0 ) ? (wp_get_attachment_url((int)$io_off) ?: '') : '';
                                $io_on_url  = ( is_numeric($io_on)  && (int)$io_on  > 0 ) ? (wp_get_attachment_url((int)$io_on)  ?: '') : '';
                            ?>
                                <option value="<?php echo esc_attr($tk); ?>"
                                    data-icon-off="<?php echo esc_attr($io_off); ?>"
                                    data-icon-on="<?php echo esc_attr($io_on); ?>"
                                    data-icon-off-url="<?php echo esc_attr($io_off_url); ?>"
                                    data-icon-on-url="<?php echo esc_attr($io_on_url); ?>"
                                    data-label="<?php echo esc_attr($td['label']??$tk); ?>"
                                    data-label-on="<?php echo esc_attr($td['label_on']??$td['label']??$tk); ?>"
                                    data-color="<?php echo esc_attr($td['color']??'#6b7280'); ?>"
                                    <?php selected($btn_type,$tk); ?>>
                                    <?php echo esc_html($td['label']??$tk); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Style</label>
                        <select class="sh-select sh-btn-style">
                            <?php foreach ($styles as $sv=>$sl) : ?>
                                <option value="<?php echo esc_attr($sv); ?>" <?php selected($btn_style,$sv); ?>><?php echo esc_html($sl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col">
                        <label>CSS Class</label>
                        <input type="text" class="sh-input sh-btn-class" value="<?php echo esc_attr($btn_class); ?>" placeholder="btn-sm my-class">
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Show Count</label>
                        <label class="sh-toggle" style="margin-top:6px;"><input type="checkbox" class="sh-btn-show-count" <?php echo $btn_sc ? 'checked' : ''; ?>><span class="sh-toggle-slider"></span></label>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Require Login</label>
                        <label class="sh-toggle" style="margin-top:6px;"><input type="checkbox" class="sh-btn-require-login" <?php echo $btn_rl ? 'checked' : ''; ?>><span class="sh-toggle-slider"></span></label>
                    </div>
                </div>
                <!-- Preview + Code -->
                <?php
                $id_var = match($btn_obj) { 'user'=>'user.ID','comment'=>'comment.ID','term'=>'term.term_id',default=>'post.ID' };
                // Twig extend (minimal)
                $twig_extend_min  = "{{ post.reaction_button('{$btn_type}')|raw }}";
                // Twig extend (with params)
                $twig_extend_full = "{{ post.reaction_button('{$btn_type}', {'style': '{$btn_style}'" . (!empty($btn_class) ? ", 'class': '{$btn_class}'" : '') . ", 'require_login': " . ($btn_rl ? 'true' : 'false') . ", 'show_count': " . ($btn_sc ? 'true' : 'false') . "})|raw }}";
                // Twig function (minimal)
                $twig_fn_min      = "{{ function('salt_reaction_button', {$id_var}, '{$btn_obj}', '{$btn_type}') }}";
                // Twig function (with params)
                $twig_fn_full     = "{{ function('salt_reaction_button', {$id_var}, '{$btn_obj}', '{$btn_type}', {'style': '{$btn_style}'" . (!empty($btn_class) ? ", 'class': '{$btn_class}'" : '') . ", 'require_login': " . ($btn_rl ? 'true' : 'false') . ", 'show_count': " . ($btn_sc ? 'true' : 'false') . "}) }}";
                // PHP (minimal)
                $php_min          = "salt_reaction_button(get_the_ID(), '{$btn_obj}', '{$btn_type}');";
                // PHP (with params)
                $php_full         = "salt_reaction_button(get_the_ID(), '{$btn_obj}', '{$btn_type}', [\n    'style'         => '{$btn_style}'," . (!empty($btn_class) ? "\n    'class'         => '{$btn_class}'," : '') . "\n    'require_login' => " . ($btn_rl ? 'true' : 'false') . ",\n    'show_count'    => " . ($btn_sc ? 'true' : 'false') . ",\n]);";
                ?>
                <div style="padding:12px;background:var(--ts-gray-50);border-radius:var(--ts-radius);margin-bottom:12px;">
                    <!-- Preview + Tab + Customize -->
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                        <div style="display:flex;gap:4px;">
                            <button type="button" class="sh-code-tab-btn active" data-tab="twig" style="padding:4px 10px;border-radius:4px;border:1px solid var(--ts-gray-300);background:var(--ts-primary);color:#fff;font-size:11px;font-weight:600;cursor:pointer;">Twig</button>
                            <button type="button" class="sh-code-tab-btn" data-tab="php" style="padding:4px 10px;border-radius:4px;border:1px solid var(--ts-gray-300);background:var(--ts-white);color:var(--ts-gray-700);font-size:11px;font-weight:600;cursor:pointer;">PHP</button>
                        </div>
                        <label style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--ts-gray-600);cursor:pointer;margin-left:16px;">
                            <input type="checkbox" class="sh-code-customize" style="margin:0;">
                            Customize params
                        </label>
                    </div>
                    <!-- Twig tab -->
                    <div class="sh-code-panel" data-panel="twig">
                        <div class="sh-twig-wrap" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                            <code class="sh-twig-code sh-code-extend-min" style="background:var(--ts-gray-100);padding:5px 10px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;flex:1;cursor:pointer;"><?php echo esc_html($twig_extend_min); ?></code>
                            <button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;">&#128203;</button>
                        </div>
                        <div class="sh-twig-wrap" style="display:flex;align-items:center;gap:6px;">
                            <code class="sh-twig-code sh-code-fn-min" style="background:var(--ts-gray-100);padding:5px 10px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;flex:1;cursor:pointer;"><?php echo esc_html($twig_fn_min); ?></code>
                            <button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;">&#128203;</button>
                        </div>
                    </div>
                    <!-- PHP tab -->
                    <div class="sh-code-panel" data-panel="php" style="display:none;">
                        <div class="sh-twig-wrap" style="display:flex;align-items:center;gap:6px;">
                            <code class="sh-twig-code sh-code-php-min" style="background:var(--ts-gray-100);padding:5px 10px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;flex:1;cursor:pointer;"><?php echo esc_html($php_min); ?></code>
                            <button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;">&#128203;</button>
                        </div>
                    </div>
                    <!-- Data attrs for JS update -->
                    <span class="sh-code-data"
                        data-extend-min="<?php echo esc_attr($twig_extend_min); ?>"
                        data-extend-full="<?php echo esc_attr($twig_extend_full); ?>"
                        data-fn-min="<?php echo esc_attr($twig_fn_min); ?>"
                        data-fn-full="<?php echo esc_attr($twig_fn_full); ?>"
                        data-php-min="<?php echo esc_attr($php_min); ?>"
                        data-php-full="<?php echo esc_attr(str_replace("\n", '&#10;', $php_full)); ?>"
                        style="display:none;"></span>
                </div>
                <div class="sh-form-footer">
                    <button type="button" class="sh-btn sh-btn-primary sh-btn-save-btn" data-key="<?php echo esc_attr($btn_key); ?>">Save</button>
                    <button type="button" class="sh-btn sh-btn-ghost sh-btn-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

}
