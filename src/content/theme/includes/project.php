<?php

use Timber\Post;

class Project {

    public $timezone = "";

	public function __construct() {
        $this->timezone = get_option('timezone_string');
		add_action('save_post', [$this, 'campaign_published'], 10, 3);
        add_action('save_post', [$this, 'store_published'], 10, 3);
        add_action('save_post', [$this, 'etkinlikler_published'], 10, 3);
        add_action('save_post', [$this, 'translate_offcanvas_menu_item'], 20, 3);

        add_filter('acf/load_value/name=stores', [$this, 'acf_stores_load_value'], 10, 3);

        add_action('acf/init', function() {
            add_filter('acf/load_field/key=field_68a7922f96596', [$this, "load_store_map_field_params"], 10, 1);
            add_filter('acf/load_value/key=field_68a7922f96596', [$this, "load_store_map_value"], 99999, 3);
            add_action('acf/render_field/key=field_68a7b28f30702', [$this, "render_store_map"], 1);
            //add_action('acf/load_field/name=map', [$this, "load_store_map"], 10);
        });
        add_filter('pae_get_store_floors', [$this, "get_store_floors"], 10, 1);
	}

	public function response(){
        return array(
            "error"       => false,
            "message"     => '',
            "description" => '',
            "data"        =>  "",
            "resubmit"    => false,
            "redirect"    => "",
            "refresh"     => false,
            "html"        => "",
            "template"    => ""
        );
    }
    
    /**
	 * - id (int) → Post ID (kat veya katlar için)
	 * - campaign (mixed) → Kampanya filtresi (varsa mağazaları kampanyaya göre filtreler)
	 * - keyword (string) → Arama kelimesi (mağaza ismi vb.)
	 * - store_id (int) → Kampanya sorgusunda kullanılmak üzere mağaza ID'si (kat içindeki alt çağrıda ekleniyor)
	 *   (Not: store_id doğrudan bu fonksiyonun dışarıdan aldığı bir parametre değil, kampanya sorgusunda kullanılıyor)
	*/
    public function kat_v1($vars = array()){
        $response = $this->response();
        if(empty($vars["id"])) return $response;

        $post = Timber::get_post($vars["id"]);
        $stores_field = get_field("stores", $vars["id"]);
        $post->stores = [];

        if($stores_field){
            $store_ids = array_column($stores_field, 'store');

            // 1) Mağazaları tek seferde çek
            $args = [
                'post_type'      => 'magazalar',
                'post__in'       => $store_ids,
                'posts_per_page' => -1,
                'orderby'        => 'post__in',
                'order'          => 'ASC'
            ];

            if(!empty($vars["keyword"])){
                $args["s"] = $vars["keyword"];
            }
            if(!pll_is_translated_post_type("magazalar")){
                $args["lang"] = $GLOBALS["language_default"];
            }
            $args = wp_query_addition($args, $vars);

            $store_posts = Timber::get_posts($args)->to_array();
            if($store_posts){
                $store_ids_ordered = wp_list_pluck($store_posts, "ID");

                // 2) Kampanyaları tek seferde al
                $campaigns = $this->kampanyalar([
                    "store_id__in" => $store_ids_ordered
                ])["data"];

                // store_id -> kampanya map
                $campaign_map = [];
                foreach($campaigns as $c){
                    $sid = get_field("store", $c->ID);
                    if($sid) $campaign_map[$sid][] = $c;
                }

                // 3) Store sırasını koruyarak birleştir
                $stores_field = array_values(array_filter($stores_field, function($item) use ($store_ids_ordered){
                    return in_array($item['store'], $store_ids_ordered);
                }));

                foreach($store_posts as $store_post){
                    $index = array_search($store_post->ID, $store_ids_ordered);
                    $store_item = $stores_field[$index] ?? null;
                    if(!$store_item) continue;

                    $store_post->campaigns = $campaign_map[$store_post->ID] ?? [];

                    if(!empty($vars["campaign"]) && empty($store_post->campaigns)){
                        continue;
                    }

                    $store_post->store_meta = $this->kat_find(["store_id" => $store_post->ID]);

                    $store_item["store"] = $store_post;
                    $post->stores[] = $store_item;
                }
            }
        }

        $response["data"] = $post;
        return $response;
    }
    public function kat($vars = array()){
        $response = $this->response();
        if(empty($vars["id"])) return $response;

        $post = Timber::get_post($vars["id"]);
        $stores_field = get_field("stores", $vars["id"]);
        $post->stores = [];

        if($stores_field){
            $store_ids = array_column($stores_field, 'store');
            $store_ids = array_filter($store_ids);

            // 1) Mağazaları tek seferde çek
            $args = [
                'post_type'      => 'magazalar',
                'post__in'       => $store_ids,
                'posts_per_page' => -1,
                'orderby'        => 'post__in',
                'order'          => 'ASC',
            ];

            if(!empty($vars["keyword"])){
                $args["s"] = $vars["keyword"];
            }
            if(!pll_is_translated_post_type("magazalar")){
                $args["lang"] = "tr,''";
            }


            $args = wp_query_addition($args, $vars);


            $store_posts = Timber::get_posts($args)->to_array();
            if($store_posts){
                /*$store_ids_ordered = wp_list_pluck($store_posts, "ID");

                // 2) Kampanyaları tek seferde al
                $campaigns = $this->kampanyalar([
                    "store_id__in" => $store_ids_ordered
                ])["data"];

                // store_id -> kampanya map
                $campaign_map = [];
                foreach($campaigns as $c){
                    $sid = get_field("store", $c->ID);
                    if($sid) $campaign_map[$sid][] = $c;
                }

                // 3) Store sırasını koruyarak birleştir
                $stores_field = array_values(array_filter($stores_field, function($item) use ($store_ids_ordered){
                    return in_array($item['store'], $store_ids_ordered);
                }));*/

                foreach($store_posts as $store_post){
                    $index = array_search($store_post->ID, $store_ids);
                    $store_item = $stores_field[$index] ?? null;

                    if(!$store_item) continue;

                    $store_post = $this->store(["store_id" => $store_post->ID]);

                    $store_post->no = $store_item["no"];
                    $store_post->store_appendix = $store_item["store_appendix"];
                    $store_post->search_only = $store_item["search_only"];

                    /*$store_post->campaigns = $campaign_map[$store_post->ID] ?? [];

                    if(!empty($vars["campaign"]) && empty($store_post->campaigns)){
                        continue;
                    }*/

                    //if(!empty($vars["campaign"]) && empty($store_post->store_meta->campaigns)){
                    if(!empty($vars["campaign"]) && empty($store_post->store_meta["campaigns"])){
                        continue;
                    }

                    /*$store_post->store_meta = $this->kat_find(["store_id" => $store_post->ID]);*/

                    //$store_item["store"] = $store_post;
                    $post->stores[] = $store_post;//$store_item;
                }
            }
        }

        $response["data"] = $post;
        return $response;
    }

    



