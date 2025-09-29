{
      before : function(response, vars, form, objs){
           $("#"+objs.story).parent().addClass("loading loading-xs");
      },
    	after : function(response, vars, form, objs){
    		console.log(response, vars, form, objs);
    		var story_args =  {
								backNative: false,
								backButton: true,
								previousTap: false,
								skin: 'snapgram',//'FaceSnap',
								autoFullScreen: false,
								avatars: true,
								paginationArrows: false,
								list: false,
								openEffect: false,
								cubeEffect: true,
								localStorage: true,
								reactive: false,
								rtl:root.lang=="ar"?true:false,
								stories: response.stories,
								language: response.language,
								callbacks:  {
								    onOpen (storyId, callback) {
									    // on open story viewer
									    if(typeof storyId === "undefined"){
									    	if($("#zuck-modal").is(":visible")){
												console.log("instagram sttory exist")
											   $("#zuck-modal")
											   .css("display", "none")
											   .find("#zuck-modal-content").empty();
											}

									    }
									    callback();
									    console.log("onOpen", storyId, callback);
									    $("[data-story-id='"+storyId+"']").unbind("click mousedown touchstart");
									    $("#zuck-modal-slider-stories-main").unbind("mousedown touchstart");
									    $("body").unbind("click mousedown touchstart");
								    },

								    onView (storyId) {
									      // on view story
									      $("[data-story-id='"+storyId+"']").unbind("click mousedown touchstart");
									      $("#zuck-modal-slider-stories-main").unbind("mousedown");
									      $("body").unbind("click mousedown touchstart");
									       console.log("onView", storyId);
								    },/**/

								    onEnd (storyId, callback) {
									     // on end story
									     callback()
									     console.log("story end", storyId, callback);
								    },

								    onClose (storyId, callback) {
									     // on close story viewer
									     callback();
									     console.log("story closed", storyId, callback);
								    },

								    onNavigateItem (storyId, nextStoryId, callback) {
									    // on navigate item of story
									    callback();
									    console.log("onNavigateItem",storyId, nextStoryId, callback);
									},
                                    
								    onDataUpdate (currentState, callback) {
									    // use to update state on your reactive framework
									    callback();
								    }/**/
								}
							};
			if(response.stories.length>0){
				//var stories = new Zuck(objs.story, story_args);

				stories_list($("#"+objs.story), response.stories);

				$("#"+objs.story).addClass("inited user-icon carousel snapgram");
				

    		    $("#"+objs.story).parent().removeClass("loading");
			}else{
				$("#"+objs.story).parent().remove();
			}
			console.log(response);
		}
};