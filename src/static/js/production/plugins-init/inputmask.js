function input_masks(){

    // ── Custom jquery-validate method: inputmask tamamlanmış mı ─────────────
    if (typeof $.validator !== 'undefined' && !$.validator.methods['phone-mask-complete']) {
        $.validator.addMethod('phone-mask-complete', function(value, element) {
            // inputmask yüklüyse isComplete ile kontrol et
            if ($(element).inputmask && typeof $(element).inputmask('isComplete') === 'boolean') {
                return $(element).inputmask('isComplete');
            }
            // Fallback: boş değilse geçerli say
            return value.replace(/\D/g, '').length > 0;
        }, 'Please enter a valid phone number');
    }
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

    // ── Phone Masks — ülke bazlı dinamik inputmask ────────────────────────────
    // phone_code select'i değişince phone-masks.json'dan o ülkenin lokal mask'ini
    // al, country code kısmını çıkar, inputmask'e uygula.
    // Sadece register form phone input'u için (.form-control-phone yanındaki select).
    (function() {
        var _phoneMasksData = null;
        var _phoneMasksUrl  = (typeof ajax_request_vars !== 'undefined' && ajax_request_vars.theme_url)
            ? ajax_request_vars.theme_url + 'vendor/salthareket/theme/src/static/data/phone-masks.json'
            : null;

        /**
         * Mask string'inden lokal kısmı çıkar:
         * "+90 (000) 000 0000" → "(999) 999 9999"
         * - Country code prefix'ini (+XX, +XXX vb.) at
         * - 0 → 9 (inputmask rakam char)
         * - [0] → [9] (opsiyonel rakam)
         */
        function extractLocalMask(fullMask, cc) {
            if (!fullMask || fullMask === 'N/A') return null;
            // cc örn: "+90" — mask başından bunu ve arkasındaki boşluğu at
            var local = fullMask;
            if (cc && fullMask.startsWith(cc)) {
                local = fullMask.slice(cc.length).trimStart();
            } else {
                // +XX formatı mask'de farklı olabilir — sadece + ile başlayan kısmı at
                local = fullMask.replace(/^\+[\d]+\s*/, '');
            }
            // 0 → 9, [0] → [9]
            local = local.replace(/\[0\]/g, '[9]').replace(/0/g, '9');
            return local;
        }

        function applyPhoneMaskForIso(iso, $phoneInput) {
            if (!iso || !_phoneMasksData) return;
            var entry = _phoneMasksData[iso.toUpperCase()];
            if (!entry || !entry.mask || entry.mask === 'N/A') {
                // Fallback: mask kaldır, serbest giriş
                try { $phoneInput.inputmask('remove'); } catch(e) {}
                $phoneInput.attr('placeholder', '');
                $phoneInput.removeAttr('data-rule-minlength data-msg-minlength');
                return;
            }
            var localMask = extractLocalMask(entry.mask, entry.cc);
            if (!localMask) return;

            // Placeholder: 9 → 5 (örnek rakam görünümü)
            var ph = localMask.replace(/\[9\]/g, '').replace(/9/g, '_');

            // Minimum uzunluk: mask'teki zorunlu rakam sayısını say (9 = zorunlu, [9] = opsiyonel)
            var minLen = (localMask.replace(/\[9\]/g, '').match(/9/g) || []).length;

            try { $phoneInput.inputmask('remove'); } catch(e) {}
            $phoneInput.inputmask({
                mask: localMask,
                placeholder: ph,
                autoUnmask: true,
                jitMasking: false
            }).attr('inputmode', 'tel').attr('placeholder', ph);

            // jquery-validate — inputmask tamamlanmış mı kontrolü
            // data-rule-minlength runtime'da güncellenemez, custom method kullan
            $phoneInput
                .attr('data-rule-phone-mask-complete', 'true')
                .attr('data-msg-phone-mask-complete', 'Please enter a valid phone number')
                .removeAttr('data-rule-minlength data-msg-minlength');

            // phone-validator.js için ISO2 güncelle — data-country attribute
            $phoneInput.attr('data-country', iso.toUpperCase());
        }

        function initPhoneMaskSystem() {
            // Register form phone code select'lerini dinle
            $(document).on('change', 'select[name="phone_code"]', function() {
                var $select     = $(this);
                var iso         = $select.find(':selected').data('iso') || '';
                var $phoneInput = $select.closest('.input-group').find('.form-control-phone');
                if (!$phoneInput.length) return;

                if (_phoneMasksData) {
                    applyPhoneMaskForIso(iso, $phoneInput);
                } else if (_phoneMasksUrl) {
                    fetch(_phoneMasksUrl)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            _phoneMasksData = data;
                            applyPhoneMaskForIso(iso, $phoneInput);
                        })
                        .catch(function() { /* sessizce geç */ });
                }
            });

            // Sayfa yüklendiğinde mevcut seçili ülkeye göre mask uygula —
            // TEK fetch, sonra tüm input'lara toplu uygula (her biri ayrı fetch yapmasın)
            var $selects = $('select[name="phone_code"]').filter(function() {
                var iso         = $(this).find(':selected').data('iso') || '';
                var $phoneInput = $(this).closest('.input-group').find('.form-control-phone');
                return iso && $phoneInput.length;
            });

            if (!$selects.length || !_phoneMasksUrl) return;

            function applyAll() {
                $selects.each(function() {
                    var iso         = $(this).find(':selected').data('iso') || '';
                    var $phoneInput = $(this).closest('.input-group').find('.form-control-phone');
                    applyPhoneMaskForIso(iso, $phoneInput);
                });
            }

            if (_phoneMasksData) {
                applyAll();
            } else {
                fetch(_phoneMasksUrl)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        _phoneMasksData = data;
                        applyAll();
                    })
                    .catch(function() {});
            }
        }

        $(document).ready(function() { initPhoneMaskSystem(); });

        // AJAX sonrası (modal/offcanvas'ta form açılırsa) yeniden init
        $(document).on('ajax_init:complete', function() { initPhoneMaskSystem(); });
    })();

    $(".form-control-gsm")
    .inputmask({
        placeholder: "05__ ___ __ __",
        mask: '0599 999 99 99',
        autoUnmask : true
    })
    .attr("inputmode", "tel")
    .attr("data-rule-minlength", 9)
    .attr("data-msg-minlength", "Telefon numaranız en az 11 karakter içermelidir");

    // ── Postcode / ZIP ────────────────────────────────────────────────────────
    // site_config.postcodes (postcodes.json'dan) regex pattern'larını kullanır.
    // ENABLE_POSTCODE_VALIDATION aktifse PHP postcodes'u site_config'e ekler.
    // WooCommerce billing/shipping: ülke değişince otomatik güncellenir.

    function apply_postcode_mask($el, country) {
        if (!$el.length) return;
        country = (country || 'default').toUpperCase();

        // Eski mask + validation attr'larını kaldır
        try { $el.inputmask('remove'); } catch(e) {}
        $el.removeAttr('data-rule-pattern data-msg-pattern data-rule-minlength data-msg-minlength');

        // site_config.postcodes'dan regex al
        var postcodes = (window.site_config && site_config.postcodes) ? site_config.postcodes : {};
        var regex = postcodes[country] || '';

        if (regex) {
            // ^ ve $ sınır karakterlerini kaldır — inputmask kendi ekliyor
            var clean_regex = regex.replace(/^\^/, '').replace(/\$$/, '');
            $el.inputmask({ regex: clean_regex, jitMasking: true, autoUnmask: true, showMaskOnHover: false })
               .attr('inputmode', 'text')
               // jquery-validation için pattern rule — orijinal regex (^ $ dahil) kullan
               .attr('data-rule-pattern', regex)
               .attr('data-msg-pattern', country === 'DEFAULT'
                    ? 'Please enter a valid postcode.'
                    : 'Please enter a valid postcode for ' + country + '.');
        } else {
            // Fallback: basit alfanümerik, min 2 max 10 karakter
            $el.inputmask({ regex: '[A-Za-z0-9 \\-]{2,10}', jitMasking: true, autoUnmask: true })
               .attr('inputmode', 'text')
               .attr('data-rule-minlength', 2)
               .attr('data-msg-minlength', 'Please enter a valid postcode.');
        }

        // Validation güncellendiğinden form validator'ı temizle — sonraki submit'te yeniden okur
        var $form = $el.closest('.form-validate');
        if ($form.length && $form.data('validator')) {
            $el.removeClass('is-invalid is-valid');
            $form.data('validator').resetForm();
        }
    }

    // İlk uygulama — .form-control-postal-code
    $('.form-control-postal-code').each(function () {
        var $el     = $(this);
        var country = $el.data('postcode-country') || '';
        if (!country) {
            var $country = $el.closest('form').find('[name="billing_country"],[name="shipping_country"],[name="country"]').first();
            country = $country.val() || 'default';
        }
        apply_postcode_mask($el, country);
    });

    // WC native #billing_postcode / #shipping_postcode
    $('#billing_postcode, #shipping_postcode').not('.form-control-postal-code').each(function () {
        var $country = $(this).closest('form').find('#billing_country, #shipping_country').first();
        apply_postcode_mask($(this), $country.val() || 'default');
    });

    // WC checkout / edit-address: ülke değişince WC formu AJAX ile yeniden render eder
    // updated_checkout event'inde maskeleri yeniden uygula
    $(document.body).on('updated_checkout wc_fragment_refresh', function () {
        $('#billing_postcode, #shipping_postcode').not('.form-control-postal-code').each(function () {
            var $country = $(this).closest('form').find('#billing_country, #shipping_country').first();
            apply_postcode_mask($(this), $country.val() || 'default');
        });
    });

    // Ülke değişince güncelle
    $(document).on('change', '#billing_country, #shipping_country, [name="billing_country"], [name="shipping_country"]', function () {
        var country   = $(this).val() || 'default';
        var $postcode = $(this).closest('form').find('.form-control-postal-code, #billing_postcode, #shipping_postcode');
        $postcode.each(function () { apply_postcode_mask($(this), country); });

        // jquery-validation rule'unu da güncelle
        $postcode.each(function () {
            var $el   = $(this);
            var $form = $el.closest('.form-validate');
            if (!$form.length || !$form.data('validator')) return;

            var fieldName = $el.attr('name');
            if (!fieldName) return;

            var postcodes = (window.site_config && site_config.postcodes) ? site_config.postcodes : {};
            var regex     = postcodes[country.toUpperCase()] || '';

            var validator = $form.data('validator');

            // Mevcut rule'u temizle
            delete validator.settings.rules[fieldName];

            if (regex) {
                // addMethod yerine rules() API'si ile dinamik ekle
                $.validator.addClassRules && null; // no-op
                validator.settings.rules[fieldName] = {
                    pattern: new RegExp(regex),
                    messages: {
                        pattern: 'Please enter a valid postcode for ' + country.toUpperCase() + '.'
                    }
                };
            }

            // State'i sıfırla — eski is-invalid vs temizle
            $el.removeClass('is-invalid is-valid');
            validator.resetForm();
        });
    });
    
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