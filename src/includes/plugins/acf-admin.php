<?php

use Timber\Timber;
use Timber\Loader;

use SaltHareket\Theme;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

function get_theme_styles($variables = array(), $root = false){
    $theme_styles = acf_get_theme_styles();
    if($theme_styles){

        $variables_mobile = [];
        $variables_media_query = [];

        $path = THEME_STATIC_PATH . 'data/theme-styles';
        if(!is_dir($path)){
            mkdir($path, 0755, true);
        }

        // Typography
        $headings_font = scss_variables_font($theme_styles["typography"]["font_family"]);
        $variables["header_font"] = $headings_font;
        $headings = $theme_styles["typography"]["headings"];
        foreach($headings as $key => $heading){
            $variables["typography_".$key."_font"] = $headings_font;
            $variables["typography_".$key."_size"] = acf_units_field_value($heading["font_size"]);
            $variables["typography_".$key."_weight"] = $heading["font_weight"];
        }

        $title_sizes = [];
        $title_mobile_sizes = [];
        $title_line_heights = [];
        $title_mobile_line_heights = [];

        foreach ($theme_styles["typography"]["title"] as $key => $breakpoint) {
            if($root){
                $title_sizes["title-".$key] = acf_units_field_value($breakpoint);
            }else{
                $title_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
            }
        }

        foreach ($theme_styles["typography"]["title_mobile"] as $key => $breakpoint) {
            if($root){
                $title_mobile_sizes["title-".$key] = acf_units_field_value($breakpoint);
            }else{
                $title_mobile_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
            }
        }

        foreach ($theme_styles["typography"]["title_line_height"] as $key => $breakpoint) {
            $line_height = acf_units_field_value($breakpoint);
            if($root){
                $title_line_heights["title-".$key."-lh"] = $line_height;
            }else{
                $title_line_heights[] = "size: $key, line-height: $line_height";
            }

            $fs = $theme_styles["typography"]["title"][$key]["value"];
            $lh = $breakpoint["value"];
            $mobile_fs = $theme_styles["typography"]["title_mobile"][$key]["value"];

            if (!empty($fs) && !empty($mobile_fs) && !empty($lh)) {
                $mobile_lh = ($mobile_fs * $lh) / $fs;
                if($root){
                    $title_mobile_line_heights["text-".$key."-lh"] = $mobile_lh."px";
                }else{
                    $title_mobile_line_heights[] = "size: $key, line-height: ".($mobile_lh)."px";
                }
            }
        }
        if($root){
            $variables = array_merge($variables, $title_sizes);
            $variables_mobile = array_merge($variables_mobile, $title_mobile_sizes);
            $variables = array_merge($variables, $title_line_heights);
            $variables_mobile = array_merge($variables_mobile, $title_mobile_line_heights);   
        }else{
            $variables["title_sizes"] = "(".implode("), (", $title_sizes).")";
            $variables["title_mobile_sizes"] = "(".implode("), (", $title_mobile_sizes).")";
            $variables["title_line_heights"] = "(".implode("), (", $title_line_heights).")";
            $variables["title_mobile_line_heights"] = "(".implode("), (", $title_mobile_line_heights).")";            
        }






        $text_sizes = [];
        $text_mobile_sizes = [];
        $text_line_heights = [];
        $text_mobile_line_heights = [];

        foreach ($theme_styles["typography"]["text"] as $key => $breakpoint) {
            if($root){
                $text_sizes["text-".$key] = acf_units_field_value($breakpoint);
            }else{
                $text_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
            }
        }

        foreach ($theme_styles["typography"]["text_mobile"] as $key => $breakpoint) {
            if($root){
                $text_mobile_sizes["text-".$key] = acf_units_field_value($breakpoint);
            }else{
                $text_mobile_sizes[] = "size: $key, font-size: ".acf_units_field_value($breakpoint);
            }
        }

        foreach ($theme_styles["typography"]["text_line_height"] as $key => $breakpoint) {
            $line_height = acf_units_field_value($breakpoint);
            if($root){
                $text_line_heights["text-".$key."-lh"] = $line_height;
            }else{
                $text_line_heights[] = "size: $key, line-height: $line_height";
            }

            $fs = $theme_styles["typography"]["text"][$key]["value"];
            $lh = $breakpoint["value"];
            $mobile_fs = $theme_styles["typography"]["text_mobile"][$key]["value"];

            if (!empty($fs) && !empty($mobile_fs) && !empty($lh)) {
                $mobile_lh = ($mobile_fs * $lh) / $fs;
                if($root){
                    $text_mobile_line_heights["text-".$key."-lh"] = $mobile_lh."px";
                }else{
                    $text_mobile_line_heights[] = "size: $key, line-height: ".($mobile_lh)."px";
                }
            }
        }
        if($root){
            $variables = array_merge($variables, $text_sizes);
            $variables_mobile = array_merge($variables_mobile, $text_mobile_sizes);
            $variables = array_merge($variables, $text_line_heights);
            $variables_mobile = array_merge($variables_mobile, $text_mobile_line_heights);  
        }else{
            $variables["text_sizes"] = "(".implode("), (", $text_sizes).")";
            $variables["text_mobile_sizes"] = "(".implode("), (", $text_mobile_sizes).")";
            $variables["text_line_heights"] = "(".implode("), (", $text_line_heights).")";
            $variables["text_mobile_line_heights"] = "(".implode("), (", $text_mobile_line_heights).")";            
        }



        if($root){
            $colors = $theme_styles["colors"];
            $variables["primary-color"] = scss_variables_color($colors["primary"]);
            $variables["secondary-color"] = scss_variables_color($colors["secondary"]);
            $variables["tertiary-color"] = scss_variables_color($colors["tertiary"]);
            $variables["quaternary-color"] = scss_variables_color($colors["quaternary"]);
            if($colors["custom"]){
                foreach($colors["custom"] as $color){
                    $variables[$color["title"]] = scss_variables_color($color["color"]);
                }
            }
        }





        // Body
        $body = $theme_styles["body"];
        $variables["font-primary"] = scss_variables_font($body["primary_font"]); //:root
        $variables["font-secondary"] = scss_variables_font($body["secondary_font"]);
        $variables["base-font-size"] = acf_units_field_value($body["font_size"]);        
        $variables["base-font-weight"] = $body["font_weight"];
        $variables["base-letter-spacing"] = acf_units_field_value($body["letter_spacing"]);
        $variables["base-font-color"] = scss_variables_color($body["color"]);
        $variables["body-bg-color"] = scss_variables_color($body["bg_color"]);
        $variables["body-bg-backdrop"] = scss_variables_color($body["backdrop_color"]);
        
        if(!$root){
            // Button Sizes
            $buttons = $theme_styles["buttons"];
            if ($buttons["custom"]) {
                $button_sizes = [];
                foreach ($buttons["custom"] as $key => $size) {
                    $button_sizes[] = "size: ".$size['size'].
                                      ", padding_x: ".acf_units_field_value($size['padding_x']).
                                      ", padding_y: ".acf_units_field_value($size['padding_y']).
                                      ", font-size: ".acf_units_field_value($size['font_size']).
                                      ", border-radius: ".acf_units_field_value($size['border_radius']);
                }
                $variables["button-sizes"] = "(".implode("), (", $button_sizes).")";
            }            
        }

        // Header
        $header = $theme_styles["header"];
        $header_general = $header["header"];
        //$variables["header-fixed"] = scss_variables_boolean($header_general["fixed"]);
        $variables["header-dropshadow"] = scss_variables_boolean($header_general["dropshadow"]);
        //$variables["header-hide-on-scroll-down"] = scss_variables_boolean($header_general["hide_on_scroll_down"]);
        $variables["header-z-index"] = $header_general["z_index"];
        $variables["header-bg"] = scss_variables_color($header_general["bg_color"]);
        $variables["header-bg-affix"] = scss_variables_color($header_general["bg_color_affix"]);

        $variables["header-height"] = acf_units_field_value($header_general["height"][array_keys($header_general["height"])[0]]);

        $header_height = [];
        foreach($header_general["height"] as $key => $breakpoint){
            if($root){
                $header_height[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-height-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-height"] = $header_height;
        }

        $variables["header-height-affix"] = acf_units_field_value($header_general["height_affix"][array_keys($header_general["height_affix"])[0]]);
        $header_height_affix = [];
        foreach($header_general["height_affix"] as $key => $breakpoint){
            if($root){
                $header_height_affix[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-height-".$key."-affix"] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-height-affix"] = $header_height_affix;
        }


        // Nav Bar
        $header_navbar = $header["navbar"];
        $variables["header-navbar-bg"] = scss_variables_color($header_navbar["bg_color"]);
        $variables["header-navbar-bg-affix"] = scss_variables_color($header_navbar["bg_color_affix"]);
        $variables["header-navbar-align-hr"] = $header_navbar["align_hr"];
        $variables["header-navbar-align-vr"] = $header_navbar["align_vr"];

            $height_header = $header_navbar["height_header"]; // is same with header

        $variables["header-navbar-height"] = acf_units_field_value($header_navbar["height"][array_keys($header_navbar["height"])[0]]);
        $header_navbar_height = [];
        foreach($header_navbar["height"] as $key => $breakpoint){
            if($root){
                $header_navbar_height[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-navbar-height-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-height"] = $header_navbar_height;
        }
        
        $variables["header-navbar-height-affix"] = acf_units_field_value($header_navbar["height_affix"][array_keys($header_navbar["height_affix"])[0]]);
        $header_navbar_height_affix = [];
        foreach($header_navbar["height_affix"] as $key => $breakpoint){
            if($root){
                $header_navbar_height_affix[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-navbar-height-".$key."-affix"] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-height-affix"] = $header_navbar_height_affix;
        }
       
        $variables["header-navbar-padding"] = $header_navbar["padding"][array_keys($header_navbar["padding"])[0]];
        $header_navbar_padding = [];
        foreach($header_navbar["padding"] as $key => $breakpoint){
            if($root){
                $header_navbar_padding[$key] = scss_variables_padding($breakpoint);
            }else{
                $variables["header-navbar-padding-".$key] = scss_variables_padding($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-padding"] = $header_navbar_padding;
        }

        $variables["header-navbar-padding-affix"] = $header_navbar["padding_affix"][array_keys($header_navbar["padding_affix"])[0]];
        $header_navbar_padding_affix = [];
        foreach($header_navbar["padding_affix"] as $key => $breakpoint){
            if($root){
                $header_navbar_padding_affix[$key] = scss_variables_padding($breakpoint);
            }else{
                $variables["header-navbar-padding-".$key."-affix"] = scss_variables_padding($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-padding-affix"] = $header_navbar_padding_affix;
        }



        // Nav
        $header_nav = $header["nav"];
        $variables["header-navbar-nav-width"] = $header_nav["width"];
        $variables["header-navbar-nav-margin"] = $header_nav["margin"];

        $variables["header-navbar-nav-align-hr"] = $header_nav["align_hr"][array_keys($header_nav["align_hr"])[0]];
        $header_navbar_nav_align_hr = [];
        foreach($header_nav["align_hr"] as $key => $breakpoint){
            if($root){
                $header_navbar_nav_align_hr[$key] = $breakpoint;
            }else{
                $variables["header-navbar-nav-align-hr-".$key] = $breakpoint;
            }
        }
        if($root){
            $variables_media_query["header-navbar-nav-align-hr"] = $header_navbar_nav_align_hr;
        }

        $variables["header-navbar-nav-align-vr"] = $header_nav["align_vr"][array_keys($header_nav["align_vr"])[0]];
        $header_navbar_nav_align_vr = [];
        foreach($header_nav["align_vr"] as $key => $breakpoint){
             if($root){
                $header_navbar_nav_align_vr[$key] = $breakpoint;
            }else{
                $variables["header-navbar-nav-align-vr-".$key] = $breakpoint;
            }
        }
        if($root){
            $variables_media_query["header-navbar-nav-align-vr"] = $header_navbar_nav_align_vr;
        }

            $height_header = $header_nav["height_header"]; // is same with header

        $variables["header-navbar-nav-height"] = acf_units_field_value($header_nav["height"][array_keys($header_nav["height"])[0]]);
        $header_navbar_nav_height = [];
        foreach($header_nav["height"] as $key => $breakpoint){
            if($root){
                $header_navbar_nav_height[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-navbar-nav-height-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-nav-height"] = $header_navbar_nav_height;
        }


        $variables["header-navbar-nav-height-affix"] = acf_units_field_value($header_nav["height_affix"][array_keys($header_nav["height_affix"])[0]]);
        $header_navbar_nav_height_affix = [];
        foreach($header_nav["height_affix"] as $key => $breakpoint){
            if($root){
                $header_navbar_nav_height_affix[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-navbar-nav-height-".$key."-affix"] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-nav-height-affix"] = $header_navbar_nav_height_affix;
        }


        // Nav Item
        $header_nav_item = $header["nav_item"];
        $variables["header-navbar-nav-font"] = scss_variables_font($header_nav_item["font_family"]);
        $variables["nav_font"] = scss_variables_font($header_nav_item["font_family"]);
        $variables["header-navbar-nav-font-weight"] = $header_nav_item["font_weight"];
        $variables["header-navbar-nav-font-weight-active"] = $header_nav_item["font_weight_active"];
        $variables["header-navbar-nav-font-text-transform"] = $header_nav_item["text_transform"];
        $variables["header-navbar-nav-font-letter-spacing"] = acf_units_field_value($header_nav_item["letter_spacing"]);
        $variables["header-navbar-nav-font-color"] = scss_variables_color($header_nav_item["color"]);
        $variables["header-navbar-nav-font-color-hover"] = scss_variables_color($header_nav_item["color_hover"]);
        $variables["header-navbar-nav-font-color-active"] = scss_variables_color($header_nav_item["color_active"]);
        $variables["header-navbar-nav-bg-color"] = scss_variables_color($header_nav_item["bg_color"]);
        $variables["header-navbar-nav-bg-color-hover"] = scss_variables_color($header_nav_item["bg_color_hover"]);

        $variables["header-navbar-nav-item-padding"] = $header_nav_item["padding"][array_keys($header_nav_item["padding"])[0]];
        $header_navbar_nav_item_padding = [];
        foreach($header_nav_item["padding"] as $key => $breakpoint){
            if($root){
                $header_navbar_nav_item_padding[$key] = scss_variables_padding($breakpoint);
            }else{
                $variables["header-navbar-nav-item-padding-".$key] = scss_variables_padding($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-nav-item-padding"] = $header_navbar_nav_item_padding;
        }


        $variables["header-navbar-nav-font-size"] = acf_units_field_value($header_nav_item["font_size"][array_keys($header_nav_item["font_size"])[0]]);
        $header_navbar_nav_font_size = [];
        foreach($header_nav_item["font_size"] as $key => $breakpoint){
            if($root){
                $header_navbar_nav_font_size[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-navbar-nav-font-size-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-navbar-nav-font-size"] = $header_navbar_nav_font_size;
        }


        // Dropdown
        $header_dropdown = $header["dropdown"];
        $header_dropdown_arrow = $header_dropdown["arrow"];
        $variables["header-navbar-nav-dropdown-root-arrow"] = scss_variables_boolean($header_dropdown_arrow["arrow"]);
        $variables["header-navbar-nav-dropdown-root-arrow-top"] = $header_dropdown_arrow["top"];
        $variables["header-navbar-nav-dropdown-root-arrow-left"] = $header_dropdown_arrow["left"];

        $header_dropdown_general = $header_dropdown["dropdown"];
        $variables["header-navbar-nav-dropdown-align"] = $header_dropdown_general["align_vr"];
        $variables["header-navbar-nav-dropdown-bg"] = scss_variables_color($header_dropdown_general["bg_color"]);
        $variables["header-navbar-nav-dropdown-width"] = $header_dropdown_general["width"];
        $variables["header-navbar-nav-dropdown-margin"] = $header_dropdown_general["margin"];
        $variables["header-navbar-nav-dropdown-top"] = $header_dropdown_general["top"];
        $variables["header-navbar-nav-dropdown-padding"] = $header_dropdown_general["padding"];
        $variables["header-navbar-nav-dropdown-border"] = $header_dropdown_general["border"];
        $variables["header-navbar-nav-dropdown-border-radius"] = $header_dropdown_general["border_radius"];

        $header_dropdown_item = $header_dropdown["dropdown_item"];
        $variables["header-navbar-nav-dropdown-font-size"] = acf_units_field_value($header_dropdown_item["font_size"]);
        $variables["header-navbar-nav-dropdown-font-color"] = scss_variables_color($header_dropdown_item["color"]);
        $variables["header-navbar-nav-dropdown-font-color-hover"] = scss_variables_color($header_dropdown_item["color_hover"]);
        $variables["header-navbar-nav-dropdown-font-weight"] = $header_dropdown_item["font_weight"];
        $variables["header-navbar-nav-dropdown-font-weight-hover"] = $header_dropdown_item["font_weight_hover"];
        $variables["header-navbar-nav-dropdown-font-text-transform"] = $header_dropdown_item["text_transform"];
        $variables["header-navbar-nav-dropdown-item-padding"] = $header_dropdown_item["padding"];
        $variables["header-navbar-nav-dropdown-item-bg"] = scss_variables_color($header_dropdown_item["bg_color"]);
        $variables["header-navbar-nav-dropdown-item-bg-hover"] = scss_variables_color($header_dropdown_item["bg_color_hover"]);
        $variables["header-navbar-nav-dropdown-item-border"] = $header_dropdown_item["border"];
        $variables["header-navbar-nav-dropdown-item-border-radius"] = $header_dropdown_item["border_radius"];

        // Logo
        $header_logo = $header["logo"];
        $variables["header-navbar-logo-color"] = scss_variables_color($header_logo["color"]);
        $variables["header-navbar-logo-color-affix"] = scss_variables_color($header_logo["color_affix"]);
        $variables["header-navbar-logo-align-hr"] = $header_logo["align_hr"];
        $variables["header-navbar-logo-align-vr"] = $header_logo["align_vr"];


        $variables["header-navbar-logo-padding"] = $header_logo["padding"][array_keys($header_logo["padding"])[0]];
        $header_navbar_logo_padding = [];
        foreach($header_logo["padding"] as $key => $breakpoint){
            if($root){
                $header_navbar_logo_padding[$key] = $breakpoint;
            }else{
                $variables["header-navbar-logo-padding-".$key] = $breakpoint;
            }
        }
        if($root){
            $variables_media_query["header-navbar-logo-padding"] = $header_navbar_logo_padding;
        }



        $variables["header-navbar-logo-padding-affix"] = $header_logo["padding_affix"][array_keys($header_logo["padding_affix"])[0]];
        $header_navbar_logo_padding_affix = [];
        foreach($header_logo["padding_affix"] as $key => $breakpoint){
            if($root){
                $header_navbar_logo_padding_affix[$key] = $breakpoint;
            }else{
                $variables["header-navbar-logo-padding-".$key."-affix"] = $breakpoint;
            }
        }
        if($root){
            $variables_media_query["header-navbar-logo-padding-affix"] = $header_navbar_logo_padding_affix;
        }



        // Footer
        $footer = $theme_styles["footer"];
        $variables["footer-height"] = acf_units_field_value($footer["height"]);
        $variables["footer-padding"] = $footer["padding"];
        $variables["footer-color"] = scss_variables_color($footer["color"]);
        $variables["footer-color-link"] = scss_variables_color($footer["link_color"]);
        $variables["footer-color-link-hover"] = scss_variables_color($footer["link_color_hover"]);
        $variables["footer-bg-color"] = scss_variables_color($footer["bg_color"]);
        $variables["footer-bg-image"] = scss_variables_image($footer["bg_image"]);


        // Breadcrumb
        $breadcrumb = $theme_styles["breadcrumb"];
        $variables["breadcrumb-item-font-family"] = scss_variables_font($breadcrumb["font_family"]);
        $variables["breadcrumb-item-font-size"] = acf_units_field_value($breadcrumb["font_size"]);
        $variables["breadcrumb-item-font-weight"] = $breadcrumb["font_weight"];
        $variables["breadcrumb-item-line-height"] = $breadcrumb["line_height"] ?? "inherit";
        $variables["breadcrumb-item-letter-spacing"] = acf_units_field_value($breadcrumb["letter_spacing"]);
        $variables["breadcrumb-item-text-transform"] = $breadcrumb["text_transform"];
        $variables["breadcrumb-item-color"] = scss_variables_color($breadcrumb["color"]);
        $variables["breadcrumb-item-color-hover"] = scss_variables_color($breadcrumb["color_hover"]);
        $variables["breadcrumb-sep-color"] = scss_variables_color($breadcrumb["seperator_color"]);


        // Pagination
        $pagination = $theme_styles["pagination"];
        $pagination_general = $pagination["pagination"];
        $variables["pagination-align"] = $pagination_general["align_vr"];

        $pagination_item = $pagination["item"];
        $variables["pagination-font-family"] = scss_variables_font($pagination_item["font_family"]);
        $variables["pagination-font-size"] = acf_units_field_value($pagination_item["font_size"]);
        $variables["pagination-font-weight"] = $pagination_item["font_weight"];
        $variables["pagination-font-weight-active"] = $pagination_item["font_weight_active"];
        $variables["pagination-item-color"] = scss_variables_color($pagination_item["color"]);
        $variables["pagination-item-color-hover"] = scss_variables_color($pagination_item["color_hover"]);
        $variables["pagination-item-color-active"] = scss_variables_color($pagination_item["color_active"]);
        $variables["pagination-item-bg-color"] = scss_variables_color($pagination_item["bg_color"]);
        $variables["pagination-item-bg-color-hover"] = scss_variables_color($pagination_item["bg_color_hover"]);
        $variables["pagination-item-bg-color-active"] = scss_variables_color($pagination_item["bg_color_active"]);
        $variables["pagination-item-border"] = $pagination_item["border"];
        $variables["pagination-item-border-hover"] = $pagination_item["border_hover"];
        $variables["pagination-item-border-active"] = $pagination_item["border_active"];
        $variables["pagination-item-border-radius"] = $pagination_item["border_radius"] ?? 0;

        $pagination_nav= $pagination["nav"];
        $variables["pagination-nav-font-family"] = scss_variables_font($pagination_nav["font_family"]);
        $variables["pagination-nav-font-size"] = acf_units_field_value($pagination_nav["font_size"]);
        $variables["pagination-nav-color"] = scss_variables_color($pagination_nav["color"]);
        $variables["pagination-nav-color-hover"] = scss_variables_color($pagination_nav["color_hover"]);
        $variables["pagination-nav-color-disabled"] = scss_variables_color($pagination_nav["color_disabled"]);
        $variables["pagination-nav-bg-color"] = scss_variables_color($pagination_nav["bg_color"]);
        $variables["pagination-nav-bg-color-hover"] = scss_variables_color($pagination_nav["bg_color_hover"]);
        $variables["pagination-nav-border"] = $pagination_nav["border"];
        $variables["pagination-nav-border-hover"] = $pagination_nav["border_hover"];
        $variables["pagination-nav-border-active"] = $pagination_nav["border_active"];
        $variables["pagination-nav-border-radius"] = acf_units_field_value($pagination_nav["border_radius"]);
        $variables["pagination-nav-prev-text"] = $pagination_nav["prev_text"];
        $variables["pagination-nav-next-text"] = $pagination_nav["next_text"];
        $variables["pagination-item-gap"] = acf_units_field_value($pagination_nav["gap"]);


        // Hero
        $hero = $theme_styles["hero"];
        $variables["hero-height"] = acf_units_field_value($hero["height"][array_keys($hero["height"])[0]]);
        $hero_height = [];
        foreach($hero["height"] as $key => $breakpoint){
            if($root){
                $hero_height[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["hero-height-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["hero-height"] = $hero_height;
        }


        // Offcanvas
        $offcanvas = $theme_styles["offcanvas"];
        $offcanvas_general = $offcanvas["offcanvas"];
        $variables["offcanvas-bg"] = scss_variables_color($offcanvas_general["bg_color"]);
        $variables["offcanvas-padding"] = $offcanvas_general["padding"];
        $variables["offcanvas-align-hr"] = $offcanvas_general["align_hr"];
        $variables["offcanvas-align-vr"] = $offcanvas_general["align_vr"];

        $offcanvas_header = $offcanvas["header"];
        $variables["offcanvas-header-font"] = scss_variables_font($offcanvas_header["font_family"]);
        $variables["offcanvas-header-font-size"] = acf_units_field_value($offcanvas_header["font_size"]);
        $variables["offcanvas-header-font-weight"] = $offcanvas_header["font_weight"];
        $variables["offcanvas-header-color"] = scss_variables_color($offcanvas_header["color"]);
        $variables["offcanvas-header-padding"] = $offcanvas_header["padding"];
        $variables["offcanvas-header-icon-font-size"] = acf_units_field_value($offcanvas_header["icon_font_size"]);
        $variables["offcanvas-header-icon-color"] = scss_variables_color($offcanvas_header["icon_color"]);

        $offcanvas_nav_item = $offcanvas["nav_item"];
        $variables["offcanvas-item-font"] = scss_variables_font($offcanvas_nav_item["font_family"]);
        $variables["offcanvas-item-font-size"] = acf_units_field_value($offcanvas_nav_item["font_size"]);
        $variables["offcanvas-item-font-weight"] = $offcanvas_nav_item["font_weight"];
        $variables["offcanvas-item-color"] = scss_variables_color($offcanvas_nav_item["color"]);
        $variables["offcanvas-item-color-hover"] = scss_variables_color($offcanvas_nav_item["color_hover"]);
        $variables["offcanvas-item-bg"] = scss_variables_color($offcanvas_nav_item["bg_color"]);
        $variables["offcanvas-item-bg-hover"] = scss_variables_color($offcanvas_nav_item["bg_color_hover"]);
        $variables["offcanvas-item-padding"] = $offcanvas_nav_item["padding"];
        $variables["offcanvas-item-align-hr"] = $offcanvas_nav_item["align_hr"];

        $offcanvas_nav_sub = $offcanvas["nav_sub"];
        $variables["offcanvas-dropdown-bg"] = scss_variables_color($offcanvas_nav_sub["bg_color"]);
        $variables["offcanvas-dropdown-padding"] = $offcanvas_nav_sub["padding"];

        $offcanvas_nav_sub_item = $offcanvas["nav_sub_item"];
        $variables["offcanvas-dropdown-item-font-family"] = scss_variables_font($offcanvas_nav_sub_item['font_family'] ?? null);
        $variables["offcanvas-dropdown-item-font-size"] = acf_units_field_value($offcanvas_nav_sub_item["font_size"]);
        $variables["offcanvas-dropdown-item-font-color"] = scss_variables_color($offcanvas_nav_sub_item["color"]);
        $variables["offcanvas-dropdown-item-font-color-hover"] = scss_variables_color($offcanvas_nav_sub_item["color_hover"]);
        $variables["offcanvas-dropdown-item-font-weight"] = $offcanvas_nav_sub_item["font_weight"];
        $variables["offcanvas-dropdown-item-font-weight-hover"] = $offcanvas_nav_sub_item["font_weight_hover"];
        $variables["offcanvas-dropdown-item-padding"] = $offcanvas_nav_sub_item["padding"];
        $variables["offcanvas-dropdown-item-bg"] = scss_variables_color($offcanvas_nav_sub_item["bg_color"]);
        $variables["offcanvas-dropdown-item-bg-hover"] = scss_variables_color($offcanvas_nav_sub_item["bg_color_hover"]);
        $variables["offcanvas-dropdown-item-border"] = $offcanvas_nav_sub_item["border"];


        // Header Tools
        $header_tools = $theme_styles["header_tools"];
        $header_tools_general = $header_tools["header_tools"];

            $height_header = $header_tools_general["height_header"]; // is same with header

        $variables["header-tools-height"] = acf_units_field_value($header_tools_general["height"][array_keys($header_tools_general["height"])[0]]);
        $header_tools_height = [];
        foreach($header_tools_general["height"] as $key => $breakpoint){
            if($root){
                $header_tools_height[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-tools-height-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-tools-height"] = $header_tools_height;
        }

        $variables["header-tools-height-affix"] = acf_units_field_value($header_tools_general["height_affix"][array_keys($header_tools_general["height_affix"])[0]]);
        $header_tools_height_affix = [];
        foreach($header_tools_general["height_affix"] as $key => $breakpoint){
            if($root){
                $header_tools_height_affix[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-tools-height-".$key."-affix"] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-tools-height-affix"] = $header_tools_height_affix;
        }

        $variables["header-tools-item-gap"] = acf_units_field_value($header_tools_general["gap"][array_keys($header_tools_general["gap"])[0]]);
        $header_tools_item_gap = [];
        foreach($header_tools_general["gap"] as $key => $breakpoint){
            if($root){
                $header_tools_item_gap[$key] = acf_units_field_value($breakpoint);
            }else{
                $variables["header-tools-item-gap-".$key] = acf_units_field_value($breakpoint);
            }
        }
        if($root){
            $variables_media_query["header-tools-item-gap"] = $header_tools_item_gap;
        }

        $header_tools_social = $header_tools["social"];
        $variables["header-social-font"] = scss_variables_font($header_tools_social["font_family"]);
        $variables["header-social-font-size"] = acf_units_field_value($header_tools_social["font_size"]);
        $variables["header-social-color"] = scss_variables_color($header_tools_social["color"]);
        $variables["header-social-color-hover"] = scss_variables_color($header_tools_social["color_hover"]);
        $variables["header-social-gap"] = acf_units_field_value($header_tools_social["gap"]);

        $header_tools_icons = $header_tools["icons"];
        $variables["header-icon-font"] = scss_variables_font($header_tools_icons["font_family"]);
        $variables["header-icon-font-size"] = acf_units_field_value($header_tools_icons["font_size"]);
        $variables["header-icon-color"] = scss_variables_color($header_tools_icons["color"]);
        $variables["header-icon-color-hover"] = scss_variables_color($header_tools_icons["color_hover"]);
        $variables["header-icon-dot-color"] = scss_variables_color($header_tools_icons["dot_color"]);

        $header_tools_link = $header_tools["link"];
        $variables["header-link-font"] = scss_variables_font($header_tools_link["font_family"]);
        $variables["header-link-font-size"] = acf_units_field_value($header_tools_link["font_size"]);
        $variables["header-link-font-weight"] = $header_tools_link["font_weight"];
        $variables["header-link-color"] = scss_variables_color($header_tools_link["color"]);
        $variables["header-link-color-hover"] = scss_variables_color($header_tools_link["color_hover"]);
        $variables["header-link-color-active"] = scss_variables_color($header_tools_link["color_active"]);

        $header_tools_button = $header_tools["button"];
        $variables["header-btn-font"] = scss_variables_font($header_tools_button["font_family"]);
        $variables["header-btn-font-size"] = acf_units_field_value($header_tools_button["font_size"]);
        $variables["header-btn-font-weight"] = $header_tools_button["font_weight"];

        $header_tools_language = $header_tools["language"];
        $variables["header-language-font"] = scss_variables_font($header_tools_language["font_family"]);
        $variables["header-language-font-size"] = acf_units_field_value($header_tools_language["font_size"]);
        $variables["header-language-font-weight"] = $header_tools_language["font_weight"];
        $variables["header-language-color"] = scss_variables_color($header_tools_language["color"]);
        $variables["header-language-color-hover"] = scss_variables_color($header_tools_language["color_hover"]);
        $variables["header-language-color-active"] = scss_variables_color($header_tools_language["color_active"]);

        $header_tools_toggler = $header_tools["toggler"];
        $variables["header-navbar-toggler-color"] = scss_variables_color($header_tools_toggler["color"]);
        $variables["header-navbar-toggler-color-hover"] = scss_variables_color($header_tools_toggler["color_hover"]);

        $header_tools_counter = $header_tools["counter"];
        $variables["notification-count-color"] = scss_variables_color($header_tools_counter["color"]);
        $variables["notification-count-bg-color"] = scss_variables_color($header_tools_counter["bg_color"]);

        $variables["breakpoints"] = "'" . implode(",", array_keys($GLOBALS["breakpoints"])) . "'";

        //Utilities
        $scroll_to_top = $theme_styles["utilities"]["scroll_to_top"];
        $variables["scroll-to-top-active"] = $scroll_to_top["active"];
        if($scroll_to_top["active"]){
            $variables["scroll-to-top-show"] = $scroll_to_top["show"];
            $variables["scroll-to-top-hr"] = $scroll_to_top["position_hr"];
            $variables["scroll-to-top-vr"] = $scroll_to_top["position_vr"];
            $variables["scroll-to-top-bg-color"] = $scroll_to_top["bg_color"];
            $variables["scroll-to-top-bg-color-hover"] = $scroll_to_top["bg_color_hover"];
            $variables["scroll-to-top-color"] = $scroll_to_top["color"];
            $variables["scroll-to-top-color-hover"] = $scroll_to_top["color_hover"];
            $variables["scroll-to-top-width"] = $scroll_to_top["width"];
            $variables["scroll-to-top-height"] = $scroll_to_top["height"];
            $variables["scroll-to-top-radius"] = acf_units_field_value($scroll_to_top["radius"]);
            $variables["scroll-to-top-gap"] = acf_units_field_value($scroll_to_top["gap"]);
            $variables["scroll-to-top-font-size"] = acf_units_field_value($scroll_to_top["font_size"]);
            $variables["scroll-to-top-duration"] = $scroll_to_top["duration"];            
        }

        $pattern = '/class="([^"]*)"/';
        $classes = [];
        if (preg_match($pattern, $scroll_to_top["icon"], $matches)) {
            if (!empty($matches[1])) {
                $classes = explode(' ', $matches[1]);
            }
        }
        update_dynamic_css_whitelist($classes);

        if($root){
            $styles = array_to_root_styles($variables, $variables_mobile, $variables_media_query);
            file_put_contents(STATIC_PATH . "css/root.css", $styles);
            return false;
        }
    }
    return $variables;
}


function acf_set_thumbnail_condition($post_id){
    $post_types = get_post_types(); // Tüm kayıtlı post tiplerini al
    $supported_post_types = []; // Thumbnail desteği olanları burada tut
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'thumbnail')) {
            $supported_post_types[] = $post_type; // Thumbnail desteği varsa listeye ekle
        }
    }
    $post_type = get_post_type( $post_id );
    if(in_array($post_type, $supported_post_types)){
        return true;
    }else{
        return false;
    }
}


add_filter('acf/validate_value', function ($valid, $value, $field, $input) {
    if (!$valid) return $valid;
    if ($field['type'] !== 'flexible_content') {
        return $valid;
    }
    if (!isset($field['wrapper']['class']) || strpos($field['wrapper']['class'], 'once') === false) {
        return $valid;
    }
    $usedLayouts = [];
    foreach ($value as $row) {
        $layout = $row['acf_fc_layout'];
        if (isset($usedLayouts[$layout])) {
            return 'Yalnızca bir adet "<strong>'.$layout.'<strong>" ekleyebilirsin!';
        }
        $usedLayouts[$layout] = true;
    }
    return $valid;
}, 10, 4);


add_filter('acf/load_value/type=file', function($value, $post_id, $field) {
    if (!empty($value) && get_post_status($value) === false) {
        return null; // Eğer dosya yoksa alanı boş yap
    }
    return $value; // Eğer dosya varsa, normal değerini döndür
}, 10, 3);


add_action('acf/save_post', 'on_acf_post_pagination_saved', 20);
function on_acf_post_pagination_saved($post_id) {
    // Sadece options sayfasıysa çalış
    if ($post_id !== 'options') {
        return;
    }

    // Değeri güvenle al
    $pagination_items = get_field('post_pagination', 'option');

    if (is_array($pagination_items)) {
        foreach ($pagination_items as $item) {
            if (
                isset($item['post_type'], $item['paged']) &&
                $item['post_type'] === 'product' &&
                !empty($item['paged'])
            ) {
                // WooCommerce seçeneklerini güncelle
                update_option("woocommerce_catalog_columns", $item["catalog_columns"] ?? 4);
                update_option("woocommerce_catalog_rows", $item["catalog_rows"] ?? 3);

                error_log("WooCommerce ayarları güncellendi.");
            }
        }
    } else {
        error_log("post_pagination alanı boş ya da array değil.");
    }
}


// Google maps
add_filter('acf/update_value/name=map_url', 'acf_map_embed_update', 10, 3);
function acf_map_embed_update( $value, $post_id, $field ) {
    if(strpos($value, "<iframe ") !== false){
        $value = preg_replace('/\\\\/', '', $value);
        $value = get_iframe_src( $value );
    }
    return $value;
}

add_action('acf/update_value', 'acf_map_lat_lng', 99, 3 ); 
function acf_map_lat_lng( $value, $post_id, $field ) {
    if( 'google_map' === $field['type']){
        if( 'map' === $field['name'] ) {
            update_post_meta( $post_id, 'lat', $value['lat'] );
            update_post_meta( $post_id, 'lng', $value['lng'] );
        }
        if( 'lat' === $field['name'] && isset($value['lat']) ) {
            update_post_meta( $post_id, 'lat', $value['lat'] );
        }
        if( 'lng' === $field['name'] && isset($value['lng']) ) {
            update_post_meta( $post_id, 'lng', $value['lng'] );
        }
    }
    return $value;
}
function acf_get_coordinates_from_embed_url($url){
    $coordinates = array();
    // Koordinatları çıkarmak için regex deseni
    $pattern = '/!3d([0-9.]+)!2d([0-9.]+)/';

    // Embed kodundan enlem ve boylam koordinatlarını çıkarın
    preg_match($pattern, $url, $matches);

    if (count($matches) >= 3) {
        $coordinates["lat"] = $matches[1];
        $coordinates["lng"] = $matches[2];
        return $coordinates;
    } 
    return false;
}


// General Settings Condition
add_filter('acf/load_field/name=enable_ecommerce', 'acf_general_option_enable_ecommerce');
function acf_general_option_enable_ecommerce($field) {
    if (ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=enable_cart', 'acf_general_option_enable_cart');
function acf_general_option_enable_cart($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=myaccount_page_id', 'acf_general_option_myaccount_page_id');
function acf_general_option_myaccount_page_id($field) {
    if (ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}



add_filter('acf/load_field/name=enable_woo_api', 'acf_general_option_enable_woo_api');
function acf_general_option_enable_woo_api($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=breadcrumb_add_product_brand', 'acf_general_option_breadcrumb_add_product_brand');
function acf_general_option_breadcrumb_add_product_brand($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=breadcrumb_add_product_taxonomy', 'acf_general_option_breadcrumb_add_product_taxonomy');
function acf_general_option_breadcrumb_add_product_taxonomy($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_filter('acf/load_field/name=remove_woocommerce_styles', 'acf_general_option_remove_woocommerce_styles');
function acf_general_option_remove_woocommerce_styles($field) {
    if (!ENABLE_ECOMMERCE) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}


function acf_generate_id($length = 12) {
    $characters = '0123456789abcdef';
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $random_index = mt_rand(0, strlen($characters) - 1);
        $id .= $characters[$random_index];
    }
    return $id;
}

function acf_get_raw_value($post_id, $field_name, $field_group_name, $index=0){ // $index required for repeater
    if(isset($field_group_name)){
        $index = isset($index)?$index."_":"";
        $meta_key = $field_group_name."_".$index.$field_name;
    }else{
        $meta_key = $field_name;
    }
    global $wpdb;
    $value = $wpdb->get_var("select meta_value from wp_postmeta where post_id=".$post_id." and meta_key='".$meta_key."'");
    if(!empty($value) && ENABLE_MULTILANGUAGE == "qtranslate-xt"){
        $lang = qtranxf_getLanguage();
        $value = qtranxf_split($value);
        if(isset($value[$lang])){
            $value = $value[$lang];
        }
    }
    return $value;
}



function acf_admin_colors_footer() { 
    $colors = [];
    $colors_file = THEME_STATIC_PATH . 'data/colors_mce.json';
    if(file_exists($colors_file)){
        $colors = file_get_contents($colors_file);
        $colors = json_decode($colors, true);
        if($colors){
            $colors = array_keys($colors);
        }
    }
    ?>
    <script type="text/javascript">
    (function($) {
        acf.add_filter('color_picker_args', function( args, $field ){
            <?php 
            if($colors){
            ?>
                let colors = <?php echo json_encode($colors);?>;
            <?php
            }else{
            ?>
                let colors = [];
                let obj = getComputedStyle(document.documentElement);
                let custom_colors = obj.getPropertyValue('--salt-colors').trim();
                if(!IsBlank(custom_colors)){
                    custom_colors = custom_colors.split(",");
                    custom_colors.forEach(color => {
                        colors.push(obj.getPropertyValue('--bs-'+color.trim()).trim());
                    });
                }
            <?php 
            }
            ?>
            args.palettes = colors
            return args;
        });
    })(jQuery);
    </script>
<?php }
add_action('acf/input/admin_footer', 'acf_admin_colors_footer');



if(ENABLE_ECOMMERCE){
    //another solutions for below:
    //https://remicorson.com/mastering-woocommerce-products-custom-fields/
    //https://remicorson.com/woocommerce-custom-fields-for-variations/

    // Render fields at the bottom of variations - does not account for field group order or placement.
    add_action( 'woocommerce_product_after_variable_attributes', function( $loop, $variation_data, $variation ) {
        global $abcdefgh_i; // Custom global variable to monitor index
        $abcdefgh_i = $loop;
        // Add filter to update field name
        add_filter( 'acf/prepare_field', 'acf_prepare_field_update_field_name' );
        
        // Loop through all field groups
        $acf_field_groups = acf_get_field_groups();
        foreach( $acf_field_groups as $acf_field_group ) {
            foreach( $acf_field_group['location'] as $group_locations ) {
                foreach( $group_locations as $rule ) {
                    // See if field Group has at least one post_type = Variations rule - does not validate other rules
                    if( $rule['param'] == 'post_type' && $rule['operator'] == '==' && $rule['value'] == 'product_variation' ) {
                        // Render field Group
                        acf_render_fields( $variation->ID, acf_get_fields( $acf_field_group ) );
                        break 2;
                    }
                }
            }
        }
        
        // Remove filter
        remove_filter( 'acf/prepare_field', 'acf_prepare_field_update_field_name' );
    }, 10, 3 );

    // Filter function to update field names
    function  acf_prepare_field_update_field_name( $field ) {
        global $abcdefgh_i;
        $field['name'] = preg_replace( '/^acf\[/', "acf[$abcdefgh_i][", $field['name'] );
        return $field;
    }
        
    // Save variation data
    add_action( 'woocommerce_save_product_variation', function( $variation_id, $i = -1 ) {
        // Update all fields for the current variation
        if ( ! empty( $_POST['acf'] ) && is_array( $_POST['acf'] ) && array_key_exists( $i, $_POST['acf'] ) && is_array( ( $fields = $_POST['acf'][ $i ] ) ) ) {
            foreach ( $fields as $key => $val ) {
                update_field( $key, $val, $variation_id );
            }
        }
    }, 10, 2 ); 
}


function update_acf_post_object_field_choices($title, $post, $field, $post_id) {
    if ($field['name'] == 'contact') {
        $contact_types = wp_get_post_terms($post->ID, "contact-type",  array("fields" => "names"));
        if (!empty($contact_types) && !is_wp_error($contact_types)) {
            $contact_type = $contact_types[0];
            $title = $title . ' <strong class="text-primary">(' . $contact_type . ')</strong>';
        }
    }
    return $title ;    
}
add_filter( 'acf/fields/post_object/result', 'update_acf_post_object_field_choices', 10, 4 );


function google_api_key_conditional_field( $field ) {
    $google_api_key = acf_get_setting('google_api_key');
    if ( empty( $google_api_key ) ) {
        return false;
    }
    return $field;
}
//Google Map field on Contact Details
add_filter('acf/prepare_field/key=field_6731e211669ab', 'google_api_key_conditional_field');

function google_api_key_found_conditional_field( $field ) {
    $google_api_key = acf_get_setting('google_api_key');
    if ( empty( $google_api_key ) ) {
        return true;
    }else{
        return false;
    }
    return $field;
}
//Google Map field messageon Contact Details
add_filter('acf/prepare_field/key=field_673386f1d3129', 'google_api_key_found_conditional_field');


    // page settings offcanvas menu template -> chhose menu -> chhose menu item for offcanvas menu root
    function acf_load_menu_field_choices( $field ) {
        $field['choices'] = array();
        $menus = get_terms('nav_menu', array('hide_empty' => false));
        if ($menus) {
            $field['choices'][""] = __("Menü seçiniz");
            foreach ($menus as $menu) {
                $menu_name = $menu->name;
                if(ENABLE_MULTILANGUAGE == "qtranslate_xt"){
                    $menu_name = translateContent($menu_name);
                }
                $field['choices'][ $menu->term_id ] = $menu_name;
            }
        }
        populate_menu_items_on_change();
        return $field;
    }
    add_filter('acf/load_field/key=field_65d5fc059efb9', 'acf_load_menu_field_choices');


function populate_menu_items_on_change() {
    // JavaScript kodunu inline olarak eklemek
    $script = <<<EOT
jQuery(document).ready(function($) {
    $(document).on('change', '[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fc059efb9]"]', function () {
        var selectedMenuId = $(this).val();
        $('[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fd749efba]"]').html("<option>Loading...</option>");
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'populate_menu_items',
                menu_id: selectedMenuId,
            },
            success: function (response) {
                $('[name="acf[field_6425cbdb1b107][field_6425cae782d9a][field_65d5fd749efba]"]').html(response);
            }
        });
    });
});
EOT;

    // Bu scripti yalnızca gerekli sayfalarda ekleyin
    if (is_admin()) {
        wp_add_inline_script('jquery', $script);
    }
}
add_action('acf/render_field/key=field_65d5fc059efb9', function() {
    add_action('admin_enqueue_scripts', 'populate_menu_items_on_change');   
});

    


    function populate_menu_items_callback() {
        $menu_id = $_POST['menu_id'];
        $menu_items = wp_get_nav_menu_items($menu_id);
        if ($menu_items) {
            $levels = array(); // Tüm seviyeleri tutan bir dizi
            echo '<option value="-1">' . __("Otomatik algıla") .'</option>';
            echo '<option value="0">' . __("Tüm menüyü göster") .'</option>';
            foreach ($menu_items as $item) {
                $level = $item->menu_item_parent > 0 ? $levels[$item->menu_item_parent] + 1 : 0; // Her seviye için uygun level değeri belirleniyor
                $levels[$item->ID] = $level; // Her itemin seviyesi saklanıyor
                $indent = str_repeat('&nbsp;', $level * 4); // Her seviye için uygun sayıda boşluk ekleniyor
                $item_title = $item->title;
                if(ENABLE_MULTILANGUAGE == "qtranslate-xt"){
                    $item_title = translateContent($item_title);
                }
                //echo '<option value="' . $item->ID . '">' . $indent . $item_title .'</option>';
                echo '<option value="' . $item->object_id . '">' . $indent . $item_title .'</option>';
            }
        }
        die();
    }
    add_action('wp_ajax_populate_menu_items', 'populate_menu_items_callback');
    add_action('wp_ajax_nopriv_populate_menu_items', 'populate_menu_items_callback');



function acf_add_field_options($field) {

    $class = explode(" ", $field["wrapper"]["class"]);

    /* Using classes for fields:
    acf-margin-padding
    acf-font-family
    acf-font-size
    acf-text-transform
    acf-font-weight
    acf-bs-align-hr
    acf-align-hr
    acf-align-vr
    acf-width-height
    acf-heading
    acf-plyr-options
    acf-plyr-settings
    acf-body-classes
    acf-main-classes
    acf-ratio
    acf-language-list
    acf-template-custom || acf-template-custom-default
    acf-wp-themes
    acf-image-blend-mode
    acf-image-filter
    acf-menu-locations
    */

    if(in_array("acf-language-list", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "array";
        $options = get_all_languages(true);
        if( is_array($options) ) {
            foreach($options as $label) {
                $field['choices'][$label["lang"]] = $label["name"];
            }
        }
    }


    if(in_array("acf-breakpoints", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "xl";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array();
        foreach ($GLOBALS["breakpoints"] as $key => $breakpoint) {
            $options[$key] = $key;
        }
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }

    if(in_array("acf-columns", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = 1;
        $field["type"] = $field["type"]=="acf_bs_breakpoints"?$field["type"]:"select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        foreach (range(1, 12) as $number) {
            $options[$number] = $number;
        }
        $field['choices'] = array();
        foreach ($options as $label) {
            $field['choices'][$label] = $label;
        }
    }

    if(in_array("acf-gaps", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = 0;
        $field["type"] = $field["type"]=="acf_bs_breakpoints"?$field["type"]:"select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        foreach (range(0, 10) as $number) {
            $options[$number] = $number;
        }
        $field['choices'] = array();
        $field['choices'][0] = "None";
        $field['choices']["auto"] = "Auto";
        foreach ($options as $label) {
            $field['choices'][$label] = $label;
        }
    }

    if(in_array("acf-margin-padding", $class) || in_array("acf-margin-padding-responsive", $class)){
        if(!empty($field["parent"]) && $field["parent"] != 0){
            global $wpdb;
            $parent_name =  $wpdb->get_var($wpdb->prepare("SELECT post_excerpt FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s", $field["parent"]));
                if($parent_name){
                    if (in_array($parent_name, ["margin", "padding", "default_margin", "default_padding"])) {
                        $field["allow_custom"] = 0;
                        $field["default_value"] = "";
                        $field["type"] = $field["type"]=="acf_bs_breakpoints"?$field["type"]:"select";
                        $field["multiple"] = 0;
                        $field["allow_null"] = 0;
                        $field["ajax"] = 0;
                        $field["ui"] = 0;
                        $field["search_placeholder"] = "";
                        $field["return_format"] = "value";
                        $options = array("auto" => "auto");
                        foreach (range(0, 12) as $number) {
                            $options[$number] = $number;
                        }
                        $field['choices'] = array();
                        if (in_array($parent_name, ["margin", "padding"])) {
                            $field['choices']["default"] = "Default";
                        }
                        if (in_array("acf-margin-padding-responsive", $class)) {
                            $field['choices']["responsive"] = "Responsive";
                        }
                        $field['choices'][""] = "None";
                        foreach ($options as $label) {
                            $field['choices'][$label] = $label;
                        }
                    }
            }   
        }
    }

    if(in_array("acf-heading", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "h3";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "h1",
            "h2", 
            "h3",
            "h4",
            "h5",
            "h6"
        );
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }
    if(in_array("acf-font-family", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "none";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $font_families = array(
            '##System Fonts'                                        => '##System Fonts',
            'Arial, Helvetica, sans-serif'                          => 'Arial, Helvetica, sans-serif',
            '"Arial Black", Gadget, sans-serif'                     => '"Arial Black", Gadget, sans-serif',
            '"Bookman Old Style", serif'                            => '"Bookman Old Style", serif',
            '"Comic Sans MS", cursive'                              => '"Comic Sans MS", cursive',
            'Courier, monospace'                                    => 'Courier, monospace',
            'Garamond, serif'                                       => 'Garamond, serif',
            'Georgia, serif'                                        => 'Georgia, serif',
            'Impact, Charcoal, sans-serif'                          => 'Impact, Charcoal, sans-serif',
            '"Lucida Console", Monaco, monospace'                   => '"Lucida Console", Monaco, monospace',
            '"Lucida Sans Unicode", "Lucida Grande", sans-serif'    => '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
            '"MS Sans Serif", Geneva, sans-serif'                   => '"MS Sans Serif", Geneva, sans-serif',
            '"MS Serif", "New York", sans-serif'                    => '"MS Serif", "New York", sans-serif',
            '"Palatino Linotype", "Book Antiqua", Palatino, serif'  => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
            'Tahoma,Geneva, sans-serif'                             => 'Tahoma, Geneva, sans-serif',
            '"Times New Roman", Times,serif'                        => '"Times New Roman", Times, serif',
            '"Trebuchet MS", Helvetica, sans-serif'                 => '"Trebuchet MS", Helvetica, sans-serif',
            'Verdana, Geneva, sans-serif'                           => 'Verdana, Geneva, sans-serif',
        );

        $fonts = array();
        $fonts["##Icon Fonts"] = "##Icon Fonts";
        $fonts['Font Awesome 6 Pro'] = 'Font Awesome 6 Pro';
        $fonts['Font Awesome 6 Brands'] = 'Font Awesome 6 Brands';
        $font_families = array_merge( $fonts, $font_families );

        if (class_exists("YABE_WEBFONT")) {
            $custom_fonts = yabe_get_fonts();
            if($custom_fonts){
               $fonts = array();
               $fonts["##Custom Fonts"] = "##Custom Fonts";
               foreach($custom_fonts as $font){
                   $name = $font["family"].(!empty($font["selector"])?", ".$font["selector"]:"");
                   $fonts[$name] = $font["title"];
               }
               $font_families = array_merge( $fonts, $font_families );
            }
        }
        
        $font_families = array_merge( array('##Defaults' => '##Defaults', 'initial' => 'initial', 'inherit' => 'inherit'), $font_families );
        $field['choices'] = array();
        foreach($font_families as $value => $label) {
           $field['choices'][$value] = $label;
        }
    }
    if(in_array("acf-font-weight", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "400";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = ["100", "200", "300", "400", "500", "600", "700", "800", "900"];
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }
    if(in_array("acf-font-size", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 1;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $types = ["title", "text"];
        $field['choices'] = array();
        
        $typography  = [];
        $theme_styles = acf_get_theme_styles();
        if($theme_styles){
            if(isset($theme_styles["typography"])){
                $typography = $theme_styles["typography"];                  
            }
        }  

        foreach($types as $type) {
            foreach($GLOBALS["breakpoints"] as $key => $breakpoint) {
                $size = "";
                if(isset($typography[$type][$key]) && !empty($typography[$type][$key]["value"])){
                   $size = " - ".$typography[$type][$key]["value"].$typography[$type][$key]["unit"];
                }
                $field['choices'][$type."-".$key] = $type."-".$key.$size;
            }
        }
    }

    if(in_array("acf-text-transform", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "none";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = ["none", "capitalize", "uppercase", "lowercase", "full-width", "full-size-kana", "inherit", "initial", "revert", "revert-layer", "unset"];
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }

    if(in_array("acf-bs-align-hr", $class)){
        $options = array(
            "start"  => "Left",
            "center" => "Center", 
            "end"    => "Right"
        );
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-align-hr", $class) || in_array("acf-align-hr-responsive", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "start";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "start"  => "Left",
            "center" => "Center", 
            "end"    => "Right"
        );
        if(in_array("acf-align-hr-responsive", $class)){
            $options["responsive"] = "Responsive";
        }
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }
    if(in_array("acf-bs-align-vr", $class)){
        $options = array(
            "start"  => "Top",
            "center" => "Center", 
            "end"    => "Bottom"
        );
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }
    if(in_array("acf-align-vr", $class) || in_array("acf-align-vr-none", $class) || in_array("acf-align-vr-responsive", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = in_array("acf-align-vr-none", $class)?"start":"start";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "start"  => "Top",
            "center" => "Center", 
            "end"    => "Bottom"
        );
        if(in_array("acf-align-vr-responsive", $class)){
            $options["responsive"] = "Responsive";
        }
        if(in_array("acf-align-vr-none", $class)){
            $options["none"] = "None";
        }
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-position-vr", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "center";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "top"  => "Top",
            "center" => "Center", 
            "bottom"    => "Bottom"
        );
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }
    if(in_array("acf-position-hr", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "center";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "left"  => "Left",
            "center" => "Center", 
            "right"    => "Right"
        );
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-width-height", $class)){
        $field["allow_custom"] = 1;
        $field["default_value"] = "auto";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 1;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = ["auto", "100%"];
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }

    
    if(in_array("acf-plyr-video-options", $class) || in_array("acf-plyr-audio-options", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 1;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 1;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        if(in_array("acf-plyr-video-options", $class)){
            $options = [
                'play-large' => 'The large play button in the center',
                'restart' => 'Restart playback',
                'rewind' => 'Rewind by the seek time (default 10 seconds)',
                'play' => 'Play/pause playback',
                'fast-forward' => 'Fast forward by the seek time (default 10 seconds)',
                'progress' => 'The progress bar and scrubber for playback and buffering',
                'current-time' => 'The current time of playback',
                'duration' => 'The full duration of the media',
                'mute' => 'Toggle mute',
                'volume' => 'Volume control',
                'captions' => 'Toggle captions',
                'settings' => 'Settings menu',
                'pip' => 'Picture-in-picture (currently Safari only)',
                'airplay' => 'Airplay (currently Safari only)',
                'download' => 'Show a download button with a link to either the current source or a custom URL you specify in your options',
                'fullscreen' => 'Toggle fullscreen',
            ];          
        }
        if(in_array("acf-plyr-audio-options", $class)){
            $options = [
                'restart' => 'Restart playback',
                'rewind' => 'Rewind by the seek time (default 10 seconds)',
                'play' => 'Play/pause playback',
                'fast-forward' => 'Fast forward by the seek time (default 10 seconds)',
                'progress' => 'The progress bar and scrubber for playback and buffering',
                'current-time' => 'The current time of playback',
                'duration' => 'The full duration of the media',
                'mute' => 'Toggle mute',
                'volume' => 'Volume control',
                'settings' => 'Settings menu',
                'airplay' => 'Airplay (currently Safari only)',
                'download' => 'Show a download button with a link to either the current source or a custom URL you specify in your options',
            ];          
        }
        $field['choices'] = array();
        foreach(array_keys($options) as $label) {
            $field['choices'][$label] = $label;
        }
    }
    if(in_array("acf-plyr-video-settings", $class) || in_array("acf-plyr-audio-settings", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 1;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 1;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        if(in_array("acf-plyr-video-settings", $class)){
            $options = ['captions', 'quality', 'speed', 'loop'];
        }
        if(in_array("acf-plyr-audio-settings", $class)){
            $options = ['quality', 'speed', 'loop'];
        }
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }
    
    if(in_array("acf-body-classes", $class) || in_array("acf-main-classes", $class)){
        $field["allow_custom"] = 1;
        $field["multiple"] = 1;
        $field["allow_null"] = 1;
        $field["ui"] = 1;
        $field["ajax"] = 0;
        $field["type"] = "select";
        $field["return_format"] = "value";
        $field['choices'] = array();

        $theme_styles = acf_get_theme_styles();

        if(in_array("acf-body-classes", $class)){
            if($theme_styles){
                if(isset($theme_styles["header"]["themes"])){
                    $header_themes = $theme_styles["header"]["themes"];
                    if($header_themes){
                        $field['choices'][] = "##Body Classes";
                        foreach($header_themes as $theme){
                            $theme_class = $theme["class"];
                            if(!in_array($theme_class, ["body", "html"])){
                                $field['choices'][$theme_class] = $theme_class;                      
                            }
                        }
                    }                   
                }
            }           
        }

        $prefixes = array(
            "##Margin" => "m", 
            "##Margin Top" => "mt", 
            "##Margin Bottom" => "mb",
            "##Margin Left" => "ms", 
            "##Margin Right" => "me", 
            "##Margin Left Right" => "mx", 
            "##Margin Top Bottom" => "my"
        );
        foreach ($prefixes as $key => $prefix) {
            $field['choices'][] = $key;
            foreach (range(0, 10) as $number) {
                $field['choices'][$prefix."-".$number] = $prefix."-".$number;  
            }
        }

        $prefixes = array(
            "##Padding" => "p", 
            "##Padding Top" => "pt", 
            "##Padding Bottom" => "pb",
            "##Padding Left" => "ps", 
            "##Padding Right" => "pe", 
            "##Padding Left Right" => "px", 
            "##Padding Top Bottom" => "py"
        );
        foreach ($prefixes as $key => $prefix) {
            $field['choices'][] = $key;
            foreach (range(0, 10) as $number) {
                $field['choices'][$prefix."-".$number] = $prefix."-".$number;  
            }
        }

        $colors = array("primary", "secondary", "tertiary", "quaternary", "success", "info", "warning", "danger", "light", "dark");
        $prefixes = array("##Text Color" => "text", "##Background Colors" => "bg");
        if($theme_styles){
            if(isset($theme_styles["colors"]["custom"])){
                $colors_custom = $theme_styles["colors"]["custom"];

                if($colors_custom){
                    foreach ($colors_custom as $color_custom) {
                        $colors[] = $color_custom["title"];
                    }
                }
            }
        }
        foreach ($prefixes as $key => $prefix) {
            $field['choices'][] = $key;
            foreach ($colors as $color) {
                $field['choices'][$prefix."-".$color] = $prefix."-".$color;  
            }
        }
    }

    if(in_array("acf-button-size", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $field['choices'] = array();
        $buttons_sizes  = [];
        $theme_styles = acf_get_theme_styles();
        if($theme_styles){
            if(isset($theme_styles["buttons"])){
                $buttons = $theme_styles["buttons"];
                if($buttons && isset($buttons["custom"]) && $buttons["custom"]){
                    $buttons_sizes = array_column($buttons["custom"], 'size');
                }               
            }
        }
        if($buttons_sizes){
            foreach($buttons_sizes as $size) {
                $field['choices'][$size] = $size;
            }
        }
    }

    if(in_array("acf-ratio", $class) || in_array("acf-default-ratio", $class) || in_array("acf-ratio-value", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "1x1" => "1:1 Square",
            "3x2" => "3:2 35mm Movie",
            "3x4" => "3:4 Vertical",
            "4x3" => "4:3 Standart TV",
            "5x4" => "5:4 Traditional Photo Size",
            "185x1" => "1.85x1 Standart Widescreen Movie",
            "235x1" => "2.35x1 Anamorphic Widescreen Movie",
            "9x16" => "9:16 Vertical - Stories, Reels etc.",
            "16x9" => "16:9 Widescreen TV, Monitor",
            "21x9" => "21:9 Ultra Widescreen TV, Monitor",
            "32x9" => "32:9 Super Ultra Widescreen TV, Monitor",
        );
        $field['choices'] = array();
        if(in_array("acf-ratio", $class)){
            $field['choices'][] = "None";
            $field['choices'][""] = "Default";
        }
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-template-custom", $class) || in_array("acf-template-custom-default", $class)){
        $handle = get_stylesheet_directory() . '/theme/templates/_custom/';
        $templates = array();// scandir($handle);
        if ($handle = opendir($handle)) {
            while (false !== ($entry = readdir($handle))) {
                // Sadece `.twig` uzantılı dosyaları kontrol et
                if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) === 'twig') {
                    $templates[] = $entry;
                }
            }
            closedir($handle);
        }
        $field['choices'] = array();
        if(in_array("acf-template-custom-default", $class)){
            $field['choices'][ 'default' ] = "Default";
        }
        $field['choices'][ 'post/tease' ] = "Post Tease (Predefined)";
        if( is_array($templates) && count($templates) > 0 ) {
            foreach( $templates as $template ) {
                $template = str_replace(".twig", "", $template);
                $field['choices'][ 'theme/templates/_custom/'.$template ] = $template;
            }        
        }
    }

    if(in_array("acf-template-modal", $class)){
        //$handle = get_stylesheet_directory() . "/templates/partials/modals";
        $handle = get_timber_template_path( "/partials/modals/" );
        $templates = array();
        if($handle){
            if ($handle = opendir($handle)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $templates[] = $entry;
                    }
                }
                closedir($handle);
            }            
        }
        $field['choices'] = array();
        if( is_array($templates) ) {
            foreach( $templates as $template ) {
                $template = str_replace(".twig", "", $template);
                $field['choices'][ "/partials/modals/".$template ] = $template;
            }        
        }
    }

    if(in_array("acf-wp-themes", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array();
        $themes = wp_get_themes();
        foreach ($themes as $theme) {
            $options[$theme->get('TextDomain')] = $theme->get('Name');
        }
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-image-blend-mode", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "" => "No",
            "multiply" => "Multiply",
            "screen" => "Screen",
            "overlay" => "Overlay",
            "darken" => "Darken",
            "lighten" => "Lighten",
            "color-dodge" => "Color Dodge",
            "color-burn" => "Color Burn",
            "hard-light" => "Hard Light",
            "soft-light" => "Soft Light",
            "difference" => "Difference",
            "exclusion" => "Exclusion",
            "hue" => "Hue",
            "saturation" => "Saturation",
            "color" => "Color",
            "luminosity" => "Luminosity",
        );
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }
    if(in_array("acf-image-filter", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array(
            "" => "No",
            "grayscale" => "Grayscale",
            "sepia" => "Sepia",
            "blur" => "Blur",
            "brightness" => "Brightness",
            "contrast" => "Contrast",
            "opacity" => "Opacity"
        );
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }


    if(in_array("acf-menu-locations", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = get_menu_locations();
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-color-classes", $class) || in_array("acf-color-classes-custom", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $colors_list_file = get_template_directory() . '/theme/static/data/colors.json';
        $colors = file_get_contents($colors_list_file);
        $options = json_decode($colors, true);
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
        if(in_array("acf-color-classes-custom", $class)){
            $field['choices']["custom"] = "Custom";
        }
    }


    if(in_array("acf-contact-accounts", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 1;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $field['choices'] = array();
        $args = array(
            'post_type' => 'contact', // Post tipi 'contaxt' olanları seç
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'contact_accounts', // 'contact' grubu içindeki 'accounts' alanını seç
                    'value' => '', // Boş olmayanları kontrol etmek için
                    'compare' => '!=' // 'accounts' metası boş değilse
                )
            )
        );
        $options = Timber::get_posts($args);
        if($options){
            foreach($options as $label) {
                $field['choices'][$label->ID] = $label->post_title;
            }
        }else{
            $field["search_placeholder"] = "Not Found";
        }
    }

     if(in_array("acf-mt", $class) || in_array("acf-mb", $class) || in_array("acf-ms", $class) || in_array("acf-me", $class) || in_array("acf-pt", $class) || in_array("acf-pb", $class) || in_array("acf-ps", $class) || in_array("acf-ee", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "array";
        $prefix = "";
        $class_check = implode(" ", $class);
        if(strpos($class_check, "-mt") !== false){
            $prefix = "mt-";
        }elseif(strpos($class_check, "-mb") !== false){
            $prefix = "mb-";
        }elseif(strpos($class_check, "-ms") !== false){
            $prefix = "ms-";
        }elseif(strpos($class_check, "-me") !== false){
            $prefix = "ms-";
        }elseif(strpos($class_check, "-pt") !== false){
            $prefix = "pt-";
        }elseif(strpos($class_check, "-pb") !== false){
            $prefix = "pb-";
        }elseif(strpos($class_check, "-ps") !== false){
            $prefix = "ps-";
        }elseif(strpos($class_check, "-pe") !== false){
            $prefix = "pe-";
        }
        $options = [];
        foreach (range(0, 10) as $number) {
            $field['choices'][$prefix."-".$number] = $prefix."-".$number;  
        };
    }

    if(in_array("acf-post-types", $class) || in_array("acf-post-types-multiple", $class)){
        $multiple = in_array("acf-post-types-multiple", $class);
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = $multiple;
        $field["allow_null"] = 1;
        $field["ajax"] = 0;
        $field["ui"] = $multiple;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = get_post_types(['public' => true], 'objects');
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label->name] = $label->label;
        }
    }

    if(in_array("acf-taxonomies", $class) || in_array("acf-taxonomies-multiple", $class)){
        $multiple = in_array("acf-taxonomies-multiple", $class);
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = $multiple;
        $field["allow_null"] = 1;
        $field["ajax"] = 0;
        $field["ui"] = $multiple;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = get_taxonomies(['public' => true]);
        $field['choices'] = array();
        foreach($options as $label) {
            $field['choices'][$label] = $label;
        }
    }

    if(in_array("acf-map-service", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 0;
        $field["search_placeholder"] = "";
        $field["return_format"] = "value";
        $options = array("leaflet" => "Leaflet (OpenSteetMap)");
        /*$google_api_key = acf_get_setting('google_api_key');
        if ( !empty( $google_api_key ) ) {*/
            $options["google"] = "Google Maps";
        /*}*/
        $field['choices'] = array();
        foreach($options as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if(in_array("acf-location-posts", $class)){
        $map_view = get_option("options_map_view");
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = $map_view == "js"?1:0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = $map_view == "js"?1:0;;
        $field["search_placeholder"] = "Find posts";
        $field["instructions"] = $map_view == "embed"?"'Map view' is set to 'embed' on settings page, so you can select only one post":"";
        $field["return_format"] = "value";
        $post_types = [];
        $posts = [];
        $args = array(
            "post_type" => "acf-field-group",
            "name"      => "group_63e6945ee6760",
            "posts_per_page" => 1
        );
        $field_group = Timber::get_post($args);
        if ($field_group && $field_group->post_type === 'acf-field-group') {
            $settings = maybe_unserialize($field_group->post_content);
            if (!empty($settings['location'])) {
                foreach ($settings['location'] as $location) {
                    foreach ($location as $rule) {
                        if ($rule['param'] === 'post_type') {
                            $post_types[] = $rule['value'];
                        }
                    }
                }
            }
        }
        if (!empty($post_types) && is_array($post_types)) {
            $args = [
                'post_type'      => $post_types,
                'posts_per_page' => -1,
                'post_status'    => 'publish'
            ];
            $result = get_posts($args);
            if($result){
                foreach ($result as $post) {
                    $posts[$post->ID] = $post->post_title . " (".$post->post_type.")"; // Burada post ID'si anahtar, başlık değeri
                }               
            }
        }
        $field['choices'] = array();
        if($posts){
            foreach($posts as $key => $label) {
                $field['choices'][$key] = $label;
            }           
        }
    }

    if(in_array("acf-api-list", $class)){
        $field["allow_custom"] = 0;
        $field["default_value"] = "";
        $field["type"] = "select";
        $field["multiple"] = 0;
        $field["allow_null"] = 0;
        $field["ajax"] = 0;
        $field["ui"] = 1;
        $field["search_placeholder"] = "Search an API";
        $field["return_format"] = "value";
        $apis = [
            'google_maps'                  => 'Google Maps API',
            'google_places'                => 'Google Places API',
            'google_geocoding'             => 'Google Geocoding API',
            'google_directions'            => 'Google Directions API',
            'google_distance_matrix'       => 'Google Distance Matrix API',
            'google_street_view'           => 'Google Street View API',
            'google_maps_elevation'        => 'Google Maps Elevation API',
            'google_maps_embed'            => 'Google Maps Embed API',
            'google_static_maps'           => 'Google Static Maps API',
            'google_cloud_vision'          => 'Google Cloud Vision API',
            'google_cloud_translation'     => 'Google Cloud Translation API',
            'google_speech_to_text'        => 'Google Cloud Speech-to-Text API',
            'google_text_to_speech'        => 'Google Cloud Text-to-Speech API',
            'google_cloud_functions'       => 'Google Cloud Functions API',
            'google_cloud_firestore'       => 'Google Cloud Firestore API',
            'google_firebase_db'           => 'Google Firebase Realtime Database API',
            'google_firebase_auth'         => 'Google Firebase Authentication API',
            'google_ads'                   => 'Google Ads API',
            'google_analytics'             => 'Google Analytics API',
            'google_search_console'        => 'Google Search Console API',
            'google_drive'                 => 'Google Drive API',
            'google_calendar'              => 'Google Calendar API',
            'google_gmail'                 => 'Google Gmail API',
            'google_youtube_data'          => 'Google YouTube Data API',
            'google_youtube_analytics'     => 'Google YouTube Analytics API',
            'google_pagespeed_insights'    => 'Google PageSpeed Insights API',
            //
            'openweather'         => 'OpenWeather',
            'twitter'             => 'Twitter API',
            'facebook_graph'      => 'Facebook Graph API',
            'instagram_graph'     => 'Instagram Graph API',
            'spotify'             => 'Spotify API',
            'github'              => 'GitHub API',
            'unsplash'            => 'Unsplash API',
            'stripe'              => 'Stripe API',
            'paypal'              => 'PayPal API',
            'linkedin'            => 'Linkedin API',
            'twilio'              => 'Twilio API',
            'mailchimp'           => 'MailChimp API',
            'sendgrid'            => 'SendGrid API',
            'firebase'            => 'Firebase API',
            'algolia'             => 'Algolia API',
            //'wordpress_rest'    => 'WordPress REST API',
            //'woocommerce'       => 'WooCommerce API',
            'amazon_s3'           => 'Amazon S3 API',
        ];
        $field['choices'] = array();
        foreach($apis as $key => $label) {
            $field['choices'][$key] = $label;
        }
    }

    if($field["type"] == "select"){
        if(in_array("multiple", $class)){
            $field["multiple"] = 1;
        }
        if(in_array("ui", $class)){
            $field["ui"] = 1;
        }
    }

    if(in_array("default-none", $class)){
        $field["default_value"] = "";
    }

    if($field["type"] == "image" || $field["type"] == "gallery"){
        $mime_types = ["jpg", "jpeg", "png", "gif", "svg", "webp", "avif"];
        $field["mime_types"] = implode(",", $mime_types);
    }

    return $field;
}
add_filter('acf/load_field', 'acf_add_field_options');


add_filter('acf/load_field/key=field_6425cced6668a', 'acf_load_offcanvas_template_files');
function acf_load_offcanvas_template_files( $field ) {
    //$handle = get_stylesheet_directory() . "/templates/partials/offcanvas/";
    $handle = get_timber_template_path( "/partials/offcanvas/" );
    $templates = array();
    if($handle){
        if ($handle = opendir($handle)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $templates[] = $entry;
                }
            }
            closedir($handle);
        }
    }
    $field['choices'] = array();
    if( is_array($templates) ) {
        foreach( $templates as $template ) {
            $field['choices'][ "/partials/offcanvas/".$template ] = $template;
        }        
    }
    return $field;
}



class UpdateFlexibleFieldLayouts {

    public $post_id;
    public $field_name;
    public $field_key;
    public $field_data;
    public $field_layouts;
    public $block_name;
    public $block_data;
    public $migration;

    private $clone;
    private $breakpoints;


    private $cached_field_data = [];
    private $cached_field_layouts = [];

    public function __construct($post_id = 0, $field_name = "", $field_key = "", $block_name = "", $migration = []) {
        $this->post_id = $post_id;
        $this->field_name = $field_name;
        $this->field_key = $field_key;
        $this->block_name = $block_name;
        $this->migration = $migration;

        $this->clone = array(
            'aria-label' => '',
            'type' => 'clone',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'parent_layout' => '',
            'clone' => array(),
            'display' => 'seamless',
            'layout' => 'block',
            'prefix_label' => 0,
            'prefix_name' => 0,
            'acfe_seamless_style' => 0,
            'acfe_clone_modal' => 0,
            'acfe_clone_modal_close' => 0,
            'acfe_clone_modal_button' => '',
            'acfe_clone_modal_size' => 'large',
        );
        $this->breakpoints = array(
            'aria-label' => '',
            'type' => 'acf_bs_breakpoints',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'parent_layout' => '',
            'acf_bs_breakpoints_type' => 'number',
            'show_description' => 1,
            'acf_bs_breakpoints_choices' => '',
            'allow_in_bindings' => 0,
            'font_size' => 14,
        );
    }

    public function get_block_field_data($block) {
        $data = [];
        $id = $block->post_name;
        $file = get_stylesheet_directory() . '/acf-json/'.$id.".json";
        $data = file_get_contents($file);
        if($data) {
            $data = json_decode($data, true);
            $fields = $data["fields"];
            $layouts = [];
            $data = [];
            foreach($fields as $item) {
                if (isset($item["layouts"])) {
                    $layouts = $item["layouts"];
                    continue;
                }
            }
            if ($layouts) {
                foreach($layouts as $layout) {
                    $fields = [];
                    $sub_fields = $layout["sub_fields"];
                    foreach($sub_fields as $sub_field) {
                        $fields[$sub_field["name"]] = $sub_field["key"];
                    }
                    $data[$layout["name"]] = array(
                        "key" => $layout["key"],
                        "sub_fields" => $fields
                    );
                }
            }
        }
        return $data;
    }

    public function get_block_fields() {
        global $wpdb;
        $block_categories = ["block"];
        $taxonomy = 'acf-field-group-category';
        $taxonomy_terms = implode("', '", array_map('esc_sql', $block_categories));
        $sql = "
            SELECT p.*
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.post_type = 'acf-field-group'
            AND p.post_status = 'publish'
            AND t.slug IN ('$taxonomy_terms')
            AND tt.taxonomy = '$taxonomy'
            ORDER BY p.post_title ASC
        ";
        return $wpdb->get_results($sql);
    }

    public function field_data($forced = false) {
        if (!empty($this->cached_field_data) && !$forced) {
            return $this->cached_field_data;
        }

        global $wpdb;
        $post_excerpt_value = 'acf_block_columns';
        $post_type = 'acf-field';

        $post_data = $wpdb->get_row( 
                $wpdb->prepare( 
                    "
                    SELECT ID, post_content 
                    FROM $wpdb->posts 
                    WHERE post_excerpt = %s 
                    AND post_type = %s 
                    LIMIT 1
                    ", 
                    $post_excerpt_value, 
                    $post_type
                ), 
                ARRAY_A // Veriyi bir dizi (array) olarak almak için
            );

        $this->cached_field_data = $post_data;
        return $post_data;
    }
    public function field_layouts() {
        if (!empty($this->cached_field_layouts)) {
            return $this->cached_field_layouts;
        }

        $fields_added = [];
        $post_data = $this->field_data();
        if ($post_data) {
            $post_content = unserialize($post_data['post_content']);
            if (isset($post_content['layouts'])) {
                foreach ($post_content['layouts'] as $item) {
                    if (isset($item['sub_fields'][1])) {
                        $fields_added[] = $item['sub_fields'][1]['name'];
                    }
                }
            }
        }

        $this->cached_field_layouts = $fields_added;
        return $fields_added;
    }

    public function get_block_data() {
        if (!empty($this->block_data)) {
            return $this->block_data;
        } else {
            global $wpdb;
            $post_data = $wpdb->get_row( 
                $wpdb->prepare( 
                    "
                    SELECT *
                    FROM $wpdb->posts 
                    WHERE post_excerpt = %s 
                    AND post_type = %s 
                    AND post_status = 'publish' 
                    LIMIT 1
                    ", 
                        $this->block_name, 
                        'acf-field-group'
                ), 
                ARRAY_A // Veriyi bir dizi (array) olarak almak için
            );
            $this->block_data = $post_data;
            return $post_data;
        }
    }
    public function block_exists_in_layouts() {
        $layouts = $this->field_layouts();
        $block_name_solid = str_replace("block-", "", $this->block_name);
        //error_log(json_encode($layouts));
        //error_log($block_name_solid." var mı? => ".in_array($block_name_solid, $layouts));
        return in_array($block_name_solid, $layouts);
    }
    public function block_exists_in_db() {
        global $wpdb;
        $post_parent = $this->field_data()["ID"];
        $block_name_solid = str_replace("block-", "", $this->block_name);
        $block = $wpdb->get_var( 
            $wpdb->prepare(
                "
                SELECT post_excerpt 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_parent = %d 
                AND post_excerpt = %s
                ", 
                'acf-field', 
                $post_parent,
                $block_name_solid
            )
        );
        error_log("post_parent: ".$post_parent.", block_name_solid: ".$block_name_solid);
        return !empty($block)?true:false;
    }
    public function create_clone($block, $post_parent, $layout_name, $layout_data = []) {
        if ($layout_data && isset($layout_data["sub_fields"]) && in_array($slug, array_values($layout_data["sub_fields"]))) {
            $post_name = $layout_data["sub_fields"][$slug];
        } else {
            $post_name = 'field_'.uniqid();
        }

        $clone = $this->clone;
        $clone["parent_layout"] = $layout_name;
        $clone["parent_id"] = $post_parent;
        $clone["clone"] = array(
            $block["post_name"]
        );
        $post_data = array(
            //'post_title'    => $block["post_title"],
            'post_content' => serialize($clone), // post_content diziyi JSON olarak kaydediyoruz
            'post_status' => 'publish', // Yayınlanmış olarak ayarla
            'post_type' => 'acf-field', // Post tipini acf-field olarak belirle
            'post_name' => $post_name, // Post slug (post_name)
            'post_parent' => $post_parent, // Parent ID, 8293 olarak belirlenmiş
            'post_excerpt' => str_replace("block-", "", $block["post_excerpt"]), // Parent ID, 8293 olarak belirlenmiş
        );
        $post_id = wp_insert_post($post_data);
        return $clone;
    }
    public function create_field($block, $post_parent, $layout_name, $args = array(), $title = "", $slug = "", $layout_data = []) {
        if ($layout_data && isset($layout_data["sub_fields"]) && in_array($slug, array_values($layout_data["sub_fields"]))) {
            $post_name = $layout_data["sub_fields"][$slug];
        } else {
            $post_name = 'field_'.uniqid();
        }
        $args["parent_layout"] = $layout_name;
        $post_data = array(
            'post_title' => $title, //"Breakpoints",
            'post_content' => serialize($args), // post_content diziyi JSON olarak kaydediyoruz
            'post_status' => 'publish', // Yayınlanmış olarak ayarla
            'post_type' => 'acf-field', // Post tipini acf-field olarak belirle
            'post_name' => $post_name, // Post slug (post_name)
            'post_parent' => $post_parent, // Parent ID, 8293 olarak belirlenmiş
            'post_excerpt' => $slug, //"breakpoints",
        );
        $post_id = wp_insert_post($post_data);
        return $args;
    }

    public function update() {
        if (!$this->block_exists_in_db()) {
            error_log("+++ Ekleniyor");

            $post_data = $this->field_data();
            if ($post_data) {
                $post_parent = $post_data['ID'];
                $post_content = unserialize($post_data['post_content']);
                $layouts = $post_content['layouts'];

                $block = $this->get_block_data();

                $layout_data = [];
                if ($this->migration && in_array($this->block_name, array_values($this->migration))) {
                    $layout_data = $this->migration[$this->block_name];
                    $layout_name = $layout_data['key'];
                } else {
                    $layout_name = "layout_" . uniqid();
                }

                $breakpoints = $this->create_field($block, $post_parent, $layout_name, $this->breakpoints, "Breakpoints", "breakpoints", $layout_data);
                $clone = $this->create_clone($block, $post_parent, $layout_name, $layout_data);

                if ($clone && $breakpoints) {
                    $clone['parent_id'] = $post_parent;

                    $layouts[$layout_name] = array(
                        'key' => $layout_name,
                        'name' => $block['post_excerpt'],
                        'label' => $block['post_title'],
                        'display' => 'block',
                        'sub_fields' => [],
                        'min' => '',
                        'max' => '',
                    );

                    // Post içeriği güncelleniyor
                    $post_content['layouts'] = $layouts;
                    $post_content = serialize($post_content);

                    global $wpdb;
                    $wpdb->update(
                        $wpdb->posts,
                        ['post_content' => $post_content],
                        ['ID' => $post_parent]
                    );
                }
            }
        } else {
            error_log("--- Eklenmiyor");
        }
    }

    public function update_layouts($post_parent) {
        global $wpdb;
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_parent = %d 
                AND (post_excerpt IS NULL OR post_excerpt = '')
                ", 
                'acf-field', 
                $post_parent
            )
        );

        if ($posts) {
            foreach($posts as $post) {
                $new_excerpt = sanitize_title(str_replace('block', '', strtolower($post->post_title)));
                $wpdb->update(
                    $wpdb->posts,
                    array('post_excerpt' => $new_excerpt), // post_excerpt alanını güncelle
                    array('ID' => $post->ID) // ID'ye göre güncelle
                );
            }
        }
    }

    public function clear_cache(){
        if ( function_exists('acf_get_field_groups') ) {
            // Tüm field group'ları al
            $field_groups = acf_get_field_groups();
            foreach ( $field_groups as $group ) {
                if ( isset($group['key']) ) {
                    acf_flush_field_cache( $group['key'] );
                }
                // Grup içindeki field'ları da al ve temizle
                $fields = acf_get_fields( $group['key'] );
                if ( $fields ) {
                    foreach ( $fields as $field ) {
                        if ( isset($field['key']) ) {
                            acf_flush_field_cache( $field['key'], 'field' );
                        }
                    }
                }
            }
            error_log('🔥 ACF field ve field group cache\'leri temizlendi.');
        }
    }

    public function update_cache() {
        if ($this->post_id) {
            acf_save_post_block_columns_action($this->post_id);

            // ACF Cache'i temizle
            //$this->clear_cache();

            // Alan grubunu yeniden yükle
            if($this->field_key){
                acf_import_field_group(acf_get_field_group($this->field_key));             
            }

            // Alan grubunu manuel kaydet
            do_action('acf/save_post', $this->post_id);
        }
    }
}
function acf_save_post_block_columns_action( $post_id ){
    if(has_term("block", 'acf-field-group-category', $post_id)){ // is block
        $block = get_post($post_id);

        error_log("block->post_excerpt: ".$block->post_excerpt);

        remove_action( 'save_post', 'acf_save_post_block_columns', 20 );

        if($block->post_excerpt != "block-bootstrap-columns"){

            $layouts = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $block->post_name, $block->post_excerpt);
            $layouts->update();

        }elseif($block->post_excerpt == "block-bootstrap-columns"){

            $layouts_check = new UpdateFlexibleFieldLayouts();
            $blocks = $layouts_check->get_block_fields();
            if($blocks){
                $group_field_data = $layouts_check->get_block_field_data($block);
                error_log("block-bootstrap-columns s a v i n g . . . . . . . . . . . . ");
                foreach($blocks as $item){
                    error_log("adding:".$item->post_excerpt);
                    $layouts = new UpdateFlexibleFieldLayouts($post_id, "acf_block_columns", $item->post_name, $item->post_excerpt, $group_field_data);
                    $layouts->update();
                }
            }
        }
        add_action( 'save_post', 'acf_save_post_block_columns', 20 );

    }
}
function acf_save_post_block_columns( $post_id ) {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( get_post_status( $post_id ) !== 'publish' ) {
        return;
    }
    if ( get_post_type( $post_id ) !== 'acf-field-group' ) {
        return;
    }
    static $has_run = false; // Hook'un iki kere çalışmasını önlemek için flag kullan
    if ($has_run) {
        return;
    }
    $has_run = true;

    acf_save_post_block_columns_action( $post_id );
}
add_action( 'save_post', 'acf_save_post_block_columns', 20);






function acf_layout_posts_preload($fields = array()){// Kullanılmıyor gozukuyor
    if($fields){

        //print_r($fields);

        $vars = $fields;/*array(
            "post_type" => $fields["post_type"],
            "taxonomy" => $fields["taxonomy"],
            "parent" => $fields["terms"],
            "numberposts" => $fields["posts_per_page"],
            "orderby" => $fields["orderby"],
            "order" => $fields["order"],
        );*/
        if($fields["load_type"] == "all"){
           //$vars["numberposts"] = -1;
        }

        //echo "aa".$vars["posts_per_page_default"];

        $class = "";
        $is_home = boolval(is_front_page());

        $context = Timber::context();
        $template = "partials/posts/archive-acf.twig";
        $templates = array();
        switch($fields["type"]){
            case "post":
                if($is_home){
                    $templates[] = $vars["post_type"]."/tease-home.twig";
                }   
                $templates[] = $vars["post_type"]."/tease.twig";
            break;
            case "taxonomy":
                if($is_home){
                    $templates[] = $vars["taxonomy"]."/tease-home.twig";
                }
                $taxonomy = get_taxonomy($vars["taxonomy"]);
                $post_types = $taxonomy->object_type;
                foreach($post_types as $post_type){
                    if($is_home){
                        $templates[] = $post_type."/tease-home.twig";
                    }
                    $templates[] = $post_type."/tease.twig";
                }
                $templates[] = $vars["taxonomy"]."/tease.twig";
            break;
            case "user":
                if($is_home){
                    $templates[] = $fields["type"]."/tease-home.twig";
                }
                $templates[] = $fields["type"]."/tease.twig";
            break;
            case "comment":
                if($is_home){
                    $templates[] = $fields["type"]."/tease-home.twig";
                }
                $templates[] = $fields["type"]."/tease.twig";
            break;
        }
        /*if(isset($vars["post_type"]) && !empty($vars["post_type"])){
            $templates[] = $vars["post_type"]."/tease.twig";
        }
        if(empty($templates) && isset($vars["taxonomy"]) && !empty($vars["taxonomy"])){
            $templates[] = $vars["taxonomy"]."/tease.twig";
            $taxonomy = get_taxonomy($vars["taxonomy"]);
            $post_types = $taxonomy->object_type;
            foreach($post_types as $post_type){
                $templates[] = $post_type."/tease.twig";
            }
        }*/
        $templates[] = "tease.twig";

        $paginate = new Paginate([], $vars);
        $result = $paginate->get_results($fields["type"]);
        //print_r($result);
        $posts = $result["posts"];
        //print_r($posts);
        if(is_wp_error($posts)){
           $posts = array();
        }
        $context["posts"] = $posts;
        $context["templates"] = $templates;
        //$response["data"] = $result["data"];
        //$response["html"] = Timber::compile($templates, $context);

        //$posts = Timber::get_posts($vars);
        
        //$context["posts"] = $posts;
        if(isset($fields["is_preview"])){
            $context["is_preview"] = $fields["is_preview"];         
        }

        return array(
            "posts" => Timber::compile($template, $context),//Timber::compile($fields["post_type"]."/archive-acf.twig", $context),
            "total" => $result["data"]["total"]//count($posts)//count($posts)
        );
    }
}



if( ENABLE_MULTILANGUAGE == "qtranslate-xt"){
    // ACF options sayfasındaki alanları kaydetmek için filtre
    add_filter('acf/load_value', 'load_acf_option_value', 10, 3);
    function load_acf_option_value($value, $post_id, $field) {
        remove_filter('acf/load_value', 'load_acf_option_value', 10, 3);

        $current_lang = qtranxf_getLanguage();
        $default_lang = qtranxf_getSortedLanguages()[0];

        if ($post_id == 'options_'.$current_lang) {

            $option_name = $field['name'];
            $default_option = "options_{$option_name}";
            $default_alt_option = "options_{$default_lang}_{$option_name}";
            $current_option = "options_{$current_lang}_{$option_name}";
            $value = get_option($current_option);

            if (empty($value)) {
                
               global $q_config;
               $q_config['language'] = $default_lang;
               //echo $option_name." > yok aabi<br>";
               $value = get_field($option_name, "options");
               //print_r($value);
               $value = get_option($default_option);
               //print_r($value);
               //echo "<br>";
               $q_config['language'] = $current_lang;
                /*if (empty($value)) {
                    $value = get_option($default_alt_option);
                }*/
            }
        }
        add_filter('acf/load_value', 'load_acf_option_value', 10, 3);
        return $value;
    }
}





function display_search_ranks_table() {
    global $wpdb;

    if ( isset($_GET['delete_id']) ) {
        $delete_id = intval($_GET['delete_id']);
        $wpdb->delete('wp_search_terms', array('id' => $delete_id));
        echo '<meta http-equiv="refresh" content="0; url=' . admin_url('admin.php?page=search-ranks') . '">';
    }

    $results = $wpdb->get_results("SELECT * FROM wp_search_terms ORDER BY rank DESC");

    if ($results) {
        echo '<div class="bg-white rounded-3 p-3 shadow-sm"><table class="table table-hover table-striped" style="width:100%; border-collapse: collapse;background-color:#fff;">';
        echo '<thead><tr style="background-color:#f2f2f2; text-align:left;">';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">ID</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Name</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Type</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Rank</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Date</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Last Modified</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->id) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html(urldecode($row->name)) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->type) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->rank) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->date) . '</td>';
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row->date_modified) . '</td>';
            // Silme butonu
            echo '<td style="padding:10px; border-bottom: 1px solid #ddd;">';
            echo '<a href="' . admin_url('admin.php?page=search-ranks&delete_id=' . esc_attr($row->id)) . '" onclick="return confirm(\'Bu kaydı silmek istediğine emin misin?\');" style="color:red; text-decoration:none;">Sil</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p>No data found.</p>';
    }
}
function update_search_ranks_message_field( $field ) {
    ob_start();
    display_search_ranks_table();
    $field['message'] = ob_get_clean();
    return $field;
}
add_filter('acf/prepare_field/key=field_66e9f03698857', 'update_search_ranks_message_field');

function display_page_assets_table() {
    $extractor = new PageAssetsExtractor();
    $urls = $extractor->get_all_urls();
    if($urls){
        $total = count($urls);
        $outputArray = [];
        foreach ($urls as $key => $item) {
            $item['id'] = $key; // Key'i 'id' olarak ekle
            $outputArray[] = $item; // Yeni array'e ekle
        }
        $urls = $outputArray;
        $message = "JS & CSS Extraction process completed with <strong>".$total." pages.</strong>";
        $type = "success";
    }else{
        $urls = [];
        $message = "Not found any pages to extract process.";
        $type = "error";
    }

    if ($urls) {
        echo '<div class="bg-white rounded-3 p-3 shadow-sm"><table class="table-page-assets table table-sm table-hover table-striped" style="width:100%; border-collapse: collapse;background-color:#fff;">';
        echo '<thead><tr style="background-color:#f2f2f2; text-align:left;">';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">ID</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Type</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Url</th>';
        echo '<th style="padding:10px; border-bottom: 1px solid #ddd;">Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($urls as $index => $row) {
            echo '<tr id="'.$row["type"].'_'.$row["id"].'" data-index="'.$index.'">';
            echo '<td data-id="'.$row["id"].'" style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row["id"]) . '</td>';
            echo '<td data-type="'.$row["type"].'" style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row["post_type"]) . '</td>';
            echo '<td data-url="'.$row["url"].'" style="padding:10px; border-bottom: 1px solid #ddd;">' . esc_html($row["url"]) . '</td>';
            echo '<td class="actions" style="width:50px;padding:10px; border-bottom: 1px solid #ddd;"><a href="#" class="btn-page-assets-single btn btn-success  btn-sm">Fetch</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="table-page-assets-status text-center py-4">';
        echo '<div class="progress-page-assets progress d-none mb-4" role="progressbar" aria-label="Animated striped example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div></div>';
        echo '<a href="#" class="btn-page-assets-update btn btn-success btn-lg px-4">Start Mass Update</a>';
        echo '</div>';
    } else {
        echo '<p>No data found.</p>';
    }
    ?>
    <script type="text/javascript">
        var index = 0;
        var urls = <?php echo json_encode($urls);?>;
        jQuery(document).ready(function($) {
            $(".btn-page-assets-single").on("click", function(e){
                e.preventDefault();
                $(this).addClass("disabled");
                var $row = $(this).closest("tr");
                var $index = $row.attr("data-index");
                page_assets_update($index, true);
            });
            $(".btn-page-assets-update").on("click", function(e){
                e.preventDefault();
                $(this).addClass("disabled");
                page_assets_update(0, false);
            });
        });
        function page_assets_update($index, $single){
            var $row = $(".table-page-assets").find("tr[data-index='"+$index+"']");
            $row.find(".actions").empty().addClass("loading loading-xs position-relative");
            if(!$single){
                $(".progress-page-assets").removeClass("d-none");
            }
             data = {
                action: 'page_assets_update',
                url: urls[$index]
            };
            $.ajax({
                url: ajaxurl,
                type: 'post',
                dataType: 'json',
                data: data,
                success: function(response) {
                    if (!response.error) {
                        $row.find("td").addClass("bg-success text-white");
                        $row.find(".actions").removeClass("loading loading-xs").html("<strong>COMPLETED</strong>");
                        if(!$single){
                            var percent = (($index+1) * 100) / urls.length;
                            $(".progress-page-assets .progress-bar").css("width", percent+"%");
                        }else{
                            $row.find(".btn-page-assets-single").removeClass("disabled");
                        }
                        if($index < urls.length-1 && !$single){
                            $index++;
                            page_assets_update($index);
                        }else{
                            $(".progress-page-assets").remove();
                            $(".table-page-assets-status").prepend("<div class='text-success fs-4 fw-bold'>COMPLETED!</div>");
                            $(".btn-page-assets-update").removeClass("disabled");
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error: ' + status + ' - ' + error);
                }
            });
        }
    </script>
    <?php
}
function update_page_assets_message_field($field){
    ob_start();
    display_page_assets_table();
    echo ob_get_clean();
    return $field;
} 
add_action('acf/render_field/name=page_assets', 'update_page_assets_message_field');
function page_assets_update(){
    $response = array(
        "error" => false,
        "message" => "",
        "html" => "",
        "data" => ""
    );
    $url = $_POST["url"];
    $id = $url["id"];
    $type = $url["type"];
    $url = $url["url"];
    $extractor = new PageAssetsExtractor();
    $extractor->mass = true;
    $extractor->type = $type;
    $response["data"] = $extractor->fetch($url, $id);
    echo json_encode($response);
    wp_die();
}
add_action('wp_ajax_page_assets_update', 'page_assets_update');
add_action('wp_ajax_nopriv_page_assets_update', 'page_assets_update');



function get_pages_need_updates($updated_plugins){
    global $wpdb;
    $pages = [];
    $like_statements = [];
    foreach ($updated_plugins as $term) {
        $like_statements[] = $wpdb->prepare("meta_value LIKE %s", '%' . $wpdb->esc_like($term) . '%');
    }
    $like_conditions = implode(" OR ", $like_statements);

    $query = "
        (SELECT post_id as id, 'post' as type FROM $wpdb->postmeta WHERE meta_key = 'assets' AND ($like_conditions))
        UNION
        (SELECT term_id as id, 'term' as type FROM $wpdb->termmeta WHERE meta_key = 'assets' AND ($like_conditions))
        UNION
        (SELECT comment_id as id, 'comment' as type FROM $wpdb->commentmeta WHERE meta_key = 'assets' AND ($like_conditions))
        UNION
        (SELECT user_id as id, 'user' as type FROM $wpdb->usermeta WHERE meta_key = 'assets' AND ($like_conditions))
    ";
    $results = $wpdb->get_results($query);
    foreach ($results as $result) {
        $pages[] = ["id" => intval($result->id), "type" => $result->type];
    }

    // Archive Control
    $like_clauses = [];
    foreach ($updated_plugins as $term) {
        $like_clauses[] = $wpdb->prepare("option_value LIKE %s", '%' . $wpdb->esc_like($term) . '%');
    }
    
    $results = [];
    if(ENABLE_MULTILANGUAGE){
        if(ENABLE_MULTILANGUAGE == "polylang"){
            $languages = pll_the_languages(['raw' => 1]);
            foreach ($languages as $lang) {
                $post_types = get_post_types(['public' => true], 'objects');
                foreach ($post_types as $post_type) {
                    if ($post_type->has_archive) {
                        $option_name = "{$post_type->name}_{$lang['slug']}_assets";
                        $query = $wpdb->prepare(
                            "SELECT option_value FROM `{$wpdb->options}` 
                            WHERE option_name = %s AND (" . implode(' OR ', $like_clauses) . ")",
                            $option_name
                        );
                        $option_value = $wpdb->get_var($query);
                        if ($option_value) {
                            foreach ($search_terms as $term) {
                                if (stripos($option_value, $term) !== false) {
                                    $pages[] = [
                                        'id' => $lang['slug'],
                                        'type' => $post_type->name
                                    ];
                                }
                            }
                        }
                    }
                }
            }            
        }
    }

    $pages = array_unique($pages, SORT_REGULAR); // Tekrarları kaldır ve sonuçları döndür

    $urls = [];
    foreach($pages as $page){
        if(is_string($page["id"])){
            $url = pll_get_post_type_archive_link($page["type"], $page["id"]);
            $urls[$page["type"]."_".$page["id"]] = [
                "type" => "archive",
                "url"  => $url
            ];
        }else{
            switch($page["type"]){
                case "post" :
                   $url = get_permalink($page["id"]); 
                break;
                case "term":
                    $url = get_term_link($page["id"]); // Term linkini al
                    break;

                case "comment":
                    // Yorumların kendilerine özgü bir bağlantısı yoktur; eğer gerekli bir URL varsa, bunu belirlemelisin
                    $url = ''; // Yorumlar için spesifik bir bağlantı yoksa boş bırak
                    break;

                case "user":
                    $url = get_author_posts_url($page["id"]); // Kullanıcı arşiv sayfasının URL'sini al
                    break;
            }
            $urls[$page["id"]] = [
                "type" => $page["type"],
                "url" => $url
            ];
        }
    }

    $extractor = new PageAssetsExtractor();
    return $extractor->fetch_urls($urls);
}

function acf_compile_js_css($value=0){
           if ( function_exists( 'rocket_clean_minify' ) ) {
                rocket_clean_minify();
            }

            $is_development = is_admin() && isLocalhost();
    
            
            // compile js files and css files
            if (!function_exists("compile_files_config")) {
                require SH_INCLUDES_PATH . "minify-rules.php";
            }
            require SH_CLASSES_PATH . "class.minify.php";

            if (class_exists('ScssPhp\ScssPhp\Compiler')) {
                $compile_errors = SaltHareket\Theme::scss_compile();
                if($compile_errors){
                    $type = "error";
                    $message = "<strong style='display:block;color:red;'>Compiling Error</strong>";
                    $message .= $compile_errors[0]["message"];
                    file_put_contents( WP_CONTENT_DIR . '/compiler_error.log', $compile_errors[0]["message"], FILE_APPEND);
                }else{
                    $type = "success";
                    $message = "scss files compiled!...";
                    $message .= "<br>js files compiled!...";
                }                
            }else{
                $type = "error";
                $message = "WP-SCSS is not intalled! SCSS is not compiled.";
            }  

            if(function_exists("add_admin_notice") && $value){
                add_admin_notice($message, $type);
            }
            
            // version update or plugin's custom init file update
            $minifier = new SaltMinifier(false, $is_development);
            $updated_plugins = $minifier->init();//compile_files(false, $is_development);
            error_log("updates_plugins: ".json_encode($updated_plugins));

            if($updated_plugins){
                if(function_exists("add_admin_notice") && $value){
                    $message = "Updated plugins or plugin init files: ".implode(",", $updated_plugins);
                    $type = "warning";
                    add_admin_notice($message, $type);
                }
            }

            if($is_development){
                // remove unused css styles
                error_log( "w e b p a c k");
                /*$output = [];
                $returnVar = 0;
                $command = "npx webpack --env enable_ecommerce=false";//.(ENABLE_ECOMMERCE ? 'true' : 'false');
                chdir(get_stylesheet_directory());
                exec($command, $output, $returnVar);//exec('npx webpack', $output, $returnVar);
                error_log( json_encode(implode("\n", $output)));
                if ($returnVar === 0) {
                    //echo 'Webpack successfully executed.';
                } else {
                    $message = 'Webpack execution failed. Error code: ' . $returnVar;
                    if(function_exists("add_admin_notice")){
                        add_admin_notice($message, "error");
                    }
                }

                $workingDir = get_stylesheet_directory();
                $process = Process::fromShellCommandline('npx webpack --env enable_ecommerce=false', $workingDir);

                try {
                    $process->mustRun();
                    error_log($process->getOutput()); 
                } catch (ProcessFailedException $e) {
                    $message = 'Webpack execution failed. Error code: ' .  $e->getMessage();
                    error_log($message);
                    if(function_exists("add_admin_notice")){
                        add_admin_notice($message, "error");
                    }
                }*/

                /**/
                $workingDir = get_stylesheet_directory();
                $command = ['npx', 'webpack', '--env', 'enable_ecommerce=false'];
                $process = new Process($command, $workingDir);

                $currentUser = getenv('USERNAME') ?: getenv('USER'); // Windows için USERNAME, diğer sistemlerde USER
                $nodeJsPath = 'C:\Program Files\nodejs';
                $npmPath = 'C:\Users\\' . $currentUser . '\AppData\Roaming\npm';
                $process->setEnv([
                    'PATH' => getenv('PATH') . ';' . $nodeJsPath . ';' . $npmPath,
                ]);
                $process->setTimeout(null);
                try {
                    $process->mustRun(); // Komutu çalıştır ve başarısız olursa hata fırlat
                    error_log($process->getOutput()); // Çıktıyı kaydet
                    //return true;
                } catch (ProcessFailedException $exception) {
                    error_log('Webpack execution failed: ' . $exception->getMessage());
                    if (function_exists("add_admin_notice")) {
                        add_admin_notice('Webpack execution failed.', 'error');
                    }
                    //return false;
                }

                // .js dosyalarını filtrele ve sil
                $js_files = glob(get_stylesheet_directory() . "/static/css/" . "*.js");
                foreach ($js_files as $js_file) {
                    try {
                        unlink($js_file);
                        //echo "Dosya silindi: $js_file <br>";
                    } catch (Exception $e) {
                        //echo "Dosya silinirken bir hata oluştu: " . $e->getMessage() . "<br>";
                    }
                }

                // TXT dosyalarını sil
                $txt_files = glob(get_stylesheet_directory() . "/static/css/" . "*.txt");
                foreach ($txt_files as $txt_file) {
                    try {
                        unlink($txt_file);
                        //echo "TXT dosyası silindi: $txt_file <br>";
                    } catch (Exception $e) {
                        //echo "TXT dosyası silinirken bir hata oluştu: " . $e->getMessage() . "<br>";
                    }
                }
            }

            if($updated_plugins){
                $pages = get_pages_need_updates($updated_plugins);
                if(function_exists("add_admin_notice") && $pages && $value){
                    $message = count($pages)." pages fetched for plugin updates";
                    $type = "success";
                    add_admin_notice($message, $type);
                }
            }
            if(!$value){
                //return true;
            }
}
function acf_development_compile_js_css( $value, $post_id, $field, $original ) {
    $is_development = is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1");
    if( $value ) {
        acf_compile_js_css($value);
    }
    return 0;
}
add_filter('acf/update_value/name=enable_compile_js_css', 'acf_development_compile_js_css', 10, 4);


function acf_methods_settings($value=0){
    error_log("acf_methods_settings");
            if ( function_exists( 'rocket_clean_minify' ) ) {
                rocket_clean_minify();
            }
            if(!class_exists("SaltHareket\MethodClass")){
                require_once SH_CLASSES_PATH . "class.methods.php";
            }
            $methods = new SaltHareket\MethodClass();
            $frontend = $methods->createFiles(false); 
            error_log(json_encode($frontend));
            $admin = $methods->createFiles(false, "admin");
            error_log(json_encode($admin));
            if(function_exists("add_admin_notice") && $value){
                if($frontend || $admin){
                    if($frontend){
                        foreach($frontend as $error){
                           add_admin_notice($error["message"], "error");
                        }
                    }
                    if($admin){
                        foreach($admin as $error){
                           add_admin_notice($error["message"], "error");
                        }
                    }
                    $message = "Only JS Frontend/Backend methods compiled!";
                    $type = "success";
                    add_admin_notice($message, $type);
                }else{
                  $message = "PHP & JS Frontend/Backend methods compiled!";
                  $type = "success";
                  add_admin_notice($message, $type);
                }
            }
            if(!$value){
                //return true;
            }
}
function acf_development_methods_settings( $value=0, $post_id=0, $field="", $original="" ) {
    if( $value ) {
        $is_development = is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1");
        if ($is_development) {
           acf_methods_settings($value); 
        }
    }
    return 0;
}
add_filter('acf/update_value/name=enable_compile_methods', 'acf_development_methods_settings', 10, 4);



function acf_development_extract_translations( $value=0, $post_id=0, $field="", $original="" ) {
    if( $value ) {
        if (is_admin() && ($_SERVER["SERVER_ADDR"] == "127.0.0.1" || $_SERVER["SERVER_ADDR"] == "localhost" || $_SERVER["SERVER_ADDR"] == "::1")) {
            if ( function_exists( 'rocket_clean_minify' ) ) {
                rocket_clean_minify();
            }

            // Get the text domain of the active theme
            $theme = wp_get_theme();
            $textDomain = $theme->get('TextDomain');

            // Get the path to the current theme's folder
            $themeFolderPath = get_template_directory();

            // Define the name and path of the output file
            $outputDir = $themeFolderPath . '/theme/static/data';
            $outputFile = $outputDir . '/translates.php';


            // Create the output directory if it doesn't exist
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Define folders to exclude
            $excludeFolders = ['assets', 'node_modules', 'vendor', 'static', 'languages', 'acf-json'];
            if(!ENABLE_ECOMMERCE){
                $excludeFolders[] = "woo";
                $excludeFolders[] = "woocommerce";
            }
            if(!ENABLE_MEMBERSHIP){
                $excludeFolders[] = "user";
                $excludeFolders[] = "my-account'";
            }

            $excludeFilePaths = [];
            if(!ENABLE_MEMBERSHIP){
                $excludeFilePaths[] = 'template-my-account.php';
                $excludeFilePaths[] = 'templates/partials/base/menu-login.twig';
                $excludeFilePaths[] = 'templates/partials/base/menu-user-menu.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-user-menu.twig';
                $excludeFilePaths[] = 'templates/partials/base/user-completion.twig';
                $excludeFilePaths[] = 'templates/author.twig';
                $excludeFilePaths[] = 'templates/partials/modals/login.twig';
                $excludeFilePaths[] = 'templates/partials/modals/fields-localization.twig';
                $excludeFilePaths[] = 'templates/partials/modals/list-languages.twig';
                $excludeFilePaths[] = SH_INCLUDES_PATH . 'helpers/membership-functions.php';
            }
            if(!ENABLE_ECOMMERCE){
                $excludeFilePaths[] = 'template-shop.php';
                $excludeFilePaths[] = 'template-checkout.php';
                $excludeFilePaths[] = 'templates/partials/base/menu-cart.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-cart.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/cart.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/cart-empty.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/cart-footer.twig';
            }
            if(!ENABLE_CHAT){
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-messages.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/messages.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/messages-empty.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/messages-footer.twig';
            }
            if(!ENABLE_FAVORITES){
                $excludeFilePaths[] = 'templates/partials/base/menu-favorites.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-favorites.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/favorites.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/favorites-empty.twig';
                $excludeFilePaths[] = 'templates/partials/dropdown/favorites-footer.twig';
            }
            if(!ENABLE_NOTIFICATIONS){
                $excludeFilePaths[] = 'templates/partials/base/menu-notifications.twig';
                $excludeFilePaths[] = 'templates/partials/base/offcanvas-notifications.twig';
            }
            if (!class_exists("Newsletter")) {
                $excludeFilePaths[] = 'template-newsletter.php';
                $excludeFilePaths[] = 'templates/page-newsletter.twig';
            }

            function scanFolder($folderPath, $excludeFolders, $excludeFilePaths) {
                $files = [];
                $dir = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($dir);
                $regex = new RegexIterator($iterator, '/^.+\.(php|twig)$/i', RecursiveRegexIterator::GET_MATCH);
                foreach ($regex as $file) {
                    $path = str_replace('\\', '/', $file[0]);
                    $exclude = false;
                    foreach ($excludeFolders as $excludeFolder) {
                        if (strpos($path, "/$excludeFolder/") !== false) {
                            $exclude = true;
                            break;
                        }
                    }

                    foreach ($excludeFilePaths as $excludeFilePath) {
                        if (strpos($path, $excludeFilePath) !== false) {
                            $exclude = true;
                            break;
                        }
                    }

                    if (!$exclude) {
                        $files[] = $path;
                    }
                }

                return $files;
            }


            function extractTranslations($filePath, $string = false) {
                if($string){
                    $content = $filePath;
                }else{
                    $content = file_get_contents($filePath);
                }
                
                // Regex for translate with 1 argument
                preg_match_all('/translate\(([^)]+)\)/', $content, $translateMatches);

                // Regex for translate_n_noop with 2 arguments
                preg_match_all('/translate_n_noop\(([^)]+)\)/', $content, $noopMatches);

                return [
                    'translate' => $translateMatches,
                    'translate_n_noop' => $noopMatches
                ];
            }

            // Scan the folder and get all PHP and Twig files
            $files = scanFolder($themeFolderPath, $excludeFolders, $excludeFilePaths);

            $translations = [
                'translate' => [],
                'translate_n_noop' => []
            ];


            /*if (is_plugin_active('multilingual-contact-form-7-with-polylang/plugin.php')) {
                global $wpdb;
                $posts = $wpdb->get_results("
                    SELECT ID, post_content 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'wpcf7_contact_form'
                ");
                $placeholders = [];
                foreach ($posts as $post) {
                    preg_match_all('/\{([^}]*)\}/', $post->post_content, $matches);
                    if (!empty($matches[1])) {
                        $placeholders[] = $matches[1];
                    }
                }
                $translations["translate"] = $placeholders;
            }*/

            // Extract translations from each file
            foreach ($files as $file) {
                $matches = extractTranslations($file);
                
                foreach ($matches['translate'][0] as $index => $match) {
                    // Split arguments by comma
                    $arguments = array_map('trim', explode(',', $matches['translate'][1][$index]));
                    
                    if (count($arguments) === 1) {
                        // translate case
                        $translations['translate'][] = $arguments[0];
                    }
                }
                
                foreach ($matches['translate_n_noop'][0] as $index => $match) {
                    // Split arguments by comma
                    $arguments = array_map('trim', explode(',', $matches['translate_n_noop'][1][$index]));
                    
                    if (count($arguments) === 2) {
                        // translate_n_noop case
                        $translations['translate_n_noop'][] = $arguments;
                    }
                }
            }

            $newsletter_forms = get_option("newsletter_htmlforms");
            if($newsletter_forms){
                foreach($newsletter_forms as $form){
                    if(!empty($form)){
                        $matches = extractTranslations($form, true);
                        foreach ($matches['translate'][0] as $index => $match) {
                            // Split arguments by comma
                            $arguments = array_map('trim', explode(',', $matches['translate'][1][$index]));
                            
                            if (count($arguments) === 1) {
                                // translate case
                                $translations['translate'][] = $arguments[0];
                            }
                        }
                        
                        foreach ($matches['translate_n_noop'][0] as $index => $match) {
                            // Split arguments by comma
                            $arguments = array_map('trim', explode(',', $matches['translate_n_noop'][1][$index]));
                            
                            if (count($arguments) === 2) {
                                // translate_n_noop case
                                $translations['translate_n_noop'][] = $arguments;
                            }
                        }                        
                    }
                }
            }

            // Remove duplicates
            $translations['translate'] = array_unique($translations['translate']);
            $translations['translate_n_noop'] = array_unique($translations['translate_n_noop'], SORT_REGULAR);

            // Create or overwrite the output file
            $output = fopen($outputFile, 'w');
            fwrite($output, "<"."?"."php\n");
            foreach ($translations['translate'] as $translation) {
                fwrite($output, "__($translation, \"$textDomain\");\n");
            }
            foreach ($translations['translate_n_noop'] as $translationPair) {
                fwrite($output, "_n_noop($translationPair[0], $translationPair[1], \"$textDomain\");\n");
            }
            fwrite($output, "?".">");
            fclose($output);


            $outputLangFile = $outputDir . '/translates.json';
            file_put_contents($outputLangFile, "[]");
            $output = fopen($outputLangFile, 'w');
            if($translations['translate']){
                $translations_new = [];
                foreach ($translations['translate'] as $translation) {
                    $translations_new[] = trim($translation, "\"'");
                }
                $translation_lang = json_encode(array_values($translations_new), JSON_UNESCAPED_UNICODE);
                fwrite($output, $translation_lang);        
            }
            fclose($output);
            $total = count($translations['translate']) + count($translations['translate_n_noop']);

            $message = "Translations file have been updated with ".$total." translations";
            $type = "success";

            if(function_exists("add_admin_notice")){
                add_admin_notice($message, $type);
            } 
        }
    }
    return 0;
}
add_filter('acf/update_value/name=enable_extract_translations', 'acf_development_extract_translations', 10, 4);









function export_and_replace_wp_mysql_dump() {
    if (!defined('ABSPATH')) {
        die("🚨 Hata: WordPress ortamında çalışmıyor!");
    }

    // **WordPress wp-config.php içindeki DB bilgilerini çek**
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASSWORD;
    $db_host = DB_HOST;

    $local_url = get_site_url();
    if (!$local_url) {
        wp_send_json_error(["message" => "🚨 Hata: Local URL alınamadı!"]);
    }

    $live_url = get_option("options_publish_url");
    if (!$live_url) {
        $live_url = $local_url;
        //wp_send_json_error(["message" => "🚨 Hata: Live URL alınamadı!"]);
    }

    // **Uploads klasörüne kaydet**
    $uploads_dir = wp_upload_dir()['basedir']; 
    $backup_file = $uploads_dir . "/" . $db_name . "_backup.sql";
    $updated_file = $uploads_dir . "/" . $db_name . "_updated.sql";
    $zip_file = $uploads_dir . "/" . $db_name . "_export.zip";

    try {
        // **MySQL Bağlantısı**
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // **Tüm tabloları al**
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!$tables) {
            wp_send_json_error(["message" => "🚨 Hata: Hiç tablo bulunamadı!"]);
        }

        // **Dump dosyasını başlat**
        $sql_dump = "-- WordPress MySQL Export\n-- " . date("Y-m-d H:i:s") . "\n\n";

        foreach ($tables as $table) {
            // **Tablo yapısını al**
            $create_table_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_dump .= $create_table_stmt['Create Table'] . ";\n\n";

            // **Tablo içeriğini al**
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                foreach ($rows as $row) {
                    $values = array_map(function ($val) use ($pdo, $local_url, $live_url) {
                        if (!isset($val)) return 'NULL'; // NULL değerleri koru

                        // **JSON olup olmadığını kontrol et**
                        $json_decoded = json_decode($val, true);
                        if (is_array($json_decoded)) {
                            // JSON içindeki URL'leri değiştir
                            array_walk_recursive($json_decoded, function (&$item) use ($local_url, $live_url) {
                                if (is_string($item) && str_contains($item, $local_url)) {
                                    $item = str_replace($local_url, $live_url, $item);
                                }
                            });
                            $val = json_encode($json_decoded, JSON_UNESCAPED_SLASHES);
                        } else {
                            // Normal metin içindeki URL'leri değiştir
                            $val = str_replace($local_url, $live_url, $val);
                        }

                        return $pdo->quote($val);
                    }, array_values($row));

                    $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
                }
                $sql_dump .= "\n";
            }
        }

        // **Dump dosyasını kaydet**
        file_put_contents($backup_file, $sql_dump);

        // **Normal metin içindeki URL'leri değiştir**
        $sql_dump = str_replace(
            [$local_url, addslashes($local_url), str_replace(['http://', 'https://'], '', $local_url)],
            [$live_url, addslashes($live_url), str_replace(['http://', 'https://'], '', $live_url)],
            $sql_dump
        );

        // **Yeni SQL dosyasını kaydet**
        file_put_contents($updated_file, $sql_dump);

        // **ZIP dosyasını oluştur**
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile($updated_file, basename($updated_file));
            $zip->close();
        } else {
            wp_send_json_error(["message" => "🚨 Hata: ZIP dosyası oluşturulamadı!"]);
        }

        wp_send_json_success(['zip_url' => wp_upload_dir()['baseurl'] . "/" . basename($zip_file)]);

    } catch (Exception $e) {
        wp_send_json_error(["message" => "🚨 Hata: " . $e->getMessage()]);
    }
}

add_action('wp_ajax_acf_export_mysql', 'acf_export_mysql');
add_action('wp_ajax_nopriv_acf_export_mysql', 'acf_export_mysql');
function acf_export_mysql() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        exit;
    }
    $new_sql_file = export_and_replace_wp_mysql_dump();
    wp_send_json_success(['url' => $new_sql_file]);
}
add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }

    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('.acf-export-mysql button').on('click', function (e) {
                e.preventDefault();
                var $button = $(this);
                $button.prop('disabled', true).text('Exporting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'acf_export_mysql'
                    },
                    success: function (response) {
                        if (response.success) {
                            window.location.href = response.data.zip_url;
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                        $button.prop('disabled', false).text('Export');
                    },
                    error: function () {
                        alert('An unexpected error occurred.');
                        $button.prop('disabled', false).text('Export');
                    }
                });
            });
        });
    </script>
    <?php
});














add_action('wp_ajax_acf_export_field_groups', 'acf_export_field_groups_to_json');
add_action('wp_ajax_nopriv_acf_export_field_groups', 'acf_export_field_groups_to_json');

function acf_export_field_groups_to_json() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        exit;
    }

    // ACF field group'ları al ve filtrele
    $theme = wp_get_theme();
    $textDomain = $theme->get('TextDomain');
    $groups = acf_get_field_groups();
    $filtered_groups = array_filter($groups, function ($group) use ($textDomain) {
        if (isset($group['acfe_categories'])) {
            $categories = array_keys($group['acfe_categories']);
            if(in_array($textDomain, $categories)){
                return false;
            }
            return array_intersect($categories, ['block', 'common', 'general']);
        }
        return false;
    });

    if (!$filtered_groups) {
        wp_send_json_error(['message' => 'No matching field groups found']);
        exit;
    }

    // JSON'ları bir diziye kaydet
    $json_files = [];
    foreach ($filtered_groups as $group) {
        if (isset($group['local_file']) && file_exists($group['local_file'])) {
            $json_data = json_decode(file_get_contents($group['local_file']), true);

            if (!$json_data) {
                continue; // JSON verisi geçerli değilse atla
            }

            // Belirli bir grup için özel düzenleme
            if ($group['key'] === "group_66e309dc049c4") {
                if (isset($json_data['fields']) && is_array($json_data['fields'])) {
                    foreach ($json_data['fields'] as &$field) {
                        if (isset($field['name']) && $field['name'] === 'acf_block_columns') {
                            if (isset($field['layouts'])) {
                                $field['layouts'] = (object)[]; // layouts alanını boş bir nesne yap
                            }
                        }
                    }
                }
            }

            $file_name = sanitize_title($group['key']) . '.json';
            $json_path = wp_upload_dir()['basedir'] . '/' . $file_name;

            // JSON verisini temizle ve yaz
            file_put_contents($json_path, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $json_files[] = $json_path;
        }
    }


    // ZIP dosyası oluştur
    $zip_file = wp_upload_dir()['basedir'] . '/acf-field-groups.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($json_files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    } else {
        wp_send_json_error(['message' => 'Failed to create ZIP file']);
        exit;
    }

    // JSON dosyalarını sil
    foreach ($json_files as $file) {
        unlink($file);
    }

    // ZIP dosyasını indir
    wp_send_json_success(['zip_url' => wp_upload_dir()['baseurl'] . '/acf-field-groups.zip']);
}
add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }

    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('.acf-export-button button').on('click', function (e) {
                e.preventDefault();
                var $button = $(this);
                $button.prop('disabled', true).text('Exporting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'acf_export_field_groups'
                    },
                    success: function (response) {
                        if (response.success) {
                            window.location.href = response.data.zip_url;
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                        $button.prop('disabled', false).text('Export');
                    },
                    error: function () {
                        alert('An unexpected error occurred.');
                        $button.prop('disabled', false).text('Export');
                    }
                });
            });
        });
    </script>
    <?php
});




function acf_json_to_db($acf_json_path = "", $overwrite = true) {
    // ACF JSON klasör yolu
    if(empty($acf_json_path)){
        $acf_json_path = get_template_directory() . '/acf-json';
    }
  
    // Klasör kontrolü
    if (!is_dir($acf_json_path)) {
        return ['success' => false, 'message' => 'acf-json directory not found'];
    }

    // JSON dosyalarını al
    $json_files = glob($acf_json_path . '/*.json');

    if (empty($json_files)) {
        return ['success' => false, 'message' => 'No JSON files found in acf-json directory'];
    }

    $imported_groups = [];
    foreach ($json_files as $file) {
        // Dosyayı oku ve JSON verisini çözümle
        $json_content = file_get_contents($file);
        $field_group = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($field_group)) {
            continue; // Geçersiz JSON dosyalarını atla
        }

        if (isset($field_group['key'])) {
            if(!in_array($field_group['key'], $imported_groups)){
                // Var olan grup kontrolü
                $existing_group = acf_get_field_group($field_group['key']);

                if ($existing_group) {
                    if ($overwrite) {
                        acf_delete_field_group($existing_group['ID']); // Eskiyi sil
                        acf_import_field_group($field_group); // Yeniyi ekle
                    } else {
                        acf_update_field_group(array_merge($existing_group, $field_group)); // Güncelle
                    }
                }else {
                    acf_import_field_group($field_group);
                }

                if (defined('ACF_LOCAL_JSON')) {
                    acf_write_json_field_group($field_group);
                }

                // Yeni grubu veritabanına ekle
                //acf_import_field_group($field_group);
                $imported_groups[] = $field_group['key'];               
            }

        }
    }

    if (!empty($imported_groups)) {
        return ['success' => true, 'message' => 'Registered ACF field groups: ' . implode(', ', $imported_groups)];
    } else {
        return ['success' => false, 'message' => 'No field groups were register.'];
    }
}





function save_theme_styles_colors($theme_styles){
        // Colors
        $colors_list_default = ["primary", "secondary", "tertiary","quaternary", "gray", "danger", "info", "success", "warning", "light", "dark"];
        $colors_list_file = THEME_STATIC_PATH . 'data/colors.json';
        $colors_mce_file = THEME_STATIC_PATH . 'data/colors_mce.json';
        $colors_file = THEME_STATIC_PATH . 'scss/_colors.scss';
        file_put_contents($colors_file, "");
        $colors_code = "";
        $colors_mce = [];
        $custom_colors = "$"."custom-colors: (\n";
        $colors_list = "$"."custom-colors-list: ";
        $colors = $theme_styles["colors"];
        foreach(["primary", "secondary","tertiary", "quaternary"] as $color){
            if(!empty($colors[$color])){
                $colors_code .= "$".$color.": ".scss_variables_color($colors[$color]).";\n";
                $custom_colors .= "\t".$color.": ".scss_variables_color($colors[$color]).",\n";
                $colors_list .= $color.",";
                $colors_mce[scss_variables_color($colors[$color])] = $color;
            }
        }
        if($colors["custom"]){
            foreach($colors["custom"] as $key => $color){
                $colors_code .= "$".$color["title"].": ".scss_variables_color($color["color"]).";\n";
                $custom_colors .= "\t".$color["title"].": ".scss_variables_color($color["color"]).",\n";
                $colors_list .= $color["title"].($key<count($colors["custom"])-1?",":"");
                $colors_list_default[] = $color["title"];
                $colors_mce[scss_variables_color($color["color"])] = $color["title"];
            }
        }
        $custom_colors .= ");\n";
        $colors_list .= ";\n";
        file_put_contents($colors_file, $colors_code.$custom_colors.$colors_list);
        file_put_contents($colors_list_file, json_encode($colors_list_default)); 
        file_put_contents($colors_mce_file, json_encode($colors_mce)); 
}

function save_theme_styles_header_themes($header){
    // Header Themes
        $header_themes_file = THEME_STATIC_PATH . 'scss/_header-themes.scss';
        file_put_contents($header_themes_file, ""); 
        $header_themes = $header["themes"];
        if($header_themes){
            $dom_elements = ["body", "header"];
            $code = "";
            foreach($header["themes"] as $theme){
                $theme["class"] = in_array($theme["class"], $dom_elements)?$theme["class"]:".".$theme["class"];
                $z_index = empty($theme["z-index"])?"null":$theme["z-index"];

                $default = $theme["default"];
                $color = empty($default["color"])?"null":$default["color"];
                $color_active = empty($default["color_active"])?"null":$default["color_active"];
                $bg_color = empty($default["bg_color"])?"null":$default["bg_color"];
                $logo = empty($default["logo"])?"null":$default["logo"];
                
                $affix = $theme["affix"];
                $color_affix = empty($affix["color"])?"null":$affix["color"];
                $color_active_affix = empty($affix["color_active"])?"null":$affix["color_active"];
                $bg_color_affix = empty($affix["bg_color"])?"null":$affix["bg_color"];
                $logo_affix = empty($affix["logo"])?"null":$affix["logo"];
                $btn_reverse = scss_variables_boolean($affix["btn_reverse"]);

                $code .= $theme["class"].":not(.menu-open):not(.menu-show-header){\n";
                    $code .= "@include headerTheme(";
                        $code .= $color.",";
                        $code .= $color_active.",";
                        $code .= $bg_color.",";
                        $code .= $logo.",";
                        $code .= $color_affix.",";
                        $code .= $color_active_affix.",";
                        $code .= $bg_color_affix.",";
                        $code .= $logo_affix.",";
                        $code .= $z_index.",";
                        $code .= $btn_reverse;
                    $code .= ");\n";
                $code .= "}\n";
            }
            file_put_contents($header_themes_file, $code); 
        }
}

function acf_save_menu_safelist_classes($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // if (get_post_type($post_id) !== 'your_post_type') return;
    $menu_classes = array();
    if (have_rows('header_tools_start', $post_id)) {
        while (have_rows('header_tools_start', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('header_tools_center', $post_id)) {
        while (have_rows('header_tools_center', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('header_tools_end', $post_id)) {
        while (have_rows('header_tools_end', $post_id)) {
            the_row();
            $menu_class = get_sub_field('menu_class');
            $classes = array_map('trim', array_filter(explode(' ', $menu_class)));
            $menu_classes[] = $classes;
        }
    }
    if (have_rows('theme_styles', $post_id)) {
        $theme_styles = get_field("theme_styles", $post_id);
        if($theme_styles){
            $header_themes = $theme_styles["header"]["themes"];
            if($header_themes){
                foreach($header_themes as $theme){
                    $class = $theme["class"];
                    if(!in_array($class, ["body", "html"])){
                        $classes = array_map('trim', array_filter(explode(' ', $class)));
                        //$menu_classes[] = $classes;
                        $menu_classes[] = $classes;                            
                    }
                }
            }
        }
    }


    // ACF block'larındaki class'ları kontrol et
    $blocks = parse_blocks(get_post_field('post_content', $post_id));
    if($blocks){
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'acf/social-media') {
                if (isset($block['attrs']['data'])) {
                    $block_data = $block['attrs']['data'];
                    if($block_data["add_accounts_from"] == "custom"){
                        $account_count = $block_data['social_accounts_custom'];
                        for ($i = 0; $i < $account_count; $i++) {
                            $account_name = isset($block_data["social_accounts_custom_{$i}_name"]) ? $block_data["social_accounts_custom_{$i}_name"] : '';
                            if ($account_name) {
                                $class = "fa-".$account_name;
                                $classes = array_map('trim', array_filter(explode(' ', $class)));
                                $menu_classes[] = $classes;
                            }
                        }
                    }
                }
                break;
            }
        }
    }

    if($menu_classes){
        $merged_menu_classes = array_map('trim', array_filter(array_unique(array_merge(...$menu_classes))));
        $json_data = array_values($merged_menu_classes);
        update_dynamic_css_whitelist($json_data);

        /*$merged_menu_classes = array_map('trim', array_filter(array_unique(array_merge(...$menu_classes))));
        $json_data = json_encode(['dynamicSafelist' => array_values($merged_menu_classes)], JSON_PRETTY_PRINT);
        $file_path = HEME_STATIC_PATH . 'data/css_safelist.json';
        file_put_contents($file_path, $json_data);*/
    }
}
add_action('acf/save_post', 'acf_save_menu_safelist_classes', 20);

function acf_theme_styles_save_hook($post_id) {
    if (have_rows('theme_styles', $post_id)) {
        $theme_styles = get_field('theme_styles', 'option');
        //print_r($theme_styles);
        //die;
        if($theme_styles){
            $action = $theme_styles["theme_styles_action"];
            $path = THEME_STATIC_PATH . 'data/theme-styles';
            if(!is_dir($path)){
                mkdir($path, 0755, true);
            }
            switch ($action) {
                case 'revert':
                    $preset_file = SH_STATIC_PATH . 'data/theme-styles-default.json';
                    $json_file = file_get_contents($preset_file);
                    $theme_styles = json_decode($json_file, true);
                    $theme_styles["theme_styles_action"] = "";
                    $theme_styles["theme_styles_filename"] = "";
                    update_field('theme_styles', $theme_styles, $post_id);
                break;
                case 'save':
                    $timestamp = time();
                    $filename = sanitize_title($theme_styles["theme_styles_filename"]);//.".".$timestamp;
                    $preset_file = THEME_STATIC_PATH . 'data/theme-styles/'.$filename.'.json';
                    //$theme_styles["theme_styles_action"] = "";
                    //$theme_styles["theme_styles_filename"] = "";
                    //update_field('theme_styles', $theme_styles, $post_id);
                    $theme_styles["theme_styles_presets"] = "";
                    $json_data = json_encode($theme_styles);
                    file_put_contents($preset_file, $json_data);
                    // save root variables
                    get_theme_styles([], true);
                break;
                case 'load':
                    $filename = $theme_styles["theme_styles_presets"];
                    if(!empty($filename)){
                        $preset_file = THEME_STATIC_PATH . 'data/theme-styles/'.$filename.'.json';
                        $json_file = file_get_contents($preset_file);
                        $theme_styles = json_decode($json_file, true);
                        $theme_styles["theme_styles_action"] = "save";
                        $theme_styles["theme_styles_filename"] = $filename;
                        $theme_styles["theme_styles_presets"] = "";
                        update_field('theme_styles', $theme_styles, $post_id);
                    }
                break;
            }
            // save latest
            $preset_file = THEME_STATIC_PATH . 'data/theme-styles/latest.json';
            $json_data = json_encode($theme_styles);
            file_put_contents($preset_file, $json_data); 
            //save colors
            save_theme_styles_colors($theme_styles);
            save_theme_styles_header_themes($theme_styles["header"]);
            delete_transient('theme_styles');
        }
    }
}
add_action('acf/save_post', 'acf_theme_styles_save_hook', 10);

function acf_theme_styles_load_presets( $field ) {
    $path = THEME_STATIC_PATH . 'data/theme-styles/';
    if(is_dir($path)){
        $handle = $path;
        $templates = array();// scandir($handle);
        if ($handle = opendir($handle)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $templates[] = $entry;
                }
            }
            closedir($handle);
        }        
    }else{
        $path = SH_STATIC_PATH . 'data/';
        $templates = ["theme-styles-default.json"];
    }
    $field['choices'] = array();
    if( is_array($templates) ) {
        foreach( $templates as $template ) {
            $filepath = $path . $template;
            if (file_exists($filepath)) {
                $save_date = date("d.m.Y H:i", filemtime($filepath));
                $template = str_replace(".json", "", $template);
                $field['choices'][ $template ] = $template." [".$save_date."]";
            }
        }        
    }
    if(count($field["choices"]) == 0){
        $field['choices'][""] = "Not found any preset";
    }
    return $field;
}
add_filter('acf/load_field/name=theme_styles_presets', 'acf_theme_styles_load_presets');

function acf_header_footer_options_save_hook($post_id) {
    // Sadece options sayfası kaydedildiğinde çalışsın
    if ($post_id !== 'options') {
        return;
    }

    // Kaydedilen option page'in slug'ını al
    if (isset($_POST['acf'])) {
        $current_screen = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        if ($current_screen === 'header' || $current_screen === 'footer') {
            $header_footer_options = header_footer_options(true);
            $preset_file = THEME_STATIC_PATH . 'data/header-footer-options.json';
            $json_data = json_encode($header_footer_options);
            file_put_contents($preset_file, $json_data);
            delete_transient('header_footer_options');
        }
    }
}
add_action('acf/save_post', 'acf_header_footer_options_save_hook', 10);


function acf_clear_wp_cache($post_id){
    if ($post_id !== 'options') {
        return;
    }
    if (isset($_POST['acf'])) {
        $current_screen = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        if ($current_screen === 'ayarlar') {
            wp_cache_delete('acf_logo', 'acf');
            wp_cache_delete('acf_logo_affix', 'acf');
            wp_cache_delete('acf_logo_mobile', 'acf');
            wp_cache_delete('acf_logo_footer', 'acf');
            wp_cache_delete('acf_logo_icon', 'acf');
        }
    }
}
add_action('acf/save_post', 'acf_clear_wp_cache', 10);


add_filter('acf/update_value/name=modal_home', function ($value, $post_id, $field) {
    $home_id = get_option('page_on_front');
    if ($home_id) {
        wp_update_post([
            'ID' => $home_id,
        ]);
    }
    return $value;
}, 10, 3);




function acf_enable_twig_cache($new_value, $post_id, $field) {
    if ($post_id !== 'options') {
        return $new_value;
    }
    $old_value = get_field($field['name'], 'option');
    if ($old_value == 1 && $new_value == 0) {
        deleteFolder(STATIC_PATH . 'twig_cache');
    }
    return $new_value;
}
add_filter('acf/update_value/name=enable_twig_cache', 'acf_enable_twig_cache', 10, 3);



function delete_related_video_attachments($post_id) {
    // Attachment olup olmadığını kontrol et
    if (get_post_type($post_id) !== 'attachment') {
        return;
    }

    // Dosya tipini al
    $mime_type = get_post_mime_type($post_id);
    
    // Eğer bir video değilse işlem yapma
    if (strpos($mime_type, 'video') === false) {
        return;
    }

    // Bağlantılı attachment'ları kontrol et
    $meta_keys = ['phone', 'tablet', 'poster', 'vtt', 'thumbnails'];
    foreach ($meta_keys as $meta_key) {
        $related_attachment_id = get_post_meta($post_id, $meta_key, true);

        if (!empty($related_attachment_id)) {
            // Önce dosyasını sil
            wp_delete_attachment($related_attachment_id, true);

            // Metadata'yı temizle
            delete_post_meta($post_id, $meta_key);
        }
    }
}
add_action('before_delete_post', 'delete_related_video_attachments');
function remove_attachment_from_video_metadata($post_id) {
    if (get_post_type($post_id) !== 'attachment') {
        return;
    }

    error_log("🛑 Silinen attachment ID: " . $post_id);

    // Video attachment'larını bul
    $video_attachments = get_posts([
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => -1,
        'meta_query'  => [
            'relation' => 'OR',
            ['key' => 'phone', 'value' => $post_id, 'compare' => '='],
            ['key' => 'tablet', 'value' => $post_id, 'compare' => '='],
            ['key' => 'vtt', 'value' => $post_id, 'compare' => '='],
            ['key' => 'poster', 'value' => $post_id, 'compare' => '='],
            ['key' => 'thumbnails', 'value' => $post_id, 'compare' => '='],
        ],
    ]);

    // Metadata'dan bu ID'yi sil
    foreach ($video_attachments as $video) {
        error_log("🎬 Meta temizleniyor -> Video ID: " . $video->ID);
        foreach (['phone', 'tablet', 'poster', 'vtt', 'thumbnails'] as $meta_key) {
            $meta_value = get_post_meta($video->ID, $meta_key, true);
            if ($meta_value == $post_id) {
                delete_post_meta($video->ID, $meta_key);
                error_log("✅ Meta silindi: " . $meta_key . " için " . $post_id);
            }
        }
    }
}
add_action('delete_attachment', 'remove_attachment_from_video_metadata');


function acf_ffmpeg_available($field) {
    $ffmpeg = new VideoProcessor();
    if (!$ffmpeg->available) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}
add_filter('acf/load_field/name=ffmpeg_available', 'acf_ffmpeg_available');

function acf_ffmpeg_not_available($field) {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') {
        return $field;
    }
    $ffmpeg = new VideoProcessor();
    if ($ffmpeg->available) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}
function acf_ffmpeg_not_available_message( $field ) {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') {
        return $field;
    }
    $ffmpeg = new VideoProcessor();
    if (!$ffmpeg->available) {
        ob_start();
        if ($ffmpeg->supported) {
            $url = esc_url(admin_url('admin.php?page=video-process'));
            echo '<div class="notice notice-warning is-dismissible" style="display: flex; align-items: center; padding: 15px;margin:0px;"><span class="dashicons dashicons-warning" style="margin-right: 10px; font-size: 24px; color: #ffba00;"></span><p><strong>Attention:</strong> To automatically generate sizes, frame thumbnails, and video poster frames for your uploaded videos, you can install the FFMpeg plugin from the <a href="'.$url.'">Video process page.</a></p></div>';
        }else{
            echo '<div class="notice notice-warning is-dismissible" style="display: flex; align-items: center; padding: 15px;margin:0px;"><span class="dashicons dashicons-no" style="color: #e74c3c; font-size: 24px; margin-right: 10px;"></span><p><strong>Attention:</strong> Unfortunately, your system does not support the FFMpeg plugin. Therefore, the automatic video size, frame thumbnails, and poster frame generation features are disabled.</p></div>';
        }
        $field['message'] = ob_get_clean();
    }
    return $field;
}
add_filter('acf/load_field/key=field_679763ca89402', 'acf_ffmpeg_not_available');
add_filter('acf/prepare_field/key=field_679763ca89402', 'acf_ffmpeg_not_available_message');

/*function custom_cron_schedules($schedules) {
    $schedules['video_tasks_schedule'] = [
        'interval' => 300, // 5 dakika = 300 saniye
        'display'  => __('Every 5 Minutes')
    ];
    return $schedules;
}
add_filter('cron_schedules', 'custom_cron_schedules');*/
function process_video_tasks_cron() {
    error_log("Cron Job: process_video_tasks_cron() has been strted...");
    $args = [
        'post_type'      => 'any',
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => 'video_tasks',
                'compare' => 'EXISTS',
            ],
        ],
        'posts_per_page' => 1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ];
    //$query = SaltBase::get_cached_query($args);
    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return;
    }

    $post = $query->posts[0];
    $post_id = $post->ID;
    $blocks = parse_blocks($post->post_content);

    $video_tasks = get_post_meta($post_id, 'video_tasks', true);
    if (empty($video_tasks) || !is_array($video_tasks)) {
        // Eğer görevler boşsa, metayı sil
        delete_post_meta($post_id, 'video_tasks');
        return;
    }
    foreach ($video_tasks as &$task) {
        if (isset($task['tasks']) || $task['tasks']) {
            $current_task = $task;
            break;
        }
    }
    if (!isset($current_task)) {
        delete_post_meta($post_id, 'video_tasks');
        return; // Tüm görevler tamamlanmış
    }
    
    $index = $current_task['index'];
    $block_index = $current_task['block_index'];
    $sizes = isset($current_task["tasks"]['sizes']) ? array_keys($current_task["tasks"]['sizes']) : [];
    $thumbnails = isset($current_task["tasks"]['thumbnails']);
    $poster = isset($current_task["tasks"]['poster']);

    $video = $blocks[$block_index]['attrs']['data']['video_file_desktop'] ?? null;

    if (is_numeric($video)) {
        $video_url = wp_get_attachment_url($video);
    } else {
        $video_url = $video;
    }

    // Video dosyasının tam yolunu belirle
    $upload_dir = wp_upload_dir();
    $video_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $video_url);

    // Eğer dosya mevcut değilse işlem yapma
    if (!file_exists($video_path)) {
        error_log('Video dosyası bulunamadı: ' . $video_path);

        if(count($video_tasks) == 1 || count($video_tasks)-1 == $index){
            delete_post_meta($post_id, 'video_tasks');
        }else{
            unset($video_tasks[$index]);
            update_post_meta($post_id, 'video_tasks', $video_tasks);
        }
        unset($blocks[$block_index]['attrs']["lock"]);
        $updated_content = serialize_blocks($blocks);
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $updated_content,
        ]);
        $timestamp = wp_next_scheduled('process_video_tasks_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'process_video_tasks_event');
            error_log("Old scheduled event cleared.");
            wp_schedule_single_event(time() + 10, 'process_video_tasks_event');
        }
        return;
    }

    // Video işleme sınıfını başlat
    $arr = [];
    try {
        $video_processor = new VideoProcessor();
        $arr = $video_processor->processVideo($post_id, $video_path, $video, $current_task);
        if (!empty($arr)) {
            if ($sizes) {
                foreach ($sizes as $size) {
                    if ($size == "480") {
                        $blocks[$block_index]['attrs']['data']['video_file_tablet'] = $arr[$size];
                    }
                    if ($size == "360") {
                        $blocks[$block_index]['attrs']['data']['video_file_phone'] = $arr[$size];
                    }
                }
            }
            if (isset($arr['720'])) {
                $blocks[$block_index]['attrs']['data']['video_file_desktop'] = $arr['720'];
            }else{
                $arr['720'] = $video;
            }
            if ($thumbnails) {
                $blocks[$block_index]['attrs']['data']['video_settings_vtt_thumbnails'] = $arr['vtt'];
            }
            if ($poster) {
                $blocks[$block_index]['attrs']['data']['video_settings_video_image'] = $arr['poster'];
            }

            $video_processor->updateVideoMeta($arr);

            unset($blocks[$block_index]['attrs']["lock"]);
        } else {
            error_log('Video işlenirken bir hata oluştu.');
        }
    } catch (Exception $e) {
        error_log('Hata: ' . $e->getMessage());
    }

    $updated_content = serialize_blocks($blocks);
    wp_update_post([
        'ID'           => $post_id,
        'post_content' => $updated_content,
    ]);
}
add_action( 'process_video_tasks', 'process_video_tasks_cron', 10, 0 );

