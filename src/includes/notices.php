<?php

/**
 * Admin Notice System
 * * Provides a reliable way to display admin notices using Transients API
 * to avoid session-related "headers already sent" errors.
 */
class AdminNotice {

    private $transient_prefix = 'bric_notices_user_';

    public function __construct() {
        // WordPress kancalarını (hooks) bağlayalım
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Yeni bir bildirim ekler.
     * * @param string $text Bildirim metni
     * @param string $type Bildirim türü (success, error, warning, info)
     */
    public function add_notice($text = '', $type = 'success') {
        $user_id = get_current_user_id();
        $key = $this->transient_prefix . $user_id;

        // Mevcut bildirimleri güvenli bir şekilde çek
        $notices = get_transient($key);
        if ( ! is_array($notices) ) {
            $notices = [];
        }

        // Yeni bildirimi listeye ekle
        $notices[] = [
            'type' => esc_attr($type),
            'text' => wp_kses_post($text), // HTML içeriğine güvenli izin ver
        ];

        // 60 saniye boyunca sakla (Bir sonraki sayfa yüklemesi için yeterli)
        set_transient($key, $notices, 60);
    }

    /**
     * Saklanan bildirimleri ekrana basar ve ardından temizler.
     */
    public function render_notices() {
        $user_id = get_current_user_id();
        $key = $this->transient_prefix . $user_id;
        
        $notices = get_transient($key);

        if ( $notices && is_array($notices) ) {
            foreach ( $notices as $notice ) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    $notice['type'],
                    $notice['text']
                );
            }
            // Bildirimler gösterildi, veriyi temizle
            delete_transient($key);
        }
    }
}
global $bric_notice_manager;
$bric_notice_manager = new AdminNotice();

if ( ! function_exists('add_admin_notice') ) {
    function add_admin_notice($text = '', $type = 'success') {
        global $bric_notice_manager;
        if ( $bric_notice_manager ) {
            $bric_notice_manager->add_notice($text, $type);
        }
    }
}

/*
class adminNotice {

    public function __construct() {
        add_action('admin_init', [$this, 'start_session']);
        add_action('admin_notices', [$this, 'display_notices']);
        add_action('shutdown', [$this, 'end_session']); 
    }

    public function add_notice($text = '', $type = 'success') {
        if (!isset($_SESSION['bric_notices'])) {
            $_SESSION['bric_notices'] = [];
        }
        $_SESSION['bric_notices'][] = [
            'type' => $type,
            'text' => $text,
        ];
    }

    public function display_notices() {
        if (isset($_SESSION['bric_notices']) && !empty($_SESSION['bric_notices'])) {
            if (is_array($_SESSION['bric_notices'])) {
                foreach ($_SESSION['bric_notices'] as $notice) {
                    $this->print_notice($notice); // Notice'ı parametre olarak gönderin
                }
            }
            unset($_SESSION['bric_notices']);
        }
        $this->end_session(); // Oturum verilerini yaz ve oturumu kapat
    }

    private function print_notice($notice) { // Notice'ı parametre olarak alın
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $notice['type'], $notice['text']);
    }

    public function start_session() {
        if (session_status() === PHP_SESSION_NONE){// && !headers_sent()) { // session_start() kullanmadan önce kontrol edin
            session_start();
        }
    }

    public function end_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close(); // Oturum verilerini yaz ve oturumu kapat
        }
    }
}

global $adminNotice;
$adminNotice = new adminNotice();

function add_admin_notice($text = '', $type = 'success') {
    global $adminNotice;
    $adminNotice->add_notice($text, $type);
}
*/