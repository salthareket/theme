<?php
$required_setting = ENABLE_SEARCH_HISTORY;

$user = is_user_logged_in() ? wp_get_current_user() : null;
$search_history = new SearchHistory();

if (($vars['history'] ?? '') === 'popular') {
    $title  = trans('Popular search terms');
    $result = $search_history->get_popular_terms($vars['post_type'] ?? '');
} else {
    $title  = trans('Your last searches');
    $result = $user ? $search_history->get_user_terms($user->ID, $vars['post_type'] ?? '') : [];
}

if ($result) {
    $context = Timber::context();
    $context['title']        = $title;
    $context['search_terms'] = $result;
    $context['vars']         = $vars;
    $response['html'] = Timber::compile('partials/snippets/search-field-history.twig', $context);
}

echo json_encode($response);
wp_die();
