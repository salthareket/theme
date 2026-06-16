<?php

namespace SaltHareket\Reactions\Admin;

use SaltHareket\Reactions\Reactions;
use SaltHareket\Reactions\ReactionsSettings;

/**
 * ReactionsAjax
 * AJAX handler'lari ve button renderer.
 * turbo_api_handle filter uzerinden reaction_toggle, reaction_count, reaction_state handle edilir.
 *
 * @version 1.3.0
 * @changelog
 *   1.3.0 - 2026-05-08
 *     - Add: getSavedButtonConfig() — Generator'daki kayitli button config'ini dondurur (static cache)
 *     - Change: renderButton() — $options eksik degerler Generator config'den otomatik gelir
 *     - Change: renderButton() — subtype belirleme (get_post_type, term taxonomy, user role)
 *     - Add: getSavedButtonConfig() — object_type + subtype + type ile eslesme, wildcard fallback
 *   1.2.0 - 2026-05-08
 *     - Add: getLoginUrl() — WooCommerce my-account veya custom login page'e yonlendirir
 *     - Add: handleToggle() — amount parametresi (cumulative bulk increment)
 *     - Add: renderButton() — data-reaction-mode, data-reaction-limit, data-reaction-value attribute'lari
 *     - Add: renderButton() — type enabled kontrolu (pasif type render olmaz)
 *     - Fix: require_login=false — array_key_exists ile dogru boolean kontrolu
 *     - Remove: getPlacementForObject() — Placements tab kaldirildi
 *     - Add: toggleGuestCookie() — mode + amount destegi (cumulative value cookie'ye yazilir)
 *     - Add: migrateGuestCookie() — cumulative value ile interact(), toggle/additive icin has() kontrolu
 *   1.1.0 - 2026-05-07 *     - Add: isButtonDisabled() — Generator'daki pasif button kontrolu
 *     - Add: migrateGuestCookie() — login'de guest cookie'yi DB'ye tasir
 *   1.0.0 - 2026-05-06 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Button render (PHP):
 * ReactionsAjax::renderButton(42, 'post', 'like', ['style' => 'icon-count']);
 * ReactionsAjax::renderButton(42, 'post', 'clap', ['style' => 'icon-count', 'require_login' => false]);
 *
 * // Button render (Twig):
 * {{ function('salt_reaction_button', post.ID, 'post', 'like', {'style': 'pill'}) }}
 * {{ function('salt_reaction_button', post.ID, 'post', 'clap', {'style': 'icon-count', 'require_login': false}) }}
 *
 * // AJAX toggle (JS fetch):
 * fetch('/api/reaction_toggle', {
 *   method: 'POST',
 *   headers: { 'X-WP-Nonce': saltConfig.nonce, 'Content-Type': 'application/json' },
 *   body: JSON.stringify({ vars: { type: 'clap', object_id: 42, object_type: 'post', amount: 10, require_login: false } })
 * })
 * // → { success: true, action: 'incremented', count: 284, value: 23, limit: 50, has_reaction: true }
 *
 * // Guest cookie migration on login:
 * ReactionsAjax::migrateGuestCookie($user_id);
 *
 * ──────────────────────────────────────────────────────────
 */
class ReactionsAjax {

    // ─── TOGGLE ──────────────────────────────────────────

    /**
     * reaction_toggle AJAX handler.
     *
     * @param array $vars  { type, object_id, object_type }
     * @return array
     */
    public static function handleToggle( array $vars ): array {
        $type        = sanitize_key( $vars['type'] ?? '' );
        $object_id   = (int) ( $vars['object_id'] ?? 0 );
        $object_type = sanitize_key( $vars['object_type'] ?? 'post' );
        $amount      = max( 1, (int) ( $vars['amount'] ?? 1 ) );

        if ( ! $type || $object_id < 1 ) {
            return [ 'error' => true, 'message' => 'Invalid parameters' ];
        }

        if ( ! ReactionsSettings::getType( $type ) ) {
            return [ 'error' => true, 'message' => 'Unknown reaction type: ' . $type ];
        }

        // Guest kullanıcı
        if ( ! is_user_logged_in() ) {
            $require_login = isset( $vars['require_login'] ) ? (bool) $vars['require_login'] : true;
            if ( $require_login ) {
                return [
                    'error'     => true,
                    'message'   => 'login_required',
                    'login_url' => self::getLoginUrl( get_permalink( $object_id ) ?: home_url() ),
                ];
            }

            // Login gerektirmeyen reaction → ensureGuest() + DB'ye yaz
            $guest_id = \SaltHareket\Membership\GuestIdentity::ensureGuest();
            $result   = Reactions::interact( $type, $object_id, $object_type, 0, $amount, $guest_id );
            $count    = Reactions::count( $type, $object_id, $object_type );
            return array_merge( $result, [ 'error' => false, 'count' => $count ] );
        }

        // Logged-in
        $result = Reactions::interact( $type, $object_id, $object_type, 0, $amount );
        return array_merge( $result, [ 'error' => ! $result['success'] ] );
    }

