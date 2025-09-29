<?php

class Project {

	public function __construct() {
	}

	public function response(){
        return array(
            "error"       => false,
            "message"     => '',
            "description" => '',
            "data"        =>  "",
            "resubmit"    => false,
            "redirect"    => "",
            "refresh"     => false,
            "html"        => "",
            "template"    => ""
        );
    }

}
new Project();