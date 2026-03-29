<?php

/**
 * Post Type Registration — Contact + Template
 */

// ═══════════════════════════════════════════════════════════════
// CONTACT POST TYPE
// ═══════════════════════════════════════════════════════════════

register_post_type('contact', [
    'labels' => [
        'name'               => 'Contacts',
        'singular_name'      => 'Contact',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Contact',
        'edit_item'          => 'Edit Contact',
        'new_item'           => 'New Contact',
        'all_items'          => 'All Contacts',
        'view_item'          => 'View Contact',
        'search_items'       => 'Search Contacts',
        'not_found'          => 'No Contacts found',
        'not_found_in_trash' => 'No Contacts found in Trash',
        'menu_name'          => 'Contacts',
    ],
    'public'              => true,
    'publicly_queryable'  => true,
    'show_ui'             => true,
    'show_in_menu'        => 'iletisim2',
    'query_var'           => true,
    'rewrite'             => ['slug' => 'contact'],
    'capability_type'     => 'post',
    'has_archive'         => false,
    'hierarchical'        => false,
    'menu_position'       => 5,
    'supports'            => ['title', 'editor', 'thumbnail'],
    'taxonomies'          => ['contact-type'],
]);

if (is_admin()) {
    add_action('admin_menu', function() {
        add_submenu_page('anasayfa', 'İletişim Bilgileri', 'İletişim Bilgileri', 'edit_theme_options', 'edit.php?post_type=contact', '', 10);
        add_submenu_page('anasayfa', 'İletişim Kategorileri', 'İletişim Kategorileri', 'edit_theme_options', 'edit-tags.php?taxonomy=contact-type&post_type=contact', '', 11);
    });
}

// ═══════════════════════════════════════════════════════════════
// TEMPLATE POST TYPE
// ═══════════════════════════════════════════════════════════════

register_post_type('template', [
    'labels' => [
        'name'               => 'Templates',
        'singular_name'      => 'Template',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Template',
        'edit_item'          => 'Edit Template',
        'new_item'           => 'New Template',
        'all_items'          => 'All Templates',
        'view_item'          => 'View Template',
        'search_items'       => 'Search Templates',
        'not_found'          => 'No Templates found',
        'not_found_in_trash' => 'No Templates found in Trash',
        'menu_name'          => 'Templates',
    ],
    'public'               => true,
    'show_ui'              => true,
    'show_in_menu'         => true,
    'supports'             => ['title', 'editor'],
    'show_in_rest'         => true,
    'publicly_queryable'   => true,
    'exclude_from_search'  => true,
]);

register_taxonomy('template-types', 'template', [
    'hierarchical'      => true,
    'label'             => 'Template Types',
    'show_ui'           => true,
    'show_admin_column' => true,
    'query_var'         => true,
    'rewrite'           => ['slug' => 'template-type'],
]);


// ─── Template: Admin UI ─────────────────────────────────────

