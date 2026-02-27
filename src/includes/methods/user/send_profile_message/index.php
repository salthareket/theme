<?php
$required_setting = ENABLE_MEMBERSHIP;

            $sender_id = get_current_user_id();
            $reciever_id = $vars["id"];
            $post_id = isset($vars["post_id"])?$vars["post_id"]:0;
            $message = $vars["message"];
            if($message){
                $conv_id = yobro_check_conversation_exist($post_id, $sender_id, $reciever_id, true);
                if($conv_id){
                    $conversation = yobro_send_message($conv_id, $sender_id, $reciever_id, $message);
                }else{
                    $conversation = yobro_new_conversation($sender_id, $reciever_id, $message, $post_id);
                }
                if($post_id){
                    $url = get_permalink($post_id);
                }else{
                    $url = Data::get("base_urls.messages");//$GLOBALS['base_urls']["messages"];
                    $url .= $conversation->conv_id."/chat/".$reciever_id."/";                     
                }
                if(is_true($vars["static"])){
                    $response["message"] = "Your message has been sent!";
                    $response["description"] = "<a href='".$url."' target='_blank'>View your conversation</a>";
                }else{
                    if($post_id){
                        $response["redirect"] = $url."?conversationId=".$conversation->conv_id."#messages";
                        /*$context = Timber::context();
                        $context["id"] = $reciever_id;
                        $response["html"] = Timber::compile("partials/messages/chat.twig", $context);*/
                    }else{
                        $response["redirect"] = $url; 
                    }
                }
                //$GLOBALS["salt"]->after_store_new_message($conversation);
                $conversation = before_store_new_message($conversation);
                after_store_new_message($conversation);
            }else{
                $response["error"] = true;
                $response["message"] = "Please write a message";
            }
            echo json_encode($response);
            die();