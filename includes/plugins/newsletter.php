<?php
    function newsletter_ajax() {
        wp_enqueue_script(
            'newsletter-ajax',
            get_stylesheet_directory_uri() . '/static/js/plugins/newsletter-ajax/main.js',
            array(),
            '1.2', true
        );
        //if ( !is_admin() ) {
            /** */
            wp_localize_script( 'newsletter-ajax', 'newsletter_ajax', array(
                'url' =>            admin_url( 'admin-ajax.php' ),
                'ajax_nonce' =>     wp_create_nonce( 'noncy_nonce' ),
                'assets_url' =>     get_stylesheet_directory_uri()
            ) );
        //} 
    }
    /**
     * Ajax newsletter
     * 
     * @url http://www.thenewsletterplugin.com/forums/topic/ajax-subscription
     */
    function realhero_ajax_subscribe() {
        check_ajax_referer( 'noncy_nonce', 'nonce' );
        $data = urldecode( $_POST['data'] );
        if ( !empty( $data ) ) :
            $data_array = explode( "&", $data );
            $fields = [];
            foreach ( $data_array as $array ) :
                $array = explode( "=", $array );
                $fields[ $array[0] ] = $array[1];
            endforeach;
        endif;
        if ( !empty( $fields ) ) :
            global $wpdb;
            
            // check if already exists
            
            /** @var int $count **/
            $count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}newsletter WHERE email = %s", $fields['ne'] ) );
            
            if( $count > 0 ) {
                $output = array(
                    'status'    => 'error',
                    'msg'       => trans( 'You are already our subscriber.')
                );
            } elseif( !defined( 'NEWSLETTER_VERSION' ) ) {
                $output = array(
                    'status'    => 'error',
                    'msg'       => trans( 'Please install & activate newsletter plugin.')
                );           
            } else {
                /**
                 * Generate token
                 */
                
                /** @var string $token */
                $token =  wp_generate_password( rand( 10, 50 ), false );
                $wpdb->insert( $wpdb->prefix . 'newsletter', array(
                        'email'         => $fields['ne'],
                        //'sex'         => $fields['nx'],
                        'name'          => $fields['nn'],
                        'surname'       => $fields['ns'],
                        'status'        => $fields['na'],
                        //'list_1'        => $fields['list_1'],
                        'http_referer'  => $fields['nhr'],
                        'token'         => $token,
                    )
                );
                $opts = get_option('newsletter');
                $opt_in = (int) $opts['noconfirmation'];
                // This means that double opt in is enabled
                // so we need to send activation e-mail
                if ($opt_in == 0) {
                    $newsletter = Newsletter::instance();
                    $user = NewsletterUsers::instance()->get_user( $wpdb->insert_id );
                    NewsletterSubscription::instance()->mail($user, $newsletter->replace($opts['confirmation_subject'], $user), $newsletter->replace($opts['confirmation_message'], $user));
                }
                if ($opt_in == 1) {
                    $newsletter = Newsletter::instance();
                    $user = NewsletterUsers::instance()->get_user( $wpdb->insert_id );
                    NewsletterSubscription::instance()->mail($user, $newsletter->replace($opts['confirmed_subject'], $user), $newsletter->replace($opts['confirmed_message'], $user));
                }
                $output = array(
                    'status'    => 'success',
                    'msg'       => trans( 'Thank you!' )
                );  
            }
            
        else :
            $output = array(
                'status'    => 'error',
                'msg'       => trans( 'Error..')
            );
        endif;
        
        wp_send_json( $output );
    }


    /* using on newsletter. tans_static() */
    function get_user_first_name(){
        $value = "";
        if(isset($GLOBALS['user'])){
           if(isset($GLOBALS['user']->first_name)){
              $value = $GLOBALS['user']->first_name;
           }
        }
        return $value;
    }
    function get_user_last_name(){
        $value = "";
        if(isset($GLOBALS['user'])){
           if(isset($GLOBALS['user']->last_name)){
              $value = $GLOBALS['user']->last_name;
           }
        }
        return $value;
    }
    function get_user_email(){
        $value = "";
        if(isset($GLOBALS['user'])){
           if(isset($GLOBALS['user']->user_email)){
              $value = $GLOBALS['user']->user_email;
           }
        }
        return $value;
    }




add_action( 'wp_enqueue_scripts', 'newsletter_ajax' );
add_action( 'wp_ajax_realhero_subscribe', 'realhero_ajax_subscribe' );
add_action( 'wp_ajax_nopriv_realhero_subscribe', 'realhero_ajax_subscribe' );