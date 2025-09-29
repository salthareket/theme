//var ajax = false;
var debug = false;

document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
        document.body.classList.remove('loading-process');
    }
});

window.lazySizesConfig = window.lazySizesConfig || {};
window.lazySizesConfig.customMedia = {
    '--xs'   : '(max-width: 575px)',
    '--sm'   : '(min-width: 576px) and (max-width: 767px)',
    '--sm_ls': '(min-width: 576px) and (max-width: 767px) and (orientation: landscape)',
    '--md'   : '(min-width: 768px) and (max-width: 991px)',
    '--lg'   : '(min-width: 992px) and (max-width: 1199px)',
    '--xl'   : '(min-width: 1200px) and (max-width: 1399px)',
    '--xxl'  : '(min-width: 1400px) and (max-width: 1599px)',
    '--xxxl' : '(min-width: 1600px)'
};

var _extensions = {
    "ai": "application/postscript",
    "aif": "audio/x-aiff",
    "aifc": "audio/x-aiff",
    "aiff": "audio/x-aiff",
    "asc": "text/plain",
    "atom": "application/atom+xml",
    "atom": "application/atom+xml",
    "au": "audio/basic",
    "avi": "video/x-msvideo",
    "bcpio": "application/x-bcpio",
    "bin": "application/octet-stream",
    "bmp": "image/bmp",
    "cdf": "application/x-netcdf",
    "cgm": "image/cgm",
    "class": "application/octet-stream",
    "cpio": "application/x-cpio",
    "cpt": "application/mac-compactpro",
    "csh": "application/x-csh",
    "css": "text/css",
    "csv": "text/csv",
    "dcr": "application/x-director",
    "dir": "application/x-director",
    "djv": "image/vnd.djvu",
    "djvu": "image/vnd.djvu",
    "dll": "application/octet-stream",
    "dmg": "application/octet-stream",
    "dms": "application/octet-stream",
    "doc": "application/msword",
    "dtd": "application/xml-dtd",
    "dvi": "application/x-dvi",
    "dxr": "application/x-director",
    "eps": "application/postscript",
    "etx": "text/x-setext",
    "exe": "application/octet-stream",
    "ez": "application/andrew-inset",
    "gif": "image/gif",
    "gram": "application/srgs",
    "grxml": "application/srgs+xml",
    "gtar": "application/x-gtar",
    "hdf": "application/x-hdf",
    "hqx": "application/mac-binhex40",
    "htm": "text/html",
    "html": "text/html",
    "ice": "x-conference/x-cooltalk",
    "ico": "image/x-icon",
    "ics": "text/calendar",
    "ief": "image/ief",
    "ifb": "text/calendar",
    "iges": "model/iges",
    "igs": "model/iges",
    "jpe": "image/jpeg",
    "jpeg": "image/jpeg",
    "jpg": "image/jpeg",
    "js": "application/x-javascript",
    "json": "application/json",
    "kar": "audio/midi",
    "latex": "application/x-latex",
    "lha": "application/octet-stream",
    "lzh": "application/octet-stream",
    "m3u": "audio/x-mpegurl",
    "man": "application/x-troff-man",
    "mathml": "application/mathml+xml",
    "me": "application/x-troff-me",
    "mesh": "model/mesh",
    "mid": "audio/midi",
    "midi": "audio/midi",
    "mif": "application/vnd.mif",
    "mov": "video/quicktime",
    "movie": "video/x-sgi-movie",
    "mp2": "audio/mpeg",
    "mp3": "audio/mpeg",
    "mpe": "video/mpeg",
    "mpeg": "video/mpeg",
    "mpg": "video/mpeg",
    "mpga": "audio/mpeg",
    "ms": "application/x-troff-ms",
    "msh": "model/mesh",
    "mxu": "video/vnd.mpegurl",
    "nc": "application/x-netcdf",
    "oda": "application/oda",
    "ogg": "application/ogg",
    "pbm": "image/x-portable-bitmap",
    "pdb": "chemical/x-pdb",
    "pdf": "application/pdf",
    "pgm": "image/x-portable-graymap",
    "pgn": "application/x-chess-pgn",
    "png": "image/png",
    "pnm": "image/x-portable-anymap",
    "ppm": "image/x-portable-pixmap",
    "ppt": "application/vnd.ms-powerpoint",
    "ps": "application/postscript",
    "qt": "video/quicktime",
    "ra": "audio/x-pn-realaudio",
    "ram": "audio/x-pn-realaudio",
    "ras": "image/x-cmu-raster",
    "rdf": "application/rdf+xml",
    "rgb": "image/x-rgb",
    "rm": "application/vnd.rn-realmedia",
    "roff": "application/x-troff",
    "rss": "application/rss+xml",
    "rtf": "text/rtf",
    "rtx": "text/richtext",
    "sgm": "text/sgml",
    "sgml": "text/sgml",
    "sh": "application/x-sh",
    "shar": "application/x-shar",
    "silo": "model/mesh",
    "sit": "application/x-stuffit",
    "skd": "application/x-koan",
    "skm": "application/x-koan",
    "skp": "application/x-koan",
    "skt": "application/x-koan",
    "smi": "application/smil",
    "smil": "application/smil",
    "snd": "audio/basic",
    "so": "application/octet-stream",
    "spl": "application/x-futuresplash",
    "src": "application/x-wais-source",
    "sv4cpio": "application/x-sv4cpio",
    "sv4crc": "application/x-sv4crc",
    "svg": "image/svg+xml",
    "svgz": "image/svg+xml",
    "swf": "application/x-shockwave-flash",
    "t": "application/x-troff",
    "tar": "application/x-tar",
    "tcl": "application/x-tcl",
    "tex": "application/x-tex",
    "texi": "application/x-texinfo",
    "texinfo": "application/x-texinfo",
    "tif": "image/tiff",
    "tiff": "image/tiff",
    "tr": "application/x-troff",
    "tsv": "text/tab-separated-values",
    "txt": "text/plain",
    "ustar": "application/x-ustar",
    "vcd": "application/x-cdlink",
    "vrml": "model/vrml",
    "vxml": "application/voicexml+xml",
    "wav": "audio/x-wav",
    "wbmp": "image/vnd.wap.wbmp",
    "wbxml": "application/vnd.wap.wbxml",
    "wml": "text/vnd.wap.wml",
    "wmlc": "application/vnd.wap.wmlc",
    "wmls": "text/vnd.wap.wmlscript",
    "wmlsc": "application/vnd.wap.wmlscriptc",
    "wrl": "model/vrml",
    "xbm": "image/x-xbitmap",
    "xht": "application/xhtml+xml",
    "xhtml": "application/xhtml+xml",
    "xls": "application/vnd.ms-excel",
    "xml": "application/xml",
    "xpm": "image/x-xpixmap",
    "xsl": "application/xml",
    "xslt": "application/xslt+xml",
    "xul": "application/vnd.mozilla.xul+xml",
    "xwd": "image/x-xwindowdump",
    "xyz": "chemical/x-xyz",
    "zip": "application/zip"
};
var _extensions_type = {};
for (var key in _extensions) {
    if (_extensions.hasOwnProperty(key)) {
        var value = _extensions[key];
        _extensions_type[value] = key;
    }
}

