<?php

/**
 * Reviews Bootstrap
 * Tüm review sınıflarını yükler, admin ve AJAX handler'larını register eder.
 *
 * variables.php'de şu şekilde include edilir:
 *   include_once SH_INCLUDES_PATH . 'apps/reviews/bootstrap.php';
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-06-17
 *     - Add: enable_reviews = false → ReviewsDisabler devreye girer
 *       WordPress comment/discussion sistemi komple kapatılır:
 *       menü, admin bar, post listesi sütunu, metabox, post type support,
 *       Discussion settings, REST endpoint, XML-RPC metodları, dashboard widget
 *   1.0.0 — Initial release
 */

$reviews_base = __DIR__ . '/';

// ─── SETTINGS — her zaman yükle (Reviews admin sayfası için gerekli) ──────────

require_once $reviews_base . 'ReviewsSettings.php';

// ─── ENABLE CHECK ─────────────────────────────────────────────────────────────
// enable_reviews = false ise WP comment sistemini komple kapat, app'i yükleme.

$reviews_enabled = (bool) \SaltHareket\Reviews\ReviewsSettings::get( 'general.enable_reviews' );

if ( ! $reviews_enabled ) {
    require_once $reviews_base . 'ReviewsDisabler.php';
    \SaltHareket\Reviews\ReviewsDisabler::register();

    // Reviews admin sayfası hâlâ erişilebilir olsun — sadece "Enable Reviews" toggle için.
    // Tam uygulama (AJAX, hooks) yüklenmez.
    require_once $reviews_base . 'Admin/ReviewsAdmin.php';
    \SaltHareket\Reviews\Admin\ReviewsAdmin::register();

    return; // ← app yüklenmeden çık
}

// ─── AUTOLOAD ────────────────────────────────────────────────────────────────

require_once $reviews_base . 'Concerns/ManagesReviews.php';
require_once $reviews_base . 'Concerns/QueriesReviews.php';
require_once $reviews_base . 'Concerns/CalculatesRating.php';
require_once $reviews_base . 'Concerns/ChecksPermissions.php';
require_once $reviews_base . 'Concerns/HandlesNotifications.php';
require_once $reviews_base . 'Concerns/HandlesMedia.php';
require_once $reviews_base . 'Concerns/HandlesVotes.php';
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
