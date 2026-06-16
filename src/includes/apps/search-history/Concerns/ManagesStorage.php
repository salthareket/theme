<?php

/**
 * ManagesStorage — SearchHistory Kullanıcı Geçmişi Depolama Trait
 *
 * Arama terimlerini user_meta (login) ve cookie (guest) ile saklar.
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.0.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrıldı
 *   - Fix: add_to_cookie() — wp_json_encode + is_ssl() + httponly flag
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sınıfı içinde `use ManagesStorage;` ile kullanılır.
 *   TracksSearches trait'i tarafından otomatik çağrılır.
 *
 * @example Login kullanıcı geçmişi:
 *   // Otomatik — set_term() / auto_track_search() içinde çağrılır
 *   // Manuel okuma:
 *   $terms = $sh->get_user_terms(0, 'search', 5);
 *
 * @example Guest cookie geçmişi:
 *   // Otomatik — set_term() / auto_track_search() içinde çağrılır
 *   // Cookie adı: wp_search_terms (JSON array, 30 gün, max 20 terim)
 */
trait ManagesStorage {

    /**
     * Arama terimini kullanıcı meta'sına ekle.
     * Max 50 terim saklar, duplicate'leri atlar.
     *
     * @param  string $term
     */
    private function add_to_user_meta( string $term ): void {
        $user_id = (int) get_current_user_id();
        if ( $user_id < 1 ) return;

        $terms = get_user_meta( $user_id, 'search_terms', true );
        if ( ! is_array( $terms ) ) $terms = [];

        if ( in_array( $term, $terms, true ) ) return;

        $terms[] = $term;
        if ( count( $terms ) > 50 ) $terms = array_slice( $terms, -50 );

        update_user_meta( $user_id, 'search_terms', $terms );
    }

    /**
     * Arama terimini cookie'ye ekle.
     * Max 20 terim, 30 gün, httponly.
     *
     * @param  string $term
     */
    private function add_to_cookie( string $term ): void {
        if ( headers_sent() ) return;

        $cookie_name = 'wp_search_terms';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $raw   = isset( $_COOKIE[ $cookie_name ] ) ? stripslashes( $_COOKIE[ $cookie_name ] ) : '';
        $terms = [];

        if ( '' !== $raw ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) $terms = $decoded;
        }

        if ( in_array( $term, $terms, true ) ) return;

        $terms[] = $term;
        if ( count( $terms ) > 20 ) $terms = array_slice( $terms, -20 );

        setcookie(
            $cookie_name,
            (string) wp_json_encode( $terms ),
            time() + ( 86400 * 30 ),
            '/',
            '',
            is_ssl(),
            true // httponly
        );
    }

    /**
     * Cookie'den arama terimlerini al.
     *
     * @param  int $count Kaç adet
     * @return string[]
     */
    private function get_cookie_terms( int $count ): array {
        $cookie_name = 'wp_search_terms';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $raw = isset( $_COOKIE[ $cookie_name ] ) ? stripslashes( $_COOKIE[ $cookie_name ] ) : '';

        if ( '' === $raw ) return [];

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) return [];

        return array_slice( $decoded, -$count );
    }
}
