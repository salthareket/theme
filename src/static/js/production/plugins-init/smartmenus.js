function init_smartmenus(){
	$('.navbar-nav-main')
    .smartmenus({
        showOnClick: false,
        hideOnClick: false,
        bootstrapHighlightClasses: "show",
        keepHighlighted: true,
        subIndicators: false
    })
    .on('show.smapi', function (e, menu) {
        // 1️⃣ Açık olan diğer dropdown’ları kapat
        var opened = $(this).find(".dropdown.show .dropdown-menu").not($(menu));
        if (opened.length) {
            opened.closest(".show")
                  .find(".dropdown-toggle")
                  .trigger("click");
        }

        // 2️⃣ Body class ve collapse yönetimi
        $("body").addClass("menu-dropdown-open");
        $(".collapse-search.show").collapse("hide");
    })
    .on('hide.smapi', function (e, menu) {
        // Sadece level 1 menü kapanınca body class’ı kaldır
        if ($(menu).parent().data("menu-level") == 1) {
            $("body").removeClass("menu-dropdown-open");
        }
    })
    .find("a.has-submenu").on("click", function() {
        if (!$(this).hasClass("highlighted")) {
            //$.SmartMenus.hideAll();
            obj.find(".navbar-nav").smartmenus('menuHideAll');
        }
    });
}