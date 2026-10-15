/**
 * simplyCountdown.js init
 *
 * Kullanım — HTML:
 *   <span class="sh-countdown"
 *         data-seconds="300"
 *         data-format="mm:ss"
 *         data-zero-callback="myZeroFunc"
 *         data-tick-callback="myTickFunc">
 *   </span>
 *
 * data-seconds   : toplam süre (saniye) — OTP expiry için kullan
 * data-format    : görüntü formatı: "mm:ss" | "hh:mm:ss" | "dd hh:mm:ss"
 * data-zero-callback : süre bitince çağrılacak global fonksiyon adı (string)
 * data-tick-callback : her saniyede çağrılacak global fonksiyon adı (string)
 *
 * JS ile programatik başlatma:
 *   shCountdown(element, seconds, onZero, onTick);
 *
 * @version 1.0.0
 */

function init_simplycountdown($scope) {
    var $scope = $scope || $('body');
    $scope.find('.sh-countdown').not('.sh-countdown-init').each(function(){
        var $el      = $(this);
        var seconds  = parseInt($el.data('seconds')) || 60;
        var format   = $el.data('format') || 'mm:ss';
        var zeroCb   = $el.data('zero-callback') || null;
        var tickCb   = $el.data('tick-callback')  || null;

        $el.addClass('sh-countdown-init');

        shCountdownStart($el[0], seconds, zeroCb ? function(){ if(typeof window[zeroCb] === 'function') window[zeroCb]($el); } : null, tickCb ? function(r){ if(typeof window[tickCb] === 'function') window[tickCb](r, $el); } : null, format);
    });
}

/**
 * Programatik countdown başlat.
 *
 * @param {Element|string} el      DOM element veya selector
 * @param {number}         seconds Toplam süre (saniye)
 * @param {Function}       onZero  Süre bitince callback
 * @param {Function}       onTick  Her saniyede callback(remainingSeconds)
 * @param {string}         format  'mm:ss' | 'hh:mm:ss' | 'dd hh:mm:ss'
 * @returns {{ stop: Function, remaining: Function }}
 */
function shCountdownStart(el, seconds, onZero, onTick, format) {
    format = format || 'mm:ss';
    var $el     = typeof el === 'string' ? document.querySelector(el) : el;
    var remaining = parseInt(seconds) || 0;
    var timer     = null;

    function fmt(r) {
        var d  = Math.floor(r / 86400);
        var h  = Math.floor((r % 86400) / 3600);
        var m  = Math.floor((r % 3600) / 60);
        var s  = r % 60;
        var pad = function(n){ return n < 10 ? '0' + n : '' + n; };

        if (format === 'dd hh:mm:ss') return d + 'd ' + pad(h) + ':' + pad(m) + ':' + pad(s);
        if (format === 'hh:mm:ss')    return pad(h) + ':' + pad(m) + ':' + pad(s);
        return pad(m) + ':' + pad(s);
    }

    function tick() {
        if ($el) $el.textContent = fmt(remaining);
        if (onTick) onTick(remaining);
        if (remaining <= 0) {
            clearInterval(timer);
            if (onZero) onZero();
            return;
        }
        remaining--;
    }

    tick();
    timer = setInterval(tick, 1000);

    return {
        stop:      function(){ clearInterval(timer); },
        remaining: function(){ return remaining; },
    };
}
