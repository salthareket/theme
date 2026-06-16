<?php

use SaltHareket\Image;

/**
 * Term — Timber\Term extend'i.
 * Kategori, tag ve custom taxonomy terimleri icin.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-05-08
 *     - Add: reaction_count() — term'in reaction sayisi
 *     - Add: has_reaction() — mevcut kullanici bu term'e reaction yapti mi
 *     - Add: reaction_button() — reaction button HTML'i render et
 *   1.0.0 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Twig'de:
 * {{ term.reaction_count('follow') }}
 * {% if term.has_reaction('follow') %}...{% endif %}
 * {{ term.reaction_button('follow', {'style': 'pill'})|raw }}
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   {{ term.reaction_count('follow') }} takipci
 *
 * @example
 *   {% if term.has_reaction('follow') %}Takip Ediliyor{% endif %}
 *
 * @example
 *   {{ term.reaction_button('follow', {'style': 'pill'})|raw }}
 *
 * @example
 *   {{ term.reaction_button('favorite', {'style': 'icon-only'})|raw }}
 *
 * @example
 *   {% set count = term.reaction_count('follow') %}{{ count }} kisi takip ediyor
 */

class Term extends Timber\Term{
    protected $post_count;

    public function thumbnail(){
        return Timber::get_image(get_term_meta($this->term_id, '_thumbnail_id', true));
    }
    public function get_thumbnail(array $args = []){
        $media = $this->meta('media');
        $src   = '';

        if ($media && is_array($media)) {
            $type = $media["media_type"] ?? null;

            if ($type == 'image') {
                if (!empty($media["use_responsive_image"]) && !empty($media["image_responsive"])) {
                    $src = $media["image_responsive"];
                } elseif (!empty($media["image"])) {
                    $src = $media["image"];
                }
            }

            if ($type == 'gallery') {

                if (!empty($media["video_gallery"]) && count($media["video_gallery"]) > 0) {
                    $video = $media["video_gallery"][0];
                    $video_type = $video["type"];
                    $video_poster = $video["image"] ?? "";
                    $video_poster = $video_poster?$video_poster["url"]:"";
                    $video_file = $video["file"] ?? "";
                    $video_url = $video["url"] ?? "";
                    $videoArgs = [
                        "video_type" => $video_type,
                        "video_settings" => [
                            "videoBg" => isset($args["videoBg"]) ? $args["videoBg"] : 1, 
                            "autoplay" => isset($args["autoplay"]) ? $args["autoplay"] : 0, 
                            "loop" => isset($args["loop"]) ? $args["loop"] : 0,
                            "muted" => isset($args["muted"]) ? $args["muted"] : 0,
                            "videoReact" => isset($args["videoReact"]) ? $args["videoReact"] : 0, 
                            "controls" => isset($args["controls"]) ? $args["controls"] : 0, 
                            "controls_options" => [], 
                            "controls_options_settings" => [],
                            "controls_hide" => isset($args["controls"]) ? 1 : 0,
                            "ratio" => "", 
                            "custom_video_image" => "",
                            "video_image" => $video_poster ?? null, 
                            "vtt" => ""
                        ],
                    ];
                    $videoDataKey = ($video_type == "file") ? "video_file" : "video_url";
                    $videoArgs[$videoDataKey] = ($video_type == "file") 
                        ? ["desktop" => $video_file ?? null] 
                        : $video_url ?? null;
                    return get_video([
                        "src" => $videoArgs, "class" => $args["class"]." ", "init" => true, "lazy" => true, "attrs" => []
                    ]);                    
                }
            }
        }

        // 2) Fallback: Timber Term Thumbnail
        if (empty($src)) {
            $src = method_exists($this, 'thumbnail') ? $this->thumbnail() : '';
        }

        $args['src'] = $src;

        // 3) SaltHareket\Image güvenli çağrı
        try {
            $image = new \SaltHareket\Image($args);
            return $image->init();
        } catch (\Throwable $e) {
            //error_log('Term get_thumbnail failed: ' . $e->getMessage());
            return '';
        }
    }

