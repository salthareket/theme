<?php
/**
 * woo_get_variation_gallery
 *
 * Bir variation veya variable ürünün tüm görsel URL'lerini döndürür.
 * product-gallery.twig'i AJAX ile güncellemek için kullanılır.
 *
 * vars:
 *   product_id  — variation veya parent product ID
 *   size        — WP image size (default: woocommerce_single)
 */
$required_setting = ENABLE_ECOMMERCE;

$product_id = (int) ( $vars['product_id'] ?? 0 );
$size       = sanitize_key( $vars['size'] ?? 'woocommerce_single' );

if ( ! $product_id ) {
    $response['error']   = true;
    $response['message'] = 'Invalid product_id';
    echo json_encode( $response );
    wp_die();
}

$p = wc_get_product( $product_id );
if ( ! $p ) {
    $response['error']   = true;
    $response['message'] = 'Product not found';
    echo json_encode( $response );
    wp_die();
}

$images      = [];
$thumb_images = [];

if ( $p->is_type('variation') ) {
    // Variation: kendi ana görseli + ek görseller
    $image_id = $p->get_image_id();

    if ( $image_id ) {
        $url = wp_get_attachment_image_url( $image_id, $size );
        if ( $url ) $images[] = $url;
    }

    // WC 11.1 core variation gallery
    $gallery_ids = $p->get_gallery_image_ids();
    foreach ( $gallery_ids as $gid ) {
        $url = wp_get_attachment_image_url( $gid, $size );
        if ( $url ) $images[] = $url;
    }

    // Fallback: eski _wc_additional_variation_images meta
    if ( count( $images ) <= 1 ) {
        $additional = get_post_meta( $product_id, '_wc_additional_variation_images', true );
        if ( $additional ) {
            foreach ( array_filter( explode(',', $additional) ) as $add_id ) {
                $add_id = (int) trim($add_id);
                if ( $add_id ) {
                    $url = wp_get_attachment_image_url( $add_id, $size );
                    if ( $url && ! in_array($url, $images) ) $images[] = $url;
                }
            }
        }
    }

    // Hiç görsel yoksa parent'ın ana görselini kullan
    if ( empty( $images ) ) {
        $parent = wc_get_product( $p->get_parent_id() );
        if ( $parent ) {
            $pid = $parent->get_image_id();
            if ( $pid ) {
                $url = wp_get_attachment_image_url( $pid, $size );
                if ( $url ) $images[] = $url;
            }
        }
    }

} elseif ( $p->is_type('variable') ) {
    // Variable parent: sadece ana ürün görseli + galeri
    // (Variation seçilmemiş durumu — clear basıldığında)
    $pid = $p->get_image_id();
    if ( $pid ) {
        $url = wp_get_attachment_image_url( $pid, $size );
        if ( $url ) $images[] = $url;
    }
    foreach ( $p->get_gallery_image_ids() as $gid ) {
        $url = wp_get_attachment_image_url( $gid, $size );
        if ( $url ) $images[] = $url;
    }
    // Galeri boşsa default variation görsellerini kullan
    if ( empty( $images ) ) {
        $seen = [];
        foreach ( $p->get_children() as $child_id ) {
            $child    = wc_get_product( $child_id );
            if ( ! $child ) continue;
            $image_id = $child->get_image_id();
            if ( $image_id && ! isset($seen[$image_id]) ) {
                $url = wp_get_attachment_image_url( $image_id, $size );
                if ( $url ) { $images[] = $url; $seen[$image_id] = true; }
            }
        }
    }

} else {
    // Simple: ana görsel + galeri
    $pid = $p->get_image_id();
    if ( $pid ) {
        $url = wp_get_attachment_image_url( $pid, $size );
        if ( $url ) $images[] = $url;
    }
    foreach ( $p->get_gallery_image_ids() as $gid ) {
        $url = wp_get_attachment_image_url( $gid, $size );
        if ( $url ) $images[] = $url;
    }
}

// Thumbnail ve zoom görselleri
$zoom_images  = [];
foreach ( $images as $img_url ) {
    $att_id = attachment_url_to_postid( $img_url );
    if ( $att_id ) {
        $thumb_url     = wp_get_attachment_image_url( $att_id, 'woocommerce_thumbnail' );
        $thumb_images[] = $thumb_url ?: $img_url;
        $zoom_url      = wp_get_attachment_image_url( $att_id, 'full' );
        $zoom_images[]  = $zoom_url ?: $img_url;
    } else {
        $thumb_images[] = $img_url;
        $zoom_images[]  = $img_url;
    }
}

$response['data'] = [
    'images'       => $images,
    'thumb_images' => $thumb_images,
    'zoom_images'  => $zoom_images,
    'count'        => count( $images ),
];

echo json_encode( $response );
wp_die();
