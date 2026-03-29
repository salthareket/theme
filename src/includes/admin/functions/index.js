function IsBlank(txt) {
    return txt === undefined || txt === null || txt === 'null' || txt === 'undefined' || txt === '';
}

function synchronize_child_and_parent_category($) {
    $('.categorychecklist').find('input').each(function(index, input) {
        $(input).on('change', function() {
            var $cb = $(this);
            if ($cb.is(':checked')) {
                $cb.closest('.categorychecklist').find('input').not($cb).removeAttr('checked');
                $cb.parents('li').children('label').children('input').attr('checked', 'checked');
            } else {
                $cb.parentsUntil('ul').find('input').removeAttr('checked');
            }
        });
    });
}

function updateDonutChart(el, percent, donut) {
    percent = Math.max(0, Math.min(100, Math.round(percent)));
    var deg = Math.round(360 * (percent / 100));
    var bw = donut ? '0.1em' : '0.5em';

    if (percent > 50) {
        el.find('.pie').css('clip', 'rect(auto, auto, auto, auto)');
        el.find('.right-side').css('transform', 'rotate(180deg)');
    } else {
        el.find('.pie').css('clip', 'rect(0, 1em, 1em, 0.5em)');
        el.find('.right-side').css('transform', 'rotate(0deg)');
    }

    el.find('.right-side, .left-side, .shadow').css('border-width', bw);
    el.find('.left-side').css('transform', 'rotate(' + deg + 'deg)');
}

function get_star_rating_readonly($stars, $value, $count, $star_front, $star_back) {
    $stars = parseInt($stars) || 5;
    $value = parseFloat($value) || 0;
    $star_front = $star_front || 'fas fa-star';
    $star_back  = $star_back  || 'fas fa-star';

    var countHtml = '';
    if (typeof $count !== 'undefined' && $count > 0) {
        countHtml = '<span class="count">(' + $count + ')</span>';
    }

    var className  = $value === 0 ? ' not-reviewed' : '';
    var percentage = (100 * $value) / $stars;
    var stars_back = '', stars_front = '';

    for (var i = 0; i < $stars; i++) {
        stars_back  += '<i class="' + $star_back  + '" aria-hidden="true"></i>';
        stars_front += '<i class="' + $star_front + '" aria-hidden="true"></i>';
    }

    return '<div class="star-rating star-rating-readonly' + className + '" title="' + $value + '">' +
        '<div class="back">' + stars_back +
            '<div class="front" style="width:' + percentage + '%">' + stars_front + '</div>' +
        '</div>' +
        '<div class="sum">' + $value.toFixed(1) + countHtml + '</div>' +
    '</div>';
}

function text2clipboard() {
    $('.clipboard').each(function() {
        $(this).addClass('user-select-none').wrapInner("<span class='p-1 rounded-2'/>");
    });

    $('.clipboard').on('click', function() {
        var $span = $(this).find('span');
        var text  = $(this).text();

        $span.css('background-color', '#ddd');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            var $tmp = $('<textarea>').appendTo('body').val(text).select();
            document.execCommand('copy');
            $tmp.remove();
        }

        $span.animate({ backgroundColor: 'transparent' }, 1000);
    });
}
