jQuery(document).ready(function($) {
    $('#publish').on('click', function(e) {
        var lang = $('#wp-admin-bar-languages > .ab-item > .ab-label').attr('lang');
        if (lang && typeof typenow !== 'undefined' && typenow === '') {
            if (!confirm('Bu optionlar ' + lang + ' sayfası için ayarlanacaktır. Emin misiniz?')) {
                e.preventDefault();
            }
        }
    });
});
