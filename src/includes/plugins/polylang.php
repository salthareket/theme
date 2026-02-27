<?php

/**
 * BRIC CORE - BACK TO BASICS (Dinamik & Garantili)
 * SQL kasmayı bırakıp, WP'nin kendi fonksiyonlarıyla map dolduruyoruz.
 */

if (is_admin()) {
    add_action('admin_footer-nav-menus.php', function() {
        if (!function_exists('pll_default_language')) return;

        $default_lang = pll_default_language(); 
        $current_lang = pll_current_language();
        if ($default_lang === $current_lang) return;

        $bricMap = [];

        // 1. POSTLAR: Sadece menüde kullanılan veya yaygın tipleri tara
        $posts = get_posts([
            'post_type' => ['page', 'post', 'magaza-tipi', 'product'], // Buraya CPT'lerini ekleyebilirsin
            'numberposts' => -1,
            'lang' => $current_lang // Mevcut dildekileri alıyoruz
        ]);

        foreach ($posts as $p) {
            $trans_id = pll_get_post($p->ID, $default_lang);
            if ($trans_id && $trans_id != $p->ID) {
                $bricMap['p_' . $p->ID] = get_the_title($trans_id);
            }
        }

        // 2. TAXONOMY: Kategoriler ve diğer her şey
        $taxonomies = get_taxonomies(['public' => true]);
        foreach ($taxonomies as $tax) {
            $terms = get_terms([
                'taxonomy' => $tax,
                'hide_empty' => false,
                'lang' => $current_lang
            ]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $trans_id = pll_get_term($t->term_id, $default_lang);
                    if ($trans_id && $trans_id != $t->term_id) {
                        $def_term = get_term($trans_id);
                        if ($def_term) $bricMap['t_' . $t->term_id] = $def_term->name;
                    }
                }
            }
        }

        ?>
        <script type="text/javascript">
        (function($) {
            var translations = <?php echo json_encode($bricMap); ?>;
            console.log("BRIC MAP GELDİ:", translations);

            function runJustice() {
                // SAĞ TARAF
                $('.menu-item').each(function() {
                    var $item = $(this);
                    var $span = $item.find('.menu-item-title').first();
                    if ($span.hasClass('bric-ok')) return;

                    var objId = $item.find('.menu-item-data-object-id').val();
                    var type = $item.find('.menu-item-data-type').val() === 'taxonomy' ? 't_' : 'p_';

                    if (translations[type + objId]) {
                        var node = $span.contents().filter(function(){ return this.nodeType === 3; })[0];
                        if (node && !node.textContent.includes('(')) {
                            var $sub = $span.find('.is-submenu').clone();
                            $span.text(node.textContent.trim() + ' (' + translations[type + objId] + ')').append($sub);
                        }
                    }
                    $span.addClass('bric-ok');
                });

                // SOL TARAF
                $('.menu-item-checkbox').each(function() {
                    var $cb = $(this);
                    if ($cb.hasClass('bric-ok')) return;

                    var id = $cb.attr('data-menu-item-id') || $cb.val();
                    var nameAttr = $cb.attr('name') || "";
                    var type = (nameAttr.indexOf('menu-item-taxonomy') !== -1) ? 't_' : 'p_';

                    if (translations[type + id]) {
                        var $label = $cb.closest('label');
                        var node = $label.contents().filter(function(){ return this.nodeType === 3; })[0];
                        if (node && !node.textContent.includes('(')) {
                            node.textContent = node.textContent.trim() + ' (' + translations[type + id] + ')';
                        }
                    }
                    $cb.addClass('bric-ok');
                });
            }

            var obs = new MutationObserver(runJustice);
            $(document).ready(function() {
                var s = document.getElementById('side-sortables'), m = document.getElementById('menu-to-edit');
                if (s) obs.observe(s, { childList: true, subtree: true });
                if (m) obs.observe(m, { childList: true, subtree: true });
                runJustice();
                $(document).ajaxComplete(function() { setTimeout(runJustice, 500); });
            });
        })(jQuery);
        </script>
        <?php
    });
}

