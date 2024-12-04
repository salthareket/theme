if($(".chart-donut").length>0){
          $(".chart-donut").each(function(){
              var percent = $(this).attr("data-percent");
              updateDonutChart($(this), percent, true);
          });
    }