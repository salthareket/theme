<?php

/**
 * Templates App Bootstrap
 *
 * Template post type, template-types taxonomy ve tüm admin/save hook'larını yönetir.
 * Theme Settings altında submenu olarak gösterilir.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-07-29
 *     - Taşındı: register/post-type.php'den apps/templates/bootstrap.php'ye
 *     - Add: Sadece başlık/type/tarih/modified columns
 *     - Add: Tüm tarihler dropdown kaldırıldı, template-types filter eklendi
 *     - Add: Theme Settings altında submenu (native menü kaldırıldı)
 */

// ─── Post Type ───────────────────────────────────────────────

require_once __DIR__ . '/OffcanvasRegistry.php';

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

// ─── Admin UI ────────────────────────────────────────────────

if (is_admin()) {

    // Columns: sadece başlık, template type, tarih, modified
    add_filter('manage_template_posts_columns', function($cols) {
        return [
            'cb'                      => $cols['cb'],
            'title'                   => __('Başlık'),
            'taxonomy-template-types' => __('Template Type'),
            'date'                    => __('Tarih'),
            'modified'                => __('Modified'),
        ];
    });

    add_action('manage_template_posts_custom_column', function($col, $post_id) {
        if ($col === 'modified') {
            echo get_the_modified_date('d.m.Y, H:i', $post_id);
        }
    }, 10, 2);

    add_filter('manage_edit-template_sortable_columns', function($cols) {
        $cols['modified'] = 'modified';
        return $cols;
    });

    // "Tüm tarihler" dropdown kaldır, template-types filter ekle
    add_filter('months_dropdown_results', function($months, $post_type) {
        if ($post_type === 'template') return [];
        return $months;
    }, 10, 2);

    add_action('restrict_manage_posts', function($post_type) {
        if ($post_type !== 'template') return;
        $current = sanitize_key($_GET['template-types'] ?? '');
        $terms   = get_terms(['taxonomy' => 'template-types', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) return;
        echo '<select name="template-types" id="filter-template-types">';
        foreach ($terms as $term) {
            printf(
                '<option value="%s"%s>%s (%d)</option>',
                esc_attr($term->slug),
                selected($current, $term->slug, false),
                esc_html(ucfirst($term->name)),
                (int)$term->count
            );
        }
        echo '</select>';
    });

    add_action('pre_get_posts', function($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ($query->get('post_type') !== 'template') return;
        if (!empty($_GET['template-types'])) {
            $query->set('tax_query', [[
                'taxonomy' => 'template-types',
                'field'    => 'slug',
                'terms'    => sanitize_key($_GET['template-types']),
            ]]);
        }
    });

    // Term seeding
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

    // Admin menü — native kaldır, erişim kontrolü için hidden olarak kaydet (menüde görünmez)
    add_action('admin_menu', function() {
        remove_menu_page('edit.php?post_type=template');
        remove_meta_box('template-typesdiv', 'template', 'side');
        // Erişim için kayıtlı ama DashboardAdmin cleanup'ı $submenu'den siler
        add_submenu_page('theme-settings', 'Templates', 'Templates', 'edit_posts', 'edit.php?post_type=template');
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

    // Template Type metabox
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

    // ACF field visibility
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

    add_filter('redirect_post_location', function($location) {
        if (isset($_REQUEST['post_type'], $_REQUEST['template-types']) && $_REQUEST['post_type'] === 'template') {
            $location = add_query_arg('template-types', sanitize_text_field($_REQUEST['template-types']), $location);
        }
        return $location;
    });

} // end is_admin()

// ─── Save Post — Header Template Block Temizleyici ───────────────────────────
// Header tipindeki template kaydedilince post_content parse edilir.
// Root level'da acf/header-builder dışındaki block'lar silinir.
// Bu sayede yanlışlıkla eklenen core/paragraph vb. template'i bozmaz.

add_action( 'save_post_template', function ( int $post_id, \WP_Post $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;

    // Sadece header tipi
    $terms = wp_get_post_terms( $post_id, 'template-types', [ 'fields' => 'slugs' ] );
    if ( is_wp_error( $terms ) || ! in_array( 'header', $terms, true ) ) return;

    $content = $post->post_content ?? '';
    if ( empty( $content ) ) return;

    $blocks  = parse_blocks( $content );
    $cleaned = array_filter( $blocks, function ( array $b ): bool {
        $name = $b['blockName'] ?? '';
        // null/boş = WP separator — koru
        if ( $name === '' || $name === null ) return true;
        return $name === 'acf/header-builder';
    } );

    // Değişiklik yoksa dokunma
    if ( count( $cleaned ) === count( $blocks ) ) return;

    $new_content = implode( '', array_map( 'serialize_block', array_values( $cleaned ) ) );

    // Döngüyü önle
    remove_action( 'save_post_template', __FUNCTION__, 5 );

    wp_update_post( [
        'ID'           => $post_id,
        'post_content' => $new_content,
    ] );

    add_action( 'save_post_template', __FUNCTION__, 5, 2 );

}, 5, 2 );



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

    $post_slug = get_post_field('post_name', $post_id) ?: sanitize_title($post->post_title);

    if ($term_slug === 'custom') {
        $content = get_field('content', $post_id);
        if (empty($content)) return;
        $filename = $post_slug . '.twig';
        _template_save_file($render_dir, $filename, $content, $post_id);
        return;
    }

    if (!class_exists('Timber')) return;
    $timber_post = Timber::get_post($post_id);
    if (!$timber_post) return;

    $auth_cookies = [];
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'wordpress_logged_in') === 0 || strpos($name, 'wordpress_sec') === 0) {
            $auth_cookies[] = new WP_Http_Cookie(['name' => $name, 'value' => $value]);
        }
    }

    $logout_result = _template_fetch_and_process($post_id, $timber_post, $post_slug, []);
    $logged_result = !empty($auth_cookies)
        ? _template_fetch_and_process($post_id, $timber_post, $post_slug, $auth_cookies)
        : null;

    $final_css   = '';
    $html_output = '';

    // CSS: logout ve logged fetch AYNI main-combined'i AYNI scope ile purge
    // ettiginden ciktilari buyuk olcude ozdes — ikisini birlestirmek TAM
    // DUPLICATE CSS uretiyordu. Bu yuzden CSS'i yalnizca BIR fetch'ten al
    // (logout oncelikli, yoksa logged). HTML ise login/logout header farki
    // icin gerekiyorsa ayri tutulur.
    if ($logout_result) {
        $final_css   = $logout_result['css'];
        $html_output = $logout_result['html'];
    }
    if ($logged_result) {
        if (empty($final_css))   $final_css   = $logged_result['css'];
        if (empty($html_output)) $html_output = $logged_result['html'];
    }

    if (empty($html_output) && empty($final_css)) return;

    if (!empty($final_css) && class_exists('MatthiasMullie\Minify\CSS')) {
        $css_minifier = new \MatthiasMullie\Minify\CSS();
        $css_minifier->add($final_css);
        $final_css = $css_minifier->minify();
    }

    file_put_contents($render_dir . $post_id . '.css', $final_css);
    $html_output .= "<style type='text/css'>" . $final_css . '</style>';

    $file_lang = 'default';
    if (function_exists('pll_get_post_language'))  $file_lang = pll_get_post_language($post_id);
    elseif (function_exists('qtranxf_getLanguage')) $file_lang = qtranxf_getLanguage();

    $filename = sprintf('%s_%s.twig', $post_id, $file_lang);
    _template_save_file($render_dir, $filename, $html_output, $post_id);
    update_post_meta($post_id, 'template', $filename);
    update_post_meta($post_id, 'css', $post_id . '.css');
}, 20, 3);

