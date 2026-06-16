<?php


use Carbon\Carbon;

class ThemePost extends Post{

    /*public function merge_dates($simple = false) {
	    Carbon::setLocale($GLOBALS["language"] ?? 'tr');

	    $dt1_raw = trim((string) $this->meta("start_date"));
	    $dt2_raw = trim((string) $this->meta("end_date"));
	    $period  = $this->meta("period") ?? false;

	    // Günleri locale'e göre çevir
	    $daysMap = [
	        0 => Carbon::create()->startOfWeek()->addDays(0)->translatedFormat('l'), // Pazartesi
	        1 => Carbon::create()->startOfWeek()->addDays(1)->translatedFormat('l'),
	        2 => Carbon::create()->startOfWeek()->addDays(2)->translatedFormat('l'),
	        3 => Carbon::create()->startOfWeek()->addDays(3)->translatedFormat('l'),
	        4 => Carbon::create()->startOfWeek()->addDays(4)->translatedFormat('l'),
	        5 => Carbon::create()->startOfWeek()->addDays(5)->translatedFormat('l'),
	        6 => Carbon::create()->startOfWeek()->addDays(6)->translatedFormat('l'), // Pazar
	    ];

	    $days = [];
	    if (is_array($period) && !empty($period)) {
	        foreach ($period as $d) {
	            if (isset($daysMap[$d])) {
	                $days[] = $daysMap[$d];
	            }
	        }
	    }

	    // start_date yok ve sadece period varsa
	    if (empty($dt1_raw) && empty($dt2_raw) && !empty($days)) {
	        return sprintf(
	            //translators: %s gün adlarını içerir
	            translate('Her %s'),
	            implode(", ", $days)
	        );
	    }

	    // start_date parse et
	    $dt1 = null;
	    if (!empty($dt1_raw)) {
	        try {
	            $dt1 = Carbon::createFromFormat('Y-m-d H:i', $dt1_raw);
	        } catch (\Exception $e) {
	            $dt1 = null;
	        }
	    }

	    // end_date parse et
	    $dt2 = null;
	    if (!empty($dt2_raw)) {
	        try {
	            $dt2 = Carbon::createFromFormat('Y-m-d H:i', $dt2_raw);
	        } catch (\Exception $e) {
	            $dt2 = null;
	        }
	    }

	    // sadece period + start_date varsa
	    if ($dt1 && !$dt2 && !empty($days)) {
	        return sprintf(
	            //translators: 1: başlangıç tarihi, 2: gün adları
	            translate('%1$s tarihinden itibaren her %2$s'),
	            $dt1->translatedFormat('j F Y l H:i'),
	            implode(", ", $days)
	        );
	    }

	    // sadece period + end_date varsa
	    if ($dt2 && !$dt1 && !empty($days)) {
	        return sprintf(
	            //translators: 1: bitiş tarihi, 2: gün adları
	            translate('%1$s tarihine kadar her %2$s'),
	            $dt2->translatedFormat('j F Y l H:i'),
	            implode(", ", $days)
	        );
	    }

	    // hem start hem end hem de period varsa
	    if ($dt1 && $dt2 && !empty($days)) {
	        return sprintf(
	            //translators: 1: başlangıç tarihi, 2: bitiş tarihi, 3: gün adları
	            translate('%1$s - %2$s arası her %3$s'),
	            $dt1->translatedFormat('j F Y l H:i'),
	            $dt2->translatedFormat('j F Y l H:i'),
	            implode(", ", $days)
	        );
	    }

	    // === period yok, default eski mantık ===
	    if ($dt1 && !$dt2) {
	        return $dt1->translatedFormat('j F Y l H:i');
	    }

	    if ($dt1 && $dt2) {
	        if ($dt1->isSameDay($dt2)) {
	            if ($dt1->hour === $dt2->hour) {
	                return $dt1->translatedFormat('j F Y l H:i');
	            } else {
	                return $dt1->translatedFormat('j F Y l H:i') . ' - ' . $dt2->translatedFormat('H:i');
	            }
	        }

	        if ($dt1->isSameYear($dt2)) {
	            if ($dt1->isSameMonth($dt2)) {
	                return $dt1->translatedFormat('j') . ' - ' . $dt2->translatedFormat('j') . ' ' .
	                       $dt1->translatedFormat('F Y H:i') . ' - ' . $dt2->translatedFormat('H:i');
	            } else {
	                return $dt1->translatedFormat('j F') . ' - ' . $dt2->translatedFormat('j F') . ' ' .
	                       $dt1->translatedFormat('Y H:i') . ' - ' . $dt2->translatedFormat('H:i');
	            }
	        }

	        return $dt1->translatedFormat('j F Y l H:i') . " - " . $dt2->translatedFormat('j F Y l H:i');
	    }

	    return null;
	}*/
	public function merge_dates($simple = false) {
	    Carbon::setLocale(Data::get("language") ?? 'tr');

	    $dt1_raw = trim((string) $this->meta("start_date"));
	    $dt2_raw = trim((string) $this->meta("end_date"));
	    $period  = $this->meta("period") ?? false;

	    // Formatları belirle
	    $dateTimeFormat = 'j F Y l H:i'; // Normal format (Tarih, Gün Adı, Saat)
	    $dateFormat = 'j F Y';          // Sadece Tarih formatı
	    $timeFormat = 'H:i';            // Sadece Saat formatı

	    // Ana formatı belirle
	    $mainFormat = $simple ? $dateFormat : $dateTimeFormat;

	    // Günleri locale'e göre çevir (DEĞİŞİKLİK YOK)
	    $daysMap = [
	        0 => Carbon::create()->startOfWeek()->addDays(0)->translatedFormat('l'), 
	        1 => Carbon::create()->startOfWeek()->addDays(1)->translatedFormat('l'),
	        2 => Carbon::create()->startOfWeek()->addDays(2)->translatedFormat('l'),
	        3 => Carbon::create()->startOfWeek()->addDays(3)->translatedFormat('l'),
	        4 => Carbon::create()->startOfWeek()->addDays(4)->translatedFormat('l'),
	        5 => Carbon::create()->startOfWeek()->addDays(5)->translatedFormat('l'),
	        6 => Carbon::create()->startOfWeek()->addDays(6)->translatedFormat('l'),
	    ];

	    $days = [];
	    if (is_array($period) && !empty($period)) {
	        foreach ($period as $d) {
	            if (isset($daysMap[$d])) {
	                $days[] = $daysMap[$d];
	            }
	        }
	    }

	    // start_date yok ve sadece period varsa
	    if (empty($dt1_raw) && empty($dt2_raw) && !empty($days)) {
	        // Basit modda bile çeviri metnini kullanıyoruz ki, dictionary'de karşılığı varsa gelsin.
	        return sprintf(
	            /* translators: %s gün adlarını içerir */
	            translate('Her %s'),
	            implode(", ", $days)
	        );
	    }

	    // start_date parse et (DEĞİŞİKLİK YOK)
	    $dt1 = null;
	    if (!empty($dt1_raw)) {
	        try {
	            $dt1 = Carbon::createFromFormat('Y-m-d H:i', $dt1_raw);
	        } catch (\Exception $e) {
	            $dt1 = null;
	        }
	    }

	    // end_date parse et (DEĞİŞİKLİK YOK)
	    $dt2 = null;
	    if (!empty($dt2_raw)) {
	        try {
	            $dt2 = Carbon::createFromFormat('Y-m-d H:i', $dt2_raw);
	        } catch (\Exception $e) {
	            $dt2 = null;
	        }
	    }

	    // sadece period + start_date varsa
	    if ($dt1 && !$dt2 && !empty($days)) {
	        if ($simple) {
	            // Basit modda: Çeviri metnini kullan, gün adlarını atla (sadece başlangıç tarihi)
	            return $dt1->translatedFormat($mainFormat);
	        }
	        return sprintf(
	            /* translators: 1: başlangıç tarihi, 2: gün adları */
	            translate('%1$s tarihinden itibaren her %2$s'),
	            $dt1->translatedFormat($mainFormat),
	            implode(", ", $days)
	        );
	    }

	    // sadece period + end_date varsa
	    if ($dt2 && !$dt1 && !empty($days)) {
	        if ($simple) {
	            // Basit modda: Çeviri metnini kullan, gün adlarını atla (sadece bitiş tarihi)
	            return $dt2->translatedFormat($mainFormat);
	        }
	        return sprintf(
	            /* translators: 1: bitiş tarihi, 2: gün adları */
	            translate('%1$s tarihine kadar her %2$s'),
	            $dt2->translatedFormat($mainFormat),
	            implode(", ", $days)
	        );
	    }

	    // hem start hem end hem de period varsa
	    if ($dt1 && $dt2 && !empty($days)) {
	        if ($simple) {
	            // Basit modda: Çeviri metnini kullan, gün adlarını atla (sadece tarih aralığı)
	            return $dt1->translatedFormat($mainFormat) . ' - ' . $dt2->translatedFormat($mainFormat);
	        }
	        return sprintf(
	            /* translators: 1: başlangıç tarihi, 2: bitiş tarihi, 3: gün adları */
	            translate('%1$s - %2$s arası her %3$s'),
	            $dt1->translatedFormat($mainFormat),
	            $dt2->translatedFormat($mainFormat),
	            implode(", ", $days)
	        );
	    }

	    // === period yok, default eski mantık ===
	    
	    // Sadece start_date varsa (DEĞİŞİKLİK YOK)
	    if ($dt1 && !$dt2) {
	        return $dt1->translatedFormat($mainFormat);
	    }

	    if ($dt1 && $dt2) {
	        
	        // 1. Aynı gün mü? (DEĞİŞİKLİK YOK)
	        if ($dt1->isSameDay($dt2)) {
	            if ($simple) {
	                return $dt1->translatedFormat($dateFormat);
	            }
	            if ($dt1->hour === $dt2->hour) {
	                return $dt1->translatedFormat($dateTimeFormat);
	            } else {
	                return $dt1->translatedFormat($dateTimeFormat) . ' - ' . $dt2->translatedFormat($timeFormat);
	            }
	        }
	        
	        // 2. Aynı yıl mı? (Daha önceki düzeltmeler geçerli)
	        if ($dt1->isSameYear($dt2)) {
	            // Simple modda saat bilgisi YOK. Normal modda saat formatını hazırla.
	            $timeSeparator = $simple ? '' : ' ' . $dt1->translatedFormat($timeFormat) . ' - ' . $dt2->translatedFormat($timeFormat);

	            if ($dt1->isSameMonth($dt2)) {
	                // Ay ve yıl aynı, sadece gün aralığı
	                $output = $dt1->translatedFormat('j') . ' - ' . $dt2->translatedFormat('j') . ' ' . $dt1->translatedFormat('F Y');

	                if (!$simple) {
	                    $output .= $timeSeparator;
	                }
	                return $output;

	            } else {
	                // Ay ve ay farklı
	                $output = $dt1->translatedFormat('j F') . ' - ' . $dt2->translatedFormat('j F') . ' ' . $dt1->translatedFormat('Y');
	                
	                if (!$simple) {
	                    $output .= $timeSeparator;
	                }
	                return $output;
	            }
	        }

	        // 3. Tam tarih ve saat aralığı (Yıl farklı) (DEĞİŞİKLİK YOK)
	        return $dt1->translatedFormat($mainFormat) . " - " . $dt2->translatedFormat($mainFormat);
	    }

	    return null;
	}
}

