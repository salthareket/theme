jQuery(document).ready(function ($) {

    const status = updateAjax.status;
    const tasks = updateAjax.tasks || [];

    let currentTaskIndex = 0;
    const progress = $('.progress');
    const progressBar = $('#installation-progress');
    const installationStatus = $(".installation-status");

    $('#start-installation-button').on('click', function () {
        $(this).prop('disabled', true); // Butonu devre dışı bırak
        if(status == "pending"){
            runTask(currentTaskIndex); // İlk görevi çalıştır
        }
    });

    function plugin_types() {
        const checkedValues = [];
        document.querySelectorAll('input[name="plugin_types"]:checked').forEach(checkbox => {
            checkedValues.push(checkbox.value);
        });
        return checkedValues;
    }

    function parseAjaxResponse(response) {
        // Eğer response zaten bir nesne ise direkt dön
        if (typeof response === "object") {
            debugJS("JSON Data:", response);
            return response;
        }

        // JSON içerip içermediğini kontrol et
        const jsonRegex = /{"success":.*}}/;

        // JSON kısmını bul
        const match = response.match(jsonRegex);
        if (match) {
            try {
                // JSON kısmını ayıkla ve parse et
                const jsonData = JSON.parse(match[0]);
                debugJS("JSON Data:", jsonData);
                return jsonData;
            } catch (error) {
                console.error("JSON parse hatası:", error);
                return null;
            }
        } else {
            console.error("JSON verisi bulunamadı.");
            return null;
        }
    }

    function runTask(taskIndex) {

        console.table(taskIndex, tasks.length);

        if (taskIndex >= tasks.length) {
            completeInstallation();
            return;
        }

        const task = tasks[taskIndex];
        let tasks_status = [];

        $.ajax({
            url: updateAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'run_task',
                task_id: task.id,
                plugin_types: plugin_types(),
                tasks_status: tasks_status,
                nonce: updateAjax.nonce
            },
            beforeSend: function () {
                progress.css("display", "block");
                installationStatus.css("display", "block");
                installationStatus.html(task["name"]);
            },
            success: function (response) {
                response = parseAjaxResponse(response);
                if (response.success) {
                    installationStatus.html(response.data.message);
                    tasks_status = response.data.tasks_status;
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


    function runUpdateTask(taskIndex, data) {

        console.table(taskIndex, tasks.length);

        if (taskIndex >= tasks.length) {
            composer_message(data.message, "update", "update");
            $('#update-theme-button').prop('disabled', false).html("Update Depencies");
            return;
        }

        const task = tasks[taskIndex];
        let tasks_status = status;

        $.ajax({
            url: updateAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'run_task',
                task_id: task.id,
                plugin_types: plugin_types(),
                tasks_status: tasks_status,
                nonce: updateAjax.nonce
            },
            beforeSend: function () {
                composer_message(task["name"] + ", Please wait...", "loading", "update");
            },
            success: function (response) {
                response = parseAjaxResponse(response);
                if (response.success) {
                    composer_message(response.data.message + ", Please wait...", "loading", "update");
                    runUpdateTask(taskIndex + 1, data);
                } else {
                    composer_message(task["name"]+"<br><div style='color:red;'>"+response.data.message+"</div>", "error", "update");
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                composer_message(task["name"]+"<br><div style='color:red;'>An unexpected error occurred</div>", "error", "update");
                alert('An unexpected error occurred.');
                currentTaskIndex = taskIndex;
                $('#update-theme-button').prop('disabled', false).html("Try Again");
            }
        });
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
            case "refresh" :
                $class = "alert-secondary loading loading-xs";
                location.reload(true);
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
                debugJS(response);
                if (response.success) {
                    if(response.data.action == "update"){
                        runUpdateTask(0, response.data); // İlk görevi çalıştır
                    }else{
                        composer_message(response.data.message, response.data.action, "update");
                        $button.prop('disabled', false).text('Update');
                    }
                } else {
                    composer_message(response.data.message, "error", "update");
                    $button.prop('disabled', false).text('Update');
                }
            },
            error: function () {
                composer_message('AJAX request failed.', "error", "update");
                $button.prop('disabled', false).text('Update');
            }
        });
    });
    if($('#update-theme-button').hasClass("init")){
       $('#update-theme-button').trigger("click");
    }

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
                debugJS(response)
                debugJS(response.data)
                debugJS(response.data.action)
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



    $('#install-ffmpeg-button').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true).text('Installing...');

        composer_message("Please wait...", "loading", "install");

        $.ajax({
            url: updateAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'install_ffmpeg',
                _ajax_nonce: updateAjax.nonce
            },
            success: function (response) {
                debugJS(response);
                if (response.success) {
                    composer_message(response.data.message, response.data.action, "install");
                } else {
                    composer_message(response.data.message, "error", "install");
                }
                $button.prop('disabled', false).text('Install');
            },
            error: function () {
                composer_message('AJAX request failed.', "error", "update");
                $button.prop('disabled', false).text('Install');
            }
        });
    });


});