    // ─── COUNT ───────────────────────────────────────────

    /**
     * reaction_count AJAX handler.
     * Tek veya bulk count döndürür.
     *
     * @param array $vars  { type, object_type, object_id } veya { type, object_type, object_ids: [] }
     * @return array
     */
    public static function handleCount( array $vars ): array {
        $type        = sanitize_key( $vars['type'] ?? 'like' );
        $object_type = sanitize_key( $vars['object_type'] ?? 'post' );

        if ( ! empty( $vars['object_ids'] ) && is_array( $vars['object_ids'] ) ) {
            $ids    = array_map( 'intval', $vars['object_ids'] );
            $counts = Reactions::counts( $ids, $object_type, $type );
            return [ 'error' => false, 'counts' => $counts ];
        }

        $object_id = (int) ( $vars['object_id'] ?? 0 );
        if ( $object_id < 1 ) {
            return [ 'error' => true, 'message' => 'Invalid object_id' ];
        }

        return [ 'error' => false, 'count' => Reactions::count( $type, $object_id, $object_type ) ];
    }

    // ─── STATE ───────────────────────────────────────────

    /**
     * reaction_state AJAX handler.
     * Mevcut kullanıcının reaction durumunu döndürür.
     *
     * @param array $vars  { type, object_type, object_id } veya { type, object_type, object_ids: [] }
     * @return array
     */
    public static function handleState( array $vars ): array {
        $type        = sanitize_key( $vars['type'] ?? 'like' );
        $object_type = sanitize_key( $vars['object_type'] ?? 'post' );
        $user_id     = get_current_user_id();

        // ── Toplu hydration (WP Rocket cache uyumu) ──────────────────────────
        // JS: { vars: { items: [{object_id, object_type, type}, ...] } }
        // Count + has state döner.
        // Count: object_type+type bazında gruplandırılmış tek IN sorgusu
        // Has: sadece login'li kullanıcı için, sadece true olanlar döner
        if ( ! empty( $vars['items'] ) && is_array( $vars['items'] ) ) {
            $user_id = get_current_user_id();

            // object_type+type bazında grupla — her grup için tek COUNT sorgusu
            $groups = [];
            foreach ( $vars['items'] as $item ) {
                $oid  = (int) ( $item['object_id']   ?? 0 );
                $otyp = sanitize_key( $item['object_type'] ?? 'post' );
                $typ  = sanitize_key( $item['type']        ?? 'like' );
                if ( ! $oid ) continue;
                $groups[ $otyp . '|' . $typ ][] = $oid;
            }

            // Her grup için tek sorgu ile tüm count'ları al
            $count_map = []; // "oid|otyp|typ" => count
            foreach ( $groups as $group_key => $oids ) {
                [ $otyp, $typ ] = explode( '|', $group_key, 2 );
                $counts = Reactions::counts( $oids, $otyp, $typ );
                foreach ( $counts as $oid => $cnt ) {
                    $count_map[ $oid . '|' . $otyp . '|' . $typ ] = $cnt;
                }
            }

            // Has state — login'li kullanıcı için tek sorgu
            $has_map = []; // "oid|otyp|typ" => bool
            if ( $user_id ) {
                global $wpdb;
                $table = $wpdb->prefix . 'reactions';
                foreach ( $groups as $group_key => $oids ) {
                    [ $otyp, $typ ] = explode( '|', $group_key, 2 );
                    $ids_escaped = implode( ',', array_map( 'intval', $oids ) );
                    $rows = $wpdb->get_results( $wpdb->prepare(
                        "SELECT object_id FROM {$table}
                         WHERE user_id = %d AND object_type = %s AND type = %s
                         AND object_id IN ({$ids_escaped})",
                        $user_id, $otyp, $typ
                    ) );
                    foreach ( $rows as $row ) {
                        $has_map[ (int)$row->object_id . '|' . $otyp . '|' . $typ ] = true;
                    }
                }
            }

            // Sonuçları birleştir
            $results = [];
            foreach ( $vars['items'] as $item ) {
                $oid  = (int) ( $item['object_id']   ?? 0 );
                $otyp = sanitize_key( $item['object_type'] ?? 'post' );
                $typ  = sanitize_key( $item['type']        ?? 'like' );
                if ( ! $oid ) continue;
                $map_key = $oid . '|' . $otyp . '|' . $typ;
                $results[] = [
                    'object_id'   => $oid,
                    'object_type' => $otyp,
                    'type'        => $typ,
                    'count'       => $count_map[ $map_key ] ?? 0,
                    'has'         => $has_map[ $map_key ] ?? false,
                ];
            }

            return [ 'error' => false, 'results' => $results ];
        }

        // ── Eski tek-item API (geriye uyumluluk) ─────────────────────────────
        if ( ! $user_id ) {
            return [ 'error' => false, 'has' => false, 'states' => [] ];
        }

        if ( ! empty( $vars['object_ids'] ) && is_array( $vars['object_ids'] ) ) {
            $ids    = array_map( 'intval', $vars['object_ids'] );
            $states = [];
            foreach ( $ids as $id ) {
                $states[ $id ] = Reactions::has( $type, $id, $object_type, $user_id );
            }
            return [ 'error' => false, 'states' => $states ];
        }

        $object_id = (int) ( $vars['object_id'] ?? 0 );
        return [
            'error' => false,
            'has'   => Reactions::has( $type, $object_id, $object_type, $user_id ),
            'count' => Reactions::count( $type, $object_id, $object_type ),
        ];
    }

