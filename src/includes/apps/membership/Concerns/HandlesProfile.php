<?php

namespace SaltHareket\Membership\Concerns;

/**
 * HandlesProfile
 *
 * Profil güncelleme, profil tamamlama skoru ve hesap silme işlemleri.
 * MembershipManager tarafından trait olarak kullanılır.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-09
 *     - Add: updateProfile() — action-based dispatch (save_profile, save_password, save_email, vs.)
 *     - Add: getProfileCompletion() — profil doluluk skoru
 *     - Add: validatePhone() — RapidAPI phone validator
 *     - Add: requestAccountDeletion() — GDPR hesap silme talebi
 *     - Add: processAccountDeletion() — admin onayı ile hesap sil
 *     - Add: cancelAccountDeletion() — silme talebini iptal et
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $mm = MembershipManager::getInstance();
 *
 * // Profil güncelle:
 * $result = $mm->updateProfile(['action' => 'save_profile', 'first_name' => 'Ali']);
 *
 * // Profil tamamlama skoru:
 * $completion = $mm->getProfileCompletion($user_id);
 * // ['score' => 75, 'missing' => ['phone', 'avatar'], 'completed' => ['name', 'email']]
 *
 * // Hesap silme talebi:
 * $mm->requestAccountDeletion($user_id);
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   $result = MembershipManager::getInstance()->updateProfile(['action' => 'save_profile', 'first_name' => 'Ali']);
 *
 * @example
 *   $result = MembershipManager::getInstance()->updateProfile(['action' => 'save_password', 'current' => 'old', 'new' => 'new123', 'confirm' => 'new123']);
 *
 * @example
 *   $completion = MembershipManager::getInstance()->getProfileCompletion(42);
 *   // Twig: {{ user.profile_completion.score }}%
 *
 * @example
 *   // Profil tamamlama alanlarını özelleştir:
 *   add_filter('sh_profile_completion_fields', function($fields, $user_id) {
 *       $fields['portfolio'] = (bool) get_user_meta($user_id, 'portfolio_url', true);
 *       return $fields;
 *   }, 10, 2);
 *
 * @example
 *   add_action('sh_profile_updated', function($user_id, $action, $changed) {
 *       // log tut, cache temizle
 *   }, 10, 3);
 */
trait HandlesProfile
{
    /**
     * Profil güncelleme — action'a göre dispatch.
     * Tüm profil işlemleri tek endpoint üzerinden gelir.
     *
     * @param array $vars ['action' => 'save_profile|save_password|save_email|save_sms_requirements|save_newsletter|save_profile_photo', ...]
     */
    public function updateProfile( array $vars = [] ): array
    {
        $response = $this->response();

        // user null ise get_current_user_id() ile force-yükle
        if ( ! $this->user ) {
            $uid = get_current_user_id();
            if ( $uid ) {
                $this->user = class_exists( 'User' )
                    ? new \User( $uid )
                    : \Timber\Timber::get_user( $uid );
            }
        }

        if ( ! $this->user ) {
            $response['error']   = true;
            $response['message'] = 'You must be logged in.';
            return $response;
        }

        $action = sanitize_text_field( $vars['action'] ?? '' );

        switch ( $action ) {

            case 'profile':     // profile.twig form action alias
            case 'save_profile':
                $response = $this->saveProfile( $vars );
                break;

            case 'save_password':
                $response = $this->changePassword( $vars );
                break;

            case 'save_email':
                $response = $this->saveEmail( $vars );
                break;

            case 'save_sms_requirements':
                $response = $this->saveSmsRequirements( $vars );
                break;

            case 'save_newsletter':
                $response = $this->saveNewsletter( $vars );
                break;

            case 'save_profile_photo':
                $response = $this->saveProfilePhoto( $vars );
                break;

            case 'security':    // custom-extend alias → save_password
                $response = $this->changePassword( $vars );
                break;

            case 'renew_password':
                $response = $this->renewPassword( $vars );
                break;

            case 'set_role':
                $response = $this->setRole( $vars );
                break;

            case 'upgrade':     // upgrade flow — save_profile ile aynı
                $response = $this->saveProfile( $vars );
                break;

            default:
                // Dışarıdan action eklenebilir
                $response = apply_filters( 'sh_update_profile_action_' . $action, $response, $vars, $this->user );
                if ( empty( $response ) ) {
                    $response            = $this->response();
                    $response['error']   = true;
                    $response['message'] = 'Unknown action: ' . esc_html( $action );
                }
                break;
        }

        if ( ! $response['error'] ) {
            do_action( 'sh_profile_updated', $this->user->ID, $action, $vars );
        }

        return $response;
    }

