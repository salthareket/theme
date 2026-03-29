<?php
$required_setting = ENABLE_CHAT;

$new_message = json_decode(stripslashes_deep(html_entity_decode($_POST['details'] ?? '')), true);
$allFiles    = $_FILES;

if (empty($allFiles) || empty($new_message)) {
    $response['error']   = true;
    $response['message'] = 'No files or message data';
    echo json_encode($response);
    wp_die();
}

$salt = \Salt::get_instance();
$uploaded_files = [];

foreach ($allFiles as $key => $singleFile) {
    $uploaded_files[$key]         = $salt->send_message_upload($singleFile);
    $uploaded_files[$key]['type'] = $singleFile['type'];
    $uploaded_files[$key]['size'] = $singleFile['size'];
}

try {
    $new_attachment = Attachment::create([
        'type_t'  => null,
        'conv_id' => $new_message['conv_id'],
        'url'     => json_encode($uploaded_files),
        'size'    => null,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    wp_die();
}

apply_filters('yobro_new_uploaded_assets', $new_attachment);

if (isset($new_attachment)) {
    $new_message['attachment_id'] = $new_attachment['id'];
    $stored_message                = do_store_message($new_message);
    $stored_message['attachments'] = $new_attachment;
    apply_filters('yobro_message_with_attachments', $stored_message);
    echo json_encode($stored_message);
}

wp_die();