/**
 * ThemeProduct - WooCommerce + Timber product extend.
 *
 * @version 1.2.0
 *
 * @changelog
 *   1.2.0 - 2026-04-07
 *     - Add: Variation images - variation_images(), variation_thumbnails(), default_variation_images()
 *     - Add: Free shipping helpers - free_shipping_amount(), has_free_shipping()
 *     - Add: Price helpers - variable_price_html(), price_with_currency(), currency_symbol()
 *     - Add: Product cover bridge - product_cover(), woo_gallery()
 *     - Add: Variation loop data - variations_loop(), variations_unique()
 *     - Add: Order/payment helpers - order_ids(), payments(), payment_is_complete(), deposit_payment_is_complete()
 *     - Add: Role based pricing - role_based_price()
 *     - Add: Static methods - best_sellers(), available_categories()
 *     - Add: URL helpers - url_pa_parse()
 *   1.1.0 - 2026-04-03
 *     - Add: Full product extend - pricing, stock, badges, gallery, variations,
 *            attributes, categories, Schema.org, cart helpers, reviews
 *     - Fix: get_title infinite recursion + qtranxf_use safety
 *     - Fix: is_in_grouped performance (single SQL)
 *     - Fix: category() and get_product_attribute() null safety
 *   1.0.0 - Onceki stabil versiyon
 *
 * How to use (Twig):
 *
 *   Twig Kullanim                                              Aciklama                              Donus Ornegi
 *   ─────────────────────────────────────────────────────────── ───────────────────────────────────── ─────────────────────────────────────────────
 *
 *   -- PRICING --
 *   {{ post.price_html }}                                      Fiyat HTML (indirimli/normal)         '<del>200,00 TL</del> <ins>149,90 TL</ins>'
 *   {{ post.price }}                                           Aktif fiyat (sayi)                    149.9
 *   {{ post.regular_price }}                                   Normal fiyat                          200.0
 *   {{ post.sale_price }}                                      Indirimli fiyat                       149.9
 *   {{ post.sale_percentage }}                                 Indirim yuzdesi                       25
 *   {{ post.is_on_sale }}                                      Indirimde mi                          true
 *   {{ post.formatted_price }}                                 Formatli fiyat                        '149,90 TL'
 *   {{ post.variable_price_html }}                             Variable fiyat araligi                '<del>200 TL</del> <ins>149 TL</ins>'
 *   {{ post.price_with_currency }}                             Fiyat + para birimi                   '149,90 <span class="currency">TL</span>'
 *   {{ post.currency_symbol }}                                 Para birimi sembolu                   'TL'
 *   {{ post.role_based_price }}                                Kullaniciya ozel fiyat                ['regular_price' => '180', ...]
 *
 *   -- STOCK --
 *   {{ post.is_in_stock }}                                     Stokta mi                             true
 *   {{ post.stock_quantity }}                                  Stok miktari                          42
 *   {{ post.stock_status_label }}                              Stok durumu etiketi                   'Stokta'
 *   {{ post.is_low_stock }}                                    Dusuk stok mu                         true
 *   {{ post.low_stock_amount }}                                Low stock threshold                   5
 *   {{ post.backorders_allowed }}                              Backorder kabul ediyor mu             false
 *
 *   -- PRODUCT META --
 *   {{ post.sku }}                                             SKU                                   'PRD-001'
 *   {{ post.weight }}                                          Agirlik                               '1.5 kg'
 *   {{ post.dimensions }}                                      Boyutlar                              '30 x 20 x 10 cm'
 *   {{ post.product_type }}                                    Urun tipi                             'variable'
 *   {{ post.is_featured }}                                     One cikarilmis mi                     true
 *   {{ post.is_purchasable }}                                  Satin alinabilir mi                   true
 *   {{ post.is_virtual }}                                      Virtual urun mu                       false
 *   {{ post.is_downloadable }}                                 Downloadable mi                       false
 *   {{ post.short_description }}                               Kisa aciklama                         'Urun ozeti metni...'
 *
 *   -- IMAGES & GALLERY --
 *   {{ post.cover_url('large') }}                              Ana gorsel URL                        'https://site.com/image.jpg'
 *   {{ post.gallery_ids }}                                     Galeri attachment ID'leri              [45, 46, 47]
 *   {{ post.gallery_urls('medium') }}                          Galeri URL'leri                       ['https://...jpg', 'https://...jpg']
 *   {{ post.all_image_urls('large') }}                         Cover + galeri hepsi                  ['https://cover.jpg', 'https://g1.jpg', ...]
 *   {{ post.product_cover(true) }}                             Kapak gorselleri (grouped dahil)      ['https://...jpg', 'https://...jpg']
 *   {{ post.woo_gallery }}                                     WooCommerce galeri URL'leri            ['https://...jpg', 'https://...jpg']
 *
 *   -- VARIATION IMAGES --
 *   {{ post.variation_images('color','kirmizi','large') }}     Varyasyon gorselleri                  ['https://var1.jpg', 'https://var2.jpg']
 *   {{ post.variation_thumbnails('color','thumbnail') }}       Renk secici thumbnails                [{slug:'kirmizi', name:'Kirmizi', color:'#f00', image:'url'}, ...]
 *   {{ post.default_variation_images('large') }}               Default varyasyon gorselleri          ['https://default1.jpg', 'https://default2.jpg']
 *
 *   -- TAXONOMY --
 *   {{ post.categories }}                                      Kategoriler (Timber\Term[])           [Term{name:'Elbise'}, Term{name:'Kadin'}]
 *   {{ post.primary_category }}                                Birincil kategori                     Term{name:'Elbise', link:'/elbise/'}
 *   {{ post.tags }}                                            Etiketler (Timber\Term[])             [Term{name:'Yeni Sezon'}, ...]
 *
 *   -- ATTRIBUTES --
 *   {{ post.get_product_attribute('color') }}                  Tek attribute (Timber\Term[])         [Term{name:'Kirmizi'}, Term{name:'Mavi'}]
 *   {{ post.all_attributes }}                                  Tum attribute'lar                     {color: {label:'Renk', terms:[{id:1, name:'Kirmizi', slug:'kirmizi', color:'#f00'}], ...}}
 *
 *   -- VARIATIONS --
 *   {{ post.is_variable }}                                     Variable urun mu                      true
 *   {{ post.variations }}                                      Tum varyasyonlar (WC format)          [{variation_id:55, attributes:{...}, ...}, ...]
 *   {{ post.variation_attributes }}                            Varyasyon attribute'lari              {pa_color: ['kirmizi','mavi'], pa_size: ['s','m','l']}
 *   {{ post.default_variation_id }}                            Default varyasyon ID                  55
 *   {{ post.default_attributes }}                              Default attribute'lar                 {pa_color:'kirmizi', pa_size:'m'}
 *   {{ post.variation_url }}                                   SEO-friendly varyasyon URL            '/urun/elbise/color-kirmizi/'
 *   {{ post.variations_loop }}                                 Attribute loop data                   [{id:1, name:'Renk', slug:'pa_color', terms:[...]}, ...]
 *
 *   -- CART --
 *   {{ post.add_to_cart_url }}                                 Sepete ekle URL                       '/?add-to-cart=123'
 *   {{ post.add_to_cart_attrs }}                               Sepete ekle data attrs (HTML)         'data-product_id="123" data-quantity="1" ...'
 *   {{ post.add_to_cart_text }}                                Sepete ekle buton metni               'Sepete Ekle'
 *
 *   -- BADGES --
 *   {{ post.badges(200, 'discount,stock,shipping') }}          Urun badge'leri (HTML)                '<div class="product-badges">...</div>'
 *   {{ post.free_shipping_amount }}                            Ucretsiz kargo min tutar              200.0
 *   {{ post.has_free_shipping }}                               Ucretsiz kargoya uygun mu             true
 *
 *   -- RELATED / UPSELL / CROSS-SELL --
 *   {{ post.related_ids(4) }}                                  Iliskili urun ID'leri                 [45, 67, 89, 12]
 *   {{ post.upsell_ids }}                                      Upsell urun ID'leri                   [33, 44]
 *   {{ post.cross_sell_ids }}                                  Cross-sell urun ID'leri               [55, 66]
 *
 *   -- GROUPED --
 *   {{ post.is_in_grouped }}                                   Grouped urun parcasi mi               [101, 102]  (parent grouped ID'leri)
 *   {{ post.grouped_children }}                                Grouped child ID'leri                 [10, 11, 12]
 *
 *   -- REVIEWS / RATING --
 *   {{ post.average_rating }}                                  Ortalama puan                         4.5
 *   {{ post.review_count }}                                    Yorum sayisi                          28
 *   {{ post.rating_counts }}                                   Puan dagilimi                         {5:15, 4:8, 3:3, 2:1, 1:1}
 *
 *   -- SCHEMA.ORG --
 *   {{ post.schema }}                                          Schema.org Product data               {type:'Product', name:'...', offers:{...}, ...}
 *
 *   -- ORDER / PAYMENT --
 *   {{ post.order_ids }}                                       Siparis ID'leri                       [1001, 1002, 1003]
 *   {{ post.payments }}                                        Odeme bilgileri                       [{title:'Payment', id:1001, status:'completed', ...}]
 *   {{ post.payment_is_complete }}                             Odeme tamamlandi mi                   true
 *   {{ post.deposit_payment_is_complete }}                     Deposit odemesi tamamlandi mi         false
 *
 *   -- STATIC --
 *   ThemeProduct::best_sellers(10)                             En cok satanlar                       [{id:5, count:120}, {id:8, count:95}, ...]
 *   ThemeProduct::available_categories()                       Mevcut kategoriler                    [Timber\Term, Timber\Term, ...]
 *
 * Examples:
 *   {# Indirim badge #}
 *   {% if post.is_on_sale %}
 *       <span class="badge">-{{ post.sale_percentage }}%</span>
 *   {% endif %}
 *   <div class="price">{{ post.price_html }}</div>
 *
 *   {# Gallery slider #}
 *   {% for url in post.gallery_urls('large') %}
 *       <img src="{{ url }}" alt="{{ post.title }}">
 *   {% endfor %}
 */
