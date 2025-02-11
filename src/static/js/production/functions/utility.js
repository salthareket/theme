function debugJS($value){
	if(typeof site_config.debug !== "undefined"){
		if(site_config.debug){
			console.log($value);			
		}
	}
}


(function ($) {
  $.fn.hasAttr = function (attrName) {
		return (this.filter(function() {
			if(this.hasAttribute(attrName)){
			   return true;
			}
			return false;
		}));
	};
}(jQuery));

jQuery.expr[':'].Contains = function(a,i,m){
	return (a.textContent || a.innerText || "").toUpperCase().indexOf(m[3].toUpperCase())>=0;
};

jQuery.fn.justtext = function() {   
    return $(this).clone()
            .children()
            .remove()
            .end()
            .text();
};

$.fn.textIsChanged = function(options) {
	var obj=this;
	var val=obj.html();
	var defaults = {
	   val: val,
	   callback : function(val){
					debugJS("changed : "+val);   
				 }
	}
	options = jQuery.extend(defaults, options);
	var chk=setInterval(function() {
		if (obj.html() !== options.val) {
			clearInterval(chk)
			options.callback(obj.html());
		} 		
	},500);
};

function generateCode(codeLen, type){
	if(IsBlank(codeLen)){
		codeLen=5;
	}
    var text = "";
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    switch(type){
	    case "alpha" :
            var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
	    	break;
	    case "numeric":
	    	var possible = "0123456789";
	    	break;
    }
    for( var i=0; i < codeLen; i++ )
        text += possible.charAt(Math.floor(Math.random() * possible.length));
    return text;
};

function onClassChange($obj, $class, $callback){
	var observer = new MutationObserver(function(mutations) {
	 mutations.forEach(function(mutation) {
	    if (mutation.attributeName === "class") {
	      var attributeValue = $(mutation.target).prop(mutation.attributeName);
	      var classes = attributeValue.split(/\s+/);
	      if(classes.indexOf($class) > -1 && typeof $callback === "function"){
	      	 eval($callback)();
	      	 debugJS("Class attribute changed to:", attributeValue);
	      }
	      
	    }
	  });
	});
	observer.observe($obj[0], {
	  attributes: true
	});
}

// browser detect
var BrowserDetect = {
    init: function() {
		this.browser = this.searchString(this.dataBrowser) || "An unknown browser";
		this.version = this.searchVersion(navigator.userAgent) || this.searchVersion(navigator.appVersion) || "an unknown version";
		this.OS = this.searchString(this.dataOS) || "an unknown OS";

	},
	searchString: function(data) {
		for (var i = 0; i < data.length; i++) {
			var dataString = data[i].string;
			var dataProp = data[i].prop;
			this.versionSearchString = data[i].versionSearch || data[i].identity;
			if (dataString) {
				if (dataString.indexOf(data[i].subString) != -1) return data[i].identity;
			} else if (dataProp) return data[i].identity;
		}
	},
	searchVersion: function(dataString) {
		var index = dataString.indexOf(this.versionSearchString);
		if (index == -1) return;
		return parseFloat(dataString.substring(index + this.versionSearchString.length + 1));
	},
	dataBrowser: [{
		string: navigator.userAgent,
		subString: "Chrome",
		identity: "Chrome"
	}, {
		string: navigator.userAgent,
		subString: "OmniWeb",
		versionSearch: "OmniWeb/",
		identity: "OmniWeb"
	}, {
		string: navigator.vendor,
		subString: "Apple",
		identity: "Safari",
		versionSearch: "Version"
	}, {
		string: navigator.userAgent,
		subString: "Edge",
		identity: "Edge",
		versionSearch: "Edg"
	}, { 
		// Opera'nın modern versiyonları için kontrol (Chromium tabanlı)
		string: navigator.userAgent,
		subString: "OPR",
		identity: "Opera",
		versionSearch: "OPR"
	}, {
		string: navigator.vendor,
		subString: "iCab",
		identity: "iCab"
	}, {
		string: navigator.vendor,
		subString: "KDE",
		identity: "Konqueror"
	}, {
		string: navigator.userAgent,
		subString: "Firefox",
		identity: "Firefox"
	}, {
		string: navigator.vendor,
		subString: "Camino",
		identity: "Camino"
	}, { // for newer Netscapes (6+)
		string: navigator.userAgent,
		subString: "Netscape",
		identity: "Netscape"
	}, {
		string: navigator.userAgent,
		subString: "MSIE",
		identity: "Explorer",
		versionSearch: "MSIE"
	}, {
		string: navigator.userAgent,
		subString: "Gecko",
		identity: "Mozilla",
		versionSearch: "rv"
	}, { // for older Netscapes (4-)
		string: navigator.userAgent,
		subString: "Mozilla",
		identity: "Netscape",
		versionSearch: "Mozilla"
	}, { // Android Chrome tarayıcısı için kontrol
		string: navigator.userAgent,
		subString: "Android",
		identity: "Android Chrome",
		versionSearch: "Chrome"
	}, { // iOS Safari tarayıcısı için kontrol
		string: navigator.userAgent,
		subString: "iPhone",
		identity: "iPhone Safari",
		versionSearch: "Version"
	}],
	dataOS: [{
		string: navigator.platform,
		subString: "Win",
		identity: "Windows"
	}, {
		string: navigator.platform,
		subString: "Mac",
		identity: "Mac"
	}, {
		string: navigator.userAgent,
		subString: "iPhone",
		identity: "iPhone/iPod"
	}, {
		string: navigator.platform,
		subString: "Linux",
		identity: "Linux"
	}, { // Android için ekleme
		string: navigator.userAgent,
		subString: "Android",
		identity: "Android"
	}, { // iPadOS için ekleme
		string: navigator.userAgent,
		subString: "iPad",
		identity: "iPad"
	}]
};

