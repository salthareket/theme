function countdown_item(callback){
    $(".countdown").each(function(){
        $(this).append("<div class='countdown-text'/>");
        var seconds = $(this).data("seconds");
        var countdown = moment().add(seconds, 'seconds').format("YYYY-MM-DD HH:mm:ss");
        var text = "<span>Kalan Süre</span>%M:%S";
        $(this)
        .find(".countdown-text")
        .countdown(countdown, {elapse: false})
        .on('update.countdown', function(e) {
            var $this = $(this);
                $this.html(e.strftime(text));
        })
        .on('finish.countdown', function(e) {
        	var $this = $(this);
        	$this.html("<span class='text-danger'>Süre Doldu</span>");
        	if(!IsBlank(callback)){
        		window[callback]();
        	}
        })
        if($(this).hasClass("countdown-circle")){
            $(this).append('<svg width="130" height="130" xmlns="http://www.w3.org/2000/svg">' +
                '<g>' +
                   '<circle class="circle-bg" r="60" cy="65" cx="65" stroke-width="4" stroke="#eeeeee" fill="none"/>' +
                   '<circle class="circle" r="60" cy="65" cx="65" stroke-width="8" stroke="#c4d600" fill="none"/>' +
                '</g>' +
            '</svg>');
            var initialOffset = 390;
            var i = 1;
            var $circle =  $(this).find('.circle')
            $circle.css('stroke-dashoffset', initialOffset-(1*(initialOffset/seconds)));
            var interval = setInterval(function() {
                //$('h2').text(i);
                if (i == seconds) {    
                    clearInterval(interval);
                    return;
                }
                debugJS(initialOffset-((i+1)*(initialOffset/seconds)))
                $circle.css('stroke-dashoffset', initialOffset-((i+1)*(initialOffset/seconds)));
                i++;  
            }, 1000);
        }
    });
}