function input_spinner_init(){
    var token_init = "input-spinner-init";
    $("input[type='number']").not("."+token_init).each(function(i){
        $(this).addClass(token_init);
        var classSize = "lg";
        if($(this).hasClass("size-md")){
            classSize="md";
        }
        var classWidth = "lg";
        if($(this).hasClass("width-md")){
            classWidth="md";
        }
        $(this).inputSpinner({
            incrementButton: "<strong>+</strong>",
            decrementButton: "<strong>-</strong>",
            groupClass: "input-group-quantity input-group-quantity-right input-group-"+classSize+" input-group-quantity-"+classWidth,
            buttonsClass: "",
            buttonsWidth: "2.5rem",
            textAlign: "center",
            autoDelay: 500,
            autoInterval: 100,
            boostThreshold: 10,
            boostMultiplier: "auto",
            locale: null
        });
        //$(this).next().find(".input-spinner-init").attr("name",$(this).attr("name")+"-fake-"+i)       
    });
}