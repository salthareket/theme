<?php
/**
 * WooCommerce Bootstrap Tables Integration
 * WooCommerce tablolarına Bootstrap classları ekler
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

class Tables {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        $this->add_table_hooks();
    }
    
    private function add_table_hooks() {
        // JavaScript ile tabloları dönüştür
        add_action('wp_footer', array($this, 'transform_tables'));
    }
    
    public function transform_tables() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Cart table
            $('.woocommerce-cart-form table, .cart_totals table').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-striped');
                    
                    // Responsive wrapper ekle
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // Checkout review table
            $('.woocommerce-checkout-review-order-table').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-bordered');
                    
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // My Account orders table
            $('.woocommerce-orders-table, .my_account_orders').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-hover');
                    
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // Order details table
            $('.woocommerce-table--order-details').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-striped table-bordered');
                    
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // Product attributes table
            $('.woocommerce-product-attributes, .shop_attributes').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-sm table-striped');
                }
            });
            
            // Variations table
            $('.variations').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-borderless');
                }
            });
            
            // Downloads table
            $('.woocommerce-table--my-account-downloads').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-hover');
                    
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // Addresses table
            $('.woocommerce-Address').each(function() {
                var $table = $(this);
                if ($table.is('table') && !$table.hasClass('table')) {
                    $table.addClass('table table-borderless');
                }
            });
            
            // Payment methods table
            $('.woocommerce-table--my-account-payment-methods').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-striped');
                    
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // Cross-sells table
            $('.cross-sells table').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-borderless');
                }
            });
            
            // Shipping calculator table
            $('.shipping-calculator-form table').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-sm');
                }
            });
            
            // Reviews table (eğer tablo formatında gösteriliyorsa)
            $('.commentlist table').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-striped');
                }
            });
            
            // Wishlist table (YITH)
            $('.wishlist_table').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-hover');
                    
                    if (!$table.parent().hasClass('table-responsive')) {
                        $table.wrap('<div class="table-responsive"></div>');
                    }
                }
            });
            
            // Compare table (YITH)
            $('.compare-list').each(function() {
                var $table = $(this);
                if (!$table.hasClass('table')) {
                    $table.addClass('table table-bordered table-responsive');
                }
            });
            
            // Table headers'a Bootstrap class ekle
            $('.table th').addClass('table-header');
            
            // Quantity input'ları table içinde düzenle
            $('.table .qty').addClass('form-control form-control-sm');
            
            // Table içindeki butonları küçült
            $('.table .button, .table .btn').addClass('btn-sm');
            
            // Empty cart message
            $('.cart-empty').addClass('alert alert-info text-center');
            
            // Shipping options
            $('.woocommerce-shipping-methods li').addClass('form-check');
            $('.woocommerce-shipping-methods input').addClass('form-check-input');
            $('.woocommerce-shipping-methods label').addClass('form-check-label');
        });
        </script>
        <?php
    }
}