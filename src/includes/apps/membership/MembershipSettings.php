<?php

namespace SaltHareket\Membership;

/**
 * MembershipSettings
 *
 * Membership ayarlarını yönetir.
 * ACF options'tan okur (geriye uyumluluk) + kendi wp_options key'lerini kullanır.
 * ACF field'ları silinince kendi key'lerinden okumaya devam eder.
 *
 * variables.php'de define() çağrılarından ÖNCE yüklenir:
 *   require_once SH_INCLUDES_PATH . 'apps/membership/MembershipSettings.php';
 *   define("ENABLE_MEMBERSHIP", MembershipSettings::getSetting('enable_membership'));
 *
 * @version 1.0.2
 * @changelog
 *   1.0.2 - 2026-07-04
 *     - PERF: readAcfFallback() içindeki get_option() çağrıları SaltConfig::get() ile
 *             değiştirildi. ACF init olmadan önce ~12 DB query → 0 query.
 *   1.0.1 - 2026-06-17
 *     - Add: save() sonrası do_action('sh/membership/saved') + do_action('sh/membership/setting_changed')
 *   1.0.0 - 2026-06-16
 *     - Add: Initial release — ACF options'tan taşındı
 *     - Add: Tüm membership ayarları wp_options'a alındı
 *     - Add: ACF fallback (geçiş dönemi — ACF field'ları silinene kadar)
 *     - Add: save() — partial update desteği
 *     - Add: syncToConstants() — variables.php'ye gerek kalmadan runtime define
 */
class MembershipSettings
{
    private const OPTION_KEY = 'sh_membership_settings';

