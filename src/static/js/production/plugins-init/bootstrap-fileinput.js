// MIME Type ve Uzantı Eşleşmeleri
var _extensions = {
    "ai": "application/postscript", "aif": "audio/x-aiff", "aifc": "audio/x-aiff", "aiff": "audio/x-aiff",
    "asc": "text/plain", "atom": "application/atom+xml", "au": "audio/basic", "avi": "video/x-msvideo",
    "bcpio": "application/x-bcpio", "bin": "application/octet-stream", "bmp": "image/bmp",
    "cdf": "application/x-netcdf", "cgm": "image/cgm", "class": "application/octet-stream",
    "cpio": "application/x-cpio", "cpt": "application/mac-compactpro", "csh": "application/x-csh",
    "css": "text/css", "csv": "text/csv", "dcr": "application/x-director", "dir": "application/x-director",
    "djv": "image/vnd.djvu", "djvu": "image/vnd.djvu", "dll": "application/octet-stream",
    "dmg": "application/octet-stream", "dms": "application/octet-stream", "doc": "application/msword",
    "dtd": "application/xml-dtd", "dvi": "application/x-dvi", "dxr": "application/x-director",
    "eps": "application/postscript", "etx": "text/x-setext", "exe": "application/octet-stream",
    "ez": "application/andrew-inset", "gif": "image/gif", "gram": "application/srgs",
    "grxml": "application/srgs+xml", "gtar": "application/x-gtar", "hdf": "application/x-hdf",
    "hqx": "application/mac-binhex40", "htm": "text/html", "html": "text/html",
    "ice": "x-conference/x-cooltalk", "ico": "image/x-icon", "ics": "text/calendar",
    "ief": "image/ief", "ifb": "text/calendar", "iges": "model/iges", "igs": "model/iges",
    "jpe": "image/jpeg", "jpeg": "image/jpeg", "jpg": "image/jpeg", "js": "application/x-javascript",
    "json": "application/json", "kar": "audio/midi", "latex": "application/x-latex",
    "lha": "application/octet-stream", "lzh": "application/octet-stream", "m3u": "audio/x-mpegurl",
    "man": "application/x-troff-man", "mathml": "application/mathml+xml", "me": "application/x-troff-me",
    "mesh": "model/mesh", "mid": "audio/midi", "midi": "audio/midi", "mif": "application/vnd.mif",
    "mov": "video/quicktime", "movie": "video/x-sgi-movie", "mp2": "audio/mpeg", "mp3": "audio/mpeg",
    "mpe": "video/mpeg", "mpeg": "video/mpeg", "mpg": "video/mpeg", "mpga": "audio/mpeg",
    "ms": "application/x-troff-ms", "msh": "model/mesh", "mxu": "video/vnd.mpegurl",
    "nc": "application/x-netcdf", "oda": "application/oda", "ogg": "application/ogg",
    "pbm": "image/x-portable-bitmap", "pdb": "chemical/x-pdb", "pdf": "application/pdf",
    "pgm": "image/x-portable-graymap", "pgn": "application/x-chess-pgn", "png": "image/png",
    "pnm": "image/x-portable-anymap", "ppm": "image/x-portable-pixmap",
    "ppt": "application/vnd.ms-powerpoint", "ps": "application/postscript", "qt": "video/quicktime",
    "ra": "audio/x-pn-realaudio", "ram": "audio/x-pn-realaudio", "ras": "image/x-cmu-raster",
    "rdf": "application/rdf+xml", "rgb": "image/x-rgb", "rm": "application/vnd.rn-realmedia",
    "roff": "application/x-troff", "rss": "application/rss+xml", "rtf": "text/rtf",
    "rtx": "text/richtext", "sgm": "text/sgml", "sgml": "text/sgml", "sh": "application/x-sh",
    "shar": "application/x-shar", "silo": "model/mesh", "sit": "application/x-stuffit",
    "skd": "application/x-koan", "skm": "application/x-koan", "skp": "application/x-koan",
    "skt": "application/x-koan", "smi": "application/smil", "smil": "application/smil",
    "snd": "audio/basic", "so": "application/octet-stream", "spl": "application/x-futuresplash",
    "src": "application/x-wais-source", "sv4cpio": "application/x-sv4cpio", "sv4crc": "application/x-sv4crc",
    "svg": "image/svg+xml", "svgz": "image/svg+xml", "swf": "application/x-shockwave-flash",
    "t": "application/x-troff", "tar": "application/x-tar", "tcl": "application/x-tcl",
    "tex": "application/x-tex", "texi": "application/x-texinfo", "texinfo": "application/x-texinfo",
    "tif": "image/tiff", "tiff": "image/tiff", "tr": "application/x-troff",
    "tsv": "text/tab-separated-values", "txt": "text/plain", "ustar": "application/x-ustar",
    "vcd": "application/x-cdlink", "vrml": "model/vrml", "vxml": "application/voicexml+xml",
    "wav": "audio/x-wav", "wbmp": "image/vnd.wap.wbmp", "wbxml": "application/vnd.wap.wbxml",
    "wml": "text/vnd.wap.wml", "wmlc": "application/vnd.wap.wmlc", "wmls": "text/vnd.wap.wmlscript",
    "wmlsc": "application/vnd.wap.wmlscriptc", "wrl": "model/vrml", "xbm": "image/x-xbitmap",
    "xht": "application/xhtml+xml", "xhtml": "application/xhtml+xml", "xls": "application/vnd.ms-excel",
    "xml": "application/xml", "xpm": "image/x-xpixmap", "xsl": "application/xml",
    "xslt": "application/xslt+xml", "xul": "application/vnd.mozilla.xul+xml",
    "xwd": "image/x-xwindowdump", "xyz": "chemical/x-xyz", "zip": "application/zip"
};

