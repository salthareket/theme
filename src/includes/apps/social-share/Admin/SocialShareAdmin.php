<?php

namespace SaltHareket\SocialShare\Admin;

use SaltHareket\SocialShare\SocialShare;

/**
 * SocialShareAdmin
 *
 * General Settings → Social tab yönetimi.
 *
 * @version 2.0.0
 */
class SocialShareAdmin
{
    public static function register(): void
    {
        add_action( 'wp_ajax_sh_gs_save_social_share', [ self::class, 'ajaxSave' ] );
    }

    public static function ajaxSave(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        check_ajax_referer( 'sh_gs_nonce', 'nonce' );

        $all_network_keys = array_keys( SocialShare::networks() );
        $valid_views      = [ 'button', 'solid' ];
        $valid_styles     = [ 'icon-only', 'icon-text', 'text-only' ];
        $valid_sizes      = [ 'sm', 'md', 'lg' ];
        $valid_layouts    = [ 'horizontal', 'vertical' ];
        $valid_color_opts = [ 'brand', 'custom' ];
        $valid_text_opts  = [ 'white', 'brand', 'custom' ];

        // Seçili ağlar — sıralı olarak gelir
        $networks = array_values( array_filter(
            array_map( 'sanitize_key', (array) ( $_POST['networks'] ?? [] ) ),
            fn( $k ) => in_array( $k, $all_network_keys, true )
        ) );

        $sn = function( string $key, string $default = '' ): string {
            return sanitize_text_field( $_POST[ $key ] ?? $default );
        };
        $sc = function( string $key, string $valid_fn, array $valid, string $default ) use ( $sn ): string {
            $v = $sn( $key );
            return in_array( $v, $valid, true ) ? $v : $default;
        };

        $settings = [
            'enabled'              => ! empty( $_POST['enabled'] ),
            'networks'             => $networks ?: [ 'facebook', 'x', 'whatsapp', 'telegram', 'email', 'copy' ],
            'view'                 => in_array( $sn('view'), $valid_views, true )    ? $sn('view')   : 'button',
            'style'                => in_array( $sn('style'), $valid_styles, true )  ? $sn('style')  : 'icon-only',
            'size'                 => in_array( $sn('size'), $valid_sizes, true )    ? $sn('size')   : 'md',
            'layout'               => in_array( $sn('layout'), $valid_layouts, true )? $sn('layout') : 'horizontal',
            // Button view renkler
            'btn_bg'               => in_array( $sn('btn_bg'), $valid_color_opts, true )       ? $sn('btn_bg')       : 'brand',
            'btn_bg_color'         => $sn('btn_bg_color', '#374151'),
            'btn_bg_hover'         => in_array( $sn('btn_bg_hover'), $valid_color_opts, true )  ? $sn('btn_bg_hover') : 'brand',
            'btn_bg_hover_color'   => $sn('btn_bg_hover_color', ''),
            'btn_text'             => in_array( $sn('btn_text'), $valid_text_opts, true )       ? $sn('btn_text')     : 'white',
            'btn_text_color'       => $sn('btn_text_color', '#ffffff'),
            'btn_text_hover'       => in_array( $sn('btn_text_hover'), $valid_text_opts, true ) ? $sn('btn_text_hover') : 'white',
            'btn_text_hover_color' => $sn('btn_text_hover_color', '#ffffff'),
            // Solid view renkler
            'solid_color'          => in_array( $sn('solid_color'), $valid_color_opts, true )  ? $sn('solid_color')  : 'brand',
            'solid_color_val'      => $sn('solid_color_val', ''),
            'solid_hover'          => in_array( $sn('solid_hover'), $valid_color_opts, true )   ? $sn('solid_hover')  : 'brand',
            'solid_hover_val'      => $sn('solid_hover_val', ''),
            // Diğer
            'show_count'           => ! empty( $_POST['show_count'] ),
            'ga4_tracking'         => ! empty( $_POST['ga4_tracking'] ),
            'via'                  => $sn('via'),
            'hashtags'             => $sn('hashtags'),
        ];

        update_option( 'sh_social_share_settings', $settings, false );
        wp_send_json_success( 'Social Share ayarları kaydedildi.' );
    }

