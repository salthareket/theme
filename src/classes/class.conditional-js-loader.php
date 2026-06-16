<?php

namespace SaltHareket\Theme;

/**
 * Conditional JavaScript Loader
 * Sayfa tipine ve içeriğe göre sadece gerekli JS'leri yükler
 * 
 * @version 1.0
 * @author SaltHareket
 */
class ConditionalJSLoader {
    
    private $page_type;
    private $required_features = [];
    private $post_content = '';
    
    public function __construct() {
        $this->page_type = $this->detect_page_type();
        $this->post_content = $this->get_post_content();
        $this->detect_required_features();
    }
    
    /**
     * Sayfa tipini tespit eder
     */
    private function detect_page_type() {
        if (is_home() || is_front_page()) return 'home';
        if (is_single()) return 'post';
        if (is_page()) return 'page';
        if (function_exists('is_shop') && is_shop()) return 'shop';
        if (function_exists('is_product') && is_product()) return 'product';
        if (function_exists('is_cart') && is_cart()) return 'cart';
        if (function_exists('is_checkout') && is_checkout()) return 'checkout';
        if (is_category() || is_tag() || is_archive()) return 'archive';
        if (is_search()) return 'search';
        return 'general';
    }
    
    /**
     * Post içeriğini alır
     */
    private function get_post_content() {
        global $post;
        if (!$post) return '';
        
        $content = $post->post_content;
        
        // ACF fields'ları da kontrol et
        if (function_exists('get_fields')) {
            $fields = get_fields();
            if ($fields) {
                $content .= ' ' . serialize($fields);
            }
        }
        
        return strtolower($content);
    }
    
    /**
     * Sayfa içeriğine göre gerekli feature'ları tespit eder
     */
    private function detect_required_features() {
        $content = $this->post_content;
        
        // Contact Form 7
        if (strpos($content, 'contact-form-7') !== false || 
            strpos($content, '[contact-form') !== false ||
            strpos($content, 'wpcf7') !== false) {
            $this->required_features[] = 'contact-form';
        }
        
        // Google Maps
        if (strpos($content, 'google-map') !== false ||
            strpos($content, 'leaflet') !== false ||
            strpos($content, 'openstreetmap') !== false ||
            strpos($content, 'map') !== false) {
            $this->required_features[] = 'maps';
        }
        
        // Swiper/Slider
        if (strpos($content, 'swiper') !== false ||
            strpos($content, 'slider') !== false ||
            strpos($content, 'carousel') !== false ||
            strpos($content, 'gallery') !== false) {
            $this->required_features[] = 'slider';
        }
        
        // Video Player
        if (strpos($content, 'video') !== false ||
            strpos($content, 'youtube') !== false ||
            strpos($content, 'vimeo') !== false ||
            strpos($content, 'plyr') !== false) {
            $this->required_features[] = 'video';
        }
        
        // Image Gallery/Lightbox
        if (strpos($content, 'lightbox') !== false ||
            strpos($content, 'fancybox') !== false ||
            strpos($content, 'photoswipe') !== false ||
            strpos($content, 'gallery') !== false) {
            $this->required_features[] = 'lightbox';
        }
        
        // Charts/Graphs
        if (strpos($content, 'chart') !== false ||
            strpos($content, 'graph') !== false ||
            strpos($content, 'canvas') !== false) {
            $this->required_features[] = 'charts';
        }
        
        // Animation Libraries
        if (strpos($content, 'aos') !== false ||
            strpos($content, 'animate') !== false ||
            strpos($content, 'gsap') !== false) {
            $this->required_features[] = 'animations';
        }
        
        // WooCommerce specific
        if (function_exists('is_woocommerce') && is_woocommerce()) {
            $this->required_features[] = 'woocommerce';
        }
    }
    
