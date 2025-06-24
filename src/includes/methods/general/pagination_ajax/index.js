{
    before: function(response, vars, form, objs) {
        //$(vars.container).addClass("loading-process");
        objs.btn.addClass("loading");
    },
    after: function(response, vars, form, objs) {
        debugJS(vars, form, objs);
        var scroll_to = vars.page;
        if(vars.page_total == vars.page){
            scroll_to = vars.page;
        }
        var url = "";
        var paged_url = bool(vars.paged_url);

        /*if(vars.url.indexOf("?") > -1 && paged_url){
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
        }*/

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


        /*$(vars.container)
        .removeClass("loading-process");*/
        objs.btn.removeClass("loading");

        //if(isLoadedJS("vanilla-lazyload")){
            //lazyLoadInstance.update();
        //}
        if(vars.direction == "prev"){

            $(vars.container).prepend(response["html"]);
            if($(vars.container).hasAttr("data-masonry").length != 0){
               $(vars.container).masonry( 'prepended', response["html"]  ).masonry('reloadItems').masonry('layout');
            }

            if(1 == vars.page || IsBlank(response["html"])){
                objs.btn.remove();
                var pagination = $(vars.container + "-pagination.prev");
                if(pagination.length > 0){
                    pagination.remove();
                }
            }else{
                objs.btn.attr("data-page", vars.page - 1);
                objs.btn.data("page", vars.page - 1);
            }        
        }
        if(vars.direction == "next"){

            $(vars.container).append(response["html"]);
            if($(vars.container).hasAttr("data-masonry").length != 0){
               $(vars.container).masonry( 'appended', response["html"]  ).masonry('reloadItems').masonry('layout');
            }

            if(vars.page_total == vars.page || IsBlank(response["html"])){
                objs.btn.remove();
                var pagination = $(vars.container + "-pagination.next");
                if(pagination.length > 0){
                    pagination.remove();
                }
            }else{
                objs.btn.attr("data-page", vars.page + 1);
                objs.btn.data("page", vars.page + 1);
            }    
        }
        if(!IsBlank(response["data"])){
            $(".result-count").html(response["data"]);
        }
        
        root.ui.scroll_to($(vars.container).find("[data-page='"+(scroll_to)+"']"));  
    }
}