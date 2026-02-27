<?php

   // fixes
   // get_users_all_conversation function on yobro-helper.php
   // lines 69, 70
   /*
    //fix
	if(get_query_var("conversationId")){
        $all_conversations =  \YoBro\App\Conversation::where('id', '=', get_query_var("conversationId"))
		->orderBy('created_at', 'desc')->get()->toArray();
	}else{
		$all_conversations =  \YoBro\App\Conversation::where('sender', '=', $user_id)
		->orWhere('reciever', '=', $user_id)->orderBy('created_at', 'desc')->get()->toArray();
	}
	*/

    //add required columns to conversation table
	global $wpdb;

	// Tablo ismini dinamik prefix ile alıyoruz
	$table_name = $wpdb->prefix . "yobro_conversation";

	// Collation'ı sunucunun mevcut ayarına göre dinamik çekiyoruz
	$charset_collate = $wpdb->get_charset_collate();

	$columns = array(
	    array(
	        "table" => $wpdb->prefix . "yobro_conversation",
	        "name"  => "post_id",
	        "type"  => "bigint(20) NOT NULL DEFAULT 0" // bigint(200) standart dışıdır, (20) idealdir
	    ),
	    array(
	        "table" => $wpdb->prefix . "yobro_messages",
	        "name"  => "notification",
	        "type"  => "tinytext $charset_collate" // Sabit collation yerine sisteminkini kullandık
	    )
	);
	if($columns){
		global $wpdb;
		$database = $wpdb->dbname;
		foreach($columns as $column){
			$table = $column["table"];
			$column_name = $column["name"];
			$column_type = $column["type"];
			$rows = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '$database' and table_name = '$table';");
			$exist = false;
			foreach($rows as $row){
				if($row->COLUMN_NAME == $column_name){
				   $exist = true;
				}				
			}
			if(!$exist){
				$wpdb->query("ALTER TABLE $table ADD $column_name $column_type;");	
			}
		}
	}


	/**
	 * Bildirim mesajlarını getirir ve 'bildirim gönderildi' olarak işaretler.
	 */
	function yobro_notification_messages($action = "") {
	    global $wpdb;
	    $user_id = get_current_user_id();
	    if (!$user_id) return array();

	    $user = new User($user_id);
	    $sql_action_query = "";
	    
	    // Switch case ile güvenli query ekleme
	    switch ($action) {
	        case "seen":
	            $sql_action_query = " AND messages.seen IS NULL";
	            break;
	        case "notification":
	            $sql_action_query = " AND messages.notification IS NULL";
	            break;
	    }

	    $sql = $wpdb->prepare("
	        SELECT DISTINCT messages.id, messages.conv_id, messages.sender_id, messages.message, messages.created_at, conversation.post_id
	        FROM {$wpdb->prefix}yobro_messages messages
	        INNER JOIN {$wpdb->prefix}yobro_conversation conversation ON messages.conv_id = conversation.id 
	        WHERE messages.reciever_id = %d $sql_action_query
	        ORDER BY messages.created_at ASC LIMIT 1
	    ", $user_id);

	    $messages = $wpdb->get_results($sql);
	    $unseen_messages = array();
	    $timeAgo = new Westsworld\TimeAgo();

	    foreach ($messages as $message) {
	        $title = "Message";
	        $url = get_account_endpoint_url("messages") . $message->conv_id;

	        if (isset($message->post_id) && $message->post_id > 0) {
	            $url = get_permalink($message->post_id) . "#messages";
	            $title = get_the_title($message->post_id);
	        } elseif ($message->post_id == 0) {
	            $title = "Message from Bot";
	        }

	        $sender = new User($message->sender_id);
	        $item = array(
	            "id"      => $message->conv_id,
	            "type"    => "message",
	            "title"   => $title,
	            "sender"  => array(
	                "id"    => $message->sender_id,
	                "image" => get_avatar($message->sender_id, 32),
	                "name"  => $sender->display_name
	            ),
	            "message" => truncate(strip_tags(encrypt_decrypt($message->message, $message->sender_id, 'decrypt')), 150),
	            "url"     => $url,
	            "time"    => $timeAgo->inWordsFromStrings($user->get_local_date($message->created_at, "GMT", $user->get_timezone()))
	        );

	        $unseen_messages[] = $item;

	        // Bildirim olarak işaretle (Güvenli Update)
	        $wpdb->update(
	            "{$wpdb->prefix}yobro_messages",
	            array('notification' => 1),
	            array('id' => $message->id),
	            array('%d'),
	            array('%d')
	        );
	    }
	    return $unseen_messages;
	}

	/**
	 * Okunmamış mesaj sayısını döner.
	 */
	function yobro_unseen_messages_count($conv_id = 0) {
	    global $wpdb;
	    $user_id = get_current_user_id();
	    $where = $conv_id > 0 ? $wpdb->prepare(" AND conv_id = %d", $conv_id) : "";
	    
	    return $wpdb->get_var($wpdb->prepare("
	        SELECT COUNT(DISTINCT conv_id) 
	        FROM {$wpdb->prefix}yobro_messages 
	        WHERE reciever_id = %d AND seen IS NULL $where
	    ", $user_id));
	}

	/**
	 * Yeni bir konuşma ve ilk mesajı başlatır.
	 */
	function yobro_new_conversation($sender_id, $reciever_id, $message = "", $post_id = 0) {
	    global $wpdb;

	    // Konuşmayı oluştur (Varsayılan sınıfla)
	    $new_conversation = \YoBro\App\Conversation::create(array(
	        'sender'   => $sender_id,
	        'reciever' => $reciever_id
	    ));

	    if (!$new_conversation || !isset($new_conversation['id'])) return false;

	    $conv_id = $new_conversation['id'];
	    $created_at = current_time('mysql', 1); // WordPress usulü GMT zamanı

	    // Konuşmayı güncelle (Tek bir UPDATE sorgusunda birleştirdik)
	    $wpdb->update(
	        "{$wpdb->prefix}yobro_conversation",
	        array('post_id' => $post_id, 'created_at' => $created_at),
	        array('id' => $conv_id),
	        array('%d', '%s'),
	        array('%d')
	    );

	    if (!empty($message)) {
	        return \YoBro\App\Message::create(array(
	            'conv_id'     => $conv_id,
	            'sender_id'   => $sender_id,
	            'reciever_id' => $reciever_id,
	            'message'     => encrypt_decrypt($message, $sender_id),
	            'created_at'  => $created_at,
	        ));
	    }
	    return $conv_id;
	}

    function yobro_send_message($conv_id, $sender_id, $reciever_id, $message){
    	$args = array(
		   "conv_id" => $conv_id,
		   "message" => $message,
		   "sender_id" => $sender_id,
		   "reciever_id" => $reciever_id
		);
		return do_store_message($args);
    }

    /**
 * Konuşmanın var olup olmadığını kontrol eder.
 */
function yobro_check_conversation_exist($post_id = 0, $sender_id = 0, $reciever_id = 0, $forced = false) {
    global $wpdb;

    if ($sender_id <= 0 || $reciever_id <= 0) {
        return false;
    }

    $post_id = (int)$post_id;
    $table = $wpdb->prefix . "yobro_conversation";

    if ($sender_id == $reciever_id && $post_id > 0) {
        // Aynı kullanıcılar arasındaki konuşma
        $where_user = $wpdb->prepare("(reciever = %d OR sender = %d)", $sender_id, $sender_id);
    } else {
        // İki farklı kullanıcı arasındaki konuşma
        $condition = "(reciever = %d AND sender = %d)";
        if ($forced) {
            $condition = "((reciever = %d AND sender = %d) OR (reciever = %d AND sender = %d))";
            $where_user = $wpdb->prepare($condition, $reciever_id, $sender_id, $sender_id, $reciever_id);
        } else {
            $where_user = $wpdb->prepare($condition, $reciever_id, $sender_id);
        }
    }

    $sql = "SELECT id FROM {$table} WHERE $where_user AND post_id = %d ORDER BY id ASC LIMIT 1";
    return $wpdb->get_var($wpdb->prepare($sql, $post_id));
}

/**
 * Belirli bir konuşmayı ve o konuşmaya ait tüm mesajları siler.
 */
function yobro_remove_conversation_by_id($conv_id) {
    global $wpdb;
    $conv_id = (int)$conv_id;

    if ($conv_id > 0) {
        // Mesajları sil
        $wpdb->delete($wpdb->prefix . "yobro_messages", array('conv_id' => $conv_id), array('%d'));
        // Konuşmayı sil
        $wpdb->delete($wpdb->prefix . "yobro_conversation", array('id' => $conv_id), array('%d'));
    }
}

/**
 * Bir kullanıcıya ait tüm konuşmaları temizler.
 */
function yobro_remove_conversation_by_user($user_id) {
    global $wpdb;
    $user_id = (int)$user_id;

    $table = $wpdb->prefix . "yobro_conversation";
    $conversations = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE sender = %d OR reciever = %d", $user_id, $user_id));

    if (!empty($conversations)) {
        foreach ($conversations as $conversation_id) {
            yobro_remove_conversation_by_id($conversation_id);
        }
    }
}

/**
 * Bir ilana (post) ait tüm konuşmaları temizler.
 */
function yobro_remove_conversation_by_post($post_id) {
    global $wpdb;
    $post_id = (int)$post_id;

    $table = $wpdb->prefix . "yobro_conversation";
    $conversations = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE post_id = %d", $post_id));

    if (!empty($conversations)) {
        foreach ($conversations as $conversation_id) {
            yobro_remove_conversation_by_id($conversation_id);
        }
    }
}



    /**
 * Kullanıcının kaç farklı kişiden mesaj (konuşma) aldığını sayar.
 */
function yobro_has_reciever_conservations($user_id = 0) {
    global $wpdb;
    if ($user_id == 0) {
        $user_id = get_current_user_id();
    }
    
    // COUNT(DISTINCT) sorgusu performansı etkiler, prepare ile koruyoruz.
    $sql = $wpdb->prepare(
        "SELECT COUNT(DISTINCT conv_id) FROM {$wpdb->prefix}yobro_messages WHERE reciever_id = %d",
        $user_id
    );
    return $wpdb->get_var($sql);
}

/**
 * Konuşmanın genel bilgilerini (meta verilerini) tek bir satır olarak döner.
 */
function yobro_get_conversation_row($conv_id = 0) {
    global $wpdb;
    
    // DISTINCT * yerine direkt get_row kullanmak daha mantıklı.
    $sql = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yobro_conversation WHERE id = %d LIMIT 1",
        $conv_id
    );
    return $wpdb->get_row($sql);
}

/**
 * Bir konuşmaya ait tüm mesajları tarih sırasına göre getirir.
 */
function yobro_get_conversation($conv_id = 0) {
    global $wpdb;
    
    // Mesajları çekerken ID ve zaman damgası kontrolü önemli.
    $sql = $wpdb->prepare(
        "SELECT id, conv_id, sender_id, message, created_at 
         FROM {$wpdb->prefix}yobro_messages 
         WHERE conv_id = %d 
         ORDER BY created_at ASC",
        $conv_id
    );
    return $wpdb->get_results($sql);
}

/**
 * Konuşma verilerini dizi (array) formatında döner.
 */
function yobro_get_conversation_data($conv_id = 0) {
    global $wpdb;
    
    $sql = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yobro_conversation WHERE id = %d LIMIT 1",
        $conv_id
    );
    return $wpdb->get_results($sql);
}

    function yobro_get_reciever_conversations($user_id) {
	    global $wpdb;
	    // wp_yobro_conversation -> {$wpdb->prefix}yobro_conversation
	    // wp_posts -> {$wpdb->posts}
	    // wp_users -> {$wpdb->users}
	    
	    $sql = $wpdb->prepare("
	        SELECT c.id as conversation_id, t.post_title as title, u.display_name as agent 
	        FROM {$wpdb->prefix}yobro_conversation c
	        INNER JOIN {$wpdb->posts} t ON c.post_id = t.ID
	        INNER JOIN {$wpdb->users} u ON c.sender = u.ID
	        WHERE t.post_type = 'project' 
	        AND c.reciever = %d
	    ", $user_id);
	    
	    return $wpdb->get_results($sql);
	}

	function yobro_get_sender_conversations($user_id) {
	    global $wpdb;
	    
	    $sql = $wpdb->prepare("
	        SELECT c.id as conversation_id, t.post_title as title, u.display_name as agent 
	        FROM {$wpdb->prefix}yobro_conversation c
	        INNER JOIN {$wpdb->posts} t ON c.post_id = t.ID
	        INNER JOIN {$wpdb->users} u ON c.sender = u.ID
	        WHERE t.post_type = 'project' 
	        AND c.sender = %d
	    ", $user_id);
	    
	    return $wpdb->get_results($sql);
	}

	function yobro_get_all_conversations($user_id) {
	    global $wpdb;
	    
	    // Not: Burada 'u.display_name' kısmını sender'a göre alıyoruz, 
	    // ama 'all' dediğin için display_name kafa karıştırabilir, sadece başlıkları bıraktım.
	    $sql = $wpdb->prepare("
	        SELECT c.id as conversation_id, t.post_title as title 
	        FROM {$wpdb->prefix}yobro_conversation c
	        INNER JOIN {$wpdb->posts} t ON c.post_id = t.ID
	        WHERE t.post_type = 'project' 
	        AND (c.sender = %d OR c.reciever = %d)
	    ", $user_id, $user_id);
	    
	    return $wpdb->get_results($sql);
	}

    function yobro_create_conversation_dropdown($user_id){
    	$chat_page_url = get_account_endpoint_url('messages');
    	$conversations = yobro_get_all_conversations($user_id);
    	$code = "";
    	if($conversations){
    	   $code = "<select class='selectpicker selectpicker-url-update' name='conversations'>";
    	   foreach($conversations as $conversation){
    	      $selected = $conversation->conversation_id == get_query_var("conversationId")?" selected":"";
    	   	  $code .= "<option value='".$chat_page_url."?conversationId=".$conversation->conversation_id."'".$selected.">".$conversation->title."</option>";
    	   }
    	   $code .= "</select>"; 
    	}
    	return $code;
    }



    function get_few_messages_by_conversation($conv_id){
		$current_user_id = get_current_user_id();
        $user = new User($current_user_id);
	    //get current page's tour chat
	    if(isset($_SESSION["querystring"])){
			$params = $_SESSION["querystring"];
			unset_filter_session('querystring'); 
			$params = json_decode($params, true);
			if(!empty($params)){
			   /*if( isset( $params['tour-plan-offer-id'] ) ){
			   	  $conv_id = yobro_get_offer_conversation($params['tour-plan-offer-id']);
			   }*/
			   if( isset( $params['conversationId'] ) ){
	              $conv_id = $params['conversationId'];
			   }
			}
	    }

		$messages = \YoBro\App\Message::where('conv_id', '=', $conv_id)
			->where('delete_status', '!=', 1)
			->where(function ($query) use ($current_user_id) {
				$query->where('sender_id', $current_user_id)
					->orWhere('reciever_id', $current_user_id);
			})
			->orderBy('id', 'asc')->get()->toArray();
		$total_messages = array();
		if (isset($messages) && !empty($messages)) {
			foreach ($messages as &$message) {
				$message['message'] = encrypt_decrypt($message['message'], $message['sender_id'], 'decrypt');
				if ($message['sender_id'] == get_current_user_id()) {
					$message['owner'] = 'true';
				} else {
					$message['owner'] = 'false';
				}
				if (get_avatar($message['sender_id'])) {
					$message['pic'] =  get_avatar($message['sender_id']);
				} else {
					$message['pic'] =  up_user_placeholder_image();
				}
				$message['reciever_name'] = get_user_name_by_id($message['reciever_id']) ?  get_user_name_by_id($message['reciever_id']) : 'Untitled';
				$message['sender_name'] = get_user_name_by_id($message['sender_id']) ?  get_user_name_by_id($message['sender_id']) : 'Untitled';
				$message['time'] = $user->get_local_date($message['created_at'], "GMT", $user->get_timezone());
				if (isset($message['attachment_id']) && $message['attachment_id'] != null) {
					$message['attachments'] = YoBro\App\Attachment::where('id', '=', $message['attachment_id'])->first();
				}
				if (!isset($total_messages[$message['id']])) {
					$total_messages[$message['id']] = $message;
				}
			}
			return $total_messages;
		} else {
			return array();
		}
	}

    
    /**
 * Bir konuşmadaki son mesajı getirir.
 */
function yobro_get_conversation_last_message($conv_id = 0, $sender_id = 0) {
    global $wpdb;
    $conv_id = (int)$conv_id;
    $sender_id = (int)$sender_id;

    $sender_query = $sender_id > 0 ? $wpdb->prepare(" AND sender_id = %d", $sender_id) : "";

    $sql = $wpdb->prepare("
        SELECT id, conv_id, sender_id, message, created_at, seen 
        FROM {$wpdb->prefix}yobro_messages 
        WHERE conv_id = %d $sender_query 
        ORDER BY created_at DESC LIMIT 1
    ", $conv_id);

    return $wpdb->get_row($sql);
}

/**
 * Belirli bir ilana ait konuşmaları ve okunmamış mesaj sayılarını listeler.
 */
function yobro_get_post_conversations($post_id = 0, $user_id = 0) {
    global $wpdb;
    $post_id = (int)$post_id;
    $user_id = (int)$user_id;

    $post_query = $post_id > 0 ? $wpdb->prepare(" AND conversation.post_id = %d", $post_id) : "";

    $sql = $wpdb->prepare("
        SELECT 
            m.conv_id, 
            c.post_id, 
            c.sender, 
            c.reciever, 
            (COUNT(m.id) - COUNT(m.seen)) as new_messages, 
            MAX(m.created_at) as last_date
        FROM {$wpdb->prefix}yobro_messages m
        INNER JOIN {$wpdb->prefix}yobro_conversation c ON m.conv_id = c.id
        WHERE (c.reciever = %d OR c.sender = %d) $post_query
        GROUP BY m.conv_id
        ORDER BY new_messages DESC, last_date DESC
    ", $user_id, $user_id);

    $results = $wpdb->get_results($sql);
    $output = array();

    foreach ($results as $result) {
        $other_user_id = ($user_id == $result->sender) ? $result->reciever : $result->sender;
        $other_user = new User($other_user_id);
        $last_msg = yobro_get_conversation_last_message($result->conv_id);

        $item = array(
            "id"           => $result->conv_id,
            "post_id"      => $result->post_id,
            "sender"       => array(
                "id"    => $other_user_id,
                "image" => $other_user->get_avatar_url,
                "name"  => $other_user->get_title
            ),
            "message"      => "",
            "new_messages" => (int)$result->new_messages,
            "time"         => $result->last_date
        );

        if ($last_msg) {
            $item["message"] = removeUrls(strip_tags(encrypt_decrypt($last_msg->message, $last_msg->sender_id, 'decrypt')));
            $item["time"]    = $last_msg->created_at;
            $item["seen"]    = $last_msg->seen;
        }
        $output[] = $item;
    }
    return $output;
}

/**
 * Bir kullanıcıya gelen ancak henüz görülmemiş mesajları detaylı getirir.
 */
function yobro_get_unseen_by($conv_id = 0, $user_id = 0) {
    global $wpdb;
    $current_uid = get_current_user_id();

    $sql = $wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}yobro_messages 
        WHERE conv_id = %d AND reciever_id = %d AND seen IS NULL 
        ORDER BY created_at ASC
    ", $conv_id, $user_id);

    $items = $wpdb->get_results($sql, ARRAY_A);
    $timeAgo = new Westsworld\TimeAgo();

    foreach ($items as $key => $item) {
        $items[$key]["message"] = encrypt_decrypt($item["message"], $item["sender_id"], 'decrypt');
        
        // Kullanıcı nesneleri ve zaman ayarları
        $is_owner = ($item["sender_id"] == $current_uid);
        $items[$key]["owner"] = $is_owner ? 'true' : 'false';
        
        $user_obj = $is_owner ? Data::get("user") : new User($item["sender_id"]);
        $local_time = $user_obj->get_local_date($item["created_at"], "GMT", $user_obj->get_timezone());
        
        $items[$key]["pic"] = get_avatar($item["sender_id"], 32);
        $items[$key]["time"] = $timeAgo->inWordsFromStrings($local_time);
        $items[$key]["created_at"] = $items[$key]["time"];

        // Ekler (Attachment) kontrolü
        if (!empty($item["attachment_id"])) {
            $items[$key]["attachments"] = \YoBro\App\Attachment::where('id', '=', $item["attachment_id"])->first();
        }
    }
    return $items;
}



    /*
	# BEGIN WordPressRewriteEngine On
	RewriteBase /
	RewriteRule ^index.php$ – [L]
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule . /index.php [L]# END WordPress
	*/
	function add_custom_htaccess( $rules ){
		$folder = getSiteSubfolder();
		$rules = <<<EOF
		    <IfModule mod_rewrite.c>
					RewriteEngine On
					RewriteBase ${folder}
					RewriteRule ^index\.php$ - [L]
					RewriteRule ^list-data$ ${folder}\/ajax\/(.+?)\/?$ [QSA,L]
					RewriteCond %{REQUEST_FILENAME} !-f
					RewriteCond %{REQUEST_FILENAME} !-d
					RewriteRule . ${folder}index.php [L]
					RewriteCond %{REQUEST_METHOD} POST
					RewriteCond %{REQUEST_URI} ^${folder}wp-admin/
					RewriteCond %{QUERY_STRING} action=up_asset_upload
					RewriteRule (.*) ${folder}index.php?ajax=query&method=message_upload [L,R=307]
			</IfModule>
		EOF;
	   return $rules;
	}
	add_filter('mod_rewrite_rules', 'add_custom_htaccess');


//deregister some default style & scripts
function yobro_deregister_styles() {
	wp_deregister_style('font-awesome');
	wp_deregister_style('font-for-body');
    wp_deregister_style('font-for-new');
}
add_action( 'wp_print_styles', 'yobro_deregister_styles', 100 );



add_filter('yobro_before_store_new_message', 'before_store_new_message');

function before_store_new_message($message) {
    global $wpdb;
    
    $user = Data::get("user");
    $attributes = $message["attributes"];
    $msg_id = (int)$attributes['id'];

    // 1. Zamanı WordPress standartlarında GMT olarak alıyoruz
    $created_at_gmt = current_time('mysql', 1); 
    
    // 2. Konuşma verisini çekiyoruz (zaten hazırladığımız güvenli fonksiyonu kullanıyor)
    $conversation = yobro_get_conversation_row($attributes["conv_id"]);
    
    if (!$conversation) {
        return $attributes;
    }

    // Alıcıyı belirle
    $other_id = ($conversation->reciever == $user->ID) ? $conversation->sender : $conversation->reciever;

    // 3. Tek bir UPDATE sorgusu ile hem alıcıyı hem zamanı güncelliyoruz
    // wp_ prefix'i yerine dinamik prefix ve prepare süzgeci
    $wpdb->update(
        "{$wpdb->prefix}yobro_messages",
        array(
            'reciever_id' => (int)$other_id,
            'created_at'  => $created_at_gmt
        ),
        array('id' => $msg_id),
        array('%d', '%s'),
        array('%d')
    );

    // 4. TimeAgo formatlaması
    $timeAgo = new Westsworld\TimeAgo();
    $local_date = $user->get_local_date($created_at_gmt, "GMT", $user->get_timezone(), "Y-m-d H:i:s");
    
    $attributes["created_at"] = $timeAgo->inWordsFromStrings($local_date);
    
    return $attributes;
}

add_filter('yobro_after_store_message', 'after_store_new_message');
function after_store_new_message($message){
    $user = Data::get("user");
	$attributes = $message;

    $salt = Salt::get_instance();//new Salt();
    $reciever_is_online = $salt->user_is_online($attributes["reciever_id"]);
            
    if(!$reciever_is_online){
        $reciever = get_user_by( 'id', $attributes["reciever_id"] );
        $reciever = new User($reciever);
        $sender = get_user_by( 'id', $attributes["sender_id"] );
        $sender = new User($sender);
        $attrs = array(
            "conv_id"  => $attributes["conv_id"],
            "sender"   => $sender,
            "user"     => $reciever,
            "message"  => $attributes["message"]
        );
        $salt->notification($reciever->get_role()."/new-message", $attrs);                
    }
    return $attributes;
}




add_filter('yobro_automatic_pull_messages', 'yobro_pull_messages');
function yobro_pull_messages($messages){
	$conv_id = get_query_var("conversationId");
	if(empty($conv_id)){
		$conv_id = $_POST['conv_id'];
	}
	$user = Data::get("user");
	$messages_unseen = yobro_get_unseen_by($conv_id, $user->ID);
	$messages["new_unseen_messages"] = $messages_unseen;
	return $messages;
}



add_filter("yobro_conversation_messages", "yobro_grab_conversation_message");
function yobro_grab_conversation_message($messages=array()){
	if($messages){
			foreach($messages as $key => $message){
				$reciever_id = $message["reciever_id"];
				$sender_id = $message["sender_id"];
				$time = $message["time"];
				$created_at = $message["created_at"];
				$user = Data::get("user");
				$sender = new User($sender_id);
				$reciever = new User($reciever_id);
				if($message["owner"] == "true"){
				   $time = $sender->get_local_date($time, "GMT", "", "Y-m-d H:i:s");
				}else{
				   $time = $reciever->get_local_date($time, "GMT", "", "Y-m-d H:i:s");
				}$messages[$key]["created_at"] = $time;
				$timeAgo = new Westsworld\TimeAgo();
				$time = $timeAgo->inWordsFromStrings($time);
				$messages[$key]["time"] = $time;
				
			}
	}
	return $messages;
}


add_filter("yobro_message_deleted", "yobro_on_delete_message");

function yobro_on_delete_message($message_id) {
    global $wpdb;

    // 1. message_id'nin tam sayı olduğundan emin oluyoruz (Güvenlik)
    $message_id = (int)$message_id;
    if ($message_id <= 0) return;

    // 2. get_var kullanarak tek bir hücre (conv_id) çekiyoruz. 
    // Prepare ve dinamik prefix ile zırhlı hale getirdik.
    $conv_id = $wpdb->get_var($wpdb->prepare(
        "SELECT conv_id FROM {$wpdb->prefix}yobro_messages WHERE id = %d",
        $message_id
    ));

    // Buradan sonra $conv_id ile ne yapacaksan (Örn: konuşmayı güncelleme, log tutma vb.)
    // güvenle yapabilirsin abi.
    if ($conv_id) {
        // İşlemlerin...
    }
}
