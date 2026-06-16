<?php

use voku\helper\HtmlDomParser;

/**
 * HandlesTwig - PAE Twig Template Yonetimi Trait
 *
 * Twig template yollarinin tespiti, template dosyalarinin okunmasi,
 * include cozumleme ve approx HTML uretimi islemlerini kapsar.
 *
 * @package    SaltHareket\Theme\PageAssetsExtractor\Concerns
 * @version    1.0.0
 * @since      1.0.0
 * @author     SaltHareket
 *
 * @changelog
 *   1.0.0 - 2026-05-03
 *     - Refactor: class.page-assets-extractor.php'den ayrildi
 *     - Add: CODING_PRINCIPLES uyumlu dokumantasyon eklendi
 *
 * HOW TO USE:
 *   Bu trait PageAssetsExtractor sinifi icinde `use HandlesTwig;` ile kullanilir.
 *   Timber/Twig template dosyalarini tarar, include'lari cozumler ve
 *   approx HTML uretir (plugin detection icin).
 *
 * @example Twig yollarini manuel set et:
 *   $extractor->set_twig_template_paths(['/path/to/templates']);
 *
 * @example Twig template HTML'ini topla:
 *   $html = $this->collectTwigLoadedHtml($dom);
 *
 * @example Twig'i approx HTML'e donustur:
 *   $approxHtml = $this->twigToApproxHtml($twigContent);
 *
 * @example Timber yollarini otomatik tespit et:
 *   $paths = $this->detectTimberTemplatePaths();
 *
 * @example Include'lari coz:
 *   $raw = $this->collectIncludedTwigRaw($raw, dirname($file));
 */

trait HandlesTwig
{
    public function set_twig_template_paths(array $paths)
    {
            // Sınıfınızdaki özelliğin adı 'twig_template_paths'
            $this->twig_template_paths = $paths;
    }

    // =========================================================

        private function detectTimberTemplatePaths(): array {
        $paths = [];
        $checked = [];

        // 1) Child/Parent theme default "templates"
        if (function_exists('get_stylesheet_directory')) {
            $p = trailingslashit(get_stylesheet_directory()) . 'templates';
            $checked[] = $p;
            if (is_dir($p)) { $paths[] = $p; }
        }
        if (
            function_exists('get_template_directory') &&
            function_exists('get_stylesheet_directory') &&
            get_template_directory() !== get_stylesheet_directory()
        ) {
            $p = trailingslashit(get_template_directory()) . 'templates';
            $checked[] = $p;
            if (is_dir($p)) { $paths[] = $p; }
        }

        // 2) Timber::$locations (Timber 2)
        if (class_exists('\Timber\Timber') && !empty(\Timber\Timber::$locations)) {
            foreach (\Timber\Timber::$locations as $loc) {
                $abs = rtrim($loc, '/\\');
                $checked[] = $abs;
                if (is_dir($abs)) { $paths[] = $abs; }
            }
        }

        // 3) Timber::$dirname (legacy / hala yaygın)
        if (class_exists('\Timber\Timber') && !empty(\Timber\Timber::$dirname)) {
            $dirnames = (array) \Timber\Timber::$dirname;
            $resolved = $this->resolveTimberDirnamesToAbsolute($dirnames, $checked);
            $paths = array_merge($paths, $resolved);
        }

        // Temizle + logla
        $paths = array_values(array_unique(array_filter($paths, 'is_dir')));
        $this->error_log('[PAE] detectTimberTemplatePaths checked=' . json_encode($checked, JSON_UNESCAPED_SLASHES));
        $this->error_log('[PAE] detectTimberTemplatePaths final='   . json_encode($paths,   JSON_UNESCAPED_SLASHES));
        return $paths;
    }

    // =========================================================

