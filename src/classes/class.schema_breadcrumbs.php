<?php

/**
 * Schema_Breadcrumbs — Yoast SEO breadcrumb markup'ini Schema.org + Bootstrap uyumlu hale getirir.
 *
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-04-09
 *     - Fix: modify_output inner-span trick ile ic ice span/ul sorunu cozuldu
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   if ( function_exists( 'yoast_breadcrumb' ) && class_exists( 'Schema_Breadcrumbs' ) ) {
 *       Schema_Breadcrumbs::instance();
 *   }
 *
 * @package SaltHareket
 * @since   1.0.0
 */

class Schema_Breadcrumbs {

    private static $instance = null;
    private $breadcrumb_link_counter = 0;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wpseo_breadcrumb_single_link', array( $this, 'modify_breadcrumb_element' ), 10, 2 );
        add_filter( 'wpseo_breadcrumb_output', array( $this, 'modify_breadcrumb_output' ) );
    }

    public function modify_breadcrumb_element( $link_output, $link ) {
        $output = '';

        if ( isset( $link['url'] ) && substr_count( $link_output, 'rel="v:url"' ) > 0 ) {
            $output .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
                     . '<a href="' . esc_attr( $link['url'] ) . '" class="btn-loading-page">'
                     . '<inner-span itemprop="name">' . $link['text'] . '</inner-span>'
                     . '<meta itemprop="position" content="' . ( $this->breadcrumb_link_counter + 1 ) . '" />'
                     . '</a></li>';
        } else {
            $bold_last = false;
            $titles = get_option( 'wpseo_titles', [] );
            if ( ! empty( $titles['breadcrumbs-boldlast'] ) ) {
                $bold_last = true;
            }

            $url = ! empty( $link['url'] ) ? $link['url'] : ( function_exists( 'current_url' ) ? current_url() : '' );
            $text = $bold_last ? '<strong>' . $link['text'] . '</strong>' : $link['text'];

            $output .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" class="breadcrumb_last">'
                     . '<a href="' . esc_attr( $url ) . '" class="btn-loading-page">'
                     . '<inner-span itemprop="name">' . $text . '</inner-span>'
                     . '<meta itemprop="position" content="' . ( $this->breadcrumb_link_counter + 1 ) . '" />'
                     . '</a></li>';
        }

        $this->breadcrumb_link_counter++;
        return $output;
    }

    public function modify_breadcrumb_output( $full_output ) {
        $string_to_replace = ' xmlns:v="http://rdf.data-vocabulary.org/#"';
        $output = str_replace( $string_to_replace, ' itemprop="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList"', $full_output );
        $output = str_replace( '<span', '<ul class="breadcrumb"', $output );
        $output = str_replace( '</span>', '</ul>', $output );
        $output = str_replace( 'inner-span', 'span', $output );

        // Counter sifirla
        $this->breadcrumb_link_counter = 0;

        return $output;
    }
}
