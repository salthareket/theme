<?php

use SaltHareket\Theme;

//namespace YoBro\App;
use YoBro\App\Message;
use YoBro\App\Attachment;

class TurboApi {

    private $prefix = 'api';

    function __construct() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_api_request'], 1);
        
        // Rewrite rule'ları aktif etmek için (Sadece bir kez gerekir)
        register_activation_hook(__FILE__, [$this, 'refresh_rules']);
    }

    public function refresh_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function add_query_vars($vars) {
        $vars[] = 'api_method';
        return $vars;
    }

    public function add_rewrite_rules() {
        // site.com/api/search_stores/ yapısını yakalar
        add_rewrite_rule(
            '^' . $this->prefix . '/([^/]+)/?$',
            'index.php?api_method=$matches[1]',
            'top'
        );
    }

    public function handle_api_request() {
        $method = get_query_var('api_method');
        if (!$method) return;

        // 1. IŞIK HIZI AYARLARI
        if (!defined('DOING_AJAX')) define('DOING_AJAX', true);
        
        // Gereksiz headerları temizle ve JSON bildir
        @ini_set('display_errors', 0);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: ' . get_site_url());
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $this->process($method);
        exit;
    }

    private function process($method) {
        // 2. FETCH BODY OKUMA (Modern JSON Body)
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true) ?: $_REQUEST;

        // Değişkenleri senin methods/index.php yapına hazırla
        $id       = isset($data["id"]) ? absint($data["id"]) : 0;
        $keyword  = isset($data["keyword"]) ? sanitize_text_field($data["keyword"]) : "";
        $vars     = isset($data["vars"]) ? $data["vars"] : $data;
        $template = isset($vars["template"]) ? sanitize_text_field($vars["template"]) : "";
        $lang     = isset($data["lang"]) ? $data["lang"] : ml_get_current_language();

        // 3. GÜVENLİK (NONCE)
        // Fetch ile gönderdiğin X-WP-Nonce header'ını kontrol eder
        $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? ($data['_wpnonce'] ?? '');
        $public_methods = ['site_config', 'message_upload'];
        
        if (!in_array($method, $public_methods) && !wp_verify_nonce($nonce, 'ajax')) {
            $this->respond_error('Security Check Failed');
        }

        // 4. MİRAS MANTIK (methods/index.php)
        $response = ["error" => false, "message" => "", "data" => "", "html" => ""];

        // include öncesi değişkenleri scope'a alıyoruz
        if (defined('THEME_INCLUDES_PATH')) {
            include_once THEME_INCLUDES_PATH . "methods/index.php";
        }

        // 5. TIMBER & MINIFY (Opsiyonel)
        if (!empty($template) && isset($templates) && class_exists('Timber')) {
            $context["ajax_call"] = true;
            $context["ajax_method"] = $method;
            $html = Timber::compile($templates, $context);
            $response["html"] = $this->minify_output($html);
            
            // Pagination dataları varsa ekle
            foreach (['page', 'page_count', 'post_count'] as $key) {
                if (isset($$key)) $response[$key] = $$key;
            }
        }

        echo json_encode($response);
    }

    private function respond_error($msg) {
        echo json_encode(["error" => true, "message" => $msg]);
        exit;
    }

    private function minify_output($html) {
        return preg_replace(['/\s+/s', '//s'], [' ', ''], $html);
    }
}
new TurboApi();