function stripeSaveCard(){
	/* strioe saved cards */
    var stripeSaveCard = $(".woocommerce-SavedPaymentMethods-saveNew").not(".init");
    if(stripeSaveCard.length > 0){
    	stripeSaveCard.addClass("init");
	    stripeSaveCard.addClass("custom-control").addClass("custom-checkbox");
	    stripeSaveCard.find("input").addClass("custom-control-input");
	    stripeSaveCard.find("label").addClass("custom-control-label");
    }
}

function stripeSavedCards(){
	var savedMethods = $(".wc-saved-payment-methods").not(".init");
	if(savedMethods.length > 0){
		savedMethods.addClass("init");
	    savedMethods.find(">li").each(function(i){
	        var content = $(this).html();
	       	$(this).html("<div class='custom-control custom-checkbox'></div>");
	       	$(this).find(".custom-control").html(content);
	       	$(this).find("input").addClass("custom-control-input");
	        $(this).find("label").addClass("custom-control-label");
	        if(i==0){
	           $(this).find("input").prop("checked", true);
	           $(".wc-credit-card-form").addClass("d-none");
	        }
	    });
	    $(".wc-credit-card-form").addClass("d-none");
	    if($("input[type=radio][name='wc-stripe-payment-token']").length>0){
		    $("input[type=radio][name='wc-stripe-payment-token']").change(function() {
		    	var value = $(this).val();
		    	if(value != "new"){
		    		$(".wc-credit-card-form").addClass("d-none");
		    	}else{
		    		$(".wc-credit-card-form").removeClass("d-none");
		    	}
		    }).trigger("change")    	
	    }else{
	    	$(".wc-credit-card-form").removeClass("d-none");
	    }
		//if(savedMethods.data("count") == 0){
		/*if(savedMethods.find(">li").length > 1){
		   $(".wc-credit-card-form").removeClass("d-none");
		}else{
		   $(".wc-saved-payment-methods").addClass("d-none");
		}*/
	}
	$(".payment_method_stripe").css("display","block");
}



function checkout_replacement(){
	if($("#order_review").length>0){
	   var $table = $("#order_review").find(".shop_table");
	       $table.find("tr.cart_item").each(function(){
	       	  var $target = $(this).find(".product-total");
	       	  var $amount = $(this).find(".product-name").find(".amount").html();
	       	  $target.html($amount);
	       });
    }
}


function btn_pay_now(){
	var token_init = "btn-pay-now-init";
	$(".btn-pay-now").not("."+token_init).each(function(e){
		e.preventDefault();
		var vars =  {
	        id : $(this).data("id"),
	    };
        var query = new ajax_query();
	    	query.method = "pay_now";
	    	query.vars = vars;
			query.request();
    });
}