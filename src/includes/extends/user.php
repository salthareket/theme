<?php
class User extends Timber\User {
    public $logged;
    public $role;
    public $role_name;
    public $title;
    public $avatar;
    public $avatar_url;
    public $online;
    public $last_login;
    public $last_logout;
    public $last_seen;

    public function logged($logged=0){
        $this->logged = $logged;
    }

    public function is_online(){
        $this->online = Data::get("salt")->user_is_online($this->ID);
        return $this->online;
    }
    public function get_last_login($type = 'text', $format = 'Y-m-d H:i:s') {
        $last_login = get_user_meta($this->ID, 'last_login', true);
        if (empty($last_login)) return '';

        if ($type === 'text') return human_time_diff($last_login);
        if ($type === 'date') {
            $user = Data::get('user');
            if ($this->get_timezone() && $user && $user->get_timezone()) {
                return $this->get_local_date(date('Y-m-d H:i:s', $last_login), $this->get_timezone(), $user->get_timezone(), $format);
            }
            return date($format, $last_login);
        }
        return $last_login;
    }

    public function get_last_logout($type = 'text', $format = 'Y-m-d H:i:s') {
        $last_logout = get_user_meta($this->ID, 'last_logout', true);
        if (empty($last_logout)) return '';

        if ($type === 'text') return human_time_diff($last_logout);
        if ($type === 'date') {
            $user = Data::get('user');
            if ($this->get_timezone() && $user && $user->get_timezone()) {
                return $this->get_local_date(date('Y-m-d H:i:s', $last_logout), $this->get_timezone(), $user->get_timezone(), $format);
            }
            return date($format, $last_logout);
        }
        return $last_logout;
    }

    public function get_last_seen($type = 'text', $format = '') {
        return $this->get_last_logout($type, $format) ?: $this->get_last_login($type, $format);
    }
    public function get_status(){
        if (ENABLE_MEMBERSHIP_ACTIVATION) {
            $status = (int) get_user_meta($this->ID, 'user_status', true);
        } else {
            $status = 1;
        }
        return is_user_logged_in() && ($status || $this->get_role() === 'administrator');
    }
    public function is_profile_completed(){
        if ($this->get_role() === 'administrator') return true;
        return boolval(get_user_meta($this->ID, 'profile_completed', true));
    }

