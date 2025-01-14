<?php

include "extends/user.php";
include "extends/review.php";
include "extends/post.php";
include "extends/term.php";
include "extends/menu.php";
include "extends/menuitem.php";

add_filter('timber/post/classmap', function ($classmap) {
    $post_types = get_post_types(array('public' => true), 'names');
    $post_types = array_diff($post_types, array('attachment'));
    $custom_classmap = [];
    foreach ($post_types as $post_type) {
        $custom_classmap[$post_type] = ThemePost::class;
    }
    return array_merge($classmap, $custom_classmap);
});

add_filter( 'timber/term/classmap', function( $classmap ) {
    $taxonomies = get_taxonomies(array('public' => true), 'names');
    $custom_classmap = [];
    foreach ($taxonomies as $taxonomy) {
        $custom_classmap[$taxonomy] = ThemeTerm::class; // ThemeTerm sınıfını atıyoruz
    }
    return array_merge( $classmap, $custom_classmap );
});

/*add_filter('timber/comment/classmap', function ($classmap) {
    $custom_classmap = [
        'post' => CommentPost::class,
        'book' => CommentBook::class,
    ];
    return array_merge($classmap, $custom_classmap);
});*/

add_filter( 'timber/comment/class', function( $class, $comment ) {
    return ThemeReview::class;
}, 10, 2 );

add_filter( 'timber/user/class', function($class, \WP_User $user) {
    return ThemeUser::class;
}, 10, 2);


add_filter('timber/menu/class', function ($class, $term, $args) {
    //if ($menu instanceof MenuPrimary) {
        return ThemeMenu::class;
    //}
    return $class;
}, 10, 3);

add_filter('timber/menuitem/class', function ($class, $item, $menu) {
    //if ($menu instanceof MenuPrimary) {
        return ThemeMenuItem::class;
    //}
    return $class;
}, 10, 3);