if(is_admin()){

    function pll_register_strings_for_custom_post_types_and_taxonomies() {
       if (isset($_GET['page']) && (strpos($_GET['page'], 'mlang') !== false)) {
            $custom_post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
            foreach ($custom_post_types as $post_type) {
                // Senin yazdığın formatı aynen korudum abi
                pll_register_string("post type name {$post_type->name}", $post_type->labels->name, 'Custom Post Types');
                pll_register_string("post type singular name {$post_type->name}", $post_type->labels->singular_name, 'Custom Post Types');
                pll_register_string("post type menu name {$post_type->name}", $post_type->labels->menu_name, 'Custom Post Types');
            }

            // --- TAXONOMY KAYDI ---
            $custom_taxonomies = get_taxonomies(['public' => true, '_builtin' => false], 'objects');
            foreach ($custom_taxonomies as $taxonomy) {
                pll_register_string("taxonomy name {$taxonomy->name}", $taxonomy->label, 'Custom Taxonomies');
                pll_register_string("taxonomy singular name {$taxonomy->name}", $taxonomy->labels->singular_name, 'Custom Taxonomies');
                pll_register_string("taxonomy menu name {$taxonomy->name}", $taxonomy->labels->menu_name, 'Custom Taxonomies');
            }
        }
    }






    /**
     * BRIC CORE - OPTIMIZED TAXONOMY BULK TRANSLATE SYSTEM
     * 1. Toplu işlem menüsüne "Translate Terms" ekler.
     * 2. Seçilen terimleri varsayılan dilden diğer dillere meta verileriyle kopyalar.
     * 3. Gereksiz sorguları (950 Query OÇ) engellemek için optimize edilmiştir.
     */
    // --- 1. TOPLU İŞLEM SEÇENEĞİNİ EKLE (JQUERY'SİZ, NATIVE YÖNTEM) ---
    add_filter("bulk_actions-edit-tags", function($bulk_actions) {
        // Polylang yüklü değilse ekleme
        if (!function_exists('pll_default_language')) return $bulk_actions;
        
        $bulk_actions['bulk_translate_terms'] = __('Translate Terms', 'polylang');
        return $bulk_actions;
    });
    // WordPress'in bazı sürümleri için taksonomi bazlı dinamik tetikleyici
    add_action('admin_init', function() {
        if (!is_admin()) return;
        $taxonomies = get_taxonomies(['public' => true], 'names');
        foreach ($taxonomies as $taxonomy) {
            add_filter("bulk_actions-edit-$taxonomy", function($bulk_actions) {
                $bulk_actions['bulk_translate_terms'] = __('Translate Terms', 'polylang');
                return $bulk_actions;
            });
        }
    });
    // --- 2. TOPLU İŞLEMİ YAKALA VE ÇALIŞTIR ---
    add_action('admin_action_bulk_translate_terms', function() {
        // Güvenlik ve yetki kontrolü
        if (!current_user_can('manage_categories')) {
            wp_die(__('Bu işlem için yetkiniz yok.'));
        }

        // Seçilen Terim ID'lerini al (delete_tags WP'nin varsayılan checkbox name'idir)
        $term_ids_param = isset($_REQUEST['delete_tags']) ? $_REQUEST['delete_tags'] : '';
        $term_ids = is_array($term_ids_param) ? array_map('intval', $term_ids_param) : array_map('intval', explode(',', $term_ids_param));

        if (empty($term_ids)) {
            wp_die(__('Çevrilecek terim seçilmedi.'));
        }

        // Her terimi çevir
        foreach ($term_ids as $term_id) {
            if ($term_id > 0) {
                bric_optimized_translate_term($term_id);
            }
        }

        // İşlem bitti, sayfaya geri dön ve kaç terim işlendiğini bildir
        $sendback = remove_query_arg(['bulk_translate_terms', 'message'], wp_get_referer());
        $sendback = add_query_arg('bulk_translate_terms_done', count($term_ids), $sendback);
        wp_redirect($sendback);
        exit;
    });
    // --- 3. OPTİMİZE EDİLMİŞ ÇEVİRİ FONKSİYONU ---
    function bric_optimized_translate_term($term_id) {
        if (!function_exists('pll_get_term') || !function_exists('pll_save_term_translations')) return;

        $default_lang = pll_default_language();
        $term = get_term($term_id);
        
        if (is_wp_error($term) || !$term) return;

        // Sadece varsayılan dildeki terimleri ana kaynak kabul et
        $current_lang = pll_get_term_language($term_id);
        if (!$current_lang || $current_lang !== $default_lang) return;

        // Mevcut çevirileri ve dilleri al
        $translations = pll_get_term_translations($term_id);
        $languages = pll_languages_list();

        foreach ($languages as $lang) {
            // Zaten çevirisi varsa veya varsayılan dilse atla (Sorgu tasarrufu)
            if ($lang === $default_lang || isset($translations[$lang])) continue;

            // Yeni terimi oluştur
            $new_term = wp_insert_term($term->name, $term->taxonomy, [
                'description' => $term->description,
                'slug'        => $term->slug . '-' . $lang,
            ]);

            if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                $new_id = $new_term['term_id'];

                // Dilini set et ve listeye ekle
                pll_set_term_language($new_id, $lang);
                $translations[$lang] = $new_id;

                // Meta verilerini tek seferde kopyala
                $term_meta = get_term_meta($term_id);
                if (!empty($term_meta)) {
                    foreach ($term_meta as $key => $values) {
                        foreach ($values as $val) {
                            add_term_meta($new_id, $key, maybe_unserialize($val));
                        }
                    }
                }
            }
        }

        // İlişkileri veritabanına toplu kaydet (Döngü dışı - Kritik hız farkı)
        pll_save_term_translations($translations);
    }





    /**
     * BRIC CORE - ADMIN LANGUAGE CONTEXT CONTROL
     * Çevirisi olmayan sayfalarda Polylang'ı "Tüm Diller" moduna zorlar.
     */

    // 1. Belirli Ayar Sayfalarını Kontrol Et
    add_action('admin_init', function() {
        if (!is_admin() || !function_exists('pll_default_language')) return;

        // Polylang aktif değilse veya desteklemiyorsa kaç
        if (defined("ENABLE_MULTILANGUAGE") && ENABLE_MULTILANGUAGE != "polylang") return;

        $pages_to_check = ['development', 'anasayfa', 'header', 'footer', 'menu', 'theme-styles', 'ayarlar', 'page-assets-update', 'formlar'];
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';

        if (in_array($current_page, $pages_to_check) && (!isset($_GET['lang']) || $_GET['lang'] !== 'all')) {
            wp_redirect(add_query_arg('lang', 'all'));
            exit;
        }
    });

    // 2. Çevrilemeyen İçeriklerde (Post/Tax) Dil Parametresini Engelle
    add_action('current_screen', function($screen) {
        // 1. KRİTİK EKLEME: Eğer bir POST işlemi varsa (Kayıt yapılıyorsa) ASLA dokunma!
        if (!is_admin() || !$screen || !empty($_POST) || !function_exists('pll_is_translated_post_type')) return;

        $lang = isset($_GET['lang']) ? sanitize_key($_GET['lang']) : '';
        if ($lang === 'all') return;

        $is_non_translatable = false;
        $current_type = '';

        // Post Tipi Kontrolü
        if (in_array($screen->base, ['post', 'post-new', 'edit'])) {
            if (isset($_GET['post_type'])) {
                $current_type = sanitize_key($_GET['post_type']);
            } elseif (isset($_GET['post'])) {
                $current_type = get_post_type(intval($_GET['post']));
            } else {
                $current_type = $screen->post_type;
            }

            // ACF Field Group veya Polylang'ın bilmediği tiplerde yönlendir
            if ($current_type && !pll_is_translated_post_type($current_type)) {
                $is_non_translatable = true;
            }
        }
        
        // Taksonomi Kontrolü
        if (in_array($screen->base, ['term', 'edit-tags'])) {
            $current_tax = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : $screen->taxonomy;
            if ($current_tax && !pll_is_translated_taxonomy($current_tax)) {
                $is_non_translatable = true;
            }
        }

        if ($is_non_translatable) {
            // Yönlendirme yapmadan önce admin URL'sini temizce kur
            wp_redirect(add_query_arg('lang', 'all'));
            exit;
        }
    });



    /**
     * 1. Çevrilemeyen Post Tiplerinde Terimleri Varsayılan Dile Zorla
     */
    add_filter('get_terms_args', function($args, $taxonomies) {
        if (!is_admin() || !function_exists('pll_is_translated_post_type')) return $args;

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['post', 'post-new'])) return $args;

        $post_type = $_GET['post_type'] ?? (isset($_GET['post']) ? get_post_type($_GET['post']) : $screen->post_type);

        // Eğer Post Tipi çevrilebilir DEĞİLSE, terimleri varsayılan dile (tr) çek
        if ($post_type && !pll_is_translated_post_type($post_type)) {
            $args['lang'] = pll_default_language();
        }
        return $args;
    }, 99, 2);
    /**
     * 2. ACF Alanlarını Diller Arası Senkronize Et (Optimize Versiyon)
     */
    add_action('acf/save_post', function($post_id) {
        if (!is_admin() || !function_exists('pll_get_post_translations')) return;

        // Sadece varsayılan dildeki post kaydedilirken çalış
        if (pll_get_post_language($post_id) !== pll_default_language()) return;

        $translations = pll_get_post_translations($post_id);
        if (count($translations) <= 1) return; // Çeviri yoksa çık

        // Hangi alanların 'sync' edileceğini belirle (Gereksiz döngüleri engellemek için)
        // Not: Performans için field group'ları burada manuel listelemek daha iyidir ama dinamik bırakıyoruz.
        $field_groups = acf_get_field_groups(['post_id' => $post_id]);
        $sync_fields = [];
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group);
            if ($fields) {
                foreach ($fields as $f) {
                    if (isset($f['translations']) && $f['translations'] === 'sync') {
                        $sync_fields[] = $f;
                    }
                }
            }
        }

        if (empty($sync_fields)) return;

        // Değerleri bir kere çek, döngü içinde sürekli çekme
        foreach ($translations as $lang => $translated_id) {
            if ($translated_id == $post_id) continue;

            foreach ($sync_fields as $field) {
                $value = get_field($field['name'], $post_id, false); // Formatlamadan ham veriyi al (Hızlı)
                
                // Eğer ilişki/post/term alanıysa ID'leri o dile göre güncelle
                $value = bric_translate_acf_values($value, $field, $lang);
                
                update_field($field['name'], $value, $translated_id);
            }
        }
    }, 20);
    /**
     * Yardımcı Fonksiyon: ACF Değerlerini (ID bazlı) Dile Göre Çevirir
     */
    function bric_translate_acf_values($value, $field, $lang) {
        if (empty($value)) return $value;

        // Post Nesnesi / İlişki Alanları
        if (in_array($field['type'], ['post_object', 'relationship'])) {
            return is_array($value) 
                ? array_filter(array_map(fn($id) => pll_get_post($id, $lang) ?: $id, $value))
                : (pll_get_post($value, $lang) ?: $value);
        }

        // Taksonomi / Kategori Alanları
        if (in_array($field['type'], ['taxonomy', 'acfe_taxonomy_terms'])) {
            $tax = $field['taxonomy'] ?? '';
            return is_array($value)
                ? array_filter(array_map(fn($id) => pll_get_term($id, $tax, $lang) ?: $id, $value))
                : (pll_get_term($value, $tax, $lang) ?: $value);
        }

        // Repeater / Flexible (İç içe döngü)
        if (in_array($field['type'], ['repeater', 'flexible_content']) && is_array($value)) {
            foreach ($value as &$row) {
                foreach ($field['sub_fields'] as $sub) {
                    if (isset($row[$sub['name']])) {
                        $row[$sub['name']] = bric_translate_acf_values($row[$sub['name']], $sub, $lang);
                    }
                }
            }
        }

        return $value;
    }
}



