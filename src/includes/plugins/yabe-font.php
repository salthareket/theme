<?php

/**
 * Jooosi Fon (eski adı: Yabe Webfont) — Aktif fontları ve font-face bilgilerini döndürür.
 *
 * @changelog
 *   2026-08-26 — Plugin Yabe Webfont → Jooosi Fon olarak yeniden adlandırıldı.
 *                Tablo: yabe_webfont_fonts → jooosi_fon_fonts
 *                Class kontrolü: YABE_WEBFONT → JooosiFon\Plugin
 *                Plugin dosyası: yabe-webfont/yabe-webfont.php → yabe-webfont/jooosi-fon.php
 */

function yabe_get_fonts() {
    global $wpdb;

    // Jooosi Fon (yeni) — tablo jooosi_fon_fonts
    // Geriye uyumluluk: eski Yabe Webfont kurulumlarında yabe_webfont_fonts hâlâ olabilir
    $new_table    = $wpdb->prefix . 'jooosi_fon_fonts';
    $legacy_table = $wpdb->prefix . 'yabe_webfont_fonts';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$new_table}'" ) === $new_table ) {
        $table = $new_table;
    } elseif ( $wpdb->get_var( "SHOW TABLES LIKE '{$legacy_table}'" ) === $legacy_table ) {
        $table = $legacy_table;
    } else {
        return [];
    }

    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT title, family, metadata, font_faces FROM %i WHERE status = 1 AND deleted_at IS NULL",
        $table
    ) );

    if ( ! $results ) return [];

    $fonts = [];
    foreach ( $results as $row ) {
        $faces = json_decode( $row->font_faces, true ) ?: [];
        $meta  = json_decode( $row->metadata,   true ) ?: [];
        $files = [];

        foreach ( $faces as $face ) {
            if ( empty( $face['files'][0] ) ) continue;
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
