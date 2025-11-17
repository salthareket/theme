$ = jQuery.noConflict();


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

function updateDonutChart(el, percent, donut) {
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



/*
(function($) {
    $(document).ready(function() {
        // 'unique' sınıfına sahip select alanlarını seç
        var $uniqueSelects = $('.acf-field-select.unique select');

        $uniqueSelects.each(function() {
            var $thisSelect = $(this);

            // Mevcut seçeneği yakala
            var selectedValue = $thisSelect.val();

            // Diğer 'unique' select alanlarını dolaş
            $uniqueSelects.not($thisSelect).each(function() {
                var $otherSelect = $(this);

                // Diğer select alanındaki seçili değeri al
                var otherSelectedValue = $otherSelect.val();

                // Seçili değer aynıysa
                if (selectedValue === otherSelectedValue) {
                    // Bu select alanındaki seçenekleri devre dışı bırak
                    $thisSelect.find('option[value="' + selectedValue + '"]').attr('disabled', true);
                }
            });
        });
    });
})(jQuery);

*/




jQuery(document).ready(function($){


 // synchronize_child_and_parent_category($);
    $("#gmwsvs_hide_parent_product").prop("disabled",false).prop("checked", true);
    $(".gmwsvs_exclude").css("opacity",1).css("pointer-events","auto");

    if($(".chart-donut").length>0){
          $(".chart-donut").each(function(){
              var percent = $(this).attr("data-percent");
              updateDonutChart($(this), percent, true);
          });
    }

    if($(".star-rating-readonly-ui").length>0){
        $(".star-rating-readonly-ui").each(function(){
            var stars = $(this).data("stars") || 5;
            var value = $(this).data("value");
            $(this).html(get_star_rating_readonly(stars, value, "", "", "" ));
        });
    }


    text2clipboard();


    if ( typeof acf === 'undefined' ) {
        return;
    }


    // show event description on select2 field options
    acf.addAction('select2_init', function( $select, args, settings, field ){
        var field_name = field.$el.data("name");
        if(field_name == "notification_event"){

            $select.find("option").each(function(){
                let option = jQuery(this);
                let text   = option.text().split("|");
                    option.text(text[0]);
                    option.attr("data-description", text[1])
            });

            args['templateResult'] = function (state) {
                debugJS(state);
                if (!state.id) {
                    return state.text;
                } else {
                    let title = state.text;
                    let description = $(state.element).data("description");
                    let $state = '<div><strong>' + title + '</strong></div><div>' + description + '</div>'
                    return jQuery($state);
                }
            };
        }
        $select.select2(args);   
    });
    

    

    // show active / passive carriers on accordion header title
    function notification_carriers_activate($container){
        if($container.length > 0){
            var notification_carriers = ["notification_email", "notification_alert", "notification_sms"];
            for(var i=0;i<notification_carriers.length;i++){
                var switcher = $container.find(".acf-row:not(.acf-clone)").find(".acf-field[data-name='"+notification_carriers[i]+"']");
                if(switcher.length > 0){
                    switcher.each(function(){
                        var accordion = $(this).closest("[data-type='accordion']");
                        var checkbox = $(this).find("input[type='checkbox']");
                        checkbox.on("change", function(){
                            if($(this).is(":checked")){
                                accordion.find(".acf-accordion-title").addClass("bg-success text-white");
                            }else{
                                accordion.find(".acf-accordion-title").removeClass("bg-success text-white");
                            }
                        }).trigger("change");
                    });
                }        
            }            
        }
    }
    var $repeater_obj = $(".acf-field[data-name='notifications']");
    if($repeater_obj.length > 0){
        $repeater_obj = acf.getField($repeater_obj.data("key"));
        notification_carriers_activate($($repeater_obj.$el));
        $repeater_obj.$el
        .on('click', '.acf-repeater-add-row', function() {
            notification_carriers_activate($($repeater_obj.$el));
        });
        notification_filters();      
    }


    function notification_filters(){
        //alert("sss")
        var filters = $(".acf-field[data-name='notifications_filter']");
        if(filters.length > 0){
            var roles = filters.find(".acf-field[data-name='notification_role_filter'] select");
            var events = filters.find(".acf-field[data-name='notification_event_filter'] select");
            roles.on("change", function(){
                var $args = {role: roles.val(), event: events.val()};
                notification_filters_apply($args);
            });
            events.on("change", function(){
                var $args = {role: roles.val(), event: events.val()};
                notification_filters_apply($args);
            });
        }
    }
    function notification_filters_apply($args) {
        debugJS($args);
        var $repeater_obj = $(".acf-field[data-name='notifications']");
        var $rows = $repeater_obj.find(".acf-row:not(.acf-clone)");
        //debugJS($rows)
        $rows.each(function() {
            var $row = $(this);
            //debugJS($row);
            var role = $row.find('.acf-field[data-name*="notification_role"] select').val();
            var event = $row.find('.acf-field[data-name*="notification_event"] select').val();
            debugJS($args, role, event);
            //if(($args.role == "" && $args.event == "") || role == $args.role || event == $args.event){
            if(($args.role === "" || role == $args.role) && ($args.event === "" || event == $args.event)){
                $row.removeClass("d-none");
            }else{
                $row.addClass("d-none");
            }
            /*
            if ((selectedRole === 'show-all' || role === selectedRole) && (selectedEvent === 'show-all' || event === selectedEvent)) {
                $row.show();
            } else {
                $row.hide();
            }*/
        });
    }

    








    // make repeater field unique : just add "unique" class to select field
    function updateUniqueSelect($repeater) {
            var $repeater_obj = acf.getField($repeater.data("key"));
            var $select = $repeater.find(".unique select");
            var $maxItems = $select.find("option").length;
            
            $repeater_obj.$el
            .on('change', '.unique select', function() {
                $repeater.find('.unique select option').prop("disabled", false).removeClass("d-none");
                $repeater.find('.acf-row:not(.acf-clone)').each(function() {
                    var $currentRow = $(this);
                    var $currentSelect = $currentRow.find('.unique select');
                    var selectedValue = $currentSelect.val();
                    if (selectedValue) {
                        $repeater.find('.unique select option[value="' + selectedValue + '"]:not(:selected)').prop("disabled", true).addClass("d-none");;
                    }
                });
            })
            .on('click', '.acf-repeater-add-row', function() {
                var rows = $repeater.find('.acf-row:not(.acf-clone)');
                var $newRow = $(this).closest(".acf-repeater").find(".acf-row:last-child");
                
                var selectedOptions = [];
                rows.each(function() {
                    var $currentRow = $(this);
                    var $currentSelect = $currentRow.find('.unique select');
                    var selectedValue = $currentSelect.val();
                    if (selectedValue) {
                        selectedOptions.push(selectedValue);
                    }
                });
                
                $newRow.find('.unique select option').prop("disabled", false).removeClass("d-none");
                $newRow.find('.unique select option').each(function() {
                    var optionValue = $(this).val();
                    if (selectedOptions.includes(optionValue)) {
                        $(this).prop("disabled", true);
                    }
                });
                $newRow.find('.unique select option:not(:disabled)').first().prop("selected", true).removeClass("d-none");
                
                var currentItems = rows.length;
                if (currentItems >= $maxItems) {
                    $repeater_obj.$el.find('.acf-repeater-add-row').addClass('disabled');
                } else {
                    $repeater_obj.$el.find('.acf-repeater-add-row').removeClass('disabled');
                }
                $newRow.find('.unique select').trigger('change');
            });
            
            var key = $select.closest(".acf-field").data("key");
            acf.addAction('remove_field/key=' + key, function(item) {
                $repeater.find('.unique select option').prop("disabled", false).removeClass("d-none");
                var rows = $repeater.find('.acf-row:not(.acf-clone)');
                var $deletedRow = item.$el.closest('.acf-row');
                var deletedRowSelectedValue = item.$el.find('.unique select').val();
                if (deletedRowSelectedValue) {
                    rows.each(function() {
                        var $currentRow = $(this);
                        var $currentSelect = $currentRow.find('.unique select');
                        var selectedValue = $currentSelect.val();
                        
                        if (selectedValue && selectedValue !== deletedRowSelectedValue) {
                            $repeater.find('.unique select option[value="' + selectedValue + '"]').prop("disabled", true).addClass("d-none");
                        }
                    });
                } else {
                    rows.each(function() {
                        var $currentRow = $(this);
                        var $currentSelect = $currentRow.find('.unique select');
                        var selectedValue = $currentSelect.val();
                        
                        if (selectedValue) {
                            var $otherRowSelect = $deletedRow.siblings('.acf-row').find('.unique select');
                            var otherSelectedValue = $otherRowSelect.val();
                            if (selectedValue === otherSelectedValue) {
                                $repeater.find('.unique select option[value="' + selectedValue + '"]').prop("disabled", true).addClass("d-none");
                            }
                        }
                    });
                }
            });
    }
    var unique_field = $(".acf-field-repeater .acf-row.acf-clone").find(".unique select");
    if (unique_field.length > 0) {
        var $repeater = unique_field.closest(".acf-field-repeater");
        updateUniqueSelect($repeater);
    }


});

jQuery(document).ready(function($) {
    init_swiper();
});