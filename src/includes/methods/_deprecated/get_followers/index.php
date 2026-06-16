<?php
$required_setting = ENABLE_FOLLOW;

$args = [
    'meta_query' => [
        'relation' => 'AND',
        [
            'key'     => 'following_user',
            'value'   => $vars['id'],
            'compare' => 'LIKE',
        ],
    ],
];

$paginate = new Paginate($args, $vars);
$result   = $paginate->get_results('user');

$context          = Timber::context();
$context['users'] = $result['posts'];
$context['data']  = $result['data'];
$context['vars']  = $vars;

$response['data'] = $result['data'];
$response['html'] = Timber::compile('user/archive.twig', $context);
echo json_encode($response);
wp_die();
