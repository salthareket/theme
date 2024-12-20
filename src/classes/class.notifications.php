<?php

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
    public $html_url;
    public $css_path;
    public $css_url; 
    public $administrator;

    function __construct($user=array(), $debug=0) {
    	global $wpdb;
    	if(isset($user) && !empty($user)){
    		$this->user = $user;
    	}else{
    		$this->user = Timber::get_user(wp_get_current_user());
    	}
        $this->events = json_decode(file_get_contents(get_stylesheet_directory() ."/theme/static/data/notifications.json"), true);//array();
        $this->debug = $debug;
        $this->debug_output = array();
        $this->html_path = get_stylesheet_directory() . "/theme/templates/notifications/events/";
        $this->html_url = get_stylesheet_directory_uri() . "/theme/templates/notifications/events/";
        $this->css_path = get_stylesheet_directory() . "/static/css/email.css";
        $this->css_url = get_stylesheet_directory_uri() . "/static/css/email.css";
        $table = "notifications";
        if (!$wpdb->get_var("SHOW TABLES LIKE 'wp_".$table."'")) {
		    echo "Table does not exist";
		    $this->create_db($table);
		}
		//get admin
	    $args = array(
           'role'    => 'administrator',
           'number'  => 1
        );
        $administrator = get_users( $args )[0];
	    //$admin_title = get_option('blogname');
	    $this->administrator = $administrator;
	    //add_action('wp_mail_failed', $this->log_mailer_errors(), 10, 1);
    }

    function create_db($table) {
		global $wpdb;
	  	$version = get_option( 'notifications_version', '1.0' );
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
		
		if ( version_compare( $version, '3.0' ) < 0 ) {
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
			dbDelta( $sql );
		  	update_option( 'notifications_version', '3.0' );
		}
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

    function on($event, $data){
    	$status = array();
    	$event = $this->is_valid($event);
    	if($event){
    		//$carriers = array_keys($this->events[$event]["carriers"]);
    		//$event_name = $this->get_event($event);
    		$carriers = array_keys($event["carriers"]);
    		foreach($carriers as $carrier){
    		   $action = "send_".$carrier;
               $status[$carrier] = $this->$action($event, $data);
    		}
    	}else{
    		$status = "$event not supported";
    	}
    	if($this->debug){
    		$this->debug_output = $status;
    	}
    }

    Private function data_rename($rules=array(), $data=array(), $carrier="", $event_name=""){
       if(isset($data["post"])){
          $rules["post"] = $data["post"];
       }
       if(isset($data["user"])){
          $rules["user"] = $data["user"];
       }
       if($rules["transmit"]["sender"] == "{{administrator}}"){
       	  $rules["transmit"]["sender"] = $this->administrator->ID;
       }
       if($rules["transmit"]["sender"] == "{{me}}"){
       	  $rules["transmit"]["sender"] = $this->user->ID;
       }
       if($rules["transmit"]["sender"] == "{{user}}"){
       	  $rules["transmit"]["sender"] = isset($data["sender"])?$data["sender"]:$data["user"]->ID;
       }
       if($rules["transmit"]["recipient"] == "{{administrator}}"){
       	  $rules["transmit"]["recipient"] = $this->administrator->ID;
       }
       if($rules["transmit"]["recipient"] == "{{me}}"){
       	  $rules["transmit"]["recipient"] = $this->user->ID;
       }
       if($rules["transmit"]["recipient"] == "{{users}}"){
       	  $rules["transmit"]["recipient"] = $data["recipient"];
       }
       if($rules["transmit"]["recipient"] == "{{user}}"){
       	  $rules["transmit"]["recipient"] = isset($data["recipient"])?$data["recipient"]:$data["user"]->ID;
       }
       if($rules["transmit"]["recipient"] == "{{author}}"){
       	  $rules["transmit"]["recipient"] = $data["post"]->author->ID;
       }

       switch($carrier){
       	  case "alert" :
       	  case "sms" :
       	      $message = $rules["carriers"][$carrier]["body"];
              $rules["carriers"][$carrier]["body"] = $this->render($message, $data);
       	  break;
       	  case "email" :
       	        $subject = $rules["carriers"][$carrier]["subject"];
       	        $subject = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $subject);
		        $rules["carriers"][$carrier]["subject"] = $this->render($subject, $data);

		        $body = $rules["carriers"][$carrier]["body"];
		        if($body == "template"){
		            $template_path = $this->html_path.$event_name."-parsed.html";
		            if(!file_exists($template_path)){
		              //echo "parsed not found";
		               $body = $this->html_mail($event_name);
		               $body = str_replace("%7B%7B", "{{", $body);
		               $body = str_replace("%7D%7D", "}}", $body);
		               file_put_contents($template_path, $body);
		            }else{
		              //echo "parsed found";
		           	   $template_url = $this->html_url.$event_name."-parsed.html";
		           	   $body = file_get_contents($template_url);
		            }
		        }
		        $rules["carriers"][$carrier]["body"] = $this->render($body, $data);
       	  break;
       }
       return $rules;
    }

    Private function get_users($ids=array(0), $values=array('user_email')){
    	global $wpdb;
    	$values = implode(",",$values);
    	$ids = array_map("intval", $ids);
    	$ids = implode(",", $ids);
        return $wpdb->get_results("SELECT $values FROM wp_users where ID in ($ids)");
    }
    Private function get_users_full($ids=array(0)){
        $args = array(
           'include'  => $ids
        );
        return get_users( $args );
    }




    Private function send_alert($event, $data){
    	global $wpdb;
    	$status = 0;
    	$rules = $event;//$this->events[$event];
    	$data = $this->data_rename($rules, $data, "alert", $rules["event"]);
    	$transmit = $data["transmit"];
    	$sender_id = $transmit["sender"];
    	$receivers = $transmit["recipient"];
    	if(!is_array($receivers)){
           $receivers = [$receivers]; 
    	}
    	$message = $data["carriers"]["alert"]["body"];
    	$type = "default";
    	if(isset($data["type"])){
    	   	$type = $data["type"];    	   	
    	}
    	$post_id = 0;
    	if(isset($data["post"])){
    	   if(isset($data["post"]->ID)){
    	   		$post_id = $data["post"]->ID;    	   	
    	   }
    	}
    	$user_id = 0;
    	if(isset($data["user"])){
    		if(isset($data["user"]->ID)){
    	       $user_id = $data["user"]->ID;
    	   }
    	}
    	foreach($receivers as $receiver){
            $created_at = new DateTime();
            $created_at->setTimezone(new DateTimeZone('GMT'));
            $created_at = $created_at->format("Y-m-d H:i");
	    	$row = array(
	    		'created_at' => $created_at,//date('Y-m-d H:i:s', strtotime('now')),
	            'sender_id' => $sender_id,
	            'receiver_id' => $receiver,
	            'message' => $message,
	            'action' => $rules["event"],
	            'seen' => 0,
	            'alert' => 0
	        );
	        if($type){
	        	$row["type"] = $type;
	        }
	        if($post_id){
	        	$row["post_id"] = $post_id;
	        }
	        if($user_id){
	        	$row["user_id"] = $user_id;
	        }
	        $status = $wpdb->insert("wp_notifications", $row);
    	}
    	return $status;
    }




    Private function send_email($event, $data){
        $status = 0;
    	$rules = $event;//$this->events[$event];
    	$data = $this->data_rename($rules, $data, "email", $rules["event"]);

    	$type = isset($data["carriers"]["email"]["type"])?$data["carriers"]["email"]["type"]:"";
    	
    	$subject = $data["carriers"]["email"]["subject"];
		$body = $data["carriers"]["email"]["body"];

    	$transmit = $data["transmit"];
    	//$sender_id = $transmit["sender"];
    	$receivers = $transmit["recipient"];
    	if(!is_array($receivers)){
           $receivers = [$receivers]; 
    	}
    	$receivers = $this->get_users($receivers);
    	$receivers = wp_list_pluck( $receivers, "user_email");
    	$headers = $this->send_mail_headers($this->administrator->display_name, $this->administrator->user_email);

    	if(empty($type)){
	        foreach($receivers as $receiver){
	            $status = wp_mail($receiver, $subject, $body, $headers );
		    }
    	}else{
    		foreach($receivers as $receiver){
	    		$headers[] =  $type.': '.$receiver;
	    	}
	    	$status = wp_mail("", $subject, $body, $headers );
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
		$html = file_get_contents($this->html_url.$event_name.".html");
		$css = file_get_contents($this->css_url);

		//$html = HtmlNormalizer::fromHtml($html)->render();
		$html = CssInliner::fromHtml($html)->inlineCss($css)->render();
		//$html = CssToAttributeConverter::fromHtml($html)->convertCssToVisualAttributes()->render();
		return $html;
    }

    Private function send_sms($event, $data){
    	if(!ENABLE_SMS_NOTIFICATIONS){
    		return ;
    	}
    	$rules = $event;//$this->events[$event];
    	$data = $this->data_rename($rules, $data, "sms", $rules["event"]);
    	$transmit = $data["transmit"];
    	$receivers = $transmit["recipient"];
    	if(!is_array($receivers)){
           $receivers = [$receivers]; 
    	}
    	$message = $data["carriers"]["sms"]["body"];
    	$phones = array();
    	foreach($receivers as $receiver){
    		$receiver = new User($receiver);
    		$phones[] = $receiver->get_phone();
    	}
    	$vars = array(
          "recipients" => $phones,
          "content"    => $message
        );
        if($phones){
	        $sms = new Sms($vars);
	        return $sms->message();        	
        }else{
        	return ;
        }
    }

	function log_mailer_errors( $wp_error ){
		  $fn = ABSPATH . '/mail.log'; // say you've got a mail.log file in your server root
		  $fp = fopen($fn, 'a');
		  fputs($fp, "Mailer Error: " . $wp_error->get_error_message() ."\n");
		  fclose($fp);
	}

    Private function render($text, $data){
    	$context = Timber::context();
   	    $context["data"] = $data; 
   	    return Timber::compile_string($text, $context);
    }

    function get_notifications($data=array()){

    	$results = array();
    	$where = array(); 
    	if(isset($data['user'])){
           $where[] = "receiver_id = ".$data["user"];
    	}else{
    	   $where[] = "receiver_id = ".$this->user->ID;
    	}
    	if(isset($data['post'])){
           $where[] = "post_id = ".$data["post"];
    	}
    	if(isset($data['seen'])){
           $where[] = "seen = ".$data["seen"];
    	}
    	if(isset($data['alert'])){
           $where[] = "alert = ".$data["alert"];
    	}
    	if(count($where)>0){
    		$where = implode(" AND ", $where);
    	}
    	$query_values = "*";
    	if(isset($data['get_count'])){
           $query_values = "count(*) as count";
    	}
    	$query = "SELECT ".$query_values." FROM wp_notifications where ".$where;

    	if(isset($data['get_count'])){
    		$paginate = new Paginate($query);
		    $results["data"] = $paginate->get_totals();
    	}else{

    		$orderby = "created_at";
		    if(isset($data['orderby'])){
		  		$orderby = $data['orderby'];
		    }
		    $data['orderby'] = $orderby;

		    $order = "desc";
		   	if(isset($data['order'])){
		   		$order = $data['order'];
		    }
		    $data['order'] = $order;

    		if(isset($data['posts_per_page']) || isset($data['page'])){
		    	if(isset($data['page'])){
		    		$page = $data['page'];
		    	}else{
		    		$page = 1;
		    	}
		    	$data['page'] = $page;
		    	if(isset($data['posts_per_page'])){
		    		$posts_per_page = $data['posts_per_page'];
		    	}else{
		    		$posts_per_page = 10;
		    	}
		    	$data['posts_per_page'] = $posts_per_page;
		    }

		    $paginate = new Paginate($query, $data);
		   	//$paginate->query = $query;
		   	//$paginate->orderby = $orderby;
		   	//$paginate->order = $order;
		   	if(isset($page)){
               $paginate->page = $page;
		   	}
		   	//if(isset($posts_per_page)){
	        //    $paginate->posts_per_page = $posts_per_page;
	        //}
	    	//$results["data"] = $paginate->get_totals();
	    	//$results["posts"] = $paginate->get_results();
	    	$results = $paginate->get_results();

	    	if(isset($data["set_seen"])){
               //$ids = wp_list_pluck($results["posts"],"id");
               if($results["posts"]){
               	  global $wpdb;
               	  foreach($results["posts"] as $post){
               	      $wpdb->update('wp_notifications', array('seen'=> '1'), array('id'=>$post->id));
               	  }
               }
	    	}
	    	if(isset($data["set_alert_seen"])){
               //$ids = wp_list_pluck($results["posts"],"id");
               if($results["posts"]){
               	  global $wpdb;
               	  foreach($results["posts"] as $post){
               	      $wpdb->update('wp_notifications', array('alert'=> '1'), array('id'=>$post->id));
               	  }
               }
	    	}
	    }
        return $results;
    }

    function get_unseen_notifications_count($data=array()){
    	$unseen_messages = array();
    	$notifications = $this->get_notifications([
            "seen" => 0,
            "get_count" => true
    	]);
    	return $notifications["data"]["total"];
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
    	   	$wpdb->delete( "wp_notifications", array( 'receiver_id' => $user_id, 'action' => $event, 'user_id' => $this->user->ID ));//, array( '%d', '%s', '%d' ) ); 	  	
    	}
    }
 

    static function delete_post_notifications($post_id=0){
    	global $wpdb;
    	//if($post_id > 0){
    	   	$wpdb->delete( "wp_notifications", array( 'post_id' => $post_id ) ); 	   	  	
    	//}
    }
    static function delete_user_notifications($user_id){
    	global $wpdb;
    	if($user_id > 0){
    	   	$wpdb->delete( "wp_notifications", array( 'sender_id' => $user_id ) );
    	   	$wpdb->delete( "wp_notifications", array( 'receiver_id' => $user_id ) );   	  	
    	}
    }
}








