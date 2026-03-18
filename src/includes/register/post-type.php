<?php 

  $labels = array(
        'name' => 'Contacts',
        'singular_name' => 'Contact',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Contact',
        'edit_item' => 'Edit Contact',
        'new_item' => 'New Contact',
        'all_items' => 'All Contacts',
        'view_item' => 'View Contact',
        'search_items' => 'Search Contacts',
        'not_found' =>  'No Contacts found',
        'not_found_in_trash' => 'No Contacts found in Trash', 
        'parent_item_colon' => '',
        'menu_name' => 'Contacts'
  );
  $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true, 
        'show_in_menu' => "iletisim2",
        'query_var' => true,
        'rewrite' => array( 'slug' => 'contact' ),
        'capability_type' => 'post',
        'has_archive' => false, 
        'hierarchical' => false,
        'menu_position' => 5,
        'supports' => array( 'title', 'editor', 'thumbnail' ),
        'taxonomies' => array('contact-type')
  ); 
  register_post_type( 'contact', $args );

  
if(is_admin()){
    /*function add_contact_sub_menu(){
        add_submenu_page( 'anasayfa', 'İletişim Bilgileri', 'İletişim Bilgileri', 'edit_theme_options', 'edit.php?post_type=contact', 'options-contact', 10 );
        add_submenu_page( 'anasayfa', 'İletişim Kategorileri', 'İletişim Kategorileri', 'edit_theme_options', 'edit-tags.php?taxonomy=contact-type&post_type=contact', 'options-contact', 11 );     
    }*/
    function add_contact_sub_menu(){
        // İletişim Bilgileri - edit.php linki
        add_submenu_page( 
            'anasayfa', 
            'İletişim Bilgileri', 
            'İletişim Bilgileri', 
            'edit_theme_options', 
            'edit.php?post_type=contact', 
            '', // 6. Parametre: Callback boş olmalı çünkü linke gidiyoruz
            10  // 7. Parametre: Position
        );

        // İletişim Kategorileri - taxonomy linki
        add_submenu_page( 
            'anasayfa', 
            'İletişim Kategorileri', 
            'İletişim Kategorileri', 
            'edit_theme_options', 
            'edit-tags.php?taxonomy=contact-type&post_type=contact', 
            '', // 6. Parametre: Callback boş
            11  // 7. Parametre: Position
        );      
    }
    add_action('admin_menu', 'add_contact_sub_menu');
}








$labels = array(
          'name' => 'Templates',
          'singular_name' => 'Template',
          'add_new' => 'Add New',
          'add_new_item' => 'Add New Template',
          'edit_item' => 'Edit Template',
          'new_item' => 'New Template',
          'all_items' => 'All Templates',
          'view_item' => 'View Template',
          'search_items' => 'Search Templates',
          'not_found' =>  'No Templates found',
          'not_found_in_trash' => 'No Templates found in Trash', 
          'menu_name' => 'Templates'
);
$args = array(
          'labels' => $labels,
          'public' => true,
          'show_ui' => true,
          'show_in_menu' => true, // 🔹 kendi menüsü yok
          //'supports' => array("title"),
          'supports' => array("title", "editor"),
          'show_in_rest' => true, // 🔹 Gutenberg için bu şart,
          'publicly_queryable' => true,   // ✨ önemli
          'exclude_from_search' => true,  // ✨ opsiyonel
); 
register_post_type('template', $args);

register_taxonomy('template-types', 'template', array(
          'hierarchical' => true,
          'label' => 'Template Types',
          'show_ui' => true,
          'show_admin_column' => true,
          'query_var' => true,
          'rewrite' => array('slug' => 'template-type'),
));

