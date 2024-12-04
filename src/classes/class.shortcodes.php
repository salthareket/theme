<?php
class customShortcodes {
    private $shortcodes = [];
    private static $instance = null;
    public $js = "";

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add($shortcode) {
        foreach ($shortcode as $sc) {
            if (isset($sc['shortcode']) && isset($sc['callback']) && isset($sc['atts'])) {
                $this->shortcodes[$sc['shortcode']] = $sc;
                add_shortcode($sc['shortcode'], function($atts, $content = null) use ($sc) {
                    $atts = shortcode_atts($sc['atts'], $atts);
                    return call_user_func($sc['callback'], $atts, $content);
                });
            }
        }
    }

    public function get_all() {
        return $this->shortcodes;
    }

    public function user_roles() {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $roles_array = [];
        foreach ($roles as $key => $role) {
            $roles_array[$key] = $role['name'];
        }
        return $roles_array;
    }

    public function image_sizes() {
        global $_wp_additional_image_sizes;
        $sizes = get_intermediate_image_sizes();
        $sizes_array = [];
        foreach ($sizes as $size) {
            if (isset($_wp_additional_image_sizes[$size])) {
                $sizes_array[$size] = $size . ' (' . $_wp_additional_image_sizes[$size]['width'] . 'x' . $_wp_additional_image_sizes[$size]['height'] . ')';
            } else {
                $sizes_array[$size] = $size;
            }
        }
        return $sizes_array;
    }

    public function post_types() {
        $post_types = get_post_types([], 'objects');
        $post_types_array = [];
        foreach ($post_types as $post_type) {
            if ($post_type->public) {
                $post_types_array[$post_type->name] = $post_type->labels->singular_name;
            }
        }
        return $post_types_array;
    }

    public function animations_in(){
        $animations = array(
            "flash" => "flash",
            "bounce" => "bounce",
            "shake" => "shake",
            "tada" => "tada",
            "swing" => "swing",
            "wobble" => "wobble",
            "pulse" => "pulse",
            "flip" => "flip",
            "flipInX" => "flipInX",
            "flipInY" => "flipInY",
            "fadeIn" => "fadeIn",
            "fadeInUp" => "fadeInUp",
            "fadeInDown" => "fadeInDown",
            "fadeInLeft" => "fadeInLeft",
            "fadeInRight" => "fadeInRight",
            "fadeInUpBig" => "fadeInUpBig",
            "fadeInDownBig" => "fadeInDownBig",
            "fadeInLeftBig" => "fadeInLeftBig",
            "fadeInRightBig" => "fadeInRightBig",
            "bounceIn" => "bounceIn",
            "bounceInDown" => "bounceInDown",
            "bounceInUp" => "bounceInUp",
            "bounceInLeft" => "bounceInLeft",
            "bounceInRight" => "bounceInRight",
            "rotateIn" => "rotateIn",
            "rotateInDownLeft" => "rotateInDownLeft",
            "rotateInDownRight" => "rotateInDownRight",
            "rotateInUpLeft" => "rotateInUpLeft",
            "rotateInUpRight" => "rotateInUpRight",
            "rollIn" => "rollIn"
        );
        return $animations;
    }

