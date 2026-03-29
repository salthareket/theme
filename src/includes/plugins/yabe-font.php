<?php

/**
 * Yabe Webfont — Aktif fontları ve font-face bilgilerini döndürür.
 */

function yabe_get_fonts() {
    global $wpdb;
    $table = $wpdb->prefix . 'yabe_webfont_fonts';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT title, family, metadata, font_faces FROM %i WHERE status = 1 AND deleted_at IS NULL",
        $table
    ));

    if (!$results) return [];

    $fonts = [];
    foreach ($results as $row) {
        $faces    = json_decode($row->font_faces, true) ?: [];
        $meta     = json_decode($row->metadata, true) ?: [];
        $files    = [];

        foreach ($faces as $face) {
            if (empty($face['files'][0])) continue;
            $files[] = [
                'title'  => $face['files'][0]['name'],
                'weight' => $face['weight'],
                'style'  => $face['style'],
                'file'   => $face['files'][0]['attachment_url'],
            ];
        }

        $fonts[] = [
            'family'   => $row->family,
            'title'    => $row->title,
            'selector' => $meta['selector'] ?? '',
            'files'    => $files,
        ];
    }

    return $fonts;
}
