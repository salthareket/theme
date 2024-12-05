function input_masks(){
    
    $("[data-slug]")
    .inputmask({
        min:8,
        max:25,
        onKeyValidation: function(key, result){
            debugJS(key, result)
            debugJS($(this).val());
            var slug = "";
            if (key){
              slug = $(this).val().replace(/\s+/g,'-').replace(/[^a-zA-Z0-9\-]/g,'').toLowerCase().replace(/\-{2,}/g,'-');
            }
            $(this).val(slug)
        }
        /*mask: function (a) {
            debugJS(a)
            return ["[1-]AAA-999", "[1-]999-AAA"];
        }*/
    });

    $("[data-alphaonly]")
        .inputmask({
            regex: "[A-ZÇŞİĞÖÜ a-zçşığöü]*",
            jitMasking: true,
            casing: "upper",
            onBeforePaste: function (pastedValue, opts) {
                pastedValue = pastedValue.toUpperCase();
                return pastedValue;
            }
        })
        .attr("pattern","[A-ZÇŞİĞÖÜ a-zçşığöü]*");

    $("[data-alphanumericonly]")
        .inputmask({
            regex: "[A-Za-z0-9]*",
            jitMasking: true,
            casing: "upper",
            onBeforePaste: function (pastedValue, opts) {
                pastedValue = pastedValue.toUpperCase();
                return pastedValue;
            }
        })
        .attr("pattern","[A-Za-z0-9]*");

    $("[data-numericonly]")
        .inputmask({
            regex: "[0-9]*",
            jitMasking: true,
            min:1,
            onBeforePaste: function (pastedValue, opts) {
                pastedValue = pastedValue.toUpperCase();
                return pastedValue;
            }
        })
        .attr("inputmode", "numeric");

    $(".form-control-percentage").inputmask({
        //alias : 'percentage'
        alias: "numeric",
        digits: 2,
        digitsOptional: false,
        radixPoint: ".",
        placeholder: "00,00",
        groupSeparator: "",
        min: 0,
        max: 100,
        suffix: "",
        allowMinus: false,
        numericInput: true,
        autoGroup: true
    })
    .attr("inputmode", "decimal");

    $(".form-control-date").not(".inited").inputmask({
        alias: 'datetime',
        placeholder: "__.__.____",
        mask: '99.99.9999'
    });

    $(".form-control-date-monthyear").not(".inited").inputmask({
        alias: 'datetime',
        placeholder: "__.____",
        mask: '99.9999'
    });

    $(".form-control-tckn")
    .inputmask({
        placeholder: "___________",
        mask: '99999999999',
        casing: "upper",
        autoUnmask : true
    })
    .attr("inputmode", "numeric")
    .attr("data-rule-minlength", 11)
    .attr("data-msg-minlength", "TC Kimlik numaranız en az 11 karakter içermelidir");

    $(".form-control-tckn-serial")
    .inputmask({
        placeholder: "___________",
        mask: 'A99A99999',
        casing: "upper",
        autoUnmask : true
    })
    .attr("data-rule-minlength", 9)
    .attr("data-msg-minlength", "Kimlik seri numaranız en az 9 karakter içermelidir");

    $(".form-control-tckn-old").inputmask({
        placeholder: "______",
        mask: '999999',
        casing: "upper"
    })
    .attr("inputmode", "numeric")
    .attr("data-rule-minlength", 6)
    .attr("data-msg-minlength", "TC Kimlik numaranız en az 6 karakter içermelidir");

    $(".form-control-tckn-old-serial").inputmask({
        placeholder: "___",
        mask: 'A99',
        casing: "upper"
    })

    $(".form-control-taxno").inputmask({
        placeholder: "__________",
        mask: '9999999999',
        casing: "upper"
    })
    .attr("inputmode", "numric");

    $(".form-control-vkn").inputmask({
        placeholder: "__________",
        mask: '9999999999',
        casing: "upper"
    })
    .attr("inputmode", "numeric");

    $(".form-control-license")
        .inputmask({
            mask: '99A[A][A]9999',
            jitMasking: true,
            casing: "upper",
        })

    $(".form-control-iban").inputmask({
        placeholder: '__ ____ ____ ____ ____ ____ __',
        //mask : '99 9999 9999 9999 9999 9999 99'
        mask: '** **** **** **** **** **** **',
        jitMasking: true,
        casing: "upper",
    })
    .attr("inputmode", "numeric");

    $(".form-control-email").inputmask({
        mask: "*{1,20}[.*{1,20}][.*{1,20}][.*{1,20}]@*{1,20}[.*{2,6}][.*{1,2}]",
        //mask : "{1,20}@{1,20}.{3}[.{2}]",
        greedy: false,
        /*autoUnmask : true,
        onUnMask: function(maskedValue, unmaskedValue) {
            return unmaskedValue;
        },*/
        onBeforePaste: function (pastedValue, opts) {
            pastedValue = pastedValue.toLowerCase();
            //return pastedValue;
        },
        definitions: {
            '*': {
                validator: "[0-9A-Za-z!#$%&'*+/=?^_`{|}~\-]",
                casing: "lower"
            }
        }
    })
    .bind("paste", function (e) {
        var pastedData = e.originalEvent.clipboardData.getData('text');
        $(this).val("");
    });

    $(".form-control-phone")
    .inputmask({
        placeholder: "0___ ___ __ __",
        mask: '0999 999 99 99',
        autoUnmask : true
    })
    .attr("inputmode", "tel")
    .attr("data-rule-minlength", 10)
    .attr("data-msg-minlength", "Telefon numaranız en az 11 karakter içermelidir");

    $(".form-control-gsm")
    .inputmask({
        placeholder: "05__ ___ __ __",
        mask: '0599 999 99 99',
        autoUnmask : true
    })
    .attr("inputmode", "tel")
    .attr("data-rule-minlength", 9)
    .attr("data-msg-minlength", "Telefon numaranız en az 11 karakter içermelidir");

    $(".form-control-postal-code").inputmask({
        placeholder: "_____",
        mask: '99999',
        autoUnmask : true
    })
    .attr("inputmode", "numeric")
    .attr("data-rule-minlength", 5)
    .attr("data-msg-minlength", "Posta kodunuz en az 5 karakter içermelidir");
    
    $(".form-control-currency, .form-control-currency-minus").each(function () {
        if($(this).hasClass("form-control-currency-minus")){
           $(this).attr("inputmode", "decimal")
        }
        $(this).attr("placeholder", "0.00");
        if (!IsBlank($(this).val())) {
            value = $(this).val().replace(",", "")
            value = value.split(".")[0];//parseInt($(this).val());
            value = numeral(value).format('0,0,0.00');
            $(this).val(value);
        }
    });

    $(".form-control-currency, .form-control-currency-minus")
        .on("keydown keyup", function () {
            if ($(this).val() == "-") {
                if ($(this).hasClass("form-control-currency-minus")) {
                    var value = "-";
                } else {
                    var value = "";
                }
            } else {
                var value = numeral($(this).val()).value();
            }

            var hasMinus = false;
            if (!IsBlank(value)) {
                debugJS(value)
                if (value < 0 || value == "-") {
                    hasMinus = true;
                }
            }
            if (!hasMinus) {
                if (isNaN(value)) {
                    value = 0;
                } else {
                    value = numeral($(this).val()).value();
                }
            }

            if (value > 0 || hasMinus && value != "-") {
                value = numeral(value).format();
            }
            $(this).val(value);
        })
        .on("focus", function () {
            var value = numeral($(this).val()).value();
            var hasMinus = false;
            if (!IsBlank(value)) {
                debugJS(value)
                if (value < 0) {
                    hasMinus = true;
                }
            }
            if (isNaN(value)) {
                value = 0;
            } else {
                value = numeral($(this).val()).value();
            }
            if (value > 0 || hasMinus) {
                value = numeral(value).format();
            } else {
                value = "";
            }
            $(this).val(value);

        })
        .on("blur", function () {
            var value = numeral($(this).val()).value();
            if(IsBlank(value) && value != 0){
               return;
            }
            var hasMinus = false;
            if (!IsBlank(value)) {
                if (value < 0) {
                    hasMinus = true;
                }
            }
            if (isNaN(value) || value == null) {
                value = 0;
            } else {
                value = numeral($(this).val()).value();
            }
            if (value > 0 || hasMinus) {
                value = numeral(value).format();
            }
            $(this).val(value + ",00");
        });
}