function _template_fetch_and_process($post_id, $timber_post, $post_slug, $cookies = []) {
    $fetch_url  = get_permalink($post_id);
    $fetch_body = '';
    $final_css  = '';

    if ($fetch_url) {
        $fetch_url = add_query_arg('fetch', '1', $fetch_url);
        $args = ['timeout' => 30, 'sslverify' => false, 'headers' => ['X-Internal-Fetch' => '1']];
        if (!empty($cookies)) $args['cookies'] = $cookies;
        $response   = wp_remote_get($fetch_url, $args);
        $fetch_body = wp_remote_retrieve_body($response);
    }

    $blocks_raw = '';
    if (!empty($fetch_body)) {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $fetch_body, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        $main  = $xpath->query('//main');
        if ($main && $main->length > 0) {
            foreach ($main as $node) $blocks_raw .= $dom->saveHTML($node);
        } else {
            $blocks_raw = $fetch_body;
        }
    } else {
        if (method_exists($timber_post, 'get_blocks')) {
            $blocks_raw = $timber_post->get_blocks(['seperate_css' => false, 'seperate_js' => false])['html'];
        } else {
            foreach (parse_blocks($timber_post->post_content ?? '') as $b) $blocks_raw .= render_block($b);
        }
    }

    if (empty($blocks_raw)) return false;

    $html_output = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($m) use (&$final_css) {
        $final_css .= $m[1] . "\n"; return '';
    }, $blocks_raw);
    $html_output = $timber_post->strip_tags($html_output, '<style>');

    // Scope selector'i fetch edilen HTML'in KOK elemanindan uret.
    // Template fetch'inde sadece template'in kendi kok tag'i doner:
    //   header template  → <header id="header">  → scope 'header#header'
    //   footer template  → <footer id="footer">  → scope 'footer#footer'
    //   offcanvas/modal  → <div class="offcanvas ..."> → scope '.offcanvas-...'
    // Boylece header icindeki .col kurali 'header#header .col' olur ve template
    // disina (or. shop urun grid'i) tasmaz. Eski '.{post_slug}' scope'u DOM'da
    // karsiligi olmadigi icin (wrapper eklenmiyor) hem calismıyordu hem de
    // slug bos/sayisal olunca '.'/'.8939' gibi gecersiz selector uretiyordu.
    $scope_selector = _template_derive_scope_selector($html_output, $post_slug);

    if (class_exists('SaltHareket\AssetManager\RemoveUnusedCss') && defined('STATIC_PATH')) {
        $main_css = @file_get_contents(STATIC_PATH . 'css/main-combined.css');
        if ($main_css) {
            $remover = new \SaltHareket\AssetManager\RemoveUnusedCss($html_output, $main_css, '', [], false, [
                'ignore_whitelist'      => true,
                'black_list'            => ['html', 'body', 'footer'],
                'scope'                 => $scope_selector,
                'ignore_root_variables' => true,
                'twig_scan_includes'    => false,
            ]);
            $final_css = $remover->process() . $final_css;

            // ── SCOPE'SUZ modul CSS'i (offcanvas / modal) ─────────────────────
            // Offcanvas/modal panelleri template'in <header>/<footer> KOK'unun
            // DISINDA (wp_footer, body sonunda) render edilir. Bu yuzden yukarida
            // scope'lu CSS'e 'header#header .offcanvas' seklinde girerler ve panel
            // body'de karsiligi olmadigi icin CALISMAZLAR.
            //
            // Cozum: fetch edilen HTML'de hangi modul kullaniliyorsa (offcanvas/
            // modal toggler isaretinden tespit) o modulun DUMMY iskelet HTML'inden
            // AYRI, SCOPE'SUZ bir CSS uret ve scope'lu CSS'in sonuna ekle. Bu ek
            // CSS diske ayri kaydedilmez; sadece final string'e merge edilir.
            $module_css = _template_build_module_css($html_output, $main_css);
            if ($module_css !== '') {
                $final_css .= "\n" . $module_css;
            }
        }
    }

    $pae_assets = get_post_meta($post_id, 'assets', true);
    if (is_array($pae_assets) && !empty($pae_assets['css'])) {
        // PAE assets CSS'i sadece RemoveUnusedCss yoksa ekle — yoksa çift CSS oluşur
        if (!class_exists('SaltHareket\AssetManager\RemoveUnusedCss') || !defined('STATIC_PATH')) {
            $pae_css = is_array($pae_assets['css']) ? implode("\n", $pae_assets['css']) : $pae_assets['css'];
            if (!empty(trim($pae_css))) $final_css .= "\n" . $pae_css;
        }
    }

    // NOT: <head> içindeki inline style'lar (WooCommerce, plugin CSS vb.) eklenmez —
    // bunlar global/scoped olmayan CSS'ler, template dosyasını şişirir.

    if (!empty($final_css) && class_exists('MatthiasMullie\Minify\CSS')) {
        $css_minifier = new \MatthiasMullie\Minify\CSS();
        $css_minifier->add($final_css);
        $final_css = $css_minifier->minify();
    }

    return ['html' => $html_output, 'css' => $final_css];
}

