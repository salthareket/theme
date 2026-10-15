<?php

/**
 * HandlesGrouped - PAE Grouped Fetch & URL Yonetimi Trait
 *
 * Grouped fetch modu, en zengin post/term tespiti, WooCommerce account
 * endpoint yonetimi ve toplu asset uygulama islemlerini kapsar.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor\Concerns
 * @version    1.0.1
 * @since      1.0.0
 * @author     SaltHareket
 *
 * @changelog
 *   1.0.1 - 2026-06-17
 *     - Add: get_grouped_urls() page bloğuna Polylang default lang filtresi
 *            ML sitede sadece default dil sayfaları tabloda listelenir
 *            (fetch sırasında diğer dillere zaten apply edilir)
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrildi
 *     - Add: CODING_PRINCIPLES uyumlu dokumantasyon eklendi
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sinifi icinde `use HandlesGrouped;` ile kullanilir.
 *   Grouped fetch modunda en zengin post/term bulunur, tum gruba asset uygulanir.
 *
 * @example En zengin post bul:
 *   $id = $this->find_richest_post('product');
 *
 * @example Grouped asset uygula:
 *   $count = $this->grouped_apply_assets($source_id, 'product', 'post');
 *
 * @example Tum URL'leri al (grouped modda):
 *   $extractor->grouped_fetch = true;
 *   $urls = $extractor->get_all_urls();
 *
 * @example WooCommerce account URL'leri:
 *   $urls = $extractor->get_woo_account_urls();
 *
 * @example WooCommerce account endpoint'lerini fetch et:
 *   $extractor->fetch_woo_account_endpoints();
 */