    public function get_thumbnail_urls($size = "thumbnail") {
        $media = $this->meta('media');
        $urls = [];

        if ($media && is_array($media)) {
            $type = $media["media_type"] ?? null;

            // 1) Tekil Görsel
            if ($type == 'image' && !empty($media["image"])) {
                $img = $media["image"];
                // Eğer array geliyorsa (ACF Image Array), istenen size'ı çek, yoksa ana url'i çek
                if (is_array($img)) {
                    $urls[] = $img['sizes'][$size] ?? $img['url'];
                } else {
                    $urls[] = $img;
                }
            }

            // 2) Galeri
            if ($type == 'gallery' && !empty($media["gallery"]) && is_array($media["gallery"])) {
                foreach ($media["gallery"] as $img) {
                    if (is_array($img)) {
                        $urls[] = $img['sizes'][$size] ?? $img['url'];
                    } else {
                        $urls[] = $img;
                    }
                }
            }
            
            // Video varsa (videoda size olmaz, direkt url/file gelir)
            if ($type == 'gallery' && !empty($media["video_gallery"]) && is_array($media["video_gallery"])) {
                foreach ($media["video_gallery"] as $video) {
                    if ($video["type"] == "file" && !empty($video["file"])) {
                        $urls[] = $video["file"];
                    } elseif (!empty($video["url"])) {
                        $urls[] = $video["url"];
                    }
                }
            }
        }

        // 3) Fallback
        if (empty($urls)) {
            $thumb_id = $this->meta('_thumbnail_id') ?: $this->meta('thumbnail_id');
            if ($thumb_id) {
                $img_data = wp_get_attachment_image_src($thumb_id, $size);
                if ($img_data) $urls[] = $img_data[0];
            }
        }

        return array_values(array_filter(array_unique($urls)));
    }
    public function get_thumbnail_url($size = "thumbnail") {
        $urls = $this->get_thumbnail_urls($size);
        return (!empty($urls)) ? reset($urls) : '';
    }


    public function get_field_lang($field = '', $lang = '') {
        if (empty($field)) return null;
        if (empty($lang)) $lang = Data::get('language');

        $value = get_term_meta($this->ID, $field, true);
        if (ENABLE_MULTILANGUAGE === 'qtranslate-xt' && function_exists('qtranxf_use')) {
            return qtranxf_use($lang, $value, false, true);
        }
        return $value;
    }

    public function lang($lang = '') {
        if (ENABLE_MULTILANGUAGE === 'qtranslate-xt' && isset($this->i18n_config['name']['ts'][$lang])) {
            return $this->i18n_config['name']['ts'][$lang];
        }
        return $this->name;
    }

    public function content() {
        $desc = $this->description;
        if (ENABLE_MULTILANGUAGE === 'qtranslate-xt') {
            global $q_config;
            if (empty($desc) && isset($q_config) && !$q_config['hide_untranslated'] && $q_config['show_alternative_content']) {
                $desc = nl2br($this->i18n_config['description']['ts'][$q_config['default_language']] ?? '');
            }
        }
        return $desc;
    }
    public function get_country_post_count( string $post_type = '' ): int {
        if ( ! defined( 'ENABLE_REGIONAL_POSTS' ) || ! ENABLE_REGIONAL_POSTS ) return 1;

        $user_region = \Data::get( 'site_config.user_region' );
        if ( empty( $user_region ) ) return 1; // Region yoksa filtre yok, göster

        // Post type belirtilmemişse regional settings'den bu taxonomy'ye ait post type'ı bul
        if ( empty( $post_type ) ) {
            $post_type = $this->resolvePostTypeForTaxonomy( $this->taxonomy );
        }

        // Hâlâ bulunamadıysa bu taxonomy'nin bağlı olduğu ilk public post type'ı kullan
        if ( empty( $post_type ) ) {
            $object_types = get_taxonomy( $this->taxonomy )->object_type ?? [];
            $post_type    = ! empty( $object_types ) ? $object_types[0] : 'post';
        }

        $tax_query = [
            'relation' => 'AND',
            [
                'taxonomy'         => $this->taxonomy,
                'field'            => 'term_id',
                'terms'            => $this->term_id,
                'include_children' => true,
            ],
            [
                'taxonomy' => 'region',
                'field'    => 'term_id',
                'terms'    => (array) $user_region,
                'operator' => 'IN',
            ],
        ];

        $count = get_posts( [
            'post_type'      => $post_type,
            'posts_per_page' => 1,          // Sadece var mı yok mu — performans
            'fields'         => 'ids',
            'no_found_rows'  => false,
            'tax_query'      => $tax_query,
        ] );

        return count( $count );
    }

