function text_effect(){
	//dependencies : textillate
	$(".text-effect").each(function(){
		let obj = $(this);

		let lines = obj.data("lines");
		let split_lines = obj.data("split_lines");

		let viewport = obj.data("viewport");
		console.log(obj.data());
		console.log(viewport)
		if(typeof viewport !== "undefined"){
			obj
			.addClass("viewport")
			.attr("data-autostart", false)
			.attr("data-viewport-func", "text_effect_start")
			.attr("data-initial-delay", 1000);
		}
        
        let slide = obj.closest(".swiper-slide");
		if(slide.length > 0){
			obj
			.attr("data-autostart", false)
			.attr("data-initial-delay", 1000);
			let swiper = slide.closest(".swiper")[0].swiper;
			swiper
			.on('beforeSlideChangeStart', function () {
				var swiper = this;
				var current_slide = $(swiper.slides[(swiper.previousIndex)]);
				let slide_obj = current_slide.find(".text-effect");
				slide_obj.addClass("invisible").textillate('stop');
			})
			.on('slideChange', function () {
				var swiper = this;
				var current_slide = $(swiper.slides[swiper.activeIndex]);
				let slide_obj = current_slide.find(".text-effect");
				slide_obj.addClass("invisible").textillate('start');
			});
		}

        if (obj.html().includes('<br')) {
        	let htmlContent = obj.html();
	        let parts = htmlContent.split(/<br\s*\/?>/i);
	        obj.empty();

        	if(lines == "rotate"){
	            let dataAttributes = {};
	            $.each(obj[0].attributes, function() {
	                if (this.name.startsWith('data-')) {
	                    dataAttributes[this.name] = this.value;
	                }
	            });
	            let ul = $('<ul class="texts"></ul>');
	            $.each(parts, function(index, part) {
	                if ($.trim(part)) {
	                    let li = $('<li></li>');
	                    li.html($.trim(part));
	                    $.each(dataAttributes, function(key, value) {
	                        li.attr(key, value); // direk attr ile ekle
	                    });
	                    ul.append(li);
	                }
	            });
	            obj.append(ul);
	        }
	    }

	    if(lines == "split"){
	    	obj.addClass("split-lines");
	    }

		obj
		.textillate()
		.on('inAnimationBegin.tlt', function () {
			  obj.removeClass("invisible");
		})
	});
}
function text_effect_start(obj){
	console.log(obj);
	obj
	.textillate('start');
}