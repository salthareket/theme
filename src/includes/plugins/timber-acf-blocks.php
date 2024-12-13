<?php

add_filter( 'timber/acf-gutenberg-blocks-templates', function () {
    return ['theme/templates/blocks', 'templates/blocks'];
});

add_filter( 'timber/acf-gutenberg-blocks-data', function( $context ){
    //$context['fields']['extra_data'] = 'New extra data';
    $upload_dir = wp_upload_dir();
    $upload_url = $upload_dir['baseurl'];
    $context['fields']['upload_url'] = $upload_url;
    return $context;
});

add_filter( 'timber/acf-gutenberg-blocks-default-data', function( $data ){
    /*$data['default'] = array(
        'post_type' => 'post',
    );
    $data['pages'] = array(
        'post_type' => 'page',
    );*/
    return $data;
});

add_filter( 'timber/acf-gutenberg-blocks-example-identifier', function( $sufix ){
    return $sufix;
});

add_filter( 'timber/acf-gutenberg-blocks-preview-identifier', function( $sufix ){
    return $sufix;
});

function block_responsive_classes($field=[], $type="", $block_column=""){
    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");
    $tempClasses = [];
    $lastAlign = null;

    if((!empty($block_column) && $block_column["block"] != "bootstrap-columns")){ //&& empty($type)){
        $type = "align-items-";
    }

    foreach ($field as $key => $align) {
        if (!empty($align)) {
            if ($lastAlign !== null && $lastAlign !== $align) {
                $tempClasses[] = [
                    "key" => $prevKey === "xs" ? "" : $prevKey, // Son geçerli key
                    "align" => $lastAlign,
                ];
            }
            $lastAlign = $align;
            $prevKey = $key;
        }
    }
    if ($lastAlign !== null) {
        $tempClasses[] = [
            "key" => $prevKey === "xs" ? "" : $prevKey,
            "align" => $lastAlign,
        ];
    }

    // Class'ları oluştur
    $classes = [];
    foreach ($tempClasses as $index => $entry) {
        $prefix = $entry["key"] ? $entry["key"] . "-" : "";
        $classes[] = $type . $prefix . $entry["align"];
    }
    return $classes;
}

function block_responsive_column_classes($field=[], $type="col-", $field_name=""){
    $tempClasses = [];
    $lastValue = null;
    $lastKey = null;
    foreach ($field as $key => $value) {
        if (($value)) {
            if(!empty($field_name) && isset($value[$field_name])){
                $value = $value[$field_name];
            }
            if ($value !== $lastValue) {
                $col = ($key === "xs") ? "" : $key . "-";
                $tempClasses[] = $type . $col . $value;
            } else {
                if ($key > $lastKey) {
                    $key = ($key === "xs") ? "" : $key . "-";
                    $tempClasses[count($tempClasses) - 1] = $type . $key . $value;
                }
            }
            $lastValue = $value;
            $lastKey = $key;
        }
    }
    return $tempClasses;
}



function block_container($container=""){
    $default = get_field("default_container", "options");
    $default = $default=="no"?"":"container".(empty($default)?"":"-".$default);
    switch($container){
        case "" :
            $container = "container";
        break;
        case "default" :
            $container = $default;
        break;
        case "no" :
            $container = "";
        break;
        default :
            $container = "container-".$container;
        break;
    }
    return $container;
}
function block_ratio($ratio=""){
    $default = get_option("options_default_ratio");
    $default = empty($default) ? "16x9" : $default;
    return ($ratio == "default" || empty($ratio) ? $default : $ratio);
}
function block_ratio_padding($ratio=""){
    $ratio = block_ratio($ratio);
    $ratio_val = explode("x", $ratio);
    $ratio_val[0] = $ratio_val[0] == 185 ? 1.85 : $ratio_val[0];
    $ratio_val[0] = $ratio_val[0] == 235 ? 2.35 : $ratio_val[0];
    return (number_format(($ratio_val[1]/$ratio_val[0]), 2) * 100);
}
function block_classes($block, $fields, $block_column){
    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");
    $classes = [];

    if(isset($fields["block_settings"]["hero"])){
        if($fields["block_settings"]["hero"]){
            $classes[] = "block--hero";
            if($fields["block_settings"]["pt_header"]){
                $classes[] = "pt-header";
            }
        }
    }
    if($block_column){
        $classes[] = "block-".$block_column["block"];
        if($block_column["block"] != "archive"){
            $classes[] = "flex-column";
        }
        $classes[] = "position-relative";
        if(isset($fields["block_settings"]["sticky_top"]) && $fields["block_settings"]["sticky_top"]){
            if($fields["block_settings"]["vertical_align"] != "center"){
                $classes[] = "sticky-top";
            }
        }
    }else{
        $classes[] = "block-".sanitize_title($block["title"]);
        $classes[] = "444 position-relative";
    }
    
    if(isset($fields["class"])){
        $classes[] = $fields["class"];
    }
    if(isset($block["className"])){
        if($block_column){
            if(str_replace("acf/", "", $block["name"]) == $block_column["block"]){
                $classes[] = $block["className"];
            }
        }else{
            $classes[] = $block["className"];
        }
    }
    if(isset($fields["block_settings"]["stretch_height"])){
        if($fields["block_settings"]["stretch_height"]){
            if($block_column){
                $classes[] = "h-100";
            }else{
                $classes[] = "flex-column";
            }
        }
    }
    if($block["align"]){
        $classes[] = "text-".$block["align"];
    }
    if(isset($fields["block_settings"])){
        $classes[] = block_spacing($fields["block_settings"]);
        $classes[] = "d-flex";
        if($fields["block_settings"]["vertical_align"] != "none"){
            if(!empty($block_column)){
                $classes[] = "justify-content-".$fields["block_settings"]["vertical_align"];
            }else{
                $classes[] = "align-items-".$fields["block_settings"]["vertical_align"];
            }
        }
        if(empty(block_container($fields["block_settings"]["container"]))){
            $classes[] = "flex-column-justify-content-center";
        }
        if(isset($fields["block_settings"]["text_align"]) && $fields["block_settings"]["text_align"]){
            $classes = array_merge($classes, block_responsive_classes($fields["block_settings"]["text_align"], "text-", ""));
        }
        if(isset($fields["block_settings"]["horizontal_align"]) && $fields["block_settings"]["horizontal_align"]){
            $classes = array_merge($classes, block_responsive_classes($fields["block_settings"]["horizontal_align"], "justify-content-", $block_column));
        }
    }

    $classes = implode(" ", $classes);
    return $classes;
}
function block_attrs($block, $fields, $block_column){
    $attrs = [];

    $slider_autoheight = false;
    if(isset($fields["slider_settings"])){
        if(isset($fields["slider_settings"])){
            if($fields["slider_settings"]["autoheight"]){
                $slider_autoheight = true;
            }
        }
    }

    if(!empty($block["anchor"])){
        $attrs["id"] = $block["anchor"];
    }else{
        $attrs["id"] = $block["id"];
    }
    $attrs["data-index"] = isset($block["index"])?$block["index"]:0;
    $attrs = array2Attrs($attrs);
    return $attrs;
}


