jQuery(document).ready(function($) {

    setTimeout(function(){
        if(typeof tinymce !== "undefined" && typeof qTranslateConfig !== "undefined"){
            var editor = tinymce.get('description');
            if(editor){
                var content = editor.getContent();
                var content = content.replace(/^<p>|<\/p>$/g, '');
                //var regex = /\[:([a-z]+)](.*?)\[:]/g;
                var regex = /\[:([a-z]+)]([^\[:]+)(?=\[|$)/g;
                var result = {};
                var match;
                while ((match = regex.exec(content)) !== null) {
                    var lang = match[1];
                    var value = match[2];
                    result[lang] = value;
                }
                var languages = qTranslateConfig.language_config;
                var lang_active = qTranslateConfig.activeLanguage;
                var keys = Object.keys(languages);
                for(var item in result){
                    $("input[name='qtranslate-fields[description]["+item+"]']").val(result[item]);
                }
                editor.setContent('');
                editor.execCommand('mceInsertContent', false, result[lang_active]);    /**/             
            }
        }
    }, 100);
    
});