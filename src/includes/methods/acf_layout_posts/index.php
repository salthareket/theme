<?php
$paginate = new Paginate([], $vars);
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