/**
 * BRIC CORE - ULTRA PERFORMANCE TEMPLATE FIX
 * Polylang slug değişimlerinde ana dildeki twig dosyasını bulur.
 */
add_filter('timber/render/file', function ($file) {
    // 1. GÜVENLİK KAPILARI (En hızlı kontroller en başta)
    if (is_admin() || !function_exists('pll_current_language') || !function_exists('pll_default_language')) {
        return $file;
    }

    // 2. DİL KONTROLÜ (Eğer ana dildeysek hiç yorulma)
    $current_lang = pll_current_language();
    $default_lang = pll_default_language();
    if ($current_lang === $default_lang) {
        return $file;
    }

    // 3. CONTEXT KONTROLÜ
    global $post;
    if (!$post || !isset($post->ID) || !isset($post->post_name)) {
        return $file;
    }

    // 4. SMART CACHE
    // Sadece Post ID yetmez, aranan dosya ismini de cache anahtarına ekliyoruz
    static $bric_tpl_cache = [];
    $cache_key = $post->ID . md5($file); 
    if (isset($bric_tpl_cache[$cache_key])) {
        return $bric_tpl_cache[$cache_key];
    }

    // 5. ANA DİL ID ÇEKİMİ
    $default_post_id = pll_get_post($post->ID, $default_lang);
    if (!$default_post_id || $default_post_id == $post->ID) {
        return $bric_tpl_cache[$cache_key] = $file;
    }

    // 6. SLUG ÇEKİMİ (Zaten ana dildeki post objesi elimizdeyse DB'ye gitme)
    $default_slug = '';
    if ($default_post_id == $post->ID) {
        $default_slug = $post->post_name;
    } else {
        $default_post = get_post($default_post_id); // WP Internal cache kullanır
        if (!$default_post) return $file;
        $default_slug = $default_post->post_name;
    }

    // 7. TEMPLATE ARAMA (Performans Odaklı)
    $dirnames = (array) Timber::$dirname;
    $template_names = [
        "page-{$default_slug}.twig",
        "single-{$default_slug}.twig",
        "archive-{$default_slug}.twig"
    ];

    $theme_path = get_stylesheet_directory() . '/';

    foreach ($template_names as $tpl_name) {
        foreach ($dirnames as $dir) {
            // file_exists pahalı bir işlemdir, bulduğu an döner
            if (file_exists($theme_path . $dir . '/' . $tpl_name)) {
                return $bric_tpl_cache[$cache_key] = $tpl_name;
            }
        }
    }

    // Bulamadıysa orijinali cache'le ve dön
    return $bric_tpl_cache[$cache_key] = $file;
}, 10);


