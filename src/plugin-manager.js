jQuery(document).ready(function ($) {
    // Activate/Deactivate Plugin
    $(document).on('click', '.activate-plugin, .deactivate-plugin', function () {
        let $button = $(this);
        let pluginSlug = $button.data('plugin-slug');
        let local = $button.data('local');

        // Slug doğrulama
        if (!pluginSlug || !pluginSlug.includes('/')) {
            alert('Plugin slug eksik veya hatalı.');
            return;
        }

        let actionType = $button.hasClass('deactivate-plugin') ? 'deactivate' : 'activate';

        $button.prop('disabled', true).text('Processing...');

        $.post(pluginManagerAjax.ajax_url, {
            action: 'plugin_manager_process',
            plugin_slug: pluginSlug,
            action_type: actionType,
            local: local
        })
            .done(function (response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).text(actionType === 'activate' ? 'Activate' : 'Deactivate');
                }
            })
            .fail(function () {
                alert('AJAX request failed.');
                $button.prop('disabled', false).text(actionType === 'activate' ? 'Activate' : 'Deactivate');
            });
    });

    // Install/Update Plugin
    $(document).on('click', '.install-plugin, .update-plugin', function () {
        let $button = $(this);
        let pluginSlug = $button.data('plugin-slug');

        // Slug doğrulama
        if (!pluginSlug || !pluginSlug.includes('/')) {
            alert('Plugin slug eksik veya hatalı.');
            return;
        }

        let actionType = $button.hasClass('update-plugin') ? 'update' : 'install';
        let local = $button.data('local');

        $button.prop('disabled', true).text('Processing...');

        $.post(pluginManagerAjax.ajax_url, {
            action: 'plugin_manager_process',
            plugin_slug: pluginSlug,
            action_type: actionType,
            local: local
        })
            .done(function (response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).text(actionType === 'update' ? 'Update' : 'Install');
                }
            })
            .fail(function () {
                alert('AJAX request failed.');
                $button.prop('disabled', false).text(actionType === 'update' ? 'Update' : 'Install');
            });
    });
});
