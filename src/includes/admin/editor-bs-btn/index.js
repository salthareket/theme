(function() {
    if (typeof tinymce === 'undefined') return;

    tinymce.PluginManager.add('letter_spacing_button', function(editor) {
        editor.addButton('letter_spacing_button', {
            text: 'Letter Spacing',
            icon: false,
            onclick: function() {
                var val = prompt('Enter the letter-spacing value (e.g. 2px):');
                if (val !== null) {
                    editor.formatter.apply('letter_spacing', { value: val });
                }
            }
        });

        editor.formatter.register('letter_spacing', {
            inline: 'span',
            styles: { 'letter-spacing': '%value' },
            remove_similar: true
        });
    });
})();
