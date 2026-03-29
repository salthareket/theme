<?php
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
