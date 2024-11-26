<?php

//deregister some default style & scripts
function my_deregister_styles() {
	wp_dequeue_style( 'apss-font-awesome' );
	wp_deregister_style('apss-font-awesome');
	wp_dequeue_style( 'apss-font-opensans' );
	wp_deregister_style('apss-font-opensans');
}
add_action( 'wp_print_styles', 'my_deregister_styles', 100 );