    /**
     * Regional settings'den bu taxonomy'ye ait post type'ı bul.
     * LocationManager aktifse oradan, değilse options'dan okur.
     */
    private function resolvePostTypeForTaxonomy( string $taxonomy ): string
    {
        // LocationManager varsa oradan al
        if ( class_exists( '\SaltHareket\Localization\LocationManager' ) ) {
            $lm       = \SaltHareket\Localization\LocationManager::getInstance();
            $settings = \SaltHareket\Localization\LocationSettings::get();
            foreach ( $settings['regional_post_settings'] ?? [] as $mapping ) {
                if ( ( $mapping['taxonomy'] ?? '' ) === $taxonomy ) {
                    return $mapping['post_type'] ?? '';
                }
            }
        }

        // Fallback: eski options key
        $settings = get_option( 'options_regional_post_settings', [] );
        foreach ( (array) $settings as $mapping ) {
            if ( ( $mapping['taxonomy'] ?? '' ) === $taxonomy ) {
                return $mapping['post_type'] ?? '';
            }
        }

        return '';
    }
    public function get_posts_2($post_type="post"){
        return Timber::get_posts(
            array(
                'post_type' => $post_type,
                'post_status' => 'inherit',
                'tax_query' => array(
                    array(
                        'taxonomy' => $this->taxonomy,
                        'field' => 'id',
                        'terms' => $this->term_id
                    )
                )
            )
        );
    }
    public function get_post_count(){
        if(empty($this->post_count)){
            $this->post_count = get_category_total_post_count($this->taxonomy, $this->term_id);
        }
        return $this->post_count;
    }

    public function get_breadcrumb() {
        $term_id = $this->term_id;
        $breadcrumb = [];
        $code = "";
        while ($term_id) {
            $term = get_term($term_id, $this->taxonomy);
            if (is_wp_error($term) || !$term) {
                break;
            }
            $breadcrumb[] = [
                'post_title' => $term->name,
                'link' => get_term_link($term),
                'ID' => $term->term_id
            ];
            $term_id = $term->parent;
        }
        $nodes = array_reverse($breadcrumb);
        return generate_breadcrumb($nodes);
        /*if($breadcrumb){
            foreach ($breadcrumb as $crumb) {
                $code .= '<a href="' . esc_url($crumb['link']) . '">' . esc_html($crumb['name']) . '</a> &raquo; ';
            }            
        }
        return $code;*/
    }

    public function get_term_root(){
        return get_term_root($this->term_id, $this->taxonomy);
    }

    // ── REACTIONS ────────────────────────────────────────────────────────────

    /**
     * Bu term'in belirli bir reaction sayisi.
     * Twig: {{ term.reaction_count('follow') }}
     */
    public function reaction_count( string $type = 'follow' ): int {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return 0;
        return \SaltHareket\Reactions\Reactions::count( $type, $this->term_id, 'term' );
    }

    /**
     * Mevcut kullanici bu term'e reaction yapti mi?
     * Twig: {% if term.has_reaction('follow') %}
     */
    public function has_reaction( string $type = 'follow' ): bool {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return false;
        return \SaltHareket\Reactions\Reactions::has( $type, $this->term_id, 'term' );
    }

    /**
     * Reaction button HTML'i render et.
     * Twig: {{ term.reaction_button('follow', {'style': 'pill'}) }}
     */
    public function reaction_button( string $type = 'follow', array $options = [] ): string {
        if ( ! class_exists( \SaltHareket\Reactions\Admin\ReactionsAjax::class ) ) return '';
        return \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $this->term_id, 'term', $type, $options );
    }

    // ── END REACTIONS ─────────────────────────────────────────────────────────

}