<?php
/**
 * Forms Module Template
 *
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.2.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
$fi   = $data['input']      ?? [];
$fl   = $data['label']      ?? [];
$fv   = $data['validation'] ?? [];
$fs   = $data['sizes']      ?? [];
$fc   = $data['checks']     ?? [];

$size_defaults = [
    'sm' => ['font_size' => '13px', 'padding_y' => '6px',  'padding_x' => '10px', 'border_radius' => '6px'],
    'md' => ['font_size' => '15px', 'padding_y' => '10px', 'padding_x' => '14px', 'border_radius' => '8px'],
    'lg' => ['font_size' => '17px', 'padding_y' => '14px', 'padding_x' => '18px', 'border_radius' => '10px'],
];

// 4-side border active?
$border_4side       = !empty($fi['border_4side']);
$border_4side_focus = !empty($fi['border_4side_focus']);

// Helper: border builder HTML
function ts_forms_border_builder(string $prefix, array $fi, bool $is4side, string $default_color = '#dddddd'): void {
    $sides = ['top','right','bottom','left'];
    $bw = $fi[$prefix . '_width']  ?? '1px';
    $bs = $fi[$prefix . '_style']  ?? 'solid';
    $bc = $fi[$prefix . '_color']  ?? $default_color;
    ?>
    <div class="ts-forms-border-wrap">
        <!-- Shorthand row - per-side açıkken gizlenir -->
        <div class="ts-border-builder ts-forms-border-shorthand" <?php echo $is4side ? 'style="display:none;"' : ''; ?>>
            <input type="text" class="ts-field-input" data-field="input.<?php echo $prefix; ?>_width" value="<?php echo esc_attr($bw); ?>" placeholder="1px" />
            <select class="ts-field-select" data-field="input.<?php echo $prefix; ?>_style">
                <?php foreach (['solid','dashed','dotted','none'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php selected($bs, $s); ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="ts-field-input ts-color-input" data-field="input.<?php echo $prefix; ?>_color" value="<?php echo esc_attr($bc); ?>" />
        </div>
        <!-- 4-side toggle -->
        <label class="ts-forms-4side-toggle" style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;color:var(--ts-gray-600);cursor:pointer;">
            <input type="checkbox" class="ts-forms-4side-cb" data-target="ts-forms-4side-<?php echo $prefix; ?>"
                   data-field="input.<?php echo $prefix; ?>_4side" value="1"
                   <?php checked($is4side); ?> />
            <?php _e('Per side', 'theme-styles'); ?>
        </label>
        <!-- 4-side panel -->
        <div class="ts-forms-4side-panel" id="ts-forms-4side-<?php echo $prefix; ?>" style="<?php echo $is4side ? '' : 'display:none;'; ?>margin-top:10px;">
            <?php foreach ($sides as $side):
                $sw = $fi[$prefix . '_' . $side . '_width'] ?? $bw;
                $ss = $fi[$prefix . '_' . $side . '_style'] ?? $bs;
                $sc = $fi[$prefix . '_' . $side . '_color'] ?? $bc;
            ?>
            <div style="margin-bottom:8px;">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--ts-gray-500);margin-bottom:4px;"><?php echo ucfirst($side); ?></div>
                <div class="ts-border-builder">
                    <input type="text" class="ts-field-input" data-field="input.<?php echo $prefix . '_' . $side; ?>_width" value="<?php echo esc_attr($sw); ?>" placeholder="1px" />
                    <select class="ts-field-select" data-field="input.<?php echo $prefix . '_' . $side; ?>_style">
                        <?php foreach (['solid','dashed','dotted','none'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php selected($ss, $s); ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="ts-field-input ts-color-input" data-field="input.<?php echo $prefix . '_' . $side; ?>_color" value="<?php echo esc_attr($sc); ?>" />
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
?>

<div class="ts-module-forms">

    <!-- ── Live Preview ──────────────────────────────────────── -->
    <div class="ts-section ts-sticky-preview">
        <h3 class="ts-section-title"><?php _e('Live Preview', 'theme-styles'); ?></h3>

        <div id="ts-forms-preview-area" style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;padding:4px 0;">

            <div style="flex:1;min-width:180px;">
                <label class="ts-fp-label"><?php _e('Label', 'theme-styles'); ?></label>
                <input type="text" class="ts-fp-input ts-fp-focusable" placeholder="<?php esc_attr_e('Click to see focus state...', 'theme-styles'); ?>" />
            </div>

            <div style="flex:1;min-width:180px;">
                <label class="ts-fp-label"><?php _e('Select', 'theme-styles'); ?></label>
                <select class="ts-fp-input ts-fp-focusable">
                    <option><?php _e('Option 1', 'theme-styles'); ?></option>
                    <option><?php _e('Option 2', 'theme-styles'); ?></option>
                </select>
            </div>

            <div style="flex:1;min-width:180px;">
                <label class="ts-fp-label"><?php _e('Textarea', 'theme-styles'); ?></label>
                <textarea class="ts-fp-input ts-fp-focusable" rows="3" placeholder="<?php esc_attr_e('Textarea...', 'theme-styles'); ?>"></textarea>
            </div>

            <!-- Sizes -->
            <div style="flex:0 0 100%;display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:4px;">
                <div style="flex:0 0 auto;font-size:11px;font-weight:600;color:var(--ts-gray-500);text-transform:uppercase;">Sizes:</div>
                <input type="text" class="ts-fp-input ts-fp-sm ts-fp-focusable" placeholder="Small"  style="flex:1;min-width:80px;" />
                <input type="text" class="ts-fp-input ts-fp-md ts-fp-focusable" placeholder="Medium" style="flex:1;min-width:100px;" />
                <input type="text" class="ts-fp-input ts-fp-lg ts-fp-focusable" placeholder="Large"  style="flex:1;min-width:120px;" />
            </div>

            <!-- Checks -->
            <div style="flex:0 0 100%;display:flex;gap:24px;flex-wrap:wrap;align-items:center;margin-top:4px;">
                <label class="ts-fp-check-label"><input type="checkbox" class="ts-fp-check" checked /> <?php _e('Checkbox', 'theme-styles'); ?></label>
                <label class="ts-fp-check-label"><input type="radio" class="ts-fp-radio" name="ts_fp_radio" checked /> <?php _e('Radio 1', 'theme-styles'); ?></label>
                <label class="ts-fp-check-label"><input type="radio" class="ts-fp-radio" name="ts_fp_radio" /> <?php _e('Radio 2', 'theme-styles'); ?></label>
                <label class="ts-fp-switch-label">
                    <span class="ts-fp-switch-track"><span class="ts-fp-switch-thumb"></span></span>
                    <?php _e('Switch', 'theme-styles'); ?>
                </label>
            </div>

        </div>
        <style id="ts-forms-preview-css"></style>
    </div>

    <!-- ── Input / Select / Textarea ────────────────────────── -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Input / Select / Textarea', 'theme-styles'); ?></h3>

        <div class="ts-states-grid">

            <!-- Default -->
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Default', 'theme-styles'); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="input.color" value="<?php echo esc_attr($fi['color'] ?? '#666666'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Background', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="input.bg_color" value="<?php echo esc_attr($fi['bg_color'] ?? '#ffffff'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Border', 'theme-styles'); ?></label>
                        <?php ts_forms_border_builder('border', $fi, $border_4side, '#dddddd'); ?>
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="input.border_radius" value="<?php echo esc_attr($fi['border_radius'] ?? '8px'); ?>" placeholder="8px" />
                    </div>
                </div>
            </div>

            <!-- Focus -->
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Focus', 'theme-styles'); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Text Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="input.color_focus" value="<?php echo esc_attr($fi['color_focus'] ?? '#333333'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Background', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="input.bg_color_focus" value="<?php echo esc_attr($fi['bg_color_focus'] ?? '#ffffff'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Border', 'theme-styles'); ?></label>
                        <?php ts_forms_border_builder('border_focus', $fi, $border_4side_focus, '#aaaaaa'); ?>
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Box Shadow', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="input.focus_shadow" value="<?php echo esc_attr($fi['focus_shadow'] ?? 'none'); ?>" placeholder="none" />
                    </div>
                </div>
            </div>

            <!-- Placeholder -->
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php _e('Placeholder', 'theme-styles'); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input ts-color-input" data-field="input.placeholder_color" value="<?php echo esc_attr($fi['placeholder_color'] ?? '#a3a3a3'); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                        <select class="ts-field-select" data-field="input.placeholder_font_weight">
                            <?php for ($i = 100; $i <= 900; $i += 100): ?>
                            <option value="<?php echo $i; ?>" <?php selected($fi['placeholder_font_weight'] ?? '400', $i); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

        </div>

        <!-- Typography -->
        <div style="margin-top:24px;">
            <div class="ts-field-row ts-field-row-3">
                <div>
                    <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="input.font_weight">
                        <?php for ($i = 100; $i <= 900; $i += 100): ?>
                        <option value="<?php echo $i; ?>" <?php selected($fi['font_weight'] ?? '400', $i); ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="ts-field-label"><?php _e('Text Transform', 'theme-styles'); ?></label>
                    <select class="ts-field-select" data-field="input.text_transform">
                        <?php foreach (['none','uppercase','lowercase','capitalize'] as $tt): ?>
                        <option value="<?php echo $tt; ?>" <?php selected($fi['text_transform'] ?? 'none', $tt); ?>><?php echo ucfirst($tt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Label ─────────────────────────────────────────────── -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Label', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="label.color" value="<?php echo esc_attr($fl['color'] ?? '#2271b1'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="label.font_size" value="<?php echo esc_attr($fl['font_size'] ?? '15px'); ?>" placeholder="15px" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Font Weight', 'theme-styles'); ?></label>
                <select class="ts-field-select" data-field="label.font_weight">
                    <?php for ($i = 100; $i <= 900; $i += 100): ?>
                    <option value="<?php echo $i; ?>" <?php selected($fl['font_weight'] ?? '600', $i); ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- ── Sizes ─────────────────────────────────────────────── -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Sizes', 'theme-styles'); ?></h3>
        <div class="ts-states-grid">
            <?php foreach (['sm' => 'Small', 'md' => 'Medium (Default)', 'lg' => 'Large'] as $size => $label):
                $sd = $fs[$size] ?? [];
                $d  = $size_defaults[$size];
            ?>
            <div class="ts-state-box">
                <div class="ts-state-box-header"><h5 class="ts-state-box-title"><?php echo esc_html($label); ?></h5></div>
                <div class="ts-state-box-body">
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Font Size', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="sizes.<?php echo $size; ?>.font_size" value="<?php echo esc_attr($sd['font_size'] ?? $d['font_size']); ?>" placeholder="<?php echo esc_attr($d['font_size']); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Padding Y', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="sizes.<?php echo $size; ?>.padding_y" value="<?php echo esc_attr($sd['padding_y'] ?? $d['padding_y']); ?>" placeholder="<?php echo esc_attr($d['padding_y']); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Padding X', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="sizes.<?php echo $size; ?>.padding_x" value="<?php echo esc_attr($sd['padding_x'] ?? $d['padding_x']); ?>" placeholder="<?php echo esc_attr($d['padding_x']); ?>" />
                    </div>
                    <div class="ts-state-field">
                        <label class="ts-field-label"><?php _e('Border Radius', 'theme-styles'); ?></label>
                        <input type="text" class="ts-field-input" data-field="sizes.<?php echo $size; ?>.border_radius" value="<?php echo esc_attr($sd['border_radius'] ?? $d['border_radius']); ?>" placeholder="<?php echo esc_attr($d['border_radius']); ?>" />
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Checkbox / Radio / Switch ────────────────────────── -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Checkbox / Radio / Switch', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Accent Color (checked)', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="checks.accent_color" value="<?php echo esc_attr($fc['accent_color'] ?? '#2271b1'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="checks.border_color" value="<?php echo esc_attr($fc['border_color'] ?? '#aaaaaa'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Size', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input" data-field="checks.size" value="<?php echo esc_attr($fc['size'] ?? '18px'); ?>" placeholder="18px" />
            </div>
        </div>
        <div class="ts-field-row ts-field-row-3" style="margin-top:16px;">
            <div>
                <label class="ts-field-label"><?php _e('Switch Track BG', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="checks.switch_track_bg" value="<?php echo esc_attr($fc['switch_track_bg'] ?? '#cccccc'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Switch Track BG (checked)', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="checks.switch_track_bg_checked" value="<?php echo esc_attr($fc['switch_track_bg_checked'] ?? '#2271b1'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Switch Thumb Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="checks.switch_thumb_color" value="<?php echo esc_attr($fc['switch_thumb_color'] ?? '#ffffff'); ?>" />
            </div>
        </div>
    </div>

    <!-- ── Validation ────────────────────────────────────────── -->
    <div class="ts-section">
        <h3 class="ts-section-title"><?php _e('Validation', 'theme-styles'); ?></h3>
        <div class="ts-field-row ts-field-row-3">
            <div>
                <label class="ts-field-label"><?php _e('Valid Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="validation.valid_color" value="<?php echo esc_attr($fv['valid_color'] ?? '#198754'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Invalid Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="validation.invalid_color" value="<?php echo esc_attr($fv['invalid_color'] ?? '#dc3545'); ?>" />
            </div>
            <div>
                <label class="ts-field-label"><?php _e('Invalid Border Color', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="validation.invalid_border" value="<?php echo esc_attr($fv['invalid_border'] ?? '#dc3545'); ?>" />
            </div>
        </div>
        <div class="ts-field-row" style="margin-top:16px;">
            <div>
                <label class="ts-field-label"><?php _e('Invalid Background', 'theme-styles'); ?></label>
                <input type="text" class="ts-field-input ts-color-input" data-field="validation.invalid_bg" value="<?php echo esc_attr($fv['invalid_bg'] ?? 'rgba(250,238,238,0.3)'); ?>" />
            </div>
        </div>
    </div>

</div>

<script>
(function($) {
    'use strict';

    // ── Helpers ───────────────────────────────────────────────
    function g(field) {
        var $el = $('#module-forms [data-field="' + field + '"]');
        if (!$el.length) return '';
        return $.trim($el.val()) || '';
    }

    // Renk field'ı için - boşsa transparent döner
    function gc(field) {
        var v = g(field);
        return v !== '' ? v : 'transparent';
    }

    // Build border CSS lines array - 4-side modda border:none + per-side
    function buildBorderLines(prefix) {
        var is4side = $('#module-forms [data-field="input.' + prefix + '_4side"]').is(':checked');

        if (is4side) {
            var lines = ['border:none;'];
            ['top','right','bottom','left'].forEach(function(s) {
                var w  = g('input.' + prefix + '_' + s + '_width') || '0';
                var st = g('input.' + prefix + '_' + s + '_style') || 'none';
                var c  = gc('input.' + prefix + '_' + s + '_color');
                if (w !== '0' && st !== 'none') {
                    lines.push('border-' + s + ':' + w + ' ' + st + ' ' + c + ';');
                }
            });
            return lines;
        }

        var w  = g('input.' + prefix + '_width') || '1px';
        var st = g('input.' + prefix + '_style') || 'solid';
        var c  = gc('input.' + prefix + '_color');
        return ['border:' + w + ' ' + st + ' ' + c + ';'];
    }

    // ── Preview render ────────────────────────────────────────
    function render() {
        var color   = gc('input.color')         || '#666';
        var bg      = gc('input.bg_color')      || '#fff';
        var br      = g('input.border_radius')  || '8px';
        var fw      = g('input.font_weight')    || '400';
        var tt      = g('input.text_transform') || 'none';
        var phColor = gc('input.placeholder_color');
        var phFw    = g('input.placeholder_font_weight')  || '400';

        var colorF  = gc('input.color_focus')    || '#333';
        var bgF     = gc('input.bg_color_focus') || '#fff';
        var shadow  = g('input.focus_shadow')    || 'none';

        var lblColor = gc('label.color')       || '#2271b1';
        var lblSize  = g('label.font_size')    || '15px';
        var lblFw    = g('label.font_weight')  || '600';

        var smFs = g('sizes.sm.font_size')     || '13px';
        var smPy = g('sizes.sm.padding_y')     || '6px';
        var smPx = g('sizes.sm.padding_x')     || '10px';
        // border_radius: boşsa ana input.border_radius'u kullan
        var _smBr = $.trim(g('sizes.sm.border_radius'));
        var smBr  = _smBr !== '' ? _smBr : br;

        var mdFs = g('sizes.md.font_size')     || '15px';
        var mdPy = g('sizes.md.padding_y')     || '10px';
        var mdPx = g('sizes.md.padding_x')     || '14px';
        var _mdBr = $.trim(g('sizes.md.border_radius'));
        var mdBr  = _mdBr !== '' ? _mdBr : br;

        var lgFs = g('sizes.lg.font_size')     || '17px';
        var lgPy = g('sizes.lg.padding_y')     || '14px';
        var lgPx = g('sizes.lg.padding_x')     || '18px';
        var _lgBr = $.trim(g('sizes.lg.border_radius'));
        var lgBr  = _lgBr !== '' ? _lgBr : br;

        var accent  = gc('checks.accent_color')            || '#2271b1';
        var chkBdr  = gc('checks.border_color')            || 'transparent';
        var chkSize = g('checks.size')                     || '18px';
        var swTrack = gc('checks.switch_track_bg')         || '#ccc';
        var swOn    = gc('checks.switch_track_bg_checked') || '#2271b1';
        var swThumb = gc('checks.switch_thumb_color')      || '#fff';

        var defLines   = buildBorderLines('border');
        var focusLines = buildBorderLines('border_focus');

        var css = [
            /* label */
            '#ts-forms-preview-area .ts-fp-label {',
            '  display:block; margin-bottom:6px;',
            '  color:' + lblColor + '; font-size:' + lblSize + '; font-weight:' + lblFw + ';',
            '}',
            /* default input */
            '#ts-forms-preview-area .ts-fp-input {',
            '  display:block; width:100%; box-sizing:border-box; outline:none;',
            '  color:' + color + '; background:' + bg + ';',
            '  ' + defLines.join(' '),
            '  border-radius:' + br + ' !important;',
            '  font-size:' + mdFs + '; padding:' + mdPy + ' ' + mdPx + ';',
            '  font-weight:' + fw + '; text-transform:' + tt + ';',
            '  box-shadow:none; transition:none;',
            '}',
            /* placeholder */
            '#ts-forms-preview-area .ts-fp-input::placeholder {',
            '  color:' + phColor + '; font-weight:' + phFw + ';',
            '}',
            /* focus state - real :focus */
            '#ts-forms-preview-area .ts-fp-input:focus {',
            '  color:' + colorF + '; background:' + bgF + ';',
            '  ' + focusLines.join(' '),
            '  border-radius:' + br + ' !important;',
            '  box-shadow:' + shadow + ';',
            '}',
            /* sizes - .ts-fp-input.ts-fp-sm ile daha spesifik */
            '#ts-forms-preview-area .ts-fp-input.ts-fp-sm {',
            '  font-size:' + smFs + ' !important; padding:' + smPy + ' ' + smPx + ' !important; border-radius:' + smBr + ' !important;',
            '}',
            '#ts-forms-preview-area .ts-fp-input.ts-fp-md {',
            '  font-size:' + mdFs + ' !important; padding:' + mdPy + ' ' + mdPx + ' !important; border-radius:' + mdBr + ' !important;',
            '}',
            '#ts-forms-preview-area .ts-fp-input.ts-fp-lg {',
            '  font-size:' + lgFs + ' !important; padding:' + lgPy + ' ' + lgPx + ' !important; border-radius:' + lgBr + ' !important;',
            '}',
            '#ts-forms-preview-area .ts-fp-input.ts-fp-sm:focus {',
            '  border-radius:' + smBr + ' !important;',
            '}',
            '#ts-forms-preview-area .ts-fp-input.ts-fp-md:focus {',
            '  border-radius:' + mdBr + ' !important;',
            '}',
            '#ts-forms-preview-area .ts-fp-input.ts-fp-lg:focus {',
            '  border-radius:' + lgBr + ' !important;',
            '}',
            /* checkbox / radio */
            '#ts-forms-preview-area .ts-fp-check,',
            '#ts-forms-preview-area .ts-fp-radio {',
            '  width:' + chkSize + '; height:' + chkSize + ';',
            '  accent-color:' + accent + '; border:1px solid ' + chkBdr + ';',
            '  cursor:pointer; vertical-align:middle; margin-right:4px;',
            '}',
            '#ts-forms-preview-area .ts-fp-check-label,',
            '#ts-forms-preview-area .ts-fp-switch-label {',
            '  display:inline-flex; align-items:center; gap:6px;',
            '  font-size:' + lblSize + '; color:' + lblColor + '; cursor:pointer;',
            '}',
            /* switch */
            '#ts-forms-preview-area .ts-fp-switch-track {',
            '  display:inline-block; position:relative; flex-shrink:0;',
            '  width:calc(' + chkSize + ' * 2.2); height:' + chkSize + ';',
            '  background:' + swTrack + '; border-radius:' + chkSize + ';',
            '  transition:background .2s; cursor:pointer;',
            '}',
            '#ts-forms-preview-area .ts-fp-switch-track.ts-fp-switch-on { background:' + swOn + '; }',
            '#ts-forms-preview-area .ts-fp-switch-thumb {',
            '  position:absolute; top:2px; left:2px;',
            '  width:calc(' + chkSize + ' - 4px); height:calc(' + chkSize + ' - 4px);',
            '  background:' + swThumb + '; border-radius:50%;',
            '  transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2);',
            '}',
        ].join('\n');

        document.getElementById('ts-forms-preview-css').textContent = css;
    }

    // ── 4-side toggle ─────────────────────────────────────────
    $(document).on('change', '#module-forms .ts-forms-4side-cb', function() {
        var $cb      = $(this);
        var is4side  = $cb.is(':checked');
        $('#' + $cb.data('target')).toggle(is4side);
        // shorthand row'u gizle/göster
        $cb.closest('.ts-forms-border-wrap').find('.ts-forms-border-shorthand').toggle(!is4side);
        render();
    });

    // ── Switch preview toggle ─────────────────────────────────
    $(document).on('click', '#ts-forms-preview-area .ts-fp-switch-track', function() {
        var $track  = $(this);
        var isOn    = $track.hasClass('ts-fp-switch-on');
        var chkSize = g('checks.size') || '18px';
        if (isOn) {
            $track.removeClass('ts-fp-switch-on').css('background', g('checks.switch_track_bg') || '#ccc');
            $track.find('.ts-fp-switch-thumb').css('transform', 'translateX(0)');
        } else {
            $track.addClass('ts-fp-switch-on').css('background', g('checks.switch_track_bg_checked') || '#2271b1');
            $track.find('.ts-fp-switch-thumb').css('transform', 'translateX(calc(' + chkSize + ' * 1.2))');
        }
    });

    // ── Field changes ─────────────────────────────────────────
    $(document).on('change input', '#module-forms [data-field]', function() {
        render();
    });

    // ── Init ──────────────────────────────────────────────────
    $(document).ready(function() {
        render();
    });

})(jQuery);
</script>
