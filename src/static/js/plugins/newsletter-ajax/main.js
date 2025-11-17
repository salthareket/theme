function validateEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // basitleştirilmiş, hızlı regex
    return re.test(email);
}

function isBlank(str) {
    return (!str || /^\s*$/.test(str));
}

jQuery(function($){
    var $form    = $('#newsletter');
    $form.attr('novalidate', true);

    var $name    = $form.find('input[name="nn"]'),
        $surname = $form.find('input[name="ns"]'),
        $input   = $form.find('input[name="ne"]'),
        $accept  = $form.find('input[name="ny"]'),
        $submit  = $form.find('button[type="submit"]'),
        submit_text = $submit.text();

    $form.on('submit', function(e){
        e.preventDefault();

        var serializedData = $form.serialize();

        // name check
        if ($name.length && isBlank($name.val())) {
            alert("Please write your name.");
            return false;
        }
        // surname check
        if ($surname.length && isBlank($surname.val())) {
            alert("Please write your surname.");
            return false;
        }

        // email check
        if (!validateEmail($input.val())) {
            alert("Invalid email address.");
            return false;
        }

        // privacy acceptance
        if ($accept.length && !$accept.is(":checked")) {
            alert("Please accept privacy policy.");
            return false;
        }

        // prepare ajax data
        var data = {
            action: 'realhero_subscribe',
            nonce: ajax_request_vars.ajax_nonce,
            data: serializedData
        };

        $.ajax({
            method: "POST",
            url: ajax_request_vars.url_admin,
            data: data,
            beforeSend: function(){
                $input.prop('disabled', true);
                $submit.text('Please wait...').prop('disabled', true);
            },
            success: function(response){
                $input.prop('disabled', false);
                $submit.text(submit_text).prop('disabled', false);

                if (response.success) {
                    alert(response.data.msg);
                    $form[0].reset();
                } else {
                    alert(response.data.msg || "Unexpected error.");
                }
            },
            error: function(xhr, status, error){
                $input.prop('disabled', false);
                $submit.text(submit_text).prop('disabled', false);
                alert("AJAX error: " + error);
            }
        });

    });
});
