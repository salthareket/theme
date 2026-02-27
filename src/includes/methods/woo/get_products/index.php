<?php
$required_setting = ENABLE_ECOMMERCE;

if (isset($vars["kategori"])) {
                $page_type = "product_cat";
            }
            if (isset($vars["keyword"])) {
                $page_type = "search";
                Data::set("keyword", $vars["keyword"]);
                add_filter("posts_where", "sku_where");
            }

            $templates = [$template . ".twig"];
            $context = Timber::context();

            //$query = new WP_Query();

            $query = [];
            $query_response = category_queries_ajax($query, $vars);
            $query = $query_response["query"];
            //$GLOBALS["query_vars"] = woo_sidebar_filter_vars($vars); //$query_response["query_vars"];
            $data["query_vars"] = Data::get("query_vars");

            $closure = function ($sql) {
                //$role = array_keys($GLOBALS["user"]->roles)[0];

                //print_r($GLOBALS['query_vars']);
                // remove single quotes around 'mt1.meta_value'
                //print_r($sql);
                // $sql = str_replace("CAST(mt2.meta_value AS SIGNED)","CAST(mt2.meta_value-(mt2.meta_value/2) AS SIGNED)", $sql);// 50% indirim
                return str_replace("'mt2.meta_value'", "mt2.meta_value", $sql);
            };
            add_filter("posts_request", $closure);

            query_posts($query);
            //$query = new WP_Query($args);
            //$posts = new WP_Query( $query );

            remove_filter("posts_request", spl_object_hash($closure));

            $posts = Timber::get_posts();

            $context["posts"] = $posts; //_new;

            //$queried_object = get_queried_object();
            if (ENABLE_FAVORITES) {
                $context["favorites"] = Data::get("favorites");
            }

            $context["pagination_type"] = Data::get("site_config.pagination_type");

            if (Data::get("query_vars")) {
                $query_vars = Data::get("query_vars");
            }

            global $wp_query;
            $post_count = $wp_query->found_posts;
            $page_count = $wp_query->max_num_pages;
            $page = $wp_query->query_vars["paged"];
            $context["post_count"] = $post_count;
            $context["page_count"] = $page_count;
            $context["page"] = $page;

            //if(array_key_exists( "pagination", $context['posts'] )){
            $context["pagination"] = Timber::get_pagination(); //$context['posts']->pagination;//Timber::get_pagination();
            //}
            //$context['pagination'] = Timber::get_pagination();
            //print_r($context['posts']);

            //$context['page_count'] = 1;//Timber::get_pagination(array(),$context['posts']);//floor(abs(Timber::get_pagination()["total"])/$GLOBALS['ajax_product_count']);//Timber::get_pagination();
            //echo $page;//json_encode($query_args);
            //echo json_encode(get_posts($query_args));
            //die;

            /*if ($vars["product_filters"] && ENABLE_FILTERS) {
                $data["sidebar"] = Timber::compile(
                    "product/sidebar-product-filter.twig",
                    woo_sidebar_filters(
                        $context,
                        $page_type,
                        500,
                        $query,
                        $vars
                    )
                );
            }*/

            wp_reset_postdata();
            wp_reset_query();