window.lazyFunctions = {
    masonry: function(element) {
        debugJS($(element));
        //var masonry = $(element).closest(".row").data('masonry');
        //   masonry.masonry();
    }
}

if ($(".form-main").length > 0) {
    var _hasUserLeft = false;
    const doSomethingWhenUserStays = function doSomethingWhenUserStays() {
        if (!_hasUserLeft) {
            $("body").removeClass("loading loading-process");
        }
    }
    window.onload = function() {
        window.addEventListener("beforeunload", function(e) {
            if ($(".form-main.form-changed").length == 0) {
                return undefined;
            }
            setTimeout(doSomethingWhenUserStays, 500);
            var confirmationMessage = 'It looks like you have been editing something. ' +
                'If you leave before saving, your changes will be lost.';

            (e || window.event).returnValue = confirmationMessage; //Gecko + IE
            return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
        });
    };
    window.addEventListener('unload', function onUnload() {
        _hasUserLeft = true;
    });
}


window.onerror = function(message, url, line) {
    //alert(message + ', ' + url + ', ' + line);
    //debugJS(message + ', ' + url + ', ' + line);
};



function redirect_polyfill($url, $blank=false){
    var linkElement = document.createElement('a');
        linkElement.id = 'link';
    window.document.body.appendChild(linkElement);
    var link = document.getElementById('link');
        link.setAttribute('href', $url);
        if($blank){
            link.setAttribute('target', "_blank");
        }
        link.click();
}


