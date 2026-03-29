<?php

/**
 * Notifications System
 * Event-driven notification system with email, in-app alert, and SMS carriers.
 * Events are configured via ACF Options Page and stored as JSON.
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Trigger a notification event
 * $notif = new Notifications();
 * $notif->on('customer/new_order', [
 *     'user' => $user_object,
 *     'post' => $order_post,
 * ]);
 *
 * // Get user's notifications (paginated)
 * $results = $notif->get_notifications(['page' => 1, 'posts_per_page' => 10]);
 *
 * // Get unseen count
 * $count = $notif->get_unseen_notifications_count();
 *
 * // Get unseen alerts (marks them as seen)
 * $alerts = $notif->get_unseen_notifications();
 *
 * // Delete notifications
 * Notifications::delete_post_notifications($post_id);
 * Notifications::delete_user_notifications($user_id);
 *
 * ─── SUPPORTED HOOK EVENTS (for ACF event config) ────────
 *
 * WordPress Post:
 *   save_post, publish_post, delete_post, trashed_post, transition_post_status
 *
 * WordPress User:
 *   user_register, profile_update, delete_user, wp_login
 *
 * WordPress Taxonomy:
 *   create_term, edit_term, delete_term
 *
 * WooCommerce:
 *   woocommerce_new_order, woocommerce_order_status_changed,
 *   woocommerce_payment_complete, woocommerce_add_to_cart,
 *   woocommerce_remove_cart_item
 *
 * ─── PLACEHOLDERS (for sender/recipient in ACF config) ───
 *
 *   {{administrator}} → Site admin
 *   {{me}}            → Current logged-in user
 *   {{user}}          → User from event data
 *   {{users}}         → Multiple recipients (BCC for email)
 *   {{author}}        → Post author
 *
 * ──────────────────────────────────────────────────────────
 */

//https://github.com/MyIntervals/emogrifier
use Pelago\Emogrifier\CssInliner;
//use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
//use Pelago\Emogrifier\HtmlProcessor\HtmlNormalizer;

