jQuery(document).ready(function($) {
    $('.sticky-checkbox').on('change', function() {
        var $cb = $(this);
        $.ajax({
            url: stickyAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'toggle_sticky',
                post_id: $cb.data('post-id'),
                nonce: stickyAjax.nonce
            },
            success: function(response) {
                if (!response.success) {
                    alert('Failed to update sticky status.');
                    $cb.prop('checked', !$cb.is(':checked'));
                }
            }
        });
    });
});
