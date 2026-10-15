<?php

/**
 * Field Registry Bootstrap
 *
 * FieldChoices + FieldRegistry yükler, varsayılan resolver'ları kayıt eder
 * ve ACF hook'unu bağlar.
 *
 * Hem ACF hem custom admin sayfaları bu sistemden yararlanır.
 *
 * Kullanım (custom admin sayfasında):
 *   use SaltHareket\FieldRegistry\FieldRegistry;
 *   $post_types = FieldRegistry::choices('post-types');
 *   $menu_names = FieldRegistry::choices('menu-names');
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/FieldChoices.php';
require_once __DIR__ . '/FieldRegistry.php';

// Varsayılan resolver'ları kaydet
\SaltHareket\FieldRegistry\FieldRegistry::registerDefaults();

// ACF hook — acf_add_field_options'ın yerini alır
if ( function_exists('acf') || class_exists('ACF') ) {
    add_filter( 'acf/load_field', [ \SaltHareket\FieldRegistry\FieldRegistry::class, 'applyToAcfField' ], 10, 1 );
}

// acf/load_field henüz tanımlı değilse (erken yükleme) — plugins_loaded'da da dene
add_action( 'acf/init', function() {
    if ( ! has_filter('acf/load_field', [ \SaltHareket\FieldRegistry\FieldRegistry::class, 'applyToAcfField' ]) ) {
        add_filter( 'acf/load_field', [ \SaltHareket\FieldRegistry\FieldRegistry::class, 'applyToAcfField' ], 10, 1 );
    }
}, 5 );
