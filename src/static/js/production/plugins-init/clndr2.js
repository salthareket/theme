function calendar(){
	var token_init = "calendar-init";
    if($(".calendar").not("."+token_init).length > 0){
    	var currentMonth = moment().format('MM');
		var currentYear = moment().format('YYYY');
		var nextMonth    = moment().add(1,'month').format('YYYY-MM');
	    $(".calendar").not("."+token_init).each(function(){
	    	eventsThisMonth: [ ];
	        var events_list=[];

	        var $calendar = $(this);
	            $calendar.addClass(token_init);
	       	var $template = $calendar.data("template");
	       	if(!IsBlank($template)){
	        	twig({
						href : ajax_request_vars.theme_url+"static/templates/"+$template+".twig",
						async : true,
						allowInlineIncludes : true,
						load: function(template) {
							moment.locale(root.lang);
							debugJS(moment().calendar())
							$calendar.clndr({
								moment: moment,
							    render : function(data){
							  	        return template.render(data);
							    },
							    startWithMonth: moment(),
							    clickEvents: {
								    // fired whenever a calendar box is clicked.
								    // returns a 'target' object containing the DOM element, any events, and the date as a moment.js object.
								    click: function(target){
								    	  $(".popover").each(function(){
											 var id=$(this).attr("id");
											 $("[aria-describedby="+id+"]").popover("destroy");
										  });
										 
										  if(target.events.length) {
											 var today = new Date();
	                                             
										     var eventDate = new Date(target.events[0].date);
											 debugJS(today+" = "+eventDate)
											  //if(eventDate<=today){
											     window.location.href=target.events[0].url;
											  //}
										  }
								    },
								    // fired when a user goes forward a month. returns a moment.js object set to the correct month.
								    nextMonth: function(month){ },
								    // fired when a user goes back a month. returns a moment.js object set to the correct month.
								    previousMonth: function(month){ },
								    // fired when a user goes back OR forward a month. returns a moment.js object set to the correct month.
								    onMonthChange: function(month){
								    	moment.locale("en");
								    	debugJS(month)

								    	getEvents($calendar, this, month.locale('en').format('M'), month.locale('en').format('YYYY'));
								    },
								    // fired when a user goes to the current month/year. returns a moment.js object set to the correct month.
								    today: function(month){ }
								},
							    events: [],
								doneRendering: function(am){ 
									     /*var events=this.options.events;
									     debugJS(this);
									     if(!IsBlank(events)){
								             for(var event in events){
												 var eventDay=events[event];
												 var obj=$(".calendar-day-"+eventDay.date);
												 obj.attr("id",eventDay.date.replaceAll("-","_"));
												 obj.attr("role","button");
												 obj.attr("data-content",eventDay.title);
												 obj.attr("data-trigger","focus");//"focus");
												 obj.attr("data-html","true");
												 obj.attr("data-container","body");
												 obj.attr("data-template",'<div class="popover text-xs" role="tooltip"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>');
												 obj.on("mouseover",function(){
													$(this).popover("show") 
												 });
												 obj.on("mouseout",function(){
													$(this).popover("hide") 
												 });
											 }
										}*/
								},
								ready:function(aa){
									getEvents($calendar, this, currentMonth, currentYear)
								}
							});
						}
				});
	        }
	    });
	}
}