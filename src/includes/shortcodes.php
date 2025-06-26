<?php




function get_option_shortcode( $atts ) {
    $a = shortcode_atts( array(
       'field' => ""
    ), $atts );
    return SaltBase::get_cached_option($a["field"]);//get_field($a["field"], "option");
}
add_shortcode( 'get_option', 'get_option_shortcode' );


function get_page_url_shortcode( $atts ) {
    $a = shortcode_atts( array(
       'path' => ""
    ), $atts );
    return get_permalink(get_page_by_path($a["path"]));
}
add_shortcode( 'get_page_url', 'get_page_url_shortcode' );

function translate_shortcode($atts, $content = null) {
    $atts = shortcode_atts(
        array(
            'text'   => '',
            'plural' => '',
            'count'  => 1,
        ),
        $atts,
        'translate'
    );
    $translated_text = __n($atts['text'], $atts['plural'], $atts['count']);
    return $translated_text;
}
add_shortcode('translate', 'translate_shortcode');


if(!ENABLE_ECOMMERCE){
   function salt_login_form_shortcode() {
      if (!is_user_logged_in()){
         $context = Timber::context();
         $post = Timber::get_post();
         $context['post'] = $post;
         Timber::render( array( 'my-account/form-login-native.twig' ), $context );
      }else{
        if(function_exists("my_account_content")){
           my_account_content(getUrlEndpoint());
        }
      }
   }
   function salt_add_login_shortcode() {
      add_shortcode( 'salt_my_account', 'salt_login_form_shortcode' );
   }
   add_action( 'init', 'salt_add_login_shortcode' );
}



add_action('wp_ajax_render_shortcode', 'render_shortcode_ajax');
add_action('wp_ajax_nopriv_render_shortcode', 'render_shortcode_ajax');
function render_shortcode_ajax() {
    if (!empty($_POST['shortcode'])) {
        $shortcode = wp_kses_post(stripslashes($_POST['shortcode'])); // Shortcode'u al ve escape karakterlerini kaldır
        echo do_shortcode($shortcode); // Shortcode'u render et ve sonucu döndür
    }
    wp_die(); // AJAX isteği sona erdir
}