    /**
     * Gerekli script'leri döndürür
     */
    public function get_required_scripts() {
        $scripts = [
            'core' => ['utility'], // Her sayfada gerekli (jQuery zaten yüklü)
        ];
        
        // Page type'a göre script'ler
        switch ($this->page_type) {
            case 'home':
                $scripts['page'] = ['hero-animations', 'home-slider'];
                break;
                
            case 'product':
                $scripts['page'] = ['product-gallery', 'product-zoom', 'add-to-cart-enhanced'];
                break;
                
            case 'shop':
            case 'archive':
                $scripts['page'] = ['product-filters', 'ajax-pagination', 'infinite-scroll'];
                break;
                
            case 'cart':
                $scripts['page'] = ['cart-updates', 'shipping-calculator'];
                break;
                
            case 'checkout':
                $scripts['page'] = ['checkout-validation', 'payment-methods'];
                break;
                
            case 'search':
                $scripts['page'] = ['search-filters', 'ajax-search'];
                break;
                
            case 'post':
            case 'page':
                $scripts['page'] = ['reading-progress', 'social-share'];
                break;
        }
        
        // Feature'lara göre script'ler
        $scripts['features'] = [];
        foreach ($this->required_features as $feature) {
            switch ($feature) {
                case 'contact-form':
                    $scripts['features'][] = 'contact-form-handler';
                    $scripts['features'][] = 'form-validation';
                    break;
                    
                case 'maps':
                    $scripts['features'][] = 'leaflet-maps';
                    $scripts['features'][] = 'map-interactions';
                    break;
                    
                case 'slider':
                    $scripts['features'][] = 'swiper-init';
                    $scripts['features'][] = 'slider-controls';
                    break;
                    
                case 'video':
                    $scripts['features'][] = 'plyr-init';
                    $scripts['features'][] = 'video-controls';
                    break;
                    
                case 'lightbox':
                    $scripts['features'][] = 'photoswipe-init';
                    $scripts['features'][] = 'gallery-navigation';
                    break;
                    
                case 'charts':
                    $scripts['features'][] = 'chart-js';
                    $scripts['features'][] = 'data-visualization';
                    break;
                    
                case 'animations':
                    $scripts['features'][] = 'aos-init';
                    $scripts['features'][] = 'scroll-animations';
                    break;
                    
                case 'woocommerce':
                    $scripts['features'][] = 'wc-add-to-cart';
                    $scripts['features'][] = 'wc-cart-fragments';
                    break;
            }
        }
        
        return $scripts;
    }
    
    /**
     * Debug bilgisi döndürür
     */
    public function get_debug_info() {
        return [
            'page_type' => $this->page_type,
            'required_features' => $this->required_features,
            'required_scripts' => $this->get_required_scripts(),
            'content_length' => strlen($this->post_content)
        ];
    }
    
    /**
     * Kullanılmayan script'leri loglar (debug için)
     */
    public function log_unused_scripts() {
        if (!WP_DEBUG) return;
        
        $all_possible_scripts = [
            'hero-animations', 'home-slider', 'product-gallery', 'product-zoom',
            'add-to-cart-enhanced', 'product-filters', 'ajax-pagination', 
            'infinite-scroll', 'cart-updates', 'shipping-calculator',
            'checkout-validation', 'payment-methods', 'search-filters',
            'ajax-search', 'reading-progress', 'social-share',
            'contact-form-handler', 'form-validation', 'leaflet-maps',
            'map-interactions', 'swiper-init', 'slider-controls',
            'plyr-init', 'video-controls', 'photoswipe-init',
            'gallery-navigation', 'chart-js', 'data-visualization',
            'aos-init', 'scroll-animations', 'wc-add-to-cart', 'wc-cart-fragments'
        ];
        
        $required = $this->get_required_scripts();
        $loaded_scripts = array_merge(
            $required['core'] ?? [],
            $required['page'] ?? [],
            $required['features'] ?? []
        );
        
        $unused_scripts = array_diff($all_possible_scripts, $loaded_scripts);
        
        error_log(sprintf(
            '[ConditionalJSLoader] Page: %s | Loaded: %d scripts | Unused: %d scripts | Savings: %.1f%%',
            $this->page_type,
            count($loaded_scripts),
            count($unused_scripts),
            (count($unused_scripts) / count($all_possible_scripts)) * 100
        ));
    }
}