<?php

/**
 * TinyMCE Editor — Bootstrap style select, font weights, line heights, margins.
 */

add_filter('mce_buttons_2', function($buttons) {
    array_unshift($buttons, 'styleselect');
    return $buttons;
});

add_filter('tiny_mce_before_init', function($init_array) {
    $new_styles = [];

    // ─── Button Styles ──────────────────────────────────────
    $buttons = [];
    if (Data::has('mce_text_colors')) {
        foreach (Data::get('mce_text_colors') as $value) {
            $slug      = strtolower($value);
            $buttons[] = ['title' => 'btn-' . $slug, 'selector' => 'a', 'classes' => 'btn btn-' . $slug . ' btn-extended'];
        }
    }
    $new_styles[] = ['title' => 'Button', 'items' => $buttons];

    // ─── Base Styles ────────────────────────────────────────
    $base_styles = [
        ['title' => 'List Unstyled',   'selector' => 'ul, ol', 'classes' => 'list-unstyled ms-4'],
        ['title' => 'Table Bordered',  'selector' => 'table',  'classes' => 'table-bordered'],
        ['title' => 'Table Striped',   'selector' => 'table',  'classes' => 'table-striped'],
        ['title' => 'Text - Slab',     'selector' => '*',      'classes' => 'slab-text-container'],
        ['title' => 'Small',           'inline'   => 'small'],
    ];
    $new_styles[] = ['title' => 'Styles', 'items' => $base_styles];

    // ─── Breakpoint Typography ──────────────────────────────
    $breakpoints = Data::get('breakpoints');
    if ($breakpoints) {
        $typography    = [];
        $theme_styles  = function_exists('acf_get_theme_styles') ? acf_get_theme_styles() : [];
        if (isset($theme_styles['typography'])) {
            $typography = $theme_styles['typography'];
        }

        $title_classes = [];
        $text_classes  = [];

        foreach ($breakpoints as $key => $bp) {
            $title_size = (isset($typography['title'][$key]['value']) && $typography['title'][$key]['value'] !== '')
                ? ' - ' . $typography['title'][$key]['value'] . $typography['title'][$key]['unit'] : '';
            $title_classes[] = ['title' => 'Title - ' . $key . $title_size, 'selector' => 'h1,h2,h3,h4,h5,h6', 'classes' => 'title-' . $key];

            $text_size = (isset($typography['text'][$key]['value']) && $typography['text'][$key]['value'] !== '')
                ? ' - ' . $typography['text'][$key]['value'] . $typography['text'][$key]['unit'] : '';
            $text_classes[] = ['title' => 'Text - ' . $key . $text_size, 'selector' => 'p', 'classes' => 'text-' . $key];
        }

        $new_styles[] = ['title' => 'Title', 'items' => $title_classes];
        $new_styles[] = ['title' => 'Text',  'items' => $text_classes];
    }

    // ─── Font Weight ────────────────────────────────────────
    $fw_items = [];
    foreach (['normal', 100, 200, 300, 400, 500, 600, 700, 800, 900] as $fw) {
        $fw_items[] = ['title' => 'Font Weight - ' . $fw, 'selector' => '*', 'classes' => 'fw-' . $fw];
    }
    $new_styles[] = ['title' => 'Font Weight', 'items' => $fw_items];

    // ─── Line Height ────────────────────────────────────────
    $lh_items = [];
    foreach (['1', 'base', 'sm', 'md', 'lg'] as $lh) {
        $lh_items[] = ['title' => 'Line Height - ' . $lh, 'selector' => '*', 'classes' => 'lh-' . $lh];
    }
    $new_styles[] = ['title' => 'Line Height', 'items' => $lh_items];

    // ─── Margin ─────────────────────────────────────────────
    $margin_items = [];
    foreach (['mt-5', 'mt-4', 'mt-3', 'mt-2', 'mt-1', 'm-0', 'mb-5', 'mb-4', 'mb-3', 'mb-2', 'mb-1'] as $m) {
        $margin_items[] = ['title' => 'Margin - ' . $m, 'selector' => 'h1,h2,h3,h4,h5,h6,p', 'classes' => $m];
    }
    $new_styles[] = ['title' => 'Margin', 'items' => $margin_items];

    // ─── Extra MCE Styles ───────────────────────────────────
    $mce_styles = Data::get('mce_styles');
    if (is_array($mce_styles) && !empty($mce_styles)) {
        $new_styles[] = ['title' => 'Extras', 'items' => $mce_styles];
    }

    // ─── Text Colors ────────────────────────────────────────
    $mce_text_colors = Data::get('mce_text_colors');
    if ($mce_text_colors) {
        $pairs = [];
        foreach ($mce_text_colors as $hex => $name) {
            $pairs[] = '"' . str_replace('#', '', $hex) . '"';
            $pairs[] = '"' . $name . '"';
        }
        $init_array['textcolor_map']  = '[' . implode(', ', $pairs) . ']';
        $init_array['textcolor_rows'] = 1;
    }

    $init_array['style_formats_merge'] = true;
    $init_array['style_formats']       = json_encode($new_styles);

    return $init_array;
});

add_filter('mce_buttons', function($buttons) {
    $buttons[] = 'letter_spacing_button';
    return $buttons;
});