add_action("init", function(){

    $shortcodes_list = array();

    $shortcodes_list[] = [
            'name' => 'Search Field',
            'shortcode' => 'search_field',
            'support_content' => false,
            'callback' => function($atts) {
                $context = Timber::context();
                $context["keyword"] = get_query_var("q");
                $context["atts"] = $atts;
                if (defined('ENABLE_SEARCH_HISTORY') && ENABLE_SEARCH_HISTORY) {
                    $context["salt"] = $GLOBALS["salt"];
                }
                return Timber::compile("partials/snippets/search-field.twig", $context);
            },
            'atts' => [
                'note' => [
                    'label' => trans("Uyarı"),
                    'ui' => 'note',
                    'value' => trans("Arama sonuçlarını görüntülemek için sayfaya <b>'Search Results'</b> block eklenmelidir"),
                    'func' => ''
                ],
                'size' => [
                    'label' => 'Field Size',
                    'ui' => 'select',
                    'value' => [
                         'sm' => "Small",
                         'lg' => "Large"
                     ],
                    'func' => '',
                ],
                'post_type' => [
                    'label' => 'Search Type',
                    'ui' => 'select',
                    'value' => [
                         'search' => "Global Search",
                     ],
                    'func' => 'post_types',
                ],
                'post_type_pagination' => [
                    'label' => "Use post type's custom pagination if possible",
                    'ui' => 'checkbox',
                    'value' => [
                         '1' => "Yes",
                     ],
                    'func' => '',
                    'visibility' => [
                        "show" => [
                            "post_type" => [
                                "compare" => "!=",
                                "value"   => "search"
                            ],
                            "same_page" => [
                                "compare" => "==",
                                "value"   => "1"
                            ]
                        ]
                    ]
                ],
                'post_type_hide_content' => [
                    'label' => "Show only search results n page",
                    'ui' => 'checkbox',
                    'value' => [
                         '1' => "Yes",
                     ],
                    'func' => ''
                ],
                'same_page' => [
                    'label' => 'Search in same page',
                    'ui' => 'checkbox',
                    'value' => [
                         '1' => "Yes",
                     ],
                    'func' => ''
                ],
                'placeholder' => [
                    'label' => 'Placeholder',
                    'ui' => 'text',
                    'value' => "",
                    'func' => ''
                ],
                'history' => [
                    'label' => 'Search History',
                    'ui' => 'select',
                    'value' => [
                         'no' => "No",
                         'user' => "User's last searches",
                         'popular' => "Popular Searches"
                     ],
                    'func' => ''
                ],
                'history_button' => [
                    'label' => 'History Button Class',
                    'ui' => 'text',
                    'value' => "",
                    'func' => '',
                    'visibility' => [
                        "show" => [
                            "history" => [
                                "compare" => "!=",
                                "value"   => "no"
                            ],
                        ]
                    ]
                ],
            ]
    ];

    $shortcodes_list[] = [
            'name' => 'Text Rotator',
            'shortcode' => 'text_rotator',
            'support_content' => false,
            'callback' => function($atts) {
                if(!empty($atts["words"])){
                    if (strpos($atts["words"], '|') !== false) {
                        $words = str_replace("|", ",", $atts["words"]);
                    }else{
                        $words = str_replace(" ", ",", $atts["words"]);
                    }
                    $words = explode(",", $words);
                    $words = array_filter(array_map('trim', $words), function($line) {
                        return !empty($line);
                    });
                    $words = implode("|", $words);

                    return "<span class='text-rotator invisible' data-text-rotator-animation='".$atts["animation"]."' data-text-rotator-speed='".$atts["speed"]."'>".$words."</span>";
                }else{
                    return "";
                }   
            },
            'atts' => [
                'words' => [
                    'label' => 'Words (seperate with "|")',
                    'ui' => 'textarea',
                    'value' => '',
                    'func' => ''
                ],
                'animation' => [
                    'label' => 'Animation',
                    'ui' => 'select',
                    'value' => [
                         'dissolve' => "Dissolve",
                         'flip' => "Flip",
                         'flipUp' => "Flip Up",
                         'flipCube' => "Flip Cube",
                         'flipCubeUp' => "Flip Cube Up",
                         'spin' => "Spin",
                         'fade' => "Fade",
                     ],
                    'func' => '',
                ],
                'speed' => [
                    'label' => 'Speed (ms)',
                    'ui' => 'text',
                    'value' => 2000,
                    'func' => ''
                ]
            ]
    ];

    $shortcodes_list[] = [
            'name' => 'Text Effect',
            'shortcode' => 'textillate',
            'support_content' => true,
            'callback' => function($atts, $content) {
                if(!empty($content)){
                    $default = [];
                    if(!empty($atts["lines"])&& !is_array($atts["lines"])){
                        $default["data-lines"] = $atts["lines"];
                    }
                    if(isset($atts["viewport"]) && !empty($atts["viewport"]) && $atts["viewport"] && !is_array($atts["viewport"])){
                        $default["data-viewport"] = true;
                    }
                    $default = array2Attrs($default);
                    $in = [];
                    if(!empty($atts["in"])){
                        $in["data-type"] = $atts["type"];
                        $in["data-min-display-time"] = $atts["min_display_time"];
                        $in["data-in-effect"] = $atts["in"];
                        $in["data-in-delay"] = $atts["in_delay"];
                        $in["data-in-".$atts["in_type"]] = "true";
                    }
                    $in = array2Attrs($in);
                    $out = [];
                    if(!empty($atts["out"])){
                        $out["data-out-effect"] = $atts["out"];
                        $out["data-out-delay"] = $atts["out_delay"];
                        $out["data-out-".$atts["out_type"]] = "true";
                        $out["data-loop"] = boolval($atts["loop"])?"true":false;
                    }
                    $out = array2Attrs($out);
                    return "<span class='text-effect invisible' ".$default." ".$in." ".$out.">".$content."</span>";
                }else{
                    return "";
                }   
            },
            'atts' => [
                'type' => [
                    'label' => 'Type',
                    'ui' => 'select',
                    'value' => [
                        "char" => "Letter Animation",
                        "word" => "Word Animation"
                    ],
                    'func' => ''
                ],
                'lines' => [
                    'label' => "Text Lines",
                    'ui' => 'select',
                    'value' => [
                        "" => "Default",
                        "rotate" => "Sequentially rotate each lines",
                        "split" => "Split spaces to lines"
                     ],
                    'func' => ''
                ],
                'viewport' => [
                    'label' => 'Play only inside viewport',
                    'ui' => 'checkbox',
                    'value' => [
                         '1' => "Yes",
                     ],
                    'func' => ''
                ],
                'min_display_time' => [
                    'label' => 'Display Time (ms)',
                    'ui' => 'text',
                    'value' => 2000,
                    'func' => ''
                ],
                'in' => [
                    'label' => 'In Animation Style',
                    'ui' => 'select',
                    'value' => [],
                    'func' => 'animations_in'
                ],
                'in_type' => [
                    'label' => 'In Animation Type',
                    'ui' => 'select',
                    'value' => [
                        "sequence" => "Sequence",
                        "reverse"  => "Reverse",
                        "sync"     => "Sync",
                        "shuffle"  => "Shuffle"
                    ],
                    'func' => ''
                ],
                'in_delay' => [
                    'label' => 'Character Delay',
                    'ui' => 'text',
                    'value' => 50,
                    'func' => ''
                ],
                'seperator' => [
                    'label' => '',
                    'ui' => 'seperator',
                    'value' => "",
                    'func' => ''
                ],
                'out' => [
                    'label' => 'Out Animation Style',
                    'ui' => 'select',
                    'value' => [
                       '' => "None"
                    ],
                    'func' => 'animations_out'
                ],
                'out_type' => [
                    'label' => 'Out Animation Type',
                    'ui' => 'select',
                    'value' => [
                        "sequence" => "Sequence",
                        "reverse"  => "Reverse",
                        "sync"     => "Sync",
                        "shuffle"  => "Shuffle"
                    ],
                    'func' => '',
                    'visibility' => [
                        "show" => [
                            "out" => [
                                "compare" => "!=",
                                "value"   => ""
                            ],
                        ]
                    ]
                ],
                'out_delay' => [
                    'label' => 'Character Delay',
                    'ui' => 'text',
                    'value' => 50,
                    'func' => '',
                    'visibility' => [
                        "show" => [
                            "out" => [
                                "compare" => "!=",
                                "value"   => ""
                            ],
                        ]
                    ]
                ],
                'loop' => [
                    'label' => "Loop",
                    'ui' => 'radio',
                    'value' => [
                         '0' => "No",
                         '1' => "Yes",
                     ],
                    'func' => '',
                    'visibility' => [
                        "show" => [
                            "out" => [
                                "compare" => "!=",
                                "value"   => ""
                            ],
                        ]
                    ]
                ],
                
            ]
    ];

    if (class_exists("WPCF7")) {
        $shortcodes_list[] = [
                'name' => 'CF7 Form',
                'shortcode' => 'contact_form',
                'support_content' => false,
                'callback' => function($atts) {
                    $shortcode = '[contact-form-7 id="' . esc_attr($atts["id"]) . '"]';
                    return do_shortcode($shortcode);
                },
                'atts' => [
                    'id' => [
                        'label' => 'Forms',
                        'ui' => 'select',
                        'value' => [],
                        'func' => 'cf7_forms'
                    ],
                ]
        ];    
    }

    if(isset($GLOBALS["custom_shortcodes"]) && $GLOBALS["custom_shortcodes"]){
       $shortcodes_list = array_merge($shortcodes_list, $GLOBALS["custom_shortcodes"]);
    }

    $shortcodes = customShortcodes::getInstance();
    $shortcodes->add($shortcodes_list);

});

