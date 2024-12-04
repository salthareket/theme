<?php

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
    $buttons = array();

    // Buttons from costom colors
    if(isset($GLOBALS["mce_text_colors"])){
        foreach ($GLOBALS["mce_text_colors"] as $value) {
            $slug = strtolower($value);
            $buttons[] = array(  
                'title' => 'btn-'.$slug,  
                'selector' => 'a',  
                'classes' => 'btn btn-'.$slug.' btn-extended'             
            );
        }
    }
    $style_formats = array(
        array(  
            'title' => 'List Unstyled',  
            'selector' => 'ul, ol',  
            'classes' => 'list-unstyled ms-4'             
        ),
        array(  
            'title' => 'Table Bordered',  
            'selector' => 'table',  
            'classes' => 'table-bordered'             
        ),
        array(  
            'title' => 'Table Striped',  
            'selector' => 'table',  
            'classes' => 'table-striped'             
        ),
        array(  
            'title' => 'Text - Slab',  
            'selector' => '*',  
            'classes' => 'slab-text-container'             
        ),
        array(
            'title' => 'Small',
            'inline' => 'small'
        ),
    );
    $style_formats = array_merge($buttons, $style_formats);
    
    if($GLOBALS["breakpoints"]){
        $typography = [];
        $theme_styles = acf_get_theme_styles();
        if($theme_styles){
            if(isset($theme_styles["typography"])){
                $typography = $theme_styles["typography"];                  
            }
        }
        foreach($GLOBALS["breakpoints"] as $key => $breakpoint){
            $size = "";
            if(isset($typography["title"][$key]) && !empty($typography["title"][$key]["value"])){
               $size = " - ".$typography["title"][$key]["value"].$typography["title"][$key]["unit"];
            }
            $title_classes[] = array(  
                'title' => 'Title - '.$key.$size,  
                'selector' => 'h1,h2,h3,h4,h5,h6',  
                'classes' => 'title-'.$key             
            );
            $size = "";
            if(isset($typography["text"][$key]) && !empty($typography["text"][$key]["value"])){
               $size = " - ".$typography["text"][$key]["value"].$typography["text"][$key]["unit"];
            }
            $text_classes[] = array(  
                'title' => 'Text - '.$key.$size,
                'selector' => 'p',  
                'classes' => 'text-'.$key             
            );
        }
        $style_formats = array_merge($title_classes, $style_formats);
        $style_formats = array_merge($text_classes, $style_formats);
    }
    
    $font_weights = [];
    foreach(["normal", 100, 200, 300, 400, 500, 600, 700, 800, 900] as $fw){
        $font_weights[] = array(  
            'title' => 'Font Weight - '.$fw,  
            'selector' => '*',  
            'classes' => 'fw-'.$fw             
        );
    }
    $style_formats = array_merge($font_weights, $style_formats);

    $line_heights = [];
    foreach(["1", "base", "sm", "lg"] as $lh){
        $line_heights[] = array(  
            'title' => 'Line Height - '.$lh,  
            'selector' => '*',  
            'classes' => 'lh-'.$lh             
        );
    }
    $style_formats = array_merge($line_heights, $style_formats);
    
    if(isset($GLOBALS["mce_styles"])){
        $style_formats = array_merge($GLOBALS["mce_styles"], $style_formats);
    }  
    // Insert the array, JSON ENCODED, into 'style_formats'
    $init_array['style_formats'] = json_encode( $style_formats );  


    //colors
    if(isset($GLOBALS["mce_text_colors"])){
        $mce_colors = '';
        foreach ($GLOBALS["mce_text_colors"] as $key => $value) {
            $mce_colors .= '"' . str_replace("#", "", $key) . '", "' . $value . '", ';
        }
        $mce_colors = rtrim($mce_colors, ', ');
        $init_array['textcolor_map'] = '[' . $mce_colors . ']';
        $init_array['textcolor_rows'] = 1;
    }

    return $init_array;  

} 
// Attach callback to 'tiny_mce_before_init' 
add_filter( 'tiny_mce_before_init', 'my_mce_before_init_insert_formats' );


// add letter spacing button
function custom_tinymce_buttons($buttons) {
    array_push($buttons, 'letter_spacing_button');
    return $buttons;
}
add_filter('mce_buttons', 'custom_tinymce_buttons');