    private static ?array $cache = null;

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array
    {
        return [
            // Core
            'enable_membership'                  => false,
            // Activation
            'enable_membership_activation'       => false,
            'membership_activation_type'         => 'email',   // 'email' | 'sms'
            'enable_activation_email_autologin'  => false,
            // Forms
            'enable_registration'                => true,
            'enable_remember_login'              => false,
            'enable_lost_password'               => false,
            'enable_password_recover'            => false,
            'password_recover_type'              => 'link',    // 'link' | 'generated'
            'enable_postcode_validation'         => false,
            // Social & Integrations
            'enable_social_login'                => false,
            'enable_chat'                        => false,
            // Roles
            'default_role'                       => 'subscriber',
            'custom_roles'                       => [],        // [ ['slug'=>'', 'label'=>'', 'caps'=>[]], ... ]
            'enable_role_selection'              => false,     // Signup'ta rol seçimi
            'selectable_roles'                   => [],        // Signup'ta seçilebilir roller (slug listesi)
            // Account / Public Profile
            'enable_public_profile'              => false,
            'profile_url_type'                   => 'standard', // 'standard' | 'role' | 'custom'
            'profile_url_prefix'                 => 'profile',  // /profile/{user} veya custom prefix
            'enable_account_deletion'            => false,
            // Login Security
            'login_2fa_method'                   => 'none',  // 'none' | 'sms' | 'totp'
            'enable_passwordless'                => false,  // Tel no + OTP ile şifresiz giriş
            'trusted_device_days'                => 30,     // Güvenilir cihaz süresi (gün), 0 = devre dışı
            // Login Redirect
            'login_redirect_type'                => 'dashboard', // 'referer' | 'dashboard' | 'profile' | 'home' | 'custom'
            'login_redirect_page_id'             => 0,           // login_redirect_type='custom' ise WP page ID
            // Register Fields — her field için tam schema:
            //   enabled          (bool)   — field aktif mi
            //   required         (bool)   — zorunlu mu
            //   show_on_register (bool)   — kayıt formunda görünsün mü
            //   type             (string) — 'text' | 'phone' | 'country' | 'state' | 'select' | 'product_attributes' | 'custom_select'
            //   label            (string) — özel etiket (boşsa key'den üretilir)
            //   ui_type          (string) — 'select' | 'radio' | 'checkbox'  (select/product_attributes/custom_select için)
            //   options          (array)  — [ 'value' => 'Label', ... ]  (select tipinde)
            //   options_source   (string) — 'custom' | 'product_attribute'  (custom_select için)
            //   attribute_slug   (string) — WC attribute taxonomy slug (options_source=product_attribute ise)
            'register_fields'                    => [
                'first_name'     => [ 'enabled' => true,  'required' => true,  'show_on_register' => true,  'type' => 'text'  ],
                'last_name'      => [ 'enabled' => true,  'required' => true,  'show_on_register' => true,  'type' => 'text'  ],
                'phone'          => [ 'enabled' => false, 'required' => false, 'show_on_register' => true,  'type' => 'phone' ],
                'country'        => [ 'enabled' => false, 'required' => false, 'show_on_register' => true,  'type' => 'country' ],
                'state'          => [ 'enabled' => false, 'required' => false, 'show_on_register' => true,  'type' => 'state'  ],
                'gender'         => [ 'enabled' => false, 'required' => false, 'show_on_register' => false, 'type' => 'custom_select', 'ui_type' => 'select', 'options_source' => 'custom', 'options' => [ '' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other' ] ],
                'shoe_size'      => [ 'enabled' => false, 'required' => false, 'show_on_register' => false, 'type' => 'product_attributes', 'ui_type' => 'select', 'attribute_slug' => 'pa_shoe-size',     'options' => [] ],
                'clothing_size'  => [ 'enabled' => false, 'required' => false, 'show_on_register' => false, 'type' => 'product_attributes', 'ui_type' => 'select', 'attribute_slug' => 'pa_clothing-size', 'options' => [] ],
            ],
        ];
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    /**
     * Tüm ayarları döndür.
     */
    public static function get(): array
    {
        if ( self::$cache !== null ) return self::$cache;

        $saved = get_option( self::OPTION_KEY, [] );
        $saved = is_array( $saved ) ? $saved : [];

        // ACF field'ları kaldırıldı — doğrudan saved > defaults merge
        self::$cache = array_merge( self::defaults(), $saved );
        return self::$cache;
    }

    /**
     * Tek bir ayarı al.
     */
    public static function getSetting( string $key, $default = null )
    {
        $value = self::get()[ $key ] ?? $default ?? self::defaults()[ $key ] ?? null;

        // register_fields için deep merge — DB'deki kayıt options/type eksikse defaults'tan tamamla
        if ( $key === 'register_fields' && is_array( $value ) ) {
            $defaults = self::defaults()['register_fields'];
            foreach ( $value as $field_key => $field_val ) {
                if ( isset( $defaults[ $field_key ] ) ) {
                    // Bilinen field: defaults ile merge et (eksik anahtarları doldur)
                    $value[ $field_key ] = array_merge( $defaults[ $field_key ], $field_val );
                }
                // Custom (DB'ye eklenmiş) field'lar olduğu gibi korunur
            }
        }

        return $value;
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    /**
     * Ayarları kaydet — partial update destekler.
     */
    public static function save( array $data ): void
    {
        $current = self::get();

        $settings = [
            'enable_membership'                 => isset( $data['enable_membership'] )
                ? (bool) $data['enable_membership']
                : $current['enable_membership'],
            'enable_membership_activation'      => isset( $data['enable_membership_activation'] )
                ? (bool) $data['enable_membership_activation']
                : $current['enable_membership_activation'],
            'membership_activation_type'        => isset( $data['membership_activation_type'] )
                ? ( in_array( $data['membership_activation_type'], [ 'email', 'sms' ], true ) ? $data['membership_activation_type'] : 'email' )
                : $current['membership_activation_type'],
            'enable_activation_email_autologin' => isset( $data['enable_activation_email_autologin'] )
                ? (bool) $data['enable_activation_email_autologin']
                : $current['enable_activation_email_autologin'],
            'enable_registration'               => isset( $data['enable_registration'] )
                ? (bool) $data['enable_registration']
                : $current['enable_registration'],
            'enable_remember_login'             => isset( $data['enable_remember_login'] )
                ? (bool) $data['enable_remember_login']
                : $current['enable_remember_login'],
            'enable_lost_password'              => isset( $data['enable_lost_password'] )
                ? (bool) $data['enable_lost_password']
                : $current['enable_lost_password'],
            'enable_password_recover'           => isset( $data['enable_password_recover'] )
                ? (bool) $data['enable_password_recover']
                : $current['enable_password_recover'],
            'password_recover_type'             => isset( $data['password_recover_type'] )
                ? ( in_array( $data['password_recover_type'], [ 'link', 'generated' ], true ) ? $data['password_recover_type'] : 'link' )
                : $current['password_recover_type'],
            'enable_social_login'               => isset( $data['enable_social_login'] )
                ? (bool) $data['enable_social_login']
                : $current['enable_social_login'],
            'enable_postcode_validation'        => isset( $data['enable_postcode_validation'] )
                ? (bool) $data['enable_postcode_validation']
                : $current['enable_postcode_validation'],
            'enable_chat'                       => isset( $data['enable_chat'] )
                ? (bool) $data['enable_chat']
                : $current['enable_chat'],
            // Roles
            'default_role'                      => isset( $data['default_role'] )
                ? sanitize_key( $data['default_role'] )
                : $current['default_role'],
            'custom_roles'                      => isset( $data['custom_roles'] ) && is_array( $data['custom_roles'] )
                ? self::sanitizeCustomRoles( $data['custom_roles'] )
                : $current['custom_roles'],
            'enable_role_selection'             => isset( $data['enable_role_selection'] )
                ? (bool) $data['enable_role_selection']
                : $current['enable_role_selection'],
            'selectable_roles'                  => isset( $data['selectable_roles'] ) && is_array( $data['selectable_roles'] )
                ? array_map( 'sanitize_key', $data['selectable_roles'] )
                : $current['selectable_roles'],
            // Account
            'enable_public_profile'             => isset( $data['enable_public_profile'] )
                ? (bool) $data['enable_public_profile']
                : $current['enable_public_profile'],
            'profile_url_type'                  => isset( $data['profile_url_type'] )
                ? ( in_array( $data['profile_url_type'], [ 'standard', 'role', 'custom' ], true ) ? $data['profile_url_type'] : 'standard' )
                : $current['profile_url_type'],
            'profile_url_prefix'                => isset( $data['profile_url_prefix'] )
                ? sanitize_title( $data['profile_url_prefix'] )
                : $current['profile_url_prefix'],
            'enable_account_deletion'           => isset( $data['enable_account_deletion'] )
                ? (bool) $data['enable_account_deletion']
                : $current['enable_account_deletion'],
            // Login Security
            'login_2fa_method'    => isset( $data['login_2fa_method'] )
                ? ( in_array( $data['login_2fa_method'], [ 'none', 'sms', 'totp' ], true ) ? $data['login_2fa_method'] : 'none' )
                : $current['login_2fa_method'],
            'enable_passwordless' => isset( $data['enable_passwordless'] )
                ? (bool) $data['enable_passwordless']
                : $current['enable_passwordless'],
            'trusted_device_days' => isset( $data['trusted_device_days'] )
                ? max( 0, (int) $data['trusted_device_days'] )
                : $current['trusted_device_days'],
            // Login Redirect
            'login_redirect_type'    => isset( $data['login_redirect_type'] )
                ? ( in_array( $data['login_redirect_type'], [ 'referer', 'dashboard', 'profile', 'home', 'custom' ], true )
                    ? $data['login_redirect_type'] : 'dashboard' )
                : $current['login_redirect_type'],
            'login_redirect_page_id' => isset( $data['login_redirect_page_id'] )
                ? absint( $data['login_redirect_page_id'] )
                : $current['login_redirect_page_id'],
            // Register Fields
            'register_fields'     => isset( $data['register_fields'] ) && is_array( $data['register_fields'] )
                ? self::sanitizeRegisterFields( $data['register_fields'] )
                : $current['register_fields'],
        ];

        // Kural: activation kapalıysa alt ayarları sıfırla
        if ( ! $settings['enable_membership_activation'] ) {
            $settings['enable_activation_email_autologin'] = false;
        }

        // Kural: membership kapalıysa bağımlı özellikler pasif
        if ( ! $settings['enable_membership'] ) {
            $settings['enable_membership_activation']      = false;
            $settings['enable_activation_email_autologin'] = false;
            $settings['enable_social_login']               = false;
            $settings['enable_chat']                       = false;
        }

        update_option( self::OPTION_KEY, $settings, false );
        self::$cache = null;

        // Rolleri WP ile sync et
        self::syncRoles();

        // ── Hook: dışarıdan dinlenebilir ──────────────────────────────────────
        do_action( 'sh/membership/saved', $settings, $current );

        // Değişen ayarları tek tek fire et
        foreach ( $settings as $key => $new_val ) {
            $old_val = $current[ $key ] ?? null;
            if ( $old_val !== $new_val ) {
                do_action( 'sh/membership/setting_changed', $key, $new_val, $old_val, $settings );
            }
        }
    }

    /**
     * Cache'i temizle.
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Register fields ayarını sanitize et.
     * Hem built-in hem custom (admin'den eklenen) field'ları destekler.
     *
     * Her field için kaydedilen alanlar:
     *   enabled, required, show_on_register,
     *   type, label, ui_type, options_source, attribute_slug, options (assoc array)
     */
    private static function sanitizeRegisterFields( array $fields ): array
    {
        $allowed_types    = [ 'text', 'phone', 'country', 'state', 'custom_select', 'product_attributes' ];
        $allowed_ui_types = [ 'select', 'radio', 'checkbox' ];
        $built_in         = [ 'first_name', 'last_name', 'phone', 'country', 'state', 'gender', 'shoe_size', 'clothing_size' ];
        $defaults         = self::defaults()['register_fields'];
        $result           = [];

        // Sıralama: önce built-in'ler, sonra custom'lar
        $ordered_keys = array_unique( array_merge( $built_in, array_keys( $fields ) ) );

        foreach ( $ordered_keys as $key ) {
            if ( ! isset( $fields[ $key ] ) ) {
                // Form'da yer almayan built-in field'ı defaults'tan al
                if ( isset( $defaults[ $key ] ) ) {
                    $result[ $key ] = $defaults[ $key ];
                }
                continue;
            }

            $f   = $fields[ $key ];
            $def = $defaults[ $key ] ?? [];

            // custom field için slug doğrula
            $key = sanitize_key( $key );
            if ( empty( $key ) ) continue;

            $type    = sanitize_key( $f['type'] ?? ( $def['type'] ?? 'text' ) );
            if ( ! in_array( $type, $allowed_types, true ) ) {
                $type = 'text';
            }

            $ui_type = sanitize_key( $f['ui_type'] ?? ( $def['ui_type'] ?? 'select' ) );
            if ( ! in_array( $ui_type, $allowed_ui_types, true ) ) {
                $ui_type = 'select';
            }

            $entry = [
                'enabled'          => (bool) ( $f['enabled']          ?? false ),
                'required'         => (bool) ( $f['required']         ?? false ),
                'show_on_register' => (bool) ( $f['show_on_register'] ?? true  ),
                'type'             => $type,
                'label'            => sanitize_text_field( $f['label'] ?? ( $def['label'] ?? '' ) ),
            ];

            // select / custom_select / product_attributes için ek alanlar
            if ( in_array( $type, [ 'custom_select', 'product_attributes' ], true ) ) {
                $entry['ui_type']        = $ui_type;
                $entry['options_source'] = in_array( $f['options_source'] ?? '', [ 'custom', 'product_attribute' ], true )
                                            ? $f['options_source']
                                            : 'custom';

                if ( $type === 'product_attributes' ) {
                    $entry['attribute_slug'] = sanitize_key( $f['attribute_slug'] ?? ( $def['attribute_slug'] ?? '' ) );
                    $entry['options']        = []; // product_attributes'ta options boştur, runtime'da WC'den gelir
                } else {
                    // custom_select: options textarea'dan ya da mevcut dizi
                    $entry['attribute_slug'] = '';
                    $entry['options']        = self::sanitizeOptions( $f['options'] ?? ( $def['options'] ?? [] ) );
                }
            }

            $result[ $key ] = $entry;
        }

        return $result;
    }

    /**
     * Options verisini sanitize et.
     * Hem assoc array hem textarea (key\tLabel\n) formatını destekler.
     *
     * @param mixed $raw  Array [ 'val'=>'Label', ... ] veya newline-delimited string
     * @return array      [ 'val' => 'Label', ... ]
     */
    private static function sanitizeOptions( $raw ): array
    {
        if ( is_string( $raw ) ) {
            // Textarea: her satır "value" veya "value:Label" veya sadece label
            $lines  = preg_split( '/\r?\n/', trim( $raw ) );
            $result = [];
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( $line === '' ) continue;
                if ( strpos( $line, ':' ) !== false ) {
                    [ $val, $label ] = array_pad( explode( ':', $line, 2 ), 2, '' );
                    $val   = sanitize_text_field( trim( $val ) );
                    $label = sanitize_text_field( trim( $label ) );
                } else {
                    $val   = sanitize_text_field( $line );
                    $label = $val;
                }
                if ( $val !== '' ) $result[ $val ] = $label ?: $val;
            }
            return $result;
        }

        if ( is_array( $raw ) ) {
            $result = [];
            foreach ( $raw as $k => $v ) {
                $k = sanitize_text_field( (string) $k );
                $v = sanitize_text_field( (string) $v );
                $result[ $k ] = $v;
            }
            return $result;
        }

        return [];
    }

    /**
     * Custom roles'ı sanitize et.
     */
    private static function sanitizeCustomRoles( array $roles ): array
    {
        $sanitized = [];
        foreach ( $roles as $role ) {
            if ( empty( $role['slug'] ) ) continue;
            $sanitized[] = [
                'slug'         => sanitize_key( $role['slug'] ),
                'label'        => sanitize_text_field( $role['label'] ?? ucfirst( $role['slug'] ) ),
                'capabilities' => array_map( 'sanitize_key', (array) ( $role['capabilities'] ?? [] ) ),
                'profile_prefix' => sanitize_title( $role['profile_prefix'] ?? '' ),
            ];
        }
        return $sanitized;
    }

    /**
     * Custom rolleri WP'ye sync et (save sonrası çağrılır).
     * Var olan temel WP rollerini (administrator, editor, author, contributor, subscriber) dokunmaz.
     */
    public static function syncRoles(): void
    {
        $settings     = self::get();
        $custom_roles = $settings['custom_roles'] ?? [];
        $wp_roles     = wp_roles();

        // Tema tarafından yönetilen roller (önceki kayıtta oluşturulmuş olabilir)
        $managed = get_option( 'sh_managed_roles', [] );

        // Artık listede olmayan rolleri sil
        foreach ( $managed as $slug ) {
            if ( ! in_array( $slug, array_column( $custom_roles, 'slug' ), true ) ) {
                remove_role( $slug );
            }
        }

        // Yeni/güncel rolleri ekle/güncelle
        $new_managed = [];
        foreach ( $custom_roles as $role ) {
            $slug  = $role['slug'];
            $label = $role['label'];
            $caps  = [ 'read' => true ]; // minimum capability — admin paneline erişim yok
            foreach ( $role['capabilities'] as $cap ) {
                $caps[ $cap ] = true;
            }
            // admin_panel erişimini açıkça engelle
            unset( $caps['manage_options'], $caps['activate_plugins'] );

            if ( $wp_roles->is_role( $slug ) ) {
                $wp_roles->roles[ $slug ]['name']         = $label;
                $wp_roles->roles[ $slug ]['capabilities'] = $caps;
                $wp_roles->role_objects[ $slug ]->name    = $label;
                update_option( 'wp_user_roles', $wp_roles->roles );
            } else {
                add_role( $slug, $label, $caps );
            }
            $new_managed[] = $slug;
        }

        update_option( 'sh_managed_roles', $new_managed );

        // WP default_role ile sync et
        if ( ! empty( $settings['default_role'] ) ) {
            update_option( 'default_role', $settings['default_role'] );
        }
    }

    // ─── ACF Fallback ─────────────────────────────────────────────────────────

    /**
     * ACF options'tan oku — ACF field'ları kaldırıldı, artık kullanılmıyor.
     * @deprecated — kaldırılacak
     */
    private static function readAcfFallback(): array
    {
        return []; // ACF field'ları silindiğinden bu metod devre dışı
    }
}
