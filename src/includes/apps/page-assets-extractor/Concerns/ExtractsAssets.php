<?php

use MatthiasMullie\Minify;
use voku\helper\HtmlDomParser;
use Irmmr\RTLCss\Parser as RTLParser;

/**
 * ExtractsAssets - PAE Cekirdek Asset Cikarma Trait
 *
 * HTML'den CSS/JS asset'lerini cikarir, plugin bundle'larini olusturur,
 * structure fingerprint hesaplar ve meta'ya kaydeder.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor\Concerns
 * @version    1.0.0
 * @since      1.0.0
 * @author     SaltHareket
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrildi
 *     - Add: CODING_PRINCIPLES uyumlu dokumantasyon eklendi
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sinifi icinde `use ExtractsAssets;` ile kullanilir.
 *   fetch() metodundan cagrilir, HTML DOM'u analiz ederek asset'leri cikarir.
 *
 * @example Asset cikar:
 *   $result = $this->extract_assets($html_content, $post_id);
 *
 * @example HTML'den class listesi cikar:
 *   $classes = $this->extract_class_list_from_html_string($html);
 *
 * @example Structure fingerprint hesapla:
 *   $fp = $this->build_structure_fingerprint(['type' => 'post', 'plugins' => []]);
 *
 * @example JS data duzelt:
 *   $this->fix_js_data($result, ['js', 'plugin_js']);
 *
 * @example JS selector duzelt:
 *   $fixed = $this->fix_js_data_selector($jsCode);
 */

