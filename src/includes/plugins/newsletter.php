<?php
// ----------------------------------------------------------------------
// ENQUEUE & LOCALIZE SCRIPTS
// ----------------------------------------------------------------------
/*
+------------------+-----------+-------------------------------+--------------------------------------+-----------+------------+----------------------------------------+
|      Field       |   Type    |            Label              |             Placeholder                  | Required  | Maxlength  |               Pattern                  |
+------------------+-----------+-------------------------------+--------------------------------------+-----------+------------+----------------------------------------+
| ne               | email     | E-Mail                         | example@email.com                       | true      | 255        | [a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$ |
| nn               | text      | Ad                             | Adınız                                  | false     | 100        |                                        |
| ns               | text      | Soyad                          | Soyadınız                               | false     | 100        |                                        |
| ntel             | tel       | Telefon                        | +90 5xx xxx xx xx                       | false     | 20         | ^\+?[0-9\s-]{7,20}$                    |
| ncity            | text      | Şehir / İl                     | İstanbul                                | false     | 100        |                                        |
| ncountry         | select    | Ülke                           | (dropdown)                              | false     | -          | options: TR, DE, US                    |
| ntitle           | select    | Unvan                          | (dropdown)                              | false     | -          | options: Mr, Mrs, Dr                   |
| ny               | checkbox  | Gizlilik / KVKK Onayı          | (checkbox)                              | true      | -          |                                        |
| nl[]             | select    | Liste Seçimi                   | (multi-select)                          | false     | -          | örn: newsletter list IDs               |
| nlng             | select    | Dil                            | (dropdown)                              | false     | -          | options: TR, DE, EN                    |
| nc               | hidden    | Kampanya Etiketi               | (hidden)                                | false     | 50         |                                        |
| nt               | hidden    | Token                          | (hidden, server tarafından oluşturulur) | false     | 50         |                                        |
| ncreated         | hidden    | Zaman Damgası                  | (server tarafından atanır)              | false     | -          |                                        |
| nhr              | hidden    | HTTP Referer                   | (sayfa URL)                             | false     | 255        |                                        |
| np1              | text      | Profil Alan 1                  | (opsiyonel)                             | false     | 255        |                                        |
| np2              | text      | Profil Alan 2                  | (opsiyonel)                             | false     | 255        |                                        |
| np3              | text      | Profil Alan 3                  | (opsiyonel)                             | false     | 255        |                                        |
| submit           | submit    | Abone Ol                       | (button)                                | -         | -          |                                        |
+------------------+-----------+-------------------------------+--------------------------------------+-----------+------------+----------------------------------------+
*/

add_action('wp_enqueue_scripts', function() {
    // ---- GÜVENLİ ÖN KONTROL ----
    if (
        !defined('SITE_ASSETS') ||
        !is_array(SITE_ASSETS) ||
        !isset(SITE_ASSETS['wp_js']) ||
        !is_array(SITE_ASSETS['wp_js'])
    ) {
        return;
    }

    // ---- EĞER NEWSLETTER YOKSA ----
    if (!in_array('newsletter', SITE_ASSETS['wp_js'])) {

        // 1. KENDİ THEME JS'İNİ KALDIR (newsletter-ajax)
        wp_dequeue_script('newsletter-ajax');
        wp_deregister_script('newsletter-ajax');

        // 2. PLUGIN TARAFINDAN EKLENENLERİ KALDIR
        wp_dequeue_style('newsletter');
        wp_deregister_style('newsletter');

        wp_dequeue_script('newsletter-js');
        wp_deregister_script('newsletter-js');

        // bazen plugin ek bir inline JS veya localized script de ekler
        wp_dequeue_script('newsletter');
        wp_deregister_script('newsletter');

        // (İstersen admin bar’da vs. ekli değilse kontrol edip kaldırabiliriz ama bu kadarı yeter.)
        return;
    }

    // ---- EĞER NEWSLETTER VARSA NORMAL ENQUEUE ----
    wp_enqueue_script(
        'newsletter-ajax',
        SH_STATIC_URL . 'js/plugins/newsletter-ajax/main.js',
        ['jquery'],
        '1.4',
        true
    );
}, 999);

