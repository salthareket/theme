window.ajax_hooks['add_to_cart'] = {
    required: ["toastify-js"],
    before: function(response, vars, form, objs) {
        var btn = objs.btn || objs.obj;
        if (btn && btn.length) {
            btn.addClass("loading disabled");
        }
    },
    after: function(response, vars, form, objs) {
        var btn = objs.btn || objs.obj;
        if (btn && btn.length) {
            btn.removeClass("loading disabled");
        }

        if (!response || response.error) return;

        // 1. Badge guncelle
        if (response.data && response.data.count !== undefined) {
            if (typeof cart !== "undefined") {
                cart.updateBadge("cart", response.data.count);
            }
        }

        // 2. Dropdown & offcanvas cart icerigini guncelle
        var $cartDropdown = $(".dropdown-notifications[data-type='cart']").find(".dropdown-container");
        if ($cartDropdown.length > 0 && typeof cart !== "undefined") {
            cart.get($cartDropdown, "dropdown");
        }
        var $cartOffcanvas = $("#offcanvasCart .load-container");
        if ($cartOffcanvas.length > 0 && typeof cart !== "undefined") {
            cart.get($cartOffcanvas, "offcanvas");
        }

        // 3. Toast notification
        if (typeof toast_notification === "function" && response.data && response.data.product) {
            var p = response.data.product;
            var image = p.image
                ? "<img src='" + p.image + "' class='img-fluid rounded' alt='" + (p.name || '') + "' style='width:50px;height:50px;object-fit:cover;'/>"
                : "<i class='fal fa-shopping-basket fa-2x'></i>";

            toast_notification({
                url: p.url || "",
                sender: { image: image },
                message: response.message
            });
        }

        // 4. WooCommerce native event tetikle (fragment update vs.)
        $(document.body).trigger("added_to_cart", [response.data]);
    }
};