function block_spacing($settings) {
    $margin = $settings['margin'] ?? [];
    $padding = $settings['padding'] ?? [];

    $margin_classes = generate_spacing_classes($margin, 'm');
    $padding_classes = generate_spacing_classes($padding, 'p');

    $merged_classes = array_merge($margin_classes, $padding_classes);
    $combined_string = implode(' ', $merged_classes);

    return $combined_string;
}
function generate_spacing_classes($spacing, $prefix) {
    $classes = [];
    $directions = ['top' => 't', 'bottom' => 'b', 'left' => 's', 'right' => 'e'];
    $default = get_field("default_" . ($prefix == "m" ? "margin" : "padding"), "options");

    // 'default' değerlerini doldur
    foreach ($spacing as $key => $item) {
        if ($item == "default") {
            $spacing[$key] = $default[$key];
        }
    }

    // Breakpoint listesi (büyükten küçüğe sıralanmış)
    $breakpoints = array_reverse(array_keys($GLOBALS["breakpoints"]));

    foreach ($directions as $key => $short) {
        if (isset($spacing[$key])) {
            if ($spacing[$key] == 'responsive' && isset($spacing[$key . '_responsive'])) {
                $added_auto_or_none = false; // Auto veya none eklenip eklenmediğini kontrol
                foreach ($breakpoints as $breakpoint) {
                    if (isset($spacing[$key . '_responsive'][$breakpoint])) {
                        $value = $spacing[$key . '_responsive'][$breakpoint];
                        if ($value === 'auto' || $value === 'none') {
                            if (!$added_auto_or_none || $breakpoint === 'xs' || $breakpoint === 'sm') {
                                // 'xs' ve 'sm' gibi küçük breakpointler için auto/none değerlerini yine ekleyelim
                                $classes[] = "{$prefix}{$short}-{$breakpoint}-{$value}";
                                $added_auto_or_none = true;
                            }
                        } else {
                            // İlk dolu breakpoint için prefix kaldır
                            if ($breakpoint === 'xs') {
                                $classes[] = "{$prefix}{$short}-{$value}";
                            } else {
                                $classes[] = "{$prefix}{$short}-{$breakpoint}-{$value}";
                            }
                        }
                    }
                }
            } elseif ($spacing[$key] !== 'default' && !empty($spacing[$key])) {
                $classes[] = "{$prefix}{$short}-{$spacing[$key]}";
            }
        }
    }

    // Yatay ve dikey eksenleri birleştir
    $combined_axes = [
        'y' => ['t', 'b'],
        'x' => ['s', 'e']
    ];

    foreach ($combined_axes as $axis => $sides) {
        $axis_classes = [];
        foreach ($classes as $key => $class) {
            foreach ($sides as $side) {
                if (strpos($class, "{$prefix}{$side}-") === 0) {
                    $axis_classes[] = $class;
                    unset($classes[$key]);
                }
            }
        }

        $grouped = [];
        foreach ($axis_classes as $class) {
            preg_match("/{$prefix}([se|tb])-((\w+-)?\w+)$/", $class, $matches);
            if (!empty($matches)) {
                $grouped[$matches[2]][] = $matches[1];
            }
        }

        foreach ($grouped as $value => $directions) {
            if (count($directions) === 2) {
                $classes[] = "{$prefix}{$axis}-{$value}";
            } else {
                foreach ($directions as $direction) {
                    $classes[] = "{$prefix}{$direction}-{$value}";
                }
            }
        }
    }

    return $classes;
}


