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

    // Reviews admin sayfası hâlâ erişilebilir olsun.
    // ReviewsAdmin renderPage()'de Reviews class'ını kullanıyor — yüklenmeli.
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
    \SaltHareket\Reviews\Admin\ReviewsAdmin::register();

    return;
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

// ─── REST API ────────────────────────────────────────────────────────────────
require_once $reviews_base . 'rest.php';

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

// ── Ürün sayfasında kullanıcının pending review'unu en üstte göster ────────
add_action( 'sh_product_reviews', function ( int $post_id ) {
    $user_id = get_current_user_id();
    if ( $user_id < 1 ) return;

    // Bu post'a bu kullanıcının yazdığı review'ları çek (tüm status'ler)
    $args = [
        'post_id'    => $post_id,
        'type'       => 'review',
        'user_id'    => $user_id,
        'status'     => 'all',   // onaylı + pending
        'number'     => 1,
        'orderby'    => 'comment_date',
        'order'      => 'DESC',
    ];

    $comments = get_comments( $args );
    if ( empty( $comments ) ) return;

    $review    = $comments[0];
    $approved  = $review->comment_approved === '1';
    $rating    = (int) get_comment_meta( $review->comment_ID, 'rating', true );
    $title     = get_comment_meta( $review->comment_ID, 'review_title', true ) ?: '';
    $verified  = (bool) get_comment_meta( $review->comment_ID, 'verified', true );

    // Sadece pending olanı öne çıkar — onaylı zaten normal listede görünür
    if ( $approved ) {
        // Onaylı review — "View your review" butonu göster (modal açar)
        // Çünkü 100+ yorum varsa kullanıcının yorumu paginate edilmiş olabilir, bulamaz
        $context                 = \Timber\Timber::context();
        $context['review']       = [
            'id'       => (int) $review->comment_ID,
            'content'  => $review->comment_content,
            'rating'   => $rating,
            'title'    => $title,
            'verified' => $verified,
            'approved' => true,
            'date'     => mysql2date( get_option( 'date_format' ), $review->comment_date ),
            'author'   => $review->comment_author,
            'avatar'   => get_avatar_url( $review->comment_author_email, [ 'size' => 48 ] ),
            'post'     => [
                'id'    => $post_id,
                'title' => get_the_title( $post_id ),
                'link'  => get_permalink( $post_id ),
            ],
            'is_owner' => true,
            'is_admin' => current_user_can( 'moderate_comments' ),
        ];

        \Timber\Timber::render( [ 'partials/source/reviews/your-review.twig' ], $context );
        return;
    }

    $context                 = \Timber\Timber::context();
    $context['review']       = [
        'id'       => (int) $review->comment_ID,
        'content'  => $review->comment_content,
        'rating'   => $rating,
        'title'    => $title,
        'verified' => $verified,
        'approved' => false,
        'date'     => mysql2date( get_option( 'date_format' ), $review->comment_date ),
        'author'   => $review->comment_author,
        'avatar'   => get_avatar_url( $review->comment_author_email, [ 'size' => 48 ] ),
        'post'     => [
            'id'    => $post_id,
            'title' => get_the_title( $post_id ),
            'link'  => get_permalink( $post_id ),
        ],
        'is_owner' => true,
        'is_admin' => current_user_can( 'moderate_comments' ),
    ];

    \Timber\Timber::render( [ 'partials/source/reviews/pending-review.twig' ], $context );
} );

// ── WooCommerce rating sync ────────────────────────────────────────────────────
// Bizim Reviews app'i wp_comments tablosuna yazar ama WC'nin shop sayfası
// _wc_average_rating post meta'sından okur. Review approve/create/delete/reject
// olunca bu meta'yı senkronize ediyoruz.

/**
 * Bir product'ın _wc_average_rating ve _wc_review_count meta'larını güncelle.
 * WooCommerce'in kendi `WC_Comments::get_product_review_count()` ve
 * `wc_update_product_hard_reviews_count()` fonksiyonlarına delege eder.
 * Bunlar yoksa (WC devre dışı) manual hesaplar.
 *
 * @param int $post_id  Ürün post ID'si
 */
