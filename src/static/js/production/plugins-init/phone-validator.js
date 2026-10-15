/**
 * phone-validator init
 *
 * PAE "init" key'i bu fonksiyonu çağırır: init_phone_validator()
 * libphonenumber-js bundle'ı (window.libphonenumber) yüklendikten sonra çalışır.
 *
 * Kullanım:
 *   - .form-control-phone class'lı input'ları otomatik init eder
 *   - Dinamik scope için: $(document).trigger('sh:phone:reinit', [scope])
 */
function init_phone_validator() {
    'use strict';

    var lib = window.libphonenumber || (typeof libphonenumber !== 'undefined' ? libphonenumber : null);
    if (!lib) return;

    // ── Yardımcılar ───────────────────────────────────────────────────────────

    function getIso($input) {
        // 1. data-country-iso attr
        var iso = $input.data('country-iso') || $input.attr('data-country-iso');
        if (iso) return iso.toUpperCase();

        // 2. input-group içindeki phone_code select → data-iso
        var $sel = $input.closest('.input-group').find('select[name="phone_code"]');
        if ($sel.length) {
            iso = $sel.find('option:selected').data('iso');
            if (iso) return iso.toUpperCase();
        }

        return null;
    }

    function getPrefix($input) {
        var $group  = $input.closest('.input-group');
        var $sel    = $group.find('select[name="phone_code"]');
        var $txt    = $group.find('input[name="phone_code"]');
        if ($sel.length) return ($sel.val() || '').trim();
        if ($txt.length) return ($txt.val() || '').trim();
        return '';
    }

    function clearFeedback($input) {
        $input.removeClass('is-invalid is-valid');
        $input.closest('.mb-3').find('.sh-phone-feedback').hide().text('');
        if ($input[0] && $input[0].setCustomValidity) $input[0].setCustomValidity('');
    }

    function showError($input, msg) {
        $input.addClass('is-invalid').removeClass('is-valid');
        var $fb = $input.closest('.mb-3').find('.sh-phone-feedback');
        if (!$fb.length) {
            $fb = $('<div class="sh-phone-feedback invalid-feedback d-block" style="font-size:12px"></div>');
            $input.closest('.input-group, .mb-3').after($fb);
        }
        $fb.text(msg).show();
        if ($input[0] && $input[0].setCustomValidity) $input[0].setCustomValidity(msg);
    }

    function showSuccess($input, e164) {
        $input.addClass('is-valid').removeClass('is-invalid');
        $input.closest('.mb-3').find('.sh-phone-feedback').hide().text('');
        if ($input[0] && $input[0].setCustomValidity) $input[0].setCustomValidity('');
        if (e164) $input.attr('data-e164', e164);
    }

    function doValidate($input) {
        var number = $input.val().trim();
        if (!number) { clearFeedback($input); return; }

        // Mask karakterlerini temizle — sadece rakamları al
        var digitsOnly = number.replace(/\D/g, '');
        if (!digitsOnly) { clearFeedback($input); return; }

        var iso    = getIso($input);
        var prefix = getPrefix($input);

        // prefix varsa ve numara + ile başlamıyorsa birleştir
        var full = (prefix && number.indexOf('+') !== 0)
            ? prefix + digitsOnly.replace(/^0+/, '')
            : number;

        var result = { valid: false, message: 'Invalid phone number.', e164: '' };

        try {
            var parsed = lib.parsePhoneNumber(full, iso || undefined);
            if (parsed && (iso ? lib.isValidPhoneNumber(full, iso) : parsed.isValid())) {
                result = { valid: true, message: '', e164: parsed.format('E.164') };
            } else {
                // Uzunluk hatası
                var isPoss = iso ? lib.isPossiblePhoneNumber(full, iso) : false;
                result.message = isPoss ? 'Invalid phone number.' : 'Phone number is too short or too long.';
            }
        } catch (e) {
            if (e.message) {
                if (e.message.indexOf('TOO_SHORT')     !== -1) result.message = 'Phone number is too short.';
                else if (e.message.indexOf('TOO_LONG') !== -1) result.message = 'Phone number is too long.';
                else if (e.message.indexOf('NOT_A_NUMBER') !== -1) result.message = 'Please enter a valid phone number.';
            }
        }

        if (result.valid) {
            showSuccess($input, result.e164);
        } else {
            showError($input, result.message);
        }
    }

    // ── Input init ────────────────────────────────────────────────────────────

    function bindInput($input) {
        if ($input.data('sh-pv')) return; // çift init engelle
        if ($input.attr('data-pv-skip')) return; // skip flag varsa init etme
        $input.data('sh-pv', 1);

        // Ülke değişince feedback temizle + yeniden doğrula
        $input.closest('.input-group').on('change.sh-pv', 'select[name="phone_code"]', function () {
            clearFeedback($input);
            if ($input.val().trim()) doValidate($input);
        });

        $input.on('blur.sh-pv', function () { doValidate($input); });
        $input.on('input.sh-pv', function () {
            if ($input.hasClass('is-invalid')) clearFeedback($input);
        });
    }

    // ── Public yardımcı (dışarıdan erişim) ───────────────────────────────────
    window.shPhoneValidator = {
        validate: function (number, iso) {
            try {
                var parsed = lib.parsePhoneNumber(number, iso || undefined);
                if (parsed && (iso ? lib.isValidPhoneNumber(number, iso) : parsed.isValid())) {
                    return { valid: true, e164: parsed.format('E.164'), national: parsed.formatNational(), international: parsed.formatInternational(), type: parsed.getType() || '' };
                }
            } catch (e) { /* fall through */ }
            return { valid: false, e164: '', national: '', international: '', type: '' };
        },
        reinit: function (scope) {
            $(scope || document).find('.form-control-phone').each(function () { bindInput($(this)); });
        }
    };

    // ── İlk init ──────────────────────────────────────────────────────────────
    $(document).find('.form-control-phone').each(function () { bindInput($(this)); });

    // Dinamik scope desteği
    $(document).on('sh:phone:reinit', function (e, scope) {
        window.shPhoneValidator.reinit(scope);
    });
}
