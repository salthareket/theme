<?php
$query_pagination_vars    = [];
$query_pagination_request = '';

if (!empty($vars['query_pagination_vars']) || !empty($vars['query_pagination_request'])) {
    $enc = new Encrypt();
    if (!empty($vars['query_pagination_vars'])) {
        $query_pagination_vars = $enc->decrypt($vars['query_pagination_vars']);
    }
    if (!empty($vars['query_pagination_request'])) {
        $query_pagination_request = $enc->decrypt($vars['query_pagination_request']);
    }
}

$args = $query_pagination_vars;
if (isset($vars['posts_per_page'])) {
    $args['posts_per_page'] = (int) $vars['posts_per_page'];
}

$post_type = $args['post_type'] ?? 'post';
$pt_query  = ($post_type === 'search') ? 'any' : $post_type;

// SQL request varsa direkt kullan, yoksa WP_Query args
if (!empty($query_pagination_request)) {
    global $wpdb;
    $request  = explode('LIMIT', $query_pagination_request)[0];
    $request .= ' LIMIT ' . ($args['posts_per_page'] * ($vars['page'] - 1)) . ', ' . $args['posts_per_page'];
    $results  = $wpdb->get_results($request);

    $post_args = $results ? [
        'post_type'        => $pt_query,
        'post__in'         => wp_list_pluck($results, 'ID'),
        'posts_per_page'   => -1,
        'orderby'          => 'post__in',
        'suppress_filters' => true,
    ] : ['post__in' => [0]]; // Boş sonuç
} else {
    $post_args              = $args;
    $post_args['paged']     = (int) $vars['page'];
    $post_args['post_type'] = $pt_query;
}

Data::set('pagination_page', $vars['page']);
unset($post_args['querystring'], $post_args['page']);
if (isset($post_args['s']) && empty($post_args['s'])) {
    unset($post_args['s']);
}

// ─── Query & Render ─────────────────────────────────────────
$html  = '';
$query = new WP_Query($post_args);

$folder = (is_array($pt_query) || $pt_query === 'any') ? 'search' : $pt_query;

if ($post_type === 'product') {
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            ob_start();
            wc_get_template_part('content', 'product');
            $html .= ob_get_clean();
        }
    }
} else {
    if ($query->have_posts()) {
        $index = (int) $vars['page'] * (int) $args['posts_per_page'];
        foreach (Timber::get_posts($query) as $post) {
            ob_start();
            $ctx          = Timber::context();
            $ctx['index'] = ++$index;
            $ctx['post']  = $post;
            Timber::render([$folder . '/tease.twig', 'tease.twig'], $ctx);
            $html .= ob_get_clean();
        }
    }
}

wp_reset_query();
Data::set('pagination_page', '');

// ─── Result Count Text ──────────────────────────────────────
$total    = (int) ($vars['total'] ?? 0);
$per_page = (int) ($args['posts_per_page'] ?? 10);
$current  = (int) ($vars['page'] ?? 1);
$initial  = (int) ($vars['initial'] ?? 0);

if ($total === 1) {
    $count_text = __('Showing the single result', 'woocommerce');
} elseif ($total <= $per_page || $per_page === -1) {
    $count_text = sprintf(_n('Showing all %d result', 'Showing all %d results', $total, 'woocommerce'), $total);
} else {
    $first = ($per_page * $current) - $per_page + 1;
    $last  = min($total, $per_page * $current);
    if ($initial > 0) {
        if ($current < $initial) {
            $last = min($total, $per_page * $initial);
        } else {
            $first = ($per_page * $initial) - $per_page + 1;
        }
    }
    $count_text = sprintf(_nx('Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, 'with first and last result', 'woocommerce'), $first, $last, $total);
}

$response['html'] = function_exists('minify_html') ? minify_html($html) : $html;
$response['data'] = $count_text;
echo json_encode($response);
wp_die();
