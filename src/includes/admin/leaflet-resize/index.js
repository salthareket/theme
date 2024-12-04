jQuery(document).ready(function($) {
    // Sayfada Leaflet yüklü mü kontrol et
    if (typeof L !== 'undefined' && typeof map !== 'undefined') {
        // Sayfa yüklendiğinde mevcut haritayı yeniden boyutlandır
        setTimeout(function() {
            map.invalidateSize(); // Haritanın boyutunu güncelle
        }, 100);

        // Admin sayfasında herhangi bir pencere yeniden boyutlandırıldığında da güncelle
        $(window).on('resize', function() {
            map.invalidateSize(); // Pencere yeniden boyutlandırıldığında boyutu güncelle
        });
    }
});
