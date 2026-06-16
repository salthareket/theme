window.ajax_hooks = {};
window.ajax_hooks['_offcanvas'] = (function($){$.fn._offcanvas=function(options){var settings=$.extend({title:"Offcanvas Title",body:"Offcanvas Content",position:"end",full:!1,onShow:null,onHide:null,closeButton:!0},options);return this.each(function(){var offcanvasId="offcanvas_"+Math.random().toString(36).substr(2,9);var dimensionStyle='';if(settings.full){if(settings.position==='start'||settings.position==='end'){dimensionStyle='width: 100vw;'}else if(settings.position==='top'||settings.position==='bottom'){dimensionStyle='height: 100vh;'}}
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
window.ajax_hooks['acf_layout_posts'] = {before:function(response,vars,form,objs){var obj=null;if(isset(objs.btn)){obj=$(objs.btn)}else if(isset(objs[0])){obj=$(objs[0])}else if(isset(objs.obj)){obj=$(objs.obj)}
if(obj&&obj.length>0){obj.find(".item-total").html("SEARCHING...")}},after:function(response,vars,form,objs){var obj=null;if(isset(objs.btn)){obj=$(objs.btn)}else if(isset(objs[0])){obj=$(objs[0])}else if(isset(objs.obj)){obj=$(objs.obj)}
if(!obj||obj.length===0)return;var btn=obj.find(".btn-next-page");btn.removeClass("loading processing");debugJS(vars,form,objs);var scroll_to=vars.page;if(vars.page_total==vars.page){scroll_to=vars.page}
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
window.ajax_hooks['comment_product'] = {before:function(response,vars,form,objs){debugJS(response)
$("body").addClass("loading");response.vars=vars;$(form).addClass("form-reviewed");return response},after:function(response,vars,form,objs){$("#form-comment-product").find("textarea").addClass("form-control-editable");if(!IsBlank(response.data)){$("#form-comment-product").find("input[name='comment_id']").val(response.data)}
$("#form-comment-product").find(".btn-submit").html("Update Your Comment");form_control_editable();$("body").removeClass("loading");$(form).removeClass("form-reviewed");_alert("",response.message)}};
window.ajax_hooks['comment_product_modal'] = {before:function(response,vars,form,objs){$("body").removeClass("loading");var modal_id="#modal_comment_product_detail";var index=comment_ids.indexOf(vars.id.toString());var prev=index-1;var next=index+1;if(prev<0){prev=""}
if(next==comment_ids.length){next=""}
if(!IsBlank(prev)||prev==0){vars.prev=comment_ids[prev]}
if(!IsBlank(next)){vars.next=comment_ids[next]}
var load_modal=1;if($(modal_id).length>0){load_modal=0;$(modal_id).addClass("loading").modal("show")}else{$("body").addClass("loading")}
vars.load_modal=load_modal;response.vars=vars;return response},after:function(response,vars,form,objs){var modal_id="#modal_comment_product_detail";if(!response.error){if(!IsBlank(response.html)){if(!vars.load_modal){$(modal_id).find(".modal-content").html(response.html)}else{$("body").append(response.html)}
$(modal_id).modal("show")}else{if(!IsBlank(response.data)){$(modal_id).find(".modal-header .modal-title").html(response.data.title);$(modal_id).find(".modal-body").html(response.data.comment)}else{_alert("","error")}}}else{response_view(response)}
$("body").removeClass("loading");$(modal_id).removeClass("loading");star_rating_readonly()}};
window.ajax_hooks['get_post'] = {after:function(response,vars,form,objs){}};
window.ajax_hooks['get_search_history'] = {before:function(response,vars,form,objs){debugJS(objs)
objs.addClass("loading-process")},after:function(response,vars,form,objs){objs.removeClass("loading-process").html(response.html)}};
window.ajax_hooks['pagination_ajax'] = {before:function(response,vars,form,objs){objs.btn.addClass("loading")},after:function(response,vars,form,objs){debugJS(vars,form,objs);var scroll_to=vars.page;if(vars.page_total==vars.page){scroll_to=vars.page}
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
window.ajax_hooks['site_config'] = {init:function(meta=[]){var query=new ajax_query();query.method="site_config";let vars={meta:meta}
query.vars=vars;query.request()},after:function(response,vars,form){let _self=this;site_config=response;if(site_config.hasOwnProperty("nonce")){if(!IsBlank(site_config)){ajax_request_vars.ajax_nonce=site_config.nonce}}
if(site_config.hasOwnProperty("favorites")){if(!IsBlank(favorites)){var favorites=$.parseJSON(site_config.favorites);if(favorites.length>0){debugJS(favorites)
$(".nav-item[data-type='favorites']").addClass("active");$(".btn-favorite").each(function(){var id=parseInt($(this).attr("data-id"));$(this).removeClass("active");debugJS();if(inArray(id,favorites)){$(this).addClass("active")}})}}}
if(site_config.cart>0){var counter=$(".nav-item[data-type='cart'] > a").find(".notification-count");if(counter.length==0){$(".nav-item[data-type='cart'] > a").prepend("<div class='notification-count'>"+site_config.cart+"</div>")}}
$("body").removeClass("not-logged");if(site_config.logged){}
if(site_config.hasOwnProperty("lcp")){const platformKey=window.innerWidth<=768?"m":"d";const platformFull=window.innerWidth<=768?"mobile":"desktop";if(site_config.lcp[platformKey]===0){_self.loadLCPMeasure(platformFull);log("[LCP] Veri eksik, ölçüm scripti yükleniyor...")}else{log("[LCP] Veri zaten mevcut, ölçüme gerek yok.")}}},loadLCPMeasure:function(platform,measureScriptPath){if($("#lcp-main-js").length>0)return;let _self=this;let script=document.createElement('script');script.id='lcp-main-js';script.src=ajax_request_vars.theme_url+'vendor/salthareket/theme/src/static/js/measure-lcp.js';script.onload=function(){log("[LCP] Ana dosya yüklendi, şimdi kütüphaneye geçiliyor...");_self.loadWebVitals(platform)};document.head.appendChild(script);return},loadWebVitals:function(platform){if($("#lcp-measure-js").length>0)return;let script=document.createElement('script');script.id='lcp-measure-js';script.src=ajax_request_vars.theme_url+'static/js/plugins/web-vitals.js';script.onload=function(){webVitals.onLCP((metric)=>{if(typeof lcp_data_save==='function'){log(metric,platform);lcp_data_save(metric,platform)}})};document.head.appendChild(script)}};
window.ajax_hooks['twig_render'] = {after:function(response,vars,form,objs){objs.obj.html(response)}};
window.ajax_hooks['custom_modal'] = {required:["bootbox"],before:function(response,vars,form,objs){modal_create_dialog(vars,response,objs,{className:'modal-page',defaultClose:bool(vars.close,!1),defaultCentered:bool(vars.centered,!1),defaultBackdrop:bool(vars.backdrop,!1)});return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error)return modal_handle_error(response,modal);modal_set_content(response,modal);modal.removeClass('loading');modal_load_plugins_then_init(response.data.plugins||{},modal)}};
window.ajax_hooks['form_modal'] = {required:["bootbox","contact-form-7"],before:function(response,vars,form,objs){modal_create_dialog(vars,response,objs,{className:'modal-form'});objs.modal.find('.bootbox-close-button').addClass('btn-close').empty();if(vars.id&&window['modal_'+vars.id]){window['modal_'+vars.id]()}
return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error)return modal_handle_error(response,modal);modal_set_content(response,modal);if(window.AppCF7){window.AppCF7.initForms(modal)}
if(typeof isLoadedJS==='function'&&isLoadedJS('autosize')&&typeof autosize==='function'){autosize(modal.find('textarea'))}
var $defaults=modal.find("input[name='defaults']");if($defaults.length>0&&!IsBlank($defaults.val())){try{var params=JSON.parse($defaults.val().replace(/'/g,'"'));Object.keys(params).forEach(function(key){var $el=modal.find("[name='"+key+"']");if($el.length){$el.val(params[key]);modal.find('.defaults-'+key).removeClass('d-none')}})}catch(e){log('Defaults parse error:','error')}}
modal.removeClass('loading');var $selects=modal.find('.selectpicker');if($selects.length&&$.fn.selectpicker){$selects.selectpicker()}
if(typeof recaptchaCallback!=='undefined'){recaptchaCallback()}}};
window.ajax_hooks['iframe_modal'] = {required:["bootbox"],before:function(response,vars,form,objs){if(objs.btn&&objs.btn.attr('href')){response.vars.url=objs.btn.attr('href')}
modal_create_dialog(vars,response,objs,{className:'modal-page'});return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error)return modal_handle_error(response,modal);if(vars.title!==undefined)modal.find('.modal-title').html(vars.title);if(response.html)modal.find('.modal-body').html(response.html);modal.removeClass('loading')}};
window.ajax_hooks['map_modal'] = {required:["bootbox","leaflet"],before:function(response,vars,form,objs){modal_create_dialog(vars,response,objs,{className:'modal-map'});return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error)return modal_handle_error(response,modal);modal_set_content(response,modal);modal.removeClass('loading');modal_load_plugins_then_init({leaflet:'init_leaflet'},modal)}};
window.ajax_hooks['page_modal'] = {required:["bootbox"],before:function(response,vars,form,objs){modal_create_dialog(vars,response,objs,{className:'modal-page'});return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error)return modal_handle_error(response,modal);modal_set_content(response,modal);modal.removeClass('loading');if(modal.find('.wpcf7-form').length>0&&window.AppCF7){window.AppCF7.initForms(modal)}
var required=(response.data&&Array.isArray(response.data.required_js))?response.data.required_js:[];var pluginMap={};required.forEach(function(k){pluginMap[k]=''});modal_load_plugins_then_init(pluginMap,modal)}};
window.ajax_hooks['template_modal'] = {required:["bootbox"],before:function(response,vars,form,objs){modal_create_dialog(vars,response,objs,{className:'modal-page'});return response},after:function(response,vars,form,objs){var modal=objs.modal;if(response.error)return modal_handle_error(response,modal);modal_set_content(response,modal);modal.removeClass('loading');modal_load_plugins_then_init((response.data&&response.data.plugins)||{},modal)}};
window.ajax_hooks['get_reviews'] = {after:function(response,vars,form,objs){objs.obj.find(">.card-body").append(response.html)}};
window.ajax_hooks['login'] = {required:["bootbox"],before:function(response,vars,form,objs){debugJS(response)
if(!form){form=objs.form}
if(form.length){form.addClass("loading-process")}},after:function(response,vars,form,objs){debugJS(response)
if(!form){form=objs.form}
if(form.length){form.removeClass("loading-process")}
response_view(response)}};
window.ajax_hooks['add_to_cart'] = window.ajax_hooks.add_to_cart={required:["toastify-js"],before:function(response,vars,form,objs){var btn=objs.btn||objs.obj;if(btn&&btn.length){btn.addClass("loading disabled")}},after:function(response,vars,form,objs){var btn=objs.btn||objs.obj;if(btn&&btn.length){btn.removeClass("loading disabled")}
if(!response||response.error)return;if(response.data&&response.data.count!==undefined){if(typeof cart!=="undefined"){cart.updateBadge("cart",response.data.count)}}
var $cartDropdown=$(".dropdown-notifications[data-type='cart']").find(".dropdown-container");if($cartDropdown.length>0&&typeof cart!=="undefined"){cart.get($cartDropdown,"dropdown")}
var $cartOffcanvas=$("#offcanvasCart .load-container");if($cartOffcanvas.length>0&&typeof cart!=="undefined"){cart.get($cartOffcanvas,"offcanvas")}
if(typeof toast_notification==="function"&&response.data&&response.data.product){var p=response.data.product;var image=p.image?"<img src='"+p.image+"' class='img-fluid rounded' alt='"+(p.name||'')+"' style='width:50px;height:50px;object-fit:cover;'/>":"<i class='fal fa-shopping-basket fa-2x'></i>";toast_notification({url:p.url||"",sender:{image:image},message:response.message})}
$(document.body).trigger("added_to_cart",[response.data])}};
window.ajax_hooks['custom_track_product_view'] = {init:function($vars){var query=new ajax_query();query.method="custom_track_product_view";query.vars=$vars;query.request()}};
window.ajax_hooks['pay_now'] = {before:function(response,vars,form,objs){$("body").addClass("loading-process")},after:function(response,vars,form,objs){response_view(response)}};
window.ajax_hooks['salt_recently_viewed_products'] = {init:function($vars){var query=new ajax_query();query.method="salt_recently_viewed_products";query.vars=$vars;query.request()},after:function(response,vars,form,objs){$("#"+vars.id).html(response.html).removeClass("loading")
if(response.html){init_swiper()}}};
window.ajax_hooks['wc_order_list'] = window.ajax_hooks.wc_order_list={required:["bootbox"],before:function(response,vars,form,objs){var modal_id="#modal-order-detail";var title="#"+vars.order_number;if($(modal_id).length>0){$(modal_id).find(".modal-body").html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');$(modal_id).modal("show")}else{var box=bootbox.dialog({title:title,message:'<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>',size:"large",className:"modal-order-detail",onEscape:!0,backdrop:!0,closeButton:!0,buttons:{}});box.attr("id",modal_id.replace("#",""))}
return response},after:function(response,vars,form,objs){var modal_id="#modal-order-detail";if(!response.error&&!IsBlank(response.html)){$(modal_id).find(".modal-body").html(response.html)}else{response_view(response)}}};