/*
WordPress Post Etkinlikleri:

    save_post: Bir yazı veya sayfa kaydedildiğinde tetiklenir.
    publish_post: Bir yazı veya sayfa yayınlandığında tetiklenir.
    delete_post: Bir yazı veya sayfa silindiğinde tetiklenir.
    trashed_post: Bir yazı veya sayfa çöpe atıldığında tetiklenir.
    transition_post_status: Bir yazının durumu değiştiğinde tetiklenir.

WordPress User Etkinlikleri:

    user_register: Yeni bir kullanıcı kaydolduğunda tetiklenir.
    profile_update: Kullanıcı profil bilgileri güncellendiğinde tetiklenir.
    delete_user: Bir kullanıcı silindiğinde tetiklenir.
    user_login: Bir kullanıcı oturum açtığında tetiklenir.

WordPress Taxonomy Etkinlikleri:

    create_term: Yeni bir terim oluşturulduğunda tetiklenir.
    edit_term: Bir terim düzenlendiğinde tetiklenir.
    delete_term: Bir terim silindiğinde tetiklenir.

WooCommerce Etkinlikleri (ek olarak):

    woocommerce_new_order: Yeni bir sipariş oluşturulduğunda tetiklenir.
    woocommerce_order_status_changed: Sipariş durumu değiştiğinde tetiklenir.
    woocommerce_payment_complete: Ödeme tamamlandığında tetiklenir.
    woocommerce_add_to_cart: Sepete ürün eklendiğinde tetiklenir.
    woocommerce_remove_cart_item: Sepetten ürün çıkarıldığında tetiklenir.
*/






