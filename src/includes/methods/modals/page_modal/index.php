<?php
if (isset($vars["id"])) {
                if (!is_numeric($vars["id"])) {
                    switch ($vars["id"]) {
                        case "privacy-policy":
                            $post = get_post(get_option("wp_page_for_privacy_policy"));
                            break;
                        case "terms-conditions":
                            $post_id = ENABLE_COMMERCE? wc_terms_and_conditions_page_id() : get_option('wp_page_for_privacy_policy');
                            $post = get_post($post_id);
                            break;
                        default:
                            global $wpdb;
                            $post_id = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s AND post_status = 'publish'",
                                    $vars["id"],
                                    "page"
                                )
                            );
                            $post = get_post($post_id);
                            break;
                    }
                } else {
                    $post = get_post($vars["id"]);
                }
            }
            $error = true;
            $message = "Content not found";
            $post_data = [];
            if ($post) {
                $error = false;
                $message = "";
                $post_data = array();
                $post_content = "";

                //if(has_blocks($post->post_content)){
                    $post = Timber::get_post($post);
                    $post_content = $post->get_blocks();
                //}else{
                    //$post_content = $post->post_content;
                //}
                if(ENABLE_MULTILANGUAGE){
                    switch(ENABLE_MULTILANGUAGE){
                        case "qtranslate-xt" :
                            $post_data["title"] = qtranxf_use($lang, $post->post_title, false, false);
                            $post_data["content"] = qtranxf_use($lang, $post_content, false, false);//nl2br(qtranxf_use($lang, $post->post_content, false, false));
                        break;
                        case "polylang" :
                            $post_data["title"] = $post->title;
                            $post_data["content"] = $post_content;
                        break;
                        case "wpml" :
                            $post_data["title"] = $post->title;
                            $post_data["content"] = $post_content;
                        break;
                    }
                    
                }else{
                    $post_data["title"] = $post->post_title;
                    $post_data["content"] = $post_content;//nl2br($post->post_content);
                }
            }
            $output = [
                "error" => $error,
                "message" => $message,
                "data" => $post_data,
                "html" => "",
            ];
            echo json_encode($output);
            die();