<?php

// Kampanyalar her gece yarısı kontrol edilecek, suresi gecen cope atılacak
if (!wp_next_scheduled('kampanya_cleanup_event')) {
    wp_schedule_event(strtotime('midnight'), 'daily', 'kampanya_cleanup_event');
}
add_action('kampanya_cleanup_event', function() {
    $now = current_time('Y-m-d');
    $expired = get_posts([
        'post_type'      => 'kampanya',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'end_date',
                'value'   => '',
                'compare' => '!=',
            ],
            [
                'key'     => 'end_date',
                'value'   => $now,
                'compare' => '<',
                'type'    => 'DATE',
            ],
        ],
        'fields' => 'ids',
    ]);
    foreach ($expired as $post_id) {
        wp_trash_post($post_id); // istersen wp_delete_post($post_id, true) yapabilirsin
    }
});





// Etkinlikler her gece yarısı kontrol edilecek, suresi gecen is_past metası ile lag'lenecek
if (!wp_next_scheduled('etkinlik_cleanup_event')) {
    wp_schedule_event(strtotime('midnight'), 'daily', 'etkinlik_cleanup_event');
}
add_action('etkinlik_cleanup_event', function() {
    $now = current_time('Y-m-d H:i:s');
    $expired = get_posts([
        'post_type'      => 'etkinlikler',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR', // iki durumdan biri geçerse al
            // 1) end_date geçmiş
            [
                'key'     => 'end_date',
                'value'   => $now,
                'compare' => '<',
                'type'    => 'DATETIME',
            ],
            // 2) start_date geçmiş, end_date yok, period yok veya boş
            [
                'relation' => 'AND',
                [
                    'key'     => 'start_date',
                    'value'   => $now,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ],
                [
                    'key'     => 'end_date',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'period',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => 'period',
                        'value'   => '',
                        'compare' => '=',
                    ],
                ],
            ],
        ],
    ]);
    foreach ($expired as $post_id) {
        update_post_meta($post_id, '_is_past', 1);
    }
});