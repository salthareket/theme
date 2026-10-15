/**
 * SH Select — Custom Multi-Select / Tag Input Component
 *
 * Kullanım:
 *   <!-- Auto init: data-sh-select attribute'u varsa otomatik init edilir -->
 *   <select multiple data-sh-select data-placeholder="Seçiniz...">
 *     <option value="foo">Foo</option>
 *   </select>
 *
 *   // Manuel init:
 *   shSelect.init('#my-select');
 *   shSelect.init('#my-select', { placeholder: 'Ara...' });
 *
 *   // Değerleri al (native select'ten):
 *   var vals = document.querySelector('#my-select').value;  // single
 *   var vals = jQuery('#my-select').val();  // multiple → array
 *
 * @version 1.0.0
 */
(function($) {
    'use strict';

    window.shSelect = {

        /**
         * Bir select element'i SH Select'e dönüştür.
         * @param {string|HTMLElement|jQuery} selector
         * @param {object} options
         */
        init: function(selector, options) {
            var $select = $(selector);
            if (!$select.length || $select.data('sh-select-init')) return;
            $select.data('sh-select-init', true);

            var opts = $.extend({
                placeholder: $select.data('placeholder') || $select.attr('data-placeholder') || 'Seçiniz...'
            }, options || {});

            // Native select'i gizle
            $select.hide();

            // Build DOM
            var $container = $('<div class="sh-select-container"></div>');
            var $wrap      = $('<div class="sh-select-wrap"></div>');
            var $search    = $('<input type="text" class="sh-select-search" autocomplete="off" spellcheck="false">');
            var $dropdown  = $('<div class="sh-select-dropdown" style="display:none"></div>');

            $wrap.append($search);
            $container.append($wrap).append($dropdown);
            $select.after($container);

            // Tüm option'ları topla
            var allOptions = [];
            $select.find('option').each(function() {
                var val = $(this).val();
                if (val === '' || val === null) return; // boş option'ı atla
                allOptions.push({
                    value: val,
                    label: $(this).text().trim()
                });
            });

            // Chip'leri render et
            function renderChips() {
                $wrap.find('.sh-select-chip').remove();
                var selected = $select.val() || [];
                if (!Array.isArray(selected)) selected = selected ? [selected] : [];

                selected.forEach(function(val) {
                    var opt = allOptions.find(function(o) { return o.value === val; });
                    if (!opt) return;

                    var $chip = $('<span class="sh-select-chip"></span>')
                        .text(opt.label)
                        .attr('title', opt.label)
                        .data('value', val);

                    var $rm = $('<button type="button" class="sh-select-chip-remove" aria-label="Kaldır">×</button>');
                    $rm.on('click', function(e) {
                        e.stopPropagation();
                        var vals = $select.val() || [];
                        if (!Array.isArray(vals)) vals = vals ? [vals] : [];
                        vals = vals.filter(function(v) { return v !== val; });
                        $select.val(vals);
                        renderChips();
                        renderDropdown($search.val());
                    });

                    $chip.append($rm);
                    $search.before($chip);
                });

                // Placeholder: seçili yoksa göster
                $search.attr('placeholder', selected.length ? '' : opts.placeholder);
            }

            // Dropdown içeriğini render et
            function renderDropdown(filter) {
                filter = (filter || '').toLowerCase().trim();
                var selected = $select.val() || [];
                if (!Array.isArray(selected)) selected = selected ? [selected] : [];

                var visible = allOptions.filter(function(o) {
                    if (!filter) return true;
                    return o.label.toLowerCase().indexOf(filter) !== -1 ||
                           o.value.toLowerCase().indexOf(filter) !== -1;
                });

                $dropdown.empty();

                if (!visible.length) {
                    $dropdown.append('<div class="sh-select-dropdown-empty">Sonuç bulunamadı</div>');
                    return;
                }

                visible.forEach(function(opt) {
                    var isSelected = selected.indexOf(opt.value) !== -1;
                    var $item = $('<div class="sh-select-dropdown-item"></div>');

                    if (isSelected) {
                        $item.addClass('sh-select-item-selected');
                        $item.append('<span class="sh-select-check">✓</span>');
                    }

                    $item.append($('<span></span>').text(opt.label));

                    if (!isSelected) {
                        $item.on('mousedown', function(e) {
                            e.preventDefault();
                            var vals = $select.val() || [];
                            if (!Array.isArray(vals)) vals = vals ? [vals] : [];
                            if (vals.indexOf(opt.value) === -1) {
                                vals.push(opt.value);
                            }
                            $select.val(vals);
                            $search.val('');
                            renderChips();
                            renderDropdown('');
                            $search.focus();
                        });
                    }

                    $dropdown.append($item);
                });
            }

            // Dropdown'ı viewport'a göre konumlandır — dropup desteği dahil
            var DROPDOWN_MAX_H = 260; // px — dropdown'ın max yüksekliği
            var DROPDOWN_MIN_SPACE = 200; // px — altında bu kadar yer yoksa yukarı aç

            function positionDropdown() {
                var rect      = $wrap[0].getBoundingClientRect();
                var vpHeight  = window.innerHeight;
                var spaceBelow = vpHeight - rect.bottom - 4;
                var spaceAbove = rect.top - 4;

                // Aşağıda yeterli yer var mı?
                var goUp = spaceBelow < DROPDOWN_MIN_SPACE && spaceAbove > spaceBelow;

                // Kullanılabilir yüksekliği hesapla, max ile sınırla
                var availH = goUp
                    ? Math.min(spaceAbove, DROPDOWN_MAX_H)
                    : Math.min(spaceBelow, DROPDOWN_MAX_H);

                if (goUp) {
                    // Dropup: dropdown'ı wrap'ın üstüne konumlandır
                    $dropdown.css({
                        top:        'auto',
                        bottom:     (vpHeight - rect.top + 4) + 'px',
                        left:       rect.left + 'px',
                        width:      rect.width + 'px',
                        maxHeight:  availH + 'px'
                    });
                    $dropdown.addClass('sh-select-dropup').removeClass('sh-select-dropbelow');
                } else {
                    $dropdown.css({
                        top:        (rect.bottom + 4) + 'px',
                        bottom:     'auto',
                        left:       rect.left + 'px',
                        width:      rect.width + 'px',
                        maxHeight:  availH + 'px'
                    });
                    $dropdown.addClass('sh-select-dropbelow').removeClass('sh-select-dropup');
                }
            }

            function openDropdown() {
                renderDropdown($search.val());
                positionDropdown();
                $dropdown.show();
            }

            function closeDropdown() {
                $dropdown.hide();
                $search.val('');
            }

            // Reposition on scroll/resize
            function reposition() {
                if ($dropdown.is(':visible')) {
                    positionDropdown();
                }
            }

            // Events
            $wrap.on('click', function(e) {
                if (!$(e.target).closest('.sh-select-chip-remove').length) {
                    $search.focus();
                }
            });

            $search.on('focus', openDropdown);
            $search.on('input', function() { renderDropdown($(this).val()); });
            $search.on('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDropdown();
                    $search.blur();
                }
                if (e.key === 'Backspace' && $(this).val() === '') {
                    // Son chip'i sil
                    var vals = $select.val() || [];
                    if (!Array.isArray(vals)) vals = vals ? [vals] : [];
                    if (vals.length) {
                        vals = vals.slice(0, -1);
                        $select.val(vals);
                        renderChips();
                        renderDropdown('');
                    }
                }
            });

            // Dışarı tıklayınca kapat
            var closeHandler = 'click.sh-select-' + Math.random().toString(36).substr(2, 9);
            $(document).on(closeHandler, function(e) {
                if (!$container.is(e.target) && $container.find(e.target).length === 0 &&
                    !$dropdown.is(e.target) && $dropdown.find(e.target).length === 0) {
                    closeDropdown();
                }
            });

            $(window).on('scroll.sh-select resize.sh-select', reposition);

            // İlk render
            renderChips();
        },

        /**
         * Sayfadaki tüm [data-sh-select] attribute'lu select'leri init et.
         * display:none olan bir parent içindeki select'leri atlar
         * (repeater template satırları gibi).
         */
        initAll: function() {
            $('[data-sh-select]').each(function() {
                // Herhangi bir ancestor display:none ise init etme (template row vb.)
                if ($(this).closest('[style*="display:none"], [style*="display: none"]').length) return;
                window.shSelect.init(this);
            });
        },

        /**
         * Bir element'i ve içindeki tüm sh-select'leri temizle (clone öncesi).
         * jQuery'nin data cache'ini ve DOM'daki sh-select artefact'larını kaldırır.
         * @param {jQuery} $el
         */
        destroy: function($el) {
            $el.find('[data-sh-select]').each(function() {
                $(this).removeData('sh-select-init').show();
            });
            $el.find('.sh-select-container').remove();
        }
    };

    // DOM ready'de auto-init
    $(function() {
        window.shSelect.initAll();
    });

})(jQuery);