function block_columns($args=array(), $block = []){

    $classes = [];
    $attrs = [];
    //$code = "";
    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");
    $gap_sizes = array(
        "0" => 0,
        "1" => 4,
        "2" => 8,
        "3" => 16,
        "4" => 24,
        "5" => 48
    );

    if($args){
        if(!isset($args["slider"])){
            $args["slider"] = 0;  
        }

        if(isset($args["slider"]) || isset($args["column_breakpoints"]) ) {
            if((isset($args["slider"]) && $args["slider"]) || (isset($block["name"]) && in_array($block["name"], ["acf/archive"])) ){

                if(isset($args["column_breakpoints"])){
                    $breakpoints = new ArrayObject();
                    $gaps = new ArrayObject();
                    foreach($args["column_breakpoints"] as $key => $item){
                        if(in_array($key, $sizes)){
                            if(isset($item["columns"])){
                                $breakpoints[$key] = intval($item["columns"]);
                            }
                            if(isset($item["gx"])){
                                $gaps[$key] = $gap_sizes[$item["gx"]];
                            }
                        }
                    }
                    $attrs["data-slider-breakpoints"] = json_encode($breakpoints);
                    $attrs["data-slider-gaps"] = json_encode($gaps);                
                }
                if(isset($args["slider_settings"]) && $args["slider_settings"]){
                    foreach($args["slider_settings"] as $key => $item){
                        $attrs["data-slider-".$key] = $item;
                    }
                }
                if(isset($args["block_settings"]) && $args["block_settings"]["height"] == "ratio"){
                    $attrs["data-slider-autoheight"] = false;
                }
                
            }else if(isset($args["column_breakpoints"])){

                $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "row-cols-", "columns"));
                $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "gx-", "gx"));
                $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "gy-", "gy"));

                if(isset($args["column_breakpoints"]["masonry"]) && $args["column_breakpoints"]["masonry"]){

                    $attrs["data-masonry"] = '{"percentPosition": true }';

                }else{

                    if(isset($args["block_settings"]) && isset($args["block_settings"]["horizontal_align"]) && $args["block_settings"]["horizontal_align"]){
                        $classes = array_merge($classes, block_responsive_classes($args["block_settings"]["horizontal_align"], "justify-content-"));
                    }
                    
                }         
            }            
        }
    }
    return array(
        "class" => implode(" ", $classes),
        "attrs" => array2Attrs($attrs),
    );
}
function block_aos($args=array()){
    $attrs = [];
    if($args["animation"] != "no"){
        $attrs["data-aos"] = $args["animation"];
        $attrs["data-aos-easing"] = $args["easing"];
        $attrs["data-aos-delay"] = $args["delay"];
        $attrs["data-aos-duration"] = $args["duration"];
        //$attrs["data-aos-anchor-placement"] = $args["anchor"];
    }
    return array2Attrs($attrs);
}
function block_aos_delay($str="", $delay=0) {
    if (is_string($str) && preg_match('/data-aos-delay="(\d+)"/', $str, $matches)) {
        $new_str = preg_replace('/data-aos-delay="\d+"/', 'data-aos-delay="' . $delay . '"', $str);
        return $new_str;
    }
    return $str;
}

function block_bs_columns_col_classes($args){
    $classes = [];
    if($args){
        if(isset($args["breakpoints"])){
            $classes = array_merge($classes, block_responsive_column_classes($args["breakpoints"]));
        }
    }
    return implode(" ", $classes);
}
function block_bs_columns_rowcols_classes($args){
    $classes = [];
    if($args){
        if(isset($args["row_cols"]) && $args["row_cols"]){
           $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "row-cols-"));
        }
    }
    return implode(" ", $classes);
}


