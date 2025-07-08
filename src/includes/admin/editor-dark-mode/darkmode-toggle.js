(function() {
    tinymce.PluginManager.add('darkmode_toggle', function(editor, url) {
        var isDark = false;

        editor.addButton('darkmode_toggle', {
            text: '',
            icon: 'contrast', // ikon ismi burada
            tooltip: 'Toggle Dark Mode',
            onclick: function () {
                isDark = !isDark;

                var body = editor.getBody();

                if (isDark) {
                    body.style.backgroundColor = '#111';
                    body.style.color = '#fff';
                    editor.buttons.darkmode_toggle.active(true);
                } else {
                    body.style.backgroundColor = '';
                    body.style.color = '';
                    editor.buttons.darkmode_toggle.active(false);
                }
            }
        });
    });
})();
