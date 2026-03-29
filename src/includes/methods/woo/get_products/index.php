<?php
$required_setting = ENABLE_ECOMMERCE;

$page_type = '';
if (isset($vars['kategori'])) {
    $page_type = 'product_cat';
}
if (isset($vars['keyword'])) {
    $page_type = 'search';
    Data::set('keyword', $vars['keyword']);
    add_filter('posts_where', 'sku_where');
}

$context = Timber::context();
$query   = [];

$query_response = category_queries_ajax($query, $vars);
$query = $query_response['query'];

$closure = fn($sql) => str_replace("'mt2.meta_value'", 'mt2.meta_value', $sql);
add_filter('posts_request', $closure);

query_posts($query);
remove_filter('posts_request', $closure);

$posts = Timber::get_posts();
$context['posts'] = $posts;

if (defined('ENABLE_FAVORITES') && ENABLE_FAVORITES) {
    $context['favorites'] = Data::get('favorites');
}

$context['pagination_type'] = Data::get('site_config.pagination_type');

global $wp_query;
$context['post_count']  = $wp_query->found_posts;
$context['page_count']  = $wp_query->max_num_pages;
$context['page']        = $wp_query->query_vars['paged'] ?? 1;
$context['pagination']  = Timber::get_pagination();

$templates = [$template . '.twig'];

wp_reset_postdata();
wp_reset_query();