/**
 * Fetch edilen template HTML'inin kok elemanindan CSS scope selector'i uretir.
 *
 * Ornekler:
 *   <header id="header">...   → 'header#header'
 *   <footer id="footer">...   → 'footer#footer'
 *   <div class="offcanvas offcanvas-menu">... → '.offcanvas'
 *   <section class="foo bar">... → '.foo'
 *
 * Kok elemanda id varsa 'tag#id', yoksa ilk class ile 'tag.class' ya da '.class',
 * hicbiri yoksa guvenli fallback olarak gecerli bir class uretir (rakamla
 * baslamayan): '.tpl-{post_id}'. Bu sayede eski '.{post_slug}' bug'i (bos slug
 * '.' , sayisal slug '.8939' gibi gecersiz selector) ortadan kalkar.
 *
 * @param  string     $html    Fetch edilen template HTML'i
 * @param  string     $slug    Template post slug (yalnizca fallback icin)
 * @return string              Gecerli bir CSS scope selector
 */
function _template_derive_scope_selector(string $html, string $slug = ''): string {
    $fallback = 'tpl-' . preg_replace('/[^a-z0-9\-_]/', '', strtolower((string) $slug));
    // slug bos/sayisal ise fallback zaten 'tpl-' ile basladigi icin gecerli
    if ($fallback === 'tpl-') $fallback = 'tpl-x';
    $fallback_selector = '.' . $fallback;

    if (trim($html) === '') return $fallback_selector;

    $dom = new \DOMDocument();
    $prev = libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    // Ilk element node'u bul (text/comment atla)
    $root = null;
    if ($dom->documentElement) {
        foreach ($dom->documentElement->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) { $root = $child; break; }
        }
        if (!$root && $dom->documentElement->nodeType === XML_ELEMENT_NODE) {
            $root = $dom->documentElement;
        }
    }
    if (!$root) return $fallback_selector;

    $tag = strtolower($root->nodeName);
    $id  = $root->getAttribute('id');

    if ($id !== '') {
        return $tag . '#' . $id;
    }

    $class_attr = trim($root->getAttribute('class'));
    if ($class_attr !== '') {
        $classes = preg_split('/\s+/', $class_attr);
        // Rakamla baslamayan ilk gecerli class'i sec
        foreach ($classes as $cls) {
            if ($cls !== '' && preg_match('/^[a-zA-Z_-]/', $cls)) {
                return '.' . $cls;
            }
        }
    }

    // Kok eleman header/footer gibi anlamli bir tag ise sadece tag ile scope'la
    if (in_array($tag, ['header', 'footer', 'nav', 'aside', 'main'], true)) {
        return $tag;
    }

    return $fallback_selector;
}

