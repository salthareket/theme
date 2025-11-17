var root = {

    options: {},

    lang: document.getElementsByTagName("html")[0].getAttribute("lang"),

    host: location.protocol + "//" + window.location.hostname + (window.location.port > 80 ? ":" + window.location.port + "/" : "/") + (!IsBlank(window.location.pathname) ? window.location.pathname.split("/")[1] + "/" : ""),

    get_host: function(){
      return location.protocol + "//" + window.location.hostname + 
        (window.location.port && window.location.port != 80 && window.location.port != 443 ? ":" + window.location.port : "") + "/";
    },

    hash: window.location.hash,

    Date : Date.now,

    is_home: function() {
        return this.classes.hasClass(document.body, "home")
    },

    logged: function() {
        return this.classes.hasClass(document.body, "logged")
    },

    on_resize: {},

    classes: {
        addClass: function($obj, $class) {
            if(!IsBlank($class)){
                $obj.classList.add($class);
            }
        },
        removeClass: function($obj, $class) {
            if(!IsBlank($class)){
                $obj.classList.remove($class);
            }
        },
        toggleClass: function($obj, $class) {
            if(!IsBlank($class)){
                $obj.classList.toggle($class);
            }
        },
        hasClass: function($obj, $class) {
            return $obj.classList.contains($class);
        }
    },

    css_vars: [],

    get_css_vars: function() {
        setTimeout(function() {
            var arr = {};
            var obj = getComputedStyle(document.documentElement);
            /*arr['header-height-xxxl'] = parseFloat(obj.getPropertyValue('--header-height-xxxl').trim());
            arr['header-height-xxl'] = parseFloat(obj.getPropertyValue('--header-height-xxl').trim());
            arr['header-height-xl'] = parseFloat(obj.getPropertyValue('--header-height-xl').trim());
            arr['header-height-lg'] = parseFloat(obj.getPropertyValue('--header-height-lg').trim());
            arr['header-height-md'] = parseFloat(obj.getPropertyValue('--header-height-md').trim());
            arr['header-height-sm'] = parseFloat(obj.getPropertyValue('--header-height-sm').trim());
            arr['header-height-xs'] = parseFloat(obj.getPropertyValue('--header-height-xs').trim());*/

            arr['header-height'] = parseFloat(obj.getPropertyValue('--header-height').trim());

            /*arr['header-height-affix'] = parseFloat(obj.getPropertyValue('--header-height-affix').trim());
            arr['header-height-xxxl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xxxl-affix').trim());
            arr['header-height-xxl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xxl-affix').trim());
            arr['header-height-xl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xl-affix').trim());
            arr['header-height-lg-affix'] = parseFloat(obj.getPropertyValue('--header-height-lg-affix').trim());
            arr['header-height-md-affix'] = parseFloat(obj.getPropertyValue('--header-height-md-affix').trim());
            arr['header-height-sm-affix'] = parseFloat(obj.getPropertyValue('--header-height-sm-affix').trim());
            arr['header-height-xs-affix'] = parseFloat(obj.getPropertyValue('--header-height-xs-affix').trim());*/

            arr['header-height-affix'] = parseFloat(obj.getPropertyValue('--header-height-affix').trim());

            arr['hero-height-xxxl'] = parseFloat(obj.getPropertyValue('--hero-height-xxxl').trim());
            arr['hero-height-xxl'] = parseFloat(obj.getPropertyValue('--hero-height-xxl').trim());
            arr['hero-height-xl'] = parseFloat(obj.getPropertyValue('--hero-height-xl').trim());
            arr['hero-height-lg'] = parseFloat(obj.getPropertyValue('--hero-height-lg').trim());
            arr['hero-height-md'] = parseFloat(obj.getPropertyValue('--hero-height-md').trim());
            arr['hero-height-sm'] = parseFloat(obj.getPropertyValue('--hero-height-sm').trim());
            arr['hero-height-xs'] = parseFloat(obj.getPropertyValue('--hero-height-xs').trim());
            root.css_vars = arr;
        }, 50);
    },

    get_css_var: function($var) {
        var obj = getComputedStyle(document.documentElement);
        return parseFloat(obj.getPropertyValue('--' + $var).trim());
    },

    throttle: function(fn, wait) {
        let timeout = null;
        return function() {
            const context = this, args = arguments;
            if (!timeout) {
                timeout = setTimeout(function() {
                    timeout = null;
                    fn.apply(context, args);
                }, wait);
            }
        };
    },

    browser: {

        device: function() {
            BrowserDetect.init();
            root.classes.addClass(document.getElementsByTagName("html")[0], BrowserDetect.browser);
            root.classes.addClass(document.getElementsByTagName("html")[0], isMobile.any());
        },

        disable_contextmenu: function() {
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            }, false);
        },

        size_compare : function(a,b){
            var sizes = ["xs", "sm", "md", "lg", "xl", "xxl", "xxxl"];
            return (sizes.indexOf(a) > sizes.indexOf(b));
        },

        size: function() {
            var bodyObj, bodyClass;
            bodyObj = window.document.documentElement; //document.body;
            bodyClass = "xxxl";
            bodyClass = root.classes.hasClass(bodyObj, "size-xxxl") ? "xxxl" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-xxl") ? "xxl" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-xl") ? "xl" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-lg") ? "lg" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-md") ? "md" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-sm") ? "sm" : bodyClass;
            bodyClass = root.classes.hasClass(bodyObj, "size-xs") ? "xs" : bodyClass;
            return bodyClass;
        },

        closeOffcanvas: function($breakpoint){
            let breakpoints = ["xs", "sm", "md", "lg", "xl", "xxl", "xxxl"];
            let offcanvas = $(".offcanvas.show");
            if (!$offcanvas.length) return;
            breakpoints.forEach(function(bp){
                if (bp === $breakpoint) return;
                if ($offcanvas.hasClass(`offcanvas-${bp}`)) {
                    let bsOffcanvas = bootstrap.Offcanvas.getInstance($offcanvas[0]);
                    if (bsOffcanvas) {
                        bsOffcanvas.hide();
                    } else {
                        $offcanvas.removeClass("show").hide(); // fallback
                    }
                }
            });
        },

    },

    ui: {

        reloadImage: function(img) {
            if (IsBlank(img.attr("data-src"))) {
                img.attr("data-src", img.attr("src")).addClass("fade");
            }
            var src = img.attr("data-src");
            var new_src = src + "?rnd=" + Math.random();
            img.removeClass("in").attr("src", "").attr("src", new_src).on("load", function() {
                $(this).addClass("in");
            });
        },

        viewport: function() {
            const checkViewportStatus = function() {
                $('.viewport').each(function() {
                    var obj = $(this);
                    var posY = obj.offset().top - $(window).scrollTop();
                    if(posY < 0){
                        obj.addClass('out-viewport');
                    }else{
                        obj.removeClass('out-viewport');
                    }

                    if (obj.is(":in-viewport")) {
                        obj.addClass('in-viewport');
                        if(!IsBlank(obj.data("viewport-func"))){
                            window[obj.data("viewport-func")](obj);
                            obj.data("viewport-func", "");
                        }
                    } else {
                        obj.removeClass('in-viewport');
                    }
                });
            };
            $(window).on('scroll resize', root.throttle(checkViewportStatus, 100));
            setTimeout(checkViewportStatus, 150);
        },

        navigation: function() {
            switch (root.options.navigation) {
                case "full":
                    var obj = $('#navbar_container');
                    break;
                case "":
                default:
                    var obj = $('#navigation');
                    break;
            }
            if (obj.find(".link-onepage-home").length > 0) {
                obj.find(".link-onepage-home a").attr("href", "#home");
            }
            obj
                .on('show.bs.collapse', function(e) {
                    if ($("header#header").hasClass("navbar-fixed-top")) {
                        $('.navbar-collapse')[0].body_position = $("html,body").scrollTop();
                        var active_links = [];
                        obj.find("li.active a").each(function(index) {
                            active_links[index] = $(this).attr("href");
                        })
                        $('.navbar-collapse')[0].active_links = active_links;
                    }
                    $("body").addClass("mobile-menu-open");
                })
                .on('shown.bs.collapse', function(e) {})
                .on('hide.bs.collapse', function(e) {
                    $("body").addClass("mobile-menu-closing");
                    if ($("header#header").hasClass("navbar-fixed-top")) {
                        $("html, body").scrollTop($('.navbar-collapse')[0].body_position);
                    }
                })
                .on('hidden.bs.collapse', function(e) {
                    $("body").removeClass("mobile-menu-open").removeClass("mobile-menu-closing");
                });

            $(".dropdown-toggle").on("click", function() {
                if ($(this).parent().hasClass("open")) {
                    var obj = $(this);
                } else {
                    var obj = $(this).closest(".nav").find(".dropdown.open");
                }
                if (obj.length > 0) {
                    obj.removeClass("open");
                    obj.find("a").removeClass("has-submenu highlighted");
                    obj.find("ul.dropdown-menu").attr("aria-hidden", true).attr("aria-expanded", false).css("display", "none");
                }
            });

            /*$('body.home').scrollspy({
                target: '#navigation',
                offset: 110
            });
            $('body.home').on('activate.bs.scrollspy', function(e) {
                if (!$("body").hasClass("mobile-menu-open")) {
                    root.hash = $(e.target).find("a").attr("href");
                    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                }
            });

            $(window).on("click.Bst", function(e) {
                if ($('header#header').has(e.target).length == 0 && !$('header#header').is(e.target)) {
                    if ($(".navbar-collapse").hasClass("in")) {
                        $(".navbar-collapse").collapse("hide");
                    }
                }
            });*/
        },

        offset_top: function() {
            var size = root.browser.size();
            var headerHeight = root.get_css_var("header-height");
            var headerHeightAffix = root.get_css_var("header-height-affix");
            if ($("header#header").hasClass("affix") || $("header#header").hasClass("fixed-top")) {
                return headerHeight;
            } else {
                return headerHeightAffix;
            }
        },

        scroll_dedect: function(up, down) {
            var scrollPos = window.pageYOffset || document.documentElement.scrollTop;
            var header_hide = $("body").hasClass("header-hide-on-scroll");
            $("body").addClass("scroll-dedect");

            var sticky_tops = document.querySelectorAll('.sticky-top');
            var sticky_bottoms = document.querySelectorAll('.sticky-bottom'); // <--- sticky bottom

            window.addEventListener('scroll', function() {
                var currentPos = window.pageYOffset || document.documentElement.scrollTop;

                // sticky top işlemleri
                sticky_tops.forEach(function(sticky_top) {
                    var sticky_top_style = window.getComputedStyle(sticky_top);
                    var top = parseInt(sticky_top_style.top);

                    if (sticky_top.getBoundingClientRect().top === top) {
                        sticky_top.classList.add('sticked');
                    } else {
                        sticky_top.classList.remove('sticked');
                    }
                });

                // sticky bottom işlemleri
                sticky_bottoms.forEach(function(sticky_bottom) {
                    var sticky_bottom_style = window.getComputedStyle(sticky_bottom);
                    var bottom = parseInt(sticky_bottom_style.bottom);

                    if (sticky_bottom.getBoundingClientRect().bottom === window.innerHeight - bottom) {
                        sticky_bottom.classList.add('sticked');
                    } else {
                        sticky_bottom.classList.remove('sticked');
                    }
                });

                if (currentPos <= scrollPos) {
                    $("body").removeClass("header-hide").removeClass("scroll-down").addClass("scroll-up");
                    if (!IsBlank(up)) {
                        if (typeof up === "function") {
                            up(scrollPos);
                        }
                    }
                    if (currentPos <= 0) {
                        $("body").removeClass("scroll-down").removeClass("scroll-up");
                    }
                } else {
                    $("body").removeClass("scroll-up").addClass("scroll-down");
                    if (currentPos > $("header#header").height()) {
                        if (header_hide) {
                            $("body").addClass("header-hide");
                        }
                        $(window).trigger("resize");
                    }
                    if (!IsBlank(down)) {
                        if (typeof down === "function") {
                            down(scrollPos);
                        }
                    }
                }
                scrollPos = currentPos;
            });
        },

        scroll_top: function() {
            if ($('.scroll-to-top').length > 0) {
                var show = $('.scroll-to-top').data("show");
                var duration = $('.scroll-to-top').data("duration");
                $(window).scroll(function() {
                    if(show == "scroll" || show == "always"){
                        if ($(this).scrollTop() > 1) {
                            $('.scroll-to-top').addClass("show");
                        } else {
                            $('.scroll-to-top').removeClass("show");
                        }                        
                    }
                    if(show == "scroll_more"){
                        if ($(this).scrollTop() > window.innerHeight/2) {
                            $('.scroll-to-top').addClass("show");
                        } else {
                            $('.scroll-to-top').removeClass("show");
                        }                        
                    }
                });
                $('.scroll-to-top').on("click", function(e) {
                    e.preventDefault();
                    if ($(".navbar-collapse").hasClass("show")) {
                        $(".navbar-collapse").collapse("hide");
                    }
                    $("html, body").stop().animate({
                        scrollTop: 0
                    }, duration);
                });
            }
        },

        scroll_to: function($hash, $animate, $outside, $callback) {

            if (typeof $hash === "object") {
                if (IsBlank($hash.attr("id"))) {
                    $hash_id = generateCode(5);
                    $hash.attr("id", $hash_id);
                }
                $hash = "#" + $hash.attr("id")
            }

            if (!IsBlank($hash) && typeof $hash !== "undefined") {
                var _history = true;

                //if hash is bs toggle
                if ($($hash).hasClass("tab-pane")) {
                    _history = false;
                }

                $outside = IsBlank($outside) ? false : true;
                var target = $hash;
                if ($(target).length > 0) {
                    root.hash = $hash;

                    if ($(target).hasClass("card-merged") | $(target).hasClass("collapse")) {
                        root.hash = "";
                    }

                    if ($(target).not(".show").hasClass("collapse")) {
                        $(target).collapse("show");
                    }

                    if ($(target).not(".active").hasClass("tab-pane")) {
                        $("a[href='" + target + "']").trigger("click");
                    }

                    var posY = $(target).offset().top;

                    var size = root.browser.size();
                    var headerHeight = root.get_css_var("header-height");
                    var headerHeightAffix = root.get_css_var("header-height-affix");

                    if($(".offcanvas.show").length > 0){
                         $(".offcanvas.show").offcanvas("hide");
                    }
                    if ($(".navbar-collapse").hasClass("show")) {
                        $(".navbar-collapse")
                            .collapse("hide")
                            .on('hidden.bs.collapse', function() {
                                var posY = $(target_section).offset().top;
                                if ($("header#header").hasClass("affix") || $("header#header").hasClass("navbar-fixed-top")) {
                                    posY -= $("header#header").height()
                                }
                                if ($("stick-top.sticky").length > 0) {
                                    $("stick-top.sticky").each(function() {
                                        posY -= $(this).height()
                                    });
                                }
                                $("html, body").stop().animate({
                                    scrollTop: posY
                                }, 600, function() {
                                    if (_history) {
                                        root.hash = target;
                                    }
                                });
                            });
                        return false;
                    }

                    if($(target).hasClass("offcanvas")){
                       $(target).offcanvas("show");
                       if($("[href='"+root.hash+"']").length > 0){
                           posY = $("[href='"+root.hash+"']").offset().top;
                       }
                    }

                    if (($("header#header").hasClass("affix") || $("header#header").hasClass("fixed-top")) ) {
                        if (posY >= headerHeight) {
                            posY -= headerHeight;
                        } else {
                            posY -= headerHeightAffix;
                        }
                    }
                    if(root.browser.size_compare(size,"md")){
                        if($('html').offset().top > posY){//hedef buyukse yukarı, kucukse asağı
                            //yukari
                            if($("header#header").hasClass("fixed-top")){
                                 posY -= headerHeightAffix;
                            }
                        }else{
                            //asagi
                        }
                    }else{
                        posY -= headerHeightAffix;
                    }

                    if ($(".stick-top.sticky").length > 0) {
                        posY -= $(".stick-top.sticky").outerHeight(true);
                    }

                    if ($animate) {
                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 600, function() {
                            if (!$outside && !IsBlank(root.hash)) {
                                if (_history) {
                                //    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                                }
                            }
                            if(!IsBlank($callback)){
                                $callback();
                            }
                        });
                    } else {

                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 0, function() {

                            if (!$outside && !IsBlank(root.hash)) {
                                if (_history) {
                                //    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                                }
                            }
                            if(!IsBlank($callback)){
                                $callback();
                            }
                        });
                    }
                    return false;
                }
            }
        },

        scroll_to_actions: function() {
            $(document).on("click", "a:not([data-bs-toggle]):not([data-ajax-method]):not(.scroll-to-init)", function(e){
                var btn = $(this);
                btn.addClass("scroll-to-init");
                var target = this.hash;
                if(btn.attr("href").indexOf("#") > -1){
                    var outside = false;
                    if(window.location.href != btn.attr("href").split("#")[0]){
                       outside = true;
                       return;
                    }
                    if($(target).length == 0){
                        var events = $.data(btn.get(0), 'events');
                        if (events == null || typeof events === "undefined") {
                            e.preventDefault();
                        }
                    }else{
                        e.preventDefault();
                        var offcanvas = $(this).closest(".offcanvas");
                        if(offcanvas){
                           offcanvas.offcanvas("hide");
                        }
                        root.ui.scroll_to(target, true);
                    }                    
                }
           });
        },

        prev_next: function() {
            $(document).keydown(function(e) {

                // Input ve textarea focus kontrolü
                if ($(e.target).is('input, textarea')) {
                    return;
                }

                var prev = $("link[rel=prev]").attr("href");
                var next = $("link[rel=next]").attr("href");
                if (prev || next) {
                    switch (e.which) {
                        case 37: // left
                            if (!IsBlank(prev)) {
                                if (prev.indexOf("#") < 0) {
                                    pageLoadUrl(prev);
                                } else {
                                    window.location.href = prev;
                                }
                            }
                            break;
                        case 38: // up
                            break;
                        case 39: // right
                            if (!IsBlank(next)) {
                                if (next.indexOf("#") < 0) {
                                    pageLoadUrl(next);
                                } else {
                                    window.location.href = next;
                                }
                            }
                            break;
                        case 40: // down
                            break;
                        default:
                            return;
                    }
                    e.preventDefault();
                }
            });
        },

        resizing : function(){
            let timer;
            function start() { $("body").addClass("resizing"); }
            function stop() { $("body").removeClass("resizing"); }
            $(window).on("resize", function(){
                start();
                clearTimeout(timer);
                timer = setTimeout(stop, 250);
            });
        },


        tree_menu : function(){
            var obj = $(".nav-tree");
            obj.each(function(){
                var menu = $(this);
                var single_parent = $(this).data("single-parent");
                if(single_parent){
                    $(this).find("a + ul").on('show.bs.collapse', function (e) {
                            var parent = $(e.target).parents();
                            menu.find("ul.collapse.show").not(parent).collapse('hide');
                        //}
                    });
                }
            });
        }

    },

    form: {
        init: function() {
            /*button text on form submit*/
            $('.btn-submit').attr("disabled", false).removeClass("processing");
        }
    },

    card: function() {
        //add active class to each opened panel item in panel-group
        $(document).on("show.bs.collapse", ".card-collapse", function(e) {
            var card = $(e.target).closest(".card")
            card.addClass("active");
            var cardCollapse = $(e.target);
            var parent = card.find("[href='#" + cardCollapse.attr("id") + "']").attr("data-parent");
            if (!IsBlank(parent)) {
                if (card.parent().attr("id") != parent) {
                    $(parent).find(".card-collapse").not(cardCollapse).collapse("hide");
                }
            }
        }).on("hide.bs.collapse", ".card-collapse", function(e) {
            var card = $(e.target).closest(".card")
            card.removeClass("active");
        });
    },

    responsive: {
        table: function() {
            if ($('.table.table-responsive-data').length > 0) {
                $(".table.table-responsive-data").each(function() {
                    var headers = [];
                    $(this).find("thead th").each(function(index) {
                        headers[index] = $(this).text();
                    });
                    $(this).find("tbody tr").each(function() {
                        $(this).find("td").each(function(index) {
                            $(this).attr("data-th", headers[index]);
                        })
                    });
                });
            }
        },

        tab: function() {
            if ($('.tab-collapse').length > 0) {
                $('.tab-collapse').tabCollapse({
                        tabsClass: 'd-lg-flex d-md-none d-sm-none d-none',
                        accordionClass: 'd-lg-none d-md-block s-sm-block d-block card-merged card-collapse card-collapse-scroll'
                    })
                    .on('show-accordion.bs.tabcollapse', function(e) {
                        $(window).trigger("resize");
                        var active_tab = $(e.target).find("li.active").find("a").attr("href");
                        this.activate_tab = active_tab + "-collapse";
                    })
                    .on('shown-accordion.bs.tabcollapse', function(e) {
                        $(this.activate_tab).closest(".card").addClass("active");
                    })
                    .on('show-tabs.bs.tabcollapse', function(e) {

                    })
                    .on('shown-tabs.bs.tabcollapse', function(e) {

                    })
                    .on('shown.bs.tab', function(e) {
                        /*////debugJS(root["map-google"]);
                        if(!IsBlank(root["map-google"])){
                        	eval(root["map-google"])();
                        }*/
                    })


                $(document).on("shown.bs.collapse", ".card-collapse", function(e) {
                        $(e.target).closest(".card").addClass("active");
                        var posY = $(e.target).closest(".card").offset().top;
                        posY = root.ui.fixed_top() ? posY - ($("header#header").height() + 10) : posY;
                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 500);
                        //activate tab button
                        var tab_index = $(e.target).closest(".card").index();
                        var tab_id = $(e.target).attr("id").replace("-collapse", "");
                        var tab_buttons = $(e.target).closest(".card-group").prev(".tab-collapse");
                        tab_buttons.find("li").removeClass("active")
                        tab_buttons.find("li").eq(tab_index).addClass("active");
                        //activate tab content
                        var tab_content = $(e.target).closest(".card-group").next(".tab-content");
                        tab_content.find(".tab-pane").removeClass("active").removeClass("in");
                        tab_content.find(".tab-pane#" + tab_id).addClass("active").addClass("in");
                    })
                    .on("hide.bs.collapse", ".card-collapse", function(e) {
                        $(e.target).closest(".card").removeClass("active");
                        //activate tab button
                        var tab_index = $(e.target).closest(".card").index();
                        var tab_id = $(e.target).attr("id").replace("-collapse", "");
                        var tab_buttons = $(e.target).closest(".card-group").prev(".tab-collapse");
                        tab_buttons.find("li").removeClass("active")
                        tab_buttons.find("li").eq(tab_index).addClass("active");
                        //activate tab content
                        var tab_content = $(e.target).closest(".card-group").next(".tab-content");
                        tab_content.find(".tab-pane").removeClass("active").removeClass("in");
                        tab_content.find(".tab-pane#" + tab_id).addClass("active").addClass("in");
                    });
            }
        }
    },

    get_location: function($obj) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        var pos = {
                            lat: position.coords.latitude,
                            lon: position.coords.longitude
                        };
                        if ($obj.hasOwnProperty("callback")) {
                            var obj = {
                                pos: pos,
                                status : true
                            };
                            if ($obj.hasOwnProperty("map")) {
                                obj["map"] = $obj.map;
                            }
                            if ($obj.hasOwnProperty("end")) {
                                obj["end"] = $obj.end;
                            }
                            $obj.callback(obj);
                        } else {
                            return pos;
                        }
                        //infoWindow.setPosition(pos);
                        //infoWindow.setContent('Location found.');
                        //map.setCenter(pos);
                    },
                    function() {
                        //if ($obj.hasOwnProperty("map")) {
                        //    handleLocationError(true, infoWindow, $obj.map.getCenter());
                        //} else {
                        if ($obj.hasOwnProperty("callback")) {
                            $obj.callback({status: false});
                        }
                        _alert("Lütfen browser ayarlarınızdan konum erişimine izin verin.");
                        //}
                    }
                );
            } else {
                // Browser doesn't support Geolocation
                //if ($obj.hasOwnProperty("map")) {
                //    handleLocationError(false, infoWindow, map.getCenter());
                //} else {
                if ($obj.hasOwnProperty("callback")) {
                    $obj.callback(false);
                }
                _alert("Your browser dowsn't support Geolocation");
                //}
            }

            function handleLocationError(browserHasGeolocation, infoWindow, pos) {
                ////debugJS(browserHasGeolocation)
                infoWindow.setPosition(pos);
                infoWindow.setContent(browserHasGeolocation ?
                    'Error: The Geolocation service failed.' :
                    'Error: Your browser doesn\'t support geolocation.');
                /*switch(error.code) {
                                                case error.PERMISSION_DENIED:
                                                  x.innerHTML = "User denied the request for Geolocation."
                                                  break;
                                                case error.POSITION_UNAVAILABLE:
                                                  x.innerHTML = "Location information is unavailable."
                                                  break;
                                                case error.TIMEOUT:
                                                  x.innerHTML = "The request to get user location timed out."
                                                  break;
                                                case error.UNKNOWN_ERROR:
                                                  x.innerHTML = "An unknown error occurred."
                                                  break;
                                            }*/
            }
    },

    init: function(options) {
        root.options = options;
        //root.browser.enquire();
        root.browser.device();
        $(document).ready(function() {
            root.ui.navigation();
            root.get_css_vars();
            root.ui.scroll_top();
            ///root.ui.scroll_to_actions();
            root.ui.prev_next();
            root.ui.viewport();
            root.ui.resizing();
            root.form.init();
            root.ui.tree_menu();

            //root.responsive.table();
            //root.responsive.tab();

            function onResize() {
                for (var func in root.on_resize) {
                    if (root.on_resize.hasOwnProperty(func)) {
                        root.on_resize[func]();
                    }
                }
            };

        });
    }
}

