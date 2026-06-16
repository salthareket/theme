<?php

/**
 * Reviews Bootstrap
 * Tüm review sınıflarını yükler, admin ve AJAX handler'larını register eder.
 *
 * variables.php'de şu şekilde include edilir:
 *   include_once SH_INCLUDES_PATH . 'reviews/bootstrap.php';
 *
 * @version 1.0.0
 */

$reviews_base = __DIR__ . '/';

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

require_once $reviews_base . 'Concerns/ManagesReviews.php';
require_once $reviews_base . 'Concerns/QueriesReviews.php';
require_once $reviews_base . 'Concerns/CalculatesRating.php';
require_once $reviews_base . 'Concerns/ChecksPermissions.php';
require_once $reviews_base . 'Concerns/HandlesNotifications.php';
require_once $reviews_base . 'Concerns/HandlesMedia.php';
require_once $reviews_base . 'Concerns/HandlesVotes.php';
require_once $reviews_base . 'ReviewsSettings.php';
require_once $reviews_base . 'SaltComment.php';
require_once $reviews_base . 'Reviews.php';
require_once $reviews_base . 'Admin/ReviewsAdmin.php';
require_once $reviews_base . 'Admin/ReviewsAjax.php';

// ─── GLOBAL ALIAS — geriye uyumluluk ─────────────────────────────────────────
// Eski kod `new Reviews()` veya `Reviews::rating()` kullanıyorsa çalışmaya devam eder.

if ( ! class_exists( 'Reviews' ) ) {
    class_alias( \SaltHareket\Reviews\Reviews::class, 'Reviews' );
}

// ─── INIT ────────────────────────────────────────────────────────────────────

\SaltHareket\Reviews\Admin\ReviewsAdmin::register();
\SaltHareket\Reviews\Admin\ReviewsAjax::register();

// ─── WP HOOKS ────────────────────────────────────────────────────────────────

// Post silinince review'ları temizle
add_action( 'before_delete_post', function ( int $post_id ) {
    \SaltHareket\Reviews\Reviews::deleteForPost( $post_id );
} );

// Kullanıcı silinince review'ları temizle
add_action( 'delete_user', function ( int $user_id ) {
    \SaltHareket\Reviews\Reviews::deleteForUser( $user_id );
} );

// WooCommerce: satın alma verified flag için default filter
add_filter( 'reviews/check_verified', function ( bool $verified, int $user_id, int $target_id, string $type ): bool {
    if ( $type === 'post' && function_exists( 'wc_customer_bought_product' ) ) {
        return wc_customer_bought_product( '', $user_id, $target_id );
    }
    return $verified;
}, 10, 4 );
