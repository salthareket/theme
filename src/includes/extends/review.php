<?php

/**
 * Review — Timber\Comment extend'i.
 * WooCommerce review, normal comment ve comment_reply icin kullanilir.
 * object_type her zaman 'comment' — comment_ID ile unique obje hedeflenir.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-05-08
 *     - Add: reaction_count() — comment'in reaction sayisi
 *     - Add: has_reaction() — mevcut kullanici bu comment'e reaction yapti mi
 *     - Add: reaction_button() — reaction button HTML'i render et
 *   1.0.0 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Twig'de:
 * {{ review.reaction_count('like') }}
 * {% if review.has_reaction('like') %}...{% endif %}
 * {{ review.reaction_button('like', {'style': 'icon-count'})|raw }}
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   {{ review.rating }}         → 5
 *
 * @example
 *   {{ review.reaction_count('like') }} kisi faydali buldu
 *
 * @example
 *   {% if review.has_reaction('like') %}Faydali buldunuz{% endif %}
 *
 * @example
 *   {{ review.reaction_button('like', {'style': 'icon-count'})|raw }}
 *
 * @example
 *   {{ review.stars }}          → ★★★★★
 */
class Review extends Timber\Comment {

    public function rating(): int {
        return (int) ( $this->meta( 'rating' ) ?: 0 );
    }

    public function review_title(): string {
        return (string) ( $this->meta( 'comment_title' ) ?: '' );
    }

    public function image_id(): int {
        return (int) ( $this->meta( 'comment_image' ) ?: 0 );
    }

    public function image_url( string $size = 'medium_large' ): string {
        $id = $this->image_id();
        return $id > 0 ? (string) ( wp_get_attachment_image_url( $id, $size ) ?: '' ) : '';
    }

    public function profile_id(): int {
        return (int) ( $this->meta( 'comment_profile' ) ?: 0 );
    }

    public function stars(): string {
        if ( ! class_exists( 'Reviews' ) ) return '';
        return Reviews::stars( $this->rating() );
    }

    public function stars_emoji(): string {
        if ( ! class_exists( 'Reviews' ) ) return '';
        return Reviews::stars( $this->rating(), 'emoji' );
    }

    public function is_approved(): bool {
        return (int) $this->comment_approved === 1;
    }

    public function author_user(): ?\Timber\User {
        $uid = (int) $this->user_id;
        if ( $uid < 1 ) return null;
        return class_exists( 'User' ) ? new User( $uid ) : \Timber::get_user( $uid );
    }

    /**
     * Review yapılan kullanıcı (user bazlı review'larda).
     */
    public function target_user(): ?\Timber\User {
        $pid = $this->profile_id();
        if ( $pid < 1 ) return null;
        return class_exists( 'User' ) ? new User( $pid ) : \Timber::get_user( $pid );
    }

    // ── REACTIONS ────────────────────────────────────────────────────────────

    /**
     * Bu comment/review'in belirli bir reaction sayisi.
     * Twig: {{ review.reaction_count('like') }}
     */
    public function reaction_count( string $type = 'like' ): int {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return 0;
        return \SaltHareket\Reactions\Reactions::count( $type, $this->comment_ID, 'comment' );
    }

    /**
     * Mevcut kullanici bu comment'e reaction yapti mi?
     * Twig: {% if review.has_reaction('like') %}
     */
    public function has_reaction( string $type = 'like' ): bool {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return false;
        return \SaltHareket\Reactions\Reactions::has( $type, $this->comment_ID, 'comment' );
    }

    /**
     * Reaction button HTML'i render et.
     * Twig: {{ review.reaction_button('like', {'style': 'icon-count'}) }}
     */
    public function reaction_button( string $type = 'like', array $options = [] ): string {
        if ( ! class_exists( \SaltHareket\Reactions\Admin\ReactionsAjax::class ) ) return '';
        return \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $this->comment_ID, 'comment', $type, $options );
    }

    // ── END REACTIONS ─────────────────────────────────────────────────────────
}