function block_bg_position_val($pos=""){
    if(!empty($pos)){
        switch($pos){
            case "center" :
                $pos = "50%";
            break;
            case "left" :
            case "top" :
                $pos = "0%";
            break;
            case "right" :
            case "bottom" :
                $pos = "100%";
            break;
        }
    }else{
        $pos = "50%";
    }
    return $pos;
}
function block_bg_image($block, $fields, $block_column){
    $image = "";
    $image_class = " w-100 h-100 ";
    $image_style = [];
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        $background_color = $fields["block_settings"]["background"]["color"];

        if(!empty($background["image"])){// && (!empty($background["image_filter"]) || !empty($background["image_blend_mode"]))){
            if(!empty($background["image_filter"])){
                if(is_array($background["image_filter_amount"])){
                   $image_filter_amount = unit_value($background["image_filter_amount"]);
                }else{
                   $image_filter_amount = $background["image_filter_amount"]."%";
                }
                if($background["image_filter"] == "opacity"){
                    $image_style[] = "opacity:" . $image_filter_amount;
                }else{
                    $image_style[] = "filter:" . $background["image_filter"] . "(" . $image_filter_amount . ")";
                }
            }
            if(!empty($background["image_blend_mode"])){
                $image_style[] = "mix-blend-mode:" . $background["image_blend_mode"];
            }
            if($background["size"] == "fixed"){
                $image_style[] = "background-size: cover";
            }else{
                $image_style[] = "background-size:" . $background["size"];
            }
            $image_style[] = "background-position:" . $background["position_hr"] ." " .$background["position_vr"];

            if($background["repeat"] != "no-repeat" || $background["size"] == "fixed"){
                $image_style[] = "background-image:url(" . $background["image"] . ");background-repeat:" . $background["repeat"] . ";";
                if($background["size"] == "fixed"){
                    $image_style[] = "background-attachment:" . $background["size"] . ";";
                }
            }
            
            if($image_style){
               $image_style = implode(";", $image_style); 
            }else{
                $image_style = "";
            }

            $classes = !empty($background["image_mask"])?block_spacing(["margin" => $background["margin_mask"]]):"";

            $image = '<div class="bg-cover position-absolute-fill '.$classes.' '.($background["parallax"]?"jarallax overflow-hidden":"").'" ';
            if($background["repeat"] != "no-repeat" || $background["size"] == "fixed"){
                $image .= 'style="'.$image_style.'"';
            }

            if($background["parallax"]){
                $image_class = " jarallax-img "; 
                $image .= ' data-jarallax data-speed="'.$background["parallax_settings"]["speed"].'" data-type="'.$background["parallax_settings"]["type"].'" data-img-size="'. $background["size"] .'" data-img-repeat="'. $background["repeat"] .'" data-img-position="'. block_bg_position_val($background["position_vr"]) ." " . block_bg_position_val($background["position_hr"]) .'" ';
                if(!empty($background_color)){
                   $image .= "style='background-color:".$background_color.";'";
                }
            }

            $image .= '>';
            if($background["repeat"] == "no-repeat" && $background["size"] != "fixed"){
                $image .= '<img src="'.$background["image"].'" class="object-fit-'.$background["size"].' '.$image_class.'" alt="'.trans("Arama Yap").'" style="'.$image_style.'"/>';
            }
            $image .= '</div>';
        }
    }
    return $image;
}
function block_bg_video($block, $fields, $block_column){
    $image = "";
    $image_class = " w-100 h-100 ";
    $image_style = [];
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        $background_color = $fields["block_settings"]["background"]["color"];
        if(!empty($background["video_file"]) || !empty($background["video_url"])){
            $video = !empty($background["video_file"])?$background["video_file"]:$background["video_url"];
            if(!empty($background["image_filter"])){
                if(is_array($background["image_filter_amount"])){
                   $image_filter_amount = unit_value($background["image_filter_amount"]);
                }else{
                   $image_filter_amount = $background["image_filter_amount"]."%";
                }
                if($background["image_filter"] == "opacity"){
                    $image_style[] = "opacity:" . $image_filter_amount;
                }else{
                    $image_style[] = "filter:" . $background["image_filter"] . "(" . $image_filter_amount . ")";
                }
                
            }
            if(!empty($background["image_blend_mode"])){
                $image_style[] = "mix-blend-mode:" . $background["image_blend_mode"];
            }
            
            $container_attr = "";
            $container_class = "";
            $video_class = "";
            if($background["parallax"]){
                $container_attr = "data-jarallax data-speed='".$background["parallax_settings"]["speed"]."' data-type='".$background["parallax_settings"]["type"]."' ";
                $container_class = "jarallax";
                $video_class = "jarallax-img";
                if($background_color){
                    $image_style[] = "background-color:".$background_color;
                }
                //$image = '<div class="jarallax" data-jarallax data-video-src="' . $video . '" data-jarallax data-speed="'.$background["parallax_settings"]["speed"].'" data-type="'.$background["parallax_settings"]["type"].'" ></div>';
            }
            $args = [];
            $args["video_type"] = $background["type"];
            
            $args["video_settings"] = array(
                "videoBg"  => true,
                "autoplay" => true,
                "loop"     => true,
                "muted"    => true,
                "ratio"    => "16:9",
                "controls" => true,
                "controls_options" => ["settings"],
                "controls_options_settings" => ["quality"],
                "controls_hide" => true,
            );
            if($background["type"] == "file"){
                $args["video_file"] = $background["video_file"];
            }else{
                $args["video_url"] = extract_url($background["video_url"]);
            }
            if($image_style){
                $image_style = implode(";", $image_style); 
            }else{
                $image_style = "";
            }

            $classes = !empty($background["image_mask"])?block_spacing(["margin" => $background["margin_mask"]]):"";

            $image = '<div '.$container_attr.' class="'.$container_class.' bg-cover '.$classes.' position-absolute-fill hide-controls" style="'.$image_style.'">';
            //$image .= get_video($background["type"], $args, $video_class, true);
            $image .= get_video(["src" => $args, "class" => $video_class, "init" => true]);
            $image .= "</div>";
        }
    }
    return $image;
}
function block_bg_slider($block, $fields, $block_column){
    $image = "";
    $image_class = " w-100 h-100 ";
    $image_style = [];
    $slider_style = [];
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        $background_color = $fields["block_settings"]["background"]["color"];
        if(!empty($background["slider"])){
            if(!empty($background["image_filter"])){
                if(is_array($background["image_filter_amount"])){
                   $image_filter_amount = unit_value($background["image_filter_amount"]);
                }else{
                   $image_filter_amount = $background["image_filter_amount"]."%";
                }
                if($background["image_filter"] == "opacity"){
                    $slider_style[] = "opacity:" . $image_filter_amount;
                }else{
                    $slider_style[] = "filter:" . $background["image_filter"] . "(" . $image_filter_amount . ")";
                }
            }
            if(!empty($background["image_blend_mode"])){
                $slider_style[] = "mix-blend-mode:" . $background["image_blend_mode"];
            }
            
            if($slider_style){
               $slider_style = implode(";", $slider_style); 
            }else{
                $slider_style = "";
            }
            
            $classes = !empty($background["image_mask"])?block_spacing(["margin" => $background["margin_mask"]]):"";

            $image = '<div class="bg-cover position-absolute-fill overflow-hidden '. $classes .' '.($background["parallax"]?"jarallax":"").'" ';
            //$image .= 'style="'.$image_style.'"';

            if($background["parallax"]){
                $image_class = " jarallax-img "; 
                $image .= ' data-jarallax data-speed="'.$background["parallax_settings"]["speed"].'" data-type="'.$background["parallax_settings"]["type"].'" data-img-size="'. $background["size"] .'" data-img-position="'. block_bg_position_val($background["position_hr"]) ." " . block_bg_position_val($background["position_vr"]) .'" ';
                if(!empty($background_color)){
                   $image .= "style='background-color:".$background_color.";'";
                }
            }

            $image .= '>';
            if($background["slider"]){
                $context = Timber::context();
                $context["slider_position"]  = "background"; 
                $context["block"]  = $block; 
                $context["fields"] = $fields; 
                $context["slider"] = $background["slider"];
                $row = block_columns($background, $block);
                if(!empty($slider_style)){
                    $row["attrs"] = $row["attrs"]." style='".$slider_style."' ";
                }
                $context["row"] = $row;
                $image .= Timber::compile("blocks/_slider.twig", $context);
            }
            $image .= '</div>';
        }
    }
    return $image;
}
function block_bg_media($block, $fields, $block_column){
    $result = "";
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        switch($background["type"]){
            case "image" :
                $result = block_bg_image($block, $fields, $block_column);
            break;

            case "slider" :
                $result = block_bg_slider($block, $fields, $block_column);
            break;

            case "file":
            case "embed":
                $result = block_bg_video($block, $fields, $block_column);
            break;

            default:
                if(!empty($background["image_mask"])){
                    $result = "<div class='bg-cover position-absolute-fill'></div>";
                }
            break;
        }
        if(!empty($background["overlay"])){
            $selector = $block["id"];
            if($block_column){
                $selector = $block_column["id"];
            }
            $result .= "<style>#".$selector." > .bg-cover:before{content:'';position:absolute;top:0;bottom:0;left:0;right:0;background-color:".$background["overlay"].";z-index:2;}</style>";
        }
    }
    return $result;
}
function block_css($block, $fields, $block_column){
    $code = "";
    $code_inner = "";
    $code_bg = "";
    $code_bg_color = "";
    $code_height = "";
    $media_query = [];

    $background = isset($fields["block_settings"]["background"])?$fields["block_settings"]["background"]:[];

    $selector = $block["id"];
    if($block_column){
        $selector = $block_column["id"];
    }
    $height = isset($fields["block_settings"]["height"])?($fields["block_settings"]["height"] == "auto" ? false : $fields["block_settings"]["height"]) : false;

    if($height){

        if($height == "ratio"){
            $ratio = block_ratio_padding($fields["block_settings"]["ratio"]);
            $code_height .= "#".$selector." > * {
                position: absolute!important;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }";
            if(!empty(block_container($fields["block_settings"]["container"]))){
                $code_inner .= "display:flex;
                    justify-content:center!important;";
                /*$code_height .= "#".$selector."{
                    display:flex;
                    justify-content:center!important;
                }\n";*/
                $code_height .= "#".$selector." > .container{
                    left:auto!important;
                    right:auto!important;
                }\n";
            }
            $code_height .= "#".$selector.":before{
                content: '';
                display: block;
                padding-top: ".$ratio."%;
            }";

        }elseif ($height == "responsive"){

            $height_responsive = isset($fields["block_settings"]["height_responsive"]) ? $fields["block_settings"]["height_responsive"] : [];
            $media_query = [];
            foreach($height_responsive as $breakpoint => $value){
                $code_height_responsive = "";

                if($value["height"] == "ratio"){
                    $ratio = block_ratio_padding($value["ratio"]);
                    $code_height_responsive .= "#".$selector." > * {
                         position: absolute!important;
                         top: 0;
                         left: 0;
                         width: 100%;
                         height: 100%;
                    }";
                    if(!empty(block_container($fields["block_settings"]["container"]))){
                        $code_inner .= "display:flex;
                            justify-content:center!important;";
                        /*$code_height .= "#".$selector."{
                            display:flex;
                            justify-content:center!important;
                        }\n";*/
                        $code_height_responsive .= "#".$selector." > .container{
                            left:auto!important;
                            right:auto!important;
                        }";
                    }
                    $padding_top = $ratio."%";
                    if($fields["block_settings"]["hero"]){
                        $padding_top = "calc(".$ratio."% - var(--header-height-".$breakpoint."))";
                        $code_height_responsive .= ".affix #".$selector.":before{
                            padding-top: calc(".$ratio."% - var(--header-height-".$breakpoint."-affix));
                        }";
                    }
                    $code_height_responsive .= "#".$selector.":before{
                        content: '';
                        display: block;
                        padding-top: ".$padding_top.";
                    }";
                }else{
                    if($value["height"] != "auto"){
                        /*$code_height .= "min-height: var(--hero-height-".$value["height"].");";*/
                        $code_height_responsive .= "#".$selector."{
                            ".($value["height"]=="full"?"":"min-")."height: calc(var(--hero-height-".$value["height"].") - 1px);
                        }";
                    }
                }
                if(!empty($code_height_responsive)){
                    $media_query[$breakpoint] = $code_height_responsive;
                }
            }
            if($media_query){
                $code_height = block_css_media_query($media_query);
            }
            

        }elseif ($height != "auto"){
            $code_inner .= ($height=="full"?"":"min-")."height: calc(var(--hero-height-".$height.") - 1px);";
            /*$code_height .= "#".$selector." {
                min-height: var(--hero-height-".$height.");
            }";*/
            
        }
    }
   
    $gradient = "";
    if(!$block_column || (isset($block_column["index"]) && $block_column["index"] == -1)){

        if(isset($block["style"]["color"]["text"]) && !empty($block["style"]["color"]["text"])){
            $code_inner .= "color:".$block["style"]["color"]["text"].";";
        }

        if(isset($block["backgroundColor"]) && !empty($block["backgroundColor"])){
            $code_inner .= "background-color:var(".$block["backgroundColor"].");";
        }elseif(isset($block["style"]["color"]["background"]) && !empty($block["style"]["color"]["background"])){
            $code_inner .= "background-color:".$block["style"]["color"]["background"].";";
        }
        
        
        if(isset($block["gradient"]) && !empty($block["gradient"])){
            $gradient .= "background:var(".$block["gradient"].");";
        }elseif(isset($block["style"]["color"]["gradient"]) && !empty($block["style"]["color"]["gradient"])){
            $gradient .= "background:".$block["style"]["color"]["gradient"].";";
        }

        if(!empty($gradient) && !$background["gradient_mask"] && !$block_column){
            $code_inner .= $gradient;
        }            

    }


    $color = isset($fields["block_settings"]["text_color"])?$fields["block_settings"]["text_color"]:"";
    if($color){
        $code_inner .= "color: ".$color.";";
    }
    
    if($background){
        if(!empty($background["color"])){
            $code_inner .= "background-color:".$background["color"].";";
        }
        if($block["name"] != "acf/hero"){
            if(!empty($background["image"])){
                if(!$background["image_filter"] && !$background["image_blend_mode"]){
                /*    $code_inner .= "background-image:url(".$background["image"].");";
                    if(!empty($background["position_hr"]) && !empty($background["position_vr"])){
                        $code_inner .="background-position:".$background["position_hr"]." ".$background["position_vr"].";";
                    }
                    if(!empty($background["repeat"])){
                        $code_inner .="background-repeat:".$background["repeat"].";";
                    }
                    if(!empty($background["size"])){
                        $code_inner .="background-size:".$background["size"].";";
                    }*/
                }else{
                    $code_inner .="position:relative;";
                }
            }            
        }
        if(!empty($background["image_mask"])){
            $mask_position = "";
            if(!empty($background["position_hr_mask"]) && !empty($background["position_vr_mask"])){
                $mask_position .="mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
                $mask_position .="-webkit-mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
            }
            if(!empty($background["repeat_mask"])){
                $mask_position .="mask-repeat:".$background["repeat_mask"].";";
                $mask_position .="-webkit-mask-repeat:".$background["repeat_mask"].";";
            }
            if(!empty($background["size_mask"])){
                $mask_position .="mask-size:".$background["size_mask"].";";
                $mask_position .="-webkit-mask-size:".$background["size_mask"].";";
            }

            if(empty($background["color_mask"]) && !$background["gradient_mask"]){

                $code_bg .= "mask: url(".$background["image_mask"].");-webkit-mask: url(".$background["image_mask"].")";
                $code_bg .= $mask_position;

            }else{

                $code_bg_color .= "mask: url(".$background["image_mask"].");-webkit-mask: url(".$background["image_mask"].");";
                $code_bg_color .= $mask_position;
                if(!empty($background["position_hr_mask"]) && !empty($background["position_vr_mask"])){
                    $code_bg_color .="mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
                    $code_bg_color .="-webkit-mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
                }
                if(!empty($background["repeat_mask"])){
                    $code_bg_color .="mask-repeat:".$background["repeat_mask"].";";
                    $code_bg_color .="-webkit-mask-repeat:".$background["repeat_mask"].";";
                }
                if(!empty($background["size_mask"])){
                    $code_bg_color .="mask-size:".$background["size_mask"].";";
                    $code_bg_color .="-webkit-mask-size:".$background["size_mask"].";";
                }

                if(!empty($gradient) && $background["gradient_mask"]){
                    $code_bg_color .= $gradient;
                }elseif(!empty($background["color_mask"])){
                    $code_bg_color .= "background-color:".$background["color_mask"].";";
                }

            }
        }
    }

    $slide_css_code = "";
    if(isset($fields["slider"]) && $fields["slider"] && is_array($fields["slider"])){
        if(count($fields["slider"]) > 0){
            foreach($fields["slider"] as $key => $slide){

                $slide_css = [];

                //filters
                if(isset($slide["filters"])){
                    $filters = $slide["filters"];
                    if(!empty($filters["image_filter"]) || !empty($filters["image_blend_mode"])){
                        if(!empty($filters["image_filter"])){
                            if(is_array($filters["image_filter_amount"])){
                               $image_filter_amount = unit_value($filters["image_filter_amount"]);
                            }else{
                               $image_filter_amount = $filters["image_filter_amount"]."%";
                            }
                            if($filters["image_filter"] == "opacity"){
                                $slide_css[] = "opacity:" . $image_filter_amount;
                            }else{
                                $slide_css[] = "filter:" . $filters["image_filter"] . "(" . $image_filter_amount . ")";
                            }
                            
                        }
                        if(!empty($filters["image_blend_mode"])){
                            $slide_css[] = "mix-blend-mode:" . $filters["image_blend_mode"];
                        }
                    }else{
                        if($slide["media_type"] == "image" && isset($slide["image"]["id"]) && empty($background["color"])){
                           $image = Timber::get_post($slide["image"]["id"]);
                           if($image){
                              $slide_css[] = "color:".$image->meta("contrast_color");
                              $slide_css[] = "background-color:".$image->meta("average_color");
                           }
                        }
                    }                    
                }else{
                    if($slide["media_type"] == "image" && isset($slide["image"]["id"]) && empty($background["color"])){
                        $image = Timber::get_post($slide["image"]["id"]);
                        if($image){
                            $slide_css[] = "color:".$image->meta("contrast_color");
                            $slide_css[] = "background-color:".$image->meta("average_color");
                        }
                    }
                }

                //overlay
                $slide_overlay_css = [];
                if($slide["overlay"]){
                    $overlay_color = isset($slide["overlay_color"]) && !empty($slide["overlay_color"])?$slide["overlay_color"]:"rgba(0,0,0,0)";
                    $overlay_color_alpha = isset($slide["overlay_color_alpha"]) && !empty($slide["overlay_color_alpha"])?$slide["overlay_color_alpha"]:"rgba(0,0,0,0)";
                    switch($slide["overlay_position"]){
                        case "top":
                            $slide_overlay_css[] = "background: linear-gradient(to bottom, ".$overlay_color." 0%, ".$overlay_color_alpha." 33.333%)";
                        break;
                        case "bottom":
                            $slide_overlay_css[] = "background: linear-gradient(to bottom, ".$overlay_color_alpha." 0%, ".$overlay_color." 33.333%)";
                        break;
                        case "left":
                            $slide_overlay_css[] = "background: linear-gradient(to right, ".$overlay_color." 0%, ".$overlay_color_alpha." 33.333%)";
                        break;
                        case "right":
                            $slide_overlay_css[] = "background: linear-gradient(to right, ".$overlay_color_alpha." 0%, ".$overlay_color." 33.333%)";
                        break;
                        case "full":
                            $slide_overlay_css[] = "background: ".$overlay_color;
                        break;
                    }
                }

                if($slide_overlay_css){
                    $slide_overlay_css = implode(";", $slide_overlay_css);
                    $slide_css_code .= "#".$selector." .slide-".($key + 1).":before{";
                       $slide_css_code .= $slide_overlay_css;
                    $slide_css_code .= "}";
                }

                if($slide_css){
                    $slide_css = implode(";", $slide_css);
                    if($background){
                        if(!empty($background["color"])){
                            $slide_css_code .= "#".$selector." .slide-".($key + 1)." .swiper-bg{";
                                $slide_css_code .= "background-color:".$background["color"];
                            $slide_css_code .= "}";
                        }                        
                    }
                    $slide_css_code .= "#".$selector." .slide-".($key + 1)." .swiper-bg > *{";
                       $slide_css_code .= $slide_css;
                    $slide_css_code .= "}";
                }
            }
        }
    }

     
    if(!empty($code_height)){
        $code .= $code_height;
    }
    if(!empty($code_inner)){
        $code .= "#".$selector." {".$code_inner."}";
    }

    if(!empty($code_bg)){
        $code .= "#".$selector." > .bg-cover{".$code_bg."}";
    }

    if(!empty($code_bg_color)){
        $code .= "#".$selector." > .bg-cover:before{content:'';position:absolute;top:0;bottom:0;left:0;right:0;z-index:1;".$code_bg_color."}";
    }

    if(!empty($slide_css_code)){
        $code .= $slide_css_code;
    }

    if(!empty($code)){
        $code = "<style type='text/css'>".$code."</style>";
    }

    return $code;
}
function block_css_media_query($query = []) {
    $css = "";
    if ($query) {
        $breakpoints = $GLOBALS["breakpoints"]; // mevcut breakpoints dizisi
        $keys = array_keys($breakpoints); // breakpoint anahtarları
        $existing_breakpoints = array_keys($query); // mevcut tanımlı query breakpoints

        // Eksik breakpointleri bulalım
        $missing_breakpoints = array_diff($keys, $existing_breakpoints);

        // Eksik breakpointler için CSS kopyalama işlemi
        foreach ($missing_breakpoints as $breakpoint) {
            // Önceki ve sonraki breakpointe göre aralık hesapla
            $index = array_search($breakpoint, $keys);
            if ($index > 0 && $index < count($breakpoints) - 1) {
                $min = $breakpoints[$keys[$index - 1]] + 1;
                $max = $breakpoints[$keys[$index + 1]] - 1;
            } elseif ($index == 0) {
                $min = 0;
                $max = $breakpoints[$keys[$index + 1]] - 1;
            } else {
                $min = $breakpoints[$keys[$index - 1]] + 1;
                $max = PHP_INT_MAX;
            }

            // Boş olmayan breakpointleri bul
            foreach ($query as $key => $code) {
                if (!empty($code)) {
                    // Uygun olan dolu breakpointi bulup kopyalayalım
                    $css .= "@media (min-width: " . $min . "px) and (max-width: " . $max . "px) {";
                    $css .= $code;  // Kopyalanacak CSS
                    $css .= "}\n";
                    break;  // Sadece bir dolu breakpoint üzerinden kopyalama yapılacak
                }
            }
        }

        // Mevcut query'lere göre CSS medya sorguları oluştur
        foreach ($query as $breakpoint => $code) {
            if (in_array($breakpoint, $keys)) {
                $index = array_search($breakpoint, $keys);
                if (empty($code)) {
                    if ($index > 0) {
                        $code = $query[$keys[$index - 1]];
                        $query[$breakpoint] = $code;
                    }
                }
                if (!empty($code)) {
                    if ($index == 0) {
                        $css .= "@media (max-width: " . ($breakpoints[$breakpoint]) . "px){";
                    } elseif ($index == count($breakpoints) - 1) {
                        $css .= "@media (min-width: " . ($breakpoints[$breakpoint]) . "px){";
                    } elseif ($index < count($breakpoints) - 1) {
                        if ($index == 1) {
                            $min = $breakpoints[$keys[$index - 1]] + 1;
                            $max = $breakpoints[$keys[$index + 1]] - 1;
                        } else {
                            $min = $breakpoints[$breakpoint];
                            $max = $breakpoints[$keys[$index + 1]] - 1;
                        }
                        $css .= "@media (min-width: " . $min . "px) and (max-width: " . $max . "px) {";
                    }
                    $css .= $code;
                    $css .= "}\n";
                }
            }
        }
    }
    return $css;
}

