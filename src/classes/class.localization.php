<?php 

Class Localization{

	    private $country;
	    public $woocommerce_support = ENABLE_ECOMMERCE;
	    protected  $ipdat;

	    function __construct(){
	    	global $wpdb;
	    	if(ENABLE_IP2COUNTRY && ENABLE_IP2COUNTRY_DB){
		    	$table = "ip2country";
		        if (!$wpdb->get_var("SHOW TABLES LIKE '".$table."'")) {
				    $this->create_table($table);
				}	    		
	    	}else{
	    		$table = "ip2country";
	    		$this->remove_table($table);

	    	}
	    	if((ENABLE_IP2COUNTRY && ENABLE_IP2COUNTRY_DB) || ENABLE_LOCATION_DB){
		    	$table = "countries";
			    if (!$wpdb->get_var("SHOW TABLES LIKE '".$table."'")) {
					$this->create_table($table);
				}
			    $table = "states";
			    if (!$wpdb->get_var("SHOW TABLES LIKE '".$table."'")) {
					$this->create_table($table);
				}
			}else{
	    		$table = "countries";
	    		$this->remove_table($table);
	    		$table = "states";
	    		$this->remove_table($table);
	    	}
	    }
	    public function woocommerce_support($value=true){
	    	$this->woocommerce_support = $value;
	    }
	    Private function create_table($table) {
			$filename = SH_STATIC_PATH . "data/".$table.".sql";
			$mysql_host = DB_HOST;
			$mysql_username = DB_USER;
			$mysql_password = DB_PASSWORD;
			$mysql_database = DB_NAME;
			$conn = mysqli_connect($mysql_host, $mysql_username, $mysql_password) or die('Error connecting to MySQL server: ' . mysqli_error($conn));
			mysqli_select_db($conn, $mysql_database) or die('Error selecting MySQL database: ' . mysqli_error($conn));
			$templine = '';
			$lines = file($filename);
			foreach ($lines as $line){
				// Skip it if it's a comment
				if (substr($line, 0, 2) == '--' || $line == '')
				    continue;
				$templine .= $line;
				if (substr(trim($line), -1, 1) == ';'){
				    mysqli_query($conn, $templine);// or print('Error performing query \'<strong>' . $templine . '\': ' . mysqli_error($conn) . '<br /><br />');
				    $templine = '';
				}
			}
			mysqli_close($conn);
			//echo "Tables imported successfully";
	    }

	    Private function remove_table($table) {
		    global $wpdb;
		    $table_name = $table;
		    $sql = "DROP TABLE IF EXISTS $table_name;";
		    $wpdb->query($sql);
		}

		public function where($vars){
			$where = "";
			if($vars){
			    $index = 0;
			    $where .= " where ";
			    foreach($vars as $key => $var){
			      $var = !is_numeric($var) || isset($var[0]) && $var[0] === "0"?"'$var'":$var;
			   	  $where .= "$key = $var".($index < count($vars)-1?" and ":"");
			   	  $index++;
			    }
			}
			return $where;
		}
	    
		public function regions(){
			global $wpdb;
			$query = "SELECT region FROM countries where region != '' group by region";
			return wp_list_pluck($wpdb->get_results($query), "region"); 
        }
        public function subregions($vars=array()){
			global $wpdb;
			$selected = "";
			if($vars){
				if(isset($vars["selected"])){

				}
			}
			$where = $this->where($vars);
			$query = "SELECT subregion FROM countries where subregion != '' $where group by subregion";
			return wp_list_pluck($wpdb->get_results($query), "subregion"); 
        }
        public function countries($vars=array()){
			global $wpdb;
			$where = $this->where($vars);
			//$query = "SELECT id, name, iso2, phonecode, region, subregion, latitude, longitude, timezones, (select count(id) from states where states.country_code = countries.iso2) as states FROM countries $where order by name";
			$query = "SELECT
					    c.id,
					    c.name,
					    c.iso2,
					    c.phonecode,
					    c.region,
					    c.subregion,
					    c.latitude,
					    c.longitude,
					    c.timezones,
					    COUNT(s.id) AS states,
					    IFNULL(o.option_value, 0) AS user_count,
					    IFNULL(p.option_value, 0) AS post_count
					FROM
					    countries c
					LEFT JOIN
					    states s ON s.country_code = c.iso2 COLLATE utf8mb4_unicode_520_ci
					LEFT JOIN
					    wp_options o ON o.option_name = CONCAT('country_', c.iso2, '_user_count') COLLATE utf8mb4_unicode_520_ci
					LEFT JOIN
					    wp_options p ON p.option_name = CONCAT('country_', c.iso2, '_post_count') COLLATE utf8mb4_unicode_520_ci
					    $where
					GROUP BY
					    c.id, c.name, c.iso2, c.phonecode, c.region, c.subregion, c.latitude, c.longitude, c.timezones
					ORDER BY
					    c.name";
			$result =  $wpdb->get_results($query);
			return json_decode(json_encode($result), true);
        }
        public function states($vars=array()){
			global $wpdb;
			$where = $this->where($vars);
			if($vars){
			    if($this->woocommerce_support){
				    $where .= " and c.woo IS NOT NULL ";
				}
			}else{
				if($this->woocommerce_support){
					$where = " where c.woo IS NOT NULL ";
				}
			}
			//$query = "SELECT id, name, country_code, iso2, fips_code, latitude, longitude FROM states $where order by name";
			$query = "SELECT
					    c.id,
					    c.name,
					    c.country_code,
					    c.iso2,
					    c.fips_code,
					    c.latitude,
					    c.longitude,
					    IFNULL(o.option_value, 0) AS user_count,
					    IFNULL(p.option_value, 0) AS post_count
					FROM
					    states c
					LEFT JOIN
					    wp_options o ON o.option_name = CONCAT('state_', c.id, '_user_count')
					LEFT JOIN
					    wp_options p ON p.option_name = CONCAT('state_', c.id, '_post_count')
					    $where
					GROUP BY
					    c.id, c.name, c.country_code, c.iso2, c.fips_code, c.latitude, c.longitude
					ORDER BY
					    c.name";
			$result =  $wpdb->get_results($query);
			return json_decode(json_encode($result), true);
        }
        public function cities($vars=array()){
			global $wpdb;
			$where = $this->where($vars);
			$query = "SELECT id, name, state_id, state_code, latitude, longitude FROM cities $where order by name";
			$result =  $wpdb->get_results($query);
			return json_decode(json_encode($result), true);
        }




        public function get_state_woo_data($id=0){
        	global $wpdb; 
            $query = "SELECT name, woo FROM states WHERE id = '$id'";
            return $wpdb->get_results($query, ARRAY_A);
        }

        public function has_state($country_code=""){
        	global $wpdb;
        	$query = "SELECT count(*) FROM states where country_code='$country_code'";
        	if($this->woocommerce_support){
				$query .= " and woo IS NOT NULL ";
			}
			return $wpdb->get_var($query);
        }

        public function hierarchy($add_subregion=true, $selected = "", $all = false){
        	$results = array();
        	$regions = $this->regions();
        	foreach($regions as $key => $region){
        		$item = array(
                    "name" => $region,
                    "children" => array()
        		);
        		if($add_subregion){
	        		$subregions = $this->subregions([
	        			"region" => $region
	        		]);
	        		foreach($subregions as $key_sub => $subregion){
	        			$item["children"][] = array(
	                       "name" => $subregion,
	                       "children" => array()
	        			);
		        		$countries = $this->countries([
		                    "subregion" => $subregion
		        		]);
		        		if($countries){
		        			$item["children"][$key_sub]["children"] = $countries;
		        		}else{
		        			unset($item["children"][$key_sub]);
		        		}
	        	    }        			
        		}else{
        			$countries = $this->countries([
		                 "region" => $region
		        	]);
		        	if($countries){
		        		if($all){
			                $item["children"][] = array(
			                    "name" => "All ".$region,
			                    "slug" => "",
			                    "selected" => true
			                );
			            }
			            foreach($countries as $country){
			            	$item["children"][] = array(
			                    "name" => $country->name,
			                    "slug" => $country->iso2,
			                    "selected" => ($country->name == $selected? true : false)
			                );
			            }
		        	}else{
		        		unset($item);
		        	}
        		}
        		if($item){
        	       $results[] = $item;        			
        		}
        	}
        	return $results;
        }

        function get_available_cities($post_type='post', $meta_key='city', $country = '', $selected=''){
			if(empty($country)){
			   $country = wc_get_base_country();
			}
			global $wpdb; 
			$query = "SELECT m.meta_value as city  FROM wp_posts p, wp_postmeta m
					    WHERE p.ID = m.post_id 
					    AND m.meta_key = %s
					    AND p.post_type = %s AND p.post_status = 'publish' group by city order by city ASC";

			$result = $wpdb->prepare($query, [$meta_key, $post_type]);
			$result = $wpdb->get_results($result);
			$output = array();
			foreach($result as $city){
				$output[$city->city] = get_state_name("id", $city->city);//get_state_by_code($country, $city->city);
			}
			return $output;
		}

		function get_available_districts($post_type='post', $city = ''){
			global $wpdb; 
			$query = "SELECT m2.meta_value as district FROM wp_posts p, wp_postmeta m, wp_postmeta m2
					    WHERE 
					        m.post_id = p.ID
					    AND m.meta_key = 'city'
					    AND m.meta_value = %s
					    AND m2.post_id  = p.ID
					    AND m2.meta_key  = 'district'
					    AND p.post_type = %s AND p.post_status = 'publish' group by district order by district ASC";

			$result = $wpdb->prepare($query, [$city, $post_type]);
			$result = $wpdb->get_results($result);
			$output = array();
			foreach($result as $district){
				$output[$district->district] = $district->district;
			}
			return $output;
		}

		function get_posts_by_district($post_type='post', $city = '', $district=''){
			global $wpdb; 
			$query = "SELECT p.*  FROM wp_posts p, wp_postmeta m, wp_postmeta m2
					    WHERE 
					        m.post_id = p.ID
					    AND m.meta_key = 'city'
					    AND m.meta_value = %s
					    AND m2.post_id  = p.ID
					    AND m2.meta_key  = 'district'
					    AND m2.meta_value = %s
					    AND p.post_type = %s AND p.post_status = 'publish' order by p.post_title ASC";
			$result = $wpdb->prepare($query, [$city, $district, $post_type]);
			return Timber::get_posts($wpdb->get_results($result));
		}

		function get_locations_order_by_city($post_type='post'){
			$args = array(
				   'post_type' => $post_type,
		           'posts_per_page' => -1,
		           'meta_key' => 'city',
		           'orderby' => 'meta_value', 
		           'order' => 'ASC'
		    );
		    $args = array(
		    	'post_type' => $post_type,
		        'posts_per_page' => -1,
		    	'meta_query' => array(
			        'relation' => 'AND',
			        'city' => array(
			            'key' => 'city'
			        ),
			        'district' => array(
			            'key' => 'district'
			        )
			    ),
			    'orderby' => array( 
			        'city' => 'ASC',
			        'district' => 'ASC'
			    )
		    );
		    return Timber::get_posts($args);
		}

		function get_timezone($cur_lat, $cur_long, $country_code = '', $city_name='') {
			$timezone_data = array();
			if($country_code){
				$country_data = $this->countries([
                    "iso2" => $country_code
				]);
				if($country_data){
					$timezones = json_decode($country_data[0]["timezones"], true);
					if($timezones){
						if(count($timezones) == 1){
							$timezone_data = array(
								"gmtOffset"     => $timezones[0]["gmtOffset"],
								"gmt" => str_replace("UTC", "", $timezones[0]["gmtOffsetName"]),
								"timezone"      => $timezones[0]["zoneName"],
							);
		                }else{
		                	if($city_name){
		                		$city_name = str_replace(" ", "_", ucwordstr($city_name));
			                	foreach($timezones as $timezone){
				                	if(strpos($timezone["zoneName"], $city_name) > 0){
				                		$timezone_data = array(
											"gmtOffset"     => $timezone["gmtOffset"],
											"gmt" => str_replace("UTC", "", $timezone["gmtOffsetName"]),
											"timezone"      => $timezone["zoneName"],
										);
				                		break;
				                	}	                		
			                	}	                		
		                	}
		                }
					}					
				}
			}

			if(!$timezone_data && $country_code && $cur_lat && $cur_long){
                $timezone_ids = ($country_code) ? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country_code)
			                                    : DateTimeZone::listIdentifiers();
			    if($timezone_ids && is_array($timezone_ids) && isset($timezone_ids[0])) {
			        $time_zone = '';
			        $tz_distance = 0;
			        if (count($timezone_ids) == 1) {
			            $time_zone = $timezone_ids[0];
			        } else {
			            foreach($timezone_ids as $timezone_id) {
			                $timezone = new DateTimeZone($timezone_id);
			                $location = $timezone->getLocation();
			                $tz_lat   = $location['latitude'];
			                $tz_long  = $location['longitude'];
			                $theta    = $cur_long - $tz_long;
			                $distance = (sin(deg2rad($cur_lat)) * sin(deg2rad($tz_lat))) 
			                + (cos(deg2rad($cur_lat)) * cos(deg2rad($tz_lat)) * cos(deg2rad($theta)));
			                $distance = acos($distance);
			                $distance = abs(rad2deg($distance));
			                if (!$time_zone || $tz_distance > $distance) {
			                    $time_zone   = $timezone_id;
			                    $tz_distance = $distance;
			                } 
			            }
			        }
			        $timezone_data = $this->get_gmt($time_zone);
			        $timezone_data["timezone"] = $time_zone;
			        return $timezone_data;
			    }	
			}

		    return $timezone_data;
		}

		function get_gmt($timezone=""){
			if(empty($timezone)){
				$thimezone = $GLOBAL["user"]->get_timezone();
			}
			$origin_tz = $timezone;
			$remote_tz = "UTC";
			$origin_dtz = new DateTimeZone($origin_tz);
			$remote_dtz = new DateTimeZone($remote_tz);
			$origin_dt = new DateTime("now", $origin_dtz);
			$remote_dt = new DateTime("now", $remote_dtz);
			$gmtOffset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt); // TIME DIFFERENCE IN SECONDS
			//$interval = ($gmtOffset/(60*60)); // TIME DIFFERENCE IN HOURS
			return array(
                "gmtOffset"     => $gmtOffset,
                "gmt" => $origin_dt->format('P')//$this->number_to_time($interval, $timezone)
			);
		}

		function get_country_iso2(){
			$file = get_stylesheet_directory_uri() . "/static/data/country-iso2.json";
			$data = file_get_contents($file);
			return json_decode($data, true);
		}

		function get_country_iso_list(){
			$arr = array();
			$countries = $this->get_country_iso2();
			$languages = $GLOBALS["languages"];
			foreach($countries as $country){
	            $code = strtolower($country["code"]);
	            $lang = $country["lang"];
	            $lang_item = "";
	            if(!empty($lang)){
	                foreach($languages as $item){
	                    if($lang == $item["name"]){
	                       $lang_item = $item["name"];
	                       continue;
	                    }
	                }                
	            }
	            if(empty($lang_item)){
	                $lang_item = "en";                
	            }
	            $arr[] = array(
	            	"name"    => $country["name"],
	            	"code"    => $code,
	            	"lang"    => $lang_item
	            );
	        }
	        return $arr;
		}
        
        function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
			if(ENABLE_IP2COUNTRY_DB){
				return $this->ip2Country($ip);
			}else{
				return $this->ip2Country_api($ip, $purpose, $deep_detect);
			}
		}
        
      // https://stackoverflow.com/questions/12553160/getting-visitors-country-from-their-ip
		function ip2Country($ip = NULL, $purpose = "country", $deep_detect = TRUE) {
		    $output = NULL;
		    if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
		        $ip = $_SERVER["REMOTE_ADDR"];
		        if ($deep_detect) {
		            if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
		                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		            if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
		                $ip = $_SERVER['HTTP_CLIENT_IP'];
		        }
		    }

		    if (filter_var($ip, FILTER_VALIDATE_IP) ){
		    	$ipLong = ip2long($ip);
		    	if(!is_numeric($ipLong)){
		    		$country = "";
		    	}else{
			    	global $wpdb;
	                $country = $wpdb->get_row( "select countries.name, countries.iso2 from ip2country, countries where ".$ipLong." >= ip2country.ipfrom and ".$ipLong." <= ip2country.ipto and ip2country.country = countries.iso2");		    		
		    	}
                $output = $country;
		    }
		    return $output;
		}

		function ip2Country_api($ip = NULL, $purpose = "country", $deep_detect = TRUE) {
		    $output = NULL;
		    if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
		        $ip = $_SERVER["REMOTE_ADDR"];
		        if ($deep_detect) {
		            if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
		                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		            if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
		                $ip = $_SERVER['HTTP_CLIENT_IP'];
		        }
		    }
		    //$purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
		    $purpose = str_replace(array("name", "\n", "\t", " ", "-", "_"), "", strtolower(trim($purpose)));
		    $support    = array("country", "countrycode", "state", "region", "city", "location", "address");

		    if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
		    	if(!$this->ipdat){
					$ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
					//$ipdat = @json_decode(file_get_contents("https://ipinfo.io/".$ip."?token=b157881ab7b6eb"));
					$this->ipdat = $ipdat;	    		
		    	}else{
		    		$ipdat = $this->ipdat;
		    	}
		        if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
		            switch ($purpose) {
		                case "location":
		                    $output = array(
		                        "city"           => @$ipdat->geoplugin_city,
		                        "state"          => @$ipdat->geoplugin_regionName,
		                        "country"        => @$ipdat->geoplugin_countryName,
		                        "name"           => @$ipdat->geoplugin_countryName,
		                        "country_code"   => @$ipdat->geoplugin_countryCode,
		                        "continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
		                        "continent_code" => @$ipdat->geoplugin_continentCode,
		                        "iso2"           => @$ipdat->geoplugin_countryCode,
		                    );
		                    break;
		                case "address":
		                    $address = array($ipdat->geoplugin_countryName);
		                    if (@strlen($ipdat->geoplugin_regionName) >= 1)
		                        $address[] = $ipdat->geoplugin_regionName;
		                    if (@strlen($ipdat->geoplugin_city) >= 1)
		                        $address[] = $ipdat->geoplugin_city;
		                    $output = implode(", ", array_reverse($address));
		                    break;
		                case "city":
		                    $output = @$ipdat->geoplugin_city;
		                    break;
		                case "state":
		                    $output = @$ipdat->geoplugin_regionName;
		                    break;
		                case "region":
		                    $output = @$ipdat->geoplugin_regionName;
		                    break;
		                case "country":
		                case "name":
		                    $output = @$ipdat->geoplugin_countryName;
		                    break;
		                case "countrycode":
		                case "iso2":
		                    $output = @$ipdat->geoplugin_countryCode;
		                    break;
		            }
		        }
		    }
		    return $output;
		}

		/*function get_dts($timezone=""){
			$return = 0;
			if(!empty($timezone)){
				$date = new DateTime('now', new DateTimeZone($timezone)); 
                $return = intval($date->format('I'));
			}
			return $return;
		}

		function number_to_time($dec, $timezone=""){
		    $seconds = ($dec * 3600);
		    $hours = floor($dec);
		    $seconds -= $hours * 3600;
		    $minutes = floor($seconds / 60);
		    $seconds -= $minutes * 60;
		    $hours = $hours + $this->get_dts($timezone);
		    return $this->numpad($hours).":".$this->numpad($minutes);
		}
		function numpad($num){
		    $negative = false;
		    if(strpos($num, "-") > -1){
		       $negative = true;
		       $num = str_replace("-","", $num);
		    }
		    return ($negative?"-":"").str_pad($num, 2, 0, STR_PAD_LEFT);
		}*/

}


