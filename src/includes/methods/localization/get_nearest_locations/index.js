{
    init: function($init) {
        var $vars = {
            post_type : "post",
            distance : 5,
            objs : {},
            limit: 5,
            template: "post/archive-ajax",
            output : ["posts"]
        }
        var $args = {
            callback: my_location
        }
        if (!IsBlank($init)) {
            if ($init.hasOwnProperty("post_type")) {
                $vars["post_type"] = $init["post_type"];
                $vars["template"] = $init["post_type"]+"/archive-ajax";
            }
            if ($init.hasOwnProperty("distance")) {
                $vars["distance"] = $init["distance"];
            }
            if ($init.hasOwnProperty("objs")) {
                $vars["objs"] = $init["objs"];
                $args["objs"] = $init["objs"];
                if($vars["objs"].hasOwnProperty("obj")){
                   $vars["objs"]["obj"].addClass("loading-process");
                }
                var output = [];
                if($vars["objs"].hasOwnProperty("obj")){
                   output.push("posts");
                }
                if($vars["objs"].hasOwnProperty("map")){
                    output.push("markers");
                }
                if(output){
                    $vars["output"] = output;
                }
            }
            if ($init.hasOwnProperty("limit")) {
                $vars["limit"] = $init["limit"];
            }
        }

        function my_location($obj) {
            if($obj.status){
                $vars["lat"] = $obj.pos.lat;
                $vars["lng"] = $obj.pos.lon;

                var query = new ajax_query();
                    query.method = "get_nearest_locations";
                    query.vars = $vars;
                    query.request();

                $("#offcanvasMap").offcanvas("show");

            }else{
                if ($obj.hasOwnProperty("objs")) {
                    if($obj["objs"].hasOwnProperty("obj")){
                       $obj["objs"]["obj"].removeClass("loading-process");
                    }
                }
            }
            $("body").removeClass("loading-process");
        }
        root.get_location($args);

    },
    after: function(response, vars, form, objs) {

        debugJS(response, vars, form, objs)

        if(objs.obj){
           objs.obj.html(response.html).removeClass("loading-process");
        }
 
        if(objs.map){

            var markers = response.data;
            var map = $(objs["map"]).data("map");
            var minlat = 200, minlon = 200, maxlat = -200, maxlon = -200;
              
            markers.forEach(function(d, i) {

                if (d.lat != null && d.lat != undefined) {
                  // add a Leaflet marker for the lat lng and insert the application's stated purpose in popup\
                  //var mark = L.marker([d.latitude, d.longitude]);
                  //markersLayer.addLayer(mark);
                  //clusterLayer.addLayer(mark);
                  
                  // find corners
                  if (minlat > d.lat) minlat = d.lat;
                  if (minlon > d.lon) minlon = d.lon;
                  if (maxlat < d.lat) maxlat = d.lat;
                  if (maxlon < d.lon) maxlon = d.lon;
                  
                    // set markers
                    if(d.marker){
                            var myIcon = L.icon({
                                iconUrl: d.marker.icon,
                                iconSize: [d.marker.width, d.marker.height],
                                iconAnchor: [d.marker.width/2, d.marker.height],
                                popupAnchor: [0, 0-d.marker.height]
                            });                     
                    }else{
                                var myIcon = [];
                    }

                    var target = L.latLng(d.lat, d.lon);
                    var exist = false;
                    map.eachLayer(function(layer) {
                        if (layer instanceof L.Marker) {
                            if (layer.getLatLng() === target) {
                              exist = true;
                            }
                        }
                    });
                    if(!exist){
                        //L.marker([d.lat, d.lon], {icon: myIcon}).addTo(map);
                    }

                }
            });
              
            c1 = L.latLng(minlat, minlon);
            c2 = L.latLng(maxlat, maxlon);

            // fit bounds
            map.fitBounds(L.latLngBounds(c1, c2));
              
            // correct zoom to fit markers
            setTimeout(function() {
                //map.setZoom(map.getZoom() - 1);
            }, 500);

            $(".tease-station .collapse").on("shown.bs.collapse", function(e){
                var obj = $(e.target).closest(".tease-station");
                var lat = obj.data("lat");
                var lng = obj.data("lng");
                var latLngs = [ L.latLng(lat, lng) ];
                var markerBounds = L.latLngBounds(latLngs);
                map.fitBounds(markerBounds);
            });
        }
        
    }
}
