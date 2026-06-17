<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_typography($data, $generator) {
    $t    = $data['typography'] ?? [];
    $vars = [];
    $mq   = [];
    $mob  = [];
    $mqs  = [];

    // ── Font families ────────────────────────────────────────────
    if (!empty($t['font_primary']))     $vars['font-primary']      = $t['font_primary'];
    if (!empty($t['font_heading']))     $vars['header-font']       = $t['font_heading'];
    if (!empty($t['font_secondary']))   $vars['font-secondary']    = $t['font_secondary'];
    if (!empty($t['icon_font']))        $vars['icon-font']         = $t['icon_font'];
    if (!empty($t['icon_font_brands'])) $vars['icon-font-brands']  = $t['icon_font_brands'];

    // ── Base font-size ve letter-spacing ─────────────────────────
    if (!empty($t['base_font_size']))      $vars['base-font-size']        = $t['base_font_size'];
    if (!empty($t['base_font_weight']))    $vars['base-font-weight']      = $t['base_font_weight'];
    if (!empty($t['base_line_height']))    $vars['base-font-line-height'] = $t['base_line_height'];
    $vars['base-letter-spacing'] = $t['base_letter_spacing'] ?? '0'; // her zaman set et

    // ── Headings ─────────────────────────────────────────────────
    $headings = $t['headings'] ?? [];
    foreach ($headings as $h => $hd) {
        $font = $hd['font_family'] ?? ($t['font_heading'] ?? '');
        if (!empty($font))              $vars["typography-{$h}-font"]   = $font;
        if (!empty($hd['font_size']))   $vars["typography-{$h}-size"]   = $hd['font_size'];
        if (!empty($hd['font_weight'])) $vars["typography-{$h}-weight"] = $hd['font_weight'];
    }

    // ── Responsive Sizes ─────────────────────────────────────────
    $bps = array_keys(THEME_STYLES_BREAKPOINTS);

    // TITLE
    $title_min    = $t['title_min_size'] ?? '';
    $title_max    = $t['title_max_size'] ?? '';
    $title_min_lh = $t['title_min_lh']  ?? '';
    $title_max_lh = $t['title_max_lh']  ?? '';

    // Root'ta base title-fs/lh (xxxl değeri - en büyük)
    $xxxl_title    = $t['title']['xxxl']              ?? (array_values(array_filter($t['title'] ?? []))[0] ?? $title_max);
    $xxxl_title_lh = $t['title_line_height']['xxxl']  ?? (array_values(array_filter($t['title_line_height'] ?? []))[0] ?? $title_max_lh);
    if (!empty($xxxl_title))    $vars['title-fs'] = $xxxl_title;
    if (!empty($xxxl_title_lh)) $vars['title-lh'] = $xxxl_title_lh;

    foreach ($bps as $bp) {
        // Per-breakpoint override varsa onu kullan
        $override_size = $t['title'][$bp] ?? '';
        $override_lh   = $t['title_line_height'][$bp] ?? '';

        if (!empty($override_size)) {
            // Override var → direkt değer
            $mq[$bp]['title-fs'] = $override_size;
            $mqs['title'][$bp]['fs'] = $override_size;
        } elseif (!empty($title_min) && !empty($title_max)) {
            // Min/max var → FluidCss clamp üretsin diye mqs'e yaz
            // xs = min, xxxl = max, arası interpolate edilir
            $mqs['title'][$bp]['fs'] = _ts_interpolate_fluid($bp, $title_min, $title_max, $bps);
            $mq[$bp]['title-fs']     = _ts_interpolate_fluid($bp, $title_min, $title_max, $bps);
        }

        // Line height
        if (!empty($override_lh)) {
            $mq[$bp]['title-lh'] = $override_lh;
            $mqs['title'][$bp]['lh'] = $override_lh;
        } elseif (!empty($title_min_lh) && !empty($title_max_lh)) {
            $lh = _ts_interpolate_fluid($bp, $title_min_lh, $title_max_lh, $bps, false);
            $mq[$bp]['title-lh']     = $lh;
            $mqs['title'][$bp]['lh'] = $lh;
        }
    }

    // TEXT
    $text_min    = $t['text_min_size'] ?? '';
    $text_max    = $t['text_max_size'] ?? '';
    $text_min_lh = $t['text_min_lh']  ?? '';
    $text_max_lh = $t['text_max_lh']  ?? '';

    // Root'ta base text-fs/lh (xxxl değeri)
    $xxxl_text    = $t['text']['xxxl']             ?? (array_values(array_filter($t['text'] ?? []))[0] ?? $text_max);
    $xxxl_text_lh = $t['text_line_height']['xxxl'] ?? (array_values(array_filter($t['text_line_height'] ?? []))[0] ?? $text_max_lh);
    if (!empty($xxxl_text))    $vars['text-fs'] = $xxxl_text;
    if (!empty($xxxl_text_lh)) $vars['text-lh'] = $xxxl_text_lh;

    foreach ($bps as $bp) {
        $override_size = $t['text'][$bp] ?? '';
        $override_lh   = $t['text_line_height'][$bp] ?? '';

        if (!empty($override_size)) {
            $mq[$bp]['text-fs'] = $override_size;
            $mqs['text'][$bp]['fs'] = $override_size;
        } elseif (!empty($text_min) && !empty($text_max)) {
            $mqs['text'][$bp]['fs'] = _ts_interpolate_fluid($bp, $text_min, $text_max, $bps);
            $mq[$bp]['text-fs']     = _ts_interpolate_fluid($bp, $text_min, $text_max, $bps);
        }

        if (!empty($override_lh)) {
            $mq[$bp]['text-lh'] = $override_lh;
            $mqs['text'][$bp]['lh'] = $override_lh;
        } elseif (!empty($text_min_lh) && !empty($text_max_lh)) {
            $lh = _ts_interpolate_fluid($bp, $text_min_lh, $text_max_lh, $bps, false);
            $mq[$bp]['text-lh']     = $lh;
            $mqs['text'][$bp]['lh'] = $lh;
        }
    }

    // Mobile overrides - sadece switch açıksa (mobile_override_active)
    if (!empty($t['mobile_override_active'])) {
        foreach ($bps as $bp) {
            if (!empty($t['title_mobile'][$bp])) $mob["title-fs-{$bp}"] = $t['title_mobile'][$bp];
            if (!empty($t['text_mobile'][$bp]))  $mob["text-fs-{$bp}"]  = $t['text_mobile'][$bp];
        }
    }

    return [
        'variables'       => $vars,
        'mobile'          => $mob,
        'media_queries'   => $mq,
        'media_query_set' => $mqs,
    ];
}

/**
 * Min/max arasında breakpoint'e göre lineer interpolasyon yapar.
 * FluidCss zaten clamp üretiyor ama her breakpoint'e değer vermemiz gerekiyor.
 * xs = min, xxxl = max, arası lineer.
 */
function _ts_interpolate_fluid(string $bp, string $min_val, string $max_val, array $bps, bool $with_unit = true): string {
    // Sayısal değerleri çıkar
    preg_match('/([\d.]+)([a-z%]*)/', trim($min_val), $min_m);
    preg_match('/([\d.]+)([a-z%]*)/', trim($max_val), $max_m);

    $min_num  = isset($min_m[1]) ? (float)$min_m[1] : 0;
    $max_num  = isset($max_m[1]) ? (float)$max_m[1] : 0;
    $unit     = $with_unit ? ($min_m[2] ?? 'px') : '';

    $total_steps = count($bps) - 1;
    $bp_index    = array_search($bp, $bps);

    if ($bp_index === false || $total_steps === 0) {
        return $min_val;
    }

    // xs (index 0) = min, xxxl (son index) = max
    $ratio = $bp_index / $total_steps;
    $value = $min_num + ($max_num - $min_num) * $ratio;
    $value = round($value, 2);

    return $value . $unit;
}