function block_meta($block_data=array(), $fields = array(), $extras = array(), $block_column = "", $block_column_index = -1){
    $meta = array(
        "index"     => 0,
        "id"        => "",
        "settings"  => array(),
        "classes"   => "",
        "attrs"     => "",
        "container" => "",
        "row"       => "",
        "css"       => ""
    );
    if($block_data){

        //if(isset($fields["block_settings"]["custom_id"]) && !empty($fields["block_settings"]["custom_id"])){
        if(isset($block_data["block_settings_custom_id"]) && !empty($block_data["block_settings_custom_id"])){
            $block_id = $block_data["block_settings_custom_id"];//$fields["block_settings"]["custom_id"];
        }else{
            $block_id = $block_data["id"];
        }
        $id = $block_id;
        if( $block_data["name"] == "acf/bootstrap-columns" && empty($block_column) &&
            isset($fields["acf_block_columns"][0]["acf_fc_layout"]) &&
            $fields["acf_block_columns"][0]["acf_fc_layout"] != "block-bootstrap-columns" && 
            $block_column_index > 0
        ){
            $block_column = $fields["acf_block_columns"][0]["acf_fc_layout"];
            $block_column = str_replace("block-", "", $block_column)."-sed";
        }

        if($block_column || $block_data["name"] == "acf/bootstrap-columns"){
           $id .= $block_column?"-".$fields["block_settings"]["column_id"]:"";//unique_code(5):"";
           $block_column = array(
                "block"  => $block_column?$block_column:"bootstrap-columns",
                "id"     => $id,
                "index"  => $block_column_index,
                "parent" => $block_id//$block_data["id"]
           );
        }
        $block_data["id"] = $id;

        $meta["index"]            = isset($block_data["index"])?$block_data["index"]:0;
        $meta["parent"]           = $block_column?$block_column["parent"]:0;
        $meta["id"]               = $id;//$block_data["id"].(!empty($block_column)?"-".unique_code(5):"");
        $meta["settings"]         = isset($fields["block_settings"])?$fields["block_settings"]:[];
        $meta["classes"]          = block_classes($block_data, $fields, $block_column);
        $meta["attrs"]            = block_attrs($block_data, $fields, $block_column);
        $meta["container"]        = isset($meta["settings"]["container"])?block_container($meta["settings"]["container"]):"default";
        $meta["bg_image"]         = block_bg_media($block_data, $fields, $block_column);
        $meta["data"]             = array(
                                        "align"      => $block_data["align"],
                                        "fullHeight" => $block_data["fullHeight"]
        );
       $meta["css"]              = block_css($block_data, $fields, $block_column);
       if(isset($fields["aos_settings"])){
            $meta["aos"] = block_aos($fields["aos_settings"]);
       }
       if(isset($fields["column_breakpoints"]) || isset($fields["slider_settings"])){
            $meta["row"] = block_columns($fields, $block_data);
       }
       if(isset($fields["slider_settings"])){
            $meta["container_slider"] = block_container($fields["slider_settings"]["container"]);
       }
       if(isset($block_data["_acf_context"]["post_id"])){
            $meta["post_id"] = $block_data["_acf_context"]["post_id"];
       }
       if(isset($block_data["data"]["title"])){
            $meta["title"] = $block_data["data"]["title"];
       }
       if(isset($block_data["breadcrumb"])){
            $meta["breadcrumb"] = $block_data["breadcrumb"];
       }
       if($extras){
           foreach($extras as $key => $item){
                if(in_array($key, array_keys($meta))){
                    if(in_array($key, ["classes", "container", "row", "container_slider"])){
                        $value = $meta[$key];
                        if(!empty($value)){
                            $meta[$key] = $value." ".$item;
                        }
                    }
                }
            }
        }
    }
    return $meta;
}


