var ScrollPosStyler = (function(document, window) {
    "use strict";

    // Throttle: Fonksiyonun belirli bir süreden daha sık çalışmasını engeller.
    function throttle(fn, wait) {
        let timeout = null;
        return function() {
            const context = this,
                args = arguments;
            if (!timeout) {
                timeout = setTimeout(function() {
                    timeout = null;
                    fn.apply(context, args);
                }, wait);
            }
        };
    }

    var scrollPos = 0,
        isTicking = false,
        defaultScrollOffsetY = 1,
        defaultSpsClass = "sps",
        spsElements = document.getElementsByClassName(defaultSpsClass),
        defaultClassAbove = "sps--abv",
        defaultClassBelow = "sps--blw",
        defaultOffsetTag = "data-sps-offset";

    var currentSpsClass = defaultSpsClass,
        currentScrollOffsetY = defaultScrollOffsetY,
        currentClassAbove = defaultClassAbove,
        currentClassBelow = defaultClassBelow,
        currentOffsetTag = defaultOffsetTag;

    function calculateClassChanges(force) {
        var changes = [];
        // OKUMA İŞLEMİ (Read)
        scrollPos = window.pageYOffset;

        for (var t = 0; spsElements[t]; ++t) {
            var element = spsElements[t],
                // offset değeri okunur
                offsetY = element.getAttribute(currentOffsetTag) || currentScrollOffsetY,
                hasClassAbove = element.classList.contains(currentClassAbove);

            if ((force || hasClassAbove) && offsetY < scrollPos) {
                changes.push({
                    element: element,
                    addClass: currentClassBelow,
                    removeClass: currentClassAbove
                });
            } else if ((force || !hasClassAbove) && scrollPos <= offsetY) {
                changes.push({
                    element: element,
                    addClass: currentClassAbove,
                    removeClass: currentClassBelow
                });
            }
        }
        return changes;
    }

    function applyClassChanges(changes) {
        // YAZMA İŞLEMİ (Write)
        for (var e = 0; changes[e]; ++e) {
            var change = changes[e];
            change.element.classList.add(change.addClass);
            change.element.classList.remove(change.removeClass);
        }
        isTicking = false;
    }

    var publicAPI = {
        init: function(s) {
            isTicking = true;

            if (s) {
                if (s.spsClass) {
                    currentSpsClass = s.spsClass;
                    spsElements = document.getElementsByClassName(currentSpsClass);
                }
                currentScrollOffsetY = s.scrollOffsetY || defaultScrollOffsetY;
                currentClassAbove = s.classAbove || defaultClassAbove;
                currentClassBelow = s.classBelow || defaultClassBelow;
                currentOffsetTag = s.offsetTag || defaultOffsetTag;
            }

            var changes = calculateClassChanges(true);

            if (changes.length > 0) {
                // DOM değişiklikleri için requestAnimationFrame
                window.requestAnimationFrame(function() {
                    applyClassChanges(changes);
                });
            } else {
                isTicking = false;
            }
        }
    };

    document.addEventListener("DOMContentLoaded", function() {
        // Init'i 50ms geciktirerek tarayıcıya kritik işleri bitirmesi için zaman tanır.
        setTimeout(function() {
            publicAPI.init();
        }, 50);
    });

    // SCROLL OLAYI (100ms throttle ve passive: true)
    window.addEventListener("scroll", throttle(function() {
        if (!isTicking) {
            var changes = calculateClassChanges(false);

            if (changes.length > 0) {
                isTicking = true;
                window.requestAnimationFrame(function() {
                    applyClassChanges(changes);
                });
            }
        }
    }, 100), {
        passive: true
    });

    return publicAPI;
})(document, window);