var root = {

    options: {},

    lang: document.getElementsByTagName("html")[0].getAttribute("lang"),

    host: location.protocol + "//" + window.location.hostname + (window.location.port > 80 ? ":" + window.location.port + "/" : "/") + (!IsBlank(window.location.pathname) ? window.location.pathname.split("/")[1] + "/" : ""),

    hash: window.location.hash,

    Date : function now(){
        return Date.now();
    },

    is_home: function() {
        return this.classes.hasClass(document.body, "home")
    },

    logged: function() {
        return this.classes.hasClass(document.body, "logged")
    },

    check_classes: function() {},

    add_function: function($name, $func) {
        this[$name] = $func;
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
            return (' ' + $obj.className + ' ').indexOf(' ' + $class + ' ') > -1;
        }
    },

    css_vars: [],

    get_css_vars: function() {
        var arr = {};
        var obj = getComputedStyle(document.documentElement);
        arr['header-height-xxxl'] = parseFloat(obj.getPropertyValue('--header-height-xxxl').trim());
        arr['header-height-xxl'] = parseFloat(obj.getPropertyValue('--header-height-xxl').trim());
        arr['header-height-xl'] = parseFloat(obj.getPropertyValue('--header-height-xl').trim());
        arr['header-height-lg'] = parseFloat(obj.getPropertyValue('--header-height-lg').trim());
        arr['header-height-md'] = parseFloat(obj.getPropertyValue('--header-height-md').trim());
        arr['header-height-sm'] = parseFloat(obj.getPropertyValue('--header-height-sm').trim());
        arr['header-height-xs'] = parseFloat(obj.getPropertyValue('--header-height-xs').trim());

        arr['header-height-affix'] = parseFloat(obj.getPropertyValue('--header-height-affix').trim());
        arr['header-height-xxxl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xxxl-affix').trim());
        arr['header-height-xxl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xxl-affix').trim());
        arr['header-height-xl-affix'] = parseFloat(obj.getPropertyValue('--header-height-xl-affix').trim());
        arr['header-height-lg-affix'] = parseFloat(obj.getPropertyValue('--header-height-lg-affix').trim());
        arr['header-height-md-affix'] = parseFloat(obj.getPropertyValue('--header-height-md-affix').trim());
        arr['header-height-sm-affix'] = parseFloat(obj.getPropertyValue('--header-height-sm-affix').trim());
        arr['header-height-xs-affix'] = parseFloat(obj.getPropertyValue('--header-height-xs-affix').trim());

        arr['hero-height-xl'] = parseFloat(obj.getPropertyValue('--hero-height-xl').trim());
        arr['hero-height-lg'] = parseFloat(obj.getPropertyValue('--hero-height-lg').trim());
        arr['hero-height-md'] = parseFloat(obj.getPropertyValue('--hero-height-md').trim());
        arr['hero-height-sm'] = parseFloat(obj.getPropertyValue('--hero-height-sm').trim());
        arr['hero-height-xs'] = parseFloat(obj.getPropertyValue('--hero-height-xs').trim());
        root.css_vars = arr;
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

        enquire: function() {
            if (typeof enquire !== "undefined") {
                var bodyObj = window.document.documentElement; //document.body;
                /*extra small*/
                enquire.register("(max-width: 575px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-xs");
                        var size = root.browser.size();
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-xs"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-xs");
                    }
                });
                enquire.register("(min-width: 576px) and (max-width: 767px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-sm");
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-sm"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-sm");
                    }
                });
                /*medium*/
                enquire.register("(min-width: 768px) and (max-width: 991px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-md");
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-md"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-md");
                    }
                });
                /*large*/
                enquire.register("(min-width: 992px) and (max-width: 1199px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-lg");
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-lg"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-lg");
                    }
                });
                /*xlarge*/
                enquire.register("(min-width: 1200px) and (max-width: 1399px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-xl");
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-xl"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-xl");
                    }
                });
                /*xxlarge*/
                enquire.register("(min-width: 1400px) and (max-width: 1599px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-xxl");
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-xxl"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-xxl");
                    }
                });
                /*xxxlarge*/
                enquire.register("(min-width: 1600px)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "size-xxxl");
                        $(window).on("resize", function(){
                            $("header#header.fixed-bottom-start").attr("data-affix-offset", window.innerHeight - root.css_vars["header-height-xxxl"]);
                        }).trigger("resize");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "size-xxxl");
                    }
                });
                /*landscape*/
                enquire.register("screen and (orientation: landscape)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "orientation-ls");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "orientation-ls");
                    }
                });
                /*portrait*/
                enquire.register("screen and (orientation: portrait)", {
                    match: function() {
                        root.classes.addClass(bodyObj, "orientation-pr");
                    },
                    unmatch: function() {
                        root.classes.removeClass(bodyObj, "orientation-pr");
                    }
                });
            };
        }
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
           
            $(window).scroll(function() {
                $('.viewport').each(function() {
                var obj = $(this);

                    /*var tolerance = parseInt(IsBlank(obj.data("tolerance"))?0:obj.data("tolerance"));
                    var condition = tolerance == 0 ? obj.is(":in-viewport") : obj.is(":in-viewport("+tolerance+")")
                    if (condition) {
                        if(tolerance){
                            obj.attr("top",  (obj.offset().top - $(window).scrollTop()));
                            obj.removeClass('in-viewport'); 
                        }else{
                            obj.addClass('in-viewport')
                        }
                    }else{
                        if(tolerance){
                            obj.attr("top", (obj.offset().top - $(window).scrollTop()));
                            obj.addClass('in-viewport')
                        }else{
                            obj.removeClass('in-viewport');
                        }
                    }*/

                    var posY = obj.offset().top - $(window).scrollTop();
                    if(posY < 0){
                       obj.addClass('out-viewport')
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
            });
            $(window).trigger("scroll");
            $(window).scrollTop()
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
            });*/

            /*show only one sub menu*/
            if(isLoadedJS("smartmenus")){
                obj.find("a.has-submenu").on("click", function() {
                    if (!$(this).hasClass("highlighted")) {
                        //$.SmartMenus.hideAll();
                        obj.find(".navbar-nav").smartmenus('menuHideAll');
                    }
                });
            }

            $(window).on("click.Bst", function(e) {
                if ($('header#header').has(e.target).length == 0 && !$('header#header').is(e.target)) {
                    if ($(".navbar-collapse").hasClass("in")) {
                        $(".navbar-collapse").collapse("hide");
                    }
                }
            });

            obj.find(".navbar-nav-main")
                .on('show.smapi', function(e, menu) {
                    $("body").addClass("menu-dropdown-open");
                    $(".collapse-search.show").collapse("hide");
                })
                .on('hide.smapi', function(e, menu) {
                    if ($(menu).parent().data("menu-level") == 1) {
                        $("body").removeClass("menu-dropdown-open");
                    }
                });
        },

        offset_top: function() {
            var size = root.browser.size();
            var headerHeight = root.css_vars["header-height-" + size];
            var headerHeightAffix = root.css_vars["header-height-" + size +-"-affix"];
            if ($("header#header").hasClass("affix") || $("header#header").hasClass("fixed-top")) {
                return headerHeight;
            } else {
                return headerHeightAffix;
            }
        },

        scroll_dedect: function(up, down) {
            var scrollPos = 0;
            var header_hide = $("body").hasClass("header-hide-on-scroll");
            $("body").addClass("scroll-dedect");
            window.addEventListener('scroll', function() {

                // sticky elements control
                var sticky_tops = document.querySelectorAll('.sticky-top');

                sticky_tops.forEach(function(sticky_top) {
                    var sticky_top_style = window.getComputedStyle(sticky_top);
                    var top = parseInt(sticky_top_style.top);
                    
                    window.addEventListener('scroll', function() {
                        if (sticky_top.getBoundingClientRect().top === top) {
                            sticky_top.classList.add('sticked');
                        } else {
                            sticky_top.classList.remove('sticked');
                        }
                    });
                });

                if ((document.body.getBoundingClientRect()).top >= scrollPos) {
                    $("body").removeClass("header-hide").removeClass("scroll-down").addClass("scroll-up");
                    if (!IsBlank(up)) {
                        if (typeof up === "function") {
                            up(scrollPos);
                        }
                    }
                    if ((document.body.getBoundingClientRect()).top >= 0) {
                        $("body").removeClass("scroll-down").removeClass("scroll-up");
                    }
                } else {
                    $("body").removeClass("scroll-up").addClass("scroll-down");
                    if (scrollPos <= (0 - $("header#header").height())) {
                        if(header_hide){
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
                scrollPos = (document.body.getBoundingClientRect()).top;
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
                   // var collapse_offset = 0;
                    if (_history) {
                    //    history.pushState("", document.title, window.location.pathname);
                    }
                    /*var hasCollapseScroll = false;
                    var collapseScroll = $(target).closest(".card-collapse-scroll");
                    if (collapseScroll.length > 0) {
                        hasCollapseScroll = true;
                        collapseScroll.addClass("card-collapse-scroll-paused");
                        var collapse_offset = $(target).data("collapse-offset");
                    }*/


                    if ($(target).not(".show").hasClass("collapse")) {
                        $(target).collapse("show");
                    }

                    if ($(target).not(".active").hasClass("tab-pane")) {
                        //$(target).parent().find(">.tab-pane.active").removeClass("active");
                        //$("a[href='"+target+"']").closest(".nav").find(".active").removeClass("active");
                        $("a[href='" + target + "']").trigger("click");
                        //$(target).tab("show");
                    }

                    var posY = $(target).offset().top;

                    var size = root.browser.size();
                    var headerHeight = root.css_vars["header-height-" + size];
                    var headerHeightAffix = root.css_vars["header-height-" + size + "-affix"];

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
                                    //    history.pushState(target, document.title, window.location.pathname + target);
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


                    //if (($("header#header").hasClass("affix") || $("header#header").hasClass("fixed-top")) && !$("body").hasClass("scroll-dedect")) {
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
                    /*if (!IsBlank(collapse_offset)) {
                        posY -= collapse_offset;
                    }*/


                    /*window.scrollTo({
                                                      top: posY,
                                                      behavior: "smooth"
                    });*/
                    

                    if ($animate) {
                        //debugJS(posY)
                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 600, function() {
                            /*if (hasCollapseScroll) {
                                collapseScroll.removeClass("card-collapse-scroll-paused");
                            }*/
                            if (!$outside && !IsBlank(root.hash)) {
                                ////debugJS(" 1 hash="+root.hash)
                                if (_history) {
                                //    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                                }
                            }
                            if(!IsBlank($callback)){
                                $callback();
                            }
                        });
                    } else {
                        //debugJS("no animat")
                        $("html, body").stop().animate({
                            scrollTop: posY
                        }, 0, function() {
                            /*if (hasCollapseScroll) {
                                collapseScroll.removeClass("card-collapse-scroll-paused");
                            }*/
                            if (!$outside && !IsBlank(root.hash)) {
                                ////debugJS(" 2 hash="+root.hash)
                                if (_history) {
                                //    history.pushState(root.hash, document.title, window.location.pathname + root.hash);
                                }
                            }
                            if(!IsBlank($callback)){
                                //debugJS("callback")
                                $callback();
                            }
                        });
                    }
                    return false;
                }
            }
        },

        scroll_to_actions: function() {
            $("a").not("[data-bs-toggle]").not("[data-ajax-method]").not(".scroll-to-init").on("click", function(e) {
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
                            if (next.indexOf("#") < 0) {
                                pageLoadUrl(next);
                            } else {
                                window.location.href = next;
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
            timer = 0;
            function start() {
                $("body").addClass("resizing");
            }
            function stop() {
                $("body").removeClass("resizing");
            }
            $(window).resize(function(){
                if (timer) {
                    clearTimeout(timer);
                }
                timer = setTimeout(stop, 1000);
                start();
            });
        },

        tree_menu : function(){
            var obj = $(".nav-tree");
            obj.each(function(){
                var menu = $(this);
                var single_parent = $(this).data("single-parent");
                if(single_parent){
                    $(this).find("a + ul").on('show.bs.collapse', function (e) {
                        /*if(!$(e.target).parent("li").hasClass("item-child")){
                            var parent = $(e.target);
                            menu.find("ul.collapse.show").not(parent).collapse('hide');
                        }*/
                        //if(mneu.find("item-child").length > 0){
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
            if ($(".file-input").length > 0) {
                var file_input = $(".file-input").fileinput({
                    showUpload: false,
                    showCaption: true,
                    showPreview: false,
                    language: $("html").attr("lang").split("-")[0],
                    browseLabel: "Ekle",
                    browseIcon: "<i class='fa fa-plus fa-fw'></i>",
                    // removeLabel:"İptal",
                    browseClass: "btn btn-default btn-block"
                });
            };

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

    fancybox: function() {
        /*Fancybox.bind('[data-fancybox]', {
          l10n: Fancybox.l10n[root.lang],
        });*/
        Fancybox.bind('[data-fancybox]');
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
        root.browser.enquire();
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


            if(isLoadedJS("fancybox")){
                root.fancybox();
            }
            //root.responsive.table();
            //root.responsive.tab();

            function onResize() {
                for (var func in root.on_resize) {
                    root.on_resize[func]();
                }
                if (!IsBlank(root.hash)) {
                    //root.ui.scroll_to(root.hash, false);
                }
            };

            /*var hash = window.location.hash;
            if (!IsBlank(hash)) {
                root.hash = "";
                //history.pushState("", document.title, window.location.pathname);
                root.ui.scroll_to(hash, true);
            }*/

        });
    }
}

