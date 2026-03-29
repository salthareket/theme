<?php
$required_setting = ENABLE_MEMBERSHIP;

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
