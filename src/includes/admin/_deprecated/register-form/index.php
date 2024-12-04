<?php

//add_action( 'woocommerce_register_form_start', 'display_account_registration_field' );
//add_action( 'woocommerce_edit_account_form_start', 'display_account_registration_field' );
function display_account_registration_field() {
    $user  = wp_get_current_user();
    $value = isset($_POST['billing_continent']) ? esc_attr($_POST['billing_continent']) : $user->billing_continent;
    $continents = get_continents();
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
    <label for="reg_billing_continent"><?php _e( 'Continent', 'woocommerce' ); ?> <span class="required">*</span></label>
    <select name="billing_continent" id="reg_billing_continent">
        <?php
        foreach($continents as $continent){
            ?>
            <option value="<?php echo $continent["slug"];?>" <?php if($continent["slug"]==$value){?>selected<?php } ?>><?php echo $continent["name"];?></option>
            <?php
        }
        ?>
    </select>
    </p>
    <div class="clear"></div>
    <?php
}

// registration Field validation
//add_filter( 'woocommerce_registration_errors', 'account_registration_field_validation', 10, 3 );
function account_registration_field_validation( $errors, $username, $email ) {
    if ( isset( $_POST['billing_continent'] ) && empty( $_POST['billing_continent'] ) ) {
        $errors->add( 'billing_continent_error', __( '<strong>Error</strong>: Continent is required!', 'woocommerce' ) );
    }
    return $errors;
}

// Save registration Field value
//add_action( 'woocommerce_created_customer', 'save_account_registration_field' );
function save_account_registration_field( $customer_id ) {
    if ( isset( $_POST['phone_code'] ) ) {
        update_user_meta( $customer_id, 'billing_phone_code', sanitize_text_field( $_POST['phone_code'] ) );
    }
    if ( isset( $_POST['billing_continent'] ) ) {
        update_user_meta( $customer_id, 'billing_continent', sanitize_text_field( $_POST['billing_continent'] ) );
    }
}

// Save Field value in Edit account
//add_action( 'woocommerce_save_account_details', 'save_my_account_billing_continent', 10, 1 );
function save_my_account_billing_continent( $user_id ) {
    if ( isset( $_POST['phone_code'] ) ) {
        update_user_meta( $customer_id, 'billing_phone_code', sanitize_text_field( $_POST['phone_code'] ) );
    }
    if( isset( $_POST['billing_continent'] ) )
        update_user_meta( $user_id, 'billing_continent', sanitize_text_field( $_POST['billing_continent'] ) );
}

//add_filter('woocommerce_admin_billing_fields', 'add_woocommerce_admin_billing_fields');
function add_woocommerce_admin_billing_fields($billing_fields) {
    $billing_fields['billing_continent'] = array( 'label' => __('Continent', 'woocommerce') );
    $billing_fields['billing_phone_code'] = array( 'label' => __('Phone Code', 'woocommerce') );

    return $billing_fields;
}
// Display field in admin user billing fields section
//add_filter( 'woocommerce_customer_meta_fields', 'admin_user_custom_billing_field', 10, 1 );
function admin_user_custom_billing_field( $args ) {
    $options = array();
    $continents = get_continents();
    $options[""] = __( 'Choose a continent' );
    foreach($continents as $continent){
        $options[$continent["slug"]] = $continent["name"];
    }
    ksort($options);
    $args['billing']['fields']['billing_continent'] = array(
        'type'          => 'select',
        'label'         => __( 'Continent', 'woocommerce' ),
        'description'   => '',
        'custom_attributes'   => array('maxlength' => 6),
        'options' => $options
    );
    $args['billing']['fields']['billing_phone_code'] = array(
        'type'          => 'text',
        'label'         => __( 'Phone Code', 'woocommerce' ),
        'description'   => '',
        'custom_attributes'   => array('maxlength' => 6)
    );
    return $args;
}