function page_has_block($page="", $block_name=""){
    $found = false;
     if (filter_var($page, FILTER_VALIDATE_URL)) {
        $page_id = url_to_postid($page);
    } else {
        if(is_numeric($page)){
            $page_id = $page;
        }else{
            $page_id = get_page_by_path($page)->ID;            
        }
    }
    $post_blocks = get_blocks($page_id);
    if($post_blocks){
        foreach ( $post_blocks as $key => $block ) {
            if($block["blockName"] == $block_name){
                $found = true;
            }
        }
    }
    return $found;
}
/*
function generate_custom_id( $value, $post_id, $field ) {
    if ( empty( $value ) ) {
        $value = 'block_' . md5( uniqid( '', true ) );
    }
    return $value;
}
add_filter( 'acf/load_value/name=custom_id', 'generate_custom_id', 10, 3 );
add_filter( 'acf/update_value/name=custom_id', 'generate_custom_id', 10, 3 );
*/



function generate_unique_column_id( $value, $post_id, $field ) {
    if ( empty( $value ) ) {
        $value = unique_code(5);
    }
    return $value;
}
add_filter( 'acf/load_value/name=column_id', 'generate_unique_column_id', 10, 3 );
add_filter( 'acf/update_value/name=column_id', 'generate_unique_column_id', 10, 3 );