/*
add_filter('post_type_archive_title', function($title, $post_type) {
    if (is_post_type_archive()) {
        $current_lang = pll_current_language();
        $post_type_obj = get_post_type_object($post_type);
        if ($post_type_obj) {
            $translated_name = pll_translate_string($post_type_obj->labels->name, $current_lang);
            return $translated_name ? $translated_name : $title;
        }
    }
    return $title;
}, 10, 2);
*/



// change template name if page/post using post_{{post.slug}}.twig like template and chamged its slug by polylang
/*add_filter('timber/render/file', function ($file) {
    if (function_exists('pll_current_language') && pll_current_language() !== pll_default_language()) {
        global $post;
        if ($post) {
            // Mevcut postun slug'ını al
            $current_slug = $post->post_name;

            // Default dildeki postu al
            $default_post_id = pll_get_post($post->ID, pll_default_language());
            if ($default_post_id) {
                $default_post = get_post($default_post_id);
                if ($default_post) {
                    // Default dildeki postun slug'ını al
                    $default_slug = $default_post->post_name;

                    // Sayfa tipine göre muhtemel template isimlerini oluştur
                    $template_names = [
                        "page-{$default_slug}.twig",
                        "single-{$default_slug}.twig",
                        "archive-{$default_slug}.twig",
                    ];
                    foreach ($template_names as $template_name) {
                        foreach (Timber::$dirname as $dirname) {
                            $template_path = get_stylesheet_directory() . "/{$dirname}/{$template_name}";
                            if (file_exists($template_path)) {
                                return $template_name; // İlk bulunan geçerli template dosyasını döndür
                            }
                        }
                    }
                }
            }
        }
    }
    return $file;
}, 10);*/



