<?php

use Carbon\Carbon;

class ThemePost extends Post{

		public function merge_dates($simple = false) {
	    Carbon::setLocale($GLOBALS["language"] ?? 'tr');

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

class ThemeProduct extends Timber\Post{
	protected $product = null;

    public function get_title(){
        return qtranxf_use($GLOBALS["language"], $this->get_title(), false, false );
    }

	public function product( $post = null ) {
		if(!$this->product){
			$product = wc_get_product( $this->ID );
			$this->product = $product;			
		}
		return $this->product;
	}
	public function setup( $loop_index = 0 ) {
		global $wp_query;
		$wp_query->in_the_loop = true;
		$wp_query->setup_postdata( $this->ID );
        return $this;
    }
    public function teardown() {
		global $wp_query;
		$wp_query->in_the_loop = false;
		return $this;
	}

	public function get_product_type(){
		return WC_Product_Factory::get_product_type($this->id);
	}
	public function get_variation_url(){
		return variation_url_rewrite($this->link);
	}

	public function category() {
		$categories = $this->product->get_category_ids();
		if ( $categories ) {
			$category = reset( $categories );
			$category = Timber::get_term( $category );
			return $category;
		}
		return false;
	}

	/**
	 * Get a WooCommerce product attribute by slug.
	 *
	 * @api
	 *
	 * @param string $slug          The name of the attribute to get.
	 * @param bool   $convert_terms Whether to convert terms to Timber\Term objects.
	 *
	 * @return array|false
	 */
	public function get_product_attribute( $slug, $convert_terms = true ) {
		$attributes = $this->product->get_attributes();

		if ( ! $attributes || empty( $attributes ) ) {
			return false;
		}
		$attribute = false;

		foreach ( $attributes as $key => $value ) {
			if ( "pa_{$slug}" === $key ) {
				$attribute = $attributes[ $key ];
				break;
			}
		}

		if ( ! $attribute ) {
			return false;
		}

		if ( $attribute->is_taxonomy() ) {
			$terms = wc_get_product_terms(
				$this->product->get_id(),
				$attribute->get_name(),
				array(
					'fields' => 'all',
				)
			);

			if ( $convert_terms ) {
				$terms = array_map( function( $term ) {
					return Timber::get_term( $term );
				}, $terms );
			}

			return $terms;
		}

		return $attribute->get_options();
	}


	public function get_author(){
		$author_id = $this->book_author;
		if($author_id){
			return Timber::get_post($author_id);			
		}
		return false;
	}

    public function is_in_grouped(){
	    $grouped_products = wc_get_products(array(
	        'type'     => 'grouped',
	        'limit'    => -1,
	    ));
	    $grouped_product_ids = array();
	    foreach ($grouped_products as $grouped_product) {
	        $children_ids = $grouped_product->get_children();
	        if (in_array($this->ID, $children_ids)) {
	            $grouped_product_ids[] = $grouped_product->get_id();
	        }
	    }
        return $grouped_product_ids;
    }
    
}