// ACF repeater field gruplarına göre event title'ları toplama işlevi
function get_event_titles($field) {
    $event_titles = array();
    if (function_exists('have_rows')) {
        // Option Page: event
        if (have_rows('notification_events', 'option')) {
            while (have_rows('notification_events', 'option')) {
                the_row();
                $title = get_sub_field('title');
                $slug = sanitize_title($title);
                $description = get_sub_field('description');
                if($field["name"] == "notification_event_filter"){
                	if ($title) {
	                    $event_titles[$slug] = $title;
	                }
                }else{
	                if ($title) {
	                    $event_titles[$slug] = $title."|".$description;
	                }                	
                }
            }
        }
    }
    return $event_titles;
}
// ACF repeater içindeki event seçeneği için select alanını güncelleme işlevi
function update_event_select_options($field) {
    $event_titles = get_event_titles($field);
    if (!empty($event_titles)) {
        foreach ($event_titles as $key => $title) {
            $field['choices'][$key] = $title;
        }
    }
    return $field;
}
if(is_admin()){
	add_filter('acf/load_field/name=notification_event', 'update_event_select_options');
	add_filter('acf/load_field/name=notification_event_filter', 'update_event_select_options');
}


// dont save fnotifications filter values
add_action('acf/options_page/save', 'my_acf_save_options_page', 10, 2);
function my_acf_save_options_page( $post_id, $menu_slug ) {
    if ( 'notifications' !== $menu_slug ) {
        return;     
    }
    delete_field( 'notifications_filter', "options" );
}

