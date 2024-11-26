<?php

add_filter( 'manage_pages_columns', 'table_template_columns', 10, 1 );
add_action( 'manage_pages_custom_column', 'table_template_column', 10, 2 );
function table_template_columns( $columns ) {
    $custom_columns = array(
        'col_template' => 'Template'
    );
    $columns = array_merge( $columns, $custom_columns );
    return $columns;
}
function table_template_column( $column, $post_id ) {
    if ( $column == 'col_template' ) {
        echo basename( get_page_template() );
    }
}



//rename media file name to sanitized post title
function custom_upload_filter( $file ) {
    if ( ! isset( $_REQUEST['post_id'] ) ) {
        return $file;
    }
    $id           = intval( $_REQUEST['post_id'] );
    $parent_post  = get_post( $id );
    $post_name    = sanitize_title( $parent_post->post_title );
    //$file['name'] = $post_name . '-' . $file['name'];
    $file['name'] = 'img-'. $post_name . '.' . mime2ext($file['type']);
    return $file;
}
//add_filter( 'wp_handle_upload_prefilter', 'custom_upload_filter' );



function acf_load_city_choices( $field ) {
	global $post;
    $field['choices'] = array();
    $cities = get_states(wc_get_base_country());
    if( is_array($cities) ) {
        foreach( $cities as $key => $city ) {
            $field['choices'][ $key ] = $city;
        }
    }
    return $field;
}
add_filter('acf/load_field/name=city', 'acf_load_city_choices');

function acf_admin_head(){
    global $post;

	    $district = get_post_meta( $post->ID, "district", true);
		?>
		<script type="text/javascript">
			jQuery(function($){
				$('select').on('change', function() {
					var field_name = $(this).closest(".acf-field").data("name");
					switch(field_name){
						case "city" :
						   var obj = $(".acf-field[data-name='district']").find("select");
						       obj.prop("disabled", true);
						   var city = this.value;
							$.post(ajax_request_vars.url+"?ajax=query", { method : "get_districts", vars : { city : city } })
				            .fail(function() {
				                alert( "error" );
				            })
				            .done(function( response ) {
				            	response = $.parseJSON(response);	
				            	obj.empty().val(null).trigger('change');
				            	for(var i=0;i<response.length;i++){
				            		var selected = i==0?true:false;
				            		if("<?php echo $district;?>" == response[i]){
	                                   selected = true;
				            		}
				            		var district = response[i];
					            	var newOption = new Option(district, district, selected, selected);
								    obj.append(newOption);	            		
				            	}
				            	obj.trigger('change').prop("disabled", false);
				            });
						break;
	                }
				}).trigger("change");
			});
		</script>    	

<?php
}
//add_action('acf/input/admin_head', 'acf_admin_head');




/**
 * Add ACF thumbnail columns to Linen Category custom taxonomy
 */
function add_thumbnail_columns($columns) {
    $columns['image'] = __('Thumbnail');
    $new = array();
    foreach($columns as $key => $value) {
        if ($key=='name') // Put the Thumbnail column before the Name column
            $new['image'] = 'Thumbnail';
        $new[$key] = $value;
    }
    return $new;
}
//add_filter('manage_edit-product-color_columns', 'add_thumbnail_columns');

/**
 * Output ACF thumbnail content in Linen Category custom taxonomy columns
 */
function thumbnail_columns_content($content, $column_name, $term_id) {
    if ('image' == $column_name) {
        $term = get_term($term_id);
        $linen_thumbnail_var = get_field('image', $term);
        $content = '<img src="'.$linen_thumbnail_var.'" width="60" />';
    }
    return $content;
}
//add_filter('manage_product-color_custom_column' , 'thumbnail_columns_content', 10, 3);







function acf_load_language_choices( $field ) {
    $field['choices'] = array();
    foreach(qtranxf_getSortedLanguages() as $language) {
        $field['choices'][$language] = qtranxf_getLanguageName($language);
    }   
    return $field;
}
if(function_exists("qtranxf_getSortedLanguages")){
    add_filter('acf/load_field/name=language', 'acf_load_language_choices');
}


















// Callback function to insert 'styleselect' into the $buttons array
function my_mce_buttons_2( $buttons ) {
    array_unshift( $buttons, 'styleselect' );
    return $buttons;
}
// Register our callback to the appropriate filter
add_filter('mce_buttons_2', 'my_mce_buttons_2');

