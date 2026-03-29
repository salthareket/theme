jQuery(document).ready(function($) {
    setTimeout(function() {
        if (typeof tinymce === 'undefined' || typeof qTranslateConfig === 'undefined') return;

        var editor = tinymce.get('description');
        if (!editor) return;

        var content = editor.getContent().replace(/^<p>|<\/p>$/g, '');
        var regex   = /\[:([a-z]+)]([^\[:]+)(?=\[|$)/g;
        var result  = {};
        var match;

        while ((match = regex.exec(content)) !== null) {
            result[match[1]] = match[2];
        }

        var activeLang = qTranslateConfig.activeLanguage;

        for (var lang in result) {
            $("input[name='qtranslate-fields[description][" + lang + "]']").val(result[lang]);
        }

        editor.setContent('');
        if (result[activeLang]) {
            editor.execCommand('mceInsertContent', false, result[activeLang]);
        }
    }, 100);
});
