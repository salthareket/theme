<?php
class FeaturedImage {

    Private $post_id = 0;

    public function __construct() {
        add_action('save_post', [$this, 'setFeaturedImageForPost'], 20);
        add_action('edited_terms', [$this, 'setFeaturedImageForTerm'], 20, 2);
    }

    public function setFeaturedImageForPost($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!class_exists("ACF")) {
            return;
        }

        $this->post_id = $post_id;

        $media_field = get_field('media', $post_id);
        error_log(print_r($media_field, true));
        if (!$media_field || !isset($media_field['media_type'])) {
            return;
        }

        $featured_image_id = $this->determineFeaturedImage($media_field);

        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        } else {
            delete_post_thumbnail($post_id); // Uygun görsel bulunamadıysa Featured Image'ı kaldır
        }
    }

    public function setFeaturedImageForTerm($term_id, $taxonomy) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!class_exists("ACF")) {
            return;
        }
        
        $media_field = get_field('media', 'term_' . $term_id);
        if (!$media_field || !isset($media_field['media_type'])) {
            return;
        }

        $featured_image_id = $this->determineFeaturedImage($media_field);

        if ($featured_image_id) {
            update_term_meta($term_id, '_thumbnail_id', $featured_image_id);
        } else {
            delete_term_meta($term_id, '_thumbnail_id'); // Uygun görsel bulunamadıysa Featured Image'ı kaldır
        }
    }

    private function determineFeaturedImage($media_field) {
        $featured_image_id = null;

        switch ($media_field['media_type']) {
            case 'image':
                $featured_image_id = $this->getImageField($media_field);
                break;

            case 'gallery':
                $featured_image_id = $this->getGalleryField($media_field);
                break;
        }

        return $featured_image_id;
    }

    private function getImageField($media_field) {
        if (!empty($media_field['image'])) {
            return $media_field['image'];
        }

        if (!empty($media_field['use_responsive_image']) && !empty($media_field['image_responsive']['url'])) {
            return attachment_url_to_postid($media_field['image_responsive']['url']);
        }

        return null;
    }

    private function getGalleryField($media_field) {
        if (!empty($media_field['gallery'])) {
            return $media_field['gallery'][0]; // İlk görseli al
        }

        if (!empty($media_field['video_gallery'])) {
            // İlk item için öncelik kontrolü
            $first_item = $media_field['video_gallery'][0];
            if ($first_item['type'] === 'embed' && !empty($first_item['url'])) {
                $poster_image_id = $this->getPosterFrameFromEmbed($first_item['url']);
                if ($poster_image_id) {
                    return $poster_image_id;
                }
            } elseif ($first_item['type'] === 'file' && !empty($first_item['image'])) {
                return $first_item['image'];
            }

            // Sonraki item'ları sırasıyla kontrol et
            foreach ($media_field['video_gallery'] as $item) {
                if ($item['type'] === 'embed' && !empty($item['url'])) {
                    $poster_image_id = $this->getPosterFrameFromEmbed($item['url']);
                    if ($poster_image_id) {
                        return $poster_image_id;
                    }
                } elseif ($item['type'] === 'file' && !empty($item['image'])) {
                    return $item['image'];
                }
            }
        }

        return null; // Hiçbir uygun görsel yoksa
    }

    private function getPosterFrameFromEmbed($embed_url = "", $post_id = 0) {
        $image_url = get_video_thumbnail_uri($embed_url);
        error_log("poster:" . $image_url);
        if($image_url){
            $file_name = md5($image_url);
            return featured_image_from_url($image_url, $this->post_id, false, $file_name);
        }
        return null;
        
    }
}

// Sınıfı başlat
new FeaturedImage();
