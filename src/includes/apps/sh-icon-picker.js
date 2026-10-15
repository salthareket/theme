/**
 * SH Icon Picker — FA SVG Icon Picker Component (Modal Edition)
 *
 * Kullanım:
 *   <input type="hidden" name="separator_icon" data-sh-icon-picker value="solid:chevron-right">
 *
 *   // Manuel init:
 *   shIconPicker.init('#my-input');
 *
 * Kayıt formatı: "style:icon-name"  örn: "solid:heart", "brands:facebook"
 *
 * @version 2.0.0
 */
(function ($) {
    'use strict';

    var STYLES = [
        { value: 'solid',   label: 'Solid' },
        { value: 'regular', label: 'Regular' },
        { value: 'light',   label: 'Light' },
        { value: 'brands',  label: 'Brands' },
    ];

    // Icon listesi cache: style → string[]
    var iconCache  = {};
    // SVG cache: style:name → svgString
    var svgCache   = {};
    // Tek global modal
    var $modal     = null;
    var modalTarget = null; // aktif picker instance

    var ajaxUrl = function() {
        return (window.shIconPickerConfig && window.shIconPickerConfig.ajaxUrl) || window.ajaxurl || '/wp-admin/admin-ajax.php';
    };
    var nonce = function() {
        return (window.shIconPickerConfig && window.shIconPickerConfig.nonce) || '';
    };

    // ─── AJAX helpers ────────────────────────────────────────────────────────

    function fetchIconList(style, cb) {
        if (iconCache[style] && iconCache[style].length) { cb(iconCache[style]); return; }
        $.post(ajaxUrl(), { action: 'sh_fa_icon_list', style: style, nonce: nonce() }, function(res) {
            if (res && res.success && res.data) {
                // Yeni format: { icons: [...], style: '...' }
                // Eski format fallback: res.data direkt array
                var icons = Array.isArray(res.data) ? res.data :
                            (res.data.icons && Array.isArray(res.data.icons) ? res.data.icons : []);
                // Sadece string olan elemanları al
                icons = icons.filter(function(n) { return typeof n === 'string' && n.length > 0; });
                iconCache[style] = icons;
                cb(icons);
            } else {
                cb([]);
            }
        }).fail(function(){ cb([]); });
    }

    function fetchSvgBulk(style, names, cb) {
        var result = {};
        var missing = [];
        names.forEach(function(n) {
            var k = style + ':' + n;
            if (svgCache[k]) result[n] = svgCache[k];
            else missing.push(n);
        });
        if (!missing.length) { cb(result); return; }

        $.post(ajaxUrl(), { action: 'sh_fa_icon_svg_bulk', style: style, names: missing, nonce: nonce() }, function(res) {
            if (res && res.success && res.data) {
                Object.keys(res.data).forEach(function(n) {
                    svgCache[style + ':' + n] = res.data[n];
                    result[n] = res.data[n];
                });
            }
            cb(result);
        }).fail(function(){ cb(result); });
    }

    function fetchSingleSvg(style, name, cb) {
        var k = style + ':' + name;
        if (svgCache[k]) { cb(svgCache[k]); return; }
        fetchSvgBulk(style, [name], function(r) { cb(r[name] || ''); });
    }

    // ─── Global Modal ────────────────────────────────────────────────────────

    function buildModal() {
        if ($modal) return;

        $modal = $('<div id="sh-ip-modal-overlay" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center"></div>');
        var $box = $('<div style="background:#fff;border-radius:8px;width:680px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)"></div>');

        // Header
        var $header = $('<div style="display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid #e5e7eb;flex-shrink:0"></div>');
        var $styleWrap = $('<div style="flex-shrink:0"></div>');
        var $styleOpts = STYLES.map(function(s){ return $('<option>').val(s.value).text(s.label); });
        var $styleSelect = $('<select style="padding:7px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;background:#fff;cursor:pointer;outline:none"></select>').append($styleOpts);
        $styleWrap.append($styleSelect);

        var $searchWrap = $('<div style="flex:1;position:relative"></div>');
        var $searchIcon = $('<span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px;pointer-events:none">🔍</span>');
        var $searchInput = $('<input type="text" placeholder="İkon ara..." style="width:100%;padding:7px 10px 7px 32px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;outline:none;box-sizing:border-box">');
        $searchWrap.append($searchIcon).append($searchInput);

        var $closeBtn = $('<button type="button" style="flex-shrink:0;background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer;padding:0;line-height:1">×</button>');
        $header.append($styleWrap).append($searchWrap).append($closeBtn);

        // Status bar
        var $status = $('<div style="padding:6px 20px;font-size:12px;color:#9ca3af;border-bottom:1px solid #f3f4f6;flex-shrink:0"></div>');

        // Grid body
        var $body = $('<div style="flex:1;overflow-y:auto;padding:16px"></div>');
        var $grid = $('<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:4px"></div>');
        $body.append($grid);

        $box.append($header).append($status).append($body);
        $modal.append($box);
        $('body').append($modal);
        $modal.hide();

        // ── Modal events ──────────────────────────────────────────────────

        var searchTimer = null;
        var currentStyle = 'solid';
        var currentIcons = [];
        var currentFilter = '';
        var currentPage   = 0;
        var PAGE_SIZE     = 96;
        var currentFiltered = [];

        function makeItem(name) {
            var $item = $('<div class="sh-ip-modal-item" data-name="' + name + '" style="display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:8px 4px;border-radius:6px;cursor:pointer;border:1px solid transparent;transition:background .08s;gap:4px;min-height:60px"></div>');
            var $svgWrap = $('<div style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;flex-shrink:0"></div>');
            var $lbl = $('<div style="font-size:10px;color:#6b7280;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;max-width:72px"></div>').text(name);

            if (modalTarget && modalTarget.getStyle() === currentStyle && modalTarget.getName() === name) {
                $item.css({ background: '#eff6ff', 'border-color': '#bfdbfe' });
                $lbl.css('color', '#1d4ed8');
            }

            $item.append($svgWrap).append($lbl);
            $item.on('click', function() {
                if (modalTarget) modalTarget.setValue(currentStyle, name);
                closeModal();
            });
            $item.on('mouseenter', function(){ $(this).css({ background: '#f9fafb', 'border-color': '#e5e7eb' }); });
            $item.on('mouseleave', function(){
                if (modalTarget && modalTarget.getStyle() === currentStyle && modalTarget.getName() === name) return;
                $(this).css({ background: '', 'border-color': 'transparent' });
            });

            return { $item: $item, $svgWrap: $svgWrap };
        }

        function appendPage(page) {
            var start = page * PAGE_SIZE;
            var batch = currentFiltered.slice(start, start + PAGE_SIZE);
            if (!batch.length) return;

            var $items = {};
            batch.forEach(function(name) {
                var parts = makeItem(name);
                $grid.append(parts.$item);
                $items[name] = parts.$svgWrap;
            });

            fetchSvgBulk(currentStyle, batch, function(svgs) {
                batch.forEach(function(name) {
                    if (svgs[name] && $items[name]) {
                        $items[name].html(svgs[name]);
                        $items[name].find('svg').css({ width: '20px', height: '20px', fill: '#374151' });
                    }
                });
            });
        }

        function renderGrid(filter) {
            filter = (filter !== undefined ? filter : currentFilter).toLowerCase().trim();
            currentFilter = filter;
            currentPage   = 0;

            currentFiltered = filter
                ? currentIcons.filter(function(n){ return typeof n === 'string' && n.indexOf(filter) !== -1; })
                : currentIcons.slice();

            if (filter) {
                currentFiltered.sort(function(a, b) {
                    var as = a.indexOf(filter) === 0 ? 0 : 1;
                    var bs = b.indexOf(filter) === 0 ? 0 : 1;
                    return as - bs || a.localeCompare(b);
                });
            }

            $grid.empty();
            $status.text(currentFiltered.length + ' ikon' + (filter ? ' bulundu' : ''));

            if (!currentFiltered.length) {
                $grid.append('<div style="grid-column:1/-1;text-align:center;color:#9ca3af;padding:32px;font-size:13px">Sonuç bulunamadı</div>');
                return;
            }

            appendPage(0);

            // Infinite scroll: body scroll → sonraki sayfa
            $body.off('scroll.sh-ip').on('scroll.sh-ip', function() {
                var el = $body[0];
                if (el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
                    currentPage++;
                    var start = currentPage * PAGE_SIZE;
                    if (start < currentFiltered.length) {
                        appendPage(currentPage);
                    }
                }
            });
        }

        function loadStyle(style) {
            currentStyle  = style;
            currentFilter = $searchInput.val() || '';
            currentIcons  = [];
            currentFiltered = [];
            $grid.html('<div style="grid-column:1/-1;text-align:center;color:#9ca3af;padding:32px;font-size:13px"><span style="display:inline-block;width:20px;height:20px;border:2px solid #e5e7eb;border-top-color:#6b7280;border-radius:50%;animation:sh-ip-spin .6s linear infinite;vertical-align:middle;margin-right:8px"></span>Yükleniyor...</div>');
            $status.text('Yükleniyor...');
            fetchIconList(style, function(icons) {
                currentIcons = icons;
                renderGrid($searchInput.val());
            });
        }

        $styleSelect.on('change', function() { loadStyle($(this).val()); });

        // input + keyup ikisi birden — bazı sayfalar input'u engelleyebilir
        $searchInput.on('input keyup', function(e) {
            if (e.key === 'Escape') { closeModal(); return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter') return;
            clearTimeout(searchTimer);
            var q = $(this).val();
            searchTimer = setTimeout(function(){
                if (currentIcons.length) {
                    renderGrid(q);
                }
            }, 150);
        });
        $closeBtn.on('click', closeModal);
        $modal.on('click', function(e) {
            if ($(e.target).is($modal)) closeModal();
        });
        $(document).on('keydown.sh-ip-modal', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        $modal._loadStyle   = loadStyle;
        $modal._styleSelect = $styleSelect;
        $modal._searchInput = $searchInput;
    }

    function openModal(instance) {
        buildModal();
        modalTarget = instance;
        var style = instance.getStyle();
        var name  = instance.getName();
        $modal._styleSelect.val(style);
        $modal._searchInput.val(name || '');
        $modal._loadStyle(style);

        // Modal'i ACF expanded editor icindeyse oraya append et, yoksa body'e
        var $acfModal = $('.acf-block-form-modal, .components-modal__frame.acf-block-form-modal').last();
        if ($acfModal.length) {
            // ACF modal'inin stacking context'ine gir
            $acfModal.css('overflow', 'visible');
            $modal.css('z-index', '1000000');
            $acfModal.append($modal);
        } else {
            $modal.css('z-index', '999999');
            $('body').append($modal);
        }

        $modal.fadeIn(150);
        setTimeout(function(){ $modal._searchInput.focus(); }, 200);
    }

    function closeModal() {
        if ($modal) {
            $modal.fadeOut(100, function() {
                // Kapanınca body'e geri taşı — bir sonraki açılışta doğru yere append edilsin
                if (!$.contains(document.body, $modal[0]) || $modal.parent()[0] !== document.body) {
                    $('body').append($modal);
                }
            });
        }
        modalTarget = null;
    }

    // ─── Instance ────────────────────────────────────────────────────────────

    function createInstance($hidden, opts) {
        opts = $.extend({ placeholder: 'Choose an icon...' }, opts || {});

        var currentStyle = 'solid';
        var currentName  = '';

        // Parse initial value
        var val = $hidden.val() || '';
        if (val && val.indexOf(':') !== -1) {
            var p = val.split(':');
            currentStyle = p[0] || 'solid';
            currentName  = p[1] || '';
        }

        // ── Klon artigi temizligi ─────────────────────────────────────────────
        // ACF "Add Row" template row'u (_x) klonlar. Klon, onceden init edilmis bir
        // picker'in gorsel wrap'ini (.sh-icon-picker-wrap) de tasir. Bu durumda yeni
        // instance olusturulur ama gorsel ESKI klon wrap'e bagli kalir → secim
        // "yansimiyor" gibi gorunur. Init'ten once bu hidden'a ait onceki wrap'leri sil.
        $hidden.siblings('.sh-icon-picker-wrap').remove();

        // ── DOM ──────────────────────────────────────────────────────────────
        var $wrap = $('<div class="sh-icon-picker-wrap"></div>');

        // Style dropdown
        var $styleOpts = STYLES.map(function(s){
            return $('<option>').val(s.value).text(s.label).prop('selected', s.value === currentStyle);
        });
        var $style = $('<select class="sh-icon-picker-style"></select>').append($styleOpts);

        // Trigger button (sağ taraf)
        var $trigger = $('<div class="sh-icon-picker-trigger"></div>');
        var $triggerInner = $('<div class="sh-ip-trigger-inner"></div>');
        var $svgWrap = $('<span class="sh-ip-svg"></span>');
        var $label   = $('<span class="sh-ip-label"></span>');
        var $clearBtn = $('<button type="button" class="sh-ip-clear" title="Temizle">×</button>');

        $triggerInner.append($svgWrap).append($label);
        $trigger.append($triggerInner).append($clearBtn);
        $wrap.append($style).append($trigger);
        $hidden.after($wrap);
        $hidden.hide(); // Orijinal input'u gizle — sh-icon-picker-wrap onu replace eder

        // ── Render state ──────────────────────────────────────────────────────
        function renderEmpty() {
            $svgWrap.empty();
            $label.text(opts.placeholder).css('color', '#9ca3af');
            $clearBtn.hide();
            $trigger.removeClass('sh-ip-has-value');
        }

        function renderSelected(style, name) {
            $label.text(name).css('color', '#374151');
            $clearBtn.show();
            $trigger.addClass('sh-ip-has-value');
            fetchSingleSvg(style, name, function(svg) {
                $svgWrap.html(svg || '');
                $svgWrap.find('svg').css({ width: '16px', height: '16px', fill: '#374151', 'vertical-align': 'middle' });
            });
        }

        function render() {
            if (currentName) renderSelected(currentStyle, currentName);
            else renderEmpty();
        }

        // ── Instance API ──────────────────────────────────────────────────────
        var self = {
            getStyle: function() { return $style.val(); },
            getName:  function() { return currentName; },
            setValue: function(style, name) {
                currentStyle = style;
                currentName  = name;
                $style.val(style);
                $hidden.val(name ? (style + ':' + name) : '').trigger('change').trigger('input');
                render();
            }
        };

        // ── Events ────────────────────────────────────────────────────────────
        $style.on('change', function() {
            var newStyle = $(this).val();
            var oldStyle = currentStyle;
            currentStyle = newStyle;

            if (!currentName) {
                // Henüz ikon seçilmemiş — sadece style değiştir, ikon listesi preload
                if (!iconCache[newStyle]) fetchIconList(newStyle, function(){});
                return;
            }

            var isBrandsSwitch = (newStyle === 'brands') !== (oldStyle === 'brands');

            if (isBrandsSwitch) {
                // Brands ↔ diğerleri geçişi: ikon adını sıfırla
                self.setValue(newStyle, '');
            } else {
                // Solid ↔ Light ↔ Regular: aynı ikon adıyla SVG güncelle
                // Loading overlay göster
                $svgWrap.css('opacity', '0.3');
                $label.css('opacity', '0.5');
                $hidden.val(newStyle + ':' + currentName);
                fetchSingleSvg(newStyle, currentName, function(svg) {
                    $svgWrap.html(svg || '').css('opacity', '1');
                    $svgWrap.find('svg').css({ width: '16px', height: '16px', fill: '#374151' });
                    $label.css('opacity', '1');
                });
                if (!iconCache[newStyle]) fetchIconList(newStyle, function(){});
            }
        });

        // Event delegation ile tıklama — direkt bind yerine, yeni row'larda da çalışır
        $triggerInner.attr('data-sh-ip-instance', Date.now() + Math.random());
        var instanceId = $triggerInner.attr('data-sh-ip-instance');

        $(document).on('click.sh-ip-' + instanceId, '[data-sh-ip-instance="' + instanceId + '"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openModal(self);
        });

        $clearBtn.on('click', function(e) {
            e.stopPropagation();
            self.setValue($style.val(), '');
        });

        // Initial render
        render();

        return self;
    }

    // ─── Public API ──────────────────────────────────────────────────────────
    window.shIconPicker = {
        init: function(selector, opts) {
            var $el = $(selector);
            if (!$el.length) return;
            // DOM attribute kullan — farkli jQuery instance'lari arasinda guvenli
            var el = $el[0];
            if (el.getAttribute('data-sh-ip-init')) return;
            el.setAttribute('data-sh-ip-init', '1');
            createInstance($el, opts);
        },
        initAll: function() {
            $('[data-sh-icon-picker]').each(function() {
                window.shIconPicker.init(this);
            });
        }
    };

    $(function() { window.shIconPicker.initAll(); });

})(jQuery);
