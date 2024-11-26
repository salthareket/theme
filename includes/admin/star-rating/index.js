if($(".star-rating-readonly-ui").length>0){
        $(".star-rating-readonly-ui").each(function(){
            var stars = $(this).data("stars") || 5;
            var value = $(this).data("value");
            $(this).html(get_star_rating_readonly(stars, value, "", "", "" ));
        });
    }