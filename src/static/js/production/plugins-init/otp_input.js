/**
 * OTP Input — init
 * PAE / AjaxInitManager tarafından .otp-input selector tespit edilince çağrılır.
 * Manuel: init_otp_input($scope)
 */
function init_otp_input($scope) {
    var $s = ($scope && $scope.length) ? $scope : $('body');
    $s.find('.otp-input').not('.otp-input-init').each(function() {
        sh_otp_input($(this));
    });
}
