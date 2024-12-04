<?php
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
        if (session_status() === PHP_SESSION_NONE) { // session_start() kullanmadan önce kontrol edin
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