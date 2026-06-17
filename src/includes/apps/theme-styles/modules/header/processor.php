<?php
if (!defined('ABSPATH')) exit;

function theme_styles_process_header($data, $generator) {
    $header = $data['header'] ?? [];
    $h      = $header['header']   ?? [];
    $navbar = $header['navbar']   ?? [];
    $nav    = $header['nav']      ?? [];
    $ni     = $header['nav_item'] ?? [];
    $dd     = $header['dropdown'] ?? [];
    $ddd    = $dd['dropdown']      ?? [];
    $ddi    = $dd['dropdown_item'] ?? [];
    $arrow  = $dd['arrow']         ?? [];
    $logo   = $header['logo']      ?? [];
    $ht     = $header['header_tools'] ?? [];
    $htg    = $ht['header_tools']  ?? [];
    $bps    = array_keys(THEME_STYLES_BREAKPOINTS);

    $vars = [];
    $mq   = [];

    // ── Header General ──────────────────────────────────────────
    $vars['header-dropshadow']   = !empty($h['dropshadow']) ? 'block' : 'none';
    $vars['header-z-index']      = $h['z_index']      ?? '100';
    $vars['header-bg']           = $h['bg_color']     ?? '#ffffff';
    $vars['header-bg-affix']     = $h['bg_color_affix'] ?? '#ffffff';

    // Root'ta da set et (xxxl veya ilk dolu değer) - eski sistemle uyumlu
    $h_heights = $h['height'] ?? [];
    $h_heights_affix = $h['height_affix'] ?? [];
    $xxxl_height = $h_heights['xxxl'] ?? (array_values(array_filter($h_heights))[0] ?? '');
    $xxxl_height_affix = $h_heights_affix['xxxl'] ?? (array_values(array_filter($h_heights_affix))[0] ?? '');
    if (!empty($xxxl_height))       $vars['header-height']       = $xxxl_height;
    if (!empty($xxxl_height_affix)) $vars['header-height-affix'] = $xxxl_height_affix;

    foreach ($bps as $bp) {
        if (!empty($h_heights[$bp]))       $mq[$bp]['header-height']       = $h_heights[$bp];
        if (!empty($h_heights_affix[$bp])) $mq[$bp]['header-height-affix'] = $h_heights_affix[$bp];
    }

    // ── Header Border (SCSS'de kullanılıyor) ────────────────────
    $vars['header-border-color'] = $h['border_color'] ?? 'transparent';
    $vars['header-border-style'] = $h['border_style'] ?? 'solid';
    $vars['header-border-width'] = $h['border_width'] ?? '0';

    // ── Navbar padding - root'ta xxxl değeri ─────────────────────
    $xxxl_nb_pad = $navbar['padding']['xxxl'] ?? (array_values(array_filter($navbar['padding'] ?? []))[0] ?? '0');
    $xxxl_nb_pad_affix = $navbar['padding_affix']['xxxl'] ?? (array_values(array_filter($navbar['padding_affix'] ?? []))[0] ?? '0');
    $vars['header-navbar-padding']       = $xxxl_nb_pad;
    $vars['header-navbar-padding-affix'] = $xxxl_nb_pad_affix;
    $vars['header-navbar-bg-affix'] = $navbar['bg_color_affix'] ?? '';
    $vars['header-navbar-align-hr'] = $navbar['align_hr']       ?? 'center';
    $vars['header-navbar-align-vr'] = $navbar['align_vr']       ?? 'center';

    $nb_heights = !empty($navbar['height_header']) ? $h_heights : ($navbar['height'] ?? []);
    $nb_heights_affix = !empty($navbar['height_header']) ? $h_heights_affix : ($navbar['height_affix'] ?? []);

    // Root'ta navbar height
    $xxxl_nb = $nb_heights['xxxl'] ?? (array_values(array_filter($nb_heights))[0] ?? '');
    $xxxl_nb_affix = $nb_heights_affix['xxxl'] ?? (array_values(array_filter($nb_heights_affix))[0] ?? '');
    if (!empty($xxxl_nb))       $vars['header-navbar-height']       = $xxxl_nb;
    if (!empty($xxxl_nb_affix)) $vars['header-navbar-height-affix'] = $xxxl_nb_affix;

    foreach ($bps as $bp) {
        if (!empty($nb_heights[$bp]))       $mq[$bp]['header-navbar-height']       = $nb_heights[$bp];
        if (!empty($nb_heights_affix[$bp])) $mq[$bp]['header-navbar-height-affix'] = $nb_heights_affix[$bp];
        if (!empty($navbar['padding'][$bp]))       $mq[$bp]['header-navbar-padding']       = $navbar['padding'][$bp];
        if (!empty($navbar['padding_affix'][$bp])) $mq[$bp]['header-navbar-padding-affix'] = $navbar['padding_affix'][$bp];
    }

    // ── Nav ─────────────────────────────────────────────────────
    $vars['header-navbar-nav-width']  = $nav['width']  ?? 'auto';
    $vars['header-navbar-nav-margin'] = $nav['margin'] ?? '0';

    $nav_heights = !empty($nav['height_header']) ? $h_heights : ($nav['height'] ?? []);
    // Root'ta nav height ve height-affix
    $xxxl_nav = $nav_heights['xxxl'] ?? (array_values(array_filter($nav_heights))[0] ?? '');
    if (!empty($xxxl_nav)) {
        $vars['header-navbar-nav-height']       = $xxxl_nav;
        $vars['header-navbar-nav-height-affix'] = $xxxl_nav; // affix ayrı yoksa aynı
    }

    foreach ($bps as $bp) {
        if (!empty($nav_heights[$bp])) $mq[$bp]['header-navbar-nav-height'] = $nav_heights[$bp];
    }

    // ── Nav Item ────────────────────────────────────────────────
    $vars['header-navbar-nav-font']               = $ni['font_family']       ?? 'inherit';
    $vars['nav_font']                             = $ni['font_family']       ?? 'inherit';
    $vars['header-navbar-nav-font-weight']        = $ni['font_weight']       ?? '500';
    $vars['header-navbar-nav-font-weight-active'] = $ni['font_weight_active'] ?? '600';
    $vars['header-navbar-nav-font-text-transform']= $ni['text_transform']    ?? 'none';
    $vars['header-navbar-nav-font-letter-spacing']= $ni['letter_spacing']    ?? '0';
    $vars['header-navbar-nav-font-color']         = $ni['color']             ?? '#212529';
    $vars['header-navbar-nav-font-color-hover']   = $ni['color_hover']       ?? '#2271b1';
    $vars['header-navbar-nav-font-color-active']  = $ni['color_active']      ?? '#2271b1';
    $vars['header-navbar-nav-bg-color']           = $ni['bg_color']          ?? '';
    $vars['header-navbar-nav-bg-color-hover']     = $ni['bg_color_hover']    ?? '';
    $vars['header-navbar-nav-bg-color-active']    = $ni['bg_color_active']   ?? '';
    // Nav align (SCSS'de kullanılıyor)
    $vars['header-navbar-nav-align-hr'] = $nav['align_hr'] ?? 'center';
    $vars['header-navbar-nav-align-vr'] = $nav['align_vr'] ?? 'center';

    // Root'ta xxxl nav font-size ve item-padding
    $xxxl_ni_fs  = $ni['font_size']['xxxl']  ?? (array_values(array_filter($ni['font_size'] ?? []))[0] ?? '15px');
    $xxxl_ni_pad = $ni['padding']['xxxl']    ?? (array_values(array_filter($ni['padding'] ?? []))[0] ?? '0 16px');
    $vars['header-navbar-nav-font-size']    = $xxxl_ni_fs;
    $vars['header-navbar-nav-item-padding'] = $xxxl_ni_pad;

    foreach ($bps as $bp) {
        if (!empty($ni['font_size'][$bp])) $mq[$bp]['header-navbar-nav-font-size']    = $ni['font_size'][$bp];
        if (!empty($ni['padding'][$bp]))   $mq[$bp]['header-navbar-nav-item-padding'] = $ni['padding'][$bp];
    }

    // ── Dropdown ────────────────────────────────────────────────
    $vars['header-navbar-nav-dropdown-root-arrow']           = !empty($arrow['arrow']) ? 'block' : 'none';
    $vars['header-navbar-nav-dropdown-root-arrow-top']       = $arrow['top']  ?? '100%';
    $vars['header-navbar-nav-dropdown-root-arrow-left']      = $arrow['left'] ?? '';
    $vars['header-navbar-nav-dropdown-root-arrow-transform'] = 'none';
    $vars['header-navbar-nav-dropdown-align']                = $ddd['align_vr'] ?? 'start';

    if (($ddd['align_vr'] ?? '') === 'center') {
        $vars['header-navbar-nav-dropdown-root-arrow-left']      = '50%';
        $vars['header-navbar-nav-dropdown-root-arrow-transform'] = 'translateX(-50%)';
    }

    $vars['header-navbar-nav-dropdown-bg']            = $ddd['bg_color']      ?? '#ffffff';
    $vars['header-navbar-nav-dropdown-width']         = $ddd['width']         ?? '220px';
    $vars['header-navbar-nav-dropdown-margin']        = $ddd['margin']        ?? '0';
    $vars['header-navbar-nav-dropdown-top']           = $ddd['top']           ?? 'calc(100% + 8px)';
    $vars['header-navbar-nav-dropdown-padding']       = $ddd['padding']       ?? '12px 8px';
    $vars['header-navbar-nav-dropdown-border-radius'] = $ddd['border_radius'] ?? '8px';

    // Dropdown border builder
    $ddd_bw = $ddd['border_width'] ?? '0px';
    $ddd_bs = $ddd['border_style'] ?? 'none';
    $ddd_bc = $ddd['border_color'] ?? '';
    $vars['header-navbar-nav-dropdown-border'] = "{$ddd_bw} {$ddd_bs}" . ($ddd_bc ? " {$ddd_bc}" : '');

    // Dropdown Item
    $vars['header-navbar-nav-dropdown-font']              = $ddi['font_family']      ?? 'inherit';
    $vars['header-navbar-nav-dropdown-font-size']         = $ddi['font_size']        ?? '14px';
    $vars['header-navbar-nav-dropdown-font-color']        = $ddi['color']            ?? '#212529';
    $vars['header-navbar-nav-dropdown-font-color-hover']  = $ddi['color_hover']      ?? '#2271b1';
    $vars['header-navbar-nav-dropdown-font-weight']       = $ddi['font_weight']      ?? '400';
    $vars['header-navbar-nav-dropdown-font-weight-hover'] = $ddi['font_weight_hover'] ?? '500';
    $vars['header-navbar-nav-dropdown-font-text-transform'] = $ddi['text_transform'] ?? 'none';
    $vars['header-navbar-nav-dropdown-item-padding']      = $ddi['padding']          ?? '8px 12px';
    $vars['header-navbar-nav-dropdown-item-bg']           = $ddi['bg_color']         ?? '';
    $vars['header-navbar-nav-dropdown-item-bg-hover']     = $ddi['bg_color_hover']   ?? '#f8f9fa';
    $vars['header-navbar-nav-dropdown-item-border-radius']= $ddi['border_radius']    ?? '6px';

    $ddi_bw = $ddi['border_width'] ?? '0px';
    $ddi_bs = $ddi['border_style'] ?? 'none';
    $ddi_bc = $ddi['border_color'] ?? '';
    $vars['header-navbar-nav-dropdown-item-border'] = "{$ddi_bw} {$ddi_bs}" . ($ddi_bc ? " {$ddi_bc}" : '');

    // ── Logo ────────────────────────────────────────────────────
    $vars['header-navbar-logo-color']       = $logo['color']       ?? '';
    $vars['header-navbar-logo-color-affix'] = $logo['color_affix'] ?? '';
    $vars['header-navbar-logo-align-hr']    = $logo['align_hr']    ?? 'start';
    $vars['header-navbar-logo-align-vr']    = $logo['align_vr']    ?? 'center';

    // Root'ta xxxl (veya ilk dolu) padding değerini set et
    $logo_padding_xxxl       = $logo['padding']['xxxl']       ?? (array_values(array_filter($logo['padding'] ?? []))[0] ?? '0');
    $logo_padding_affix_xxxl = $logo['padding_affix']['xxxl'] ?? (array_values(array_filter($logo['padding_affix'] ?? []))[0] ?? '0');

    if (!empty($logo_padding_xxxl) && function_exists('css_parse_spacing_value')) {
        $vars['header-navbar-logo-padding'] = $logo_padding_xxxl;
        foreach (['top', 'bottom', 'left', 'right'] as $pos) {
            $vars["header-navbar-logo-padding-{$pos}"] = css_parse_spacing_value($logo_padding_xxxl, $pos);
        }
    }
    if (!empty($logo_padding_affix_xxxl) && function_exists('css_parse_spacing_value')) {
        $vars['header-navbar-logo-padding-affix'] = $logo_padding_affix_xxxl;
        foreach (['top', 'bottom', 'left', 'right'] as $pos) {
            $vars["header-navbar-logo-padding-affix-{$pos}"] = css_parse_spacing_value($logo_padding_affix_xxxl, $pos);
        }
    }

    foreach ($bps as $bp) {
        if (!empty($logo['padding'][$bp])) {
            $mq[$bp]['header-navbar-logo-padding'] = $logo['padding'][$bp];
            // top/bottom/left/right ayrı variable'lar (eski sistemle uyumlu)
            if (function_exists('css_parse_spacing_value')) {
                foreach (['top', 'bottom', 'left', 'right'] as $pos) {
                    $mq[$bp]["header-navbar-logo-padding-{$pos}"] = css_parse_spacing_value($logo['padding'][$bp], $pos);
                }
            }
        }
        if (!empty($logo['padding_affix'][$bp])) {
            $mq[$bp]['header-navbar-logo-padding-affix'] = $logo['padding_affix'][$bp];
            if (function_exists('css_parse_spacing_value')) {
                foreach (['top', 'bottom', 'left', 'right'] as $pos) {
                    $mq[$bp]["header-navbar-logo-padding-affix-{$pos}"] = css_parse_spacing_value($logo['padding_affix'][$bp], $pos);
                }
            }
        }
    }

    // ── Logo images (WP logo / ACF logo) ────────────────────────
    $logo_map = [
        'logo'           => 'logo',
        'logo-affix'     => 'logo_affix',
        'logo-footer'    => 'logo_footer',
        'logo-mobile'    => 'logo_mobile',
        'logo-not-found' => 'logo_not_found',
        'icon'           => 'logo_icon',
        'marker'         => 'logo_marker',
    ];
    foreach ($logo_map as $var_key => $acf_key) {
        if (function_exists('get_field')) {
            $field_data = get_field($acf_key, 'options');
            if ($field_data) {
                $url = is_array($field_data) ? ($field_data['url'] ?? '') : $field_data;
                if (!empty($url)) {
                    // get_clean_root_path ile relative path'e çevir (eski sistemle uyumlu)
                    $clean_url = function_exists('get_clean_root_path') ? get_clean_root_path($url) : $url;
                    $vars[$var_key] = 'url(' . $clean_url . ')';
                }
            }
        }
    }

    // ── Header Tools ────────────────────────────────────────────
    $ht_heights = !empty($htg['height_header']) ? ($h['height'] ?? []) : ($htg['height'] ?? []);
    $ht_heights_affix = !empty($htg['height_header']) ? ($h['height_affix'] ?? []) : ($htg['height_affix'] ?? []);

    // Root'ta xxxl header-tools değerleri
    $xxxl_ht = $ht_heights['xxxl'] ?? (array_values(array_filter($ht_heights))[0] ?? '');
    $xxxl_ht_affix = $ht_heights_affix['xxxl'] ?? (array_values(array_filter($ht_heights_affix))[0] ?? '');
    $xxxl_ht_gap = $htg['gap']['xxxl'] ?? (array_values(array_filter($htg['gap'] ?? []))[0] ?? '16px');
    if (!empty($xxxl_ht))       $vars['header-tools-height']       = $xxxl_ht;
    if (!empty($xxxl_ht_affix)) $vars['header-tools-height-affix'] = $xxxl_ht_affix;
    $vars['header-tools-item-gap'] = $xxxl_ht_gap;

    foreach ($bps as $bp) {
        if (!empty($ht_heights[$bp]))       $mq[$bp]['header-tools-height']       = $ht_heights[$bp];
        if (!empty($ht_heights_affix[$bp])) $mq[$bp]['header-tools-height-affix'] = $ht_heights_affix[$bp];
        if (!empty($htg['gap'][$bp]))       $mq[$bp]['header-tools-item-gap']     = $htg['gap'][$bp];
    }

    $hts = $ht['social']   ?? [];
    $hti = $ht['icons']    ?? [];
    $htl = $ht['link']     ?? [];
    $htb = $ht['button']   ?? [];
    $htla= $ht['language'] ?? [];
    $htt = $ht['toggler']  ?? [];
    $htc = $ht['counter']  ?? [];

    if (!empty($hts['font_family'])) $vars['header-social-font']         = $hts['font_family'];
    if (!empty($hts['font_size']))   $vars['header-social-font-size']     = $hts['font_size'];
    $vars['header-social-color']       = $hts['color']       ?? '';
    $vars['header-social-color-hover'] = $hts['color_hover'] ?? '';
    if (!empty($hts['gap']))         $vars['header-social-gap']           = $hts['gap'];

    if (!empty($hti['font_family'])) $vars['header-icon-font']            = $hti['font_family'];
    if (!empty($hti['font_size']))   $vars['header-icon-font-size']       = $hti['font_size'];
    $vars['header-icon-color']         = $hti['color']       ?? '';
    $vars['header-icon-color-hover']   = $hti['color_hover'] ?? '';
    $vars['header-icon-dot-color']     = $hti['dot_color']   ?? '';

    if (!empty($htl['font_family'])) $vars['header-link-font']            = $htl['font_family'];
    if (!empty($htl['font_size']))   $vars['header-link-font-size']       = $htl['font_size'];
    if (!empty($htl['font_weight'])) $vars['header-link-font-weight']     = $htl['font_weight'];
    $vars['header-link-color']         = $htl['color']        ?? '';
    $vars['header-link-color-hover']   = $htl['color_hover']  ?? '';
    $vars['header-link-color-active']  = $htl['color_active'] ?? '';

    if (!empty($htb['font_family'])) $vars['header-btn-font']             = $htb['font_family'];
    if (!empty($htb['font_size']))   $vars['header-btn-font-size']        = $htb['font_size'];
    if (!empty($htb['font_weight'])) $vars['header-btn-font-weight']      = $htb['font_weight'];

    if (!empty($htla['font_family'])) $vars['header-language-font']       = $htla['font_family'];
    if (!empty($htla['font_size']))   $vars['header-language-font-size']  = $htla['font_size'];
    if (!empty($htla['font_weight'])) $vars['header-language-font-weight']= $htla['font_weight'];
    $vars['header-language-color']       = $htla['color']        ?? '';
    $vars['header-language-color-hover'] = $htla['color_hover']  ?? '';
    $vars['header-language-color-active']= $htla['color_active'] ?? '';

    $vars['header-navbar-toggler-color']       = $htt['color']       ?? '';
    $vars['header-navbar-toggler-color-hover'] = $htt['color_hover'] ?? '';

    $vars['header-navbar-toggler-color']       = $htt['color']       ?? '';
    $vars['header-navbar-toggler-color-hover'] = $htt['color_hover'] ?? '';

    $vars['notification-count-color']    = $htc['color']    ?? '';
    $vars['notification-count-bg-color'] = $htc['bg_color'] ?? '';

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => $mq];
}
