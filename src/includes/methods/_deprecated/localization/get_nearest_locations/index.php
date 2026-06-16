<?php
$locations = GeoLocation_Query(
    $vars['lat'],
    $vars['lng'],
    $vars['post_type'],
    $vars['distance'] ?? 5,
    $vars['limit'] ?? 10
);

$output = $vars['output'] ?? ['posts'];

if (in_array('posts', $output)) {
    $context = Timber::context();
    $context['posts'] = $locations;
    $response['html'] = Timber::compile($vars['template'] . '.twig', $context);
}

if (in_array('markers', $output)) {
    $salt = \Salt::get_instance();
    $response['data'] = $salt->get_markers($locations);
}

echo json_encode($response);
wp_die();
