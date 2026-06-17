<?php
/**
 * WooCommerce Bootstrap Manager
 * Bootstrap modüllerini yönetir ve şalterlere göre yükler
 * 
 * @package SaltHareket\Theme\WooCommerce\Hooks\Bootstrap
 * @version 1.0.0
 * @author SaltHareket
 * @since 1.0.0
 */

namespace SaltHareket\Theme\WooCommerce\Hooks\Bootstrap;

if (!defined('ABSPATH')) {
    exit;
}

class Manager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Ana şalter - remove_woocommerce_styles açık olmalı
        if (get_field('remove_woocommerce_styles', 'option')) {
            $this->load_modules();
        }
    }
    
    private function load_modules() {
        $modules = array(
            'bootstrap_forms' => array('file' => 'forms.php', 'class' => 'Forms'),
            'bootstrap_buttons' => array('file' => 'buttons.php', 'class' => 'Buttons'),
            'bootstrap_messages' => array('file' => 'messages.php', 'class' => 'Messages'),
            'bootstrap_tables' => array('file' => 'tables.php', 'class' => 'Tables'),
            'bootstrap_navigation' => array('file' => 'navigation.php', 'class' => 'Navigation'),
            'bootstrap_cards' => array('file' => 'cards.php', 'class' => 'Cards')
        );
        
        foreach ($modules as $field => $module) {
            // Eğer field yoksa default olarak yükle (geriye uyumluluk)
            $load_module = get_field($field, 'option');
            if ($load_module === null) {
                $load_module = true; // Default açık
            }
            
            if ($load_module && file_exists(__DIR__ . '/' . $module['file'])) {
                include_once __DIR__ . '/' . $module['file'];
                
                // Class'ı instantiate et
                $class_name = '\\SaltHareket\\Theme\\WooCommerce\\Hooks\\Bootstrap\\' . $module['class'];
                if (class_exists($class_name)) {
                    new $class_name();
                }
            }
        }
    }
}

// Manager'ı başlat
new Manager();