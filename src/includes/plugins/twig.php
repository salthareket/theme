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

if(ENABLE_TWIG_CACHE){
    add_filter('timber/twig/environment/options', function ($options) {
        $options['cache'] = STATIC_PATH . 'twig_cache'; // Önbellek dizinini belirtin
        $options['auto_reload'] = ENABLE_PRODUCTION;
        return $options;
    });    
}