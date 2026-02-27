<?php

if(is_admin()){

    /**
     * [ADMIN] Blok Kategorisi Ekleme
     */
    function wp_block_category_logic( $categories, $post ) {
        if ( ! is_admin() ) return $categories;

        $main_category = [
            'slug'  => 'saltblocks',
            'title' => 'Salt Blocks',
            'icon'  => 'dashicons-admin-generic'
        ];

        return array_merge( [ $main_category ], $categories );
    }
    add_filter( 'block_categories_all', 'wp_block_category_logic', 10, 2 );

    /**
     * [ADMIN] Pattern Kategorisi Kaydı
     */
    function wp_block_pattern_categories_init() {
        if ( ! is_admin() || ! function_exists( 'register_block_pattern_category' ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $kategori_slug = sanitize_title( $site_name );

        register_block_pattern_category(
            $kategori_slug,
            [ 
                'label' => $site_name, 
                'description' => 'Patterns for ' . $site_name . ' web site.' 
            ]
        );

        if ( ! get_term_by( 'slug', $kategori_slug, 'wp_pattern_category' ) ) {
            wp_insert_term( $site_name, 'wp_pattern_category', [ 'slug' => $kategori_slug ] );
        }
    }
    add_action( 'init', 'wp_block_pattern_categories_init' );

    /**
     * [ADMIN] Pattern Kaydedilirken Otomatik Kategori Atama
     */
    function wp_block_pattern_on_save_logic( $post_id, $post, $update ) {
        // Sadece admin panelinde ve 'wp_block' tipinde çalış
        if ( ! is_admin() || $post->post_type !== 'wp_block' ) {
            return;
        }

        $tax = 'wp_pattern_category';
        $post_categories = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'ids' ] );

        if ( empty( $post_categories ) ) {
            $site_name = get_bloginfo( 'name' );
            $kategori_slug = sanitize_title( $site_name );
            
            if ( get_term_by( 'slug', $kategori_slug, $tax ) ) {
                wp_set_object_terms( $post_id, $kategori_slug, $tax );
            }
        }
    }
    add_action( 'save_post', 'wp_block_pattern_on_save_logic', 10, 3 );

    function wp_block_editor_width() {
        echo '<style>
            /* Standart CSS formatına çekildi */
            @media (min-width: 1200px) {
                .wp-block, 
                .wp-block .container-xxl, 
                .wp-block .container { 
                    max-width: 1140px !important; 
                }
            }
            .wp-block[data-align="full"] { max-width: none !important; }
        </style>';
    }
    add_action('admin_head', 'wp_block_editor_width');
    
}





function get_cached_blocks( $post_id ) {
    static $blocks_cache = [];

    if ( isset( $blocks_cache[ $post_id ] ) ) {
        return $blocks_cache[ $post_id ];
    }

    $content = get_post_field( 'post_content', $post_id );
    if ( empty( $content ) || ! has_blocks( $content ) ) {
        return $blocks_cache[ $post_id ] = [];
    }

    return $blocks_cache[ $post_id ] = parse_blocks( $content );
}

function get_blocks($post_id){
    if ( ! has_blocks( $post_id ) ) {
        return false;
    }
    return parse_blocks(get_post_field('post_content', $post_id));
}

function get_block( $post_id, $block_name, $render = false ) {
    $blocks = get_cached_blocks( $post_id );
    if ( ! $blocks ) return false;

    foreach ( $blocks as $block ) {
        if ( isset( $block['blockName'] ) && $block['blockName'] === $block_name ) {
            return $render ? render_block( $block ) : $block;
        }
    }

    return false;
}

function get_field_from_block( $selector, $post_id, $block_id ) {
    $blocks = get_cached_blocks( $post_id );
    if ( ! $blocks ) return false;

    foreach ( $blocks as $block ) {
        if ( isset( $block['attrs']['id'] ) && $block['attrs']['id'] === $block_id ) {
            return $block['attrs']['data'][ $selector ] ?? false;
        }
    }

    return false;
}

function get_block_from_page($block_name, $source_page_id = null, $args = []) {
    $source_page_id = $source_page_id ?: get_option('page_on_front');
    $blocks = get_cached_blocks($source_page_id); // Artık cache'den geliyor

    if ( empty($blocks) ) return '';

    foreach ($blocks as $block) {
        if ( isset($block['blockName']) && $block['blockName'] === $block_name ) {
            if ( ! empty($args) ) {
                $block['attrs']["data"] = array_merge($block['attrs']["data"] ?? [], $args);
            }
            return render_block($block);
        }
    }
    return '';
}

//plyr video player
function add_player_class_to_embed_block($block_content, $block) {
    // Sadece video ve ses bloklarında işlem yap
    if ( ! in_array($block['blockName'], ['core/embed', 'core/audio']) ) {
        return $block_content;
    }

    if ( $block['blockName'] === 'core/embed' && isset($block["attrs"]["type"]) && $block["attrs"]["type"] === "video" ) {
        return sprintf(
            '<div class="player plyr__video-embed init-me"><iframe class="video" src="%s" allowfullscreen allowtransparency allow="autoplay"></iframe></div>',
            esc_url($block["attrs"]["url"])
        );
    }

    if ( $block['blockName'] === 'core/audio' ) {
        return str_replace('<audio', '<audio class="player init-me"', $block_content);
    }

    return $block_content;
}
//add_filter('render_block', 'add_player_class_to_embed_block', 10, 2);



/*
add_filter('register_block_type_args', function ($args, $name) {
    $supports = [
        "color" => [
                "gradients" => true,
                "link" => true,
                "__experimentalDefaultControls" => [
                    "background" => true,
                    "text" => true,
                    "link" => true
                ]
        ]
    ];
    if (strpos($name, 'acf/hero') === 0) {
        //$args['supports']['color'] = $supports["color"];
    }
    return $args;

}, 10, 2);*/