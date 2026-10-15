<?php
/**
 * Header Tools Block — wrapper, InnerBlocks ile tool item'lari alir.
 * acf-innerblocks-container div'ine nav-user class'larini vererek
 * ekstra wrapper olmadan flex row calisir.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fields   = get_fields() ?: ( $block['data'] ?? [] );
$position = $fields['position'] ?? 'end';

$navbar_classes = esc_attr( implode( ' ', [
    'navbar', 'header-tools', 'header-tools-' . $position, 'py-0', 'justify-content-' . $position,
] ) );

// InnerBlocks wrapper'i nav-user div'i olarak kullan — ekstra sarma yok
$inner_class = esc_attr( 'nav-user nav flex-nowrap flex-row' );

// Salt block'lari — header-tools icinde render edilince nav-item wrap eklenecek
$salt_blocks_in_tools = [
    'acf/buttons', 'acf/columns', 'acf/image', 'acf/languages',
    'acf/marquee', 'acf/navigation', 'acf/text', 'acf/video',
];

$allowed = esc_attr( json_encode( array_merge(
    [
        // Header tool block'lari (korunanlar)
        'acf/header-logo',
        'acf/header-tool-cart',
        'acf/header-tool-custom',
        'acf/header-tool-favorites',
        'acf/header-tool-login',
        'acf/header-tool-messages',
        'acf/header-tool-notifications',
        'acf/header-tool-search',
        'acf/header-tool-search-field',
        'acf/header-tool-user-menu',
        // Salt blocks
        'acf/buttons',
        'acf/image',
        'acf/languages',
        'acf/marquee',
        'acf/navigation',
        'acf/social-media',
        'acf/text',
        'acf/video',
    ]
) ) );
?>

    <InnerBlocks
        class="<?= $navbar_classes ?>"
        allowedBlocks="<?= $allowed ?>"
        template="[]"
        orientation="horizontal"
        role="navigation" 
        aria-label="Header Tools"
    />

