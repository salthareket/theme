<?php
namespace SaltHareket\Notifications\Admin;

use SaltHareket\Notifications\NotifyRegistry;
use SaltHareket\Notifications\NotifyDispatcher;
use SaltHareket\Notifications\Carriers\WebPushCarrier;

/**
 * NotificationsAdmin
 * Pro-level notification yonetim sayfasi.
 * Rules, Events, Push, Log tablari.
 *
 * @version 2.5.0
 * @changelog
 *   2.5.0 - 2026-06-16
 *     - Add: Overview tab — default tab, Enable Notifications master toggle (sidebar'da)
 *     - Add: Overview sidebar — SMS/Email/Web Push durum gösterimi (switch yok, JS ile anlık güncelleme)
 *     - Add: EmailSettings email_enabled kontrolü düzeltildi (mode=wp → always active)
 *     - Fix: Parse error (satır 598) — renderOverviewTab kapanışından sonra kalan garbage HTML temizlendi
 *     - Fix: shNotifsMasterToggle — sidebar sh-ch-status-* spanları JS ile anlık güncellenir
 *   2.4.0 - 2026-06-16
 *     - Change: Settings tab → Overview tab (renderSettingsTab → renderOverviewTab)
 *     - Add: Overview — stat kartlar (Rules, Delivered, Failed, Today)
 *     - Add: Overview — son 8 log kaydı tablosu
 *     - Change: Default tab 'settings' → 'overview'
 *   2.3.0 - 2026-06-16
 *     - Add: Settings tab — master enable toggle + channel status cards (Alert/Email/SMS/Push)
 *     - Fix: Warning badge showing when notifications enabled (PHP conditional was correct but badge was static)
 *     - Add: shNotifsMasterToggle saving spinner animation
 *     - Move: Enable toggle moved from Rules tab to Settings tab
 *   2.3.0 - 2026-06-16
 *     - Add: Master "Enable Notifications" toggle — toolbar'da, kapalıysa tüm içerik overlay ile disabled
 *     - Add: ajaxSaveMasterToggle() — sh_notify_save_master_toggle AJAX handler
 *     - Add: Disabled banner — notifications kapalıyken uyarı mesajı
 *     - Add: shNotifsOverlayClick() — overlay tıklanınca "enable notifications first" uyarısı
 *     - Fix: renderPushTab() — $nonce ve $ajax_url parametre olarak alıyor, inline wp_create_nonce kaldırıldı (400 fix)
 *     - Fix: shPushToggle() — ajaxurl yerine SH_AJAX benzeri PHP'den gelen $ajax_url kullanıyor, hata durumunda rollback
 *   2.2.1 - 2026-06-16
 *     - Fix: renderSmsTab() — SMS toggle checkbox enabled state'i SmsSettings['enabled']'dan
 *            değil NotificationsSettings::getSetting('enable_sms_notifications')'dan okunuyor
 *     - Fix: renderSmsTab() — SMS toggle checkbox'ına onchange="shSmsToggle()" bağlandı (önceden yoktu)
 *     - Add: shSmsToggle() JS fonksiyonu — sh_notify_save_sms_toggle AJAX çağrısı
 *     - Fix: window.shShowToast — toast() IIFE'den global'e expose edildi (SMS tab erişimi)
 *   2.2.0 - 2026-05-08
 *     - Add: enqueueAssets() — sh-admin.css enqueue edildi, inline style kaldirildi
 *     - Change: renderStyles() — bos metod, CSS artik sh-admin.css'den geliyor
 *     - Add: renderLogTab() — stat kartlar, filtreler, recipient display name, retry, CSV export, retention ayari
 *     - Add: ajaxSaveRetention(), ajaxClearLog(), ajaxRetryLog() — log yonetim AJAX handler'lari
 *     - Change: NotifyWorker::cleanup() — configurable retention (sh_notify_log_retention option)
 *   2.1.0 - 2026-05-08
 *     - Change: getEvents() — DB + NotifyRegistry + sh_notify_events filter + getDefaultEvents() merge
 *     - Change: getDefaultEvents() — sadece core + WooCommerce eventleri, app eventleri filter'dan gelir
 *     - Remove: seedDefaultEvents() — artik gerek yok, eventler runtime'da filter'dan gelir
 *     - Remove: syncReactionEvents() — reactions bootstrap'i sh_notify_events filter'i kullanir
 *     - Add: sh_notify_events filter destegi — diger app'ler Notifications'a dokunmadan event ekler
 *   2.0.0 - 2026-05-06 — Initial release (ACF-free refactor)
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Baska bir app'ten event eklemek icin (Notifications'a dokunmadan):
 * add_filter('sh_notify_events', function(array $events): array {
 *     $events[] = ['slug' => 'new-message', 'title' => 'New Message', 'description' => '...'];
 *     return $events;
 * });
 *
 * // Notification tetiklemek icin:
 * Notifications::fire('new-like', ['user' => $actor, 'recipient' => $owner_id, 'post' => $post]);
 *
 * ──────────────────────────────────────────────────────────
 */
class NotificationsAdmin
{
    // --- REGISTER ---

    public static function register(): void
    {
        add_action( 'admin_menu', [ self::class, 'addMenuPage' ], 20 );
        add_action( 'admin_head', [ self::class, 'hideNotices' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
        add_action( 'wp_ajax_sh_notify_save_rule',    [ self::class, 'ajaxSaveRule' ] );
        add_action( 'wp_ajax_sh_notify_delete_rule',  [ self::class, 'ajaxDeleteRule' ] );
        add_action( 'wp_ajax_sh_notify_save_event',   [ self::class, 'ajaxSaveEvent' ] );
        add_action( 'wp_ajax_sh_notify_delete_event', [ self::class, 'ajaxDeleteEvent' ] );
        add_action( 'wp_ajax_sh_notify_test_rule',    [ self::class, 'ajaxTestRule' ] );
        add_action( 'wp_ajax_sh_notify_save_retention', [ self::class, 'ajaxSaveRetention' ] );
        add_action( 'wp_ajax_sh_notify_clear_log',    [ self::class, 'ajaxClearLog' ] );
        add_action( 'wp_ajax_sh_notify_retry_log',    [ self::class, 'ajaxRetryLog' ] );
        add_action( 'admin_post_sh_notify_generate_vapid', [ self::class, 'handleGenerateVapid' ] );
        // Web Push toggle
        add_action( 'wp_ajax_sh_notify_save_push_toggle', [ self::class, 'ajaxSavePushToggle' ] );
        // SMS toggle
        add_action( 'wp_ajax_sh_notify_save_sms_toggle', [ self::class, 'ajaxSaveSmsToggle' ] );
        // Master enable toggle
        add_action( 'wp_ajax_sh_notify_save_master_toggle', [ self::class, 'ajaxSaveMasterToggle' ] );
        // SMS
        add_action( 'wp_ajax_sh_sms_save_settings', [ self::class, 'ajaxSmsSaveSettings' ] );
        add_action( 'wp_ajax_sh_sms_send',          [ self::class, 'ajaxSmsSend' ] );
        add_action( 'wp_ajax_sh_sms_check_balance', [ self::class, 'ajaxSmsCheckBalance' ] );
        add_action( 'wp_ajax_sh_sms_search_users',  [ self::class, 'ajaxSmsSearchUsers' ] );
        // Email
        add_action( 'wp_ajax_sh_email_save_settings', [ self::class, 'ajaxEmailSaveSettings' ] );
        add_action( 'wp_ajax_sh_email_send',          [ self::class, 'ajaxEmailSend' ] );
        add_action( 'wp_ajax_sh_email_test',          [ self::class, 'ajaxEmailTest' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '🔔 Notifications',
            '🔔 Notifications',
            'manage_options',
            'sh-notifications',
            [ self::class, 'renderPage' ]
        );
    }

    public static function enqueueAssets( string $hook ): void {
        if ( strpos( $hook, 'sh-notifications' ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-notifications' ) {
            return;
        }
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    // --- DATA HELPERS ---

    private static function getRules( array $filters = [] ): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'notify_rules';
        $where = '1=1';
        $vals  = [];
        if ( ! empty( $filters['role'] ) ) {
            $where .= ' AND role = %s';
            $vals[] = $filters['role'];
        }
        if ( ! empty( $filters['event'] ) ) {
            $where .= ' AND event = %s';
            $vals[] = $filters['event'];
        }
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC";
        if ( $vals ) {
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ) );
        } else {
            $rows = $wpdb->get_results( $sql );
        }
        if ( ! is_array( $rows ) ) {
            return [];
        }
        foreach ( $rows as &$row ) {
            $row->carriers = json_decode( $row->carriers ?? '{}', true ) ?: [];
        }
        unset( $row );
        return $rows;
    }

    private static function getEvents(): array
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'notify_events';
        $db_events = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY title ASC" ) ?: [];
        $merged    = [];

        // Onceden runtime'dan gelmesi gereken slug'lari topla
        $runtime_slugs = [];
        foreach ( apply_filters( 'sh_notify_events', [] ) as $ev ) {
            if ( ! empty( $ev['slug'] ) ) $runtime_slugs[] = sanitize_key( $ev['slug'] );
        }
        foreach ( self::getDefaultEvents() as $ev ) {
            if ( ! empty( $ev['slug'] ) ) $runtime_slugs[] = $ev['slug'];
        }

        // 1. DB — sadece admin'in manuel ekledigi custom eventler
        // Runtime'dan gelmesi gereken slug'lar DB'de olsa bile ignore et
        foreach ( $db_events as $e ) {
            if ( in_array( $e->slug, $runtime_slugs, true ) ) continue; // runtime'dan gelecek
            $merged[ $e->slug ] = [
                'slug'        => $e->slug,
                'title'       => $e->title,
                'description' => $e->description,
                'group'       => 'Custom',
                'source'      => 'db',
                'id'          => $e->id,
            ];
        }

        // 2. NotifyRegistry — PHP'de register edilmis eventler
        foreach ( NotifyRegistry::all() as $key => $ev ) {
            if ( ! isset( $merged[ $key ] ) ) {
                $merged[ $key ] = [
                    'slug'        => $key,
                    'title'       => $ev->label,
                    'description' => '',
                    'group'       => property_exists( $ev, 'group' ) ? $ev->group : 'Other',
                    'source'      => 'php',
                    'id'          => null,
                ];
            }
        }

        // 3. sh_notify_events filter — app'lerin ekledigi eventler (reactions, reviews, messages vs.)
        // Her app kendi bootstrap'inda add_filter('sh_notify_events', ...) ile ekler
        foreach ( apply_filters( 'sh_notify_events', [] ) as $ev ) {
            $slug = sanitize_key( $ev['slug'] ?? '' );
            if ( $slug && ! isset( $merged[ $slug ] ) ) {
                $merged[ $slug ] = [
                    'slug'        => $slug,
                    'title'       => $ev['title']       ?? $slug,
                    'description' => $ev['description'] ?? '',
                    'group'       => $ev['group']        ?? 'Other',
                    'source'      => 'filter',
                    'id'          => null,
                ];
            }
        }

        // 4. getDefaultEvents — core + woocommerce eventleri (filter'dan once calisir ama burada merge edilir)
        foreach ( self::getDefaultEvents() as $ev ) {
            $slug = $ev['slug'] ?? '';
            if ( $slug && ! isset( $merged[ $slug ] ) ) {
                $merged[ $slug ] = [
                    'slug'        => $slug,
                    'title'       => $ev['title']       ?? $slug,
                    'description' => $ev['description'] ?? '',
                    'group'       => $ev['group']        ?? 'Default',
                    'source'      => 'default',
                    'id'          => null,
                ];
            }
        }

        // Alfabetik sirala
        uasort( $merged, fn( $a, $b ) => strcmp( $a['title'], $b['title'] ) );

        return array_values( $merged );
    }

    private static function getRoles(): array
    {
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new \WP_Roles();
        }
        $roles = [];
        foreach ( $wp_roles->roles as $slug => $role ) {
            $roles[ $slug ] = $role['name'];
        }
        return $roles;
    }

    private static function getPlaceholders(): array
    {
        return [
            'sender' => [
                '{{admin}}'  => 'Admin',
                '{{me}}'     => 'Current User',
                '{{user}}'   => 'Event User',
                '{{author}}' => 'Post Author',
            ],
            'recipient' => [
                '{{admin}}'  => 'Admin',
                '{{me}}'     => 'Current User',
                '{{user}}'   => 'Event User',
                '{{author}}' => 'Post Author',
                '{{users}}'  => 'Multiple Users',
            ],
        ];
    }

    private static function getTypes(): array
    {
        return [
            'info'    => 'Info',
            'success' => 'Success',
            'warning' => 'Warning',
            'danger'  => 'Danger',
        ];
    }

    private static function getDefaultEvents(): array
    {
        $events = [
            // ── Core ──────────────────────────────────────────────────────────
            [ 'slug' => 'new-account',           'title' => 'New Account',           'description' => 'Yeni bir kullanici hesabi olusturuldugunda.',  'group' => 'Account' ],
            [ 'slug' => 'account-activated',     'title' => 'Account Activated',     'description' => 'Kullanici hesabi aktive edildiginde.',          'group' => 'Account' ],
            [ 'slug' => 'account-approved',      'title' => 'Account Approved',      'description' => 'Admin kullanici hesabini onayladiginda.',       'group' => 'Account' ],
            [ 'slug' => 'account-rejected',      'title' => 'Account Rejected',      'description' => 'Admin kullanici hesabini reddedtiginde.',       'group' => 'Account' ],
            [ 'slug' => 'password-reset',        'title' => 'Password Reset',        'description' => 'Kullanici sifre sifirlama istegi yaptiginda.',  'group' => 'Account' ],
        ];

        // ── WooCommerce ───────────────────────────────────────────────────────
        if ( defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ) {
            $events = array_merge( $events, [
                [ 'slug' => 'order-placed',          'title' => 'Order Placed',          'description' => 'Yeni siparis olusturuldugunda.',                                    'group' => 'WooCommerce' ],
                [ 'slug' => 'order-completed',       'title' => 'Order Completed',       'description' => 'Siparis tamamlandiginda.',                                          'group' => 'WooCommerce' ],
                [ 'slug' => 'order-cancelled',       'title' => 'Order Cancelled',       'description' => 'Siparis iptal edildiginde.',                                        'group' => 'WooCommerce' ],
                [ 'slug' => 'order-refunded',        'title' => 'Order Refunded',        'description' => 'Siparis iade edildiginde.',                                         'group' => 'WooCommerce' ],
                [ 'slug' => 'order-status-changed',  'title' => 'Order Status Changed',  'description' => 'Siparis durumu degistiginde.',                                      'group' => 'WooCommerce' ],
                [ 'slug' => 'payment-completed',     'title' => 'Payment Completed',     'description' => 'Odeme tamamlandiginda.',                                            'group' => 'WooCommerce' ],
                [ 'slug' => 'back-in-stock',         'title' => 'Back in Stock',         'description' => 'Urun tekrar stokta oldugunda.',                                     'group' => 'WooCommerce' ],
                [ 'slug' => 'price-drop',            'title' => 'Price Drop',            'description' => 'Urun fiyati dustugunde.',                                           'group' => 'WooCommerce' ],
                [ 'slug' => 'new-product',           'title' => 'New Product',           'description' => 'Takip edilen kullanici/kategori yeni urun eklediginde.',            'group' => 'WooCommerce' ],
            ] );
        }

        /**
         * Diger app'ler kendi eventlerini sh_notify_events filter'i ile ekler.
         * getEvents() bu filter'i ayrica ceker — buraya eklemeye gerek yok.
         *
         * @example
         * add_filter('sh_notify_events', function($events) {
         *     $events[] = ['slug'=>'new-like', 'title'=>'New Like', 'description'=>'...'];
         *     return $events;
         * });
         */
        return $events;
    }

    // --- MAIN PAGE ---

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $tab              = sanitize_key( $_GET['tab'] ?? 'overview' );
        $rules            = self::getRules();
        $events           = self::getEvents();
        $roles            = self::getRoles();
        $placeholders     = self::getPlaceholders();
        $sender_ph        = $placeholders['sender'];
        $recipient_ph     = $placeholders['recipient'];
        $types            = self::getTypes();
        $vapid_key        = WebPushCarrier::getPublicKey();
        $nonce            = wp_create_nonce( 'sh_notify_nonce' );
        $ajax_url         = esc_url( admin_url( 'admin-ajax.php' ) );
        $rule_count       = count( $rules );
        $event_count      = count( $events );
        $notifs_enabled   = \SaltHareket\Notifications\NotificationsSettings::getSetting( 'enable_notifications' );
        ?>
        <div class="sh-wrap" id="sh-notify-page">
        <?php self::renderStyles(); ?>

