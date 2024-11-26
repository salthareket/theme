<?php 

Class Image{

	 private $defaults = array(
        'src' => '',
        'id' => null,
        'class' => '',
        'lazy' => true,
        'lazy_native' => false,
        'width' => null,
        'height' => null,
        'alt' => '',
        'post' => null,
        'type' => "img", // img, picture
        'resize' => false,
        'lcp' => false
    );

    private $args = array();
    private $attrs = array();
    private $prefix = "";
    private $has_breakpoints = false;
	private $is_single = false;
	private $breakpoints = $GLOBALS["breakpoints"];

    public function __construct($args = array()) {

        $this->args = array_merge($this->defaults, $args);
        
        if (empty($this->args['src'])) {
            return;
        }

        if($this->args["lcp"]){
	       $this->args["lazy"] = false;
	       $this->args["lazy_native"] = false;
	       $this->attrs["fetchpriority"] = "high";
	    }

        if(is_array($this->args["src"]) && in_array(array_keys($this->args["src"])[0], array_keys($this->breakpoints))){
			$values = remove_empty_items(array_values($this->args["src"]));
			if(count($values) == 1){
				$this->args["src"] = $values;
				$this->has_breakpoints = true;
			    $this->is_single = true;
			}
		}
    }

    public function set_values(){

    }

    public function has_breakpoints(){
    	
    }

}