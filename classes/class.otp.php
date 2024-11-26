<?php

//https://rapidapi.com/

class Sms{
    private $originator;
    private $version;
    private $app_keys;
    private $method;
    private $url;
    private $content_type;
    private $vars;
    private $user_id;
    
    //only sms
    private $request_id;
    private $msg_type;
    private $channel; 
    private $recipients;
    private $schedule_time;
    private $schedule_utc_offset;
    /*
    
    private $recipient;
    private $content;
    private $otp_id;
    private $code;
    */
    private $expiry;
    private $data_coding;
    private $retry_count;
    private $retry_delay;
    private $otp_code_length;
    private $otp_type;


    public function __construct($vars=array()){
        $this->version = "v1";
        $this->app_keys = SMS_APP;
        $this->method      =  "POST";
        $this->content_type = "application/json";
        $this->expiry =  600;
        $this->data_coding =  'text';
        $this->originator = SMS_APP["originator"];

        $this->retry_count = 5;
        $this->retry_delay = 60;
        $this->otp_code_length = 6;
        $this->otp_type = "numeric";

        $this->msg_type = "text";
        $this->channel = "sms";
        /*
        $this->recipient = $recipient;
        $this->content = $content;
        $this->otp_id = $otp_id;
        $this->otp_code = $otp_code;*/

        if (!is_array($vars)) {
            $vars = array();
        }else{
            $this->vars = $vars;
            foreach ($vars as $key => $value) {
                $this->__set($key, $value);
            }            
        }
    }

