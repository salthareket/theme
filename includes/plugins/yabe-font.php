<?php

function yabe_get_fonts(){
	global $wpdb;
	$fonts = array();
	$table_name = $wpdb->prefix . 'yabe_webfont_fonts';
	$query = "
	    SELECT title, family, metadata, font_faces
	    FROM $table_name
	    WHERE status = 1
	";
    $results = $wpdb->get_results($query);
	if ($results) {
	    foreach ($results as $result) {
	    	$font_faces = json_decode($result->font_faces, true);
	    	$metadata = json_decode($result->metadata, true);
	        $item = array(
	        	"family" => $result->family,
	        	"title"  => $result->title,
	        	"selector"  => $metadata["selector"],
	        	"files"  => array()
	        );
	        if($font_faces){
	        	foreach($font_faces as $font_face){
	        		if($font_face["files"]){
		        		$item["files"][] =  array(
		        			"title"  => $font_face["files"][0]["name"],
		        			"weight" => $font_face["weight"],
		        			"style"  => $font_face["style"],
		        			"file"   => $font_face["files"][0]["attachment_url"]    			
		        		);
		        	}
	        	}
	        }
	        $fonts[] = $item;
	    }
	}
	return $fonts;
}