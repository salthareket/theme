<?php

namespace SalthareketExtend;
use Timber\Image as TimberImage;

class Image extends TimberImage {

    public function get_focal_point(){
        if($this->post_type == "attachment" && wp_attachment_is_image( $this->ID ) ) {
            $focal_point = $this->meta("focal_point");
            return $focal_point ? $focal_point : ["hr" => "center", "vr" => "center"];
        }
        return [];
    }
    
    public function get_focal_point_class(){
        $focal_point = $this->get_focal_point();
        return " object-position-".$focal_point["vr"]."-".$focal_point["hr"]." ";
    }

    public function get_aspect_ratio(){
        return getAspectRatio($this->width, $this->height);
    }

}