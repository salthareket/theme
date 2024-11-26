<?php
/**/
add_filter('pll_get_post_types--', function($post_types) {
    // Özel post türlerinizi burada ekleyin
    $custom_post_types = get_post_types(['public' => true, '_builtin' => false]);
    foreach ($custom_post_types as $post_type) {
        $post_types[$post_type] = $post_type;
    }
    return $post_types;
});
function pll_register_strings_for_custom_post_types() {
    $custom_post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
    foreach ($custom_post_types as $post_type) {
        //if(pll_is_translated_post_type( $post_type )){
            // Polylang'ın çeviri yönetiminde kullanılacak etiketleri kaydedin
            pll_register_string("post type name {$post_type->name}", $post_type->labels->name);
            pll_register_string("post type singular name {$post_type->name}", $post_type->labels->singular_name);
            /*pll_register_string("post type add new {$post_type->name}", $post_type->labels->add_new);
            pll_register_string("post type add new item {$post_type->name}", $post_type->labels->add_new_item);
            pll_register_string("post type edit item {$post_type->name}", $post_type->labels->edit_item);
            pll_register_string("post type new item {$post_type->name}", $post_type->labels->new_item);
            pll_register_string("post type view item {$post_type->name}", $post_type->labels->view_item);
            pll_register_string("post type search items {$post_type->name}", $post_type->labels->search_items);
            pll_register_string("post type not found {$post_type->name}", $post_type->labels->not_found);
            pll_register_string("post type not found in trash {$post_type->name}", $post_type->labels->not_found_in_trash);
            pll_register_string("post type parent item colon {$post_type->name}", $post_type->labels->parent_item_colon);
            pll_register_string("post type all items {$post_type->name}", $post_type->labels->all_items);
            pll_register_string("post type archives {$post_type->name}", $post_type->labels->archives);
            pll_register_string("post type attributes {$post_type->name}", $post_type->labels->attributes);
            pll_register_string("post type insert into item {$post_type->name}", $post_type->labels->insert_into_item);
            pll_register_string("post type uploaded to this item {$post_type->name}", $post_type->labels->uploaded_to_this_item);
            pll_register_string("post type featured image {$post_type->name}", $post_type->labels->featured_image);
            pll_register_string("post type set featured image {$post_type->name}", $post_type->labels->set_featured_image);
            pll_register_string("post type remove featured image {$post_type->name}", $post_type->labels->remove_featured_image);
            pll_register_string("post type use featured image {$post_type->name}", $post_type->labels->use_featured_image);*/
            pll_register_string("post type menu name {$post_type->name}", $post_type->labels->menu_name);
            //pll_register_string("post type filter items list {$post_type->name}", $post_type->labels->filter_items_list);
            //pll_register_string("post type items list navigation {$post_type->name}", $post_type->labels->items_list_navigation);
            //pll_register_string("post type items list {$post_type->name}", $post_type->labels->items_list);            
        //}
    }
}
add_action('init', 'pll_register_strings_for_custom_post_types');







function pll_register_strings_for_custom_taxonomies() {
    $custom_taxonomies = get_taxonomies(['public' => true, '_builtin' => false], 'objects');
    foreach ($custom_taxonomies as $taxonomy) {
        // Taksonomi adını çeviri yönetimine kaydet
        pll_register_string("taxonomy name {$taxonomy->name}", $taxonomy->label);

        // Varsayılan terim etiketleri (sadece name, singular_name ve menu_name için)
        pll_register_string("taxonomy singular name {$taxonomy->name}", $taxonomy->labels->singular_name);
        pll_register_string("taxonomy menu name {$taxonomy->name}", $taxonomy->labels->menu_name);
    }
}
add_action('init', 'pll_register_strings_for_custom_taxonomies');







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
add_filter('timber/render/file', function ($file) {
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
                        $template_path = get_stylesheet_directory()."/".Timber::$dirname[0] . '/' . $template_name;
                        if (file_exists($template_path)) {
                            return $template_name; // Geçerli template dosyasını döndür
                        }
                    }
                }
            }
        }
    }
    return $file;
}, 10);








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







