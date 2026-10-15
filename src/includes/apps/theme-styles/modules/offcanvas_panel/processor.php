<?php
if (!defined('ABSPATH')) exit;

/**
 * Offcanvas Panel Processor
 * BS5 uyumlu variable adları.
 * https://getbootstrap.com/docs/5.3/components/offcanvas/
 */
function theme_styles_process_offcanvas_panel($data, $generator) {
    $op   = $data['offcanvas_panel'] ?? [];
    $hd   = $op['header']   ?? [];
    $ft   = $op['footer']   ?? [];
    $bk   = $op['backdrop'] ?? [];
    $anim = $op['animation']?? [];
    $vars = [];

    // ── Background ───────────────────────────────────────────────
    $bg_type  = $op['bg_type']      ?? 'color';
    $bg_color = $op['bg_color']     ?? '';
    $bg_grad  = $op['bg_gradient']  ?? '';
    $bg_img   = $op['bg_image_url'] ?? '';
    $bg_size  = $op['bg_size']      ?? 'cover';
    $bg_pos   = $op['bg_position']  ?? 'center center';
    $bg_rep   = $op['bg_repeat']    ?? 'no-repeat';
    $bg_att   = $op['bg_attachment']?? 'scroll';

    if ( $bg_type === 'gradient' && ! empty( $bg_grad ) ) {
        // Gradient — --offcanvas-bg-gradient içine gradient string
        $vars['offcanvas-bg']          = 'transparent';
        $vars['offcanvas-bg-gradient'] = $bg_grad;
    } elseif ( $bg_type === 'image' && ! empty( $bg_img ) ) {
        // Image — --offcanvas-bg-gradient içine url() yazıyoruz
        // SCSS: background-image: var(--offcanvas-bg-gradient, none) — bu hem gradient hem image'ı kapsar
        $vars['offcanvas-bg']          = 'transparent';
        $vars['offcanvas-bg-gradient'] = "url({$bg_img})";
        $vars['offcanvas-bg-size']     = $bg_size;
        $vars['offcanvas-bg-position'] = $bg_pos;
        $vars['offcanvas-bg-repeat']   = $bg_rep;
        $vars['offcanvas-bg-attachment'] = $bg_att;
    } elseif ( ! empty( $bg_color ) ) {
        $vars['offcanvas-bg']          = $bg_color;
        $vars['offcanvas-bg-gradient'] = 'none';
        // RGB versiyonu
        if ( $generator && method_exists( $generator, 'hexToRgb' ) ) {
            $rgb = $generator->hexToRgb( $bg_color );
            if ( $rgb ) $vars['offcanvas-bg-rgb'] = $rgb;
        }
    }
    if ( ! empty( $op['color'] ) ) $vars['offcanvas-color'] = $op['color'];

    // ── Layout ───────────────────────────────────────────────────
    // BS5: --offcanvas-width, --offcanvas-padding-x, --offcanvas-padding-y, --offcanvas-border-color, --offcanvas-border-width, --offcanvas-box-shadow
    if ( ! empty($op['width']) )          $vars['offcanvas-width']        = $op['width'];
    if ( ! empty($op['padding_x']) )      $vars['offcanvas-padding-x']    = $op['padding_x'];
    if ( ! empty($op['padding_y']) )      $vars['offcanvas-padding-y']    = $op['padding_y'];
    if ( ! empty($op['border_color']) )   $vars['offcanvas-border-color'] = $op['border_color'];
    if ( ! empty($op['border_width']) )   $vars['offcanvas-border-width'] = $op['border_width'];
    if ( ! empty($op['box_shadow']) )     $vars['offcanvas-box-shadow']   = $op['box_shadow'];
    if ( ! empty($op['title_line_height']) ) $vars['offcanvas-title-line-height'] = $op['title_line_height'];

    // ── Backdrop ─────────────────────────────────────────────────
    // BS5: --backdrop-bg, --backdrop-opacity (modal ile paylaşılan)
    if ( ! empty($bk['color']) )   $vars['backdrop-bg']      = $bk['color'];
    if ( ! empty($bk['opacity']) ) $vars['backdrop-opacity'] = $bk['opacity'];

    // ── Animation / Transition ───────────────────────────────────
    // BS5: --offcanvas-transition
    $anim_duration = ! empty($anim['duration']) ? $anim['duration'] : '0.3s';
    if ( ! empty($anim['duration']) ) {
        $vars['offcanvas-transition'] = "transform {$anim_duration} ease-in-out";
    }

    // ── Header ───────────────────────────────────────────────────
    // custom variables — BS5'te offcanvas-header için ayrı var yok
    if ( ! empty($hd['bg_color']) )     $vars['offcanvas-header-bg']          = $hd['bg_color'];
    if ( ! empty($hd['color']) )        $vars['offcanvas-header-color']       = $hd['color'];
    if ( ! empty($hd['padding']) )      $vars['offcanvas-header-padding']     = $hd['padding'];
    if ( ! empty($hd['border_color']) ) $vars['offcanvas-header-border-color']= $hd['border_color'];
    if ( ! empty($hd['border_width']) ) $vars['offcanvas-header-border-width']= $hd['border_width'];
    if ( ! empty($hd['font_size']) )    $vars['offcanvas-header-font-size']   = $hd['font_size'];
    if ( ! empty($hd['font_weight']) )  $vars['offcanvas-header-font-weight'] = $hd['font_weight'];

    // ── Footer ───────────────────────────────────────────────────
    // custom variables
    if ( ! empty($ft['bg_color']) )     $vars['offcanvas-footer-bg']          = $ft['bg_color'];
    if ( ! empty($ft['color']) )        $vars['offcanvas-footer-color']       = $ft['color'];
    if ( ! empty($ft['padding']) )      $vars['offcanvas-footer-padding']     = $ft['padding'];
    if ( ! empty($ft['border_color']) ) $vars['offcanvas-footer-border-color']= $ft['border_color'];
    if ( ! empty($ft['border_width']) ) $vars['offcanvas-footer-border-width']= $ft['border_width'];

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
