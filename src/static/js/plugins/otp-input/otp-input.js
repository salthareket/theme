/**
 * OTP Input — sh_otp_input
 *
 * Vanilla JS/jQuery OTP kutucuk bileşeni.
 * Her kutucuk 1 rakam. Otomatik ilerleme, backspace, paste desteği.
 * Dolu olunca hidden input'a değer yazar.
 *
 * HTML yapısı (partials/otp-input.twig tarafından üretilir):
 *   <div class="otp-input" id="{id}" data-length="{n}">
 *     <input class="otp-input-digit" data-index="0" .../>
 *     ...
 *   </div>
 *   <input type="hidden" id="{id}-value" name="otp_code"/>
 *
 * @version 1.0.0
 */

function sh_otp_input(wrap) {
    var $wrap = (wrap instanceof jQuery) ? wrap : $(wrap);
    if ($wrap.hasClass('otp-input-init')) return;
    $wrap.addClass('otp-input-init');

    var wrapId  = $wrap.attr('id') || ('otp-' + Math.random().toString(36).slice(2, 7));
    var $hidden = $('#' + wrapId + '-value');
    var $digits = $wrap.find('.otp-input-digit');
    var length  = parseInt($wrap.data('length')) || $digits.length;

    // ── Yardımcılar ──────────────────────────────────────────────────────

    function getValue() {
        return $digits.map(function(){ return $(this).val() || ''; }).get().join('');
    }

    function syncHidden() {
        var val = getValue();
        $hidden.val(val).trigger('change');
        // Tüm kutucuklar dolunca complete event tetikle + submit butonuna focus
        if (val.length === length) {
            $wrap.trigger('otp:complete', [val]);
            // Submit butonuna geç — kullanıcı Enter ile submit edebilsin
            var $submitBtn = $wrap.closest('form').find('[type="submit"]').first();
            if ($submitBtn.length) setTimeout(function(){ $submitBtn.focus(); }, 50);
        }
    }

    function focusAt(idx) {
        var $t = $digits.eq(idx);
        if ($t.length) {
            $t[0].focus();
            setTimeout(function() {
                if ($t[0].setSelectionRange) $t[0].setSelectionRange(1, 1);
            }, 0);
        }
    }

    function clearAll() {
        $digits.val('');
        syncHidden();
        focusAt(0);
    }

    // ── Event handler'lar ────────────────────────────────────────────────

    $digits.on('keydown', function(e) {
        var $this = $(this);
        var idx   = parseInt($this.data('index'));
        var key   = e.key;

        if (key === 'Backspace') {
            e.preventDefault();
            if ($this.val()) {
                $this.val('');
                syncHidden();
            } else if (idx > 0) {
                $digits.eq(idx - 1).val('').trigger('focus');
                syncHidden();
            }
            return;
        }

        if (key === 'Delete') {
            e.preventDefault();
            $this.val('');
            syncHidden();
            return;
        }

        if (key === 'ArrowLeft'  && idx > 0)          { e.preventDefault(); focusAt(idx - 1); return; }
        if (key === 'ArrowRight' && idx < length - 1) { e.preventDefault(); focusAt(idx + 1); return; }

        if (key === 'Tab' || key === 'Enter') return;

        // Sadece rakam
        if (!/^[0-9]$/.test(key)) { e.preventDefault(); }
    });

    $digits.on('input', function() {
        var $this = $(this);
        var idx   = parseInt($this.data('index'));
        var val   = $this.val().replace(/\D/g, '').slice(-1);
        $this.val(val);
        syncHidden();
        if (val && idx < length - 1) {
            focusAt(idx + 1);
        }
    });

    $digits.on('paste', function(e) {
        e.preventDefault();
        var idx    = parseInt($(this).data('index'));
        var text   = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        var nums   = text.replace(/\D/g, '').split('');

        nums.forEach(function(d, i) {
            var pos = idx + i;
            if (pos < length) $digits.eq(pos).val(d);
        });

        syncHidden();
        var lastPos = Math.min(idx + nums.length, length) - 1;
        if (lastPos >= 0) focusAt(lastPos);
    });

    $digits.on('focus', function() {
        var el = this;
        setTimeout(function(){ if (el.select) el.select(); }, 0);
    });

    // Public API — $wrap üzerinden erişilebilir
    $wrap.data('otpInput', {
        getValue:  getValue,
        clearAll:  clearAll,
        focusFirst: function(){ focusAt(0); },
        sync:      syncHidden,
    });
}