    public static function renderTab( string $nonce, string $ajax ): void
    {
        $cfg      = SocialShare::getSettings();
        $networks = SocialShare::networks();

        $selected = $cfg['networks'];
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">🔗 Social Share</span>
                <span class="sh-card-desc">Paylaşım butonlarının görünüm ve ağ ayarları.</span>
            </div>
            <div class="sh-card-body">

                <!-- Enable -->
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle">
                        <input type="checkbox" id="sh-ss-enabled" <?php checked( $cfg['enabled'] ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <label for="sh-ss-enabled" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Social Share Aktif</label>
                </div>

                <hr class="sh-divider">

                <!-- ── Ağ Seçimi (sh-select) ────────────────────────── -->
                <div class="sh-mb-md">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Aktif Ağlar</label>
                    <p class="sh-text-sm sh-text-muted sh-mb-sm">Ağları seç, sıralamayı önizleme butonlarından sürükle-bırak ile ayarla.</p>
                    <select id="sh-ss-networks-select" multiple data-sh-select data-placeholder="Ağ seç..." style="width:100%">
                        <?php foreach ( $networks as $key => $net ) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected( in_array($key, $selected, true) ); ?>>
                            <?php echo esc_html($net['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr class="sh-divider">

                <!-- ── Görünüm ────────────────────────────────────────── -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">

                    <!-- View: Button | Solid -->
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Görünüm</label>
                        <div style="display:flex;gap:6px">
                            <?php foreach(['button'=>'⬛ Button','solid'=>'☆ Solid'] as $v=>$l): ?>
                            <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:6px 10px;
                                          border:2px solid <?php echo $cfg['view']===$v?'#2271b1':'#e5e7eb'; ?>;border-radius:6px;
                                          cursor:pointer;font-size:11px;font-weight:500;
                                          background:<?php echo $cfg['view']===$v?'#eff6ff':'#fff'; ?>">
                                <input type="radio" name="sh_ss_view" value="<?php echo $v; ?>" <?php checked($cfg['view']??'button',$v); ?> style="display:none">
                                <?php echo $l; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Style (button view'da anlamlı) -->
                    <div id="sh-ss-style-wrap" style="<?php echo ($cfg['view']??'button') === 'solid' ? 'opacity:.4;pointer-events:none' : ''; ?>">
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Stil</label>
                        <select id="sh-ss-style" class="sh-input sh-w-full">
                            <option value="icon-only" <?php selected($cfg['style'],'icon-only'); ?>>Sadece İkon</option>
                            <option value="icon-text" <?php selected($cfg['style'],'icon-text'); ?>>İkon + Metin</option>
                            <option value="text-only" <?php selected($cfg['style'],'text-only'); ?>>Sadece Metin</option>
                        </select>
                    </div>

                    <!-- Size -->
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Boyut</label>
                        <select id="sh-ss-size" class="sh-input sh-w-full">
                            <option value="sm" <?php selected($cfg['size'],'sm'); ?>>Küçük</option>
                            <option value="md" <?php selected($cfg['size'],'md'); ?>>Normal</option>
                            <option value="lg" <?php selected($cfg['size'],'lg'); ?>>Büyük</option>
                        </select>
                    </div>
                </div>

                <!-- Layout -->
                <div style="margin-bottom:16px">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Düzen</label>
                    <div style="display:flex;gap:8px">
                        <?php foreach(['horizontal'=>'↔ Yatay','vertical'=>'↕ Dikey'] as $v=>$l): ?>
                        <label style="display:flex;align-items:center;gap:5px;padding:6px 12px;
                                      border:2px solid <?php echo $cfg['layout']===$v?'#2271b1':'#e5e7eb'; ?>;border-radius:6px;
                                      cursor:pointer;font-size:12px;font-weight:500;
                                      background:<?php echo $cfg['layout']===$v?'#eff6ff':'#fff'; ?>">
                            <input type="radio" name="sh_ss_layout" value="<?php echo $v; ?>" <?php checked($cfg['layout'],$v); ?> style="margin:0">
                            <?php echo $l; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="sh-divider">

                <!-- ── Renk Ayarları ─────────────────────────────────── -->
                <?php
                $cc = $cfg; // color config shortcut
                $color_opt_html = function( string $id, string $label, string $val, string $custom_val, array $opts = ['brand','custom'], array $opt_labels = ['Marka Rengi','Özel'] ) use ($cc): void {
                    echo '<div>';
                    echo '<label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">' . esc_html($label) . '</label>';
                    echo '<div style="display:flex;gap:6px;align-items:center">';
                    echo '<select id="sh-ss-' . esc_attr($id) . '" class="sh-input" style="width:140px">';
                    foreach ( $opts as $i => $o ) {
                        $sel = $val === $o ? ' selected' : '';
                        echo '<option value="' . esc_attr($o) . '"' . $sel . '>' . esc_html($opt_labels[$i]) . '</option>';
                    }
                    echo '</select>';
                    echo '<input type="color" id="sh-ss-' . esc_attr($id) . '-color" value="' . esc_attr($custom_val ?: '#374151') . '"';
                    echo ' style="width:36px;height:32px;padding:2px;border:1px solid #e5e7eb;border-radius:4px;cursor:pointer;';
                    echo $val !== 'custom' ? 'display:none' : '';
                    echo '">';
                    echo '</div></div>';
                };
                ?>

                <!-- Button view renkler -->
                <div id="sh-ss-btn-colors" style="<?php echo ($cfg['view']??'button') === 'solid' ? 'display:none' : ''; ?>">
                    <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px">Button Renkleri</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                        <?php
                        $color_opt_html('btn-bg',         'Arka Plan',            $cc['btn_bg'],         $cc['btn_bg_color']);
                        $color_opt_html('btn-bg-hover',   'Arka Plan (hover)',    $cc['btn_bg_hover'],   $cc['btn_bg_hover_color']);
                        $color_opt_html('btn-text',       'İkon/Metin Rengi',     $cc['btn_text'],       $cc['btn_text_color'],   ['white','brand','custom'], ['Beyaz','Marka Rengi','Özel']);
                        $color_opt_html('btn-text-hover', 'İkon/Metin (hover)',   $cc['btn_text_hover'], $cc['btn_text_hover_color'], ['white','brand','custom'], ['Beyaz','Marka Rengi','Özel']);
                        ?>
                    </div>
                </div>

                <!-- Solid view renkler -->
                <div id="sh-ss-solid-colors" style="<?php echo ($cfg['view']??'button') !== 'solid' ? 'display:none' : ''; ?>">
                    <div class="sh-text-xs sh-font-semibold sh-text-label sh-mb-sm" style="text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb;padding-bottom:6px">Solid Renkleri</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                        <?php
                        $color_opt_html('solid-color',       'İkon Rengi',          $cc['solid_color'], $cc['solid_color_val']);
                        $color_opt_html('solid-color-hover', 'İkon Rengi (hover)', $cc['solid_hover'], $cc['solid_hover_val']);
                        ?>
                    </div>
                </div>

                <hr class="sh-divider">

                <!-- ── Canlı Preview (sortable) ──────────────────── -->
                <div style="margin-bottom:16px">
                    <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">Önizleme <span style="font-weight:400;color:#9ca3af;font-size:11px">— sürükle-bırak ile sırala</span></label>
                    <div id="sh-ss-preview" style="padding:12px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;min-height:52px;line-height:1;overflow:hidden"></div>
                </div>

                <hr class="sh-divider">

                <!-- Diğer -->
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-ss-show-count" <?php checked($cfg['show_count']); ?>><span class="sh-toggle-slider"></span></label>
                    <label for="sh-ss-show-count" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">Paylaşım Sayacı</label>
                </div>
                <div class="sh-flex sh-items-center sh-gap-sm sh-mb-md">
                    <label class="sh-toggle"><input type="checkbox" id="sh-ss-ga4" <?php checked($cfg['ga4_tracking']); ?>><span class="sh-toggle-slider"></span></label>
                    <label for="sh-ss-ga4" class="sh-text-base sh-font-medium sh-text-label" style="cursor:pointer">GA4 Event Tracking</label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">X / Twitter: via</label>
                        <input type="text" id="sh-ss-via" value="<?php echo esc_attr($cfg['via']); ?>" class="sh-input sh-w-full" placeholder="ornekkullanici">
                    </div>
                    <div>
                        <label class="sh-text-sm sh-font-semibold sh-text-label sh-block sh-mb-xs">X / Twitter: Hashtag'ler</label>
                        <input type="text" id="sh-ss-hashtags" value="<?php echo esc_attr($cfg['hashtags']); ?>" class="sh-input sh-w-full" placeholder="wordpress,share">
                    </div>
                </div>

                <!-- Kullanım kodu -->
                <div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:16px">
                    <span class="sh-text-sm sh-font-semibold sh-text-label">Kullanım</span>
                    <textarea readonly onclick="this.select()" style="width:100%;height:70px;font-family:monospace;font-size:11px;padding:6px;border:1px solid #ddd;border-radius:4px;background:#fff;resize:none;margin-top:6px"><?php echo esc_textarea(
"{% do action('sh_social_share', post.id) %}\n{{ sh_social_share()|raw }}\n[sh_social_share networks=\"facebook,x,whatsapp\"]"
                    ); ?></textarea>
                </div>

                <div class="sh-flex sh-items-center sh-gap-sm">
                    <button type="button" id="sh-gs-save-social" class="sh-btn sh-btn-primary">Kaydet</button>
                    <span id="sh-gs-social-msg" class="sh-text-sm sh-text-success" style="display:none">✔ Kaydedildi</span>
                </div>

            </div>
        </div>

        <style>
        #sh-ss-preview { padding:12px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; min-height:52px; font-size:0; /* inline-block gap sıfırla */ }
        .sh-ss-preview-btn { display:inline-flex !important; align-items:center; justify-content:center; vertical-align:middle; cursor:grab; user-select:none; box-sizing:border-box; text-decoration:none; font-weight:600; transition:.15s; margin:3px; }
        .sh-ss-preview-btn:active { cursor:grabbing; }
        .sh-ss-drag-over { outline:2px dashed #2271b1 !important; outline-offset:2px; opacity:.6; }
        </style>
        <script>
        (function($){
            var NONCE = <?php echo wp_json_encode($nonce); ?>;
            var AJAX  = <?php echo wp_json_encode($ajax); ?>;

            var NET_DATA = <?php
                $nd = [];
                foreach ( SocialShare::networks() as $k => $n ) {
                    $nd[$k] = [
                        'label' => $n['label'],
                        'color' => $n['color'],
                        'svg'   => fa_icon( $n['icon'], $n['icon_style'] ?? 'solid', '' ),
                    ];
                }
                echo wp_json_encode($nd);
            ?>;

            var savedOrder = <?php echo wp_json_encode( $selected ); ?>;
            var $netSelect = $('#sh-ss-networks-select');

            // ── helpers ───────────────────────────────────────────────
            function getView()   { return $('input[name="sh_ss_view"]:checked').val()   || 'button'; }
            function getStyle()  { return $('#sh-ss-style').val()                        || 'icon-only'; }
            function getSize()   { return $('#sh-ss-size').val()                         || 'md'; }
            function getLayout() { return $('input[name="sh_ss_layout"]:checked').val()  || 'horizontal'; }

            var BTN_PX  = {sm:'28px', md:'36px', lg:'44px'};
            var ICON_PX = {sm:'13px', md:'15px', lg:'18px'};
            var FONT_PX = {sm:'11px', md:'12px', lg:'14px'};
            var SOLID_ICON_PX = {sm:'16px', md:'20px', lg:'24px'};

            function resolveColor(selId, brand) {
                var v = $('#sh-ss-' + selId).val() || 'brand';
                if (v === 'brand')  return brand;
                if (v === 'white')  return '#ffffff';
                return $('#sh-ss-' + selId + '-color').val() || brand;
            }

            function getBtnColors(key) {
                var net   = NET_DATA[key] || {};
                var brand = net.color || '#374151';
                var view  = getView();
                var bg, fg;
                if (view === 'solid') {
                    bg = 'transparent';
                    fg = resolveColor('solid-color', brand);
                } else {
                    bg  = resolveColor('btn-bg', brand);
                    var tv = $('#sh-ss-btn-text').val() || 'white';
                    fg  = tv === 'white' ? '#fff' : tv === 'brand' ? brand : ($('#sh-ss-btn-text-color').val() || '#fff');
                }
                return { bg: bg, fg: fg };
            }

            // ── buton DOM oluştur ─────────────────────────────────────
            function makeBtn(key) {
                var net   = NET_DATA[key];
                if (!net) return null;
                var view  = getView();
                var style = getStyle();
                var size  = getSize();
                var c     = getBtnColors(key);
                var isSolid = (view === 'solid');
                var iconPx  = isSolid ? (SOLID_ICON_PX[size] || '20px') : (ICON_PX[size] || '15px');

                var $btn = $('<a>', {
                    href:       '#',
                    'class':    'sh-ss-preview-btn',
                    'data-key': key,
                    title:      net.label
                }).css({
                    gap:          '5px',
                    width:        isSolid ? 'auto' : (style === 'icon-only' ? BTN_PX[size] : 'auto'),
                    height:       isSolid ? 'auto' : BTN_PX[size],
                    padding:      isSolid ? '4px'  : (style === 'icon-only' ? '0' : '0 12px'),
                    background:   c.bg,
                    color:        c.fg,
                    border:       isSolid ? 'none' : '2px solid transparent',
                    borderRadius: isSolid ? '4px'  : '6px',
                    fontSize:     FONT_PX[size],
                });

                if (style !== 'text-only' && net.svg) {
                    var $ic = $('<span>').css({
                        display:'inline-flex', alignItems:'center', justifyContent:'center',
                        flexShrink:'0', width:iconPx, height:iconPx, color:c.fg, pointerEvents:'none',
                    }).html(net.svg);
                    $ic.find('svg').css({ width:iconPx, height:iconPx, fill:'currentColor', display:'block', pointerEvents:'none' });
                    $btn.append($ic);
                }
                if (style === 'icon-text' || style === 'text-only') {
                    $btn.append($('<span>').css('pointer-events','none').text(net.label));
                }
                return $btn;
            }

            // ── preview yeniden kur ────────────────────────────────────
            function rebuildPreview() {
                var prev = document.getElementById('sh-ss-preview');
                if (!prev) return;

                // Mevcut sırayı koru, seçime göre filtrele
                var existing = [];
                prev.querySelectorAll('.sh-ss-preview-btn').forEach(function(b){ existing.push(b.getAttribute('data-key')); });
                var nowSel = [];
                $netSelect.find('option:selected').each(function(){ nowSel.push(this.value); });
                var merged = existing.filter(function(k){ return nowSel.indexOf(k) >= 0; });
                nowSel.forEach(function(k){ if (merged.indexOf(k) < 0) merged.push(k); });

                $(prev).empty();
                merged.forEach(function(key) {
                    var $b = makeBtn(key);
                    if ($b) $(prev).append($b);
                });
                if (!merged.length) {
                    $(prev).append($('<span>').css({fontSize:'12px',color:'#9ca3af',display:'inline-block'}).text('Ag secilmedi'));
                }
                bindDrag(prev);
            }

            // ── sadece renk/boyut güncelle (drag sonrası sırayı bozmaz) ──
            function refreshBtnStyles() {
                var prev = document.getElementById('sh-ss-preview');
                if (!prev) return;
                var view  = getView();
                var style = getStyle();
                var size  = getSize();
                var isSolid = (view === 'solid');
                prev.querySelectorAll('.sh-ss-preview-btn').forEach(function(btn) {
                    var key    = btn.getAttribute('data-key');
                    var c      = getBtnColors(key);
                    var iconPx = isSolid ? (SOLID_ICON_PX[size] || '20px') : (ICON_PX[size] || '15px');
                    btn.style.width        = isSolid ? 'auto' : (style === 'icon-only' ? BTN_PX[size] : 'auto');
                    btn.style.height       = isSolid ? 'auto' : BTN_PX[size];
                    btn.style.padding      = isSolid ? '4px'  : (style === 'icon-only' ? '0' : '0 12px');
                    btn.style.background   = c.bg;
                    btn.style.color        = c.fg;
                    btn.style.border       = isSolid ? 'none' : '2px solid transparent';
                    btn.style.borderRadius = isSolid ? '4px'  : '6px';
                    btn.style.fontSize     = FONT_PX[size];
                    var ic = btn.querySelector('span');
                    if (ic) {
                        ic.style.width  = iconPx; ic.style.height = iconPx; ic.style.color = c.fg;
                        var svg = ic.querySelector('svg');
                        if (svg) { svg.style.width = iconPx; svg.style.height = iconPx; }
                    }
                });
            }

            // ── HTML5 drag & drop ──────────────────────────────────────
            function bindDrag(prev) {
                var dragSrc = null;
                prev.querySelectorAll('.sh-ss-preview-btn').forEach(function(btn) {
                    btn.setAttribute('draggable', 'true');
                    btn.ondragstart  = function(e) {
                        dragSrc = this;
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', this.getAttribute('data-key') || '');
                        var el = this; setTimeout(function(){ el.style.opacity = '0.35'; }, 0);
                    };
                    btn.ondragend    = function() {
                        this.style.opacity = '';
                        prev.querySelectorAll('.sh-ss-preview-btn').forEach(function(b){ b.classList.remove('sh-ss-drag-over'); });
                    };
                    btn.ondragover   = function(e) { e.preventDefault(); e.dataTransfer.dropEffect='move'; };
                    btn.ondragenter  = function(e) { e.preventDefault(); if (this !== dragSrc) this.classList.add('sh-ss-drag-over'); };
                    btn.ondragleave  = function()  { this.classList.remove('sh-ss-drag-over'); };
                    btn.ondrop       = function(e) {
                        e.stopPropagation(); e.preventDefault();
                        this.classList.remove('sh-ss-drag-over');
                        if (!dragSrc || dragSrc === this) return;
                        var btns = Array.from(prev.querySelectorAll('.sh-ss-preview-btn'));
                        if (btns.indexOf(dragSrc) < btns.indexOf(this)) {
                            prev.insertBefore(dragSrc, this.nextSibling);
                        } else {
                            prev.insertBefore(dragSrc, this);
                        }
                    };
                });
            }

            // ── event bağlantıları ─────────────────────────────────────
            $netSelect.on('change', rebuildPreview);

            $('input[name="sh_ss_view"]').on('change', function() {
                var isSolid = this.value === 'solid';
                $('input[name="sh_ss_view"]').each(function() {
                    var lbl = this.closest('label');
                    if (lbl) { lbl.style.borderColor = this.checked?'#2271b1':'#e5e7eb'; lbl.style.background = this.checked?'#eff6ff':'#fff'; }
                });
                $('#sh-ss-btn-colors').toggle(!isSolid);
                $('#sh-ss-solid-colors').toggle(isSolid);
                $('#sh-ss-style-wrap').css({opacity:isSolid?.4:1,'pointer-events':isSolid?'none':'auto'});
                refreshBtnStyles();
            });

            $('input[name="sh_ss_layout"]').on('change', function() {
                $('input[name="sh_ss_layout"]').each(function() {
                    var lbl = this.closest('label');
                    if (lbl) { lbl.style.borderColor = this.checked?'#2271b1':'#e5e7eb'; lbl.style.background = this.checked?'#eff6ff':'#fff'; }
                });
            });

            // Boyut + stil degisince rebuildPreview (icon px de degisiyor)
            $('#sh-ss-style, #sh-ss-size').on('change', rebuildPreview);

            // Renk secenekleri
            ['btn-bg','btn-bg-hover','btn-text','btn-text-hover','solid-color','solid-color-hover'].forEach(function(id) {
                var $sel = $('#sh-ss-' + id);
                var $inp = $('#sh-ss-' + id + '-color');
                $sel.on('change', function() {
                    if (this.value === 'custom') $inp.show(); else $inp.hide();
                    refreshBtnStyles();
                });
                $inp.on('input', refreshBtnStyles);
            });

            // ── ilk yukle ─────────────────────────────────────────────
            setTimeout(function() {
                var prev = document.getElementById('sh-ss-preview');
                if (!prev) return;
                savedOrder.forEach(function(key) {
                    var $b = makeBtn(key);
                    if ($b) $(prev).append($b);
                });
                if (!savedOrder.length) {
                    $(prev).innerHTML = '';
                    $(prev).append($('<span>').css({fontSize:'12px',color:'#9ca3af',display:'inline-block'}).text('Ag secilmedi'));
                }
                bindDrag(prev);
            }, 200);

            // ── kaydet ────────────────────────────────────────────────
            $('#sh-gs-save-social').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Kaydediliyor...');
                var networks = [];
                document.querySelectorAll('#sh-ss-preview .sh-ss-preview-btn').forEach(function(b){ networks.push(b.getAttribute('data-key')); });
                if (!networks.length) $netSelect.find('option:selected').each(function(){ networks.push(this.value); });

                var fd = new FormData();
                fd.append('action',               'sh_gs_save_social_share');
                fd.append('nonce',                NONCE);
                fd.append('enabled',              $('#sh-ss-enabled')[0].checked    ? '1':'0');
                fd.append('show_count',           $('#sh-ss-show-count')[0].checked ? '1':'0');
                fd.append('ga4_tracking',         $('#sh-ss-ga4')[0].checked        ? '1':'0');
                fd.append('view',                 getView());
                fd.append('style',                getStyle());
                fd.append('size',                 getSize());
                fd.append('layout',               getLayout());
                fd.append('btn_bg',               $('#sh-ss-btn-bg').val());
                fd.append('btn_bg_color',         $('#sh-ss-btn-bg-color').val());
                fd.append('btn_bg_hover',         $('#sh-ss-btn-bg-hover').val());
                fd.append('btn_bg_hover_color',   $('#sh-ss-btn-bg-hover-color').val());
                fd.append('btn_text',             $('#sh-ss-btn-text').val());
                fd.append('btn_text_color',       $('#sh-ss-btn-text-color').val());
                fd.append('btn_text_hover',       $('#sh-ss-btn-text-hover').val());
                fd.append('btn_text_hover_color', $('#sh-ss-btn-text-hover-color').val());
                fd.append('solid_color',          $('#sh-ss-solid-color').val());
                fd.append('solid_color_val',      $('#sh-ss-solid-color-color').val());
                fd.append('solid_hover',          $('#sh-ss-solid-color-hover').val());
                fd.append('solid_hover_val',      $('#sh-ss-solid-color-hover-color').val());
                fd.append('via',                  $('#sh-ss-via').val());
                fd.append('hashtags',             $('#sh-ss-hashtags').val());
                networks.forEach(function(n){ fd.append('networks[]', n); });

                fetch(AJAX, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(res) {
                    $btn.prop('disabled', false).text('Kaydet');
                    var $m = $('#sh-gs-social-msg');
                    $m.text(res.success ? '\u2714 Kaydedildi' : '\u2716 Hata').show();
                    setTimeout(function(){ $m.hide(); }, 2800);
                });
            });

        })(jQuery);
        </script>
        <?php
    }
}
