<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesRegistration
 *
 * Kullanıcı kayıt işlemleri.
 * MembershipManager tarafından trait olarak kullanılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: register() — wp_insert_user + user_register_hook
 *     - Add: userRegisterHook() — role set, activation trigger, notification
 *     - Add: userExist() / nicknameExist() — validation helpers
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 * $result = $mm->register([
 *     'email'      => 'a@b.com',
 *     'password'   => '123456',
 *     'first_name' => 'Ali',
 *     'last_name'  => 'Veli',
 *     'role'       => 'customer',
 * ]);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = MembershipManager::getInstance()->register($vars);
 *   if ($result['error']) echo $result['message'];
 *
 * @example
 *   $exists = MembershipManager::userExist(['email' => 'a@b.com']);
 *   if ($exists) echo $exists; // hata mesajı döner
 *
 * @example
 *   $exists = MembershipManager::nicknameExist(['nickname' => 'aliVeli']);
 *
 * @example
 *   // Kayıt sonrası hook dinle:
 *   add_action('sh_user_registered', function($user_id, $role) { ... }, 10, 2);
 *
 * @example
 *   // Kayıt rolünü filtrele:
 *   add_filter('sh_register_default_role', function($role, $vars) {
 *       return 'subscriber';
 *   }, 10, 2);
 */
