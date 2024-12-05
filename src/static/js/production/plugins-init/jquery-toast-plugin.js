function toast_notification($notification){
	//dependencies: jquery-toast-plugin
	        var text = "";
	        if(!IsBlank($notification.url)){
	        	text += "<a href='"+$notification.url+"' class='jq-toast-text-linked'>";
	        }
	        text += "<div class='jq-toast-text-wrapper'>";
	        if(!IsBlank($notification.sender.image)){
		       text += $notification.sender.image;
		    }
		    if(!IsBlank($notification.message)){
		       text += "<div class='jq-toast-text'>"+$notification.message;
		    }
		    	if(!IsBlank($notification.time)){
			       text += "<small class='jq-toast-text-date'>"+$notification.time+"</small>";
			    }
		    if(!IsBlank($notification.message)){
		       text += "</div>";
		    }
            text += "</div>";
	        if(!IsBlank($notification.url)){
	        	text += "</a'>";
	        }
            $.toast({
			    //heading: response[i].title,
			    text: text,
			    stack: 4,
			    position: 'bottom-left',
			    icon : false,
			    bgColor: '#fff',
                textColor: '#333',
                hideAfter: 6000,
                loaderBg: '#bf1e2d',
                showHideTransition : 'fade',
                beforeShow: function () {
			        $("body").addClass('toast-open');
			    },
			    afterShown: function () {
			    },
			    beforeHide: function () {
			    },
			    afterHidden: function () {
			        $("body").removeClass('toast-open');
			    }
			});
			/*myToast.update({
			    position: 'top-left',
			    stack : 1,
			    showHideTransition : 'slide'
			});*/
}