    // ─── Profile Actions ─────────────────────────────────────────────────────

    /**
     * Temel profil bilgilerini kaydet.
     */
    private function saveProfile( array $vars ): array
    {
        $response = $this->response();
        $user_id  = $this->user->ID;

        $user_data = [ 'ID' => $user_id ];

        if ( isset( $vars['first_name'] ) ) {
            $user_data['first_name']   = sanitize_text_field( $vars['first_name'] );
            $user_data['display_name'] = sanitize_text_field( $vars['first_name'] ) . ' ' . sanitize_text_field( $vars['last_name'] ?? '' );
        }
        if ( isset( $vars['last_name'] ) ) {
            $user_data['last_name'] = sanitize_text_field( $vars['last_name'] );
        }
        if ( isset( $vars['description'] ) ) {
            $user_data['description'] = sanitize_textarea_field( $vars['description'] );
        }
        if ( isset( $vars['nickname'] ) ) {
            $nickname = sanitize_text_field( $vars['nickname'] );
            $exists   = self::nicknameExist( [ 'nickname' => $nickname, 'user_id' => $user_id ] );
            if ( $exists ) {
                $response['error']   = true;
                $response['message'] = $exists;
                return $response;
            }
            $user_data['nickname'] = $nickname;
            update_user_meta( $user_id, 'nickname', $nickname );
        }

        wp_update_user( $user_data );

        // ACF alanları — varsa kaydet
        $acf_fields = apply_filters( 'sh_profile_acf_fields', [
            'billing_country', 'billing_phone', 'billing_phone_code',
            'city', 'website', 'languages', 'language',
        ] );

        foreach ( $acf_fields as $field ) {
            if ( isset( $vars[ $field ] ) && function_exists( 'update_field' ) ) {
                update_field( $field, $vars[ $field ], 'user_' . $user_id );
            }
        }

        // ── Ek meta alanları — register_fields ile uyumlu ───────────────────
        // about
        if ( isset( $vars['about'] ) ) {
            update_user_meta( $user_id, 'about', sanitize_textarea_field( $vars['about'] ) );
        }
        // phone
        if ( isset( $vars['phone'] ) ) {
            update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $vars['phone'] ) );
        }
        if ( isset( $vars['phone_code'] ) ) {
            update_user_meta( $user_id, 'billing_phone_code', sanitize_text_field( $vars['phone_code'] ) );
        }
        // country + state/city
        if ( isset( $vars['country'] ) ) {
            update_user_meta( $user_id, 'billing_country', sanitize_text_field( $vars['country'] ) );
        }
        if ( isset( $vars['city'] ) ) {
            update_user_meta( $user_id, 'city', sanitize_text_field( $vars['city'] ) );
        }
        // ── Register fields — dinamik kayıt ────────────────────────────────
        // text, custom_select, product_attributes tiplerini handle eder
        $rf = \SaltHareket\Membership\MembershipSettings::getSetting( 'register_fields', [] );
        $builtin_handled = [ 'first_name', 'last_name', 'phone', 'country', 'state' ]; // özel mantıkla handle edildi
        foreach ( $rf as $field_key => $field_cfg ) {
            if ( empty( $field_cfg['enabled'] ) ) continue;
            if ( in_array( $field_key, $builtin_handled, true ) ) continue;

            $type    = $field_cfg['type'] ?? 'text';
            $ui_type = $field_cfg['ui_type'] ?? 'select';

            if ( $type === 'text' ) {
                if ( isset( $vars[ $field_key ] ) ) {
                    update_user_meta( $user_id, $field_key, sanitize_text_field( $vars[ $field_key ] ) );
                }
            } elseif ( in_array( $type, [ 'custom_select', 'product_attributes' ], true ) ) {
                if ( ! isset( $vars[ $field_key ] ) ) continue;

                if ( $ui_type === 'checkbox' ) {
                    $submitted = (array) $vars[ $field_key ];
                    $safe      = array_map( 'sanitize_text_field', $submitted );
                    update_user_meta( $user_id, $field_key, $safe );
                } else {
                    // select / radio
                    $value = sanitize_text_field( $vars[ $field_key ] );
                    update_user_meta( $user_id, $field_key, $value );
                }
            }
        }
        // website (user_url)
        if ( isset( $vars['user_url'] ) ) {
            wp_update_user( [ 'ID' => $user_id, 'user_url' => esc_url_raw( $vars['user_url'] ) ] );
        }
        // Social media — repeater formatı: social_media[brand][] + social_media[url][]
        if ( isset( $vars['social_media'] ) && is_array( $vars['social_media'] ) ) {
            $allowed_networks = array_keys( \SaltHareket\SocialShare\SocialShare::networks() );

            // Önce tüm eski sosyal medya meta'larını temizle
            foreach ( $allowed_networks as $net_key ) {
                delete_user_meta( $user_id, $net_key );
            }

            $items = $vars['social_media'];

            // Format 1: indexed — social_media[0][brand], social_media[0][url]
            // Format 2: grouped — social_media[brand][], social_media[url][]
            if ( isset( $items[0] ) || ( ! empty( $items ) && is_array( reset( $items ) ) ) ) {
                // Format 1: indexed array
                foreach ( $items as $item ) {
                    if ( ! is_array( $item ) ) continue;
                    $brand = sanitize_key( $item['brand'] ?? '' );
                    $url   = esc_url_raw( $item['url']   ?? '' );
                    if ( $brand && $url && in_array( $brand, $allowed_networks, true ) ) {
                        update_user_meta( $user_id, $brand, $url );
                    }
                }
            } else {
                // Format 2: grouped
                $brands = (array) ( $items['brand'] ?? [] );
                $urls   = (array) ( $items['url']   ?? [] );
                foreach ( $brands as $i => $brand ) {
                    $brand = sanitize_key( $brand );
                    $url   = esc_url_raw( $urls[ $i ] ?? '' );
                    if ( $brand && $url && in_array( $brand, $allowed_networks, true ) ) {
                        update_user_meta( $user_id, $brand, $url );
                    }
                }
            }
        }

        // Profil tamamlama kontrolü
        $completion = $this->getProfileCompletion( $user_id );
        if ( $completion['score'] >= 100 ) {
            update_user_meta( $user_id, 'profile_completed', 1 );
        }

        $this->refreshUser( $user_id );

        $response['message'] = 'Profile updated successfully.';
        $response['refresh'] = (bool) ( $vars['refresh'] ?? false );
        return $response;
    }

    /**
     * Email değişikliği — doğrulama linki gönder.
     */
    private function saveEmail( array $vars ): array
    {
        $response = $this->response();
        $user_id  = $this->user->ID;
        $new_email = sanitize_email( $vars['email'] ?? '' );

        if ( ! is_email( $new_email ) ) {
            $response['error']   = true;
            $response['message'] = 'Please enter a valid email address.';
            return $response;
        }

        if ( $new_email === $this->user->user_email ) {
            $response['error']   = true;
            $response['message'] = 'This is already your current email address.';
            return $response;
        }

        if ( email_exists( $new_email ) ) {
            $response['error']   = true;
            $response['message'] = 'This email address is already registered.';
            return $response;
        }

        // Email change cooldown — günde 1 kez
        $last_change = (int) get_user_meta( $user_id, 'email_changed_at', true );
        if ( $last_change && ( time() - $last_change ) < DAY_IN_SECONDS ) {
            $response['error']   = true;
            $response['message'] = 'You can only change your email once per day.';
            return $response;
        }

        update_user_meta( $user_id, '_email_temp', $new_email );
        update_user_meta( $user_id, 'email_changed_at', time() );

        $sent = self::sendEmailChangeLink( $user_id );

        if ( ! $sent ) {
            $response['error']   = true;
            $response['message'] = 'Could not send the verification email.';
            return $response;
        }

        $response['message'] = 'A verification link has been sent to ' . esc_html( $new_email ) . '. Please check your email.';
        return $response;
    }

    /**
     * SMS gereksinimleri kaydet (telefon, ülke kodu).
     */
    private function saveSmsRequirements( array $vars ): array
    {
        $response = $this->response();
        $user_id  = $this->user->ID;

        $phone      = sanitize_text_field( $vars['billing_phone'] ?? '' );
        $phone_code = sanitize_text_field( $vars['billing_phone_code'] ?? '' );
        $country    = sanitize_text_field( $vars['billing_country'] ?? '' );

        if ( empty( $phone ) ) {
            $response['error']   = true;
            $response['message'] = 'Phone number is required.';
            return $response;
        }

        // Telefon doğrulama (opsiyonel)
        if ( ! empty( $country ) && defined( 'PHONE_VALIDATOR_KEYS' ) ) {
            $validation = $this->validatePhone( $phone, $country, $phone_code );
            if ( $validation['error'] ) {
                return $validation;
            }
        }

        if ( function_exists( 'update_field' ) ) {
            update_field( 'billing_phone', $phone, 'user_' . $user_id );
            update_field( 'billing_phone_code', $phone_code, 'user_' . $user_id );
            update_field( 'billing_country', $country, 'user_' . $user_id );
        } else {
            update_user_meta( $user_id, 'billing_phone', $phone );
            update_user_meta( $user_id, 'billing_phone_code', $phone_code );
            update_user_meta( $user_id, 'billing_country', $country );
        }

        $this->refreshUser( $user_id );

        $response['message'] = 'Phone number saved.';
        $response['refresh'] = (bool) ( $vars['refresh'] ?? false );
        return $response;
    }

    /**
     * Newsletter tercihi kaydet.
     */
    private function saveNewsletter( array $vars ): array
    {
        $response  = $this->response();
        $subscribe = ! empty( $vars['newsletter'] );

        // Newsletter plugin entegrasyonu — varsa kullan
        if ( class_exists( 'Newsletter' ) ) {
            // newsletter plugin'i ile entegrasyon
            // Burada newsletter plugin'inin API'si kullanılabilir
        }

        update_user_meta( $this->user->ID, 'newsletter_subscribed', $subscribe ? 1 : 0 );

        $response['message'] = $subscribe ? 'Subscribed to newsletter.' : 'Unsubscribed from newsletter.';
        return $response;
    }

    /**
     * Profil fotoğrafı kaydet.
     */
    private function saveProfilePhoto( array $vars ): array
    {
        $response = $this->response();
        $user_id  = $this->user->ID;

        $attachment_id = (int) ( $vars['profile_image'] ?? 0 );

        if ( $attachment_id < 1 ) {
            $response['error']   = true;
            $response['message'] = 'Invalid image.';
            return $response;
        }

        if ( function_exists( 'update_field' ) ) {
            update_field( 'profile_image', $attachment_id, 'user_' . $user_id );
        } else {
            update_user_meta( $user_id, 'profile_image', $attachment_id );
        }

        $this->refreshUser( $user_id );

        $response['message'] = 'Profile photo updated.';
        $response['refresh'] = true;
        return $response;
    }

    // ─── Phone Validation ────────────────────────────────────────────────────

    /**
     * Telefon numarasını doğrula (RapidAPI).
     */
    public function validatePhone( string $phone, string $country, string $phone_code = '' ): array
    {
        $response = $this->response();

        if ( empty( $phone ) || empty( $country ) ) {
            $response['error']   = true;
            $response['message'] = 'Phone number and country are required.';
            return $response;
        }

        $full_phone = $phone_code . $phone;

        if ( strlen( $full_phone ) < 5 ) {
            $response['error']   = true;
            $response['message'] = 'Phone number is too short.';
            return $response;
        }

        if ( ! defined( 'PHONE_VALIDATOR_KEYS' ) ) {
            return $response; // Validator yoksa geç
        }

        $url = PHONE_VALIDATOR_KEYS['url'];
        $url = str_replace( '{phone}', urlencode( $full_phone ), $url );
        $url = str_replace( '{country}', urlencode( $country ), $url );

        $result = wp_remote_get( $url, [
            'headers' => [
                'X-RapidAPI-Key'  => PHONE_VALIDATOR_KEYS['X-RapidAPI-Key'],
                'X-RapidAPI-Host' => PHONE_VALIDATOR_KEYS['X-RapidAPI-Host'],
            ],
        ] );

        if ( is_wp_error( $result ) ) {
            $response['error']   = true;
            $response['message'] = 'Phone validation service unavailable.';
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $result ), true );

        if ( empty( $data['isValidNumber'] ) ) {
            $reason = $data['isPossibleNumberWithReason'] ?? '';
            $response['error']   = true;
            $response['message'] = match ( $reason ) {
                'TOO_SHORT'      => 'Phone number is too short.',
                'INVALID_LENGTH' => 'Phone number length is invalid.',
                default          => 'Phone number is not valid.',
            };
        }

        return $response;
    }

    // ─── Profile Completion ──────────────────────────────────────────────────

    /**
     * Profil tamamlama skoru hesapla.
     *
     * @return array ['score' => int, 'missing' => string[], 'completed' => string[]]
     */
    public function getProfileCompletion( int $user_id = 0 ): array
    {
        if ( ! $user_id ) $user_id = $this->user->ID ?? get_current_user_id();

        $user = get_userdata( $user_id );
        if ( ! $user ) return [ 'score' => 0, 'missing' => [], 'completed' => [] ];

        // Temel alanlar — her proje için filter ile özelleştirilebilir
        $fields = [
            'first_name'    => ! empty( get_user_meta( $user_id, 'first_name', true ) ),
            'last_name'     => ! empty( get_user_meta( $user_id, 'last_name', true ) ),
            'email'         => ! empty( $user->user_email ),
            'avatar'        => ! empty( get_user_meta( $user_id, 'profile_image', true ) ),
            'phone'         => ! empty( get_user_meta( $user_id, 'billing_phone', true ) ),
            'country'       => ! empty( get_user_meta( $user_id, 'billing_country', true ) ),
        ];

        // Dışarıdan alan ekle/çıkar
        $fields = apply_filters( 'sh_profile_completion_fields', $fields, $user_id );

        $total     = count( $fields );
        $completed = array_keys( array_filter( $fields ) );
        $missing   = array_keys( array_filter( $fields, fn( $v ) => ! $v ) );
        $score     = $total > 0 ? (int) round( count( $completed ) / $total * 100 ) : 0;

        return [
            'score'     => $score,
            'completed' => $completed,
            'missing'   => $missing,
        ];
    }

    // ─── Account Deletion (GDPR) ─────────────────────────────────────────────

    /**
     * Hesap silme talebi oluştur.
     * 30 gün bekleme süresi — admin onaylar veya otomatik silinir.
     */
    public function requestAccountDeletion( int $user_id ): array
    {
        $response = $this->response();

        // Zaten talep var mı?
        $existing = get_user_meta( $user_id, 'deletion_requested_at', true );
        if ( $existing ) {
            $response['error']   = true;
            $response['message'] = 'A deletion request is already pending.';
            return $response;
        }

        $deletion_date = time() + ( 30 * DAY_IN_SECONDS );
        update_user_meta( $user_id, 'deletion_requested_at', time() );
        update_user_meta( $user_id, 'deletion_scheduled_at', $deletion_date );

        do_action( 'sh_account_deletion_requested', $user_id, $deletion_date );

        // Admin'e bildirim
        $user      = get_userdata( $user_id );
        $site_name = get_bloginfo( 'name' );
        wp_mail(
            get_bloginfo( 'admin_email' ),
            $site_name . ' — Account Deletion Request',
            'User ' . $user->user_email . ' has requested account deletion. Scheduled for: ' . date( 'Y-m-d', $deletion_date )
        );

        $response['message'] = 'Your account deletion request has been received. Your account will be deleted in 30 days.';
        return $response;
    }

    /**
     * Hesap silme talebini iptal et.
     */
    public function cancelAccountDeletion( int $user_id ): array
    {
        $response = $this->response();

        delete_user_meta( $user_id, 'deletion_requested_at' );
        delete_user_meta( $user_id, 'deletion_scheduled_at' );

        do_action( 'sh_account_deletion_cancelled', $user_id );

        $response['message'] = 'Account deletion request cancelled.';
        return $response;
    }

    /**
     * Hesabı sil (admin onayı veya cron ile).
     */
    public function processAccountDeletion( int $user_id ): bool
    {
        $requested = get_user_meta( $user_id, 'deletion_requested_at', true );
        if ( ! $requested ) return false;

        do_action( 'sh_before_account_deleted', $user_id );

        // WP kullanıcı sil
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $result = wp_delete_user( $user_id );

        if ( $result ) {
            do_action( 'sh_account_deleted', $user_id );
        }

        return $result;
    }

    /**
     * Şifre sıfırlama linki ile yeni şifre kaydet (renew_password action).
     */
    private function renewPassword( array $vars ): array
    {
        $response = $this->response();
        $code     = $vars['activation-password'] ?? '';

        if ( ! class_exists( 'Encrypt' ) ) {
            $response['error']   = true;
            $response['message'] = 'Encryption class not found.';
            return $response;
        }

        $decrypt = new \Encrypt();
        $data    = $decrypt->decrypt( $code );

        if ( ! $data || empty( $data['email'] ) ) {
            $response['error']   = true;
            $response['message'] = trans( 'Your password renew link is invalid.' );
            return $response;
        }

        $user = get_user_by( 'email', $data['email'] );
        if ( ! $user ) {
            $response['error']   = true;
            $response['message'] = trans( 'The user you are trying to update the password for could not be found.' );
            return $response;
        }

        $password = $vars['password'] ?? '';
        wp_set_password( $password, $user->ID );
        $response['message']  = trans( 'Your password updated!' );
        $response['redirect'] = function_exists( 'Data' ) ? \Data::get( 'base_urls.account' ) : home_url( '/' );

        return $response;
    }

    /**
     * Kullanıcı rolünü ata (set_role action).
     */
    private function setRole( array $vars ): array
    {
        $response = $this->response();
        $user_id  = $this->user->ID;
        $role     = sanitize_key( $vars['role'] ?? '' );

        $membership_roles = function_exists( 'Data' ) ? ( \Data::get( 'membership_roles' ) ?: [] ) : [];

        if ( ! $role || ! in_array( $role, $membership_roles, true ) ) {
            $response['error']   = true;
            $response['message'] = 'Role (' . esc_html( $role ) . ') is not supported!';
            return $response;
        }

        wp_update_user( [ 'ID' => $user_id, 'role' => $role ] );
        update_user_meta( $user_id, 'billing_first_name', $this->user->first_name ?? '' );
        update_user_meta( $user_id, 'billing_last_name',  $this->user->last_name  ?? '' );
        update_user_meta( $user_id, 'billing_email',      $this->user->user_email ?? '' );

        // Aktivasyon gerekiyorsa
        if ( defined( 'ENABLE_MEMBERSHIP_ACTIVATION' ) && ENABLE_MEMBERSHIP_ACTIVATION ) {
            $activation_type = defined( 'MEMBERSHIP_ACTIVATION_TYPE' ) ? MEMBERSHIP_ACTIVATION_TYPE : 'email';
            if ( in_array( $activation_type, [ 'sms', 'email' ], true ) ) {
                $vars['action'] = 'save_sms_requirements';
                $response = $this->saveSmsRequirements( $vars );
            }
        }

        do_action( 'sh_user_role_set', $user_id, $role );

        $response['refresh'] = true;
        return $response;
    }
}