// Add translate and duplicate function to taxonomies
add_action('admin_init', 'register_bulk_term_translate_action');
function register_bulk_term_translate_action() {
    $taxonomies = get_taxonomies(array('public' => true), 'names');
    foreach ($taxonomies as $taxonomy) {
        add_filter("bulk_actions-edit-$taxonomy", 'add_bulk_term_translate_action');
    }
}
function add_bulk_term_translate_action($bulk_actions) {
    $bulk_actions['bulk_translate_terms'] = __('Translate Terms', 'polylang');
    return $bulk_actions;
}
add_action('admin_action_bulk_translate_terms', 'handle_bulk_translate_terms_action');
function handle_bulk_translate_terms_action() {
    // Güvenlik kontrolü
    if (!current_user_can('manage_categories')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    // Seçilen terim ID'lerini al
    $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field($_REQUEST['taxonomy']) : '';
    $term_ids_param = isset($_REQUEST['delete_tags']) ? $_REQUEST['delete_tags'] : '';

    // Term IDs'yi işleme
    if (is_array($term_ids_param)) {
        $term_ids = array_map('intval', $term_ids_param);
    } else {
        $term_ids = array_map('intval', explode(',', $term_ids_param));
    }

    // Eğer term_ids boşsa hata döndür
    if (empty($term_ids)) {
        wp_die(__('No terms selected for translation.'));
    }

    // Her bir terim ID için çeviri işlemini gerçekleştir
    foreach ($term_ids as $term_id) {
        if ($term_id <= 0) {
            error_log('Geçersiz terim ID: ' . $term_id);
            continue; // Geçersiz ID'leri atla
        }

        // Çeviri işlevi burada çağrılmalı
        translate_term($term_id);
    }

    // İşlem sonrası yönlendirme
    $sendback = remove_query_arg(array('bulk_translate_terms', 'untrashed', 'deleted', 'message', 'ids'), wp_get_referer());
    $sendback = add_query_arg('bulk_translate_terms', count($term_ids), $sendback);
    wp_redirect($sendback);
    exit;
}
function translate_term($term_id) {
    if (!function_exists('pll_get_term')) {
        error_log('Polylang fonksiyonları bulunamadı.');
        return;
    }

    $default_lang = pll_default_language();

    // Terimi al
    $term = get_term($term_id);
    if (is_wp_error($term) || !$term || $term->term_id != $term_id) {
        error_log('Terim alınamadı veya bir hata oluştu: ' . $term_id);
        return;
    }

    // Terim dilini al
    $current_lang = pll_get_term_language($term_id);
    if (!$current_lang) {
        $current_lang = $default_lang;
        error_log('Terim dil bilgisi bulunamadı, varsayılan dil kullanıldı: ' . $current_lang);
    }

    error_log('Varsayılan dil: ' . $default_lang);
    error_log('Mevcut dil: ' . $current_lang);

    // Varsayılan dilde olup olmadığını kontrol et
    if ($current_lang !== $default_lang) {
        error_log('Terim zaten varsayılan dilde değil.');
        return;
    }

    $languages = pll_languages_list();

    foreach ($languages as $lang) {
        if ($lang === $default_lang) {
            continue;
        }

        $translated_term_id = pll_get_term($term_id, $lang);
        if ($translated_term_id) {
            error_log('Bu dil için zaten çeviri var: ' . $lang);
            continue;
        }

        $translated_term_args = array(
            'description' => $term->description,
            'slug' => $term->slug . '-' . $lang,
        );

        $new_term = wp_insert_term($term->name, $term->taxonomy, $translated_term_args);

        if (is_wp_error($new_term) || !$new_term) {
            error_log('Yeni terim oluşturulamadı: ' . print_r($new_term, true));
            continue;
        }

        $new_term_id = $new_term['term_id'];

        pll_set_term_language($new_term_id, $lang);
        pll_save_term_translations(array(
            $default_lang => $term_id,
            $lang => $new_term_id,
        ));

        $term_meta = get_term_meta($term_id);
        foreach ($term_meta as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                add_term_meta($new_term_id, $meta_key, maybe_unserialize($meta_value));
            }
        }

        error_log('Yeni terim başarıyla oluşturuldu ve ilişkilendirildi: ' . $new_term_id);
    }
}
add_action('admin_footer', 'add_bulk_action_translate_term_js');
function add_bulk_action_translate_term_js() {
    $screen = get_current_screen();
    if ($screen->base == 'edit-tags') {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('select[name="action"], select[name="action2"]').find('option[value="bulk_translate_terms"]').remove(); // Mevcut seçeneği kaldır
            $('select[name="action"], select[name="action2"]').append('<option value="bulk_translate_terms"><?php _e('Translate Terms', 'polylang'); ?></option>');
        });
        </script>
        <?php
    }
}


add_filter('pll_copy_post_metas', function($meta_keys, $sync, $from, $to) {
    // Senkronizasyonu devre dışı bırakmak istediğin meta key'ler
    $excluded_meta_keys = ['assets']; 
    
    // Filtreyi uygula ve istenen meta key'leri senkronizasyondan çıkar
    return array_diff($meta_keys, $excluded_meta_keys);
}, 10, 4);





function pll_get_post_type_archive_link($post_types = '', $lang = ''){
    $current_lang = pll_current_language() === pll_default_language() ? '' : '/' . pll_current_language();
    if ($lang != '') {
        $current_lang = $lang == pll_default_language() ? '' : '/' . $lang;
    }
    $re = '/(https?:\/\/[A-z.]*)(.*)/';
    $str = get_post_type_archive_link($post_types);
    $subst = "$1" . $current_lang . "$2";
    return preg_replace($re, $subst, $str);
}
