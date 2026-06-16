<?php
/**
 * Admin Save Handler
 * 
 * @package SaltHareket\Theme\ThemeStylesNew
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Save theme styles
add_action('wp_ajax_theme_styles_new_save', 'theme_styles_new_save_ajax');
function theme_styles_new_save_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];
    
    if (empty($data)) {
        wp_send_json_error(['message' => 'No data provided']);
    }
    
    $instance = Theme_Styles_New::init();
    $result = $instance->save_data($data, get_option('theme_styles_new_active_preset', ''));
    
    if ($result) {
        wp_send_json_success([
            'message' => 'Saved successfully',
            'activePreset' => get_option('theme_styles_new_active_preset', '')
        ]);
    } else {
        wp_send_json_error(['message' => 'Save failed']);
    }
}

// Save preset
add_action('wp_ajax_theme_styles_new_save_preset', 'theme_styles_new_save_preset_ajax');
function theme_styles_new_save_preset_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];
    
    if (empty($name) || empty($data)) {
        wp_send_json_error(['message' => 'Name and data required']);
    }
    
    $result = Theme_Styles_Preset_Manager::save($name, $data);
    
    if ($result) {
        wp_send_json_success([
            'message' => 'Preset saved',
            'presets' => Theme_Styles_Preset_Manager::get_all()
        ]);
    } else {
        wp_send_json_error(['message' => 'Preset save failed']);
    }
}

// Load preset
add_action('wp_ajax_theme_styles_new_load_preset', 'theme_styles_new_load_preset_ajax');
function theme_styles_new_load_preset_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    
    if (empty($name)) {
        wp_send_json_error(['message' => 'Name required']);
    }
    
    $data = Theme_Styles_Preset_Manager::load($name);
    
    if ($data) {
        // Aktif preset olarak kaydet
        $instance = Theme_Styles_New::init();
        $instance->save_data($data, $name);
        wp_send_json_success(['data' => $data, 'activePreset' => $name]);
    } else {
        wp_send_json_error(['message' => 'Preset not found']);
    }
}

// Delete preset
add_action('wp_ajax_theme_styles_new_delete_preset', 'theme_styles_new_delete_preset_ajax');
function theme_styles_new_delete_preset_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    
    if (empty($name)) {
        wp_send_json_error(['message' => 'Name required']);
    }
    
    $result = Theme_Styles_Preset_Manager::delete($name);
    
    if ($result) {
        wp_send_json_success([
            'message' => 'Preset deleted',
            'presets' => Theme_Styles_Preset_Manager::get_all()
        ]);
    } else {
        wp_send_json_error(['message' => 'Preset delete failed']);
    }
}

// Duplicate preset
add_action('wp_ajax_theme_styles_new_duplicate_preset', 'theme_styles_new_duplicate_preset_ajax');
function theme_styles_new_duplicate_preset_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

    $name     = sanitize_text_field($_POST['name'] ?? '');
    $new_name = sanitize_text_field($_POST['new_name'] ?? '');

    if (!$name || !$new_name) wp_send_json_error(['message' => 'Name required']);

    $result = Theme_Styles_Preset_Manager::duplicate($name, $new_name);
    if ($result) {
        wp_send_json_success(['message' => 'Duplicated', 'presets' => Theme_Styles_Preset_Manager::get_all()]);
    } else {
        wp_send_json_error(['message' => 'Duplicate failed']);
    }
}

// Rename preset
add_action('wp_ajax_theme_styles_new_rename_preset', 'theme_styles_new_rename_preset_ajax');
function theme_styles_new_rename_preset_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

    $name     = sanitize_text_field($_POST['name'] ?? '');
    $new_name = sanitize_text_field($_POST['new_name'] ?? '');

    if (!$name || !$new_name) wp_send_json_error(['message' => 'Name required']);

    $result = Theme_Styles_Preset_Manager::rename($name, $new_name);
    if ($result) {
        // Aktif preset adını da güncelle
        if (get_option('theme_styles_new_active_preset') === $name) {
            update_option('theme_styles_new_active_preset', $new_name);
        }
        wp_send_json_success(['message' => 'Renamed', 'presets' => Theme_Styles_Preset_Manager::get_all()]);
    } else {
        wp_send_json_error(['message' => 'Rename failed']);
    }
}

// Export preset
add_action('admin_post_theme_styles_new_export_preset', 'theme_styles_new_export_preset');
function theme_styles_new_export_preset() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
    
    if (empty($name)) {
        wp_die('Name required');
    }
    
    Theme_Styles_Preset_Manager::export($name);
}

// Import preset
add_action('wp_ajax_theme_styles_new_import_preset', 'theme_styles_new_import_preset_ajax');
function theme_styles_new_import_preset_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => 'No file uploaded']);
    }
    
    $result = Theme_Styles_Preset_Manager::import($_FILES['file']);
    
    if ($result) {
        wp_send_json_success([
            'message' => 'Preset imported',
            'presets' => Theme_Styles_Preset_Manager::get_all()
        ]);
    } else {
        wp_send_json_error(['message' => 'Import failed']);
    }
}

// Revert to default
add_action('wp_ajax_theme_styles_new_revert', 'theme_styles_new_revert_ajax');
function theme_styles_new_revert_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $default_file = THEME_STYLES_NEW_PATH . '/data/default.json';
    if (file_exists($default_file)) {
        $data = json_decode(file_get_contents($default_file), true);
        
        $instance = Theme_Styles_New::init();
        $result = $instance->save_data($data, '');
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Reverted to default',
                'data' => $data,
                'activePreset' => ''
            ]);
        }
    }
    
    wp_send_json_error(['message' => 'Revert failed']);
}

// Generate preview CSS
add_action('wp_ajax_theme_styles_new_generate_preview', 'theme_styles_new_generate_preview_ajax');
function theme_styles_new_generate_preview_ajax() {
    check_ajax_referer('theme_styles_new_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];
    
    if (empty($data)) {
        wp_send_json_error(['message' => 'No data provided']);
    }
    
    // Generate CSS from current form data
    $generator = new Theme_Styles_CSS_Generator();
    $css = $generator->generate($data);
    
    if ($css) {
        wp_send_json_success(['css' => $css]);
    } else {
        wp_send_json_error(['message' => 'CSS generation failed']);
    }
}
