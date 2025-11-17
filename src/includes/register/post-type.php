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
        'supports' => array( 'title', 'editor', 'thumbnail' )
  ); 
  register_post_type( 'contact', $args );

  add_action('admin_menu', 'add_contact_sub_menu');

  function add_contact_sub_menu(){
	  add_submenu_page( 'anasayfa', 'İletişim Bilgileri', 'İletişim Bilgileri', 'edit_theme_options', 'edit.php?post_type=contact', 'options-contact', 10 );
	  add_submenu_page( 'anasayfa', 'İletişim Kategorileri', 'İletişim Kategorileri', 'edit_theme_options', 'edit-tags.php?taxonomy=contact-type&post_type=contact', 'options-contact', 11 );  	
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
      'show_in_menu' => false, // 🔹 kendi menüsü yok
      //'supports' => array("title"),
      'supports' => array("title", "editor"),
      'show_in_rest' => true, // 🔹 Gutenberg için bu şart,
      'publicly_queryable' => true,   // ✨ önemli
      'exclude_from_search' => true,  // ✨ opsiyonel
); 
register_post_type('template', $args);

register_taxonomy('template-types', 'template', array(
      'hierarchical' => false,
      'label' => 'Template Types',
      'show_ui' => false,
      'show_admin_column' => true,
      'query_var' => true,
      'rewrite' => array('slug' => 'template-type'),
));

$terms = ['custom', 'header', 'footer', 'modal', 'offcanvas'];
foreach ($terms as $term) {
    if (!term_exists($term, 'template-types')) {
        wp_insert_term(ucfirst($term), 'template-types', ['slug' => $term]);
    }
}