    // ─── BUTTON RENDERER ─────────────────────────────────

    /**
     * Reaction butonu HTML'i üret.
     *
     * @param int    $object_id
     * @param string $object_type   'post' | 'user' | 'comment' | 'term'
     * @param string $type          'like' | 'follow' | 'favorite' | 'bookmark' | custom
     * @param array  $options {
     *   @type string $style         'icon-only'|'icon-count'|'icon-text'|'text-only'|'pill'
     *   @type bool   $show_count    Sayı göster (default: true)
     *   @type string $class         Ekstra CSS class
     *   @type bool   $require_login Login gerektir (default: true)
     * }
     * @return string  HTML
     */
    public static function renderButton(
        int    $object_id,
        string $object_type,
        string $type,
        array  $options = []
    ): string {
        $type_def = ReactionsSettings::getType( $type );
        if ( ! $type_def ) return '';

        // Type pasifse render etme
        if ( isset( $type_def['enabled'] ) && ! $type_def['enabled'] ) return '';

        // Saved buttons'da pasif mi kontrol et
        if ( self::isButtonDisabled( $object_type, $type ) ) return '';

        // Subtype'i belirle — Generator config lookup icin
        $subtype = '';
        if ( $object_type === 'post' && $object_id > 0 ) {
            $subtype = get_post_type( $object_id ) ?: '';
        } elseif ( $object_type === 'term' && $object_id > 0 ) {
            $term    = get_term( $object_id );
            $subtype = ( $term && ! is_wp_error( $term ) ) ? $term->taxonomy : '';
        } elseif ( $object_type === 'user' && $object_id > 0 ) {
            $u       = get_userdata( $object_id );
            $roles   = $u ? (array) $u->roles : [];
            $subtype = $roles[0] ?? '';
        }

        // Generator'daki kayitli button config'ini al (static cache — tek DB sorgusu)
        $saved = self::getSavedButtonConfig( $object_type, $subtype, $type );

        // $options parametresi oncelikli, eksik degerler saved config'den gelir, o da yoksa default
        $style         = $options['style']      ?? $saved['style']      ?? 'icon-count';
        $show_count    = array_key_exists( 'show_count', $options )
                         ? (bool) $options['show_count']
                         : ( isset( $saved['show_count'] ) ? (bool) $saved['show_count'] : true );
        $require_login = array_key_exists( 'require_login', $options )
                         ? (bool) $options['require_login']
                         : ( array_key_exists( 'require_login', (array) $saved )
                             ? (bool) $saved['require_login']
                             : false ); // default: login gerekmez
        $extra_class   = isset( $options['class'] )
                         ? esc_attr( $options['class'] )
                         : esc_attr( $saved['class'] ?? '' );

        // State
        $is_logged_in = is_user_logged_in();
        $has          = $is_logged_in ? Reactions::has( $type, $object_id, $object_type ) : false;
        $count        = (bool) $show_count ? Reactions::count( $type, $object_id, $object_type ) : 0;
        $mode         = $type_def['mode'] ?? 'toggle';
        $limit        = (int) ( $type_def['limit'] ?? 0 );
        $user_value   = ( $is_logged_in && $mode === 'cumulative' )
                        ? Reactions::getUserValue( $type, $object_id, $object_type )
                        : 0;

        // Icons & labels
        $icon_off_val = $type_def['icon_off'] ?? 'far fa-circle';
        $icon_on_val  = $type_def['icon_on']  ?? 'fas fa-circle';
        $label        = esc_html( $has ? ( $type_def['label_on'] ?? $type_def['label'] ?? ucfirst( $type ) ) : ( $type_def['label'] ?? ucfirst( $type ) ) );
        $color        = esc_attr( $type_def['color'] ?? '#6b7280' );

        // Icon HTML — FA class veya attachment ID
        $icon_off_html = self::renderIconHtml( $icon_off_val, '#9ca3af' );
        $icon_on_html  = self::renderIconHtml( $icon_on_val, $color );
        $icon_cls      = $has ? $icon_on_val : $icon_off_val; // buildInner icin

        // CSS classes
        $btn_class = trim( implode( ' ', array_filter( [
            'salt-reaction-btn',
            'salt-reaction--' . esc_attr( $type ),
            'salt-reaction--' . esc_attr( $style ),
            $has ? 'is-active' : '',
            $extra_class,
        ] ) ) );

        // Data attrs — icon HTML'i base64 encode ile gonder (attribute icinde HTML guvenli)
        $data = sprintf(
            'data-reaction-type="%s" data-reaction-object="%d" data-reaction-object-type="%s" data-reaction-style="%s" data-reaction-color="%s" data-reaction-mode="%s" data-icon-off="%s" data-icon-on="%s"',
            esc_attr( $type ),
            $object_id,
            esc_attr( $object_type ),
            esc_attr( $style ),
            $color,
            esc_attr( $mode ),
            esc_attr( base64_encode( $icon_off_html ) ),
            esc_attr( base64_encode( $icon_on_html ) )
        );

        if ( $mode === 'cumulative' && $limit > 0 ) {
            $data .= sprintf( ' data-reaction-limit="%d" data-reaction-value="%d"', $limit, $user_value );
        }

        if ( $require_login && ! $is_logged_in ) {
            $data .= sprintf( ' data-reaction-login="%s"', esc_url( self::getLoginUrl( get_permalink( $object_id ) ?: home_url() ) ) );
        }

        $inner = self::buildInner( $style, $icon_cls, $label, $count, $has, $color );

        return sprintf(
            '<button type="button" class="%s" %s aria-label="%s" aria-pressed="%s">%s</button>',
            esc_attr( $btn_class ),
            $data,
            esc_attr( $type_def['label'] ?? $type ),
            $has ? 'true' : 'false',
            $inner
        );
    }

