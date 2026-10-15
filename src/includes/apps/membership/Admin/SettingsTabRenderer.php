<?php
namespace SaltHareket\Membership\Admin;

/**
 * SettingsTabRenderer — Membership Settings sekmesi render'ı
 * Tab yapısı: Forms | Activation | Roles | Account | Integrations
 *
 * @version 2.0.0
 * @package SaltHareket\Membership\Admin
 */
class SettingsTabRenderer
{
    public static function render(): void
    {
        $s     = \SaltHareket\Membership\MembershipSettings::get();
        $nonce = wp_create_nonce( 'sh_membership_admin' );
        $stab  = sanitize_key( $_GET['stab'] ?? 'forms' );

        $act_type     = $s['membership_activation_type'] ?? 'email';
        $pw_type      = $s['password_recover_type']      ?? 'link';
        $default_role = $s['default_role']               ?? get_option( 'default_role', 'subscriber' );
        $custom_roles = $s['custom_roles']               ?? [];
        $profile_type = $s['profile_url_type']           ?? 'standard';
        $profile_pfx  = $s['profile_url_prefix']         ?? 'profile';
        $sel_roles    = $s['selectable_roles']            ?? [];

        // All WP roles for select
        $all_roles    = wp_roles()->roles;
        ?>
        <div class="sh-layout">
        <div class="sh-main">
        <div id="sh-membership-toast" style="position:fixed;bottom:24px;right:24px;z-index:9999"></div>

        <!-- Settings Tabs -->
        <div id="shm-membership-opts" style="<?php echo ! $s['enable_membership'] ? 'display:none' : ''; ?>">

        <!-- Tab Nav — JS ile çalışır, sayfa yenileme yok -->
        <div class="sh-toolbar" style="margin-bottom:0;border-bottom-left-radius:0;border-bottom-right-radius:0">
            <div class="sh-toolbar-right" style="flex:1">
                <?php foreach ( [ 'forms'=>'Forms', 'register-fields'=>'Register Fields', 'roles'=>'Roles', 'account'=>'Account', 'security'=>'Login Security', 'integrations'=>'Integrations' ] as $slug => $label ) : ?>
                <button type="button" class="sh-tab-btn <?php echo $stab === $slug ? 'active' : ''; ?>"
                        onclick="shmSwitchTab('<?php echo $slug; ?>')"
                        id="shm-tab-btn-<?php echo $slug; ?>"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sh-card" style="border-top-left-radius:0;border-top-right-radius:0">
        <div style="padding:20px">
        <?php /* ─── FORMS TAB ─── */ ?>
        <div id="shm-stab-forms" class="shm-stab-panel" style="<?php echo $stab !== 'forms' ? 'display:none' : ''; ?>"><?php if(true): ?>
        <!-- ═══ FORMS TAB ═══ -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Registration & Login</span>
            </div>
            <div class="sh-card-body">
                <?php
                    $toggles = [
                        [ 'shm-enable-registration',  'enable_registration',  'Enable Registration',       'Allow new users to register' ],
                        [ 'shm-enable-remember-login','enable_remember_login','Enable Remember Login',      'Show "Remember Me" checkbox on login form' ],
                        [ 'shm-enable-postcode',      'enable_postcode_validation','Enable Postcode Validation','Require valid postcode on registration' ],
                    ];
                    foreach ( $toggles as [ $id, $key, $label, $desc ] ) : ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                        <label class="sh-toggle">
                            <input type="checkbox" id="<?php echo $id; ?>" <?php checked( $s[$key] ?? false ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <div>
                            <span style="font-size:13px;font-weight:500"><?php echo $label; ?></span>
                            <br><span style="font-size:11px;color:#9ca3af"><?php echo $desc; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
            </div>
        </div>

        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Password Recovery</span>
            </div>
            <div class="sh-card-body">

                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                        <label class="sh-toggle">
                            <input type="checkbox" id="shm-enable-lost-password" <?php checked( $s['enable_lost_password'] ?? false ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <span style="font-size:13px;font-weight:500">Enable Lost Password</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                        <label class="sh-toggle">
                            <input type="checkbox" id="shm-enable-password-recover" <?php checked( $s['enable_password_recover'] ?? false ); ?> onchange="shmToggle('password_recover',this.checked)">
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <span style="font-size:13px;font-weight:500">Enable Password Recover</span>
                    </div>
                    <div id="shm-pw-recover-opts" style="margin-left:44px;<?php echo empty( $s['enable_password_recover'] ) ? 'display:none' : ''; ?>">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin-bottom:6px">
                            <input type="radio" name="shm_pw_recover_type" value="link" <?php checked( $pw_type, 'link' ); ?>>
                            Send link to create new password
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                            <input type="radio" name="shm_pw_recover_type" value="generated" <?php checked( $pw_type, 'generated' ); ?>>
                            Send a new generated password
                        </label>
                    </div>
            </div>
        </div>

        <?php endif; ?></div><!-- /shm-stab-forms -->

        <?php /* ─── REGISTER FIELDS TAB ─── */ ?>
        <div id="shm-stab-register-fields" class="shm-stab-panel" style="<?php echo $stab !== 'register-fields' ? 'display:none' : ''; ?>"><?php if(true): ?>
        <!-- ═══ REGISTER FIELDS TAB ═══ -->

        <?php
        $rf      = \SaltHareket\Membership\MembershipSettings::getSetting( 'register_fields' );
        global $wpdb;
        $has_loc = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}countries'" );

        // Built-in field özellikleri (bunlar type-lock — değiştirilemez)
        $builtin_meta = [
            'first_name'    => [ 'label_default' => 'First Name',   'type_lock' => 'text',    'needs_loc' => false, 'icon' => '👤' ],
            'last_name'     => [ 'label_default' => 'Last Name',    'type_lock' => 'text',    'needs_loc' => false, 'icon' => '👤' ],
            'phone'         => [ 'label_default' => 'Phone Number', 'type_lock' => 'phone',   'needs_loc' => true,  'icon' => '📱' ],
            'country'       => [ 'label_default' => 'Country',      'type_lock' => 'country', 'needs_loc' => true,  'icon' => '🌍' ],
            'state'         => [ 'label_default' => 'State / City', 'type_lock' => 'state',   'needs_loc' => true,  'icon' => '📍' ],
        ];

        // WooCommerce attribute'larını çek
        $wc_attributes = [];
        if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
            foreach ( wc_get_attribute_taxonomies() as $attr ) {
                $wc_attributes[ 'pa_' . $attr->attribute_name ] = $attr->attribute_label;
            }
        }

        // Field tipi seçenekleri
        $type_options = [
            'text'               => '📝 Text input',
            'custom_select'      => '🔽 Custom select/radio/checkbox',
            'product_attributes' => '🏷️ WooCommerce product attribute',
        ];
        ?>

        <?php if ( ! $has_loc ) : ?>
        <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:13px">
            ⚠️ <strong>Localization tables not found.</strong>
            Phone, Country, State fields require <code>wp_countries</code>.
            <a href="<?php echo esc_url( admin_url('admin.php?page=sh-localization&tab=database') ); ?>" style="color:#2271b1">Install Localization data →</a>
        </div>
        <?php endif; ?>

        <div class="sh-card">
            <div class="sh-card-header--col" style="display:flex;flex-direction:row;align-items:center">
                <div style="display:flex;flex-direction:column">
                    <span class="sh-card-title">Registration Form Fields</span>
                    <span class="sh-card-desc">
                        Enabled fields are injected via <code>do_action('sh_register_form_field_{key}')</code> in twig.
                        The register form loops all enabled fields automatically.
                    </span>
                </div>
                <button type="button" class="sh-btn sh-btn-primary sh-btn-sm ms-auto" onclick="shmRfAddField()">+ Add Field</button>
            </div>
            <div class="sh-card-body">

                <div id="shm-rf-list">
                <?php foreach ( $rf as $key => $fcfg ) :
                    $is_builtin       = isset( $builtin_meta[ $key ] );
                    $enabled          = (bool) ( $fcfg['enabled']          ?? false );
                    $required         = (bool) ( $fcfg['required']         ?? false );
                    $show_on_register = isset( $fcfg['show_on_register'] ) ? (bool) $fcfg['show_on_register'] : true;
                    $f_type           = $fcfg['type']    ?? ( $is_builtin ? $builtin_meta[$key]['type_lock'] : 'text' );
                    $f_label          = $fcfg['label']   ?? '';
                    $f_ui_type        = $fcfg['ui_type'] ?? 'select';
                    $f_options_source = $fcfg['options_source'] ?? 'custom';
                    $f_attr_slug      = $fcfg['attribute_slug'] ?? '';
                    $f_options        = $fcfg['options'] ?? [];
                    $loc_blocked      = $is_builtin && ( $builtin_meta[$key]['needs_loc'] ?? false ) && ! $has_loc;

                    // Options'ı textarea formatına çevir: val:Label
                    $opts_textarea = '';
                    foreach ( $f_options as $ov => $ol ) {
                        $opts_textarea .= ( $ov !== '' ? $ov . ':' . $ol : $ol ) . "\n";
                    }
                    $opts_textarea = trim( $opts_textarea );

                    // Label placeholder
                    $label_placeholder = $is_builtin
                        ? ( $builtin_meta[$key]['label_default'] ?? ucwords( str_replace( '_', ' ', $key ) ) )
                        : ucwords( str_replace( '_', ' ', $key ) );
                ?>
                <?php /* ── FIELD CARD ── */ ?>
                <div class="shm-rf-row" data-field="<?php echo esc_attr( $key ); ?>"
                     style="padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:10px;<?php echo $loc_blocked ? 'opacity:.5' : ''; ?>">

                    <!-- Top row: toggle + field name + type badge + remove -->
                    <div style="display:flex;align-items:center;gap:10px">
                        <label class="sh-toggle" style="flex-shrink:0">
                            <input type="checkbox"
                                   class="shm-rf-enabled"
                                   data-field="<?php echo esc_attr( $key ); ?>"
                                   <?php checked( $enabled ); ?>
                                   <?php echo $loc_blocked ? 'disabled' : ''; ?>
                                   onchange="shmRfToggle('<?php echo esc_js( $key ); ?>',this.checked)">
                            <span class="sh-toggle-slider"></span>
                        </label>

                        <!-- Label input (editable) -->
                        <input type="text"
                               class="shm-rf-label sh-input"
                               data-field="<?php echo esc_attr( $key ); ?>"
                               value="<?php echo esc_attr( $f_label ); ?>"
                               placeholder="<?php echo esc_attr( $label_placeholder ); ?>"
                               style="font-size:13px;font-weight:600;flex:1;min-width:0"
                               <?php echo $is_builtin ? '' : ''; ?>>

                        <?php if ( $is_builtin ) : ?>
                        <span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:2px 7px;border-radius:12px;font-weight:600;white-space:nowrap">
                            <?php echo esc_html( $builtin_meta[$key]['type_lock'] ); ?>
                        </span>
                        <?php else : ?>
                        <span style="font-size:10px;background:#f3f4f6;color:#6b7280;padding:2px 7px;border-radius:12px;white-space:nowrap">
                            <?php echo esc_html( $key ); ?>
                        </span>
                        <button type="button"
                                onclick="shmRfRemoveField('<?php echo esc_js( $key ); ?>')"
                                style="color:#d63638;background:none;border:none;cursor:pointer;padding:0 4px;font-size:16px;flex-shrink:0"
                                title="Remove field">✕</button>
                        <?php endif; ?>

                        <!-- Expand toggle -->
                        <button type="button"
                                onclick="shmRfExpand('<?php echo esc_js( $key ); ?>')"
                                class="shm-rf-expand-btn"
                                data-field="<?php echo esc_attr( $key ); ?>"
                                style="background:none;border:none;cursor:pointer;padding:0 4px;color:#6b7280;font-size:16px;flex-shrink:0"
                                title="Expand/collapse options">
                            <?php echo $enabled ? '▲' : '▼'; ?>
                        </button>
                    </div>

                    <!-- Sub options (enabled → göster) -->
                    <div id="shm-rf-sub-<?php echo esc_attr( $key ); ?>"
                         style="margin-top:12px;margin-left:44px;<?php echo ! $enabled ? 'display:none' : ''; ?>">

                        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:10px">

                            <!-- Required -->
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
                                <input type="checkbox"
                                       class="shm-rf-required"
                                       data-field="<?php echo esc_attr( $key ); ?>"
                                       <?php checked( $required ); ?>>
                                Required
                            </label>

                            <!-- Show on register form -->
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer"
                                   title="Uncheck to collect on profile page only, not at registration">
                                <input type="checkbox"
                                       class="shm-rf-show-register"
                                       data-field="<?php echo esc_attr( $key ); ?>"
                                       <?php checked( $show_on_register ); ?>>
                                Show on register form
                            </label>

                        </div>

                        <?php if ( ! $is_builtin ) : ?>
                        <!-- Field type -->
                        <div style="margin-bottom:10px">
                            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">Field Type</label>
                            <select class="shm-rf-type sh-input"
                                    data-field="<?php echo esc_attr( $key ); ?>"
                                    onchange="shmRfTypeChange('<?php echo esc_js( $key ); ?>',this.value)"
                                    style="max-width:280px;font-size:13px">
                                <?php foreach ( $type_options as $tval => $tlabel ) : ?>
                                <option value="<?php echo esc_attr( $tval ); ?>" <?php selected( $f_type, $tval ); ?>>
                                    <?php echo esc_html( $tlabel ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ( in_array( $f_type, [ 'custom_select', 'product_attributes' ], true ) || ! $is_builtin ) : ?>
                        <!-- Type-specific options — hidden/shown via JS -->
                        <div id="shm-rf-type-opts-<?php echo esc_attr( $key ); ?>"
                             style="<?php echo ( $is_builtin || in_array( $f_type, [ 'custom_select', 'product_attributes' ], true ) ) ? '' : 'display:none'; ?>">

                            <?php if ( in_array( $f_type, [ 'custom_select', 'product_attributes' ], true ) ) : ?>

                            <!-- UI Type -->
                            <div style="margin-bottom:10px">
                                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">UI Type</label>
                                <div style="display:flex;gap:12px">
                                    <?php foreach ( [ 'select' => 'Dropdown', 'radio' => 'Radio buttons', 'checkbox' => 'Checkboxes' ] as $uval => $ulabel ) : ?>
                                    <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer">
                                        <input type="radio"
                                               class="shm-rf-ui-type"
                                               name="shm_rf_ui_type_<?php echo esc_attr( $key ); ?>"
                                               data-field="<?php echo esc_attr( $key ); ?>"
                                               value="<?php echo esc_attr( $uval ); ?>"
                                               <?php checked( $f_ui_type, $uval ); ?>>
                                        <?php echo esc_html( $ulabel ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Options source toggle (product_attributes type için sabit, custom_select için seçilebilir) -->
                            <?php if ( $f_type === 'product_attributes' ) : ?>
                            <!-- Product Attribute Seçici -->
                            <div style="margin-bottom:10px">
                                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">WooCommerce Attribute</label>
                                <?php if ( ! empty( $wc_attributes ) ) : ?>
                                <select class="shm-rf-attr-slug sh-input"
                                        data-field="<?php echo esc_attr( $key ); ?>"
                                        style="max-width:280px;font-size:13px">
                                    <option value="">— Select attribute —</option>
                                    <?php foreach ( $wc_attributes as $aslug => $alabel ) : ?>
                                    <option value="<?php echo esc_attr( $aslug ); ?>" <?php selected( $f_attr_slug, $aslug ); ?>>
                                        <?php echo esc_html( $alabel ); ?> <span style="color:#9ca3af">(<?php echo esc_html( $aslug ); ?>)</span>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="font-size:11px;color:#9ca3af;margin-top:4px">Options will be fetched from WooCommerce product attribute terms at runtime.</div>
                                <?php else : ?>
                                <div style="font-size:12px;color:#e11d48">⚠ No WooCommerce attributes found. <a href="<?php echo esc_url( admin_url('edit.php?post_type=product&page=product_attributes') ); ?>" style="color:#2271b1">Create attributes →</a></div>
                                <?php endif; ?>
                            </div>

                            <?php else : /* custom_select */ ?>
                            <!-- Custom Options — textarea, her satır val:Label veya sadece label -->
                            <div style="margin-bottom:10px">
                                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">
                                    Options
                                    <span style="font-weight:400;text-transform:none;letter-spacing:0"> — one per line, format: <code>value:Label</code> or just <code>label</code></span>
                                </label>
                                <textarea class="shm-rf-options sh-input"
                                          data-field="<?php echo esc_attr( $key ); ?>"
                                          rows="5"
                                          style="width:100%;font-size:12px;font-family:monospace;max-width:420px"
                                          placeholder="male:Male&#10;female:Female&#10;other:Other"><?php echo esc_textarea( $opts_textarea ); ?></textarea>
                            </div>
                            <?php endif; ?>

                            <?php endif; /* in_array custom_select / product_attributes */ ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- /sub -->

                    <?php if ( $loc_blocked ) : ?>
                    <div style="font-size:11px;color:#e11d48;margin-top:6px">⚠ Needs Localization (wp_countries) data</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div><!-- /#shm-rf-list -->

            </div>
        </div>

        <!-- Twig Usage hint -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Twig Usage</span>
                <span class="sh-card-desc">The register form loops enabled fields automatically via a generic loop. You can also place individual hooks manually.</span>
            </div>
            <div class="sh-card-body">
                <pre style="background:#1e1e2e;color:#cdd6f4;padding:16px;border-radius:8px;font-size:12px;overflow-x:auto;line-height:1.7"><?php
echo htmlspecialchars(
<<<'TWIG'
{# Generic dynamic loop — all enabled show_on_register fields render automatically #}
{% set _rf = register_fields ?? {} %}
<div class="row g-3">
{% for _fkey, _fcfg in _rf %}
    {% if (_fcfg.enabled ?? false) and (_fcfg.show_on_register ?? true) %}
    <div class="col-12 col-sm-6">
        {% do action('sh_register_form_field_' ~ _fkey) %}
    </div>
    {% endif %}
{% endfor %}
</div>

{# Or place individual fields: #}
{% do action('sh_register_form_field_gender') %}
{% do action('sh_register_form_field_shoe_size') %}
TWIG
); ?></pre>
            </div>
        </div>

        <script>
        // ─── Register Fields JS ───────────────────────────────────────────────
        var shmWcAttributes = <?php echo wp_json_encode( $wc_attributes ); ?>;
        var shmRfFieldCount = <?php echo count( $rf ); ?>;

        // Expand / collapse sub-options
        function shmRfExpand(key) {
            var sub = document.getElementById('shm-rf-sub-' + key);
            var btn = document.querySelector('.shm-rf-expand-btn[data-field="' + key + '"]');
            if (!sub) return;
            var open = sub.style.display !== 'none';
            sub.style.display = open ? 'none' : '';
            if (btn) btn.textContent = open ? '▼' : '▲';
        }

        // Enabled toggle
        function shmRfToggle(key, enabled) {
            var sub = document.getElementById('shm-rf-sub-' + key);
            var btn = document.querySelector('.shm-rf-expand-btn[data-field="' + key + '"]');
            if (sub) { sub.style.display = enabled ? '' : 'none'; }
            if (btn) btn.textContent = enabled ? '▲' : '▼';
        }

        // Field type değişince ilgili options bölümünü göster/gizle
        function shmRfTypeChange(key, type) {
            var wrap = document.getElementById('shm-rf-type-opts-' + key);
            if (!wrap) return;
            wrap.style.display = (type === 'custom_select' || type === 'product_attributes') ? '' : 'none';

            // product_attributes: attribute seçici göster, textarea gizle
            var attrWrap    = wrap.querySelector('.shm-rf-attr-slug-wrap');
            var optionsWrap = wrap.querySelector('.shm-rf-options-wrap');
            if (attrWrap)    attrWrap.style.display    = (type === 'product_attributes') ? '' : 'none';
            if (optionsWrap) optionsWrap.style.display = (type === 'custom_select')       ? '' : 'none';
        }

        // Add new custom field
        function shmRfAddField() {
            var slug = prompt('Field key (lowercase, no spaces, e.g. "birth_year"):');
            if (!slug) return;
            slug = slug.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
            if (!slug) return;

            // Duplicate check
            if (document.querySelector('.shm-rf-row[data-field="' + slug + '"]')) {
                alert('A field with key "' + slug + '" already exists.');
                return;
            }

            var uiTypeOpts = ['select','radio','checkbox'].map(function(u){
                return '<label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer">'
                    + '<input type="radio" class="shm-rf-ui-type" name="shm_rf_ui_type_' + slug + '" data-field="' + slug + '" value="' + u + '"' + (u==='select'?' checked':'') + '>'
                    + u.charAt(0).toUpperCase()+u.slice(1)+'</label>';
            }).join('');

            var attrOpts = '<option value="">— Select attribute —</option>';
            Object.keys(shmWcAttributes).forEach(function(k){
                attrOpts += '<option value="' + k + '">' + shmWcAttributes[k] + ' (' + k + ')</option>';
            });

            var html = '<div class="shm-rf-row" data-field="'+slug+'" style="padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:10px">'
                + '<div style="display:flex;align-items:center;gap:10px">'
                    + '<label class="sh-toggle" style="flex-shrink:0"><input type="checkbox" class="shm-rf-enabled" data-field="'+slug+'" checked onchange="shmRfToggle(\''+slug+'\',this.checked)"><span class="sh-toggle-slider"></span></label>'
                    + '<input type="text" class="shm-rf-label sh-input" data-field="'+slug+'" value="" placeholder="'+slug.replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();})+'" style="font-size:13px;font-weight:600;flex:1;min-width:0">'
                    + '<span style="font-size:10px;background:#f3f4f6;color:#6b7280;padding:2px 7px;border-radius:12px;white-space:nowrap">'+slug+'</span>'
                    + '<button type="button" onclick="shmRfRemoveField(\''+slug+'\')" style="color:#d63638;background:none;border:none;cursor:pointer;padding:0 4px;font-size:16px;flex-shrink:0" title="Remove">✕</button>'
                    + '<button type="button" onclick="shmRfExpand(\''+slug+'\')" class="shm-rf-expand-btn" data-field="'+slug+'" style="background:none;border:none;cursor:pointer;padding:0 4px;color:#6b7280;font-size:16px;flex-shrink:0">▲</button>'
                + '</div>'
                + '<div id="shm-rf-sub-'+slug+'" style="margin-top:12px;margin-left:44px">'
                    + '<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:10px">'
                        + '<label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer"><input type="checkbox" class="shm-rf-required" data-field="'+slug+'"> Required</label>'
                        + '<label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer"><input type="checkbox" class="shm-rf-show-register" data-field="'+slug+'" checked> Show on register form</label>'
                    + '</div>'
                    + '<div style="margin-bottom:10px">'
                        + '<label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">Field Type</label>'
                        + '<select class="shm-rf-type sh-input" data-field="'+slug+'" onchange="shmRfTypeChange(\''+slug+'\',this.value)" style="max-width:280px;font-size:13px">'
                            + '<option value="text">📝 Text input</option>'
                            + '<option value="custom_select">🔽 Custom select/radio/checkbox</option>'
                            + '<option value="product_attributes">🏷️ WooCommerce product attribute</option>'
                        + '</select>'
                    + '</div>'
                    + '<div id="shm-rf-type-opts-'+slug+'" style="display:none">'
                        + '<div style="margin-bottom:10px"><label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">UI Type</label>'
                            + '<div style="display:flex;gap:12px">' + uiTypeOpts + '</div></div>'
                        + '<div class="shm-rf-attr-slug-wrap" style="margin-bottom:10px;display:none">'
                            + '<label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">WooCommerce Attribute</label>'
                            + '<select class="shm-rf-attr-slug sh-input" data-field="'+slug+'" style="max-width:280px;font-size:13px">' + attrOpts + '</select>'
                            + '<div style="font-size:11px;color:#9ca3af;margin-top:4px">Options fetched at runtime from WC attribute terms.</div>'
                        + '</div>'
                        + '<div class="shm-rf-options-wrap" style="margin-bottom:10px;display:none">'
                            + '<label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">Options <span style="font-weight:400;text-transform:none;letter-spacing:0"> — one per line, format: <code>value:Label</code></span></label>'
                            + '<textarea class="shm-rf-options sh-input" data-field="'+slug+'" rows="5" style="width:100%;font-size:12px;font-family:monospace;max-width:420px" placeholder="male:Male&#10;female:Female&#10;other:Other"></textarea>'
                        + '</div>'
                    + '</div>'
                + '</div>'
            + '</div>';

            document.getElementById('shm-rf-list').insertAdjacentHTML('beforeend', html);
            shmRfFieldCount++;
        }

        function shmRfRemoveField(key) {
            if (!confirm('Remove field "' + key + '"? This will delete saved user meta values.')) return;
            var row = document.querySelector('.shm-rf-row[data-field="' + key + '"]');
            if (row) row.remove();
        }
        </script>

        <?php endif; ?></div><!-- /shm-stab-register-fields -->

        <?php /* ─── ROLES TAB ─── */ ?>
        <div id="shm-stab-roles" class="shm-stab-panel" style="<?php echo $stab !== 'roles' ? 'display:none' : ''; ?>"><?php if(true): ?>
        <!-- ═══ ROLES TAB ═══ -->

        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Default Role</span>
                <span class="sh-card-desc">Role assigned to new users by default. Synced with WordPress Settings → General.</span>
            </div>
            <div class="sh-card-body">
                <select id="shm-default-role" style="min-width:200px;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px">
                    <?php foreach ( $all_roles as $role_slug => $role_data ) : ?>
                    <option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( $default_role, $role_slug ); ?>>
                        <?php echo esc_html( $role_data['name'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="sh-card">
            <div class="sh-card-header--col">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                    <label class="sh-toggle">
                        <input type="checkbox" id="shm-enable-role-selection" <?php checked( $s['enable_role_selection'] ?? false ); ?> onchange="shmToggle('role_selection',this.checked)">
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <span style="font-size:13px;font-weight:500">Show role selection on signup form</span>
                        <br><span style="font-size:11px;color:#9ca3af">Users can choose their role when registering</span>
                    </div>
                </div>
            </div>
            <div class="sh-card-body" id="shm-role-selection-opts" style="margin-left:44px;<?php echo empty( $s['enable_role_selection'] ) ? 'display:none' : ''; ?>">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:8px">Which roles can be selected?</label>
                <div id="shm-selectable-roles" style="display:flex;flex-wrap:wrap;gap:16px">
                    <?php
                    $available = array_filter( $all_roles, fn( $slug ) => $slug !== 'administrator', ARRAY_FILTER_USE_KEY );
                    if ( empty( $available ) ) :
                    ?>
                    <span style="font-size:12px;color:#9ca3af;font-style:italic">No roles found.</span>
                    <?php else : foreach ( $available as $role_slug => $role_data ) :
                        $checked = in_array( $role_slug, $sel_roles, true );
                    ?>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" class="shm-selectable-role" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( $checked ); ?>>
                        <?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="sh-card">
            <div class="sh-card-header--col"  style="display:flex;flex-direction: row;">
                <div style="display:flex;flex-direction: column;">
                    <span class="sh-card-title">Custom Role</span>
                    <span class="sh-card-desc">Add roles specific to your site (e.g. Freelancer, Employer, Coach)</span>
                </div>
                <button type="button" class="sh-btn sh-btn-primary sh-btn-sm ms-auto" onclick="shmAddRole()">+ Add Role</button>
            </div>
            <div class="sh-card-body" id="shm-roles-list">
                <?php foreach ( $custom_roles as $i => $role ) : ?>
                <div class="shm-role-row" style="display:flex;gap:8px;align-items:flex-start;padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:8px" data-index="<?php echo $i; ?>">
                    <div style="flex:1">
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <div>
                                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Slug</label>
                                <input type="text" class="sh-input shm-role-slug" placeholder="e.g. freelancer" value="<?php echo esc_attr( $role['slug'] ?? '' ); ?>" style="width:130px" />
                            </div>
                            <div>
                                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Label</label>
                                <input type="text" class="sh-input shm-role-label" placeholder="e.g. Freelancer" value="<?php echo esc_attr( $role['label'] ?? '' ); ?>" style="width:130px" />
                            </div>
                            <div>
                                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Profile URL prefix</label>
                                <input type="text" class="sh-input shm-role-prefix" placeholder="e.g. freelancer" value="<?php echo esc_attr( $role['profile_prefix'] ?? '' ); ?>" style="width:130px" />
                            </div>
                            <div style="flex:1;min-width:160px">
                                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Capabilities</label>
                                <div class="shm-role-caps" style="display:flex;flex-wrap:wrap;gap:6px">
                                    <?php
                                    $common_caps = [ 'read', 'edit_posts', 'upload_files', 'publish_posts', 'edit_published_posts' ];
                                    foreach ( $common_caps as $cap ) :
                                        $has = in_array( $cap, $role['capabilities'] ?? [], true );
                                    ?>
                                    <label style="font-size:11px;display:flex;align-items:center;gap:3px;cursor:pointer">
                                        <input type="checkbox" class="shm-cap" value="<?php echo $cap; ?>" <?php checked( $has ); ?>>
                                        <?php echo $cap; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="this.closest('.shm-role-row').remove()" style="color:#d63638;background:none;border:none;cursor:pointer;padding:4px;margin-top:16px" title="Remove">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php endif; ?></div><!-- /shm-stab-roles -->

        <?php /* ─── ACCOUNT TAB ─── */ ?>
        <div id="shm-stab-account" class="shm-stab-panel" style="<?php echo $stab !== 'account' ? 'display:none' : ''; ?>"><?php if(true): ?>
        <!-- ═══ ACCOUNT TAB ═══ -->

        <!-- Public Profile -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <div style="display:flex;align-items:center;gap:12px;">
                    <label class="sh-toggle">
                        <input type="checkbox" id="shm-enable-public-profile" <?php checked( $s['enable_public_profile'] ?? false ); ?> onchange="shmToggle('public_profile',this.checked)">
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <span style="font-size:13px;font-weight:500">Enable Public Profiles</span>
                        <br><span style="font-size:11px;color:#9ca3af">Each user gets a publicly accessible profile page</span>
                    </div>
                </div>
            </div>
            <div class="sh-card-body" id="shm-public-profile-opts" style="margin-left:44px;<?php echo empty( $s['enable_public_profile'] ) ? 'display:none' : ''; ?>">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:8px">Profile URL Structure</label>
                <?php
                $url_opts = [
                    'standard' => [ 'label' => 'Standard prefix', 'example' => '/profile/{username}' ],
                    'role'     => [ 'label' => 'Role-based prefix', 'example' => '/{role}/{username}' ],
                    'custom'   => [ 'label' => 'Custom prefix', 'example' => '/{prefix}/{username}' ],
                ];
                foreach ( $url_opts as $type => $opt ) :
                ?>
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;margin-bottom:8px" onclick="shmToggle('profile_url','<?php echo $type; ?>')">
                    <input type="radio" name="shm_profile_url_type" value="<?php echo $type; ?>" <?php checked( $profile_type, $type ); ?> style="margin-top:3px">
                    <div>
                        <strong><?php echo $opt['label']; ?></strong>
                        <code style="margin-left:6px;font-size:11px;background:#f3f4f6;padding:1px 5px;border-radius:3px"><?php echo $opt['example']; ?></code>
                    </div>
                </label>
                <?php endforeach; ?>

                <div id="shm-profile-prefix-wrap" style="margin-top:8px;<?php echo $profile_type === 'custom' ? '' : 'display:none'; ?>">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Custom Prefix</label>
                    <input type="text" id="shm-profile-prefix" class="sh-input" value="<?php echo esc_attr( $profile_pfx ); ?>" placeholder="profile" style="width:160px" />
                    <span style="font-size:11px;color:#9ca3af;margin-left:8px">→ /{prefix}/{username}</span>
                </div>
            </div>
        </div>

        <!-- Account Deletion -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <div style="display:flex;align-items:center;gap:12px">
                    <label class="sh-toggle">
                        <input type="checkbox" id="shm-enable-account-deletion" <?php checked( $s['enable_account_deletion'] ?? false ); ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <span style="font-size:13px;font-weight:500">Allow Account Deletion Request</span>
                        <br><span style="font-size:11px;color:#9ca3af">Users can request to delete their account from their profile page</span>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?></div><!-- /shm-stab-account -->

        <?php /* ─── LOGIN SECURITY TAB ─── */ ?>
        <div id="shm-stab-security" class="shm-stab-panel" style="<?php echo $stab !== 'security' ? 'display:none' : ''; ?>"><?php if(true): ?>
        <!-- ═══ LOGIN SECURITY TAB ═══ -->

        <?php
        $sms_ok     = defined('ENABLE_SMS') && ENABLE_SMS;
        $sms_available = $sms_ok;
        $totp_ok    = class_exists( \PragmaRX\Google2FAQRCode\Google2FA::class );
        $days       = (int) ( $s['trusted_device_days'] ?? 30 );
        $method_2fa = $s['login_2fa_method'] ?? 'none';
        // SMS disabled iken SMS activation seçiliyse → email'e zorla
        if ( ! $sms_available && $act_type === 'sms' ) { $act_type = 'email'; }
        ?>

        <!-- Account Activation -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <div style="display:flex;align-items:center;gap:12px;">
                    <label class="sh-toggle">
                        <input type="checkbox" id="shm-enable-membership-activation" <?php checked( $s['enable_membership_activation'] ?? false ); ?> onchange="shmToggle('activation',this.checked)">
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <span style="font-size:13px;font-weight:500">Enable Account Activation</span>
                        <br><span style="font-size:11px;color:#9ca3af">Require users to verify their account after registration</span>
                    </div>
                </div>
            </div>
            <div class="sh-card-body" id="shm-activation-opts" style="margin-left:44px;<?php echo empty( $s['enable_membership_activation'] ) ? 'display:none' : ''; ?>">
                <div style="margin-bottom:14px">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:8px">Activation Method</div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:8px">
                        <input type="radio" name="shm_activation_type" value="email" <?php checked( $act_type, 'email' ); ?> onchange="shmToggle('activation_type','email')">
                        by Email
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;<?php echo $sms_available ? 'cursor:pointer' : 'cursor:not-allowed;opacity:.4'; ?>">
                        <input type="radio" name="shm_activation_type" value="sms"
                               <?php checked( $act_type, 'sms' ); ?>
                               <?php echo $sms_available ? 'onchange="shmToggle(\'activation_type\',\'sms\')"' : 'disabled'; ?>>
                        by SMS
                        <?php if ( ! $sms_available ) : ?>
                        <span style="font-size:11px;color:#9ca3af">— <a href="<?php echo esc_url( admin_url('admin.php?page=sh-sms') ); ?>" style="color:#2271b1;text-decoration:none">Enable SMS →</a></span>
                        <?php endif; ?>
                    </label>
                </div>
                <div id="shm-autologin-wrap" style="<?php echo $act_type !== 'email' ? 'display:none' : ''; ?>">
                    <div style="display:flex;align-items:center;gap:12px">
                        <label class="sh-toggle">
                            <input type="checkbox" id="shm-enable-activation-email-autologin" <?php checked( $s['enable_activation_email_autologin'] ?? false ); ?>>
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <div>
                            <span style="font-size:13px;font-weight:500">Auto-Login after activation</span>
                            <br><span style="font-size:11px;color:#9ca3af">User is logged in automatically when they click the activation link</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2FA Method — Radio Group -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Two-Factor Authentication (2FA)</span>
                <span class="sh-card-desc">After a successful login, users must verify their identity with a second factor. Choose one method.</span>
            </div>
            <div class="sh-card-body">
                <!-- None -->
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:6px;cursor:pointer;margin-bottom:8px;<?php echo $method_2fa === 'none' ? 'background:#f0fdf4;border:1px solid #86efac;' : 'background:#f9fafb;border:1px solid #e5e7eb;'; ?>">
                    <input type="radio" name="shm_2fa_method" value="none" <?php checked( $method_2fa, 'none' ); ?> style="margin-top:3px">
                    <div>
                        <strong style="font-size:13px">Disabled</strong>
                        <br><span style="font-size:11px;color:#9ca3af">Standard email + password login only.</span>
                    </div>
                </label>

                <!-- SMS OTP -->
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:6px;cursor:<?php echo $sms_ok ? 'pointer' : 'not-allowed'; ?>;margin-bottom:8px;<?php echo $method_2fa === 'sms' ? 'background:#eff6ff;border:1px solid #93c5fd;' : 'background:#f9fafb;border:1px solid #e5e7eb;'; ?><?php echo ! $sms_ok ? 'opacity:.5;' : ''; ?>">
                    <input type="radio" name="shm_2fa_method" value="sms" <?php checked( $method_2fa, 'sms' ); ?> <?php echo ! $sms_ok ? 'disabled' : ''; ?> style="margin-top:3px">
                    <div>
                        <strong style="font-size:13px">SMS OTP</strong>
                        <br><span style="font-size:11px;color:#9ca3af">After login, a one-time code is sent to the user's registered phone number.</span>
                        <?php if ( ! $sms_ok ) : ?>
                        <br><span style="font-size:11px;color:#e11d48">⚠ <a href="<?php echo esc_url( admin_url('admin.php?page=sh-sms') ); ?>" style="color:#2271b1">Enable SMS app →</a></span>
                        <?php endif; ?>
                    </div>
                </label>

                <!-- TOTP -->
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:6px;cursor:<?php echo $totp_ok ? 'pointer' : 'not-allowed'; ?>;margin-bottom:8px;<?php echo $method_2fa === 'totp' ? 'background:#eff6ff;border:1px solid #93c5fd;' : 'background:#f9fafb;border:1px solid #e5e7eb;'; ?><?php echo ! $totp_ok ? 'opacity:.5;' : ''; ?>">
                    <input type="radio" name="shm_2fa_method" value="totp" <?php checked( $method_2fa, 'totp' ); ?> <?php echo ! $totp_ok ? 'disabled' : ''; ?> style="margin-top:3px">
                    <div>
                        <strong style="font-size:13px">Authenticator App (TOTP)</strong>
                        <br><span style="font-size:11px;color:#9ca3af">Users set up Google Authenticator, Authy or similar app. A 6-digit code is required on every login.</span>
                        <?php if ( ! $totp_ok ) : ?>
                        <br><span style="font-size:11px;color:#e11d48">⚠ <code>pragmarx/google2fa-qrcode</code> not installed.</span>
                        <?php endif; ?>
                    </div>
                </label>
            </div>
        </div>

        <!-- Passwordless Login -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <div style="display:flex;align-items:center;gap:12px;<?php echo ! $sms_ok ? 'opacity:.6' : ''; ?>">
                    <label class="sh-toggle">
                        <input type="checkbox" id="shm-enable-passwordless" <?php checked( $s['enable_passwordless'] ?? false ); ?> <?php echo ! $sms_ok ? 'disabled' : ''; ?>>
                        <span class="sh-toggle-slider"></span>
                    </label>
                    <div>
                        <span style="font-size:13px;font-weight:500">Passwordless Login via SMS</span>
                        <br><span style="font-size:11px;color:#9ca3af">Show a "Log in without password" option. Users enter their registered phone number and receive an OTP. Works <strong>in addition</strong> to normal login.</span>
                        <?php if ( ! $sms_ok ) : ?>
                        <br><span style="font-size:11px;color:#e11d48">⚠ <a href="<?php echo esc_url( admin_url('admin.php?page=sh-sms') ); ?>" style="color:#2271b1">Enable SMS app →</a></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trusted Device -->
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Trusted Devices</span>
                <span class="sh-card-desc">After successful 2FA, the device is remembered. Users won't be asked for 2FA again on trusted devices until the period expires.</span>
            </div>
            <div class="sh-card-body">
                <div style="display:flex;align-items:center;gap:10px">
                    <label style="font-size:13px;font-weight:500">Remember for</label>
                    <input type="number" id="shm-trusted-device-days" class="sh-input" value="<?php echo $days; ?>" min="0" max="365" style="width:80px">
                    <span style="font-size:13px;color:#6b7280">days</span>
                    <span style="font-size:11px;color:#9ca3af">(0 = disabled, ask 2FA every login)</span>
                </div>
            </div>
        </div>

        <!-- Login Redirect -->
        <?php
        $redirect_type    = $s['login_redirect_type']    ?? 'dashboard';
        $redirect_page_id = (int) ( $s['login_redirect_page_id'] ?? 0 );
        // Polylang aktifse sadece default dil sayfalarını listele
        $pages_args = [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ];
        if ( function_exists( 'pll_default_language' ) ) {
            $pages_args['lang'] = pll_default_language();
        }
        $all_pages = get_posts( $pages_args );
        ?>
        <div class="sh-card">
            <div class="sh-card-header--col">
                <span class="sh-card-title">Login Redirect</span>
                <span class="sh-card-desc">After a successful login, where should the user be sent?</span>
            </div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:200px 1fr;align-items:center;gap:12px;margin-bottom:14px">
                    <label style="font-size:13px;font-weight:500">Redirect to</label>
                    <select id="shm-login-redirect-type" class="sh-input" onchange="shmToggleRedirectPage(this.value)">
                        <option value="dashboard" <?php selected( $redirect_type, 'dashboard' ); ?>>My Account Dashboard</option>
                        <option value="profile"   <?php selected( $redirect_type, 'profile'   ); ?>>Profile Page</option>
                        <option value="home"      <?php selected( $redirect_type, 'home'      ); ?>>Homepage</option>
                        <option value="referer"   <?php selected( $redirect_type, 'referer'   ); ?>>Previous Page (referer)</option>
                        <option value="custom"    <?php selected( $redirect_type, 'custom'    ); ?>>Custom Page...</option>
                    </select>
                </div>
                <div id="shm-redirect-page-wrap" style="display:grid;grid-template-columns:200px 1fr;align-items:center;gap:12px;<?php echo $redirect_type !== 'custom' ? 'display:none' : ''; ?>">
                    <label style="font-size:13px;font-weight:500">Select Page</label>
                    <select id="shm-login-redirect-page-id" class="sh-input">
                        <option value="0">— Select a page —</option>
                        <?php foreach ( $all_pages as $p ) : ?>
                        <option value="<?php echo $p->ID; ?>" <?php selected( $redirect_page_id, $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p style="font-size:11px;color:#9ca3af;margin:10px 0 0">
                    <strong>Referer:</strong> user returns to the page they were on before logging in.
                    If no referer is stored, falls back to Dashboard.
                </p>
            </div>
        </div>

        <script>
        function shmToggleRedirectPage(val) {
            var wrap = document.getElementById('shm-redirect-page-wrap');
            if (wrap) wrap.style.display = val === 'custom' ? 'grid' : 'none';
        }
        </script>

        <?php endif; ?></div><!-- /shm-stab-security -->

        <?php /* ─── INTEGRATIONS TAB ─── */ ?>
        <div id="shm-stab-integrations" class="shm-stab-panel" style="<?php echo $stab !== 'integrations' ? 'display:none' : ''; ?>"><?php if(true): ?>
        <!-- ═══ INTEGRATIONS TAB ═══ -->
        <h3 style="margin:0 0 4px;font-size:15px">Integrations</h3>
        <p style="font-size:12px;color:#9ca3af;margin:0 0 20px">Third-party plugin integrations.</p>

        <?php
        $social_ok = class_exists( 'NextendSocialLogin' );
        $chat_ok   = class_exists( 'Redq_YoBro' );
        $integrations = [
            [
                'id'      => 'shm-enable-social-login',
                'key'     => 'enable_social_login',
                'label'   => 'Social Login',
                'desc'    => 'Allow login/register via Google, Facebook etc.',
                'plugin'  => 'NextendSocialLogin',
                'ok'      => $social_ok,
                'icon'    => '🔗',
            ],
            [
                'id'      => 'shm-enable-chat',
                'key'     => 'enable_chat',
                'label'   => 'Live Chat (YoBro)',
                'desc'    => 'Enable real-time chat between members.',
                'plugin'  => 'YoBro',
                'ok'      => $chat_ok,
                'icon'    => '💬',
            ],
        ];
        foreach ( $integrations as $int ) :
            $disabled = ! $int['ok'];
            $checked  = $int['ok'] && ( $s[ $int['key'] ] ?? false );
        ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px;background:#f9fafb;border-radius:8px;margin-bottom:10px;<?php echo $disabled ? 'opacity:.6' : ''; ?>">
            <span style="font-size:22px"><?php echo $int['icon']; ?></span>
            <div style="flex:1">
                <strong style="font-size:13px"><?php echo $int['label']; ?></strong>
                <br><span style="font-size:11px;color:#6b7280"><?php echo $int['desc']; ?></span>
                <?php if ( $disabled ) : ?>
                <br><span style="font-size:11px;color:#e11d48">⚠ <?php echo esc_html( $int['plugin'] ); ?> plugin required</span>
                <?php endif; ?>
            </div>
            <label class="sh-toggle">
                <input type="checkbox" id="<?php echo $int['id']; ?>" <?php checked( $checked ); ?> <?php echo $disabled ? 'disabled' : ''; ?>>
                <span class="sh-toggle-slider"></span>
            </label>
        </div>
        <?php endforeach; ?>

        <?php endif; ?></div><!-- /shm-stab-integrations -->

        <!-- Save Button -->
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:12px">
            <button type="button" id="shm-save-settings" class="sh-btn sh-btn-primary">💾 Save Settings</button>
            <span id="shm-save-status" style="font-size:12px;color:#6b7280"></span>
        </div>

        </div><!-- /padding:20px -->
        </div><!-- .sh-card -->
        </div><!-- #shm-membership-opts -->

        </div><!-- .sh-main -->
        <div class="sh-sidebar"><?php \SaltHareket\Membership\Admin\MembershipAdmin::renderSidebarPublic(); ?></div>
        </div><!-- .sh-layout -->

        <script>
        // ─── Tab switcher ──────────────────────────────────────────────────────
        function shmSwitchTab(slug) {
            document.querySelectorAll('.shm-stab-panel').forEach(function(p){ p.style.display = 'none'; });
            document.querySelectorAll('[id^="shm-tab-btn-"]').forEach(function(b){ b.classList.remove('active'); });
            var panel = document.getElementById('shm-stab-' + slug);
            if (panel) panel.style.display = '';
            var btn = document.getElementById('shm-tab-btn-' + slug);
            if (btn) btn.classList.add('active');
        }

        // ─── Toggle helpers ────────────────────────────────────────────────────
        function shmToggle(type, val) {
            var map = {
                'membership':    'shm-membership-opts',
                'activation':    'shm-activation-opts',
                'password_recover': 'shm-pw-recover-opts',
                'role_selection': 'shm-role-selection-opts',
                'public_profile': 'shm-public-profile-opts',
            };
            if (map[type]) {
                document.getElementById(map[type]).style.display = val ? '' : 'none';
            } else if (type === 'activation_type') {
                document.getElementById('shm-autologin-wrap').style.display = (val === 'email') ? '' : 'none';
            } else if (type === 'profile_url') {
                document.querySelectorAll('input[name="shm_profile_url_type"]').forEach(function(r){ r.value === val ? r.checked = true : null; });
                var pw = document.getElementById('shm-profile-prefix-wrap');
                if (pw) pw.style.display = (val === 'custom') ? '' : 'none';
            }

            // enable_membership → AJAX ile hemen kaydet (disable yapınca form gizleniyor, submit edilemiyor)
            if (type === 'membership') {
                var fd = new FormData();
                fd.append('action',   'sh_membership_save_settings');
                fd.append('nonce',    <?php echo wp_json_encode( wp_create_nonce('sh_membership_admin') ); ?>);
                fd.append('settings[enable_membership]', val ? '1' : '0');
                fetch(<?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>, {
                    method: 'POST', body: fd, credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res.success) console.warn('[Membership] enable toggle save failed', res);
                })
                .catch(function(err){ console.warn('[Membership] enable toggle error', err); });
            }
        }

        // ─── Register Fields Toggle ───────────────────────────────────────────
        // enabled toggle açılınca sub-options (required + show_on_register) görünür
        function shmRfToggle(field, enabled) {
            var sub = document.getElementById('shm-rf-sub-' + field);
            if (sub) sub.style.display = enabled ? '' : 'none';
        }

        // ─── Add Role ─────────────────────────────────────────────────────────
        var shmRoleIndex = <?php echo count( $custom_roles ); ?>;
        var shmCommonCaps = ['read','edit_posts','upload_files','publish_posts','edit_published_posts'];
        function shmAddRole() {
            var idx = shmRoleIndex++;
            var capsHtml = shmCommonCaps.map(function(cap) {
                return '<label style="font-size:11px;display:flex;align-items:center;gap:3px;cursor:pointer"><input type="checkbox" class="shm-cap" value="'+cap+'"> '+cap+'</label>';
            }).join('');
            var html = '<div class="shm-role-row" style="display:flex;gap:8px;align-items:flex-start;padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:8px" data-index="'+idx+'">' +
                '<div style="flex:1"><div style="display:flex;gap:8px;flex-wrap:wrap">' +
                '<div><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Slug</label><input type="text" class="sh-input shm-role-slug" placeholder="e.g. freelancer" style="width:130px" /></div>' +
                '<div><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Label</label><input type="text" class="sh-input shm-role-label" placeholder="e.g. Freelancer" style="width:130px" /></div>' +
                '<div><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Profile prefix</label><input type="text" class="sh-input shm-role-prefix" placeholder="e.g. freelancer" style="width:130px" /></div>' +
                '<div style="flex:1;min-width:160px"><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Capabilities</label><div class="shm-role-caps" style="display:flex;flex-wrap:wrap;gap:6px">'+capsHtml+'</div></div>' +
                '</div></div>' +
                '<button type="button" onclick="this.closest(\'.shm-role-row\').remove()" style="color:#d63638;background:none;border:none;cursor:pointer;padding:4px;margin-top:16px" title="Remove">✕</button>' +
                '</div>';
            document.getElementById('shm-roles-list').insertAdjacentHTML('beforeend', html);
        }

        // ─── Toast ────────────────────────────────────────────────────────────
        function shmToast(msg, isError) {
            var el = document.getElementById('sh-membership-toast');
            if (!el) return;
            var item = document.createElement('div');
            item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
            item.textContent = msg;
            el.appendChild(item);
            setTimeout(function(){ item.remove(); }, 3500);
        }

        // ─── Collect & Save ───────────────────────────────────────────────────
        jQuery(function($) {
            $('#shm-save-settings').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var $st  = $('#shm-save-status').text('');

                // Collect custom roles
                var customRoles = [];
                document.querySelectorAll('.shm-role-row').forEach(function(row) {
                    var slug  = row.querySelector('.shm-role-slug')?.value?.trim();
                    var label = row.querySelector('.shm-role-label')?.value?.trim();
                    var pfx   = row.querySelector('.shm-role-prefix')?.value?.trim();
                    if (!slug) return;
                    var caps = [];
                    row.querySelectorAll('.shm-cap:checked').forEach(function(c){ caps.push(c.value); });
                    customRoles.push({ slug: slug, label: label || slug, profile_prefix: pfx, capabilities: caps });
                });

                // Collect selectable roles
                var selectableRoles = [];
                document.querySelectorAll('.shm-selectable-role:checked').forEach(function(c){ selectableRoles.push(c.value); });

                var settings = {
                    enable_membership:                 1, // Her zaman true — bu tab membership aktifken görünür
                    // Forms
                    enable_registration:               $('#shm-enable-registration').is(':checked') ? 1 : 0,
                    enable_remember_login:             $('#shm-enable-remember-login').is(':checked') ? 1 : 0,
                    enable_postcode_validation:        $('#shm-enable-postcode').is(':checked') ? 1 : 0,
                    enable_lost_password:              $('#shm-enable-lost-password').is(':checked') ? 1 : 0,
                    enable_password_recover:           $('#shm-enable-password-recover').is(':checked') ? 1 : 0,
                    password_recover_type:             $('input[name="shm_pw_recover_type"]:checked').val() || 'link',
                    // Activation
                    enable_membership_activation:      $('#shm-enable-membership-activation').is(':checked') ? 1 : 0,
                    membership_activation_type:        $('input[name="shm_activation_type"]:checked').val() || 'email',
                    enable_activation_email_autologin: $('#shm-enable-activation-email-autologin').is(':checked') ? 1 : 0,
                    // Roles
                    default_role:                      $('#shm-default-role').val() || 'subscriber',
                    custom_roles:                      JSON.stringify(customRoles),
                    enable_role_selection:             $('#shm-enable-role-selection').is(':checked') ? 1 : 0,
                    selectable_roles:                  JSON.stringify(selectableRoles),
                    // Account
                    enable_public_profile:             $('#shm-enable-public-profile').is(':checked') ? 1 : 0,
                    profile_url_type:                  $('input[name="shm_profile_url_type"]:checked').val() || 'standard',
                    profile_url_prefix:                $('#shm-profile-prefix').val() || 'profile',
                    enable_account_deletion:           $('#shm-enable-account-deletion').is(':checked') ? 1 : 0,
                    // Login Security
                    login_2fa_method:                  $('input[name="shm_2fa_method"]:checked').val() || 'none',
                    enable_passwordless:               $('#shm-enable-passwordless').is(':checked') ? 1 : 0,
                    trusted_device_days:               parseInt($('#shm-trusted-device-days').val()) || 0,
                    login_redirect_type:               $('#shm-login-redirect-type').val() || 'dashboard',
                    login_redirect_page_id:            parseInt($('#shm-login-redirect-page-id').val()) || 0,
                    // Integrations
                    enable_social_login:               $('#shm-enable-social-login').is(':checked') ? 1 : 0,
                    enable_chat:                       $('#shm-enable-chat').is(':checked') ? 1 : 0,
                };

                // Register Fields — her field için tam veriyi topla
                var rfData = {};
                document.querySelectorAll('.shm-rf-row').forEach(function(row) {
                    var key = row.dataset.field;
                    if (!key) return;

                    var enabledCb    = row.querySelector('.shm-rf-enabled[data-field="' + key + '"]');
                    var requiredCb   = row.querySelector('.shm-rf-required[data-field="' + key + '"]');
                    var showCb       = row.querySelector('.shm-rf-show-register[data-field="' + key + '"]');
                    var typeSelect   = row.querySelector('.shm-rf-type[data-field="' + key + '"]');
                    var labelInput   = row.querySelector('.shm-rf-label[data-field="' + key + '"]');
                    var uiTypeRadio  = row.querySelector('.shm-rf-ui-type[data-field="' + key + '"]:checked');
                    var attrSelect   = row.querySelector('.shm-rf-attr-slug[data-field="' + key + '"]');
                    var optTextarea  = row.querySelector('.shm-rf-options[data-field="' + key + '"]');

                    var ftype    = typeSelect   ? typeSelect.value   : '';
                    var ui_type  = uiTypeRadio  ? uiTypeRadio.value  : 'select';
                    var attr_slug= attrSelect   ? attrSelect.value   : '';
                    var options  = optTextarea  ? optTextarea.value  : '';

                    rfData[key] = {
                        enabled:          (enabledCb  && enabledCb.checked)  ? 1 : 0,
                        required:         (requiredCb && requiredCb.checked) ? 1 : 0,
                        show_on_register: (showCb     && showCb.checked)     ? 1 : 0,
                        type:             ftype,
                        label:            labelInput ? labelInput.value.trim() : '',
                        ui_type:          ui_type,
                        options_source:   (ftype === 'product_attributes') ? 'product_attribute' : 'custom',
                        attribute_slug:   attr_slug,
                        options:          options,   // textarea string — backend sanitizeOptions() ile parse eder
                    };
                });
                settings['register_fields'] = JSON.stringify(rfData);

                $.post(ajaxurl, {
                    action:   'sh_membership_save_settings',
                    nonce:    '<?php echo esc_js( $nonce ); ?>',
                    settings: settings,
                }, function(res) {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                    if (res.success) {
                        $st.text('✓ Saved').css('color','#00a32a');
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        shmToast('✗ ' + (res.data || 'Error'), true);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                    shmToast('✗ Request failed.', true);
                });
            });
        });
        </script>
        <?php
    }
}
