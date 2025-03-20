const leaflet_images = ajax_request_vars.theme_url+'/static/js/assets/';

// Mevcut URL'leri alıp prefix ekleyerek güncelleriz
L.Icon.Default.mergeOptions({
    iconUrl: leaflet_images + L.Icon.Default.prototype.options.iconUrl.split('/').pop(),
    shadowUrl: leaflet_images + L.Icon.Default.prototype.options.shadowUrl.split('/').pop()
});

L.TileLayer.LazyLoad = L.TileLayer.extend({
    createTile: function (coords, done) {
        const tile = document.createElement('img');

        tile.setAttribute("loading", "lazy"); // Native Lazy Load
        tile.style.width = this.options.tileSize + "px";
        tile.style.height = this.options.tileSize + "px";
        tile.alt = "Leaflet map tile";

        tile.onload = () => done(null, tile);
        tile.onerror = () => {
            console.error("Tile yüklenemedi: ", this.getTileUrl(coords));
            done("Tile yüklenemedi", tile);
        };

        // Gerçek tile URL'sini belirle
        tile.src = this.getTileUrl(coords);
        return tile;
    }
});

function init_leaflet(){
	var token_init = "leaflet-init";
    if($(".leaflet-custom").not("."+token_init).length>0){
        $(".leaflet-custom").not("."+token_init).each(function(){
        	var obj = $(this);
           	obj.addClass(token_init);
           	var id = obj.attr("id");
           	if(IsBlank(id)){
           	   id = generateCode(5);
           	}
    		obj.attr("id", id);

           	var config = obj.data("config");
           	if(typeof window[config] === "undefined"){
           		config = obj.data("config");
           		if(IsBlank(config)){
           			config = false;
           		}
           	}else{
           		config = window[config];
           	}

           	console.log(config)

           	var locations = config.locations;
           	var buttons = config.buttons;

			L.Popup = L.Popup.extend({
			    getEvents: function () {
			        var events = L.DivOverlay.prototype.getEvents.call(this);
			        if ('closeOnClick' in this.options ? this.options.closeOnClick : this._map.options.closePopupOnClick) {
			            //events.preclick = this._close;
			        }
			        if (this.options.keepInView) {
			            events.moveend = this._adjustPan;
			        }
			        return events;
			    },
			});

			var map_config = {
    			scrollWheelZoom: false,
    			dragging: !L.Browser.mobile
    		};
			if(buttons){
            	if(buttons.hasOwnProperty("zoom_position")){
            		map_config["zoomControl"] = false;
            	}
            }
    		var map = L.map(id, map_config).setView([51.505, -0.09], 13);

    		L.tileLayer.lazyLoad = function (url, options) {
			    return new L.TileLayer.LazyLoad(url, options);
			};

			// Lazy yükleme yapan Tile Layer ekle
			L.tileLayer.lazyLoad('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			    tileSize: 256,
			    maxZoom: 19,
			    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
			}).addTo(map);

    		/*L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			    maxZoom: 19,
			    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
			})
			.on('tileloadstart', function(event) {
		        event.tile.setAttribute('loading', 'lazy');
		    })
			.addTo(map);*/



	    	var markers = L.markerClusterGroup({
				spiderfyOnMaxZoom: true,
				showCoverageOnHover: true,
				zoomToBoundsOnClick: true,
				/*iconCreateFunction: function(cluster) {
					return L.divIcon({ html: '<b>' + cluster.getChildCount() + '</b>' });
				}*/
				//spiderLegPolylineOptions: { weight: 1.5, color: '#222', opacity: 0.5 }
			});    			

    		var marker = [];
    		for (let i = 0; i < locations.length; i++) { 

	    			var latlng = L.latLng(locations[i].lat, locations[i].lng);
                    
                    if(config.popup.type != "static"){
		    			if(isset(locations[i].marker)){
							var myIcon = L.icon({
								iconUrl: locations[i].marker.icon,
								iconSize: [locations[i].marker.width, locations[i].marker.height],
								iconAnchor: [locations[i].marker.width/2, locations[i].marker.height],
								popupAnchor: [0, 0-locations[i].marker.height]
							});						
						}else{
							var myIcon = L.icon({
					            iconUrl: L.Icon.Default.prototype.options.iconUrl,
					            iconSize: [25, 41], // Varsayılan ikon boyutu
					            iconAnchor: [12, 41], // İkonun taban noktası
					            popupAnchor: [1, -34], // Popup için ayarlama
					            shadowUrl: L.Icon.Default.prototype.options.shadowUrl, // Gölge URL'si
					            shadowSize: [41, 41] // Gölge boyutu
					        });
						}

		    			var marker_item = L.marker([locations[i].lat, locations[i].lng], {icon: myIcon})//.addTo(map);
		    			    marker_item.post_id = locations[i].id;
	    			}

	    			if(config.popup.active){

	    				if(config.popup.type == "static"){
	    					if(config.popup.template == "default"){

	    						var popup = L.popup(latlng, {
									    content: locations[i].title, 
									   	minWidth : 120,
									   	maxWidth : config.popup.width,
									   	closeButton : false,
									   	closeOnClick : true,
								    	autoClose : true,
								    	className : "leaflet-popup-custom"
								    	//keepInView : true
								}).openOn(map).openPopup();
							    map.addLayer(popup)
							    popup.openPopup();
								marker[i] = popup;

	    					}else{

		    					twig({
			                        href : ajax_request_vars.theme_url+config.popup.template,
									async : false,
									allowInlineIncludes : false,
									load: function(template) {
										var html = template.render(locations[i]);
										var popup = L.popup(latlng, {
									    	content: html, 
									    	minWidth : 120,
									    	maxWidth : config.popup.width,
									    	closeButton : false,
									    	closeOnClick : false,
									    	autoClose : false,
									    	className : "leaflet-popup-custom"
									    	//keepInView : true
									    }).openOn(map).openPopup();
									    map.addLayer(popup)
									    popup.openPopup();
									    marker[i] = popup;
			                        }
			                    });

		    				}
	    				}

	    				if(["mouseover", "click"].indexOf(config.popup.type) > -1){

	    					if(config.popup.ajax && config.popup.template != "default"){

	    						var popup = L.popup(latlng, {
								    content: "", 
								   	minWidth : 120,
								   	maxWidth : config.popup.width,
								   	closeButton : false,
								   	closeOnClick : true,
							    	autoClose : true,
							    	className : "leaflet-popup-custom"
							    	//keepInView : true
								});
	    						marker_item
	    						.on(config.popup.type, function (e) {
								    var currentMarker = this;
								    if (currentMarker.isPopupOpen()) {
                                        popup.closePopup();
                                        return;
                                    }
								    if(currentMarker.loaded){
								    	if(config.popup.type == "mouseover"){
								    		currentMarker.openPopup();
								    	}else{
								    		popup.openPopup();
								    	}
								    }else{
									    currentMarker.bindPopup('Loading...').openPopup();
									    var vars =  {
									        id : currentMarker.post_id,
									        template : config.popup.template,
									    };
								        var query = new ajax_query();
									    	query.method = "get_post";
									    	query.vars = vars;
									    	query.after = function(response, vars, form, objs){
									    		currentMarker.setPopupContent(response.html);
									    		currentMarker.loaded = true;
									    	}
											query.request();
										currentMarker.query = query;							    	
								    }
								})
								if(config.popup.type == "mouseover"){
									marker_item
									.on('mouseout', function (e) {
										if(this.query){
											this.query.abort();
										}
								        this.closePopup();
								    });									
								}

	    					}else{

	    						if(config.popup.template == "default"){

	    							var popup = L.popup(latlng, {
									    content: locations[i].title, 
									   	minWidth : 120,
									   	maxWidth : config.popup.width,
									   	closeButton : false,
									   	closeOnClick : true,
								    	autoClose : true,
								    	className : "leaflet-popup-custom"
								    	//keepInView : true
									});
									marker_item.bindPopup(popup.getContent());//.openPopup();
									marker_item
									.on(config.popup.type, function (e) {
										var currentMarker = this;
									    if (currentMarker.isPopupOpen()) {
	                                        popup.closePopup();
	                                        return;
	                                    }
	                                    if(config.popup.type == "click"){
											popup.openPopup();
									    }else{
									    	currentMarker.openPopup();
									    }
									});
									if(config.popup.type == "mouseover"){
										marker_item
										.on('mouseout', function (e) {
											var currentMarker = this;
											if(config.popup.type == "click"){
												popup.closePopup();
										    }else{
										    	currentMarker.closePopup();
										    }
										});
									}

	    						}else{

									twig({
				                        href : ajax_request_vars.theme_url+config.popup.template,
										async : false,
										allowInlineIncludes : false,
										load: function(template) {
											var html = template.render(locations[i]);
											var popup = L.popup(latlng, {
										    	content: html, 
										    	minWidth : 120,
										    	maxWidth : config.popup.width,
										    	closeButton : false,
										    	closeOnClick : true,
										    	autoClose : true,
										    	className : "leaflet-popup-custom"
										    	//keepInView : true
										    });
										    marker_item.bindPopup(popup.getContent());//.openPopup();
									        marker_item
									        .on(config.popup.type, function (e) {
									            this.openPopup();
									        });
									        if(config.popup.type == "mouseover"){
										        marker_item
										        .on('mouseout', function (e) {
										            this.closePopup();
										        });
										    }
									    }
								    });

	    						}

	    					}
	    				}

	    			}

	    			if(config.callback){
				    	let func = window[config.callback];
						let onclick_func = new Function("map", "marker", func);
						marker_item.on('click', function (e) {
							e.preventDefault;
						    onclick_func(map, this);
						});
					}

					/*if(typeof window[config.callback] === "function"){
						marker_item.on('click', function (e) {
	                        e.preventDefault;
	                        let postId = this.post_id;
					        window[config.callback](postId);
					    });					
					}*/

					if(config.popup.type != "static"){
						marker[i] = marker_item;
					}
		    }
            
            if(marker){
				marker.forEach(function(marker) {
					markers.addLayer(marker);
					map.addLayer(markers);
				});            	
            }

			//var group = new L.featureGroup(marker);
            //map.fitBounds(group.getBounds());

            function fitMapToWindow() {
			    var group = new L.featureGroup(marker);
                map.fitBounds(group.getBounds());
			}
			if(locations.length > 1){
				fitMapToWindow();
				window.addEventListener('resize', fitMapToWindow);
			}else if(locations.length == 1){
				let zoom = map.getZoom()
				if(!IsBlank(locations[0].zoom)){
					zoom = locations[0].zoom;
				}
				map.setView([locations[0].lat, locations[0].lng], zoom); 
			}
            
            if(buttons){

            	if(buttons.hasOwnProperty("zoom_position")){
					L.control.zoom({
					    position: buttons.zoom_position
					}).addTo(map);            		
            	}

				L.Control.Button = L.Control.extend({
					  options: {
					    position: config.buttons.position
					  },
					  initialize: function (options) {
					    this._button = {};
					    this.setButton(options);
					  },

					  onAdd: function (map) {
					    this._map = map;

					    this._container = L.DomUtil.create('div', 'leaflet-control-button leaflet-bar');

					    this._update();
					    return this._container;
					  },

					  onRemove: function (map) {
					    this._button = {};
					    this._update();
					  },

					  setButton: function (options) {
					    var button = {
					      'class': options.class || "",
					      'text': options.text || "",
					      'onClick': options.onClick || function() {},
					      'title': options.title || "",
					      'data' :options.data || {}
					    };

					    this._button = button;
					    this._update();
					  },

					  _update: function () {
					    if (!this._map) {
					      return;
					    }

					    this._container.innerHTML = '';
					    this._makeButton(this._button);
					  },

					  _makeButton: function (button) {
					    var newButton = L.DomUtil.create('a', 'leaflet-buttons-control-button '+button.class, this._container);
					    newButton.href = '#';
					    newButton.innerHTML = button.text;
					    newButton.title = button.title;
					    if(button.data){
					    	for (var key in button.data) {
							    if (button.data.hasOwnProperty(key)) {
							        newButton.setAttribute(key, button.data[key]);
							    }
							}	
					    }

					    onClick = function(event) {
					      button.onClick(event, newButton);
					    };
					
					    L.DomEvent.addListener(newButton, 'click', onClick, this);
					    return newButton;// from https://gist.github.com/emtiu/6098482
					}
	            });
	            if(buttons.hasOwnProperty("items")){
		            for(var i=0;i<buttons.items.length;i++){
			            let button_config = {
					        title: buttons.items[i].title,
							class : buttons.items[i].class,
					    };
					    if(buttons.items[i].attributes){
					    	let data = {};
					    	for(var z=0;z<buttons.items[i].attributes.length;z++){
					    		let name = buttons.items[i].attributes[z].name;
					    		let value = buttons.items[i].attributes[z].value;
					    		data[name] = value;
					    	}
					    	button_config["data"] = data;
					    }

					    if(buttons.items[i].onclick){
							let onclick_func = new Function("map", buttons.items[i].onclick);
							button_config["onClick"] = function(e){
							    e.preventDefault();
							    onclick_func(map);
							};
						}

                        /*
					    let onclick_func = new Function();
					    if(buttons.items[i].onclick){
					    	let onclick = buttons.items[i].onclick;
							onclick_func = new Function(onclick);
					    }
					    button_config["onClick"] = function(e){
							e.preventDefault();
	                        onclick_func();
						}*/
					    new L.Control.Button(button_config).addTo(map); 	            	
		            }	            	
	            }
            }    
      
            obj.data("map", map);

            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

            return obj;

	    });
    }
}


