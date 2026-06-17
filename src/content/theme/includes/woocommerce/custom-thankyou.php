<?php

/**
 * Custom Thank You / Order Received
 *
 * WooCommerce'in standart order-received endpoint'ini kullanır.
 * Statik slug yerine WC option'larından dinamik olarak sayfa bilgisi alınır.
 * page.php → page-{slug}.twig pattern'i ile Timber render edilir.
 *
 * @version 2.1.0
 *
 * @changelog
 *   2.1.0 - 2026-05-01
 *     - Refactor: the_content filter kaldırıldı
 *       page.php'nin page-{slug}.twig pattern'i kullanılıyor
 *       timber/context filter ile order context inject ediliyor
 *   2.0.0 - 2026-05-01
 *     - Refactor: Timber tabanlı render, dinamik sayfa ID
 *   1.0.0 - İlk versiyon
 */

// ─── 1. Redirect: checkout/order-received → order-ok sayfası ────────────────
add_action('template_redirect', function() {
    global $wp;

    $endpoint = get_option('woocommerce_checkout_order_received_endpoint', 'order-received');

    if (!is_checkout() || empty($wp->query_vars[$endpoint])) {
        return;
    }

    $order_id  = absint($wp->query_vars[$endpoint]);
    $order_key = wc_clean(wp_unslash($_GET['key'] ?? ''));

    if (!$order_id || !$order_key) return;

    $page_id = (int) get_option('woocommerce_order_received_page_id');
    if (!$page_id) return;

    $redirect = add_query_arg([
        'order' => $order_id,
        'key'   => $order_key,
    ], get_permalink($page_id));

    wp_safe_redirect($redirect);
    exit;
});

// ─── 2. Order context'ini Timber'a inject et ─────────────────────────────────
// page.php → page-order-ok.twig render ederken bu context kullanılır
add_filter('timber/context', function($context) {
    $page_id = (int) get_option('woocommerce_order_received_page_id');
    if (!$page_id || !is_page($page_id)) {
        return $context;
    }

    $order_id  = absint($_GET['order'] ?? 0);
    $order_key = wc_clean(wp_unslash($_GET['key'] ?? ''));
    $is_pae_fetch = !empty($_SERVER['HTTP_X_INTERNAL_FETCH']) || isset($_GET['fetch']);

    // PAE fetch: fake order data ile CSS class'larını yakala
    if ($is_pae_fetch && (!$order_id || !$order_key)) {
        // En son siparişi bul — fake data olarak kullan
        $recent_orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        if (!empty($recent_orders)) {
            $order = $recent_orders[0];
            $items = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $items[] = [
                    'name'  => $item->get_name(),
                    'qty'   => $item->get_quantity(),
                    'total' => wc_price($item->get_total()),
                    'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') : '',
                    'link'  => $product ? get_permalink($product->get_id()) : '',
                ];
            }
            $context['order']        = $order;
            $context['is_failed']    = false;
            $context['is_logged_in'] = true; // logged state CSS'i için
            $context['items']        = $items;
            $context['shop_url']     = wc_get_page_permalink('shop');
            $context['account_url']  = wc_get_page_permalink('myaccount');
            $context['orders_url']   = wc_get_account_endpoint_url('orders');
        }
        return $context;
    }

    if (!$order_id || !$order_key) {
        return $context;
    }

    $order = wc_get_order($order_id);

    if (!$order || $order->get_order_key() !== $order_key) {
        return $context;
    }

    // Güvenlik: sipariş sahibi mi?
    $customer_id = $order->get_customer_id();

    // PAE internal fetch ise güvenlik kontrolünü atla — CSS class'larının yakalanması için
    $is_pae_fetch = !empty($_SERVER['HTTP_X_INTERNAL_FETCH']) || isset($_GET['fetch']);

    if (!$is_pae_fetch && $customer_id > 0) {
        // Kayıtlı kullanıcının siparişi — login zorunlu
        if (!is_user_logged_in()) {
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
        // Başka kullanıcının siparişi
        if (get_current_user_id() !== $customer_id) {
            wp_safe_redirect(home_url());
            exit;
        }
    }
    // $customer_id === 0 → misafir siparişi — order_key yeterli güvenlik

    // Order items
    $items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $items[] = [
            'name'  => $item->get_name(),
            'qty'   => $item->get_quantity(),
            'total' => wc_price($item->get_total()),
            'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') : '',
            'link'  => $product ? get_permalink($product->get_id()) : '',
        ];
    }

    $context['order']      = $order;
    $context['is_failed']  = $order->has_status('failed');
    $context['is_logged_in'] = is_user_logged_in();
    $context['items']      = $items;
    $context['shop_url']   = wc_get_page_permalink('shop');
    $context['account_url'] = wc_get_page_permalink('myaccount');
    $context['orders_url'] = wc_get_account_endpoint_url('orders');

    return $context;
});

// ─── 3. Order-received sayfasında woo/pages/order-received.twig kullan ───────
add_filter('template_include', function($template) {
    $page_id = (int) get_option('woocommerce_order_received_page_id');
    if (!$page_id || !is_page($page_id)) {
        return $template;
    }

    // order ve key parametresi yoksa — yetkisiz erişim, login sayfasına yönlendir
    $order_id  = absint($_GET['order'] ?? 0);
    $order_key = wc_clean(wp_unslash($_GET['key'] ?? ''));
    $is_pae_fetch = !empty($_SERVER['HTTP_X_INTERNAL_FETCH']) || isset($_GET['fetch']);
    if (!$order_id || !$order_key) {
        if ($is_pae_fetch) return $template; // PAE fetch — boş sayfa dönsün, redirect yapma
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    // Tema klasöründe özel template var mı?
    $custom = locate_template('woocommerce/order-received.php');
    return $custom ?: $template;
});

