<?php
$required_setting = ENABLE_FAVORITES;

$favorites_obj = new Favorites();
$fav_ids       = $favorites_obj->favorites;
$tpl           = !empty($template) ? $template : 'product/dropdown/archive';
$user          = Data::get('user');

$posts = [];
if (!empty($fav_ids)) {
    $posts = Timber::get_posts([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post__in'       => $fav_ids,
    ]);
}

// Silinen ürünleri favorites'tan temizle
if (count($posts) !== count($fav_ids) && isset($user->ID)) {
    $ids = wp_list_pluck($posts, 'ID');
    update_user_meta($user->ID, 'wpcf_favorites', unicode_decode(json_encode($ids, JSON_NUMERIC_CHECK)));
}

$context          = Timber::context();
$context['type']  = 'favorites';
$context['posts'] = $posts;
$templates        = [$tpl . '.twig'];