function response_view(response) {
    //debugJS(response)
    var modal = $(".modal.show");
    if (response.error) {
        $("body").removeClass("loading-process");
        if(response.hasOwnProperty("error_type")){
           if(response.error_type == "nonce"){
              if(modal.length > 0){
                 modal.modal("hide");
              }
              _alert(response.message, response.description, "", "", "Refresh Page", function(){
                  window.location.reload();
              });
           }
        }else{
            _alert(response.message, response.description);
        }
    } else {
        if (response.redirect) {
            if (response.message) {
                if (modal) {
                    modal.addClass("remove-on-hidden").modal("hide");
                }
                _alert(response.message, response.description);
            }
            if(response.redirect_blank){
                redirect_polyfill(response.redirect, true);
            }else{
                redirect_polyfill(response.redirect);
            }
        } else {
            if (response.refresh) {
                $("body").addClass("loading");
                window.location.reload();
            } else {
                if(response.refresh_confirm){
                    _alert(response.message, response.description, "", "", "Tamam", function(){
                      window.location.reload();
                    });
                }else{
                    if (response.message) {
                        if (modal) {
                            modal.addClass("remove-on-hidden").modal("hide");
                        }
                        _alert(response.message, response.description);
                    }                    
                }
                $("body").removeClass("loading-process");
            }
        }
    }
}