    public function animations_out(){
        $animations = array(
            'flash' => 'flash',
            'bounce' => 'bounce',
            'shake' => 'shake',
            'tada' => 'tada',
            'swing' => 'swing',
            'wobble' => 'wobble',
            'pulse' => 'pulse',
            'flip' => 'flip',
            'flipOutX' => 'flipOutX',
            'flipOutY' => 'flipOutY',
            'fadeOut' => 'fadeOut',
            'fadeOutUp' => 'fadeOutUp',
            'fadeOutDown' => 'fadeOutDown',
            'fadeOutLeft' => 'fadeOutLeft',
            'fadeOutRight' => 'fadeOutRight',
            'fadeOutUpBig' => 'fadeOutUpBig',
            'fadeOutDownBig' => 'fadeOutDownBig',
            'fadeOutLeftBig' => 'fadeOutLeftBig',
            'fadeOutRightBig' => 'fadeOutRightBig',
            'bounceOut' => 'bounceOut',
            'bounceOutDown' => 'bounceOutDown',
            'bounceOutUp' => 'bounceOutUp',
            'bounceOutLeft' => 'bounceOutLeft',
            'bounceOutRight' => 'bounceOutRight',
            'rotateOut' => 'rotateOut',
            'rotateOutDownLeft' => 'rotateOutDownLeft',
            'rotateOutDownRight' => 'rotateOutDownRight',
            'rotateOutUpLeft' => 'rotateOutUpLeft',
            'rotateOutUpRight' => 'rotateOutUpRight',
            'hinge' => 'hinge',
            'rollOut' => 'rollOut',
        );
        return $animations;
    }

