<?php
/**
 * WooCommerce Bootstrap Forms Integration
 * WooCommerce form elementlerine Bootstrap classları ekler
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

class Forms {
    
    private $remove_woocommerce_styles;
    
    public function __construct() {
        // Eğer init hook'u geçmişse direkt çalıştır
        if (did_action('init')) {
            $this->init();
        } else {
            add_action('init', array($this, 'init'));
        }
    }
    
    public function init() {
        // ACF ayarını kontrol et - remove_woocommerce_styles field'ı
        $this->remove_woocommerce_styles = get_field('remove_woocommerce_styles', 'option');
        
        // Eğer WooCommerce stilleri kaldırılmışsa Bootstrap classları ekle
        if ($this->remove_woocommerce_styles) {
            $this->add_bootstrap_hooks();
        }
    }
    
    private function add_bootstrap_hooks() {
        // Form elementlerine Bootstrap classları ekle
        add_filter('woocommerce_form_field_text', array($this, 'add_bootstrap_classes_to_text_field'), 10, 4);
        add_filter('woocommerce_form_field_email', array($this, 'add_bootstrap_classes_to_text_field'), 10, 4);
        add_filter('woocommerce_form_field_tel', array($this, 'add_bootstrap_classes_to_text_field'), 10, 4);
        add_filter('woocommerce_form_field_password', array($this, 'add_bootstrap_classes_to_text_field'), 10, 4);
        add_filter('woocommerce_form_field_textarea', array($this, 'add_bootstrap_classes_to_textarea'), 10, 4);
        add_filter('woocommerce_form_field_select', array($this, 'add_bootstrap_classes_to_select'), 10, 4);
        add_filter('woocommerce_form_field_checkbox', array($this, 'add_bootstrap_classes_to_checkbox'), 10, 4);
        add_filter('woocommerce_form_field_radio', array($this, 'add_bootstrap_classes_to_radio'), 10, 4);
        
        // Butonlara Bootstrap classları ekle
        add_filter('woocommerce_loop_add_to_cart_link', array($this, 'add_bootstrap_classes_to_buttons'), 10, 2);
        
        // SERVER-SIDE: Quantity input ve diğer elementler için PHP hooks
        add_filter('woocommerce_quantity_input_classes', array($this, 'add_quantity_classes'), 10, 2);
        add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'add_variation_select_classes'), 10, 1);
        add_filter('woocommerce_catalog_orderby', array($this, 'add_orderby_select_classes'), 10, 1);
        add_filter('woocommerce_order_button_html', array($this, 'add_checkout_button_classes'), 10, 1);
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'add_single_product_button_classes'), 10, 1);
        add_filter('woocommerce_reset_variations_link', array($this, 'add_reset_variations_classes'), 10, 1);
        
        // Cart page buttons - artık template'de class var
        // add_action('woocommerce_cart_actions', array($this, 'add_cart_button_classes'), 1);
        
        // Coupon form - artık template'de class var
        // add_filter('woocommerce_cart_coupon', array($this, 'modify_coupon_form'), 10, 1);
        
        // Cart quantity inputs
        add_filter('woocommerce_cart_item_quantity', array($this, 'add_quantity_input_classes_cart'), 10, 3);
        
        // Proceed to checkout button
        add_filter('woocommerce_proceed_to_checkout', array($this, 'add_proceed_to_checkout_classes'), 10, 1);
    }
    
    /**
     * Plugin class'larını kontrol et
     */
    private function has_plugin_class($field, $plugin_classes = array()) {
        if (empty($plugin_classes)) {
            $plugin_classes = apply_filters('woocommerce_bootstrap_plugin_classes', array(
                'selectpicker', 'select2', 'chosen', 'nice-select', 'icheck', 'custom-input'
            ));
        }
        
        foreach ($plugin_classes as $plugin_class) {
            if (strpos($field, $plugin_class) !== false) {
                return true;
            }
        }
        return false;
    }
    
    public function add_bootstrap_classes_to_text_field($field, $key, $args, $value) {
        // Input elementine Bootstrap classları ekle
        $field = str_replace('class="input-text', 'class="form-control input-text', $field);
        
        // Wrapper'a Bootstrap classları ekle
        if (strpos($field, 'woocommerce-input-wrapper') !== false) {
            $field = str_replace('woocommerce-input-wrapper', 'woocommerce-input-wrapper mb-3', $field);
        }
        
        return $field;
    }
    
    public function add_bootstrap_classes_to_textarea($field, $key, $args, $value) {
        // Textarea elementine Bootstrap classları ekle
        $field = str_replace('<textarea', '<textarea class="form-control"', $field);
        
        // Wrapper'a Bootstrap classları ekle
        if (strpos($field, 'woocommerce-input-wrapper') !== false) {
            $field = str_replace('woocommerce-input-wrapper', 'woocommerce-input-wrapper mb-3', $field);
        }
        
        return $field;
    }
    
    public function add_bootstrap_classes_to_select($field, $key, $args, $value) {
        // Plugin detection - eğer özel class varsa Bootstrap ekleme
        if ($this->has_plugin_class($field)) {
            // Plugin kullanılıyor, sadece wrapper'a mb-3 ekle
            if (strpos($field, 'woocommerce-input-wrapper') !== false) {
                $field = str_replace('woocommerce-input-wrapper', 'woocommerce-input-wrapper mb-3', $field);
            }
            return apply_filters('woocommerce_bootstrap_select_with_plugin', $field, $key, $args, $value);
        }
        
        // Plugin yok, Bootstrap class ekle
        $bootstrap_class = apply_filters('woocommerce_bootstrap_select_class', 'form-select', $key, $args);
        $field = str_replace('<select', '<select class="' . $bootstrap_class . '"', $field);
        
        // Wrapper'a Bootstrap classları ekle
        if (strpos($field, 'woocommerce-input-wrapper') !== false) {
            $field = str_replace('woocommerce-input-wrapper', 'woocommerce-input-wrapper mb-3', $field);
        }
        
        return apply_filters('woocommerce_bootstrap_select_field', $field, $key, $args, $value);
    }
    
    public function add_bootstrap_classes_to_checkbox($field, $key, $args, $value) {
        // Checkbox wrapper'ına Bootstrap classları ekle
        $field = str_replace('woocommerce-form__label-for-checkbox', 'form-check-label woocommerce-form__label-for-checkbox', $field);
        $field = str_replace('woocommerce-form__input woocommerce-form__input-checkbox', 'form-check-input woocommerce-form__input woocommerce-form__input-checkbox', $field);
        
        // Ana wrapper
        if (strpos($field, 'woocommerce-input-wrapper') !== false) {
            $field = str_replace('woocommerce-input-wrapper', 'woocommerce-input-wrapper form-check mb-3', $field);
        }
        
        return $field;
    }
    
    public function add_bootstrap_classes_to_radio($field, $key, $args, $value) {
        // Radio button'lara Bootstrap classları ekle
        $field = str_replace('woocommerce-form__input woocommerce-form__input-radio', 'form-check-input woocommerce-form__input woocommerce-form__input-radio', $field);
        $field = str_replace('woocommerce-form__label woocommerce-form__label-for-radio', 'form-check-label woocommerce-form__label woocommerce-form__label-for-radio', $field);
        
        return $field;
    }
    
    public function add_bootstrap_classes_to_buttons($link, $product) {
        // Add to cart butonlarına Bootstrap classları ekle
        $link = str_replace('class="button', 'class="btn btn-primary button', $link);
        return $link;
    }
    
    /**
     * SERVER-SIDE METHODS - PurgeCSS için
     */
    
    public function add_quantity_classes($classes, $product) {
        $classes[] = 'form-control';
        return $classes;
    }
    
    public function add_variation_select_classes($html) {
        // Variation select'lere form-select ekle
        return str_replace('<select', '<select class="form-select"', $html);
    }
    
    public function add_orderby_select_classes($orderby) {
        // Orderby select için HTML output'a direkt müdahale et
        // NOT: orderby.php template'inde zaten form-select class'ı var
        // Bu hook sadece fallback olarak kalıyor
        add_filter('woocommerce_catalog_ordering_html', array($this, 'modify_orderby_html'), 10, 1);
        return $orderby;
    }
    
    public function modify_orderby_html($html) {
        // Select elementine form-select class ekle (eğer yoksa)
        if (strpos($html, 'form-select') === false) {
            $html = str_replace('class="orderby"', 'class="orderby form-select"', $html);
        }
        return $html;
    }
    
    public function add_checkout_button_classes($button) {
        // Checkout button'a Bootstrap class ekle
        return str_replace('class="button', 'class="button btn btn-success', $button);
    }
    
    public function add_single_product_button_classes($text) {
        // Single product add to cart button için filter ekle
        add_filter('woocommerce_product_add_to_cart_text', function($text) {
            // Button HTML'ine müdahale et
            add_filter('woocommerce_loop_add_to_cart_link', function($link) {
                if (strpos($link, 'single_add_to_cart_button') !== false) {
                    $link = str_replace('class="', 'class="btn btn-primary ', $link);
                }
                return $link;
            }, 20);
            return $text;
        });
        return $text;
    }
    
    public function add_reset_variations_classes($link) {
        // Reset variations button'a Bootstrap class ekle
        return str_replace('class="reset_variations', 'class="reset_variations btn btn-sm btn-outline-warning', $link);
    }
    
    public function add_proceed_to_checkout_classes($checkout_html) {
        // Proceed to checkout button'a Bootstrap class ekle (eğer yoksa)
        if (strpos($checkout_html, 'btn btn-success') === false) {
            $checkout_html = str_replace('class="checkout-button', 'class="checkout-button btn btn-success', $checkout_html);
        }
        return $checkout_html;
    }
    
    public function add_quantity_input_classes_cart($product_quantity, $cart_item_key, $cart_item) {
        // Quantity input'a form-control class ekle (eğer yoksa)
        if (strpos($product_quantity, 'form-control') === false) {
            $product_quantity = str_replace('class="qty', 'class="qty form-control', $product_quantity);
            // Eğer class attribute yoksa ekle
            if (strpos($product_quantity, 'class=') === false) {
                $product_quantity = str_replace('<input', '<input class="form-control"', $product_quantity);
            }
        }
        return $product_quantity;
    }
}