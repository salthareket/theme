function isset(variable) {
    return typeof variable !== 'undefined' && variable !== null;
}

function isJson(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

function inArray(target, array){
      for(var i = 0; i < array.length; i++) {
        if(array[i] === target){
          return true;
        }
      }
      return false; 
}

var isEqual = function (value, other) {
    // 1. Tip kontrolü
    var type = Object.prototype.toString.call(value);
    if (type !== Object.prototype.toString.call(other)) return false;

    // 2. Sadece Array ve Object ise derinliğe gir, değilse direkt karşılaştır
    if (['[object Array]', '[object Object]'].indexOf(type) < 0) {
        return value === other;
    }

    // 3. Uzunluk kontrolü
    var valueLen = type === '[object Array]' ? value.length : Object.keys(value).length;
    var otherLen = type === '[object Array]' ? other.length : Object.keys(other).length;
    if (valueLen !== otherLen) return false;

    // 4. İçerik karşılaştırma (Helper)
    var areItemsEqual = function (item1, item2) {
        var itemType = Object.prototype.toString.call(item1);
        
        // Eğer içerdeki de Array/Object ise recursive (öz yinelemeli) devam et
        if (['[object Array]', '[object Object]'].indexOf(itemType) >= 0) {
            return isEqual(item1, item2);
        }
        
        // Fonksiyon kontrolü
        if (itemType === '[object Function]') {
            return item1.toString() === item2.toString();
        }
        
        // Diğer her şey (String, Number, Date vb.)
        return item1 === item2;
    };

    // 5. Döngüler
    if (type === '[object Array]') {
        for (var i = 0; i < valueLen; i++) {
            if (areItemsEqual(value[i], other[i]) === false) return false;
        }
    } else {
        for (var key in value) {
            // KRİTİK DÜZELTME: Object.prototype üzerinden çağırıyoruz
            if (Object.prototype.hasOwnProperty.call(value, key)) {
                // Karşı tarafta bu key var mı ve içerikleri aynı mı?
                if (Object.prototype.hasOwnProperty.call(other, key) === false) return false;
                if (areItemsEqual(value[key], other[key]) === false) return false;
            }
        }
    }

    return true;
};

function arrayRemove(arr, value) {
   return arr.filter(function(ele){
       return ele != value;
   });
}

function removeDuplicates(array) {
	  var unique = {};
	  array.forEach(function(i) {
	    if(!unique[i]) {
	      unique[i] = true;
	    }
	  });
	  return Object.keys(unique);
}

function js_array_to_php_array (a){
    var a_php = "";
    var total = 0;
    for (var key in a){
        ++ total;
        a_php = a_php + "s:" +
                String(key).length + ":\"" + String(key) + "\";s:" +
                String(a[key]).length + ":\"" + String(a[key]) + "\";";
    }
    a_php = "a:" + total + ":{" + a_php + "}";
    return a_php;
}



// 1. Anahtar, değer veya her ikisine göre objeleri bulur
function getObjects(obj, key, val) {
    var objects = [];
    for (var i in obj) {
        // jQuery 4.0 ve güvenli kontrol
        if (!Object.prototype.hasOwnProperty.call(obj, i)) continue;

        if (typeof obj[i] == 'object' && obj[i] !== null) {
            objects = objects.concat(getObjects(obj[i], key, val));
        } else {
            // Anahtar eşleşiyor mu ve (değer eşleşiyor mu VEYA değer boş mu geçilmiş?)
            if (i == key && (obj[i] == val || val === '')) {
                objects.push(obj);
            } 
            // Sadece değer üzerinden arama (anahtar belirtilmemişse)
            else if (obj[i] == val && key === '') {
                if (objects.indexOf(obj) === -1) {
                    objects.push(obj);
                }
            }
        }
    }
    return objects;
}

// 2. Belirli bir anahtara ait tüm değerleri toplar
function getValues(obj, key) {
    var objects = [];
    for (var i in obj) {
        if (!Object.prototype.hasOwnProperty.call(obj, i)) continue;

        if (typeof obj[i] == 'object' && obj[i] !== null) {
            objects = objects.concat(getValues(obj[i], key));
        } else if (i == key) {
            objects.push(obj[i]);
        }
    }
    return objects;
}

// 3. Belirli bir değere sahip tüm anahtarları toplar
function getKeys(obj, val) {
    var objects = [];
    for (var i in obj) {
        if (!Object.prototype.hasOwnProperty.call(obj, i)) continue;

        if (typeof obj[i] == 'object' && obj[i] !== null) {
            objects = objects.concat(getKeys(obj[i], val));
        } else if (obj[i] == val) {
            objects.push(i);
        }
    }
    return objects;
}


function removeEmptyValueObjects(obj){
  for (var propName in obj) {
    if (obj[propName] === null || obj[propName] === undefined || obj[propName] === "") {
      delete obj[propName];
    }
  }
  return obj
}