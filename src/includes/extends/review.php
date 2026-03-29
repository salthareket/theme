<?php

/**
 * Review — Timber\Comment extend'i.
 *
 * Twig template'lerinde kullanım:
 *   {{ review.rating }}         → 5
 *   {{ review.review_title }}   → "Harika!"
 *   {{ review.stars }}          → ★★★★★
 *   {{ review.stars_emoji }}    → ⭐⭐⭐⭐⭐
 *   {{ review.image_url }}      → https://...
 *   {{ review.profile_id }}     → 456
 *   {{ review.author_user.get_title }} → "Ahmet Yılmaz"
 *   {{ review.is_approved }}    → true
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
        return Reviews::stars( $this->rating() );
    }

    public function stars_emoji(): string {
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
}