///// mobile
const isMobile = {
  Android: () => /Android/i.test(navigator.userAgent),
  BlackBerry: () => /BlackBerry/i.test(navigator.userAgent),
  iOS: () => /iPhone|iPad|iPod/i.test(navigator.userAgent),
  Opera: () => /Opera Mini/i.test(navigator.userAgent),
  Windows: () => /IEMobile|Windows Phone/i.test(navigator.userAgent),
  any: function() {
    if (this.Android()) return 'Android';
    if (this.BlackBerry()) return 'BlackBerry';
    if (this.iOS()) return 'iOS';
    if (this.Opera()) return 'Opera Mini';
    if (this.Windows()) return 'Windows';
    return ""; // Hiçbir eşleşme yoksa boş string döner
  }
};

var observeDOM = (function(){
	var MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
	return function( obj, callback ){
		    if( !obj || !obj.nodeType === 1 ) return; // validation

		    if( MutationObserver ){
		      // define a new observer
		      var obs = new MutationObserver(function(mutations, observer){
		          callback(mutations);
		      })
		      // have the observer observe foo for changes in children
		      obs.observe( obj, { childList:true, subtree:true });
		    }
		    
		    else if( window.addEventListener ){
		      obj.addEventListener('DOMNodeInserted', callback, false);
		      obj.addEventListener('DOMNodeRemoved', callback, false);
		    }
	}
})();

//Convert DOM objects into selector strings (tag#id.class)
function domObjectToSelector(object){
    //If a jQuery object was passed, use the proper node
    if ( !object.nodeType ){
        object = object[0];
    }

    var selector = object.nodeName.toLowerCase();

    if ( object.id ){
        selector += '#' + object.id;
    }

    if ( object.className ){
        selector += '.' + object.className.replace(/\s/g, '.');
    }

    return selector;
}
    function setCookie(cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }

    function deleteCookie(cname){
    	setCookie(cname, "", -1);
    }

    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }

function getDataAttributes(node) {
    var d = {}, 
        re_dataAttr = /^data\-(.+)$/;

    $.each(node.get(0).attributes, function(index, attr) {
        if (re_dataAttr.test(attr.nodeName)) {
            var key = attr.nodeName.match(re_dataAttr)[1];
            d[key] = attr.nodeValue;
        }
    });

    return d;
}

function str2Bool($value){
	if(typeof($value) !== "boolean"){
	    switch($value.toLowerCase().trim()){
	        case "true": 
	        case "yes": 
	        case "1": 
	          return true;

	        case "false": 
	        case "no": 
	        case "0": 
	        case null: 
	        case undefined:
	          return false;

	        case "":
	        case "undefined":
	          return "";

	        default: 
	          return JSON.parse($value);
	    }		
	}else{
		return $value;
	}
}

