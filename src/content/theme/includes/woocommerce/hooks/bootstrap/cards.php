<?php
/**
 * WooCommerce Bootstrap Cards Integration
 * WooCommerce card/panel elementlerine Bootstrap classları ekler
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

class Cards {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        $this->add_card_hooks();
    }
    
    private function add_card_hooks() {
        // JavaScript ile card elementlerini dönüştür
        add_action('wp_footer', array($this, 'transform_cards'));
    }
    
    public function transform_cards() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Product cards (loop)
            $('.woocommerce ul.products li.product').each(function() {
                var $product = $(this);
                if (!$product.hasClass('card')) {
                    $product.addClass('card h-100');
                    
                    // Product image
                    $product.find('.woocommerce-loop-product__link img').wrap('<div class="card-img-top-wrapper"></div>');
                    $product.find('.woocommerce-loop-product__link img').addClass('card-img-top');
                    
                    // Product content wrapper
                    var $content = $product.find('h2, .price, .star-rating, .woocommerce-loop-product__link:not(:has(img))');
                    if ($content.length && !$content.parent().hasClass('card-body')) {
                        $content.wrapAll('<div class="card-body"></div>');
                    }
                    
                    // Product title
                    $product.find('h2').addClass('card-title h6');
                    
                    // Product price
                    $product.find('.price').addClass('card-text fw-bold');
                    
                    // Add to cart button
                    $product.find('.add_to_cart_button').wrap('<div class="card-footer bg-transparent border-0"></div>');
                }
            });
            
            // Cart totals
            $('.cart_totals').each(function() {
                var $totals = $(this);
                if (!$totals.hasClass('card')) {
                    $totals.addClass('card');
                    $totals.find('h2').addClass('card-header').wrap('<div class="card-header"></div>');
                    $totals.find('table').wrap('<div class="card-body"></div>');
                    $totals.find('.wc-proceed-to-checkout').addClass('card-footer bg-transparent');
                }
            });
            
            // Checkout order review
            $('.woocommerce-checkout-review-order').each(function() {
                var $review = $(this);
                if (!$review.hasClass('card')) {
                    $review.addClass('card');
                    $review.find('h3').addClass('card-header');
                    $review.find('.woocommerce-checkout-review-order-table').wrap('<div class="card-body"></div>');
                }
            });
            
            // Payment methods
            $('.woocommerce-checkout-payment').each(function() {
                var $payment = $(this);
                if (!$payment.hasClass('card')) {
                    $payment.addClass('card');
                    $payment.find('.wc_payment_methods').wrap('<div class="card-body"></div>');
                    $payment.find('#place_order').wrap('<div class="card-footer"></div>');
                }
            });
            
            // My Account sections
            $('.woocommerce-MyAccount-content').each(function() {
                var $content = $(this);
                if (!$content.hasClass('card')) {
                    $content.addClass('card');
                    $content.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Order details
            $('.woocommerce-order').each(function() {
                var $order = $(this);
                if (!$order.hasClass('card')) {
                    $order.addClass('card');
                    $order.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Product single - summary
            $('.summary.entry-summary').each(function() {
                var $summary = $(this);
                if (!$summary.hasClass('card') && $('body').hasClass('single-product')) {
                    $summary.addClass('card');
                    $summary.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Product tabs content
            $('.woocommerce-tabs .panel').each(function() {
                var $panel = $(this);
                if (!$panel.hasClass('card')) {
                    $panel.addClass('card');
                    $panel.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Widgets
            $('.widget.woocommerce').each(function() {
                var $widget = $(this);
                if (!$widget.hasClass('card')) {
                    $widget.addClass('card mb-4');
                    
                    // Widget title
                    var $title = $widget.find('.widget-title, h2, h3').first();
                    if ($title.length) {
                        $title.addClass('card-header').wrap('<div class="card-header"></div>');
                    }
                    
                    // Widget content
                    var $content = $widget.children().not('.card-header');
                    if ($content.length) {
                        $content.wrapAll('<div class="card-body"></div>');
                    }
                }
            });
            
            // Cross-sells
            $('.cross-sells').each(function() {
                var $crossSells = $(this);
                if (!$crossSells.hasClass('card')) {
                    $crossSells.addClass('card');
                    $crossSells.find('h2').addClass('card-header');
                    $crossSells.find('.products').wrap('<div class="card-body"></div>');
                }
            });
            
            // Up-sells
            $('.up-sells').each(function() {
                var $upSells = $(this);
                if (!$upSells.hasClass('card')) {
                    $upSells.addClass('card');
                    $upSells.find('h2').addClass('card-header');
                    $upSells.find('.products').wrap('<div class="card-body"></div>');
                }
            });
            
            // Related products
            $('.related.products').each(function() {
                var $related = $(this);
                if (!$related.hasClass('card')) {
                    $related.addClass('card');
                    $related.find('h2').addClass('card-header');
                    $related.find('.products').wrap('<div class="card-body"></div>');
                }
            });
            
            // Reviews
            $('.woocommerce-Reviews').each(function() {
                var $reviews = $(this);
                if (!$reviews.hasClass('card')) {
                    $reviews.addClass('card');
                    $reviews.find('.woocommerce-Reviews-title').addClass('card-header');
                    $reviews.find('#reviews').wrap('<div class="card-body"></div>');
                }
            });
            
            // Individual review
            $('.comment').each(function() {
                var $comment = $(this);
                if (!$comment.hasClass('card') && $comment.closest('.woocommerce-Reviews').length) {
                    $comment.addClass('card mb-3');
                    $comment.wrapInner('<div class="card-body"></div>');
                    
                    // Review meta
                    $comment.find('.meta').addClass('card-text text-muted small');
                    
                    // Review rating
                    $comment.find('.star-rating').addClass('mb-2');
                }
            });
            
            // Login/Register forms
            $('.woocommerce-form-login, .woocommerce-form-register').each(function() {
                var $form = $(this);
                if (!$form.hasClass('card')) {
                    $form.addClass('card');
                    $form.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Coupon form
            $('.coupon').each(function() {
                var $coupon = $(this);
                if (!$coupon.hasClass('card')) {
                    $coupon.addClass('card');
                    $coupon.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Shipping calculator
            $('.shipping-calculator-form').each(function() {
                var $calc = $(this);
                if (!$calc.hasClass('card')) {
                    $calc.addClass('card');
                    $calc.wrapInner('<div class="card-body"></div>');
                }
            });
            
            // Wishlist items (YITH)
            $('.wishlist_table tr').each(function() {
                var $row = $(this);
                if (!$row.hasClass('card') && !$row.is('thead tr')) {
                    $row.find('td').addClass('align-middle');
                }
            });
            
            // Compare table (YITH)
            $('.compare-list').each(function() {
                var $compare = $(this);
                if (!$compare.hasClass('card')) {
                    $compare.addClass('card');
                    $compare.wrapInner('<div class="card-body"></div>');
                }
            });
        });
        </script>
        <?php
    }
}