class ThemeProduct extends Timber\Post {

    protected $product = null;

    // ── CORE ─────────────────────────────────────────────

    public function product() {
        if (!$this->product) {
            $this->product = wc_get_product($this->ID);
        }
        return $this->product;
    }

    public function get_title() {
        if (function_exists('qtranxf_use')) {
            return html_entity_decode(qtranxf_use(Data::get("language"), parent::title(), false, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return html_entity_decode(parent::title(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function setup($loop_index = 0) {
        global $wp_query;
        $wp_query->in_the_loop = true;
        $wp_query->setup_postdata($this->ID);
        return $this;
    }

    public function teardown() {
        global $wp_query;
        $wp_query->in_the_loop = false;
        return $this;
    }

    // ── PRICING ──────────────────────────────────────────

    public function price_html() {
        $p = $this->product();
        return $p ? $p->get_price_html() : '';
    }

    public function price() {
        $p = $this->product();
        return $p ? (float) $p->get_price() : 0;
    }

    public function regular_price() {
        $p = $this->product();
        return $p ? (float) $p->get_regular_price() : 0;
    }

    public function sale_price() {
        $p = $this->product();
        if (!$p) return 0;
        $sale = $p->get_sale_price();
        return $sale !== '' ? (float) $sale : 0;
    }

    public function sale_percentage() {
        $p = $this->product();
        if (!$p || !$p->is_on_sale()) return 0;

        if ($p->is_type('variable')) {
            $percentages = [];
            foreach ($p->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if ($child && $child->is_on_sale()) {
                    $regular = (float) $child->get_regular_price();
                    $sale = (float) $child->get_sale_price();
                    if ($regular > 0) {
                        $percentages[] = round((($regular - $sale) / $regular) * 100);
                    }
                }
            }
            return $percentages ? max($percentages) : 0;
        }

        $regular = (float) $p->get_regular_price();
        $sale = (float) $p->get_sale_price();
        return ($regular > 0 && $sale > 0) ? round((($regular - $sale) / $regular) * 100) : 0;
    }

    public function is_on_sale() {
        $p = $this->product();
        return $p ? $p->is_on_sale() : false;
    }

    public function formatted_price() {
        return wc_price($this->price());
    }

    // ── STOCK ────────────────────────────────────────────

    public function is_in_stock() {
        $p = $this->product();
        if (!$p) return false;
        // variation için: kendi stock'u yoksa parent'ın stock'una bak
        if ($p->is_type('variation')) {
            if ($p->managing_stock()) {
                return $p->is_in_stock();
            }
            // manage_stock kapalıysa parent'ın stock_status'ına bak
            $parent = wc_get_product($p->get_parent_id());
            return $parent ? $parent->is_in_stock() : $p->is_in_stock();
        }
        return $p->is_in_stock();
    }

    public function stock_quantity() {
        $p = $this->product();
        return $p ? $p->get_stock_quantity() : null;
    }

    public function stock_status_label() {
        $p = $this->product();
        if (!$p) return '';
        $map = [
            'instock'     => __('Stokta', 'woocommerce'),
            'outofstock'  => __('Tükendi', 'woocommerce'),
            'onbackorder' => __('Sipariş Üzerine', 'woocommerce'),
        ];
        return $map[$p->get_stock_status()] ?? $p->get_stock_status();
    }

    public function is_low_stock() {
        $p = $this->product();
        if (!$p) return false;
        $qty = $p->get_stock_quantity();
        if ($qty === null) return false;
        return $qty > 0 && $qty <= wc_get_low_stock_amount($p);
    }

    public function backorders_allowed() {
        $p = $this->product();
        return $p ? $p->backorders_allowed() : false;
    }

    // ── PRODUCT META ─────────────────────────────────────

    public function sku() {
        $p = $this->product();
        return $p ? $p->get_sku() : '';
    }

    public function weight() {
        $p = $this->product();
        if (!$p || !$p->has_weight()) return '';
        return $p->get_weight() . ' ' . get_option('woocommerce_weight_unit');
    }

    public function dimensions() {
        $p = $this->product();
        if (!$p || !$p->has_dimensions()) return '';
        return wc_format_dimensions($p->get_dimensions(false));
    }

    public function product_type() {
        $p = $this->product();
        return $p ? $p->get_type() : '';
    }

    public function get_product_type() {
        return $this->product_type();
    }

    public function is_featured() {
        $p = $this->product();
        return $p ? $p->is_featured() : false;
    }

    public function is_purchasable() {
        $p = $this->product();
        return $p ? $p->is_purchasable() : false;
    }

    public function is_virtual() {
        $p = $this->product();
        return $p ? $p->is_virtual() : false;
    }

    public function is_downloadable() {
        $p = $this->product();
        return $p ? $p->is_downloadable() : false;
    }

    public function short_description() {
        $p = $this->product();
        return $p ? $p->get_short_description() : '';
    }

    // ── IMAGES & GALLERY ─────────────────────────────────

    public function gallery_ids() {
        $p = $this->product();
        return $p ? $p->get_gallery_image_ids() : [];
    }

    public function gallery_urls($size = 'full') {
        $urls = [];
        foreach ($this->gallery_ids() as $id) {
            $url = wp_get_attachment_image_url($id, $size);
            if ($url) $urls[] = $url;
        }
        return $urls;
    }

    public function cover_url($size = 'full') {
        $p = $this->product();
        if (!$p) return '';
        $image_id = $p->get_image_id();
        // variation'ın kendi resmi yoksa parent'ın resmini kullan
        if (!$image_id && $p->is_type('variation')) {
            $parent = wc_get_product($p->get_parent_id());
            if ($parent) $image_id = $parent->get_image_id();
        }
        return $image_id ? wp_get_attachment_image_url($image_id, $size) : '';
    }

    public function all_image_urls($size = 'full') {
        $images = [];
        $cover = $this->cover_url($size);
        if ($cover) $images[] = $cover;
        return array_merge($images, $this->gallery_urls($size));
    }

    // ── TAXONOMY ─────────────────────────────────────────

    public function categories() {
        $p = $this->product();
        if (!$p) return [];
        $ids = $p->get_category_ids();
        if (!$ids) return [];
        return array_map(function ($id) { return Timber::get_term($id); }, $ids);
    }

    public function primary_category() {
        $cats = $this->categories();
        return $cats ? reset($cats) : false;
    }

    public function category() {
        return $this->primary_category();
    }

    public function tags() {
        $p = $this->product();
        if (!$p) return [];
        $ids = $p->get_tag_ids();
        if (!$ids) return [];
        return array_map(function ($id) { return Timber::get_term($id); }, $ids);
    }

    // ── ATTRIBUTES ───────────────────────────────────────

    public function get_product_attribute($slug, $convert_terms = true) {
        $product = $this->product();
        if (!$product) return false;
        $attributes = $product->get_attributes();
        if (!$attributes) return false;

        $attribute = $attributes["pa_{$slug}"] ?? false;
        if (!$attribute) return false;

        if ($attribute->is_taxonomy()) {
            $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
            if ($convert_terms) {
                $terms = array_map(function ($term) { return Timber::get_term($term); }, $terms);
            }
            return $terms;
        }
        return $attribute->get_options();
    }

    public function all_attributes() {
        $product = $this->product();
        if (!$product) return [];
        $result = [];
        $attributes = $product->get_attributes();
        if (!$attributes) return [];

        foreach ($attributes as $key => $attribute) {
            $slug = str_replace('pa_', '', $key);
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all']);
                $result[$slug] = [
                    'label'     => wc_attribute_label($attribute->get_name()),
                    'terms'     => array_map(function ($term) {
                        return [
                            'id' => $term->term_id, 'name' => $term->name,
                            'slug' => $term->slug, 'color' => get_term_meta($term->term_id, 'color', true),
                        ];
                    }, $terms),
                    'visible'   => $attribute->get_visible(),
                    'variation' => $attribute->get_variation(),
                ];
            } else {
                $result[$slug] = [
                    'label'     => $attribute->get_name(),
                    'terms'     => $attribute->get_options(),
                    'visible'   => $attribute->get_visible(),
                    'variation' => $attribute->get_variation(),
                ];
            }
        }
        return $result;
    }

    // ── VARIATIONS ───────────────────────────────────────

    public function is_variable() {
        return $this->product_type() === 'variable';
    }

    public function variations() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];
        return $p->get_available_variations();
    }

    public function variation_attributes() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];
        return $p->get_variation_attributes();
    }

    /**
     * product_variation için seçili attribute değerlerini döndürür.
     * Twig: post.variation_selected_attrs
     * Döner: [['label'=>'Renk', 'slug'=>'color', 'value'=>'kirmizi', 'name'=>'Kırmızı', 'type'=>'color', 'color'=>'#f00'], ...]
     */
    public function variation_selected_attrs(): array {
        $p = $this->product();
        if (!$p || !$p->is_type('variation')) return [];

        $result = [];
        $attrs  = $p->get_variation_attributes(); // ['attribute_pa_color' => 'kirmizi', ...]

        foreach ($attrs as $key => $value) {
            if (empty($value)) continue;
            $slug     = str_replace('attribute_pa_', '', $key);
            $taxonomy = 'pa_' . $slug;
            $term     = get_term_by('slug', $value, $taxonomy);
            $label    = wc_attribute_label($taxonomy);
            $type     = get_option("woocommerce_pa_{$slug}_type", 'select'); // color/image/button/select

            $result[] = [
                'label' => $label,
                'slug'  => $slug,
                'value' => $value,
                'name'  => $term ? $term->name : $value,
                'type'  => $type,
                'color' => $term ? (string) get_term_meta($term->term_id, 'color', true) : '',
                'image' => $term ? (string) get_term_meta($term->term_id, 'product_attribute_image', true) : '',
            ];
        }
        return $result;
    }

    public function default_variation_id() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return 0;
        $attributes = $p->get_default_attributes();
        if (!$attributes) return 0;
        $lookup = [];
        foreach ($attributes as $key => $value) {
            $lookup[strpos($key, 'attribute_') === 0 ? $key : "attribute_{$key}"] = $value;
        }
        if (class_exists('WC_Data_Store')) {
            return WC_Data_Store::load('product')->find_matching_product_variation($p, $lookup);
        }
        return $p->get_matching_variation($lookup);
    }

