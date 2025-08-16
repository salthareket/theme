<?php
/**
 * Admin Listelerine Thumbnail Sütunu Ekleyen Sınıf
 *
 * Bu sınıf, WordPress admin panelindeki tüm yazı tipleri ve taksonomi listelerine
 * otomatik olarak bir "Thumbnail" (küçük resim) sütunu ekler.
 * Kod, gelecekte eklenecek yeni yazı tipleri veya taksonomiler için de
 * değişiklik gerektirmeden çalışacak şekilde dinamik olarak tasarlanmıştır.
 */
class AdminThumbnailColumns {

    /**
     * Sınıf başlatıldığında tüm kancaları (hooks) ayarlar.
     */
    public function __construct() {
        
        // Tüm halka açık yazı tiplerini al ve her biri için kancaları ekle
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", [$this, 'add_thumbnail_column']);
            add_action("manage_{$post_type}_posts_custom_column", [$this, 'render_post_thumbnail_column'], 10, 2);
        }

        // Tüm halka açık taksonomileri al ve her biri için kancaları ekle
        $taxonomies = get_taxonomies(['public' => true], 'names');
        foreach ($taxonomies as $taxonomy) {
            add_filter("manage_edit-{$taxonomy}_columns", [$this, 'add_thumbnail_column']);
            add_filter("manage_{$taxonomy}_custom_column", [$this, 'render_term_thumbnail_column'], 10, 3);
        }
        
        // Admin paneline sütun genişliği için CSS ekle
        add_action('admin_head', [$this, 'add_admin_styles']);
    }

    /**
     * Listeleme tablosuna 'Thumbnail' sütununu ekler.
     * Sütunu her zaman checkbox'tan hemen sonra yerleştirir.
     *
     * @param array $columns Mevcut sütunlar.
     * @return array Güncellenmiş sütunlar.
     */
    public function add_thumbnail_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'cb') { // Checkbox sütunundan sonra
                $new_columns['thumbnail'] = __('Thumbnail', 'textdomain');
            }
        }
        return $new_columns;
    }

    /**
     * Yazı tipleri listesindeki 'Thumbnail' sütununun içeriğini oluşturur.
     *
     * @param string $column_name Sütun adı.
     * @param int    $post_id     Yazı ID'si.
     */
    public function render_post_thumbnail_column($column_name, $post_id) {
        if ($column_name === 'thumbnail') {
            if (has_post_thumbnail($post_id)) {
                // Öne çıkan görseli 'thumbnail' boyutunda al ve göster
                echo get_the_post_thumbnail($post_id, 'thumbnail', [
                    'class' => '',
                    'style' => '',
                    'alt'   => 'image',
                    'loading' => 'lazy',
                ]);
            } else {
                // Görsel yoksa bir tire işareti göster
                echo '<span style="opacity: 0.5;">—</span>';
            }
        }
    }

    /**
     * Taksonomi (kategori, etiket vb.) listesindeki 'Thumbnail' sütununun içeriğini oluşturur.
     *
     * @param string $content     Mevcut içerik (boş).
     * @param string $column_name Sütun adı.
     * @param int    $term_id     Terim ID'si.
     * @return string Sütun için oluşturulan HTML içeriği.
     */
    public function render_term_thumbnail_column($content, $column_name, $term_id) {
        if ($column_name === 'thumbnail') {
            // ACF gibi eklentilerle eklenen 'thumbnail_id' meta alanını kontrol et
            $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                // Görsel varsa 'thumbnail' boyutunda al ve göster
                $image = wp_get_attachment_image($thumbnail_id, 'thumbnail', false, [
                    'class' => '',
                    'style' => '',
                    'alt'   => 'image',
                    'loading' => 'lazy',
                ]);
                return $image;
            } else {
                // Görsel yoksa bir tire işareti göster
                return '<span style="opacity: 0.5;">—</span>';
            }
        }
        return $content;
    }
    
    /**
     * Admin paneline thumbnail sütununun genişliğini ayarlamak için CSS ekler.
     */
    public function add_admin_styles() {
        echo '<style>
            .column-thumbnail { width: 90px; text-align: center; }
            .column-thumbnail img { max-width: 60px; height: auto; border-radius: 8px;border: 2px solid #fff; box-shadow: rgba(0,0,0,.15) 0px 0px 15px;}
        </style>';
    }
}