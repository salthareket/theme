/**
 * toast_notification — Toastify-js wrapper.
 * Eski jquery-toast-plugin yerine kullanilir.
 * Ayni API: { url, sender: { image, name }, message, time }
 *
 * Dependencies: toastify-js (https://github.com/apvarun/toastify-js)
 *
 * Kullanim:
 *   toast_notification({ message: "Basarili!", sender: { image: "<i class='fa fa-check'></i>" } });
 *   toast_notification({ url: "/urun/123", sender: { image: "<img src='thumb.jpg'/>" }, message: "Sepete eklendi", time: "simdi" });
 */
function toast_notification(notification) {
    if (!notification) return;

    var _show = function() {
        if (typeof Toastify !== "function") return;

        // HTML icerik olustur
        var html = "";

        if (notification.url) {
            html += "<a href='" + notification.url + "' class='toast-link'>";
        }

        html += "<div class='toast-content'>";

        // Gorsel / icon
        if (notification.sender && notification.sender.image) {
            html += "<div class='toast-image'>" + notification.sender.image + "</div>";
        }

        // Metin blogu
        html += "<div class='toast-body'>";

        // Gonderen adi
        if (notification.sender && notification.sender.name) {
            html += "<div class='toast-sender'>" + notification.sender.name + "</div>";
        }

        // Mesaj
        if (notification.message) {
            html += "<div class='toast-message'>" + notification.message + "</div>";
        }

        // Zaman
        if (notification.time) {
            html += "<small class='toast-time'>" + notification.time + "</small>";
        }

        html += "</div>"; // toast-body
        html += "</div>"; // toast-content

        if (notification.url) {
            html += "</a>";
        }

        // Toastify calistir
        var node = document.createElement("div");
        node.innerHTML = html;

        Toastify({
            node: node,
            duration: notification.duration || 6000,
            close: true,
            gravity: notification.gravity || "bottom",
            position: notification.position || "left",
            stopOnFocus: true,
            className: "toast-notification" + (notification.className ? " " + notification.className : ""),
            onClick: function() {
                if (notification.url) {
                    window.location.href = notification.url;
                }
            },
            callback: function() {
                document.body.classList.remove("toast-open");
            }
        }).showToast();

        document.body.classList.add("toast-open");
    };

    // Toastify yuklu mu? Degilse isLoadedJS ile yukle, sonra goster
    if (typeof Toastify === "function") {
        _show();
    } else if (typeof isLoadedJS === "function") {
        isLoadedJS("toastify-js", true, _show);
    }
}