trait HandlesGrouped
{
    private function find_richest_post($post_type): ?int {
        global $wpdb;

        // product için: en fazla varyasyon veya galeri resmi içeren ürünü seç
        // Böylece gallery, swatches, variation class'ları da yakalanır
        if ($post_type === 'product') {
            // En fazla varyasyonu olan variable product
            $term_id = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->posts} v ON v.post_parent = p.ID AND v.post_type = 'product_variation' AND v.post_status = 'publish'
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 GROUP BY p.ID
                 ORDER BY COUNT(v.ID) DESC
                 LIMIT 1",
                $post_type
            ));
            if ($term_id) {
                $this->error_log("[PAE] find_richest_post(product): variable product id={$term_id}");

                // Variable product'lar default variation URL'ine redirect yapar.
                // PAE fetch sırasında ?fetch&nocache=true eklenince bu redirect kırılıyor.
                // Çözüm: default variation'ın URL'ini direkt kullan — ama bu ID'yi döndürüyoruz.
                // get_grouped_urls()'da product için URL'i parent URL olarak kullanıyoruz,
                // redirect sorununu önlemek için ?is_variation=1 ekle ya da simple product'a düş.
                // Alternatif: en fazla galeriye sahip simple product'ı seç (daha güvenli fetch)
                $simple_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT p.ID
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->posts} v ON v.post_parent = p.ID AND v.post_type = 'product_variation'
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_product_image_gallery'
                     WHERE p.post_type = %s AND p.post_status = 'publish'
                       AND v.ID IS NULL
                       AND pm.meta_value != ''
                     ORDER BY CHAR_LENGTH(pm.meta_value) DESC
                     LIMIT 1",
                    $post_type
                ));

                // Simple product varsa onu tercih et (redirect riski yok)
                // Variable product daha zengin CSS class'lar içerse bile
                // fetch başarısız olursa işe yaramaz
                if ($simple_id) {
                    $this->error_log("[PAE] find_richest_post(product): using simple product id={$simple_id} (avoids variable redirect)");
                    return (int) $simple_id;
                }

                // Simple product yoksa variable product'ı kullan ama ID'yi döndür
                // get_grouped_urls() URL üretirken permalink kullanacak
                return (int) $term_id;
            }

            // Varyasyon yoksa en fazla galeri resmi olan ürün
            $term_id = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_product_image_gallery'
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                   AND pm.meta_value != ''
                 ORDER BY CHAR_LENGTH(pm.meta_value) DESC
                 LIMIT 1",
                $post_type
            ));
            if ($term_id) {
                $this->error_log("[PAE] find_richest_post(product): gallery product id={$term_id}");
                return (int) $term_id;
            }
        }

        // Diğer post type'lar için içerik uzunluğuna göre seç
        return ($id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY CHAR_LENGTH(post_content) DESC LIMIT 1", $post_type
        ))) ? (int) $id : null;
    }

    // =========================================================

        private function find_richest_term($taxonomy): ?int {
        global $wpdb;

        // product_cat için: alt kategorisi olmayan (leaf) ve en fazla direkt ürün içeren term
        // Alt kategorisi olan kategoriler sayfada ürün yerine alt kategori listesi gösterebilir
        if ($taxonomy === 'product_cat' || $taxonomy === 'product_tag') {

            // Önce: alt kategorisi olmayan (leaf) term'ler arasından en fazla direkt ürün içereni bul
            $term_id = $wpdb->get_var($wpdb->prepare(
                "SELECT tt.term_id
                 FROM {$wpdb->term_taxonomy} tt
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 WHERE tt.taxonomy = %s
                   AND p.post_type = 'product'
                   AND p.post_status = 'publish'
                   AND tt.term_id NOT IN (
                       SELECT DISTINCT parent FROM {$wpdb->term_taxonomy}
                       WHERE taxonomy = %s AND parent > 0
                   )
                 GROUP BY tt.term_id
                 ORDER BY COUNT(tr.object_id) DESC
                 LIMIT 1",
                $taxonomy,
                $taxonomy
            ));

            if ($term_id) {
                $this->error_log("[PAE] find_richest_term({$taxonomy}): leaf term_id={$term_id}");
                return (int) $term_id;
            }

            // Leaf bulunamazsa (tüm kategoriler alt kategori içeriyorsa) direkt ürün içeren herhangi birini al
            $term_id = $wpdb->get_var($wpdb->prepare(
                "SELECT tt.term_id
                 FROM {$wpdb->term_taxonomy} tt
                 INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 WHERE tt.taxonomy = %s
                   AND p.post_type = 'product'
                   AND p.post_status = 'publish'
                 GROUP BY tt.term_id
                 ORDER BY COUNT(tr.object_id) DESC
                 LIMIT 1",
                $taxonomy
            ));

            if ($term_id) {
                $this->error_log("[PAE] find_richest_term({$taxonomy}): fallback term_id={$term_id}");
                return (int) $term_id;
            }
        }

        // Diğer taxonomy'ler için standart count bazlı seçim
        // category taxonomy'sinde default category'yi atla (disable_default_cat açıksa)
        if ( in_array($taxonomy, ['category', 'product_cat'], true) && defined('DISABLE_DEFAULT_CAT') && DISABLE_DEFAULT_CAT ) {
            $option_key  = $taxonomy === 'product_cat' ? 'default_product_cat' : 'default_category';
            $wp_default  = (int) get_option($option_key, 0);
            $exclude_ids = [];
            if ( $wp_default > 0 ) {
                $exclude_ids = function_exists('pll_get_term_translations')
                    ? array_values( pll_get_term_translations( $wp_default ) )
                    : [ $wp_default ];
                $exclude_ids = array_filter( array_map('intval', $exclude_ids) );
            }
            if ( ! empty($exclude_ids) ) {
                $placeholders = implode(',', array_fill(0, count($exclude_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT t.term_id FROM {$wpdb->terms} t
                     INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                     WHERE tt.taxonomy = %s
                       AND t.term_id NOT IN ({$placeholders})
                     ORDER BY tt.count DESC LIMIT 1",
                    array_merge([$taxonomy], $exclude_ids)
                );
                $id = $wpdb->get_var($query);
                if ($id) {
                    $this->error_log("[PAE] find_richest_term(category): skipped default, using term_id={$id}");
                    return (int) $id;
                }
                // Non-default term yok — taxonomy skip edilecek
                return null;
            }
        }

        return ($id = $wpdb->get_var($wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY tt.count DESC LIMIT 1", $taxonomy
        ))) ? (int) $id : null;
    }

    // =========================================================

        public function grouped_apply_assets($source_id, $post_type, $context = 'post'): int {
        $source_assets = $this->meta_get($context, $source_id);
        if (!$source_assets || !is_array($source_assets)) return 0;

        // css_page ve css_page_rtl kasıtlı olarak dışarıda bırakıldı:
        // Her ürünün HTML'i farklı — kaynak ürünün purge edilmiş CSS'i
        // diğer ürünlerde eksik class içerebilir. Bunun yerine plugin bundle'ı
        // (plugin_js/plugin_css) paylaşılır; css_page her ürün için ayrı fetch edilir.
        // wp_js da ürün bazında farklılaşabileceğinden paylaşmıyoruz.
        $shared_keys = ['plugins', 'plugin_js', 'plugin_css', 'plugin_css_rtl', 'structure_fp'];
        $shared_data = array_intersect_key($source_assets, array_flip($shared_keys));

        // css_page'i boş olarak zorla — HandlesFrontendCss'de fallback olarak
        // main-combined.css yüklenecek. Her ürünün fetch sonrası kendi css_page'i oluşacak.
        $shared_data['css_page']     = '';
        $shared_data['css_page_rtl'] = '';
        if (empty($shared_data)) return 0;

        // plugins array'inde duplicate olmasın
        if ( ! empty( $shared_data['plugins'] ) && is_array( $shared_data['plugins'] ) ) {
            $shared_data['plugins'] = array_values( array_unique( $shared_data['plugins'] ) );
        }

        // Manifest: mark this group as using the plugin bundle (orphan protection)
        // Page'ler grouped değil — her biri kendi CSS'ini kullanır
        $content_usage_key = "grouped:{$context}:{$post_type}";
        if ($context !== 'page' && !empty($source_assets['structure_fp'])) {
            $this->manifest['content_usage'][$content_usage_key] = [
                'structure_fp' => $source_assets['structure_fp'],
                'plugins_key'  => '',
                'source_id'    => $source_id,
                'last_fetched' => time(),
            ];
            // Find and mark plugins_key
            foreach ($this->manifest['plugins'] as $pk => $pm) {
                if (in_array("{$context}:{$source_id}", $pm['contents'] ?? [])) {
                    $this->manifest['content_usage'][$content_usage_key]['plugins_key'] = $pk;
                    if (!in_array($content_usage_key, $pm['contents'])) {
                        $this->manifest['plugins'][$pk]['contents'][] = $content_usage_key;
                    }
                    break;
                }
            }
            $this->manifest_write();
        }

        global $wpdb;
        if ($context === 'post') {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND ID != %d", $post_type, $source_id));
        } elseif ($context === 'term') {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND t.term_id != %d", $post_type, $source_id));
        } else {
            return 0;
        }

        $count = 0;
        foreach ($ids as $item_id) {
            $existing = $this->meta_get($context, $item_id) ?: [];

            $shared_to_apply = $shared_data;

            if ( ! empty( $existing['structure_fp'] ) ) {
                // Bu item daha önce bireysel fetch edilmiş — kendi structure_fp ve css_page'ini koru
                unset( $shared_to_apply['structure_fp'] );
                // css_page: bireysel fetch varsa koru, aksi halde main-combined fallback için boş bırak
                // Eski gruptan kopyalanmış css_page varsa sil — artık geçersiz
                if ( ! empty( $existing['css_page'] ) ) {
                    // Kendi css_page dosyası fiziksel olarak hâlâ var mı kontrol et
                    $css_abs = rtrim( STATIC_PATH, '/' ) . '/' . ltrim( $existing['css_page'], '/' );
                    if ( file_exists( $css_abs ) ) {
                        // Geçerli bireysel css var — koru
                        unset( $shared_to_apply['css_page'] );
                        unset( $shared_to_apply['css_page_rtl'] );
                    }
                    // Yoksa shared_data'daki boş değer uygulanacak → fallback devreye girer
                }
            } elseif ( ! empty( $source_assets['structure_fp'] ) ) {
                // Hiç fetch edilmemiş — structure_fp'yi source'tan al
                $shared_to_apply['structure_fp'] = $source_assets['structure_fp'];
            }

            $merged = array_replace_recursive(self::ASSETS_STRUCTURE, $existing, $shared_to_apply);
            if (!isset($merged['meta'])) $merged['meta'] = ['type' => $context, 'id' => $item_id];
            if (!isset($merged['lcp'])) $merged['lcp'] = ['desktop' => [], 'mobile' => []];
            $this->meta_update($context, $item_id, $merged);

            // Polylang: copy to translations
            if (function_exists('pll_default_language')) {
                $prev_type = $this->type;
                $this->type = $context;
                $this->maybe_copy_meta_to_translations((int) $item_id, $merged);
                $this->type = $prev_type;
            }

            $count++;
        }
        $this->error_log("[PAE] grouped_apply_assets: {$count} items updated from #{$source_id} ({$post_type})");
        return $count;
    }

    // =========================================================

    private function get_grouped_urls(): array {
        global $wpdb;
        $groups = [];

        // ── Özel sayfa rollerini bir kez topla ───────────────────────────────
        $special_page_roles = $this->_collect_special_page_roles();

        // WooCommerce sayfalarını filtrelemek için ID'leri topla
        $woo_page_ids = [];
        if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && function_exists('wc_get_page_id')) {
            $woo_main_pages = ['shop', 'cart', 'checkout', 'myaccount', 'refund_returns', 'order_received'];
            foreach ($woo_main_pages as $page_key) {
                $page_id = wc_get_page_id($page_key);
                if ($page_id && $page_id > 0) {
                    $woo_page_ids[] = (int) $page_id;
                }
            }
        }

        // Dil sayısını bir kez hesapla — page/archive/dynamic item'larda real_count için
        $global_lang_count = 1;
        if ( function_exists('pll_languages_list') ) {
            $global_lang_count = count( pll_languages_list(['fields' => 'slug']) ) ?: 1;
        }

        foreach (get_post_types(['public' => true], 'objects') as $pt) {
            if ($this->is_post_type_excluded($pt->name)) continue;

            // WooCommerce aktifse product'ı ana tabloya ekleme
            if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && in_array($pt->name, ['product'])) {
                continue;
            }

            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $pt->name));
            if (!$count) continue;

            // Unique count: sadece default dil post'ları
            // Polylang tüm dillerdeki post'ları ayrı row olarak tutar
            $unique_count = $count;
            $lang_count   = 1;
            if ( function_exists('pll_default_language') && function_exists('pll_languages_list') ) {
                $default_lang = pll_default_language();
                $lang_count   = count( pll_languages_list(['fields' => 'slug']) ) ?: 1;
                // Post type çevriliyorsa: Polylang language taxonomy'si atanmış mı?
                // Polylang sadece kayıtlı post type'lara language ekler
                if ( function_exists('pll_is_translated_post_type') && pll_is_translated_post_type( $pt->name ) ) {
                    $unique_count = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(DISTINCT p.ID)
                         FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                         INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                         AND tt.taxonomy = 'language' AND t.slug = %s",
                        $pt->name, $default_lang
                    ) );
                    if ( ! $unique_count ) $unique_count = (int) ceil( $count / $lang_count );
                }
                // Çevrilmeyen post type: count olduğu gibi, lang_count=1
                if ( ! function_exists('pll_is_translated_post_type') || ! pll_is_translated_post_type( $pt->name ) ) {
                    $lang_count = 1;
                    $unique_count = $count;
                }
            }

            // Page'ler gruplanmaz
            // ML site ise sadece default language page'leri listele —
            // Fetch sırasında build_related_assets() diğer dillere zaten apply eder
            if ($pt->name === 'page') {
                $default_lang = $this->pae_lang_default();
                $is_ml        = defined('ENABLE_MULTILANGUAGE') && ENABLE_MULTILANGUAGE;

                $pages = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' ORDER BY menu_order ASC, post_title ASC");
                foreach ($pages as $p) {
                    if (in_array((int) $p->ID, $woo_page_ids)) continue;

                    // ML site + Polylang: sadece default lang sayfaları listele
                    if ( $is_ml && $default_lang && ENABLE_MULTILANGUAGE === 'polylang' && function_exists('pll_get_post_language') ) {
                        $page_lang = pll_get_post_language( (int) $p->ID );
                        // Dil yoksa (false/null) veya default dil değilse atla
                        if ( ! $page_lang || strtolower( $page_lang ) !== strtolower( $default_lang ) ) {
                            continue;
                        }
                    }

                    $url    = get_permalink($p->ID);
                    $title  = get_the_title($p->ID);
                    $groups[] = [
                        'id'          => $p->ID,
                        'type'        => 'page',
                        'post_type'   => 'page',
                        'label'       => $title,
                        'count'       => 1,
                        'real_count'  => $global_lang_count,
                        'url'         => $url,
                        'url_short'   => $url ? str_replace(home_url(), '', $url) : '',
                        'context'     => 'page',
                        'role_badges' => $special_page_roles[(int) $p->ID] ?? [],
                    ];
                }
                continue;
            }

            $rid = $this->find_richest_post($pt->name);
            $url = $rid ? get_permalink($rid) : '';
            $groups[] = [
                'id'          => $rid ?: $pt->name,
                'type'        => 'post',
                'post_type'   => $pt->name,
                'label'       => $pt->labels->name,
                'count'       => $unique_count,
                'real_count'  => $lang_count > 1 ? $count : $unique_count,
                'url'         => $url,
                'url_short'   => $url ? str_replace(home_url(), '', $url) : '',
                'context'     => 'post',
                'role_badges' => [],
            ];
            if ($pt->has_archive && ($au = get_post_type_archive_link($pt->name))) {
                $lang = $this->pae_lang_from_url($au);
                $groups[] = [
                    'id'          => $pt->name . '_archive_' . $lang,
                    'type'        => 'archive',
                    'post_type'   => $pt->name,
                    'label'       => $pt->labels->name . ' Archive',
                    'count'       => 1,
                    'real_count'  => $global_lang_count,
                    'url'         => $au,
                    'url_short'   => str_replace(home_url(), '', $au),
                    'context'     => 'archive',
                    'role_badges' => [],
                ];
            }
        }

        foreach (get_taxonomies(['public' => true], 'objects') as $tax) {
            if ($this->is_taxonomy_excluded($tax->name)) continue;

            // publicly_queryable false ise frontend URL'i yok — skip
            if ( isset( $tax->publicly_queryable ) && $tax->publicly_queryable === false ) continue;

            // rewrite yoksa frontend URL'i yok — skip
            if ( empty( $tax->rewrite ) || $tax->rewrite === false ) continue;

            // WooCommerce aktifse product_cat, product_tag'i ana tabloya ekleme
            if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE && in_array($tax->name, ['product_cat', 'product_tag'])) {
                continue;
            }

            $count = (int) wp_count_terms(['taxonomy' => $tax->name, 'hide_empty' => false]);
            if (!$count) continue;
            $rid = $this->find_richest_term($tax->name);

            // Bulunan term geçerli bir URL'e sahip mi kontrol et
            if ( ! $rid ) continue;
            $url = get_term_link($rid, $tax->name);
            if (is_wp_error($url)) continue;

            // Unique term sayısı — default cat hariç, sadece default dil
            // Polylang aktifse default dil term'lerini say, default cat hariç
            $unique_count = $count;
            $real_count   = $count;
            if ( function_exists('pll_default_language') && function_exists('pll_get_term_language') ) {
                $default_lang = pll_default_language();
                $all_ids = get_terms(['taxonomy' => $tax->name, 'hide_empty' => false, 'fields' => 'ids']);
                $excl = [];
                if ( defined('DISABLE_DEFAULT_CAT') && DISABLE_DEFAULT_CAT ) {
                    $opt = in_array($tax->name, ['category']) ? 'default_category' : 'default_product_cat';
                    $def = (int) get_option($opt, 0);
                    if ( $def > 0 ) {
                        $excl = function_exists('pll_get_term_translations')
                            ? array_values(pll_get_term_translations($def))
                            : [$def];
                    }
                }
                $unique_count = count(array_filter((array)$all_ids, function($tid) use ($default_lang, $excl) {
                    if ( in_array((int)$tid, $excl, true) ) return false;
                    return pll_get_term_language((int)$tid) === $default_lang;
                }));
                // real_count = tüm dillerdeki term'ler, default cat hariç
                $real_count = count(array_filter((array)$all_ids, function($tid) use ($excl) {
                    return ! in_array((int)$tid, $excl, true);
                }));
            }
            if ( $unique_count <= 0 ) continue;

            if ( is_wp_error($url) || empty($url) ) $url = '';
            $groups[] = [
                'id'          => $rid ?: $tax->name,
                'type'        => 'term',
                'post_type'   => $tax->name,
                'label'       => $tax->labels->name,
                'count'       => $unique_count,
                'real_count'  => $real_count,
                'url'         => $url,
                'url_short'   => $url ? str_replace(home_url(), '', $url) : '',
                'context'     => 'term',
                'role_badges' => [],
            ];        }

        $groups[] = [
            'id' => 'search', 'type' => 'dynamic', 'post_type' => 'search',
            'label' => 'Search', 'count' => 1, 'real_count' => 1,
            'url' => get_search_link('turbo-cache-warmup'),
            'url_short' => '/?s=turbo-cache-warmup',
            'context' => 'dynamic',
            'role_badges' => [['label' => 'Search', 'color' => '#0ea5e9', 'icon' => '🔍']],
        ];
        $groups[] = [
            'id' => '404', 'type' => 'dynamic', 'post_type' => '404',
            'label' => '404', 'count' => 1, 'real_count' => 1,
            'url' => site_url('/404-css-cache-trigger'),
            'url_short' => '/404-css-cache-trigger',
            'context' => 'dynamic',
            'role_badges' => [['label' => '404', 'color' => '#ef4444', 'icon' => '⚠️']],
        ];

        // Public profil sayfası — ENABLE_MEMBERSHIP + enable_public_profile aktifse
        if (
            defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP &&
            \SaltHareket\Membership\MembershipSettings::getSetting('enable_public_profile')
        ) {
            $prefix = function_exists('membership_profile_url_prefix') ? rtrim( membership_profile_url_prefix(), '/' ) : 'profile';

            // Fetch için gerçek bir kullanıcı slug'ı lazım — admin'i sample al
            $admin_users = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['user_nicename']]);
            $sample_slug = !empty($admin_users) ? $admin_users[0]->user_nicename : 'sample';

            $profile_url   = trailingslashit( site_url( '/' . $prefix . '/' . $sample_slug ) );
            $profile_short = '/' . $prefix . '/{user}/';  // PAE tablosunda okunabilir gösterim

            $groups[] = [
                'id'          => 'public_profile',
                'type'        => 'dynamic',
                'post_type'   => 'public_profile',
                'label'       => 'Public Profile',
                'count'       => 1,
                'real_count'  => 1,
                'url'         => $profile_url,
                'url_short'   => $profile_short,
                'context'     => 'dynamic',
                'role_badges' => [['label' => 'Profile', 'color' => '#8b5cf6', 'icon' => '👤']],
            ];
        }

        // WooCommerce sayfaları (eğer WooCommerce yüklüyse)
        if (defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE) {
            // WooCommerce My Account endpoint'leri (sanal sayfalar)
            $woo_account_urls = $this->get_woo_account_urls();
            foreach ($woo_account_urls as $option_id => $item) {
                $groups[] = [
                    'id'          => $option_id,
                    'type'        => 'dynamic',
                    'post_type'   => 'woo_account',
                    'label'       => $item['label'],
                    'count'       => 1,
                    'real_count'  => 1,
                    'url'         => $item['url'],
                    'url_short'   => str_replace(home_url(), '', $item['url']),
                    'context'     => 'dynamic',
                    'icon'        => 'woo',
                    'role_badges' => [['label' => 'WC Account', 'color' => '#7f54b3', 'icon' => '👤']],
                ];
            }

            // Ana WooCommerce sayfaları (Shop, Cart, Checkout, My Account, Refund, Order Received)
            $woo_main_pages = [
                'shop'           => ['label' => 'Shop',             'badge' => ['label' => 'WC Shop',     'color' => '#7f54b3', 'icon' => '🛍️']],
                'cart'           => ['label' => 'Cart',             'badge' => ['label' => 'WC Cart',     'color' => '#7f54b3', 'icon' => '🛒']],
                'checkout'       => ['label' => 'Checkout',         'badge' => ['label' => 'WC Checkout', 'color' => '#7f54b3', 'icon' => '💳']],
                'myaccount'      => ['label' => 'My Account',       'badge' => ['label' => 'WC Account',  'color' => '#7f54b3', 'icon' => '👤']],
                'refund_returns' => ['label' => 'Refund & Returns', 'badge' => ['label' => 'WC Refund',   'color' => '#7f54b3', 'icon' => '↩️']],
                'order_received' => ['label' => 'Order Received',   'badge' => ['label' => 'WC Order OK', 'color' => '#7f54b3', 'icon' => '✅']],
            ];

            foreach ($woo_main_pages as $page_key => $page_data) {
                if (function_exists('wc_get_page_id')) {
                    $page_id = wc_get_page_id($page_key);
                    if ($page_id && $page_id > 0) {
                        $url = get_permalink($page_id);
                        if ($url) {
                            $groups[] = [
                                'id'          => $page_id,
                                'type'        => 'page',
                                'post_type'   => 'woo_page',
                                'label'       => $page_data['label'],
                                'count'       => 1,
                                'real_count'  => $global_lang_count,
                                'url'         => $url,
                                'url_short'   => str_replace(home_url(), '', $url),
                                'context'     => 'page',
                                'icon'        => 'woo',
                                'role_badges' => [$page_data['badge']],
                            ];
                        }
                    }
                }
            }

            // Product post type'ını WooCommerce bölümüne ekle (grouped yapıda)
            if (post_type_exists('product')) {
                $product_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
                if ($product_count > 0) {
                    $rid = $this->find_richest_post('product');
                    $url = $rid ? get_permalink($rid) : '';

                    // Variable product ise WC default variation URL'ini kullan
                    // Aksi halde PAE ?fetch&nocache=true ile fetch ederken redirect'e takılıyor
                    if ( $rid && function_exists('wc_get_product') ) {
                        $prod = wc_get_product($rid);
                        if ( $prod && $prod->is_type('variable') ) {
                            $default_attrs = $prod->get_default_attributes();
                            if ( ! empty($default_attrs) ) {
                                // Default variation'ı DB'den direkt bul — get_available_variations() ağır
                                global $wpdb;
                                $variation_id = null;
                                $variations = $wpdb->get_results( $wpdb->prepare(
                                    "SELECT ID FROM {$wpdb->posts}
                                     WHERE post_parent = %d AND post_type = 'product_variation'
                                     AND post_status = 'publish'
                                     LIMIT 20",
                                    $rid
                                ) );
                                foreach ( $variations as $v ) {
                                    $var_attrs = get_post_meta( $v->ID );
                                    $match = true;
                                    foreach ( $default_attrs as $attr => $val ) {
                                        $meta_key = 'attribute_' . $attr;
                                        $var_val  = $var_attrs[ $meta_key ][0] ?? '';
                                        if ( $var_val !== '' && $var_val !== $val ) {
                                            $match = false; break;
                                        }
                                    }
                                    if ( $match ) { $variation_id = (int) $v->ID; break; }
                                }
                                if ( $variation_id ) {
                                    $var_url = get_permalink( $variation_id );
                                    if ( ! $var_url || $var_url === $url ) {
                                        // Variation permalink yok veya parent ile aynı — query string ekle
                                        $var_url = add_query_arg( 'variation_id', $variation_id, $url );
                                    }
                                    $url = $var_url;
                                    $this->error_log("[PAE] product richest: using default variation url={$url}");
                                }
                            }
                        }
                    }

                    // Unique product count — Polylang varsa default dil ürünlerini say
                    $unique_product_count = $product_count;
                    if ( function_exists('pll_default_language') ) {
                        $default_lang = pll_default_language();
                        $unique_product_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                             WHERE p.post_type = 'product' AND p.post_status = 'publish'
                             AND tt.taxonomy = 'language' AND t.slug = %s",
                            $default_lang
                        ));
                        if ( ! $unique_product_count ) $unique_product_count = (int) ceil( $product_count / max(1, count(pll_languages_list(['fields'=>'slug']))) );
                    }

                    $groups[] = [
                        'id'          => $rid ?: 'product',
                        'type'        => 'post',
                        'post_type'   => 'product',
                        'label'       => 'Products',
                        'count'       => $unique_product_count,
                        'real_count'  => $product_count,
                        'url'         => $url,
                        'url_short'   => $url ? str_replace(home_url(), '', $url) : '',
                        'context'     => 'post',
                        'icon'        => 'woo',
                        'woo_section' => true,
                        'role_badges' => [],
                    ];

                    // Product archive — Shop sayfasıyla aynı URL'e düşüyorsa ekleme (duplicate)
                    if (($au = get_post_type_archive_link('product'))) {
                        $shop_url = function_exists('wc_get_page_id')
                            ? trailingslashit( get_permalink( wc_get_page_id('shop') ) )
                            : '';
                        $au_trailing = trailingslashit( $au );
                        if ( $shop_url && rtrim($au_trailing,'/') === rtrim($shop_url,'/') ) {
                            // Shop sayfası zaten listeye eklendi, archive duplicate — skip
                        } else {
                            $lang = $this->pae_lang_from_url($au);
                            $groups[] = [
                                'id'          => 'product_archive_' . $lang,
                                'type'        => 'archive',
                                'post_type'   => 'product',
                                'label'       => 'Products Archive',
                                'count'       => 1,
                                'real_count'  => $global_lang_count,
                                'url'         => $au,
                                'url_short'   => str_replace(home_url(), '', $au),
                                'context'     => 'archive',
                                'icon'        => 'woo',
                                'woo_section' => true,
                                'role_badges' => [],
                            ];
                        }
                    }
                }
            }

            // Product taxonomies'leri WooCommerce bölümüne ekle (grouped yapıda)
            $woo_taxonomies = ['product_cat' => 'Product Categories', 'product_tag' => 'Product Tags'];
            foreach ($woo_taxonomies as $tax_name => $tax_label) {
                if (taxonomy_exists($tax_name)) {
                    $count = (int) wp_count_terms(['taxonomy' => $tax_name, 'hide_empty' => false]);
                    if ($count > 0) {
                        $rid = $this->find_richest_term($tax_name);
                        $url = $rid ? get_term_link($rid, $tax_name) : '';
                        if (!is_wp_error($url) && $url) {
                            $unique_woo_count = $count;
                            $real_woo_count   = $count;
                            if ( function_exists('pll_default_language') && function_exists('pll_get_term_language') ) {
                                $default_lang = pll_default_language();
                                $all_ids = get_terms(['taxonomy' => $tax_name, 'hide_empty' => false, 'fields' => 'ids']);
                                $excl = [];
                                if ( defined('DISABLE_DEFAULT_CAT') && DISABLE_DEFAULT_CAT && $tax_name === 'product_cat' ) {
                                    $def = (int) get_option('default_product_cat', 0);
                                    if ( $def > 0 ) {
                                        $excl = function_exists('pll_get_term_translations')
                                            ? array_values(pll_get_term_translations($def))
                                            : [$def];
                                    }
                                }
                                $unique_woo_count = count(array_filter((array)$all_ids, function($tid) use ($default_lang, $excl) {
                                    if ( in_array((int)$tid, $excl, true) ) return false;
                                    return pll_get_term_language((int)$tid) === $default_lang;
                                }));
                                // real_count = tüm dillerdeki term'ler, default cat hariç
                                $real_woo_count = count(array_filter((array)$all_ids, function($tid) use ($excl) {
                                    return ! in_array((int)$tid, $excl, true);
                                }));
                            }
                            if ( $unique_woo_count > 0 ) {
                                $groups[] = [
                                    'id'          => $rid ?: $tax_name,
                                    'type'        => 'term',
                                    'post_type'   => $tax_name,
                                    'label'       => $tax_label,
                                    'count'       => $unique_woo_count,
                                    'real_count'  => $real_woo_count,
                                    'url'         => $url,
                                    'url_short'   => str_replace(home_url(), '', $url),
                                    'context'     => 'term',
                                    'icon'        => 'woo',
                                    'woo_section' => true,
                                    'role_badges' => [],
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            // WooCommerce yüklü değilse, products post type'ını normal post type olarak ekle
            if (post_type_exists('product')) {
                $product_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
                if ($product_count > 0) {
                    $rid = $this->find_richest_post('product');
                    $url = $rid ? get_permalink($rid) : '';
                    $groups[] = ['id' => $rid ?: 'product', 'type' => 'post', 'post_type' => 'product', 'label' => 'Products', 'count' => $product_count, 'real_count' => $product_count, 'url' => $url, 'url_short' => $url ? str_replace(home_url(), '', $url) : '', 'context' => 'post'];
                    
                    // Product archive
                    if (($au = get_post_type_archive_link('product'))) {
                        $lang = $this->pae_lang_from_url($au);
                        $groups[] = ['id' => 'product_archive_'.$lang, 'type' => 'archive', 'post_type' => 'product', 'label' => 'Products Archive', 'count' => 1, 'real_count' => $global_lang_count, 'url' => $au, 'url_short' => str_replace(home_url(), '', $au), 'context' => 'archive'];
                    }
                }
            }
        }
        return $groups;
    }

    // =========================================================

        public function get_all_urls($sitemap_url = null, &$urls = []) {
        // Grouped fetch mode: return grouped URLs instead
        if ($this->grouped_fetch) {
            return $this->get_grouped_urls();
        }

        // 1. ADIM: Başlangıç Ayarları ve Sanal Sayfaların Enjeksiyonu
        if ($sitemap_url === null) {
            $sitemap_url = function_exists('site_url') ? site_url('/sitemap_index.xml') : '/sitemap_index.xml';

            // Search ve 404 sayfalarını 'archive' yapısında enjekte ediyoruz
            $urls['search'] = [ 
                "url"       => get_search_link('turbo-cache-warmup'), 
                "post_type" => "search", 
                "type"      => "dynamic" 
            ];

            $urls['404'] = [ 
                "url"       => site_url('/404-css-cache-trigger'), 
                "post_type" => "404", 
                "type"      => "dynamic" 
            ];
        }

        static $downloaded = [];
        if (isset($downloaded[$sitemap_url])) return $urls; // Aynı sitemap'i iki kez indirme lan

        $cache_key = 'sh_turbo_sitemap_' . md5($sitemap_url);
        $sitemap_content = get_transient($cache_key);

        if (false === $sitemap_content) {
            $response = wp_remote_get($sitemap_url, ['timeout' => 20, 'sslverify' => false]);
            $sitemap_content = is_wp_error($response) ? @file_get_contents($sitemap_url) : wp_remote_retrieve_body($response);
            if ($sitemap_content) set_transient($cache_key, $sitemap_content, HOUR_IN_SECONDS);
        }

        if (!$sitemap_content) return $urls;

        $xml = @simplexml_load_string($sitemap_content);
        if (!$xml) return $urls;

        $downloaded[$sitemap_url] = true;
        $namespaces = $xml->getDocNamespaces(true);
        $xml->registerXPathNamespace('ns', $namespaces[''] ?? 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $sitemap_path      = parse_url($sitemap_url, PHP_URL_PATH) ?: '';
        $sitemap_file_name = preg_replace('/-sitemap\.xml$/', '', basename($sitemap_path));
        $roles             = method_exists($this, 'get_roles') ? (array) $this->get_roles() : [];

        $sub_sitemaps = $xml->xpath('//ns:sitemap/ns:loc');
        if ($sub_sitemaps) {
            foreach ($sub_sitemaps as $loc) { $this->get_all_urls((string)$loc, $urls); }
            return $urls;
        }

        $url_nodes = $xml->xpath('//ns:url/ns:loc');
        if (!$url_nodes) return $urls;

        if ($this->is_post_type_excluded($sitemap_file_name) || $this->is_taxonomy_excluded($sitemap_file_name)) return $urls;

        foreach ($url_nodes as $url_loc) {
            $url_string = (string)$url_loc;
            $data = [ "url" => $url_string, "post_type" => $sitemap_file_name ];

            if ((!empty($roles) && in_array($sitemap_file_name, $roles, true)) || $sitemap_file_name === 'author') {
                $slug = basename(rtrim($url_string, '/'));
                $user = function_exists('get_user_by') ? get_user_by('slug', $slug) : null;
                if ($user) $urls[$user->ID] = array_merge($data, ["type" => "user"]);
                continue;
            }

            if ($sitemap_file_name === 'post' || $sitemap_file_name === 'page' || post_type_exists($sitemap_file_name)) {
                $post_id = url_to_postid($url_string);
                if (!$post_id && function_exists('getUrlEndpoint')) {
                    if (getUrlEndpoint($url_string) == $sitemap_file_name && $this->pae_is_default_lang_url($url_string)) {
                        $urls[$sitemap_file_name] = array_merge($data, ["type" => "archive"]);
                        continue;
                    }
                }
                if ($post_id) {
                    $urls[$post_id] = array_merge($data, ["type" => "post", "post_type" => get_post_type($post_id)]);
                }
                continue;
            }

            $tax_name = ($sitemap_file_name === 'format') ? 'post_format' : $sitemap_file_name;
            if (taxonomy_exists($tax_name)) {
                $term_slug = basename(rtrim($url_string, '/'));
                $term = get_term_by('slug', $term_slug, $tax_name);
                if ($term) $urls[$term->term_id] = array_merge($data, ["type" => "term", "post_type" => $tax_name]);
                continue;
            }

            $urls[$sitemap_file_name] = array_merge($data, ["type" => "archive"]);
        }
        return $urls;
    }

    // =========================================================

        public function fetch_woo_account_endpoints() {
        if (!class_exists('WooCommerce') || !defined('ENABLE_MEMBERSHIP') || !ENABLE_MEMBERSHIP) return;
        if (!function_exists('salt_my_account_links')) return;
        $links = salt_my_account_links();
        if (empty($links)) return;
        $this->auth_cookies = $this->get_admin_cookies();
        $lang = $this->pae_lang_default();
        foreach ($links as $slug => $link) {
            if (empty($link['menu'])) continue;
            $url = function_exists('get_account_endpoint_url') ? get_account_endpoint_url($slug) : '';
            if (empty($url)) continue;
            $option_id = "woo_account_{$slug}_{$lang}";
            $this->error_log("[PAE] WC Account fetch: slug={$slug} url={$url} option_id={$option_id}");
            $this->fetch($url, $option_id, 'dynamic');
        }
        $this->auth_cookies = [];
    }

    // =========================================================

        public function get_woo_account_urls() {
        if (!class_exists('WooCommerce') || !defined('ENABLE_MEMBERSHIP') || !ENABLE_MEMBERSHIP) {
            return [];
        }
        
        $lang = $this->pae_lang_default();
        $urls = [];
        
        // 1. WooCommerce'in ORİJİNAL default endpoint'lerini al (hook'lardan ÖNCE)
        // Hook'lar bunları menüden kaldırıyor ama biz fetch için hepsine ihtiyacımız var
        if(function_exists('wc_get_account_menu_items')){
            // Önce hook'lardan geçmiş listeyi al (custom endpoint'ler için)
            $woo_menu_items = wc_get_account_menu_items();
            
            // Sonra WooCommerce'in default endpoint'lerini manuel ekle
            // Bunlar hook ile menüden kaldırılmış olsa bile fetch etmek istiyoruz
            $wc_default_endpoints = array(
                'orders'          => __('Orders', 'woocommerce'),
                'downloads'       => __('Downloads', 'woocommerce'),
                'edit-address'    => __('Addresses', 'woocommerce'),
                'payment-methods' => __('Payment methods', 'woocommerce'),
                'edit-account'    => __('Account details', 'woocommerce'),
            );
            
            // Default endpoint'leri ekle
            foreach($wc_default_endpoints as $endpoint => $label){
                $url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url($endpoint) : '';
                
                if (empty($url)) {
                    continue;
                }
                
                $option_id = "woo_account_{$endpoint}_{$lang}";
                $urls[$option_id] = [
                    'url'       => $url,
                    'post_type' => 'woo_account',
                    'type'      => 'dynamic',
                    'label'     => $label,
                    'slug'      => $endpoint,
                    'source'    => 'woocommerce',
                ];
            }
            
            // Custom endpoint'leri de ekle (hook'tan geçmiş liste)
            foreach($woo_menu_items as $endpoint => $label){
                // Dashboard ve logout'u atla
                if(in_array($endpoint, ['dashboard', 'customer-logout'])){
                    continue;
                }
                
                // Zaten default'lardan eklenmişse atla
                $option_id = "woo_account_{$endpoint}_{$lang}";
                if(isset($urls[$option_id])){
                    continue;
                }
                
                $url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url($endpoint) : '';
                
                if (empty($url)) {
                    continue;
                }
                
                $urls[$option_id] = [
                    'url'       => $url,
                    'post_type' => 'woo_account',
                    'type'      => 'dynamic',
                    'label'     => $label,
                    'slug'      => $endpoint,
                    'source'    => 'custom',
                ];
            }
        }
        
        return $urls;
    }

    // =========================================================

        private function build_related_assets($id, $post_type_val = '') {
        $this->error_log('[PAE] build_related_assets ENTER type=' . $this->type . ' id=' . $id . ' disable=' . ($this->disable_hooks ? '1':'0'));
        if ($this->disable_hooks) { $this->error_log('[PAE] build_related_assets EARLY-RETURN (disable_hooks)'); return; }

        $this->disable_hooks = true;
        try {
            if ($this->type === 'post') {
                $post = function_exists('get_post') ? get_post($id) : null;
                $this->error_log('[PAE] post obj=' . ($post ? ('ok#'.$post->ID) : 'null'));

                // Arşivleri (tüm diller) güncelle
                $pt = $post_type_val ?: ($post ? $post->post_type : '');
                $this->error_log('[PAE] pt=' . $pt);
                if ($pt) { $this->fetch_and_save_archives_assets($pt); }

                // ── ML: Çeviri sayfalarını fetch et ─────────────────────────
                // Default dil post'u fetch edildi. Polylang aktifse çevirilerinin
                // css_page'i eksik veya HTML farklıysa bağımsız fetch tetikle.
                // Heavy process lock aktif olduğu için shutdown hook'ta çalıştır.
                if (
                    function_exists('pll_get_post_language') &&
                    function_exists('pll_default_language') &&
                    function_exists('pll_get_post_translations')
                ) {
                    $post_lang = pll_get_post_language( (int) $id );
                    $default   = pll_default_language();

                    if ( $post_lang === $default ) {
                        $source_hash  = get_post_meta( (int) $id, self::HTML_HASH_META_KEY, true ) ?: '';
                        $translations = pll_get_post_translations( (int) $id );
                        $queue        = [];

                        foreach ( $translations as $l => $pid ) {
                            if ( ! $pid || (int) $pid === (int) $id || $l === $default ) continue;

                            $trans_hash = get_post_meta( (int) $pid, self::HTML_HASH_META_KEY, true ) ?: '';
                            $trans_meta = $this->meta_get( 'post', $pid );
                            $has_css    = ! empty( $trans_meta['css_page'] );

                            // css_page varsa ve hash aynıysa fetch gerekmez
                            if ( $has_css && $source_hash && $trans_hash && $source_hash === $trans_hash ) {
                                $this->error_log( "[PAE] build_related: post:{$pid} lang={$l} css_page OK + hash match → skip" );
                                continue;
                            }

                            $trans_url = get_permalink( (int) $pid );
                            if ( ! $trans_url ) continue;

                            $this->error_log( "[PAE] build_related: post:{$pid} lang={$l} css_page=" . ($has_css ? 'yes':'NO') . " hash_match=" . ($source_hash === $trans_hash ? 'yes':'NO') . " → queued shutdown" );
                            $queue[] = [ 'url' => $trans_url, 'id' => $pid ];
                        }

                        if ( ! empty( $queue ) ) {
                            $extractor = $this;
                            add_action( 'shutdown', static function() use ( $extractor, $queue ) {
                                foreach ( $queue as $item ) {
                                    $extractor->error_log( "[PAE] shutdown fetch: url={$item['url']} id={$item['id']}" );
                                    $prev   = $extractor->type;
                                    $extractor->type         = 'post';
                                    $extractor->disable_hooks = true; // döngü önleme
                                    $extractor->fetch( $item['url'], $item['id'], 'post' );
                                    $extractor->disable_hooks = false;
                                    $extractor->type = $prev;
                                }
                            }, 99 );
                        }
                    }
                }

            } elseif ($this->type === 'term') {
                // Term çevirileri — Polylang varsa default dil term'inin
                // diğer dil çevirilerinin meta'sını güncelle
                if (
                    function_exists('pll_get_term_language') &&
                    function_exists('pll_default_language') &&
                    function_exists('pll_get_term_translations')
                ) {
                    $term_lang = pll_get_term_language( (int) $id );
                    $default   = pll_default_language();
                    if ( $term_lang === $default ) {
                        $translations = pll_get_term_translations( (int) $id );
                        $source_hash  = get_term_meta( (int) $id, self::HTML_HASH_META_KEY, true ) ?: '';
                        $term_queue   = [];
                        foreach ( $translations as $l => $tid ) {
                            if ( ! $tid || (int) $tid === (int) $id || $l === $default ) continue;
                            $trans_hash = get_term_meta( (int) $tid, self::HTML_HASH_META_KEY, true ) ?: '';
                            $trans_meta = $this->meta_get( 'term', $tid );
                            $has_css    = ! empty( $trans_meta['css_page'] );
                            if ( $has_css && $source_hash && $trans_hash && $source_hash === $trans_hash ) {
                                $this->error_log( "[PAE] build_related: term:{$tid} lang={$l} OK → skip" );
                                continue;
                            }
                            $trans_url = get_term_link( (int) $tid );
                            if ( is_wp_error( $trans_url ) || ! $trans_url ) continue;
                            $this->error_log( "[PAE] build_related: term:{$tid} lang={$l} → queued shutdown" );
                            $term_queue[] = [ 'url' => $trans_url, 'id' => $tid ];
                        }
                        if ( ! empty( $term_queue ) ) {
                            $extractor = $this;
                            add_action( 'shutdown', static function() use ( $extractor, $term_queue ) {
                                foreach ( $term_queue as $item ) {
                                    $extractor->error_log( "[PAE] shutdown fetch term: url={$item['url']}" );
                                    $prev   = $extractor->type;
                                    $extractor->type         = 'term';
                                    $extractor->disable_hooks = true; // döngü önleme
                                    $extractor->fetch( $item['url'], $item['id'], 'term' );
                                    $extractor->disable_hooks = false;
                                    $extractor->type = $prev;
                                }
                            }, 99 );
                        }

                        // maybe_copy — hash match'li olanlar için plugin bundle kopyala
                        $existing_meta = $this->meta_get( 'term', $id );
                        if ( is_array( $existing_meta ) && ! empty( $existing_meta ) ) {
                            $this->disable_hooks = false;
                            $this->maybe_copy_meta_to_translations( $id, $existing_meta );
                            $this->disable_hooks = true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->error_log("build_related_assets error: " . $e->getMessage());
        }
        $this->disable_hooks = false;
        $this->error_log('[PAE] build_related_assets EXIT');
    }

    // =========================================================

        private function expand_required_plugins(array $plugins, array $pluginMap): array {
        $queue = $plugins;
        $seen  = array_fill_keys($plugins, true);

        while (!empty($queue)) {
            $p = array_shift($queue);
            if (!isset($pluginMap[$p])) continue;

            if (!empty($pluginMap[$p]['required']) && is_array($pluginMap[$p]['required'])) {
                foreach ($pluginMap[$p]['required'] as $dep) {
                    $dep = trim((string)$dep);
                    if ($dep === '' || !isset($pluginMap[$dep])) {
                        if ($dep !== '') $this->error_log("[PAE] WARN: required '{$dep}' tanımsız (source: {$p})");
                        continue;
                    }
                    // c: false olan plugin'ler zaten her sayfada yükleniyor,
                    // conditional bundle'a ekleme — çift yüklemeye yol açar.
                    if (empty($pluginMap[$dep]['c'])) {
                        $this->error_log("[PAE] required '{$dep}' skipped (c:false, always loaded)");
                        continue;
                    }
                    if (!isset($seen[$dep])) {
                        $seen[$dep] = true;
                        $plugins[]  = $dep;
                        $queue[]    = $dep;
                    }
                }
            }
        }
        sort($plugins);
        return array_values(array_unique($plugins));
    }

    // =========================================================

    /**
     * Tüm WordPress ve plugin özel sayfa rollerini ID → badges map olarak döndürür.
     * get_grouped_urls() tarafından bir kez çağrılır.
     *
     * Desteklenen kaynaklar:
     * - WordPress core: front page, posts page, privacy policy
     * - WooCommerce: shop, cart, checkout, myaccount, order-received, refund
     * - Yoast SEO: sitemap page
     * - WP Rocket, WPML, Polylang, bbPress, BuddyPress, vb.
     *
     * @return array<int, array[]>  [page_id => [['label'=>'...','color'=>'...','icon'=>'...'], ...]]
     */
    private function _collect_special_page_roles(): array {
        $roles = []; // [page_id => [badge, badge, ...]]

        $add = function(int $id, string $label, string $color, string $icon) use (&$roles): void {
            if ($id <= 0) return;
            $roles[$id][] = ['label' => $label, 'color' => $color, 'icon' => $icon];
        };

        // ── WordPress Core ────────────────────────────────────────────────────
        $front_page_id = (int) get_option('page_on_front');
        $posts_page_id = (int) get_option('page_for_posts');
        $privacy_id    = (int) get_option('wp_page_for_privacy_policy');

        $add($front_page_id, 'Front Page',    '#10b981', '🏠');
        $add($posts_page_id, 'Posts Page',    '#6366f1', '📰');
        $add($privacy_id,    'Privacy Policy','#64748b', '🔒');

        // ── WooCommerce ───────────────────────────────────────────────────────
        if (function_exists('wc_get_page_id')) {
            $wc_pages = [
                'shop'           => ['WC Shop',     '#7f54b3', '🛍️'],
                'cart'           => ['WC Cart',     '#7f54b3', '🛒'],
                'checkout'       => ['WC Checkout', '#7f54b3', '💳'],
                'myaccount'      => ['WC Account',  '#7f54b3', '👤'],
                'refund_returns' => ['WC Refund',   '#7f54b3', '↩️'],
                'order_received' => ['WC Order OK', '#7f54b3', '✅'],
            ];
            foreach ($wc_pages as $key => [$label, $color, $icon]) {
                $add((int) wc_get_page_id($key), $label, $color, $icon);
            }
        }

        // ── Yoast SEO ─────────────────────────────────────────────────────────
        if (defined('WPSEO_FILE')) {
            $yoast_id = (int) get_option('wpseo_titles')['breadcrumbs-home'] ?? 0;
            if (!$yoast_id) {
                // Yoast sitemap sayfası genellikle ayrı bir page değil ama
                // breadcrumb home page set edilmişse göster
                $yoast_opts = get_option('wpseo_titles', []);
                $yoast_id   = (int) ($yoast_opts['breadcrumbs-home'] ?? 0);
            }
            $add($yoast_id, 'Yoast Breadcrumb Home', '#a21caf', '🔍');
        }

        // ── bbPress ───────────────────────────────────────────────────────────
        if (function_exists('bbp_get_page_by_path') || defined('bbp_get_forums_page_id')) {
            if (function_exists('bbp_get_forums_page_id'))  $add((int) bbp_get_forums_page_id(),  'bbPress Forums',  '#f59e0b', '💬');
            if (function_exists('bbp_get_topics_page_id'))  $add((int) bbp_get_topics_page_id(),  'bbPress Topics',  '#f59e0b', '💬');
            if (function_exists('bbp_get_replies_page_id')) $add((int) bbp_get_replies_page_id(), 'bbPress Replies', '#f59e0b', '💬');
            if (function_exists('bbp_get_search_page_id'))  $add((int) bbp_get_search_page_id(),  'bbPress Search',  '#f59e0b', '🔍');
        }

        // ── BuddyPress ────────────────────────────────────────────────────────
        if (function_exists('bp_get_directory_page_ids')) {
            foreach ((array) bp_get_directory_page_ids() as $component => $pid) {
                $add((int) $pid, 'BP ' . ucfirst($component), '#3b82f6', '👥');
            }
        }

        // ── Contact Form 7 ────────────────────────────────────────────────────
        // CF7 sayfaları standart page'ler — özel bir option yok, atla.

        // ── WP Rocket ────────────────────────────────────────────────────────
        if (defined('WP_ROCKET_VERSION')) {
            $rocket_opts = get_option('wp_rocket_settings', []);
            foreach ((array) ($rocket_opts['cache_reject_pages'] ?? []) as $slug) {
                $pid = (int) url_to_postid(home_url($slug));
                $add($pid, 'Rocket Excluded', '#ef4444', '🚀');
            }
        }

        // ── WPML ─────────────────────────────────────────────────────────────
        if (class_exists('SitePress')) {
            $wpml_pages = get_option('icl_sitepress_settings', []);
            if (!empty($wpml_pages['urls']['directory_for_default_language'])) {
                // WPML language switcher page
                $ls_id = (int) ($wpml_pages['language_selector_page_id'] ?? 0);
                $add($ls_id, 'WPML Lang Switcher', '#0284c7', '🌐');
            }
        }

        // ── Polylang ─────────────────────────────────────────────────────────
        if (function_exists('pll_default_language')) {
            $pll_opts = get_option('polylang', []);
            $ls_id    = (int) ($pll_opts['page_for_posts'] ?? 0);
            $add($ls_id, 'Polylang Posts Page', '#0284c7', '🌐');
        }

        // ── YITH Wishlist ─────────────────────────────────────────────────────
        if (defined('YITH_WCWL')) {
            $wishlist_id = (int) get_option('yith_wcwl_wishlist_page_id', 0);
            $add($wishlist_id, 'Wishlist', '#ec4899', '❤️');
        }

        // ── YITH Compare ─────────────────────────────────────────────────────
        if (defined('YITH_WOOCOMPARE')) {
            $compare_id = (int) get_option('yith_woocompare_compare_page_id', 0);
            $add($compare_id, 'Compare', '#f97316', '⚖️');
        }

        // ── Newsletter ────────────────────────────────────────────────────────
        if (class_exists('Newsletter')) {
            $nl_opts = get_option('newsletter_subscription', []);
            $add((int) ($nl_opts['confirmation_page'] ?? 0), 'Newsletter Confirm', '#84cc16', '📧');
            $add((int) ($nl_opts['unsubscription_page'] ?? 0), 'Newsletter Unsub',   '#84cc16', '📧');
        }

        // ── Membership / My Account (custom) ─────────────────────────────────
        if (defined('ENABLE_MEMBERSHIP') && ENABLE_MEMBERSHIP) {
            if (function_exists('get_page_url')) {
                $account_id = (int) url_to_postid((string) get_page_url('my-account'));
                $add($account_id, 'My Account', '#8b5cf6', '👤');
            }
        }

        return $roles;
    }

    
}