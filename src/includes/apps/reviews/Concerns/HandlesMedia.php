<?php

namespace SaltHareket\Reviews\Concerns;

/**
 * HandlesMedia
 * Review medya yönetimi — çoklu image upload, attachment.
 *
 * @version 1.0.0
 */
trait HandlesMedia
{
    /**
     * Review'a medya ekle / güncelle.
     *
     * @param  int[]  $attachment_ids  WP attachment ID'leri
     *
     * @example
     *   Reviews::setMedia(99, [123, 456, 789]);
     */
    public static function setMedia( int $comment_id, array $attachment_ids ): void
    {
        $ids = array_unique( array_filter( array_map( 'intval', $attachment_ids ) ) );
        update_comment_meta( $comment_id, 'comment_images', $ids );

        // İlk resmi primary olarak da kaydet (geriye uyumluluk)
        if ( ! empty( $ids ) ) {
            update_comment_meta( $comment_id, 'comment_image', $ids[0] );
        }
    }

    /**
     * Review'ın medyalarını getir.
     * @return array{id: int, url: string, thumb: string}[]
     */
    public static function getMedia( int $comment_id ): array
    {
        $ids = (array) get_comment_meta( $comment_id, 'comment_images', true );
        if ( empty( $ids ) ) {
            // Geriye uyumluluk — eski tek image
            $single = (int) get_comment_meta( $comment_id, 'comment_image', true );
            if ( $single > 0 ) $ids = [ $single ];
        }

        $media = [];
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $id < 1 ) continue;

            $full  = wp_get_attachment_image_url( $id, 'full' );
            $thumb = wp_get_attachment_image_url( $id, 'thumbnail' );

            if ( $full ) {
                $media[] = [
                    'id'    => $id,
                    'url'   => $full,
                    'thumb' => $thumb ?: $full,
                    'alt'   => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: '',
                ];
            }
        }

        return $media;
    }

    /**
     * $_FILES'tan review medyası yükle.
     * Maksimum dosya sayısı ve boyutu filter ile ayarlanabilir.
     *
     * @return array{success: bool, ids: int[], errors: string[]}
     *
     * @example
     *   $result = Reviews::uploadMedia($_FILES['review_images'], get_current_user_id());
     *   if ($result['success']) {
     *       Reviews::setMedia($comment_id, $result['ids']);
     *   }
     */
    public static function uploadMedia( array $files, int $user_id ): array
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $max_files = (int) apply_filters( 'reviews/max_media', 5 );
        $max_size  = (int) apply_filters( 'reviews/max_media_size', 5 * MB_IN_BYTES );
        $allowed   = (array) apply_filters( 'reviews/allowed_media_types', [ 'image/jpeg', 'image/png', 'image/webp' ] );

        $ids    = [];
        $errors = [];

        // Normalize $_FILES array (multiple files)
        $normalized = [];
        if ( isset( $files['name'] ) && is_array( $files['name'] ) ) {
            foreach ( $files['name'] as $i => $name ) {
                if ( empty( $name ) ) continue;
                $normalized[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
        } elseif ( isset( $files['name'] ) ) {
            $normalized[] = $files;
        }

        $normalized = array_slice( $normalized, 0, $max_files );

        foreach ( $normalized as $file ) {
            if ( $file['error'] !== UPLOAD_ERR_OK ) {
                $errors[] = trans( 'Dosya yüklenemedi.' );
                continue;
            }
            if ( $file['size'] > $max_size ) {
                $errors[] = sprintf( trans( '%s dosyası çok büyük.' ), $file['name'] );
                continue;
            }
            if ( ! in_array( $file['type'], $allowed, true ) ) {
                $errors[] = sprintf( trans( '%s dosya türü desteklenmiyor.' ), $file['name'] );
                continue;
            }

            $_FILES['review_upload'] = $file;
            $attach_id = media_handle_upload( 'review_upload', 0, [
                'post_author' => $user_id,
            ] );

            if ( is_wp_error( $attach_id ) ) {
                $errors[] = $attach_id->get_error_message();
            } else {
                $ids[] = $attach_id;
            }
        }

        return [
            'success' => ! empty( $ids ),
            'ids'     => $ids,
            'errors'  => $errors,
        ];
    }
}