if (is_admin()) {


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


    add_action('admin_menu', function() {
        add_menu_page(
            'Templates', 
            'Templates', 
            'edit_posts', 
            'edit.php?post_type=template&template-types=custom', // 🔹 burası değişti
            '', 
            'dashicons-layout', 
            25
        );
        $terms = get_terms(array(
            'taxonomy' => 'template-types',
            'hide_empty' => false
        ));
        foreach ($terms as $term) {
            add_submenu_page(
                'edit.php?post_type=template&template-types=custom', // 🔹 ana menü slug ile eşleşmeli
                $term->name . ' Templates',
                ucfirst($term->name),
                'edit_posts',
                'edit.php?post_type=template&template-types=' . $term->slug
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
    add_action('save_post_template', function($post_id, $post, $update) {
        if ( get_post_type( $post_id ) !== 'template' ) return;
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
        $terms = wp_get_post_terms($post_id, 'template-types', array('fields' => 'slugs'));
        if (!empty($terms)) return;
        $term_slug = isset($_GET['template-types']) ? sanitize_text_field($_GET['template-types']) : 'custom';
        wp_set_object_terms($post_id, $term_slug, 'template-types');
    }, 10, 3);

    function save_template_as_twig( $post_id ) {
        // Post türü kontrolü
        if ( get_post_type( $post_id ) !== 'template' ) {
            return;
        }

        // Autosave veya revizyon kontrolü
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Post durumu kontrolü (publish veya draft olmalı)
        $post_status = get_post_status( $post_id );
        if ( $post_status !== 'publish' && $post_status !== 'draft' ) {
            return;
        }

        // Post slug'ını ve içeriği al
        $post_slug = get_post_field( 'post_name', $post_id );
        $content = get_field( 'content', $post_id );

        // İçerik boşsa işlem yapma
        if ( empty( $content ) ) {
            return;
        }

        $theme_dir = get_stylesheet_directory();
        $templates_dir = $theme_dir . '/theme/templates/_custom/'; // "themes" yerine "theme"

        // Klasör yoksa oluştur
        if ( ! file_exists( $templates_dir ) ) {
            mkdir( $templates_dir, 0755, true );
        }

        // Meta değerini kontrol et
        $existing_file_name = get_post_meta( $post_id, 'template', true );

        // Eğer mevcut bir dosya varsa, dosyayı sil
        if ( !empty( $existing_file_name ) ) {
            $existing_file_path = $templates_dir . $existing_file_name;
            if ( file_exists( $existing_file_path ) ) {
                unlink( $existing_file_path );
            }
        }

        // Yeni dosya adını oluştur
        $new_file_name = $post_slug . '.twig';
        $file_path = $templates_dir . $new_file_name;

        // Dosya içeriğini oluştur veya güncelle
        file_put_contents( $file_path, $content );

        // Post meta değerini güncelle
        update_post_meta( $post_id, 'template', $new_file_name );
    }
    add_action( 'save_post', 'save_template_as_twig', 10, 3 );
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


    /**
     * Save rendered block HTML for 'template' post type into theme/templates/_custom/
     * - Only runs for post_type = 'template'
     * - Only runs for templates with template-types in allowed list (not 'custom')
     * - Uses Timber::get_post and $post->get_blocks() if available, otherwise falls back to parse_blocks + render_block
     * - Writes files like: {post_name}_{lang}.html into theme/templates/_custom/
     * - Saves hash meta to avoid unnecessary writes
     */

    add_action( 'save_post', function( $post_id, $post, $update ) {
        // Basic guards
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Ensure we have a WP_Post object
        if ( ! $post instanceof WP_Post ) {
            $post = get_post( $post_id );
            if ( ! $post ) return;
        }

        // Only run for our CPT
        if ( $post->post_type !== 'template' ) {
            return;
        }

        // Only run for publish or draft (adjust if needed)
        $status = get_post_status( $post_id );
        if ( ! in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ) {
            return;
        }

        // Get template-type terms and allow only these (skip 'custom' or empty)
        $allowed_terms = array( 'header', 'footer', 'modal', 'offcanvas' );
        $terms = wp_get_post_terms( $post_id, 'template-types', array( 'fields' => 'slugs' ) );

        if ( is_wp_error( $terms ) ) {
            return;
        }

        if ( empty( $terms ) ) {
            // Eğer term atanmadıysa işlem yapma (isteğe göre default atama yapılabilir)
            return;
        }

        // Eğer post birden fazla term'e sahipse ilkini alıyoruz (gerekiyorsa değiştir)
        $term_slug = $terms[0];

        // Eğer term 'custom' ise burayı atla (custom'lar twig kullanıyor)
        if ( $term_slug === 'custom' ) {
            return;
        }

        // sadece allowed list içindeyse devam et
        if ( ! in_array( $term_slug, $allowed_terms, true ) ) {
            return;
        }

        // Timber hazır mı?
        if ( ! class_exists( 'Timber' ) ) {
            // Timber yoksa yine de WP core render ile deneyebiliriz
            // ama burada Timber beklediğin için çık
            return;
        }

        // Timber post al
        $timber_post = Timber::get_post( $post_id );
        if ( ! $timber_post ) return;

        // BLOCKS: öncelikle Timber Post->get_blocks() kullan
        $blocks = "";
        $html_output = '';
        $html = "";
        $css = "";

        if ( method_exists( $timber_post, 'get_blocks' ) ) {

            $blocks = $timber_post->get_blocks([
                "seperate_css" => false,
                "seperate_js"  => false
            ]);

        } else {

            $raw_content = $timber_post->post_content ?? '';
            if ( $raw_content ) {
                $parsed = parse_blocks( $raw_content );
                foreach ( $parsed as $b ) {
                    $blocks .= render_block( $b );
                }
            }
            
        }

        $html = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($matches) use (&$css) {
            $css .= $matches[1] . "\n";
            return '';
        }, $blocks);

        $html = $timber_post->strip_tags($html, "<style>");
        $content = array(
            "html" => $html,
            "css" => $css
        );
        $html_output .= (string) $content["html"];//$blocks;

        // Eğer hiç içerik yoksa çık
        if ( empty( trim( $html_output ) ) ) {
            return;
        }

        // Dil suffix: Polylang, qTranslate ve fallback
        $lang = '';
        if ( function_exists( 'pll_get_post_language' ) ) {
            $lang = pll_get_post_language( $post_id ); // örn 'tr' veya 'en'
        } elseif ( function_exists( 'qtranxf_getLanguage' ) ) {
            $lang = qtranxf_getLanguage();
        } else {
            $locale = get_locale();
            $lang = substr( $locale, 0, 2 );
        }
        if ( ! $lang ) $lang = 'default';

        // Kayıt klasörü: tema içinde theme/templates/_custom/
        $theme_dir   = get_stylesheet_directory();
        $render_dir  = trailingslashit( $theme_dir ) . 'theme/templates/_custom/';

        // Güvenli klasör oluştur
        if ( ! file_exists( $render_dir ) ) {
            wp_mkdir_p( $render_dir );
        }

        // Dosya adı: slug_lang.html
        $slug = get_post_field( 'post_name', $post_id );
        if ( ! $slug ) {
            $slug = sanitize_title( $post->post_title );
        }
        $filename = sprintf( '%s_%s.twig', $slug, $lang );
        $filepath = $render_dir . $filename;

        $input = file_get_contents(STATIC_PATH ."css/main-combined.css");
        $remover = new RemoveUnusedCss($html_output, $input, "", [], false, [
            "ignore_whitelist" => true,
            "black_list" => ["html", "body", "footer"],
            "scope" => ".".$slug,
            "ignore_root_variables" => true
        ]);
        $css = $remover->process();
        file_put_contents( $render_dir . $slug . ".css", $css.$content["css"] );
        $html_output .= "<style type='text/css'>".$css.$content["css"]."</style>";


        // Hash kontrolü: gereksiz yazmayı engelle
        $new_hash = md5( $html_output );
        $old_hash = get_post_meta( $post_id, '_rendered_html_hash', true );

        if ( $old_hash === $new_hash && file_exists( $filepath ) ) {
            // Değişiklik yok, iş bitti
            return;
        }

        // Yaz (temp fhandle + chmod daha güvenli)
        $tmp = wp_tempnam( $filepath );
        if ( $tmp ) {
            file_put_contents( $tmp, $html_output );
            // move
            rename( $tmp, $filepath );
            @chmod( $filepath, 0644 );
        } else {
            // fallback direkt yaz
            file_put_contents( $filepath, $html_output );
            @chmod( $filepath, 0644 );
        }

        // Hash'ı kaydet
        update_post_meta( $post_id, '_rendered_html_hash', $new_hash );

    }, 20, 3 );


}