        private function ensureTwigPaths(): void {
        if ($this->twig_paths_initialized) return;

        // 1) dışarıdan geldiyse önce onu kullan
        if (!empty($this->twig_options['twig_paths']) && is_array($this->twig_options['twig_paths'])) {
            $paths = array_values(array_filter($this->twig_options['twig_paths'], 'is_string'));
            $paths = array_values(array_filter($paths, 'is_dir'));
            $this->twig_template_paths = $paths;
            $this->twig_paths_initialized = true;
        } else {
            // 2) otomatik tespit (bir kez)
            $this->twig_template_paths = $this->detectTimberTemplatePaths();
            $this->twig_paths_initialized = true;
        }

        $this->error_log('[PAE] twig_paths (lazy)=' . json_encode($this->twig_template_paths, JSON_UNESCAPED_SLASHES));
    }

    // =========================================================

        private function resolveTimberDirnamesToAbsolute(array $dirnames, array &$checked): array {
        $roots = [];

        // Child & parent theme kökleri
        if (function_exists('get_stylesheet_directory')) {
            $roots[] = trailingslashit(get_stylesheet_directory());
        }
        if (function_exists('get_template_directory')) {
            $roots[] = trailingslashit(get_template_directory());
        }

        // wp-content ve ABSPATH de dene (vendor/... gibi)
        if (defined('WP_CONTENT_DIR')) {
            $roots[] = trailingslashit(WP_CONTENT_DIR);
        }
        if (defined('ABSPATH')) {
            $roots[] = trailingslashit(ABSPATH);
        }

        // Bu sınıfın bulunduğu plugin/theme kökü (vendor senaryosu için iş görür)
        if (defined('__DIR__')) {
            $roots[] = trailingslashit(dirname(__DIR__)); // sınıfa göre 1 seviye yukarı
            $roots[] = trailingslashit(__DIR__);          // bulunduğu klasör
        }

        $out = [];
        foreach ($dirnames as $d) {
            if (!$d) { continue; }
            // Absolute geldiyse direkt dene
            if ($d[0] === '/' || preg_match('#^[A-Z]:[\\\\/]#i', $d)) {
                $candidate = rtrim($d, '/\\');
                $checked[] = $candidate;
                if (is_dir($candidate)) {
                    $out[] = $candidate;
                    continue;
                }
            }

            // Relative ise her root’a ekle
            foreach ($roots as $root) {
                $candidate = rtrim($root . ltrim($d, '/\\'), '/\\');
                $checked[] = $candidate;
                if (is_dir($candidate)) {
                    $out[] = $candidate;
                }
            }
        }

        // uniq
        $out = array_values(array_unique($out));
        return $out;
    }

    // =========================================================

        private function locateTwig(string $template): ?string {
        $this->ensureTwigPaths(); // path’lar yoksa şimdi tespit et

        $tpl = ltrim($template, '/\\');
        if (!str_ends_with($tpl, '.twig')) {
            $tpl .= '.twig';
        }
        if (array_key_exists($tpl, $this->twig_locate_cache)) {
            return $this->twig_locate_cache[$tpl] ?: null;
        }
        foreach ($this->twig_template_paths as $base) {
            $path = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $tpl;
            if (is_file($path)) {
                $this->twig_locate_cache[$tpl] = $path;
                return $path;
            }
        }
        $this->twig_locate_cache[$tpl] = null;
        return null;
    }

    // =========================================================

        private function collectIncludedTwigRaw(string $raw, string $current_dir, array &$visited = []): string {
        $out = '';
        if (preg_match_all('/\{\%\s*include\s*[\'"]([^\'"]+)[\'"]\s*(?:with\s+[^%]+)?\%\}/', $raw, $m)) {
            foreach ($m[1] as $inc) {
                $candidates = [];
                if (strpos($inc, '/') === 0) {
                    $candidates[] = $this->locateTwig(ltrim($inc, '/'));
                } else {
                    $rel = $current_dir . DIRECTORY_SEPARATOR . $inc;
                    $candidates[] = is_file($rel) ? $rel : null;
                    $candidates[] = $this->locateTwig($inc);
                }
                $found = false;
                foreach (array_filter($candidates) as $file) {
                    if (!isset($visited[$file]) && is_readable($file)) {
                        $visited[$file] = true;
                        $sub = file_get_contents($file);
                        if ($sub !== false) {
                            $out .= "\n" . $sub;
                            $found = true;
                            $this->error_log('[PAE] include resolved: ' . $inc . ' -> ' . $file);
                            // rekürsif
                            $out .= "\n" . $this->collectIncludedTwigRaw($sub, dirname($file), $visited);
                        } else {
                            $this->error_log('[PAE] include read fail: ' . $file);
                        }
                    }
                }
                if (!$found) {
                    $this->error_log('[PAE] include NOT found: ' . $inc);
                }
            }
        }
        return $out;
    }

