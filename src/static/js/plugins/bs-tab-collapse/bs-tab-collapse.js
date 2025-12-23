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
        breakpoint: 'lg', 
        
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

            // 1. Akordeon Başlıklarını Geri Taşı
            this.$accordion.find('.js-tabcollapse-card-header').each((i, header) => {
                const $header = $(header);
                const $parentLi = $header.data('bs.tabcollapse.parentLi');
                const $toggle = $parentLi.find('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');
                
                // Başlık ögelerini asıl yerlerine koy (Akordeon niteliklerini kaldır)
                $toggle.attr({
                    'data-bs-toggle': 'tab',
                    'data-bs-target': $header.attr('data-bs-target'),
                    'aria-expanded': null,
                    'aria-controls': $header.attr('aria-controls'),
                }).removeClass('collapsed');
                
                // Aktif sınıfı güncelle
                $parentLi.removeClass('active');
                if ($header.data('bs.tabcollapse.isActive')) {
                    $parentLi.addClass('active');
                }
            });
            
            // 2. Tab İçeriklerini Geri Taşı
            this.$accordion.find('.js-tabcollapse-card-body').each((i, body) => {
                const $cardBody = $(body);
                const $tabPane = $cardBody.data('bs.tabcollapse.tabpane');
                // İçeriği (contents) ana tab pane'e taşı
                $tabPane.append($cardBody.contents().detach());
            });
            
            // 3. Akordeon kapsayıcısını temizle
            this.$accordion.html('');

            this.$tabs.trigger($.Event('shown-tabs.bs.tabcollapse'));
        }

        /**
         * Sekme görünümünden akordeon görünümüne geçiş.
         */
        showAccordion() {
            this.$tabs.trigger($.Event('show-accordion.bs.tabcollapse'));

            // Tab başlıklarını al (sadece data-bs-toggle'ı olanları)
            const $headers = this.$tabs.find('li:not(.dropdown) [data-bs-toggle="tab"], li:not(.dropdown) [data-bs-toggle="pill"]');
            
            $headers.each((i, element) => {
                const $header = $(element);
                const $parentLi = $header.closest('li');
                const isActive = $parentLi.hasClass('active');

                // Orijinal verileri sakla
                $header.data('bs.tabcollapse.parentLi', $parentLi);
                $header.data('bs.tabcollapse.isActive', isActive);
                
                const headerHtml = $header.html();
                
                // Akordeon Grubu Oluştur
                const $card = this._createAccordionGroup(this.$accordion.attr('id'), $header, headerHtml, isActive);
                
                // Akordeona ekle
                this.$accordion.append($card);
            });

            this.$tabs.trigger($.Event('shown-accordion.bs.tabcollapse'));
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
        $('[data-toggle="tabcollapse"], .tab-collapse, [class*="tab-collapse-"]').each(function () {
            const $tabNav = $(this);
            // Sadece bir kez başlatıldığından emin ol
            if (!$tabNav.data(`bs.${NAME}`)) {
                 $tabNav[NAME]();
            }
        });
    });

}(window.jQuery);