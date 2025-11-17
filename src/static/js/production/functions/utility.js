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

const BrowserDetect = {
    browser: "Unknown",
    version: "Unknown",
    OS: "Unknown",
    init: function() {
        const ua = navigator.userAgent;
        const platform = navigator.platform;

        // Browser detection (modern, hızlı)
        if (/Edg\/\d+/.test(ua)) this.browser = "Edge";
        else if (/OPR\/\d+/.test(ua)) this.browser = "Opera";
        else if (/Chrome\/\d+/.test(ua)) this.browser = "Chrome";
        else if (/Firefox\/\d+/.test(ua)) this.browser = "Firefox";
        else if (/Safari\/\d+/.test(ua) && !/Chrome/.test(ua)) this.browser = "Safari";
        else if (/MSIE|Trident/.test(ua)) this.browser = "IE";

        // Browser version detection
        const versionMatch = ua.match(/(Edg|OPR|Chrome|Firefox|Safari|MSIE|rv:)\D*(\d+(\.\d+)?)/);
        if(versionMatch) this.version = parseFloat(versionMatch[2]);

        // OS detection (basit ve hızlı)
        if (/Win/i.test(platform)) this.OS = "Windows";
        else if (/Mac/i.test(platform)) this.OS = "Mac";
        else if (/iPhone|iPad|iPod/i.test(ua)) this.OS = "iOS";
        else if (/Android/i.test(ua)) this.OS = "Android";
        else if (/Linux/i.test(platform)) this.OS = "Linux";

        return this;
    }
};
const isMobile = {
    Android: () => /Android/i.test(navigator.userAgent),
    iOS: () => /iPhone|iPad|iPod/i.test(navigator.userAgent),
    Opera: () => /Opera Mini/i.test(navigator.userAgent),
    Windows: () => /IEMobile|Windows Phone/i.test(navigator.userAgent),
    any: function() {
        if (this.Android()) return "Android";
        if (this.iOS()) return "iOS";
        if (this.Opera()) return "Opera Mini";
        if (this.Windows()) return "Windows";
        return "";
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

$.fn.sameSize = function (width, max, keepMin = false) {
  const $elements = this;
  if (!$elements || $elements.length === 0) return this;

  const prop = width ? 'width' : 'height';
  const minProp = `min-${prop}`;

  function getBreakpointPx($els) {
    const m = ($els.eq(0).attr('class') || '').match(/nav-equal-([a-zA-Z]+)/);
    const bpKey = m ? m[1] : 'sm';
    // CSS custom property’den px değerini oku (örn. --bs-breakpoint-sm)
    return getCssValue(`--bs-breakpoint-${bpKey}`) || 576; // fallback
  }

  function computeMaxSize($els) {
    if (max !== undefined) return max;
    // Ölçmeden önce temizle ki doğru ölçelim
    $els.css({ [prop]: '', [minProp]: '' });
    return Math.max(...$els.map(function () {
      return $(this)[prop](); // width() / height()
    }).get());
  }

  function applyEqualize() {
    if (!$elements || $elements.length === 0) return;

    const bp = getBreakpointPx($elements);
    const vw = $(window).width();

    // her seferinde başta temizle
    $elements.css({ [prop]: '', [minProp]: '' });

    if (vw >= bp) {
      const maxSize = computeMaxSize($elements);
      const css = { [minProp]: maxSize, [prop]: maxSize };
      $elements.css(css).addClass('nav-equalized');
    } else {
      // ALTINDA: her şeyi temizle (keepMin true ise minProp tut)
      if (keepMin && max !== undefined) {
        $elements.css({ [minProp]: max })//.removeClass('nav-equalized');
      } else {
        $elements.css({ [prop]: '', [minProp]: '' })//.removeClass('nav-equalized');
      }
    }
  }

  // İlk çalıştırma
  applyEqualize();

  // Resize: raf + namespaced
  let rafId = null;
  $(window).off('resize.sameSize').on('resize.sameSize', () => {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      rafId = null;
      applyEqualize();
    });
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

function ajaxResponseFilter(input) {
  if (typeof input !== "string") return null;

  // Trim + BOM + olası XSSI prefix temizliği
  let str = input.replace(/^\uFEFF/, "").trim();
  str = str.replace(/^\)\]\}',?\s*/, ""); // )]}',\n... gibi

  // 1) Direkt parse etmeyi dene
  try {
    return JSON.parse(str);
  } catch (_) {}

  // 2) İlk { veya [ konumunu bul
  const start = (() => {
    const iObj = str.indexOf("{");
    const iArr = str.indexOf("[");
    if (iObj === -1) return iArr;
    if (iArr === -1) return iObj;
    return Math.min(iObj, iArr);
  })();

  if (start === -1) {
    console.error("Geçerli JSON başlangıcı bulunamadı.");
    return null;
  }

  // 3) Karakter karakter gezip parantezleri dengele
  const openChar = str[start];
  const closeChar = openChar === "{" ? "}" : "]";
  let depth = 0;
  let inString = false;
  let escape = false;
  let end = -1;

  for (let i = start; i < str.length; i++) {
    const ch = str[i];

    if (inString) {
      if (escape) {
        escape = false;
      } else if (ch === "\\") {
        escape = true;
      } else if (ch === "\"") {
        inString = false;
      }
      continue;
    }

    if (ch === "\"") {
      inString = true;
      continue;
    }

    if (ch === openChar) depth++;
    else if (ch === closeChar) depth--;

    if (depth === 0) { end = i; break; }
  }

  if (end === -1) {
    console.error("JSON kapanış parantezi bulunamadı.");
    return null;
  }

  const possibleJson = str.slice(start, end + 1);

  try {
    return JSON.parse(possibleJson);
  } catch (e) {
    console.error("JSON parse hatası:", e, "\nKesit:", possibleJson);
    return null;
  }
}


function resizeDebounce(func, wait) {
	let timeout;
	return function(...args) {
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(this, args), wait);
	};
}