    // =========================================================

        private function logApproxHtmlSelectors(string $html, string $label = ''): void {
        $classes = [];
        $ids     = [];

        $frag = HtmlDomParser::str_get_html($html);
        if (!$frag) {
            $this->error_log('[PAE] logApproxHtmlSelectors: failed to parse approx html for ' . $label);
            return;
        }

        foreach ($frag->find('*') as $el) {
            // class
            $cls = $el->getAttribute('class');
            if ($cls) {
                foreach (preg_split('/\s+/', trim($cls)) as $c) {
                    if ($c !== '') { $classes[$c] = true; }
                }
            }
            // id
            $id = $el->getAttribute('id');
            if ($id) {
                $ids[$id] = true;
            }
        }

        $classes = array_keys($classes);
        $ids     = array_keys($ids);

        // Çok uzun olmasın diye ilk 30 tanesini gösterelim
        $sampleClasses = array_slice($classes, 0, 30);
        $sampleIds     = array_slice($ids, 0, 30);

        $this->error_log(sprintf('[PAE] selectors from %s | classes=%d ids=%d', $label, count($classes), count($ids)));
        $this->error_log('[PAE] classes sample: ' . implode(', ', $sampleClasses));
        $this->error_log('[PAE] ids sample: ' . implode(', ', $sampleIds));
    }

    // =========================================================

        private function twigToApproxHtml(string $twig): string {
        $s = $twig;

        // 1) Twig yorumları {# ... #}
        $s = preg_replace('/\{\#.*?\#\}/s', '', $s);

        // 2) Twig control blokları {% ... %} → tamamen sil
        $s = preg_replace('/\{\%.*?\%\}/s', '', $s);

        // 3) Twig değişkenleri {{ ... }} → boşalt (bazı yerlerde class attribute içinde olabilir)
        //   class="{{ something }}" → class="" kalsın
        $s = preg_replace('/\{\{.*?\}\}/s', '', $s);

        // 4) Bozuk kalan attribute/etiket kapanışlarını biraz toparla
        //   (Bu approx; HtmlDomParser çoğu durumda yine parse edebiliyor.)
        //   Fazla boşlukları azalt
        $s = preg_replace('/\s+/', ' ', $s);

        // 5) Twig include kalıntıları vs yok
        $s = trim($s);

        // Artık bu string, DOM’a gömülüp selector taramasında kullanılabilir
        return $s;
    }

    // =========================================================

