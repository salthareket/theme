jQuery(document).ready(function ($) {

    const tasks = updateAjax.tasks || [];
    console.log(tasks);
    let currentTaskIndex = 0;

    const progressBar = $('#installation-progress');
    const taskElements = tasks.map(task => $(`#task-${task.id}`));

    $('#start-installation-button').on('click', function () {
        $(this).prop('disabled', true); // Butonu devre dışı bırak
        runTask(currentTaskIndex); // İlk görevi çalıştır
    });

    function runTask(taskIndex) {
        if (taskIndex >= tasks.length) {
            completeInstallation();
            return;
        }

        const task = tasks[taskIndex];

        $.ajax({
            url: updateAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'run_task',
                task_id: task.id,
                nonce: updateAjax.nonce
            },
            beforeSend: function () {
                taskElements[taskIndex]
                    .removeClass('bg-secondary')
                    .addClass('bg-warning')
                    .text('In Progress');
            },
            success: function (response) {
                if (response.success) {
                    const progress = Math.round(((taskIndex + 1) / tasks.length) * 100);
                    progressBar
                        .css('width', progress + '%')
                        .attr('aria-valuenow', progress)
                        .text(progress + '%');

                    taskElements[taskIndex]
                        .removeClass('bg-warning')
                        .addClass('bg-success')
                        .text('Completed');

                    runTask(taskIndex + 1);
                } else {
                    taskElements[taskIndex]
                        .removeClass('bg-warning')
                        .addClass('bg-danger')
                        .text('Failed');
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                taskElements[taskIndex]
                    .removeClass('bg-warning')
                    .addClass('bg-danger')
                    .text('Error');
                alert('An unexpected error occurred.');
            }
        });
    }

    function completeInstallation() {
        alert('Installation completed successfully!');
        location.reload(); // Sayfayı yenile
    }


    function composer_message($message = "", $action = "", $type = ""){
        if($message == ""){
            $(".alert").removeClass("show").addClass("d-none").empty();
            if($action != "loading"){
                return;
            }else{
                $message = "Please wait...";
            }
        }
        let $class = "";
        switch($action){
            case "install" :
                $class = "alert-success";
                break;
            case "update" :
                $class = "alert-success";
                break;
            case "remove" :
            case "error" :
                $class = "alert-danger";
                break;
            case "nothing" :
                $class = "alert-secondary";
                break;
            case "loading" :
                $class = "alert-secondary loading loading-xs";
                break;
        } 
        let alert = $(".alert[data-action='" + $type +"']");
        alert
        .removeClass("loading alert-success alert-danger alert-secondary alert-info show d-none")
        .addClass($class)
        .html($message)
        .addClass("show");
    }
    $('#update-theme-button').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true).text('Updating...');

        composer_message("", "loading", "update");

        $.ajax({
            url: updateAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_theme_package',
                _ajax_nonce: updateAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    composer_message(response.data.message, response.data.action, "update");
                } else {
                    composer_message(response.data.message, "error", "update");
                }
                $button.prop('disabled', false).text('Update');
            },
            error: function () {
                composer_message('AJAX request failed.', "error", "update");
                $button.prop('disabled', false).text('Update');
            }
        });
    });

    $('#install-package-button').on('click', function () {
        const packageName = $('#install-package-name').val();
        if (!packageName) {
            alert('Please enter a package name.');
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Installing...');

        composer_message("", "loading", "install");

        $.post(
            updateAjax.ajax_url,
            {
                action: 'install_new_package',
                nonce: updateAjax.nonce,
                package: packageName
            },
            function (response) {
                if (response.success) {
                    if ($('#remove-package-name option[value="' + packageName + '"]').length === 0) {
                        $('#remove-package-name').append(
                            $('<option>', {
                                value: packageName,
                                text: packageName
                            })
                        );
                    }
                    composer_message(response.data.message, response.data.action, "install");
                } else {
                    composer_message(response.data.message, "error", "install");
                }
                $button.prop('disabled', false).text('Install Package');
            }
        );
    });

    $('#remove-package-button').on('click', function () {
        const packageName = $('#remove-package-name').val();
        if (!packageName) {
            alert('Please choose a package name.');
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Removing...');

        composer_message("", "loading", "remove");

        $.post(
            updateAjax.ajax_url,
            {
                action: 'remove_package',
                nonce: updateAjax.nonce,
                package: packageName
            },
            function (response) {
                console.log(response)
                console.log(response.data)
                console.log(response.data.action)
                if (response.success) {
                    $('#remove-package-name option[value="' + packageName + '"]').remove();
                    composer_message(response.data.message, response.data.action, "remove");
                } else {
                    composer_message(response.data.message, "error", "remove");
                }
                $button.prop('disabled', false).text('Remove Package');
            }
        );
    });


});
