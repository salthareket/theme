function select_2(){
    var token_init = "select-2-init";
    $(".select-2").not("."+token_init).each(function(){
        var $obj = $(this);
        var $args = {
            theme: 'bootstrap-5',
            width: '100%',
            closeOnSelect : false
        };

        if($obj.data("hide-search")){
            $args["minimumResultsForSearch"] = -1;
        }

        var classes = "";
        if(bool($obj.data("checkbox"), false)){
             classes += "select2-checkbox";
        }
        if(bool($obj.data("hide-selected"), true)){
             classes += "select2-hide-selected";
        }
        $args["dropdownCssClass"] = classes;

        if($(this).attr("min")){
           if($(this).attr("min") > 0){
              $args["minimumInputLength"] = $(this).attr("min");
           }
        }
        if($(this).attr("placeholder")){
           $args["placeholder"] =  $(this).attr("placeholder");
        }
        if($(this).data("tags")){
            $args["tags"] = true;
            $args["tokenSeparators"] = [','];
            $args["createTag"] = function (params) {
                var term = $.trim(params.term);

                if (term === '') {
                  return null;
                }

                return {
                  id: term,
                  text: term,
                  newTag: true // add additional parameters
                }
            }
        }
        if($(this).data("autocomplete")){
            $args["ajax"] =  {
                dataType: 'json',
                delay: 250,
                url: ajax_request_vars.url+"?ajax=query",
                method : "post",
                data: function (params) {
                  var query = {
                    method : "autocomplete_terms",
                    keyword: params.term,
                    _wpnonce : ajax_request_vars.ajax_nonce,
                    vars : {
                        type : $(this).data("type"),
                        count : $(this).data("count"),
                        response_extra : $(this).data("response-extra"),
                        page: params.page || 1,
                        selected : function(){
                            var selected = "";
                            if(bool($obj.data("hide-selected"), true)){
                                var data = $obj.select2('data');
                                selected = data.map(function(a) { return a.id; })                                
                            }
                            return selected;
                        }
                    }
                  }
                  return query;
                },
                processResults: function (data, params) {
                    return {
                        results: data.data.results,
                        pagination: data.data.pagination
                    };
                },
                cache: true
              }
              $args["templateResult"] = formatRepo;
              if(bool($obj.data("minimal-view"), false)){
                 $args["templateSelection"] = formatMinimalSelection;
              }else{
                 $args["templateSelection"] = formatRepoSelection;
              }
        }
        $(this).select2($args).addClass(token_init);

        if($(this).data("sortable")){
            $(this).next().find("ul.select2-selection__rendered").sortable({
                containment: 'parent'
            });
        }
        if(!$(this).data("dropdown")){
            $(this).on('select2:opening select2:close', function(e){
                //$('body').toggleClass('kill-all-select2-dropdowns', e.type=='select2:opening');
            });
        }
        /*
        $(this).on('select2:close', function() {
            let select = $(this)
            $(this).next('span.select2').find('ul').html(function() {
               let count = select.select2('data').length
               return "<li class='w-100 d-flex'>" + select.attr("placeholder") + "<div class='badge rounded-pill text-bg-primary ms-auto'>" + count + "</div></li>"
            });
        });*/


        //fix default selected items title re-print
        /*$(this).next(".select2").find(".select2-selection__choice__display").each(function(){
            if(IsBlank($(this).text())){
                var title = $(this).closest(".select2-selection__choice").attr("title");
                $(this).text(title);
            }
        });
        $(this).on('change', function (e) {
           setTimeout(function(){
                $(e.target).next(".select2").find(".select2-selection__choice__display").each(function(){
                    if(IsBlank($(this).text())){
                        var title = $(this).closest(".select2-selection__choice").attr("title");
                        $(this).text(title);
                    }
                });                
            }, 1);
        });*/
    });
    
    function getSelected(){
        debugJS($(this).select2('data'))
        return $(this).select2('data')                   
    }

    function formatRepo (repo) {
        if (repo.loading) {
            return repo.text;
        }
        var $container = $(
            "<div class='select2-result-item'>" +
                "<div class='select2-result-item__title'></div>" +
            "</div>"
        );
        $container.find(".select2-result-item__title").text(repo.text);

        return $container;
    }

    function formatRepoSelection (repo) {
      return repo.text || repo.name;
    }
    function formatMinimalSelection(data, $obj) {
        //debugJS(data)
        //debugJS($obj)
        let select = $obj;
        var count = select.find('option:selected').length;
        //$obj.next('span.select2').find('ul').html(function() {
               //let count = select.select2('data').length
               //alert(count)
               return "<li class='w-100 d-flex'>" + select.attr("placeholder") + "<div class='badge rounded-pill text-bg-primary ms-auto'>" + count + "</div></li>"
        //});
    }
}

if(typeof $.fn.select2 !== "undefined"){
    $.fn.select2.defaults.set("theme", "bootstrap4");
}