/*
// Translate post_type labels
add_action('registered_post_type', function($post_type, $post_type_object) {
    $labels = get_post_type_labels($post_type_object);
    if (function_exists('pll__')) {
        foreach ($labels as $key => &$label) {
            $label = pll__($label);
        }
        $post_type_object->labels = (object) $labels;
    }
}, 10, 2);
// Translate taxonomy labels
add_action('registered_taxonomy', function($taxonomy, $object_type, $args) {
    $taxonomy_object = get_taxonomy($taxonomy);
    if ($taxonomy_object && function_exists('pll__')) {
        $labels = $taxonomy_object->labels;
        foreach ($labels as $key => &$label) {
            $label = pll__($label);
        }
        $taxonomy_object->labels = (object) $labels;
    }
}, 10, 3);
function pll_get_post_type_archive_link($post_types = '', $lang = ''){
    $current_lang = pll_current_language() === pll_default_language() ? '' : '/' . pll_current_language();
    if ($lang != '') {
        $current_lang = $lang == pll_default_language() ? '' : '/' . $lang;
    }
    $re = '/(https?:\/\/[A-z.]*)(.*)/';
    $str = get_post_type_archive_link($post_types);
    $subst = "$1" . $current_lang . "$2";
    return preg_replace($re, $subst, $str);
}*/




/**
 * BRIC CORE - SMART DYNAMIC LABEL TRANSLATION
 * Ne statik if-else, ne de her saniye DB sorgusu. 
 * Hafızada tutan (static cache) dinamik yapı.
 */
function bric_dynamic_translate_labels($labels) {
    if (!is_admin() || !function_exists('pll__')) return $labels;
    // Sadece ana dilde değilsek çalış
    if (pll_current_language() === pll_default_language()) return $labels;
    // Hafıza (Cache) kontrolü: Aynı sayfa içinde onlarca kez DB'ye gitme
    static $label_cache = [];
    foreach ($labels as $key => $value) {
        // Eğer bu string zaten hafızadaysa oradan al, değilse Polylang'e sor
        if (!isset($label_cache[$value])) {
            $label_cache[$value] = pll__($value);
        }
        $labels->$key = $label_cache[$value];
    }
    return $labels;
}
add_filter('post_type_labels', 'bric_dynamic_translate_labels'); // Hangi CPT lazımsa ekle
add_filter('taxonomy_labels', 'bric_dynamic_translate_labels'); // Hangi Tax lazımsa ekle