    // ─── GUEST COOKIE ────────────────────────────────────

    /**
     * Guest cookie'de reaction isle — mode'a gore toggle/additive/cumulative.
     * Cookie key: sh_guest_reactions → JSON array
     * Her item: { type, object_id, object_type, value }
     * value: toggle/additive=1, cumulative=tik sayisi
     */
    public static function toggleGuestCookie( string $type, int $object_id, string $object_type, string $mode = 'toggle', int $amount = 1 ): array {
        $key       = 'sh_guest_reactions';
        $raw       = isset( $_COOKIE[ $key ] ) ? urldecode( $_COOKIE[ $key ] ) : '[]';
        $reactions = json_decode( $raw, true );
        if ( ! is_array( $reactions ) ) $reactions = [];

        $found_idx = null;
        foreach ( $reactions as $i => $item ) {
            if (
                (int) ( $item['object_id'] ?? 0 ) === $object_id &&
                ( $item['object_type'] ?? '' ) === $object_type &&
                ( $item['type'] ?? '' ) === $type
            ) {
                $found_idx = $i;
                break;
            }
        }

        $action      = 'added';
        $new_value   = 1;
        $has_reaction = true;

        if ( $mode === 'cumulative' ) {
            $type_config   = ReactionsSettings::getType( $type );
            $limit         = (int) ( $type_config['limit'] ?? 50 );
            $current_value = $found_idx !== null ? (int) ( $reactions[ $found_idx ]['value'] ?? 0 ) : 0;

            if ( $current_value >= $limit ) {
                // Limit doldu
                $json = wp_json_encode( $reactions );
                if ( ! headers_sent() ) setcookie( $key, $json, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
                $_COOKIE[ $key ] = $json;
                return [ 'success' => true, 'action' => 'limit_reached', 'has_reaction' => true, 'value' => $current_value, 'limit' => $limit ];
            }

            $add       = min( $amount, $limit - $current_value );
            $new_value = $current_value + $add;
            $action    = $new_value >= $limit ? 'limit_reached' : 'incremented';

            if ( $found_idx !== null ) {
                $reactions[ $found_idx ]['value'] = $new_value;
            } else {
                $reactions[] = [ 'type' => $type, 'object_id' => $object_id, 'object_type' => $object_type, 'value' => $new_value ];
            }

        } elseif ( $mode === 'additive' ) {
            // Sadece ekle, kaldir yok
            if ( $found_idx === null ) {
                $reactions[] = [ 'type' => $type, 'object_id' => $object_id, 'object_type' => $object_type, 'value' => 1 ];
                $action = 'added';
            } else {
                $action = 'exists';
            }

        } else {
            // Toggle
            if ( $found_idx !== null ) {
                unset( $reactions[ $found_idx ] );
                $action       = 'removed';
                $has_reaction = false;
                $new_value    = 0;
            } else {
                $reactions[] = [ 'type' => $type, 'object_id' => $object_id, 'object_type' => $object_type, 'value' => 1 ];
            }
        }

        $reactions = array_values( $reactions );
        $json      = wp_json_encode( $reactions );

        if ( ! headers_sent() ) {
            setcookie( $key, $json, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        }
        $_COOKIE[ $key ] = $json;

        return [
            'success'      => true,
            'action'       => $action,
            'has_reaction' => $has_reaction,
            'value'        => $new_value,
        ];
    }

    /**
     * Login'de guest cookie'yi DB'ye migrate et.
     * bootstrap.php'deki wp_login hook'undan cagrilir.
     * Toggle: yoksa ekle. Cumulative: value kadar interact. Additive: yoksa ekle.
     */
    public static function migrateGuestCookie( int $user_id ): void {
        if ( $user_id < 1 ) return;

        $key = 'sh_guest_reactions';
        if ( empty( $_COOKIE[ $key ] ) ) return;

        $raw       = urldecode( $_COOKIE[ $key ] );
        $reactions = json_decode( $raw, true );
        if ( ! is_array( $reactions ) ) return;

        foreach ( $reactions as $item ) {
            $type        = sanitize_key( $item['type'] ?? '' );
            $object_id   = (int) ( $item['object_id'] ?? 0 );
            $object_type = sanitize_key( $item['object_type'] ?? '' );
            $value       = max( 1, (int) ( $item['value'] ?? 1 ) );

            if ( ! $type || $object_id < 1 || ! $object_type ) continue;

            $type_config = ReactionsSettings::getType( $type );
            if ( ! $type_config ) continue;

            $mode = $type_config['mode'] ?? 'toggle';

            if ( $mode === 'cumulative' ) {
                // Mevcut DB value'sunu al, eksigi tamamla
                $current_db = Reactions::getUserValue( $type, $object_id, $object_type, $user_id );
                $remaining  = $value - $current_db;
                if ( $remaining > 0 ) {
                    Reactions::interact( $type, $object_id, $object_type, $user_id, $remaining );
                }
            } else {
                // Toggle / Additive: sadece yoksa ekle
                if ( ! Reactions::has( $type, $object_id, $object_type, $user_id ) ) {
                    Reactions::interact( $type, $object_id, $object_type, $user_id );
                }
            }
        }

        if ( ! headers_sent() ) {
            setcookie( $key, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        }
        unset( $_COOKIE[ $key ] );
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────

    /**
     * Saved buttons'da bu object_type + type kombinasyonu pasif mi?
     * Pasifse renderButton boş döner.
     */
    private static function isButtonDisabled( string $object_type, string $type ): bool {
        static $cache = null;
        if ( $cache === null ) {
            $cache = get_option( 'sh_reactions_buttons', [] );
        }
        foreach ( $cache as $btn ) {
            if (
                ( $btn['object_type'] ?? '' ) === $object_type &&
                ( $btn['type']        ?? '' ) === $type &&
                isset( $btn['active'] ) && ! $btn['active']
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generator'da kayitli button config'ini dondur.
     * object_type + subtype + type kombinasyonuyla eslesir.
     * Subtype eslesmezse sadece object_type + type ile fallback yapar.
     * Static cache — ayni request'te tek DB sorgusu.
     *
     * @return array|null  Kayitli button config veya null
     */
    private static function getSavedButtonConfig( string $object_type, string $subtype, string $type ): ?array {
        static $cache = null;
        if ( $cache === null ) {
            $cache = get_option( 'sh_reactions_buttons', [] );
        }

        $fallback = null;

        foreach ( $cache as $btn ) {
            if ( ( $btn['object_type'] ?? '' ) !== $object_type ) continue;
            if ( ( $btn['type']        ?? '' ) !== $type        ) continue;
            if ( isset( $btn['active'] ) && ! $btn['active']    ) continue;

            $btn_subtype = $btn['subtype'] ?? '';

            // Tam eslesme: object_type + subtype + type
            if ( $btn_subtype === $subtype ) return $btn;

            // Wildcard: subtype bos (tum subtypelara uygulanir)
            if ( $btn_subtype === '' ) $fallback = $btn;
        }

        return $fallback;
    }

    /**
     * Dogru login URL'ini dondur — WooCommerce my-account veya custom login page.
     * wp_login_url() admin login'e yonlendirebilir, bunu onlemek icin.
     */
    private static function getLoginUrl( string $redirect = '' ): string {
        // WooCommerce my-account login sayfasi
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $url = wc_get_page_permalink( 'myaccount' );
            if ( $url && $url !== false ) {
                return $redirect ? add_query_arg( 'redirect_to', urlencode( $redirect ), $url ) : $url;
            }
        }
        // Theme'in get_page_url fonksiyonu varsa kullan
        if ( function_exists( 'get_page_url' ) ) {
            $url = get_page_url( 'my-account' );
            if ( $url ) {
                return $redirect ? add_query_arg( 'redirect_to', urlencode( $redirect ), $url ) : $url;
            }
        }
        // Fallback: WP login (en azindan admin login'den kurtulalim)
        return wp_login_url( $redirect ?: home_url() );
    }

    /**
     * Icon HTML uret — FA class veya attachment ID.
     */
    private static function renderIconHtml( string $value, string $color = '#6b7280' ): string {
        if ( is_numeric( $value ) && (int) $value > 0 ) {
            $att_id  = (int) $value;
            $att_url = wp_get_attachment_url( $att_id );
            $mime    = get_post_mime_type( $att_id );
            if ( $att_url ) {
                if ( $mime === 'image/svg+xml' && function_exists('inline_svg') ) {
                    return '<span class="salt-reaction-icon" aria-hidden="true" style="display:inline-flex;width:1em;height:1em;">'
                         . inline_svg( $att_url, ['width' => 18, 'height' => 18] )
                         . '</span>';
                }
                return '<img src="' . esc_url( $att_url ) . '" class="salt-reaction-icon" style="width:1em;height:1em;object-fit:contain;" alt="" aria-hidden="true">';
            }
        }
        return '<i class="' . esc_attr( $value ) . '" aria-hidden="true"></i>';
    }

    /**
     * Button inner HTML — style'a göre.
     */
    private static function buildInner(
        string $style,
        string $icon_cls,
        string $label,
        int    $count,
        bool   $has,
        string $color
    ): string {
        $icon  = self::renderIconHtml( $icon_cls, $color );
        $cnt   = '<span class="salt-reaction-count">' . $count . '</span>';
        $lbl   = '<span class="salt-reaction-label">' . $label . '</span>';

        switch ( $style ) {
            case 'icon-only':  return $icon;
            case 'icon-count': return $icon . ' ' . $cnt;
            case 'icon-text':  return $icon . ' ' . $lbl;
            case 'text-only':  return $lbl;
            case 'pill':
                $style_attr = $has ? 'background-color:' . $color . ';color:#fff;' : '';
                return '<span class="salt-reaction-pill-inner" style="' . esc_attr( $style_attr ) . '">'
                    . $icon . ' '
                    . ( $count > 0 ? $cnt : $lbl )
                    . '</span>';
            default:           return $icon . ' ' . $cnt;
        }
    }
}