// get location names
function get_country_name($key="", $value=""){
    if(empty($key)){
        $key = is_numeric($var)?"id":"iso2";
    }
    if(ENABLE_ECOMMERCE){
        /*$WC_Countries = new WC_Countries();
        $country_list = $WC_Countries->__get( "countries" );
        if(isset($country_list[$iso2])){
           return $country_list[$iso2];
        }else{
           return $iso2;       
        }*/
        return WC()->countries->countries[$value];
    }else{
        $localization = new Localization();
        $args = array(
            $key => $value
        );
        $result = $localization->countries($args);
        if($result){
            return $result[0]["name"];
        }else{
            return $value;
        }
    }
}
function get_city_name($by="id", $id = 0){
    if(ENABLE_ECOMMERCE){
        global $wpdb; 
        $query = "SELECT country_code, iso2  FROM states 
                         WHERE $by = %s 
                         order by name ASC";
        $result = $wpdb->prepare($query, [$id]);
        $data = $wpdb->get_results($result);
        return WC()->countries->get_states( $data["country_code"] )[$data["iso2"]];
    }else{
        global $wpdb; 
        $query = "SELECT name FROM states 
                         WHERE $by = %s 
                         order by name ASC";
        $result = $wpdb->prepare($query, [$id]);
        $result = $wpdb->get_var($result);
        if($result){
            return $result;
        }        
    }
}
function get_district_name($by="id", $id = 0){
    global $wpdb; 
    $query = "SELECT name FROM cities 
                     WHERE $by = %s 
                     order by name ASC";
    $result = $wpdb->prepare($query, [$id]);
    $result = $wpdb->get_var($result);
    if($result){
        return $result;
    }
}