trait ExtractsAssets
{
    public function extract_assets($html_content, $id) {
        $this->error_log("extract_assets START: id={$id} | html length: " . strlen($html_content), 'extract');
        $js = [];
        $css = [];
        //$css_rtl = [];
        $css_page = "";
        $css_page_rtl = "";
        $plugins = [];
        $plugin_js = "";
        $plugin_css = "";
        $plugin_css_rtl = "";
        $wp_js = [];

        // data-template taraması
        $extra_html = $this->collectTwigLoadedHtml($html_content);
        if ($extra_html) {

            $this->error_log('[PAE] collected extra html length=' . strlen($extra_html));

            // Devasa HTML'yi DOM'a ekleme - sadece class ve attr'leri çıkar, skeleton div yap
            $all_classes = [];
            $all_attrs   = [];

            if (preg_match_all('/class=["\']([^"\']+)["\']/i', $extra_html, $class_matches)) {
                foreach ($class_matches[1] as $match) {
                    $split = preg_split('/\s+/', trim($match), -1, PREG_SPLIT_NO_EMPTY);
                    $all_classes = array_merge($all_classes, $split);
                }
            }

            if (preg_match_all('/\s(data-[a-z0-9_-]+|playsinline|autoplay|loop|preload)\b/i', $extra_html, $attr_matches)) {
                $all_attrs = array_unique($attr_matches[1]);
            }

            $clean_classes = implode(' ', array_unique($all_classes));
            $clean_attrs   = '';
            foreach ($all_attrs as $attr) {
                $clean_attrs .= ' ' . $attr . '="1"';
            }

            $extra_html_wrapped = '<div id="__twig_extra__" class="' . $clean_classes . '"' . $clean_attrs . '></div>';

            try {
                $bodyNode = $html_content->find('main', 0);
                if ($bodyNode) {
                    $bodyNode->innertext .= $extra_html_wrapped;
                    $this->error_log('[PAE] Skeleton HTML appended into <main>');
                } else {
                    $html_content->innertext .= $extra_html_wrapped;
                    $this->error_log('[PAE] Skeleton HTML appended into root');
                }
            } catch (\Exception $e) {
                $this->error_log('[PAE] Skeleton eklerken hata: ' . $e->getMessage());
            }

        } else {
            $this->error_log('[PAE] no extra twig html collected');
        }


        // ---------- DOM kırpma ----------
        $html_temp = HtmlDomParser::str_get_html($html_content->__toString());

        $header_node = $html_temp->findOne('#header');
        $header_content = '';
        if ($header_node) { 
            $header_content = $header_node->outerHtml(); 
            $header_node->delete(); 
        }

        $footer_node = $html_temp->findOne('#footer');
        $footer_content = '';
        if ($footer_node) { $footer_content = $footer_node->outerHtml(); $footer_node->delete(); }/**/

        $main_node = $html_temp->findOne('main');
        $main_content = '';
        if ($main_node) { 
            $main_content = $main_node->outerHtml(); 
            $main_node->delete(); 
        }

        $block_content = '';
        $block_node = $html_temp->findOne('.block--hero');
        if ($block_node) { 
            $block_content = $block_node->outerHtml(); 
            $block_node->delete(); 
        }

        $offcanvas_html = [];
        $offcanvas_elements = $html_temp->findMulti('.offcanvas');
        if (!empty($offcanvas_elements)) {
            foreach ($offcanvas_elements as $el) { 
                $offcanvas_html[] = $el->outerHtml(); 
            }
        }
        $offcanvas_string = implode("\n", $offcanvas_html);
        $html_temp = null;

        $final_html_string = $header_content . $main_content . $block_content . $offcanvas_string . $footer_content;
        //$final_html_string = $main_content . $block_content . $offcanvas_string;
        $html = HtmlDomParser::str_get_html($final_html_string);

        /*$theme_dir = get_template_directory();
        $file_path = $theme_dir . '/test.html';
        $success = file_put_contents($file_path, $final_html_string, LOCK_EX);*/

        // ---------- inline <script>/<style> topla ----------
        if ($html) {

            /*$scripts = $html->findMulti('script');
            foreach ($scripts as $script) {
                if ($script->hasAttribute('data-inline')) {
                    continue;
                }
                if (isset($script->src) && !empty($script->src)) {
                    continue;
                }
                $is_type_valid = true;
                if (isset($script->type)) {
                    if (strtolower(trim($script->type)) !== 'text/javascript') {
                        $is_type_valid = false;
                    }
                }
                if (!$is_type_valid) {
                    continue;
                }
                if (is_object($script) && method_exists($script, 'innerHtml')) {
                    $code = trim($script->innerHtml());
                    if ($code !== '') {
                        $js[] = $code;
                    }
                }
            }
            if($js){
                $js = array_unique($js);
                $js = implode("\n", $js);
                $minifier = new Minify\JS();
                $minifier->add($js);
                $js = $minifier->minify();
                $js = str_replace($this->upload_url, "{upload_url}", $js);
                $js = str_replace($this->upload_url_encoded, "{upload_url}", $js);
                $js = str_replace($this->home_url, "{home_url}", $js);
                $js = str_replace($this->home_url_encoded, "{home_url}", $js);
            }*/

            $blocks = $html->findMulti('.block-salt-theme');
            $js_codes = [];
            $js = '';

            if (!empty($blocks)) {
                foreach ($blocks as $block) {
                    
                    // Artık HTML dengeli olduğu için, kütüphane bu aramanın kapsamını 
                    // doğru şekilde $block elementi ile sınırlayacaktır.
                    $scripts = $block->findMulti('script'); 
                    
                    foreach ($scripts as $script) {
                        if ($script->hasAttribute('data-inline')) {
                            continue;
                        }
                        if (isset($script->src) && !empty($script->src)) {
                            continue;
                        }
                        if (isset($script->type) && strtolower(trim($script->type)) !== 'text/javascript') {
                            continue;
                        }
                        if (is_object($script) && method_exists($script, 'innerHtml')) {
                            $code = trim($script->innerHtml());
                            if ($code !== '') {
                                $js_codes[] = $code;
                            }
                        }
                    }
                }
            }

            if (!empty($js_codes)) {
                $js_codes = array_unique($js_codes);
                $js = implode("\n", $js_codes);
                
                $minifier = new Minify\JS();
                $minifier->add($js);
                $js = $minifier->minify();
                
                $js = str_replace([
                    $this->upload_url,
                    $this->upload_url_encoded,
                    $this->home_url,
                    $this->home_url_encoded
                ], [
                    "{upload_url}",
                    "{upload_url}",
                    "{home_url}",
                    "{home_url}"
                ], $js);
            }

            $styles = $html->findMulti('style');
            $styles_filtered = [];
            foreach ($styles as $style) {
                if (!$style->hasAttribute('data-inline')) { 
                    $styles_filtered[] = $style; 
                }
            }
            foreach ($styles_filtered as $style) {
                $code = $style->innerHtml();
                if ($code !== '') { 
                    $css[] = $code; 
                }
            }
            if($css){
                $css = array_unique($css);
                $css = implode("\n", $css);
                $minifier = new Minify\CSS();
                $minifier->add($css);
                $css = $minifier->minify();
                //$css = str_replace($this->upload_url, "{upload_url}", $css);
                //$css = str_replace($this->upload_url_encoded, "{upload_url}", $css);
                //$css = str_replace($this->home_url, "{home_url}", $css);
                //$css = str_replace($this->home_url_encoded, "{home_url}", $css);

                /*// RTL üretimi
                $parser = new \Sabberworm\CSS\Parser($css);
                $tree   = $parser->parse();
                $rtlcss = new RTLParser($tree);
                $rtlcss->flip();
                $css_rtl = $tree->render();*/
            }
        }

        // ---------- koşullu plugin map ----------
        if (!function_exists("compile_files_config")) {
            require SH_INCLUDES_PATH . "minify-rules.php";
        }

        $files = compile_files_config(true);

        if (!empty($files["js"]["plugins"])) {
            foreach ($files["js"]["plugins"] as $key => $plugin) {
                if (!empty($plugin['c'])) {
                    $condition = isset($plugin['condition']) ? $plugin['condition'] : 1;

                    if (!empty($plugin['class'])) {
                        foreach ($plugin['class'] as $class) {
                            $pattern = '/class\s*=\s*["\'][^"\']*\b' . preg_quote($class, '/') . '\b[^"\']*["\']/i';
                            $matches = [];
                            $exists = preg_match($pattern, $final_html_string, $matches);
                            $this->error_log($key." için ".$class." varmı = ".($exists ? 'true' : 'false'));
                            /*if ($exists) {
                                $matched_html = $matches[0];
                                $this->error_log(" | EŞLEŞEN HTML (Open Tag): " . substr($matched_html, 0, 150) . "...");
                            }*/
                            if ($exists && $condition) { 
                                $plugins[] = $key; 
                                break; 
                            }
                        }
                    }
                    if (!empty($plugin['attrs'])) {
                        foreach ($plugin['attrs'] as $attr) {
                            if (strpos($attr, '=') !== false) {
                                $exists = strpos($final_html_string, $attr) !== false;
                                if ($exists && $condition) { $plugins[] = $key; break; }
                            } else {
                                $pattern = '/\s' . preg_quote($attr, '/') . '\s*=\s*["\'].*?["\']/i';
                                $exists = preg_match($pattern, $final_html_string);
                                if ($exists && $condition) { 
                                    $plugins[] = $key; 
                                    break; 
                                }
                            }
                        }
                    }
                }
            }

            // data-required-js="plugin1,plugin2"
            if (preg_match_all('/\bdata-required-js\s*=\s*(["\'])(.*?)\1/si', $final_html_string, $m)) {
                foreach ($m[2] as $attrVal) {
                    $names = preg_split('/\s*,\s*/', $attrVal, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($names as $name) {
                        $key = trim($name);
                        if ($key !== '' && isset($files['js']['plugins'][$key])) {
                            $plugins[] = $key;
                        }
                    }
                }
            }

            if($plugins){ $plugins = array_values(array_unique($plugins)); }

            $this->error_log($plugins);

            // REQUIRED bağımlılıklarını genişlet
            $plugins = $this->expand_required_plugins($plugins, $files['js']['plugins']);

            // Expand sonrası tekrar unique — güvenlik
            $plugins = array_values(array_unique($plugins));
        }

        // WP kısa kod bazlı JS ekleri
        $shortcodes = ['contact_form', 'contact-form-7', 'form_modal', 'wpsr_share_icons', 'newsletter'];
        foreach($shortcodes as $sc){
            if (strpos($final_html_string, $sc) !== false) {
                $wp_js[] = ($sc === 'form_modal') ? 'contact-form-7' : $sc;
            }
        }
        $wp_js = array_values(array_unique($wp_js));

        // plugin whitelist (CSS purge için)
        $plugin_files_whitelist = [];
        if (!empty($plugins) && !empty($files["js"]["plugins"])) {
            foreach($plugins as $plg){
                if(!empty($files["js"]["plugins"][$plg]["whitelist"])){
                    $plugin_files_whitelist = array_merge($plugin_files_whitelist, $files["js"]["plugins"][$plg]["whitelist"]);
                }
            }
            $plugin_files_whitelist = array_values(array_unique($plugin_files_whitelist));
        }

        // Pagination whitelist: arşiv/search/term sayfalarında pagination class'larını koru
        $post_type_for_pagination = $this->detect_post_type($id);
        $pagination_config = class_exists('Data') ? \Data::get("post_pagination") : [];
        // Admin context'te Data boş olabilir - doğrudan option'dan oku
        if (empty($pagination_config) && function_exists('get_field')) {
            $raw = get_field('post_pagination', 'options');
            if (is_array($raw)) {
                $pagination_config = [];
                foreach ($raw as $item) {
                    if (!isset($item['post_type'])) continue;
                    $pt = $item['post_type'];
                    unset($item['post_type']);
                    $pagination_config[$pt] = $item;
                }
                // Search pagination
                $search = get_field('search_pagination', 'options');
                if ($search && !empty($search['paged'])) {
                    $pagination_config['search'] = $search;
                }
            }
        }
        if (is_array($pagination_config)) {
            $needs_pagination = false;

            // Archive sayfası
            if (in_array($this->type, ['archive', 'dynamic'], true)) {
                $needs_pagination = true;
            }
            // Term sayfası
            elseif ($this->type === 'term') {
                $needs_pagination = true;
            }
            // Search
            elseif ($this->type === 'dynamic' || (is_string($id) && strpos($id, 'search') !== false)) {
                $needs_pagination = !empty($pagination_config['search']['paged']);
            }
            // Post type (single değil, archive context'te)
            elseif (!empty($post_type_for_pagination) && !empty($pagination_config[$post_type_for_pagination]['paged'])) {
                $needs_pagination = true;
            }

            // WooCommerce: Mağaza sayfası page olarak gelir ama product arşivi
            if (!$needs_pagination && is_numeric($id)) {
                if (function_exists('wc_get_page_id')) {
                    $shop_page_id = wc_get_page_id('shop');
                    $this->error_log("[PAE] WC shop check: id={$id} shop_page_id={$shop_page_id} product_paged=" . (!empty($pagination_config['product']['paged']) ? 'yes' : 'no'), 'extract');
                    if ($shop_page_id && (int) $id === (int) $shop_page_id && !empty($pagination_config['product']['paged'])) {
                        $needs_pagination = true;
                        $this->error_log("[PAE] WooCommerce shop page detected as product archive: id={$id}", 'extract');
                    }
                } else {
                    $this->error_log("[PAE] wc_get_page_id not available", 'extract');
                }
            }

            $this->error_log("[PAE] Pagination check: type={$this->type} post_type={$post_type_for_pagination} needs_pagination=" . ($needs_pagination ? 'yes' : 'no'), 'extract');

            if ($needs_pagination) {
                $pagination_whitelist = [
                    '.pagination', '.pagination-container', '.page-item', '.page-link',
                    '.page-item-ajax', '.btn-pagination-ajax', '.card-footer',
                    '.page-item.active', '.page-item.prev', '.page-item.next',
                    '.page-item.invisible', '.btn-loading-page',
                ];
                $plugin_files_whitelist = array_merge($plugin_files_whitelist, $pagination_whitelist);
                $plugin_files_whitelist = array_values(array_unique($plugin_files_whitelist));
                $this->error_log("[PAE] Pagination whitelist eklendi: type={$this->type} post_type={$post_type_for_pagination}", 'extract');
            }
        }

        // WooCommerce whitelist: WC sayfalarinda dinamik olarak degisen class'lari koru
        if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('wc_get_page_id')) {
            $wc_page_type = '';
            $wc_whitelist = [];

            if (is_numeric($id)) {
                $int_id = (int) $id;
                $shop_id     = (int) wc_get_page_id('shop');
                $cart_id     = (int) wc_get_page_id('cart');
                $checkout_id = (int) wc_get_page_id('checkout');
                $account_id  = (int) wc_get_page_id('myaccount');

                if ($int_id === $cart_id)     $wc_page_type = 'cart';
                elseif ($int_id === $checkout_id) $wc_page_type = 'checkout';
                elseif ($int_id === $account_id)  $wc_page_type = 'myaccount';
                elseif ($int_id === $shop_id)     $wc_page_type = 'shop';
            }

            // Product single veya product archive
            if ($post_type_for_pagination === 'product' || $wc_page_type === 'shop') {
                $wc_page_type = $wc_page_type ?: 'product';
            }

            // Tum WC sayfalari icin ortak class'lar
            if ($wc_page_type) {
                $wc_whitelist = [
                    // WooCommerce genel
                    '.woocommerce', '.woocommerce-*',
                    '.wc-*',
                    '.woocommerce-error', '.woocommerce-info', '.woocommerce-message',
                    '.woocommerce-notices-wrapper',
                    '.woocommerce-result-count',
                    '.woocommerce-ordering',
                    '.woocommerce-pagination',
                    // Fiyat
                    '.price', '.price-*',
                    '.woocommerce-Price-amount', '.woocommerce-Price-currencySymbol',
                    '.amount',
                    // Urun genel
                    '.product', '.product-*', '.products',
                    '.type-product',
                    // Badge
                    '.onsale', '.sale', '.badge-sale', '.badge-new',
                    '.product-badge', '.product-badge-*', '.product-badges',
                    // Stok
                    '.stock', '.in-stock', '.out-of-stock', '.low-stock', '.onbackorder',
                    // Sepete ekle
                    '.add_to_cart_button', '.added_to_cart', '.added',
                    '.single_add_to_cart_button',
                    '.cart', '.cart-*',
                    // Miktar
                    '.quantity', '.qty',
                    '.quantity-*',
                ];
            }

            // Sayfa tipine ozel whitelist
            switch ($wc_page_type) {
                case 'cart':
                    $wc_whitelist = array_merge($wc_whitelist, [
                        '.woocommerce-cart-form',
                        '.shop_table', '.shop_table-*',
                        '.cart_item', '.cart-item-*',
                        '.product-remove', '.product-thumbnail', '.product-name', '.product-price', '.product-quantity', '.product-subtotal',
                        '.cart-empty', '.return-to-shop',
                        '.cart-collaterals', '.cart_totals',
                        '.shipping-*', '.shipping',
                        '.order-total',
                        '.coupon', '.coupon-*',
                        '.cross-sells', '.cross-sells-*',
                        '.checkout-button',
                        '.cart-discount', '.cart-subtotal',
                    ]);
                    break;

                case 'checkout':
                    $wc_whitelist = array_merge($wc_whitelist, [
                        '.woocommerce-checkout',
                        '.checkout', '.checkout-*',
                        '.woocommerce-checkout-review-order',
                        '.woocommerce-checkout-review-order-table',
                        '.woocommerce-checkout-payment',
                        '.payment_methods', '.payment_method', '.payment_method-*',
                        '.payment_box', '.payment_box-*',
                        '.wc_payment_method',
                        '.place-order',
                        '.form-row', '.form-row-*',
                        '.woocommerce-billing-fields', '.woocommerce-shipping-fields',
                        '.woocommerce-additional-fields',
                        '.woocommerce-input-wrapper',
                        '.woocommerce-validated', '.woocommerce-invalid', '.woocommerce-invalid-*',
                        '.order-review', '.order-total',
                        '.shipping-*', '.shipping',
                    ]);
                    break;

                case 'myaccount':
                    $wc_whitelist = array_merge($wc_whitelist, [
                        '.woocommerce-MyAccount-navigation', '.woocommerce-MyAccount-content',
                        '.woocommerce-orders-table', '.woocommerce-orders-table-*',
                        '.woocommerce-order-details',
                        '.woocommerce-account',
                        '.woocommerce-form-login', '.woocommerce-form-register',
                        '.woocommerce-form-*',
                        '.woocommerce-address-fields',
                        '.woocommerce-EditAccountForm',
                        '.order-*', '.order-status',
                    ]);
                    break;

                case 'shop':
                case 'product':
                    $wc_whitelist = array_merge($wc_whitelist, [
                        // Arsiv/loop
                        '.woocommerce-loop-product-*',
                        '.product-category',
                        // Filtre
                        '.widget_price_filter', '.price_slider', '.price_slider-*',
                        '.widget_layered_nav', '.widget_layered_nav-*',
                        '.woocommerce-widget-layered-nav-*',
                        // Siralama
                        '.orderby',
                        // Varyasyon (single product)
                        '.variations', '.variations_form', '.variation', '.variation-*',
                        '.single_variation', '.single_variation_wrap',
                        '.woocommerce-variation', '.woocommerce-variation-*',
                        '.reset_variations',
                        // Galeri
                        '.woocommerce-product-gallery', '.woocommerce-product-gallery-*',
                        '.flex-*',
                        // Tabs
                        '.woocommerce-tabs', '.wc-tab', '.wc-tab-*',
                        // Review
                        '.woocommerce-Reviews', '.comment-form-rating',
                        '.star-rating', '.star-rating-*',
                        // Related/Upsell
                        '.related', '.upsells', '.cross-sells',
                        // Grouped
                        '.grouped_form', '.woocommerce-grouped-product-list',
                        // Bundle
                        '.bundled_product', '.bundled_product-*',
                    ]);
                    break;
            }

            if (!empty($wc_whitelist)) {
                $plugin_files_whitelist = array_merge($plugin_files_whitelist, $wc_whitelist);
                $plugin_files_whitelist = array_values(array_unique($plugin_files_whitelist));
                $this->error_log("[PAE] WooCommerce whitelist eklendi: wc_page_type={$wc_page_type} (" . count($wc_whitelist) . " class)", 'extract');
            }
        }

        $post_type_val = $this->detect_post_type($id);
        $template = '';
        if ($this->type === 'post' && $post_type_val === 'page' && function_exists('get_page_template_slug')) {
            $template = (string) get_page_template_slug($id);
        }
        $dom_classes = $this->extract_class_list_from_html_string($final_html_string);
        $dir = function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr';

        $this->structure_fp = $this->build_structure_fingerprint([
            'type'      => $this->type,
            'post_type' => $post_type_val,
            'template'  => $template,
            'plugins'   => $plugins,
            'wp_js'     => $wp_js,
            'whitelist' => $plugin_files_whitelist,
            'classes'   => $dom_classes,
            'dir'       => $dir,
        ]);

        // Eski dosya/meta temizliği
        $this->delete_existing_assets($id);

        // Content usage key
        $content_usage_key = $this->type . ':' . $id;

        /* --------- EARLY RETURN: içerik değişmediyse rebuild etme --------- */
        if (!$this->force_rebuild) {
            $prev_usage = $this->manifest['content_usage'][$content_usage_key] ?? null;
            if ($prev_usage
                && ($prev_usage['structure_fp'] ?? '') === $this->structure_fp
                && ($prev_usage['plugins_key']  ?? '') !== ''
            ) {
                $pk  = $prev_usage['plugins_key'];
                $pm  = $this->manifest['plugins'][$pk] ?? null;
                $tm  = $this->manifest['templates'][$this->structure_fp] ?? null;

                // Mtime kontrolü: plugin kaynak dosyaları değişmiş mi?
                $mtime_ok = true;
                if ($pm && !empty($pm['plugins_list'])) {
                    $check_files = [];
                    foreach ($pm['plugins_list'] as $p) {
                        $check_files[] = STATIC_PATH . 'js/plugins/' . $p . '.js';
                        $check_files[] = STATIC_PATH . 'js/plugins/' . $p . '-init.js';
                        $check_files[] = STATIC_PATH . 'js/plugins/' . $p . '.css';
                    }
                    $current_mtime = $this->source_files_mtime_hash($check_files);
                    $expected_key = sha1(json_encode($pm['plugins_list']) . '|' . $current_mtime);
                    if ($expected_key !== $pk) {
                        $mtime_ok = false;
                        $this->error_log("[PAE] EARLY RETURN BLOCKED - plugin source files changed. id={$id} old_key={$pk} new_key={$expected_key}");
                    }
                }

                $all_files_ok = $mtime_ok && $pm && $tm
                    && $this->file_exists_rel($pm['css']  ?? '')
                    && $this->file_exists_rel($pm['js']   ?? '')
                    && $this->file_exists_rel($tm['css']  ?? '')
                    && $this->file_exists_rel($tm['css_rtl'] ?? '');
                if ($all_files_ok) {
                    $this->error_log("[PAE] EARLY RETURN – içerik değişmedi, dosyalar mevcut. id={$id}");
                    $existing_meta = $this->meta_get($this->type, $id) ?: [];
                    return $this->save_meta(array_merge($existing_meta, [
                        'plugins'       => $plugins,
                        'plugin_js'     => $pm['js'],
                        'plugin_css'    => $pm['css'],
                        'plugin_css_rtl'=> $pm['css_rtl'] ?? '',
                        'css_page'      => $tm['css'],
                        'css_page_rtl'  => $tm['css_rtl'],
                        'wp_js'         => $wp_js,
                    ]), $id);
                }
            }
        }

        /* --------- PLUGIN BUNDLE (manifest + FS-check + mtime-aware) --------- */
        $plugins_key = '';
        if(!empty($plugins) && !empty($files["js"]["plugins"])){

            $this->error_log("Plugins boş diil ");

            // c:false olan plugin'ler zaten her sayfada yükleniyor — bundle'a ekleme
            // condition:0 olan plugin'ler de bundle'a girmesin
            $plugins = array_values(array_filter($plugins, function($p) use ($files) {
                if (empty($files["js"]["plugins"][$p]["c"])) return false;
                $condition = $files["js"]["plugins"][$p]["condition"] ?? 1;
                return !empty($condition);
            }));

            // mtime hash: kaynak dosyalar değişince aynı plugin listesi için yeni bundle
            $plugin_files_css_src = [];
            $plugin_files_css_rtl_src = [];
            $plugin_files_js_src = [];
            foreach($plugins as $plugin){
                if(!empty($files["js"]["plugins"][$plugin]["css"])){
                    $plugin_files_css_src[]     = STATIC_PATH . 'js/plugins/'.$plugin.".css";
                    $plugin_files_css_rtl_src[] = STATIC_PATH . 'js/plugins/'.$plugin."-rtl.css";
                }
                $plugin_files_js_src[] = STATIC_PATH . 'js/plugins/'.$plugin.".js";
                $plugin_files_js_src[] = STATIC_PATH . 'js/plugins/'.$plugin."-init.js";
            }
            $src_mtime = $this->source_files_mtime_hash(array_merge($plugin_files_css_src, $plugin_files_js_src));
            $plugins_key = sha1(json_encode($plugins) . '|' . $src_mtime);

            $plugin_manifest = $this->manifest['plugins'][$plugins_key] ?? null;

            $need_rebuild_plugin = true;
            if ($plugin_manifest && !$this->force_rebuild) {
                $mc = $plugin_manifest['css']     ?? '';
                $mr = $plugin_manifest['css_rtl'] ?? '';
                $mj = $plugin_manifest['js']      ?? '';

                $has_css     = !$mc || $this->file_exists_rel($mc);
                $has_css_rtl = !$mr || $this->file_exists_rel($mr);
                $has_js      = !$mj || $this->file_exists_rel($mj);

                if ($has_css && $has_css_rtl && $has_js) {
                    $plugin_css     = $mc;
                    $plugin_css_rtl = $mr;
                    $plugin_js      = $mj;
                    $need_rebuild_plugin = false;
                    $this->error_log("[PAE] Plugin bundle cache HIT: {$plugins_key}");
                }
            }

            if ($need_rebuild_plugin) {
                $this->error_log("[PAE] Plugin bundle REBUILD: {$plugins_key}");

                // Eski plugins_key'i kullanan başka content var mı? Yoksa eski dosyaları sil
                // Mass mode'da silme - mass bitince orphan cleanup temizler
                $old_plugins_key = $this->manifest['content_usage'][$content_usage_key]['plugins_key'] ?? '';
                if ($old_plugins_key && $old_plugins_key !== $plugins_key && !$this->mass) {
                    $this->_maybe_delete_plugin_bundle($old_plugins_key);
                }

                if(!empty($plugin_files_css_src)){
                    $plugin_css = $this->combine_and_cache_files("css", $plugin_files_css_src, $plugin_files_whitelist);
                    $plugin_css = str_replace(STATIC_URL, '', $plugin_css);
                }
                if(!empty($plugin_files_css_rtl_src)){
                    $plugin_css_rtl = $this->combine_and_cache_files("css", $plugin_files_css_rtl_src, $plugin_files_whitelist);
                    $plugin_css_rtl = str_replace(STATIC_URL, '', $plugin_css_rtl);
                }

                $plugin_files_js_src = array_filter($plugin_files_js_src, 'file_exists');
                if($plugin_files_js_src){
                    $plugin_js = $this->combine_and_cache_files("js", array_values($plugin_files_js_src));
                                 $this->combine_and_cache_modules($plugins, $plugin_js);
                    $plugin_js = str_replace(STATIC_URL, '', $plugin_js);
                }

                $this->manifest['plugins'][$plugins_key] = [
                    'css'          => $plugin_css ?? '',
                    'css_rtl'      => $plugin_css_rtl ?? '',
                    'js'           => $plugin_js ?? '',
                    'plugins_list' => $plugins, // hangi plugin'leri içerdiği
                    'contents'     => [],
                ];
                $this->manifest_write();

                // CASCADE: bu plugin'lerden herhangi birini içeren diğer bundle'ları da yeniden oluştur
                // Mass mode'da cascade gereksiz - her sayfa kendi bundle'ını zaten oluşturacak
                if (!$this->mass) {
                    $this->cascade_rebuild_bundles($plugins, $plugins_key);
                }
            }

            // Bu content'i bundle'ın kullanıcı listesine ekle
            if (!in_array($content_usage_key, $this->manifest['plugins'][$plugins_key]['contents'] ?? [])) {
                $this->manifest['plugins'][$plugins_key]['contents'][] = $content_usage_key;
            }
        }

        /* --------- TEMPLATE/PAGE PRUNED CSS (manifest + FS-check) + RTL --------- */
        if($html_content){

            $this->error_log("HTML var ");

            $tpl_manifest = $this->manifest['templates'][$this->structure_fp] ?? null;
            $need_rebuild_tpl = true;

            if ($tpl_manifest && !$this->force_rebuild) {
                $mc = $tpl_manifest['css']     ?? '';
                $mr = $tpl_manifest['css_rtl'] ?? '';

                $has_css     = $mc && $this->file_exists_rel($mc);
                $has_css_rtl = $mr && $this->file_exists_rel($mr);

                if ($has_css && $has_css_rtl) {
                    $css_page     = $mc;
                    $css_page_rtl = $mr;
                    $need_rebuild_tpl = false;
                }
            }

            if ($need_rebuild_tpl) {

                $this->error_log("need_rebuild_tpl ");

                $css_page_raw = $this->remove_unused_css_cached($html_content, "", $plugin_files_whitelist);

                $cache_dir = rtrim(STATIC_PATH, '/').'/css/cache/';
                if (!is_dir($cache_dir)) { wp_mkdir_p($cache_dir); }

                $css_page_hash = $this->content_hash($css_page_raw, 'css');
                $css_page_file = $cache_dir . $css_page_hash . '.css';
                if (!file_exists($css_page_file)) {
                    @file_put_contents($css_page_file, $this->normalize_content($css_page_raw, 'css'));
                }
                $css_page = str_replace(STATIC_PATH, '', $css_page_file);

                // RTL üretimi
                $parser = new \Sabberworm\CSS\Parser($css_page_raw);
                $tree   = $parser->parse();

                $rtlcss = new RTLParser($tree);
                $rtlcss->flip();
                $css_page_rtl_raw = $tree->render();

                $css_page_rtl_hash = $this->content_hash($css_page_rtl_raw, 'css');
                $css_page_rtl_file = $cache_dir . $css_page_rtl_hash . '.css';
                if (!file_exists($css_page_rtl_file)) {
                    @file_put_contents($css_page_rtl_file, $this->normalize_content($css_page_rtl_raw, 'css'));
                }
                $css_page_rtl = str_replace(STATIC_PATH, '', $css_page_rtl_file);

                $this->manifest['templates'][$this->structure_fp] = [
                    'css'     => $css_page,
                    'css_rtl' => $css_page_rtl,
                    'plugins' => $plugins_key,
                    'critical_css' => 'css/cache/' . $this->structure_fp . '-critical.css',
                ];
                $this->manifest_write();
            }
        }

        $result = [
            "js"            => $js,
            "css"           => $css,
            "css_page"      => $css_page,
            "css_page_rtl"  => $css_page_rtl,
            "plugins"       => $plugins,
            "plugin_js"     => $plugin_js ?? '',
            "plugin_css"    => $plugin_css ?? '',
            "plugin_css_rtl"=> $plugin_css_rtl ?? '',
            "wp_js"         => $wp_js,
        ];

        $this->fix_js_data($result, ["js", "plugin_js", "wp_js"]);

        // Content usage manifest'ini güncelle
        $this->manifest['content_usage'][$content_usage_key] = [
            'plugins_key'  => $plugins_key,
            'structure_fp' => $this->structure_fp,
        ];
        $this->manifest_write();

        $this->error_log("PAE result summary: css_page={$result['css_page']} | plugin_css={$result['plugin_css']} | plugins=".count($plugins));

        return $this->save_meta($result, $id);
    }

