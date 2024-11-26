<?php

// fixes
// get_users_all_conversation function on yobro-helper.php
// lines 69, 70


    //add required columns to conversation table
	$table_name = "wp_yobro_conversation";
	$columns = array(
		array(
			"table" => "wp_yobro_conversation",
			"name"  => "project_id",
			"type"  => "bigint(200) NOT NULL DEFAULT 0"
		),
		array(
			"table" => "wp_yobro_conversation",
			"name" => "product_id",
			"type" => "bigint(200) NOT NULL DEFAULT 0"
		),
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


class Messenger{

    public $user;
    public $sender_id;
    public $reciever_id;
    public $project_id;
    public $product_id;

    /*$args = array(
       "type" => "conversation", //message
       "action" => "remove", //"update", "add", "get"
       "sender_id" => 1,
       "reciever_id" => 2,
       "project_id" => 3,
       "product_id" => 4
    );*/

    function __construct($user=array()) {
    	if($user){
           $this->user = new User($user);
    	}else{
           $this->user = new User(wp_get_current_user());
    	}
    	if(!$reciever){
    		$this->reciever_id = $this->user->ID;
    	}
    }

    Private function response(){
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

    function init(){
    	add_action('mod_rewrite_rules', [ $this, 'rewrite_rules' ]);

    }

    function conversation($args = array()){
    	if($args){
    		switch($args["action"]){
    			case "remove" :

    			break;
    		}
    	}
    }


	function rewrite_rules( $rules ){
		/*
		# BEGIN WordPressRewriteEngine On
		RewriteBase /
		RewriteRule ^index.php$ – [L]
		RewriteCond %{REQUEST_FILENAME} !-f
		RewriteCond %{REQUEST_FILENAME} !-d
		RewriteRule . /index.php [L]# END WordPress
		*/
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

}