<?php

/*class GeoLocation_Query extends WP_Query {
	var $lat;
	var $lon;
	var $distance;

	function __construct( $args=array() ) {
		if( !empty( $args['lat'] ) ) {
			$this->lat = $args['lat'];
			$this->lon = $args['lon'];
			$this->distance = $args['distance'];
			add_filter('posts_fields', array($this, 'posts_fields'));
			add_filter('posts_groupby', array($this, 'posts_groupby'));
			add_filter('posts_join_paged', array($this, 'posts_join_paged'));
		}
		parent::query($args);
		remove_filter('posts_fields', array($this, 'posts_fields'));
		remove_filter('posts_groupby', array($this, 'posts_groupby'));
		remove_filter('posts_join_paged', array($this, 'posts_join_paged'));
	}

	function posts_fields($fields) {
		global $wpdb;
		$fields = $wpdb->prepare(" ((ACOS(SIN(%f * PI() / 180) * SIN(mtlat.meta_value * PI() / 180) + COS(%f * PI() / 180) * COS(mtlat.meta_value * PI() / 180) * COS((%f - mtlon.meta_value) * PI() / 180)) * 180 / PI()) * 60 * 1.1515) AS distance", $this->lat, $this->lat, $this->lon);
		return $fields;
	}

	function posts_groupby($where) {
		global $wpdb;
		$where .= $wpdb->prepare(" distance HAVING distance < %d ", $this->distance);
		return $where;
	}

	function posts_join_paged($join) {
		$join .= " INNER JOIN wp_postmeta AS mtlat ON (IF(mtlat.meta_value != wp_posts.ID, mtlat.meta_value, wp_posts.ID) = mtlat.post_id AND mtlat.meta_key = 'lat') ";
		$join .= " INNER JOIN wp_postmeta AS mtlon ON (IF(mtlon.meta_value != wp_posts.ID, mtlon.meta_value, wp_posts.ID) = mtlon.post_id AND mtlon.meta_key = 'lon') ";
		return $join;
	}
}*/



function GeoLocation_Query( $lat, $lon, $post_type = 'post', $distance = 5, $limit = 999999, $class = "Post" ) {
  global $wpdb;
  $earth_radius = 3959; // miles

  $sql = $wpdb->prepare( "
    SELECT DISTINCT
        p.ID,
        ( %d * acos(
        cos( radians( %s ) )
        * cos( radians( map_lat.meta_value ) )
        * cos( radians( map_lon.meta_value ) - radians( %s ) )
        + sin( radians( %s ) )
        * sin( radians( map_lat.meta_value ) )
        ) )
        AS distance
    FROM $wpdb->posts p
    INNER JOIN $wpdb->postmeta map_lat ON p.ID = map_lat.post_id
    INNER JOIN $wpdb->postmeta map_lon ON p.ID = map_lon.post_id
    WHERE p.post_type = '%s'
        AND p.post_status = 'publish'
        AND map_lat.meta_key = 'lat'
        AND map_lon.meta_key = 'lon'
    HAVING distance < %s
    ORDER BY distance ASC
    LIMIT %d",
    $earth_radius,
    $lat,
    $lon,
    $lat,
    $post_type,
    $distance,
    $limit
  );
  $ids = $wpdb->get_results( $sql );
  $args = array(
      "post_type" => $post_type,
      'post__in'  => wp_list_pluck( $ids, 'ID'),
      'posts_per_page' => count($ids),
  );
  $posts = Timber::get_posts($args, $class);
  foreach($posts as $key => $post){
  	  $posts[$key]->distance = $ids[$key]->distance;
  }
  return $posts;
}

