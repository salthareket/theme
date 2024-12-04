(function() {
    if(typeof tinymce !== "undefined"){
        tinymce.PluginManager.add('letter_spacing_button', function(editor, url) {
            editor.addButton('letter_spacing_button', {
                text: 'Letter Spacing',
                icon: false,
                onclick: function() {
                    // Ask the user for the letter-spacing value
                    var spacingValue = prompt('Enter the letter-spacing value (e.g. 2px):');
                    
                    // Check if the user entered a value
                    if (spacingValue !== null) {
                        // Wrap the selected text with a span element and apply letter-spacing style
                        editor.formatter.apply('letter_spacing', {value: spacingValue});
                    }
                }
            });

            // Define the custom formatter for letter-spacing
            editor.formatter.register('letter_spacing', {
                inline: 'span',
                styles: { 'letter-spacing': '%value' },
                remove_similar: true
            });
        });   
    }
})();
