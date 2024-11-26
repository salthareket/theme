<?php

    function salt_widgets_init() {
		register_sidebar( array(
			'name'          => translate( 'Blog Sidebar'),
			'id'            => 'sidebar-1',
			'description'   => translate( 'Add widgets here to appear in your sidebar on blog posts and archive pages.'),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		) );
	}

    function hstngr_register_widget() {
        register_widget( 'hstngr_widget' );
    }
    add_action( 'widgets_init', 'hstngr_register_widget' );

    class hstngr_widget extends WP_Widget {
	    function __construct() {
		    parent::__construct(
			    // widget ID
			    'hstngr_widget',
			    // widget name
			    __('Hostinger Sample Widget', 'hstngr_widget_domain'),
			    // widget description
			    array( 'description' => __( 'Hostinger Widget Tutorial', 'hstngr_widget_domain' ))
	        );
        }
	    public function widget( $args, $instance ) {
		    $title = apply_filters( 'widget_title', $instance['title'] );
		    $title_alt = apply_filters( 'widget_title', $instance['title_alt'] );
		    $image = get_field('image', 'widget_' . 'hstngr_widget');
		    echo $args['before_widget'];
		    //if title is present
		    if ( ! empty( $title ) )
			    echo $args['before_title'] . $title . $args['after_title'];
			    echo $args['before_title'] . $title_alt . $args['after_title'];
			    echo($image);
			    //output
			    echo trans( 'Greetings from Hostinger.com!', 'hstngr_widget_domain' );

				if ($image ) {
				    $attachment_id = $image ;
				    $size = "full";
				    $image = wp_get_attachment_image_src( $attachment_id, $size );						
				    $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
				        echo '<img alt="'.$alt.'" src="' . $image[0] . '" />';			   
				}

			    echo $args['after_widget'];
	    }
	    public function form( $instance ) {
		    if ( isset( $instance[ 'title' ] ) ){
		    	$title = $instance[ 'title' ];
		    }else {
		   	 $title = __( 'Default Title', 'hstngr_widget_domain' );
		    }

		    if ( isset( $instance[ 'title_alt' ] ) ){
		    	$title_alt = $instance[ 'title_alt' ];
		    }else {
		   	 $title_alt = __( 'Alternative Title', 'hstngr_widget_domain' );
		    }


		    ?>
		    <p>
			    <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			    <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			    <input class="widefat" id="<?php echo $this->get_field_id( 'title_alt' ); ?>" name="<?php echo $this->get_field_name( 'title_alt' ); ?>" type="text" value="<?php echo esc_attr( $title_alt ); ?>" />
		    </p>
		    <?php
	    }
	    public function update( $new_instance, $old_instance ) {
		    $instance = array();
		    $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		    $instance['title_alt'] = ( ! empty( $new_instance['title_alt'] ) ) ? strip_tags( $new_instance['title_alt'] ) : '';
		  	return $instance;
		}
    }