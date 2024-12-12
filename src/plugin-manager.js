jQuery(document).ready(function ($) {
    let plugins = pluginManagerAjax.plugins;
    let pluginsLocal = pluginManagerAjax.plugins_local;
    let $tableBody = $('#plugin-manager-table tbody');

    // Tabloyu doldur
    function renderTable() {
        $tableBody.empty();

        // WordPress Repo Pluginleri
        plugins.forEach(function (plugin, index) {
            let pluginName = plugin.split('/')[0].replace(/-/g, ' ').replace(/_/g, ' ');
            pluginName = pluginName.charAt(0).toUpperCase() + pluginName.slice(1);

            let isInstalled = $(`#plugin-row-${index}`).data('installed') || false;

            $tableBody.append(`
                <tr id="plugin-row-${index}">
                    <td>${pluginName}</td>
                    <td>${isInstalled ? 'Installed' : 'Not Installed'}</td>
                    <td>N/A</td>
                    <td>
                        ${isInstalled
                            ? '<button class="button button-secondary" disabled>Installed</button>'
                            : `<button class="button button-primary install-plugin" data-plugin-slug="${plugin}">Install</button>`
                        }
                    </td>
                </tr>
            `);
        });

        // Local Pluginler
        pluginsLocal.forEach(function (plugin, index) {
            let pluginName = plugin.name;
            let installedVersion = plugin.installed_version || 'Not Installed';
            let updateAvailable = plugin.installed_version && plugin.installed_version < plugin.v;

            $tableBody.append(`
                <tr id="plugin-row-local-${index}">
                    <td>${pluginName}</td>
                    <td>${installedVersion}</td>
                    <td>${installedVersion} -> ${plugin.v}</td>
                    <td>
                        ${updateAvailable
                            ? `<button class="button button-warning update-plugin" data-plugin-file="${plugin.file}">Update</button>`
                            : `<button class="button button-primary install-local-plugin" data-plugin-file="${plugin.file}">Install</button>`
                        }
                    </td>
                </tr>
            `);
        });
    }

    // Plugin işlemi başlat
    function processPlugin($button, action, pluginSlug, pluginFile) {
        $button.prop('disabled', true).text('Processing...');

        $.post(pluginManagerAjax.ajax_url, {
            action: 'plugin_manager_process',
            action_type: action,
            plugin_slug: pluginSlug || '',
            plugin_file: pluginFile || '',
        })
            .done(function (response) {
                if (response.success) {
                    $button.text(action === 'update' ? 'Updated' : 'Installed').removeClass('button-primary').addClass('button-secondary');
                } else {
                    $button.text('Error').prop('disabled', false);
                    alert(response.data.message || 'An error occurred.');
                }
                renderTable(); // Tabloyu yeniden yükle
            })
            .fail(function () {
                $button.text('Error').prop('disabled', false);
                alert('An unknown error occurred.');
            });
    }

    // Install/Update butonuna tıklanma işlemi
    $tableBody.on('click', '.install-plugin, .install-local-plugin, .update-plugin', function () {
        let $button = $(this);
        let pluginSlug = $button.data('plugin-slug');
        let pluginFile = $button.data('plugin-file');
        let action = $button.hasClass('update-plugin') ? 'update' : 'install';

        processPlugin($button, action, pluginSlug, pluginFile);
    });

    // Tabloyu başlat
    renderTable();
});
