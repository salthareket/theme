<?php
$modal = "";
            $data = [];
            $template = "tour-plan/comment-modal";

            $comment = new Timber\Comment(intval($vars["id"]));

            $title = $comment->comment_title;
            $comments = json_decode($comment->comment_content);
            $author = $comment->comment_author;
            //$rating = '<div class="star-rating-readonly-ui" data-stars="5" data-value="'.$comment->rating.'"></div>';
            $image = wp_get_attachment_image_url(
                $comment->comment_image,
                "medium_large"
            );
            $tour_plan_id = $comment->meta("comment_tour");
            $tour_plan_offer_id = get_field(
                "tour_plan_offer_id",
                $tour_plan_id
            );
            $agent_id = get_post_field("post_author", $tour_plan_offer_id);
            $agent = get_user_by("id", $agent_id);

            $destinations = get_terms(
                "taxonomy=destinations&include=" .
                    join(",", $comment->meta("comment_destination"))
            );
            $destinations = wp_list_pluck($destinations, "name");
            /*$destination_list = '<ul class="list-inline mb-0">';
                    foreach($destination as $item){
                       $destination_list .= '<li class="list-inline-item mb-2"><div class="btn btn-warning btn-unlinked rounded-pill">'.$item.'</div></li>';
                    }   
                    $destination_list .= '</ul>';*/

            $templates = [$template . ".twig"];
            $context = Timber::context();
            $context["title"] = $title;
            $context["comments"] = $comments;
            $context["author"] = $author;
            $context["image"] = $image;
            $context["agent"] = $agent;
            $context["destinations"] = $destinations;
            $context["vars"] = $vars;
            $data = [
                "error" => false,
                "message" => "",
                "data" => $data,
                "html" => "",
            ];