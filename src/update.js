jQuery(document).ready(function ($) {

    const tasks = updateAjax.tasks || [];

    let currentTaskIndex = 0;
    const progress = $('.progress');
    const progressBar = $('#installation-progress');
    const installationStatus = $(".installation-status");

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
                progress.css("display", "block");
                installationStatus.css("display", "block");
                installationStatus.html(task["name"]);
            },
            success: function (response) {
                if (response.success) {
                    installationStatus.html(response.data.message);
                    const progress = Math.round(((taskIndex + 1) / tasks.length) * 100);
                    progressBar
                        .css('width', progress + '%')
                        .attr('aria-valuenow', progress)
                        .text(progress + '%');
                    runTask(taskIndex + 1);
                } else {
                    installationStatus.html(task["name"]+"<br><div style='color:red;'>"+response.data.message+"</div>");
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                installationStatus.html(task["name"]+"<br><div style='color:red;'>An unexpected error occurred</div>");
                alert('An unexpected error occurred.');
                currentTaskIndex = taskIndex;
                $('#start-installation-button').prop('disabled', false).html("Try Again")
            }
        });
    }

    function completeInstallation() {
        installationStatus.html("<div style='color:green;'>Installation completed successfully!</div>");
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
