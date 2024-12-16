jQuery(document).ready(function ($) {
    $('#update-theme-button').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true).text('Updating...');

        $.ajax({
            url: updateAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_theme_package',
                _ajax_nonce: updateAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload(); // Sayfayı güncellemeden sonra yeniler
                } else {
                    alert('Update failed: ' + response.data.message);
                    $button.prop('disabled', false).text('Update');
                }
            },
            error: function () {
                alert('AJAX request failed.');
                $button.prop('disabled', false).text('Update');
            }
        });
    });
});