function pll_get_post_type_archive_link($post_type, $lang = '') {
    $url = get_post_type_archive_link($post_type);
    if (!$url || !function_exists('pll_home_url')) return $url;
    $target_lang = $lang ?: pll_current_language();
    // Polylang zaten kendi filtreleriyle bu URL'yi çoğu zaman düzeltir.
    // Düzeltmediği yerde biz sadece domain kısmını dilin home_url'i ile değiştiriyoruz.
    return str_replace(home_url(), pll_home_url($target_lang), $url);
}





function translate_term($term_id) {//editeds on 17.02.2026
    // Polylang yoksa boşuna kürek çekme
    if (!function_exists('pll_get_term') || !function_exists('pll_save_term_translations')) {
        return;
    }

    $default_lang = pll_default_language();

    // Terimi al, hata varsa kaç
    $term = get_term($term_id);
    if (is_wp_error($term) || !$term) {
        return;
    }

    // Terimin dilini kontrol et, zaten çeviri ise veya dili yoksa elleme
    $current_lang = pll_get_term_language($term_id);
    if (!$current_lang || $current_lang !== $default_lang) {
        return;
    }

    // Mevcut çeviri haritasını al (Örn: ['tr' => 123, 'en' => 456])
    $translations = pll_get_term_translations($term_id);
    $languages = pll_languages_list();

    foreach ($languages as $lang) {
        // Zaten o dilde çevirisi varsa veya dil varsayılan dil ise atla
        if ($lang === $default_lang || isset($translations[$lang])) {
            continue;
        }

        // Yeni terimi oluştur
        $translated_term_args = array(
            'description' => $term->description,
            'slug'        => $term->slug . '-' . $lang,
        );

        $new_term = wp_insert_term($term->name, $term->taxonomy, $translated_term_args);

        if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
            $new_term_id = $new_term['term_id'];

            // Yeni terimin dilini set et
            pll_set_term_language($new_term_id, $lang);
            
            // Çeviri haritasına ekle (Döngü sonunda toplu kaydedilecek)
            $translations[$lang] = $new_term_id;

            // Meta verilerini kopyala
            $term_meta = get_term_meta($term_id);
            if (!empty($term_meta)) {
                foreach ($term_meta as $meta_key => $meta_values) {
                    foreach ($meta_values as $meta_value) {
                        add_term_meta($new_term_id, $meta_key, maybe_unserialize($meta_value));
                    }
                }
            }
        }
    }

    // Bütün dilleri gezdik, şimdi eşleşmeleri TEK SEFERDE veritabanına yaz
    pll_save_term_translations($translations);
}






function pll_set_language($lang = "en") {
    if (function_exists('PLL') && function_exists('pll_current_language')) {
        $current_lang = pll_current_language("slug");
        if ($current_lang !== $lang) {
            //$_desiredLang = PLL()->model->get_language($lang);
            //if (false !== $_desiredLang) {
                PLL()->curlang = $lang;//$_desiredLang;
            //}
            return $current_lang;
        }
        return $lang;
    }
    return $lang;
}





/**
 * BRIC CORE - ULTRA OPTIMIZED POST SYNC (v2)
 * Assets metasını korur ve veritabanını yormaz.
 */
function pll_copy_post_languages($post_id) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;

    if (!function_exists('pll_get_post_language') || !function_exists('pll_default_language')) return;

    if (pll_get_post_language($post_id) !== pll_default_language()) return;

    $target_ids = get_option('synchronized_pages');
    if (!is_array($target_ids) || !in_array($post_id, $target_ids)) return;

    $translations = pll_get_post_translations($post_id);
    $source_post = get_post($post_id);

    remove_action('save_post', 'pll_copy_post_languages');

    foreach ($translations as $lang => $trans_id) {
        if ($trans_id === $post_id || !$trans_id) continue;

        // 1. SQL ile Hızlı İçerik Güncelleme
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            [
                'post_content' => $source_post->post_content,
                'post_excerpt' => $source_post->post_excerpt
            ],
            ['ID' => $trans_id]
        );

        // 2. Meta Güncelleme (Filtreli)
        $all_metas = get_post_meta($post_id);
        
        // --- KRİTİK KISIM: Assets ve WP Sistem metalarını kopyalama ---
        $ignore_keys = ['_edit_lock', '_edit_last', '_wp_page_template', '_wp_old_slug', '_thumbnail_id', 'assets'];
        
        foreach ($all_metas as $key => $values) {
            if (in_array($key, $ignore_keys)) continue;
            
            $value = maybe_unserialize(end($values));
            update_post_meta($trans_id, $key, $value);
        }

        // 3. Öne Çıkan Görsel
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            update_post_meta($trans_id, '_thumbnail_id', $thumb_id);
        }
    }

    add_action('save_post', 'pll_copy_post_languages', 100);
}
function my_custom_pll_filter($meta_keys, $sync, $from, $to) {
    $excluded_meta_keys = ['assets'];
    $meta_keys = array_diff($meta_keys, $excluded_meta_keys);
    return $meta_keys;
}
add_filter('pll_copy_post_metas', 'my_custom_pll_filter', 10, 4);







