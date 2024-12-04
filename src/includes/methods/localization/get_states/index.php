<?php
$woo_countries = new WC_Countries();
            $states = $woo_countries->get_states($vars["id"]);
            echo json_encode($states);
            die();