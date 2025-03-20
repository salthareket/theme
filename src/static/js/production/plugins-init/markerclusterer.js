function hola(id){
	alert(id)
}

class GoogleMaps{
	constructor() {
		this.config = [];
        this.coords = [];
        this.highestZIndex = 0;
        this.markers = [];
        this.options = {
	        zoom: 13,
	        center: new google.maps.LatLng(-33.92, 151.25),
	        disableDefaultUI: true,
	        scrollwheel: false,
	        draggable: true,
	        styles: typeof map_style !== "undefined" ? map_style : '',
	        zoomControl: true,
	        zoomControlOptions: {
	            position: google.maps.ControlPosition.LEFT_CENTER
	        }
	    }
    }
    init(){
    	var classObj = this;
    	var token_init = "google-maps-init";
	    if($(".googlemaps-custom").not("."+token_init).length>0){
	        $(".googlemaps-custom").not("."+token_init).each(function(){
	        	var obj = $(this);
	           	obj.addClass(token_init);
	           	var id = obj.attr("id");
	           	if(IsBlank(id)){
	           	   id = generateCode(5);
	           	}
	    		obj.attr("id", id);

	           	var type = obj.data("map-type");

	           	var config = obj.data("config");
	           	if(typeof window[config] === "undefined"){
	           		config = obj.data("config");
	           		if(IsBlank(config)){
	           			config = false;
	           		}
	           	}else{
	           		config = window[config];
	           	}

	           	var locations = config.locations;
	           	var buttons = config.buttons;

	           	if(buttons){
	            	if(buttons.hasOwnProperty("zoom_position")){
	            		//classObj.options["zoomControl"] = false;
						classObj.options.zoomControlOptions.position = google.maps.ControlPosition[classObj.position_rename(buttons.zoom_position)];//LEFT_CENTER
	            	}
	            }

	            classObj.config = config;

	            console.log(config)

	            var bounds = new google.maps.LatLngBounds();
	           	
	           	if (locations) {
		            var map = new google.maps.Map(this, classObj.options);
		            var coords = [];
		            for (var i = 0; i < locations.length; i++) {
		                classObj.coords[i] = classObj.render(obj, i, map, bounds, locations);
		            }
		            
		            if(locations.length > 1){
			            google.maps.event.addDomListener(window, 'resize', function() {
			                map.fitBounds(bounds);
			            });
			            if (obj.hasClass("map-google-path")) {
			                classObj.drawPath(map, classObj.coords);
			            }
			            map.fitBounds(bounds);
                        let markers = classObj.markers;
						new markerClusterer.MarkerClusterer({ markers, map });

		            }else if(locations.length == 1){
		            	if(!IsBlank(locations[0].zoom)){
			            	map.setZoom(locations[0].zoom);
			            }
			            map.setCenter(classObj.coords[0]);
		            }
		        }

		        classObj.buttons(map, buttons);

                obj.data("map", map);

		        if(isLoadedJS("vanilla-lazyload")){
		           lazyLoadInstance.update();
	            }

	            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

                return obj;

	        });
	    }
    }
    render(obj, i, map, bounds, locations){
    	//var markers = []; 
    	var config = this.config;

		    var latlng = new google.maps.LatLng(locations[i].lat, locations[i].lng);
		    
		    /*var myIcon = new google.maps.MarkerImage(
		        locations[i].marker ? locations[i].marker.icon : "https://maps.google.com/mapfiles/ms/icons/blue-dot.png", 
		        new google.maps.Size(locations[i].marker ? locations[i].marker.width : 42, locations[i].marker ? locations[i].marker.height : 53),
		        new google.maps.Point(0, 0),
		        new google.maps.Point(locations[i].marker ? locations[i].marker.width / 2 : 26, locations[i].marker ? locations[i].marker.height : 53),
		        new google.maps.Size(locations[i].marker ? locations[i].marker.width : 42, locations[i].marker ? locations[i].marker.height : 53)
		    );*/
		    var myIcon = {
			    url: locations[i].marker ? locations[i].marker.icon : "https://maps.google.com/mapfiles/ms/icons/blue-dot.png", // İkon URL'si
			    size: new google.maps.Size(locations[i].marker ? locations[i].marker.width : 32, locations[i].marker ? locations[i].marker.height : 32), // Orijinal boyut
			    origin: new google.maps.Point(0, 0), // Başlangıç noktası
			    anchor: new google.maps.Point(
			        locations[i].marker ? locations[i].marker.width / 2 : 17, 
			        locations[i].marker ? locations[i].marker.height : 32
			    ), // Anchor noktası (ikonun harita üzerindeki konumunu belirleyen nokta)
			    scaledSize: new google.maps.Size(locations[i].marker ? locations[i].marker.width : 32, locations[i].marker ? locations[i].marker.height : 32) // İkonun haritadaki boyutu
			};


		    var marker_item = new google.maps.Marker({
		        position: latlng,
		        icon: myIcon,
		        map: map
		    });

		    marker_item.post_id = locations[i].id;
		    
		    var infowindow = new google.maps.InfoWindow();

		    if (config.popup.active) {
		        if (config.popup.type == "static") {
		            if (config.popup.template == "default") {
		                infowindow.setContent(locations[i].title);
		                infowindow.setOptions({
		                    maxWidth: config.popup.width
		                });
		                infowindow.open(map, marker_item);
		            } else {
		                // Template-based popup content
		                twig({
		                    href: ajax_request_vars.theme_url + config.popup.template,
		                    async: false,
		                    allowInlineIncludes: false,
		                    load: function (template) {
		                        var html = template.render(locations[i]);
		                        infowindow.setContent(html);
		                        infowindow.setOptions({
		                            maxWidth: config.popup.width
		                        });
		                        infowindow.open(map, marker_item);
		                    }
		                });
		            }
		        } else if (["mouseover", "click"].indexOf(config.popup.type) > -1) {
		        	let popupStat = false;
		            google.maps.event.addListener(marker_item, config.popup.type, function () {
		            	var currentMarker = this;
		            	if(config.popup.type == "click"){
		            		if(config.popup.ajax && config.popup.template != "default"){
								if(currentMarker.query){
									currentMarker.query.abort();
								}
							}
		            		if (popupStat) {
			                    infowindow.close();
			                    popupStat = false;
			                    return;
			                }
		            	}
		                if (config.popup.ajax && config.popup.template != "default") {
		                	if(currentMarker.loaded){
							    infowindow.open(map, currentMarker);
							}else{
			                    infowindow.setContent('Loading...');
			                    infowindow.open(map, currentMarker);
			                    var vars = {
			                        id: currentMarker.post_id,
			                        template: config.popup.template,
			                    };
			                    var query = new ajax_query();
			                    query.method = "get_post";
			                    query.vars = vars;
			                    query.after = function (response, vars, form, objs) {
			                        infowindow.setContent(response.html);
			                        currentMarker.loaded = true;
			                    };
			                    query.request();
			                    this.query = query;
			                }
		                } else {
		                    if (config.popup.template == "default") {
		                        infowindow.setContent(locations[i].title);
		                        infowindow.setOptions({
		                            maxWidth: config.popup.width
		                        });
		                        infowindow.open(map, marker_item);
		                    } else {
		                        twig({
		                            href: ajax_request_vars.theme_url + config.popup.template,
		                            async: false,
		                            allowInlineIncludes: false,
		                            load: function (template) {
		                                var html = template.render(locations[i]);
		                                infowindow.setContent(html);
		                                infowindow.setOptions({
		                                    maxWidth: config.popup.width
		                                });
		                                infowindow.open(map, marker_item);
		                            }
		                        });
		                    }
		                }
		                popupStat = true;
		            });
		            if(config.popup.type == "click"){
			            google.maps.event.addListener(map, "click", function () {
			            	if(config.popup.ajax && config.popup.template != "default"){
								if(marker_item.query){
									marker_item.query.abort();
								}
							}
						    if (popupStat) {
						        infowindow.close();
						        popupStat = false;
						    }
						});
			        }

		            if(config.popup.type == "mouseover"){
						google.maps.event.addListener(marker_item, "mouseout", function () {
							if(config.popup.ajax && config.popup.template != "default"){
								if(this.query){
									this.query.abort();
								}
							}
							if (popupStat) {
			                    infowindow.close();
			                    popupStat = false;
			                }
			                marker_item.setAnimation(null);
			                //marker_item.setIcon(markerIcon);
			                marker_item.setOptions({
			                    zIndex: marker_item.get("myZIndex")
			                });
						});
					}
		        }
		    }

		    if(config.callback){
		    	let func = window[config.callback];
				let onclick_func = new Function("map", "marker", func);
				google.maps.event.addListener(marker_item, 'click', function (e) {
				    onclick_func(map, this);
				});
			}

		    this.markers.push(marker_item);
		    marker_item.set("myZIndex", marker_item.getZIndex());
	       

		/*if (markers.length > 0) {
		    // You can add all the markers to the map at once
		    for (let i = 0; i < markers.length; i++) {
		        markers[i].setMap(map);
		    }
		}*/

		bounds.extend(latlng);

		return latlng;
    }
	position_rename(position){
		switch(position){
			case "topright":
				position = "TOP_RIGHT";
			break;
		    case "topleft":
				position = "TOP_LEFT";
			break;
		    case "bottomright":
				position = "BOTTOM_RIGHT";
			break;
			case "bottomleft":
				position = "BOTTOM_LEFT";
		    break;
	    }
	    return position;
	}
	createButton(map, button) {
		const controlButton = document.createElement("button");
		controlButton.style.backgroundColor = "#fff";
	    controlButton.style.border = "2px solid #fff";
		controlButton.style.borderRadius = "3px";
		controlButton.style.boxShadow = "0 2px 6px rgba(0,0,0,.3)";
		controlButton.style.color = "rgb(25,25,25)";
		controlButton.style.cursor = "pointer";
		controlButton.style.fontFamily = "Roboto,Arial,sans-serif";
		controlButton.style.fontSize = "16px";
		controlButton.style.lineHeight = "38px";
		controlButton.style.margin = "8px 0 22px";
		controlButton.style.padding = "0 5px";
	    controlButton.style.textAlign = "center";
	    controlButton.style.borderRadius = "6px";
		controlButton.textContent = button.title;
		controlButton.title = button.title;
		controlButton.type = "button";

	    controlButton.classList.add(button.class);
		controlButton.classList.add("googlemaps-button");
		if(button.attributes){
			for(var z=0;z<button.attributes.length;z++){
				let name = button.attributes[z].name;
				let value = button.attributes[z].value;
				controlButton.setAttribute(name, value);
			}
		}
		if(button.onclick){
			let onclick_func = new Function("map", "return " + button.onclick);
			controlButton.addEventListener("click", (e) => {
			    e.preventDefault();
			    onclick_func(map); // param1 = "value1" ve param2 = "value2" olacak şekilde çağrılır
			});
		}

		return controlButton;
	}
	buttons(map, buttons){
		if(buttons){
			if(buttons.hasOwnProperty("items")){
			    const controlContainer = document.createElement("div");
			          controlContainer.classList.add("googlemaps-control-button");	  
				for(var i=0;i<buttons.items.length;i++){
					let button = this.createButton(map, buttons.items[i]);
					controlContainer.appendChild(button);
				}
				map.controls[google.maps.ControlPosition[this.position_rename(buttons.position)]].push(controlContainer);            	
			}
		}
	}
    drawPath(map, coords){
    	var lineSymbol = {
			path: 'M 0,-1 0,1',
			strokeOpacity: 1,
			strokeColor: '#6eb2ff',
			scale: 2
		};

		var line = new google.maps.Polyline({
			path: coords,
			/*geodesic: true,
			strokeColor: '#6eb2ff',
			strokeOpacity: 1.0,
			strokeWeight: 3,
			clickable : true,*/
			strokeOpacity: 0,
			icons: [{
				icon: lineSymbol,
				offset: '0',
				repeat: '10px'
			}],
			map: map
		});
		line.setMap(map);
    }
    getHighestZIndex(markers) {
	    if (this.highestZIndex == 0) {
			if (markers.length > 0) {
				for (var i = 0; i < markers.length; i++) {
					tempZIndex = markers[i].getZIndex();
					if (tempZIndex > this.highestZIndex) {
						this.highestZIndex = tempZIndex;
					}
				}
			}
		}
		return this.highestZIndex;
	}
	smoothZoom(map, level, cnt, pos, mode) {
		var maxZoomIn = 17;
		var maxZoomOut = 4;
		var timeOut = 150;
		if (mode == true) {
			if (cnt >= level) {
				var obj = $(map.__gm.X);
					obj.addClass("zoomed");
				return;
			} else {
				if ((maxZoomOut + 2) <= cnt) {
					var z = google.maps.event.addListener(map, 'zoom_changed', function(event) {
								google.maps.event.removeListener(z);
					        	map.setCenter(pos);
								this.smoothZoom(map, level, cnt + 1, pos, true);
	                });
	                setTimeout(function() {
						map.setZoom(cnt);
	                }, timeOut);
	            } else {
	                map.setZoom(cnt);
	                this.smoothZoom(map, level, cnt + 1, pos, true);
	            }
	        }
	    } else {
			if (cnt <= level) {
				var obj = $(map.__gm.X);
				obj.removeClass("zoomed");
				return;
			} else {
				var z = google.maps.event.addListener(map, 'zoom_changed', function(event) {
					google.maps.event.removeListener(z);
	               	map.setCenter(pos);
	               	this.smoothZoom(map, level, cnt - 1, pos, false);
	            });
				if (maxZoomIn - 2 <= cnt) {
					map.setZoom(cnt);
				} else {
					setTimeout(function() {
						map.setZoom(cnt);
					}, timeOut);
				}
			}
		}
	}
	toPoint(latLng, map) {
		var topRight = map.getProjection().fromLatLngToPoint(map.getBounds().getNorthEast());
		var bottomLeft = map.getProjection().fromLatLngToPoint(map.getBounds().getSouthWest());
		var scale = Math.pow(2, map.getZoom());
		var worldPoint = map.getProjection().fromLatLngToPoint(latLng);
		return new google.maps.Point((worldPoint.x - bottomLeft.x) * scale, (worldPoint.y - topRight.y) * scale);
	}
	get_location($obj) {
		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(
				function(position) {
					var pos = {
						lat: position.coords.latitude,
						lon: position.coords.longitude
					};
					if ($obj.hasOwnProperty("callback")) {
						var obj = {
							pos: pos,
							status : true
						};
						if ($obj.hasOwnProperty("map")) {
							obj["map"] = $obj.map;
						}
						if ($obj.hasOwnProperty("end")) {
							obj["end"] = $obj.end;
						}
						$obj.callback(obj);
	                } else {
	                    return pos;
	                }
	            },
	            function() {
                    if ($obj.hasOwnProperty("callback")) {
                        $obj.callback({status: false});
                    }
	                _alert("Lütfen browser ayarlarınızdan konum erişimine izin verin.");
	            }
	        );
	    } else {
            if ($obj.hasOwnProperty("callback")) {
                $obj.callback(false);
            }
	        _alert("Your browser dowsn't support Geolocation");
	    }
	}
	reset(obj) {
		var map = obj.data("map");
		var zoomOut = obj.data("zoomOut");
		if (obj.hasClass("zoomed")) {
			obj.removeClass("zoomed");
		}
		if (zoomOut) {
			var bounds = new google.maps.LatLngBounds();
			this.smoothZoom(map, map.getZoom(), 4, bounds, false);
		} else {
			var map = obj.data("map");
			var data = obj.data("locations");
			var bounds = new google.maps.LatLngBounds();
			if (!IsBlank(data)) {
				var map = new google.maps.Map(obj[0], this.options);
				for (var i = 0; i < data.length; i++) {
					this.render(obj, i, map, bounds, data[i]);
				}
				google.maps.event.addDomListener(window, 'resize', function() {
					map.fitBounds(bounds);
				});
				map.fitBounds(bounds);
			}
	        obj.data("map", map);
	    }
	}
}

function init_google_maps(){
	const maps = new GoogleMaps();
		  maps.init()
}

