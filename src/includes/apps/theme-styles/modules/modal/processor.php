<?php
if (!defined('ABSPATH')) exit;

/**
 * Modal Processor
 * BS5 uyumlu variable adları kullanılıyor.
 * https://getbootstrap.com/docs/5.3/components/modal/
 */
function theme_styles_process_modal($data, $generator) {
    $m   = $data['modal'] ?? [];
    $hd  = $m['header']   ?? [];
    $bd  = $m['body']     ?? [];
    $ft  = $m['footer']   ?? [];
    $bk  = $m['backdrop'] ?? [];
    $anim= $m['animation']?? [];
    $close = $m['close_btn'] ?? [];
    $vars = [];

    // ── Content Background ───────────────────────────────────────
    // BS5: --modal-bg, --modal-color
    $bg_type = $m['bg_type']     ?? 'color';
    $bg_color= $m['bg_color']    ?? '';
    $bg_grad = $m['bg_gradient'] ?? '';
    if ( ! empty($bg_color) || $bg_type === 'gradient' ) {
        $vars['modal-bg'] = $bg_type === 'gradient' ? 'transparent' : $bg_color;
        if ( $bg_type === 'gradient' && ! empty($bg_grad) ) {
            $vars['modal-bg-gradient'] = $bg_grad;
        }
    }
    if ( $bg_type === 'image' && ! empty($m['bg_image_url']) ) {
        $vars['modal-bg']       = 'transparent';
        $vars['modal-bg-image'] = 'url(' . $m['bg_image_url'] . ')';
    }
    if ( ! empty($m['color']) ) $vars['modal-color'] = $m['color'];

    // ── Content Border ───────────────────────────────────────────
    // BS5: --modal-border-color, --modal-border-width, --modal-border-radius, --modal-box-shadow
    if ( ! empty($m['border_color']) )  $vars['modal-border-color']  = $m['border_color'];
    if ( ! empty($m['border_width']) )  $vars['modal-border-width']  = $m['border_width'];
    if ( ! empty($m['border_radius']) ) $vars['modal-border-radius'] = $m['border_radius'];
    if ( ! empty($m['box_shadow']) )    $vars['modal-box-shadow']    = $m['box_shadow'];

    // ── Backdrop ─────────────────────────────────────────────────
    // BS5: --backdrop-bg, --backdrop-opacity (modal ve offcanvas paylaşıyor)
    if ( ! empty($bk['color']) )   $vars['backdrop-bg']      = $bk['color'];
    if ( ! empty($bk['opacity']) ) $vars['backdrop-opacity'] = $bk['opacity'];

    // ── Animation / Transition ───────────────────────────────────
    $anim_type     = $anim['type']        ?? '';
    $anim_duration = ! empty($anim['duration_raw']) ? $anim['duration_raw'] . 's' : '0.3s';
    if ( ! empty($anim_type) || ! empty($anim['duration_raw']) ) {
        $vars['modal-transition'] = "transform {$anim_duration} ease-out";
        switch ( $anim_type ) {
            case 'slide-up':   $vars['modal-fade-transform'] = 'translate(0, -50px)'; break;
            case 'slide-down': $vars['modal-fade-transform'] = 'translate(0, 50px)';  break;
            case 'zoom':       $vars['modal-fade-transform'] = 'scale(0.85)';         break;
            case 'zoom-in':    $vars['modal-fade-transform'] = 'scale(1.15)';         break;
            case 'flip':       $vars['modal-fade-transform'] = 'rotateX(-15deg)';     break;
            default:           $vars['modal-fade-transform'] = 'translate(0, -50px)'; break;
        }
    }

    // ── Close Button ─────────────────────────────────────────────
    // custom variables — BS5'te ayrı yok
    if ( ! empty($close['color']) )       $vars['modal-close-color']       = $close['color'];
    if ( ! empty($close['color_hover']) ) $vars['modal-close-color-hover'] = $close['color_hover'];
    if ( ! empty($close['size']) )        $vars['modal-close-size']        = $close['size'];
    if ( ! empty($close['opacity']) )     $vars['modal-close-opacity']     = $close['opacity'];

    // ── Header ───────────────────────────────────────────────────
    // BS5: --modal-header-padding, --modal-header-border-color, --modal-header-border-width
    if ( ! empty($hd['bg_color']) )      $vars['modal-header-bg']           = $hd['bg_color']; // custom
    if ( ! empty($hd['color']) )         $vars['modal-header-color']        = $hd['color'];    // custom
    if ( ! empty($hd['padding']) )       $vars['modal-header-padding']      = $hd['padding'];
    if ( ! empty($hd['border_color']) )  $vars['modal-header-border-color'] = $hd['border_color'];
    if ( ! empty($hd['border_width']) )  $vars['modal-header-border-width'] = $hd['border_width'];
    if ( ! empty($hd['font_size']) )     $vars['modal-header-font-size']    = $hd['font_size'];   // custom
    if ( ! empty($hd['font_weight']) )   $vars['modal-header-font-weight']  = $hd['font_weight']; // custom
    if ( ! empty($hd['title_line_height']) ) $vars['modal-title-line-height'] = $hd['title_line_height'];

    // ── Body ─────────────────────────────────────────────────────
    // BS5: --modal-padding (body/footer için ortak)
    if ( ! empty($bd['padding']) )   $vars['modal-padding']      = $bd['padding'];
    if ( ! empty($bd['bg_color']) )  $vars['modal-body-bg']      = $bd['bg_color'];  // custom
    if ( ! empty($bd['color']) )     $vars['modal-body-color']   = $bd['color'];     // custom
    if ( ! empty($bd['font_size']) ) $vars['modal-body-font-size'] = $bd['font_size']; // custom

    // ── Footer ───────────────────────────────────────────────────
    // BS5: --modal-footer-bg, --modal-footer-border-color, --modal-footer-border-width, --modal-footer-gap
    if ( ! empty($ft['bg_color']) )     $vars['modal-footer-bg']            = $ft['bg_color'];
    if ( ! empty($ft['color']) )        $vars['modal-footer-color']         = $ft['color'];           // custom
    if ( ! empty($ft['padding']) )      $vars['modal-footer-padding']       = $ft['padding'];         // custom
    if ( ! empty($ft['border_color']) ) $vars['modal-footer-border-color']  = $ft['border_color'];
    if ( ! empty($ft['border_width']) ) $vars['modal-footer-border-width']  = $ft['border_width'];
    if ( ! empty($ft['gap']) )          $vars['modal-footer-gap']           = $ft['gap'];

    return ['variables' => $vars, 'mobile' => [], 'media_queries' => []];
}
