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
        // Lenis aktifse onun scroll degerini kullan, yoksa native
        scrollPos = (window.lenis ? window.lenis.scroll : null) || window.pageYOffset;

        for (var t = 0; spsElements[t]; ++t) {
            var element = spsElements[t];
            var attrValue = element.getAttribute(currentOffsetTag);
            var offsetY = (attrValue !== null && attrValue !== "") ? parseInt(attrValue, 10) : currentScrollOffsetY;
            var hasClassAbove = element.classList.contains(currentClassAbove);

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
                window.requestAnimationFrame(function() {
                    applyClassChanges(changes);
                });
            } else {
                isTicking = false;
            }
        }
    };

    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            publicAPI.init();
        }, 50);
    });

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
    }, 16), {
        passive: true
    });

    return publicAPI;
})(document, window);