    public function default_attributes() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];
        return $p->get_default_attributes();
    }

    public function variation_url() {
        $p = $this->product();
        // product_variation için parent URL + attribute query string
        if ($p && $p->is_type('variation')) {
            $url = $p->get_permalink();
            return function_exists('variation_url_rewrite') ? variation_url_rewrite($url) : $url;
        }
        return function_exists('variation_url_rewrite') ? variation_url_rewrite($this->link) : $this->link;
    }

    public function get_variation_url() {
        return $this->variation_url();
    }

    // ── CART ─────────────────────────────────────────────

    public function add_to_cart_url() {
        $p = $this->product();
        return $p ? $p->add_to_cart_url() : '';
    }

    public function add_to_cart_attrs() {
        return function_exists('get_add_to_cart_attrs') ? get_add_to_cart_attrs($this->ID) : '';
    }

    public function add_to_cart_text() {
        $p = $this->product();
        return $p ? $p->add_to_cart_text() : '';
    }

    // ── BADGES ───────────────────────────────────────────

    public function badges($free_shipping_min = 0, $types = 'discount,stock,shipping') {
        $p = $this->product();
        if (!$p) return '';
        return function_exists('woo_product_badges') ? woo_product_badges($p, $free_shipping_min, $types) : '';
    }

    // ── RELATED / UPSELL / CROSS-SELL ────────────────────

    public function related_ids($limit = 4) {
        return wc_get_related_products($this->ID, $limit);
    }

    public function upsell_ids() {
        $p = $this->product();
        return $p ? $p->get_upsell_ids() : [];
    }

    public function cross_sell_ids() {
        $p = $this->product();
        return $p ? $p->get_cross_sell_ids() : [];
    }

    // ── GROUPED ──────────────────────────────────────────

    public function is_in_grouped() {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' AND p.post_status = 'publish'
            AND pm.meta_key = '_children' AND pm.meta_value LIKE %s
        ", '%"' . (int) $this->ID . '"%'));
    }

    public function grouped_children() {
        $p = $this->product();
        if (!$p || !$p->is_type('grouped')) return [];
        return $p->get_children();
    }

    // ── REVIEWS / RATING ─────────────────────────────────

    public function average_rating() {
        $p = $this->product();
        return $p ? (float) $p->get_average_rating() : 0;
    }

    public function review_count() {
        $p = $this->product();
        return $p ? (int) $p->get_review_count() : 0;
    }

    public function rating_counts() {
        $p = $this->product();
        return $p ? $p->get_rating_counts() : [];
    }

    // ── SCHEMA.ORG ───────────────────────────────────────

    public function schema() {
        $p = $this->product();
        if (!$p) return [];

        $schema = [
            '@type'       => 'Product',
            'name'        => $this->get_title(),
            'description' => wp_strip_all_tags($p->get_short_description() ?: $p->get_description()),
            'sku'         => $p->get_sku(),
            'image'       => $this->cover_url('large'),
            'url'         => $this->link,
        ];

        $brands = $this->terms('wpc-brand');
        if (!$brands) $brands = $this->terms('product_brand');
        if ($brands) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $brands[0]->name];
        }

        $schema['offers'] = [
            '@type'         => 'Offer',
            'url'           => $this->link,
            'priceCurrency' => get_woocommerce_currency(),
            'price'         => $p->get_price(),
            'availability'  => $p->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        if ($p->get_review_count() > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $p->get_average_rating(),
                'reviewCount' => $p->get_review_count(),
            ];
        }
        return $schema;
    }

    // ── VARIATION IMAGES ─────────────────────────────────

    /**
     * Varyasyon gorselleri (attribute + value bazli).
     * Ornek: post.variation_images('color', 'kirmizi', 'large')
     * @param string $attr Attribute slug (color, size vs.)
     * @param string $attr_value Attribute value slug (kirmizi, mavi vs.)
     * @param string $size WP image size
     * @return array URL listesi
     */
    public function variation_images($attr, $attr_value = '', $size = 'full') {
        if (function_exists('woo_get_product_variation_thumbnails')) {
            return woo_get_product_variation_thumbnails($this->ID, $attr, $attr_value, $size);
        }
        return [];
    }

    /**
     * Her varyasyon degeri icin tek thumbnail (renk secici icin).
     * Ornek: post.variation_thumbnails('color', 'thumbnail')
     * Doner: [['slug' => 'kirmizi', 'image' => 'url'], ...]
     * @param string $attr Attribute slug
     * @param string $size WP image size
     */
    public function variation_thumbnails($attr, $size = 'thumbnail') {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return [];

        $seen = [];
        $result = [];
        $variations = $p->get_available_variations();

        foreach ($variations as $variation) {
            $key = "attribute_pa_{$attr}";
            if (!isset($variation['attributes'][$key])) continue;
            $value = $variation['attributes'][$key];
            if (isset($seen[$value])) continue;
            $seen[$value] = true;

            $image_url = '';
            if (!empty($variation['image_id'])) {
                $image_url = wp_get_attachment_image_url($variation['image_id'], $size);
            } elseif (!empty($variation['image'][$size . '_src'])) {
                $image_url = $variation['image'][$size . '_src'];
            }

            $term = get_term_by('slug', $value, "pa_{$attr}");
            $result[] = [
                'slug'  => $value,
                'name'  => $term ? $term->name : $value,
                'color' => $term ? get_term_meta($term->term_id, 'color', true) : '',
                'image' => $image_url,
            ];
        }
        return $result;
    }

    /**
     * Default varyasyonun gorselleri.
     * Ornek: post.default_variation_images('large')
     */
    public function default_variation_images($size = 'full') {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return $this->all_image_urls($size);

        $var_id = $this->default_variation_id();
        if (!$var_id) return $this->all_image_urls($size);

        $images = [];
        $variation = wc_get_product($var_id);
        if (!$variation) return $this->all_image_urls($size);

        $image_id = $variation->get_image_id();
        if ($image_id) {
            $url = wp_get_attachment_image_url($image_id, $size);
            if ($url) $images[] = $url;
        }

        $additional = get_post_meta($var_id, '_wc_additional_variation_images', true);
        if ($additional) {
            foreach (array_filter(explode(',', $additional)) as $add_id) {
                $url = wp_get_attachment_image_url($add_id, $size);
                if ($url) $images[] = $url;
            }
        }

        return $images ?: $this->all_image_urls($size);
    }

    // ── FREE SHIPPING ────────────────────────────────────

    /**
     * Ucretsiz kargo minimum tutari.
     */
    public function free_shipping_amount($multiply_by = 1) {
        return function_exists('get_free_shipping_amount') ? get_free_shipping_amount($multiply_by) : 0;
    }

    /**
     * Ucretsiz kargoya uygun mu?
     */
    public function has_free_shipping() {
        $min = $this->free_shipping_amount();
        return $min > 0 && $this->price() >= $min;
    }

    // ── PRICE HELPERS ────────────────────────────────────

    /**
     * Variable urun fiyat araligi HTML.
     */
    public function variable_price_html() {
        $p = $this->product();
        if (!$p || !$p->is_type('variable')) return $this->price_html();
        return function_exists('variable_product_price') ? variable_product_price($p) : $p->get_price_html();
    }

    /**
     * Fiyat + para birimi formatli.
     * Ornek: "149,90 ₺"
     */
    public function price_with_currency($price = null) {
        if ($price === null) $price = $this->price();
        return function_exists('woo_get_currency_with_price') ? woo_get_currency_with_price($price) : wc_price($price);
    }

    /**
     * Para birimi sembolu.
     */
    public function currency_symbol() {
        return get_woocommerce_currency_symbol();
    }

    // ── PRODUCT COVER (functions.php bridge) ─────────────

    /**
     * Urun kapak gorselleri (simple, variable, grouped destekli).
     * functions.php'deki get_product_cover() fonksiyonunu kullanir.
     * @param bool $multiple Coklu gorsel dondur
     */
    public function product_cover($multiple = false) {
        return function_exists('get_product_cover') ? get_product_cover($this->product(), $multiple) : ($multiple ? [] : '');
    }

    // ── GALLERY (functions.php bridge) ───────────────────

    /**
     * WooCommerce galeri URL'leri (functions.php bridge).
     */
    public function woo_gallery() {
        return function_exists('woo_get_product_gallery') ? woo_get_product_gallery($this->ID) : [];
    }

    // ── VARIATION LOOP DATA ──────────────────────────────

    /**
     * Urun attribute'larini loop icin hazir data olarak doner.
     * functions.php'deki woo_get_product_variations_loop() bridge.
     */
    public function variations_loop() {
        $p = $this->product();
        if (!$p) return [];
        return function_exists('woo_get_product_variations_loop') ? woo_get_product_variations_loop($p) : [];
    }

    /**
     * Unique varyasyon degerleri.
     */
    public function variations_unique($arr = []) {
        return function_exists('woo_get_product_variations_unique') ? woo_get_product_variations_unique($arr) : [];
    }

    // ── LOW STOCK ────────────────────────────────────────

    /**
     * Low stock threshold degeri.
     */
    public function low_stock_amount() {
        $p = $this->product();
        if (!$p) return 0;
        return function_exists('woo_get_product_low_stock_amount') ? woo_get_product_low_stock_amount($p) : 0;
    }

    // ── URL HELPERS ──────────────────────────────────────

    /**
     * Attribute bazli URL parse (SEO-friendly variation URL).
     */
    public function url_pa_parse($variation = '') {
        $p = $this->product();
        if (!$p) return [];
        return function_exists('woo_url_pa_parse') ? woo_url_pa_parse($p, $variation) : [];
    }

    // ── ROLE BASED PRICING ───────────────────────────────

    /**
     * Kullaniciya ozel fiyat (role based pricing).
     */
    public function role_based_price($user = null) {
        if (!$user) $user = wp_get_current_user();
        return function_exists('role_based_price') ? role_based_price($user, $this) : [];
    }

    // ── ORDER / PAYMENT HELPERS ──────────────────────────

    /**
     * Bu urune ait siparis ID'leri.
     */
    public function order_ids() {
        return function_exists('get_orders_ids_by_product_id') ? get_orders_ids_by_product_id($this->ID) : [];
    }

    /**
     * Bu urunun odeme bilgileri.
     */
    public function payments() {
        return function_exists('get_product_payments') ? get_product_payments($this->ID) : [];
    }

    /**
     * Odeme tamamlandi mi (deposit dahil)?
     */
    public function payment_is_complete($forced = false) {
        return function_exists('product_payment_is_complete') ? product_payment_is_complete($this->ID, $forced) : false;
    }

    /**
     * Deposit odemesi tamamlandi mi?
     */
    public function deposit_payment_is_complete() {
        return function_exists('product_deposit_payment_is_complete') ? product_deposit_payment_is_complete($this->ID) : false;
    }

    // ── BEST SELLERS ─────────────────────────────────────

    /**
     * En cok satan urunler (statik).
     */
    public static function best_sellers($limit = 10) {
        return function_exists('get_best_selling_products') ? get_best_selling_products($limit) : [];
    }

    // ── AVAILABLE CATEGORIES ─────────────────────────────

    /**
     * Mevcut urun kategorileri (shop/archive icin).
     */
    public static function available_categories() {
        return function_exists('woo_get_available_categories') ? woo_get_available_categories() : [];
    }

    // ── MISC / BACKWARD COMPAT ───────────────────────────

    public function get_author() {
        $author_id = $this->meta('book_author');
        return $author_id ? Timber::get_post($author_id) : false;
    }
}