/**
 * BRIC CORE - OPTIMIZED DYNAMIC TAXONOMY SYNC
 * Ön yüzde sorgulanan taksonomi ID'lerini o anki dile otomatik saptırır.
 */
add_filter('pre_get_posts', function($query) {
    // Admin panelinde veya ana sorgu değilse (opsiyonel) yorma
    // Ama ACF blokları için is_admin() kontrolü yeterli
    if (is_admin() || !function_exists('pll_get_term')) return $query;

    $tax_query = $query->get('tax_query');
    if (empty($tax_query) || !is_array($tax_query)) return $query;

    // Senkronize edilecek taksonomileri bir kere çek, hafızada tut
    static $sync_tax_list = null;
    if ($sync_tax_list === null) {
        $sync_tax_list = get_option('sync_taxonomies_to_language', []);
    }

    if (empty($sync_tax_list)) return $query;

    $current_lang = pll_current_language();
    $changed_any = false;

    // Term ID çevirileri için lokal cache (Aynı ID'yi defalarca sormasın)
    static $term_translation_cache = [];

    foreach ($tax_query as $key => $condition) {
        if (!is_array($condition) || !isset($condition['taxonomy'], $condition['terms'])) continue;

        // Listemizde olmayan taksonomiyi es geç
        if (!in_array($condition['taxonomy'], $sync_tax_list)) continue;

        $terms = (array) $condition['terms'];
        $new_terms = [];
        $changed_this_tax = false;

        foreach ($terms as $term_id) {
            if (is_numeric($term_id)) {
                $cache_key = "{$term_id}_{$current_lang}";
                
                if (!isset($term_translation_cache[$cache_key])) {
                    $translated_id = pll_get_term($term_id, $current_lang);
                    $term_translation_cache[$cache_key] = ($translated_id && $translated_id != $term_id) ? $translated_id : $term_id;
                }

                $new_id = $term_translation_cache[$cache_key];
                if ($new_id != $term_id) {
                    $changed_this_tax = true;
                    $changed_any = true;
                }
                $new_terms[] = $new_id;
            } else {
                $new_terms[] = $term_id;
            }
        }

        if ($changed_this_tax) {
            $tax_query[$key]['terms'] = $new_terms;
        }
    }

    if ($changed_any) {
        $query->set('tax_query', $tax_query);
    }

    return $query;
}, 10);






/**
 * BRIC CORE - OPTIMIZED ACFE ADVANCED LINK TRANSLATION
 * Link verisini dile göre çevirirken veritabanını tokatlamaz.
 */
function pll_acfe_advanced_link($data = []) {
    // Polylang yoksa veya veri boşsa kaç
    if (empty($data) || !is_array($data) || !function_exists('pll_get_post')) {
        return $data;
    }

    $type = $data['type'] ?? '';
    $original_id = $data['value'] ?? '';

    // Sadece post veya term ise ve ID varsa işlem yap
    if (!in_array($type, ['post', 'term']) || empty($original_id)) {
        return $data;
    }

    // Statik Cache: Aynı sayfa içinde aynı link 5 kere geçiyorsa DB'ye gitme
    static $link_cache = [];
    $current_lang = pll_current_language();
    $cache_key = "{$type}_{$original_id}_{$current_lang}";

    if (isset($link_cache[$cache_key])) {
        return $link_cache[$cache_key];
    }

    $translated_id = null;

    if ($type === 'post') {
        $translated_id = pll_get_post($original_id, $current_lang);
    } elseif ($type === 'term') {
        $translated_id = pll_get_term($original_id, $current_lang);
    }

    // Eğer çeviri varsa ve geçerliyse
    if ($translated_id && $translated_id != $original_id) {
        if ($type === 'post') {
            $new_title = get_the_title($translated_id);
            $new_url   = get_permalink($translated_id);
        } else {
            $term = get_term($translated_id);
            if ($term && !is_wp_error($term)) {
                $new_title = $term->name;
                $new_url   = get_term_link($term);
            }
        }

        // Sadece veri geldiyse güncelle
        if (!empty($new_url) && !is_wp_error($new_url)) {
            $data['value'] = $translated_id;
            $data['url']   = $new_url;
            $data['title'] = $new_title ?? $data['title'];
            $data['name']  = $data['title'];
        }
    }

    // Sonucu cache'e at ve döndür
    return $link_cache[$cache_key] = $data;
}




