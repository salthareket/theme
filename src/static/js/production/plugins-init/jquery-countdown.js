function countdown(){
	//dependencies : jquery-countdown
	var token_init = "countdown-init";
    if($(".countdown").not("."+token_init).length>0){
        $(".countdown").not("."+token_init).each(function(){
        	$(this).addClass(token_init);
			var start = $(this).data("event-start");
			var end = $(this).data("event-end");
			var completed = $(this).data("event-completed");
			var completed_callback = $(this).data("event-completed-callback");
			var timezone = $(this).data("event-timezone");
			var live_text = $(this).data("event-live");
			var live_callback = $(this).data("event-live-callback");
			if(IsBlank(live)){
				//live_text = "Session is live";
			}
			if(!IsBlank(timezone)){
				var client_timezone = moment.tz.guess();	
                if(!IsBlank(start)){
					var date_start = moment.tz(start, "GMT");
					    date_start = moment.tz(date_start, client_timezone);                	
                }
                if(!IsBlank(end)){
					var date_end = moment.tz(end, "GMT");
					    date_end = moment.tz(date_end, client_timezone);
                }
			}else{
				if(!IsBlank(start)){
					var date_start = moment(start);              	
                }
                if(!IsBlank(end)){
					var date_end = moment(end);
                }
			}

			if(!IsBlank(date_start)){
				var countdown = date_start.toDate();//start;
			}

			var live = "";
			var now = moment.tz(new Date(), "GMT");

			if(!IsBlank(date_start)){
			    if(moment.tz(date_start, "GMT").isBefore(now)){
					countdown = date_end.toDate();//end;
					if(IsBlank(live_text) && typeof live_text !== "undefined"){
						live = "<div>"+live_text+"</div>";
					}
				}				
			}else{
				countdown = date_end.toDate();//end;
				if(IsBlank(live_text) && typeof live_text !== "undefined"){
					live = "<div>"+live_text+"</div>";
				}
			}

			$(this).countdown(countdown)
			.on('update.countdown', function(event) {
				  var format = '%H:%M:%S';
				  if(event.offset.totalDays > 0) {
				    format = '%-d day%!d ' + format;
				  }
				  if(event.offset.weeks > 0) {
				    format = '%-w week%!w ' + format;
				  }
				  $(this).html(live+event.strftime(format));
			})
			.on('finish.countdown', function(event) {
				countdown = "";
				var live = "";
			    var now = moment.tz(new Date(), "GMT");
				 	if(moment.tz(date_start, "GMT").isBefore(now)){
					countdown = date_end.toDate();//end;
					live = "<div>"+live_text+"</div>";
				}
				if(IsBlank(countdown)){
				 	$(this).html(completed).parent().addClass('disabled');
				 	if(!IsBlank(live_callback)){
				       	if(typeof window[live_callback] === "function"){
				       	    window[live_callback]();
				       	}
				    }
				}else{
				 	$(this).countdown(countdown);
				 	if(!IsBlank(completed_callback)){
				       	if(typeof window[completed_callback] === "function"){
				       	    window[completed_callback]();
				       	}
				    }
				}
			});
	    });
    }
}