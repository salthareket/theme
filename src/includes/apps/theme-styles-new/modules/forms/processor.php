<?php
/**
 * Forms Module - CSS Processor
 *
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.1.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 1.1.0 - 2026-04-29
 * - border_style field eklendi (border shorthand üretimi)
 * - checks section eklendi (checkbox, radio, switch)
 * - focus border shorthand eklendi
 *
 * 1.0.0 - 2026-04-29
 * - Initial release
 *
 * HOW TO USE:
 * Bu processor form modülü verilerini CSS variable'lara dönüştürür.
 * Üretilen variable'lar _form.scss tarafından tüketilir.
 *
 * @example Üretilen CSS variable'lar:
 * --form-input-color, --form-input-bg, --form-input-border
 * --form-input-border-radius, --form-input-font-size-sm/md/lg
 * --form-label-color, --form-label-font-size, --form-placeholder-color
 * --form-check-accent, --form-check-size, --form-switch-track-bg
 */

if (!defined('ABSPATH')) exit;

function theme_styles_process_forms($data, $generator): array {
    $f  = $data['forms']       ?? [];
    $fi = $f['input']          ?? [];
    $fl = $f['label']          ?? [];
    $fv = $f['validation']     ?? [];
    $fs = $f['sizes']          ?? [];
    $fc = $f['checks']         ?? [];

    $vars = [];

    // ── Input ────────────────────────────────────────────────────
    $vars['form-input-color']        = $fi['color']        ?? '#666666';
    $vars['form-input-color-focus']  = $fi['color_focus']  ?? '#333333';
    $vars['form-input-bg']           = $fi['bg_color']     ?? '#ffffff';
    $vars['form-input-bg-focus']     = $fi['bg_color_focus'] ?? '#ffffff';
    $vars['form-input-font-weight']  = $fi['font_weight']  ?? '400';
    $vars['form-input-text-transform'] = $fi['text_transform'] ?? 'none';
    $vars['form-input-border-radius']= $fi['border_radius'] ?? '8px';
    $vars['form-input-focus-shadow'] = $fi['focus_shadow'] ?? 'none';

    // Border shorthand (default)
    $bw = $fi['border_width'] ?? '1px';
    $bs = $fi['border_style'] ?? 'solid';
    $bc = $fi['border_color'] ?? '#dddddd';
    $vars['form-input-border-width'] = $bw;
    $vars['form-input-border-color'] = $bc;

    if (!empty($fi['border_4side'])) {
        // Per-side borders → individual CSS variables
        foreach (['top','right','bottom','left'] as $side) {
            $sw = $fi['border_' . $side . '_width'] ?? $bw;
            $ss = $fi['border_' . $side . '_style'] ?? $bs;
            $sc = $fi['border_' . $side . '_color'] ?? $bc;
            $vars["form-input-border-{$side}"] = "{$sw} {$ss} {$sc}";
        }
        $vars['form-input-border'] = '0'; // reset shorthand
    } else {
        $vars['form-input-border'] = "{$bw} {$bs} {$bc}";
    }

    // Border shorthand (focus)
    $bwf = $fi['border_focus_width'] ?? '1px';
    $bsf = $fi['border_focus_style'] ?? 'solid';
    $bcf = $fi['border_focus_color'] ?? '#aaaaaa';
    $vars['form-input-border-color-focus'] = $bcf;

    if (!empty($fi['border_focus_4side'])) {
        foreach (['top','right','bottom','left'] as $side) {
            $sw = $fi['border_focus_' . $side . '_width'] ?? $bwf;
            $ss = $fi['border_focus_' . $side . '_style'] ?? $bsf;
            $sc = $fi['border_focus_' . $side . '_color'] ?? $bcf;
            $vars["form-input-border-focus-{$side}"] = "{$sw} {$ss} {$sc}";
        }
        $vars['form-input-border-focus'] = '0';
    } else {
        $vars['form-input-border-focus'] = "{$bwf} {$bsf} {$bcf}";
    }

    // ── Placeholder ──────────────────────────────────────────────
    $vars['form-placeholder-color']       = $fi['placeholder_color']        ?? '#a3a3a3';
    $vars['form-placeholder-font-weight'] = $fi['placeholder_font_weight']  ?? '400';

    // ── Label ────────────────────────────────────────────────────
    $vars['form-label-color']      = $fl['color']       ?? '#2271b1';
    $vars['form-label-font-size']  = $fl['font_size']   ?? '15px';
    $vars['form-label-font-weight']= $fl['font_weight'] ?? '600';

    // ── Validation ───────────────────────────────────────────────
    $vars['form-valid-color']          = $fv['valid_color']    ?? '#198754';
    $vars['form-invalid-color']        = $fv['invalid_color']  ?? '#dc3545';
    $vars['form-invalid-border-color'] = $fv['invalid_border'] ?? '#dc3545';
    $vars['form-invalid-bg']           = $fv['invalid_bg']     ?? 'rgba(250,238,238,0.3)';

    // ── Sizes ────────────────────────────────────────────────────
    $size_defaults = [
        'sm' => ['font_size' => '13px', 'padding_y' => '6px',  'padding_x' => '10px'],
        'md' => ['font_size' => '15px', 'padding_y' => '10px', 'padding_x' => '14px'],
        'lg' => ['font_size' => '17px', 'padding_y' => '14px', 'padding_x' => '18px'],
    ];

    $default_br = $fi['border_radius'] ?? '8px';

    foreach ($size_defaults as $size => $d) {
        $sd = $fs[$size] ?? [];
        $vars["form-input-font-size-{$size}"] = $sd['font_size']  ?? $d['font_size'];
        $vars["form-input-padding-y-{$size}"] = $sd['padding_y']  ?? $d['padding_y'];
        $vars["form-input-padding-x-{$size}"] = $sd['padding_x']  ?? $d['padding_x'];
        // border-radius: boşsa ana input border-radius'u kullan
        $size_br = !empty($sd['border_radius']) ? $sd['border_radius'] : $default_br;
        $vars["form-input-border-radius-{$size}"] = $size_br;
    }

    // ── Checkbox / Radio / Switch ────────────────────────────────
    $vars['form-check-accent']            = $fc['accent_color']           ?? '#2271b1';
    $vars['form-check-border-color']      = $fc['border_color']           ?? '#aaaaaa';
    $vars['form-check-size']              = $fc['size']                   ?? '18px';
    $vars['form-switch-track-bg']         = $fc['switch_track_bg']        ?? '#cccccc';
    $vars['form-switch-track-bg-checked'] = $fc['switch_track_bg_checked'] ?? '#2271b1';
    $vars['form-switch-thumb-color']      = $fc['switch_thumb_color']     ?? '#ffffff';

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
