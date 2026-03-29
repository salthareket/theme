<?php
/**
 * Admin Listelerine Thumbnail Sütunu Ekleyen Sınıf
 *
 * Tüm public post type ve taxonomy listelerine otomatik thumbnail sütunu ekler.
 * Yeni post type/taxonomy eklendiğinde değişiklik gerektirmez.
 */
class AdminThumbnailColumns {

    private $term_thumb_keys = ['thumbnail_id', 'image', 'thumbnail'];

    public function __construct() {
        // init sonrası register et — tüm CPT ve taxonomy'ler hazır olsun
        add_action('admin_init', [$this, 'register_hooks']);
    }

    public function register_hooks() {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", [$this, 'add_thumbnail_column']);
            add_action("manage_{$post_type}_posts_custom_column", [$this, 'render_post_thumbnail_column'], 10, 2);
        }

        $taxonomies = get_taxonomies(['public' => true], 'names');
        foreach ($taxonomies as $taxonomy) {
            add_filter("manage_edit-{$taxonomy}_columns", [$this, 'add_thumbnail_column']);
            add_filter("manage_{$taxonomy}_custom_column", [$this, 'render_term_thumbnail_column'], 10, 3);
        }

        add_action('admin_head', [$this, 'add_admin_styles']);
    }

    /**
     * Checkbox'tan hemen sonra thumbnail sütunu ekler
     */
    public function add_thumbnail_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'cb') {
                $text_domain = defined('TEXT_DOMAIN') ? TEXT_DOMAIN : 'default';
                $new_columns['thumbnail'] = __('Thumbnail', $text_domain);
            }
        }
        return $new_columns;
    }

    /**
     * Post listesinde thumbnail render
     */
    public function render_post_thumbnail_column($column_name, $post_id) {
        if ($column_name !== 'thumbnail') return;

        if (has_post_thumbnail($post_id)) {
            echo get_the_post_thumbnail($post_id, 'thumbnail', [
                'alt'     => '',
                'loading' => 'lazy',
            ]);
        } else {
            echo '<span class="column-thumbnail-empty">—</span>';
        }
    }

    /**
     * Taxonomy listesinde thumbnail render
     */
    public function render_term_thumbnail_column($content, $column_name, $term_id) {
        if ($column_name !== 'thumbnail') return $content;

        $thumbnail_id = $this->get_term_thumbnail_id($term_id);

        if (!$thumbnail_id) {
            return '<span class="column-thumbnail-empty">—</span>';
        }

        $mime_type = get_post_mime_type($thumbnail_id);
        $attr = [
            'alt'     => '',
            'loading' => 'lazy',
        ];

        if ($mime_type === 'image/svg+xml') {
            $attr['width']  = '50';
            $attr['height'] = '50';
        }

        return wp_get_attachment_image($thumbnail_id, 'thumbnail', false, $attr);
    }

    /**
     * Term'in thumbnail ID'sini birden fazla meta key'den arar
     */
    private function get_term_thumbnail_id($term_id) {
        foreach ($this->term_thumb_keys as $key) {
            $value = get_term_meta($term_id, $key, true);
            if (!empty($value) && is_numeric($value)) {
                return (int) $value;
            }
        }
        // ACF field olarak da dene
        if (function_exists('get_field')) {
            foreach ($this->term_thumb_keys as $key) {
                $value = get_field($key, 'term_' . $term_id);
                if (!empty($value)) {
                    return is_array($value) ? ($value['ID'] ?? 0) : (is_numeric($value) ? (int) $value : 0);
                }
            }
        }
        return 0;
    }

    /**
     * Thumbnail sütunu CSS
     */
    public function add_admin_styles() {
        echo '<style>
            .column-thumbnail { width: 80px; text-align: center; vertical-align: middle; }
            .column-thumbnail img { max-width: 56px; height: auto; border-radius: 6px; border: 2px solid #fff; box-shadow: 0 1px 8px rgba(0,0,0,.1); display: block; margin: 0 auto; }
            .column-thumbnail-empty { opacity: 0.3; font-size: 18px; }
        </style>';
    }
}