// ----------------------------------------------------------------------
// AJAX SUBSCRIBE HANDLER
// ----------------------------------------------------------------------

function realhero_ajax_subscribe() {
    global $wpdb;

    // 1. NONCE CHECK
    if ( ! check_ajax_referer( 'ajax', 'nonce', false ) ) {
        wp_send_json_error( array( 'msg' => __( 'Security check failed.', 'text-domain' ) ) );
        wp_die();
    }

    // 2. POST DATA
    $serialized_data = isset( $_POST['data'] ) ? $_POST['data'] : '';
    if ( empty( $serialized_data ) ) {
        wp_send_json_error( array( 'msg' => __( 'Error: Empty form data.', 'text-domain' ) ) );
        wp_die();
    }

    parse_str( $serialized_data, $fields );

    // 3. EMAIL REQUIRED
    if ( empty( $fields['ne'] ) ) {
        wp_send_json_error( array( 'msg' => __( 'Email is required.', 'text-domain' ) ) );
        wp_die();
    }

    $email        = sanitize_email( $fields['ne'] );
    $name         = isset( $fields['nn'] ) ? sanitize_text_field( $fields['nn'] ) : '';
    $surname      = isset( $fields['ns'] ) ? sanitize_text_field( $fields['ns'] ) : '';
    $status       = isset( $fields['na'] ) ? sanitize_text_field( $fields['na'] ) : 'C';
    $http_referer = isset( $fields['nhr'] ) ? esc_url_raw( $fields['nhr'] ) : '';

    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'msg' => __( 'Invalid email format.', 'text-domain' ) ) );
        wp_die();
    }

    if ( ! defined( 'NEWSLETTER_VERSION' ) ) {
        wp_send_json_error( array( 'msg' => __( 'Newsletter plugin not active.', 'text-domain' ) ) );
        wp_die();
    }

    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}newsletter WHERE email = %s",
        $email
    ) );

    if ( $count > 0 ) {
        wp_send_json_error( array( 'msg' => __( 'Already subscribed.', 'text-domain' ) ) );
        wp_die();
    }

    // 4. INSERT
    $token = wp_generate_password( 32, false );

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'newsletter',
        array(
            'email'        => $email,
            'name'         => $name,
            'surname'      => $surname,
            'status'       => $status,
            'http_referer' => $http_referer,
            'token'        => $token,
            'created'      => current_time( 'mysql' ),
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( $inserted === false ) {
        wp_send_json_error( array( 'msg' => __( 'Database insert failed.', 'text-domain' ) ) );
        wp_die();
    }

    // 5. SEND CONFIRMATION OR WELCOME MAIL
    $opts   = get_option( 'newsletter' );
    $opt_in = (int) $opts['noconfirmation']; // 0 = double opt-in, 1 = single
    $user_id = $wpdb->insert_id;

    $newsletter = Newsletter::instance();
    $user       = NewsletterUsers::instance()->get_user( $user_id );

    if ( $opt_in === 0 ) {
        NewsletterSubscription::instance()->mail(
            $user,
            $newsletter->replace( $opts['confirmation_subject'], $user ),
            $newsletter->replace( $opts['confirmation_message'], $user )
        );
    } else {
        NewsletterSubscription::instance()->mail(
            $user,
            $newsletter->replace( $opts['confirmed_subject'], $user ),
            $newsletter->replace( $opts['confirmed_message'], $user )
        );
    }

    // 6. RESPONSE
    wp_send_json_success( array( 'msg' => __( 'Thanks! You are subscribed.', 'text-domain' ) ) );
}
add_action( 'wp_ajax_realhero_subscribe', 'realhero_ajax_subscribe' );
add_action( 'wp_ajax_nopriv_realhero_subscribe', 'realhero_ajax_subscribe' );