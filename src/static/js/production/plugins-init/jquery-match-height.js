function init_match_height(){
	// data-mh-all eklenirse tum data-mh'ler aynı yukseklikte olur.
    var mhByRow = [];
	$("[data-mh][data-mh-all]").each(function(){
		var mh = $(this).data("mh");
		if(mhByRow.indexOf(mh) < 0){
			mhByRow.push(mh);
			$("[data-mh='"+mh+"']").matchHeight({
				byRow : false
			});
		}
	});
}