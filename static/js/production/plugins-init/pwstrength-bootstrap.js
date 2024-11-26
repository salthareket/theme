function form_control_password_strength(){
    var token_init = "form-control-password-strength-init";
    if($("input[type='password'].form-control-password-strength").not("."+token_init).length > 0){
        $("input[type='password'].form-control-password-strength").not("."+token_init).each(function(i){
            var $el = $(this);
            $el.addClass(token_init);
            $el.closest(".form-group").append("<div class='form-text'><span></span></div>");

            var options = {};
                options.common = {
                    minChar : 8,
                    usernameField : $("input[name='email_new']"),
                    debug:true
                }
                options.ui = {
                    container: $el.closest(".form-group").find('.form-text'),
                    showStatus: true,
                    showProgressBar: false,
                    showPopover:false,
                    showVerdictsInsideProgressBar : false,
                    viewports: {
                        verdict: $el.closest(".form-group").find('span'),
                        errors: $el.closest(".form-group").find('span')
                    },
                    verdicts : ["Weak", "Normal", "Medium", "Strong", "Very Strong"],
                    errorMessages: {
                          password_too_short: "The Password is too short",
                          email_as_password: "Do not use your email as your password",
                          same_as_username: "Your password cannot contain your username",
                          two_character_classes: "Use different character classes",
                          repeated_character: "Too many repetitions",
                          sequence_found: "Your password contains sequences"
                    },
                    spanError : function (options, key) {
                          var text = options.ui.errorMessages[key];
                          debugJS(key);
                          return '<span style="color: #d52929">' + text + '</span>';
                    },
                    showErrors: false
                };
                options.rules = {
                    activated: {
                        wordMaxLength: true,
                        wordInvalidChar: true
                    }
                };
            $($el).pwstrength(options);
        });
    }   
}