/**
 * BRIC CORE - ULTIMATE DEFAULT FIELD BYPASS
 * Dilden bağımsız olarak TR (default) verisini söküp getirir.
 */
function get_field_default($field_name, $id = 'options') {
    if (!function_exists('pll_default_language')) {
        return get_field($field_name, $id);
    }

    // 1. Statik Değişkenle Gereksiz Filtre Yükünü Azalt
    static $default_lang = null;
    if ($default_lang === null) {
        $default_lang = pll_default_language();
    }

    // 2. ACF'e "Hangi dildesin?" diye sorduklarında "TR" dedirtiyoruz
    $force_default = function() use ($default_lang) { return $default_lang; };
    add_filter('acf/settings/current_language', $force_default, 999);

    // 3. Veriyi çek (Ham veri çekmek için false parametresi eklenebilir ama standart iyidir)
    $value = get_field($field_name, $id);

    // 4. Sistemi hemen eski haline döndür
    remove_filter('acf/settings/current_language', $force_default, 999);

    return $value;
}






/**
 * BRIC CORE - ACF DYNAMIC LABEL TRANSLATION
 * Arapça/İngilizce panelde, Post Object ve Relationship alanlarında 
 * Türkçe karşılıklarını parantez içinde (Statik Cache ile) gösterir.

function bric_acf_fields_display_default_lang_label($title, $post_or_term, $field, $post_id) {
    // 1. Polylang ve Temel Kontroller
    if (!function_exists('pll_default_language') || !function_exists('pll_current_language')) {
        return $title;
    }

    $default_lang = pll_default_language(); // tr
    $current_lang = pll_current_language(); // ar, en vs.

    // Zaten Türkçe paneldeysek veya veri yoksa kaç
    if ($current_lang === $default_lang || !$post_or_term) {
        return $title;
    }

    // 2. ID ve Tip Belirleme (Post mu Term mi?)
    $object_id = 0;
    $is_post = false;

    if (is_object($post_or_term)) {
        if (isset($post_or_term->post_type)) {
            $object_id = $post_or_term->ID;
            $is_post = true;
        } elseif (isset($post_or_term->term_id)) {
            $object_id = $post_or_term->term_id;
            $is_post = false;
        }
    } elseif (is_array($post_or_term)) {
        $object_id = $post_or_term['ID'] ?? ($post_or_term['term_id'] ?? 0);
        $is_post = isset($post_or_term['post_type']);
    }

    if (!$object_id) return $title;

    // 3. Statik Cache (Hız için kritik!)
    static $acf_label_cache = [];
    if (isset($acf_label_cache[$object_id])) {
        return $acf_label_cache[$object_id];
    }

    // 4. Türkçe Karşılığını Bul
    $default_id = $is_post 
        ? pll_get_post($object_id, $default_lang) 
        : pll_get_term($object_id, $default_lang);

    if ($default_id && $default_id != $object_id) {
        $default_title = $is_post 
            ? get_the_title($default_id) 
            : get_term($default_id)->name;

        if (!empty($default_title) && $default_title !== $title) {
            // Başlığı güncelle (HTML güvenliği için span yerine direkt ekleme yapıyoruz)
            $title .= ' (' . $default_title . ')';
        }
    }

    // Cache'e yaz ve döndür
    return $acf_label_cache[$object_id] = $title;
}
$acf_result_filters = [
    'acf/fields/post_object/result',
    'acf/fields/relationship/result',
    'acf/fields/taxonomy/result'
];
foreach ($acf_result_filters as $filter) {
    add_filter($filter, 'bric_dynamic_acf_label_fix', 10, 4);
}
function bric_dynamic_acf_label_fix($title, $obj, $field, $post_id) {
    return bric_acf_fields_display_default_lang_label($title, $obj, $field, $post_id);
} */