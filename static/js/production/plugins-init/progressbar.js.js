function progressCircle(){
	//dependencies: progressbar.js
	var token_init = "progress-circle-init";
    if($(".progress-circle").not("."+token_init).length>0){
        $(".progress-circle").not("."+token_init).each(function(){
        	$(this).addClass(token_init);

        	var progress = $(this).data("progress");

        	var progress_text = $(this).data("progress-text");
        	var progress_term = $(this).data("progress-term");
        	var text = progress_text+(progress_term?"<span>"+progress_term+"</span>":"");
        	var duration = $(this).data("duration");

        	var progress_start = $(this).data("progress-start");
        	var progress_end = $(this).data("progress-end");

        	var options = {
				strokeWidth: 6,
				easing: 'easeInOut',
				duration: duration||1400,
				color: '#FFEA82',
				trailColor: '#eee',
				trailWidth: 1,
				svgStyle: null,
				text : {
				   value : text
				},
			};
			if(progress_start && progress_end){
				options["from"] = { color: progress_start };
				options["to"] = { color: progress_end };
				options["step"] = function(state, circle, attachment) {
				   circle.path.setAttribute('stroke', state.color);
				};
			}
			var bar = new ProgressBar.Circle($(this)[0], options);
	            bar.animate(progress/100);
	    });
    }
}
