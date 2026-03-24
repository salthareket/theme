<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined('ABSPATH') || exit;

global $product, $wp_query, $woocommerce_loop;

if (!is_a($product, WC_Product::class) || !$product->is_visible()) {
    return;
}

if (empty($woocommerce_loop['loop'])) {
    $woocommerce_loop['loop'] = 0;
}
$woocommerce_loop['loop']++;

// Sayfa numarasını belirle
$page_no = 1;
if (isset($GLOBALS["pagination_page"]) && !empty($GLOBALS["pagination_page"])) {
    $page_no = $GLOBALS["pagination_page"];
} elseif ($wp_query->max_num_pages > 1) {
    $page_no = max(1, get_query_var('paged'));
}

// Querystring session'dan
$querystring = "";
if (isset($_SESSION['query_pagination_vars'][$product->post_type]["querystring"])) {
    $querystring = $_SESSION['query_pagination_vars'][$product->post_type]["querystring"];
}

$product_id = $product->get_id();

// Variable product: URL'deki filter_ parametrelerine göre doğru variation'ı bul
if ($product->get_type() === 'variable') {
    $filter_attributes = [];

    foreach ($_GET as $key => $value) {
        if (strpos($key, 'filter_') === 0) {
            // filter_color → attribute_pa_color formatına çevir
            $attribute_name = 'attribute_pa_' . substr($key, 7);
            $filter_attributes[$attribute_name] = sanitize_text_field($value);
        }
    }

    if (!empty($filter_attributes)) {
        $variation_id = $product->get_matching_variation($filter_attributes);
        if ($variation_id) {
            $product_id = $variation_id;
        }
    }
}
?>
<div class="col" data-page="<?php echo esc_attr($page_no); ?>">
<?php
    Timber::render("woo/tease.twig", [
        "post"        => Timber::get_post($product_id),
        "product"     => $product,
        "page"        => $page_no,
        "index"       => $woocommerce_loop['loop'],
        "querystring" => $querystring,
    ]);
?>
</div>