function sh_sync_wc_product_rating( int $post_id ): void {
    if ( $post_id < 1 ) return;
    if ( get_post_type( $post_id ) !== 'product' ) return;

    global $wpdb;

    // Onaylı review'ların ortalamasını bizim tablodan hesapla
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT COUNT(*) as total,
                AVG(CAST(cm.meta_value AS DECIMAL(3,1))) as average
         FROM {$wpdb->comments} c
         INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID
             AND cm.meta_key = 'rating'
         WHERE c.comment_post_ID = %d
           AND c.comment_type    = 'review'
           AND c.comment_approved = '1'",
        $post_id
    ) );

    $count   = (int)   ( $row->total   ?? 0 );
    $average = round( (float) ( $row->average ?? 0 ), 2 );

    // wp_postmeta güncelle
    update_post_meta( $post_id, '_wc_average_rating', (string) $average );
    update_post_meta( $post_id, '_wc_review_count',   (string) $count );

    // wp_wc_product_meta_lookup güncelle — WC orderby=rating bu tablodan okur
    $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
    $wpdb->update(
        $lookup_table,
        [
            'average_rating' => $average,
            'rating_count'   => $count,
        ],
        [ 'product_id' => $post_id ],
        [ '%f', '%d' ],
        [ '%d' ]
    );

    // WC product object cache'ini temizle
    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients( $post_id );
    }
    wp_cache_delete( 'wc_product_' . $post_id, 'products' );
}

// Yorum status değişince (approve/unapprove/spam/trash) sync et
add_action( 'transition_comment_status', function ( string $new_status, string $old_status, \WP_Comment $comment ) {
    if ( $comment->comment_type !== 'review' ) return;
    $post_id = (int) $comment->comment_post_ID;
    sh_sync_wc_product_rating( $post_id );
}, 10, 3 );

// Yeni review eklenince (create AJAX) sync et
add_action( 'wp_insert_comment', function ( int $comment_id, \WP_Comment $comment ) {
    if ( $comment->comment_type !== 'review' ) return;
    $post_id = (int) $comment->comment_post_ID;
    sh_sync_wc_product_rating( $post_id );
}, 10, 2 );

// Review silinince sync et
add_action( 'deleted_comment', function ( int $comment_id, \WP_Comment $comment ) {
    if ( $comment->comment_type !== 'review' ) return;
    $post_id = (int) $comment->comment_post_ID;
    sh_sync_wc_product_rating( $post_id );
}, 10, 2 );

// ── Extract Translations entegrasyonu ─────────────────────────────────────────
// "Extract Translations" butonu tıklandığında bu filter çalışır.
// DB'deki tüm kriter label'larını translates.json / translates.php'ye ekler.
// Böylece Loco Translate bu string'leri kaynak kodda görmeden de yakalar.
add_filter( 'sh_extract_translations', function( array $strings ): array {
    if ( ! class_exists( '\\SaltHareket\\Reviews\\ReviewsSettings' ) ) return $strings;

    $settings        = \SaltHareket\Reviews\ReviewsSettings::all();
    $criteria_groups = $settings['rating']['criteria'] ?? [];

    // default + post_types + user_roles altındaki tüm kriter label'larını topla
    $collect = [];

    // default kriterleri
    foreach ( (array) ( $criteria_groups['default'] ?? [] ) as $item ) {
        $label = trim( $item['label'] ?? '' );
        if ( $label !== '' ) $collect[] = $label;
    }

    // post type bazlı kriterler
    foreach ( (array) ( $criteria_groups['post_types'] ?? [] ) as $pt_items ) {
        foreach ( (array) $pt_items as $item ) {
            $label = trim( $item['label'] ?? '' );
            if ( $label !== '' ) $collect[] = $label;
        }
    }

    // user role bazlı kriterler
    foreach ( (array) ( $criteria_groups['user_roles'] ?? [] ) as $role_items ) {
        foreach ( (array) $role_items as $item ) {
            $label = trim( $item['label'] ?? '' );
            if ( $label !== '' ) $collect[] = $label;
        }
    }

    // types içindeki criteria'lar
    foreach ( (array) ( $settings['types'] ?? [] ) as $type_def ) {
        foreach ( (array) ( $type_def['criteria'] ?? [] ) as $item ) {
            $label = trim( $item['label'] ?? '' );
            if ( $label !== '' ) $collect[] = $label;
        }
    }

    // Benzersiz yap ve ana listeye ekle
    foreach ( array_unique( $collect ) as $label ) {
        if ( ! in_array( $label, $strings, true ) ) {
            $strings[] = $label;
        }
    }

    return $strings;
} );
