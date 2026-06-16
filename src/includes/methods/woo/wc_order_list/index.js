window.ajax_hooks['wc_order_list'] = {

    required: ["bootbox"],

    before: function(response, vars, form, objs) {
        // Modal'ı loading state ile aç
        var modal_id = "#modal-order-detail";
        var title    = "#" + vars.order_number;

        if ($(modal_id).length > 0) {
            // Modal zaten varsa içeriği temizle ve loading göster
            $(modal_id).find(".modal-body").html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
            $(modal_id).modal("show");
        } else {
            // Modal yoksa bootbox ile oluştur
            var box = bootbox.dialog({
                title:       title,
                message:     '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>',
                size:        "large",
                className:   "modal-order-detail",
                onEscape:    true,
                backdrop:    true,
                closeButton: true,
                buttons:     {}
            });
            box.attr("id", modal_id.replace("#", ""));
        }

        return response;
    },

    after: function(response, vars, form, objs) {
        var modal_id = "#modal-order-detail";

        if (!response.error && !IsBlank(response.html)) {
            $(modal_id).find(".modal-body").html(response.html);
        } else {
            response_view(response);
        }
    }

};