    /**
	 * - id (int) → Kat ID'si (tek bir kat getirmek için)
	 * - ignore_empty_floor (bool) → Boş katları (store'suz) dahil etmemek için
	 * - campaign (mixed) → Kat içindeki mağaza filtrelerinde kullanılmak üzere
	 * - keyword (string) → Kat içindeki mağaza filtrelerinde kullanılmak üzere
	*/
    public function katlar($vars = array()){
        $response = $this->response();
        $ignore_empty_floor = !empty($vars["ignore_empty_floor"]);

        // Kat ID’leri alınacak
        $posts = [];
        if(!empty($vars["id"])){
            $posts = [$vars["id"]];
        } else {
            $args = [
                "post_type"   => "katlar",
                "orderby"     => "menu_order",
                "order"       => "ASC",
                "numberposts" => -1,
                "fields"      => "ids"
            ];
            if(!pll_is_translated_post_type("katlar")){
                $args["lang"] = "";
            }
            $posts = get_posts($args);
        }

        $data = [];
        $store_count = 0;

        foreach($posts as $post_id){
            $vars["id"] = $post_id;
            //$vars["lang"] = "en";
            $floor = $this->kat($vars)["data"];
            if($ignore_empty_floor && empty($floor->stores)){
                continue;
            }
            $data[] = $floor;
            $store_count += count($floor->stores);
        }

        $response["count"] = $store_count;
        $response["data"] = $data;
        return $response;
    }

    
    /**
	 * - store_id (int)
	 * - ignore_empty_floor (bool) → Boş katları (store'suz) dahil etmemek için
	 * - campaign (mixed) → Kat içindeki mağaza filtrelerinde kullanılmak üzere
	 * - keyword (string) → Kat içindeki mağaza filtrelerinde kullanılmak üzere
	*/
    public function kat_find($vars = array()) {
        $data = [];

        $store_id = isset($vars['store_id']) ? (int)$vars['store_id'] : 0;
        if(!$store_id) return $data;

        global $wpdb;
        $pattern = '^stores_[0-9]+_store$';
        $sql = $wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','private')
              AND pm.meta_key REGEXP %s
              AND pm.meta_value = %d
        ", 'katlar', $pattern, $store_id);
        $floor_ids = $wpdb->get_col($sql);
        if($floor_ids){
            $args = [
                'post_type'      => 'katlar',
                'post__in'       => $floor_ids,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'posts_per_page' => -1,
            ];
            if(!pll_is_translated_post_type("katlar")) $args["lang"] = "";
            $floors = Timber::get_posts($args)->to_array();

            $store = Timber::get_post($vars['store_id']);
            $terms = $store->terms("hizmetler");
            $data["hizmetler"] = $terms;


            if($floors){
                $store_title = get_the_title($store_id);
                $store_map = get_field('store_map', $store_id);
                $data['floors'] = [];

                $title = [];
                $link  = [];
                foreach($floors as $key => $floor){
                    $stores = get_field('stores', $floor->ID);
                    $floor_store_title = $no = '';

                    if($stores && is_array($stores)){
                        foreach($stores as $store_item){
                            if((int)$store_item['store'] === $store_id){
                                $no = $store_item['no'] ?? '';
                                $search_only = $store_item['search_only'] ?? false;
                                $floor_store_title = trim($store_title . ' ' . ($store_item['store_appendix'] ?? ''));
                                break;
                            }
                        }
                    }

                    $floor_plan = get_field('floor_plan', $floor->ID);
                    if(is_array($floor_plan) && isset($floor_plan['url'])){
                        $floor_plan = $floor_plan['url'];
                    } elseif(is_numeric($floor_plan)){
                        $floor_plan = wp_get_attachment_url((int)$floor_plan);
                    } elseif(is_string($floor_plan)){
                        $floor_plan = trim($floor_plan);
                    } else $floor_plan = '';


                    $title[$GLOBALS["language_default"]] = $floor->post_title;
                    $link[$GLOBALS["language_default"]] = get_permalink($floor->ID);
                    foreach($GLOBALS["languages"] as $language){
                        if($language["name"] != $GLOBALS["language_default"]){
                            $lang_id = pll_get_post($floor->ID, $language["name"]);
                            $title[$language["name"]] = get_the_title($lang_id);
                            $link[$language["name"]] = get_permalink($lang_id);
                        }
                    }

                    $floor = [
                        'id'          => $floor->ID,
                        'title'       => $title,
                        'store_title' => $floor_store_title,
                        'link'        => $link,
                        'no'          => $no,
                        'search_only' => $search_only,
                        'floor_no'    => (int) filter_var($floor->post_title, FILTER_SANITIZE_NUMBER_INT),
                        'map'         => $floor_plan,
                        'position'    => ""
                    ];
                    if(isset($store_map[$key]["map"]) && !empty($store_map[$key]["map"])){
                        $coords = explode(",", $store_map[$key]["map"]);
                        $left = trim($coords[0]);
                        $top = trim($coords[1]);
                        $floor["position"] = "left:".$left.";top:".$top.";";
                    }
                    $data['floors'][] = $floor;
                }            
            }            
        }

        $campaigns = $this->kampanyalar($vars)['data'] ?? [];
        $data['campaigns'] = $campaigns;

        return $data;
    }