    // =========================================================

        private function extract_class_list_from_html_string(string $html): array {
        $classes = [];
        if (preg_match_all('/class\s*=\s*(["\'])(.*?)\1/si', $html, $m)) {
            foreach ($m[2] as $chunk) {
                foreach (preg_split('/\s+/', trim($chunk)) as $c) {
                    if ($c !== '') { $classes[] = $c; }
                }
            }
        }
        if (count($classes) > 5000) { $classes = array_slice($classes, 0, 5000); }
        $classes = array_values(array_unique($classes));
        sort($classes);
        return $classes;
    }

    // =========================================================

        private function build_structure_fingerprint(array $parts): string {
        $norm = [];
        $norm['type']      = (string)($parts['type'] ?? '');
        $norm['post_type'] = (string)($parts['post_type'] ?? '');
        $norm['template']  = (string)($parts['template'] ?? '');
        $norm['dir']       = (string)($parts['dir'] ?? (function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr'));

        $plugins = $parts['plugins'] ?? [];
        sort($plugins);                  $norm['plugins'] = $plugins;

        $wp_js = $parts['wp_js'] ?? [];
        sort($wp_js);                    $norm['wp_js'] = $wp_js;

        $wl = $parts['whitelist'] ?? [];
        sort($wl);                       $norm['whitelist'] = $wl;

        $classes = $parts['classes'] ?? [];
        $classes = array_values(array_unique(array_filter(array_map('trim', $classes))));
        sort($classes);                  $norm['classes'] = $classes;

        return sha1(json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // =========================================================

        private function fix_js_data(array &$data, array $js_keys): array {
        $fixed_keys = [];
        foreach ($js_keys as $key) {
            if (!isset($data[$key])) continue;
            $value = $data[$key];
            if (is_array($value)) {
                foreach ($value as $index => $js_code) {
                    if (!is_string($js_code)) continue;
                    $fixed = $this->fix_js_data_selector($js_code);
                    if ($fixed !== $js_code) {
                        $data[$key][$index] = $fixed;
                        $fixed_keys[] = "{$key}[$index]";
                    }
                }
            } elseif (is_string($value)) {
                $fixed = $this->fix_js_data_selector($value);
                if ($fixed !== $value) {
                    $data[$key] = $fixed;
                    $fixed_keys[] = $key;
                }
            }
        }
        return $fixed_keys;
    }

    // =========================================================

        private function fix_js_data_selector(string $js): string {
        $js = preg_replace_callback(
            '/("selector_matches"\s*:\s*)"((?:[^"\\\\]|\\\\.)*)"/',
            function ($m) { $escaped = addcslashes($m[2], '"\\'); return $m[1] . '"' . $escaped . '"'; },
            $js
        );
        return str_replace('</script', '<\/script', $js);
    }

    // =========================================================

    
}
