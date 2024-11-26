<?php
$required_setting = ENABLE_MEMBERSHIP;

$user = new WP_User(get_current_user_id());
            $files = $_FILES["profile_photo_main"];
            if (!empty($files) && $user->ID > 0) {
                $attachments = [];
                foreach ($files["name"] as $key => $value) {
                    if ($files["name"][$key]) {
                        $file = [
                            "name" => $files["name"][$key],
                            "type" => $files["type"][$key],
                            "tmp_name" => $files["tmp_name"][$key],
                            "error" => $files["error"][$key],
                            "size" => $files["size"][$key],
                        ];
                        $attachments[] = $file;
                    }
                }

                foreach ($attachments as $file) {
                    if (is_uploaded_file($file["tmp_name"])) {
                        $remove_these = [" ", "", '\"', "\\", "\/"];

                        $newname = str_replace(
                            $remove_these,
                            "",
                            $file["name"]
                        );
                        $newname = time() . "-" . $newname;
                        $uploads = wp_upload_dir();
                        $upload_path = "{$uploads["path"]}/$newname";
                        move_uploaded_file($file["tmp_name"], $upload_path);
                        $upload_file_url = "{$uploads["url"]}/$newname";

                        $wp_filetype = wp_check_filetype(
                            basename($upload_path),
                            null
                        );
                        $attachment = [
                            "guid" => $upload_file_url,
                            "post_mime_type" => $wp_filetype["type"],
                            "post_title" => preg_replace(
                                '/\.[^.]+$/',
                                "",
                                basename($upload_path)
                            ),
                            "post_content" => "",
                            "post_status" => "inherit",
                        ];
                        $attachment_id = wp_insert_attachment(
                            $attachment,
                            $upload_path,
                            0
                        );

                        if (is_wp_error($attachment_id)) {
                            $json["error"] = "Error.";
                        } else {
                            //delete current
                            $profile_image = get_field(
                                "profile_image",
                                "user_" . $user->ID
                            ); //get_user_meta($user->ID, 'profile_image', true);
                            if ($profile_image) {
                                /*$profile_image = json_decode($profile_image);
                                                if (isset($profile_image->attachment_id)) {
                                                    wp_delete_attachment($profile_image->attachment_id, true);
                                                }*/
                                wp_delete_attachment($profile_image, true);
                            }

                            if (!function_exists("wp_crop_image")) {
                                include ABSPATH . "wp-admin/includes/image.php";
                            }

                            //Generate attachment in the media library
                            $attachment_file_path = get_attached_file(
                                $attachment_id
                            );
                            $data = wp_generate_attachment_metadata(
                                $attachment_id,
                                $attachment_file_path
                            );

                            //Get the attachment entry in media library
                            $image_full_attributes = wp_get_attachment_image_src(
                                $attachment_id,
                                "full"
                            );
                            $image_thumb_attributes = wp_get_attachment_image_src(
                                $attachment_id,
                                "smallthumb"
                            );

                            $arr = [
                                "attachment_id" => $attachment_id,
                                "url" => $image_full_attributes[0],
                                "thumb" => $image_thumb_attributes[0],
                            ];

                            //Save the image in the user metadata
                            update_post_meta(
                                $attachment_id,
                                "_wp_attachment_wp_user_avatar",
                                $user->ID
                            );
                            update_field(
                                "profile_image",
                                $attachment_id,
                                "user_" . $user->ID
                            );

                            $response["message"] = "Image has been uploaded";
                            $response["data"] = $arr["thumb"];
                        }
                    }
                }
            } else {
                $response["error"] = true;
                $response["message"] = "Error";
            }
            echo json_encode($response);
            die();