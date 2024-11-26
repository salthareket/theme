function IsUrl(s) {
   var regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
   return regexp.test(s);
}

function getUrlVars(url) {
    var hash;
    var myJson = {};
    var hashes = url.slice(url.indexOf('?') + 1).split('&');
    debugJS(hashes)
    if(hashes.length>0 && url.indexOf('?')>-1){
	    for (var i = 0; i < hashes.length; i++) {
	        hash = hashes[i].split('=');
	        if(typeof hash[1] !== "undefined"){
	        	myJson[hash[0]] = hash[1];
	        }
	    }    	
    }
    return myJson;
}

function url2json(str) {
  /*var reg = /[^?]*\??([^&]+)=([^&]+)/g, result, obj = {};
  while(result = reg.exec(str)) {
    obj[result[1]] = result[2];
  }
  return obj;*/
  return getUrlVars(str);
}

function json2url(json) {
  var arr= [];
  for (var k in json) {
    if (json.hasOwnProperty(k)) {
      arr.push(k + '=' + json[k]);
    }
  }
  return arr.join('&');
}


//Get querystring value
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
    results = regex.exec(location.search);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

function removeQueryString(key) {
    // Mevcut sayfa URL'sini al
    var currentUrl = window.location.href;

    // URL içindeki query string'i kontrol et
    var urlParts = currentUrl.split("?");
    if (urlParts.length >= 2) {
        var queryString = urlParts[1];

        // Query string parametrelerini ayrıştır
        var params = queryString.split("&");
        var updatedParams = [];

        // Belirtilen anahtarı kontrol et ve hariç tut
        for (var i = 0; i < params.length; i++) {
            var param = params[i].split("=");
            if (param[0] !== key) {
                updatedParams.push(params[i]);
            }
        }

        // Güncellenmiş query string'i oluştur
        var updatedQueryString = updatedParams.join("&");

        // Yeni URL'yi oluştur ve güncelle
        var newUrl = urlParts[0] + (updatedQueryString ? "?" + updatedQueryString : "");
        history.replaceState(null, null, newUrl);
    }
}