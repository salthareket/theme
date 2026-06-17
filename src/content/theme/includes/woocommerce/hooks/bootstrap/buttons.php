<?php
/**
 * WooCommerce Bootstrap Buttons Integration
 * WooCommerce butonlarına Bootstrap classları ekler
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

class Buttons {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        $this->add_button_hooks();
    }
    
    private function add_button_hooks() {
        // Shop loop butonları
        add_filter('woocommerce_loop_add_to_cart_link', array($this, 'loop_add_to_cart_button'), 10, 2);
        
        // Single product butonları
        add_filter('woocommerce_single_product_summary', array($this, 'single_product_buttons'), 25);
        
        // Cart butonları
        add_action('wp_footer', array($this, 'cart_buttons_js'));
        
        // Checkout butonları
        add_action('wp_footer', array($this, 'checkout_buttons_js'));
        
        // My Account butonları
        add_action('wp_footer', array($this, 'account_buttons_js'));
        
        // Wishlist & Compare (eğer plugin varsa)
        add_action('wp_footer', array($this, 'plugin_buttons_js'));
    }
    
    public function loop_add_to_cart_button($link, $product) {
        // Mevcut button class'ını bul ve Bootstrap ekle
        if (strpos($link, 'class="') !== false) {
            $link = str_replace('class="', 'class="btn btn-primary ', $link);
        } else {
            $link = str_replace('<a ', '<a class="btn btn-primary" ', $link);
        }
        
        // Product type'a göre farklı stiller
        if ($product->is_type('variable')) {
            $link = str_replace('btn-primary', 'btn-outline-primary', $link);
        } elseif ($product->is_type('external')) {
            $link = str_replace('btn-primary', 'btn-outline-info', $link);
        }
        
        return $link;
    }
    
    public function single_product_buttons() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Single add to cart button
            $('.single_add_to_cart_button').addClass('btn btn-success btn-lg');
            
            // Variation buttons
            $('.variations_button .button').addClass('btn btn-outline-secondary');
            
            // Reset variations
            $('.reset_variations').addClass('btn btn-sm btn-outline-warning');
        });
        </script>
        <?php
    }
    
    public function cart_buttons_js() {
        if (!is_cart()) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Update cart button
            $('input[name="update_cart"]').addClass('btn btn-warning');
            
            // Remove item links
            $('.remove').addClass('btn btn-sm btn-outline-danger');
            
            // Continue shopping
            $('.wc-backward').addClass('btn btn-outline-secondary');
            
            // Proceed to checkout
            $('.checkout-button').addClass('btn btn-success btn-lg');
            
            // Apply coupon
            $('input[name="apply_coupon"]').addClass('btn btn-info');
            
            // Clear cart (eğer varsa)
            $('.clear-cart').addClass('btn btn-outline-danger');
        });
        </script>
        <?php
    }
    
    public function checkout_buttons_js() {
        if (!is_checkout()) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Place order button
            $('#place_order').addClass('btn btn-success btn-lg');
            
            // Login/Register buttons
            $('.woocommerce-form-login .button').addClass('btn btn-primary');
            $('.woocommerce-form-register .button').addClass('btn btn-primary');
            
            // Back to cart
            $('.wc-backward').addClass('btn btn-outline-secondary');
            
            // Payment method buttons (eğer varsa)
            $('.payment_method_paypal .button').addClass('btn btn-warning');
            $('.payment_method_stripe .button').addClass('btn btn-info');
        });
        </script>
        <?php
    }
    
    public function account_buttons_js() {
        if (!is_account_page()) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Account form buttons
            $('.woocommerce-form .button').addClass('btn btn-primary');
            
            // Edit buttons
            $('.edit').addClass('btn btn-sm btn-outline-primary');
            
            // Delete/Cancel buttons
            $('.delete, .cancel').addClass('btn btn-sm btn-outline-danger');
            
            // View buttons
            $('.view').addClass('btn btn-sm btn-outline-info');
            
            // Download buttons
            $('.download').addClass('btn btn-sm btn-success');
            
            // Logout button
            $('.woocommerce-MyAccount-navigation-link--customer-logout a').addClass('btn btn-outline-danger');
        });
        </script>
        <?php
    }
    
    public function plugin_buttons_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // YITH Wishlist
            $('.add_to_wishlist').addClass('btn btn-outline-danger');
            $('.wishlist_table .add_to_cart').addClass('btn btn-primary');
            
            // YITH Compare
            $('.compare').addClass('btn btn-outline-info');
            
            // Quick View
            $('.quick-view, .yith-wcqv-button').addClass('btn btn-outline-dark');
            
            // Social Share
            $('.share-button').addClass('btn btn-outline-secondary');
            
            // Review buttons
            $('.comment-reply-link').addClass('btn btn-sm btn-outline-primary');
            $('#submit').addClass('btn btn-primary');
        });
        </script>
        <?php
    }
}