    public function __set($name, $value) {
        $this->$name = $value;
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
        );
    }

    public function url($type="", $method=""){
        $url = $this->app_keys["url"];
        $url = str_replace("{type}", $type, $url);
        $url = str_replace("{version}", $this->version, $url);
        $this->url = str_replace("{method}", $method, $url);
    }
    

    // SMS

    public function message($schedule_time="", $schedule_utc_offset=""){
        if(!empty($schedule_time)){
           $this->schedule_time = $schedule_time;
        }
        if(!empty($schedule_utc_offset)){
           $this->schedule_utc_offset = $schedule_utc_offset;
        }
        $this->url("messages", "send");
        $params = array(
           "channel", // sms, WhatsApp, Viber, Telegram
           "originator",
           "recipients",
           "content",
           "data_coding",
           "report_url",
           "msg_type" // txt, audio sms, multimedia, image
        );
        return $this->request("message", $params);
        //response : { otp_id:"8d963dbf-d655-4fe6-9157-48885a036050", status:"OPEN", expiry:600}
    }
    public function message_status(){
        $this->url("message-log", $this->request_id);
        $this->content_type = "";
        $this->method =  "GET";
        return $this->request("message_status");
        //response: {status : "OPEN"}
    }



    // OTP

    public function generate(){
        $this->url("verify", "otp/send-otp");
        $params = array(
           "originator",
           "recipient",
           "content",
           "expiry",
           "data_coding"
        );
        return $this->request("generate", $params);
        //response : { otp_id:"8d963dbf-d655-4fe6-9157-48885a036050", status:"OPEN", expiry:600}
    }

    public function resend(){
        $this->url("verify", "otp/resend-otp");
        $params = array(
           "otp_id"
        );
        return $this->request("resend", $params);
        //response : { otp_id:"8d963dbf-d655-4fe6-9157-48885a036050", status:"OPEN", expiry:600, resend_count:1}
    }

    public function verify(){
        $this->url("verify", "otp/verify-otp");
        $params = array(
           "otp_id",
           "otp_code"
        );
        return $this->request("verify", $params);
        //response: {status : "APPROVED"}
    }

    public function otp_status(){
        $this->url("verify", "report/".$this->otp_id);
        $this->content_type = "";
        $this->method =  "GET";
        return $this->request("otp_status");
        //response: {status : "OPEN"}
    }

    public function check_balance(){
        $this->url("messages", "balance");
        $this->content_type = "";
        $this->method =  "GET";
        return $this->request("check_balance");
        //response: { balance:99.9999999 }
    }

    private function request($type="", $params=array()){
        $response = $this->response();
        $url = $this->url;
        $args = array(
            'headers' => array(
                //'Token'  => $this->app_keys["Token"],
                //'X-RapidAPI-Key' => $this->app_keys["X-RapidAPI-Key"],
                //'X-RapidAPI-Host' => $this->app_keys["X-RapidAPI-Host"],
                'Authorization' => 'Bearer ' . $this->app_keys["Token"]
            ),
        );
        if(!empty($this->content_type)){
            $args["headers"]["Content-Type"] = $this->content_type;
        }
        $args["method"] = $this->method;
        $data = array();
        if($params){

            if($type == "message"){

                $data["messages"] = array();
                foreach($params as $param){
                    if(isset($this->vars[$param])){
                        $data["messages"][$param] = $this->vars[$param];
                    }else{
                        if(isset($this->{$param})){
                            $data["messages"][$param] = $this->{$param};
                        }
                    }
                }
                unset($data["messages"]["channel"]);
                unset($data["messages"]["originator"]);
                $data["messages"] = array($data["messages"]);

                $data["message_globals"] = array(
                    "channel"    => $this->channel,
                    "originator" => $this->originator
                );
                if(!empty($this->schedule_time)){
                    $data["message_globals"]["schedule_time"] = $this->schedule_time;
                    if(!empty($this->schedule_utc_offset)){
                        $data["message_globals"]["schedule_utc_offset"] = $this->schedule_utc_offset;
                    }
                    if(isset($this->report_url)){
                        $data["message_globals"]["report_url"] = $this->report_url;
                    }
                }

            }else{

                foreach($params as $param){
                    if(isset($this->vars[$param])){
                        $data[$param] = $this->vars[$param];
                    }else{
                        if(isset($this->{$param})){
                           $data[$param] = $this->{$param};
                        }
                    }
                } 
                  
            }

            if($data){
                $args["body"] = json_encode($data);
            }
        }


        if($this->method == "GET"){
            $result = wp_remote_get( $url, $args );            
        }else{
            $result = wp_remote_post( $url, $args ); 
        }

        if ( is_wp_error( $result ) ) {
            $response["error"] = true;
            $response["message"] = $result->get_error_message();
        }else{
            $body = wp_remote_retrieve_body( $result );
            $result_code = intval(wp_remote_retrieve_response_code($result));
            $data = json_decode( $body, true);

            
             /*//if($type=="message"){
                print_r($args);
                print_r($data);
                print_r("type=".$type);
                print_r("code=".$result_code);
            //}
           */  

            switch($result_code){

                case 200:
                    switch($type){

                        case "message" :
                            $recipient_count = count($this->recipients);
                            $text = pluralize($recipient_count, "recipient", "recipients", "", "saran");
                            if(!empty($this->schedule_time)){
                                $response["message"] = $this->channel." message will send to ".$text." in ".$this->schedule_time;
                            }else{
                                $response["message"] = $this->channel." message has been sent to ".$text;
                            }
                        break;

                        case "generate" :
                            switch($data["status"]){
                                case "OPEN":
                                    if(isset($this->user_id)){
                                        $expiry_date = gmdate('Y-m-d H:i:s', gmdate('U')+$this->expiry);
                                        update_user_meta($this->user_id, 'otp_expiry', $expiry_date);
                                        update_user_meta($this->user_id, 'otp_id', $data["otp_id"]);
                                        update_user_meta($this->user_id, 'otp_resend_count', 0);
                                    }
                                    $response["message"] = "OTP code has been sent to ".masked_text($this->recipient);
                                break;
                                case "FAILED" :
                                    if(isset($this->user_id)){
                                        $expiry_date = gmdate('Y-m-d H:i:s', gmdate('U')+$this->expiry);
                                        update_user_meta($this->user_id, 'otp_expiry', $expiry_date);
                                        update_user_meta($this->user_id, 'otp_id', $data["otp_id"]);
                                        update_user_meta($this->user_id, 'otp_resend_count', 0);
                                    }
                                    $response["error"] = true;
                                    $response["message"] = "OTP code has not been send to ".masked_text($this->recipient);
                                break; 
                            }
                        break;

                        case "verify":
                            switch($data["status"]){
                                case "APPROVED" :
                                    $response["message"] = "Code verified!";
                                    $response["refresh"] = true;
                                break;
                                case "EXPIRED" :
                                    $response["error"] = true;
                                    $response["message"] = "OTP code is expired, please get a new one.";
                                break;
                            }
                        break;

                        case "resend" :
                            $user = new User($this->user_id);
                            $phone = $user->get_phone();
                            switch($data["status"]){
                                case "OPEN" :
                                    $response["message"] = "A new OTP code has been sent to ".masked_text($phone);
                                    $expiry_date = gmdate('Y-m-d H:i:s', gmdate('U')+$this->expiry);
                                    update_user_meta($this->user_id, 'otp_expiry', $expiry_date);
                                    update_user_meta($this->user_id, 'otp_id', $data["otp_id"]);
                                    update_user_meta($this->user_id, 'otp_resend_count', $data["resend_count"]);
                                    $data["otp_expiry"] = $user->get_local_date($expiry_date, 'GMT', $user->get_timezone(), "Y-m-d H:i:s");
                                    if($data["resend_count"] >= $this->retry_count){
                                       $url = '<a href="#" class="btn-link fw-bold" data-ajax-method="change_activation_method" data-user_id="'.$this->user_id.'" data-activation_method="email" data-bs-dismiss="modal">click here</a>';
                                       $response["message"] = "Your maximum resend limit is ".$this->retry_count.".<br>Please ".$url." to activate your account by email.";
                                    }
                                break;
                                case "FAILED" :
                                    $response["error"] = true;
                                    $response["message"] = "New OTP code has not been send to ".masked_text($phone);
                                    update_user_meta($this->user_id, 'otp_resend_count', $data["resend_count"]);
                                break;                              
                            }
                        break;

                        case "otp_status" :
                        break;

                        case "check_balance" :
                        break;
                    }
                    $response["data"] = $data;
                break;

                case 400:
                    $response["error"] = true;
                    if(isset($data["detail"]["code"])){
                        $response["data"] = $data["detail"]["code"];
                        $response["message"] = $data["detail"]["message"];
                    }else{
                        $response["message"] = $data["detail"];
                        if($type == "resend"){
                            if(strpos($data["detail"], "Resend Failed") !== false){
                                $response["data"] = ["status" => "EXPIRED"];
                            }
                        }
                    }
                break;

                case 401:
                    $response["error"] = true;
                    $response["data"] = $data["detail"]["code"];
                    $response["message"] = $data["detail"]["message"];
                break;

                case 422 :
                    $response["error"] = true;
                    $response["data"] = $data["detail"][0]["type"];
                    $response["message"] = $data["detail"][0]["msg"];
                break;

            }           
        }
        return $response;
    }
}


if(!(defined('DOING_AJAX') && DOING_AJAX)){
    add_action("init", function(){
     /*  $vars = array(
          "recipient" => "+905353584348",
          "content"   => "Your otp code is {}"
        );
        $otp = new Sms($vars);
        print_r($otp->generate());

        $vars = array(
          "recipients" => array("+905353584348", "+905353584322"),
          "content"   => "Hallo leute!..."
        );
        $otp = new Sms($vars);
        print_r($otp->message("2023-07-04 22:00"));*/
    });
}