/*
add_filter('acf/update_value/name=custom_id', function($value, $post_id, $field) {
    // Eğer alan boşsa post ID'yi veya başka bir mantıksal ID'yi kullan.
    $value = uniqid('block_'); // Bloğun DOM ID'si yerine başka bir benzersiz değer.
    error_log("custom_id:".$value);
    if (empty($value)) {
        
    }
    return $value;
}, 10, 3);

function set_custom_id_on_load( $value, $post_id, $field ) {
    // Eğer `custom_id` boşsa, yeni bir değer ata.
    if ( empty( $value ) ) {}
        // Burada istediğin ID'yi al.
        $block_id = $post_id;//uniqid('block_'); // Uniqid yerine istediğin ID’yi oluştur veya al.
        $value = $block_id;

        // Kaydetmek için update_post_meta kullan.
        update_post_meta( $post_id, $field['name'], $value );
    

    return $value;
}
add_filter( 'acf/load_value/name=custom_id', 'set_custom_id_on_load', 10, 3 );


*/
function acf_block_id_fields($post_id){
    $content = get_post_field('post_content', $post_id);
    if (empty($content)) {
        return;
    }
    error_log("blocks parsing for id set");
    $blocks = parse_blocks($content);
    $updated = false;
    foreach ($blocks as &$block) {
        if (isset($block['blockName']) && strpos($block['blockName'], 'acf/') === 0) {
            
            error_log($block['blockName']);

            $data = $block['attrs']['data'];

            $block_settings_field_id = explode("_field", $data['_block_settings_hero'])[0];

            if (!isset($data['block_settings_custom_id'])){
                $block['attrs']['data']['_block_settings_custom_id'] = $block_settings_field_id."_field_674d65b2e1dd0";
                $block['attrs']['data']['block_settings_custom_id'] = 'block_' . md5(uniqid('', true));
                error_log("block : block_settings_custom_id added");
                $updated = true;
            }
            if (!isset($data['block_settings_column_id'])){
                $block['attrs']['data']['_block_settings_column_id'] = $block_settings_field_id."_field_67213addcfaf3";
                $block['attrs']['data']['block_settings_column_id'] = unique_code(5);
                error_log("block : block_settings_column_id added");
                $updated = true;
            }
            if($block['blockName'] == "acf/bootstrap-columns"){
                foreach ($data as $key => $value) {
                    if (str_ends_with($key, '_block_settings_column_id')) {
                        if (!preg_match('/^(_|block_settings|_block_settings)/', $key)) {
                            if (empty($data[$key])) {
                                $id = unique_code(5);
                                error_log("column : ".$key."=".$id);
                                $block['attrs']['data'][$key] = $id;
                                $updated = true;
                            }
                        }
                    }
                }                  
            }
        }
    }
    if ($updated) {
        $new_content = wp_slash(serialize_blocks($blocks));
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $new_content,
        ]);
    }
}