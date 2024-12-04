<?php

function get_star_vote($post_id=0, $user_id=0){
	global $wpdb;
	return $wpdb->get_var(  "SELECT vote FROM wp_yasr_log  where post_id = ".$post_id." ".($user_id?"and user_id=".$user_id:"")." order by id asc limit 1" );
}

function get_star_votes($post_id){
	global $wpdb;
	return $wpdb->get_results( 'SELECT count(*) as total,  CAST(AVG(vote) AS DECIMAL(10,1)) as point FROM wp_yasr_log WHERE post_id = '.$post_id, OBJECT );
}

function get_star_votes_profile($user_id=0, $approved=1){
	global $wpdb;
	$query= "SELECT 
                    count(*) as total, CAST(AVG(wpcm2.meta_value) AS DECIMAL(10,1)) as point
                FROM
                    ".$wpdb->prefix."comments AS wpc
                INNER JOIN ".$wpdb->prefix."commentmeta AS wpcm
                    ON
                        wpcm.comment_id = wpc.comment_id AND wpc.comment_type = 'review' and wpcm.meta_key = 'comment_profile' AND wpcm.meta_value = $user_id
                INNER JOIN ".$wpdb->prefix."commentmeta AS wpcm2
                    ON
                        wpcm2.comment_id = wpc.comment_id AND wpcm2.meta_key = 'rating'
                WHERE 
                    wpc.comment_approved = $approved";
    return $wpdb->get_row($query);
}


//add this to yasr/lib/yasr-functions.php 449
//$filtered_schema_type = apply_filters( 'yasr_filter_schema_type', $review_choosen );
add_filter( 'yasr_filter_schema_type','yasr_schema_type');
function yasr_schema_type($type){
	global $post;
	if($post->post_type=="product"){
       $type="Product";
	}
    return $type;
}

add_filter( 'yasr_filter_schema_jsonld','yasr_schema');
function yasr_schema($type){
    return $type;
}

function yasr_wpml_save($post_id, $rating) {
}
//do_action('yasr_action_on_visitor_vote', 'yasr_wpml_save', $post_id, $rating);


if(is_admin()){
        function plt_hide_yet_another_stars_rating_menus() {
            //Hide "Yet Another Stars Rating".
            remove_menu_page('yasr_settings_page');
            //Hide "Yet Another Stars Rating → Settings".
            remove_submenu_page('yasr_settings_page', 'yasr_settings_page');
            //Hide "Yet Another Stars Rating → Stats".
            remove_submenu_page('yasr_settings_page', 'yasr_stats_page');
            //Hide "Yet Another Stars Rating → Contact Us".
            remove_submenu_page('yasr_settings_page', '#');
            //Hide "Yet Another Stars Rating → Upgrade".
            remove_submenu_page('yasr_settings_page', 'yasr_settings_page-pricing');
        }
        add_action('admin_menu', 'plt_hide_yet_another_stars_rating_menus', 1000000000);

        function plt_hide_yet_another_stars_rating_metaboxes() {
            $screen = get_current_screen();
            if ( !$screen ) {
                return;
            }
            //Hide the "YASR" meta box.
            remove_meta_box('yasr_metabox_overall_rating', $screen->id, 'side');
            //Hide the "Yet Another Stars Rating" meta box.
            remove_meta_box('yasr_metabox_below_editor_metabox', $screen->id, 'normal');
        }
        add_action('add_meta_boxes', 'plt_hide_yet_another_stars_rating_metaboxes', 20);

        function plt_hide_yet_another_stars_rating_dashboard_widgets() {
            $screen = get_current_screen();
            if ( !$screen ) {
                return;
            }
            //Remove the "Recent Ratings" widget.
            remove_meta_box('yasr_widget_log_dashboard', 'dashboard', 'normal');
            //Remove the "Your Ratings" widget.
            remove_meta_box('yasr_users_dashboard_widget', 'dashboard', 'normal');
        }
        add_action('wp_dashboard_setup', 'plt_hide_yet_another_stars_rating_dashboard_widgets', 20);
}