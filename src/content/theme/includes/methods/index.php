<?php
switch ($method) {
case 'acf_layout_posts':
// Encrypted query'yi decrypt et
$query = [];
if (!empty($vars['query'])) {
$enc = new \Encrypt();
$decrypted = $enc->decrypt($vars['query']);
if (is_array($decrypted)) {
$query = $decrypted;
}
}
$paginate = new Paginate($query, $vars);
$result   = $paginate->get_results($vars['type'] ?? 'post');
$tpl_list = $vars['templates'] ?? [];
if (!is_array($tpl_list)) {
$tpl_list = json_decode(stripslashes($tpl_list), true) ?: [];
}
$context              = Timber::context();
$context['slider']    = $vars['slider'] ?? false;
$context['heading']   = $vars['heading'] ?? '';
$context['posts']     = $result['posts'];
$context['templates'] = $tpl_list;
$context['is_preview'] = is_admin();
$response['data'] = $result['data'];
$response['html'] = Timber::compile('acf-query-field/loop.twig', $context);
echo json_encode($response);
wp_die();
break;
case 'comment_product':
$salt = \Salt::get_instance();
echo $salt->comment_product($vars);
wp_die();
break;
case 'comment_product_detail':
$salt = \Salt::get_instance();
echo $salt->comment_product_detail($vars);
wp_die();
break;
case 'comment_product_modal':
$comment_id = absint($vars['id'] ?? 0);
$comment    = new Timber\Comment($comment_id);
$tour_plan_id       = $comment->meta('comment_tour');
$tour_plan_offer_id = get_field('tour_plan_offer_id', $tour_plan_id);
$agent_id           = get_post_field('post_author', $tour_plan_offer_id);
$dest_ids      = $comment->meta('comment_destination');
$destinations  = is_array($dest_ids) ? wp_list_pluck(get_terms('taxonomy=destinations&include=' . implode(',', $dest_ids)), 'name') : [];
$context                 = Timber::context();
$context['title']        = $comment->comment_title ?? '';
$context['comments']     = json_decode($comment->comment_content);
$context['author']       = $comment->comment_author;
$context['image']        = wp_get_attachment_image_url($comment->comment_image, 'medium_large');
$context['agent']        = get_user_by('id', $agent_id);
$context['destinations'] = $destinations;
$context['vars']         = $vars;
$templates = ['tour-plan/comment-modal.twig'];
break;
case 'autocomplete_terms':
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
break;
case 'get_post':
$error   = true;
$message = 'Content not found';
$post_data = [];
$html    = '';
if (isset($vars['id'])) {
$post_data = Timber::get_post($vars['id']);
$error     = false;
$message   = '';
if ($post_data) {
$post_content = $post_data->get_blocks()['html'];
if (ENABLE_MULTILANGUAGE && ENABLE_MULTILANGUAGE === 'qtranslate-xt') {
$post_data->title   = qtranxf_use($lang, $post_data->post_title, false, false);
$post_data->content = qtranxf_use($lang, $post_content, false, false);
} else {
$post_data->title   = $post_data->post_title;
$post_data->content = $post_content;
}
if (!empty($vars['template'])) {
$context         = Timber::context();
$context['post'] = $post_data;
$html = Timber::compile($vars['template'], $context);
}
}
}
$response['error']   = $error;
$response['message'] = $message;
$response['data']    = $post_data;
$response['html']    = $html;
echo json_encode($response);
wp_die();
break;
case 'get_search_history':
/**
* get_search_history — AJAX handler
*
* Kullanıcının son aramaları veya popüler aramaları döner.
* Yeni format: { terms: string[], title: string }
* Eski format (html) de desteklenir — geriye uyumluluk için.
*
* POST params:
*   history   = 'user' | 'popular'
*   post_type = 'search' | 'product' | ...
*   lang      = '' | 'tr' | 'en' (opsiyonel, ML siteler için)
*   format    = 'json' | 'html' (default: json)
*/
$user         = is_user_logged_in() ? wp_get_current_user() : null;
$search_history = new SearchHistory();
$history_type = sanitize_key( $vars['history']   ?? 'popular' );
$post_type    = sanitize_key( $vars['post_type']  ?? 'search' );
$lang         = sanitize_key( $vars['lang']       ?? '' );
$format       = sanitize_key( $vars['format']     ?? 'json' );
if ( $history_type === 'popular' ) {
$title  = trans( 'Popular search terms' );
$result = $search_history->get_popular_terms( $post_type, 10, $lang );
} else {
$title  = trans( 'Your last searches' );
$result = $user
? $search_history->get_user_terms( $user->ID, $post_type, 10 )
: [];
}
// Yeni format: terms array — JS dropdown tarafından render edilir
$response['terms'] = array_values( $result ?: [] );
$response['title'] = $title;
$response['type']  = $history_type;
// Geriye uyumluluk: format=html isterse eski twig'i de derle
if ( $format === 'html' && ! empty( $result ) ) {
$context                 = Timber::context();
$context['title']        = $title;
$context['search_terms'] = $result;
$context['vars']         = $vars;
$response['html'] = Timber::compile( 'partials/search/search-field-history.twig', $context );
}
echo json_encode( $response );
wp_die();
break;
case 'pagination_ajax':
$query_pagination_vars = [];
$query_pagination_request = "";
if (
!empty($vars["query_pagination_vars"]) ||
!empty($vars["query_pagination_request"])
) {
$enc = new Encrypt();
if (!empty($vars["query_pagination_vars"])) {
$query_pagination_vars = $enc->decrypt($vars["query_pagination_vars"]);
}
if (!empty($vars["query_pagination_request"])) {
$query_pagination_request = $enc->decrypt(
$vars["query_pagination_request"]
);
}
}
$args = $query_pagination_vars;
if (empty($args['post_type']) && !empty($vars['post_type'])) {
$args['post_type'] = $vars['post_type'];
}
if (isset($vars["posts_per_page"])) {
$args["posts_per_page"] = (int) $vars["posts_per_page"];
}
$post_type = $args["post_type"] ?? "post";
$pt_query = $post_type === "search" ? "any" : $post_type;
// is_woo_favorites: hem product hem product_variation getir
if (!empty($args['is_woo_favorites'])) {
$pt_query = ['product', 'product_variation'];
}
// SQL request varsa direkt kullan, yoksa WP_Query args
if (!empty($query_pagination_request)) {
global $wpdb;
$request = explode("LIMIT", $query_pagination_request)[0];
$request .=
" LIMIT " .
$args["posts_per_page"] * ($vars["page"] - 1) .
", " .
$args["posts_per_page"];
$results = $wpdb->get_results($request);
$post_args = $results
? [
"post_type" => $pt_query,
"post__in" => wp_list_pluck($results, "ID"),
"posts_per_page" => -1,
"orderby" => "post__in",
"suppress_filters" => true,
]
: ["post__in" => [0]]; // Boş sonuç
} else {
$post_args = $args;
$post_args["paged"] = (int) $vars["page"];
$post_args["post_type"] = $pt_query;
}
Data::set("pagination_page", $vars["page"]);
unset($post_args["querystring"], $post_args["page"]);
if (isset($post_args["s"]) && empty($post_args["s"])) {
unset($post_args["s"]);
}
// ─── Query & Render ─────────────────────────────────────────
$html = "";
// Single variation plugin'leri (XT/WSSV) AJAX context'inde çalışmıyor.
// hide_parent ayarı açıksa variable product parent'larını SQL WHERE ile filtrele.
if ($post_type === "product" && empty($args['is_woo_favorites'])) {
$hide_parent = get_option('xt_woovas_hide_parent', 'no') === 'yes'
|| get_option('jck_wssv_hide_parent', 'no') === 'yes'
|| (class_exists('Iconic_WSSV') && function_exists('jck_wssv') && !empty(jck_wssv()->settings['general_hide_parent']));
if ($hide_parent) {
// post_type'a product_variation ekle — variation'lar da gelsin
$post_args['post_type']              = ['product', 'product_variation'];
$post_args['salt_hide_var_parents']  = true; // flag — posts_where için
// Variable product parent'larını SQL WHERE ile gizle
add_filter('posts_where', function($where, $q) {
if (!$q->get('salt_hide_var_parents')) return $where;
global $wpdb;
$where .= " AND {$wpdb->posts}.ID NOT IN (
SELECT p2.ID FROM {$wpdb->posts} p2
INNER JOIN {$wpdb->term_relationships} tr2 ON tr2.object_id = p2.ID
INNER JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
INNER JOIN {$wpdb->terms} t2 ON t2.term_id = tt2.term_id
WHERE tt2.taxonomy = 'product_type' AND t2.slug = 'variable'
)";
return $where;
}, 999, 2);
}
}
$query = new WP_Query($post_args);
$folder = is_array($pt_query) || $pt_query === "any" ? "search" : $pt_query;
$is_woo_render = !empty($args['is_woo_favorites'])
|| $post_type === "product"
|| (is_array($post_type) && in_array('product', $post_type));
if ($is_woo_render) {
if ($query->have_posts()) {
while ($query->have_posts()) {
$query->the_post();
wc_setup_product_data(get_the_ID());
ob_start();
wc_get_template_part("content", "product");
$html .= ob_get_clean();
}
}
} else {
if ($query->have_posts()) {
$index = (int) $vars["page"] * (int) $args["posts_per_page"];
foreach (Timber::get_posts($query) as $post) {
ob_start();
$ctx = Timber::context();
$ctx["index"] = ++$index;
$ctx["post"] = $post;
Timber::render([$folder . "/tease.twig", "tease.twig"], $ctx);
$html .= ob_get_clean();
}
}
}
wp_reset_query();
Data::set("pagination_page", "");
// ─── Result Count Text ──────────────────────────────────────
$total = (int) ($vars["total"] ?? 0);
$per_page = (int) ($args["posts_per_page"] ?? 10);
$current = (int) ($vars["page"] ?? 1);
$initial = (int) ($vars["initial"] ?? 0);
if ($total === 1) {
$count_text = __("Showing the single result", "woocommerce");
} elseif ($total <= $per_page || $per_page === -1) {
$count_text = sprintf(
_n(
"Showing all %d result",
"Showing all %d results",
$total,
"woocommerce"
),
$total
);
} else {
$first = $per_page * $current - $per_page + 1;
$last = min($total, $per_page * $current);
if ($initial > 0) {
if ($current < $initial) {
$last = min($total, $per_page * $initial);
} else {
$first = $per_page * $initial - $per_page + 1;
}
}
$count_text = sprintf(
_nx(
'Showing %1$d&ndash;%2$d of %3$d result',
'Showing %1$d&ndash;%2$d of %3$d results',
$total,
"with first and last result",
"woocommerce"
),
$first,
$last,
$total
);
}
$response["html"] = function_exists("minify_html") ? minify_html($html) : $html;
$response["data"] = $count_text;
echo json_encode($response);
wp_die();
break;
case 'site_config':
$meta = $vars['meta'] ?? [];
echo json_encode(SaltHareket\Theme::get_site_config(1, $meta));
wp_die();
break;
case 'twig_render':
$context = Timber::context();
$context['data'] = $vars['data'] ?? [];
echo Timber::compile([$vars['template'] . '.twig'], $context);
wp_die();
break;
case 'custom_modal':
$error   = true;
$message = 'Content not found';
$html    = '';
if ( isset($vars['id']) ) {
$post  = Timber::get_post($vars['id']);
$error = false;
$message = '';
$html = $post->strip_tags($post->content);
// Harici CSS (post meta'dan — logout+logged merged tek dosya)
$css = $post->meta('css');
if ( $css ) {
$html .= '<link rel="stylesheet" href="' . get_template_directory_uri() . '/theme/templates/_custom/' . $css . '" media="all" crossorigin="anonymous">';
}
}
modal_json_output( $html, modal_get_plugins_req($vars['id'] ?? 0, $html), $vars, $error, $message );
break;
case 'form_modal':
if (function_exists('wpcf7_enqueue_scripts'))  wpcf7_enqueue_scripts();
if (function_exists('wpcf7_enqueue_styles'))   wpcf7_enqueue_styles();
if (function_exists('wpcf7cf_enqueue_scripts')) wpcf7cf_enqueue_scripts();
if (function_exists('wpcf7cf_enqueue_styles'))  wpcf7cf_enqueue_styles();
$form_id = absint($vars['id'] ?? 0);
$response['error']   = false;
$response['message'] = '';
$response['data']    = [
'title'   => $vars['title'] ?? '',
'content' => do_shortcode('[contact-form-7 id="' . $form_id . '"]'),
];
echo json_encode($response);
wp_die();
break;
case 'iframe_modal':
$error   = true;
$message = 'Content not found';
$html    = '';
if (!empty($vars['url'])) {
$error   = false;
$message = '';
$url     = esc_url($vars['url']);
$height  = (int) ($vars['height'] ?? 500);
$html    = "<iframe src=\"{$url}\" width=\"100%\" height=\"{$height}\" frameborder=\"0\" allowfullscreen></iframe>";
}
$response['error']   = $error;
$response['message'] = $message;
$response['html']    = $html;
echo json_encode($response);
wp_die();
break;
case 'map_modal':
$map_service = \SaltHareket\SaltConfig::get('options_map_service') ?: 'leaflet';
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
break;
case 'page_modal':
$post = null;
if (isset($vars['id'])) {
if (is_numeric($vars['id'])) {
$post = get_post(absint($vars['id']));
} else {
switch ($vars['id']) {
case 'privacy-policy':
$post = get_post(get_option('wp_page_for_privacy_policy'));
break;
case 'terms-conditions':
$pid  = defined('ENABLE_ECOMMERCE') && ENABLE_ECOMMERCE ? wc_terms_and_conditions_page_id() : get_option('wp_page_for_privacy_policy');
$post = get_post($pid);
break;
default:
$post = get_page_by_path(sanitize_title($vars['id']));
break;
}
}
}
$error     = true;
$message   = 'Content not found';
$post_data = [];
if ($post) {
$error   = false;
$message = '';
$blocks  = null;
// Selector varsa: sayfayı render edip DOM'dan çek
if (!empty($vars['selector'])) {
$post_url  = get_permalink($post->ID);
$wp_resp   = wp_remote_get($post_url);
$full_html = wp_remote_retrieve_body($wp_resp);
if (!is_wp_error($wp_resp) && !empty($full_html)) {
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $full_html);
$xpath    = new DOMXPath($dom);
$selector = $vars['selector'];
if (str_starts_with($selector, '.')) {
$cls   = substr($selector, 1);
$nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$cls} ')]");
} elseif (str_starts_with($selector, '#')) {
$sid   = substr($selector, 1);
$nodes = $xpath->query("//*[@id='{$sid}']");
} else {
$nodes = $xpath->query("//{$selector}");
}
if ($nodes && $nodes->length > 0) {
$post_content = '';
foreach ($nodes as $node) {
$post_content .= $dom->saveHTML($node);
}
}
}
}
// Selector bulamadıysa veya yoksa: Timber blocks
if (empty($post_content)) {
$post   = Timber::get_post($post);
$blocks = $post->get_blocks();
$post_content = $blocks['html'] ?? '';
}
$post_data['required_js'] = $blocks['required_js'] ?? [];
// Çokdilli başlık/içerik
if (ENABLE_MULTILANGUAGE && ENABLE_MULTILANGUAGE === 'qtranslate-xt') {
$post_data['title']   = qtranxf_use($lang, $post->post_title, false, false);
$post_data['content'] = qtranxf_use($lang, $post_content, false, false);
} else {
$post_data['title']   = $post->post_title;
$post_data['content'] = $post_content;
}
}
$response['error']   = $error;
$response['message'] = $message;
$response['data']    = $post_data;
echo json_encode($response);
wp_die();
break;
case 'review_modal':
/**
* review_modal — Kullanıcının kendi yorumunu modal'da göster.
* Onaylı/onaysız fark etmez — comment_id ile doğrudan DB'den çekilir.
* Status badge, rating, içerik ve delete butonu gösterir.
*
* vars:
*   id — comment ID
*/
$comment_id = (int) ( $vars['id'] ?? 0 );
if ( $comment_id < 1 ) {
$response['error']   = true;
$response['message'] = 'Invalid review ID';
echo json_encode( $response );
wp_die();
}
$comment = get_comment( $comment_id );
if ( ! $comment ) {
$response['error']   = true;
$response['message'] = 'Review not found';
echo json_encode( $response );
wp_die();
}
// Sadece yorumun sahibi veya admin görebilir
$is_owner = (int) $comment->user_id === get_current_user_id();
$is_admin = current_user_can( 'moderate_comments' );
if ( ! $is_owner && ! $is_admin ) {
$response['error']   = true;
$response['message'] = 'Unauthorized';
echo json_encode( $response );
wp_die();
}
$rating     = (int) get_comment_meta( $comment_id, 'rating', true );
$title      = get_comment_meta( $comment_id, 'review_title', true ) ?: '';
$verified   = (bool) get_comment_meta( $comment_id, 'verified', true );
$approved   = $comment->comment_approved === '1';
$post_id    = (int) $comment->comment_post_ID;
$post_title = $post_id ? get_the_title( $post_id ) : '';
$post_link   = $post_id ? get_permalink( $post_id ) : '#';
$context                 = Timber::context();
$context['review']       = [
'id'       => $comment_id,
'content'  => $comment->comment_content,
'rating'   => $rating,
'title'    => $title,
'verified' => $verified,
'approved' => $approved,
'date'     => mysql2date( get_option( 'date_format' ), $comment->comment_date ),
'author'   => $comment->comment_author,
'avatar'   => get_avatar_url( $comment->comment_author_email, [ 'size' => 64 ] ),
'post'     => [
'id'    => $post_id,
'title' => $post_title,
'link'  => $post_link,
],
'is_owner' => $is_owner,
'is_admin' => $is_admin,
];
$html = Timber::compile( [ 'partials/modals/review.twig' ], $context );
$response['error']   = false;
$response['message']  = '';
$response['html']     = $html;
$response['data']     = [
'title' => $title ?: ( $post_title ? 'Review on ' . $post_title : 'Your Review' ),
];
echo json_encode( $response );
wp_die();
break;
case 'template_modal':
$error   = true;
$message = 'Template not found';
$html    = '';
$post_id = 0;
if ( isset($vars['template']) ) {
$error   = false;
$message = '';
$context = Timber::context();
if ( !empty($vars['id']) ) {
$post_id          = (int) $vars['id'];
$context['post']  = Timber::get_post($post_id);
}
$context['data'] = $vars;
$html = Timber::compile([ $vars['template'] . '.twig' ], $context);
}
modal_json_output( $html, modal_get_plugins_req($post_id), $vars, $error, $message );
break;
case 'get_notification_alerts':
if (is_user_logged_in()) {
$messages       = [];
$messages_count = 0;
if (ENABLE_CHAT) {
$messages       = Messenger::notifications('notification');
$messages_count = Messenger::count();
}
$notifications       = new Notifications();
$notifications_count = (int) $notifications->get_unseen_notifications_count();
$notifications_posts = $notifications->get_unseen_notifications();
$response['data'] = [
'count' => [
'message'      => $messages_count,
'notification' => $notifications_count,
],
'notifications' => array_merge($messages, $notifications_posts),
];
} else {
echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
wp_die();
}
echo json_encode($response);
wp_die();
break;
case 'get_notifications':
if ( ! is_user_logged_in() ) {
echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
wp_die();
}
$user          = Timber::get_user( wp_get_current_user() );
$notifications = new Notifications( $user );
$view          = $vars['view'] ?? 'dropdown';
$page          = (int) ( $vars['page'] ?? 1 );
$per_page      = (int) ( $vars['posts_per_page'] ?? 15 );
$set_seen      = ! empty( $vars['set_seen'] );
$result     = $notifications->get_notifications( array_merge( $vars, [
'set_seen'       => $set_seen,
'page'           => $page,
'posts_per_page' => $per_page,
] ) );
$posts      = $result['posts'] ?? [];
$data       = $result['data'] ?? [];
$page_total = (int) ( $data['page_total'] ?? 1 );
// ── Post listesini twig-friendly format'a çevir ───────────────────────────
$timeAgo = class_exists( '\\Westsworld\\TimeAgo' ) ? new \Westsworld\TimeAgo() : null;
$items   = [];
foreach ( $posts as $row ) {
$sender = new User( $row->sender_id );
$url    = '#';
if ( function_exists( 'notification_url_map' ) ) {
$ndata   = json_decode( $row->data ?? '{}', true );
$post_id = (int) ( $ndata['post_id'] ?? 0 );
$uid     = (int) ( $ndata['user_id'] ?? 0 );
$url     = notification_url_map( $row->event, $post_id, $uid ) ?: '#';
}
$time = $row->created_at;
if ( $timeAgo && method_exists( $user, 'get_local_date' ) ) {
$time = $timeAgo->inWordsFromStrings(
$user->get_local_date( $row->created_at, $sender->get_timezone(), $user->get_timezone() )
);
}
$items[] = [
'id'     => $row->id,
'event'  => $row->event,
'message' => strip_tags( $row->message ?? '' ),
'url'    => $url,
'time'   => $time,
'read'   => (bool) ( $row->status ?? false ),
'sender' => [
'image' => get_avatar( $sender->ID, 40, 'mystery', $sender->get_title() ),
'name'  => $sender->get_title(),
],
];
}
$context           = Timber::context();
$context['source'] = 'notifications';
$context['view']   = $view;
$context['items']  = $items;
// Standart zarf (convention): count = toplam, unread_count = okunmamış (unseen).
// Notifications sınıfı 'count'/'total'/'unread'/'unseen' anahtarlarından hangisini verirse normalize et.
$count        = (int) ( $data['count'] ?? $data['total'] ?? count( $items ) );
$unread_count = (int) ( $data['unread_count'] ?? $data['unseen'] ?? $data['unread'] ?? 0 );
if ( method_exists( $user, 'get_notifications_unseen_count' ) ) {
$unread_count = (int) $user->get_notifications_unseen_count();
}
unset( $data['total'], $data['unseen'], $data['unread'] );
$data['count']        = $count;
$data['page']         = (int) $page;
$data['page_total']   = (int) $page_total;
$data['per_page']     = (int) $per_page;
$data['unread_count'] = $unread_count;
$context['meta']   = [
'count'        => $count,
'page'         => (int) $page,
'page_total'   => (int) $page_total,
'per_page'     => (int) $per_page,
'unread_count' => $unread_count,
];
// Feed modu (my-account): sadece tease'ler. Popup: archive (+ archive-{view} override fallback).
$popup_views = [ 'offcanvas', 'dropdown', 'modal' ];
if ( ! in_array( $view, $popup_views, true ) ) {
$html = '';
foreach ( $items as $item ) {
$html .= Timber::compile( 'partials/source/notifications/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
}
$data['loader'] = $vars['loader'] ?? 'ul';
$response['html'] = $html;
} else {
$response['html'] = Timber::compile(
[ "partials/source/notifications/archive-{$view}.twig", 'partials/source/notifications/archive.twig' ],
$context
);
}
// data'yı en son set et — loader gibi sonradan eklenen key'ler dahil olsun
$response['data'] = $data;
echo json_encode( $response );
wp_die();
break;
case 'get_reactions':
/**
* get_reactions — Kullanıcının belirli bir reaction tipindeki içeriklerini döndürür.
*
* vars:
*   reaction_type  — favorite | like | follow | bookmark | clap | ...
*   object_type    — post | user | term | comment  (default: post)
*   view           — offcanvas | dropdown | modal   (default: dropdown)
*   page           — sayfa numarası (default: 1)
*   posts_per_page — sayfa başı öğe (default: 12)
*   post_type      — (object_type=post)    post type filtresi (örn: product)
*   comment_type   — (object_type=comment) comment type filtresi (örn: review)
*/
if ( ! is_user_logged_in() ) {
echo json_encode( response_login_required( $vars['view'] ?? 'dropdown' ) );
wp_die();
}
$reaction_type = sanitize_key( $vars['reaction_type'] ?? 'favorite' );
$object_type   = sanitize_key( $vars['object_type']   ?? 'post' );
$view          = sanitize_key( $vars['view']           ?? 'dropdown' );
$page          = max( 1, (int) ( $vars['page']           ?? 1 ) );
$per_page      = max( 1, (int) ( $vars['posts_per_page'] ?? 12 ) );
$user_id       = get_current_user_id();
// ── Subtype filtresi — object_type'a göre doğru filtreyi seç ─────────────
$post_type_filter    = sanitize_key( $vars['post_type']    ?? '' );
$comment_type_filter = sanitize_key( $vars['comment_type'] ?? '' );
$object_subtype_filter = '';
if ( $object_type === 'post' && $post_type_filter !== '' ) {
$object_subtype_filter = $post_type_filter;
} elseif ( $object_type === 'comment' && $comment_type_filter !== '' ) {
$object_subtype_filter = $comment_type_filter;
}
// ── Reaction ID'lerini çek (subtype filtresiyle) ──────────────────────────
$all_ids = \SaltHareket\Reactions\Reactions::getByUser( $user_id, $reaction_type, $object_type, $object_subtype_filter );
$total   = count( $all_ids );
// Paginate
$offset     = ( $page - 1 ) * $per_page;
$paged_ids  = array_slice( $all_ids, $offset, $per_page );
$page_total = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
// ── Object'leri yükle ─────────────────────────────────────────────────────
$items = [];
if ( ! empty( $paged_ids ) ) {
if ( $object_type === 'post' ) {
// product seçilmişse product_variation'ı da dahil et
$query_post_type = 'any';
if ( $post_type_filter === 'product' ) {
$query_post_type = [ 'product', 'product_variation' ];
} elseif ( $post_type_filter !== '' ) {
$query_post_type = $post_type_filter;
}
$timber_posts = Timber::get_posts( [
'post__in'       => $paged_ids,
'orderby'        => 'post__in',
'posts_per_page' => $per_page,
'post_type'      => $query_post_type,
'post_status'    => 'publish',
'lang'           => '', // Polylang: tüm dillerdeki postları getir
] );
if ( $timber_posts ) {
foreach ( $timber_posts as $p ) {
$items[] = $p;
}
}
} elseif ( $object_type === 'user' ) {
foreach ( $paged_ids as $uid ) {
$timber_user = Timber::get_user( $uid );
if ( $timber_user ) $items[] = $timber_user;
}
} elseif ( $object_type === 'term' ) {
foreach ( $paged_ids as $term_id ) {
$timber_term = Timber::get_term( $term_id );
if ( $timber_term ) $items[] = $timber_term;
}
} elseif ( $object_type === 'comment' ) {
foreach ( $paged_ids as $comment_id ) {
$comment = get_comment( $comment_id );
if ( $comment ) $items[] = $comment;
}
}
}
$context                     = Timber::context();
$context['source']           = 'reaction:' . $reaction_type;
$context['reaction_type']    = $reaction_type;
$context['object_type']      = $object_type;
$context['post_type_filter'] = $post_type_filter;     // pagination'da filtre korunur
$context['comment_type_filter'] = $comment_type_filter;
$context['view']             = $view;
$context['items']            = $items;
$context['meta']             = [
'count'      => $total,
'page'       => $page,
'page_total' => $page_total,
'per_page'   => $per_page,
];
// Standart zarf (convention): data = { count, page, page_total, per_page }.
// reaction'da "okunmamış" kavramı yok → unread_count YOK.
$response['data'] = [
'count'      => (int) $total,
'page'       => (int) $page,
'page_total' => (int) $page_total,
'per_page'   => (int) $per_page,
];
// Feed modu (my-account): sadece tease'ler. Popup (offcanvas/dropdown/modal): archive.
// archive-{view}.twig varsa onu, yoksa archive.twig'i kullan (view override fallback).
$popup_views = [ 'offcanvas', 'dropdown', 'modal' ];
if ( ! in_array( $view, $popup_views, true ) ) {
$html = '';
foreach ( $items as $item ) {
$html .= Timber::compile( 'partials/source/reactions/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
}
$response['html'] = $html;
} else {
$response['html'] = Timber::compile(
[ "partials/source/reactions/archive-{$view}.twig", 'partials/source/reactions/archive.twig' ],
$context
);
}
echo json_encode( $response );
wp_die();
break;
case 'get_reviews':
// Login zorunlu değil — public profil sayfasında herkes review görebilir
// Yazma işlemi ayrıca kontrol edilir
if ( ! class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
$response['error']   = true;
$response['message'] = 'Reviews not available';
echo json_encode( $response );
wp_die();
}
$view      = sanitize_key( $vars['view'] ?? 'list' );
$user_id   = (int) ( $vars['user'] ?? $vars['user_id'] ?? 0 );
$page      = max( 1, (int) ( $vars['page'] ?? 1 ) );
$per_page  = max( 1, (int) ( $vars['posts_per_page'] ?? 10 ) );
$reviews   = new \SaltHareket\Reviews\Reviews( $user_id );
// Hangi review kümesi?
$popup_views  = [ 'offcanvas', 'dropdown', 'modal' ];
$default_mode = in_array( $view, $popup_views, true ) ? 'received' : 'authored';
$mode         = sanitize_key( $vars['mode'] ?? $default_mode );
$args      = [
'page'     => $page,
'per_page' => $per_page,
'order'    => sanitize_key( $vars['order'] ?? 'desc' ),
'status'   => ( isset( $vars['status'] ) && (string) $vars['status'] === '0' ) ? 'hold' : 'approve',
];
// authored mode'da status parametresi gelmediyse tüm yorumlar
if ( $mode === 'authored' && ! isset( $vars['status'] ) ) {
$args['status'] = 'all';
}
$result     = ( $mode === 'authored' )
? $reviews->getByAuthor( $user_id, $args )
: ( ( $mode === 'profile' || $mode === 'on_me' )
? $reviews->getForUser( $user_id, $args )
: ( $mode === 'on_posts'
? $reviews->getForPosts( $user_id, $args )
: $reviews->getReceived( $user_id, $args ) ) );
$raw        = $result['reviews'] ?? [];
$data       = $result['data'] ?? [];
$page_total = (int) ( $data['page_total'] ?? 1 );
// data.count normalize — runQuery 'total' döndürür, diğer source'lar 'count' kullanır.
// unread_count her zaman badge kümesiyle (getUnreadCount) tutarlı olsun.
$data['count']        = (int) ( $data['count'] ?? $data['total'] ?? count( $raw ) );
$data['unread_count'] = \SaltHareket\Reviews\Reviews::getUnreadCount( get_current_user_id() );
unset( $data['total'] );
// ── Twig-friendly format ──────────────────────────────────────────────────
$items = [];
foreach ( $raw as $review ) {
$post_id    = (int) ( $review->comment_post_ID ?? 0 );
$post_title = $post_id ? get_the_title( $post_id ) : '';
$post_link  = $post_id ? get_permalink( $post_id ) : '#';
$author_id     = (int) ( $review->user_id ?? 0 );
$author_name   = $review->comment_author ?? '';
$author_avatar = $author_id ? get_avatar_url( $author_id, [ 'size' => 40 ] ) : '';
$items[] = [
'id'       => $review->comment_ID,
'content'  => wp_strip_all_tags( $review->comment_content ?? '' ),
'rating'   => (int) get_comment_meta( $review->comment_ID, 'rating', true ),
'date'     => mysql2date( get_option( 'date_format' ), $review->comment_date ),
'read'     => (bool) get_comment_meta( $review->comment_ID, '_sh_review_read', true ),
'approved' => $review->comment_approved === '1',
'verified' => (bool) get_comment_meta( $review->comment_ID, 'verified', true ),
'post'    => [
'id'    => $post_id,
'title' => $post_title,
'link'  => $post_link,
],
'author'  => [
'name'   => $author_name,
'avatar' => $author_avatar,
],
];
}
$context           = Timber::context();
$context['source'] = 'reviews';
$context['view']   = $view;
$context['mode']   = $mode;
$context['items']  = $items;
$context['meta']   = [
'count'        => $data['count'],
'page'         => $page,
'page_total'   => $page_total,
'unread_count' => $data['unread_count'],
];
// Feed modu: sadece tease'ler, popup modu: tam archive ($popup_views yukarıda tanımlı)
if ( ! in_array( $view, $popup_views, true ) ) {
$html = '';
foreach ( $items as $item ) {
$html .= Timber::compile( 'partials/source/reviews/tease.twig', array_merge( $context, [ 'item' => $item ] ) );
}
// Boşsa not-found mesajı
if ( empty( $items ) ) {
$html = Timber::compile( 'partials/not-found/reviews.twig', $context );
}
$data['loader'] = $vars['loader'] ?? 'ul';
$response['html'] = $html;
} else {
// archive-{view}.twig varsa onu, yoksa archive.twig (view override fallback).
$response['html'] = Timber::compile(
[ "partials/source/reviews/archive-{$view}.twig", 'partials/source/reviews/archive.twig' ],
$context
);
}
// data'yı en son set et — loader dahil
$response['data'] = $data;
echo json_encode( $response );
wp_die();
break;
case 'login':
$salt = \Salt::get_instance();
echo json_encode($salt->login($vars));
wp_die();
break;
case 'nickname_exist':
$salt   = \Salt::get_instance();
$status = $salt->nickname_exist($vars);
$response['error']   = (bool) $status;
$response['message'] = $status ?: '';
echo json_encode($response);
wp_die();
break;
case 'password_recover':
$salt = \Salt::get_instance();
echo $salt->password_recover($vars);
wp_die();
break;
case 'register':
$salt = \Salt::get_instance();
echo json_encode($salt->register($vars));
wp_die();
break;
case 'search_terms_add':
$sh = new SearchHistory();
$sh->set_term($keyword);
wp_die();
break;
case 'search_terms_remove':
echo userSearchTermsRemove(wp_get_current_user());
wp_die();
break;
case 'send_profile_message':
$sender_id   = get_current_user_id();
$reciever_id = $vars['id'] ?? 0;
$post_id     = $vars['post_id'] ?? 0;
$message     = $vars['message'] ?? '';
if (empty($message)) {
$response['error']   = true;
$response['message'] = 'Please write a message';
echo json_encode($response);
wp_die();
}
$conv_id = Messenger::find_conversation($post_id, $sender_id, $reciever_id, true);
$conversation = $conv_id
? Messenger::store($conv_id, $sender_id, $reciever_id, $message)
: Messenger::create_conversation($sender_id, $reciever_id, $message, $post_id);
if (is_true($vars['static'] ?? false)) {
$url = $post_id ? get_permalink($post_id) : Data::get('base_urls.messages') . $conversation->conv_id . '/chat/' . $reciever_id . '/';
$response['message']     = 'Your message has been sent!';
$response['description'] = "<a href='" . esc_url($url) . "' target='_blank'>View your conversation</a>";
} else {
if ($post_id) {
$response['redirect'] = get_permalink($post_id) . '?conversationId=' . $conversation->conv_id . '#messages';
} else {
$response['redirect'] = Data::get('base_urls.messages') . $conversation->conv_id . '/chat/' . $reciever_id . '/';
}
}
$conversation = before_store_new_message($conversation);
after_store_new_message($conversation);
echo json_encode($response);
wp_die();
break;
case 'sh_review_owner_action':
/**
* sh_review_owner_action — Review sahibinin approve/reject/delete yapması
*
* vars:
*   id     — comment ID
*   action — approve | reject | delete
*/
if ( ! is_user_logged_in() ) {
echo json_encode( [ 'success' => false, 'message' => 'Not logged in.' ] );
wp_die();
}
$comment_id = (int) ( $vars['id'] ?? 0 );
$action     = sanitize_key( $vars['action'] ?? '' );
$user_id    = get_current_user_id();
if ( ! $comment_id || ! in_array( $action, [ 'approve', 'reject', 'delete' ], true ) ) {
echo json_encode( [ 'success' => false, 'message' => 'Invalid parameters.' ] );
wp_die();
}
$comment = get_comment( $comment_id );
if ( ! $comment || $comment->comment_type !== 'review' ) {
echo json_encode( [ 'success' => false, 'message' => 'Review not found.' ] );
wp_die();
}
// Yetki: kullanıcı bu yorumun hedefi olmalı
//   a) comment_profile meta = bu kullanıcı (profile review)
//   b) comment_post_ID'nin post_author = bu kullanıcı (post review)
$profile_target = (int) get_comment_meta( $comment_id, 'comment_profile', true );
$post_author    = $comment->comment_post_ID ? (int) get_post_field( 'post_author', $comment->comment_post_ID ) : 0;
$is_authorized = ( $profile_target === $user_id ) || ( $post_author === $user_id );
// Admin her zaman yapabilir
if ( current_user_can( 'moderate_comments' ) ) {
$is_authorized = true;
}
if ( ! $is_authorized ) {
echo json_encode( [ 'success' => false, 'message' => 'Unauthorized.' ] );
wp_die();
}
switch ( $action ) {
case 'approve':
wp_set_comment_status( $comment_id, 'approve' );
update_comment_meta( $comment_id, '_sh_review_read', 1 );
if ( class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
( new \SaltHareket\Reviews\Reviews() )->approve( $comment_id );
}
echo json_encode( [ 'success' => true, 'message' => 'Approved.' ] );
break;
case 'reject':
wp_set_comment_status( $comment_id, 'hold' );
if ( class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
( new \SaltHareket\Reviews\Reviews() )->reject( $comment_id );
}
echo json_encode( [ 'success' => true, 'message' => 'Rejected.' ] );
break;
case 'delete':
if ( class_exists( '\SaltHareket\Reviews\Reviews' ) ) {
( new \SaltHareket\Reviews\Reviews() )->delete( $comment_id );
} else {
wp_delete_comment( $comment_id, true );
}
echo json_encode( [ 'success' => true, 'message' => 'Deleted.' ] );
break;
}
wp_die();
break;
case 'update_profile':
try {
$salt = \Salt::get_instance();
echo json_encode($salt->update_profile($vars));
} catch (\Throwable $e) {
error_log('[update_profile] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
}
wp_die();
break;
case 'update_profile_photo':
$user_id = get_current_user_id();
if (!$user_id) {
$response['error']   = true;
$response['message'] = 'Not logged in';
echo json_encode($response);
wp_die();
}
$files = $_FILES['profile_photo_main'] ?? null;
if (empty($files)) {
$response['error']   = true;
$response['message'] = 'No file uploaded';
echo json_encode($response);
wp_die();
}
require_once ABSPATH . 'wp-admin/includes/image.php';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
foreach ($files['name'] as $key => $name) {
if (empty($name) || !is_uploaded_file($files['tmp_name'][$key])) continue;
if (!in_array($files['type'][$key], $allowed_types)) continue;
$uploads   = wp_upload_dir();
$safe_name = time() . '-' . sanitize_file_name($name);
$file_path = $uploads['path'] . '/' . $safe_name;
$file_url  = $uploads['url'] . '/' . $safe_name;
if (!move_uploaded_file($files['tmp_name'][$key], $file_path)) continue;
$filetype = wp_check_filetype(basename($file_path), null);
$attach_id = wp_insert_attachment([
'guid'           => $file_url,
'post_mime_type' => $filetype['type'],
'post_title'     => sanitize_file_name($safe_name),
'post_content'   => '',
'post_status'    => 'inherit',
], $file_path, 0);
if (is_wp_error($attach_id)) continue;
// Eski profil resmini sil
$old_image = get_field('profile_image', 'user_' . $user_id);
if ($old_image) {
wp_delete_attachment($old_image, true);
}
wp_generate_attachment_metadata($attach_id, $file_path);
update_post_meta($attach_id, '_wp_attachment_wp_user_avatar', $user_id);
update_field('profile_image', $attach_id, 'user_' . $user_id);
$thumb = wp_get_attachment_image_src($attach_id, 'smallthumb');
$response['message'] = 'Image has been uploaded';
$response['data']    = $thumb[0] ?? $file_url;
break; // Sadece ilk dosya
}
echo json_encode($response);
wp_die();
break;
case 'user_exist':
$salt   = \Salt::get_instance();
$status = $salt->user_exist($vars);
$response['error']   = (bool) $status;
$response['message'] = $status ?: '';
echo json_encode($response);
wp_die();
break;
case 'validate_phone':
$salt   = \Salt::get_instance();
$status = $salt->validate_phone($vars['phone'] ?? '', $vars['country'] ?? '', $vars['phone_code'] ?? '');
echo json_encode($status);
wp_die();
break;
case 'verify_2fa_totp':
/**
* verify_2fa_totp — TOTP kodunu doğrula, login'i tamamla.
*
* vars:
*   pending_token — setPending2FA()'dan gelen token
*   totp_code     — authenticator app'den gelen 6 haneli kod
*   remember      — (opsiyonel) bool
*   redirect_url  — (opsiyonel) string
*/
if ( ! $required_setting ) {
$response['error']   = true;
$response['message'] = 'Membership is not enabled.';
echo json_encode( $response );
wp_die();
}
$mm = \SaltHareket\Membership\MembershipManager::getInstance();
echo json_encode( $mm->verify2FATOTP( $vars ) );
wp_die();
break;
case 'add_to_cart':
global $woocommerce;
$product_id   = absint($vars['product_id'] ?? $vars['productId'] ?? 0);
$quantity     = max(1, absint($vars['quantity'] ?? 1));
$variation_id = absint($vars['variation_id'] ?? $vars['variationId'] ?? 0);
if (!$product_id) {
$response['error']   = true;
$response['message'] = __('Ürün bulunamadı.', 'woocommerce');
echo json_encode($response);
wp_die();
}
$product = wc_get_product($variation_id ?: $product_id);
if (!$product || !$product->is_purchasable()) {
$response['error']   = true;
$response['message'] = __('Bu ürün satın alınamaz.', 'woocommerce');
echo json_encode($response);
wp_die();
}
if (!$product->is_in_stock()) {
$response['error']   = true;
$response['message'] = __('Bu ürün stokta yok.', 'woocommerce');
echo json_encode($response);
wp_die();
}
$added = $woocommerce->cart->add_to_cart($product_id, $quantity, $variation_id);
if ($added) {
$cart_item = $woocommerce->cart->get_cart_item($added);
$_product  = $cart_item['data'];
$response['error']   = false;
$response['message'] = sprintf(
__('<b>%s</b> sepete eklendi.', 'woocommerce'),
esc_html($_product->get_name())
);
$response['data'] = [
'cart_key'  => $added,
'count'     => $woocommerce->cart->get_cart_contents_count(),
'total'     => strip_tags(wc_price($woocommerce->cart->get_cart_contents_total())),
'product'   => [
'id'    => $_product->get_id(),
'name'  => $_product->get_name(),
'price' => strip_tags(wc_price($_product->get_price())),
'image' => wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail'),
'url'   => get_permalink($product_id),
],
];
} else {
$response['error']   = true;
$response['message'] = __('This product cannot be added to the cart.', 'woocommerce');
}
echo json_encode($response);
wp_die();
break;
case 'custom_track_product_view':
custom_track_product_view_js($vars['post_id'] ?? 0);
wp_die();
break;
case 'get_cart':
global $woocommerce;
$view    = $vars['view'] ?? 'dropdown';
$cart    = woo_get_cart_object();
$items   = $cart['items'] ?? [];
$count   = $woocommerce->cart->get_cart_contents_count();
$context            = Timber::context();
$context['source']  = 'cart';
$context['view']    = $view;
$context['items']   = $items;
$context['meta']    = [
'count'      => $count,
'page'       => 1,
'page_total' => 1,
'per_page'   => $count > 0 ? $count : 1,
'total'      => $cart['total'] ?? '',
];
// Standart zarf. Cart paginate edilmez → page=1, page_total=1.
$response['data'] = [
'count'      => $count,
'page'       => 1,
'page_total' => 1,
];
// archive-{view}.twig varsa onu, yoksa archive.twig (cart .load-container istisnası korunur).
$response['html'] = Timber::compile(
[ "partials/source/cart/archive-{$view}.twig", 'partials/source/cart/archive.twig' ],
$context
);
echo json_encode( $response );
wp_die();
break;
case 'get_products':
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
if (defined('ENABLE_REACTIONS') && ENABLE_REACTIONS) {
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
break;
case 'pay_now':
$salt = \Salt::get_instance();
$salt->remove_cart_content();
$salt->add_to_cart($vars['id']);
$response['redirect'] = woo_checkout_url();
echo json_encode($response);
wp_die();
break;
case 'salt_recently_viewed_products':
$posts = Timber::get_posts(salt_recently_viewed_products());
$vars['posts'] = $posts->to_array();
$response['data'] = $vars;
echo json_encode($response);
wp_die();
break;
case 'wc_api_create_product':
echo json_encode(wc_api_create_product($vars['data']));
wp_die();
break;
case 'wc_api_create_product_variation':
echo json_encode(wc_api_create_product_variation($vars['data'], $vars['id']));
wp_die();
break;
case 'wc_api_filter_products':
$woo_api = Data::get('woo_api');
echo json_encode($woo_api->get('products', $vars['filters'] ?? ['tag' => '103,63']));
wp_die();
break;
case 'wc_api_update_product':
echo json_encode(wc_api_update_product($vars['data']));
wp_die();
break;
case 'wc_cart_clear':
global $woocommerce;
$woocommerce->cart->empty_cart();
// Standart zarf — sepet temizlendi, count = 0.
$response['data'] = [
'count'      => 0,
'page'       => 1,
'page_total' => 1,
];
echo json_encode( $response );
wp_die();
break;
case 'wc_cart_item_remove':
global $woocommerce;
$woocommerce->cart->remove_cart_item( $vars['key'] );
$view  = sanitize_key( $vars['view'] ?? 'dropdown' );
$cart  = woo_get_cart_object();
$count = (int) $woocommerce->cart->get_cart_contents_count();
$context            = Timber::context();
$context['source']  = 'cart';
$context['type']    = 'cart';        // geriye dönük uyum
$context['view']    = $view;
$context['cart']    = $cart;
$context['items']   = $cart['items'] ?? [];
$context['meta']    = [
'count' => $count,
'total' => $cart['total'] ?? '',
];
// Convention: source/cart/archive.twig (eski partials/{view}/archive.twig değil).
// archive-{view}.twig varsa onu, yoksa archive.twig.
$response['data'] = [ 'count' => $count ];
$response['html'] = Timber::compile(
[ "partials/source/cart/archive-{$view}.twig", 'partials/source/cart/archive.twig' ],
$context
);
echo json_encode( $response );
wp_die();
break;
case 'wc_cart_quantity_update':
global $woocommerce;
$woocommerce->cart->set_quantity( $vars['key'], (int) $vars['count'] );
$view  = sanitize_key( $vars['view'] ?? 'dropdown' );
$cart  = woo_get_cart_object();
$count = (int) $woocommerce->cart->get_cart_contents_count();
$context            = Timber::context();
$context['source']  = 'cart';
$context['type']    = 'cart';
$context['view']    = $view;
$context['cart']    = $cart;
$context['items']   = $cart['items'] ?? [];
$context['meta']    = [
'count' => $count,
'total' => $cart['total'] ?? '',
];
// Standart zarf. data içinde cart nesnesi de korunur (fragment/JS tüketimi için).
$response['data'] = array_merge( (array) $cart, [ 'count' => $count ] );
$response['html'] = Timber::compile(
[ "partials/source/cart/archive-{$view}.twig", 'partials/source/cart/archive.twig' ],
$context
);
echo json_encode( $response );
wp_die();
break;
case 'wc_modal_page_template':
global $woocommerce;
$context         = Timber::context();
$context['date'] = date('d.m.Y');
$content         = apply_filters('the_content', get_post_field('post_content', $id));
// Müşteri bilgileri
$customer_data = $woocommerce->cart->get_customer();
$shipping      = $customer_data->shipping;
$context['customer'] = [
'name'             => trim($customer_data->first_name . ' ' . $customer_data->last_name),
'shipping_address' => implode(' ', array_filter([
$shipping['address_1'] ?? '',
$shipping['city'] ?? '',
$shipping['state'] ?? '',
$shipping['postcode'] ?? '',
$shipping['country'] ?? '',
])),
'phone' => $customer_data->billing['phone'] ?? '',
'email' => $customer_data->email ?? '',
'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
];
// Sepet öğeleri
$cart_items = [];
$tax_total  = 0;
foreach ($woocommerce->cart->get_cart() as $values) {
$_product = wc_get_product($values['data']->get_id());
$detail   = wc_get_product($values['product_id']);
$tax      = $values['line_subtotal_tax'];
$tax_total += $tax;
$cart_items[] = [
'image'       => $detail->get_image('thumbnail'),
'title'       => $_product->get_title(),
'price'       => woo_get_currency_with_price(get_post_meta($values['variation_id'], '_price', true)),
'quantity'    => $values['quantity'],
'tax'         => woo_get_currency_with_price($tax),
'total_price' => woo_get_currency_with_price($values['line_subtotal']),
];
}
$context['cart']      = $cart_items;
$context['total_tax'] = woo_get_currency_with_price($tax_total);
$context['total']     = woo_get_currency_with_price($woocommerce->cart->total);
Timber::render_string($content, $context);
wp_die();
break;
case 'wc_order_list':
$order_number = $vars['order_number'] ?? '';
ob_start();
woocommerce_order_details_table($order_number);
$html = ob_get_clean();
$response['error']   = false;
$response['message'] = '';
$response['data']    = ['order_number' => $order_number];
$response['html']    = $html;
echo json_encode($response);
wp_die();
break;
case 'woo_get_product_variation_thumbnails':
$images  = woo_get_product_variation_thumbnails($vars['product_id'], $vars['attr'], $vars['attr_value'], $vars['size'] ?? 'medium');
$product = wc_get_product($vars['product_id']);
$context           = Timber::context();
$context['post']   = $product;
$context['type']   = $product->get_type();
$context['images'] = $images;
$response['html'] = Timber::compile($vars['template'] . '.twig', $context);
echo json_encode($response);
wp_die();
break;
case 'woo_get_variation_gallery':
/**
* woo_get_variation_gallery
*
* Bir variation veya variable ürünün tüm görsel URL'lerini döndürür.
* product-gallery.twig'i AJAX ile güncellemek için kullanılır.
*
* vars:
*   product_id  — variation veya parent product ID
*   size        — WP image size (default: woocommerce_single)
*/
$product_id = (int) ( $vars['product_id'] ?? 0 );
$size       = sanitize_key( $vars['size'] ?? 'woocommerce_single' );
if ( ! $product_id ) {
$response['error']   = true;
$response['message'] = 'Invalid product_id';
echo json_encode( $response );
wp_die();
}
$p = wc_get_product( $product_id );
if ( ! $p ) {
$response['error']   = true;
$response['message'] = 'Product not found';
echo json_encode( $response );
wp_die();
}
$images      = [];
$thumb_images = [];
if ( $p->is_type('variation') ) {
// Variation: kendi ana görseli + ek görseller
$image_id = $p->get_image_id();
if ( $image_id ) {
$url = wp_get_attachment_image_url( $image_id, $size );
if ( $url ) $images[] = $url;
}
// WC 11.1 core variation gallery
$gallery_ids = $p->get_gallery_image_ids();
foreach ( $gallery_ids as $gid ) {
$url = wp_get_attachment_image_url( $gid, $size );
if ( $url ) $images[] = $url;
}
// Fallback: eski _wc_additional_variation_images meta
if ( count( $images ) <= 1 ) {
$additional = get_post_meta( $product_id, '_wc_additional_variation_images', true );
if ( $additional ) {
foreach ( array_filter( explode(',', $additional) ) as $add_id ) {
$add_id = (int) trim($add_id);
if ( $add_id ) {
$url = wp_get_attachment_image_url( $add_id, $size );
if ( $url && ! in_array($url, $images) ) $images[] = $url;
}
}
}
}
// Hiç görsel yoksa parent'ın ana görselini kullan
if ( empty( $images ) ) {
$parent = wc_get_product( $p->get_parent_id() );
if ( $parent ) {
$pid = $parent->get_image_id();
if ( $pid ) {
$url = wp_get_attachment_image_url( $pid, $size );
if ( $url ) $images[] = $url;
}
}
}
} elseif ( $p->is_type('variable') ) {
// Variable parent: sadece ana ürün görseli + galeri
// (Variation seçilmemiş durumu — clear basıldığında)
$pid = $p->get_image_id();
if ( $pid ) {
$url = wp_get_attachment_image_url( $pid, $size );
if ( $url ) $images[] = $url;
}
foreach ( $p->get_gallery_image_ids() as $gid ) {
$url = wp_get_attachment_image_url( $gid, $size );
if ( $url ) $images[] = $url;
}
// Galeri boşsa default variation görsellerini kullan
if ( empty( $images ) ) {
$seen = [];
foreach ( $p->get_children() as $child_id ) {
$child    = wc_get_product( $child_id );
if ( ! $child ) continue;
$image_id = $child->get_image_id();
if ( $image_id && ! isset($seen[$image_id]) ) {
$url = wp_get_attachment_image_url( $image_id, $size );
if ( $url ) { $images[] = $url; $seen[$image_id] = true; }
}
}
}
} else {
// Simple: ana görsel + galeri
$pid = $p->get_image_id();
if ( $pid ) {
$url = wp_get_attachment_image_url( $pid, $size );
if ( $url ) $images[] = $url;
}
foreach ( $p->get_gallery_image_ids() as $gid ) {
$url = wp_get_attachment_image_url( $gid, $size );
if ( $url ) $images[] = $url;
}
}
// Thumbnail ve zoom görselleri
$zoom_images  = [];
foreach ( $images as $img_url ) {
$att_id = attachment_url_to_postid( $img_url );
if ( $att_id ) {
$thumb_url     = wp_get_attachment_image_url( $att_id, 'woocommerce_thumbnail' );
$thumb_images[] = $thumb_url ?: $img_url;
$zoom_url      = wp_get_attachment_image_url( $att_id, 'full' );
$zoom_images[]  = $zoom_url ?: $img_url;
} else {
$thumb_images[] = $img_url;
$zoom_images[]  = $img_url;
}
}
$response['data'] = [
'images'       => $images,
'thumb_images' => $thumb_images,
'zoom_images'  => $zoom_images,
'count'        => count( $images ),
];
echo json_encode( $response );
wp_die();
break;
}
function get_ajax_methods() {
return ['acf_layout_posts', 'comment_product', 'comment_product_detail', 'comment_product_modal', 'autocomplete_terms', 'get_post', 'get_search_history', 'pagination_ajax', 'site_config', 'twig_render', 'custom_modal', 'form_modal', 'iframe_modal', 'map_modal', 'page_modal', 'review_modal', 'template_modal', 'get_notification_alerts', 'get_notifications', 'get_reactions', 'get_reviews', 'login', 'nickname_exist', 'password_recover', 'register', 'search_terms_add', 'search_terms_remove', 'send_profile_message', 'sh_review_owner_action', 'update_profile', 'update_profile_photo', 'user_exist', 'validate_phone', 'verify_2fa_totp', 'add_to_cart', 'custom_track_product_view', 'get_cart', 'get_products', 'pay_now', 'salt_recently_viewed_products', 'wc_api_create_product', 'wc_api_create_product_variation', 'wc_api_filter_products', 'wc_api_update_product', 'wc_cart_clear', 'wc_cart_item_remove', 'wc_cart_quantity_update', 'wc_modal_page_template', 'wc_order_list', 'woo_get_product_variation_thumbnails', 'woo_get_variation_gallery'];
}