        private function collectTwigLoadedHtml(\voku\helper\HtmlDomParser $dom): string {
        $nodes = $dom->find("*[{$this->twig_attr}]");
        if (!$nodes || count($nodes) === 0) {
            $this->error_log('[PAE] data-template: node bulunamadı');
            return '';
        }

        $this->ensureTwigPaths();

        // AJAX ile yüklenecek template'leri hariç tut (modal, offcanvas vs.)
        $ajax_method_nodes = $dom->find("*[data-ajax-method]");
        $ajax_templates = [];
        if ($ajax_method_nodes && count($ajax_method_nodes) > 0) {
            foreach ($ajax_method_nodes as $anode) {
                $tplVal = trim((string) $anode->getAttribute($this->twig_attr));
                if ($tplVal === '') continue;
                foreach (preg_split('/[,;]+/', $tplVal) as $p) {
                    $p = trim($p);
                    if ($p === '') continue;
                    if (!str_ends_with($p, '.twig')) $p .= '.twig';
                    $ajax_templates[$p] = true;
                }
            }
            if (!empty($ajax_templates)) {
                $this->error_log('[PAE] AJAX templates excluded: ' . json_encode(array_keys($ajax_templates)));
            }
        }

        // 1) DOM’daki TÜM data değerlerini topla ve normalize et
        $uniqueTemplates = [];
        foreach ($nodes as $node) {
            $raw = trim((string) $node->getAttribute($this->twig_attr));
            if ($raw === '') { continue; }
            $parts = preg_split('/[,;]+/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (!str_ends_with($p, '.twig')) $p .= '.twig';
                if (isset($ajax_templates[$p])) continue;
                $uniqueTemplates[$p] = true;
            }
        }

        $all = array_keys($uniqueTemplates);
        if (empty($all)) {
            $this->error_log('[PAE] data-template: template değeri yok (boş)');
            return '';
        }

        // 2) Daha önce işlenmişleri at
        $toProcess = [];
        foreach ($all as $tpl) {
            if (!isset($this->twig_seen_templates[$tpl])) {
                $this->twig_seen_templates[$tpl] = true;
                $toProcess[] = $tpl;
            }
        }

        if (empty($toProcess)) {
            $this->error_log('[PAE] data-template: tüm template değerleri önceden işlenmiş, atlandı. uniq=' . count($all));
            return '';
        }

        $this->error_log('[PAE] data-template: uniq=' . count($all) . ', yeni_islenecek=' . count($toProcess));
        $html_chunks = [];
        $foundFiles  = [];
        $missed      = [];

        // 3) Her bir uniq template için bir kez çalış
        foreach ($toProcess as $tpl) {
            $file = $this->locateTwig($tpl);
            if (!$file || !is_readable($file)) {
                $missed[] = $tpl;
                continue;
            }

            $foundFiles[] = $file;

            // 4) Dosya approx-HTML cache’i
            if (isset($this->twig_approx_cache[$file])) {
                $html_chunks[] = $this->twig_approx_cache[$file];
                continue;
            }

            $raw = file_get_contents($file);
            if ($raw === false) { continue; }

            // include’ları çöz (opsiyonel)
            if ($this->twig_scan_includes) {
                $raw .= "\n" . $this->collectIncludedTwigRaw($raw, dirname($file));
            }

            $approx_html = $this->twigToApproxHtml($raw);
            $this->twig_approx_cache[$file] = $approx_html ?: '';

            if ($approx_html) {
                $html_chunks[] = $approx_html;
            }
        }

        // 5) Log
        if ($foundFiles) {
            $this->error_log('[PAE] Twig bulundu: ' . count($foundFiles));
            foreach ($foundFiles as $f) {
                $this->error_log('[PAE]  - ' . $f);
            }
        }
        if ($missed) {
            $this->error_log('[PAE] Twig bulunamadı: ' . json_encode($missed, JSON_UNESCAPED_SLASHES));
            $this->error_log('[PAE]  aranan_yollar: ' . json_encode($this->twig_template_paths, JSON_UNESCAPED_SLASHES));
        }

        // Örnek: basit sınıf/ID istatistiği (yaklaşık)
        $summary = strip_tags(implode(' ', $html_chunks));
        preg_match_all('/class="([^"]+)"/', $summary, $m1);
        preg_match_all('/id="([^"]+)"/', $summary, $m2);
        $classes = [];
        if (!empty($m1[1])) {
            foreach ($m1[1] as $cstr) {
                foreach (preg_split('/\s+/', trim($cstr)) as $c) {
                    if ($c !== '') { $classes[$c] = true; }
                }
            }
        }
        $ids = array_unique($m2[1] ?? []);
        $this->error_log('[PAE] approx selectors: classes=' . count($classes) . ' ids=' . count($ids));

        return implode("\n", $html_chunks);
    }

    // =========================================================

    
}