        <div class="sh-toolbar">
            <h1>🔔 Notifications</h1>
            <span class="sh-badge sh-badge-blue"><?php echo $rule_count; ?> rule<?php echo $rule_count !== 1 ? 's' : ''; ?></span>
            <span class="sh-badge sh-badge-gray"><?php echo $event_count; ?> event<?php echo $event_count !== 1 ? 's' : ''; ?></span>
            <div class="sh-toolbar-right">
                <a href="?page=sh-notifications&tab=overview" class="sh-tab-btn <?php echo $tab === 'overview' ? 'active' : ''; ?>">Overview</a>
                <a href="?page=sh-notifications&tab=rules"    class="sh-tab-btn <?php echo $tab === 'rules'    ? 'active' : ''; ?>">Rules</a>
                <a href="?page=sh-notifications&tab=events" class="sh-tab-btn <?php echo $tab === 'events' ? 'active' : ''; ?>">Events</a>
                <a href="?page=sh-notifications&tab=sms"    class="sh-tab-btn <?php echo $tab === 'sms'    ? 'active' : ''; ?>">📱 SMS</a>
                <a href="?page=sh-notifications&tab=email"  class="sh-tab-btn <?php echo $tab === 'email'  ? 'active' : ''; ?>">📧 Email</a>
                <a href="?page=sh-notifications&tab=push"   class="sh-tab-btn <?php echo $tab === 'push'   ? 'active' : ''; ?>">Web Push</a>
                <a href="?page=sh-notifications&tab=log"    class="sh-tab-btn <?php echo $tab === 'log'    ? 'active' : ''; ?>">Log</a>
            </div>
        </div>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="sh-notice sh-notice-success sh-inline">&#10003; Saved successfully.</div>
        <?php endif; ?>

        <?php
        if ( $tab === 'events' ) {
            self::renderEventsTab( $events, $nonce, $ajax_url );
        } elseif ( $tab === 'sms' ) {
            self::renderSmsTab( $nonce, $ajax_url );
        } elseif ( $tab === 'email' ) {
            self::renderEmailTab( $nonce, $ajax_url );
        } elseif ( $tab === 'push' ) {
            self::renderPushTab( $vapid_key, $nonce, $ajax_url );
        } elseif ( $tab === 'log' ) {
            self::renderLogTab();
        } elseif ( $tab === 'rules' ) {
            self::renderRulesTab( $rules, $events, $roles, $sender_ph, $recipient_ph, $types, $nonce, $ajax_url );
        } else {
            self::renderOverviewTab( $nonce, $ajax_url );
        }
        ?>