var _extensions_type = {};
for (var key in _extensions) {
    if (_extensions.hasOwnProperty(key)) {
        _extensions_type[_extensions[key]] = key;
    }
}

function fileinput(){
    var token_init = "fileinput-init";
    if($(".fileinput").not("."+token_init).length>0){
        $('.fileinput').not("."+token_init).each(function(){
            var defaults = {
                showPreview: false,
                showUploadStats: false,
                showUpload: false,
                language: "en",
                browseClass: "btn btn-outline-success",
                browseLabel: "BROWSE",
                removeClass: "btn btn-slim-default",
                removeLabel: "",
                removeIcon: "<i class='fa fa-times'></i>",
                previewFileIcon: "<i class='far fa-image'></i>"
                //allowedFileTypes : ['image'],
                //allowedFileExtensions : ['jpg', 'jpeg', 'gif', 'png', 'bmp']
            };
            var fileTypes = $(this).data("file-types");
            if (!IsBlank(fileTypes)) {
                fileTypes = fileTypes.split(",");
                if (fileTypes.length > 0) {
                    defaults["allowedFileTypes"] = fileTypes;//['image', 'html', 'text', 'video', 'audio', 'flash', 'object']
                }
            }
            var fileFormats = $(this).data("file-formats")
            if (!IsBlank(fileFormats)) {
                fileFormats = fileFormats.split(",");
                if (fileFormats.length > 0) {
                    defaults["allowedFileExtensions"] = fileFormats;
                }
            }
            $(this).removeAttr("data-file-types").removeAttr("data-file-formats");
            $(this).fileinput(defaults)
                .on('fileerror', function (event, data, msg) {
                    _alert('', msg);
                    $(this).fileinput('clear');
                })
                .on('fileselect', function (event, numFiles, label) {
                    debugJS(event);
                    debugJS(numFiles);
                    debugJS(label);
                    var pluginData = $(event.target).data("fileinput");
                    var component = $(event.target).closest('.file-input');
                    var filesCount = $(pluginData.$element).fileinput('getFilesCount');
                    var preview = "";
                    debugJS(pluginData);
                    debugJS(component);
                    if (filesCount > 0) {
                        var ext = getFileExtension(this.files[0].name);
                        alert(ext);
                        var fileType = ["jpg", "jpeg", "png", "gif", "bmp"].indexOf(ext) > -1 ? "image" : ["html", "doc", "docx", "rtf", "xls", "xlsx", "txt", "ppt", "pdf"].indexOf(ext) > -1 ? "doc" : "";
                        if (fileType == "image" || fileType == "doc") {
                            var btnBrowse = component.find(".btn-file");
                            btnBrowse.find("span").text("Preview");

                            if (fileType == "image") {
                                var img = $('<img/>', {
                                    class: 'img-fluid'
                                });
                                var file = this.files[0];
                                var reader = new FileReader();
                                reader.onload = function (e) {
                                    img.attr('src', e.target.result);
                                }
                                reader.readAsDataURL(file);
                                preview = $(img)[0].outerHTML;
                            }

                            if (fileType == "doc") {
                                var docUrl = URL.createObjectURL(this.files[0]);
                                var preview = '<iframe id="fake-preview" frameborder="0" scrolling="no" width="100%" height="500" src="' + docUrl + '"></iframe>';
                            }

                            btnBrowse.on("click", function (e) {
                                e.preventDefault();
                                switch (fileType) {
                                    case "doc":
                                        if (ext == "pdf" || ext == "html") {
                                            _alert('', preview, 'lg');
                                        } else {
                                            var fakePreview = $("body").find("#fake-preview");
                                            if (fakePreview.length > 0) {
                                                fakePreview.remove();
                                            }
                                            var hiddenPreview = $(preview);
                                            hiddenPreview.css("display", "none");
                                            $("body").append(hiddenPreview);
                                        }
                                        break;

                                    case "image":
                                        preview = $(img)[0].outerHTML;
                                        _alert('', preview, 'lg');
                                        break;
                                }
                            });

                        }
                    }
                })
                .on('filecleared', function (event) {
                    $("body").find("#fake-preview").remove();
                    var pluginData = $(event.target).data("fileinput");
                    var component = $(event.target).closest('.file-input');
                    var btnBrowse = component.find(".btn-file");
                    btnBrowse.find("span").text(pluginData.browseLabel);
                    btnBrowse.unbind("click");
                });
        });
    }
}