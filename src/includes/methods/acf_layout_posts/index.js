{
    before: function(response, vars, form, objs) {
        if(isset(objs[0])){
            var obj = $(objs[0]);
        }else if(isset(objs.obj)){
            var obj = $(objs.obj);
        }
        obj.find(".item-total").html("SEARCHING...");
    },
    after: function(response, vars, form, objs) {
        if(isset(objs[0])){
            var obj = $(objs[0]);
        }else if(isset(objs.obj)){
            var obj = $(objs.obj);
        }

        var btn = obj.find(".btn-next-page");
        btn.removeClass("loading processing");

        debugJS(vars, form, objs);
        var scroll_to = vars.page;
        if(vars.page_total == vars.page){
            scroll_to = vars.page;
        }
        var paged_url = bool(vars.paged_url);

        if(vars.page>1 && paged_url){
            var url = "";
            if(vars.url.indexOf("?") > -1){
               var url_parts = vars.url.split("?");
               if(!IsBlank(url_parts[1])){
                   var url_json = url2json(vars.url);
                   if(url_json){
                      if(url_json.hasOwnProperty("paged")){
                         //url_json["paged"] = vars.page;
                      }
                      url_parts[1] = json2url(url_json);
                   }
                   var url = url_parts[0] + "page/"+vars.page+"/?" + url_parts[1];
                   //var url = url_parts[0] + "?" + url_parts[1];
               }else{
                   var url = url_parts[0] + "page/"+vars.page+"/";
               }
            }else{
                var url = vars.url + "page/"+vars.page+"/";
            }
            if(!IsBlank(url)){
                history.pushState(null, document.title, url);
            }            
        }


        var total = parseInt(response.data.count_total);
        var page = parseInt(response.data.page);
        var page_total = parseInt(response.data.page_total);
        var posts_per_page = parseInt(vars.posts_per_page);
                              
        debugJS(response.data);
        obj.attr("data-page", page + 1);
        obj.attr("data-page-total", page_total);
        obj.attr("data-count", response.data.count);

        //alert(translate("Hücrelerimizde"));

            var total = pluralize("<b>{}</b> member found", "<b>{}</b> members found", response.data.count, "Nothing found");
            obj.find(".item-total").html(total);

            //var button_text = obj.find(".card-footer .text");
            //    button_text.html(pluralize("<b>{}</b> member found", "<b>{}</b> members found", (response.data.total - (vars.posts_per_page - vars.page)), "Nothing found"));

            var container = obj.find(".list-cards");

            if (vars.posts_per_page) {
                container.append(response.html);
            } else {
                container.html(response.html);
            }
            if(container.hasAttr("data-masonry").length != 0){
               container.masonry( 'appended', response.html ).masonry('reloadItems').masonry('layout');
            }
            if(container.hasClass("swiper-wrapper")){
                var swiper = container.closest(".swiper")
                if(typeof swiper[0].swiper !== "undefined"){
                    swiper[0].swiper.update();
                }else{
                    swiper.removeClass("swiper-slider-init");
                    init_swiper_obj(swiper);
                    swiper[0].swiper.update();
                }
            }
            if($(".offcanvas-filters").length>0){
                $(".offcanvas-filters").offcanvas("hide");
            }

            if(response.data.page >= response.data.page_count_total && vars.load_type != "default"){
                obj.find(".card-footer").remove();
            }
            response_view(response);
    }
}