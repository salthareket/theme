<?php
$required_setting = ENABLE_FAVORITES;

$favorites_obj = new Favorites();
$fav_ids       = $favorites_obj->favorites;
$user          = Data::get('user');

$view = $vars['view'] ?? 'dropdown';
$template = 'partials/' . $view . '/archive.twig';

$posts = [];
if (!empty($fav_ids)) {
    $posts = Timber::get_posts([
        'post_type'      => 'any',
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

$response['data']  = $fav_ids;
$response['count'] = $favorites_obj->count();
$response['html']  = Timber::compile($template, $context);
echo json_encode($response);
wp_die();
