document.addEventListener('wpcf7invalid', function(event) {
    var invalidFields = event.detail.apiResponse.invalid_fields;
    if (invalidFields.length === 1 && invalidFields[0].field === 'your-email') {
        var form = event.target;
        var messageElement = form.querySelector('.wpcf7-response-output');
            messageElement.classList.add('d-none');
        setTimeout(function() {
            messageElement.innerHTML = invalidFields[0].message;
            messageElement.classList.remove('d-none');
        }, 100);
    }
}, false);
