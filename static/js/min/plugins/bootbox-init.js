bootbox.setDefaults({locale:site_config.user_language,animate:!0,centerVertical:!0,closeButton:!1,});function _confirm(title,msg,size,className,btn_confirm,btn_cancel,callback){var options={className:"modal-confirm nodal-alert text-center ",message:".",buttons:{cancel:{label:'No',className:'btn-outline-danger btn-extend pull-right '},confirm:{label:'Yes',className:'btn-success btn-extend pull-left '}}}
if(!IsBlank(title)){options.title=title}
if(!IsBlank(msg)){options.message=msg}else{options.className+="modal-alert-textless "}
if(!IsBlank(size)){options.size=size}
if(!IsBlank(className)){options.className=className}
if(!IsBlank(btn_confirm)){options.buttons.confirm.label=btn_confirm}
if(!IsBlank(btn_cancel)){options.buttons.cancel.label=btn_cancel}
if(!IsBlank(callback)){options.callback=function(result){if(result){callback(result)}}}
var modal=bootbox.confirm(options);if(IsBlank(title)){modal.find(".modal-header").remove()}
if(IsBlank(msg)){modal.find(".modal-body").remove()}
return modal}
function _alert(title,msg,size,className,btn_ok,callback,closeButton,centerContent){if(!isLoadedJS("bootbox")){alert(title+"\n"+msg);return!1}
var options={className:"modal-alert text-center ",message:".",buttons:{ok:{label:'OK',className:'btn-outline-success btn-extend'}}}
var fullscreen=!1;var footer=!0;var content_classes="";if(!IsBlank(title)){options.title=title}
if(!IsBlank(msg)){options.message=msg}else{options.className+="modal-alert-textless"}
if(!IsBlank(size)){options.size=size}
if(!IsBlank(className)){var classes=className.split(" ");options.className+=" "+className;if(className.indexOf("modal-fullscreen")>-1){fullscreen=!0}
for(var i=0;i<classes.length;i++){if(classes[i].indexOf("bg-")>-1||classes[i].indexOf("text-")>-1){content_classes+=classes[i]+" "}}}
if(!IsBlank(closeButton)){options.closeButton=closeButton}
if(!IsBlank(callback)){options.callback=function(){callback()}}
if(!IsBlank(btn_ok)){options.buttons.ok.label=btn_ok}else{footer=!1}
var modal=bootbox.alert(options);if(fullscreen){modal.find(".modal-dialog").addClass("modal-fullscreen")}
if(!footer){modal.find(".modal-footer").remove()}
if(!IsBlank(centerContent)){modal.find(".modal-body").addClass("d-flex align-items-center justify-content-center")}
if(!IsBlank(content_classes)){modal.find(".modal-content").addClass(content_classes)}}
function _prompt(){var options={title:'A custom dialog with buttons and callbacks',message:"<p>This dialog has buttons. Each button has it's own callback function.</p>",size:'large',buttons:{cancel:{label:"I'm a cancel button!",className:'btn-danger',callback:function(){debugJS('Custom cancel clicked')}},noclose:{label:"I don't close the modal!",className:'btn-warning',callback:function(){debugJS('Custom button clicked');return!1}},ok:{label:"I'm an OK button!",className:'btn-info',callback:function(){debugJS('Custom OK clicked')}}}};var dialog=bootbox.dialog(options)}
function modal_confirm(){var token_init="modal-confirm-init";$("[data-toggle='confirm']").unbind("click").on("click",function(e){e.preventDefault();var url=$(this).attr("href");var title=$(this).data("confirm-title");var message=$(this).data("confirm-message");var size=$(this).data("confirm-size");var classname=$(this).data("confirm-classname");var btn_ok=$(this).data("confirm-btn-ok");var btn_cancel=$(this).data("confirm-btn-cancel");var _callback=$(this).data("confirm-callback");var callback=function(){};if(IsUrl(url)){var callback=function(){$("body").addClass("loading");window.location.href=url}}else if(!IsBlank(_callback)){var callback=function(){eval(_callback)}}
_confirm(title,message,size,classname,btn_ok,btn_cancel,callback)})}
function modal_alert(){var token_init="modal-alert-init";$("[data-toggle='alert']").on("click",function(e){e.preventDefault();var url=$(this).attr("href");var title=$(this).data("alert-title");var message=$(this).data("alert-message");if(!IsBlank(message)){if(message.indexOf("#")==0){message=$(message).html()}}
var size=$(this).data("alert-size");var btn_ok=$(this).data("alert-btn-ok");var classname=$(this).data("alert-classname");var _callback=$(this).data("alert-callback");if(IsUrl(url)){var callback=function(){$("body").addClass("loading");window.location.href=url}}else if(!IsBlank(_callback)){var callback=function(){eval(_callback)}}
_alert(title,message,size,classname,btn_ok,callback)})}