<?php
$required_setting = ENABLE_FAVORITES;

$fav_id    = absint($vars['id'] ?? 0);
$favorites = new Favorites();
$favorites->add($fav_id);
Data::set('favorites', $favorites);

$count = get_post_meta($fav_id, 'wpcf_favorites_count', true);
$feedback = $count
    ? '<span>' . sprintf(trans("%s person's favorite tour."), $count) . '</span>'
    : '';

$response['error']   = false;
$response['message'] = '<b class="d-block">' . esc_html(get_the_title($fav_id)) . '</b> added to your favorites.';
$response['data']    = $favorites->favorites;
$response['html']    = trans('Remove') . $feedback;
$response['count']   = $favorites->count();
echo json_encode($response);
wp_die();
