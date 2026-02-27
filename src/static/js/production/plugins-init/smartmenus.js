function init_smartmenus() {
    var $menu = $('.navbar-nav-main');
    if (!$menu.length) return;

    // 1️⃣ Onepage Home Link Ayarı (Daha önce manuel yapıyordun, buraya aldık)
    $menu.find(".link-onepage-home a").attr("href", "#home");

    $menu.smartmenus({
        showOnClick: false,
        hideOnClick: false,
        bootstrapHighlightClasses: "show",
        keepHighlighted: true,
        subIndicators: false
    })
    .on('show.smapi', function(e, menu) {
        // 2️⃣ Diğer açık dropdown'ları kapat (SmartMenus API kullanarak)
        // Senin manuel click tetiklemene gerek yok, API ile daha güvenli
        var $obj = $(this).data('smartmenus');
        var $opened = $(this).find(".dropdown.show").not($(menu).parent());
        
        if ($opened.length) {
            $obj.menuHideAll(); // Tümünü temizle
        }

        // 3️⃣ Yan etkileri yönet (Body class ve Arama çubuğu)
        $("body").addClass("menu-dropdown-open");
        $(".collapse-search.show").collapse("hide");

        // Bootstrap uyumu için aria ve class fix
        var $link = $(menu).prev('a');
        $link.addClass('highlighted').attr('aria-expanded', 'true');
        $(menu).parent().addClass('show');
    })
    .on('hide.smapi', function(e, menu) {
        var $parentLi = $(menu).parent();
        
        // Bootstrap class temizliği
        $parentLi.removeClass('show');
        $(menu).prev('a').removeClass('highlighted').attr('aria-expanded', 'false');

        // 4️⃣ Sadece Level 1 (Ana menü) kapanınca body class'ı kaldır
        // Not: data-menu-level yerine SmartMenus'ün iç yapısını kullanmak daha sağlamdır
        if ($(menu).data('smartmenus-level') === 1 || $parentLi.parent().hasClass('navbar-nav-main')) {
            $("body").removeClass("menu-dropdown-open");
        }
    });

    // 5️⃣ Manuel Toggle Desteği (Açık olmayana tıklayınca diğerlerini kapat)
    $menu.find("a.has-submenu").on("click", function(e) {
        var $this = $(this);
        if (!$this.hasClass("highlighted")) {
            $menu.smartmenus('menuHideAll');
        }
    });
}


/*function init_smartmenus(){
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
            $('.navbar-nav-main').find(".navbar-nav").smartmenus('menuHideAll');
        }
    });
}*/