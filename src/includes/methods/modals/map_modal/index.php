<?php
$map_service = QueryCache::get_field('map_service', 'options') ?: 'leaflet';
$map_id      = absint($vars['id'] ?? 0);
$map_ids     = $vars['ids'] ?? [];
$lat         = $vars['lat'] ?? '';
$lng         = $vars['lng'] ?? '';
$title       = $vars['title'] ?? get_bloginfo('name');
$popup       = $vars['popup'] ?? [];
$html        = '';

$skeleton = [
    'map_type'     => '',
    'map_settings' => [
        'lat'              => '',
        'lng'              => '',
        'zoom'             => '',
        'map'              => ['markers' => []],
        'posts'            => [],
        'zoom_position'    => ($map_service === 'leaflet') ? 'topleft' : 'TOP_LEFT',
        'buttons_position' => '',
        'buttons'          => [],
        'marker'           => [],
        'popup_active'     => false,
        'popup_type'       => 'hover',
        'popup_template'   => '',
        'popup_ajax'       => false,
        'popup_width'      => '',
    ],
];

if (!empty($popup)) {
    $skeleton['map_settings']['popup_active']   = true;
    $skeleton['map_settings']['popup_type']     = $popup['type'] ?? 'hover';
    $skeleton['map_settings']['popup_template'] = 'default';
}

if ($map_id) {
    // Tekil post haritası
    $post      = Timber::get_post($map_id);
    $post_data = $post->get_map_data();

    $skeleton['map_type']                = 'static';
    $skeleton['map_settings']['lat']     = $post_data['lat'];
    $skeleton['map_settings']['lng']     = $post_data['lng'];
    $skeleton['map_settings']['zoom']    = $post_data['zoom'] ?? 14;
    $skeleton['map_settings']['map']['markers'][] = $post_data;
    $html = get_map_config($skeleton);

} elseif (!empty($map_ids)) {
    // Çoklu post haritası
    $posts = Timber::get_posts([
        'post_type'        => 'any',
        'post__in'         => array_map('absint', $map_ids),
        'posts_per_page'   => -1,
        'orderby'          => 'post__in',
        'suppress_filters' => true,
        'lang'             => '',
    ]);

    if ($posts) {
        $skeleton['map_type']              = 'dynamic';
        $skeleton['map_settings']['posts'] = $posts;
        $html = get_map_config($skeleton);
    }

} elseif ($lat !== '' && $lng !== '') {
    // Koordinat bazlı harita
    $skeleton['map_type']            = 'static';
    $skeleton['map_settings']['lat'] = $lat;
    $skeleton['map_settings']['lng'] = $lng;
    $skeleton['map_settings']['map']['markers'][] = [
        'id'    => 'marker_' . unique_code(4),
        'title' => !empty($popup['title']) ? $popup['title'] : $title,
        'lat'   => $lat,
        'lng'   => $lng,
    ];
    $html = get_map_config($skeleton);
}

$response['data'] = [
    'title'   => $title,
    'content' => $html,
];
echo json_encode($response);
wp_die();