var favorites = {
    class_tease: ".card-profile-tease",
    add: function(obj) {
        var id = obj.data("id");
        var vars = {
            id: id
        };
        var data = {
            method: "favorites_add",
            vars: vars,
            _wpnonce: ajax_request_vars.ajax_nonce
        };
        obj.addClass("disabled loading");
        this.request(obj, data);
    },
    remove: function(obj, id) {
        var id = obj.data("id");
        var vars = {
            id: id
        };
        var data = {
            method: "favorites_remove",
            vars: vars,
            _wpnonce: ajax_request_vars.ajax_nonce
        };
        obj.addClass("disabled loading");
        if (obj.data("type") == "favorites") {
            obj.closest(this.class_tease).addClass("loading-process");
        }
        this.request(obj, data);
    },
    get: function(obj) {
        var template = "partials/dropdown/archive";
        var vars = {
            template: template
        };
        var data = {
            method: "favorites_get",
            vars: vars,
            _wpnonce: ajax_request_vars.ajax_nonce
        };
        this.request(obj, data);
    },
    update: function($data) {
        debugJS($data);
        site_config.favorites = $data;
        var $data_parsed = $data;//$.parseJSON($data);
        var dropdown = $(".dropdown-notifications[data-type='favorites']");
        if ($data_parsed.length > 0) {
            dropdown.addClass("active");
        } else {
            dropdown.removeClass("active");
        }
    },
    request: function(obj, data) {
        data["vars"]["ajax"] = true;
        data["ajax"] = "query";
        data["_wpnonce"] = ajax_request_vars.ajax_nonce;
        $.post(host, data)
            .fail(function() {
                alert("error");
                obj.removeClass("disabled");
            })
            .done(function(response) {
                response = $.parseJSON(response);
                if (errorView(response)) {
                    obj.removeClass("disabled");
                    return false;
                };
                switch (data["method"]) {
                    case "favorites_add":
                        obj
                            .removeClass("disabled loading")
                            .addClass("active");
                        $(".btn-favorite[data-id='" + obj.data("id") + "']").each(function() {
                            $(this).addClass("active");
                        });
                        favorites.update(response.data);
                        $(".count-favorites").text(response.count);
                        obj.find(".info").html(response.html);
                        if(typeof $.toast === "function"){
                            toast_notification({
                                url: "",
                                sender: {
                                    image: "<img src='" + ajax_request_vars.theme_url + "/static/img/notification/favorites-add.jpg' class='img-fluid' alt='Added to favorites'/>"
                                },
                                message: response.message
                            });                            
                        }
                        break;
                    case "favorites_remove":
                        obj
                            .removeClass("active")
                            .removeClass("disabled loading");
                        var dropdownBody = obj.closest(".dropdown-body");
                        if (obj.data("type") == "favorites") {
                            obj.closest(favorites.class_tease).parent().remove();
                            if (count > 0) {
                                dropdownBody.parent().addClass("has-dropdown-item");
                                $("h1.title").find(".description").find("strong").text(count);
                            } else {
                                dropdownBody.parent().removeClass("has-dropdown-item");
                                $("h1.title").find(".description").addClass("d-none");
                                if (dropdownBody.length > 0) {
                                    dropdownBody.find(".notifications").remove();
                                    //dropdownBody.next(".dropdown-footer").remove();//addClass("d-none");
                                }
                            }
                        }
                        $(".btn-favorite[data-id='" + obj.data("id") + "']").each(function() {
                            $(this).removeClass("active");
                            if ($(this).data("type") == "favorites") {
                                $(this).closest(favorites.class_tease).parent().remove();
                            }
                        });
                        obj.find(".info").html(response.html);
                        favorites.update(response.data);
                        $(".count-favorites").text(response.count);
                        if (dropdownBody.find(".notification-item").length == 0) {
                            //dropdownBody.find()
                        }
                        if(typeof $.toast === "function"){
                            toast_notification({
                                url: "",
                                sender: {
                                    image: "<img src='" + ajax_request_vars.theme_url + "/static/img/notification/favorites-remove.jpg' class='img-fluid' alt='Removed from favorites'/>"
                                },
                                message: response.message
                            });
                        }
                        break;
                    case "favorites_get":
                        obj
                            .html(response.html)
                            .removeClass("loading-process")
                            .find(".favorites-remove").on("click", function(e) {
                                e.preventDefault();
                                var id = $(this).data("id");
                                favorites.remove($(this), id);
                            });
                        if (response.post_count > 0) {
                            obj.addClass("has-dropdown-item")
                        } else {
                            obj.removeClass("has-dropdown-item")
                        }
                        SimpleScrollbar.initEl(obj.find(".dropdown-body")[0]);
                        /*if(obj.find(".product-tease").length>0){
                           obj.next(".dropdown-footer").removeClass("d-none");
                        }*/
                        break;
                }
            });
    }
}

