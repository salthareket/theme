(function($) {
    $.fn._offcanvas = function(options) {
        // Default ayarlar
        var settings = $.extend({
            title: "Offcanvas Title", // Varsayılan başlık
            body: "Offcanvas Content", // Varsayılan içerik
            position: "end", // start, end, top, bottom
            full: false, // Fullscreen ayarı
            onShow: null, // Gösterildiğinde çalışacak callback
            onHide: null, // Kapatıldığında çalışacak callback
            closeButton: true // Kapat butonu gösterilsin mi?
        }, options);
        
        // Her seferinde yeni bir offcanvas oluşturuyoruz
        return this.each(function() {
            // Unique ID oluştur
            var offcanvasId = "offcanvas_" + Math.random().toString(36).substr(2, 9);

            // Genişlik/Yükseklik ayarı (Full seçeneğine göre)
            var dimensionStyle = '';
            if (settings.full) {
                if (settings.position === 'start' || settings.position === 'end') {
                    dimensionStyle = 'width: 100vw;';
                } else if (settings.position === 'top' || settings.position === 'bottom') {
                    dimensionStyle = 'height: 100vh;';
                }
            }

            // Dinamik offcanvas HTML yapısını oluştur
            var offcanvasTemplate = `
                <div class="offcanvas offcanvas-${settings.position}" tabindex="-1" id="${offcanvasId}" style="${dimensionStyle}" aria-labelledby="${offcanvasId}_label">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title" id="${offcanvasId}_label">${settings.title}</h5>
                        ${settings.closeButton ? '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>' : ''}
                    </div>
                    <div class="offcanvas-body">
                        ${settings.body}
                    </div>
                </div>
            `;

            // Body'ye ekleyelim
            $('body').append(offcanvasTemplate);

            // Bootstrap offcanvas'ı init et
            var offcanvasElement = new bootstrap.Offcanvas(document.getElementById(offcanvasId));

            // Butona tıklandığında açılacak
            $(this).on('click', function() {
                offcanvasElement.show();

                // Eğer onShow callback varsa çalıştır
                if (typeof settings.onShow === 'function') {
                    settings.onShow();
                }
            });

            // Kapatıldığında çalışacak olay
            $(`#${offcanvasId}`).on('hidden.bs.offcanvas', function() {
                // Eğer onHide callback varsa çalıştır
                if (typeof settings.onHide === 'function') {
                    settings.onHide();
                }

                // DOM'dan kaldır
                $(`#${offcanvasId}`).remove();
            });
        });
    };
}(jQuery));
