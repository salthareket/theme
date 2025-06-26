<?php
/**
 * Plugin Name: Local IP Fix for Mobile Devices (Pro)
 * Description: Replaces localhost with local IP for mobile preview on same network. Handles Timber, ACF, menus, Polylang, scripts, etc.
 * Author: Tolga Koçak
 */

if (
    !isset($_SERVER['REMOTE_ADDR']) ||
    strpos($_SERVER['REMOTE_ADDR'], '192.168.') !== 0 ||
    !isset($_SERVER['HTTP_HOST'])
) return;

$host_raw = $_SERVER['HTTP_HOST'];
$host_clean = preg_replace('/:\d+$/', '', $host_raw);
if (!$host_clean || $host_clean === 'localhost') return;

$schema = is_ssl() ? 'https' : 'http';
$full_host = "$schema://$host_clean";
$replaces = ['http://localhost', 'https://localhost', 'localhost'];

function lip_replace_url($value) {
    global $replaces, $full_host;
    if (!is_string($value)) return $value;
    foreach ($replaces as $search) {
        if (strpos($value, $search) !== false) {
            return str_replace($search, $full_host, $value);
        }
    }
    return $value;
}

function lip_recursive_replace(&$data) {
    if (!is_array($data)) return;
    array_walk_recursive($data, function (&$v) {
        $v = lip_replace_url($v);
    });
}

// Genel URL filtreleri
$url_filters = [
    'home_url', 'site_url', 'content_url', 'plugins_url', 'includes_url',
    'stylesheet_directory_uri', 'template_directory_uri',
    'wp_get_attachment_url', 'rest_url', 'admin_url',
    'option_home', 'option_siteurl',
    'script_loader_src', 'style_loader_src'
];
foreach ($url_filters as $filter) {
    add_filter($filter, 'lip_replace_url', 99);
}

// Timber context
add_filter('timber/context', function ($context) {
    lip_recursive_replace($context);
    if (isset($context['site']->url)) {
        $context['site']->url = lip_replace_url($context['site']->url);
    }
    return $context;
}, 99);

// Polylang URL düzeltme
if (function_exists('pll_home_url')) {
    add_filter('pll_home_url', function ($url) {
        return lip_replace_url($url);
    }, 99);
}

// Menü linkleri
add_filter('wp_get_nav_menu_items', function ($items) {
    if (!is_array($items)) return $items;
    foreach ($items as $item) {
        if (isset($item->url)) {
            $item->url = lip_replace_url($item->url);
        }
    }
    return $items;
}, 99);

add_filter('wp_nav_menu', function ($html) {
    return lip_replace_url($html);
}, 99);

// ACF field value
add_filter('acf/load_value', function ($value) {
    if (is_string($value)) {
        return lip_replace_url($value);
    } elseif (is_array($value)) {
        lip_recursive_replace($value);
    }
    return $value;
}, 99);

// Canonical yönlendirme kapalı
add_filter('redirect_canonical', '__return_false', 99);
