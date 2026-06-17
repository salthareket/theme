<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_utilities($data, $generator) {
    $util = $data['utilities'] ?? [];
    $stt  = $util['scroll_to_top'] ?? [];
    $hero = $util['hero']          ?? [];
    $vars = [];
    $mq   = [];
    $mqs  = [];
    $bps  = array_keys(THEME_STYLES_BREAKPOINTS);

    // ── Hero height per breakpoint ───────────────────────────────
    $xxxl_hero = $hero['height']['xxxl'] ?? ($hero['min_height']['xxxl'] ?? '');
    if (!empty($xxxl_hero)) {
        $vars['hero-height']     = $xxxl_hero;
        $vars['hero-height-min'] = $xxxl_hero;
    }

    foreach ($bps as $bp) {
        $val = $hero['height'][$bp] ?? ($hero['min_height'][$bp] ?? '');
        if (!empty($val)) {
            $mq[$bp]['hero-height']     = $val;
            $mqs['hero'][$bp]['height'] = $val;
        }
    }

    // ── Scroll to Top ────────────────────────────────────────────
    // Template: scroll_to_top.active (switch) | Processor: enabled
    $active = !empty($stt['active']) || (isset($stt['enabled']) && $stt['enabled'] === '1');
    $vars['scroll-to-top-active'] = $active ? '1' : '0';
    $vars['scroll-to-top-show']   = $active ? 'block' : 'none';

    // Template: position_hr/position_vr | Eski: hr/vr
    $vars['scroll-to-top-hr']           = $stt['position_hr'] ?? ($stt['hr'] ?? 'right');
    $vars['scroll-to-top-vr']           = $stt['position_vr'] ?? ($stt['vr'] ?? 'bottom');

    // Template: width/height ayrı | Eski: size
    $vars['scroll-to-top-width']        = $stt['width']  ?? ($stt['size'] ?? '40px');
    $vars['scroll-to-top-height']       = $stt['height'] ?? ($stt['size'] ?? '40px');

    // Template: radius | Eski: border_radius
    $vars['scroll-to-top-radius']       = $stt['radius'] ?? ($stt['border_radius'] ?? '50%');

    $vars['scroll-to-top-bg-color']       = $stt['bg_color']        ?? '#8c734b';
    $vars['scroll-to-top-bg-color-hover'] = $stt['bg_color_hover']  ?? '#414042';
    $vars['scroll-to-top-color']          = $stt['color']           ?? '#ffffff';
    $vars['scroll-to-top-color-hover']    = $stt['color_hover']     ?? '#ffffff';
    $vars['scroll-to-top-gap']            = $stt['gap']             ?? '35px';
    $vars['scroll-to-top-font-size']      = $stt['font_size']       ?? '22px';
    $vars['scroll-to-top-duration']       = $stt['duration']        ?? '600';

    // ── Global variable'lar ──────────────────────────────────────
    $vars['breakpoints'] = "'" . implode(',', $bps) . "'";

    $colors     = $data['colors'] ?? [];
    $color_keys = ['primary', 'secondary', 'tertiary', 'quaternary'];
    if (!empty($colors['custom']) && is_array($colors['custom'])) {
        foreach ($colors['custom'] as $c) {
            if (!empty($c['title'])) $color_keys[] = $c['title'];
        }
    }
    $vars['custom-colors-list'] = implode(',', $color_keys);

    return [
        'variables'       => $vars,
        'mobile'          => [],
        'media_queries'   => $mq,
        'media_query_set' => $mqs,
    ];
}
