//nestedObjectAssign
!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define("nestedObjectAssign",[],t):"object"==typeof exports?exports.nestedObjectAssign=t():e.nestedObjectAssign=t()}(this,function(){return function(e){function t(n){if(r[n])return r[n].exports;var o=r[n]={exports:{},id:n,loaded:!1};return e[n].call(o.exports,o,o.exports,t),o.loaded=!0,o.exports}var r={};return t.m=e,t.c=r,t.p="",t(0)}([function(e,t,r){"use strict";function n(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function o(e){for(var t=arguments.length,r=Array(t>1?t-1:0),c=1;c<t;c++)r[c-1]=arguments[c];if(!r.length)return e;var u=r.shift();if((0,i.isObject)(e)&&(0,i.isObject)(u))for(var f in u)(0,i.isObject)(u[f])?(e[f]||Object.assign(e,n({},f,{})),o(e[f],u[f])):(0,s.isArray)(u[f])?(e[f]||Object.assign(e,n({},f,[])),e[f]=e[f].concat(u[f])):Object.assign(e,n({},f,u[f]));return o.apply(void 0,[e].concat(r))}Object.defineProperty(t,"__esModule",{value:!0}),t.default=o;var i=r(1),s=r(2);e.exports=t.default},function(e,t){"use strict";function r(e){return e&&"object"===("undefined"==typeof e?"undefined":n(e))&&!Array.isArray(e)}Object.defineProperty(t,"__esModule",{value:!0});var n="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e};t.isObject=r},function(e,t){"use strict";function r(e){return e&&Array.isArray(e)}Object.defineProperty(t,"__esModule",{value:!0}),t.isArray=r}])});

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
    var type = Object.prototype.toString.call(value);
    if (type !== Object.prototype.toString.call(other)) return false;
    if (['[object Array]', '[object Object]'].indexOf(type) < 0) return false;
    var valueLen = type === '[object Array]' ? value.length : Object.keys(value).length;
    var otherLen = type === '[object Array]' ? other.length : Object.keys(other).length;
    if (valueLen !== otherLen) return false;
    var compare = function (item1, item2) {
        var itemType = Object.prototype.toString.call(item1);
        if (['[object Array]', '[object Object]'].indexOf(itemType) >= 0) {
            if (!isEqual(item1, item2)) return false;
        }else {
            if (itemType !== Object.prototype.toString.call(item2)) return false;
            if (itemType === '[object Function]') {
                if (item1.toString() !== item2.toString()) return false;
            } else {
                if (item1 !== item2) return false;
            }
        }
    };
    if (type === '[object Array]') {
        for (var i = 0; i < valueLen; i++) {
            if (compare(value[i], other[i]) === false) return false;
        }
    } else {
        for (var key in value) {
            if (value.hasOwnProperty(key)) {
                if (compare(value[key], other[key]) === false) return false;
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



//return an array of objects according to key, value, or key and value matching
function getObjects(obj, key, val) {
    var objects = [];
    for (var i in obj) {
        if (!obj.hasOwnProperty(i)) continue;
        if (typeof obj[i] == 'object') {
            objects = objects.concat(getObjects(obj[i], key, val));    
        } else 
        //if key matches and value matches or if key matches and value is not passed (eliminating the case where key matches but passed value does not)
        if (i == key && obj[i] == val || i == key && val == '') { //
            objects.push(obj);
        } else if (obj[i] == val && key == ''){
            //only add if the object is not already in the array
            if (objects.lastIndexOf(obj) == -1){
                objects.push(obj);
            }
        }
    }
    return objects;
}
//return an array of values that match on a certain key
function getValues(obj, key) {
    var objects = [];
    for (var i in obj) {
        if (!obj.hasOwnProperty(i)) continue;
        if (typeof obj[i] == 'object') {
            objects = objects.concat(getValues(obj[i], key));
        } else if (i == key) {
            objects.push(obj[i]);
        }
    }
    return objects;
}
//return an array of keys that match on a certain value
function getKeys(obj, val) {
    var objects = [];
    for (var i in obj) {
        if (!obj.hasOwnProperty(i)) continue;
        if (typeof obj[i] == 'object') {
            objects = objects.concat(getKeys(obj[i], val));
        } else if (obj[i] == val) {
            objects.push(i);
        }
    }
    return objects;
}


function remove_array_item_by_value(item){
    var index = array.indexOf(item);
    if (index !== -1) {
      array.splice(index, 1);
    }
}


function removeEmptyValueObjects(obj){
  for (var propName in obj) {
    if (obj[propName] === null || obj[propName] === undefined || obj[propName] === "") {
      delete obj[propName];
    }
  }
  return obj
}