var cart = {

    get: function(obj, type) {
        var vars = {
            type: type
        };
        var data = {
            method: "get_cart",
            vars: vars
        };
        this.request(obj, data);
    },
    remove_item: function(obj, type) {
        var key = obj.data("key");
        var vars = {
            key : key,
            type : type
        };
        var data = {
            method: "wc_cart_item_remove",
            vars: vars
        };
        obj.addClass("loading-process");
        this.request(obj.closest(".load-container"), data);
    },
    request: function(obj, data) {
        data["ajax"] = "query";
        $.post(host, data)
            .fail(function() {
                alert("error");
                obj.removeClass("disabled");
            })
            .done(function(response) {
                response = $.parseJSON(response);
                if (errorView(response)) {
                    obj.removeClass("disabled");
                    return false;
                };
                switch (data["method"]) {
                    case "get_cart":
                    case "wc_cart_item_remove":
                        var html = $("<div class='temp'>"+response.html+"</div>");
                        var footer = "";
                        if(html.find(".offcanvas-footer").length > 0){
                            footer = html.find(".offcanvas-footer").html();
                            html.find(".offcanvas-footer").remove();
                        }
                        html = html.html();
                        obj
                        .html(html)
                        .removeClass("loading-process");
                        if(!IsBlank(footer)){
                            obj.next(".offcanvas-footer").removeClass("d-none").html(footer);
                        }else{
                            obj.next(".offcanvas-footer").addClass("d-none").empty();
                        }
                        var count = 0;
                        if (response.hasOwnProperty("data")) {
                            var count = response.data.count;
                        }
                        var counter = $(".dropdown-notifications[data-type='cart'] > a").find(".notification-count");

                        if (counter.length == 0) {
                            $(".dropdown-notifications[data-type='cart'] > a").prepend("<div class='notification-count'></div>");
                            counter = $(".dropdown-notifications[data-type='cart']").find(".notification-count");
                        }
                        if (count == 0) {
                            counter.remove();
                            $(".dropdown-notifications[data-type='cart'] .dropdown-container").removeClass("has-dropdown-item");
                        } else {
                            counter.html(count);
                            $(".dropdown-notifications[data-type='cart'] .dropdown-container").addClass("has-dropdown-item");
                        }
                        obj.find(".cart-item-remove").not(".init").each(function() {
                            $(this).addClass("init");
                            $(this).on("click", function(e) {
                                e.preventDefault();
                                var item = $(this).closest(".notification-item");
                                cart.remove_item(item, data.vars.type);
                            });
                        });
                        break;

                }
            });
    }
}

var messages = {

    get: function(obj) {
        var template = "partials/offcanvas/archive";
        var vars = {
            template: template
        };
        var data = {
            ajax: "query",
            method: "get_messages",
            vars: vars,
            _wpnonce: ajax_request_vars.ajax_nonce
        };
        this.request(obj, data);
    },
    remove_item: function(obj) {
        var key = obj.data("key");
        var vars = {
            key: key
        };
        var data = {
            method: "wc_cart_item_remove",
            vars: vars
        };
        obj.addClass("loading-process");
        this.request(obj.closest(".dropdown-container"), data);
    },
    request: function(obj, data) {

        $.post(ajax_request_vars.url, data)
            .fail(function() {
                alert("error");
                obj.removeClass("disabled");
            })
            .done(function(response) {
                response = $.parseJSON(response);
                if (errorView(response)) {
                    obj.removeClass("disabled");
                    return false;
                };
                switch (data["method"]) {
                    case "get_messages":
                        //case "wc_cart_item_remove" :
                        obj
                            .html(response.html)
                            .removeClass("loading-process");
                        var count = 0;
                        if (response.hasOwnProperty("data")) {
                            var count = response.data.count;
                        }
                        debugJS(count);
                        var counter = $(".dropdown-notifications[data-type='messages'] > a").find(".notification-count");
                        debugJS(counter);
                        if (counter.length == 0) {
                            $(".dropdown-notifications[data-type='messages'] > a").prepend("<div class='notification-count'></div>");
                            counter = $(".dropdown-notifications[data-type='messages']").find(".notification-count");
                        }
                        if (count == 0) {
                            counter.remove();
                        } else {
                            counter.html(count);
                        }
                        obj.find(".cart-item-remove").not(".init").each(function() {
                            $(this).addClass("init");
                            $(this).on("click", function(e) {
                                e.preventDefault();
                                var item = $(this).closest(".notification-item");
                                messages.remove_item(item);
                            });
                        });
                        SimpleScrollbar.initEl(obj.find(".dropdown-body")[0]);
                        break;
                }
            });
    }
}

