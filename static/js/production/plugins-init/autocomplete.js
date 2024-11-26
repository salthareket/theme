function form_control_autocomplete(){
    /* type : 
       response-type :
       count :
       page :
       selected :
    */
    var token_init = "form-control-autocomplete-init";
    $(".form-control-autocomplete").not("."+token_init).each(function(){
        $(this).addClass(token_init);
        if(IsBlank($(this).attr("id"))){
            var id = generateCode(8, "alpha");
            $(this).attr("id", id);
        }
        var queryType = $(this).data("query-type");
        function get_autocomplete_keyword($obj){
            return $obj.val();
        }
        AutoComplete({
            EmptyMessage: "No item found",
            HttpMethod: "post",
            HttpHeaders: {
                "HTTP_X_CSRF_TOKEN": ajax_request_vars.ajax_nonce
            },
            MinChars: 2,
            QueryArg: {
                ajax:"query",
                method:"autocomplete_terms",

                type:queryType,
                "response-type":"autocomplete",
                _wpnonce:ajax_request_vars.ajax_nonce

            },//"ajax=query&method=autocomplete_terms&type="+queryType+"&response-type=autocomplete&_wpnonce="+ajax_request_vars.ajax_nonce+"&keyword",
            Url: ajax_request_vars.url,//+"?ajax=query&method=autocomplete_terms&type="+queryType+"&response-type=autocomplete&_wpnonce="+ajax_request_vars.ajax_nonce,
            _Select: function(item) {
                debugJS(item)
                //debugJS(this.Request.response);
                $(this.Input).val($(item).text());
            }
        }, "#"+id);
    });
}