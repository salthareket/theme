$required_setting = ENABLE_REGIONAL_POSTS;

if ( typeof acf !== 'undefined' ) {

    jQuery(".acf-row").not(".acf-clone").find("[data-name='post_type']").find("select")

    //jQuery(".acf-field-640100fd7e753").find("select")
    .on("change", function(e) { // Layouts -> Posts -> Post Type
            acf_regional_post_taxonomy(e, $);
    })
    .trigger('ready').trigger("change");
    function acf_regional_post_taxonomy(e, $){
        if (this.request) {
            //this.request.abort();
        }
        var target = jQuery(e.target);
        var name = target.closest(".acf-field").attr("data-name");
        var value = target.val();
        var id = target.attr("id");
        var row_number = "";
        if(id.indexOf("-row-")>-1){
           row_number = id.split("-row-")[1];
           row_number = row_number.split("-")[0];
        }else{
            id = id.split("acfcloneindex");
            var obj = jQuery("[id$="+id[1]+"][id^="+id[0]+"row-]");
            id = obj.attr("id");
            row_number = id.split("-row-")[1];
            row_number = row_number.split("-")[0];
        }
        var type = value;//jQuery("select#acf-field_63dce8ede6960-row-"+row_number+"-field_64288a003273b").val();
        var taxonomy_select = target.closest(".acf-row").find("[data-name='taxonomy']").find("select")//jQuery(".acf-field-6428222aa7af5").find("select"); // taxonomy field
        debugJS(taxonomy_select)

        if (!value) {
            return;
        }
        var data = {
            action: 'get_regional_posts_type_taxonomies',
            type : type,
            name : name,
            value : value,
            post_id: acf.get('post_id')
        }
        if(row_number != ""){
            data["row"] = row_number;
        }
        data = acf.prepareForAjax(data);
        var container = target.closest(".acf-repeater");
        container.addClass("loading-process");
        this.request = $.ajax({
            url: acf.get('ajaxurl'),
            data: data,
            type: 'post',
            dataType: 'json',
            success: function(json) {
                if (!json) {
                    return;
                }
                if(json.error){
                    //taxonomy_select.find("option").prop("disabled", true).addClass("d-none");
                    container.removeClass("loading-process");
                    alert(json.message);
                }else{
                   //taxonomy_select.find("option").prop("disabled", true).addClass("d-none");
                    taxonomy_select.html("<option value=''>Choose a taxonomy</option>");
                    taxonomy_select.append(json.html);
                }
                container.removeClass("loading-process");
            }
        });
    }

}