    public function get_social_login_providers(){
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT type FROM {$wpdb->prefix}social_users WHERE ID = %d",
            $this->ID
        ));
    }

    public function generate_url_slug(){
        $nickname = $this->nickname;
        if(strlen($nickname) < 8){
            $nickname .= "-" . unique_code(8 - strlen($nickname));
        }
        if(strlen($nickname) > 25){
            $nickname = substr($nickname, 0, 25);
        }
        return $nickname;
    }
    
    public function get_role(){
        if ($this->role) return $this->role;
        $this->role = isset($this->roles) ? array_keys($this->roles)[0] ?? '' : '';
        return $this->role;
    }

    public function get_role_name(){
        if ($this->role_name) return $this->role_name;
        $this->role_name = $this->roles[$this->get_role()] ?? '';
        return $this->role_name;
    }

    public function get_title() {
        if ($this->title) return $this->title;

        $first = $this->meta('first_name');
        $last  = $this->meta('last_name');

        if (!empty($first) || !empty($last)) {
            $this->title = trim($first . ' ' . $last);
        } elseif (!empty($this->display_name)) {
            $this->title = $this->display_name;
        } else {
            $this->title = $this->user_email;
        }
        return $this->title;
    }

    public function get_avatar($width = 120, $class = '') {
        if ($this->avatar) return $this->avatar;

        $profile_image = get_field('profile_image', 'user_' . $this->ID);
        if ($profile_image) {
            $url = wp_get_attachment_image_src($profile_image, 'thumbnail')[0] ?? '';
            $this->avatar = '<img data-src="' . esc_url($url) . '" class="avatar img-fluid lazy ' . esc_attr($class) . '" alt="' . esc_attr($this->display_name) . '"/>';
        } else {
            $this->avatar = get_avatar($this->id, $width, 'mystery', $this->display_name, ['class' => $class]);
        }
        return $this->avatar;
    }

    public function get_avatar_url($width = 120) {
        if ($this->avatar_url) return $this->avatar_url;

        $profile_image = get_field('profile_image', 'user_' . $this->ID);
        $this->avatar_url = $profile_image
            ? (wp_get_attachment_image_src($profile_image, 'thumbnail')[0] ?? '')
            : get_avatar_url($this->id, ['size' => $width, 'default' => 'mystery']);
        return $this->avatar_url;
    }
    public function get_social_media(){
        $social_media = [];
        $providers = $GLOBALS['wp_social_media'] ?? [];
        foreach ($providers as $item) {
            $data = get_field($item['slug'], 'user_' . $this->id);
            if (!empty($data)) {
                $social_media[$item['slug']] = $data;
            }
        }
        return $social_media;
    }
    public function get_terms($taxonomy = "") {
        return get_terms_for_user($this->id, $taxonomy);
    }


    public function get_languages($native = false) {
        $return = array();
        $languages = get_field("languages", "user_".$this->id);
        if($languages){
            $all_languages = get_all_languages($native);
            foreach($all_languages as $lang){
                if(in_array($lang["lang"], $languages)){
                   $return[$lang["lang"]] = $lang["name"];
                }
            }
            return $return;            
        }
    }
    public function get_languages_level($native = false) {
        $return = array();
        $languages = $this->get_languages($native);
        if($languages){
            $languages_levels = $this->language;
            foreach($languages_levels as $lang){
                $level_term = get_term_by("id", $lang["level"], "proficiency-level");
                $return[] = array(
                    "language" => $lang["language"],
                    "name"     => $languages[$lang["language"]],
                    "title" => $level_term->name,
                    "level" => get_field("level", "proficiency-level_".$lang["level"])
                );
            }
            $level = array_column($return, 'level');
            array_multisort($level, SORT_DESC, $return); 
        }
        return $return;
    }



    public function get_address() {
        $location = array();
        if($this->city){
           $location[] = get_city_name("id", $this->city);
        }
        if($this->billing_country){
           $location[] = get_country_name("code", $this->billing_country);
        }
        $location = implode(", ", $location);

        $address = "";
        if($this->billing_address_1){
           $address = $this->billing_address_1;    
        }
        if($location){
            if(!empty($address)){
               $location = $address ."<br>".$location;
            }
        }
        return $location;
    }
    public function get_location() {
        $location = array();
        if(isset($this->city)){
           if(!empty($this->city)){
                $location[] = get_city_name("id", $this->city);
           }
        }
        if(isset($this->billing_country)){
           $location[] = get_country_name("code", $this->billing_country);
        }
        $location = implode(", ", $location);
        return $location;//mb_convert_encoding($location, "UTF-8", "auto"); 
    }
    public function get_location_data(){
        $vars = array(
            "id" => $this->city
        );
        $localization = new Localization();
        return $localization->states($vars)[0];
    }
    public function get_map_data($popup=false){
        $location_data = $this->get_location_data();
        $data = array(
            "title"        => $this->get_title(),
            "avatar"       => $this->get_avatar_url(60),
            "country"      => get_country_name("code", $this->billing_country),
            "country_code" => $this->billing_country,
            "city"         => get_city_name("id", $this->city),
            "city_code"    => $this->city,
            "lat"          => $location_data["latitude"],
            "lng"          => $location_data["longitude"],
        );
        if($popup){
           $data["popup"] = esc_html($this->get_map_popup());
        }
        return $data;
    }
    public function get_map_popup(){
        $user = Data::get("user");
        $map_data = $this->get_map_data();
        return  "<div class='row gx-3 gy-2'>" .
                    "<div class='col-auto'>" .
                         "<img src='" . esc_url($map_data["avatar"] ?? '') . "' class='img-fluid rounded' style='max-width:50px;'/>" .
                    "</div>" .
                    "<div class='col'>" .
                        "<ul class='list-unstyled m-0'>" .
                            "<li class='fw-bold'>" . esc_html($map_data["title"] ?? '') . "</li>" .
                            "<li class='text-muted' style='font-size:12px;'>" . esc_html($this->get_location()) . "</li>" .
                        "</ul>" .
                    "</div>" .
                    "<div class='col-12 text-primary' style='font-size:12px;'>" .
                        ($user ? $this->get_local_date("", "", $user->get_timezone()) . " GMT" . $this->get_gmt() : '') .
                    "</div>" .
                "</div>";
    }
    public function get_phone(){
        return $this->billing_phone_code.$this->billing_phone;
    }



    // ── REVIEWS (Reviews class'ına delegate) ──

    /**
     * Kullanıcının ortalama rating'i.
     * @return array{total: int, average: float}  veya eski uyumluluk için object
     */
    public function get_rating() {
        $data = Reviews::rating( $this->ID, 'user' );
        // Eski kod $rating->point ve $rating->total kullanıyordu — uyumluluk
        return (object) [ 'total' => $data['total'], 'point' => $data['average'] ];
    }

    public function get_reviews_count(): int {
        return Reviews::rating( $this->ID, 'user' )['total'];
    }

    public function set_review_approve( int $id = 0 ): array {
        $reviews = new Reviews();
        return $reviews->approve( $id );
    }

    public function get_application_review( int $post_id = 0 ) {
        $reviews = new Reviews();
        $result  = $reviews->get_for_user( $this->ID, [ 'per_page' => 1 ] );
        // post_id filtresi varsa post bazlı çek
        if ( $post_id > 0 ) {
            $result = $reviews->get_for_post( $post_id, [ 'per_page' => 1 ] );
        }
        return ! empty( $result['reviews'] ) ? $result['reviews'][0] : [];
    }



    public function is_following($id = 0){
        $salt = Salt::get_instance();
        return $salt->is_following($id ?: $this->ID, 'user');
    }

    public function is_follows($id = 0){
        $salt = Salt::get_instance();
        return $salt->is_follows($id ?: $this->ID, 'user');
    }

    public function get_followers($id = 0){
        $salt = Salt::get_instance();
        return $salt->get_followers($id ?: $this->ID, 'user');
    }

    public function get_followers_count($id = 0){
        $salt = Salt::get_instance();
        return $salt->get_followers_count($id ?: $this->ID, 'user');
    }




    public function cart(){
        if(ENABLE_ECOMMERCE){
            if(function_exists("woo_get_cart_object")){
                return woo_get_cart_object();
            }
        }
    }


    public function get_post_count($post_type = "post", $public_only = true){
        $post_count = count_user_posts($this->ID, $post_type, $public_only);
        return intval($post_count);
    }


}