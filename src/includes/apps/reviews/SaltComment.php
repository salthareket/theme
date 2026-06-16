<?php

namespace SaltHareket\Reviews;

/**
 * SaltComment
 * WP_Comment wrapper — Twig'de meta() ve diğer helper metodları sağlar.
 * Timber v2'de Comment::__construct() protected olduğu için bu wrapper kullanılır.
 *
 * @version 1.0.0
 */
class SaltComment
{
    public int    $ID;
    public int    $comment_ID;
    public int    $comment_post_ID;
    public string $comment_author;
    public string $comment_author_email;
    public string $comment_author_url;
    public string $comment_content;
    public string $comment_type;
    public int    $comment_parent;
    public int    $user_id;
    public string $comment_approved;
    public string $comment_date;
    public string $comment_date_gmt;

    private \WP_Comment $raw;

    public function __construct( \WP_Comment $comment )
    {
        $this->raw                  = $comment;
        $this->ID                   = (int) $comment->comment_ID;
        $this->comment_ID           = (int) $comment->comment_ID;
        $this->comment_post_ID      = (int) $comment->comment_post_ID;
        $this->comment_author       = $comment->comment_author       ?? '';
        $this->comment_author_email = $comment->comment_author_email ?? '';
        $this->comment_author_url   = $comment->comment_author_url   ?? '';
        $this->comment_content      = $comment->comment_content      ?? '';
        $this->comment_type         = $comment->comment_type         ?? '';
        $this->comment_parent       = (int) ( $comment->comment_parent ?? 0 );
        $this->user_id              = (int) ( $comment->user_id ?? 0 );
        $this->comment_approved     = $comment->comment_approved     ?? '0';
        $this->comment_date         = $comment->comment_date         ?? '';
        $this->comment_date_gmt     = $comment->comment_date_gmt     ?? '';
    }

    /**
     * Comment meta oku — Twig'de {{ review.meta('rating') }} şeklinde kullanılır.
     */
    public function meta( string $key ): mixed
    {
        return get_comment_meta( $this->comment_ID, $key, true );
    }

    /**
     * Raw WP_Comment objesi.
     */
    public function raw(): \WP_Comment
    {
        return $this->raw;
    }

    /**
     * Magic getter — WP_Comment property'lerine erişim.
     */
    public function __get( string $name ): mixed
    {
        return $this->raw->$name ?? null;
    }

    /**
     * Magic isset.
     */
    public function __isset( string $name ): bool
    {
        return isset( $this->raw->$name );
    }
}
