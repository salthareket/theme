jQuery(document).ready(function($) {
    $('#publish').on('click', function(e) {
        var language = $('#wp-admin-bar-languages > .ab-item > .ab-label').attr("lang"); // Dil seçimini yakala
        if (language && typenow == "") {
            var confirmMessage = 'Eğer sayfayı kaydederseniz bu optionlar ' + language + ' sayfası için ayarlanacaktır. Emin misiniz?';
            if (!confirm(confirmMessage)) {
                e.preventDefault(); // Kaydetmeyi iptal et
            }
        }
    });
});
