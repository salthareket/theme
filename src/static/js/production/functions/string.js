function IsBlank(txt){
	var stat = false;
	if(typeof txt === "undefined" || txt == null || txt == "null" || txt == undefined || txt == "undefined" || (txt == "" && txt != false && txt != "false") || txt == "<empty string>" || (typeof txt == "string" && txt.length == 0)){
		stat = true;
	};
	return stat;
};

function nl2br(str="", is_xhtml=true) {   
	var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';    
	return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1'+ breakTag +'$2');
};

String.prototype.replaceAll = function(target, replacement) {
  return this.split(target).join(replacement);
};

function isEmail(myVar){
    var regEmail = new RegExp('^[0-9a-z._-]+@{1}[0-9a-z.-]{2,}[.]{1}[a-z]{2,5}$','i');
    return regEmail.test(myVar);
}

function pluralize($singular="", $plural="", $count=0, $null=""){
  if($count == 0 && !IsBlank($null)){
       return $null;
	}else{
		 if($count == 0){
		 	  return $count;
		 }else{
			  var pluralized = $count==1?$singular:$plural;
		    return pluralized.replace('{}', $count);		 	
		 }
	}
}

function decodeHtml(html) {
    var txt = document.createElement("textarea");
    txt.innerHTML = html;
    var decodedValue = txt.value;
    txt.remove(); // Textarea elementini sil
    return decodedValue;
}
