<?php
/**
 * FeaturedImage
 * Auto-sets featured image from ACF 'media' field group on post/term save.
 * Supports: image, gallery, video_gallery (poster frame extraction).
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   new FeaturedImage();
 *   // Hook'lar otomatik register edilir (acf/save_post, edited_term).
 *   // Post veya term kaydedildiğinde ACF 'media' field grubundan
 *   // otomatik olarak featured image set edilir.
 */
class FeaturedImage {

    private $current_object_id = 0;

    public function __construct() {
        add_action('acf/save_post', [$this, 'setFeaturedImageForPost'], 20);
        add_action('edited_term', [$this, 'setFeaturedImageForTerm'], 20, 3);
    }

    public function setFeaturedImageForPost($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!class_exists("ACF")) return;

        $this->current_object_id = $post_id;
        $media_field = get_field('media', $post_id);

        if (!$this->has_media_data($media_field)) return;

        $featured_image_id = $this->determineFeaturedImage($media_field);
        $featured_image_id = $this->normalize_image_id($featured_image_id);

        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        } else {
            delete_post_thumbnail($post_id);
        }
    }

    public function setFeaturedImageForTerm($term_id, $tt_id = 0, $taxonomy = '') {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!class_exists("ACF")) return;

        $this->current_object_id = $term_id;
        $media_field = get_field('media', 'term_' . $term_id);

        if (!$this->has_media_data($media_field)) return;

        $featured_image_id = $this->determineFeaturedImage($media_field);
        $featured_image_id = $this->normalize_image_id($featured_image_id);

        if ($featured_image_id) {
            update_term_meta($term_id, '_thumbnail_id', $featured_image_id);
        } else {
            delete_term_meta($term_id, '_thumbnail_id');
        }
    }

    /**
     * Check if media field has any usable data
     */
    private function has_media_data($media_field) {
        if (empty($media_field) || !is_array($media_field)) return false;
        return !empty($media_field['image'])
            || !empty($media_field['gallery'])
            || !empty($media_field['video_gallery']);
    }

    /**
     * Normalize any image value to integer ID
     * Handles: int, numeric string, array with ID key
     */
    private function normalize_image_id($value) {
        if (empty($value)) return 0;
        if (is_array($value) && isset($value['ID'])) return (int) $value['ID'];
        if (is_numeric($value)) return (int) $value;
        return 0;
    }

    /**
     * Determine featured image based on media_type
     */
    private function determineFeaturedImage($media_field) {
        $media_type = $media_field['media_type'] ?? '';

        switch ($media_type) {
            case 'image':
                return $this->getFromImage($media_field);
            case 'gallery':
                return $this->getFromGallery($media_field);
            case 'video':
            case 'video_gallery':
                return $this->getFromVideoGallery($media_field);
            default:
                // Fallback: try image first, then gallery, then video
                return $this->getFromImage($media_field)
                    ?: $this->getFromGallery($media_field)
                    ?: $this->getFromVideoGallery($media_field);
        }
    }

    private function getFromImage($media_field) {
        if (!empty($media_field['image'])) {
            return $media_field['image'];
        }
        if (!empty($media_field['use_responsive_image']) && !empty($media_field['image_responsive']['url'])) {
            return attachment_url_to_postid($media_field['image_responsive']['url']);
        }
        return null;
    }

    private function getFromGallery($media_field) {
        if (!empty($media_field['gallery']) && is_array($media_field['gallery'])) {
            return $media_field['gallery'][0]; // First image — normalize_image_id handles array/int
        }
        // Gallery empty → try video gallery as fallback
        return $this->getFromVideoGallery($media_field);
    }

    private function getFromVideoGallery($media_field) {
        if (empty($media_field['video_gallery']) || !is_array($media_field['video_gallery'])) {
            return null;
        }
        foreach ($media_field['video_gallery'] as $item) {
            if (!is_array($item)) continue;
            $type = $item['type'] ?? '';

            if ($type === 'embed' && !empty($item['url'])) {
                $poster_id = $this->getPosterFrameFromEmbed($item['url']);
                if ($poster_id) return $poster_id;
            } elseif ($type === 'file' && !empty($item['image'])) {
                return $item['image'];
            }
        }
        return null;
    }

    private function getPosterFrameFromEmbed($embed_url) {
        if (!class_exists('OembedVideo')) return null;

        $embed = new OembedVideo($embed_url);
        $embed_data = $embed->get();
        $image_url = $embed_data['src'] ?? '';

        if (empty($image_url) || !function_exists('featured_image_from_url')) return null;

        $file_name = md5($image_url);
        return featured_image_from_url($image_url, $this->current_object_id, false, $file_name);
    }
}

new FeaturedImage();