        <div id="sh-toast"></div>
        </div><!-- /sh-wrap -->
        <?php
        self::renderScripts( $nonce, $ajax_url, $events, $roles, $sender_ph, $recipient_ph, $types );
    }

    // --- RULES TAB ---

    private static function renderRulesTab( array $rules, array $events, array $roles, array $sender_ph, array $recipient_ph, array $types, string $nonce, string $ajax_url ): void
    {
        $events_json    = wp_json_encode( array_values( $events ) );
        $roles_json     = wp_json_encode( $roles );
        $sender_json    = wp_json_encode( $sender_ph );
        $recipient_json = wp_json_encode( $recipient_ph );
        $types_json     = wp_json_encode( $types );
        // Mevcut rule kombinasyonları — duplicate kontrolü için
        $existing = [];
        foreach ( $rules as $r ) {
            $existing[] = $r->role . '|' . $r->event;
        }
        $existing_json  = wp_json_encode( $existing );
        ?>

        <div class="sh-layout">
        <div class="sh-main">

            <div class="sh-filter-bar">
                <label>Role</label>
                <select id="sh-filter-role" onchange="shFilterRules()">
                    <option value="">All Roles</option>
                    <?php foreach ( $roles as $slug => $name ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Event</label>
                <select id="sh-filter-event" onchange="shFilterRules()">
                    <option value="">All Events</option>
                    <?php
                    $ev_by_group = [];
                    foreach ( $events as $ev ) {
                        $g = $ev['group'] ?? 'Other';
                        $ev_by_group[$g][] = $ev;
                    }
                    ksort($ev_by_group);
                    foreach ( $ev_by_group as $group_name => $group_events ) :
                    ?>
                        <optgroup label="<?php echo esc_attr( $group_name ); ?>">
                        <?php foreach ( $group_events as $ev ) : ?>
                            <option value="<?php echo esc_attr( $ev['slug'] ); ?>"><?php echo esc_html( $ev['title'] ); ?></option>
                        <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span id="sh-rule-count" class="sh-count-label"><?php echo count( $rules ); ?> rules</span>
                <button type="button" class="sh-btn sh-btn-primary" onclick="shAddRule()" style="margin-left:auto">+ Add Rule</button>
            </div>

            <div id="sh-rules-list">
            <?php foreach ( $rules as $rule ) : ?>
                <?php self::renderRuleCard( $rule, $events, $roles, $sender_ph, $recipient_ph, $types ); ?>
            <?php endforeach; ?>
            <?php if ( empty( $rules ) ) : ?>
                <div class="sh-empty-box" id="sh-empty-state">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#c3c4c7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                    <p style="margin:0 0 4px;font-weight:500;color:#50575e">No notification rules yet</p>
                    <p style="margin:0 0 16px;font-size:12px;color:#9ca3af">Click "Add Rule" to create your first notification</p>
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shAddRule()">+ Add Rule</button>
                </div>
            <?php endif; ?>
            </div>

        </div>

        <div class="sh-sidebar">
            <?php self::renderVariableReference(); ?>
        </div>
        </div><!-- /sh-layout -->
        <?php
    }

    // --- SETTINGS TAB ---

    // --- OVERVIEW TAB ---

    private static function renderOverviewTab( string $nonce, string $ajax_url ): void
    {
        global $wpdb;

        $s              = \SaltHareket\Notifications\NotificationsSettings::get();
        $notifs_enabled = (bool) ( $s['enable_notifications'] ?? false );
        $sms_enabled    = (bool) ( $s['enable_sms_notifications'] ?? false );
        $push_enabled   = (bool) ( $s['enable_web_push'] ?? false );
        $email_settings = class_exists( '\SaltHareket\Notifications\Carriers\Email\EmailSettings' )
            ? \SaltHareket\Notifications\Carriers\Email\EmailSettings::get() : [];
        // Email: mode='wp' ise her zaman aktif (wp_mail kullanır), mode='custom' ise host/api_key kontrolü
        $email_mode    = $email_settings['mode'] ?? 'wp';
        $email_enabled = true; // wp mode → always configured
        if ( $email_mode === 'custom' ) {
            $email_type = $email_settings['custom_type'] ?? 'smtp';
            if ( $email_type === 'smtp' ) {
                $email_enabled = ! empty( $email_settings['smtp']['host'] ?? '' );
            } else {
                $email_enabled = ! empty( $email_settings['api']['api_key'] ?? '' );
            }
        }

        // Log istatistikleri
        $log_table = $wpdb->prefix . 'notify_log';
        $log_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" ) === $log_table;
        $total      = $log_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" ) : 0;
        $sent       = $log_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table} WHERE status='sent'" ) : 0;
        $failed     = $log_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table} WHERE status='failed'" ) : 0;
        $today      = $log_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table} WHERE DATE(sent_at)=CURDATE()" ) : 0;
        $logs       = $log_exists ? ( $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY id DESC LIMIT 10" ) ?: [] ) : [];
        $rule_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}notify_rules" );
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- ── ENABLE SWITCH ─────────────────────────────────────── -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px;border-bottom:none">
                <h3 style="margin:0">Notifications</h3>
                <label class="sh-toggle">
                    <input type="checkbox" id="sh-notifs-master-toggle"
                           <?php checked( $notifs_enabled ); ?>
                           onchange="shNotifsMasterToggle(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span id="sh-notifs-master-label" style="font-size:12px;color:<?php echo $notifs_enabled ? '#00a32a' : '#9ca3af'; ?>">
                    <?php echo $notifs_enabled ? 'Enabled' : 'Disabled'; ?>
                </span>
                <span id="sh-notifs-master-saving" style="display:none;font-size:12px;color:#9ca3af">
                    <span style="display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#2271b1;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:4px"></span>Saving...
                </span>
            </div>
        </div>

        <!-- ── RECENT DELIVERIES ──────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between">
                <strong>Recent Deliveries</strong>
                <a href="?page=sh-notifications&tab=log" class="sh-btn sh-btn-sm" style="font-size:12px">View All →</a>
            </div>
            <?php if ( empty( $logs ) ) : ?>
            <div style="padding:32px;text-align:center;color:#9ca3af;font-size:13px">No deliveries yet.</div>
            <?php else : ?>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <?php foreach ( [ 'Event', 'Channel', 'Recipient', 'Status', 'Time' ] as $h ) : ?>
                    <th style="padding:10px 18px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #f3f4f6"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ( $logs as $log ) :
                    $sc = $log->status === 'sent' ? '#00a32a' : '#d63638';
                    $recipient = $log->recipient ?? '—';
                    if ( is_numeric( $recipient ) && (int)$recipient > 0 ) {
                        $u = get_user_by( 'id', (int) $recipient );
                        if ( $u ) $recipient = $u->display_name;
                    }
                ?>
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:10px 18px"><code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:11px"><?php echo esc_html( $log->event ?? '—' ); ?></code></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html( strtoupper( $log->channel ?? '—' ) ); ?></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html( $recipient ); ?></td>
                    <td style="padding:10px 18px"><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $sc; ?>22;color:<?php echo $sc; ?>"><?php echo esc_html( ucfirst( $log->status ?? '' ) ); ?></span></td>
                    <td style="padding:10px 18px;color:#6b7280;font-size:13px"><?php echo esc_html( $log->sent_at ?? '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        </div><!-- .sh-main -->

        <!-- ── SIDEBAR ────────────────────────────────────────────── -->
        <div class="sh-sidebar">

            <!-- Channel status — no switches, just status display -->
            <div class="sh-card" style="margin-bottom:16px">
                <div class="sh-card-header"><h3 style="margin:0;font-size:13px">Channels</h3></div>
                <div id="sh-notifs-sidebar-content" class="sh-card-body" style="padding:14px">
                    <?php
                    // Notifications false ise hepsi false görünür, true ise kendi değerleri
                    $show_sms   = $notifs_enabled && $sms_enabled;
                    $show_email = $notifs_enabled && $email_enabled;
                    $show_push  = $notifs_enabled && $push_enabled;

                    $items = [
                        [ 'icon' => '📱', 'label' => 'SMS',      'active' => $show_sms,   'url' => '?page=sh-notifications&tab=sms',   'id' => 'sms',   'own' => $sms_enabled   ],
                        [ 'icon' => '📧', 'label' => 'Email',    'active' => $show_email, 'url' => '?page=sh-notifications&tab=email', 'id' => 'email', 'own' => $email_enabled ],
                        [ 'icon' => '🌐', 'label' => 'Web Push', 'active' => $show_push,  'url' => '?page=sh-notifications&tab=push',  'id' => 'push',  'own' => $push_enabled  ],
                    ];
                    foreach ( $items as $i => $item ) :
                        $is_last = $i === count($items) - 1;
                    ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 0;<?php echo ! $is_last ? 'border-bottom:1px solid #f3f4f6' : ''; ?>">
                        <span><?php echo $item['icon']; ?></span>
                        <span style="font-size:13px;flex:1"><?php echo $item['label']; ?></span>
                        <a href="<?php echo esc_attr( $item['url'] ); ?>"
                           id="sh-ch-status-<?php echo $item['id']; ?>"
                           data-own="<?php echo $item['own'] ? '1' : '0'; ?>"
                           style="font-size:11px;font-weight:600;color:<?php echo $item['active'] ? '#00a32a' : '#9ca3af'; ?>;text-decoration:none">
                            <?php echo $item['active'] ? '● Active' : '○ Inactive'; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick links -->
            <div class="sh-card">
                <div class="sh-card-header"><h3 style="margin:0;font-size:13px">Quick Links</h3></div>
                <div class="sh-card-body" style="padding:14px">
                    <?php foreach ( [
                        [ '?page=sh-notifications&tab=rules',  '📋 Rules'  ],
                        [ '?page=sh-notifications&tab=events', '⚡ Events' ],
                        [ '?page=sh-notifications&tab=log',    '📜 Full Log' ],
                    ] as [ $url, $lbl ] ) : ?>
                    <a href="<?php echo esc_attr( $url ); ?>" style="display:block;padding:7px 0;font-size:13px;color:#2271b1;text-decoration:none;border-bottom:1px solid #f3f4f6"><?php echo $lbl; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- .sh-sidebar -->
        </div><!-- .sh-layout -->

        <style>@keyframes sh-spin{to{transform:rotate(360deg)}}</style>
        <?php
    }

    // --- RULE CARD ---

    private static function renderRuleCard( object $rule, array $events, array $roles, array $sender_ph, array $recipient_ph, array $types ): void
    {
        $carriers    = $rule->carriers;
        $event_label = '';
        foreach ( $events as $ev ) {
            if ( $ev['slug'] === $rule->event ) {
                $event_label = $ev['title'];
                break;
            }
        }
        $role_label = $roles[ $rule->role ] ?? $rule->role;
        ?>
        <div class="sh-rule-card <?php echo $rule->active ? '' : 'sh-rule-inactive'; ?>" data-id="<?php echo esc_attr( $rule->id ); ?>" data-role="<?php echo esc_attr( $rule->role ); ?>" data-event="<?php echo esc_attr( $rule->event ); ?>">
            <div class="sh-rule-header">

                <!-- Switch — en başta -->
                <label class="sh-rule-switch" title="<?php echo $rule->active ? 'Active — click to deactivate' : 'Inactive — click to activate'; ?>">
                    <input type="checkbox" class="sh-rule-switch-input" <?php checked( $rule->active ); ?> onchange="shToggleRule(this)">
                    <span class="sh-rule-switch-slider"></span>
                </label>

                <div class="sh-rule-meta">
                    <span class="sh-rule-role"><?php echo esc_html( $role_label ); ?></span>
                    <span class="sh-rule-arrow">→</span>
                    <span class="sh-rule-event"><?php echo esc_html( $event_label ?: $rule->event ); ?></span>
                    <span class="sh-rule-type sh-type-<?php echo esc_attr( $rule->type ); ?>"><?php echo esc_html( $rule->type ); ?></span>
                </div>

                <div class="sh-rule-actions">
                    <?php
                    $web_push_enabled = defined( 'ENABLE_WEB_PUSH' ) && ENABLE_WEB_PUSH;
                    foreach ( [ 'email', 'alert', 'sms', 'push' ] as $ch ) :
                        if ( $ch === 'push' && ! $web_push_enabled ) continue;
                        $ch_active = ! empty( $carriers[ $ch ]['active'] );
                    ?>
                        <span class="sh-ch-badge sh-ch-<?php echo $ch; ?> <?php echo $ch_active ? 'active' : 'inactive'; ?>"><?php echo strtoupper( $ch ); ?></span>
                    <?php endforeach; ?>

                    <div class="sh-rule-btns">
                        <button type="button" class="sh-rule-btn sh-rule-btn-edit" onclick="shEditRule(this)" title="Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="sh-rule-btn sh-rule-btn-test" onclick="shTestRule(this)" title="Test Send">
                            <span class="dashicons dashicons-controls-play"></span>
                        </button>
                        <button type="button" class="sh-rule-btn" onclick="shGetCode(this)" title="Get Code" style="font-family:Consolas,monospace;font-size:13px;font-weight:700;">&lt;/&gt;</button>
                        <button type="button" class="sh-rule-btn sh-rule-btn-delete" onclick="shDeleteRule(this)" title="Delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sh-rule-form" style="display:none">
                <div class="sh-form-row">
                    <div class="sh-form-col">
                        <label>Role *</label>
                        <select name="role" class="sh-select">
                            <?php foreach ( $roles as $slug => $name ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $rule->role, $slug ); ?>><?php echo esc_html( $name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col">
                        <label>Event *</label>
                        <select name="event" class="sh-select">
                            <?php
                            $ev_by_group2 = [];
                            foreach ( $events as $ev ) {
                                $g = $ev['group'] ?? 'Other';
                                $ev_by_group2[$g][] = $ev;
                            }
                            ksort($ev_by_group2);
                            foreach ( $ev_by_group2 as $group_name => $group_events ) :
                            ?>
                                <optgroup label="<?php echo esc_attr( $group_name ); ?>">
                                <?php foreach ( $group_events as $ev ) : ?>
                                    <option value="<?php echo esc_attr( $ev['slug'] ); ?>" <?php selected( $rule->event, $ev['slug'] ); ?>><?php echo esc_html( $ev['title'] ); ?></option>
                                <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Type</label>
                        <select name="type" class="sh-select">
                            <?php foreach ( $types as $slug => $name ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $rule->type, $slug ); ?>><?php echo esc_html( $name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Sender</label>
                        <select name="sender" class="sh-select">
                            <?php foreach ( $sender_ph as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $rule->sender, $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sh-form-col sh-form-col-sm">
                        <label>Recipient</label>
                        <select name="recipient" class="sh-select">
                            <?php foreach ( $recipient_ph as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $rule->recipient, $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="sh-carriers">
                    <?php
                    $web_push_enabled  = defined( 'ENABLE_WEB_PUSH' ) && ENABLE_WEB_PUSH;
                    $carrier_icons = [ 'email' => '&#9993;', 'alert' => '&#128276;', 'sms' => '&#128172;', 'push' => '&#128242;' ];
                    foreach ( [ 'email', 'alert', 'sms', 'push' ] as $ch ) :
                        if ( $ch === 'push' && ! $web_push_enabled ) continue;
                        $cfg    = $carriers[ $ch ] ?? [];
                        $active = ! empty( $cfg['active'] );
                    ?>
                    <div class="sh-carrier <?php echo $active ? 'sh-carrier-active sh-carrier-open' : ''; ?>" data-channel="<?php echo $ch; ?>">
                        <div class="sh-carrier-header" onclick="shToggleCarrier(this)">
                            <span class="sh-carrier-icon"><?php echo $carrier_icons[ $ch ]; ?></span>
                            <span class="sh-carrier-name"><?php echo strtoupper( $ch ); ?></span>
                            <label class="sh-toggle" onclick="event.stopPropagation()">
                                <input type="checkbox" name="carriers[<?php echo $ch; ?>][active]" value="1" <?php checked( $active ); ?> onchange="shCarrierToggle(this)">
                                <span class="sh-toggle-slider"></span>
                            </label>
                            <span class="sh-carrier-arrow">&#8250;</span>
                        </div>
                        <div class="sh-carrier-body">
                            <?php if ( $ch === 'email' ) : ?>
                                <div class="sh-field">
                                    <label>Subject</label>
                                    <input type="text" name="carriers[email][subject]" value="<?php echo esc_attr( $cfg['subject'] ?? '' ); ?>" placeholder="Order confirmed" class="sh-input">
                                </div>
                                <div class="sh-field sh-field-row">
                                    <label>Use HTML Template</label>
                                    <label class="sh-toggle">
                                        <input type="checkbox" name="carriers[email][template]" value="1" <?php checked( ! empty( $cfg['template'] ) ); ?> onchange="shTemplateToggle(this)">
                                        <span class="sh-toggle-slider"></span>
                                    </label>
                                    <span class="sh-template-hint" style="font-size:11px;color:#9ca3af;margin-left:8px">
                                        <?php echo esc_html( 'theme/templates/notifications/events/' . str_replace( '/', '-', $rule->event ) . '.html' ); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ( $ch === 'push' ) : ?>
                                <div class="sh-field">
                                    <label>Title</label>
                                    <input type="text" name="carriers[push][title]" value="<?php echo esc_attr( $cfg['title'] ?? '' ); ?>" placeholder="New notification" class="sh-input">
                                </div>
                                <div class="sh-field">
                                    <label>Icon URL</label>
                                    <input type="text" name="carriers[push][icon]" value="<?php echo esc_attr( $cfg['icon'] ?? '' ); ?>" placeholder="/static/img/icon-192.png" class="sh-input">
                                </div>
                                <div class="sh-field">
                                    <label>Click URL</label>
                                    <input type="text" name="carriers[push][url]" value="<?php echo esc_attr( $cfg['url'] ?? '' ); ?>" placeholder="{{ data.post.link }}" class="sh-input">
                                </div>
                            <?php endif; ?>
                            <div class="sh-field sh-body-field <?php echo ( $ch === 'email' && ! empty( $cfg['template'] ) ) ? 'sh-hidden' : ''; ?>">
                                <label>Body <?php echo $ch === 'email' ? '<span style="color:#9ca3af;font-weight:400">(Twig)</span>' : ''; ?></label>
                                <textarea name="carriers[<?php echo $ch; ?>][body]" rows="4" class="sh-textarea" placeholder="<?php echo $ch === 'email' ? 'Hello {{ data.user.name }}...' : 'Your message here...'; ?>"><?php echo esc_textarea( $cfg['body'] ?? '' ); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="sh-form-footer">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shSaveRule(this)">Save Rule</button>
                    <button type="button" class="sh-btn sh-btn-ghost" onclick="shCancelEdit(this)">Cancel</button>
                    <button type="button" class="sh-btn sh-btn-secondary" onclick="shGetCode(this)" style="margin-left:auto;font-family:Consolas,monospace;font-size:12px;">&lt;/&gt; Get Code</button>
                </div>
            </div>
        </div>
        <?php
    }

    // --- EVENTS TAB ---

    private static function renderEventsTab( array $events, string $nonce, string $ajax_url ): void
    {
        ?>
        <div class="sh-layout">
        <div class="sh-main">

            <div style="margin-bottom:14px">
                <button type="button" class="sh-btn sh-btn-primary" id="sh-add-event-btn" onclick="shShowAddEvent()">+ Add New Event</button>
            </div>

            <div class="sh-add-event-form" id="sh-add-event-form" style="display:none;margin-bottom:14px">
                <div class="sh-form-row">
                    <div class="sh-form-col">
                        <label>Slug *</label>
                        <input type="text" id="sh-new-slug" placeholder="new-account" class="sh-input">
                    </div>
                    <div class="sh-form-col">
                        <label>Title *</label>
                        <input type="text" id="sh-new-title" placeholder="New Account" class="sh-input">
                    </div>
                    <div class="sh-form-col">
                        <label>Description</label>
                        <input type="text" id="sh-new-desc" placeholder="Triggered when..." class="sh-input">
                    </div>
                </div>
                <div class="sh-form-footer">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shSaveNewEvent()">Add Event</button>
                    <button type="button" class="sh-btn sh-btn-ghost" onclick="shHideAddEvent()">Cancel</button>
                </div>
            </div>

            <?php
            // Sadece custom (DB'den) eventler — edit/delete butonlu
            $custom_events = array_filter( $events, fn($ev) => ( $ev['source'] ?? '' ) === 'db' );
            ?>

            <div id="sh-events-list" style="margin-bottom:16px">
            <?php foreach ( $custom_events as $ev ) : ?>
                <div class="sh-event-row" data-id="<?php echo esc_attr( $ev['id'] ?? '' ); ?>" data-source="db">
                    <div class="sh-event-info">
                        <div class="sh-event-left">
                            <span class="sh-event-title"><?php echo esc_html( $ev['title'] ); ?></span>
                            <?php if ( $ev['description'] ) : ?>
                                <span class="sh-event-desc"><?php echo esc_html( $ev['description'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sh-event-right">
                            <code class="sh-event-slug"><?php echo esc_html( $ev['slug'] ); ?></code>
                            <button type="button" class="sh-icon-btn sh-icon-edit" onclick="shEditEvent(this)" title="Edit">&#9998;</button>
                            <button type="button" class="sh-icon-btn sh-icon-delete" onclick="shDeleteEvent(this)" title="Delete">&#10005;</button>
                        </div>
                    </div>
                    <div class="sh-event-form" style="display:none">
                        <div class="sh-form-row">
                            <div class="sh-form-col">
                                <label>Slug *</label>
                                <input type="text" name="slug" value="<?php echo esc_attr( $ev['slug'] ); ?>" class="sh-input">
                            </div>
                            <div class="sh-form-col">
                                <label>Title *</label>
                                <input type="text" name="title" value="<?php echo esc_attr( $ev['title'] ); ?>" class="sh-input">
                            </div>
                            <div class="sh-form-col">
                                <label>Description</label>
                                <input type="text" name="description" value="<?php echo esc_attr( $ev['description'] ?? '' ); ?>" class="sh-input">
                            </div>
                        </div>
                        <div class="sh-form-footer">
                            <button type="button" class="sh-btn sh-btn-primary" onclick="shSaveEvent(this)">Save</button>
                            <button type="button" class="sh-btn sh-btn-ghost" onclick="shCancelEventEdit(this)">Cancel</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <div class="sh-empty-box" id="sh-events-empty" <?php echo ! empty( $custom_events ) ? 'style="display:none"' : ''; ?>>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#c3c4c7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
                <p style="margin:0 0 4px;font-weight:500;color:#50575e">No custom events yet</p>
                <p style="margin:0;font-size:12px;color:#9ca3af">Add custom events with the button above.</p>
            </div>

            <!-- Tum eventlerin info listesi — gruplar halinde, sadece okuma -->
            <?php
            $all_by_group = [];
            foreach ( $events as $ev ) {
                if ( ( $ev['source'] ?? '' ) === 'db' ) continue; // custom'lar ustte zaten var
                $g = $ev['group'] ?? 'Other';
                $all_by_group[$g][] = $ev;
            }
            ksort( $all_by_group );
            ?>
            <div style="margin-top:32px;">
                <h3 style="font-size:14px;font-weight:600;color:var(--ts-gray-700);margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid var(--ts-gray-200);">
                    &#128196; All Available Events
                    <span style="font-size:11px;font-weight:400;color:var(--ts-gray-400);margin-left:6px;">Use these slugs in your rules</span>
                </h3>
                <?php foreach ( $all_by_group as $group_name => $group_events ) : ?>
                <div style="margin-bottom:20px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ts-gray-500);margin-bottom:8px;"><?php echo esc_html( $group_name ); ?></div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:8px;">
                    <?php foreach ( $group_events as $ev ) : ?>
                        <div style="background:#fff;border:1px solid var(--ts-gray-200);border-radius:6px;padding:10px 12px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:3px;">
                                <span style="font-size:13px;font-weight:600;color:var(--ts-gray-900);"><?php echo esc_html( $ev['title'] ); ?></span>
                                <code style="font-size:10px;background:var(--ts-white);border:1px solid var(--ts-gray-200);padding:2px 6px;border-radius:3px;color:var(--ts-gray-600);white-space:nowrap;flex-shrink:0;"><?php echo esc_html( $ev['slug'] ); ?></code>
                            </div>
                            <?php if ( ! empty( $ev['description'] ) ) : ?>
                                <div style="font-size:11px;color:var(--ts-gray-500);"><?php echo esc_html( $ev['description'] ); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <div class="sh-sidebar">
            <?php self::renderVariableReference(); ?>
        </div>
        </div>
        <?php
    }

    // --- PUSH TAB ---

    private static function renderPushTab( string $vapid_key, string $nonce, string $ajax_url ): void
    {
        $push_enabled = \SaltHareket\Notifications\NotificationsSettings::getSetting( 'enable_web_push' );
        $composer_ok  = class_exists( '\\Minishlink\\WebPush\\WebPush' );
        ?>
        <div class="sh-layout">
        <div class="sh-main">
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px">
                <h3 style="margin:0">Web Push (VAPID)</h3>
                <label class="sh-toggle" title="Web Push aktif/pasif">
                    <input type="checkbox" id="sh-push-enabled" <?php checked( $push_enabled ); ?> onchange="shPushToggle(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">Enable Web Push Notifications</span>
            </div>

            <div id="sh-push-content" style="<?php echo ! $push_enabled ? 'display:none' : ''; ?>">
            <div class="sh-card-body">

                <?php if ( ! $composer_ok ) : ?>
                <div style="padding:16px;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;margin-bottom:16px">
                    <strong>⚠️ minishlink/web-push yüklü değil</strong>
                    <p style="margin:6px 0 0;font-size:13px;color:#854d0e">Tema kökünde şunu çalıştır:</p>
                    <code style="display:block;background:#fff7ed;padding:8px 12px;border-radius:4px;margin-top:8px;font-size:13px">composer require minishlink/web-push</code>
                </div>
                <?php else : ?>
                <p style="margin-top:0;color:#50575e;font-size:13px">VAPID keys are required for Web Push. Generate once and keep them safe.</p>
                <?php if ( $vapid_key ) : ?>
                    <div class="sh-field" style="margin-bottom:16px">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Public Key</label>
                        <input type="text" value="<?php echo esc_attr( $vapid_key ); ?>" class="sh-input" readonly onclick="this.select()" style="font-family:Consolas,monospace;font-size:12px">
                    </div>
                    <p style="font-size:12px;color:#9ca3af;margin:0 0 16px">⚠️ Regenerating keys will invalidate all existing push subscriptions.</p>
                <?php else : ?>
                    <p style="color:#9ca3af;font-size:13px;margin-bottom:16px">No VAPID keys found. Generate a new pair to enable Web Push.</p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="sh_notify_generate_vapid">
                    <?php wp_nonce_field( 'sh_notify_generate_vapid' ); ?>
                    <button type="submit" class="sh-btn sh-btn-primary" onclick="return confirm('This will replace existing VAPID keys. Continue?')">
                        <?php echo $vapid_key ? '🔄 Regenerate VAPID Keys' : '🔑 Generate VAPID Keys'; ?>
                    </button>
                </form>
                <?php endif; ?>

            </div>
            </div><!-- /sh-push-content -->
        </div>
        </div><!-- .sh-main -->
        </div><!-- .sh-layout -->

        <script>
        function shPushToggle(enabled) {
            document.getElementById('sh-push-content').style.display = enabled ? '' : 'none';
            fetch('<?php echo esc_js( $ajax_url ); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=sh_notify_save_push_toggle&enabled=' + (enabled ? 1 : 0) + '&nonce=<?php echo esc_js( $nonce ); ?>'
            }).then(r => r.json()).then(function(res) {
                if (res.success) {
                    if (typeof shShowToast === 'function') shShowToast(enabled ? '✅ Web Push enabled' : '⛔ Web Push disabled');
                } else {
                    if (typeof shShowToast === 'function') shShowToast('❌ ' + (res.data || 'Error'), 'error');
                    document.getElementById('sh-push-enabled').checked = !enabled;
                    document.getElementById('sh-push-content').style.display = !enabled ? '' : 'none';
                }
            });
        }
        </script>
        <?php
    }

    // --- LOG TAB ---

    private static function renderLogTab(): void
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'notify_log';
        $retention = (int) get_option( 'sh_notify_log_retention', 30 );
        $nonce     = wp_create_nonce( 'sh_notify_nonce' );

        // Stats
        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $sent    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent'" );
        $failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" );
        $today   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(sent_at) = CURDATE()" );
        $week    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );

        // Top event
        $top_event_row = $wpdb->get_row( "SELECT event, COUNT(*) as cnt FROM {$table} GROUP BY event ORDER BY cnt DESC LIMIT 1" );

        // Logs — son 500
        $logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 500" ) ?: [];

        // Distinct events + channels for filters
        $distinct_events   = $wpdb->get_col( "SELECT DISTINCT event FROM {$table} ORDER BY event ASC" ) ?: [];
        $distinct_channels = $wpdb->get_col( "SELECT DISTINCT channel FROM {$table} ORDER BY channel ASC" ) ?: [];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

            <!-- Stat Cards -->
            <div class="sh-cards" style="margin-bottom:20px;">
                <div class="sh-card">
                    <h3>Toplam</h3>
                    <div class="sh-card-val"><?php echo number_format_i18n($total); ?></div>
                    <div class="sh-card-sub">Tum zamanlar</div>
                </div>
                <div class="sh-card">
                    <h3>Basarili</h3>
                    <div class="sh-card-val" style="color:#00a32a;"><?php echo number_format_i18n($sent); ?></div>
                    <div class="sh-card-sub"><?php echo $total > 0 ? round($sent/$total*100) : 0; ?>% basari orani</div>
                </div>
                <div class="sh-card">
                    <h3>Basarisiz</h3>
                    <div class="sh-card-val" style="color:#d63638;"><?php echo number_format_i18n($failed); ?></div>
                    <div class="sh-card-sub">Hata alan</div>
                </div>
                <div class="sh-card">
                    <h3>Bugun</h3>
                    <div class="sh-card-val"><?php echo number_format_i18n($today); ?></div>
                    <div class="sh-card-sub">Son 7 gun: <?php echo number_format_i18n($week); ?></div>
                </div>
                <?php if ($top_event_row) : ?>
                <div class="sh-card">
                    <h3>En Cok</h3>
                    <div class="sh-card-val" style="font-size:14px;line-height:1.4;"><?php echo esc_html($top_event_row->event); ?></div>
                    <div class="sh-card-sub"><?php echo number_format_i18n((int)$top_event_row->cnt); ?> kez</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filter Bar -->
            <div class="sh-filter-bar" style="margin-bottom:16px;">
                <label>Event</label>
                <select id="sh-log-event-f" class="sh-select" style="width:160px;">
                    <option value="">Tum eventler</option>
                    <?php foreach ($distinct_events as $ev) : ?>
                        <option value="<?php echo esc_attr($ev); ?>"><?php echo esc_html($ev); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Channel</label>
                <select id="sh-log-channel-f" class="sh-select" style="width:120px;">
                    <option value="">Tum kanallar</option>
                    <?php foreach ($distinct_channels as $ch) : ?>
                        <option value="<?php echo esc_attr($ch); ?>"><?php echo esc_html(strtoupper($ch)); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Status</label>
                <select id="sh-log-status-f" class="sh-select" style="width:110px;">
                    <option value="">Tumu</option>
                    <option value="sent">Basarili</option>
                    <option value="failed">Basarisiz</option>
                </select>
                <span id="sh-log-cnt" class="sh-count-label"></span>
                <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
                    <button type="button" class="sh-btn sh-btn-secondary" id="sh-log-export-btn" style="font-size:12px;">&#8595; CSV</button>
                    <button type="button" class="sh-btn sh-btn-danger" id="sh-log-clear-btn" data-nonce="<?php echo esc_attr($nonce); ?>" style="font-size:12px;">&#128465; Clear Log</button>
                </div>
            </div>

            <!-- Log Table -->
            <?php if (empty($logs)) : ?>
                <div class="sh-empty-box">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#c3c4c7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <p style="margin:0 0 4px;font-weight:500;color:#50575e">Henuz log kaydi yok</p>
                    <p style="margin:0;font-size:12px;color:#9ca3af">Notification gonderilince burada gorunecek.</p>
                </div>
            <?php else : ?>
            <div class="sh-table-wrap">
                <table class="sh-table" id="sh-log-tbl">
                    <thead><tr>
                        <th style="width:140px;">Zaman</th>
                        <th>Event</th>
                        <th style="width:80px;">Kanal</th>
                        <th style="width:160px;">Alici</th>
                        <th style="width:80px;">Durum</th>
                        <th>Mesaj</th>
                        <th style="width:60px;"></th>
                    </tr></thead>
                    <tbody id="sh-log-tbody">
                    <?php foreach ($logs as $log) :
                        $receiver_id = (int) ($log->receiver_id ?? 0);
                        $receiver_name = '';
                        if ($receiver_id > 0) {
                            $ru = get_userdata($receiver_id);
                            $receiver_name = $ru ? $ru->display_name : '#' . $receiver_id;
                        }
                        $status = $log->status ?? 'unknown';
                        $error  = $log->error ?? '';
                    ?>
                    <tr data-event="<?php echo esc_attr($log->event ?? ''); ?>"
                        data-channel="<?php echo esc_attr($log->channel ?? ''); ?>"
                        data-status="<?php echo esc_attr($status); ?>">
                        <td style="font-size:11px;color:#9ca3af;white-space:nowrap;"><?php echo esc_html($log->sent_at ?? ''); ?></td>
                        <td><code style="font-size:11px;background:var(--ts-gray-100);padding:2px 6px;border-radius:3px;"><?php echo esc_html($log->event ?? ''); ?></code></td>
                        <td><span class="sh-ch-badge sh-ch-<?php echo esc_attr($log->channel ?? ''); ?> active" style="font-size:10px;"><?php echo esc_html(strtoupper($log->channel ?? '')); ?></span></td>
                        <td style="font-size:12px;">
                            <?php if ($receiver_name) : ?>
                                <span style="display:flex;align-items:center;gap:5px;">
                                    <?php $av = $receiver_id > 0 ? get_avatar_url($receiver_id, ['size'=>18]) : ''; ?>
                                    <?php if ($av) : ?><img src="<?php echo esc_url($av); ?>" style="width:18px;height:18px;border-radius:50%;object-fit:cover;" alt=""><?php endif; ?>
                                    <?php echo esc_html(mb_substr($receiver_name, 0, 20)); ?>
                                </span>
                            <?php else : ?>
                                <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sh-log-status sh-log-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status); ?></span>
                        </td>
                        <td style="font-size:11px;color:#6b7280;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($error); ?>">
                            <?php echo esc_html(mb_substr($error, 0, 80)); ?>
                        </td>
                        <td>
                            <?php if ($status === 'failed') : ?>
                                <button type="button" class="sh-icon-btn sh-icon-edit sh-log-retry-btn" title="Retry" data-id="<?php echo esc_attr($log->id); ?>" data-event="<?php echo esc_attr($log->event ?? ''); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">&#8635;</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="sh-pag" id="sh-log-pag"></div>
            </div>
            <?php endif; ?>

        </div>
        <div class="sh-sidebar">
            <!-- Retention Ayari -->
            <div class="sh-card">
                <h3 style="margin:0 0 12px;font-size:13px;font-weight:600;">Log Retention</h3>
                <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">Log kayitlari kac gun saklansin?</p>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" id="sh-log-retention" class="sh-input" value="<?php echo esc_attr($retention); ?>" min="1" max="365" style="width:70px;">
                    <span style="font-size:12px;color:#6b7280;">gun</span>
                    <button type="button" class="sh-btn sh-btn-primary" id="sh-log-retention-save" data-nonce="<?php echo esc_attr($nonce); ?>" style="font-size:12px;padding:6px 12px;">Kaydet</button>
                </div>
                <p style="font-size:11px;color:#9ca3af;margin:8px 0 0;">Gunluk cron ile otomatik temizlenir.</p>
            </div>
            <!-- Log Stats -->
            <div class="sh-card" style="margin-top:12px;">
                <h3 style="margin:0 0 10px;font-size:13px;font-weight:600;">Kanal Dagilimi</h3>
                <?php
                $ch_stats = $wpdb->get_results("SELECT channel, COUNT(*) as cnt, SUM(status='sent') as ok, SUM(status='failed') as fail FROM {$table} GROUP BY channel ORDER BY cnt DESC") ?: [];
                foreach ($ch_stats as $cs) :
                    $pct = $cs->cnt > 0 ? round($cs->ok / $cs->cnt * 100) : 0;
                ?>
                <div style="margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                        <span class="sh-ch-badge sh-ch-<?php echo esc_attr($cs->channel); ?> active" style="font-size:10px;"><?php echo esc_html(strtoupper($cs->channel)); ?></span>
                        <span style="font-size:11px;color:#6b7280;"><?php echo number_format_i18n((int)$cs->cnt); ?> / <?php echo $pct; ?>%</span>
                    </div>
                    <div style="height:4px;background:#f0f0f1;border-radius:2px;overflow:hidden;">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:#00a32a;border-radius:2px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($ch_stats)) : ?>
                    <p style="font-size:12px;color:#9ca3af;margin:0;">Henuz veri yok.</p>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <script>
        (function(){
            var tbody = document.getElementById('sh-log-tbody');
            var allRows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
            var filtered = allRows.slice();
            var PAGE = 50, page = 1;

            function go() {
                var ev  = document.getElementById('sh-log-event-f')?.value   || '';
                var ch  = document.getElementById('sh-log-channel-f')?.value || '';
                var st  = document.getElementById('sh-log-status-f')?.value  || '';
                filtered = allRows.filter(function(r){
                    if (ev && r.dataset.event   !== ev) return false;
                    if (ch && r.dataset.channel !== ch) return false;
                    if (st && r.dataset.status  !== st) return false;
                    return true;
                });
                page = 1;
                render();
            }

            function render() {
                var start = (page-1)*PAGE;
                allRows.forEach(function(r){ r.classList.add('sh-hidden'); });
                filtered.slice(start, start+PAGE).forEach(function(r){ r.classList.remove('sh-hidden'); });
                var cnt = document.getElementById('sh-log-cnt');
                if (cnt) cnt.textContent = filtered.length + ' kayit';
                renderPag();
            }

            function renderPag() {
                var pag = document.getElementById('sh-log-pag');
                if (!pag) return;
                var total = Math.ceil(filtered.length / PAGE);
                pag.innerHTML = '';
                if (total <= 1) return;
                for (var i = 1; i <= total; i++) {
                    (function(p){
                        var btn = document.createElement('button');
                        btn.className = 'sh-pg-btn' + (p===page?' active':'');
                        btn.textContent = p;
                        btn.addEventListener('click', function(){ page=p; render(); });
                        pag.appendChild(btn);
                    })(i);
                }
            }

            ['sh-log-event-f','sh-log-channel-f','sh-log-status-f'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', go);
            });

            go();

            // Retention save
            document.getElementById('sh-log-retention-save')?.addEventListener('click', function(){
                var val = document.getElementById('sh-log-retention')?.value;
                var btn = this;
                btn.textContent = 'Kaydediliyor...';
                btn.disabled = true;
                jQuery.post(ajaxurl, { action:'sh_notify_save_retention', nonce:this.dataset.nonce, days:val }, function(res){
                    btn.textContent = 'Kaydet';
                    btn.disabled = false;
                    if (res.success) {
                        var t = document.getElementById('sh-toast');
                        if (t) { t.textContent='Kaydedildi!'; t.classList.add('show'); setTimeout(function(){t.classList.remove('show');},2000); }
                    }
                });
            });

            // Clear log
            document.getElementById('sh-log-clear-btn')?.addEventListener('click', function(){
                if (!confirm('Tum log kayitlarini silmek istediginizden emin misiniz?')) return;
                var btn = this;
                btn.disabled = true;
                jQuery.post(ajaxurl, { action:'sh_notify_clear_log', nonce:btn.dataset.nonce }, function(res){
                    btn.disabled = false;
                    if (res.success) { location.reload(); }
                });
            });

            // Retry
            document.getElementById('sh-log-tbody')?.addEventListener('click', function(e){
                var btn = e.target.closest('.sh-log-retry-btn');
                if (!btn) return;
                btn.disabled = true;
                btn.textContent = '...';
                jQuery.post(ajaxurl, { action:'sh_notify_retry_log', nonce:btn.dataset.nonce, id:btn.dataset.id, event:btn.dataset.event }, function(res){
                    btn.disabled = false;
                    btn.textContent = '↻';
                    var row = btn.closest('tr');
                    if (res.success && row) {
                        row.querySelector('[class*="sh-log-status"]').textContent = 'sent';
                        row.querySelector('[class*="sh-log-status"]').className = 'sh-log-status sh-log-status-sent';
                        btn.remove();
                    }
                });
            });

            // CSV Export
            document.getElementById('sh-log-export-btn')?.addEventListener('click', function(){
                var headers = ['Zaman','Event','Kanal','Alici','Durum','Mesaj'];
                var rows = [headers];
                filtered.forEach(function(r){
                    var cells = r.querySelectorAll('td');
                    rows.push([
                        cells[0]?.textContent.trim(),
                        cells[1]?.textContent.trim(),
                        cells[2]?.textContent.trim(),
                        cells[3]?.textContent.trim(),
                        cells[4]?.textContent.trim(),
                        cells[5]?.textContent.trim(),
                    ]);
                });
                var csv = rows.map(function(r){ return r.map(function(c){ return '"'+(c||'').replace(/"/g,'""')+'"'; }).join(','); }).join('\n');
                var a = document.createElement('a');
                a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
                a.download = 'notify-log-' + new Date().toISOString().slice(0,10) + '.csv';
                a.click();
            });

        })();
        </script>
        <?php
    }

    // --- VARIABLE REFERENCE ---

    private static function renderVariableReference(): void
    {
        $groups = [
            'User' => [
                '{{ data.user.ID }}'           => 'User ID',
                '{{ data.user.name }}'         => 'Display Name',
                '{{ data.user.email }}'        => 'Email',
                '{{ data.user.first_name }}'   => 'First Name',
                '{{ data.user.last_name }}'    => 'Last Name',
                '{{ data.user.profile_url }}'  => 'Profile URL',
                '{{ data.user.avatar_url }}'   => 'Avatar URL',
            ],
            'Post' => [
                '{{ data.post.ID }}'           => 'Post ID',
                '{{ data.post.title }}'        => 'Post Title',
                '{{ data.post.link }}'         => 'Post URL',
                '{{ data.post.excerpt }}'      => 'Excerpt',
                '{{ data.post.author_name }}'  => 'Author Name',
                '{{ data.post.thumbnail }}'    => 'Featured Image URL',
            ],
            'WooCommerce' => [
                '{{ data.order.ID }}'          => 'Order ID',
                '{{ data.order.total }}'       => 'Order Total',
                '{{ data.order.status }}'      => 'Order Status',
                '{{ data.order.items }}'       => 'Order Items',
                '{{ data.order.billing_email }}' => 'Billing Email',
            ],
            'Notify' => [
                '{{ data.event }}'             => 'Event Slug',
                '{{ data.type }}'              => 'Notification Type',
                '{{ data.message }}'           => 'Message Body',
                '{{ data.created_at }}'        => 'Timestamp',
            ],
            'Placeholders' => [
                '{{admin}}'                    => 'Admin User',
                '{{me}}'                       => 'Current User',
                '{{user}}'                     => 'Event User',
                '{{author}}'                   => 'Post Author',
                '{{users}}'                    => 'Multiple Users',
            ],
        ];
        ?>
        <div class="sh-ref-panel" id="sh-ref-panel">
            <div class="sh-ref-header">
                <span>&#128196; Variable Reference</span>
                <small style="font-weight:400;color:#9ca3af;font-size:11px">click to copy</small>
            </div>
            <div id="sh-ref-body">
                <?php $first = true; foreach ( $groups as $group_name => $vars ) : ?>
                    <div class="sh-ref-group <?php echo $first ? 'sh-ref-open' : ''; ?>">
                        <button type="button" class="sh-ref-group-trigger" onclick="shRefGroupToggle(this)">
                            <span><?php echo esc_html( $group_name ); ?></span>
                            <span class="sh-ref-count"><?php echo count( $vars ); ?></span>
                        </button>
                        <div class="sh-ref-group-body">
                            <?php foreach ( $vars as $var => $desc ) : ?>
                                <div class="sh-ref-item" onclick="shRefCopy(this)" data-var="<?php echo esc_attr( $var ); ?>">
                                    <span class="sh-ref-desc"><?php echo esc_html( $desc ); ?></span>
                                    <code class="sh-ref-code"><?php echo esc_html( $var ); ?></code>
                                    <span class="sh-ref-copied">&#10003;</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <?php
    }

    // --- STYLES ---

    // --- STYLES ---

    // --- STYLES ---

    private static function renderStyles(): void
    {
        // CSS sh-admin.css uzerinden enqueueAssets() ile yukleniyor.
        // Notification-ozgu CSS de sh-admin.css icinde tanimli.
    }

    // --- SCRIPTS ---

    private static function renderScripts( string $nonce, string $ajax_url, array $events = [], array $roles = [], array $sender_ph = [], array $recipient_ph = [], array $types = [] ): void
    {
        ?>
        <script>
        var SH_NONCE     = <?php echo wp_json_encode( $nonce ); ?>;
        var SH_AJAX      = <?php echo wp_json_encode( $ajax_url ); ?>;
        var SH_EVENTS    = <?php echo wp_json_encode( array_values( $events ) ); ?>;
        var SH_ROLES     = <?php echo wp_json_encode( $roles ); ?>;
        var SH_SENDER_PH = <?php echo wp_json_encode( $sender_ph ); ?>;
        var SH_RECIP_PH  = <?php echo wp_json_encode( $recipient_ph ); ?>;
        var SH_PH        = <?php echo wp_json_encode( $recipient_ph ); ?>; /* backward compat */
        var SH_TYPES     = <?php echo wp_json_encode( $types ); ?>;
        if (typeof SH_EXISTING === 'undefined') var SH_EXISTING = [];
        </script>
        <script>
        (function() {
            // Toast
            function toast( msg, type ) {
                type = type || 'default';
                var el = document.createElement('div');
                el.className = 'sh-toast-item' + ( type !== 'default' ? ' sh-toast-' + type : '' );
                el.textContent = msg;
                document.getElementById('sh-toast').appendChild(el);
                setTimeout(function(){ el.remove(); }, 3000);
            }
            window.shShowToast = toast; // SMS tab ve diğer tab'lar kullanabilsin

            // Master Notifications toggle
            window.shNotifsMasterToggle = function(enabled) {
                // Show saving indicator
                var saving = document.getElementById('sh-notifs-master-saving');
                var label  = document.getElementById('sh-notifs-master-label');
                if (saving) saving.style.display = '';
                if (label)  label.style.display  = 'none';

                ajax( 'sh_notify_save_master_toggle', { enabled: enabled ? 1 : 0 }, function(res) {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = '';

                    if ( res.success ) {
                        if (label) {
                            label.textContent = enabled ? 'Enabled' : 'Disabled';
                            label.style.color = enabled ? '#00a32a' : '#9ca3af';
                        }
                        // Overview sidebar: notifications toggle'a göre kanal durumlarını güncelle
                        [ 'sms', 'email', 'push' ].forEach(function(ch) {
                            var el = document.getElementById('sh-ch-status-' + ch);
                            if (!el) return;
                            var own = el.dataset.own === '1'; // kanalın kendi DB değeri
                            var active = enabled && own;
                            el.textContent = active ? '● Active' : '○ Inactive';
                            el.style.color  = active ? '#00a32a' : '#9ca3af';
                        });
                        toast(enabled ? '✅ Notifications enabled' : '⛔ Notifications disabled');
                    } else {
                        toast('❌ ' + (res.data || 'Error'), 'error');
                        var toggle = document.getElementById('sh-notifs-master-toggle');
                        if (toggle) toggle.checked = !enabled;
                    }
                });
            };

            // Overlay tıklanınca uyarı (artık kullanılmıyor ama backward compat için bırak)
            window.shNotifsOverlayClick = function() {
                toast('⚠️ Enable Notifications first using the toggle above.', 'warning');
            };

            // AJAX helper
            function ajax( action, data, cb ) {
                data.action  = action;
                data.nonce   = SH_NONCE;
                var fd = new FormData();
                for ( var k in data ) { fd.append( k, data[k] ); }
                fetch( SH_AJAX, { method:'POST', body:fd } )
                    .then(function(r){ return r.json(); })
                    .then(function(r){ cb( r ); })
                    .catch(function(){ cb({ success:false, data:'Network error' }); });
            }

            // Filter rules
            window.shFilterRules = function() {
                var role  = document.getElementById('sh-filter-role').value;
                var event = document.getElementById('sh-filter-event').value;
                var cards = document.querySelectorAll('.sh-rule-card');
                var count = 0;
                cards.forEach(function(c){
                    var show = ( !role  || c.dataset.role  === role  ) &&
                               ( !event || c.dataset.event === event );
                    c.style.display = show ? '' : 'none';
                    if ( show ) count++;
                });
                var lbl = document.getElementById('sh-rule-count');
                if ( lbl ) lbl.textContent = count + ' rule' + ( count !== 1 ? 's' : '' );
            };

            // Add new rule (blank card)
            window.shAddRule = function() {
                var list = document.getElementById('sh-rules-list');
                var empty = document.getElementById('sh-empty-state');
                if ( empty ) empty.style.display = 'none';

                var roleOpts = '';
                for ( var r in SH_ROLES ) {
                    roleOpts += '<option value="' + r + '">' + SH_ROLES[r] + '</option>';
                }
                var eventOpts = '';
                (function(){
                    var groups = {};
                    SH_EVENTS.forEach(function(e){
                        var g = e.group || 'Other';
                        if (!groups[g]) groups[g] = [];
                        groups[g].push(e);
                    });
                    Object.keys(groups).sort().forEach(function(g){
                        eventOpts += '<optgroup label="' + g + '">';
                        groups[g].forEach(function(e){
                            eventOpts += '<option value="' + e.slug + '">' + e.title + '</option>';
                        });
                        eventOpts += '</optgroup>';
                    });
                })();
                var typeOpts = '';
                for ( var t in SH_TYPES ) {
                    typeOpts += '<option value="' + t + '">' + SH_TYPES[t] + '</option>';
                }
                var senderOpts = '';
                for ( var s in SH_SENDER_PH ) {
                    senderOpts += '<option value="' + s + '">' + SH_SENDER_PH[s] + '</option>';
                }
                var recipOpts = '';
                for ( var p in SH_RECIP_PH ) {
                    recipOpts += '<option value="' + p + '">' + SH_RECIP_PH[p] + '</option>';
                }

                var carriers = ['email','alert','sms','push'];
                var icons    = { email:'&#9993;', alert:'&#128276;', sms:'&#128172;', push:'&#128242;' };
                var carrierHtml = '';
                carriers.forEach(function(ch){
                    var extra = '';
                    if ( ch === 'email' ) {
                        extra = '<div class="sh-field"><label>Subject</label><input type="text" name="carriers[email][subject]" value="" placeholder="Order confirmed" class="sh-input"></div>' +
                                '<div class="sh-field sh-field-row"><label>Use HTML Template</label><label class="sh-toggle"><input type="checkbox" name="carriers[email][template]" value="1" onchange="shTemplateToggle(this)"><span class="sh-toggle-slider"></span></label></div>';
                    }
                    if ( ch === 'push' ) {
                        extra = '<div class="sh-field"><label>Title</label><input type="text" name="carriers[push][title]" value="" placeholder="New notification" class="sh-input"></div>' +
                                '<div class="sh-field"><label>Icon URL</label><input type="text" name="carriers[push][icon]" value="" placeholder="/static/img/icon-192.png" class="sh-input"></div>' +
                                '<div class="sh-field"><label>Click URL</label><input type="text" name="carriers[push][url]" value="" placeholder="{{ data.post.link }}" class="sh-input"></div>';
                    }
                    carrierHtml += '<div class="sh-carrier" data-channel="' + ch + '">' +
                        '<div class="sh-carrier-header" onclick="shToggleCarrier(this)">' +
                        '<span class="sh-carrier-icon">' + icons[ch] + '</span>' +
                        '<span class="sh-carrier-name">' + ch.toUpperCase() + '</span>' +
                        '<label class="sh-toggle" onclick="event.stopPropagation()"><input type="checkbox" name="carriers[' + ch + '][active]" value="1" onchange="shCarrierToggle(this)"><span class="sh-toggle-slider"></span></label>' +
                        '<span class="sh-carrier-arrow">&#8250;</span></div>' +
                        '<div class="sh-carrier-body">' + extra +
                        '<div class="sh-field sh-body-field"><label>Body</label><textarea name="carriers[' + ch + '][body]" rows="4" class="sh-textarea" placeholder="Your message here..."></textarea></div>' +
                        '</div></div>';
                });

                var card = document.createElement('div');
                card.className = 'sh-rule-card';
                card.dataset.id = '0';
                card.innerHTML =
                    '<div class="sh-rule-header"><div class="sh-rule-meta"><span style="color:#9ca3af;font-size:13px">New Rule</span></div>' +
                    '<div class="sh-rule-actions"><button type="button" class="sh-icon-btn sh-icon-delete" onclick="shDeleteRule(this)" title="Cancel">&#10005;</button></div></div>' +
                    '<div class="sh-rule-form">' +
                    '<div class="sh-form-row">' +
                    '<div class="sh-form-col"><label>Role *</label><select name="role" class="sh-select">' + roleOpts + '</select></div>' +
                    '<div class="sh-form-col"><label>Event *</label><select name="event" class="sh-select">' + eventOpts + '</select></div>' +
                    '<div class="sh-form-col sh-form-col-sm"><label>Type</label><select name="type" class="sh-select">' + typeOpts + '</select></div>' +
                    '<div class="sh-form-col sh-form-col-sm"><label>Sender</label><select name="sender" class="sh-select">' + senderOpts + '</select></div>' +
                    '<div class="sh-form-col sh-form-col-sm"><label>Recipient</label><select name="recipient" class="sh-select">' + recipOpts + '</select></div>' +
                    '</div>' +
                    '<div class="sh-carriers">' + carrierHtml + '</div>' +
                    '<div class="sh-form-footer">' +
                    '<button type="button" class="sh-btn sh-btn-primary" onclick="shSaveRule(this)">Save Rule</button>' +
                    '<button type="button" class="sh-btn sh-btn-ghost" onclick="shCancelEdit(this)">Cancel</button>' +
                    '</div></div>';
                list.insertBefore( card, list.firstChild );
                card.querySelector('.sh-rule-form').style.display = '';
            };

            window.shEditRule = function(btn) {
                var card = btn.closest('.sh-rule-card');
                card.querySelector('.sh-rule-form').style.display = '';
            };

            window.shCancelEdit = function(btn) {
                var card = btn.closest('.sh-rule-card');
                if ( card.dataset.id === '0' ) {
                    card.remove();
                    return;
                }
                card.querySelector('.sh-rule-form').style.display = 'none';
                var editBtn = card.querySelector('.sh-icon-edit');
                if ( editBtn ) editBtn.style.display = '';
            };

            window.shSaveRule = function(btn) {
                var card = btn.closest('.sh-rule-card');
                var id   = card.dataset.id;
                var data = { id: id };
                var form = card.querySelector('.sh-rule-form');

                form.querySelectorAll('[name]').forEach(function(el){
                    if ( el.type === 'checkbox' ) {
                        data[ el.name ] = el.checked ? '1' : '0';
                    } else {
                        data[ el.name ] = el.value;
                    }
                });

                btn.disabled = true;
                btn.textContent = 'Saving...';

                ajax( 'sh_notify_save_rule', data, function(r) {
                    btn.disabled = false;
                    btn.textContent = 'Save Rule';
                    if ( r.success ) {
                        toast( 'Rule saved!', 'success' );
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        toast( r.data || 'Error saving rule', 'error' );
                    }
                });
            };

            window.shDeleteRule = function(btn) {
                var card = btn.closest('.sh-rule-card');
                var id   = card.dataset.id;
                if ( id === '0' ) { card.remove(); return; }
                if ( !confirm('Delete this rule?') ) return;
                ajax( 'sh_notify_delete_rule', { id: id }, function(r) {
                    if ( r.success ) {
                        toast( 'Rule deleted', 'success' );
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        toast( r.data || 'Error', 'error' );
                    }
                });
            };

            window.shToggleRule = function(checkbox) {
                var card = checkbox.closest('.sh-rule-card');
                var id   = card.dataset.id;
                ajax( 'sh_notify_save_rule', { id: id, toggle: '1' }, function(r) {
                    if ( r.success ) {
                        card.classList.toggle('sh-rule-inactive', !checkbox.checked);
                        toast( checkbox.checked ? 'Rule activated' : 'Rule deactivated', 'success' );
                    } else {
                        checkbox.checked = !checkbox.checked; // revert
                        toast( r.data || 'Error', 'error' );
                    }
                });
            };

            window.shTestRule = function(btn) {
                var card = btn.closest('.sh-rule-card');
                var id   = card.dataset.id;
                btn.disabled = true;
                ajax( 'sh_notify_test_rule', { id: id }, function(r) {
                    btn.disabled = false;
                    if ( r.success ) {
                        toast( r.data || 'Test sent!', 'success' );
                    } else {
                        toast( r.data || 'Test failed', 'error' );
                    }
                });
            };

            window.shToggleCarrier = function(header) {
                var carrier = header.closest('.sh-carrier');
                carrier.classList.toggle('sh-carrier-open');
            };

            window.shCarrierToggle = function(cb) {
                var carrier = cb.closest('.sh-carrier');
                if ( cb.checked ) {
                    carrier.classList.add('sh-carrier-active');
                    carrier.classList.add('sh-carrier-open');
                } else {
                    carrier.classList.remove('sh-carrier-active');
                    carrier.classList.remove('sh-carrier-open');
                }
            };

            window.shTemplateToggle = function(cb) {
                var bodyField = cb.closest('.sh-carrier-body').querySelector('.sh-body-field');
                if ( bodyField ) {
                    bodyField.classList.toggle('sh-hidden', cb.checked);
                }
            };

            // Events tab
            window.shShowAddEvent = function() {
                document.getElementById('sh-add-event-form').style.display = '';
                document.getElementById('sh-add-event-btn').style.display = 'none';
            };

            window.shHideAddEvent = function() {
                document.getElementById('sh-add-event-form').style.display = 'none';
                document.getElementById('sh-add-event-btn').style.display = '';
                document.getElementById('sh-new-slug').value  = '';
                document.getElementById('sh-new-title').value = '';
                document.getElementById('sh-new-desc').value  = '';
            };

            window.shSaveNewEvent = function() {
                var slug  = document.getElementById('sh-new-slug').value.trim();
                var title = document.getElementById('sh-new-title').value.trim();
                var desc  = document.getElementById('sh-new-desc').value.trim();
                if ( !slug || !title ) { toast('Slug and Title are required', 'error'); return; }
                ajax( 'sh_notify_save_event', { id:'0', slug:slug, title:title, description:desc }, function(r) {
                    if ( r.success ) {
                        toast('Event added!', 'success');
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        toast( r.data || 'Error', 'error' );
                    }
                });
            };

            window.shEditEvent = function(btn) {
                var row = btn.closest('.sh-event-row');
                row.querySelector('.sh-event-info').style.display = 'none';
                row.querySelector('.sh-event-form').style.display = '';
            };

            window.shCancelEventEdit = function(btn) {
                var row = btn.closest('.sh-event-row');
                row.querySelector('.sh-event-info').style.display = '';
                row.querySelector('.sh-event-form').style.display = 'none';
            };

            window.shSaveEvent = function(btn) {
                var row  = btn.closest('.sh-event-row');
                var id   = row.dataset.id;
                var slug  = row.querySelector('[name="slug"]').value.trim();
                var title = row.querySelector('[name="title"]').value.trim();
                var desc  = row.querySelector('[name="description"]').value.trim();
                if ( !slug || !title ) { toast('Slug and Title are required', 'error'); return; }
                btn.disabled = true;
                ajax( 'sh_notify_save_event', { id:id, slug:slug, title:title, description:desc }, function(r) {
                    btn.disabled = false;
                    if ( r.success ) {
                        toast('Event saved!', 'success');
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        toast( r.data || 'Error', 'error' );
                    }
                });
            };

            window.shDeleteEvent = function(btn) {
                var row = btn.closest('.sh-event-row');
                var id  = row.dataset.id;
                if ( !confirm('Delete this event?') ) return;
                ajax( 'sh_notify_delete_event', { id:id }, function(r) {
                    if ( r.success ) {
                        toast('Event deleted', 'success');
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        toast( r.data || 'Error', 'error' );
                    }
                });
            };

            // Variable reference panel — accordion
            window.shRefGroupToggle = function(btn) {
                var group  = btn.closest('.sh-ref-group');
                var panel  = document.getElementById('sh-ref-body');
                var isOpen = group.classList.contains('sh-ref-open');
                panel.querySelectorAll('.sh-ref-group').forEach(function(g){ g.classList.remove('sh-ref-open'); });
                if ( !isOpen ) group.classList.add('sh-ref-open');
            };

            window.shRefCopy = function(el) {
                var v = el.dataset.var;
                var copy = function() {
                    el.classList.add('copied');
                    setTimeout(function(){ el.classList.remove('copied'); }, 1500);
                };
                if ( navigator.clipboard ) {
                    navigator.clipboard.writeText(v).then(copy).catch(function(){
                        var ta = document.createElement('textarea');
                        ta.value = v; document.body.appendChild(ta); ta.select();
                        document.execCommand('copy'); ta.remove(); copy();
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = v; document.body.appendChild(ta); ta.select();
                    document.execCommand('copy'); ta.remove(); copy();
                }
            };
            // ── Get Code Modal ────────────────────────────────────────────────

            // Recipient placeholder → PHP kod karsiligi
            var RECIP_CODE_MAP = {
                '{{admin}}':  "// Recipient: Admin\n$admin = get_users(['role'=>'administrator','number'=>1])[0] ?? null;\n$recipient_id = $admin ? $admin->ID : 1;",
                '{{me}}':     "// Recipient: Current User\n$recipient_id = get_current_user_id();",
                '{{user}}':   "// Recipient: Event User (fire() cagrisindaki user)\n$recipient_id = $actor->ID; // fire() icinde 'user' olarak gecilen kullanici",
                '{{author}}': "// Recipient: Post Author\n$recipient_id = (int) get_post_field('post_author', $post_id);",
                '{{users}}':  "// Recipient: Multiple Users — her biri icin ayri fire() cagir\n$users = get_users(['role' => 'subscriber']);\nforeach ($users as $u) {\n    Notifications::fire('{event}', ['user' => $actor, 'recipient' => $u->ID]);\n}",
            };

            window.shGetCode = function(btn) {
                var card      = btn.closest('.sh-rule-card');
                var eventSlug = card.dataset.event || card.querySelector('[name="event"]')?.value || '';
                var role      = card.dataset.role  || card.querySelector('[name="role"]')?.value  || '';
                var recipient = card.querySelector('[name="recipient"]')?.value || '{{user}}';

                var recipCode = RECIP_CODE_MAP[recipient] || "// Recipient: " + recipient + "\n$recipient_id = $recipient_id;";
                recipCode = recipCode.replace(/\{event\}/g, eventSlug);

                var isMultiple = recipient === '{{users}}';

                var code;
                if (isMultiple) {
                    code = recipCode.replace(/\{event\}/g, eventSlug);
                } else {
                    code = recipCode + "\n\n" +
                        "Notifications::fire('" + eventSlug + "', [\n" +
                        "    'user'      => $actor,        // tetikleyen kullanici (WP_User)\n" +
                        "    'recipient' => $recipient_id, // alici kullanici ID\n" +
                        "    // 'post'   => get_post($post_id), // opsiyonel: ilgili post\n" +
                        "]);";
                }

                // Modal'i ac
                var modal = document.getElementById('sh-code-modal');
                modal.querySelector('.sh-code-modal-event').textContent = eventSlug;
                modal.querySelector('.sh-code-modal-role').textContent  = role || 'all';
                modal.querySelector('.sh-code-modal-body').textContent  = code;
                modal.style.display = 'flex';
            };

            window.shCloseCodeModal = function() {
                document.getElementById('sh-code-modal').style.display = 'none';
            };

            window.shCopyCode = function() {
                var code = document.querySelector('#sh-code-modal .sh-code-modal-body').textContent;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(code).then(function(){
                        var btn = document.getElementById('sh-copy-code-btn');
                        btn.textContent = 'Copied!';
                        setTimeout(function(){ btn.textContent = 'Copy'; }, 2000);
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = code; document.body.appendChild(ta); ta.select();
                    document.execCommand('copy'); ta.remove();
                }
            };

            // Modal disina tiklayinca kapat
            document.getElementById('sh-code-modal')?.addEventListener('click', function(e){
                if (e.target === this) shCloseCodeModal();
            });

        })();
        </script>

        <!-- Get Code Modal -->
        <div id="sh-code-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;width:600px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb;">
                    <div>
                        <strong style="font-size:15px;color:#111827;">Get Code</strong>
                        <span style="font-size:12px;color:#6b7280;margin-left:8px;">
                            Event: <code style="background:#f3f4f6;padding:1px 5px;border-radius:3px;" class="sh-code-modal-event"></code>
                            &nbsp;Role: <code style="background:#f3f4f6;padding:1px 5px;border-radius:3px;" class="sh-code-modal-role"></code>
                        </span>
                    </div>
                    <button onclick="shCloseCodeModal()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#6b7280;line-height:1;">&#10005;</button>
                </div>
                <div style="padding:20px;">
                    <p style="margin:0 0 10px;font-size:12px;color:#6b7280;">Bu kodu ilgili hook veya fonksiyonun icine yapistir:</p>
                    <pre class="sh-code-modal-body" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;font-family:Consolas,monospace;line-height:1.7;overflow:auto;max-height:320px;margin:0;white-space:pre-wrap;"></pre>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e5e7eb;background:#f9fafb;">
                    <button onclick="shCloseCodeModal()" style="padding:7px 16px;border-radius:4px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:13px;">Close</button>
                    <button id="sh-copy-code-btn" onclick="shCopyCode()" style="padding:7px 16px;border-radius:4px;border:none;background:#2271b1;color:#fff;cursor:pointer;font-size:13px;font-weight:500;">Copy</button>
                </div>
            </div>
        </div>
        <?php
    }

    // --- AJAX HANDLERS ---

    public static function ajaxSaveRule(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'notify_rules';
        $id    = (int) ( $_POST['id'] ?? 0 );

        // Toggle only
        if ( ! empty( $_POST['toggle'] ) ) {
            $current = $wpdb->get_var( $wpdb->prepare( "SELECT active FROM {$table} WHERE id = %d", $id ) );
            $wpdb->update( $table, [ 'active' => $current ? 0 : 1 ], [ 'id' => $id ] );
            wp_send_json_success( [ 'id' => $id ] );
        }

        $carriers = [];
        if ( ! empty( $_POST['carriers'] ) && is_array( $_POST['carriers'] ) ) {
            foreach ( $_POST['carriers'] as $ch => $cfg ) {
                $ch  = sanitize_key( $ch );
                $carriers[ $ch ] = [
                    'active'  => ! empty( $cfg['active'] ),
                    'body'    => wp_unslash( $cfg['body'] ?? '' ),   // Twig template — sanitize etme, slash'ları kaldır
                    'subject' => wp_unslash( $cfg['subject'] ?? '' ),
                    'template'=> ! empty( $cfg['template'] ),
                    'title'   => sanitize_text_field( $cfg['title'] ?? '' ),
                    'icon'    => esc_url_raw( $cfg['icon'] ?? '' ),
                    'url'     => wp_unslash( $cfg['url'] ?? '' ),    // Twig variable içerebilir
                ];
            }
        }

        $data = [
            'role'      => sanitize_key( $_POST['role'] ?? '' ),
            'event'     => sanitize_key( $_POST['event'] ?? '' ),
            'type'      => sanitize_key( $_POST['type'] ?? 'info' ),
            'sender'    => sanitize_text_field( $_POST['sender'] ?? '' ),
            'recipient' => sanitize_text_field( $_POST['recipient'] ?? '' ),
            'carriers'  => wp_json_encode( $carriers ),
            'active'    => 1,
        ];

        if ( $id > 0 ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function ajaxDeleteRule(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        $wpdb->delete( $wpdb->prefix . 'notify_rules', [ 'id' => $id ] );
        wp_send_json_success();
    }

    public static function ajaxSaveEvent(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'notify_events';
        $id    = (int) ( $_POST['id'] ?? 0 );
        $data  = [
            'slug'        => sanitize_key( $_POST['slug'] ?? '' ),
            'title'       => sanitize_text_field( $_POST['title'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
        ];
        if ( ! $data['slug'] || ! $data['title'] ) {
            wp_send_json_error( 'Slug and title are required' );
        }
        if ( $id > 0 ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'id' => $id ] );
    }

    public static function ajaxDeleteEvent(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        $wpdb->delete( $wpdb->prefix . 'notify_events', [ 'id' => $id ] );
        wp_send_json_success();
    }

    public static function ajaxTestRule(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        global $wpdb;
        $id   = (int) ( $_POST['id'] ?? 0 );
        $rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}notify_rules WHERE id = %d", $id ) );
        if ( ! $rule ) {
            wp_send_json_error( 'Rule not found' );
        }

        $carriers = json_decode( $rule->carriers ?? '{}', true ) ?: [];
        $user     = wp_get_current_user();
        $post     = get_post( get_option( 'page_on_front' ) );
        $data     = [ 'user' => $user, 'post' => $post ];

        // Rule'daki event'i geçici NotifyEvent olarak oluştur
        $event = \SaltHareket\Notifications\NotifyEvent::make( $rule->event, [
            'label'     => $rule->event,
            'channels'  => array_keys( array_filter( $carriers, fn($c) => ! empty( $c['active'] ) ) ),
            'sender'    => $rule->sender,
            'recipient' => $rule->recipient,
        ] + array_combine(
            array_keys( $carriers ),
            array_map( fn($c) => [
                'body'     => $c['body']     ?? '',
                'subject'  => $c['subject']  ?? '',
                'template' => ! empty( $c['template'] ),
                'title'    => $c['title']    ?? '',
                'icon'     => $c['icon']     ?? '',
                'url'      => $c['url']      ?? '',
            ], $carriers )
        ) );

        NotifyDispatcher::init();

        $summary  = [];
        $sender_id = (int) get_current_user_id();

        foreach ( $event->channels as $channel ) {
            $carrier = \SaltHareket\Notifications\NotifyDispatcher::getCarrier( $channel );
            if ( ! $carrier ) {
                $summary[] = strtoupper( $channel ) . ': no carrier registered';
                continue;
            }

            $payload = new \SaltHareket\Notifications\NotifyPayload(
                event:       $event,
                channel:     $channel,
                sender_id:   $sender_id,
                receiver_id: (int) $user->ID,
                data:        $data,
            );

            $emailCarrier = ( $channel === 'email' )
                ? new \SaltHareket\Notifications\Carriers\EmailCarrier()
                : null;

            $payload = \SaltHareket\Notifications\NotifyRenderer::render( $payload, $emailCarrier );
            $result  = $carrier->handle( $payload );

            if ( $result->skipped ) {
                $summary[] = strtoupper( $channel ) . ': skipped (' . $result->reason . ')';
            } elseif ( $result->success ) {
                $summary[] = strtoupper( $channel ) . ': sent ✓';
            } else {
                $summary[] = strtoupper( $channel ) . ': failed — ' . $result->error;
            }
        }

        if ( empty( $summary ) ) {
            wp_send_json_error( 'No active carriers in this rule.' );
        }

        wp_send_json_success( implode( ' | ', $summary ) );
    }

    public static function ajaxSaveRetention(): void {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $days = max( 1, min( 365, (int) ( $_POST['days'] ?? 30 ) ) );
        update_option( 'sh_notify_log_retention', $days );
        wp_send_json_success( [ 'days' => $days ] );
    }

    public static function ajaxClearLog(): void {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        global $wpdb;
        $deleted = $wpdb->query( "DELETE FROM {$wpdb->prefix}notify_log" );
        wp_send_json_success( [ 'deleted' => $deleted ] );
    }

    public static function ajaxRetryLog(): void {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $id    = (int) ( $_POST['id'] ?? 0 );
        $event = sanitize_key( $_POST['event'] ?? '' );
        if ( ! $id || ! $event ) wp_send_json_error( 'Invalid params' );

        global $wpdb;
        $log = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}notify_log WHERE id = %d LIMIT 1", $id
        ) );
        if ( ! $log ) wp_send_json_error( 'Log not found' );

        // Receiver'i bul ve fire et
        $receiver_id = (int) ( $log->receiver_id ?? 0 );
        $user        = $receiver_id > 0 ? get_userdata( $receiver_id ) : null;

        try {
            $results = \Notifications::fire( $event, [
                'user'      => $user,
                'recipient' => $receiver_id,
            ] );
            // Log kaydini guncelle
            $wpdb->update(
                $wpdb->prefix . 'notify_log',
                [ 'status' => 'sent', 'error' => null ],
                [ 'id' => $id ]
            );
            wp_send_json_success( [ 'id' => $id ] );
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }

    public static function ajaxSaveMasterToggle(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $enabled = ! empty( $_POST['enabled'] );
        \SaltHareket\Notifications\NotificationsSettings::save( [ 'enable_notifications' => $enabled ] );
        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    public static function ajaxSavePushToggle(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $enabled = ! empty( $_POST['enabled'] );
        \SaltHareket\Notifications\NotificationsSettings::save( [ 'enable_web_push' => $enabled ] );
        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    public static function ajaxSaveSmsToggle(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $enabled = ! empty( $_POST['enabled'] );
        \SaltHareket\Notifications\NotificationsSettings::save( [ 'enable_sms_notifications' => $enabled ] );
        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    public static function handleGenerateVapid(): void
    {
        check_admin_referer( 'sh_notify_generate_vapid' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        try {
            WebPushCarrier::generateVapidKeys();
            wp_redirect( add_query_arg( [ 'page' => 'sh-notifications', 'tab' => 'push', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        } catch ( \Throwable $e ) {
            wp_die( esc_html( $e->getMessage() ) );
        }
        exit;
    }

    // =========================================================================
    // SMS TAB
    // =========================================================================

    private static function renderSmsTab( string $nonce, string $ajax_url ): void
    {
        $settings     = \SaltHareket\Notifications\Carriers\SmsSettings::get();
        $sms_enabled  = \SaltHareket\Notifications\NotificationsSettings::getSetting( 'enable_sms_notifications' );
        $providers    = \SaltHareket\Notifications\Carriers\SmsManager::providers();
        $active       = $settings['provider'] ?? 'd7';
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <!-- ── PROVIDER SEÇİMİ ─────────────────────────────────────────── -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header" style="display:flex;align-items:center;gap:12px">
                <h3 style="margin:0">SMS Provider</h3>
                <label class="sh-toggle" title="SMS aktif/pasif">
                    <input type="checkbox" id="sms-enabled" <?php checked( $sms_enabled ); ?> onchange="shSmsToggle(this.checked)">
                    <span class="sh-toggle-slider"></span>
                </label>
                <span style="font-size:12px;color:#9ca3af">Enable SMS notifications</span>
            </div>
            <div id="sh-sms-content" style="<?php echo ! $sms_enabled ? 'display:none' : ''; ?>">
            <div class="sh-card-body">
                <div class="sh-provider-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px">
                <?php foreach ( $providers as $key => $p ) :
                    $caps    = $p['capabilities'];
                    $is_active = ( $key === $active );
                    $otp_ok  = ! empty( $caps['otp'] );
                    $global  = ( $caps['coverage'] ?? '' ) === 'global';
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
            </div><!-- /sh-sms-content -->
        </div>

        <!-- ── PROVIDER AYARLARI ──────────────────────────────────────── -->
        <div id="sh-sms-extra-content" style="<?php echo ! $sms_enabled ? 'display:none' : ''; ?>">
        <?php foreach ( $providers as $key => $p ) :
            $caps   = $p['capabilities'];
            $cfg    = $settings[ $key ] ?? [];
            $hidden = ( $key !== $active ) ? 'style="display:none"' : '';
        ?>
        <div class="sh-card sh-provider-fields" id="sms-fields-<?php echo esc_attr( $key ); ?>" <?php echo $hidden; ?> style="margin-bottom:20px">
            <div class="sh-card-header">
                <h3 style="margin:0"><?php echo esc_html( $p['label'] ); ?> — Credentials</h3>
            </div>
            <div class="sh-card-body">
                <?php if ( $key === 'twilio' ) : ?>
                <div style="padding:10px 14px;background:#fef9c3;border-radius:6px;font-size:12px;color:#854d0e;margin-bottom:16px">
                    ⚠️ <strong>Trial hesap:</strong> "From" alanına Twilio Console'dan aldığın trial numaranı gir (+1415... gibi). Kendi numaranı girme — "To and From cannot be same" hatası alırsın. Verify Service SID için Console → Verify → Services'tan yeni bir servis oluştur.
                </div>
                <?php endif; ?>
                <?php if ( ! empty( $caps['auth_fields'] ) ) : ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <?php foreach ( $caps['auth_fields'] as $field_key => $field ) :
                    $field_val = $cfg[ $field_key ] ?? '';
                    $is_checkbox = ( $field['type'] === 'checkbox' );
                ?>
                    <div class="sh-field" <?php echo $is_checkbox ? 'style="grid-column:1/-1"' : ''; ?>>
                        <?php if ( $is_checkbox ) : ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox"
                                       data-provider="<?php echo esc_attr( $key ); ?>"
                                       data-field="<?php echo esc_attr( $field_key ); ?>"
                                       <?php checked( ! empty( $field_val ) ); ?>
                                       style="width:16px;height:16px">
                                <span style="font-size:13px;font-weight:600"><?php echo esc_html( $field['label'] ); ?></span>
                            </label>
                            <?php if ( ! empty( $field['description'] ) ) : ?>
                                <p style="font-size:11px;color:#9ca3af;margin:4px 0 0 24px"><?php echo esc_html( $field['description'] ); ?></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px"><?php echo esc_html( $field['label'] ); ?></label>
                            <input type="<?php echo esc_attr( $field['type'] ); ?>"
                                   class="sh-input"
                                   data-provider="<?php echo esc_attr( $key ); ?>"
                                   data-field="<?php echo esc_attr( $field_key ); ?>"
                                   value="<?php echo esc_attr( $field_val ); ?>"
                                   placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                                   style="width:100%">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:16px;display:flex;gap:8px;align-items:center">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shSmsSaveSettings()">💾 Save Settings</button>
                    <button type="button" class="sh-btn" onclick="shSmsCheckBalance()" style="background:#f0f0f0">💰 Check Balance</button>
                    <span class="sms-save-status" style="font-size:12px;color:#666"></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ── OTP AYARLARI ──────────────────────────────────────────── -->
        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header">
                <h3 style="margin:0">🔐 OTP Settings</h3>
                <span style="font-size:12px;color:#9ca3af;margin-left:8px">Tüm provider'larda geçerli global OTP ayarları</span>
            </div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                            OTP Geçerlilik Süresi (sn)
                            <span style="font-weight:400;color:#9ca3af">— default: 300</span>
                        </label>
                        <input type="number" id="otp-expiry" class="sh-input"
                               value="<?php echo esc_attr( $settings['otp_expiry'] ?? 300 ); ?>"
                               min="60" max="3600" step="30" style="width:100%">
                        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">60–3600 sn arası. 300 = 5 dakika.</p>
                    </div>
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                            OTP Kod Uzunluğu
                            <span style="font-weight:400;color:#9ca3af">— default: 6</span>
                        </label>
                        <input type="number" id="otp-length" class="sh-input"
                               value="<?php echo esc_attr( $settings['otp_length'] ?? 6 ); ?>"
                               min="4" max="10" style="width:100%">
                        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">4–10 karakter. Netgsm/D7 gibi provider'larda geçerli.</p>
                    </div>
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">
                            Max Yeniden Gönderim
                            <span style="font-weight:400;color:#9ca3af">— default: 5</span>
                        </label>
                        <input type="number" id="otp-max-resend" class="sh-input"
                               value="<?php echo esc_attr( $settings['max_resend'] ?? 5 ); ?>"
                               min="1" max="20" style="width:100%">
                        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">Kullanıcı başına max resend sayısı.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── SMS GÖNDER ─────────────────────────────────────────────── -->
        <div class="sh-card">
            <div class="sh-card-header">
                <h3 style="margin:0">📤 Send SMS</h3>
            </div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Recipients</label>
                        <select id="sms-recipient-type" class="sh-select" onchange="shSmsToggleRecipients()" style="width:100%;margin-bottom:8px">
                            <option value="test">🧪 Test Only (my number)</option>
                            <option value="specific">👤 Specific Users</option>
                            <option value="all">📢 All Users (with phone)</option>
                            <option value="role">🎭 By Role</option>
                            <option value="manual">✏️ Manual Numbers</option>
                        </select>

                        <!-- Test: kendi numaranı gir -->
                        <div id="sms-recipients-test">
                            <input type="text" id="sms-test-number" class="sh-input" placeholder="+905551234567" style="width:100%">
                            <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">E.164 format: +905551234567</p>
                        </div>

                        <!-- Specific users -->
                        <div id="sms-recipients-specific" style="display:none">
                            <input type="text" id="sms-user-search" class="sh-input" placeholder="Search users..." oninput="shSmsSearchUsers(this.value)" style="width:100%;margin-bottom:6px">
                            <div id="sms-user-results" style="max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;display:none"></div>
                            <div id="sms-selected-users" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px"></div>
                        </div>

                        <!-- Role -->
                        <div id="sms-recipients-role" style="display:none">
                            <select id="sms-role" class="sh-select" style="width:100%">
                                <?php foreach ( self::getRoles() as $slug => $name ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Manual -->
                        <div id="sms-recipients-manual" style="display:none">
                            <textarea id="sms-manual-numbers" class="sh-input" rows="3" placeholder="+905551234567&#10;+905559876543" style="width:100%;font-family:monospace"></textarea>
                            <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">Her satıra bir numara, E.164 format</p>
                        </div>

                        <!-- All users info -->
                        <div id="sms-recipients-all" style="display:none">
                            <div style="padding:10px;background:#fef9c3;border-radius:6px;font-size:12px;color:#854d0e">
                                ⚠️ Telefon numarası kayıtlı tüm kullanıcılara gönderilecek.
                            </div>
                        </div>
                    </div>

                    <div class="sh-field">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Message</label>
                        <textarea id="sms-message" class="sh-input" rows="5" placeholder="Mesajınızı yazın..." style="width:100%;resize:vertical"></textarea>
                        <div style="display:flex;justify-content:space-between;margin-top:4px">
                            <span style="font-size:11px;color:#9ca3af">Max 160 karakter (1 SMS)</span>
                            <span id="sms-char-count" style="font-size:11px;color:#9ca3af">0 / 160</span>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:8px;align-items:center">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shSmsSend()">📤 Send SMS</button>
                    <span id="sms-send-status" style="font-size:12px;color:#666"></span>
                </div>

                <div id="sms-send-result" style="margin-top:12px;display:none"></div>
            </div>
        </div>

        </div><!-- /sh-sms-extra-content -->
        </div><!-- .sh-main -->
        </div><!-- .sh-layout -->

        <script>
        var shSmsNonce   = '<?php echo esc_js( $nonce ); ?>';
        var shSmsAjaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
        var shSmsSelectedUsers = [];

        function shSmsToggle(enabled) {
            document.getElementById('sh-sms-content').style.display      = enabled ? '' : 'none';
            document.getElementById('sh-sms-extra-content').style.display = enabled ? '' : 'none';
            fetch(shSmsAjaxUrl + '?action=sh_notify_save_sms_toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'enabled=' + (enabled ? 1 : 0) + '&nonce=' + shSmsNonce
            }).then(r => r.json()).then(function(res) {
                if (res.success) {
                    shShowToast(enabled ? '✅ SMS notifications enabled' : '⛔ SMS notifications disabled');
                } else {
                    shShowToast('❌ ' + (res.data || 'Error'), 'error');
                    // Hata olursa toggle'ı geri al
                    document.getElementById('sms-enabled').checked = !enabled;
                    document.getElementById('sh-sms-content').style.display      = !enabled ? '' : 'none';
                    document.getElementById('sh-sms-extra-content').style.display = !enabled ? '' : 'none';
                }
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
            if (q.length < 2) { document.getElementById('sms-user-results').style.display = 'none'; return; }
            fetch(shSmsAjaxUrl + '?action=sh_sms_search_users&q=' + encodeURIComponent(q) + '&nonce=' + shSmsNonce)
                .then(r => r.json()).then(function(res) {
                    var box = document.getElementById('sms-user-results');
                    if (!res.success || !res.data.length) { box.style.display = 'none'; return; }
                    box.innerHTML = res.data.map(function(u) {
                        return '<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px" onclick="shSmsAddUser(' + u.id + ',\'' + u.name.replace(/'/g,"\\'") + '\')">' + u.name + ' <span style="color:#9ca3af;font-size:11px">' + (u.phone || 'no phone') + '</span></div>';
                    }).join('');
                    box.style.display = '';
                });
        }

        function shSmsAddUser(id, name) {
            if (shSmsSelectedUsers.find(u => u.id === id)) return;
            shSmsSelectedUsers.push({id: id, name: name});
            var box = document.getElementById('sms-selected-users');
            var tag = document.createElement('span');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#e0f2fe;border-radius:12px;font-size:12px';
            tag.innerHTML = name + ' <span style="cursor:pointer;color:#666" onclick="shSmsRemoveUser(' + id + ',this.parentNode)">×</span>';
            box.appendChild(tag);
            document.getElementById('sms-user-results').style.display = 'none';
            document.getElementById('sms-user-search').value = '';
        }

        function shSmsRemoveUser(id, el) {
            shSmsSelectedUsers = shSmsSelectedUsers.filter(u => u.id !== id);
            el.remove();
        }

        // Karakter sayacı
        document.getElementById('sms-message').addEventListener('input', function() {
            var len = this.value.length;
            var counter = document.getElementById('sms-char-count');
            counter.textContent = len + ' / 160';
            counter.style.color = len > 160 ? '#dc2626' : '#9ca3af';
        });

        function shSmsSaveSettings() {
            var provider = document.getElementById('sms-provider').value;
            var enabled  = document.getElementById('sms-enabled').checked;
            var data     = {
                action:      'sh_sms_save_settings',
                nonce:       shSmsNonce,
                provider:    provider,
                enabled:     enabled ? 1 : 0,
                otp_expiry:  document.getElementById('otp-expiry').value,
                otp_length:  document.getElementById('otp-length').value,
                max_resend:  document.getElementById('otp-max-resend').value,
            };

            // TÜM provider'ların field'larını topla (sadece aktifi değil)
            document.querySelectorAll('[data-provider][data-field]').forEach(function(el) {
                var p = el.dataset.provider;
                var f = el.dataset.field;
                if (!data[p]) data[p] = {};
                if (el.type === 'checkbox') {
                    data[p][f] = el.checked ? 1 : 0;
                } else {
                    data[p][f] = el.value;
                }
            });

            // Aktif provider'ın card'ındaki status span'ını bul
            var statusEl = document.querySelector('#sms-fields-' + provider + ' .sms-save-status');
            if (statusEl) statusEl.textContent = 'Saving...';

            fetch(shSmsAjaxUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(shFlattenData(data)) })
                .then(r => r.json()).then(function(res) {
                    if (statusEl) {
                        statusEl.textContent = res.success ? '✅ Saved' : '❌ ' + (res.data || 'Error');
                        statusEl.style.color = res.success ? '#166534' : '#dc2626';
                        setTimeout(() => { statusEl.textContent = ''; }, 3000);
                    }
                });
        }

        function shFlattenData(obj, prefix) {
            var result = {};
            for (var key in obj) {
                var fullKey = prefix ? prefix + '[' + key + ']' : key;
                if (typeof obj[key] === 'object' && obj[key] !== null) {
                    Object.assign(result, shFlattenData(obj[key], fullKey));
                } else {
                    result[fullKey] = obj[key];
                }
            }
            return result;
        }

        function shSmsCheckBalance() {
            var provider = document.getElementById('sms-provider').value;
            var statusEl = document.querySelector('#sms-fields-' + provider + ' .sms-save-status');
            if (statusEl) statusEl.textContent = 'Checking...';
            fetch(shSmsAjaxUrl + '?action=sh_sms_check_balance&nonce=' + shSmsNonce)
                .then(r => r.json()).then(function(res) {
                    if (statusEl) {
                        statusEl.textContent = res.success ? '💰 ' + (res.data.balance || JSON.stringify(res.data)) : '❌ ' + (res.data || 'Error');
                        statusEl.style.color = res.success ? '#166534' : '#dc2626';
                    }
                });
        }

        function shSmsSend() {
            var type    = document.getElementById('sms-recipient-type').value;
            var message = document.getElementById('sms-message').value.trim();
            var status  = document.getElementById('sms-send-status');
            var result  = document.getElementById('sms-send-result');

            if (!message) { status.textContent = '❌ Mesaj boş olamaz'; status.style.color = '#dc2626'; return; }

            var data = { action: 'sh_sms_send', nonce: shSmsNonce, message: message, recipient_type: type };

            if (type === 'test') {
                data.numbers = document.getElementById('sms-test-number').value;
            } else if (type === 'specific') {
                data.user_ids = shSmsSelectedUsers.map(u => u.id).join(',');
            } else if (type === 'role') {
                data.role = document.getElementById('sms-role').value;
            } else if (type === 'manual') {
                data.numbers = document.getElementById('sms-manual-numbers').value;
            }

            status.textContent = '⏳ Sending...';
            status.style.color = '#666';
            result.style.display = 'none';

            fetch(shSmsAjaxUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(data) })
                .then(r => r.json()).then(function(res) {
                    if (res.success) {
                        status.textContent = '✅ Sent to ' + (res.data.sent || 0) + ' recipient(s)';
                        status.style.color = '#166534';
                        if (res.data.errors && res.data.errors.length) {
                            result.innerHTML = '<div style="padding:10px;background:#fee2e2;border-radius:6px;font-size:12px;color:#991b1b">Errors: ' + res.data.errors.join('<br>') + '</div>';
                            result.style.display = '';
                        }
                    } else {
                        status.textContent = '❌ ' + (res.data || 'Send failed');
                        status.style.color = '#dc2626';
                    }
                });
        }
        </script>
        <?php
    }

    // --- SMS AJAX HANDLERS ---

    public static function ajaxSmsSaveSettings(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $data = $_POST;
        unset( $data['action'], $data['nonce'] );

        \SaltHareket\Notifications\Carriers\SmsSettings::save( $data );
        wp_send_json_success( 'Saved' );
    }

    public static function ajaxSmsSend(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $type    = sanitize_key( $_POST['recipient_type'] ?? 'test' );
        $message = sanitize_textarea_field( $_POST['message'] ?? '' );

        if ( empty( $message ) ) {
            wp_send_json_error( 'Message is empty' );
        }

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
                $ids = array_filter( array_map( 'intval', explode( ',', $_POST['user_ids'] ?? '' ) ) );
                foreach ( $ids as $uid ) {
                    $phone = get_user_meta( $uid, 'phone', true );
                    if ( $phone ) $numbers[] = $phone;
                }
                break;

            case 'role':
                $role  = sanitize_key( $_POST['role'] ?? '' );
                $users = get_users( [ 'role' => $role, 'fields' => 'ID', 'number' => 500 ] );
                foreach ( $users as $uid ) {
                    $phone = get_user_meta( $uid, 'phone', true );
                    if ( $phone ) $numbers[] = $phone;
                }
                break;

            case 'all':
                global $wpdb;
                $rows = $wpdb->get_col(
                    "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'phone' AND meta_value != '' LIMIT 1000"
                );
                $numbers = array_values( array_filter( $rows ) );
                break;
        }

        if ( empty( $numbers ) ) {
            wp_send_json_error( 'No recipients found' );
        }

        $numbers = array_unique( $numbers );
        $errors  = [];
        $sent    = 0;

        // Batch gönder (max 50'şer)
        $chunks = array_chunk( $numbers, 50 );
        try {
            $driver = \SaltHareket\Notifications\Carriers\SmsManager::driver();
            foreach ( $chunks as $chunk ) {
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

    public static function ajaxSmsCheckBalance(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        try {
            $result = \SaltHareket\Notifications\Carriers\SmsManager::driver()->checkBalance();
            if ( ! empty( $result['error'] ) ) {
                wp_send_json_error( $result['message'] );
            }
            wp_send_json_success( $result['data'] );
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }

    public static function ajaxSmsSearchUsers(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $q = sanitize_text_field( $_GET['q'] ?? '' );
        if ( strlen( $q ) < 2 ) {
            wp_send_json_success( [] );
        }

        $users = get_users( [
            'search'         => '*' . $q . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number'         => 20,
            'fields'         => [ 'ID', 'display_name', 'user_email' ],
        ] );

        $result = [];
        foreach ( $users as $u ) {
            $phone = get_user_meta( $u->ID, 'phone', true );
            $result[] = [
                'id'    => $u->ID,
                'name'  => $u->display_name . ' (' . $u->user_email . ')',
                'phone' => $phone ?: '',
            ];
        }

        wp_send_json_success( $result );
    }

    // ─── EMAIL TAB ───────────────────────────────────────────────────────────
    private static function renderEmailTab( string $nonce, string $ajax_url ): void
    {
        $settings  = \SaltHareket\Notifications\Carriers\Email\EmailSettings::get();
        $plugin    = \SaltHareket\Notifications\Carriers\Email\EmailSettings::detectWpSmtpPlugin();
        $presets   = \SaltHareket\Notifications\Carriers\Email\EmailSettings::smtpPresets();
        $api_provs = \SaltHareket\Notifications\Carriers\Email\EmailSettings::apiProviders();
        $mode      = $settings['mode']        ?? 'wp';
        $ctype     = $settings['custom_type'] ?? 'smtp';
        $smtp      = $settings['smtp']        ?? [];
        $api       = $settings['api']         ?? [];
        ?>
        <div class="sh-layout">
        <div class="sh-main">

        <?php if ( $plugin['found'] ) : ?>
        <div style="padding:12px 16px;background:#f0f6ff;border:1px solid #c3d9f7;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">ℹ️</span>
            <div>
                <strong><?php echo esc_html( $plugin['name'] ); ?> aktif</strong> —
                "WP/Plugin" seçersen gönderimler onun üzerinden gider.
                Farklı hesap kullanmak istiyorsan "Kendi Ayarlarım"ı seç.
                <a href="<?php echo esc_url( $plugin['settings_url'] ); ?>" target="_blank" style="margin-left:8px"><?php echo esc_html( $plugin['name'] ); ?> Ayarları →</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="sh-card" style="margin-bottom:20px">
            <div class="sh-card-header"><h3 style="margin:0">Gönderim Yöntemi</h3></div>
            <div class="sh-card-body">
                <div style="display:flex;gap:20px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 20px;border:2px solid <?php echo $mode==='wp'?'#2271b1':'#ddd'; ?>;border-radius:8px;background:<?php echo $mode==='wp'?'#f0f6ff':'#fff'; ?>">
                        <input type="radio" name="email_mode" value="wp" <?php checked($mode,'wp'); ?> onchange="shEmailModeChange(this.value)">
                        <div><strong>WP / Plugin</strong><br><span style="font-size:11px;color:#9ca3af">wp_mail() — <?php echo $plugin['found'] ? esc_html($plugin['name']).' üzerinden' : 'PHP mail'; ?></span></div>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 20px;border:2px solid <?php echo $mode==='custom'?'#2271b1':'#ddd'; ?>;border-radius:8px;background:<?php echo $mode==='custom'?'#f0f6ff':'#fff'; ?>">
                        <input type="radio" name="email_mode" value="custom" <?php checked($mode,'custom'); ?> onchange="shEmailModeChange(this.value)">
                        <div><strong>Kendi Ayarlarım</strong><br><span style="font-size:11px;color:#9ca3af">SMTP veya API ile gönder</span></div>
                    </label>
                </div>
            </div>
        </div>

        <div id="email-custom-settings" <?php echo $mode!=='custom'?'style="display:none"':''; ?>>

            <div class="sh-card" style="margin-bottom:20px">
                <div class="sh-card-header"><h3 style="margin:0">Gönderim Tipi</h3></div>
                <div class="sh-card-body" style="display:flex;gap:16px">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="radio" name="email_custom_type" value="smtp" <?php checked($ctype,'smtp'); ?> onchange="shEmailTypeChange(this.value)">
                        <strong>SMTP</strong> <span style="font-size:11px;color:#9ca3af">(Gmail, Outlook, hosting...)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="radio" name="email_custom_type" value="api" <?php checked($ctype,'api'); ?> onchange="shEmailTypeChange(this.value)">
                        <strong>API</strong> <span style="font-size:11px;color:#9ca3af">(SendGrid, Mailgun, Brevo...)</span>
                    </label>
                </div>
            </div>

            <div id="email-smtp-settings" class="sh-card" <?php echo $ctype!=='smtp'?'style="display:none"':''; ?> style="margin-bottom:20px">
                <div class="sh-card-header"><h3 style="margin:0">SMTP Ayarları</h3></div>
                <div class="sh-card-body">
                    <div style="margin-bottom:16px">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Preset (Hızlı Ayar)</label>
                        <select id="smtp-preset" class="sh-select" onchange="shSmtpPresetChange(this.value)" style="width:300px">
                            <?php foreach ( $presets as $key => $p ) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($smtp['preset']??'custom',$key); ?>><?php echo esc_html($p['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="smtp-preset-note" style="font-size:11px;color:#854d0e;margin-top:6px;padding:6px 10px;background:#fef9c3;border-radius:4px;display:none"></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">SMTP Host</label>
                            <input type="text" id="smtp-host" class="sh-input" value="<?php echo esc_attr($smtp['host']??''); ?>" placeholder="smtp.gmail.com" style="width:100%">
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Port</label>
                            <input type="number" id="smtp-port" class="sh-input" value="<?php echo esc_attr($smtp['port']??587); ?>" style="width:100%">
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Encryption</label>
                            <select id="smtp-encryption" class="sh-select" style="width:100%">
                                <option value="tls" <?php selected($smtp['encryption']??'tls','tls'); ?>>TLS (önerilen)</option>
                                <option value="ssl" <?php selected($smtp['encryption']??'tls','ssl'); ?>>SSL</option>
                                <option value="none" <?php selected($smtp['encryption']??'tls','none'); ?>>None</option>
                            </select>
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Username</label>
                            <input type="text" id="smtp-username" class="sh-input" value="<?php echo esc_attr($smtp['username']??''); ?>" placeholder="you@gmail.com" style="width:100%">
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Password / App Password</label>
                            <input type="password" id="smtp-password" class="sh-input" value="<?php echo esc_attr($smtp['password']??''); ?>" placeholder="••••••••" style="width:100%">
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">From Name</label>
                            <input type="text" id="smtp-from-name" class="sh-input" value="<?php echo esc_attr($smtp['from_name']??get_bloginfo('name')); ?>" style="width:100%">
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">From Email</label>
                            <input type="email" id="smtp-from-email" class="sh-input" value="<?php echo esc_attr($smtp['from_email']??get_option('admin_email')); ?>" style="width:100%">
                        </div>
                    </div>
                </div>
            </div>

            <div id="email-api-settings" class="sh-card" <?php echo $ctype!=='api'?'style="display:none"':''; ?> style="margin-bottom:20px">
                <div class="sh-card-header"><h3 style="margin:0">API Ayarları</h3></div>
                <div class="sh-card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:20px">
                    <?php foreach ( $api_provs as $key => $p ) :
                        $is_active = ( ($api['provider']??'sendgrid') === $key );
                    ?>
                        <div onclick="shEmailApiProviderChange('<?php echo esc_js($key); ?>')"
                             style="border:2px solid <?php echo $is_active?'#2271b1':'#ddd'; ?>;border-radius:8px;padding:12px;cursor:pointer;background:<?php echo $is_active?'#f0f6ff':'#fff'; ?>">
                            <div style="font-weight:600;font-size:13px"><?php echo esc_html($p['label']); ?></div>
                            <div style="font-size:10px;color:#166534;margin-top:4px">✅ <?php echo esc_html($p['free']); ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="api-provider" value="<?php echo esc_attr($api['provider']??'sendgrid'); ?>">
                    <?php foreach ( $api_provs as $key => $p ) :
                        $hidden = ( ($api['provider']??'sendgrid') !== $key ) ? 'style="display:none"' : '';
                    ?>
                    <div id="api-fields-<?php echo esc_attr($key); ?>" <?php echo $hidden; ?>>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <?php foreach ( $p['fields'] as $fkey => $field ) :
                            $fval = $api[$fkey] ?? '';
                        ?>
                            <div class="sh-field">
                                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px"><?php echo esc_html($field['label']); ?></label>
                                <?php if ( $field['type'] === 'select' ) : ?>
                                    <select data-api-provider="<?php echo esc_attr($key); ?>" data-api-field="<?php echo esc_attr($fkey); ?>" class="sh-select" style="width:100%">
                                        <?php foreach ( $field['options'] as $oval => $olabel ) : ?>
                                            <option value="<?php echo esc_attr($oval); ?>" <?php selected($fval,$oval); ?>><?php echo esc_html($olabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else : ?>
                                    <input type="<?php echo esc_attr($field['type']); ?>"
                                           data-api-provider="<?php echo esc_attr($key); ?>"
                                           data-api-field="<?php echo esc_attr($fkey); ?>"
                                           class="sh-input"
                                           value="<?php echo esc_attr($fval); ?>"
                                           placeholder="<?php echo esc_attr($field['placeholder']??''); ?>"
                                           style="width:100%">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php if ( !empty($p['notes']) ) : ?>
                        <div style="margin-top:12px;padding:8px 12px;background:#f0f6ff;border-radius:6px;font-size:11px;color:#1e40af">
                            <?php echo implode('<br>', array_map('esc_html', $p['notes'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center;margin-bottom:24px">
            <button type="button" class="sh-btn sh-btn-primary" onclick="shEmailSaveSettings()">💾 Save Settings</button>
            <button type="button" class="sh-btn" onclick="shEmailTest()" style="background:#f0f0f0">📤 Test Email</button>
            <span id="email-save-status" style="font-size:12px;color:#666"></span>
        </div>

        <div class="sh-card">
            <div class="sh-card-header"><h3 style="margin:0">📤 Send Email</h3></div>
            <div class="sh-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Recipients</label>
                        <select id="email-recipient-type" class="sh-select" onchange="shEmailToggleRecipients()" style="width:100%;margin-bottom:8px">
                            <option value="test">🧪 Test Only (manual email)</option>
                            <option value="specific">👤 Specific Users</option>
                            <option value="role">🎭 By Role</option>
                            <option value="all">📢 All Users</option>
                            <option value="manual">✏️ Manual Emails</option>
                        </select>
                        <div id="email-recipients-test">
                            <input type="email" id="email-test-address" class="sh-input" placeholder="test@example.com" style="width:100%">
                        </div>
                        <div id="email-recipients-specific" style="display:none">
                            <input type="text" id="email-user-search" class="sh-input" placeholder="Search users..." oninput="shEmailSearchUsers(this.value)" style="width:100%;margin-bottom:6px">
                            <div id="email-user-results" style="max-height:150px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;display:none"></div>
                            <div id="email-selected-users" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px"></div>
                        </div>
                        <div id="email-recipients-role" style="display:none">
                            <select id="email-role" class="sh-select" style="width:100%">
                                <?php foreach ( self::getRoles() as $slug => $name ) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="email-recipients-manual" style="display:none">
                            <textarea id="email-manual-addresses" class="sh-input" rows="3" placeholder="user1@example.com&#10;user2@example.com" style="width:100%;font-family:monospace"></textarea>
                        </div>
                        <div id="email-recipients-all" style="display:none">
                            <div style="padding:10px;background:#fef9c3;border-radius:6px;font-size:12px;color:#854d0e">⚠️ Email adresi kayıtlı tüm kullanıcılara gönderilecek.</div>
                        </div>
                    </div>
                    <div>
                        <div class="sh-field" style="margin-bottom:12px">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Subject</label>
                            <input type="text" id="email-subject" class="sh-input" placeholder="Email konusu" style="width:100%">
                        </div>
                        <div class="sh-field">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Message (HTML destekli)</label>
                            <textarea id="email-body" class="sh-input" rows="5" placeholder="&lt;p>Mesajınız...</p>" style="width:100%;resize:vertical"></textarea>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button type="button" class="sh-btn sh-btn-primary" onclick="shEmailSend()">📤 Send Email</button>
                    <span id="email-send-status" style="font-size:12px;color:#666"></span>
                </div>
                <div id="email-send-result" style="margin-top:12px;display:none"></div>
            </div>
        </div>

        </div></div>
        <?php
        $presets_json = wp_json_encode( $presets );
        ?>
        <script>
        var shEmailNonce='<?php echo esc_js($nonce);?>';var shEmailAjaxUrl='<?php echo esc_js($ajax_url);?>';var shEmailPresets=<?php echo $presets_json;?>;var shEmailSelectedUsers=[];
        function shEmailModeChange(v){document.querySelectorAll('[name="email_mode"]').forEach(function(r){var l=r.closest('label');l.style.borderColor=r.value===v?'#2271b1':'#ddd';l.style.background=r.value===v?'#f0f6ff':'#fff';});document.getElementById('email-custom-settings').style.display=v==='custom'?'':'none';}
        function shEmailTypeChange(v){document.getElementById('email-smtp-settings').style.display=v==='smtp'?'':'none';document.getElementById('email-api-settings').style.display=v==='api'?'':'none';}
        function shSmtpPresetChange(k){var p=shEmailPresets[k];if(!p)return;if(p.host)document.getElementById('smtp-host').value=p.host;if(p.port)document.getElementById('smtp-port').value=p.port;if(p.encryption)document.getElementById('smtp-encryption').value=p.encryption;var n=document.getElementById('smtp-preset-note');if(p.note){n.textContent=p.note;n.style.display='';}else{n.style.display='none';}}
        function shEmailApiProviderChange(key) {
            document.getElementById('api-provider').value = key;
            // Field'ları göster/gizle
            document.querySelectorAll('[id^="api-fields-"]').forEach(function(el) {
                el.style.display = el.id === 'api-fields-' + key ? '' : 'none';
            });
            // Box'ların active görünümünü güncelle
            document.querySelectorAll('[onclick^="shEmailApiProviderChange"]').forEach(function(box) {
                var boxKey = box.getAttribute('onclick').match(/'([^']+)'/)?.[1];
                box.style.borderColor = boxKey === key ? '#2271b1' : '#ddd';
                box.style.background  = boxKey === key ? '#f0f6ff' : '#fff';
            });
        }

        function shEmailToggleRecipients(){var t=document.getElementById('email-recipient-type').value;['test','specific','role','manual','all'].forEach(function(x){var el=document.getElementById('email-recipients-'+x);if(el)el.style.display=x===t?'':'none';});}
        function shEmailSearchUsers(q){if(q.length<2){document.getElementById('email-user-results').style.display='none';return;}fetch(shEmailAjaxUrl+'?action=sh_sms_search_users&q='+encodeURIComponent(q)+'&nonce='+shEmailNonce).then(r=>r.json()).then(function(res){var box=document.getElementById('email-user-results');if(!res.success||!res.data.length){box.style.display='none';return;}box.innerHTML=res.data.map(function(u){return '<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px" onclick="shEmailAddUser('+u.id+',\''+u.name.replace(/\'/g,"\\'")+'\')">'+u.name+'</div>';}).join('');box.style.display='';});}
        function shEmailAddUser(id,name){if(shEmailSelectedUsers.find(u=>u.id===id))return;shEmailSelectedUsers.push({id:id,name:name});var box=document.getElementById('email-selected-users');var tag=document.createElement('span');tag.style.cssText='display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#e0f2fe;border-radius:12px;font-size:12px';tag.innerHTML=name+' <span style="cursor:pointer;color:#666" onclick="shEmailRemoveUser('+id+',this.parentNode)">×</span>';box.appendChild(tag);document.getElementById('email-user-results').style.display='none';document.getElementById('email-user-search').value='';}
        function shEmailRemoveUser(id,el){shEmailSelectedUsers=shEmailSelectedUsers.filter(u=>u.id!==id);el.remove();}
        function shEmailSaveSettings(){var mode=document.querySelector('[name="email_mode"]:checked')?.value||'wp';var ctype=document.querySelector('[name="email_custom_type"]:checked')?.value||'smtp';var data={action:'sh_email_save_settings',nonce:shEmailNonce,mode:mode,custom_type:ctype,smtp:{preset:document.getElementById('smtp-preset')?.value||'custom',host:document.getElementById('smtp-host')?.value||'',port:document.getElementById('smtp-port')?.value||587,encryption:document.getElementById('smtp-encryption')?.value||'tls',username:document.getElementById('smtp-username')?.value||'',password:document.getElementById('smtp-password')?.value||'',from_name:document.getElementById('smtp-from-name')?.value||'',from_email:document.getElementById('smtp-from-email')?.value||''},api:{provider:document.getElementById('api-provider')?.value||'sendgrid'}};document.querySelectorAll('[data-api-provider][data-api-field]').forEach(function(el){data.api[el.dataset.apiField]=el.value;});var status=document.getElementById('email-save-status');status.textContent='Saving...';fetch(shEmailAjaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(shFlattenData(data))}).then(r=>r.json()).then(function(res){status.textContent=res.success?'✅ Saved':'❌ '+(res.data||'Error');status.style.color=res.success?'#166534':'#dc2626';setTimeout(()=>{status.textContent='';},3000);});}
        function shEmailTest(){var status=document.getElementById('email-save-status');status.textContent='⏳ Sending test...';fetch(shEmailAjaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'sh_email_test',nonce:shEmailNonce})}).then(r=>r.json()).then(function(res){status.textContent=res.success?'✅ Test email sent':'❌ '+(res.data||'Error');status.style.color=res.success?'#166534':'#dc2626';});}
        function shEmailSend(){var type=document.getElementById('email-recipient-type').value;var subject=document.getElementById('email-subject').value.trim();var body=document.getElementById('email-body').value.trim();var status=document.getElementById('email-send-status');var result=document.getElementById('email-send-result');if(!subject||!body){status.textContent='❌ Subject ve message boş olamaz';status.style.color='#dc2626';return;}var data={action:'sh_email_send',nonce:shEmailNonce,subject:subject,body:body,recipient_type:type};if(type==='test')data.email=document.getElementById('email-test-address').value;else if(type==='specific')data.user_ids=shEmailSelectedUsers.map(u=>u.id).join(',');else if(type==='role')data.role=document.getElementById('email-role').value;else if(type==='manual')data.emails=document.getElementById('email-manual-addresses').value;status.textContent='⏳ Sending...';status.style.color='#666';result.style.display='none';fetch(shEmailAjaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}).then(r=>r.json()).then(function(res){if(res.success){status.textContent='✅ Sent to '+(res.data.sent||0)+' recipient(s)';status.style.color='#166534';if(res.data.errors&&res.data.errors.length){result.innerHTML='<div style="padding:10px;background:#fee2e2;border-radius:6px;font-size:12px;color:#991b1b">Errors:<br>'+res.data.errors.join('<br>')+'</div>';result.style.display='';}}else{status.textContent='❌ '+(res.data||'Send failed');status.style.color='#dc2626';}});}

        function shFlattenData(obj, prefix) {
            var result = {};
            for (var key in obj) {
                var fullKey = prefix ? prefix + '[' + key + ']' : key;
                if (typeof obj[key] === 'object' && obj[key] !== null) {
                    Object.assign(result, shFlattenData(obj[key], fullKey));
                } else {
                    result[fullKey] = obj[key];
                }
            }
            return result;
        }


        </script>
        <?php
    }

    public static function ajaxEmailSaveSettings(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }
        $data = $_POST;
        unset( $data['action'], $data['nonce'] );
        \SaltHareket\Notifications\Carriers\Email\EmailSettings::save( $data );
        wp_send_json_success( 'Saved' );
    }

    public static function ajaxEmailTest(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }
        $to     = get_option( 'admin_email' );
        $result = \SaltHareket\Notifications\Carriers\Email\EmailManager::send(
            $to,
            '[Test] ' . get_bloginfo( 'name' ) . ' Email Test',
            '<p>Bu bir test emailidir. Gönderim ayarlarınız çalışıyor.</p><p>Zaman: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC</p>',
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );
        if ( ! empty( $result['error'] ) ) { wp_send_json_error( $result['message'] ); }
        wp_send_json_success( 'Test email sent to ' . $to );
    }

    public static function ajaxEmailSend(): void
    {
        check_ajax_referer( 'sh_notify_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }

        $type    = sanitize_key( $_POST['recipient_type'] ?? 'test' );
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $body    = wp_kses_post( $_POST['body'] ?? '' );

        if ( empty( $subject ) || empty( $body ) ) { wp_send_json_error( 'Subject veya body boş' ); }

        $emails = [];
        switch ( $type ) {
            case 'test':
                $e = sanitize_email( $_POST['email'] ?? '' );
                if ( $e ) $emails[] = $e;
                break;
            case 'manual':
                foreach ( preg_split( '/[\r\n,]+/', $_POST['emails'] ?? '' ) as $e ) {
                    $e = sanitize_email( trim( $e ) );
                    if ( $e ) $emails[] = $e;
                }
                break;
            case 'specific':
                $ids = array_filter( array_map( 'intval', explode( ',', $_POST['user_ids'] ?? '' ) ) );
                foreach ( $ids as $uid ) {
                    $u = get_userdata( $uid );
                    if ( $u && $u->user_email ) $emails[] = $u->user_email;
                }
                break;
            case 'role':
                $role  = sanitize_key( $_POST['role'] ?? '' );
                $users = get_users( [ 'role' => $role, 'fields' => 'ID', 'number' => 500 ] );
                foreach ( $users as $uid ) {
                    $u = get_userdata( $uid );
                    if ( $u && $u->user_email ) $emails[] = $u->user_email;
                }
                break;
            case 'all':
                $users = get_users( [ 'fields' => [ 'ID', 'user_email' ], 'number' => 1000 ] );
                foreach ( $users as $u ) { if ( $u->user_email ) $emails[] = $u->user_email; }
                break;
        }

        $emails = array_unique( array_filter( $emails ) );

        if ( empty( $emails ) ) {
            wp_send_json_error( 'Alıcı bulunamadı' );
        }

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent    = 0;
        $errors  = [];

        foreach ( $emails as $to ) {
            $result = \SaltHareket\Notifications\Carriers\Email\EmailManager::send(
                $to,
                $subject,
                $body,
                $headers
            );
            if ( ! empty( $result['error'] ) ) {
                $errors[] = $to . ': ' . $result['message'];
            } else {
                $sent++;
            }
        }

        wp_send_json_success( [ 'sent' => $sent, 'errors' => $errors ] );
    }
}