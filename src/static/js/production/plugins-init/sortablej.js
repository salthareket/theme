function sortable(){
	//{dependencies: [ 'sortablejs' ]}
	function addNestedClasses($element, level) {
	    $element.children("li").each(function() {
	        var $this = $(this);
	        var $ul = $this.children("ul");

	        $this.addClass("nested-" + level);

	        if ($ul.length > 0) {
	            $ul.addClass("nested-sortable");
	            addNestedClasses($ul, level + 1);
	        }
	    });
	}
	var token_init = "sortable-init";
    if($(".sortable").not("."+token_init).length>0){
        $(".sortable").not("."+token_init).each(function(){
        	var $obj = $(this);
        	var nested = $obj.data("nested");
        	var onEnd = $obj.data("on-end");
        	if(nested){
        		$obj.addClass("nested-sortable");
        		addNestedClasses($(this), 1);
        		$obj.wrap("<div class='sortable-wrapper'/>");
        		var nestedSortables = $(this).parent().find(".nested-sortable");
        		for (var i = 0; i < nestedSortables.length; i++) {
					new Sortable(nestedSortables[i], {
						handle: '.handle',
						group: 'nested',
						animation: 150,
						fallbackOnBody: true,
						swapThreshold: 0.65,
						onEnd: function (/**Event*/evt) {
							var itemEl = evt.item;  // dragged HTMLElement
							evt.to;    // target list
							evt.from;  // previous list
							evt.oldIndex;  // element's old index within old parent
							evt.newIndex;  // element's new index within new parent
							evt.oldDraggableIndex; // element's old index within old parent, only counting draggable elements
							evt.newDraggableIndex; // element's new index within new parent, only counting draggable elements
							evt.clone // the clone element
							evt.pullMode;  // when item is in another sortable: `"clone"` if cloning, `true` if moving
							//debugJS($(itemEl).closest("li").attr("data-id"));
							if(onEnd && typeof window[onEnd] !== "undefined"){
								window[onEnd]($(itemEl));
							}
						},
					});
				}
        	}
        });
    }
}