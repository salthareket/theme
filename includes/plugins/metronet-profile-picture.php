<?php
    
    function get_user_profile_image($user_id=false, $size="thumbnail"){
	    mt_profile_img( $user_id, 
	    	array(
	        'size' => $size,
	        'attr' => array( 
	        	'alt' => '' 
	         ),
	        'echo' => true 
	        )
	    );    	
    }