function acf_block_video_process_on_save($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post_id === 'options') {
        return;
    }
    $content = get_post_field('post_content', $post_id);
    if (empty($content)) {
        return;
    }
    $blocks = parse_blocks($content);

    $tasks = [];
    $counter = 0;
    foreach ($blocks as $key => &$block) {
        //error_log(print_r($block, true));
        if(!isset($block['attrs']['data'])){
            continue;
        }
        $data = $block['attrs']['data'];
        if(!isset($data['video_file_desktop'])){
            continue;
        }
        if(empty($data['video_file_desktop'])){
            continue;
        }

        $process = false;

        $desktop = $data['video_file_desktop'] ?? null;
        $tablet  = $data['video_file_tablet'] ?? null;
        $phone   = $data['video_file_phone'] ?? null;

        if($desktop){
            $sizes      = $data['ffmpeg_available_generate_sizes'] ?? 0;
            $thumbnails = $data['ffmpeg_available_generate_thumbnails'] ?? 0;
            $poster     = $data['ffmpeg_available_generate_poster'] ?? 0;

            $task = [
                "index" => $counter,
                "block_index" => $key,
                "tasks" => []
                /*"status" => false,
                "sizes"      => [],
                "thumbnails" => false,
                "poster"     => false*/
            ];

            $task["tasks"]["sizes"]["720"] = 0;

            error_log("desktop:".$desktop." tablet:".$tablet." phone:".$phone);
            error_log("sizes:".$sizes." thumbnails:".$thumbnails." poster:".$poster);

            if($sizes && (empty($tablet) || empty($phone))){
                $process = true;
                $task["tasks"]["sizes"] = [];
                if(empty($tablet)){
                    $task["tasks"]["sizes"]["480"] = 0;
                }
                if(empty($phone)){
                    $task["tasks"]["sizes"]["360"] = 0;
                }
            }
            if($thumbnails && $sizes){
                $process = true;
                $task["tasks"]["thumbnails"] = false;
            }
            if($poster && $sizes){
                $process = true;
                $task["tasks"]["poster"] = false;
            }
            if($process){
                $block['attrs']['data']['ffmpeg_available_generate_sizes'] = 0;
                $block['attrs']['data']['ffmpeg_available_generate_thumbnails'] = 0;
                $block['attrs']['data']['ffmpeg_available_generate_poster'] = 0;
                $tasks[] = $task;
                $block['attrs']['lock'] = array(
                    'move'   => true,
                    'remove' => true,
                );
            }
        }
        $counter++;
    }
    if($tasks){

        error_log(print_r($tasks, true));

       /* */
        update_post_meta($post_id, 'video_tasks', $tasks);
        //error_log(print_r($blocks, true));
        $updated_content = serialize_blocks($blocks);
        //error_log($updated_content);
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $updated_content,
        ]);
        // Cron job kontrol et ve yoksa tetikle
        do_action('process_video_tasks_event');
        


        /*$timestamp = wp_next_scheduled('process_video_tasks_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'process_video_tasks_event');
            error_log("Old scheduled event cleared.");
            wp_schedule_single_event(time() + 10, 'process_video_tasks_event');
        }*/
        /*if (!wp_next_scheduled('process_video_tasks_event')) {
            wp_schedule_single_event(time() + 10, 'process_video_tasks_event');
            error_log("Cron Job: process_video_tasks_cron() triggered...");
        }*/
    }
}
add_action('acf/save_post', 'acf_block_video_process_on_save', 20);


