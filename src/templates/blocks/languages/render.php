<?php
/**
 * Languages Block — Custom render
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$ctx = [];
if ( ! empty( $GLOBALS['_salt_block_context'] ) ) {
    $ctx = $GLOBALS['_salt_block_context'];
} elseif ( class_exists( 'Timber\\Timber' ) ) {
    $ctx = \Timber\Timber::context();
}

$block_id = $block['id'] ?? '';
if ( $block_id && class_exists( 'QueryCache' ) && \QueryCache::is_enabled() ) {
    $ctx['fields'] = \QueryCache::wrap(
        'block_fields_' . md5( $block_id ),
        static fn() => get_fields() ?: [],
        [ 'post_id' => [ $post_id ] ]
    );
} else {
    $ctx['fields'] = get_fields() ?: [];
}

$ctx['block']      = $block;
$ctx['is_preview'] = $is_preview;
$ctx['post_id']    = $post_id;

// languages degerlerini her zaman Data'dan taze al
if ( defined( 'ENABLE_MULTILANGUAGE' ) && ENABLE_MULTILANGUAGE ) {
    $langs = \Data::get( 'languages', [] );

    // flag / flag_url cache'de yoksa pll_the_languages'tan taze al
    if ( ! empty( $langs ) && empty( $langs[0]['flag'] ) && ENABLE_MULTILANGUAGE === 'polylang'
         && function_exists( 'pll_the_languages' ) ) {
        $pll_langs = pll_the_languages( [ 'raw' => 1 ] );
        foreach ( $langs as $i => $lang ) {
            foreach ( $pll_langs as $pll ) {
                if ( $pll['slug'] === $lang['name'] ) {
                    $langs[ $i ]['flag']     = $pll['flag']     ?? '';
                    $langs[ $i ]['flag_url'] = $pll['flag_url'] ?? '';
                    break;
                }
            }
        }
    }

    if ( ! empty( $langs ) ) {
        $ctx['languages']             = $langs;
        $ctx['language']              = \Data::get( 'language', $ctx['language'] ?? '' );
        $ctx['language_default']      = \Data::get( 'language_default', $ctx['language_default'] ?? '' );
        $ctx['enable_multilanguage']  = true;
    }
}

\Timber\Timber::render( [
    'blocks/languages/languages.twig',
    'vendor/salthareket/theme/src/templates/blocks/languages/languages.twig',
], $ctx );