/**
 * Fetch edilen template HTML'inde kullanilan MODUL'lerin (offcanvas / modal)
 * SCOPE'SUZ CSS'ini uretir.
 *
 * NEDEN: Offcanvas/modal panelleri template kok elemaninin (header#header)
 * DISINDA render edilir (wp_footer, body sonu). Template CSS'i scope'lu
 * uretildigi icin bu paneller 'header#header .offcanvas' seklinde scope'a
 * girer ve DOM'da karsiligi olmadigindan calismaz. Bu fonksiyon o modullerin
 * yapisal CSS'ini AYRI ve SCOPE'SUZ olarak dondurur; cagiran taraf bunu
 * scope'lu CSS'in sonuna merge eder.
 *
 * NASIL: Fetch edilen HTML'de modul kullanildigina dair bir toggler isareti
 * varsa (button block'un urettigi attribute'lar), o modul icin bir DUMMY
 * iskelet HTML'i olusturup RemoveUnusedCss'e (scope YOK) verir. Boylece
 * yalnizca gercekten kullanilan modulun CSS'i uretilir; kullanilmayan modul
 * icin sifir CSS eklenir (sayfa sismesi olmaz).
 *
 * @param  string  $html      Fetch edilen template HTML'i (toggler tespiti icin)
 * @param  string  $main_css  Kaynak CSS (main-combined)
 * @return string             Scope'suz modul CSS'i (bos olabilir)
 */
