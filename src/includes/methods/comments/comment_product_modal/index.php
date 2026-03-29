<?php
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
