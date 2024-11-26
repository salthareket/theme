
function qrCode_item($obj){
	//dependencies: easyqrcodejs
	var token_init = "qrcode-init";
	$obj.addClass(token_init);
        	var text = $obj.data("text");
        	var width = $obj.data("width")||50;
        	var colorDark = $obj.data("color-dark")||"#000000";
        	var colorLight = $obj.data("color-light")||"#ffffff";

            if(!IsBlank(text)){
	            new QRCode($obj[0], {
					text: text,
					width: width,
					height: width,
					colorDark: colorDark,
					colorLight: colorLight,
					subTitleTop: 0,
					titleHeight: 0,
					/*
					title: 'Ekosinerji',
					titleFont: "bold 16px Arial",
					titleColor: "#000000",
					titleBgColor: "#fff",
					titleHeight: 35,
					titleTop: 0,
					
					subTitle: '<?=time()?>',
					subTitleFont: "14px Arial",
					subTitleColor: "#004284",
					subTitleTop: 0,
					
					logo:"logo.png", // LOGO
					logoWidth:80, // 
					logoHeight:80,
					logoBgColor:'#ffffff',
					logoBgTransparent:false,
					*/
					
					correctLevel: QRCode.CorrectLevel.M // L, M, Q, H
				});
			} 	
}
function qrCode(){
	//dependencies: easyqrcodejs
	var token_init = "qrcode-init";
    if($(".qrcode").not("."+token_init).length>0){
        $(".qrcode").not("."+token_init).each(function(){
        	if(!$(this).hasClass("viewport")){
               qrCode_item($(this)); 
        	}else{
        	   $(this).data("viewport-func", "qrCode_item"); 
        	}
        });
    }
}