trait HandlesRegistration
{
    /**
     * Yeni kullanıcı kaydı oluştur.
     *
     * @param array  $vars     ['email', 'password', 'first_name', 'last_name', 'phone', 'phone_code', 'country', 'city', 'gender', 'shoe_size', 'clothing_size', 'role']
     * @param string $callback Opsiyonel callback slug (eski uyumluluk)
     * @param string $role     Varsayılan rol
     * @return array           Standard response array
     */
    public function register( array $vars = [], string $callback = '', string $role = 'author' ): array
    {
        $response = $this->response();

        $email      = sanitize_email( $vars['email'] ?? '' );
        $first_name = sanitize_text_field( $vars['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $vars['last_name'] ?? '' );
        $password   = $vars['password'] ?? '';
        $user_role  = sanitize_text_field( $vars['role'] ?? '' );

        if ( ! empty( $user_role ) ) {
            // Güvenlik: sadece selectable_roles listesindeki roller kabul edilir
            $allowed_roles = (array) \SaltHareket\Membership\MembershipSettings::getSetting( 'selectable_roles', [] );
            $role_selection_enabled = (bool) \SaltHareket\Membership\MembershipSettings::getSetting( 'enable_role_selection', false );

            if ( $role_selection_enabled && ! empty( $allowed_roles ) ) {
                if ( in_array( $user_role, $allowed_roles, true ) ) {
                    $role = $user_role;
                }
                // Listede yoksa default rol kullanılır — silently ignore
            } else {
                // Rol seçimi kapalıysa form'dan gelen role'ü yoksay
                // (admin panelinde role_selection OFF iken kötü niyetli kullanım engellenir)
            }
        }

        // Filter ile rol override edilebilir
        $role = apply_filters( 'sh_register_default_role', $role, $vars );

        if ( empty( $email ) || ! is_email( $email ) ) {
            $response['error']   = true;
            $response['message'] = 'Valid email address is required.';
            return $response;
        }

        $user_data = [
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'user_nicename'=> strtolower( $email ),
            'display_name' => trim( $first_name . ' ' . $last_name ) ?: $email,
            'role'         => $role,
        ];

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            $errors = $user_id->errors;
            if ( isset( $errors['existing_user_email'] ) ) {
                $response['message'] = 'This email address is already registered.';
            } elseif ( isset( $errors['existing_user_login'] ) ) {
                $response['message'] = 'This username is already taken.';
            } elseif ( isset( $errors['empty_user_login'] ) ) {
                $response['message'] = 'Email address is required.';
            } else {
                $response['message'] = 'Registration failed. Please check your details.';
            }
            $response['error'] = true;
            return $response;
        }

        // ── Ek field meta kaydet ─────────────────────────────────────────────
        $this->saveRegisterFieldMeta( $user_id, $vars );

        $this->refreshUser( $user_id );
        $response = $this->userRegisterHook( $user_id, $vars );

        return $response;
    }

    /**
     * Kayıt sırasında gelen ek field'ları user_meta olarak kaydet.
     * MembershipSettings register_fields'taki tüm enabled field'lar işlenir.
     * Built-in özel field'lar (phone, country, state) kendi meta key'lerini kullanır.
     * Diğer field'lar field_key adında meta olarak kaydedilir.
     */
    private function saveRegisterFieldMeta( int $user_id, array $vars ): void
    {
        $rf = \SaltHareket\Membership\MembershipSettings::getSetting( 'register_fields', [] );

        foreach ( $rf as $field_key => $field_cfg ) {
            if ( empty( $field_cfg['enabled'] ) ) continue;

            $type = $field_cfg['type'] ?? 'text';

            // ── Özel meta key'li built-in field'lar ─────────────────────────
            if ( $field_key === 'phone' ) {
                $phone      = sanitize_text_field( $vars['phone']      ?? '' );
                $phone_code = sanitize_text_field( $vars['phone_code'] ?? '' );
                if ( $phone )      update_user_meta( $user_id, 'billing_phone',      $phone );
                if ( $phone_code ) update_user_meta( $user_id, 'billing_phone_code', $phone_code );
                continue;
            }

            if ( $field_key === 'country' ) {
                $country = sanitize_text_field( $vars['country'] ?? '' );
                if ( $country ) update_user_meta( $user_id, 'billing_country', $country );
                continue;
            }

            if ( $field_key === 'state' ) {
                $city = sanitize_text_field( $vars['city'] ?? '' );
                if ( $city ) update_user_meta( $user_id, 'city', $city );
                continue;
            }

            // ── Generic field'lar — text, custom_select, product_attributes ─
            if ( ! isset( $vars[ $field_key ] ) ) continue;

            if ( $type === 'text' ) {
                $value = sanitize_text_field( $vars[ $field_key ] );
                if ( $value !== '' ) update_user_meta( $user_id, $field_key, $value );
                continue;
            }

            if ( in_array( $type, [ 'custom_select', 'product_attributes' ], true ) ) {
                $ui_type = $field_cfg['ui_type'] ?? 'select';

                if ( $ui_type === 'checkbox' ) {
                    // Checkbox — birden fazla değer, array POST
                    $submitted = (array) ( $vars[ $field_key ] ?? [] );
                    $allowed   = $this->getFieldOptions( $field_cfg );
                    $safe      = [];
                    foreach ( $submitted as $v ) {
                        $v = sanitize_text_field( $v );
                        if ( $v === '' || empty( $allowed ) || array_key_exists( $v, $allowed ) ) {
                            $safe[] = $v;
                        }
                    }
                    update_user_meta( $user_id, $field_key, $safe );
                } else {
                    // select / radio — tek değer
                    $value   = sanitize_text_field( $vars[ $field_key ] );
                    $options = $this->getFieldOptions( $field_cfg );
                    if ( $value === '' || empty( $options ) || array_key_exists( $value, $options ) ) {
                        update_user_meta( $user_id, $field_key, $value );
                    }
                }
                continue;
            }
        }

        do_action( 'sh_register_field_meta_saved', $user_id, $vars, $rf );
    }

    /**
     * Field config'den option listesini döndür.
     * product_attributes için WooCommerce'den çeker; custom_select için config'den okur.
     *
     * @return array [ 'value' => 'Label', ... ]
     */
    private function getFieldOptions( array $field_cfg ): array
    {
        $type = $field_cfg['type'] ?? 'custom_select';

        if ( $type === 'product_attributes' ) {
            $slug = $field_cfg['attribute_slug'] ?? '';
            if ( ! $slug || ! taxonomy_exists( $slug ) ) return [];

            $terms = get_terms( [
                'taxonomy'   => $slug,
                'hide_empty' => false,
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
            ] );

            if ( is_wp_error( $terms ) ) return [];

            $options = [];
            foreach ( $terms as $term ) {
                $options[ $term->slug ] = $term->name;
            }
            return $options;
        }

        // custom_select — options dizisi config'den gelir
        return is_array( $field_cfg['options'] ?? null ) ? $field_cfg['options'] : [];
    }

    // ─── Register Form Field Renderers ───────────────────────────────────────

    /**
     * Built-in field render metodları — bu field'ların type'ı sabittir.
     * Yeni custom field eklemek için artık buraya dokunmak gerekmez;
     * admin'den eklenip type'ı seçilen field'lar registerFormFieldActions() içinde
     * dinamik olarak renderFieldDynamic() ile render edilir.
     *
     * @var array<string,string>  key => render method name
     */
    private static array $builtinFieldRenderers = [
        'first_name' => 'renderFieldFirstName',
        'last_name'  => 'renderFieldLastName',
        'phone'      => 'renderFieldPhone',
        'country'    => 'renderFieldCountry',
        'state'      => 'renderFieldState',
    ];

    /**
     * sh_register_form_field_* action'larını register et.
     * MembershipHooks::register() içinden init hook'una bağlanır.
     *
     * Built-in field'lar (first_name, last_name, phone, country, state) özel
     * render metodlarını kullanır.  Diğer tüm field'lar (gender, shoe_size,
     * clothing_size ve admin'den eklenen custom field'lar) renderFieldDynamic()
     * ile işlenir; render type'a göre (text / custom_select / product_attributes)
     * otomatik HTML çıktısı üretir.
     */
    public function registerFormFieldActions(): void
    {
        $rf = \SaltHareket\Membership\MembershipSettings::getSetting( 'register_fields', [] );

        foreach ( $rf as $key => $cfg ) {
            if ( empty( $cfg['enabled'] ) ) continue;

            $req              = ! empty( $cfg['required'] );
            $show_on_register = isset( $cfg['show_on_register'] ) ? (bool) $cfg['show_on_register'] : true;

            if ( ! $show_on_register ) continue;

            // Built-in field'lar için özel render
            if ( isset( self::$builtinFieldRenderers[ $key ] ) ) {
                $method = self::$builtinFieldRenderers[ $key ];
                add_action(
                    'sh_register_form_field_' . $key,
                    function () use ( $method, $req ) {
                        $this->{$method}( $req );
                    }
                );
                continue;
            }

            // Dinamik field — type'a göre renderFieldDynamic() çağır
            add_action(
                'sh_register_form_field_' . $key,
                function () use ( $key, $cfg, $req ) {
                    $this->renderFieldDynamic( $key, $cfg, $req );
                }
            );
        }
    }

    // ── Dynamic Field Renderer ───────────────────────────────────────────────

    /**
     * Dinamik field render — type + ui_type + options'a göre HTML üretir.
     * gender, shoe_size, clothing_size ve admin'den eklenen custom field'ların tümü buradan geçer.
     *
     * @param string $key       Field key (form input name attribute)
     * @param array  $cfg       MembershipSettings register_fields[$key] verisi
     * @param bool   $required  Required mi
     */
    private function renderFieldDynamic( string $key, array $cfg, bool $required ): void
    {
        $type    = $cfg['type'] ?? 'text';
        $ui_type = $cfg['ui_type'] ?? 'select';
        $label   = ! empty( $cfg['label'] )
                    ? $cfg['label']
                    : ucwords( str_replace( '_', ' ', $key ) );
        $req_attr = $required ? ' required' : '';
        $req_star = $required ? ' <span class="text-danger">*</span>' : '';

        // ── Text input ────────────────────────────────────────────────────
        if ( $type === 'text' ) {
            echo '<div class="mb-3">';
            echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">'
                 . esc_html( trans( $label ) ) . $req_star . '</label>';
            echo '<input type="text" name="' . esc_attr( $key ) . '" class="form-control form-control-lg"'
                 . ' placeholder="' . esc_attr( trans( $label ) ) . '"' . $req_attr . '/>';
            echo '</div>';
            return;
        }

        // ── custom_select / product_attributes ────────────────────────────
        $options = $this->getFieldOptions( $cfg );

        if ( empty( $options ) && $type === 'product_attributes' ) {
            // WC attribute bulunamadıysa sessizce çıkmayalım — uyarı göster
            echo '<div class="mb-3">';
            echo '<label class="form-label fw-semibold small">' . esc_html( trans( $label ) ) . $req_star . '</label>';
            echo '<p class="text-muted small mb-0">— No options available —</p>';
            echo '</div>';
            return;
        }

        echo '<div class="mb-3">';
        echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">'
             . esc_html( trans( $label ) ) . $req_star . '</label>';

        if ( $ui_type === 'radio' ) {
            // Radio buttons
            foreach ( $options as $val => $opt_label ) {
                $id = 'rf_' . esc_attr( $key ) . '_' . esc_attr( $val );
                echo '<div class="form-check form-check-inline">';
                echo '<input class="form-check-input" type="radio" name="' . esc_attr( $key ) . '"'
                     . ' id="' . $id . '" value="' . esc_attr( $val ) . '"' . $req_attr . '>';
                echo '<label class="form-check-label" for="' . $id . '">'
                     . esc_html( trans( $opt_label ) ) . '</label>';
                echo '</div>';
            }
        } elseif ( $ui_type === 'checkbox' ) {
            // Checkboxes — multi-value, input name="key[]"
            foreach ( $options as $val => $opt_label ) {
                $id = 'rf_' . esc_attr( $key ) . '_' . esc_attr( $val );
                echo '<div class="form-check form-check-inline">';
                echo '<input class="form-check-input" type="checkbox" name="' . esc_attr( $key ) . '[]"'
                     . ' id="' . $id . '" value="' . esc_attr( $val ) . '">';
                echo '<label class="form-check-label" for="' . $id . '">'
                     . esc_html( trans( $opt_label ) ) . '</label>';
                echo '</div>';
            }
        } else {
            // Select (default)
            echo '<select name="' . esc_attr( $key ) . '" class="form-select form-select-lg"' . $req_attr . '>';
            // Boş / placeholder seçeneği
            echo '<option value="">' . esc_html( trans( 'Select...' ) ) . '</option>';
            foreach ( $options as $val => $opt_label ) {
                echo '<option value="' . esc_attr( $val ) . '">'
                     . esc_html( trans( $opt_label ) ) . '</option>';
            }
            echo '</select>';
        }

        echo '</div>';
    }

    // ── Built-in Field Render Fonksiyonları ──────────────────────────────────

    private function renderFieldFirstName( bool $required ): void
    {
        $req = $required ? ' required' : '';
        echo '<div class="mb-3">';
        echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">' . esc_html( trans( 'First Name' ) ) . ( $required ? ' <span class="text-danger">*</span>' : '' ) . '</label>';
        echo '<input type="text" name="first_name" class="form-control form-control-lg" placeholder="' . esc_attr( trans( 'First Name' ) ) . '" autocomplete="given-name"' . $req . '/>';
        echo '</div>';
    }

    private function renderFieldLastName( bool $required ): void
    {
        $req = $required ? ' required' : '';
        echo '<div class="mb-3">';
        echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">' . esc_html( trans( 'Last Name' ) ) . ( $required ? ' <span class="text-danger">*</span>' : '' ) . '</label>';
        echo '<input type="text" name="last_name" class="form-control form-control-lg" placeholder="' . esc_attr( trans( 'Last Name' ) ) . '" autocomplete="family-name"' . $req . '/>';
        echo '</div>';
    }

    private function renderFieldPhone( bool $required ): void
    {
        $req           = $required ? ' required' : '';
        $has_countries = $this->hasCountriesTable();
        echo '<div class="mb-3">';
        echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">' . esc_html( trans( 'Phone Number' ) ) . ( $required ? ' <span class="text-danger">*</span>' : '' ) . '</label>';
        echo '<div class="input-group input-group-lg">';

        if ( $has_countries ) {
            $countries = $this->getCountriesForSelect();
            echo '<select name="phone_code" class="form-select flex-shrink-0" style="max-width:120px" aria-label="' . esc_attr( trans( 'Country code' ) ) . '">';
            foreach ( $countries as $c ) {
                $selected = ( ( $c['iso2'] ?? '' ) === 'TR' ) ? ' selected' : '';
                echo '<option value="+' . esc_attr( $c['phonecode'] ) . '" data-iso="' . esc_attr( $c['iso2'] ) . '"' . $selected . '>';
                echo '+' . esc_html( $c['phonecode'] ) . ' ' . esc_html( $c['name'] );
                echo '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="phone_code" class="form-control flex-shrink-0" style="max-width:100px" placeholder="+90" value="+90" maxlength="6"/>';
        }

        echo '<input type="tel" name="phone" class="form-control form-control-phone" placeholder="555 000 00 00" autocomplete="tel" data-pv-skip="1"' . $req . '/>';
        echo '</div>';
        echo '</div>';
    }

    private function renderFieldCountry( bool $required ): void
    {
        $req = $required ? ' required' : '';
        echo '<div class="mb-3">';
        echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">' . esc_html( trans( 'Country' ) ) . ( $required ? ' <span class="text-danger">*</span>' : '' ) . '</label>';

        if ( $this->hasCountriesTable() ) {
            $countries = $this->getCountriesForSelect();
            echo '<select name="country" class="form-select form-select-lg"' . $req . '>';
            echo '<option value="">' . esc_html( trans( 'Select your country' ) ) . '</option>';
            foreach ( $countries as $c ) {
                $flag = $this->countryFlag( $c['iso2'] ?? '' );
                echo '<option value="' . esc_attr( $c['iso2'] ) . '" data-phonecode="' . esc_attr( $c['phonecode'] ) . '" data-states="' . esc_attr( $c['states'] ?? 0 ) . '">';
                echo esc_html( $c['name'] );
                echo '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="country" class="form-control form-control-lg" placeholder="' . esc_attr( trans( 'Country' ) ) . '"' . $req . '/>';
        }

        echo '<input type="hidden" name="states_view" value="0"/>';
        echo '</div>';
    }

    private function renderFieldState( bool $required ): void
    {
        $req = $required ? ' required' : '';
        echo '<div class="mb-3" id="sh-reg-state-wrap" style="display:none">';
        echo '<label class="form-label fw-semibold small" style="display:flex;flex-direction:row;align-items:center;gap:4px;text-align:left">' . esc_html( trans( 'State / City' ) ) . ( $required ? ' <span class="text-danger">*</span>' : '' ) . '</label>';
        echo '<select name="city" class="form-select form-select-lg" id="sh-reg-state-select">';
        echo '<option value="">' . esc_html( trans( 'Select state / city' ) ) . '</option>';
        echo '</select>';
        echo '</div>';
    }

    // ── Localization Helpers ─────────────────────────────────────────────────

    /**
     * wp_countries tablosu var mı kontrol et.
     */
    private function hasCountriesTable(): bool
    {
        global $wpdb;
        static $checked = null;
        if ( $checked !== null ) return $checked;
        $checked = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}countries'" );
        return $checked;
    }

    /**
     * wp_countries tablosundan select için ülke listesi çek.
     * Statik cache — sayfa başına tek query.
     */
    private function getCountriesForSelect(): array
    {
        global $wpdb;
        static $cache = null;
        if ( $cache !== null ) return $cache;
        $cache = $wpdb->get_results(
            "SELECT iso2, name, phonecode,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}states s WHERE s.country_code = c.iso2) AS states
             FROM {$wpdb->prefix}countries c
             ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
        return $cache;
    }

    /**
     * ISO2 kodundan Unicode flag emoji üret.
     * TR → 🇹🇷, US → 🇺🇸
     */
    private function countryFlag( string $iso2 ): string
    {
        if ( strlen( $iso2 ) !== 2 ) return '';
        $iso2 = strtoupper( $iso2 );
        $flag = '';
        foreach ( str_split( $iso2 ) as $char ) {
            $flag .= mb_chr( 0x1F1E6 + ord( $char ) - ord( 'A' ), 'UTF-8' );
        }
        return $flag;
    }

    /**
     * Kayıt sonrası hook — rol set, aktivasyon, notification, autologin.
     * WooCommerce user_register hook'undan da çağrılır.
     *
     * @param int   $user_id
     * @param array $vars    POST vars (opsiyonel — WC'den gelince boş olabilir)
     * @return array
     */
    public function userRegisterHook( int $user_id, array $vars = [] ): array
    {
        $response = $this->response();

        // Register type (email / social provider adı)
        $register_type = 'email';
        $qs = json_decode( function_exists( 'queryStringJSON' ) ? queryStringJSON() : '{}', true );
        if ( ! empty( $qs['loginSocial'] ) ) {
            $register_type = sanitize_text_field( $qs['loginSocial'] );
        }
        update_user_meta( $user_id, 'register_type', $register_type );

        // Rol belirle
        $role = sanitize_text_field( $vars['role'] ?? 'default' );
        if ( empty( $role ) ) $role = 'default';

        // Rolü WP'ye set et
        $user_obj = new \WP_User( $user_id );
        $user_obj->set_role( $role );
        wp_update_user( [ 'ID' => $user_id, 'role' => $role ] );

        $this->refreshUser( $user_id );

        $password_set = false;

        if ( $role !== 'default' ) {
            $password_set = true;

            if ( self::isActivationRequired() || ( defined( 'ENABLE_SMS_NOTIFICATIONS' ) && ENABLE_SMS_NOTIFICATIONS ) ) {
                $activation_type = defined( 'MEMBERSHIP_ACTIVATION_TYPE' ) ? MEMBERSHIP_ACTIVATION_TYPE : 'email';

                if ( $activation_type === 'sms' || ( defined( 'ENABLE_SMS_NOTIFICATIONS' ) && ENABLE_SMS_NOTIFICATIONS ) ) {
                    // SMS gereksinimlerini kaydet
                    $sms_vars            = $vars;
                    $sms_vars['action']  = 'save_sms_requirements';
                    $sms_vars['refresh'] = true;
                    $response            = $this->updateProfile( $sms_vars );
                    $this->refreshUser( $user_id );

                    // Aktivasyon yoksa direkt new-account notification
                    if ( ! $response['error'] && ! self::isActivationRequired() ) {
                        $this->fireAccountEvent( 'new-account', $user_id );
                    }
                } else {
                    // Email aktivasyon gönder
                    $response = $this->sendActivation( $user_id );
                }
            } else {
                // Aktivasyon yok — direkt new-account
                $this->fireAccountEvent( 'new-account', $user_id );
            }
        }

        update_user_meta( $user_id, 'password_set', $password_set );

        // Autologin
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        // Hook: dışarıdan dinlenebilir
        do_action( 'sh_user_registered', $user_id, $role );

        // AJAX'ta response dön, değilse redirect
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            if ( empty( $response['redirect'] ) ) {
                $response['redirect'] = $this->getEndpointUrl( 'profile' );
            }
            return $response;
        }

        wp_redirect( $this->getEndpointUrl( 'profile' ) );
        exit();
    }

    /**
     * Email adresi kayıtlı mı kontrol et.
     *
     * @param array $vars ['email', 'exclude' => 'bu email hariç tut']
     * @return string|false Hata mesajı veya false
     */
    public static function userExist( array $vars = [] )
    {
        $email = sanitize_email( $vars['email'] ?? '' );

        if ( ! empty( $vars['exclude'] ) && $vars['exclude'] === $email ) {
            return false;
        }

        return email_exists( $email )
            ? 'That email address is already registered.'
            : false;
    }

    /**
     * Nickname/kullanıcı adı kayıtlı mı kontrol et.
     *
     * @param array $vars ['nickname', 'exclude', 'user_id']
     * @return string|false
     */
    public static function nicknameExist( array $vars = [] )
    {
        global $wpdb;

        $nickname = sanitize_text_field( $vars['nickname'] ?? '' );
        $user_id  = (int) ( $vars['user_id'] ?? get_current_user_id() );

        if ( ! empty( $vars['exclude'] ) && $vars['exclude'] === $nickname ) {
            return false;
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(ID) FROM {$wpdb->users} u
             JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'nickname' AND m.meta_value = %s AND u.ID <> %d",
            $nickname,
            $user_id
        ) );

        return $exists ? 'That username is already taken.' : false;
    }

    /**
     * Account event'i tetikle — Notifications app'e bildir.
     * Hem eski Notifications::on() hem yeni Notifications::fire() desteklenir.
     */
    private function fireAccountEvent( string $event, int $user_id ): void
    {
        if ( ! $this->user ) $this->refreshUser( $user_id );

        $role = $this->user ? $this->user->get_role() : 'subscriber';

        // Yeni sistem
        if ( defined( 'ENABLE_NOTIFICATIONS' ) && ENABLE_NOTIFICATIONS && class_exists( 'Notifications' ) ) {
            \Notifications::fire( $event, [
                'user'      => $this->user,
                'recipient' => $user_id,
            ] );
        }

        do_action( 'sh_membership_event', $event, $user_id );
    }
}
