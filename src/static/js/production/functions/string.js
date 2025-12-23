/*function IsBlank(txt){
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
*/



function IsBlank(val) {
    return (
        val === undefined ||
        val === null ||
        val === '' ||
        val === 'null' ||
        val === 'undefined' ||
        val === '<empty string>' ||
        (typeof val === 'string' && val.trim().length === 0)
    );
}
/*function nl2br(str = '', isXhtml = true) {
    const breakTag = isXhtml ? '<br />' : '<br>';
    return str.replace(/(?:\r\n|\r|\n)/g, breakTag);
}*/
function nl2br (str, is_xhtml) {   
    var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';    
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1'+ breakTag +'$2');
};
function IsUrl(s) {
   var regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
   return regexp.test(s);
}
function isEmail(email) {
    return /^[\w.-]+@[\w.-]+\.[a-z]{2,}$/i.test(email);
}
function pluralize(singular = '', plural = '', count = 0, fallback = '') {
    if (count === 0 && !IsBlank(fallback)) {
        return fallback;
    }

    if (count === 0) {
        return count.toString();
    }

    const text = count === 1 ? singular : plural;
    return text.replace('{}', count);
}
function decodeHtml(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    const decoded = txt.value;
    txt.remove();
    return decoded;
}

function getAllMatches(regex, text) {
    if (regex.constructor !== RegExp) {
        throw new Error('not RegExp');
    }

    var res = [];
    var match = null;

    if (regex.global) {
        while (match = regex.exec(text)) {
            res.push(match);
        }
    }
    else {
        if (match = regex.exec(text)) {
            res.push(match);
        }
    }

    return res;
}

/**
 * Bir dizeyi, PHP/JSON'un kaçış dizisi (Unicode Escape) formatına dönüştürür.
 * Bu, çeviri sözlüğündeki anahtarlarla (key) doğru eşleşme yapılmasını sağlar.
 *
 * @param {string} str Kaynak dize (örneğin: "Mağaza Bilgisi").
 * @returns {string} Kaçış dizisi formatına dönüştürülmüş dize (örneğin: "Ma\u011faza Bilgisi").
 */
function escapeToUnicode(str) {
    let result = '';
    
    for (let i = 0; i < str.length; i++) {
        const char = str[i];
        const charCode = char.charCodeAt(0);
        
        // 127'den büyük karakter kodlarını (Türkçe, Arapça, Kiril, vb.) kaçış dizisine dönüştür
        if (charCode > 127) {
            // Kaçış dizisi (\uXXXX) formatını oluştur
            let hex = charCode.toString(16);
            
            // 4 haneye tamamla (örneğin: ğ için \u011f)
            while (hex.length < 4) {
                hex = '0' + hex;
            }
            result += '\\u' + hex;
        } else {
            // Standart ASCII karakterleri (a-z, 0-9, boşluk, vb.) doğrudan ekle
            result += char;
        }
    }
    
    return result;
}