function bool(value, fallback = false) {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'string') {
        switch (value.toLowerCase().trim()) {
            case 'true':
            case 'yes':
            case '1':
                return true;
            case 'false':
            case 'no':
            case '0':
            case null:
            case undefined:
            case 'undefined':
                return false;
            default:
                return fallback;
        }
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    return fallback;
}

function text2clipboard(){
    $('.clipboard').each(function(){
        $(this)
        .addClass("user-select-none")
        .wrapInner("<span class='p-1 rounded-2'/>");
    })
    $('.clipboard').click(function() {
    	var span = $(this).find("span");
        span.css("background-color", "#ddd");
        var textToCopy = $(this).text();
        var tempTextarea = $('<textarea>');
        $('body').append(tempTextarea);
        tempTextarea.val(textToCopy).select();
        document.execCommand('copy');
        tempTextarea.remove();
        span.animate({
            backgroundColor : "transparent"
          }, 1000, function() {
            // Animation complete.
          });
    });
}

$.fn.sameSize = function (width, max) {
  const prop = width ? 'width' : 'height';
  const minProp = `min-${prop}`;

  function updateSize() {
    // Önce bu fonksiyonun uygulanacağı elementleri seç
    const elements = this;

    if (!elements || elements.length === 0) {
      return;
    }

    // Daha önce atanmış stil değerlerini kaldır
    elements.css({
      [prop]: '',
      [minProp]: ''
    });

    // Max genişliği saptayıp elementlere atıyoruz
    const maxSize = max !== undefined ? max : Math.max(...elements.map(function () {
      return $(this)[prop]();
    }).get());

    const cssProperties = {
      [minProp]: maxSize
    };

    // Eğer pencere genişliği belirtilen boyuttan büyükse
    var breakpoint = elements.attr('class').match(/nav-equal-([a-zA-Z]+)/);
    if (breakpoint) {
      breakpoint = breakpoint[1];
    } else {
      breakpoint = "sm";
    }
    breakpoint = getCssValue("--bs-breakpoint-"+breakpoint);

    if ($(window).width() >= breakpoint) {
      cssProperties[prop] = maxSize;
    }

    // Yeni stil değerlerini ata
    elements.css(cssProperties);
    elements.addClass("nav-equalized");
  }

  // İlk çalıştırma
  updateSize.call(this);

  // Resize işlemi sırasında kontrol yapmak için olay dinleyicisi eklenir
  $(window).on('resize', () => {
    updateSize.call(this);
  });

  return this;
};

function getCssValue(property){
	var returnValue = "";
	var obj = getComputedStyle(document.documentElement);
    var value = obj.getPropertyValue(property).trim();
	if (value) {
	  returnValue = parseFloat(value);
	}
	return returnValue;
}

function fitToContainer() {
    const iframes = document.querySelectorAll('.h-container');
    iframes.forEach(iframe => {
        const container = iframe.parentElement;
        iframe.style.height = container.offsetHeight + 'px';
    });
}


function resizeDebounce(func, wait) {
	let timeout;
	return function(...args) {
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(this, args), wait);
	};
}

$.fn.fitEmbedBackground = function() {
        return this.each(function() {
            var container = $(this),
                iframe = container.find('iframe');

            // Video boyutunu yeniden hesapla
            function resizeVideo() {
                var containerWidth = container.width(),
                    containerHeight = container.height(),
                    containerRatio = containerWidth / containerHeight,
                    videoRatio = 16 / 9;

                // Genişlik/Yükseklik oranlarına göre iframe boyutunu ayarla
                if (containerRatio > videoRatio) {
                    iframe.css({
                        width: containerWidth + 'px',
                        height: (containerWidth / videoRatio) + 'px'
                    });
                } else {
                    iframe.css({
                        width: (containerHeight * videoRatio) + 'px',
                        height: containerHeight + 'px'
                    });
                }
            }

            // İlk çalıştırma
            resizeVideo();

            // Gecikmeli resize
            var debounce = resizeDebounce(resizeVideo, 10);

            // Resize ve fullscreen olaylarını dinle
            $(window).on('resize', debounce);
            $(document).on(
                'fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange',
                debounce
            );

        });
    };