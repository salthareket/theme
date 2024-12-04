<?php
$error = false;
                   $message = "";
                   $events_data = array();
                      /*$outlet = new Outlet();
                      $events = $outlet->etkinlikler($vars)["data"];
                      
                       foreach ( $events as $event ) {
                               $event_item=array();
                               $event_item['id']=$event->ID;
                                $event_item['title']=$event->post_title; 
                                $event_item['date']=date_format(date_create($event->start_date),'Y-m-d');
                                $event_item['category']=$event->post_type;
                                //$event_item['color']=post_type_color($event->post_type);//get_post_meta( $id, 'color', true );
                                $event_item['url'] = get_post_type_archive_link("etkinlikler")."?event_date=".$event_item['date'];//get_permalink($event->ID);
                                array_push($events_data, $event_item);
                       }*/
                       $output = array(
                            "error"   => $error,
                            "message" => $message,
                            "data"    => $events_data
                       );
                    echo json_encode($output);
                    die;