function _template_build_module_css(string $html, string $main_css): string {
    if ($main_css === '' || !class_exists('SaltHareket\AssetManager\RemoveUnusedCss')) {
        return '';
    }

    // Modul tespiti — button block'un urettigi toggler isaretleri
    $has_offcanvas = (bool) preg_match(
        '/data-salt-offcanvas|data-bs-toggle=["\']offcanvas|\boffcanvas-toggler\b|nav-toggler-custom/i',
        $html
    );
    $has_modal = (bool) preg_match(
        '/data-bs-source-modal|data-bs-toggle=["\']modal|data-ajax-modal|data-modal-style/i',
        $html
    );

    if (!$has_offcanvas && !$has_modal) {
        return '';
    }

    // Kullanilan modullerin DUMMY iskelet HTML'i. RemoveUnusedCss bu HTML'deki
    // class'lara gore CSS uretir; scope verilmedigi icin cikti scope'suzdur.
    // Tum pozisyon/visibility varyantlarini dahil ediyoruz ki toggler ile hangi
    // varyant secilirse secilsin CSS mevcut olsun (bunlar zaten kucuk kurallar).
    $dummy = '<html><body>';

    if ($has_offcanvas) {
        $dummy .= '<div class="offcanvas offcanvas-sm offcanvas-md offcanvas-lg offcanvas-xl offcanvas-xxl offcanvas-xxxl '
                . 'offcanvas-start offcanvas-end offcanvas-top offcanvas-bottom offcanvas-fullscreen show showing hiding" '
                . 'tabindex="-1">'
                . '<div class="offcanvas-header"><h5 class="offcanvas-title"></h5>'
                . '<button class="btn-close" data-bs-dismiss="offcanvas"></button></div>'
                . '<div class="offcanvas-body scrollable"><div class="spinner-border"></div></div>'
                . '<div class="offcanvas-footer"></div>'
                . '</div>'
                . '<div class="offcanvas-backdrop fade show"></div>';
    }

    if ($has_modal) {
        $dummy .= '<div class="modal fade show" tabindex="-1"><div class="modal-dialog modal-dialog-centered '
                . 'modal-dialog-scrollable modal-sm modal-lg modal-xl modal-fullscreen">'
                . '<div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5>'
                . '<button class="btn-close" data-bs-dismiss="modal"></button></div>'
                . '<div class="modal-body"></div><div class="modal-footer"></div></div></div></div>'
                . '<div class="modal-backdrop fade show"></div>';
    }

    $dummy .= '</body></html>';

    $remover = new \SaltHareket\AssetManager\RemoveUnusedCss($dummy, $main_css, '', [], false, [
        'ignore_whitelist'      => true,
        'black_list'            => ['html', 'body'],
        // scope YOK — cikti global (.offcanvas{...}) olmali
        'ignore_root_variables' => true,
        'twig_scan_includes'    => false,
    ]);

    return (string) $remover->process();
}

function _template_save_file($render_dir, $filename, $content, $post_id) {
    $filepath = $render_dir . $filename;
    $new_hash = md5($content);
    $old_hash = get_post_meta($post_id, '_rendered_html_hash', true);
    if ($old_hash === $new_hash && file_exists($filepath)) return;
    $tmp = wp_tempnam($filepath);
    if ($tmp) { file_put_contents($tmp, $content); rename($tmp, $filepath); @chmod($filepath, 0644); }
    else { file_put_contents($filepath, $content); }
    update_post_meta($post_id, '_rendered_html_hash', $new_hash);
}

