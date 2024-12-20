<?php

//namespace YoBro\App;
use YoBro\App\Message;
use YoBro\App\Attachment;

/*
header('Content-Type: text/html');
send_nosniff_header();

//Disable caching
header('Cache-Control: no-cache');
header('Pragma: no-cache');
*/

$unique = "ajax";

add_action($unique . "_query", "query", 10, 1);
add_action($unique . "_nopriv_query", "query", 10, 1);

function no_data($data){
    echo json_encode([]);
}

function ajax_disable_plugins($plugins){

    /* load all plugins if not in ajax mode */
    if ( !defined( 'DOING_AJAX' ) )  {
        return $plugins;
    }

    /* load all plugins if fast_ajax is set to false */
    if ( !isset($_REQUEST['fast_ajax']) || !$_REQUEST['fast_ajax'] )  {
        return $plugins;
    }

    /* disable all plugins if none are told to load by the load_plugins array */
    if ( !isset($_REQUEST['load_plugins']) || !$_REQUEST['load_plugins'] )  {
        return array();
    }

    /* convert json */
    if (!is_array($_REQUEST['load_plugins']) && $_REQUEST['load_plugins']) {
        $_REQUEST['load_plugins'] = json_decode($_REQUEST['load_plugins'],true);
    }

    /* unset plugins not included in the load_plugins array */
    foreach ($plugins as $key => $plugin_path) {

        if (!in_array($plugin_path, $_REQUEST['load_plugins'] )) {
            unset($plugins[$key]);
        }

    }

    return $plugins;
}
//define('FAST_AJAX' , true );
//    add_filter( 'option_active_plugins', 'ajax_disable_plugins' );


function ajax_security($data){
    $response = [
        "error" => false,
        "message" => "",
        "data" => "",
        "resubmit" => false,
        "redirect" => "",
        "redirect_blank" => false,
        "html" => "",
    ];
    if(!isset($data['_wpnonce'])){
        $nonce = isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
        if(empty($nonce)){
           $response["error"] = true;
           $response["message"] = 'Security reason...';
           echo(json_encode($response));
           die;
        }        
    }else{
        $nonce = $data['_wpnonce'];
    }
    $nonce = wp_verify_nonce( $nonce, 'ajax' );
    switch ( $nonce ) {
        case 1:
            //echo 'Nonce is less than 12 hours old';
            break;
        case 2:
            //echo 'Nonce is between 12 and 24 hours old';
            break;
        default:
            $response["error"] = true;
            $response["error_type"] = "nonce";
            $response["message"] = 'Page is expired. Please refresh.';
            echo(json_encode($response));
            exit;
    }
}

function query($data){

    if (!is_iterable($data)) {
        exit();
    }

    if($data["method"] != "message_upload" && $data["method"] != "site_config"){
       ajax_security($data);        
    }

    $lang = strtolower( substr( get_locale(), 0, 2 ) );
    if (function_exists("qtranxf_getSortedLanguages")) {
        $lang = qtranxf_getLanguage();
    }

    $method = isset($data["method"]) ? $data["method"] : "";
    $terms = isset($data["terms"]) ? $data["terms"] : "";
    //$template = isset($data["template"]) ? $data["template"] : "ajax/archive";
    $id = isset($data["id"]) ? $data["id"] : "";
    $vars = isset($data["vars"]) ? $data["vars"] : $data;
    $keyword = trim(isset($data["keyword"]) ? $data["keyword"] : "");
    $template = isset($vars["template"]) ? $vars["template"] : "";
    //$page = isset($data["page"]) ? $data["page"] : 0;
    /*$count = isset($data["count"]) ? $data["count"] : 10;
		 $term = isset($data["term"]) ? $data["term"] : "category";

		 $pagination=isset($data["pagination"])? ($data["pagination"]=== 'true'? true: false) : false;*/

    /*if(isset($_SERVER['HTTP_REFERER'])){
			 $_SERVER['REQUEST_URI'] = str_replace('http://'.$_SERVER['HTTP_HOST'],'',$_SERVER['HTTP_REFERER']);
			 $url_parts=explode('?method=',$_SERVER['REQUEST_URI']);
			 $_SERVER['REQUEST_URI'] = $url_parts[0];
			 if($method=='author-most-readed' || $method=='most-readed'){
				 $_SERVER['REQUEST_URI'].='?method='.$method;
			 }                   
		}*/

    ///add_action( 'wp_ajax_'.$method, 'wpdocs_action_function' );

         //print_r(check_ajax_referer( $method."-security", 'method' ));

    if (isset($data["upload"])) {
        //$vars=$data;
    }

    

    //print_r($vars);

    if ($vars) {
        foreach ($vars as $key => $var) {
            if (!isset($var)) {
                $vars[$key] = "";
            }
        }
    }


    if (isset($vars["lang"])) {
        $lang = $vars["lang"];
    }

    $error = false;
    $message = "";
    $redirect_url = "";
    //$data = "";
    $html = "";

    $response = [
        "error" => false,
        "message" => "",
        "data" => "",
        "resubmit" => false,
        "redirect" => "",
        "html" => "",
    ];

    $output = [];

    include_once SH_INCLUDES_PATH . "methods/index.php";

    if (isset($template)) {
        $context["ajax_call"] = true;
        $context["ajax_method"] = $method;
        if (isset($templates)) {
            $data["html"] = Timber::compile($templates, $context);
        }
        if (isset($page)) {
            $data["page"] = $page;
        }
        if (isset($page_count)) {
            $data["page_count"] = $page_count;
        }
        if (isset($post_count)) {
            $data["post_count"] = $post_count;
        }
        if (isset($query_vars)) {
            $data["query_vars"] = $query_vars;
        }
        echo json_encode($data);
        /*Timber::render( $templates, $context );*/
    }
}