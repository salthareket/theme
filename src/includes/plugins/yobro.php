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
	$table_name = "wp_yobro_conversation";
	$columns = array(
		array(
			"table" => "wp_yobro_conversation",
			"name"  => "post_id",
			"type"  => "bigint(200) NOT NULL DEFAULT 0"
		),
		array(
			"table" => "wp_yobro_messages",
			"name" => "notification",
		    "type" => "tinytext COLLATE utf8mb4_unicode_520_ci"
		)
	);
	if($columns){
		global $wpdb;
		$database = $wpdb->dbname;;
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


	function yobro_notification_messages($action = ""){
		global $wpdb;
    	$yobro_settings = get_option('yo_bro_settings', true);
    	$chat_page_url = get_account_endpoint_url('messages');//get_account_endpoint_url('messages');
    	$user_id = get_current_user_id();
    	$user = new User($user_id);
    	$sql_action_query = "";
    	if(isset($action)){
    	   switch($action){
    	   	   case "seen":
                    $sql_action_query = " and messages.seen is null";
    	   	   break;
    	   	   case "notification":
                    $sql_action_query = " and messages.notification is null";
    	   	   break;
    	   }
    	}
    	$sql = "SELECT DISTINCT messages.id, messages.conv_id, messages.sender_id, messages.message, messages.created_at, conversation.post_id
					  FROM wp_yobro_messages messages
					  INNER JOIN wp_yobro_conversation conversation
					  ON messages.conv_id = conversation.id 
					  where messages.reciever_id=$user_id". $sql_action_query ."
					  order by messages.created_at ASC limit 1";
		$messages = $wpdb->get_results($sql);
	    $unseen_messages = array();
	    $timeAgo = new Westsworld\TimeAgo();
	    global $post;
	    foreach($messages as $message){
	    	$url = "?conversationId=".$message->conv_id."&action=error";
	    	$title = "";
	    	if(isset($message->post_id) && $message->post_id == 0){
		       $url = get_account_endpoint_url("messages").$message->conv_id."/chat/".$message->sender_id;
		       $title = "Message from Bot";
		    }
		    if(isset($message->post_id) && $message->post_id > 0){
		       $url = get_permalink($message->post_id)."#messages";
		       $title = get_the_title( $message->post_id );
		    }
		    $sender = new User($message->sender_id);//get_user_by("id", $message->sender_id);

	    	$item = array(
	    		"id"      => $message->conv_id,
	    		"type"    => "message",
	    		"title"   => $title,
                "sender"  => array(
                	             "id"    => $message->sender_id,
                	             "image" => get_avatar( $message->sender_id, 32, 'mystery', $sender->display_name),
                	             "name"  => $sender->display_name
                	         ),
                "message" => truncate(strip_tags(encrypt_decrypt($message->message, $message->sender_id, 'decrypt')), 150),
                "url"     => $url,
                "time"    => $timeAgo->inWordsFromStrings($user->get_local_date($message->created_at, "GMT", $user->get_timezone()))
	    	);
	    	if(function_exists("notification_update_map")){
	    		$update = notification_update_map($item);
	    		if($update){
	    			$item["update"] = $update;
	    		}
	    	}
	    	$unseen_messages[] = $item;
	    	$wpdb->query("UPDATE wp_yobro_messages SET notification=1 WHERE id=".$message->id);
	    }
	    return $unseen_messages ;
	}



	function yobro_unseen_messages_count($conv_id=0){
		global $wpdb;
		//"select count(*) from wp_yobro_messages where receiver_id=$user_id and not seen = 1"
        $user_id = get_current_user_id();
        $sql = "SELECT COUNT(DISTINCT conv_id) FROM wp_yobro_messages where reciever_id=$user_id and seen is null ".($conv_id>0?" and conv_id=$conv_id":"");
		return $wpdb->get_var($sql);
	}
	function yobro_unseen_post_messages_count($post_id=0){
		global $wpdb;
		//"select count(*) from wp_yobro_messages where receiver_id=$user_id and not seen = 1"
        $user_id = get_current_user_id();
        $sql = "SELECT COUNT(*) 
                   FROM wp_yobro_messages m
                   INNER JOIN wp_yobro_conversation AS c ON (m.conv_id = c.id)
                   where 
                          m.reciever_id=$user_id
                      and m.seen is null 
                      and c.post_id=$post_id";
		return $wpdb->get_var($sql);
	}
    function yobro_unseen_messages(){
    	global $wpdb;
    	$yobro_settings = get_option('yo_bro_settings', true);
    	$chat_page_url = get_account_endpoint_url('messages');//$yobro_settings['chat_page_url'];
    	$user_id = get_current_user_id();
    	$user = new User($user_id);
    	$sql = "SELECT DISTINCT messages.conv_id, messages.sender_id, messages.message, messages.created_at, conversation.post_id 
					  FROM wp_yobro_messages messages
					  INNER JOIN wp_yobro_conversation conversation
					  ON messages.conv_id = conversation.id 
					  where messages.reciever_id=$user_id and messages.seen is null
					  group by messages.conv_id
					  order by messages.created_at DESC,messages.id asc";
	    $messages = $wpdb->get_results($sql);
	    $unseen_messages = array();
	    $timeAgo = new Westsworld\TimeAgo();
	    foreach($messages as $message){
	    	$url_query = $message->conv_id."/chat/".$message->sender_id."/";

	    	$title = "";
		    if(isset($message->post_id) && $message->post_id > 0){
		       $url_query = $message->conv_id."&action=post&post_id=".$message->post_id;
		       $title = get_the_title( $message->project_id );
		    }
		    if(!isset($message->post_id)){
		       $url_query = $message->conv_id."&action=project&post_id=".$message->post_id;
		       $title = get_the_title( $message->post_id );
		    }
		    $sender = get_user_by("id", $message->sender_id);
	    	$unseen_messages[] = array(
	    		"id"      => $message->conv_id,
	    		"title"   => $title,
                "sender"  => array(
                	             "image" => get_avatar( $message->sender_id, 32, 'mystery', $sender->display_name),
                	             "name"  => $sender->display_name
                 ),
                "message" => removeUrls(strip_tags(encrypt_decrypt($message->message, $message->sender_id, 'decrypt'))),
                "url"     => $chat_page_url.$url_query,
                "time"    => $timeAgo->inWordsFromStrings($user->get_local_date($message->created_at, "GMT", $user->get_timezone()))
	    	);
	    }
	    return $unseen_messages ;
	}

	function yobro_messages(){
    	global $wpdb;
    	$yobro_settings = get_option('yo_bro_settings', true);
    	$chat_page_url = get_account_endpoint_url('messages');//$yobro_settings['chat_page_url'];
    	$user_id = get_current_user_id();
    	$user = $GLOBALS["user"];
		$sql = "SELECT messages.conv_id, messages.sender_id, messages.message, messages.created_at, conversation.post_id, messages.seen
				FROM wp_yobro_messages messages
				INNER JOIN wp_yobro_conversation conversation ON messages.conv_id = conversation.id
				WHERE messages.reciever_id = $user_id
				AND messages.id = (
				    SELECT MAX(id)
				    FROM wp_yobro_messages
				    WHERE conv_id = messages.conv_id and reciever_id = $user_id
				)
				ORDER BY messages.created_at DESC";
	    $messages = $wpdb->get_results($sql);
	    $unseen_messages = array();
	    $timeAgo = new Westsworld\TimeAgo();
	    foreach($messages as $message){
	    	$url = $chat_page_url.$message->conv_id."/chat/".$message->sender_id."/";
	    	$title = "";
		    if(isset($message->post_id) && $message->post_id > 0){
		       $url = get_permalink($message->post_id)."#messages";//$message->conv_id."&action=project&post_id=".$message->post_id;
		       $title = get_the_title( $message->post_id );
		    }
		    $sender = new User($message->sender_id);
	    	$unseen_messages[] = array(
	    		"id"      => $message->conv_id,
	    		"title"   => $title,
                "sender"  => array(
                	"image" => $sender->get_avatar(32),// get_avatar( $message->sender_id, 32, 'mystery', $sender->display_name),
                    "name"  => $sender->get_title()
                 ),
                "message" => removeUrls(strip_tags(encrypt_decrypt($message->message, $message->sender_id, 'decrypt'))),
                "url"     => $url,
                "time"    => $timeAgo->inWordsFromStrings($user->get_local_date($message->created_at, "GMT", $user->get_timezone())),
                "seen"    => $message->seen
	    	);
	    }
	    return $unseen_messages ;
	}



    function yobro_first_conversation($post_id, $sender_id, $reciever_id){
    	global $wpdb;
        $sql = "SELECT * FROM wp_yobro_conversation where reciever=$reciever_id and sender=$sender_id ". ($post_id > 0 ?"and post_id = $post_id":"")." order by created_at DESC limit 1";
		return $wpdb->get_results($sql);
    }
    function yobro_first_admin_conversation($post_id, $reciever_id){
    	global $wpdb;
    	$administrator = QueryCache::get_cached_option("site_admin");//get_field("site_admin", "option");
	    $admin_id =  $administrator["ID"];
        $sql = "SELECT * FROM wp_yobro_conversation where reciever=$reciever_id and sender=$admin_id and post_id = $post_id order by created_at DESC limit 1";
		return $wpdb->get_results($sql);
    }
    function yobro_has_admin_conversation($post_id, $reciever_id){
    	global $wpdb;
    	$administrator = QueryCache::get_cached_option("site_admin");//get_field("site_admin", "option");
	    $admin_id =  $administrator["ID"];
		$sql = "SELECT COUNT(DISTINCT id) FROM wp_yobro_conversation where reciever=$reciever_id and sender=$admin_id and post_id = $post_id order by created_at DESC limit 1";
		return $wpdb->get_var($sql);
    }
    
    function yobro_new_conversation($sender_id, $reciever_id, $message="", $post_id=0){
    	global $wpdb;
		$new_conversation =  \YoBro\App\Conversation::create(array(
	      'sender'   => $sender_id,
	      'reciever' => $reciever_id
	    ));
	    if($post_id > 0){
	    	$wpdb->query("UPDATE wp_yobro_conversation SET post_id=".$post_id." WHERE id=".$new_conversation['id']);
	    }
	    $created_at = new DateTime("now");
        $created_at->setTimezone(new DateTimeZone('GMT'));
        $created_at = $created_at->format("Y-m-d H:i:s");
        $wpdb->query("UPDATE wp_yobro_conversation SET created_at='".$created_at."' WHERE id=".$new_conversation['id']);

	    if(!empty($message)){
		    return  \YoBro\App\Message::create(array(
		      'conv_id' => $new_conversation['id'],
		      'sender_id' => $sender_id,
		      'reciever_id' => $reciever_id ,
		      'message' => encrypt_decrypt($message, $sender_id),
		      'attachment_id' => null ,
			  'created_at' => $created_at,
		    ));	    	
	    }else{
            return $new_conversation['id'];
	    }
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

    function yobro_check_conversation_exist($post_id=0, $sender_id=0, $reciever_id=0, $forced=false){
    	global $wpdb;
    	if($sender_id <= 0 || $reciever_id <= 0){
           return false;
    	}
    	if($sender_id == $reciever_id && $post_id > 0){
    		$where_user = " reciever=$sender_id or sender=$sender_id ";
    	}else{
            $where_user = "((reciever=$reciever_id and sender=$sender_id )".($forced?" or (reciever=$sender_id and sender=$reciever_id)":"").")";
    	}
        //$sql = "SELECT id FROM wp_yobro_conversation where ". $where_user . ($post_id > 0 ?"and post_id = $post_id":"")." order by id ASC limit 1";
        $sql = "SELECT id FROM wp_yobro_conversation where $where_user and post_id = ".($post_id > 0 ?"$post_id":"0")." order by id ASC limit 1";
		return $wpdb->get_var($sql);
    }

    function yobro_remove_conversation($sender, $reciever, $post_id){
    	global $wpdb;
    	if($sender){
    	    foreach($sender as $sender_id){
    	   	    $conv_id = $wpdb->get_var("select id from wp_yobro_conversation where sender=".$sender_id." and reciever=".$reciever." and post_id=".$post_id);
    	   	    yobro_remove_conversation_by_id($conv_id);
    	    }
    	}
    }
    function yobro_remove_conversation_by_post($post_id){
    	global $wpdb;
    	$conversations = $wpdb->get_results("select id from wp_yobro_conversation where post_id=".$post_id);
    	if($conversations){
    	   $conversations = wp_list_pluck($conversations, "id");
    	   foreach($conversations as $conversation){
    	   	  yobro_remove_conversation_by_id($conversation);
    	   }
    	}
    }
    function yobro_remove_conversation_by_user($user_id){
    	global $wpdb;
    	$conversations = $wpdb->get_results("select id from wp_yobro_conversation where sender=$user_id or reciever=$user_id");
    	if($conversations){
    	   $conversations = wp_list_pluck($conversations, "id");
    	   foreach($conversations as $conversation){
    	   	  yobro_remove_conversation_by_id($conversation);
    	   }
    	}
    }
    function yobro_remove_conversation_by_id($conv_id){
    	global $wpdb;
    	if($conv_id > 0){
    	   	$wpdb->delete( "wp_yobro_messages", array( 'conv_id' => $conv_id ) );
    	   	$wpdb->delete( "wp_yobro_conversation", array( 'id' => $conv_id ) );  	   	  	
    	}
    }



    function yobro_has_reciever_conservations($user_id=0){
		global $wpdb;
		if($user_id == 0){
		   $user_id = get_current_user_id();
		}
        $sql = "SELECT COUNT(DISTINCT conv_id) FROM wp_yobro_messages where reciever_id=$user_id";
		return $wpdb->get_var($sql);
	}
	function yobro_get_conversation_row($conv_id=0){
    	global $wpdb;
    	$sql = "SELECT DISTINCT *
					  FROM wp_yobro_conversation 
					  where id = ".$conv_id;
		return $wpdb->get_row($sql);
    }

	function yobro_get_conversation($conv_id=0){
    	global $wpdb;
    	$sql = "SELECT DISTINCT messages.id, messages.conv_id, messages.sender_id, messages.message, messages.created_at
					  FROM wp_yobro_messages messages
					  where messages.conv_id = $conv_id
					  order by messages.created_at ASC";
		return $wpdb->get_results($sql);
    }

    function yobro_get_conversation_data($conv_id=0){
    	global $wpdb;
    	$sql = "SELECT DISTINCT *
					  FROM wp_yobro_conversation
					  where id = $conv_id limit 1";
		return $wpdb->get_results($sql);
    }

    function yobro_get_reciever_conversations($user_id){
    	global $wpdb;
    	$sql = "Select c.id as conversation_id, t.post_title as title, u.display_name as agent from wp_yobro_conversation c, wp_posts t, wp_users u where t.post_type='project' and c.reciever=".$user_id." and c.post_id = t.ID and c.sender = u.ID";
    	return $wpdb->get_results($sql);
    }
    function yobro_get_sender_conversations($user_id){
    	global $wpdb;
    	$sql = "Select c.id as conversation_id, t.post_title as title, u.display_name as agent from wp_yobro_conversation c, wp_posts t, wp_users u where t.post_type='project' and c.sender=".$user_id." and c.post_id = t.ID and c.sender = u.ID";
    	return $wpdb->get_results($sql);
    }
    function yobro_get_all_conversations($user_id){
    	global $wpdb;
    	$sql = "Select c.id as conversation_id, t.post_title as title from wp_yobro_conversation c, wp_posts t, wp_users u where t.post_type='project' and (c.sender=".$user_id." or c.reciever=".$user_id.") and c.post_id = t.ID and c.sender = u.ID";
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

    
    function yobro_get_conversation_last_message($conv_id=0, $sender_id=0){
    	global $wpdb;
    	$sql = "SELECT DISTINCT messages.id, messages.conv_id, messages.sender_id, messages.message, messages.created_at, messages.seen
					  FROM wp_yobro_messages messages
					  where messages.conv_id = $conv_id ".
					  ($sender_id>0?" and sender_id=".$sender_id:"").
					  " order by messages.created_at DESC limit 1";
		$results = $wpdb->get_results($sql);
		if(count($results)>0){
			$results=$results[0];
		}
		return $results;
    } 
	function yobro_get_post_conversations($post_id=0, $user_id=0){
		global $wpdb;
	    $sql = "SELECT DISTINCT messages.created_at, messages.conv_id, conversation.post_id, conversation.sender, conversation.reciever, (count(messages.id) - count(messages.seen)) as new_messages, messages.message, messages.created_at, messages.seen
					FROM 
						wp_yobro_messages messages 
					INNER JOIN 
						wp_yobro_conversation conversation 
					ON 
						messages.conv_id = conversation.id 
					where 
						(conversation.reciever=$user_id or conversation.sender=$user_id) ".($post_id>0?" and conversation.post_id=$post_id ":"").
					"group by 
						messages.conv_id 
					order by new_messages DESC, messages.created_at DESC";
		$results =  $wpdb->get_results($sql);
        $output = array();
		foreach($results as $result){
			$sender_id = $user_id==$result->sender?$result->reciever:$result->sender;
			$sender = new User($sender_id);
			$message = yobro_get_conversation_last_message($result->conv_id);
            $item = array(
	    		"id"      => $result->conv_id,
	    		"post_id"      => $result->post_id,
                "sender"  => array(
                	             "id"    => $sender_id,
                	             "image" => $sender->get_avatar_url,
                	             "name"  => $sender->get_title
                ),
                "message" => "",
                //"url"     => $chat_page_url.$url_query,
                "new_messages" => 0,
                "time"    => $result->created_at
	    	);
	    	if($message){
	    		/*$item["sender"] = array(
                	             "id"    => $message->sender_id,
                	             "image" => $sender->get_avatar_url,
                	             "name"  => $sender->get_title
                );*/
                $item["message"] = removeUrls(strip_tags(encrypt_decrypt($message->message, $message->sender_id, 'decrypt')));
                $item["new_messages"] = $result->new_messages;
                $item["time"] = $message->created_at;
                $item["seen"] = $message->seen;
	    	}
            $output[] = $item;
		}
		return $output;	
	}

	function yobro_get_unseen_by($conv_id = 0, $user_id = 0){
		global $wpdb;
    	$sql = "SELECT DISTINCT *
				FROM wp_yobro_messages 
				where conv_id =". $conv_id ." and reciever_id = ".$user_id." and seen is null order by created_at asc";
		$items = $wpdb->get_results($sql, ARRAY_A);
        foreach($items as $key => $item){
		        $items[$key]["message"] = encrypt_decrypt($item["message"], $item["sender_id"], 'decrypt');
				if ($item["sender_id"] == get_current_user_id()) {
					$items[$key]["owner"] = 'true';
					$sender = $GLOBALS["user"];
					$reciever = new User($item["reciever_id"]);
					$time = $sender->get_local_date($item["created_at"],  "GMT", $sender->get_timezone());
				} else {
					$items[$key]["owner"] = 'false';
					$sender = new User($item["reciever_id"]);
					$reciever = $GLOBALS["user"];
					$time = $sender->get_local_date($item["created_at"], "GMT", $sender->get_timezone());
				}
				if (get_avatar($item["sender_id"])) {
					$items[$key]["pic"] =  get_avatar($item["sender_id"]);
				} else {
					$items[$key]["pic"] =  get_avatar($item["sender_id"]);
				}

				if (isset($item["attachment_id"]) && $item["attachment_id"] != null) {
					$items[$key]["attachments"] = YoBro\App\Attachment::where('id', '=', $item["attachment_id"])->first();
				}
				$items[$key]["reciever_name"] = get_user_name_by_id($item["reciever_id"]) ?  get_user_name_by_id($item["reciever_id"]) : 'Untitled';
				$items[$key]["sender_name"] = get_user_name_by_id($item["sender_id"]) ?  get_user_name_by_id($item["sender_id"]) : 'Untitled';
				$timeAgo = new Westsworld\TimeAgo();
				$time = $timeAgo->inWordsFromStrings($time);
				$items[$key]["created_at"] = $time;	
				$items[$key]["time"] = $time;	
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
function before_store_new_message($message){
	    $user = $GLOBALS["user"];
    	$attributes = $message["attributes"];

    	$created_at = new DateTime("now");
        $created_at->setTimezone(new DateTimeZone('GMT'));
        $created_at = $created_at->format("Y-m-d H:i:s");
        
    	$conversation = yobro_get_conversation_row($attributes["conv_id"]);
        if($conversation->reciever == $user->ID){
        	$other_id = $conversation->sender;
        }else{
        	$other_id = $conversation->reciever;
        }
        global $wpdb;
        $wpdb->query("UPDATE wp_yobro_messages SET reciever_id=".$other_id." WHERE id=".$attributes['id']);
        $wpdb->query("UPDATE wp_yobro_messages SET created_at='".$created_at."' WHERE id=".$attributes['id']);
    	//$this->send_mail($post_id, "on_sent_new_message", 0, $attrs);

    	$timeAgo = new Westsworld\TimeAgo();
    	$created_at = $user->get_local_date($created_at, "GMT", $user->get_timezone(), "Y-m-d H:i:s");
    	$created_at = $timeAgo->inWordsFromStrings($created_at);
	    $attributes["created_at"] = $created_at;
    	return $attributes;
}

add_filter('yobro_after_store_message', 'after_store_new_message');
function after_store_new_message($message){
    $user = $GLOBALS["user"];
	$attributes = $message;

    $salt = new Salt();
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
	$user = $GLOBALS["user"];
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
				$user = $GLOBALS["user"];
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
function yobro_on_delete_message($message_id){
	global $wpdb;
	$conv_id = $wpdb->get_results("select conv_id from wp_yobro_messages where id=".$message_id);
}

