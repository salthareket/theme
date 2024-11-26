jQuery(document).ready(function($) {
    $('.sticky-checkbox').on('change', function() {
        var post_id = $(this).data('post-id');
        var checked = $(this).is(':checked');

        $.ajax({
            url: stickyAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'toggle_sticky',
                post_id: post_id,
                sticky: checked
            },
            success: function(response) {
                if (!response.success) {
                    alert('Failed to update sticky status.');
                }
            }
        });
    });
});