function sync_acf_with_post_meta($value, $post_id, $field) {
    // Post meta anahtarları ve ilgili ACF field'leri eşleştiriyoruz
    $meta_to_acf_mapping = [
        'field_6797647a4fd31' => 'generate_sizes',
        'field_679764d54fd32' => 'generate_thumbnails',
        'field_679764f74fd33' => 'generate_poster',
        'field_66f9e4c018b78' => 'tablet',
        'field_66f9e4ca18b79' => 'phone',
        'field_663f74df3ea87' => 'poster',
        'field_664210041992f' => 'vtt',
        'field_6798cd6f61a0f' => 'vtt'
    ];

    // Eğer field ID listede yoksa işlemi yapma
    if (!array_key_exists($field['key'], $meta_to_acf_mapping)) {
        return $value;
    }

    // İlgili post'un ID'sini al
    $related_post_id = get_field('field_66f9e48418b77', $post_id);

    // Eğer ilgili post ID yoksa işlemi yapma
    if (!$related_post_id) {
        return $value;
    }

    // İlgili post'tan meta değerini çek
    $meta_key = $meta_to_acf_mapping[$field['key']];
    $meta_value = get_post_meta($related_post_id, $meta_key, true);

    $value = (!empty($meta_value) && empty($value)) ? $meta_value : $value;
    if(in_array($meta_key, $meta_to_acf_mapping)){
        $value = 0;
    }
    // Eğer ACF alanı boşsa ve meta doluysa, meta değerini döndür
    return $value;
}
add_filter('acf/load_value/key=field_66f9e4c018b78', 'sync_acf_with_post_meta', 10, 3);
add_filter('acf/load_value/key=field_66f9e4ca18b79', 'sync_acf_with_post_meta', 10, 3);
add_filter('acf/load_value/key=field_663f74df3ea87', 'sync_acf_with_post_meta', 10, 3);
add_filter('acf/load_value/key=field_664210041992f', 'sync_acf_with_post_meta', 10, 3);
add_filter('acf/load_value/key=field_6798cd6f61a0f', 'sync_acf_with_post_meta', 10, 3);