    public function cf7_forms() {
        $forms = get_posts([
            'post_type' => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $forms_array = [];
        if (!empty($forms)) {
            foreach ($forms as $form) {
                $form_id = $form->ID;
                $form_title = get_the_title($form_id);
                $hash = get_post_meta($form_id, '_hash', true);
                if (!empty($hash)) {
                    $short_hash = substr($hash, 0, 7);
                    $forms_array[$short_hash] = $form_title;
                }
            }
        }

        return $forms_array;
    }

    public function get_fields($shortcode) {
        $output = '';

        if (!isset($this->shortcodes[$shortcode])) {
            return 'Shortcode not found.';
        }

        $atts = $this->shortcodes[$shortcode]['atts'];

        $js_code = "";

        foreach ($atts as $key => $value) {
            $type = isset($value['ui']) ? $value['ui'] : 'text';
            $label = isset($value['label']) ? $value['label'] : '';
            $options = isset($value['value']) ? $value['value'] : [];
            $func = isset($value['func']) ? $value['func'] : '';
            $visibility = isset($value['visibility']) ? $value['visibility'] : [];
            $class = $this->get_bootstrap_class($type);

            if ($func && method_exists($this, $func)) {
                $dynamic_options = $this->$func();
                $options = array_merge($options, $dynamic_options);
            }

            $field_html = '';
            switch ($type) {
                case 'text':
                case 'email':
                case 'number':
                    $field_html = $this->create_input($type, $key, $label, $class, $options);
                    break;
                case 'textarea':
                    $field_html = $this->create_textarea($key, $label, $class, $options);
                    break;
                case 'select':
                    $field_html = $this->create_select($key, $label, $options, $class);
                    break;
                case 'checkbox':
                    $field_html = $this->create_checkbox($key, $label, $options, $class);
                    break;
                case 'radio':
                    $field_html = $this->create_radio($key, $label, $options, $class);
                    break;
                case 'note':
                    $field_html = $this->create_note($key, $label, $options, $class);
                    break;
                case 'seperator':
                    $field_html = $this->create_seperator($key, $label, $options, $class);
                    break;
            }

            $output .= $field_html;

            if($visibility){
               $js_code .= $this->visibility($key, $visibility);
            }
        }
        //$this->js .= $js_code;

        return $output . $js_code;
    }


    private function get_bootstrap_class($type) {
        $bootstrap_classes = [
            'text' => 'form-control',
            'email' => 'form-control',
            'number' => 'form-control',
            'textarea' => 'form-control',
            'select' => 'form-select',
            'checkbox' => 'form-check-input',
            'radio' => 'form-check-input',
            'note' => 'alert alert-info',
            'seperator' => 'my-5'
        ];
        return isset($bootstrap_classes[$type]) ? $bootstrap_classes[$type] : 'form-control';
    }

    private function create_input($type, $name, $label, $class, $options) {
        return "<div class='form-group mb-3'>
                    <label for='$name' class='form-label'>$label</label>
                    <input type='$type' class='$class' id='$name' name='$name' value='$options'>
                </div>";
    }

    private function create_textarea($name, $label, $class, $options) {
        return "<div class='form-group mb-3'>
                    <label for='$name' class='form-label'>$label</label>
                    <textarea class='$class' id='$name' name='$name'>$options</textarea>
                </div>";
    }

    private function create_select($name, $label, $options, $class) {
        $options_html = '';
        foreach ($options as $value => $text) {
            $selected = false;
            if(strpos($value, "*") !== false){
                $selected = true;
                $value = str_replace("*", "", $value);
            }
            $options_html .= "<option value='$value' ".($selected?"selected":"").">$text</option>";
        }
        return "<div class='form-group mb-3'>
                    <label for='$name' class='form-label'>$label</label>
                    <select class='$class' id='$name' name='$name'>
                        $options_html
                    </select>
                </div>";
    }

    private function create_checkbox($name, $label, $options, $class) {
        $checkboxes_html = '';
        $multiple = count($options)>1?"[]":"";
        foreach ($options as $value => $text) {
            $checked = false;
            if(strpos($value, "*") !== false){
                $checked = true;
                $value = str_replace("*", "", $value);
            }
            $checkboxes_html .= "<div class='form-check'>
                                    <input class='$class' type='checkbox' id='{$name}-{$value}' name='{$name}{$multiple}' value='$value' ".($checked?"checked":"").">
                                    <label class='form-check-label' for='{$name}-{$value}'>$text</label>
                                </div>";
                                $checked = true;
        }
        return "<div class='form-group mb-3'>
                    <label class='form-label'>$label</label>
                    $checkboxes_html
                </div>";
    }

    private function create_radio($name, $label, $options, $class) {
        $radios_html = '';
        foreach ($options as $value => $text) {
            $checked = false;
            if(strpos($value, "*") !== false){
                $checked = true;
                $value = str_replace("*", "", $value);
            }
            $radios_html .= "<div id='$name' class='form-check'>
                                <input class='$class' type='radio' id='{$name}-{$value}' name='$name' value='$value' ".($checked?"checked":"").">
                                <label class='form-check-label' for='{$name}-{$value}'>$text</label>
                            </div>";
                            $checked = true;
        }
        return "<div class='form-group mb-3'>
                    <label class='form-label'>$label</label>
                    $radios_html
                </div>";
    }

    private function create_note($name, $label, $options, $class) {
        return "<div class='$class mb-3'>
                    <h3 class='mb-2'>$label</h3>
                    $options
                </div>";
    }

    private function create_seperator($name, $label, $options, $class) {
        return "<hr class='$class'/>";
    }

    private function visibility($elementId, $visibility) {
        $code = "";

        $operators = [
            "==" => "==",
            "!=" => "!=",
            ">"  => ">",
            "<"  => "<",
            ">=" => ">=",
            "<=" => "<="
        ];

        if (isset($visibility['show'])) {
            $conditions = [];
            foreach ($visibility['show'] as $dependentId => $condition) {
                $compare = $condition['compare'];
                $expectedValue = $condition['value'];
                $operator = isset($operators[$compare]) ? $operators[$compare] : "==";
                $conditions[] = "
                    dependentElements['$dependentId'] &&
                    (
                        (dependentElements['$dependentId'].type === 'checkbox' && dependentElements['$dependentId'].checked && dependentElements['$dependentId'].value $operator '$expectedValue') ||
                        (dependentElements['$dependentId'].type !== 'checkbox' && dependentElements['$dependentId'].value $operator '$expectedValue')
                    )
                ";
            }
            $conditionsJS = implode(' && ', $conditions);

            $code .= "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var element = document.querySelector('#$elementId');
                    if (element) {
                        element = element.closest('.form-group');
                        var dependentElements = {
                            " . implode(",\n", array_map(function($dependentId) {
                                return "'$dependentId': document.getElementById('$dependentId')";
                            }, array_keys($visibility['show']))) . "
                        };

                        function checkVisibility() {
                            if ($conditionsJS) {
                                element.style.display = 'block';
                            } else {
                                element.style.display = 'none';
                            }
                        }

                        Object.values(dependentElements).forEach(function(dependentElement) {
                            if (dependentElement) {
                                dependentElement.addEventListener('change', checkVisibility);
                            }
                        });

                        checkVisibility();
                    }
                });
            </script>";
        }
        
        if (isset($visibility['hide'])) {
            $conditions = [];
            foreach ($visibility['hide'] as $dependentId => $condition) {
                $compare = $condition['compare'];
                $expectedValue = $condition['value'];
                $operator = isset($operators[$compare]) ? $operators[$compare] : "==";
                $conditions[] = "
                    dependentElements['$dependentId'] &&
                    (
                        (dependentElements['$dependentId'].type === 'checkbox' && dependentElements['$dependentId'].checked && dependentElements['$dependentId'].value $operator '$expectedValue') ||
                        (dependentElements['$dependentId'].type !== 'checkbox' && dependentElements['$dependentId'].value $operator '$expectedValue')
                    )
                ";
            }
            $conditionsJS = implode(' && ', $conditions);

            $code .= "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var element = document.querySelector('#$elementId').closest('.form-group');
                    var dependentElements = {
                        " . implode(",\n", array_map(function($dependentId) {
                            return "'$dependentId': document.getElementById('$dependentId')";
                        }, array_keys($visibility['hide']))) . "
                    };

                    function checkVisibility() {
                        if ($conditionsJS) {
                            element.style.display = 'none';
                        } else {
                            element.style.display = 'block';
                        }
                    }

                    Object.values(dependentElements).forEach(function(dependentElement) {
                        if (dependentElement) {
                            dependentElement.addEventListener('change', checkVisibility);
                        }
                    });

                    checkVisibility();
                });
            </script>";
        }

        if (isset($visibility['show_or'])) {
            $conditions = [];
            foreach ($visibility['show_or'] as $dependentId => $condition) {
                $compare = $condition['compare'];
                $expectedValue = $condition['value'];
                $operator = isset($operators[$compare]) ? $operators[$compare] : "==";
                $conditions[] = "
                    dependentElements['$dependentId'] &&
                    (
                        (dependentElements['$dependentId'].type === 'checkbox' && dependentElements['$dependentId'].checked && dependentElements['$dependentId'].value $operator '$expectedValue') ||
                        (dependentElements['$dependentId'].type !== 'checkbox' && dependentElements['$dependentId'].value $operator '$expectedValue')
                    )
                ";
            }
            $conditionsJS = implode(' || ', $conditions);

            $code .= "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var element = document.querySelector('#$elementId').closest('.form-group');
                    var dependentElements = {
                        " . implode(",\n", array_map(function($dependentId) {
                            return "'$dependentId': document.getElementById('$dependentId')";
                        }, array_keys($visibility['show_or']))) . "
                    };

                    function checkVisibility() {
                        if ($conditionsJS) {
                            element.style.display = 'block';
                        } else {
                            element.style.display = 'none';
                        }
                    }

                    Object.values(dependentElements).forEach(function(dependentElement) {
                        if (dependentElement) {
                            dependentElement.addEventListener('change', checkVisibility);
                        }
                    });

                    checkVisibility();
                });
            </script>";
        }

        return $code;
    }

    public function get_shortcodes() {
        $options = '';
        foreach ($this->shortcodes as $shortcode => $sc) {
            $label = isset($sc['name']) ? $sc['name'] : $shortcode;
            $options .= "<option value='$shortcode'>$label</option>";
        }
        return "<div class='mb-3'><label for='shortcodes' class='form-label'>Shortcodes</label><select class='form-select' name='shortcodes' id='shortcodes'>$options</select></div>" .
            "<div class='mb-3'><div class='form-check ps-0'><input type='checkbox' id='render_shortcode' name='render_shortcode' value='true'><label class='form-check-label ps-2' for='render_shortcode'>Render Shortcode</label></div></div>";
    }
}