if (is_admin()) {

    /*add_action('admin_init', function() {
        $terms = ['custom', 'header', 'footer', 'modal', 'offcanvas'];
        foreach ($terms as $term) {
            if (!term_exists($term, 'template-types')) {
                wp_insert_term(ucfirst($term), 'template-types', ['slug' => $term]);
            }
        }
    });*/
    add_action('admin_init', function() {
        $terms = ['custom', 'header', 'footer', 'modal', 'offcanvas', 'sidebar']; // Yeni eklediğini buraya çak
        $version = 'v1.1'; // Terim eklediğinde burayı v1.2, v1.3 diye değiştir
        $opt_name = 'template_types_created_' . $version;

        // Eğer bu versiyon zaten işlendiyse sorgu bile atma, siktir et çık
        if (get_option($opt_name)) {
            return;
        }
        $taxonomy = 'template-types';
        if (!taxonomy_exists($taxonomy)) return;
        $inserted = false;
        foreach ($terms as $term) {
            // term_exists hala SELECT atar ama bu kod ömür boyu değil, 
            // versiyon değişince sadece 1 kez çalışacağı için koymaz
            if (!term_exists($term, $taxonomy)) {
                wp_insert_term(ucfirst($term), $taxonomy, ['slug' => $term]);
                $inserted = true;
            }
        }
        // İşlem bittiyse (veya zaten varsa) bu versiyonu bir daha çalıştırma
        update_option($opt_name, true);
    });

    add_action('current_screen', function($screen){

        if (!$screen || $screen->post_type !== 'template') return;

        // Block editor kullanımı filter'ı
        add_filter('use_block_editor_for_post', function($use_block_editor, $post) {
            $block_terms = ['header', 'footer', 'modal', 'offcanvas']; // artık modal ve offcanvas de var

            $terms = wp_get_post_terms($post->ID, 'template-types', ['fields'=>'slugs']);
            $term = !empty($terms) ? $terms[0] : (isset($_GET['template-types']) ? sanitize_text_field($_GET['template-types']) : 'custom');

            if ($term === 'custom') {
                // custom post için block editor kapalı
                return false;
            }

            // allowed block terms varsa editor açık
            return in_array($term, $block_terms);
        }, 10, 2);

        // editor desteğini toggle et
        $block_terms = ['header', 'footer', 'modal', 'offcanvas'];
        $term = 'custom';

        if(isset($_GET['post'])){
            $post_id = intval($_GET['post']);
            $terms = wp_get_post_terms($post_id, 'template-types', ['fields'=>'slugs']);
            if(!is_wp_error($terms) && !empty($terms)) $term = $terms[0];
        } elseif(isset($_GET['template-types'])){
            $term = sanitize_text_field($_GET['template-types']);
        }

        if ($term === 'custom') {
            // sadece custom postlarda editor kaldır
            remove_post_type_support('template', 'editor');
        } elseif (in_array($term, $block_terms)){
            // block terms varsa editor açık
            add_post_type_support('template', 'editor');
        } else {
            // diğer bilinmeyen durumlarda editor kapalı
            remove_post_type_support('template', 'editor');
        }
    });

    /*add_action('admin_menu', function() {

        remove_menu_page('edit.php?post_type=template');
        remove_meta_box('template-typesdiv', 'template', 'side');

        $parent_slug = 'edit.php?post_type=template&template-types=custom';

        add_menu_page(
            'Templates', 
            'Templates', 
            'edit_posts', 
            $parent_slug, 
            '', 
            'dashicons-layout', 
            25
        );

        // Yeni sürüm dostu get_terms kullanımı
        $terms = get_terms(array(
            'taxonomy'   => 'template-types',
            'hide_empty' => false,
        ));

        // 1. HATA KONTROLÜ: Eğer taxonomy yoksa veya boşsa get_terms WP_Error dönebilir
        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        foreach ($terms as $term) {
            // 2. ARRAY MI OBJE MI KONTROLÜ
            // Eğer her ihtimale karşı array gelirse diye objeye cast ediyoruz
            $term_obj = (object) $term;

            // "custom" slug'ına sahip terimi zaten ana menü yaptık, 
            // alt menüde tekrar çıksın istemiyorsan skip edebilirsin:
            if ($term_obj->slug === 'custom') continue;

            add_submenu_page(
                $parent_slug,
                $term_obj->name . ' Templates',
                ucfirst($term_obj->name),
                'edit_posts',
                'edit.php?post_type=template&template-types=' . $term_obj->slug
            );
        }
    });*/
    add_action('admin_menu', function() {

        remove_menu_page('edit.php?post_type=template');
        remove_meta_box('template-typesdiv', 'template', 'side');

        $parent_slug = 'edit.php?post_type=template&template-types=custom';

        // Ana Menü
        add_menu_page(
            'Templates', 
            'Templates', 
            'edit_posts', 
            $parent_slug, 
            '', // Callback boş
            'dashicons-layout', 
            25
        );

        $terms = get_terms(array(
            'taxonomy'   => 'template-types',
            'hide_empty' => false,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $term_obj = (object) $term;

            if ($term_obj->slug === 'custom') continue;

            // Alt Menü - DÜZELTİLDİ
            add_submenu_page(
                $parent_slug,                                            // 1. Parent Slug
                $term_obj->name . ' Templates',                          // 2. Page Title
                ucfirst($term_obj->name),                                // 3. Menu Title
                'edit_posts',                                            // 4. Capability
                'edit.php?post_type=template&template-types=' . $term_obj->slug, // 5. Menu Slug (Link)
                '',                                                       // 6. Callback (BOŞ BIRAKILMALI!)
                1
            );
        }
    });

    add_filter('get_edit_post_link', function($link, $post_id, $context) {
      if (get_post_type($post_id) === 'template' && isset($_GET['template-types'])) {
        $term = sanitize_text_field($_GET['template-types']);
        $link = add_query_arg('template-types', $term, $link);
      }
      return $link;
    }, 10, 3);
    add_action('admin_footer-edit.php', function() {
      global $typenow;

      if ($typenow === 'template' && isset($_GET['template-types'])) {
        $term = esc_attr($_GET['template-types']);
        ?>
        <script type="text/javascript">
          document.addEventListener('DOMContentLoaded', function() {
            const addNewBtn = document.querySelector('.page-title-action');
            if (addNewBtn) {
              addNewBtn.href = addNewBtn.href + '&template-types=<?php echo $term; ?>';
            }
          });
        </script>
        <?php
      }
    });
    add_action('admin_footer-post.php', function() {
        global $post, $typenow;
        if ($typenow === 'template') {
            $terms = wp_get_post_terms($post->ID, 'template-types', array('fields' => 'slugs'));
            if (!empty($terms)) {
                $term = $terms[0];
            } else {
                $term = 'custom'; // default
            }
            ?>
            <script type="text/javascript">
                document.addEventListener('DOMContentLoaded', function() {
                    const addNewBtn = document.querySelector('.page-title-action');
                    if (addNewBtn) {
                        if (!addNewBtn.href.includes('template-types')) {
                            addNewBtn.href = addNewBtn.href + '&template-types=<?php echo esc_js($term); ?>';
                        }
                    }
                });
            </script>
            <?php
        }
    });

    function render_template_type_select($post) {
        $taxonomy = 'template-types';
        
        // Mevcut seçili term'i al
        $current_terms = wp_get_object_terms($post->ID, $taxonomy);
        $selected_id = !empty($current_terms) && !is_wp_error($current_terms) ? $current_terms[0]->term_id : '';

        // Tüm term'leri çek
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);

        // Güvenlik için nonce ekleyelim
        wp_nonce_field('save_template_type_nonce', 'template_type_nonce');

        echo '<select name="template_type_id" id="template_type_id" class="postbox" style="width:100%; height: 35px;">';
        echo '<option value="">Select Type...</option>';
        
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                echo '<option value="' . $term->term_id . '" ' . selected($selected_id, $term->term_id, false) . '>';
                echo $term->name;
                echo '</option>';
            }
        }
        echo '</select>';
        echo '<p class="description" style="margin-top:8px;">Choose a single type for this template.</p>';
    }
    add_action('add_meta_boxes', function() {
        add_meta_box(
            'template_type_select_box',    // ID
            'Template Type',               // Başlık
            'render_template_type_select', // Callback fonksiyonu
            'template',                    // Post Type
            'side',                        // Konum
            'high'                      // Öncelik
        );
    });

    add_filter('acf/prepare_field/name=modal', 'show_hide_groups_by_term');
    add_filter('acf/prepare_field/name=offcanvas', 'show_hide_groups_by_term');
    function show_hide_groups_by_term($field) {
        if(!is_admin()) return $field;
        global $post;
        if (!$post) return $field;
        $terms = wp_get_post_terms($post->ID, 'template-type');
        $selected_slugs = wp_list_pluck($terms, 'slug');
        if (!in_array($field['_name'], $selected_slugs)) {
            $field['wrapper']['class'] .= ' custom-taxonomy-logic';
        }
        return $field;
    }
    
    function acf_taxonomy_toggle_js() {
        ?>
        <script type="text/javascript">
        (function($) {
            if(typeof acf === 'undefined') return;
            function checkTaxonomy() {
                var selectedTerms = [];
                $('#template_type_select_box select option:selected').each(function() {
                    var label = $(this).text().trim().toLowerCase();
                    selectedTerms.push(label);
                });
                var $modalGroup = $('.acf-field[data-name="modal"]');
                var $offcanvasGroup = $('.acf-field[data-name="offcanvas"]');
                if(selectedTerms.includes('modal')) {
                    $modalGroup.show();
                } else {
                    $modalGroup.hide();
                }
                if(selectedTerms.includes('offcanvas')) {
                    $offcanvasGroup.show();
                } else {
                    $offcanvasGroup.hide();
                }
            }
            $(document).on('change', '#template_type_select_box select', function() {
                checkTaxonomy();
            });
            acf.add_action('load', function() {
                checkTaxonomy();
            });
        })(jQuery);
        </script>
        <?php
    }
    add_action('admin_footer', 'acf_taxonomy_toggle_js');

    // Post silindiğinde veya çöpe atıldığında URL'deki template-type parametresini koru
    add_filter('redirect_post_location', function($location) {
        // Sadece 'template' post type için işlem yap
        if (isset($_REQUEST['post_type']) && $_REQUEST['post_type'] === 'template') {
            
            // Eğer URL'de zaten bir template-types parametresi varsa (yönlendirme geliyorsa)
            // veya silme isteği sırasında gönderildiyse onu yakala
            $term = '';
            if (isset($_REQUEST['template-types'])) {
                $term = sanitize_text_field($_REQUEST['template-types']);
            }

            // Eğer bir terim bulduysak, yönlenecek olan yeni URL'ye (location) bunu ekle
            if ($term) {
                $location = add_query_arg('template-types', $term, $location);
            }
        }
        return $location;
    }, 10, 1);

}

