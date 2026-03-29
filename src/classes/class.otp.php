<?php

//https://rapidapi.com/

$phoneValidator_keys = array(
    "url"             => "https://phonenumbervalidatefree.p.rapidapi.com/ts_PhoneNumberValidateTest.jsp?number={phone}&country={country}",
    'X-RapidAPI-Key'  => '63ddf56134msh2bc4d8aecd6dae8p111410jsne8cc18da82a5',
    'X-RapidAPI-Host' => 'phonenumbervalidatefree.p.rapidapi.com'
);
define('PHONE_VALIDATOR_KEYS', $phoneValidator_keys);


// https://app.d7networks.com/sms/api-report
//https://github.com/d7networks/D7API-Sample-Codes
// sender_id registration requirement list by country : https://25428574.fs1.hubspotusercontent-eu1.net/hubfs/25428574/International%20SenderID%20Registration%20Checklist.pdf
$sms_app_keys = array(
    "originator"      => "Salthareket",//"SignOTP",
    "url"             => "https://api.d7networks.com/{type}/{version}/{method}",//"https://d7-verify.p.rapidapi.com/{type}/{version}/{method}",
    "Token"           => "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJhdXRoLWJhY2tlbmQ6YXBwIiwic3ViIjoiYzE2MTM2YzgtYzMyNS00YWRlLTg4NTMtYjE5MDVhZTFhYTkxIn0.hWFAglKh6rba1j8jF6Jgo07imnI7p-HHU4ld0-gl_Jo",
    //"X-RapidAPI-Key"  => "63ddf56134msh2bc4d8aecd6dae8p111410jsne8cc18da82a5",
    //'X-RapidAPI-Host' => 'd7-verify.p.rapidapi.com'
);
define('SMS_APP', $sms_app_keys);

/**
 * SMS & OTP Service
 * D7 Networks API integration for SMS messaging and OTP verification.
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $sms = new Sms(['recipients' => ['+905551234567'], 'content' => 'Hello!']);
 * $result = $sms->message();                    // Send SMS
 * $result = $sms->message('2025-07-04 22:00');  // Scheduled SMS
 *
 * $sms = new Sms(['recipient' => '+905551234567', 'content' => 'Code: {}', 'user_id' => 5]);
 * $result = $sms->generate();                   // Generate OTP
 *
 * $sms = new Sms(['otp_id' => 'abc-123', 'otp_code' => '123456']);
 * $result = $sms->verify();                     // Verify OTP
 *
 * $sms = new Sms(['otp_id' => 'abc-123', 'user_id' => 5]);
 * $result = $sms->resend();                     // Resend OTP
 *
 * $sms = new Sms();
 * $result = $sms->check_balance();              // Check balance
 *
 * ──────────────────────────────────────────────────────────
 */
class Sms {

    private string $originator = '';
    private string $version = 'v1';
    private array $app_keys = [];
    private string $method = 'POST';
    private string $url = '';
    private string $content_type = 'application/json';
    private array $vars = [];
    private string $msg_type = 'text';
    private string $channel = 'sms';
    private string $data_coding = 'text';
    private int $expiry = 600;
    private int $retry_count = 5;
    private int $retry_delay = 60;
    private int $otp_code_length = 6;
    private string $otp_type = 'numeric';

    // Dynamic properties (set via $vars)
    private $user_id = 0;
    private $recipient = '';
    private $recipients = [];
    private $content = '';
    private $otp_id = '';
    private $otp_code = '';
    private $request_id = '';
    private $schedule_time = '';
    private $schedule_utc_offset = '';
    private $report_url = '';

    private static array $allowed_vars = [
        'user_id', 'recipient', 'recipients', 'content',
        'otp_id', 'otp_code', 'request_id', 'report_url',
        'schedule_time', 'schedule_utc_offset',
        'channel', 'msg_type', 'data_coding', 'expiry',
    ];

    public function __construct(array $vars = []) {
        if (!defined('SMS_APP')) return;

        $this->app_keys = SMS_APP;
        $this->originator = $this->app_keys['originator'] ?? '';
        $this->vars = $vars;

        foreach ($vars as $key => $value) {
            if (in_array($key, self::$allowed_vars, true)) {
                $this->$key = $value;
            }
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
                        $response["message"] = $data["detail"]["message"] ?? 'Bad request';
                    }else{
                        $response["message"] = $data["detail"] ?? 'Unknown error';
                        if($type == "resend"){
                            if(is_string($response["message"]) && strpos($response["message"], "Resend Failed") !== false){
                                $response["data"] = ["status" => "EXPIRED"];
                            }
                        }
                    }
                break;

                case 401:
                    $response["error"] = true;
                    $response["data"] = $data["detail"]["code"] ?? 'unauthorized';
                    $response["message"] = $data["detail"]["message"] ?? 'Unauthorized';
                break;

                case 422 :
                    $response["error"] = true;
                    $response["data"] = $data["detail"][0]["type"] ?? 'validation_error';
                    $response["message"] = $data["detail"][0]["msg"] ?? 'Validation error';
                break;

            }           
        }
        return $response;
    }
}
