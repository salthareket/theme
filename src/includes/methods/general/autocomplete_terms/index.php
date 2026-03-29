<?php
$error         = false;
$message       = '';
$data          = ['results' => []];
$type          = $vars['type'] ?? '';
$kw            = sanitize_text_field($vars['keyword'] ?? ($keyword ?? ''));
$response_type = $vars['response_type'] ?? 'select2';
$count         = (int) ($vars['count'] ?? 10);
$page          = max(1, (int) ($vars['page'] ?? 1));
$offset        = ($page - 1) * $count;
$total_pages   = 1;
$terms         = [];

if (empty($type)) {
    $response['error']   = true;
    $response['message'] = 'Please provide a type';
    echo json_encode($response);
    wp_die();
}

$is_user      = ($type === 'user');
$is_taxonomy  = !$is_user && taxonomy_exists($type);
$is_post_type = !$is_user && !$is_taxonomy && post_type_exists($type);

// ─── Veri Çekme ─────────────────────────────────────────────
if ($is_taxonomy) {
    $args = [
        'taxonomy'   => $type,
        'hide_empty' => false,
        'number'     => $count,
        'offset'     => $offset,
        'fields'     => 'id=>name',
    ];
    if (!empty($vars['value']))    $args['include'] = $vars['value'];
    if (!empty($vars['selected'])) $args['exclude'] = $vars['selected'];
    if (!empty($kw))               $args['search']  = $kw;

    $total       = !empty($kw) ? wp_count_terms($args) : wp_count_terms($type);
    $total_pages = $count > 0 ? ceil($total / $count) : 1;
    $terms       = get_terms($args);

} elseif ($is_post_type) {
    $args = [
        'post_type'      => $type,
        'posts_per_page' => $count,
        'offset'         => $offset,
    ];
    if (!empty($kw)) $args['s'] = $kw;

    $total       = !empty($kw) ? wp_count_posts_by_query($args) : wp_count_posts($type)->publish;
    $total_pages = $count > 0 ? ceil($total / $count) : 1;
    $terms       = Timber::get_posts($args)->to_array();

} elseif ($is_user) {
    $parts = explode(' ', esc_attr(trim($kw)));
    $args  = ['meta_query' => ['relation' => 'OR']];
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $args['meta_query'][] = ['key' => 'first_name', 'value' => $part, 'compare' => 'LIKE'];
        $args['meta_query'][] = ['key' => 'last_name',  'value' => $part, 'compare' => 'LIKE'];
    }
    $terms = (new WP_User_Query($args))->get_results();
}

// ─── Response Formatlama ────────────────────────────────────
$results = [];

if ($response_type === 'select2') {
    if ($is_taxonomy) {
        foreach ($terms as $tid => $name) {
            $results[] = ['id' => $tid, 'text' => $name];
        }
    } elseif ($is_post_type) {
        foreach ($terms as $post) {
            $text = $post->post_title;
            if (!empty($vars['response_extra'])) {
                foreach (array_map('trim', explode(',', $vars['response_extra'])) as $extra) {
                    $text .= ' - ' . ($extra === 'author' ? ($post->author->display_name ?? '') : ($post->{$extra} ?? ''));
                }
            }
            $results[] = ['id' => $post->ID, 'text' => $text];
        }
    } elseif ($is_user) {
        foreach ($terms as $u) {
            $results[] = ['id' => $u->ID, 'text' => $u->first_name . ' ' . $u->last_name];
        }
    }
    $data = [
        'results'    => $results,
        'pagination' => ['more' => ($page < $total_pages && !empty($terms))],
    ];

} elseif ($response_type === 'autocomplete') {
    if ($is_taxonomy) {
        foreach ($terms as $tid => $name) $results[$tid] = $name;
    } elseif ($is_post_type) {
        foreach ($terms as $post) $results[$post->ID] = $post->post_title;
    } elseif ($is_user) {
        foreach ($terms as $u) $results[$u->ID] = $u->first_name . ' ' . $u->last_name;
    }
    // autocomplete direkt results döner
    echo json_encode($results);
    wp_die();
}

$response['error']   = $error;
$response['message'] = $message;
$response['data']    = $data;
echo json_encode($response);
wp_die();
