<?php
require_once( dirname(dirname(dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) ) . '/wp-load.php' );
        if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
            wpcf7_enqueue_scripts();
        }
        if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
            wpcf7_enqueue_styles();
        }
        if ( function_exists( 'wpcf7cf_enqueue_scripts' ) ) {
            wpcf7cf_enqueue_scripts();
        }
        if ( function_exists( 'wpcf7cf_enqueue_styles' ) ) {
            wpcf7cf_enqueue_styles();
        }

            $output = [
                "error" => false,
                "message" => "",
                "data" => [
                    "title" => $vars["title"],
                    "content" => do_shortcode(
                        '[contact-form-7 id="' . $vars["id"] . '"]'
                    ),
                ],
                "html" => "",
            ];
            echo json_encode($output);
            die();