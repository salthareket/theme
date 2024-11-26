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