Class Notifications{

	public $user;
	public $events;
	public $debug;
	public $debug_output;
    public $html_path;
    public $css_path;
    public $administrator;
    private $theme_dir;

    function __construct($user=array(), $debug=0) {
    	if(isset($user) && !empty($user)){
    		$this->user = $user;
    	}else{
    		$this->user = Timber::get_user(wp_get_current_user());
    	}

        $this->theme_dir = get_stylesheet_directory();
        $notifications_file = $this->theme_dir . '/theme/static/data/notifications.json';
		if (file_exists($notifications_file)) {
		    $this->events = json_decode(file_get_contents($notifications_file), true);
		} else {
		    $this->events = [];
		}
        $this->debug        = $debug;
        $this->debug_output = [];
        $this->html_path    = $this->theme_dir . "/theme/templates/notifications/events/";
        $this->css_path     = $this->theme_dir . "/static/css/email.css";

        // Tablo varlığını transient ile cache'le — her instantiation'da SHOW TABLES çalışmasın
        $this->maybe_create_table();

        // Admin kullanıcısını transient ile cache'le
        $this->administrator = $this->get_cached_administrator();
    }

    private function maybe_create_table(): void {
        $cache_key = 'sh_notifications_table_exists';
        if ( get_transient( $cache_key ) ) return;
        global $wpdb;
        $exists = (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'notifications' )
        );
        if ( ! $exists ) $this->create_db( 'notifications' );
        set_transient( $cache_key, true, 7 * DAY_IN_SECONDS );
    }

    private function get_cached_administrator(): ?WP_User {
        $cache_key = 'sh_notifications_administrator';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;
        $users = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
        $admin = $users[0] ?? null;
        if ( $admin ) set_transient( $cache_key, $admin, DAY_IN_SECONDS );
        return $admin;
    }

    function create_db($table) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . $table;

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			sender_id smallint(5) NOT NULL,
			receiver_id smallint(5) NOT NULL,
			message longtext NOT NULL,
			action text NOT NULL,
			type text NOT NULL,
			seen smallint(5) NOT NULL,
			alert smallint(5) NOT NULL,
			post_id smallint(5) NOT NULL,
			user_id smallint(5) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		update_option( 'notifications_version', '3.0' );
	}

    Private function is_valid($event){
    	$keys = explode('/', $event);
	    $value = $this->events;
	    foreach ($keys as $key) {
	        if (isset($value[$key])) {
	            $value = $value[$key];
	        } else {
	            return null; // Path geçersiz ise null döndür
	        }
	    }
	    return $value;
    }

    /**
     * Ensure receivers is always an array of IDs
     */
    private function normalize_receivers($receivers): array {
        if (!is_array($receivers)) $receivers = [$receivers];
        return array_filter(array_map('intval', $receivers));
    }

    function on($event_path, $data){
    	$status = [];
    	$event = $this->is_valid($event_path);
    	if($event){
    		$allowed_carriers = ['alert', 'email', 'sms'];
    		$carriers = array_intersect(array_keys($event["carriers"]), $allowed_carriers);
    		foreach($carriers as $carrier){
    		   $action = "send_" . $carrier;
               $status[$carrier] = $this->$action($event, $data);
    		}
    	}else{
    		$status['error'] = "{$event_path} not supported";
    	}
    	if($this->debug){
    		$this->debug_output = $status;
    	}
    	return $status;
    }

    Private function data_rename($rules=array(), $data=array(), $carrier="", $event_name=""){
       if(isset($data["post"])) $rules["post"] = $data["post"];
       if(isset($data["user"])) $rules["user"] = $data["user"];

       // Resolve sender/recipient placeholders
       $rules["transmit"]["sender"] = $this->resolve_user_placeholder($rules["transmit"]["sender"], $data);
       $rules["transmit"]["recipient"] = $this->resolve_user_placeholder($rules["transmit"]["recipient"], $data);

       $carrier_data = $rules["carriers"][$carrier] ?? [];

       switch($carrier){
       	  case "alert" :
       	  case "sms" :
       	      $rules["carriers"][$carrier]["body"] = $this->render($carrier_data["body"] ?? '', $data);
       	  break;
       	  case "email" :
       	        $subject = $carrier_data["subject"] ?? '';
       	        $subject = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $subject);
		        $rules["carriers"][$carrier]["subject"] = $this->render($subject, $data);

		        $body = $carrier_data["body"] ?? '';
		        if($body == "template"){
		            $template_path = $this->html_path . $event_name . "-parsed.html";
		            if(!file_exists($template_path)){
		               $body = $this->html_mail($event_name);
		               $body = str_replace("%7B%7B", "{{", $body);
		               $body = str_replace("%7D%7D", "}}", $body);
		               file_put_contents($template_path, $body);
		            }else{
		           	   $body = file_get_contents($this->html_path . $event_name . "-parsed.html");
		            }
		        }
		        $rules["carriers"][$carrier]["body"] = $this->render($body, $data);
       	  break;
       }
       return $rules;
    }

    /**
     * Resolve {{administrator}}, {{me}}, {{user}}, {{users}}, {{author}} placeholders
     */
    private function resolve_user_placeholder($value, $data) {
        return match($value) {
            '{{administrator}}' => $this->administrator->ID,
            '{{me}}'            => $this->user->ID,
            '{{user}}'          => $data["user"]->ID ?? ($data["sender"] ?? ($data["recipient"] ?? 0)),
            '{{users}}'         => $data["recipient"] ?? 0,
            '{{author}}'        => $data["post"]->author->ID ?? 0,
            default             => $value,
        };
    }

   private function get_users($ids = array(0), $values = array('user_email')) {
	    global $wpdb;

	    // 1. ID'leri temizle (Sadece rakam olduklarından emin oluyoruz)
	    $ids = array_map("intval", $ids);
	    $clean_ids = implode(",", $ids);

	    // 2. Sütun isimlerini (values) temizle
	    // Sadece harf, rakam ve alt çizgiye izin veriyoruz (SQL Injection önlemi)
	    $clean_values_array = array_map(function($val) {
	        return preg_replace('/[^a-zA-Z0-9_]/', '', $val);
	    }, $values);
	    $values_sql = implode(",", $clean_values_array);

	    // Eğer ID listesi veya değer listesi boşsa boşa sorgu yapma
	    if (empty($clean_ids) || empty($values_sql)) {
	        return array();
	    }

	    // 3. Sorguyu çalıştır
	    // wp_users yerine {$wpdb->users} kullanarak prefix'i dinamik yapıyoruz.
	    // %s ve %d burada kullanılamaz (sütun isimleri prepare ile bağlanmaz), 
	    // o yüzden yukarıda manuel temizlik yaptık.
	    $query = "SELECT $values_sql FROM {$wpdb->users} WHERE ID IN ($clean_ids)";
	    
	    return $wpdb->get_results($query);
    }
    Private function get_users_full($ids=array(0)){
        $args = array(
           'include'  => $ids
        );
        return get_users( $args );
    }




    Private function send_alert($event, $data){
    	global $wpdb;
    	$rules = $event;
    	$data = $this->data_rename($rules, $data, "alert", $rules["event"]);
    	$transmit = $data["transmit"];
    	$sender_id = intval($transmit["sender"]);
    	$receivers = $this->normalize_receivers($transmit["recipient"]);
    	$alert = $data["carriers"]["alert"];
    	$message = $alert["body"];
    	$type = $data["type"] ?? "default";
    	$post_id = intval($data["post"]->ID ?? 0);
    	$user_id = intval($data["user"]->ID ?? 0);
    	$table_name = $wpdb->prefix . 'notifications';
    	$status = 0;

    	foreach($receivers as $receiver){
            $row = [
	    		'created_at'  => gmdate("Y-m-d H:i"),
	            'sender_id'   => $sender_id,
	            'receiver_id' => intval($receiver),
	            'message'     => $message,
	            'action'      => $rules["event"],
	            'seen'        => 0,
	            'alert'       => 0,
	            'type'        => $type,
	            'post_id'     => $post_id,
	            'user_id'     => $user_id,
	        ];
	        $status = $wpdb->insert( $table_name, $row );
    	}
    	return $status;
    }




    Private function send_email($event, $data){
    	$rules = $event;
    	$data = $this->data_rename($rules, $data, "email", $rules["event"]);
    	$email = $data["carriers"]["email"];
    	$type = $email["type"] ?? "";
    	$subject = $email["subject"];
		$body = $email["body"];
    	$receivers = $this->normalize_receivers($data["transmit"]["recipient"]);
    	$receivers = $this->get_users($receivers);
    	$receivers = wp_list_pluck($receivers, "user_email");
    	$headers = $this->send_mail_headers($this->administrator->display_name, $this->administrator->user_email);

    	$status = 0;
    	if(empty($type)){
	        foreach($receivers as $receiver){
	            $status = wp_mail($receiver, $subject, $body, $headers);
		    }
    	}else{
    		foreach($receivers as $receiver){
	    		$headers[] = $type . ': ' . $receiver;
	    	}
	    	$status = wp_mail("", $subject, $body, $headers);
    	}
	    return $status;
    }
    function send_mail_headers($from_name, $from_email){
    	$headers = array();
        $headers[] = 'From: '.$from_name.' <'. $from_email.'>';
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$headers[] = 'Reply-To: '.$from_name.' <'. $from_email.'>';
		return $headers;
    }
    function html_mail($event_name=""){
        $html_file = $this->html_path . $event_name . ".html";
        if (!file_exists($html_file) || !file_exists($this->css_path)) return '';

		$html = file_get_contents($html_file);
		$css = file_get_contents($this->css_path);

		return CssInliner::fromHtml($html)->inlineCss($css)->render();
    }

    Private function send_sms($event, $data){
    	if(!defined('ENABLE_SMS_NOTIFICATIONS') || !ENABLE_SMS_NOTIFICATIONS){
    		return;
    	}
    	$rules = $event;
    	$data = $this->data_rename($rules, $data, "sms", $rules["event"]);
    	$receivers = $this->normalize_receivers($data["transmit"]["recipient"]);
    	$message = $data["carriers"]["sms"]["body"];
    	$phones = [];
    	foreach($receivers as $receiver){
    		$receiver = new User($receiver);
    		$phone = $receiver->get_phone();
    		if ($phone) $phones[] = $phone;
    	}
        if($phones){
	        $sms = new Sms(["recipients" => $phones, "content" => $message]);
	        return $sms->message();
        }
        return null;
    }

	function log_mailer_errors( $wp_error ){
		$fn = ABSPATH . '/mail.log';
		@file_put_contents($fn, "Mailer Error: " . $wp_error->get_error_message() . "\n", FILE_APPEND | LOCK_EX);
	}

    Private function render($text, $data){
    	$context = Timber::context();
   	    $context["data"] = $data; 
   	    return Timber::compile_string($text, $context);
    }

    function get_notifications($data=array()){

    	global $wpdb;
    	$results      = array();
    	$where_clauses = [];
    	$where_values  = [];
    	$table_name    = $wpdb->prefix . 'notifications';

    	$where_clauses[] = 'receiver_id = %d';
    	$where_values[]  = isset($data['user']) ? intval($data['user']) : intval($this->user->ID);

    	if(isset($data['post']))  { $where_clauses[] = 'post_id = %d'; $where_values[] = intval($data['post']); }
    	if(isset($data['seen']))  { $where_clauses[] = 'seen = %d';    $where_values[] = intval($data['seen']); }
    	if(isset($data['alert'])) { $where_clauses[] = 'alert = %d';   $where_values[] = intval($data['alert']); }

    	$where_sql    = 'WHERE ' . implode(' AND ', $where_clauses);
    	$query_values = isset($data['get_count']) ? 'count(*) as count' : '*';
    	$query        = $wpdb->prepare( "SELECT {$query_values} FROM {$table_name} {$where_sql}", $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    	if(isset($data['get_count'])){
    		$paginate = new Paginate($query);
		    $results["data"] = $paginate->get_totals();
    	}else{
    		$data['orderby'] = $data['orderby'] ?? 'created_at';
    		$data['order']   = $data['order']   ?? 'desc';

    		if(isset($data['posts_per_page']) || isset($data['page'])){
		    	$data['page']           = $data['page'] ?? 1;
		    	$data['posts_per_page'] = $data['posts_per_page'] ?? 10;
		    }

		    $paginate = new Paginate($query, $data);
		   	if(isset($data['page'])) $paginate->page = $data['page'];
	    	$results = $paginate->get_results();

	    	if(!empty($results["posts"])){
	    	    $ids = wp_list_pluck($results["posts"], 'id');
	    	    $ids = array_map('intval', $ids);
	    	    $ids_sql = implode(',', $ids);

	    	    if(isset($data["set_seen"]) && $ids_sql){
               	   $wpdb->query("UPDATE {$table_name} SET seen = 1 WHERE id IN ({$ids_sql})");
	    	    }
	    	    if(isset($data["set_alert_seen"]) && $ids_sql){
               	   $wpdb->query("UPDATE {$table_name} SET alert = 1 WHERE id IN ({$ids_sql})");
	    	    }
	    	}
	    }
        return $results;
    }

    function get_unseen_notifications_count(){
    	$notifications = $this->get_notifications([
            "seen" => 0,
            "get_count" => true
    	]);
    	return $notifications["data"]["total"] ?? 0;
    }

    function get_unseen_notifications($data=array()){
    	$unseen_messages = array();
    	$notifications = $this->get_notifications([
            "seen" => 0,
            "alert" => 0,
            "set_alert_seen" => 1
    	]);
    	if($notifications["posts"]){
    		$timeAgo = new Westsworld\TimeAgo();
    		foreach($notifications["posts"] as $notification){
    			$sender = new User($notification->sender_id);
    			$url = "";
    			if(function_exists("notification_url_map")){
    			   $url = notification_url_map($notification->action, $notification->post_id, $notification->user_id);
    			}
		        $unseen_messages[] = array(
			    	"id"      => $notification->id,
			    	"type"    => "notification",
			    	"title"   => "",
		            "sender"  => array(
		               	"image" => get_avatar( $sender->ID, 32, 'mystery', $sender->get_title()),
		                "name"  => $sender->get_title()
		            ),
		            "message" => truncate(strip_tags($notification->message), 150),
		            "url"     => $url,
		            "time"    => $timeAgo->inWordsFromStrings($this->user->get_local_date($notification->created_at, $sender->get_timezone(), $this->user->get_timezone()))
			    );
    		}
    	}
    	return $unseen_messages;
    }


    public function delete_user_event_notification($event="", $user_id=0){
    	global $wpdb;
    	if(!empty($event) && $user_id > 0){
    	   	$wpdb->delete( $wpdb->prefix . 'notifications', [ 'receiver_id' => $user_id, 'action' => $event, 'user_id' => $this->user->ID ] );
    	}
    }

    static function delete_post_notifications($post_id=0){
    	global $wpdb;
    	$wpdb->delete( $wpdb->prefix . 'notifications', [ 'post_id' => intval($post_id) ] );
    }

    static function delete_user_notifications($user_id) {
    	global $wpdb;
    	if ($user_id > 0) {
    		$table = $wpdb->prefix . 'notifications';
    	   	$wpdb->delete($table, ['sender_id' => intval($user_id)]);
    	   	$wpdb->delete($table, ['receiver_id' => intval($user_id)]);
    	}
    }

    // ─── ADMIN: ACF HOOKS ────────────────────────────────

    /**
     * Register admin hooks for ACF field population and event saving
     */
    public static function register_admin_hooks() {
        if (!is_admin()) return;

        add_filter('acf/load_field/name=notification_event', [self::class, 'populate_event_select']);
        add_filter('acf/load_field/name=notification_event_filter', [self::class, 'populate_event_select']);
        add_action('acf/options_page/save', [self::class, 'clear_filter_on_save'], 10, 2);
        add_action('acf/save_post', [self::class, 'save_events_to_json']);
    }

    /**
     * Populate ACF select field with notification event titles
     */
    public static function populate_event_select($field) {
        if (!function_exists('have_rows')) return $field;
        if (!have_rows('notification_events', 'option')) return $field;

        while (have_rows('notification_events', 'option')) {
            the_row();
            $title = get_sub_field('title');
            if (!$title) continue;

            $slug = sanitize_title($title);
            if ($field["name"] === "notification_event_filter") {
                $field['choices'][$slug] = $title;
            } else {
                $field['choices'][$slug] = $title . "|" . get_sub_field('description');
            }
        }
        return $field;
    }

    /**
     * Clear filter values on notifications options page save
     */
    public static function clear_filter_on_save($post_id, $menu_slug) {
        if ($menu_slug !== 'notifications') return;
        delete_field('notifications_filter', "options");
    }

    /**
     * Save all notification events to JSON file for runtime use
     */
    public static function save_events_to_json() {
        $data = QueryCache::get_field('notifications', "options");
        if (!$data) return;

        $roles = array_unique(array_column(array_column($data, 'notification_settings'), 'notification_role'));
        $grouped = [];

        foreach ($roles as $role) {
            foreach ($data as $item) {
                $settings = $item['notification_settings'] ?? [];
                $carriers_config = $item['notification_carriers'] ?? [];

                if (empty($settings) || ($settings['notification_role'] ?? '') !== $role) continue;

                $event = $settings['notification_event'];
                $carriers = [];

                if (!empty($carriers_config['notification_email'])) {
                    $email = $carriers_config['notification_email_content'];
                    $carriers['email'] = ['subject' => $email['subject'], 'body' => $email['body']];
                    if ($settings['notification_recipient'] === "{{users}}") {
                        $carriers['email']['type'] = 'BCC';
                    }
                }
                if (!empty($carriers_config['notification_alert'])) {
                    $carriers['alert'] = ['body' => $carriers_config['notification_alert_content']['body']];
                }
                if (!empty($carriers_config['notification_sms'])) {
                    $carriers['sms'] = ['body' => $carriers_config['notification_sms_content']['body']];
                }

                if (!empty($carriers)) {
                    $grouped[$role][$event] = [
                        'event' => $event,
                        'type' => $settings['notification_type'],
                        'user' => [],
                        'post' => [],
                        'transmit' => [
                            'sender' => $settings['notification_sender'],
                            'recipient' => $settings['notification_recipient']
                        ],
                        'carriers' => $carriers
                    ];
                }
            }
        }

        $json_path = get_template_directory() . '/theme/static/data/notifications.json';
        file_put_contents($json_path, json_encode($grouped, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

// Init admin hooks
Notifications::register_admin_hooks();
