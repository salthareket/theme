/**
 * Buttons Module JavaScript
 * Repeater + Live Preview
 * @version 2.0.0
 */

(function($) {
    'use strict';

    const ThemeStylesButtons = {

        init() {
            this.bindEvents();
            this.initSortable();
            this.updatePreview();
            this.checkEmpty();
        },

        bindEvents() {
            // Add button size
            $(document).on('click', '#ts-add-button-size', () => this.addButtonSize());

            // Remove item
            $(document).on('click', '#ts-buttons-list .ts-repeater-remove', function() {
                const $item = $(this).closest('.ts-repeater-item');
                $item.fadeOut(200, function() {
                    $(this).remove();
                    ThemeStylesButtons.reindex();
                    ThemeStylesButtons.updatePreview();
                    ThemeStylesButtons.checkEmpty();
                });
            });

            // Field change → update preview
            $(document).on('change input', '#ts-buttons-list [data-field]', () => {
                this.updatePreview();
            });
        },

        initSortable() {
            $('#ts-buttons-list').sortable({
                handle: '.ts-repeater-handle',
                placeholder: 'ts-repeater-placeholder',
                axis: 'y',
                opacity: 0.8,
                tolerance: 'pointer',
                update: () => {
                    this.reindex();
                    this.updatePreview();
                }
            });
        },

        addButtonSize() {
            const $list = $('#ts-buttons-list');
            const index = $list.find('.ts-repeater-item').length;

            $('.ts-repeater-empty', '#module-buttons').addClass('hidden');

            const html = `
                <div class="ts-repeater-item" data-index="${index}" style="display:none;">
                    <div class="ts-repeater-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="ts-repeater-fields ts-button-fields">
                        <div>
                            <label class="ts-field-label">Size</label>
                            <select class="ts-field-select" data-field="custom.${index}.size">
                                <option value="default">default</option>
                                ${(themeStylesNew.breakpoints || ['xxxl','xxl','xl','lg','md','sm','xs']).map(bp => 
                                    `<option value="${bp}">${bp}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="ts-field-label">Padding X</label>
                            <input type="text" class="ts-field-input" data-field="custom.${index}.padding_x" value="16px" placeholder="16px" />
                        </div>
                        <div>
                            <label class="ts-field-label">Padding Y</label>
                            <input type="text" class="ts-field-input" data-field="custom.${index}.padding_y" value="8px" placeholder="8px" />
                        </div>
                        <div>
                            <label class="ts-field-label">Font Size</label>
                            <input type="text" class="ts-field-input" data-field="custom.${index}.font_size" value="14px" placeholder="14px" />
                        </div>
                        <div>
                            <label class="ts-field-label">Border Radius</label>
                            <input type="text" class="ts-field-input" data-field="custom.${index}.border_radius" value="4px" placeholder="4px" />
                        </div>
                    </div>
                    <button type="button" class="ts-repeater-remove" title="Remove">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;

            $list.append(html);
            $list.find('.ts-repeater-item:last').fadeIn(300, () => {
                this.updatePreview();
            });

            if ($list.hasClass('ui-sortable')) {
                $list.sortable('refresh');
            }
        },

        reindex() {
            $('#ts-buttons-list .ts-repeater-item').each(function(i) {
                $(this).attr('data-index', i);
                $(this).find('[data-field]').each(function() {
                    const field = $(this).attr('data-field');
                    $(this).attr('data-field', field.replace(/custom\.\d+\./, `custom.${i}.`));
                });
            });
        },

        checkEmpty() {
            const $list = $('#ts-buttons-list');
            const $empty = $list.closest('.ts-repeater-container').find('.ts-repeater-empty');
            const $footer = $list.closest('.ts-repeater-container').find('.ts-repeater-footer');
            
            if ($list.find('.ts-repeater-item').length === 0) {
                $empty.removeClass('hidden');
                $footer.css('margin-top', '0');
            } else {
                $empty.addClass('hidden');
                $footer.css('margin-top', '20px');
            }
        },

        collectSizes() {
            const sizes = [];
            $('#ts-buttons-list .ts-repeater-item').each(function() {
                const $item = $(this);
                sizes.push({
                    size: $item.find('[data-field$=".size"]').val() || 'default',
                    padding_x: $item.find('[data-field$=".padding_x"]').val() || '16px',
                    padding_y: $item.find('[data-field$=".padding_y"]').val() || '8px',
                    font_size: $item.find('[data-field$=".font_size"]').val() || '14px',
                    border_radius: $item.find('[data-field$=".border_radius"]').val() || '4px',
                });
            });
            return sizes;
        },

        updatePreview() {
            const sizes = this.collectSizes();
            const $preview = $('#ts-buttons-preview');

            $preview.empty();

            if (sizes.length === 0) {
                $preview.html('<p class="ts-preview-empty-text">Add button sizes below to see preview</p>');
                return;
            }

            sizes.forEach(size => {
                const $wrap = $('<div class="ts-preview-btn-wrap"></div>');
                const $btn = $(`
                    <button class="ts-preview-btn" style="
                        padding: ${size.padding_y} ${size.padding_x};
                        font-size: ${size.font_size};
                        border-radius: ${size.border_radius};
                    ">
                        Button ${size.size}
                    </button>
                `);
                const $label = $(`<span class="ts-preview-btn-label">.btn-${size.size}</span>`);
                $wrap.append($btn).append($label);
                $preview.append($wrap);
            });
        }
    };

    $(document).ready(() => {
        ThemeStylesButtons.init();
    });

})(jQuery);
