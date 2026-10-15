/*!
 * SaltHareket Tab-Collapse Plugin v2.1
 * DOM manipulation approach — no CSS show/hide tricks.
 *
 * On init : TAB structure is always in the DOM.
 * < breakpoint : tab nav + tab-content hidden, accordion built & inserted, content moved.
 * >= breakpoint: accordion removed, tab nav + tab-content restored, content moved back,
 *                Bootstrap Tab instances re-initialised so clicking works again.
 */
!function ($) {
    'use strict';

    const NAME      = 'tabCollapse';
    const BS_BPS    = ['sm', 'md', 'lg', 'xl', 'xxl'];
    const BP_WIDTHS = { sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 };

    function isBelowBp(bp) {
        return window.innerWidth < (BP_WIDTHS[bp] || BP_WIDTHS.md);
    }

    class TabCollapse {

        constructor(element, options) {
            this.$nav  = $(element);
            this.$wrap = this.$nav.closest('.sh-product-tabs, [data-tab-collapse-wrap]');

            // Breakpoint'i class'tan oku: tab-collapse-md -> 'md'
            const m = (this.$nav.attr('class') || '').match(/tab-collapse-([a-z]+)/);
            this.bp = (m && BS_BPS.includes(m[1])) ? m[1] : ((options && options.breakpoint) || 'md');

            this._snapshot();
            this._mode = 'tab';
            this._render();

            this._resizeHandler = this._debounce(() => this._render(), 150);
            $(window).on('resize.tabCollapse.' + this.bp, this._resizeHandler);
        }

        // ------------------------------------------------------------------
        // Snapshot: kaydet tab yapısını (once, at init)
        // ------------------------------------------------------------------
        _snapshot() {
            this._tabs = [];

            this.$tabContent = this.$nav.siblings('.tab-content');
            if (!this.$tabContent.length && this.$wrap.length) {
                this.$tabContent = this.$wrap.find('.tab-content').first();
            }

            this.$nav.find('[data-bs-toggle="tab"],[data-bs-toggle="pill"]').each((i, btn) => {
                const $btn   = $(btn);
                const paneId = ($btn.attr('data-bs-target') || $btn.attr('href') || '').replace(/^#/, '');
                const $pane  = paneId ? $('#' + paneId) : $();
                const isAct  = $btn.hasClass('active') || i === 0;

                // Dispose any existing Bootstrap Tab instance so we control it ourselves
                if (window.bootstrap && window.bootstrap.Tab) {
                    const inst = window.bootstrap.Tab.getInstance(btn);
                    if (inst) inst.dispose();
                }

                this._tabs.push({
                    id       : paneId || ('tab-' + i),
                    btnHtml  : $btn.html(),
                    btnId    : $btn.attr('id') || '',
                    paneId   : paneId,
                    active   : isAct,
                    $pane    : $pane,
                    $content : $pane.find('.sh-product-tab-body').length
                                 ? $pane.find('.sh-product-tab-body')
                                 : $pane,
                });
            });

            // Re-init Bootstrap Tabs cleanly after dispose
            this._initBsTabs();
        }

        // ------------------------------------------------------------------
        // Bootstrap Tab instance'larini (yeniden) olustur
        // ------------------------------------------------------------------
        _initBsTabs() {
            if (!(window.bootstrap && window.bootstrap.Tab)) return;
            this.$nav.find('[data-bs-toggle="tab"],[data-bs-toggle="pill"]').each(function () {
                const existing = window.bootstrap.Tab.getInstance(this);
                if (existing) existing.dispose();
                new window.bootstrap.Tab(this);
            });
        }

        // ------------------------------------------------------------------
        // Render: durum kontrolu
        // ------------------------------------------------------------------
        _render() {
            const needAcc = isBelowBp(this.bp);
            if (needAcc && this._mode !== 'accordion') {
                this._toAccordion();
            } else if (!needAcc && this._mode !== 'tab') {
                this._toTab();
            }
        }

        // ------------------------------------------------------------------
        // TAB -> ACCORDION
        // ------------------------------------------------------------------
        _toAccordion() {
            const wrapId = this.$nav.closest('[id]').attr('id') || 'sh-tc';
            const accId  = wrapId + '--acc';

            const $acc = $('<div class="accordion sh-tab-accordion" id="' + accId + '"></div>');

            this._tabs.forEach((tab) => {
                const colId = accId + '-' + tab.id;
                const open  = tab.active;

                const $item = $(
                    '<div class="accordion-item">' +
                        '<h2 class="accordion-header" id="h-' + colId + '">' +
                            '<button class="accordion-button' + (open ? '' : ' collapsed') + '"' +
                                ' type="button"' +
                                ' data-bs-toggle="collapse"' +
                                ' data-bs-target="#' + colId + '"' +
                                ' aria-expanded="' + open + '"' +
                                ' aria-controls="' + colId + '">' +
                                tab.btnHtml +
                            '</button>' +
                        '</h2>' +
                        '<div id="' + colId + '"' +
                             ' class="accordion-collapse collapse' + (open ? ' show' : '') + '"' +
                             ' aria-labelledby="h-' + colId + '"' +
                             ' data-bs-parent="#' + accId + '">' +
                            '<div class="accordion-body sh-tab-acc-body"></div>' +
                        '</div>' +
                    '</div>'
                );

                // move content into accordion body
                $item.find('.sh-tab-acc-body').append(tab.$content.contents().detach());
                tab.$accBody = $item.find('.sh-tab-acc-body');

                $acc.append($item);
            });

            this.$nav.hide();
            this.$tabContent.hide();

            if (this.$tabContent.length) {
                this.$tabContent.after($acc);
            } else {
                this.$nav.after($acc);
            }

            this.$accordion = $acc;
            this._mode = 'accordion';
        }

        // ------------------------------------------------------------------
        // ACCORDION -> TAB
        // ------------------------------------------------------------------
        _toTab() {
            if (!this.$accordion || !this.$accordion.length) {
                this._mode = 'tab';
                return;
            }

            // Move content back to original pane wrappers
            this._tabs.forEach((tab) => {
                if (tab.$accBody) {
                    tab.$content.append(tab.$accBody.contents().detach());
                    tab.$accBody = null;
                }
            });

            // Remove accordion from DOM
            this.$accordion.remove();
            this.$accordion = null;

            // Show tab nav + tab-content
            this.$nav.show();
            this.$tabContent.show();

            // Restore active state on panes (Bootstrap may have lost it)
            this._tabs.forEach((tab) => {
                const $pane = $('#' + tab.paneId);
                $pane.removeClass('show active');
                if (tab.active) {
                    $pane.addClass('show active');
                }
            });

            // Restore active state on nav buttons
            this.$nav.find('[data-bs-toggle="tab"],[data-bs-toggle="pill"]').each((i, btn) => {
                const $btn = $(btn);
                const isAct = this._tabs[i] && this._tabs[i].active;
                $btn.toggleClass('active', !!isAct)
                    .attr('aria-selected', isAct ? 'true' : 'false');
            });

            // Re-init Bootstrap Tab instances so click events work again
            this._initBsTabs();

            this._mode = 'tab';
        }

        // ------------------------------------------------------------------
        destroy() {
            if (this._mode === 'accordion') this._toTab();
            $(window).off('resize.tabCollapse.' + this.bp, this._resizeHandler);
            this.$nav.removeData('bs.' + NAME);
        }

        _debounce(fn, wait) {
            let t;
            return function () {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, arguments), wait);
            };
        }
    }

    // jQuery plugin
    $.fn[NAME] = function (option) {
        return this.each(function () {
            const $el  = $(this);
            let   inst = $el.data('bs.' + NAME);
            const opts = $.extend({}, typeof option === 'object' ? option : {});
            if (!inst) {
                inst = new TabCollapse(this, opts);
                $el.data('bs.' + NAME, inst);
            }
            if (typeof option === 'string' && typeof inst[option] === 'function') {
                inst[option]();
            }
        });
    };
    $.fn[NAME].Constructor = TabCollapse;

    // Auto-init on DOM ready
    $(function () {
        $('.tab-collapse, [class*="tab-collapse-"]').each(function () {
            const $el = $(this);
            if (!$el.data('bs.' + NAME)) {
                $el[NAME]();
            }
        });
    });

}(window.jQuery);
