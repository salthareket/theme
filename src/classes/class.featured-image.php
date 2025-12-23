<?php
class FeaturedImage {

    private $post_id = 0;

    public function __construct() {
        add_action('acf/save_post', [$this, 'setFeaturedImageForPost'], 20);
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

        // ✅ Hiçbir data yoksa direkt çık
        if (empty($media_field) || !is_array($media_field)) {
            return;
        }

        // ✅ Eğer hem image, hem gallery, hem video boşsa → işlem yapma
        if (
            empty($media_field['image']) &&
            empty($media_field['gallery']) &&
            empty($media_field['video_gallery'])
        ) {
            return;
        }

        $featured_image_id = $this->determineFeaturedImage($media_field);

        if ($featured_image_id) {
            if (is_array($featured_image_id) && isset($featured_image_id["ID"])) {
                $featured_image_id = $featured_image_id["ID"];
            }
            set_post_thumbnail($post_id, $featured_image_id);
        } else {
            // ✅ Sadece gerçekten media eklenmişti ama uygun görsel bulunamadıysa sil
            delete_post_thumbnail($post_id);
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

        if (empty($media_field) || !is_array($media_field)) {
            return;
        }

        if (
            empty($media_field['image']) &&
            empty($media_field['gallery']) &&
            empty($media_field['video_gallery'])
        ) {
            return;
        }

        $featured_image_id = $this->determineFeaturedImage($media_field);
        if ($featured_image_id) {
            update_term_meta($term_id, '_thumbnail_id', $featured_image_id);
        } else {
            delete_term_meta($term_id, '_thumbnail_id');
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

    /*private function getImageField($media_field) {
        if (!empty($media_field['image'])) {
            return $media_field['image'];
        }

        if (!empty($media_field['use_responsive_image']) && !empty($media_field['image_responsive']['url'])) {
            return attachment_url_to_postid($media_field['image_responsive']['url']);
        }

        return null;
    }*/

    private function getImageField($media_field) {
        // 1. Ana Görsel Kontrolü
        if (!empty($media_field['image'])) {
            
            // Burayı kontrol et: Gelen değer array ise ve ID içeriyorsa sadece ID'yi döndür.
            if (is_array($media_field['image']) && isset($media_field['image']['ID'])) {
                return $media_field['image']['ID']; // ARTIK SADECE ID DÖNDÜRÜLÜYOR
            }
            
            // Eğer Image Return Format ID ise, zaten direkt ID dönecektir.
            return $media_field['image'];
        }

        // 2. Responsive Görsel Kontrolü (Zaten ID'yi döndürüyor, doğru)
        if (!empty($media_field['use_responsive_image']) && !empty($media_field['image_responsive']['url'])) {
            return attachment_url_to_postid($media_field['image_responsive']['url']);
        }

        return null;
    }

    private function getGalleryField($media_field) {
        /*if (!empty($media_field['gallery'])) {
            return $media_field['gallery'][0]; // İlk görseli al
        }*/
        if (!empty($media_field['gallery'])) {
            $first_item = $media_field['gallery'][0]; 

            if (is_array($first_item) && isset($first_item['ID'])) {
                return $first_item['ID']; // Galeri nesnesinin ID'sini döndür
            }

            return $first_item; // ID olarak ayarlıysa ID'yi döndür
        }

        if (!empty($media_field['video_gallery'])) {
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

        return null;
    }

    private function getPosterFrameFromEmbed($embed_url = "", $post_id = 0) {
        $embed = new OembedVideo($embed_url);
        $embed_data = $embed->get();
        $image_url = $embed_data["src"];
        if (!empty($image_url)) {
            $file_name = md5($image_url);
            return featured_image_from_url($image_url, $this->post_id, false, $file_name);
        }
        return null;
    }
}

new FeaturedImage();