    public function store($vars=array()){
        $post = Timber::get_post($vars["store_id"]);
        $post->store_meta = $this->kat_find($vars);
        return $post;
    }


    public function load_store_map_field_params($field) {
        global $post;
        if (!is_admin() || !$post || get_post_type($post) !== 'magazalar') {
            return $field;
        }

        $floors = apply_filters('pae_get_store_floors', $post->ID);
        if (!empty($floors) && is_array($floors)) {
            $count = count($floors);
            $field['min'] = $count;
            $field['max'] = $count;
        }

        return $field;
    }
    public function load_store_map_value($value, $post_id, $field) {
        if (!is_admin() || get_post_type($post_id) !== 'magazalar') {
            return $value;
        }

        $floors = apply_filters('pae_get_store_floors', $post_id);
        if (empty($floors) || !is_array($floors)) {
            return $value;
        }

        $rows = [];
        foreach ($floors as $key => $f) {
            $map_value = '';
            if (isset($value[$key]['field_68a7b28f30702']) && !empty($value[$key]['field_68a7b28f30702'])) {
                $map_value = $value[$key]['field_68a7b28f30702'];
            } elseif (!empty($f["map"])) {
                $map_value = $f["map"];
            }

            $rows[] = [
                'field_68a7b27c30701' => $f['id'],
                'field_68a7b28f30702' => $map_value,
            ];
        }
        return $rows;
    }
    public function load_store_map($field) {
        global $post;
        if ($post && get_post_type($post) === 'magazalar') {
            $field['image_field_label'] = 'otomatik_gorsel';
        }
        return $field;
    }
    public function render_store_map($field) {
        global $post;
        if (!is_admin() || !$post || get_post_type($post) !== 'magazalar') return;

        $floors = apply_filters('pae_get_store_floors', $post->ID);
        if (empty($floors) || !is_array($floors)) return;

        $prefix = $field['prefix'];
        $repeater_index = 0;
        if (preg_match('/row-(\d+)/', $prefix, $matches)) {
            $repeater_index = intval($matches[1]);
        }

        // Floor index kontrolü
        if (!isset($floors[$repeater_index])) {
            echo '<div class="alert alert-warning">Floor not found for index '.$repeater_index.'</div>';
            return true;
        }

        $image_url = $floors[$repeater_index]["map"] ?? '';
        $value     = $field['value'] ?? '';
        $xy_pair   = explode(',', $value);

        $x = $y = 0;
        if (count($xy_pair) > 1) {
            $x = esc_attr(trim($xy_pair[0]));
            $y = esc_attr(trim($xy_pair[1]));
        }

        if (!empty($image_url)) {
            $safe_url = esc_url($image_url);
            $safe_name = esc_attr($field['name']);
            $safe_val  = esc_attr($value);

            echo <<<HTML
                <div data-name="map_{$repeater_index}" data-type="image" style="display:none;">
                    <img data-name="image" src="{$safe_url}" alt="Floor Map {$repeater_index}" />
                </div>
                <div class="image_mapping-image floor-{$repeater_index}">
                    <img src="" data-percent-based="{$field['percent_based']}" data-label="map_{$repeater_index}" alt="Mapping Image"/>
                    <span style="left:{$x};top:{$y};"></span>
                </div>
                <input class="image_mapping-input" type="text" name="{$safe_name}" value="{$safe_val}" readonly />
            HTML;
        } else {
            echo '<div class="alert alert-danger">Floor Map is not found!</div>';
        }

        return true;
    }



    public function get_store_floors($store_id = 0) { 
        $data = [];

        if (!$store_id) return $data;

        global $wpdb;

        $pattern = '^stores_[0-9]+_store$';

        // Kat ID’lerini al
        $sql = $wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','private')
              AND pm.meta_key REGEXP %s
              AND pm.meta_value = %d
        ", 'katlar', $pattern, $store_id);

        $floor_ids = $wpdb->get_col($sql);
        if (!$floor_ids) return $data;

        $args = [
            'post_type'      => 'katlar',
            'post__in'       => $floor_ids,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => -1,
        ];
        if(!pll_is_translated_post_type("katlar")) {
            $args["lang"] = "";
        }

        $floors = get_posts($args);
        if (!$floors) return $data;

        foreach ($floors as $floor) {
            // 🔥 Burada ACF kullanıyoruz → her zaman doğru URL gelir
            $floor_plan = get_field('floor_plan', $floor->ID);
            if (is_array($floor_plan) && isset($floor_plan['url'])) {
                $floor_plan = $floor_plan['url'];
            } elseif (is_numeric($floor_plan)) {
                $floor_plan = wp_get_attachment_url((int)$floor_plan);
            } elseif (is_string($floor_plan)) {
                $floor_plan = trim($floor_plan); // string URL ise direkt kullan
            } else {
                $floor_plan = '';
            }

            $data[] = [
                'id'  => $floor->ID,
                'map' => $floor_plan,
            ];
        }

        return $data;
    }

