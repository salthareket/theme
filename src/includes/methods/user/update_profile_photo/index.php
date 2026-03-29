<?php
$required_setting = ENABLE_MEMBERSHIP;

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
