<?php

/**
 * Schema_Breadcrumbs — Yoast SEO breadcrumb markup'ını Schema.org + Bootstrap uyumlu hale getirir.
 *
 * Yoast'ın default breadcrumb HTML'ini <ul class="breadcrumb"><li> yapısına çevirir,
 * Schema.org BreadcrumbList / ListItem markup'ı ekler, btn-loading-page class'ı ile
 * SPA-style sayfa geçişi destekler.
 *
 * KULLANIM:
 *   // theme.php veya functions.php'de:
 *   if ( function_exists( 'yoast_breadcrumb' ) && class_exists( 'Schema_Breadcrumbs' ) ) {
 *       Schema_Breadcrumbs::instance();
 *   }
 *
 * NOT: Yoast SEO 20+ zaten JSON-LD Schema.org breadcrumb üretiyor.
 *      Bu class sadece GÖRSEL HTML markup'ı için gerekli.
 *      Eğer Yoast'ın default HTML'i yeterliyse bu class devre dışı bırakılabilir.
 *
 * @package SaltHareket
 * @since   1.0.0
 */

class Schema_Breadcrumbs {

    private static ?self $instance = null;
    private int $position = 0;
    private bool $bold_last = false;

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // boldlast ayarını bir kez çek — her element'te DB'ye gitme
        $titles = get_option( 'wpseo_titles', [] );
        $this->bold_last = ! empty( $titles['breadcrumbs-boldlast'] );

        add_filter( 'wpseo_breadcrumb_single_link', [ $this, 'modify_element' ], 10, 2 );
        add_filter( 'wpseo_breadcrumb_output', [ $this, 'modify_output' ] );
    }

    /**
     * Tekil breadcrumb element'ini Schema.org ListItem markup'ına çevirir.
     */
    public function modify_element( string $link_output, array $link ): string {
        $this->position++;

        $url  = esc_url( $link['url'] ?? '' );
        $text = esc_html( $link['text'] ?? '' );

        if ( empty( $text ) ) {
            return $link_output;
        }

        $is_link = ! empty( $url ) && str_contains( $link_output, 'rel="v:url"' );

        // Son element (current page) — link yoksa current URL kullan
        if ( ! $is_link && empty( $url ) ) {
            $url = esc_url( $this->current_url() );
        }

        $name_html = $text;
        if ( ! $is_link && $this->bold_last ) {
            $name_html = '<strong>' . $text . '</strong>';
        }

        $last_class = $is_link ? '' : ' breadcrumb_last';

        return sprintf(
            '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" class="%s">'
            . '<a href="%s" class="btn-loading-page">'
            . '<span itemprop="name">%s</span>'
            . '<meta itemprop="position" content="%d" />'
            . '</a></li>',
            ltrim( $last_class ),
            $url,
            $name_html,
            $this->position
        );
    }

    /**
     * Breadcrumb wrapper'ını Schema.org BreadcrumbList + <ul> yapısına çevirir.
     */
    public function modify_output( string $output ): string {
        // Yoast'ın RDFa namespace'ini Schema.org ile değiştir
        $output = str_replace(
            [ ' xmlns:v="http://rdf.data-vocabulary.org/#"', ' prefix="v: http://rdf.data-vocabulary.org/#"' ],
            ' itemscope itemtype="https://schema.org/BreadcrumbList"',
            $output
        );

        // Wrapper <span> → <ul class="breadcrumb">
        // Sadece açılış ve kapanış span'ını hedefle — içerideki span'lara dokunma
        $output = preg_replace(
            '/<span\s+([^>]*(?:itemscope|itemprop)[^>]*)>/',
            '<ul class="breadcrumb" $1>',
            $output,
            1 // sadece ilk match
        );

        // Kapanış — son </span>'ı </ul> yap
        $last_span_pos = strrpos( $output, '</span>' );
        if ( $last_span_pos !== false ) {
            $output = substr_replace( $output, '</ul>', $last_span_pos, 7 );
        }

        return $output;
    }

    private function current_url(): string {
        if ( function_exists( 'current_url' ) ) {
            return current_url();
        }
        // Fallback
        $protocol = is_ssl() ? 'https' : 'http';
        return $protocol . '://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' );
    }
}
