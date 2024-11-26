function IsBlank(txt){
  var stat=false;
  if(typeof(txt)==="undefined" || txt==null || txt=="null" || txt==undefined  || txt=="undefined"|| txt==""){
    stat= true;
  };
  return stat;
};

function synchronize_child_and_parent_category($) {
  $('.categorychecklist').find('input').each(function(index, input) {
    $(input).bind('change', function() {
      var checkbox = $(this);
      var is_checked = $(checkbox).is(':checked');
     
      if(is_checked) {
        $(checkbox).closest(".categorychecklist").find('input').not($(checkbox)).removeAttr('checked');
        //checkbox.attr('checked', 'checked');
        $(checkbox).parents('li').children('label').children('input').attr('checked', 'checked');
      } else {
        $(checkbox).parentsUntil('ul').find('input').removeAttr('checked');
      }
    });
  });
}

function updateDonutChart (el, percent, donut) {
    percent = Math.round(percent);
    if (percent > 100) {
        percent = 100;
    } else if (percent < 0) {
        percent = 0;
    }
    var deg = Math.round(360 * (percent / 100));

    if (percent > 50) {
         el.find('.pie').css('clip', 'rect(auto, auto, auto, auto)');
         el.find('.right-side').css('transform', 'rotate(180deg)');
    } else {
         el.find('.pie').css('clip', 'rect(0, 1em, 1em, 0.5em)');
         el.find('.right-side').css('transform', 'rotate(0deg)');
    }
    if (donut) {
         el.find('.right-side').css('border-width', '0.1em');
         el.find('.left-side').css('border-width', '0.1em');
         el.find('.shadow').css('border-width', '0.1em');
    } else {
         el.find('.right-side').css('border-width', '0.5em');
         el.find('.left-side').css('border-width', '0.5em');
         el.find('.shadow').css('border-width', '0.5em');
    }
     //el.find('.num').text(percent);
     el.find('.left-side').css('transform', 'rotate(' + deg + 'deg)');
}


function get_star_rating_readonly($stars, $value, $count, $star_front, $star_back ){
    $stars = parseInt($stars);
    $stars = IsBlank($stars)||isNaN($stars)?5:$stars;
    $value = parseFloat($value);
    if(typeof $count === "undefined"){
      $count="";
    }else{
      if($count>0){
         $count='<span class="count">('+$count+')</span>';
      }else{
         $count = "";
      }
    }
    var $className = "";
    if($value == 0 ){
       //return "";*  
       $className = " not-reviewed ";
    }
    $value = IsBlank($value)||isNaN($value)?0:$value;
    $star_front = IsBlank($star_front)?"fas fa-star":$star_front;
    $star_back = IsBlank($star_back)?"fas fa-star":$star_back;
    var $percentage = (100 * $value)/$stars;
    var $code ='<div class="star-rating star-rating-readonly '+$className+'" title="' + $value + '">' +
                    '<div class="back">';
                            for ($i = 1; $i <= $stars; $i++) {
                                 $code += '<i class="'+$star_back+'" aria-hidden="true"></i>';
                            };
                      $code += '<div class="front" style="width:'+$percentage+'%;">';
                                   for ($i = 1; $i <= $stars; $i++) {
                                        $code += '<i class="'+$star_front+'" aria-hidden="true"></i>';
                                   };
                      $code += '</div>' +
                    '</div>' +
                    '<div class="sum">'+$value.toFixed(1) + $count +'</div>' +
               '</div>';
    return $code;
}

function text2clipboard(){
    $('.clipboard').each(function(){
        $(this)
        .addClass("user-select-none")
        .wrapInner("<span class='p-1 rounded-2'/>");
    })
    $('.clipboard').click(function() {
        $(this).find("span").css("background-color", "#ddd");
        var textToCopy = $(this).text();
        var tempTextarea = $('<textarea>');
        $('body').append(tempTextarea);
        tempTextarea.val(textToCopy).select();
        document.execCommand('copy');
        tempTextarea.remove();
        $(this).find("span").animate({
            backgroundColor : "transparent"
          }, 1000, function() {
            // Animation complete.
          });
    });
}
