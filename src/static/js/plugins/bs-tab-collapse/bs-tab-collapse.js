/*
 * Bootstrap 5 Uyumlu TabCollapse Eklentisi (Gelişmiş Breakpoint Algılama ile)
 * Yazar: Gemini (Yüksek Performanslı Kodlama Asistanı)
 * Lisans: MIT
 */
!function ($) {
    "use strict";

    // Eklentinin adı
    const NAME = 'tabCollapse';
    // Geçerli Bootstrap breakpoint'leri (Küçükten büyüğe sıralı)
    const BREAKPOINTS = ['sm', 'md', 'lg', 'xl', 'xxl'];

    // Varsayılan ayarlar
    const DEFAULTS = {
        // Varsayılan breakpoint. Eğer özel sınıf (tab-collapse-lg gibi) bulunmazsa kullanılır.
        breakpoint: 'md', 
        
        // Bu sınıflar INIT sırasında dinamik olarak ayarlanacaktır.
        tabsClass: '', 
        accordionClass: '', 

        // Akordeon öğesi şablonu (BS5'e uygun)
        accordionTemplate: function(headerHtml, groupId, parentId, active) {
            // Güvenlik ve performans için ES6 Template Literals kullanıldı.
            return `
                <div class="card mb-2">
                    <div class="card-header p-0" id="heading-${groupId}">
                        <h2 class="mb-0">
                            <button class="btn btn-block text-left p-3 ${active ? '' : 'collapsed'}" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#${groupId}" 
                                    aria-expanded="${active}" aria-controls="${groupId}">
                                ${headerHtml}
                            </button>
                        </h2>
                    </div>
                    <div id="${groupId}" class="collapse ${active ? 'show' : ''}" 
                         aria-labelledby="heading-${groupId}" data-bs-parent="#${parentId}">
                        <div class="card-body js-tabcollapse-card-body">
                            </div>
                    </div>
                </div>
            `;
        }
    };

    /**
     * @class TabCollapse
     * @description Ana Eklenti Sınıfı
     */
    class TabCollapse {
        constructor(element, options) {
            this.$tabs = $(element);
            this.options = $.extend({}, DEFAULTS, options);

            this._determineBreakpoint();
            this._setResponsiveClasses();

            this._accordionVisible = false;
            this._resizeTimeout = null;

            this._initAccordion();
            this._bindEvents();
            this._bindAccordionEvents();

            // Asenkron kontrol (DOM yükleme/işleme sonrası doğru durumu yakalar)
            setTimeout(() => this.checkState(), 0);
        }

        /**
         * Navigasyon sınıfından özel breakpoint'i algılar.
         * tab-collapse-md -> breakpoint: 'md' olur.
         */
        _determineBreakpoint() {
            let userBreakpoint = this.options.breakpoint;
            
            // Sınıfları regex ile kontrol et: tab-collapse-X
            const classes = this.$tabs.attr('class');
            if (classes) {
                const match = classes.match(/tab-collapse-([a-z]+)/);
                if (match && match[1] && BREAKPOINTS.includes(match[1])) {
                    userBreakpoint = match[1];
                }
            }
            this.options.breakpoint = userBreakpoint;
        }

        /**
         * Belirlenen breakpoint'e göre Bootstrap 5 responsive sınıflarını ayarlar.
         */
        _setResponsiveClasses() {
            const bp = this.options.breakpoint;
            
            // Örnek: bp='lg' ise
            // Tabs: d-none d-lg-flex (lg'den itibaren görünür)
            // Accordion: d-lg-none (lg'ye kadar görünür)
            this.options.tabsClass = `d-none d-${bp}-flex`;
            this.options.accordionClass = `d-${bp}-none`;
        }


        // DOM'da akordeonun görünür olup olmadığını kontrol eder.
        checkState() {
            // Kontrol, akordeonun kendine özgü sınıfının (d-lg-none) görünürlüğünü baz alır.
            const isAccordionVisible = this.$accordion.css('display') !== 'none';
            // Alternatif ve daha güvenilir yol:
            // const isAccordionVisible = this.$accordion.is(':visible');

            if (!isAccordionVisible && this._accordionVisible) {
                // Akordeon gizli, sekmeler görünür olmalı
                this.showTabs();
                this._accordionVisible = false;
            } else if (isAccordionVisible && !this._accordionVisible) {
                // Akordeon görünür, akordeon moduna geçilmeli
                this.showAccordion();
                this._accordionVisible = true;
            }
        }

        /**
         * Akordeon görünümünden sekme görünümüne geçiş.
         */
        showTabs() {
            this.$tabs.trigger($.Event('show-tabs.bs.tabcollapse'));

            this.$accordion.find('.js-tabcollapse-card-header').each((i, header) => {
                const $header = $(header);
                const $parentLi = $header.data('bs.tabcollapse.parentLi');
                const $toggle = $parentLi.find('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');
                
                $toggle.attr({
                    'data-bs-toggle': 'tab',
                    'data-bs-target': $header.attr('data-bs-target'),
                    'aria-expanded': null,
                    'aria-controls': $header.attr('aria-controls'),
                }).removeClass('collapsed');
                
                $parentLi.removeClass('active');
                if ($header.data('bs.tabcollapse.isActive')) {
                    $parentLi.addClass('active');
                }
            });
            
            this.$accordion.find('.js-tabcollapse-card-body').each((i, body) => {
                const $cardBody = $(body);
                const $tabPane = $cardBody.data('bs.tabcollapse.tabpane');
                $tabPane.append($cardBody.contents().detach());
            });
            
            this.$accordion.html('');
            this.$tabs.trigger($.Event('shown-tabs.bs.tabcollapse'));
        }

        /**
         * Sekme görünümünden akordeon görünümüne geçiş.
         */
        showAccordion() {
            this.$tabs.trigger($.Event('show-accordion.bs.tabcollapse'));
            
            // UI Refresh: Diğer pluginleri uyandır
            $(window).trigger("resize");

            const $headers = this.$tabs.find('li:not(.dropdown) [data-bs-toggle="tab"], li:not(.dropdown) [data-bs-toggle="pill"]');
            
            $headers.each((i, element) => {
                const $header = $(element);
                const $parentLi = $header.closest('li');
                const isActive = $parentLi.hasClass('active');

                $header.data('bs.tabcollapse.parentLi', $parentLi);
                $header.data('bs.tabcollapse.isActive', isActive);
                
                const $card = this._createAccordionGroup(this.$accordion.attr('id'), $header, $header.html(), isActive);
                this.$accordion.append($card);

                // PRO EKLEME: Eğer aktifse, kartın kendisine de active class ver
                if(isActive) $card.addClass('active');
            });

            this.$tabs.trigger($.Event('shown-accordion.bs.tabcollapse'));
        }

        _bindAccordionEvents() {
            const _this = this;

            // Accordion içindeki kartlar açıldığında/kapandığında çalışır
            this.$accordion.on('shown.bs.collapse', '.collapse', function(e) {
                const $el = $(e.target);
                const $card = $el.closest('.card');
                
                // 1. Görsel aktiflik
                $card.addClass('active');

                // 2. Akıllı Scroll (Lenis veya Native)
                if (root.ui && root.ui.scroll_to) {
                    root.ui.scroll_to($card);
                }

                // 3. Tab Senkronizasyonu
                const tabId = $el.attr('id').replace('-collapse', '');
                const $tabPane = $('#' + tabId);
                const tabIndex = $card.index();

                // Üstteki tab navigasyonunu güncelle
                _this.$tabs.find('li').removeClass('active').eq(tabIndex).addClass('active');
                
                // Tab Pane'leri güncelle
                _this.getTabContentElement().find('.tab-pane').removeClass('active show in');
                $tabPane.addClass('active show');
            });

            this.$accordion.on('hidden.bs.collapse', '.collapse', function(e) {
                $(e.target).closest('.card').removeClass('active');
            });
        }
        
        // Yardımcı metot: Akordeon Grubu Oluşturma
        _createAccordionGroup(parentId, $header, headerHtml, active){
            // Tab İçeriği/Pane'ini bul
            let tabSelector = $header.attr('data-bs-target') || $header.attr('href');
            tabSelector = tabSelector && tabSelector.replace(/.*(?=#[^\s]*$)/, '');
            
            const $tabPane = $(tabSelector);

            // Akordeon grubu ID'sini oluştur
            const groupId = $tabPane.attr('id') + '-collapse';
            
            // Şablonu kullanarak kartı oluştur
            const $card = $(this.options.accordionTemplate(headerHtml, groupId, parentId, active));

            // Kart başlık button'ını özelleştir
            const $accordionToggle = $card.find('button');
            $accordionToggle.addClass('js-tabcollapse-card-header'); // İşaretleyici sınıf
            $accordionToggle.data('bs.tabcollapse.parentLi', $header.data('bs.tabcollapse.parentLi'));
            
            // Tab içeriğini (Pane) akordeon gövdesine taşı
            $card.find('.js-tabcollapse-card-body').append($tabPane.contents().detach())
                .data('bs.tabcollapse.tabpane', $tabPane); // Orijinal tab pane'i sakla

            return $card;
        }


        // Yardımcı metot: Akordeon yapısını başlat
        _initAccordion() {
            // Rastgele ID oluşturma fonksiyonu
            const randomString = () => Math.random().toString(36).substring(2, 7);

            const srcId = this.$tabs.attr('id') || randomString();
            const accordionId = srcId + '-accordion';

            // Akordeon kapsayıcısını oluştur
            this.$accordion = $(`<div class="accordion ${this.options.accordionClass}" id="${accordionId}"></div>`);
            
            // DOM'a ekle
            this.$tabs.after(this.$accordion);
            
            // Responsive sınıflarını uygula
            this.$tabs.addClass(this.options.tabsClass);
            this.getTabContentElement().addClass(this.options.tabsClass);
        }
        
        // Yardımcı metot: Tab içeriği elementini bulur
        getTabContentElement(){
            // Tab içeriği selector'ü options'a eklenmemişse, hemen yanındaki .tab-content'i bul
            return $(this.options.tabContentSelector) || this.$tabs.siblings('.tab-content');
        }

        // Olay dinleyicilerini bağla (resize)
        _bindEvents() {
            // Yüksek performans için Debounce mekanizması
            $(window).on('resize.bs.tabcollapse', () => {
                clearTimeout(this._resizeTimeout);
                this._resizeTimeout = setTimeout(() => {
                    this.checkState();
                }, 150); 
            });
        }
    } // End of TabCollapse Class

    // TABCOLLAPSE PLUGIN DEFINITION
    // =======================

    $.fn[NAME] = function (option) {
        return this.each(function () {
            const $this = $(this);
            let data = $this.data(`bs.${NAME}`);
            // Data attribute'larını ve özel option'ları birleştir
            const options = $.extend({}, $this.data(), typeof option === 'object' && option);

            if (!data) {
                // Eklenti örneğini oluştur ve data attribute'una kaydet
                data = new TabCollapse(this, options);
                $this.data(`bs.${NAME}`, data);
            }
            
            // Programatik kontrol
            if (typeof option === 'string') {
                data[option]();
            }
        });
    };

    $.fn[NAME].Constructor = TabCollapse;


    // VERİ API'SI - OTOMATİK İNİT
    // =======================
    // data-toggle="tabcollapse", .tab-collapse veya .tab-collapse-{breakpoint} sınıfı olan her şeyi otomatik başlatır.

    $(window).on('load', function () {
        $('[data-toggle="tabcollapse"], [data-bs-toggle="tabcollapse"],.tab-collapse, [class*="tab-collapse-"]').each(function () {
            const $tabNav = $(this);
            // Sadece bir kez başlatıldığından emin ol
            if (!$tabNav.data(`bs.${NAME}`)) {
                 $tabNav[NAME]();
            }
        });
    });

}(window.jQuery);