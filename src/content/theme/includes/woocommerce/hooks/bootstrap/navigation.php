<?php
/**
 * WooCommerce Bootstrap Navigation Integration
 * WooCommerce navigasyon elementlerine Bootstrap classları ekler
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

class Navigation {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        $this->add_navigation_hooks();
    }
    
    private function add_navigation_hooks() {
        // Breadcrumb'ları override et
        add_filter('woocommerce_breadcrumb_defaults', array($this, 'breadcrumb_defaults'));
        
        // Pagination'ı override et
        add_filter('woocommerce_pagination_args', array($this, 'pagination_args'));
        
        // JavaScript ile navigasyon elementlerini dönüştür
        add_action('wp_footer', array($this, 'transform_navigation'));
    }
    
    public function breadcrumb_defaults($args) {
        $args['delimiter'] = '';
        $args['wrap_before'] = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        $args['wrap_after'] = '</ol></nav>';
        $args['before'] = '<li class="breadcrumb-item">';
        $args['after'] = '</li>';
        $args['home'] = '<li class="breadcrumb-item"><a href="' . home_url() . '">Ana Sayfa</a></li>';
        
        return $args;
    }
    
    public function pagination_args($args) {
        $args['prev_text'] = '<span aria-hidden="true">&laquo;</span> Önceki';
        $args['next_text'] = 'Sonraki <span aria-hidden="true">&raquo;</span>';
        $args['type'] = 'list';
        
        return $args;
    }
    
    public function transform_navigation() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Pagination
            $('.woocommerce-pagination ul').each(function() {
                var $ul = $(this);
                if (!$ul.hasClass('pagination')) {
                    $ul.addClass('pagination justify-content-center');
                    
                    // Page items
                    $ul.find('li').addClass('page-item');
                    $ul.find('a, span').addClass('page-link');
                    
                    // Current page
                    $ul.find('.current').parent().addClass('active');
                    
                    // Disabled states
                    $ul.find('li:first-child').each(function() {
                        if ($(this).find('span').length) {
                            $(this).addClass('disabled');
                        }
                    });
                    $ul.find('li:last-child').each(function() {
                        if ($(this).find('span').length) {
                            $(this).addClass('disabled');
                        }
                    });
                }
            });
            
            // Product tabs
            $('.woocommerce-tabs ul.tabs').each(function() {
                var $tabs = $(this);
                if (!$tabs.hasClass('nav')) {
                    $tabs.addClass('nav nav-tabs');
                    $tabs.find('li').addClass('nav-item');
                    $tabs.find('a').addClass('nav-link');
                    $tabs.find('.active a').addClass('active');
                }
            });
            
            // My Account navigation
            $('.woocommerce-MyAccount-navigation ul').each(function() {
                var $nav = $(this);
                if (!$nav.hasClass('nav')) {
                    $nav.addClass('nav nav-pills flex-column');
                    $nav.find('li').addClass('nav-item');
                    $nav.find('a').addClass('nav-link');
                    $nav.find('.is-active a').addClass('active');
                }
            });
            
            // Breadcrumb (eğer custom değilse)
            $('.woocommerce-breadcrumb').each(function() {
                var $breadcrumb = $(this);
                if (!$breadcrumb.find('.breadcrumb').length) {
                    // Mevcut breadcrumb'ı Bootstrap formatına çevir
                    var html = $breadcrumb.html();
                    var links = html.split(' / ');
                    var newHtml = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
                    
                    $.each(links, function(index, link) {
                        if (index === links.length - 1) {
                            // Son element - active
                            newHtml += '<li class="breadcrumb-item active" aria-current="page">' + link + '</li>';
                        } else {
                            newHtml += '<li class="breadcrumb-item">' + link + '</li>';
                        }
                    });
                    
                    newHtml += '</ol></nav>';
                    $breadcrumb.html(newHtml);
                }
            });
            
            // Result count ve ordering
            $('.woocommerce-result-count').addClass('text-muted small');
            $('.woocommerce-ordering select').addClass('form-select form-select-sm');
            
            // Filters (sidebar)
            $('.widget_layered_nav ul, .widget_product_categories ul').each(function() {
                var $list = $(this);
                if (!$list.hasClass('list-group')) {
                    $list.addClass('list-group list-group-flush');
                    $list.find('li').addClass('list-group-item');
                    $list.find('a').addClass('text-decoration-none');
                }
            });
            
            // Price filter
            $('.widget_price_filter .price_slider_wrapper').addClass('mb-3');
            $('.widget_price_filter .button').addClass('btn btn-primary btn-sm');
            
            // Rating filter
            $('.widget_rating_filter ul').addClass('list-unstyled');
            $('.widget_rating_filter a').addClass('text-decoration-none');
            
            // Product search widget
            $('.widget_product_search input[type="search"]').addClass('form-control');
            $('.widget_product_search input[type="submit"]').addClass('btn btn-primary');
            
            // Recently viewed products
            $('.widget_recently_viewed_products ul').addClass('list-group list-group-flush');
            $('.widget_recently_viewed_products li').addClass('list-group-item');
            
            // Top rated products
            $('.widget_top_rated_products ul').addClass('list-group list-group-flush');
            $('.widget_top_rated_products li').addClass('list-group-item');
            
            // Product tag cloud
            $('.widget_product_tag_cloud .tagcloud a').addClass('badge bg-secondary me-1 mb-1 text-decoration-none');
            
            // Mini cart widget
            $('.widget_shopping_cart .cart_list').addClass('list-group list-group-flush');
            $('.widget_shopping_cart .cart_list li').addClass('list-group-item');
            $('.widget_shopping_cart .buttons a').addClass('btn btn-sm');
            $('.widget_shopping_cart .wc-forward').addClass('btn-primary');
            
            // Cross-sells navigation
            $('.cross-sells h2').addClass('h4 mb-3');
            
            // Up-sells navigation
            $('.up-sells h2').addClass('h4 mb-3');
            
            // Related products navigation
            $('.related h2').addClass('h4 mb-3');
        });
        </script>
        <?php
    }
}