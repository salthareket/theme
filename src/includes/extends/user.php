<?php

/**
 * User — Timber\User extend'i.
 *
 * @version 1.2.0
 * @changelog
 *   1.2.0 - 2026-05-08
 *     - Add: reaction_count() — user'in reaction sayisi (kac kisi follow etti vs)
 *     - Add: has_reaction() — mevcut kullanici bu user'a reaction yapti mi
 *     - Add: reaction_button() — reaction button HTML'i render et
 *     - Add: reaction_ids() — kullanicinin reaction yaptigi object ID listesi
 *   1.0.0 — Initial release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Twig'de:
 * {{ user.reaction_count('follow') }}
 * {% if user.has_reaction('follow') %}...{% endif %}
 * {{ user.reaction_button('follow', {'style': 'pill'})|raw }}
 * {% set fav_ids = user.reaction_ids('favorite', 'post') %}
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   {{ user.reaction_count('follow') }} takipci
 *
 * @example
 *   {% if user.has_reaction('follow') %}Takip Ediliyor{% endif %}
 *
 * @example
 *   {{ user.reaction_button('follow', {'style': 'pill'})|raw }}
 *
 * @example
 *   {% set fav_ids = user.reaction_ids('favorite', 'post') %}
 *
 * @example
 *   {% set following = user.reaction_ids('follow', 'user') %}
 */
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

    public function __construct($user = null) {
        parent::__construct($user);
    }

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
        // MembershipManager'a delegate — geriye dönük uyumluluk korunuyor
        if ( class_exists( '\SaltHareket\Membership\MembershipManager' ) ) {
            return \SaltHareket\Membership\MembershipManager::getInstance()->isUserActive( $this->ID );
        }
        if (ENABLE_MEMBERSHIP_ACTIVATION) {
            $status = (int) get_user_meta($this->ID, 'user_status', true);
        } else {
            $status = 1;
        }
        return is_user_logged_in() && ($status || $this->get_role() === 'administrator');
    }

    /**
     * Aktivasyon durumunu döndür.
     * Twig: {{ user.activation_status }} → 'pending'|'activated'|'approved'|'rejected'
     */
    public function get_activation_status(): string {
        if ( class_exists( '\SaltHareket\Membership\MembershipManager' ) ) {
            return \SaltHareket\Membership\MembershipManager::getInstance()->getActivationStatus( $this->ID );
        }
        return get_user_meta( $this->ID, 'user_status', true ) ? 'approved' : 'pending';
    }

    /**
     * Profil tamamlama skoru.
     * Twig: {{ user.profile_completion.score }}%
     *       {{ user.profile_completion.missing|join(', ') }}
     */
    public function get_profile_completion(): array {
        if ( class_exists( '\SaltHareket\Membership\MembershipManager' ) ) {
            return \SaltHareket\Membership\MembershipManager::getInstance()->getProfileCompletion( $this->ID );
        }
        return [ 'score' => 0, 'missing' => [], 'completed' => [] ];
    }

    /**
     * Bağlı sosyal login provider'ları.
     * Twig: {% if user.social_providers %}...{% endif %}
     */
    public function get_social_providers(): array {
        if ( class_exists( '\SaltHareket\Membership\MembershipManager' ) ) {
            return \SaltHareket\Membership\MembershipManager::getInstance()->getSocialProviders( $this->ID );
        }
        return $this->get_social_login_providers();
    }

    /**
     * Hesap silme talebi var mı?
     * Twig: {% if user.deletion_pending %}...{% endif %}
     */
    public function get_deletion_pending(): bool {
        return (bool) get_user_meta( $this->ID, 'deletion_requested_at', true );
    }

    // ── LOCATION ──────────────────────────────────────────────────────────────

    /**
     * Kullanıcının ülke adı.
     * Twig: {{ user.country_name }}
     */
    public function get_country_name(): string {
        if ( empty( $this->billing_country ) ) return '';
        return function_exists( 'get_country_name' )
            ? get_country_name( 'iso2', $this->billing_country )
            : $this->billing_country;
    }

    /**
     * Kullanıcının şehir adı.
     * Twig: {{ user.city_name }}
     */
    public function get_city_name(): string {
        if ( empty( $this->city ) ) return '';
        return function_exists( 'get_city_name' )
            ? get_city_name( 'id', $this->city )
            : '';
    }

    /**
     * Kullanıcının timezone'u.
     * Twig: {{ user.timezone }}
     */
    public function get_timezone(): string {
        $tz = get_user_meta( $this->ID, '_wp_utz_opts', true );
        if ( is_array( $tz ) && ! empty( $tz['timezone'] ) ) {
            return $tz['timezone'];
        }
        return get_option( 'timezone_string', 'UTC' );
    }

    /**
     * Kullanıcının GMT offset'i.
     * Twig: {{ user.gmt }}
     */
    public function get_gmt(): string {
        return get_user_meta( $this->ID, 'gmt', true ) ?: '';
    }

    /**
     * Kullanıcının region term'leri.
     * Twig: {% for region in user.regions %}{{ region.name }}{% endfor %}
     */
    public function get_regions(): array {
        if ( ! taxonomy_exists( 'region' ) ) return [];
        $terms = get_terms( [
            'taxonomy'   => 'region',
            'hide_empty' => false,
            'meta_query' => [ [
                'key'     => 'country',
                'value'   => serialize( strtoupper( $this->billing_country ?? '' ) ),
                'compare' => 'LIKE',
            ] ],
        ] );
        return ( $terms && ! is_wp_error( $terms ) ) ? $terms : [];
    }

    /**
     * Tarih/saati kullanıcının timezone'una göre formatla.
     * Twig: {{ user.get_local_date(post.date, 'UTC', user.timezone) }}
     */
    public function get_local_date( string $date = '', string $from_tz = 'UTC', string $to_tz = '', string $format = 'Y-m-d H:i:s' ): string {
        if ( empty( $date ) ) return '';
        if ( empty( $to_tz ) ) $to_tz = $this->get_timezone();

        try {
            $dt = new \DateTime( $date, new \DateTimeZone( $from_tz ) );
            $dt->setTimezone( new \DateTimeZone( $to_tz ) );
            return $dt->format( $format );
        } catch ( \Exception $e ) {
            return $date;
        }
    }

    // ── END LOCATION ──────────────────────────────────────────────────────────

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
           $location[] = function_exists( 'get_country_name' )?get_country_name("code", $this->billing_country):"";
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
           $location[] = function_exists( 'get_country_name' )?get_country_name("code", $this->billing_country):"";
        }
        $location = implode(", ", $location);
        return $location;//mb_convert_encoding($location, "UTF-8", "auto"); 
    }
    public function get_location_data(){
        $lm = class_exists( '\SaltHareket\Localization\LocationManager' )
            ? \SaltHareket\Localization\LocationManager::getInstance()
            : null;

        if ( $lm ) {
            $result = $lm->states( [ 'id' => $this->city ] );
            return $result[0] ?? null;
        }

        // Fallback — eski Localization class
        if ( class_exists( 'Localization' ) ) {
            $localization = new Localization();
            return $localization->states( [ 'id' => $this->city ] )[0] ?? null;
        }

        return null;
    }
    public function get_map_data($popup=false){
        $location_data = $this->get_location_data();
        $data = array(
            "title"        => $this->get_title(),
            "avatar"       => $this->get_avatar_url(60),
            "country"      => function_exists( 'get_country_name' )?get_country_name("code", $this->billing_country):"",
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
        if ( ! class_exists( 'Reviews' ) ) return (object) [ 'total' => 0, 'point' => 0 ];
        $data = Reviews::rating( $this->ID, 'user' );
        return (object) [ 'total' => $data['total'], 'point' => $data['average'] ];
    }

    public function get_reviews_count(): int {
        if ( ! class_exists( 'Reviews' ) ) return 0;
        return Reviews::rating( $this->ID, 'user' )['total'];
    }

    public function set_review_approve( int $id = 0 ): array {
        if ( ! class_exists( 'Reviews' ) ) return [];
        $reviews = new Reviews();
        return $reviews->approve( $id );
    }

    public function get_application_review( int $post_id = 0 ) {
        if ( ! class_exists( 'Reviews' ) ) return [];
        $reviews = new Reviews();
        $result  = $reviews->get_for_user( $this->ID, [ 'per_page' => 1 ] );
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




    // ── REACTIONS ────────────────────────────────────────────────────────────

    /**
     * Bu kullanicinin belirli bir reaction sayisi (kac kisi follow etti vs).
     * Twig: {{ user.reaction_count('follow') }}
     */
    public function reaction_count( string $type = 'follow' ): int {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return 0;
        return \SaltHareket\Reactions\Reactions::count( $type, $this->ID, 'user' );
    }

    /**
     * Mevcut kullanici bu user'a reaction yapti mi?
     * Twig: {% if user.has_reaction('follow') %}
     */
    public function has_reaction( string $type = 'follow' ): bool {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return false;
        return \SaltHareket\Reactions\Reactions::has( $type, $this->ID, 'user' );
    }

    /**
     * Reaction button HTML'i render et.
     * Twig: {{ user.reaction_button('follow', {'style': 'pill'}) }}
     */
    public function reaction_button( string $type = 'follow', array $options = [] ): string {
        if ( ! class_exists( \SaltHareket\Reactions\Admin\ReactionsAjax::class ) ) return '';
        return \SaltHareket\Reactions\Admin\ReactionsAjax::renderButton( $this->ID, 'user', $type, $options );
    }

    /**
     * Bu kullanicinin yaptigi reaction'larin object ID listesi.
     * Twig: {% set fav_ids = user.reaction_ids('favorite', 'post') %}
     */
    public function reaction_ids( string $type = 'favorite', string $object_type = 'post' ): array {
        if ( ! class_exists( \SaltHareket\Reactions\Reactions::class ) ) return [];
        return \SaltHareket\Reactions\Reactions::getByUser( $this->ID, $type, $object_type );
    }

    // ── END REACTIONS ─────────────────────────────────────────────────────────

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