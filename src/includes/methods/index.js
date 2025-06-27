var ajax_hooks = {};
ajax_hooks['_offcanvas'] = (function($){$.fn._offcanvas=function(options){var settings=$.extend({title:"Offcanvas Title",body:"Offcanvas Content",position:"end",full:!1,onShow:null,onHide:null,closeButton:!0},options);return this.each(function(){var offcanvasId="offcanvas_"+Math.random().toString(36).substr(2,9);var dimensionStyle='';if(settings.full){if(settings.position==='start'||settings.position==='end'){dimensionStyle='width: 100vw;'}else if(settings.position==='top'||settings.position==='bottom'){dimensionStyle='height: 100vh;'}}
var offcanvasTemplate=`
<div class="offcanvas offcanvas-${settings.position}" tabindex="-1" id="${offcanvasId}" style="${dimensionStyle}" aria-labelledby="${offcanvasId}_label">
<div class="offcanvas-header">
<h5 class="offcanvas-title" id="${offcanvasId}_label">${settings.title}</h5>
${settings.closeButton ? '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>' : ''}
</div>
<div class="offcanvas-body">
${settings.body}
</div>
</div>
`;$('body').append(offcanvasTemplate);var offcanvasElement=new bootstrap.Offcanvas(document.getElementById(offcanvasId));$(this).on('click',function(){offcanvasElement.show();if(typeof settings.onShow==='function'){settings.onShow()}});$(`#${offcanvasId}`).on('hidden.bs.offcanvas',function(){if(typeof settings.onHide==='function'){settings.onHide()}
$(`#${offcanvasId}`).remove()})})}}(jQuery));
ajax_hooks['acf_layout_posts'] = {before:function(response,vars,form,objs){if(isset(objs[0])){var obj=$(objs[0])}else if(isset(objs.obj)){var obj=$(objs.obj)}
console.log("acf layout posts çalıştı");obj.find(".item-total").html("SEARCHING...")},after:function(response,vars,form,objs){if(isset(objs[0])){var obj=$(objs[0])}else if(isset(objs.obj)){var obj=$(objs.obj)}
var btn=obj.find(".btn-next-page");btn.removeClass("loading processing");debugJS(vars,form,objs);var scroll_to=vars.page;if(vars.page_total==vars.page){scroll_to=vars.page}
var paged_url=bool(vars.paged_url);if(vars.page>1&&paged_url){var url="";if(vars.url.indexOf("?")>-1){var url_parts=vars.url.split("?");if(!IsBlank(url_parts[1])){var url_json=url2json(vars.url);if(url_json){if(url_json.hasOwnProperty("paged")){}
url_parts[1]=json2url(url_json)}
var url=url_parts[0]+"page/"+vars.page+"/?"+url_parts[1]}else{var url=url_parts[0]+"page/"+vars.page+"/"}}else{var url=vars.url+"page/"+vars.page+"/"}
if(!IsBlank(url)){history.pushState(null,document.title,url)}}
var total=parseInt(response.data.count_total);var page=parseInt(response.data.page);var page_total=parseInt(response.data.page_total);var posts_per_page=parseInt(vars.posts_per_page);debugJS(response.data);obj.attr("data-page",page+1);obj.attr("data-page-total",page_total);obj.attr("data-count",response.data.count);var total=pluralize("<b>{}</b> member found","<b>{}</b> members found",response.data.count,"Nothing found");obj.find(".item-total").html(total);var container=obj.find(".list-cards");if(vars.posts_per_page){container.append(response.html)}else{container.html(response.html)}
if(container.hasAttr("data-masonry").length!=0){container.masonry('appended',response.html).masonry('reloadItems').masonry('layout')}
if(container.hasClass("swiper-wrapper")){var swiper=container.closest(".swiper")
if(typeof swiper[0].swiper!=="undefined"){swiper[0].swiper.update()}else{swiper.removeClass("swiper-slider-init");init_swiper_obj(swiper);swiper[0].swiper.update()}}
if($(".offcanvas-filters").length>0){$(".offcanvas-filters").offcanvas("hide")}
if(response.data.page>=response.data.page_count_total&&vars.load_type!="default"){obj.find(".card-footer").remove()}
response_view(response)}};
ajax_hooks['comment_product'] = {before:function(response,vars,form,objs){debugJS(response)
$("body").addClass("loading");response.vars=vars;$(form).addClass("form-reviewed");return response},after:function(response,vars,form,objs){$("#form-comment-product").find("textarea").addClass("form-control-editable");if(!IsBlank(response.data)){$("#form-comment-product").find("input[name='comment_id']").val(response.data)}
$("#form-comment-product").find(".btn-submit").html("Update Your Comment");form_control_editable();$("body").removeClass("loading");$(form).removeClass("form-reviewed");_alert("",response.message)}};
ajax_hooks['comment_product_modal'] = {before:function(response,vars,form,objs){$("body").removeClass("loading");var modal_id="#modal_comment_product_detail";var index=comment_ids.indexOf(vars.id.toString());var prev=index-1;var next=index+1;if(prev<0){prev=""}
if(next==comment_ids.length){next=""}
if(!IsBlank(prev)||prev==0){vars.prev=comment_ids[prev]}
if(!IsBlank(next)){vars.next=comment_ids[next]}
var load_modal=1;if($(modal_id).length>0){load_modal=0;$(modal_id).addClass("loading").modal("show")}else{$("body").addClass("loading")}
vars.load_modal=load_modal;response.vars=vars;return response},after:function(response,vars,form,objs){var modal_id="#modal_comment_product_detail";if(!response.error){if(!IsBlank(response.html)){if(!vars.load_modal){$(modal_id).find(".modal-content").html(response.html)}else{$("body").append(response.html)}
$(modal_id).modal("show")}else{if(!IsBlank(response.data)){$(modal_id).find(".modal-header .modal-title").html(response.data.title);$(modal_id).find(".modal-body").html(response.data.comment)}else{_alert("","error")}}}else{response_view(response)}
$("body").removeClass("loading");$(modal_id).removeClass("loading");star_rating_readonly()}};
ajax_hooks['get_post'] = {after:function(response,vars,form,objs){}};
ajax_hooks['pagination_ajax'] = {before:function(response,vars,form,objs){objs.btn.addClass("loading")},after:function(response,vars,form,objs){debugJS(vars,form,objs);var scroll_to=vars.page;if(vars.page_total==vars.page){scroll_to=vars.page}
var url="";var paged_url=bool(vars.paged_url);if(vars.page>1&&paged_url){var url="";if(vars.url.indexOf("?")>-1){var url_parts=vars.url.split("?");if(!IsBlank(url_parts[1])){var url_json=url2json(vars.url);if(url_json){if(url_json.hasOwnProperty("paged")){}
url_parts[1]=json2url(url_json)}
var url=url_parts[0]+"page/"+vars.page+"/?"+url_parts[1]}else{var url=url_parts[0]+"page/"+vars.page+"/"}}else{var url=vars.url+"page/"+vars.page+"/"}
if(!IsBlank(url)){history.pushState(null,document.title,url)}}
objs.btn.removeClass("loading");if(vars.direction=="prev"){$(vars.container).prepend(response.html);if($(vars.container).hasAttr("data-masonry").length!=0){$(vars.container).masonry('prepended',response.html).masonry('reloadItems').masonry('layout')}
if(1==vars.page||IsBlank(response.html)){objs.btn.remove();var pagination=$(vars.container+"-pagination.prev");if(pagination.length>0){pagination.remove()}}else{objs.btn.attr("data-page",vars.page-1);objs.btn.data("page",vars.page-1)}}
if(vars.direction=="next"){$(vars.container).append(response.html);if($(vars.container).hasAttr("data-masonry").length!=0){$(vars.container).masonry('appended',response.html).masonry('reloadItems').masonry('layout')}
if(vars.page_total==vars.page||IsBlank(response.html)){objs.btn.remove();var pagination=$(vars.container+"-pagination.next");if(pagination.length>0){pagination.remove()}}else{objs.btn.attr("data-page",vars.page+1);objs.btn.data("page",vars.page+1)}}
if(!IsBlank(response.data)){$(".result-count").html(response.data)}
root.ui.scroll_to($(vars.container).find("[data-page='"+(scroll_to)+"']"))}};
ajax_hooks['site_config'] = {init:function(meta=[]){var query=new ajax_query();query.method="site_config";let vars={meta:meta}
query.vars=vars;query.request()},after:function(response,vars,form){site_config=response;if(site_config.hasOwnProperty("nonce")){if(!IsBlank(site_config)){ajax_request_vars.ajax_nonce=site_config.nonce}}
if(site_config.hasOwnProperty("favorites")){if(!IsBlank(favorites)){var favorites=$.parseJSON(site_config.favorites);if(favorites.length>0){debugJS(favorites)
$(".nav-item[data-type='favorites']").addClass("active");$(".btn-favorite").each(function(){var id=parseInt($(this).attr("data-id"));$(this).removeClass("active");debugJS();if(inArray(id,favorites)){$(this).addClass("active")}})}}}
if(site_config.cart>0){var counter=$(".nav-item[data-type='cart'] > a").find(".notification-count");if(counter.length==0){$(".nav-item[data-type='cart'] > a").prepend("<div class='notification-count'>"+site_config.cart+"</div>")}}
$("body").removeClass("not-logged");if(site_config.logged){get_notifications()}}};
ajax_hooks['twig_render'] = {after:function(response,vars,form,objs){objs.obj.html(response)}};
ajax_hooks['get_available_districts'] = {after:function(response,vars,form,objs){debugJS(response)}};
ajax_hooks['get_city_options'] = {before:function(response,vars,form,objs){if(objs.hasClass("selectpicker")){objs.parent(".bootstrap-select").addClass("loading-xs loading-process")}else{objs.wrap("<div class='loading-xs loading-process position-relative'></div>")}},after:function(response,vars,form,objs){var show_count=bool(objs.data("count"),!1);var count_type=objs.data("count-type");if(objs.hasClass("selectpicker")){objs.parent(".bootstrap-select").removeClass("loading-xs loading-process")}else{objs.unwrap()}
if(response){objs.find("option[value='']").addClass("d-none");objs.find("option").not("[value='']").remove();var selected=!1;for(var i=0;i<response.length;i++){var count=response[i][count_type+"_count"];debugJS(response[i].name+" - "+show_count+" - "+count);if(show_count&&count==0){continue}
var counter=show_count?"("+count+")":"";objs.append("<option value='"+response[i].id+"' "+(vars.selected==response[i].id?"selected":"")+">"+response[i].name+counter+"</option>")}
if(objs.hasClass("selectpicker")){if(!selected){objs.find("option").not(".d-none").first().prop("selected",!0)}else{objs.val(selected)}
objs.selectpicker("refresh");objs.selectpicker("show")}
objs.trigger("change")}}};
ajax_hooks['get_country_options'] = {before:function(response,vars,form,objs){if(objs.hasClass("selectpicker")){objs.parent(".bootstrap-select").addClass("loading-xs loading-process")}else{objs.find("option").not(".d-none").remove();var text=objs.find("option").first().text();objs.find("option").first().attr("data-title",text);var text=objs.find("option").first().text("Loading...")}},after:function(response,vars,form,objs){if(objs.hasClass("selectpicker")){objs.parent(".bootstrap-select").removeClass("loading-xs loading-process")}else{$("body").removeClass("loading-process");var text=objs.find("option").first().data("title");objs.find("option").first().text(text)}
if(response){if(!objs.data("chain-all")){objs.find("option[value=''].all").addClass(" ajax d-none")}
objs.find("option").not("[value='']").remove();var selected=!1;for(var i=0;i<response.length;i++){objs.append("<option class='"+(IsBlank(response[i].slug)?"all":"")+"'' value='"+response[i].slug+"' "+(response[i].selected?"selected":"")+">"+response[i].name+"</option>");if(!selected){selected=response[i].selected}}
if(objs.hasClass("selectpicker")){if(!selected){objs.find("option").not(".d-none").first().prop("selected",!0)}else{objs.val(selected)}
objs.selectpicker("refresh");objs.selectpicker("show")}
objs.trigger("change")}}};
ajax_hooks['get_districts'] = {after:function(response,vars,form,objs){debugJS(response)}};
ajax_hooks['get_nearest_locations'] = {init:function($init){var $vars={post_type:"post",distance:5,objs:{},limit:5,template:"post/archive-ajax",output:["posts"]}
var $args={callback:my_location}
if(!IsBlank($init)){if($init.hasOwnProperty("post_type")){$vars.post_type=$init.post_type;$vars.template=$init.post_type+"/archive-ajax"}
if($init.hasOwnProperty("distance")){$vars.distance=$init.distance}
if($init.hasOwnProperty("objs")){$vars.objs=$init.objs;$args.objs=$init.objs;if($vars.objs.hasOwnProperty("obj")){$vars.objs.obj.addClass("loading-process")}
var output=[];if($vars.objs.hasOwnProperty("obj")){output.push("posts")}
if($vars.objs.hasOwnProperty("map")){output.push("markers")}
if(output){$vars.output=output}}
if($init.hasOwnProperty("limit")){$vars.limit=$init.limit}}
function my_location($obj){if($obj.status){$vars.lat=$obj.pos.lat;$vars.lng=$obj.pos.lon;var query=new ajax_query();query.method="get_nearest_locations";query.vars=$vars;query.request();$("#offcanvasMap").offcanvas("show")}else{if($obj.hasOwnProperty("objs")){if($obj.objs.hasOwnProperty("obj")){$obj.objs.obj.removeClass("loading-process")}}}
$("body").removeClass("loading-process")}
root.get_location($args)},after:function(response,vars,form,objs){debugJS(response,vars,form,objs)
if(objs.obj){objs.obj.html(response.html).removeClass("loading-process")}
if(objs.map){var markers=response.data;var map=$(objs.map).data("map");var minlat=200,minlon=200,maxlat=-200,maxlon=-200;markers.forEach(function(d,i){if(d.lat!=null&&d.lat!=undefined){if(minlat>d.lat)minlat=d.lat;if(minlon>d.lon)minlon=d.lon;if(maxlat<d.lat)maxlat=d.lat;if(maxlon<d.lon)maxlon=d.lon;if(d.marker){var myIcon=L.icon({iconUrl:d.marker.icon,iconSize:[d.marker.width,d.marker.height],iconAnchor:[d.marker.width/2,d.marker.height],popupAnchor:[0,0-d.marker.height]})}else{var myIcon=[]}
var target=L.latLng(d.lat,d.lon);var exist=!1;map.eachLayer(function(layer){if(layer instanceof L.Marker){if(layer.getLatLng()===target){exist=!0}}});if(!exist){}}});c1=L.latLng(minlat,minlon);c2=L.latLng(maxlat,maxlon);map.fitBounds(L.latLngBounds(c1,c2));setTimeout(function(){},500);$(".tease-station .collapse").on("shown.bs.collapse",function(e){var obj=$(e.target).closest(".tease-station");var lat=obj.data("lat");var lng=obj.data("lng");var latLngs=[L.latLng(lat,lng)];var markerBounds=L.latLngBounds(latLngs);map.fitBounds(markerBounds)})}}};
ajax_hooks['get_posts_by_city'] = {after:function(response,vars,form,objs){debugJS(response)}};
ajax_hooks['get_posts_by_district'] = {after:function(response,vars,form,objs){debugJS(response)}};
ajax_hooks['get_states'] = {before:function(response,vars,form,objs){var state=vars.state;var select=$(".selectpicker[name='"+state+"']");var text=$(".form-control[name='"+state+"']");text.addClass("d-none").val("").removeAttr("required").attr("data-required",!0).prop("disabled",!0);select.addClass("d-none").removeAttr("required").attr("data-required",!0).prop("disabled",!0);select.closest(".bootstrap-select").addClass("d-none");text.closest(".form-group").addClass("loading-hide")},after:function(response,vars,form,objs){var use_select=!1;var state=vars.state;var select=$(".selectpicker[name='"+state+"']");var text=$(".form-control[name='"+state+"']");text.val("").closest(".form-group").removeClass("loading-hide");if(response!="false"&&response!="[]"){if(Object.keys(response).length>0){use_select=!0}}
if(use_select){var val=select.data("val");var options="";for(var i in response){options+="<option value='"+i+"'>"+response[i]+"</option>"}
select.removeClass("d-none").removeAttr("data-required").attr("required",!0).prop("disabled",!1);select.closest(".bootstrap-select").removeClass("d-none");select.html(options).selectpicker("refresh");if(typeof response[val]!=="undefined"){select.val(val);select.selectpicker("render")}else{select.find("option").first().prop("selected",!0);select.selectpicker("render")}
text.addClass("d-none").removeAttr("required").attr("data-required",!0).prop("disabled",!0)}else{select.html("").selectpicker("refresh");select.addClass("d-none").removeAttr("required").attr("data-required",!0).prop("disabled",!0);select.closest(".bootstrap-select").addClass("d-none");text.removeClass("d-none").removeAttr("data-required").attr("required",!0).prop("disabled",!1)}
text.closest(".form-group").removeClass("loading");if(!text.parent().is(':visible')&&!select.parent().is(':visible')){text.attr("data-required",!0).prop("disabled",!0);select.attr("data-required",!0).prop("disabled",!0)}}};
ajax_hooks['form_modal'] = {required:"bootbox",before:function(response,vars,form,objs){var className="modal-form loading "+(vars.class?vars.class:'');var scrollable=bool(vars.scrollable,!1);var close=bool(vars.close,!0);var dialog=bootbox.dialog({className:className,title:"<div></div>",message:'<div></div>',closeButton:close,size:!IsBlank(vars.size)?vars.size:'xl',scrollable:scrollable,backdrop:!0,buttons:{},onHidden:function(e){response.abort()}});if(vars.fullscreen){dialog.find(".modal-dialog").addClass("modal-fullscreen")}
if(vars.modal){vars.modal.forEach(item=>{for(const[key,value]of Object.entries(item)){dialog.find("."+key).addClass(value)}})}
dialog.data("response",response);dialog.find(".bootbox-close-button").addClass("btn-close").empty();objs.modal=dialog;response.objs={"modal":dialog,"btn":objs.btn}
if(window["modal_"+vars.id]){window["modal_"+vars.id]()}
return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error){modal.addClass("remove-on-hidden").modal("hide");if(response.message){response_view(response)}
return!1}
modal.find(".modal-title").html(response.data.title);modal.find(".modal-body").html(response.data.content);initContactForm();root.form.init();if(isLoadedJS("autosize")){autosize($('textarea'))}
if(modal.find("input[name='defaults']").length>0){var params=modal.find("input[name='defaults']").val();if(!IsBlank(params)){params=params.replaceAll("'",'"');params=$.parseJSON(params);if(Object.keys(params).length>0){for(param in params){var el=$("[name='"+param+"']");if(el.length>0){el.val(params[param]);el.closest(".defaults-"+param).removeClass("d-none")}}}
debugJS(params)}}
modal.removeClass("loading");if(modal.find(".selectpicker").length>0){modal.find(".selectpicker").selectpicker()}
if(typeof recaptchaCallback!=="undefined"){recaptchaCallback()}}};
ajax_hooks['iframe_modal'] = {required:"bootbox",before:function(response,vars,form,objs){response.vars.url=objs.btn.attr("href");var className="modal-page loading "+(vars.class?vars.class:'');var scrollable=bool(vars.scrollable,!1);var close=bool(vars.close,!0);var dialog=bootbox.dialog({className:className,title:"<div></div>",message:'<div></div>',closeButton:close,size:!IsBlank(vars.size)?vars.size:'xl',scrollable:scrollable,centerVertical:!0,backdrop:!0,buttons:{},onHidden:function(e){response.abort()}});if(vars.fullscreen){dialog.find(".modal-dialog").addClass("modal-fullscreen")}
if(vars.modal){vars.modal.forEach(item=>{for(const[key,value]of Object.entries(item)){dialog.find("."+key).addClass(value)}})}
objs.modal=dialog;var id=generateCode(5);dialog.attr("id",id);response.objs={"modal":dialog,"btn":objs.btn}
return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error){modal.addClass("remove-on-hidden").modal("hide");if(response.message){response_view(response)}
return!1}
if(vars.hasOwnProperty("title")){modal.find(".modal-title").html(vars.title)}
modal.find(".modal-body").html(response.html);modal.removeClass("loading")}};
ajax_hooks['map_modal'] = {required:"bootbox",before:function(response,vars,form,objs){var className="modal-map loading "+(vars.class?vars.class:'');var scrollable=bool(vars.scrollable,!1);var close=bool(vars.close,!0);var dialog=bootbox.dialog({className:className,title:'<div></div>',message:'<div></div>',closeButton:close,size:!IsBlank(vars.size)?vars.size:'xl',scrollable:scrollable,backdrop:!0,buttons:{},onHidden:function(e){response.abort()}});if(vars.fullscreen){dialog.find(".modal-dialog").addClass("modal-fullscreen")}
if(vars.modal){vars.modal.forEach(item=>{for(const[key,value]of Object.entries(item)){dialog.find("."+key).addClass(value)}})}
objs.modal=dialog;var id=generateCode(5);dialog.attr("id",id);response.objs={"modal":dialog,"btn":objs.btn}
return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error){modal.addClass("remove-on-hidden").modal("hide");if(response.message){response_view(response)}
return!1}
if(response.data.hasOwnProperty("title")){modal.find(".modal-title").html(response.data.title)}
modal.find(".modal-body").html(response.data.content);modal.removeClass("loading")}};
ajax_hooks['page_modal'] = {required:"bootbox",before:function(response,vars,form,objs){var className="modal-page loading "+(vars.class?vars.class:'');var scrollable=bool(vars.scrollable,!1);var close=bool(vars.close,!0);var dialog=bootbox.dialog({className:className,title:"<div></div>",message:'<div></div>',closeButton:close,size:!IsBlank(vars.size)?vars.size:'xl',scrollable:scrollable,centerVertical:!0,buttons:{},onHidden:function(e){response.abort()}});if(vars.fullscreen){dialog.find(".modal-dialog").addClass("modal-fullscreen")}
if(vars.modal){vars.modal.forEach(item=>{for(const[key,value]of Object.entries(item)){dialog.find("."+key).addClass(value)}})}
objs.modal=dialog;var id=generateCode(5);dialog.attr("id",id);response.objs={"modal":dialog,"btn":objs.btn}
return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error){modal.addClass("remove-on-hidden").modal("hide");if(response.message){response_view(response)}
return!1}
if(response.data.hasOwnProperty("title")){modal.find(".modal-title").html(response.data.title)}
modal.find(".modal-body").html(response.data.content);modal.removeClass("loading")}};
ajax_hooks['template_modal'] = {required:"bootbox",before:function(response,vars,form,objs){var className="modal-page loading "+(vars.class?vars.class:'');var scrollable=bool(vars.scrollable,!1);var close=bool(vars.close,!0);var dialog=bootbox.dialog({className:className,title:"<div></div>",message:'<div></div>',closeButton:close,size:!IsBlank(vars.size)?vars.size:'xl',scrollable:scrollable,centerVertical:!0,buttons:{},onHidden:function(e){response.abort()}});if(vars.fullscreen){dialog.find(".modal-dialog").addClass("modal-fullscreen")}
if(vars.modal){vars.modal.forEach(item=>{for(const[key,value]of Object.entries(item)){dialog.find("."+key).addClass(value)}})}
objs.modal=dialog;var id=generateCode(5);dialog.attr("id",id);response.objs={"modal":dialog,"btn":objs.btn}
return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error){modal.addClass("remove-on-hidden").modal("hide");if(response.message){response_view(response)}
return!1}
if(response.data.hasOwnProperty("content")){modal.find(".modal-content").html(response.data.content)}else{if(response.data.hasOwnProperty("title")){modal.find(".modal-title").html(response.data.title)}
if(response.data.hasOwnProperty("body")){modal.find(".modal-body").html(response.data.body)}}
modal.removeClass("loading");init_functions()}};