$.ajaxQueue = [];
var ajax_query_queue = $.ajaxQueue;
ajax_query_process = false;

class ajax_query {
    constructor(method, vars, form, objs) {
        this.method = method;
        this.vars = vars;
        this.form = form;
        this.objs = objs;
        this.upload = false;
        this.skipBefore = false;
        if (IsBlank(vars)) {
            this.vars = {};
        }
        if (IsBlank(form)) {
            this.form = {};
        } else {
            form[0].ajax_query = this;
        }
        if (IsBlank(objs)) {
            this.objs = {};
        }
        this.vars["lang"] = root.lang;
    }
    data() {
        if (Object.keys(this.form).length > 0) {
            // has upload
            if (this.form.find('[type="file"]').length > 0) {
                this.upload = true;
                var form = this.form[0];
                var data = new FormData(form);
                deleteFormData(data, this.vars);
                data.append("ajax", "query");
                data.append("method", this.method);
                data.append("_wpnonce", ajax_request_vars.ajax_nonce);
                createFormData(data, "vars", this.vars);
            } else {
                this.upload = false;
                var data = {
                    ajax: "query",
                    method: this.method,
                    vars: this.vars,
                    _wpnonce: ajax_request_vars.ajax_nonce
                };
            }
        } else {
            this.upload = false;
            var data = {
                ajax: "query",
                method: this.method,
                vars: this.vars,
                _wpnonce: ajax_request_vars.ajax_nonce
            };
        }
        return data;
    }
    abort() {
        if (this.ajax !== "undefined") {
            this.ajax.abort();
        }
    }
    queue() {
        ajax_query_process = false;
        if(this.form.length > 0){
            this.form.removeClass("ajax-processing");
        }
        if (ajax_query_queue.length > 0) {
            var req = ajax_query_queue.shift();
            this.request(req);
        }
    }
    request(obj) {
        if (!IsBlank(obj)) {
            var $obj = obj;
        } else {
            var $obj = this;
        }

        var objs = {};
        if(Object.keys($obj.objs).length > 0){
            objs = $obj.objs;
        }else{
            if(this["vars"].hasOwnProperty("objs")){
                objs = this["vars"]["objs"];
                delete this["vars"]["objs"]                
            }else{
                if(ajax_objs.hasOwnProperty($obj.method)){
                    objs = ajax_objs[$obj.method];
                }
            }                
        }
        $obj.objs = objs;


        if (!ajax_hooks.hasOwnProperty($obj.method)) {
            debugJS($obj)
            if(isLoadedJS("bootbox")){
                _alert("Ajax JS Error", $obj.method + " is not defined.");
            }else{
                console.log("Ajax JS Error", $obj.method + " is not defined.")
            }
            return false;
        }else{

            var $required = "";
            if (ajax_hooks[$obj.method].hasOwnProperty("required")){
                $required = ajax_hooks[$obj.method].required;
            }
            if($required){
                let _this = this
                let sonuc = isLoadedJS($required, true, function(){
                    _this.request($obj);
                });
                console.log(ajax_query_queue)
                if(!sonuc){
                    return false;
                }
            }

        }

        if (ajax_hooks[$obj.method].hasOwnProperty("before") && !$obj.skipBefore) {

            var $obj_update = true;
            if ($obj.form.length > 0) {
                $obj.form.addClass("ajax-processing")
                debugJS("has form");
                if ($obj.form.hasClass("form-review")) {
                    debugJS("need review");
                    if (!$obj.form.hasClass("form-reviewed")) {
                        debugJS("not reviewed yet");
                        return ajax_hooks[$obj.method].before($obj, $obj.vars, $obj.form, $obj.objs);
                    } else {
                        debugJS("reviewed");
                    }
                } else {
                    debugJS("no need review");
                    $obj_update = ajax_hooks[$obj.method].before($obj, $obj.vars, $obj.form, $obj.objs);
                }
            } else {
                debugJS("has not form");
                $obj_update = ajax_hooks[$obj.method].before($obj, $obj.vars, $obj.form, $obj.objs);
            }
            debugJS($obj_update);
            debugJS("$obj_update=" + $obj_update);

            //if(typeof $obj_update !== "undefined"){
            if ($obj_update == false || $obj_update == "false" || $obj_update == 0) {
                debugJS("not empty");
                if ($obj_update == false || $obj_update == "false" || $obj_update == 0) {
                    debugJS("will stop");
                    $obj.form.removeClass("ajax-processing")
                    return false;
                } else {
                    debugJS("update and go");
                    $obj = $obj_update;
                }
            }else{
                //$obj = $obj_update;
                objs = $obj.objs
            }
            //}


            //alert("target after")

            /*if($obj.form.length > 0  && !$obj.form.hasClass("form-reviewed")){
                           return ajax_hooks[$obj.method].before($obj, $obj.vars, $obj.form, $obj.objs);
                        }else{
                           ajax_hooks[$obj.method].before($obj, $obj.vars, $obj.form, $obj.objs);
                        }*/
        }
        

        if (typeof $obj.before === "function") {
            $obj.before($obj, $obj.vars, $obj.form, $obj.objs);
        }

        // update 17.08.2023 : added blow || (!site_config.logged && !site_config.loaded && $obj.method != "site_config")
        if ((ajax_query_process && ajax_query_queue.length > 0) || (!site_config.logged && !site_config.loaded && $obj.method != "site_config")) {
            debugJS("process var")
            debugJS(ajax_query_queue)
            debugJS($obj)
            ajax_query_queue.push($obj);
            return false;
        }
        ajax_query_process = true;

        var data = $obj.data();
        if ($obj.upload) {

            this.ajax = $.ajax({
                    queue: true,
                    url: ajax_request_vars.url,
                    type: 'post',
                    data: data,
                    enctype: 'multipart/form-data',
                    contentType: false,
                    processData: false
                })
                .fail(function() {
                    $obj.queue();
                    _alert("", "error");
                    $("body").removeClass("loading");
                })
                .done(function(response) {
                    $obj.queue();
                    response = ajaxResponseFilter(response);
                    console.log(response)
                    response = $.parseJSON(response);
                    if(response.hasOwnProperty("error")){
                        if(response.error){
                            response_view(response);
                            return false;
                        }
                    }
                    //$obj["vars"]["objs"] = objs;
                    $obj["objs"] = objs;
                    //var type = "";//$obj.check(response);
                    if (ajax_hooks[$obj.method].hasOwnProperty("after") || ajax_hooks[$obj.method].hasOwnProperty("done") || $obj.hasOwnProperty("done")) {
                        if (ajax_hooks[$obj.method].hasOwnProperty("after")) {
                            ajax_hooks[$obj.method].after(response, $obj.vars, $obj.form, $obj.objs);
                        }
                        if (ajax_hooks[$obj.method].hasOwnProperty("done")) {
                            ajax_hooks[$obj.method].done(response, $obj.vars, $obj.form, $obj.objs);
                        }
                        if ($obj.hasOwnProperty("done")) {
                            $obj.done(response, $obj.vars, $obj.form, $obj.objs);
                        }
                    } else {
                        $obj.check(response);
                    }

                    if (typeof $obj.after === "function") {
                        $obj.after(response, $obj.vars, $obj.form, $obj.objs);
                    }

                });
        } else {

            debugJS(ajax_request_vars.url, data)
            //this.ajax = $.post(ajax_request_vars.url, data)
            this.ajax = $.ajax({
                    queue: true,
                    url: ajax_request_vars.url,
                    type: 'post',
                    data: data
                })
                .fail(function() {
                    $obj.queue();
                    $("body").removeClass("loading");
                })
                .done(function(response) {
                     console.log(response)
                    response = ajaxResponseFilter(response);
                    console.log(response)
                    if (isJson(response)) {
                        response = $.parseJSON(response);
                    }
                    //$obj["vars"]["objs"] = objs;
                    $obj["objs"] = objs;
                    /*if(response.hasOwnProperty("error")){
                        if(response.error){
                            response_view(response);
                            $obj.queue();
                            return false;
                        }
                    }*/
                    //var type = $obj.check(response);
                    if (ajax_hooks[$obj.method].hasOwnProperty("after") || ajax_hooks[$obj.method].hasOwnProperty("done") || $obj.hasOwnProperty("done")) {
                        if (ajax_hooks[$obj.method].hasOwnProperty("after")) {
                            ajax_hooks[$obj.method].after(response, $obj.vars, $obj.form, $obj.objs);
                        }
                        if (ajax_hooks[$obj.method].hasOwnProperty("done")) {
                            ajax_hooks[$obj.method].done(response, $obj.vars, $obj.form, $obj.objs);
                        }
                        if ($obj.hasOwnProperty("done")) {
                            $obj.done(response, $obj.vars, $obj.form, $obj.objs);
                        }
                    } else {
                        $obj.check(response);
                    }
                    if (typeof $obj.after === "function") {
                        $obj.after(response, $obj.vars, $obj.form, $obj.objs);
                    }
                    $obj.queue();

                });
        }
    }
    check(data) {
        var type = "";
        if (!data.resubmit) {
            $("body").removeClass("loading");
        }
        if (!IsBlank(data.message)) {
            if ($(".modal.show").length > 0 && this.form.find("#message").length > 0) {
                this.form.find(".message").html("<div class='alert alert-" + (data.error ? "danger" : "success") + " text-center' role='alert'>" + data.message + "</div>")
            } else {
                response_view(data)
                //_alert("", data.message, "md");
            }
            if (data.error) {
                return false;
            }
        }
        if (!IsBlank(data.redirect)) {
            if (IsUrl(data.redirect)) {
                if ($("body").hasClass("loading-steps")) {
                    var loading = $(this.form).data("loading");
                    loading.steps("completed").close();
                }
                window.location.href = data.redirect;
                return false;
            }
        }
        if (!IsBlank(data.html)) {
            type = "html";
            $("body").removeClass("loading");
        }
        if (data.resubmit) {
            type = "resubmit";
            $("body").removeClass("loading");
        }
        return type;
    }
}




