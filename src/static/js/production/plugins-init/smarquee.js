function init_smarquee() {
    console.log("smarquee");
    var token_init = "smarquee-init";
    $(".smarquee").not("."+token_init).each(function() {
        let id = $(this).attr("id");
        if (IsBlank(id)) {
            id = "smarquee_" + generateCode(5);
            $(this).attr("id", id)
        }
        let smarquee = new Smarquee({
            selector: "#" + id,
            velocity: $(this).data("velocity") || 100,
            styleOptions: {
                scrollingTitleMargin: 24,
                animationName: 'marquee',
                timingFunction: 'linear',
                iterationCount: 'infinite',
                fillMode: 'none',
                playState: 'running',
                delay: '0',
                pausePercent: 30,
            }
        });
        smarquee.init();
        if($(this).data("pause-on-hover")){
            $(this).on('mouseover', function() {
                smarquee.pause()
            }).on('mouseout', function() {
                smarquee.play()
            });            
        }
        $(this).addClass("show "+token_init);
        smarquee.play();
        console.log(smarquee)
    })
}