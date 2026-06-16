<?php
/**
 * Frontend Helper Functions
 * 
 * @package SaltHareket\Theme\ThemeStylesNew
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Get theme styles data
 * 
 * @return array
 */
function theme_styles_new_get_data() {
    $instance = Theme_Styles_New::init();
    return $instance->get_data();
}

/**
 * Get specific module data
 * 
 * @param string $module_name
 * @return array|null
 */
function theme_styles_new_get_module($module_name) {
    $data = theme_styles_new_get_data();
    return $data[$module_name] ?? null;
}

/**
 * Get CSS variable value
 * 
 * @param string $var_name
 * @param mixed $default
 * @return mixed
 */
function theme_styles_new_get_var($var_name, $default = '') {
    $data = theme_styles_new_get_data();
    
    // Parse dot notation (e.g., 'colors.primary')
    $keys = explode('.', $var_name);
    $value = $data;
    
    foreach ($keys as $key) {
        if (isset($value[$key])) {
            $value = $value[$key];
        } else {
            return $default;
        }
    }
    
    return $value !== '' ? $value : $default;
}

/**
 * Load modules for specific context (e.g., template post type)
 * 
 * @param array $modules Module names to load
 * @param int $post_id Optional post ID for post-specific styles
 * @return string Generated CSS
 */
function theme_styles_load_modules($modules = [], $post_id = null) {
    if (empty($modules)) {
        return '';
    }
    
    $data = [];
    
    // Get post-specific data if post_id provided
    if ($post_id) {
        $post_data = get_post_meta($post_id, '_theme_styles_data', true);
        if ($post_data) {
            $data = $post_data;
        }
    }
    
    // If no post-specific data, use global
    if (empty($data)) {
        $data = theme_styles_new_get_data();
    }
    
    // Generate CSS for specific modules
    $generator = new Theme_Styles_CSS_Generator();
    $css = '';
    
    foreach ($modules as $module) {
        $processor_file = THEME_STYLES_NEW_PATH . "/modules/{$module}/processor.php";
        if (file_exists($processor_file)) {
            include_once $processor_file;
            
            $processor_function = "theme_styles_process_{$module}";
            if (function_exists($processor_function)) {
                $result = $processor_function($data, $generator);
                // CSS will be added to generator
            }
        }
    }
    
    // Build inline CSS
    $variables = [];
    foreach ($generator->variables ?? [] as $key => $value) {
        $variables[] = "--{$key}: {$value}";
    }
    
    if (!empty($variables)) {
        $css = ":root { " . implode('; ', $variables) . "; }";
    }
    
    return $css;
}

/**
 * Output inline styles for modules
 * 
 * @param array $modules
 * @param int $post_id
 */
function theme_styles_output_modules($modules = [], $post_id = null) {
    $css = theme_styles_load_modules($modules, $post_id);
    if ($css) {
        echo '<style id="theme-styles-inline">' . $css . '</style>';
    }
}