    public function etkinlikler(array $vars = []): array {
        global $wpdb;
        $response = $this->response();
        $tz = new DateTimeZone($this->timezone ?? 'Europe/Istanbul');

        // sanitize inputs
        $date       = !empty($vars['date']) ? sanitize_text_field($vars['date']) : null;
        $event_date = !empty($vars['event_date']) ? sanitize_text_field($vars['event_date']) : null;
        $timing     = !empty($vars['timing']) ? sanitize_text_field($vars['timing']) : null;
        $count      = !empty($vars['count']) ? absint($vars['count']) : null;

        $query_type = 'day';
        $now = new DateTimeImmutable('now', $tz);
        $weekday = (int) $now->format('N') - 1;

        if ($date) {
            $query_type  = 'month';
            $now         = new DateTimeImmutable($date, $tz);
            $month_start = $now->format('Y-m-01');
            $month_end   = $now->format('Y-m-t');
        } elseif ($timing === 'now') {
            if ($event_date) {
                $query_type = 'day';
                $now = new DateTimeImmutable($event_date, $tz);
                $weekday = (int) $now->format('N') - 1;
            } else {
                $query_type = 'now';
            }
        } elseif ($timing === 'past') {
            $query_type = 'past';
        } elseif ($event_date) {
            $query_type = 'day';
            $now = new DateTimeImmutable($event_date, $tz);
            $weekday = (int) $now->format('N') - 1;
        }

        $nowStr = esc_sql($now->format('Y-m-d'));

        // SQL base
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m1 ON (p.ID = m1.post_id AND m1.meta_key = 'start_date')
            LEFT JOIN {$wpdb->postmeta} m2 ON (p.ID = m2.post_id AND m2.meta_key = 'end_date')
            LEFT JOIN {$wpdb->postmeta} m3 ON (p.ID = m3.post_id AND m3.meta_key = 'period')
            WHERE p.post_type = 'etkinlikler'
            AND p.post_status = 'publish'
        ";

        switch ($query_type) {
            case 'day':
                $sql .= $wpdb->prepare("
                    AND (
                        (m1.meta_value <= %s AND m2.meta_value >= %s)
                        OR
                        (m1.meta_value <= %s AND (m2.meta_value IS NULL OR m2.meta_value = '') 
                            AND m3.meta_value LIKE %s)
                    )
                ", $nowStr, $nowStr, $nowStr, '%s:1:"' . $weekday . '"%');
                break;

            case 'month':
                $sql .= $wpdb->prepare("
                    AND (
                        (m1.meta_value <= %s AND m2.meta_value >= %s)
                        OR
                        (m1.meta_value <= %s AND (m2.meta_value IS NULL OR m2.meta_value = '') 
                            AND m3.meta_value LIKE %s)
                    )
                ", $month_end, $month_start, $month_end, '%s:1:"%');
                break;

            case 'now':
                $sql .= $wpdb->prepare("
                    AND (
                        (m1.meta_value <= %s AND m2.meta_value >= %s)
                        OR
                        (m1.meta_value <= %s AND (m2.meta_value IS NULL OR m2.meta_value = '') 
                            AND m3.meta_value LIKE %s)
                    )
                ", $nowStr, $nowStr, $nowStr, '%s:1:"%');
                break;

            case 'past':
                $sql .= $wpdb->prepare(" AND m2.meta_value < %s ", $nowStr);
                break;
        }

        // order
        $sql .= " ORDER BY m1.meta_value ASC ";

        // limit
        if ($count) {
            $sql .= " LIMIT " . intval($count);
        }

        // execute
        $ids = $wpdb->get_col($sql);

        // Timber ile postları çek
        $events = [];
        if ($ids) {
            $events = Timber::get_posts([
                'post_type' => 'etkinlikler',
                'post__in'  => $ids,
                'orderby'   => 'post__in'
            ]);
        }

        $response['data'] = $events;
        return $response;
    }

    public function normalize_campaigns(array $campaigns): array {
        $newData = [];

        if (empty($campaigns)) {
            return $newData;
        }

        // 🔥 Dictionary cache
        static $dictionaries = [];
        foreach ($GLOBALS["languages"] as $language) {
            $lang = $language["name"];
            if (!isset($dictionaries[$lang])) {
                $path = THEME_STATIC_PATH . "data/dictionary-" . $lang . ".json";
                if (file_exists($path)) {
                    $json = file_get_contents($path);
                    $dictionaries[$lang] = !empty($json) ? json_decode($json, true) : [];
                } else {
                    $dictionaries[$lang] = [];
                }
            }
        }

        // 🔥 Formatter cache
        static $formatters = [];
        foreach ($GLOBALS["languages"] as $language) {
            $lang = $language["name"];
            if (!isset($formatters[$lang])) {
                $formatters[$lang] = new IntlDateFormatter(
                    $lang,
                    IntlDateFormatter::LONG,
                    IntlDateFormatter::NONE,
                    'UTC',
                    IntlDateFormatter::GREGORIAN,
                    'd MMMM yyyy EEEE'
                );
            }
        }

        // 🔥 Store preload (tüm campaign store ID’lerini çekip tek seferde DB’den al)
        $store_ids = array_unique(array_map(fn($c) => $c->store ?? 0, $campaigns));
        $stores = get_posts([
            'post_type'   => 'magazalar',
            'post__in'    => $store_ids,
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        $store_map = [];
        foreach ($stores as $s) {
            $store_map[$s->ID] = $s;
        }

        // 🔥 Store logolarını preload (meta cache)
        update_postmeta_cache($store_ids);

        foreach ($campaigns as $campaign) {
            if (empty($campaign->store) || empty($store_map[$campaign->store])) {
                continue;
            }

            $store = $store_map[$campaign->store] ?? null;

            $store_logo = get_field('logo', $store->ID);;
            $images = get_field("images", $campaign->ID);
            if (empty($images) || !is_array($images)) {
                continue;
            }

            foreach ($images as $key => $img) {
                // 🔥 Attachment metadata tek seferde
                $meta = wp_get_attachment_metadata($img);
                $preview = $large = '';

                if ($meta) {
                    $upload_dir = wp_get_upload_dir();
                    $baseurl = $upload_dir['baseurl'] . '/' . dirname($meta['file']);

                    $preview = $meta['sizes']['thumbnail']['file'] ?? '';
                    if ($preview) $preview = $baseurl . '/' . $meta['sizes']['thumbnail']['file'];

                    $large = $meta['sizes']['large']['file'] ?? $meta['file'];
                    $large = $baseurl . '/' . $large;

                    //$large = str_replace(home_url(), "", $large);
                }


                // 🔥 Caption veya fallback title
                $title = wp_get_attachment_caption($img) 
                         ?: ($campaign->title ?: $store->post_title);

                $fake = new \stdClass();
                $fake->ID          = count($images) > 1 ? $campaign->ID . '_' . $key : $campaign->ID;
                $fake->id          = $fake->ID;
                $fake->title       = $title;
                $fake->content     = $campaign->content ?? '';
                $fake->store       = $store->ID;
                $fake->store_logo  = $store_logo;
                $fake->date        = $campaign->date;
                $fake->preview     = $preview;
                $fake->large       = $large;
                $fake->account     = $store->post_title;
                $fake->name        = $title;
                $fake->start_date  = $campaign->start_date;
                $fake->end_date    = $campaign->end_date;
                $fake->time        = get_post_time('U', false, $campaign->ID);

                // 🔥 Çok dilli text cache’li dictionary + formatter
                $campaign_text = [];
                foreach ($GLOBALS["languages"] as $language) {
                    $lang = $language["name"];
                    $dictionary = $dictionaries[$lang];
                    $formatter  = $formatters[$lang];

                    $start_date = $campaign->start_date ? $formatter->format(new DateTime($campaign->start_date)) : '';
                    $end_date   = $campaign->end_date   ? $formatter->format(new DateTime($campaign->end_date))   : '';

                    if (!empty($start_date) && !empty($end_date)) {
                        $node = 'Kampanya %s ve %s günleri arasında geçerlidir.';
                    }elseif(empty($start_date) && !empty($end_date)){
                        $node = 'Kampanya %s gününe kadar geçerlidir.';
                    }elseif(!empty($start_date) && empty($end_date)){
                        $node = 'Kampanya %s günü geçerlidir.';
                    }

                    translate('Kampanya %s ve %s günleri arasında geçerlidir.');
                    translate('Kampanya %s gününe kadar geçerlidir.');
                    translate('Kampanya %s günü geçerlidir.');

                    if($node){
                        if ($lang !== $GLOBALS["language_default"] && !empty($dictionary[$node])) {
                            $node = $dictionary[$node];
                        }

                        //$campaign_text[$lang] = sprintf($node, $start_date, $end_date);
                        $campaign_text[$lang] = sprintf(
                            $node,
                            !empty($start_date) ? $start_date : $end_date,
                            $end_date // ikinci %s sadece doluysa kullanılacak, boşsa node zaten onu gerektirmiyor
                        );                        
                    }else{
                        $campaign_text[$lang] = "";
                    }

                }

                $fake->text = $campaign_text;
                $fake->default_id = pll_get_post($campaign->ID, $GLOBALS["language_default"]).(count($images) > 1 ? '_' . $key:'');

                $newData[]  = $fake;
            }
        }

        return $newData;
    }
	public function kampanyalar($vars = array()){
        global $wpdb;
        $response = $this->response();
        $now = (new DateTime('now', new DateTimeZone(get_option('timezone_string'))))->format('Ymd');

        $data_type = "default";
        if(isset($vars["data_type"])){
           $data_type = $vars["data_type"];
        }

        $args = [
            "post_type" => "kampanya",
            'posts_per_page' => -1,
            "meta_query" => [
                'relation' => 'and',
                [
                    'relation' => 'OR',
                    ['key'=>'start_date','compare'=>'NOT EXISTS'],
                    ['key'=>'start_date','value'=>'','compare'=>'='],
                    ['key'=>'start_date','value'=>$now,'compare'=>'<=','type'=>'DATE']
                ],
                [
                    'relation' => 'OR',
                    ['key'=>'end_date','compare'=>'NOT EXISTS'],
                    ['key'=>'end_date','value'=>'','compare'=>'='],
                    ['key'=>'end_date','value'=>$now,'compare'=>'>=','type'=>'DATE']
                ]
            ]
        ];

        if($vars["data_type"] == "story"){
            $args["lang"] = $GLOBALS["language_default"];
        }
 
        // Tek ID veya array ile sorgu
        if(isset($vars["store_id"])){
            if(is_array($vars["store_id"]) && !empty($vars["store_id"])){
                $store_ids = [];
                foreach($vars["store_id"] as $store_id){
                    $store_ids[] = pll_get_post($store_id, $GLOBALS["language_default"]);
                }
                $args["meta_query"][] = [
                    'key'     => 'store',
                    'value'   => $store_ids,
                    'compare' => 'IN'
                ];
            } else {
                $store_id = pll_get_post($vars["store_id"], $GLOBALS["language_default"]);
                $args["meta_query"][] = [
                    'key'     => 'store',
                    'value'   => $store_id,
                    'compare' => '='
                ];
            }
        }
        if(isset($vars["store_id__in"]) && is_array($vars["store_id__in"])){
            $store_ids = [];
            foreach($vars["store_id__in"] as $store_id){
                $store_ids[] = pll_get_post($store_id, $GLOBALS["language_default"]);
            }
            $args["meta_query"][] = [
                'key'     => 'store',
                'value'   => $store_ids,
                'compare' => 'IN'
            ];
        }

        $campaigns = Timber::get_posts($args)->to_array();

        if(isset($vars["taxonomy"]) && !isset($vars["store_id"])){
            $store_ids = array();
            $store_ids_filtered = array();
            $campaigns_filtered = array();
            foreach($campaigns as $campaign){
                $campaign_store = pll_get_post( $campaign->store, $GLOBALS["language"] );
                //check store is published on any floors
                $results = $wpdb->get_results( $wpdb->prepare("SELECT count(*) as count, post_id as floor FROM {$wpdb->prefix}postmeta WHERE meta_key like 'stores_%_store' and meta_value = %s", $campaign_store ) );
                if($results[0]->count > 0 && get_post_status($results[0]->floor) =="publish"){
                    $store_ids[] = $campaign_store;
                }
            }

            if($store_ids){
                $args = array(
                       "post_type" => "magazalar",
                       "post__in" => $store_ids,
                       'numberposts' => -1,
                       "fields" => "ids"
                );
                $args = wp_query_addition($args, $vars);
                $store_ids_filtered = get_posts($args);

                foreach($campaigns as $campaign){
                    $campaign_store = pll_get_post( $campaign->store, $GLOBALS["language"] );
                    if(in_array($campaign_store, $store_ids_filtered)){
                        $campaigns_filtered[] = $campaign;
                    }
                }
                $campaigns = $campaigns_filtered;   
       
            }
        }else{
            // Yayınlanmış store kontrolü
            $keys = [];
            foreach($campaigns as $key => $campaign){
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT count(*) as count, post_id as floor 
                     FROM {$wpdb->prefix}postmeta 
                     WHERE meta_key like 'stores_%_store' 
                       AND meta_value = %s", 
                    $campaign->store
                ));
                if($results[0]->count == 0 || get_post_status($results[0]->floor) != "publish"){
                    $keys[] = $key;
                }
            }
            foreach($keys as $key) unset($campaigns[$key]);
        }

        $campaigns = $this->normalize_campaigns($campaigns);

        if($data_type == "story"){
            $data = array();
            foreach($campaigns as $key => $campaign){
                $length = 5;
                $link = "#store-".pll_get_post($campaign->store, $GLOBALS["language_default"]);//get_permalink($campaign->store)."?from=campaigns";//#campaigns";
                $link_text = "Mağaza Bilgisi";//translate("Mağaza Bilgisi");
                $item = array(
                    "id" => $campaign->id,
                    "photo" => $campaign->store_logo,//str_replace(home_url(), "", $campaign->store_logo),
                    "account" =>  $campaign->account,
                    "name" => $campaign->title,
                    "link" => $link,
                    "lastUpdated" => $campaign->time,
                    "seen" => false,
                    "start_date" => $campaign->start_date,
                    "end_date" => $campaign->end_date,
                    "items" => array()
                );
                $items = array(
                    "id" => $campaign->slug.'_1',//$campaign->ID,//."1",
                    "type" => "photo",
                    "length" => $length,
                    "src" => $campaign->large,//str_replace(home_url(), "", $campaign->large),
                    "preview" => $campaign->preview,//str_replace(home_url(), "", $campaign->preview),
                    "link" => $link,
                    "linkText" => $link_text,
                    "linkAttr" => $link,//parse_external_url($link),
                    "time" => $campaign->time,
                    "seen" => false,
                    "brand" => $campaign->account,
                );
                $item["items"][] = $items;
                $data[] = $item;
            }
        }else{
            $data = $campaigns;
        }
        
        $response["data"] = $data;
        return $response;
    }
    public function create_stories_js(){
        $terms = get_terms([
            'taxonomy'   => 'magaza-tipi',
            'hide_empty' => false,
            'fields'     => 'slugs',
            'lang'       => $GLOBALS["language_default"]
        ]);
        if (empty($terms) || is_wp_error($terms)) return;

        $dir = THEME_STATIC_PATH . 'data/campaigns/';
        if (!file_exists($dir)) mkdir($dir, 0755, true);

        $all_data = []; // tüm verileri tek array/object içinde birleştireceğiz

        foreach ($terms as $term_slug) {
            $param = [
                'data_type' => 'story',
                'taxonomy' => [
                    'magaza-tipi' => [$term_slug]
                ]
            ];
            $res = $this->kampanyalar($param);
            if ($res["error"]) return;

            $data = $res["data"];
            $all_data = array_merge($all_data, $data); // tüm dataları birleştir

            // Her term için JS dosyası
            $file_path = $dir . $term_slug . '.js';
            $js_content = 'window.story_data = ' . wp_json_encode($data) . ';';
            file_put_contents($file_path, $js_content);
        }

        // all.js oluştur (sadece birleşik data)
        $all_file_path = $dir . 'all.js';
        $all_js_content = 'window.story_data = ' . wp_json_encode($all_data) . ';';
        file_put_contents($all_file_path, $all_js_content);
    }

    public function create_stores_js($data = []){
        if(!$data) return;
        $dir = THEME_STATIC_PATH . 'data/stores/';
        if (!file_exists($dir)) mkdir($dir, 0755, true);
        $file_path = $dir . "store-".$data["id"] . '.json';
        $js_content = wp_json_encode($data);
        file_put_contents($file_path, $js_content);
    }

    public function campaign_published($post_id, $post, $update ){
        //if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        //    return;
        //}
        if($post->post_type != "kampanya" || $post->post_status != "publish" || !is_admin()) return;

        $this->create_stories_js();

        $store_id = get_field("store", $post_id);
        if($store_id){
            $store = get_post($store_id);
            $this->store_published($store_id, $store, true );
        }

        $images = get_field("images", $post_id);
        if($images){
            set_post_thumbnail( $post_id, $images[0] );
        }       
	}
    public function store_published($post_id, $post, $update ){
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if($post->post_type != "magazalar" || $post->post_status != "publish" || !is_admin()) return;

        $store = $this->store(["store_id" => $post_id]);

        $hizmetler = [];
        $terms = $store->terms(["taxonomy" => "hizmetler"]);
        foreach ($GLOBALS["languages"] as $language) {
            $lang = $language['name'];
            $translated_names = [];
            foreach ($terms as $term) {
                $translated_id = pll_get_term($term->term_id, $lang);
                if ($translated_id) {
                    $translated_term = get_term($translated_id, "hizmetler");
                    if ($translated_term && !is_wp_error($translated_term)) {
                        $translated_names[] = $translated_term->name;
                    }
                }
            }
            $hizmetler[$lang] = $translated_names;
        }

        $gallery = [];
        foreach($store->meta("gallery") as $image){
            $gallery[] = $image["url"];
        }
        $logo = $store->thumbnail();
        $store_data = [
            "id" => $store->ID,
            "title" => $store->title,
            "search_only" => false,
            "logo" => $logo?$logo->src("thumbnail"):'',
            "hizmetler" => $hizmetler,
            "campaigns" => $store->store_meta["campaigns"],//$kampanyalar,
            "gallery"  => $gallery,
            "floors" => $store->store_meta["floors"],
            "no" => $store->no,
            "phone" => $store->phone,
            "phone_link" => phone_link($store->phone),
            "url" => $store->url,
            "url_link" => url_link($store->url, '_blank', '', true),
            "email" => $store->email,
            "email_link" => email_link($store->email),
            "accounts" => $store->accounts
        ];
        $this->create_stores_js($store_data);

        //$this->translate_post($post_id);
    }
    public function etkinlikler_published($post_id, $post, $update ){
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if($post->post_type != "etkinlikler" || $post->post_status != "publish" || !is_admin()) return;

        $start_date  = get_post_meta($post_id, 'start_date', true);
        $end_date    = get_post_meta($post_id, 'end_date', true);
        $period      = get_post_meta($post_id, 'period', true);
        $now         = current_time('Y-m-d');
        $is_past = 0;
        if (!empty($end_date) && $end_date < $now) {
            $is_past = 1;
        }
        elseif (!empty($start_date) && empty($end_date) && empty($period) && $start_date < $now) {
            $is_past = 1;
        }
        update_post_meta($post_id, '_is_past', $is_past);

        $this->translate_post($post_id);

        if ( function_exists('pll_get_post') ) {
            $default_lang = pll_default_language();
            $lang = pll_get_post_language($post_id);
            if ($lang == $default_lang){
                $translations = pll_get_post_translations($post_id);
                if ($translations && is_array($translations)) {
                    foreach ($translations as $lang => $translated_post_id) {
                        if ($translated_post_id != $post_id) {
                            update_post_meta($translated_post_id, '_is_past', $is_past);
                        }
                    }
                }
            }
        }
        
    }
	public function acf_stores_load_value( $value, $post_id, $field ) {
	    $order = array();
	    if( empty($value) ) {
	        return $value;
	    }
	    foreach( $value as $i => $row ) {
	        if(isset($row['field_5f67ddf419c4f'])){
	            $order[ $i ] = $row['field_5f67ddf419c4f'];
	        }
	    }
	    array_multisort( $order, SORT_ASC, $value );  
	    return $value;
	}

    public function translate_post($post_id) {
        if (!function_exists('pll_get_post_language')) return;

        $post = get_post($post_id);
        if (!$post || $post->post_type === 'revision') return;

        // Varsayılan dil ve mevcut postun dili
        $default_lang = pll_default_language();
        $lang = pll_get_post_language($post_id);

        // sadece default dilde çalışsın
        if ($lang !== $default_lang) return;

        // Tüm aktif dilleri al
        $all_langs = array_column($GLOBALS["languages"], "name");

        foreach ($all_langs as $target_lang) {
            if ($target_lang === $default_lang) continue; // default dili atla

            // Çeviri var mı?
            $translated_post_id = pll_get_post($post_id, $target_lang);

            if (!$translated_post_id) {
                // === 1. Yeni post oluştur ===
                $new_post_id = wp_insert_post([
                    'post_title'   => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_excerpt' => $post->post_excerpt,
                    'post_status'  => $post->post_status,
                    'post_type'    => $post->post_type,
                    'post_author'  => $post->post_author,
                ]);

                if (!$new_post_id) continue;

                // === 2. Dilini bağla ===
                pll_set_post_language($new_post_id, $target_lang);

                // Çeviri ilişkilerini güncelle
                $translations = pll_get_post_translations($post_id);
                $translations[$target_lang] = $new_post_id;
                pll_save_post_translations($translations);

                // === 3. Meta (ACF dahil) ===
                $meta = get_post_meta($post_id);
                foreach ($meta as $key => $values) {
                    if (in_array($key, ['_edit_lock','_edit_last'])) continue;
                    foreach ($values as $value) {
                        add_post_meta($new_post_id, $key, maybe_unserialize($value));
                    }
                }

                // === 4. Taxonomy / Terms ===
                $taxonomies = get_object_taxonomies($post->post_type);
                foreach ($taxonomies as $taxonomy) {
                    $terms = wp_get_object_terms($post_id, $taxonomy);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $translated_terms = [];
                        foreach ($terms as $term) {
                            // Eğer term çevirisi varsa al, yoksa yeni oluştur
                            $translated_term_id = function_exists('pll_get_term')
                                ? pll_get_term($term->term_id, $target_lang)
                                : 0;

                            if ($translated_term_id) {
                                $translated_terms[] = intval($translated_term_id);
                            } else {
                                // Term çevirisi yok -> yeni oluştur
                                $new_term = wp_insert_term($term->name, $taxonomy);
                                if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                                    $new_term_id = $new_term['term_id'];
                                    pll_set_term_language($new_term_id, $target_lang);

                                    // term translations güncelle
                                    $term_translations = pll_get_term_translations($term->term_id);
                                    $term_translations[$target_lang] = $new_term_id;
                                    pll_save_term_translations($term_translations);

                                    $translated_terms[] = $new_term_id;
                                }
                            }
                        }
                        if (!empty($translated_terms)) {
                            wp_set_object_terms($new_post_id, $translated_terms, $taxonomy);
                        }
                    }
                }

                // === 5. Featured Image ===
                $thumbnail_id = get_post_thumbnail_id($post_id);
                if ($thumbnail_id) {
                    set_post_thumbnail($new_post_id, $thumbnail_id);
                }
            }
        }
    }

    /*public function update_custom_settings($post_id){
        return;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        // Sadece sayfa için çalıştır
        if (get_post_type($post_id) !== 'page') return;

        // Default dil mi?
        if (function_exists('pll_get_post_language') && pll_get_post_language($post_id) === pll_default_language()) {
            // Bu post'un tüm çevirilerini al
            $translations = pll_get_post_translations($post_id);

            if (!empty($translations)) {
                foreach ($translations as $lang => $tr_post_id) {
                    if ($tr_post_id == $post_id) continue;

                    // Burada hangi field'ları senkronlayacağını seç
                    $fields = ['custom_settings', 'page_settings'];
                    foreach ($fields as $field) {
                        $value = get_field($field, $post_id, false); // raw değer al
                        if (!empty($value)) {
                            update_field($field, $value, $tr_post_id);
                        }
                    }
                }
            }
        }        
    }*/

    public function translate_offcanvas_menu_item($post_id, $post, $update) {
        // 1. Varsayılan dil kontrolü
        if (function_exists('pll_get_post_type') && pll_get_post_language($post_id) !== pll_default_language()) {
            return;
        }

        // 2. Field değerlerini al
        $menu_item_id = get_field('page_settings_offcanvas_menu_item', $post_id); // seçili menu item
        $menu_id      = get_field('page_settings_offcanvas_menu', $post_id); // hangi menü

        error_log("menu_id: ".$menu_id." menu_item_id: ".$menu_item_id);

        if (!$menu_item_id || $menu_item_id <= 0) return;

        // 3. Menü items'ı çekelim (ACF veya wp_get_nav_menu_items)
        $menu_items = wp_get_nav_menu_items($menu_id);
        $menu_item_type = null;
        if ($menu_items && is_array($menu_items)) {
            foreach ($menu_items as $item) {
                if ($item->ID == $menu_item_id) {
                    $menu_item_type = $item->object; // post_type veya 'category'
                    break;
                }
            }
        }

        if (!$menu_item_type) return;

        // 4. Diğer dillerdeki çevirileri bul
        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($post_id);
            foreach ($translations as $lang => $translated_post_id) {
                if ($translated_post_id == $post_id) continue; // kendi dilini atla

                $new_value = $menu_item_id;

                // 5. Eğer post ise karşılığını al
                if (in_array($menu_item_type, get_post_types(['public' => true]))) {
                    $new_value = pll_get_post($menu_item_id, $lang) ?: $menu_item_id;
                }
                // 6. Eğer term ise karşılığını al
                elseif (taxonomy_exists($menu_item_type)) {
                    $new_value = pll_get_term($menu_item_id, $lang) ?: $menu_item_id;
                }

                error_log(" --------------------------------- ".$new_value.", ".$translated_post_id);

                // 7. Field'ı güncelle
                update_field('page_settings_offcanvas_menu_item', $new_value, $translated_post_id);
            }
        }
    }


}
new Project();