add_action('save_post', function($post_id, $post, $update) {
        // 1. GÜVENLİK VE DURUM KONTROLLERİ
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

        if (!$post instanceof WP_Post) {
            $post = get_post($post_id);
            if (!$post) return;
        }

        if ($post->post_type !== 'template') return;

        $status = get_post_status($post_id);
        if (!in_array($status, array('publish', 'draft', 'pending'), true)) return;

        // 2. ŞABLON TÜRÜNÜ BELİRLE
        $taxonomy = 'template-types';
        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'slugs'));
        $term_slug = (!empty($terms) && !is_wp_error($terms)) ? $terms[0] : 'custom';

        // 3. DOSYA VE KLASÖR HAZIRLIĞI
        $theme_dir = get_stylesheet_directory();
        $render_dir = trailingslashit($theme_dir) . 'theme/templates/_custom/';
        if (!file_exists($render_dir)) {
            wp_mkdir_p($render_dir);
        }

        $post_slug = get_post_field('post_name', $post_id) ?: sanitize_title($post->post_title);
        $html_output = '';
        $final_css = '';

        // 4. İÇERİK OLUŞTURMA MANTIĞI
        if ($term_slug === 'custom') {
            // --- SENARYO A: CUSTOM (ACF TABANLI) ---
            $content = get_field('content', $post_id);
            if (empty($content)) return;
            
            $html_output = $content;
            $filename = $post_slug . '.twig'; // Customlar için eski format
        } else {
            // --- SENARYO B: BLOK EDİTÖR TABANLI (Header, Footer, Modal, Offcanvas) ---
            if (!class_exists('Timber')) return;
            $timber_post = Timber::get_post($post_id);
            if (!$timber_post) return;

            $blocks_raw = "";
            if (method_exists($timber_post, 'get_blocks')) {
                $blocks_raw = $timber_post->get_blocks(["seperate_css" => false, "seperate_js" => false])["html"];
            } else {
                $parsed = parse_blocks($timber_post->post_content ?? '');
                foreach ($parsed as $b) { $blocks_raw .= render_block($b); }
            }

            // CSS'i ayıkla
            $html_output = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($matches) use (&$final_css) {
                $final_css .= $matches[1] . "\n";
                return '';
            }, $blocks_raw);
            $html_output = $timber_post->strip_tags($html_output, "<style>");

            // Unused CSS Temizliği
            if (class_exists('RemoveUnusedCss') && defined('STATIC_PATH')) {
                $main_css = @file_get_contents(STATIC_PATH . "css/main-combined.css");
                if ($main_css) {
                    $remover = new RemoveUnusedCss($html_output, $main_css, "", [], false, [
                        "ignore_whitelist" => true,
                        "black_list" => ["html", "body", "footer"],
                        "scope" => "." . $post_slug,
                        "ignore_root_variables" => true
                    ]);
                    $final_css = $remover->process() . $final_css;
                }
            }

            // CSS dosyasını ayrıca kaydet
            file_put_contents($render_dir . $post_slug . ".css", $final_css);
            
            // HTML çıktısına stili ekle
            $html_output .= "<style type='text/css'>" . $final_css . "</style>";

            // Dil takısı ekle
            $lang = 'default';
            if (function_exists('pll_get_post_language')) $lang = pll_get_post_language($post_id);
            elseif (function_exists('qtranxf_getLanguage')) $lang = qtranxf_getLanguage();
            
            $filename = sprintf('%s_%s.twig', $post_slug, $lang);
        }

        // 5. DOSYAYI YAZMA (Gereksiz yazmayı önlemek için Hash kontrolü)
        $filepath = $render_dir . $filename;
        $new_hash = md5($html_output);
        $old_hash = get_post_meta($post_id, '_rendered_html_hash', true);

        if ($old_hash === $new_hash && file_exists($filepath)) {
            return; // Değişiklik yoksa yazma
        }

        // Güvenli yazma (Temp dosya üzerinden)
        $tmp = wp_tempnam($filepath);
        if ($tmp) {
            file_put_contents($tmp, $html_output);
            rename($tmp, $filepath);
            @chmod($filepath, 0644);
        } else {
            file_put_contents($filepath, $html_output);
        }

        // Meta güncelle
        update_post_meta($post_id, '_rendered_html_hash', $new_hash);
        update_post_meta($post_id, 'template', $filename); // Eski kodla uyum için
}, 20, 3);