var waiting_init = {
    elements: [],

    // Seçici ve callback fonksiyonunu kaydetme
    add: function(elements, callback) {
        this.elements.push({ elements: elements, callback: callback });
    },

    // Eleman init edildiğinde ilgili callback fonksiyonunu çalıştırma
    initElement: function() {
        var self = this;
        this.elements.forEach(function(item) {
            item.elements.forEach(function(element) {
                item.callback(element);
            });
        });
        // Tüm elemanları listeden kaldır
        this.elements = [];
    }
};

function translate(str, count = 1, replacements = {}) {
    if(str == ""){
        return str;
    }
    const defaultLang = site_config.language_default;
    const currentLang = site_config.user_language;

    let entry = str;

    const dictEntry = site_config.dictionary?.[str];

    // Dil farklıysa dictionary'den çeviri yap
    if (defaultLang !== currentLang && dictEntry !== undefined) {
        if (Array.isArray(dictEntry)) {
            const safeCount = parseInt(count, 10);
            entry = safeCount === 1 ? dictEntry[0] : dictEntry[1] || dictEntry[0];
        } else if (typeof dictEntry === 'string') {
            entry = dictEntry;
        }
    }

    // HER ZAMAN %count ve diğer %placeholder’ları replace et
    entry = String(entry).replace('%count', count);

    for (const key in replacements) {
        entry = entry.replaceAll(key, replacements[key]);
    }

    return entry;
}
