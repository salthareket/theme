function form_control_datepicker(){
    var token_init = "form-control-datepicker-init";
    $('.input-group.date, .datepicker').not("."+token_init).each(function(){
        $(this).addClass(token_init);
        var options = {
            language: root.lang,
            weekStart: 1,
            todayHighlight: false,
            //startView: "years",
            autoclose: true,
            //startDate : new Date()
            templates : {
                leftArrow: '<i class="fa fa-angle-left"></i>',
                rightArrow: '<i class="fa fa-angle-right"></i>'
            },
            /*format: {
                toDisplay: function (date, format, language) {
                    var d = new Date(date);
                    return moment(d).format('YYYY-MM-DD');
                },
                toValue: function (date, format, language) {
                    var d = new Date(date);
                    return moment(d).format('YYYY-MM-DD');
                }
            }*/
        };
        if($(this).hasClass("form-control-date-min-today")){
           options["startDate"] =  new Date();
        }
        if($(this).hasClass("form-control-date-monthyear")){
            options["maxViewMode"] = "years";
            options["minViewMode"] = "months";
            options["format"]      = "mm.yyyy";
        }
        var picker = $(this)
        .datepicker(options)
        .on("changeDate", function(e) {
            var obj = $(e.target);
            debugJS($(e.target));
            if(obj.hasClass("date-start")){
                var dateRelated = $(obj.attr("data-related"))
                var startDate = e.date;
                if(typeof startDate === "undefined"){
                    startDate = obj.datepicker("getDate");
                }
                var endDate   = dateRelated.datepicker("getDate");
                var startDateParsed = new Date(startDate);
                var endDateParsed = new Date(endDate);
                if(startDateParsed > endDateParsed && !IsBlank(endDate)){
                    dateRelated.datepicker("clearDates");
                }
                dateRelated.datepicker("setStartDate", startDate);
                debugJS(dateRelated);
                debugJS(startDate);
            }
        })
        .on('hide', function(e) {
            e.preventDefault();
            e.stopPropagation();
        })
        //.trigger("changeDate");
        if(!IsBlank(picker.data("date-start"))){
            startDate = picker.data("date-start");
            picker.datepicker("setStartDate", startDate);
        }
        if(!IsBlank(picker.data("date-end"))){
            endDate = new Date(picker.data("date-end"));
            picker.datepicker("setEndDate", endDate);
         }
    });
    $('.input-daterange').not("."+token_init).each(function(){
        $(this).addClass(token_init);
        $(this)
        .datepicker({
            maxViewMode: 0,
            weekStart: 1,
            todayBtn: "linked",
            language: root.lang,
            //startView: "years",
            autoclose: true
         });
    })  
}