add_action('save_post_template', function($post_id, $post, $update) {
        // 1. Temel Güvenlik ve Durum Kontrolleri
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $taxonomy = 'template-types';

        // 2. Senaryo A: Metabox üzerinden bir seçim yapıldıysa (Manuel Seçim)
        if (isset($_POST['template_type_id']) && wp_verify_nonce($_POST['template_type_nonce'] ?? '', 'save_template_type_nonce')) {
            $term_id = intval($_POST['template_type_id']);
            
            if ($term_id > 0) {
                wp_set_object_terms($post_id, $term_id, $taxonomy, false);
            } else {
                wp_set_object_terms($post_id, null, $taxonomy);
            }
            return; // Seçim yapıldıysa işlemi burada bitir
        }

        // 3. Senaryo B: Otomatik Atama (Yeni yazı veya parametreli kayıt)
        // Eğer post'un zaten bir terimi varsa dokunma
        $current_terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'slugs'));
        if (empty($current_terms)) {
            // URL'den gelen türü al, yoksa 'custom' ata
            $term_slug = isset($_GET['template-types']) ? sanitize_text_field($_GET['template-types']) : 'custom';
            wp_set_object_terms($post_id, $term_slug, $taxonomy);
        }
}, 10, 3);

function delete_template_twig_file( $post_id ) {
        if ( get_post_type( $post_id ) !== 'template' ) {
            return;
        }
        $file_name = get_post_meta( $post_id, 'template', true );
        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/theme/templates/_custom/';
        $file_path = $templates_dir . $file_name;
        if ( file_exists( $file_path ) ) {
            unlink( $file_path );
        }
}
add_action( 'before_delete_post', 'delete_template_twig_file' );

//if ( is_admin() || wp_is_serving_rest_request() ) {    }