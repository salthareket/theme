<?php
/**
 * WooCommerce Bootstrap Messages Integration
 * WooCommerce mesajlarına Bootstrap alert classları ekler
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

class Messages {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        $this->add_message_hooks();
    }
    
    private function add_message_hooks() {
        // WooCommerce notices
        add_filter('wc_get_template', array($this, 'override_notice_template'), 10, 2);
        
        // JavaScript ile mevcut mesajları dönüştür
        add_action('wp_footer', array($this, 'transform_existing_messages'));
    }
    
    public function override_notice_template($template, $template_name) {
        // notices/error.php, notices/notice.php, notices/success.php template'lerini override et
        if (strpos($template_name, 'notices/') === 0) {
            $custom_template = get_template_directory() . '/woocommerce/' . $template_name;
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    public function transform_existing_messages() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // WooCommerce notices'ları Bootstrap alert'e dönüştür
            
            // Success messages
            $('.woocommerce-message, .wc-forward').each(function() {
                var $this = $(this);
                if (!$this.hasClass('alert')) {
                    $this.addClass('alert alert-success alert-dismissible fade show');
                    $this.prepend('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                }
            });
            
            // Error messages
            $('.woocommerce-error, .woocommerce-invalid').each(function() {
                var $this = $(this);
                if (!$this.hasClass('alert')) {
                    $this.addClass('alert alert-danger alert-dismissible fade show');
                    $this.prepend('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                }
            });
            
            // Info messages
            $('.woocommerce-info').each(function() {
                var $this = $(this);
                if (!$this.hasClass('alert')) {
                    $this.addClass('alert alert-info alert-dismissible fade show');
                    $this.prepend('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                }
            });
            
            // Notice messages (genel)
            $('.woocommerce-notice').each(function() {
                var $this = $(this);
                if (!$this.hasClass('alert')) {
                    if ($this.hasClass('woocommerce-notice--success')) {
                        $this.addClass('alert alert-success alert-dismissible fade show');
                    } else if ($this.hasClass('woocommerce-notice--error')) {
                        $this.addClass('alert alert-danger alert-dismissible fade show');
                    } else if ($this.hasClass('woocommerce-notice--info')) {
                        $this.addClass('alert alert-info alert-dismissible fade show');
                    } else {
                        $this.addClass('alert alert-warning alert-dismissible fade show');
                    }
                    $this.prepend('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                }
            });
            
            // Validation errors (checkout)
            $('.woocommerce-checkout .woocommerce-error li').each(function() {
                $(this).addClass('alert alert-danger');
            });
            
            // Out of stock messages
            $('.out-of-stock').addClass('alert alert-warning');
            
            // Stock status messages
            $('.stock').each(function() {
                var $this = $(this);
                if ($this.hasClass('in-stock')) {
                    $this.addClass('alert alert-success');
                } else if ($this.hasClass('out-of-stock')) {
                    $this.addClass('alert alert-danger');
                } else {
                    $this.addClass('alert alert-warning');
                }
            });
            
            // Coupon messages
            $('.woocommerce-remove-coupon').addClass('btn btn-sm btn-outline-danger');
            
            // Login/Register errors
            $('.login .woocommerce-error').addClass('alert alert-danger');
            $('.register .woocommerce-error').addClass('alert alert-danger');
            
            // Password strength indicator
            $('.woocommerce-password-strength').each(function() {
                var $this = $(this);
                var strength = $this.attr('class');
                
                if (strength.includes('strong')) {
                    $this.addClass('alert alert-success');
                } else if (strength.includes('good')) {
                    $this.addClass('alert alert-info');
                } else if (strength.includes('medium')) {
                    $this.addClass('alert alert-warning');
                } else {
                    $this.addClass('alert alert-danger');
                }
            });
            
            // Review messages
            $('.comment-awaiting-moderation').addClass('alert alert-info');
            
            // Shipping messages
            $('.shipping-calculator-form .woocommerce-message').addClass('alert alert-success');
            
            // Demo store notice
            $('.woocommerce-store-notice').addClass('alert alert-info alert-dismissible fade show');
            $('.woocommerce-store-notice').prepend('<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
        });
        </script>
        <?php
    }
}