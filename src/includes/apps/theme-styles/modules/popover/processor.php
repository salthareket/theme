<?php
if (!defined('ABSPATH')) exit;

/**
 * Popover & Tooltip Processor
 * BS5 uyumlu variable adları.
 * https://getbootstrap.com/docs/5.3/components/popovers/
 * https://getbootstrap.com/docs/5.3/components/tooltips/
 */
function theme_styles_process_popover($data, $generator) {
    $p   = $data['popover']  ?? [];
    $phd = $p['header']      ?? [];
    $pbd = $p['body']        ?? [];
    $tt  = $data['tooltip']  ?? [];
    $vars = [];

    // ── Popover ──────────────────────────────────────────────────
    // BS5: --popover-bg, --popover-max-width, --popover-font-size
    //      --popover-border-width/color/radius, --popover-box-shadow
    if ( ! empty($p['bg_color']) )      $vars['popover-bg']            = $p['bg_color'];
    if ( ! empty($p['max_width']) )     $vars['popover-max-width']     = $p['max_width'];
    if ( ! empty($p['font_size']) )     $vars['popover-font-size']     = $p['font_size'];
    if ( ! empty($p['border_color']) )  $vars['popover-border-color']  = $p['border_color'];
    if ( ! empty($p['border_width']) )  $vars['popover-border-width']  = $p['border_width'];
    if ( ! empty($p['border_radius']) ) $vars['popover-border-radius'] = $p['border_radius'];
    if ( ! empty($p['box_shadow']) )    $vars['popover-box-shadow']    = $p['box_shadow'];

    // Arrow
    if ( ! empty($p['arrow_width']) )   $vars['popover-arrow-width']   = $p['arrow_width'];
    if ( ! empty($p['arrow_height']) )  $vars['popover-arrow-height']  = $p['arrow_height'];

    // Header — BS5: --popover-header-bg, --popover-header-color, --popover-header-padding-x/y, --popover-header-font-size
    if ( ! empty($phd['bg_color']) )  $vars['popover-header-bg']        = $phd['bg_color'];
    if ( ! empty($phd['color']) )     $vars['popover-header-color']     = $phd['color'];
    if ( ! empty($phd['font_size']) ) $vars['popover-header-font-size'] = $phd['font_size'];
    if ( ! empty($phd['padding_x']) ) $vars['popover-header-padding-x'] = $phd['padding_x'];
    if ( ! empty($phd['padding_y']) ) $vars['popover-header-padding-y'] = $phd['padding_y'];

    // Body — BS5: --popover-body-color, --popover-body-padding-x/y
    if ( ! empty($pbd['color']) )     $vars['popover-body-color']     = $pbd['color'];
    if ( ! empty($pbd['padding_x']) ) $vars['popover-body-padding-x'] = $pbd['padding_x'];
    if ( ! empty($pbd['padding_y']) ) $vars['popover-body-padding-y'] = $pbd['padding_y'];

    // ── Tooltip ──────────────────────────────────────────────────
    // BS5: --tooltip-bg, --tooltip-color, --tooltip-font-size, --tooltip-border-radius
    //      --tooltip-opacity, --tooltip-padding-x/y, --tooltip-max-width
    //      --tooltip-arrow-width/height
    if ( ! empty($tt['bg_color']) )      $vars['tooltip-bg']            = $tt['bg_color'];
    if ( ! empty($tt['color']) )         $vars['tooltip-color']         = $tt['color'];
    if ( ! empty($tt['font_size']) )     $vars['tooltip-font-size']     = $tt['font_size'];
    if ( ! empty($tt['border_radius']) ) $vars['tooltip-border-radius'] = $tt['border_radius'];
    if ( ! empty($tt['opacity']) )       $vars['tooltip-opacity']       = $tt['opacity'];
    if ( ! empty($tt['padding_x']) )     $vars['tooltip-padding-x']     = $tt['padding_x'];
    if ( ! empty($tt['padding_y']) )     $vars['tooltip-padding-y']     = $tt['padding_y'];
    if ( ! empty($tt['max_width']) )     $vars['tooltip-max-width']     = $tt['max_width'];
    if ( ! empty($tt['arrow_width']) )   $vars['tooltip-arrow-width']   = $tt['arrow_width'];
    if ( ! empty($tt['arrow_height']) )  $vars['tooltip-arrow-height']  = $tt['arrow_height'];

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
