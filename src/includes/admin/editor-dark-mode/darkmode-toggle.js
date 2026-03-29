(function() {
    tinymce.PluginManager.add('darkmode_toggle', function(editor) {
        var isDark = false;

        editor.addButton('darkmode_toggle', {
            text: '',
            icon: 'contrast',
            tooltip: 'Toggle Dark Mode',
            onclick: function() {
                isDark = !isDark;
                var body = editor.getBody();
                body.style.backgroundColor = isDark ? '#111' : '';
                body.style.color           = isDark ? '#fff' : '';
                editor.buttons.darkmode_toggle.active(isDark);
            }
        });
    });
})();
