<?php
$post_type = $vars['post_type'] ?? 'post';
$data      = get_posts_by_district($post_type, $vars['city'] ?? '', $vars['district'] ?? '');

$context         = Timber::context();
$context['vars'] = $vars;
$context['data'] = $data;
$templates       = [$post_type . '/archive-ajax.twig'];