if (is_admin()) {

    // Term seeding (version controlled)
    add_action('admin_init', function() {
        $terms   = ['custom', 'header', 'footer', 'modal', 'offcanvas', 'sidebar'];
        $version = 'v1.1';
        $opt     = 'template_types_created_' . $version;

        if (get_option($opt)) return;
        if (!taxonomy_exists('template-types')) return;

        foreach ($terms as $slug) {
            if (!term_exists($slug, 'template-types')) {
                wp_insert_term(ucfirst($slug), 'template-types', ['slug' => $slug]);
            }
        }
        update_option($opt, true);
    });

    // Block editor toggle — template type'a göre
    add_action('current_screen', function($screen) {
        if (!$screen || $screen->post_type !== 'template') return;

        $block_terms = ['header', 'footer', 'modal', 'offcanvas'];

        add_filter('use_block_editor_for_post', function($use, $post) use ($block_terms) {
            $terms = wp_get_post_terms($post->ID, 'template-types', ['fields' => 'slugs']);
            $term  = $terms[0] ?? ($_GET['template-types'] ?? 'custom');
            return in_array($term, $block_terms);
        }, 10, 2);

        // Editor support toggle
        $term = 'custom';
        if (isset($_GET['post'])) {
            $t = wp_get_post_terms((int) $_GET['post'], 'template-types', ['fields' => 'slugs']);
            if (!is_wp_error($t) && !empty($t)) $term = $t[0];
        } elseif (isset($_GET['template-types'])) {
            $term = sanitize_text_field($_GET['template-types']);
        }

        if (in_array($term, $block_terms)) {
            add_post_type_support('template', 'editor');
        } else {
            remove_post_type_support('template', 'editor');
        }
    });

    // Admin menü — template-types bazlı alt menüler
    add_action('admin_menu', function() {
        remove_menu_page('edit.php?post_type=template');
        remove_meta_box('template-typesdiv', 'template', 'side');

        $parent = 'edit.php?post_type=template&template-types=custom';

        add_menu_page('Templates', 'Templates', 'edit_posts', $parent, '', 'dashicons-layout', 25);

        $terms = get_terms(['taxonomy' => 'template-types', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) return;

        foreach ($terms as $t) {
            if ($t->slug === 'custom') continue;
            add_submenu_page($parent, $t->name . ' Templates', ucfirst($t->name), 'edit_posts', 'edit.php?post_type=template&template-types=' . $t->slug, '', 1);
        }
    });

    // Edit/New link'lerine template-types parametresi ekle
    add_filter('get_edit_post_link', function($link, $post_id, $context) {
        if (get_post_type($post_id) === 'template' && isset($_GET['template-types'])) {
            $link = add_query_arg('template-types', sanitize_text_field($_GET['template-types']), $link);
        }
        return $link;
    }, 10, 3);

    add_action('admin_footer-edit.php', function() {
        global $typenow;
        if ($typenow !== 'template' || !isset($_GET['template-types'])) return;
        $term = esc_attr($_GET['template-types']);
        echo "<script>document.addEventListener('DOMContentLoaded',function(){var b=document.querySelector('.page-title-action');if(b)b.href+='&template-types={$term}';});</script>";
    });

    add_action('admin_footer-post.php', function() {
        global $post, $typenow;
        if ($typenow !== 'template') return;
        $terms = wp_get_post_terms($post->ID, 'template-types', ['fields' => 'slugs']);
        $term  = esc_js($terms[0] ?? 'custom');
        echo "<script>document.addEventListener('DOMContentLoaded',function(){var b=document.querySelector('.page-title-action');if(b&&!b.href.includes('template-types'))b.href+='&template-types={$term}';});</script>";
    });

    // Template Type metabox (select dropdown)
    add_action('add_meta_boxes', function() {
        add_meta_box('template_type_select_box', 'Template Type', 'render_template_type_metabox', 'template', 'side', 'high');
    });

    function render_template_type_metabox($post) {
        $current = wp_get_object_terms($post->ID, 'template-types');
        $sel_id  = (!empty($current) && !is_wp_error($current)) ? $current[0]->term_id : '';
        $terms   = get_terms(['taxonomy' => 'template-types', 'hide_empty' => false]);

        wp_nonce_field('save_template_type_nonce', 'template_type_nonce');

        echo '<select name="template_type_id" id="template_type_id" style="width:100%;height:35px;">';
        echo '<option value="">Select Type...</option>';
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                echo '<option value="' . (int) $t->term_id . '"' . selected($sel_id, $t->term_id, false) . '>' . esc_html($t->name) . '</option>';
            }
        }
        echo '</select>';
        echo '<p class="description" style="margin-top:8px;">Choose a single type for this template.</p>';
    }

    // ACF field visibility — template type'a göre modal/offcanvas field'larını gizle/göster
    add_filter('acf/prepare_field/name=modal', 'template_type_field_visibility');
    add_filter('acf/prepare_field/name=offcanvas', 'template_type_field_visibility');

    function template_type_field_visibility($field) {
        global $post;
        if (!$post || !is_admin()) return $field;

        $terms = wp_get_post_terms($post->ID, 'template-types', ['fields' => 'slugs']);
        if (!in_array($field['_name'], $terms)) {
            $field['wrapper']['class'] .= ' custom-taxonomy-logic';
        }
        return $field;
    }

    // ACF field toggle JS
    add_action('admin_footer', function() {
        global $typenow;
        if ($typenow !== 'template') return;
        ?>
        <script>
        (function($){
            if(typeof acf==='undefined')return;
            function check(){
                var sel=$('#template_type_select_box select option:selected').text().trim().toLowerCase();
                $('.acf-field[data-name="modal"]').toggle(sel==='modal');
                $('.acf-field[data-name="offcanvas"]').toggle(sel==='offcanvas');
            }
            $(document).on('change','#template_type_select_box select',check);
            acf.add_action('load',check);
        })(jQuery);
        </script>
        <?php
    });

    // Redirect'te template-types parametresini koru
    add_filter('redirect_post_location', function($location) {
        if (isset($_REQUEST['post_type'], $_REQUEST['template-types']) && $_REQUEST['post_type'] === 'template') {
            $location = add_query_arg('template-types', sanitize_text_field($_REQUEST['template-types']), $location);
        }
        return $location;
    });

} // end is_admin()


// ─── Template: Save Post — Twig Dosyası Oluşturma ──────────

