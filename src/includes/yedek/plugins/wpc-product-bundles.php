<?php

/**
 * WPC Product Bundles — Bundle image helper.
 */

function woo_wpc_bundle_images($product) {
    $items = $product->meta('woosb_ids');
    if (!is_array($items)) return [];

    $images = [];
    foreach ($items as $item) {
        $bundled = wc_get_product($item['id'] ?? 0);
        if (!$bundled) continue;

        $type = $bundled->get_type();
        $thumb = $bundled->get_image_id();

        switch ($type) {
            case 'simple':
                // Simple product image
                break;
            case 'variable':
                // Variable product — default image
                break;
            case 'grouped':
                // Grouped product
                break;
            case 'external':
                // External/affiliate product
                break;
            case 'woosg':
                // Smart grouped product
                break;
        }

        if ($thumb) {
            $images[] = [
                'id'   => $thumb,
                'url'  => wp_get_attachment_url($thumb),
                'type' => $type,
            ];
        }
    }

    return $images;
}