// ─── Term Ataması ────────────────────────────────────────────

add_action('save_post_template', function($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $taxonomy = 'template-types';
    if (isset($_POST['template_type_id']) && wp_verify_nonce($_POST['template_type_nonce'] ?? '', 'save_template_type_nonce')) {
        $term_id = (int) $_POST['template_type_id'];
        wp_set_object_terms($post_id, $term_id > 0 ? $term_id : null, $taxonomy, false);
        return;
    }
    $current = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
    if (empty($current)) {
        $slug = sanitize_text_field($_GET['template-types'] ?? 'custom');
        wp_set_object_terms($post_id, $slug, $taxonomy);
    }
}, 10, 3);

// ─── Twig Dosyası Silme ──────────────────────────────────────

add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) !== 'template') return;
    $filename = get_post_meta($post_id, 'template', true);
    if (empty($filename)) return;
    $dir = get_stylesheet_directory() . '/theme/templates/_custom/';
    if (file_exists($dir . $filename)) @unlink($dir . $filename);
    if (file_exists($dir . $post_id . '.css')) @unlink($dir . $post_id . '.css');
});

// ─── SaltOffcanvas Registry — Frontend (host-kullanim modeli) ────────────────
// Artik TUM offcanvas template'leri degil, SADECE sayfada gercekten kullanilan
// (host'lar tarafindan OffcanvasRegistry'ye kaydedilmis) offcanvas'lar islenir.
//   - render_mode='static' VEYA sidebar → HTML DOM'a basilir (SEO / sidebar auto-load).
//   - render_mode='dynamic' → sadece JS registry (SaltOffcanvas lazy build/AJAX).
//   - method varsa → dynamic, icerik TurboAPI/sh_offcanvas_render'dan gelir.
add_action( 'wp_footer', function () {
    if ( is_admin() ) return;
    if ( ! class_exists( 'OffcanvasRegistry' ) || ! OffcanvasRegistry::hasAny() ) return;

    // 1) DOM'a basilacaklar: static + sidebar (dynamic sidebar bos basilir, JS auto-load eder)
    foreach ( OffcanvasRegistry::getPrintable() as $id => $e ) {
        $is_static = ( $e['render_mode'] === 'static' );

        // Body HTML — sadece static ise template icerigi basilir.
        // Sidebar + dynamic ise bos basilir (JS DOMContentLoaded'da AJAX ile doldurur).
        $body_html = '';
        if ( $is_static && ! empty( $e['template'] ) ) {
            // ── Post-ID template (offcanvas post) ──
            $tid = (int) $e['template'];
            if ( class_exists( 'Timber\Timber' ) ) {
                $timber_post = \Timber\Timber::get_post( $tid );
                if ( $timber_post && method_exists( $timber_post, 'get_blocks' ) ) {
                    $blocks    = $timber_post->get_blocks();
                    $body_html = $blocks['html'] ?? '';
                }
            }
            if ( empty( $body_html ) ) {
                $post_obj  = get_post( $tid );
                $body_html = $post_obj ? apply_filters( 'the_content', $post_obj->post_content ) : '';
            }

            // Bos block'lari temizle (ör. Gutenberg'in ekledigi bos <p class="wp-block-paragraph"></p>).
            $body_html = sh_strip_empty_blocks( $body_html );
        } elseif ( $is_static && ! empty( $e['twig_path'] ) ) {
            // ── Custom / Manual twig template (post ID YOK) ──
            // Bu offcanvas'lar bir twig dosyasidir ( or. search.twig). Post-ID
            // pipeline'i bunlari basamadigi icin icerik bos geliyordu. Timber ile
            // compile edip offcanvas-body'ye gomuyoruz.
            if ( class_exists( 'Timber\Timber' ) ) {
                $twig_file = $e['twig_path'];
                if ( substr( $twig_file, -5 ) !== '.twig' ) $twig_file .= '.twig';
                try {
                    $body_html = (string) \Timber\Timber::compile(
                        $twig_file,
                        [ 'offcanvas_id' => $id ]
                    );
                } catch ( \Throwable $ex ) {
                    $body_html = '';
                }
            }
        }

        $vis_class  = esc_attr( $e['visibility'] ?: 'offcanvas' );
        $pos_class  = 'offcanvas-' . esc_attr( $e['position'] ?: 'end' );
        $full_class = ! empty( $e['fullscreen'] ) ? ' offcanvas-fullscreen' : '';
        $extra      = ! empty( $e['class'] ) ? ' ' . esc_attr( trim( $e['class'] ) ) : '';
        $bs_scroll  = ! empty( $e['scrollable'] ) ? 'true' : 'false';
        $bs_back    = ! empty( $e['backdrop'] )   ? 'true' : 'false';

        // Manual/custom twig template'leri kendi offcanvas-body/header/footer'larını
        // içerebilir. Eğer içeriyorsa dışarıdan sarmalama — çift element oluşmasın.
        $has_body   = stripos( $body_html, 'offcanvas-body' )   !== false;
        $has_header = stripos( $body_html, 'offcanvas-header' ) !== false;
        $has_footer = stripos( $body_html, 'offcanvas-footer' ) !== false;

        // Wrapper: body varsa (manual template) içeriği sarmalamadan direkt bas,
        // yoksa (post-id/dynamic) offcanvas-body ile sarmala.
        if ( $has_body || $has_header || $has_footer ) {
            // Manual template — kendi yapısını kullan, sarma
            $inner = $body_html;
        } else {
            $inner = '<div class="offcanvas-body">' . $body_html . '</div>';
        }

        echo '<div'
            . ' id="' . esc_attr( $id ) . '"'
            . ' class="' . $vis_class . ' ' . $pos_class . $full_class . $extra . '"'
            . ' tabindex="-1"'
            . ' aria-labelledby="' . esc_attr( $id ) . '-label"'
            . ' data-bs-scroll="' . $bs_scroll . '"'
            . ' data-bs-backdrop="' . $bs_back . '"'
            . '>'
            . $inner
            . '</div>' . "\n";
    }

    // 2) JS registry — tum kayitli offcanvas'lar (static + dynamic). SaltOffcanvas kullanir.
    //    ajax_url: admin_url() subdirectory kurulumlarda (/salthub/) dogru path'i verir.
    //    api_url:  TurboAPI (method) icin.
    echo '<script>window.shOffcanvasRegistry=' . wp_json_encode( OffcanvasRegistry::getClientRegistry() ) . ';'
        . 'window.shOffcanvasApiUrl=' . wp_json_encode( home_url( '/api/' ) ) . ';'
        . 'window.shOffcanvasAjaxUrl=' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';</script>' . "\n";
}, 20 );

// ─── SaltOffcanvas Dynamic Content AJAX ──────────────────────────────────────
// Dynamic offcanvas template HTML'ini döndürür (method yerine template render).
add_action( 'wp_ajax_sh_offcanvas_render',        'sh_offcanvas_render_callback' );
add_action( 'wp_ajax_nopriv_sh_offcanvas_render', 'sh_offcanvas_render_callback' );
function sh_offcanvas_render_callback() {
    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'No post_id' );

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'template' || $post->post_status !== 'publish' ) {
        wp_send_json_error( 'Template not found' );
    }

    $html = '';
    if ( class_exists( 'Timber\Timber' ) ) {
        $timber_post = \Timber\Timber::get_post( $post_id );
        if ( $timber_post && method_exists( $timber_post, 'get_blocks' ) ) {
            $blocks = $timber_post->get_blocks();
            $html   = $blocks['html'] ?? apply_filters( 'the_content', $post->post_content );
        }
    }
    if ( empty( $html ) ) {
        $html = apply_filters( 'the_content', $post->post_content );
    }

    wp_send_json_success( [ 'html' => $html ] );
}