add_action('save_post', function($post_id, $post, $update) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if (!($post instanceof WP_Post)) $post = get_post($post_id);
    if (!$post || $post->post_type !== 'template') return;
    if (!in_array(get_post_status($post_id), ['publish', 'draft', 'pending'])) return;

    $taxonomy  = 'template-types';
    $terms     = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
    $term_slug = (!empty($terms) && !is_wp_error($terms)) ? $terms[0] : 'custom';

    $theme_dir  = get_stylesheet_directory();
    $render_dir = trailingslashit($theme_dir) . 'theme/templates/_custom/';
    if (!file_exists($render_dir)) wp_mkdir_p($render_dir);

    $post_slug   = get_post_field('post_name', $post_id) ?: sanitize_title($post->post_title);
    $html_output = '';
    $final_css   = '';

    if ($term_slug === 'custom') {
        // Custom: ACF content field
        $content = get_field('content', $post_id);
        if (empty($content)) return;
        $html_output = $content;
        $filename    = $post_slug . '.twig';
    } else {
        // Block editor: header, footer, modal, offcanvas
        if (!class_exists('Timber')) return;
        $timber_post = Timber::get_post($post_id);
        if (!$timber_post) return;

        $blocks_raw = '';
        if (method_exists($timber_post, 'get_blocks')) {
            $blocks_raw = $timber_post->get_blocks(['seperate_css' => false, 'seperate_js' => false])['html'];
        } else {
            foreach (parse_blocks($timber_post->post_content ?? '') as $b) {
                $blocks_raw .= render_block($b);
            }
        }

        // CSS'i HTML'den ayıkla
        $html_output = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($m) use (&$final_css) {
            $final_css .= $m[1] . "\n";
            return '';
        }, $blocks_raw);
        $html_output = $timber_post->strip_tags($html_output, '<style>');

        // Unused CSS temizliği
        if (class_exists('RemoveUnusedCss') && defined('STATIC_PATH')) {
            $main_css = @file_get_contents(STATIC_PATH . 'css/main-combined.css');
            if ($main_css) {
                $remover = new RemoveUnusedCss($html_output, $main_css, '', [], false, [
                    'ignore_whitelist'      => true,
                    'black_list'            => ['html', 'body', 'footer'],
                    'scope'                 => '.' . $post_slug,
                    'ignore_root_variables' => true,
                ]);
                $final_css = $remover->process() . $final_css;
            }
        }

        file_put_contents($render_dir . $post_id . '.css', $final_css);
        $html_output .= "<style type='text/css'>" . $final_css . '</style>';

        // Dil suffix'i
        $file_lang = 'default';
        if (function_exists('pll_get_post_language'))  $file_lang = pll_get_post_language($post_id);
        elseif (function_exists('qtranxf_getLanguage')) $file_lang = qtranxf_getLanguage();

        $filename = sprintf('%s_%s.twig', $post_id, $file_lang);
    }

    // Hash kontrolü — değişiklik yoksa yazma
    $filepath = $render_dir . $filename;
    $new_hash = md5($html_output);
    $old_hash = get_post_meta($post_id, '_rendered_html_hash', true);

    if ($old_hash === $new_hash && file_exists($filepath)) return;

    // Güvenli yazma
    $tmp = wp_tempnam($filepath);
    if ($tmp) {
        file_put_contents($tmp, $html_output);
        rename($tmp, $filepath);
        @chmod($filepath, 0644);
    } else {
        file_put_contents($filepath, $html_output);
    }

    update_post_meta($post_id, '_rendered_html_hash', $new_hash);
    update_post_meta($post_id, 'template', $filename);
    update_post_meta($post_id, 'css', $post_id . '.css');
}, 20, 3);

// ─── Template: Term Ataması (Save) ──────────────────────────

add_action('save_post_template', function($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $taxonomy = 'template-types';

    // Metabox'tan seçim yapıldıysa
    if (isset($_POST['template_type_id']) && wp_verify_nonce($_POST['template_type_nonce'] ?? '', 'save_template_type_nonce')) {
        $term_id = (int) $_POST['template_type_id'];
        wp_set_object_terms($post_id, $term_id > 0 ? $term_id : null, $taxonomy, false);
        return;
    }

    // Otomatik atama — term yoksa URL'den veya default 'custom'
    $current = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
    if (empty($current)) {
        $slug = sanitize_text_field($_GET['template-types'] ?? 'custom');
        wp_set_object_terms($post_id, $slug, $taxonomy);
    }
}, 10, 3);

// ─── Template: Twig Dosyası Silme ───────────────────────────

add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) !== 'template') return;

    $filename = get_post_meta($post_id, 'template', true);
    if (empty($filename)) return;

    $filepath = get_stylesheet_directory() . '/theme/templates/_custom/' . $filename;
    if (file_exists($filepath)) @unlink($filepath);

    $css_file = get_stylesheet_directory() . '/theme/templates/_custom/' . $post_id . '.css';
    if (file_exists($css_file)) @unlink($css_file);
});