/**
 * Popover & Tooltip Module JavaScript
 * @version 1.0.0
 */
(function($) {
    'use strict';

    const ThemeStylesPopover = {

        init() {
            if (!$('#module-popover').length) return;
            this.updatePreview();
            this.bindEvents();
        },

        getField(field) {
            const $el = $(`#module-popover [data-field="${field}"]`);
            return $el.is('select') ? $el.find('option:selected').val() : ($el.val() || '');
        },

        updatePreview() {
            const $popover = $('#ts-pv-preview-popover');
            const $pvHeader= $('#ts-pv-preview-popover-header');
            const $pvBody  = $('#ts-pv-preview-popover-body');
            const $tooltip = $('#ts-pv-preview-tooltip');
            if (!$popover.length) return;

            const g = (f) => this.getField(f);

            // Popover
            const pvBg     = g('popover.bg_color')     || '#ffffff';
            const pvBordC  = g('popover.border_color') || 'rgba(0,0,0,.2)';
            const pvBordR  = g('popover.border_radius')|| '0.375rem';
            const pvShadow = g('popover.box_shadow')   || '0 .5rem 1rem rgba(0,0,0,.15)';
            const pvMaxW   = g('popover.max_width')    || '276px';
            $popover.css({ background: pvBg, border: `1px solid ${pvBordC}`, borderRadius: pvBordR, boxShadow: pvShadow, maxWidth: pvMaxW });

            // Popover header
            const pvHdBg  = g('popover.header.bg_color') || '#f0f0f0';
            const pvHdC   = g('popover.header.color')    || '#212529';
            const pvHdFs  = g('popover.header.font_size')|| '1rem';
            const pvHdPad = g('popover.header.padding')  || '0.5rem 1rem';
            $pvHeader.css({ background: pvHdBg, color: pvHdC, fontSize: pvHdFs, padding: pvHdPad });

            // Popover body
            const pvBdC   = g('popover.body.color')    || '#212529';
            const pvBdFs  = g('popover.body.font_size')|| '1rem';
            const pvBdPad = g('popover.body.padding')  || '1rem';
            $pvBody.css({ color: pvBdC, fontSize: pvBdFs, padding: pvBdPad });

            // Tooltip
            const ttBg    = g('tooltip.bg_color')     || '#000000';
            const ttC     = g('tooltip.color')        || '#ffffff';
            const ttFs    = g('tooltip.font_size')    || '0.875rem';
            const ttBordR = g('tooltip.border_radius')|| '0.375rem';
            const ttPad   = g('tooltip.padding')      || '0.25rem 0.5rem';
            const ttOpacity= g('tooltip.opacity')     || '0.9';
            const ttMaxW  = g('tooltip.max_width')    || '200px';
            $tooltip.css({ background: ttBg, color: ttC, fontSize: ttFs, borderRadius: ttBordR, padding: ttPad, opacity: ttOpacity, maxWidth: ttMaxW });
        },

        bindEvents() {
            $(document).on('change input', '#module-popover [data-field]', () => {
                this.updatePreview();
            });
        }
    };

    $(document).ready(() => ThemeStylesPopover.init());

})(jQuery);