// Callback function to filter the MCE settings
function my_mce_before_init_insert_formats( $init_array ) {  
    // Define the style_formats array
    $style_formats = array(  
        // Each array child is a format with it's own settings
        array(  
            'title' => 'btn-primary',  
            'selector' => 'a',  
            'classes' => 'btn btn-primary btn-extended btn-loading-page'             
        ),
        array(  
            'title' => 'btn-secondary',  
            'selector' => 'a',  
            'classes' => 'btn btn-secondary btn-extended btn-loading-page'             
        ),
        array(  
            'title' => 'btn-tertiary',  
            'selector' => 'a',  
            'classes' => 'btn btn-tertiary btn-extended btn-loading-page'             
        ),
        array(  
            'title' => 'btn-quaternary',  
            'selector' => 'a',  
            'classes' => 'btn btn-quaternary btn-extended btn-loading-page'             
        )
    );  
    // Insert the array, JSON ENCODED, into 'style_formats'
    $init_array['style_formats'] = json_encode( $style_formats );  

    return $init_array;  

} 
// Attach callback to 'tiny_mce_before_init' 
add_filter( 'tiny_mce_before_init', 'my_mce_before_init_insert_formats' );







/**
 * Registers an editor stylesheet for the theme.
 */
function wpdocs_theme_add_editor_styles() {
    add_editor_style( 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.1/css/bootstrap.min.css' );
}
add_action( 'admin_init', 'wpdocs_theme_add_editor_styles' );



//saran-group
add_filter( 'manage_product_posts_columns', 'product_posts_columns' );
add_action( 'manage_product_posts_custom_column', 'product_posts_custom_column', 10, 2 );
add_filter( 'manage_edit-product_sortable_columns', 'product_posts_custom_column_sortable' );
function product_posts_columns ( $columns ) {
    return array_merge ( $columns, array ( 
        'application_status' => __( 'Application Status' ),
        'client' => __( 'Client' ),
        'expert' => __( 'Expert' ),
        'platform' => __( 'Platform' )
    ));
}
function product_posts_custom_column ( $column, $post_id ) {
        switch ( $column ) {
            case 'application_status':
                echo get_field( 'application_status', $post_id );
            break;
            case 'client':
                $application = new Application($post_id);
                $client = $application->parent->author;
                $client_date = $application->get_session_date($client);
                echo $application->parent->author->get_title()."<br>";
                if($client_date["start"]["date"] == $client_date["end"]["date"]){
                   echo $client_date["start"]["date"]." ".$client_date["start"]["time"]."-".$client_date["end"]["time"];
                }else{
                   echo $client_date["start"]["date"]." ".$client_date["start"]["time"]."<br>";
                   echo $client_date["end"]["date"]." ".$client_date["end"]["time"];
                }
            break;
            case 'expert':
                $application = new Application($post_id);
                $expert = $application->author;
                $expert_date = $application->get_session_date($expert);
                echo $application->author->get_title()."<br>";
                if($expert_date["start"]["date"] == $expert_date["end"]["date"]){
                   echo $expert_date["start"]["date"]." ".$expert_date["start"]["time"]."-".$expert_date["end"]["time"];
                }else{
                   echo $expert_date["start"]["date"]." ".$expert_date["start"]["time"]."<br>";
                   echo $expert_date["end"]["date"]." ".$expert_date["end"]["time"]."<br>";
                }
            break;
            case 'platform':
                $application = new Application($post_id);
                echo $application->_platform;
            break;
        }
}
function product_posts_custom_column_sortable( $columns ) {
        $columns['application_status'] = 'application_status';
        return $columns;
}










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








add_action('wp_ajax_get_posts_type_terms', 'get_posts_type_terms');
add_action('wp_ajax_nopriv_get_posts_type_terms', 'get_posts_type_terms');
function get_posts_type_terms(){
    $response = array(
        "error" => false,
        "message" => "",
        "html" => "",
        "data" => ""
    );

    $options = "";
    $ids = array();
    $selected = get_field("layouts_".$_POST['row']."_terms", $_POST['post_id']);

    if($_POST["name"] == "post_type"){
        $taxonomies = get_object_taxonomies( array( 'post_type' => $_POST["value"] ) );   
        foreach( $taxonomies as $taxonomy ){
            $terms = get_terms( $taxonomy );
            $ids[] = $taxonomy;
            foreach( $terms as $term ){
                $options .= "<option value='".$term->term_id."' ".($selected?"selected":"").">".$term->name."</option>";
                
            }
        }
    }
    if($_POST["name"] == "taxonomy"){
        $terms = Timber::get_terms( array( 'taxonomy' => $_POST["value"] ) );   
        foreach( $terms as $term ){
            if($_POST["type"] == "taxonomy"){
                if($term->children()){
                    $options .= "<option value='".$term->term_id."' ".($selected?"selected":"").">".$term->name."</option>";
                    //$term_ids[] = $term->term_id;
                }else{
                    $options .= "<option value='0' selected>All Terms</option>";
                    break;
                }                
            }
            if($_POST["type"] == "post"){
                $options .= "<option value='".$term->term_id."' ".($selected?"selected":"").">".$term->name."</option>";               
            }
        }
    }

    $values = array();
    $values["selected"] = $selected;
    $values["ids"] = $ids;
    /*$selected = array();
    if($selected_terms){
        foreach($selected_terms as $key => $term){
            $selected[] = $term;
        }
        $response["data"] = $selected;
    }*/
    $response["data"] = $values;
    $response["html"] = $options;
    echo json_encode($response);
    die;
}


add_action('wp_ajax_get_posts_type_taxonomies', 'get_posts_type_taxonomies');
add_action('wp_ajax_nopriv_get_posts_type_taxonomies', 'get_posts_type_taxonomies');
function get_posts_type_taxonomies(){
    $response = array(
        "error" => false,
        "message" => "",
        "html" => "",
        "data" => ""
    );

    $options = "";
    $ids = array();
    $selected = get_field("layouts_".$_POST['row']."_filters_taxonomies", $_POST['post_id']);

    $taxonomies = get_object_taxonomies( $_POST["type"]  );
    foreach( $taxonomies as $taxonomy ){
        $ids[] = $taxonomy;
        $options .= "<option value='".$taxonomy."' ".($selected?"selected":"").">".$taxonomy."</option>";        
    }

    $values = array();
    $values["selected"] = $selected;
    $values["ids"] = $ids;

    $response["data"] = $values;
    $response["html"] = $options;
    echo json_encode($response);
    die;
}








// on global settings changed
function acf_general_settings_rewrite( $value, $post_id, $field, $original ) {
    $old = get_field($field["name"], "option");
    if( $value != $old) {
        flush_rewrite_rules();
    }
    return $value;
}
add_filter('acf/update_value/name=enable_membership', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_membership_activation', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_chat', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_notifications', 'acf_general_settings_rewrite', 10, 4);
add_filter('acf/update_value/name=enable_favorites', 'acf_general_settings_rewrite', 10, 4);










function add_company_columns($columns) {
    $columns['author'] = __('Added by');
    $new = array();
    foreach($columns as $key => $value) {
        if ($key == 'description') // Put the Thumbnail column before the Name column
            $new['author'] = 'Author';
            $new[$key] = $value;
    }
    unset($new["description"]);
    return $new;
}
add_filter('manage_edit-company_columns', 'add_company_columns');

function company_columns_content($content, $column_name, $term_id) {
    if ('author' == $column_name) {
        $author = get_field('author', "company_".$term_id);
        if($author){
            $author = new User($author);
            $created_at = get_field('created_at', "company_".$term_id);
            echo "<a href='".$author->link()."' target='_blank'><b>".$author->get_title()."</b></a><br>on ".date("j F Y H:i:s", ($created_at));
        }else{
            echo " - ";
        }
    }
    //return $content;
}
add_filter('manage_company_custom_column' , 'company_columns_content', 10, 3);










function new_modify_user_table( $column ) {
    $column['user_status'] = 'Activated';
    $column['user_locked'] = 'Password Set';
    return $column;
}
add_filter( 'manage_users_columns', 'new_modify_user_table' );

function new_modify_user_table_row( $val, $column_name, $user_id ) {
    switch ($column_name) {
        case 'user_status' :
            $value = get_user_meta( $user_id, 'user_status', true);
            if($value){
                return "<span style='color:green;'>Yes</span>";
            }else{
                return "<span style='color:red;'>No</span>";
            }
        case 'user_locked' :
            $value = get_user_meta( $user_id, 'user_locked', true);
            if(!metadata_exists( 'user', $user_id, 'user_locked')){
                return "<span style='color:red;'>No - not exist</span>";
            }else{
                if($value){
                    return "<span style='color:red;'>No".$value."</span>";
                }else{
                    return "<span style='color:green;'>Yes</span>";
                }
            }
        default:
    }
    return $val;
}
add_filter( 'manage_users_custom_column', 'new_modify_user_table_row', 10, 3 );


