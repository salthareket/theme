<?php

namespace SaltHareket\Theme;

use MatthiasMullie\Minify\JS as JSMinifier;

/**
 * AssetPacker - CSS ve JS dosyalarını birleştirir, minify eder ve akıllıca cache'ler.
 * Mükerrer bundle oluşumunu engellemek için dosya listesini stabilize eder.
 */
class AssetPacker {
    private $files = [];
    private $type;
    private $output_name;
    private $cache_dir;
    private $cache_url;

    /**
     * @param array  $files       Path listesi, Handle listesi veya [] (Otomatik Tema Ayıklama)
     * @param string $type        'css' veya 'js'
     * @param string $output_name Dosya adı ön eki (Örn: 'main-bundle')
     */
    public function __construct(array $files = [], string $type = 'css', string $output_name = 'bundle') {
        $this->type = strtolower($type);
        $this->output_name = $output_name;
        
        $this->cache_dir = STATIC_PATH . "cache/";
        $this->cache_url = STATIC_URL . "cache/";

        // 1. Dosyaları Topla
        if (empty($files)) {
            $this->files = $this->collect_wp_assets([], true);
        } elseif (strpos($files[0], '/') === false) {
            $this->files = $this->collect_wp_assets($files, false);
        } else {
            $this->files = $files;
        }

        // 2. Mükerrerliği Engelle: CSS için sırala, JS için sırayı koru (dependency chain)
        $this->files = array_values(array_unique($this->files));
        if ($this->type === 'css') {
            sort($this->files);
        }

        if (!is_dir($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    /**
     * WordPress Registry içinden sadece ilgili dosyaları çeker.
     */
    private function collect_wp_assets(array $filter_handles = [], bool $only_theme_assets = false) {
        global $wp_styles, $wp_scripts;
        $paths = [];
        $wp_registry = ($this->type === 'css') ? $wp_styles : $wp_scripts;

        if ($wp_registry && isset($wp_registry->registered)) {
            $theme_folder = get_template(); 
            $loop_target = !empty($filter_handles) ? $filter_handles : array_keys($wp_registry->registered);

            foreach ($loop_target as $handle) {
                if (isset($wp_registry->registered[$handle])) {
                    $obj = $wp_registry->registered[$handle];
                    if (!$obj->src) continue;

                    $url = $obj->src;

                    // Otomatik Ayıklama: Sadece tema klasöründekileri al
                    if ($only_theme_assets && strpos($url, $theme_folder) === false) {
                        continue;
                    }

                    if (!preg_match('/^(https?:)?\/\//', $url)) {
                        $url = site_url($url);
                    }

                    $path = $this->url_to_path($url);
                    if ($path && file_exists($path)) {
                        $paths[] = $path;
                    }
                }
            }
        }
        return array_unique($paths);
    }

    /**
     * URL'yi sunucu dosya yoluna çevirir (subfolder, protocol farkı vs. tolere eder)
     */
    private function url_to_path($url) {
        // Protocol-relative URL'leri normalize et
        $url = preg_replace('/^\/\//', 'https://', $url);
        
        $site_url = site_url('/');
        $parsed_site = parse_url($site_url);
        $parsed_url  = parse_url($url);

        // Aynı host değilse (CDN vs.) dönüştüremeyiz
        if (isset($parsed_url['host']) && isset($parsed_site['host']) && $parsed_url['host'] !== $parsed_site['host']) {
            return false;
        }

        // Path kısmından site path'ini çıkar
        $site_path = rtrim($parsed_site['path'] ?? '', '/');
        $url_path  = $parsed_url['path'] ?? '';

        if ($site_path && strpos($url_path, $site_path) === 0) {
            $url_path = substr($url_path, strlen($site_path));
        }

        $path = rtrim(ABSPATH, '/') . $url_path;
        return file_exists($path) ? $path : false;
    }

    /**
     * Dosya tarihlerinden eşsiz bir HASH üretir
     */
    private function generate_hash() {
        $data = "";
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                $data .= $file . filemtime($file);
            }
        }
        return substr(md5($data), 0, 12);
    }

    /**
     * Birleşmiş dosyanın URL'ini verir. Dosya yoksa oluşturur.
     */
    public function get_url() {
        if (empty($this->files)) return "";

        $combined_hash = $this->generate_hash();
        $final_filename = $this->output_name . "-" . $combined_hash . "." . $this->type;
        $file_path = $this->cache_dir . $final_filename;
        $file_url = $this->cache_url . $final_filename;

        if (file_exists($file_path)) {
            return $file_url;
        }

        return $this->pack($file_path) ? $file_url : "";
    }

    /**
     * Birleştirme ve Minify işlemini yapar.
     */
    private function pack($save_path) {
        if (empty($this->files)) return false;

        $header = "/* Generated by AssetPacker | Files: " . count($this->files) . " | Date: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;

        $success = false;

        if ($this->type === 'css') {
            $merger = new \SaltHareket\Theme\MergeCSS($this->files, $save_path, true, false);
            $merger->run();
            $success = file_exists($save_path);
        } else {
            $minifier = new JSMinifier();
            foreach ($this->files as $file) {
                if (file_exists($file)) {
                    $minifier->add($file);
                }
            }
            $content = $header . $minifier->minify();
            $success = file_put_contents($save_path, $content) !== false;
        }

        // Eski cache dosyalarını temizle — aynı output_name prefix'li eski hash'leri sil
        if ($success) {
            $pattern = $this->cache_dir . $this->output_name . "-*." . $this->type;
            foreach (glob($pattern) ?: [] as $old_file) {
                if ($old_file !== $save_path) {
                    @unlink($old_file);
                }
            }
        }

        return $success;
    }
}