function get_countries($continent_value="", $selected = "", $all = false){
    $data = array();
    if(!ENABLE_ECOMMERCE){
        $loc = new Localization();
        $loc->woocommerce_support = true;
        $data = $loc->hierarchy(false, $selected , $all);
    }else{
        $data = array();
        $WC_Countries = new WC_Countries();
        $country_list = $WC_Countries->__get( "countries" );
        $continent_list = $WC_Countries->get_continents();
        if(!empty($continent_value)){
            if($all){
               $data[] = array(
                    "name" => "All ".$continent_list[$continent_value]["name"],
                    "slug" => "",
                    "selected" => true
                );
            }
            foreach ($continent_list[$continent_value]["countries"] as $country) {
                $data[] = array(
                    "name" => $country_list[$country],
                    "slug" => $country,
                    "selected" => ($country == $selected? true : false)
                );
            }
        }else{
            foreach ($continent_list as $key => $continent) {
                $countries = array();
                foreach ($continent["countries"] as $country) {
                    $countries[] = array(
                        "name" => $country_list[$country],
                        "slug" => $country
                    );
                }
                $continent = array(
                    "name" => $continent["name"],
                    "children" => $countries
                );
                $data[] = $continent;
            }
        }
        return $data;
    }         
}
function get_cities($country_value="", $selected = ""){
    global $wpdb; 
    $query = "SELECT id as slug, name FROM states 
                     WHERE country_code = %s 
                     order by name ASC";
    $result = $wpdb->prepare($query, [$country_value]);
    $result = $wpdb->get_results($result);
    if($result){
        if(!empty($selected)){
          $result_selected = array();
          foreach($result as $key => $city){
             if($city->slug == $selected){
                $result[$key]->selected = true;
             }
          }
       }
       return $result;      
    }else{
        return array(array(
            "name" => get_country_name("code", $country_value),
            "slug" => $country_value
        ));
    }
}
function get_states($country=""){
    $states = array();
    if(!ENABLE_ECOMMERCE){
        $localization = new Localization();
        $args = array(
            "country_code" => $country
        );
        $result = $localization->states($args);
        if($result){
            foreach($result as $item){
                $key = $item["country_code"].$item["iso2"];
                $states[$key] = $item["name"];
            }
        }
    }else{
        $states = WC()->countries->get_states( $country );
    }
    return $states;
}
function get_districts($state=""){
    $states = array();
    //if(!ENABLE_ECOMMERCE){
        $localization = new Localization();
        $state_code = substr($state, -2); // Son iki karakteri al
        $country_code = substr($state, 0, -2); 
        $args = array(
            "country_code" => $country_code,
            "state_code" => $state_code
        );
        
        $result = $localization->cities($args);
        if($result){
            foreach($result as $item){
                $key = $item["id"];
                $states[$key] = $item["name"];
            }
        }
    /*}else{
        $states = WC()->countries->get_cities( $state );
    }*/
    return $states;
}

/*
function get_wp_states_city_match($country=0, $city=0){
    $code = "";
    global $wpdb; 
    $query = "SELECT name, iso2 FROM states WHERE id = '$city'";
    $city_data = $wpdb->get_results($query, ARRAY_A);
    
    if($city_data){
        $city = strtolower($city_data[0]["name"]);
        $city = str_replace("district", "", $city);
        $city = str_replace("county", "", $city);
        $city = sanitize_title(trim($city));
        $iso2 = $city_data[0]["iso2"];
        $states = get_states($country);
        if($states){
            $found = false;
            $code = $iso2;
            foreach($states as $key => $state){
                $state = strtolower($state);
                if($city == sanitize_title($state)){
                    $found = true;
                    if($key == $country.$iso2){
                        $code = $country.$iso2;
                    }
                    if($key == $country."-".$iso2){
                        $code = $country."-".$iso2;
                    }
                    if($key == $iso2){
                        $code = $iso2;
                    }
                    break;
                }
            }
        }        
    }
    return $code;
}
*/




function get_languages_list(){
    $localization = new Localization();
    $country_list = $localization->get_country_iso_list();
    return $country_list;
}