// store all notification events in a json filr
function notifications_save_events() {
    $field_group = 'notifications';
    $data = get_field($field_group, 'options');
    if(!$data){
    	return;
    }
	$roles = array_unique(array_column(array_column($data, 'notification_settings'), 'notification_role'));
	$grouped_data = array();
	foreach ($roles as $role) {
	    foreach ($data as $item) {
	        if (isset($item['notification_settings']) && $item['notification_settings']['notification_role'] === $role) {
	            $event = $item['notification_settings']['notification_event'];

	            // Düzenlemeleri yap
	            $carriers = array();
	            if ($item['notification_carriers']['notification_email'] === true) {
	                $email_content = $item['notification_carriers']['notification_email_content'];
	                $carriers['email'] = array(
	                    'subject' => $email_content['subject'],
	                    //'template' => $email_content['template'],
	                    'body' => $email_content['body']
	                );
	                if($item['notification_settings']['notification_recipient'] == "{{users}}"){
	                	$carriers['email']["type"] = "BCC";
	                }
	            }
	            if ($item['notification_carriers']['notification_alert'] === true) {
	                $alert_content = $item['notification_carriers']['notification_alert_content'];
	                $carriers['alert'] = array(
	                    'body' => $alert_content['body']
	                );
	            }
	            if ($item['notification_carriers']['notification_sms'] === true) {
	                $sms_content = $item['notification_carriers']['notification_sms_content'];
	                $carriers['sms'] = array(
	                    'body' => $sms_content['body']
	                );
	            }

	            // Sadece boş olmayanları ekle
	            if (!empty($carriers)) {
	                $grouped_data[$role][$event] = array(
	                	'event' => $event,
	                    'type' => $item['notification_settings']['notification_type'],
	                    'user' => array(),
	                    'post' => array(),
	                    'transmit' => array(
	                        'sender' => $item['notification_settings']['notification_sender'],
	                        'recipient' => $item['notification_settings']['notification_recipient']
	                    ),
	                    'carriers' => $carriers
	                );
	            }
	        }
	    }
	}
	$data = $grouped_data;
    $json_file_path = get_template_directory() . '/theme/static/data/notifications.json';
    $json_data = json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($json_file_path, $json_data);
}
add_action('acf/save_post', 'notifications_save_events');
