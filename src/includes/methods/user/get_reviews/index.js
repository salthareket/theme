$required_setting = true;

{
    after: function(response, vars, form, objs) {
        // Guard: yeni source sistemi (loadSourceContent + _renderSourceResult) kendi render'ini
        // yapar ve objs.obj gondermez. Sadece eski cagri sekli (objs.obj set edilmis) icin calis.
        if (objs && objs.obj && objs.obj.length && objs.obj.find(">.card-body").length) {
            objs.obj.find(">.card-body").append(response.html);
        }
    }
}