add_filter('acf/load_field/name=wph_settings', 'acf_general_option_wph_settings');
function acf_general_option_wph_settings($field) {
    if (!\PluginManager::is_plugin_installed("wp-hide-security-enhancer-pro/wp-hide.php")) {
        $field['wrapper']['class'] = 'hidden';
    }else{
        $field['wrapper']['class'] = '';
    }
    return $field;
}

add_action('wp_ajax_acf_wph_settings', 'acf_wph_settings');
add_action('wp_ajax_nopriv_acf_wph_settings', 'acf_wph_settings');
function acf_wph_settings() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        exit;
    }
    \Update::wph_load_settings();
    wp_send_json_success(["message" => "Settings applied!"]);
}
add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }
    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('[data-name="wph_settings"] button').on('click', function (e) {
                e.preventDefault();
                var $button = $(this);
                $button.prop('disabled', true).text('Applying...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'acf_wph_settings'
                    },
                    success: function (response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                        $button.prop('disabled', false).text('Set Default Settings');
                    },
                    error: function () {
                        alert('An unexpected error occurred.');
                        $button.prop('disabled', false).text('Set Default Settings');
                    }
                });
            });
        });
    </script>
    <?php
});



if(ENABLE_ECOMMERCE){
    add_filter('acf/location/rule_values/post_type', 'acf_location_rule_values_Post');
    function acf_location_rule_values_Post( $choices ) {
        $choices['product_variation'] = 'Product Variation';
        return $choices;
    }    
}