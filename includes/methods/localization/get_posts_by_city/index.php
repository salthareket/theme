<?php
echo json_encode(
                get_posts_by_city($vars["post_type"], $vars["city"])
            );
            die();