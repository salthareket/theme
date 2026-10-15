<?php

namespace SaltHareket\Sms\Admin;

use SaltHareket\Sms\SmsSettings;
use SaltHareket\Sms\SmsManager;

/**
 * SmsAdmin — Bağımsız SMS yönetim sayfası.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-06-18
 *     - Add: Initial release — notifications/Admin'den ayrıldı
 *     - Add: Bağımsız admin sayfası (sh-sms)
 *     - Add: Provider seçimi, credential yönetimi, OTP ayarları, SMS gönderme
 *
 * @package SaltHareket\Sms
 */
class SmsAdmin
{
    public static function register(): void
    {
        // add_action( 'admin_menu',            [ self::class, 'addMenuPage' ], 25 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_head',            [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_sh_sms_save_settings',  [ self::class, 'ajaxSaveSettings' ] );
        add_action( 'wp_ajax_sh_sms_send',           [ self::class, 'ajaxSend' ] );
        add_action( 'wp_ajax_sh_sms_check_balance',  [ self::class, 'ajaxCheckBalance' ] );
        add_action( 'wp_ajax_sh_sms_search_users',   [ self::class, 'ajaxSearchUsers' ] );
        add_action( 'wp_ajax_sh_sms_toggle',         [ self::class, 'ajaxToggle' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '📱 SMS',
            '📱 SMS',
            'manage_options',
            'sh-sms',
            [ self::class, 'renderPage' ]
        );
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-sms' ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-sms' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    // ─── MAIN PAGE ───────────────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $nonce    = wp_create_nonce( 'sh_sms_nonce' );
        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
        ?>
        <div class="sh-wrap" id="sh-sms-page">
        <div class="sh-toolbar">
            <?php $sms_badge_enabled = SmsSettings::get()['enabled'] ?? false; ?>
            <h1 style="display:inline-flex;align-items:center;gap:8px;">&#128241; SMS
                <span class="sh-badge <?php echo $sms_badge_enabled ? 'sh-badge-green' : 'sh-badge-gray'; ?>" style="font-size:11px"><?php echo $sms_badge_enabled ? 'Enabled' : 'Disabled'; ?></span>
            </h1>
        </div>
        <?php self::renderSmsContent( $nonce, $ajax_url ); ?>
        <div id="sh-toast"></div>
        </div>
        <?php
        self::renderToastScript();
    }

    // ─── SMS CONTENT (aynı UI, bağımsız sayfada) ─────────────────────────────

    private static function renderSmsContent( string $nonce, string $ajax_url ): void
    {
        $settings  = SmsSettings::get();
        $enabled   = (bool) ( $settings['enabled'] ?? false );
        $providers = SmsManager::providers();
        $active    = $settings['provider'] ?? 'd7';
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- ENABLE TOGGLE -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px;border-bottom:none">
                <h3 style="margin:0">SMS</h3>
                <label class="sh-toggle">
                    <input type="checkbox" id="sms-enabled" <?php checked( $enabled ); ?> onchange="shSmsToggle(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span id="sms-status-label" style="font-size:12px;color:<?php echo $enabled ? '#00a32a' : '#9ca3af'; ?>">
                    <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                </span>
                <span id="sms-status-saving" style="display:none;font-size:12px;color:#9ca3af;margin-left:4px">
                    <span style="display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#2271b1;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:4px"></span>Saving...
                </span>
            </div>
        </div>

        <!-- PROVIDER SEÇİMİ -->
        <div class="sh-card" id="sh-sms-content" style="margin-bottom:20px;<?php echo ! $enabled ? 'display:none' : ''; ?>">
            <div class="sh-card-header"><h3 style="margin:0">Provider</h3></div>
            <div class="sh-card-body">
                <div class="sh-provider-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px">
                <?php foreach ( $providers as $key => $p ) :
                    $caps      = $p['capabilities'];
                    $is_active = ( $key === $active );
                    $otp_ok    = ! empty( $caps['otp'] );
                    $global    = ( $caps['coverage'] ?? '' ) === 'global';
                ?>
                    <div class="sh-provider-card <?php echo $is_active ? 'sh-provider-active' : ''; ?>"
                         data-provider="<?php echo esc_attr( $key ); ?>"
                         onclick="shSelectProvider('<?php echo esc_js( $key ); ?>')"
                         style="border:2px solid <?php echo $is_active ? '#2271b1' : '#ddd'; ?>;border-radius:8px;padding:14px;cursor:pointer;transition:all .2s;background:<?php echo $is_active ? '#f0f6ff' : '#fff'; ?>">
                        <div style="font-weight:600;font-size:14px;margin-bottom:8px"><?php echo esc_html( $p['label'] ); ?></div>
                        <div style="display:flex;flex-wrap:wrap;gap:4px">
                            <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:<?php echo $otp_ok ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $otp_ok ? '#166534' : '#991b1b'; ?>">
                                <?php echo $otp_ok ? '✅ OTP' : '❌ OTP'; ?>
                            </span>
                            <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:<?php echo $global ? '#dbeafe' : '#fef9c3'; ?>;color:<?php echo $global ? '#1e40af' : '#854d0e'; ?>">
                                <?php echo $global ? '🌍 Global' : '🇹🇷 TR Only'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <input type="hidden" id="sms-provider" value="<?php echo esc_attr( $active ); ?>">
            </div>
        </div>

        <!-- PROVIDER AYARLARI -->
        <div id="sh-sms-extra-content" style="<?php echo ! $enabled ? 'display:none' : ''; ?>">
        <?php foreach ( $providers as $key => $p ) :
            $caps   = $p['capabilities'];
            $cfg    = $settings[$key] ?? [];
            $hidden = ( $key !== $active ) ? 'style="display:none"' : '';
        ?>
        <div class="sh-card sh-provider-fields" id="sms-fields-<?php echo esc_attr( $key ); ?>" <?php echo $hidden; ?> style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0"><?php echo esc_html( $p['label'] ); ?> — Credentials</h3></div>
            <div class="sh-card-body">
                <?php if ( $key === 'twilio' ) : ?>
                <div style="padding:10px 14px;background:#fef9c3;border-radius:6px;font-size:12px;color:#854d0e;margin-bottom:16px">
                    ⚠️ <strong>Trial hesap:</strong> "From" alanına Twilio Console'dan aldığın trial numaranı gir.
                </div>
                <?php endif; ?>
                <?php if ( ! empty( $caps['auth_fields'] ) ) : ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <?php foreach ( $caps['auth_fields'] as $field_key => $field ) :
                    $field_val   = $cfg[$field_key] ?? '';
                    $is_checkbox = ( $field['type'] === 'checkbox' );
                ?>
                    <div class="sh-field" <?php echo $is_checkbox ? 'style="grid-column:1/-1"' : ''; ?>>
                        <?php if ( $is_checkbox ) : ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox" data-provider="<?php echo esc_attr( $key ); ?>" data-field="<?php echo esc_attr( $field_key ); ?>" <?php checked( ! empty( $field_val ) ); ?> style="width:16px;height:16px">
                                <span style="font-size:13px;font-weight:600"><?php echo esc_html( $field['label'] ); ?></span>
                            </label>
                            <?php if ( ! empty( $field['description'] ) ) : ?>
                                <p style="font-size:11px;color:#9ca3af;margin:4px 0 0 24px"><?php echo esc_html( $field['description'] ); ?></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px"><?php echo esc_html( $field['label'] ); ?></label>
                            <input type="<?php echo esc_attr( $field['type'] ); ?>" class="sh-input"
                                   data-provider="<?php echo esc_attr( $key ); ?>"
                                   data-field="<?php echo esc_attr( $field_key ); ?>"
                                   value="<?php echo esc_attr( (string) $field_val ); ?>"
                                   placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                                   style="width:100%">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shSmsSaveSettings()">💾 Save</button>
                    <button type="button" class="sh-btn" onclick="shSmsCheckBalance()" style="background:#f0f0f0">💰 Balance</button>
                    <span class="sms-save-status" style="font-size:12px;color:#666"></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- OTP AYARLARI -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0">🔐 OTP Settings</h3></div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Geçerlilik Süresi (sn)</label>
                        <input type="number" id="otp-expiry" class="sh-input" value="<?php echo esc_attr( $settings['otp_expiry'] ?? 300 ); ?>" min="60" max="3600" step="30" style="width:100%">
                    </div>
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Kod Uzunluğu</label>
                        <input type="number" id="otp-length" class="sh-input" value="<?php echo esc_attr( $settings['otp_length'] ?? 6 ); ?>" min="4" max="10" style="width:100%">
                    </div>
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Max Yeniden Gönderim</label>
                        <input type="number" id="otp-max-resend" class="sh-input" value="<?php echo esc_attr( $settings['max_resend'] ?? 5 ); ?>" min="1" max="20" style="width:100%">
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS GÖNDER -->
        <?php self::renderSendForm( $settings ); ?>

        </div><!-- /sh-sms-extra-content -->
        </div><!-- .sh-main -->
        </div><!-- .sh-layout -->

        <?php self::renderScripts( $nonce, $ajax_url ); ?>
        <?php
    }

    private static function renderSendForm( array $settings ): void
    {
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) $wp_roles = new \WP_Roles();
        $roles = [];
        foreach ( $wp_roles->roles as $slug => $role ) {
            $roles[$slug] = $role['name'];
        }
        ?>
        <div class="sh-card">
            <div class="sh-card-header"><h3 style="margin:0">📤 Send SMS</h3></div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Recipients</label>
                        <select id="sms-recipient-type" class="sh-select" onchange="shSmsToggleRecipients()" style="width:100%;margin-bottom:8px">
                            <option value="test">🧪 Test (manual number)</option>
                            <option value="specific">👤 Specific Users</option>
                            <option value="all">📢 All Users (with phone)</option>
                            <option value="role">🎭 By Role</option>
                            <option value="manual">✏️ Manual Numbers</option>
                        </select>
                        <div id="sms-recipients-test">
                            <input type="text" id="sms-test-number" class="sh-input" placeholder="+905551234567" style="width:100%">
                        </div>
                        <div id="sms-recipients-specific" style="display:none">
                            <input type="text" id="sms-user-search" class="sh-input" placeholder="Search users..." oninput="shSmsSearchUsers(this.value)" style="width:100%;margin-bottom:6px">
                            <div id="sms-user-results" style="max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;display:none"></div>
                            <div id="sms-selected-users" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px"></div>
                        </div>
                        <div id="sms-recipients-role" style="display:none">
                            <select id="sms-role" class="sh-select" style="width:100%">
                                <?php foreach ( $roles as $slug => $name ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="sms-recipients-manual" style="display:none">
                            <textarea id="sms-manual-numbers" class="sh-input" rows="3" placeholder="+905551234567&#10;+905559876543" style="width:100%;font-family:monospace"></textarea>
                        </div>
                        <div id="sms-recipients-all" style="display:none">
                            <div style="padding:10px;background:#fef9c3;border-radius:6px;font-size:12px;color:#854d0e">⚠️ Telefon numarası kayıtlı tüm kullanıcılara gönderilecek.</div>
                        </div>
                    </div>
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Message</label>
                        <textarea id="sms-message" class="sh-input" rows="5" placeholder="Mesajınızı yazın..." style="width:100%;resize:vertical"></textarea>
                        <div style="display:flex;justify-content:flex-end;margin-top:4px">
                            <span id="sms-char-count" style="font-size:11px;color:#9ca3af">0 / 160</span>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shSmsSend()">📤 Send</button>
                    <span id="sms-send-status" style="font-size:12px;color:#666"></span>
                </div>
                <div id="sms-send-result" style="margin-top:12px;display:none"></div>
            </div>
        </div>
        <?php
    }

    private static function renderScripts( string $nonce, string $ajax_url ): void
    {
        ?>
        <script>
        var shSmsNonce        = '<?php echo esc_js( $nonce ); ?>';
        var shSmsAjaxUrl      = '<?php echo esc_js( $ajax_url ); ?>';
        var shSmsSelectedUsers = [];

        function shSmsToggle(enabled) {
            ['sh-sms-content','sh-sms-extra-content'].forEach(function(id) {
                document.getElementById(id).style.display = enabled ? '' : 'none';
            });
            var lbl    = document.getElementById('sms-status-label');
            var saving = document.getElementById('sms-status-saving');
            var toggle = document.getElementById('sms-enabled');
            if (lbl)    lbl.style.display    = 'none';
            if (saving) saving.style.display = 'inline-flex';
            fetch(shSmsAjaxUrl, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=sh_sms_toggle&enabled=' + (enabled?1:0) + '&nonce=' + shSmsNonce
            }).then(r=>r.json()).then(function(res) {
                if (saving) saving.style.display = 'none';
                if (lbl) {
                    lbl.style.display = '';
                    lbl.textContent   = enabled ? 'Enabled' : 'Disabled';
                    lbl.style.color   = enabled ? '#00a32a' : '#9ca3af';
                }
                // header badge sync
                var hb = document.querySelector('.sh-toolbar h1 .sh-badge');
                if (hb) { hb.textContent = enabled ? 'Enabled' : 'Disabled'; hb.className = 'sh-badge ' + (enabled ? 'sh-badge-green' : 'sh-badge-gray'); }
                shShowToast(res.success ? (enabled?'&#10003; SMS enabled':'SMS disabled') : ('Error'));
                if (!res.success) {
                    if (toggle) toggle.checked = !enabled;
                    ['sh-sms-content','sh-sms-extra-content'].forEach(function(id) {
                        document.getElementById(id).style.display = !enabled ? '' : 'none';
                    });
                }
            }).catch(function() {
                if (saving) saving.style.display = 'none';
                if (lbl)    lbl.style.display    = '';
                if (toggle) toggle.checked = !enabled;
            });
        }

        function shSelectProvider(key) {
            document.getElementById('sms-provider').value = key;
            document.querySelectorAll('.sh-provider-card').forEach(function(c) {
                var active = c.dataset.provider === key;
                c.style.borderColor = active ? '#2271b1' : '#ddd';
                c.style.background  = active ? '#f0f6ff' : '#fff';
            });
            document.querySelectorAll('.sh-provider-fields').forEach(function(f) {
                f.style.display = f.id === 'sms-fields-' + key ? '' : 'none';
            });
        }

        function shSmsToggleRecipients() {
            var type = document.getElementById('sms-recipient-type').value;
            ['test','specific','role','manual','all'].forEach(function(t) {
                var el = document.getElementById('sms-recipients-' + t);
                if (el) el.style.display = t === type ? '' : 'none';
            });
        }

        function shSmsSearchUsers(q) {
            if (q.length < 2) { document.getElementById('sms-user-results').style.display='none'; return; }
            fetch(shSmsAjaxUrl+'?action=sh_sms_search_users&q='+encodeURIComponent(q)+'&nonce='+shSmsNonce)
                .then(r=>r.json()).then(function(res) {
                    var box = document.getElementById('sms-user-results');
                    if (!res.success||!res.data.length) { box.style.display='none'; return; }
                    box.innerHTML = res.data.map(function(u) {
                        return '<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px" onclick="shSmsAddUser('+u.id+',\''+u.name.replace(/'/g,"\\'")+'\')">'
                            +u.name+' <span style="color:#9ca3af;font-size:11px">'+(u.phone||'no phone')+'</span></div>';
                    }).join('');
                    box.style.display = '';
                });
        }

        function shSmsAddUser(id,name) {
            if (shSmsSelectedUsers.find(u=>u.id===id)) return;
            shSmsSelectedUsers.push({id:id,name:name});
            var box=document.getElementById('sms-selected-users');
            var tag=document.createElement('span');
            tag.style.cssText='display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#e0f2fe;border-radius:12px;font-size:12px';
            tag.innerHTML=name+' <span style="cursor:pointer;color:#666" onclick="shSmsRemoveUser('+id+',this.parentNode)">×</span>';
            box.appendChild(tag);
            document.getElementById('sms-user-results').style.display='none';
            document.getElementById('sms-user-search').value='';
        }

        function shSmsRemoveUser(id,el) { shSmsSelectedUsers=shSmsSelectedUsers.filter(u=>u.id!==id); el.remove(); }

        document.addEventListener('DOMContentLoaded',function(){
            var msg=document.getElementById('sms-message');
            if(msg) msg.addEventListener('input',function(){
                var len=this.value.length;
                var c=document.getElementById('sms-char-count');
                c.textContent=len+' / 160';
                c.style.color=len>160?'#dc2626':'#9ca3af';
            });
        });

        function shSmsSaveSettings() {
            var provider=document.getElementById('sms-provider').value;
            var data={action:'sh_sms_save_settings',nonce:shSmsNonce,provider:provider,
                enabled:document.getElementById('sms-enabled').checked?1:0,
                otp_expiry:document.getElementById('otp-expiry').value,
                otp_length:document.getElementById('otp-length').value,
                max_resend:document.getElementById('otp-max-resend').value};
            document.querySelectorAll('[data-provider][data-field]').forEach(function(el){
                var p=el.dataset.provider,f=el.dataset.field;
                if(!data[p])data[p]={};
                data[p][f]=el.type==='checkbox'?(el.checked?1:0):el.value;
            });
            var statusEl=document.querySelector('#sms-fields-'+provider+' .sms-save-status');
            if(statusEl)statusEl.textContent='Saving...';
            fetch(shSmsAjaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(shFlattenData(data))})
                .then(r=>r.json()).then(function(res){
                    if(statusEl){statusEl.textContent=res.success?'✅ Saved':'❌ '+(res.data||'Error');statusEl.style.color=res.success?'#166534':'#dc2626';setTimeout(()=>{statusEl.textContent='';},3000);}
                    shShowToast(res.success?'✅ Saved':'❌ '+(res.data||'Error'));
                });
        }

        function shFlattenData(obj,prefix){var result={};for(var key in obj){var fullKey=prefix?prefix+'['+key+']':key;if(typeof obj[key]==='object'&&obj[key]!==null){Object.assign(result,shFlattenData(obj[key],fullKey));}else{result[fullKey]=obj[key];}}return result;}

        function shSmsCheckBalance(){
            var provider=document.getElementById('sms-provider').value;
            var statusEl=document.querySelector('#sms-fields-'+provider+' .sms-save-status');
            if(statusEl)statusEl.textContent='Checking...';
            fetch(shSmsAjaxUrl+'?action=sh_sms_check_balance&nonce='+shSmsNonce)
                .then(r=>r.json()).then(function(res){
                    if(statusEl){statusEl.textContent=res.success?'💰 '+(res.data.balance||JSON.stringify(res.data)):'❌ '+(res.data||'Error');statusEl.style.color=res.success?'#166534':'#dc2626';}
                });
        }

        function shSmsSend(){
            var type=document.getElementById('sms-recipient-type').value;
            var message=document.getElementById('sms-message').value.trim();
            var status=document.getElementById('sms-send-status');
            var result=document.getElementById('sms-send-result');
            if(!message){status.textContent='❌ Mesaj boş olamaz';status.style.color='#dc2626';return;}
            var data={action:'sh_sms_send',nonce:shSmsNonce,message:message,recipient_type:type};
            if(type==='test'||type==='manual'){data.numbers=document.getElementById(type==='test'?'sms-test-number':'sms-manual-numbers').value;}
            else if(type==='specific'){data.user_ids=shSmsSelectedUsers.map(u=>u.id).join(',');}
            else if(type==='role'){data.role=document.getElementById('sms-role').value;}
            status.textContent='⏳ Sending...';status.style.color='#666';result.style.display='none';
            fetch(shSmsAjaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)})
                .then(r=>r.json()).then(function(res){
                    if(res.success){
                        status.textContent='✅ Sent to '+(res.data.sent||0)+' recipient(s)';status.style.color='#166534';
                        if(res.data.errors&&res.data.errors.length){result.innerHTML='<div style="padding:10px;background:#fee2e2;border-radius:6px;font-size:12px;color:#991b1b">Errors: '+res.data.errors.join('<br>')+'</div>';result.style.display='';}
                    }else{status.textContent='❌ '+(res.data||'Error');status.style.color='#dc2626';}
                });
        }
        </script>
        <?php
    }

    private static function renderToastScript(): void
    {
        ?>
        <script>
        function shShowToast(msg, type) {
            var container = document.getElementById('sh-toast');
            if (!container) return;
            var el = document.createElement('div');
            el.className = 'sh-toast-item' + (type && type !== 'default' ? ' sh-toast-' + type : ' sh-toast-success');
            el.innerHTML = msg;
            container.appendChild(el);
            setTimeout(function() {
                el.style.opacity = '0';
                el.style.transition = 'opacity .3s';
                setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 320);
            }, 3000);
        }
        </script>
        <?php
    }

    // ─── AJAX HANDLERS ───────────────────────────────────────────────────────

    public static function ajaxToggle(): void
    {
        check_ajax_referer( 'sh_sms_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $enabled = ! empty( $_POST['enabled'] );
        $current = SmsSettings::get();
        $current['enabled'] = $enabled;
        SmsSettings::save( $current );

        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    public static function ajaxSaveSettings(): void
    {
        check_ajax_referer( 'sh_sms_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $data = $_POST;
        unset( $data['action'], $data['nonce'] );
        SmsSettings::save( $data );
        wp_send_json_success( 'Saved' );
    }

    public static function ajaxCheckBalance(): void
    {
        check_ajax_referer( 'sh_sms_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        try {
            $result = SmsManager::driver()->checkBalance();
            $result['error'] ? wp_send_json_error( $result['message'] ) : wp_send_json_success( $result['data'] );
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }

    public static function ajaxSearchUsers(): void
    {
        check_ajax_referer( 'sh_sms_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $q     = sanitize_text_field( $_GET['q'] ?? '' );
        $users = get_users( [ 'search' => "*{$q}*", 'search_columns' => [ 'user_login', 'display_name', 'user_email' ], 'number' => 20, 'fields' => [ 'ID', 'display_name' ] ] );

        $result = [];
        foreach ( $users as $u ) {
            $result[] = [ 'id' => $u->ID, 'name' => $u->display_name, 'phone' => get_user_meta( $u->ID, 'phone', true ) ];
        }

        wp_send_json_success( $result );
    }

    public static function ajaxSend(): void
    {
        check_ajax_referer( 'sh_sms_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $type    = sanitize_key( $_POST['recipient_type'] ?? 'test' );
        $message = sanitize_textarea_field( $_POST['message'] ?? '' );
        if ( empty( $message ) ) wp_send_json_error( 'Message is empty' );

        $numbers = [];

        switch ( $type ) {
            case 'test':
            case 'manual':
                $raw = sanitize_textarea_field( $_POST['numbers'] ?? '' );
                foreach ( preg_split( '/[\r\n,]+/', $raw ) as $n ) {
                    $n = trim( $n );
                    if ( $n ) $numbers[] = $n;
                }
                break;
            case 'specific':
                foreach ( array_filter( array_map( 'intval', explode( ',', $_POST['user_ids'] ?? '' ) ) ) as $uid ) {
                    $phone = get_user_meta( $uid, 'phone', true );
                    if ( $phone ) $numbers[] = $phone;
                }
                break;
            case 'role':
                foreach ( get_users( [ 'role' => sanitize_key( $_POST['role'] ?? '' ), 'fields' => 'ID', 'number' => 500 ] ) as $uid ) {
                    $phone = get_user_meta( $uid, 'phone', true );
                    if ( $phone ) $numbers[] = $phone;
                }
                break;
            case 'all':
                global $wpdb;
                $numbers = array_values( array_filter( $wpdb->get_col(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key='phone' AND meta_value!='' LIMIT 1000"
                ) ) );
                break;
        }

        if ( empty( $numbers ) ) wp_send_json_error( 'No recipients found' );

        $numbers = array_unique( $numbers );
        $errors  = [];
        $sent    = 0;

        try {
            $driver = SmsManager::driver();
            foreach ( array_chunk( $numbers, 50 ) as $chunk ) {
                $result = $driver->send( $chunk, $message );
                if ( ! empty( $result['error'] ) ) {
                    $errors[] = $result['message'];
                } else {
                    $sent += count( $chunk );
                }
            }
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }

        wp_send_json_success( [ 'sent' => $sent, 'errors' => $errors ] );
    }
}
