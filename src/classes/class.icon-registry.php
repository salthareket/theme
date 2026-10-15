<?php

namespace SaltHareket;

/**
 * IconRegistry
 *
 * Site genelinde kullanılan ikonları JS'e erişilebilir hale getirir.
 * General Settings + Reactions icon'larını merge edip SVG dictionary üretir.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-08-26
 *     - Initial release
 *
 * How to use:
 *   // PHP'de tüm JS ikonlarını al
 *   $icons = IconRegistry::getJsIcons();
 *   // → ['cart' => '<svg...>', 'notifications' => '<svg...>', 'favorite' => '<svg...>', ...]
 *
 *   // Tek bir icon SVG'si (class eklenebilir)
 *   echo IconRegistry::get('cart', 'fa-3x opacity-25');
 *
 *   // site_config'e entegrasyon (startersite.php)
 *   $site_config['icons'] = IconRegistry::getJsIcons();
 *
 * Examples:
 *   // JS tarafında kullanım:
 *   fa_icon('cart')                      // → '<svg class="fa-icon fa-light">...</svg>'
 *   fa_icon('favorite', 'fa-3x opacity-25') // → '<svg class="fa-icon fa-regular fa-3x opacity-25">...</svg>'
 *   fa_icon('like')                      // → reaction type'ın icon_off SVG'si
 */
class IconRegistry
{
    /**
     * Cached icons dictionary.
     */
    private static ?array $cache = null;

    /**
     * System icon option keys and their defaults.
     */
    private static function systemDefaults(): array
    {
        return [
            'cart'          => 'light:cart-shopping',
            'notifications' => 'light:bell',
            'messages'      => 'light:envelope',
            'reviews'       => 'light:star',
            'empty'         => 'regular:face-smile',
            'search'        => 'light:magnifying-glass',
        ];
    }

    /**
     * Get all JS-accessible icons as key → SVG string dictionary.
     * Merges system icons (General Settings) + reaction type icons.
     *
     * @return array<string, string>
     */
    public static function getJsIcons(): array
    {
        if ( self::$cache !== null ) {
            return self::$cache;
        }

        $icons = [];

        // 1. System icons from General Settings
        foreach ( self::systemDefaults() as $key => $default ) {
            $value = get_option( 'options_default_icon_' . $key, $default );
            $svg   = self::resolveIconValue( $value ?: $default );
            if ( $svg ) {
                $icons[ $key ] = $svg;
            }
        }

        // 2. Reaction type icons
        if ( class_exists( '\\SaltHareket\\Reactions\\ReactionsSettings' ) ) {
            $types = \SaltHareket\Reactions\ReactionsSettings::getTypes();
            foreach ( $types as $key => $config ) {
                // icon_off as the representative icon for this reaction type
                $icon_value = $config['icon_off'] ?? '';
                if ( ! $icon_value ) continue;

                $svg = self::resolveIconValue( $icon_value );
                if ( $svg ) {
                    $icons[ $key ] = $svg;
                }
            }
        }

        self::$cache = $icons;
        return $icons;
    }

    /**
     * Get a single icon SVG with optional extra classes.
     *
     * @param string $key    Icon key (e.g., 'cart', 'favorite', 'like')
     * @param string $class  Extra CSS classes to add
     * @return string SVG HTML or empty string
     */
    public static function get( string $key, string $class = '' ): string
    {
        $icons = self::getJsIcons();
        $svg   = $icons[ $key ] ?? '';

        if ( ! $svg ) return '';

        if ( $class ) {
            $svg = preg_replace(
                '/class="([^"]*)"/',
                'class="$1 ' . esc_attr( $class ) . '"',
                $svg,
                1
            );
        }

        return $svg;
    }

    /**
     * Resolve an icon value string to SVG output.
     * Handles both formats:
     *   - New: "style:name" (e.g., "solid:heart", "regular:bell")
     *   - Old: FA class string (e.g., "far fa-heart", "fas fa-star")
     *
     * @param string $value
     * @return string SVG HTML or empty string
     */
    private static function resolveIconValue( string $value ): string
    {
        if ( empty( $value ) ) return '';

        // New format: style:name
        if ( preg_match( '/^(solid|regular|light|brands):(.+)$/', $value, $m ) ) {
            $style = $m[1];
            $name  = $m[2];
            return FaIconManager::render( $name, $style );
        }

        // Old format: "far fa-heart" or "fas fa-star"
        // Parse: "far fa-xxx" → regular:xxx, "fas fa-xxx" → solid:xxx, "fal fa-xxx" → light:xxx
        if ( preg_match( '/\b(fas?|far?|fal|fab)\s+fa-([a-z0-9-]+)/', $value, $m ) ) {
            $prefix = $m[1];
            $name   = $m[2];
            $style_map = [
                'fas'  => 'solid',
                'fa'   => 'solid',
                'far'  => 'regular',
                'fal'  => 'light',
                'fab'  => 'brands',
            ];
            $style = $style_map[ $prefix ] ?? 'regular';
            return FaIconManager::render( $name, $style );
        }

        return '';
    }

    /**
     * Clear the static cache (useful after settings save).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
