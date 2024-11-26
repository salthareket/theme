<?php

function wp_block_category( $categories, $post ) {
    $main_category = array(
        array(
            'slug'  => 'saltblocks',
            'title' => 'Salt Blocks',
            'icon'  => 'dashicons-admin-generic' // Dashicon simgesi
        )
    );
    $new_categories = array_merge( $GLOBALS['block_categories'], $main_category);
    //error_log(json_encode($new_categories));
    $new_categories = array_reverse( $new_categories );
    foreach ( $new_categories as $new_category ) {
        array_unshift( $categories, $new_category );
    }
    return $categories;
}
add_filter( 'block_categories_all', 'wp_block_category', 10, 2 );

function wp_block_pattern_categories( $categories ) {
    if ( function_exists( 'register_block_pattern_category' ) ) {
        $site_name = get_bloginfo( 'name' );
        $kategori_slug = sanitize_title( $site_name );
        register_block_pattern_category(
            $kategori_slug,
            array( 
                'label' => $site_name,
                'description' => 'Patterns for '.$site_name.' web site.'
            )
        );
        $block_pattern_category = wp_insert_term( 
            $site_name, 
            'wp_pattern_category', 
            array( 'slug' => $kategori_slug ) // it is the optional part
        );

        if( ! is_wp_error( $block_pattern_category ) ) {
            wp_set_object_terms( $block_pattern_category[ 'term_id' ], $kategori_slug, 'wp_pattern_category' );
        }
    }
}
add_action( 'init', 'wp_block_pattern_categories' );

function wp_block_pattern_on_save( $post_id, $post, $update ) {
    if ( $post->post_type !== 'wp_block' ) {
        return;
    }
    $post_categories = wp_get_post_terms( $post->ID, 'wp_pattern_category', array( 'fields' => 'ids' ) );
    if(!$post_categories){
        $site_name = get_bloginfo( 'name' );
        $kategori_slug = sanitize_title( $site_name );
        $category = get_term_by('slug', $kategori_slug, 'wp_pattern_category');
        if($category){
            wp_set_object_terms($post_id, $kategori_slug, 'wp_pattern_category');
        }        
    }
}
add_action( 'save_post', 'wp_block_pattern_on_save', 10, 3 );


function get_blocks($post_id){
    if ( ! has_blocks( $post_id ) ) {
        return false;
    }
    return parse_blocks( get_the_content( '', false, $post_id ) );
}
function get_block( $post_id, $block_id, $render=false ) {
    $post_blocks = get_blocks($post_id );
    if(!$post_blocks){
        return false;
    }
    foreach ( $post_blocks as $block ) {
        if ( isset( $block['blockName']) && $block_id == $block['blockName'] ) {
            if($render){
                return render_block($block);
            }else{
                return $block;
            }
        }
    }
    return false;
}
function get_field_from_block( $selector, $post_id, $block_id ) {
    $post_blocks = get_blocks($post_id );
    if(!$post_blocks){
        return false;
    }
    foreach ( $post_blocks as $block ) {
        if ( isset( $block['attrs']['id'] ) && $block_id == $block['attrs']['id'] ) {
            if ( isset( $block['attrs']['data'][$selector] ) ) {
                return $block['attrs']['data'][$selector];
            } else {
                break;
            }
        }
    }
    return false;
}

function add_player_class_to_embed_block($block_content, $block) {

    switch ($block['blockName']) {

        case 'core/embed':
            if($block["attrs"]["type"] == "video"){
                $block_content = '<div class="player plyr__video-embed init-me"><iframe
                    class="video"
                    src="'.$block["attrs"]["url"].'"
                    allowfullscreen
                    allowtransparency
                    allow="autoplay"
                  ></iframe></div>';                
            }else{
                $block_content2 = '<iframe
                    class="w-100"
                    src="'.$block["attrs"]["url"].'"                    
                    allowtransparency
                  ></iframe>';
            }
        break;

        case 'core/audio':
             $block_content = str_replace('<audio', '<audio class="player init-me"', $block_content);
        break;

    }

    return $block_content;
}
add_filter('render_block', 'add_player_class_to_embed_block', 10, 2);


function wp_block_editor_width() {
    ?>
    <style>
        .is-fullscreen-mode{
            
        }
        @media (min-width: 576px) {
            .wp-block {
                max-width: 540px; /* Or your desired width for small screens */
                .container-xxxl,
                .container-xxl,
                .container,
                .container-xl,
                .container-lg,
                .container-md,
                .container-sm,
                .container-xs{
                    max-width: 540px;
                }
            }
        }

        @media (min-width: 768px) {
            .wp-block {
                max-width: 720px; /* Or your desired width for medium screens */
                .container-xxxl,
                .container-xxl,
                .container,
                .container-xl,
                .container-lg,
                .container-md,
                .container-sm,
                .container-xs{
                    max-width: 720px;
                }
            }
        }

        @media (min-width: 992px) {
            .wp-block {
                max-width: 960px; /* Or your desired width for large screens */
                .container-xxxl,
                .container-xxl,
                .container,
                .container-xl,
                .container-lg,
                .container-md,
                .container-sm,
                .container-xs{
                    max-width: 960px;
                }
            }
        }

        @media (min-width: 1200px) {
            .wp-block {
                max-width: 1140px; /* Or your desired width for extra large screens */
                .container-xxxl,
                .container-xxl,
                .container,
                .container-xl,
                .container-lg,
                .container-md,
                .container-sm,
                .container-xs{
                    max-width: 1140px;
                }
            }
        }
        /* Width of "wide" blocks */
        .wp-block[data-align="wide"] {
            max-width: 1080px;
                .container-xxxl,
                .container-xxl,
                .container,
                .container-xl,
                .container-lg,
                .container-md,
                .container-sm,
                .container-xs{
                    max-width: 1080px;
                }
        }
 
        /* Width of "full-wide" blocks */
        .wp-block[data-align="full"] {
            max-width: none;
        }
    </style>
    <?php
}
add_action('admin_head', 'wp_block_editor_width');


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