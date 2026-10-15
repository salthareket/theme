<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension {
    public function getFilters(): array {
        return [
            new TwigFilter('pluralize', [$this, 'pluralize']),
            new TwigFilter('values', [$this, 'values']), // Yeni filtreyi ekledik
        ];
    }

    public function pluralize(int $count, string $singular, string $plural, ?string $zero = null): string {
        if ($count > 1) {
            return str_replace('{}', $count, $plural);
        } elseif ($count <= 0 && null !== $zero) {
            return $zero; // No string replacement required for zero
        }
        return str_replace('{}', $count, $singular);
    }


    public function values(array $array): array {
        return array_values($array); // PHP'nin array_values fonksiyonunu kullanıyoruz
    }
}


if (wp_using_ext_object_cache() && wp_cache_get('test_cache', 'test_group')) {
    apply_filters('timber/cache/mode', function () {
        return Timber\Loader::CACHE_OBJECT;
    });
} else {
    apply_filters('timber/cache/mode', function () {
        return Timber\Loader::CACHE_TRANSIENT;
    });
}

add_filter('timber/cache/location', function($path) {
    return STATIC_PATH . 'twig_cache'; 
});

add_filter('timber/twig/environment/options', function ($options) {
    $cache_dir = STATIC_PATH . 'twig_cache';

    // Development mode açıkken twig cache üretilmez — enable_twig_cache değeri değiştirilmez
    $dev_mode = defined('ENABLE_DEVELOPMENT') && ENABLE_DEVELOPMENT;

    if ( ! $dev_mode && defined('ENABLE_TWIG_CACHE') && ENABLE_TWIG_CACHE) {
        // Eğer klasör yoksa oluştur
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        $options['cache'] = $cache_dir;
        // auto_reload: cache'li twig dosyalarının disk değişikliklerini takip eder.
        $options['auto_reload'] = false;
    } else {
        // Cache kapalı — development modda veya enable_twig_cache=false
        $options['cache'] = false;
    }

    return $options;
}, 999);