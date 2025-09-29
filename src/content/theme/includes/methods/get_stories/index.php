<?php 

$outlet = new Project();
                    $stories = $outlet->kampanyalar($vars)["data"];

                    $language = array(
                    	"unmute" => trans('Sesi açmak için dokun'),
                    	"keyboardTip" => trans('Geçmek için "Boşluk" tuşuna tıklayın'),
                    	"visitLink" => trans("Linke git"),
                    	"time" => array(
                    		"ago" => trans('önce'),
                    		"hour" => trans('saat'),
                    		"hours" => trans('saat'),
                    		"minute" => trans('dakika'),
                    		"minutes" => trans('dakika'),
                    		"fromnow" => trans('şu andan beri'),
                    		"seconds" => trans('saniye'),
                    		"yesterday" => trans('dün'),
                    		"tomorrow" => trans('yarın'),
                    		"days" => trans('gün'),
                    	)
                    );
                    $output = array(
                       "stories" => $stories,
                       "language" => $language
                    );
                    echo json_encode($output);
                    die;
                     //print_r($output);
                     /*if(!$template){
                        $template = "partials/stories";
				    }
				    $templates = array( $template.'.twig' );
				    $context = Timber::get_context();
				    $context['posts'] = $output["data"];*/