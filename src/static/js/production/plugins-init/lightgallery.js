function init_lightGallery(){
	$(".lightgallery.init-me").each(function(){

        $(this).removeClass("init-me");
		var id = $(this).attr("id");
		if(IsBlank(id)){
			id = "gallery-"+generateCode(5);
			$(this).attr("id", id);
		}
		var gallery_type = $(this).data("gallery-type");
		var lightbox = bool($(this).data("lightbox"), true);
		var gallery_source = [];

		let plugins = [];
		if(gallery_type != "dynamic"){
	        if($(this).children("[data-src]").length > 0 || $(this).children("[data-video]").length > 0){
	           plugins = [lgVideo];
	        }			
		}else{
			console.log(id.replaceAll("-", "_"));
			gallery_source = window[id.replaceAll("-", "_")];
			console.log(gallery_source)
			var hasVideo = gallery_source.some(item => item.poster || item.video) ? true : false;;
			if(hasVideo){
				plugins = [lgVideo];
			}
		}

		if(gallery_type == "justified"){

			$(this)
			.justifiedGallery({
                captions: $(this).data("item-captions"),
                lastRow: $(this).data("item-last-row"),
                rowHeight: $(this).data("item-height"),
                margins: $(this).data("item-margin")
            })
            .on("jg.complete", function () {
                $(this).removeClass("loading-hide");
                if(lightbox){
	                lightGallery(
						document.getElementById(id),
						{
							download: false,
							galleryId: id,
							getCaptionFromTitleOrAlt: false,
							plugins: plugins,
							licenseKey:"1111-1111-111-1111",
							mobileSettings: { controls: true, showCloseIcon: true, download: false } 
						}
					);                	
                }

			});	

		}else if(gallery_type == "dynamic"){
            
            $(this).removeClass("loading-hide");
			let dynamicGallery = window.lightGallery(document.getElementById(id),
			{
			    dynamic: true,
			    dynamicEl: gallery_source,
			    plugins: plugins,
			    mobileSettings: { controls: true, showCloseIcon: true, download: false } 
			});
			$(this).on("click", function(){
				console.log(gallery_source)
			    dynamicGallery.openGallery(0);
			});

		}else{

			$(this).removeClass("loading-hide");
			if(lightbox){
	            lightGallery(
					document.getElementById(id),
					{
						download: false,
						galleryId: id,
						getCaptionFromTitleOrAlt: false,
						plugins: plugins,
						mobileSettings: { controls: true, showCloseIcon: true, download: false } 
					}
				);
	        }

		}
	});
}