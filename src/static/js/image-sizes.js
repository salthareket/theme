function updateImageSizes() {
    document.querySelectorAll("img, picture source").forEach(el => {
        let parent = el.closest("[class*='col-'], [class*='row-cols-']"); // Sütunu bul
        let row = el.closest("[class*='row-cols-']"); // Eğer row-cols-* varsa onu da bul
        let viewportWidth = window.innerWidth; // Viewport genişliği
        if (parent) {
            let parentWidth = parent.offsetWidth; // Varsayılan sütun genişliği
            let numCols = 1; // Varsayılan olarak 1 sütun
            if (row && row.className) {
                // Ekran genişliğine göre grid sınıfını kontrol et
                if (viewportWidth >= 1600) { // xxxl breakpoint
                    numCols = parseInt(row.className.match(/row-cols-xxxl-(\d+)/)?.[1] || 1);
                } else if (viewportWidth >= 1400) { // xxl breakpoint
                    numCols = parseInt(row.className.match(/row-cols-xxl-(\d+)/)?.[1] || 1);
                } else if (viewportWidth >= 1200) { // xl breakpoint
                    numCols = parseInt(row.className.match(/row-cols-xl-(\d+)/)?.[1] || 1);
                } else if (viewportWidth >= 992) { // lg breakpoint
                    numCols = parseInt(row.className.match(/row-cols-lg-(\d+)/)?.[1] || 1);
                } else if (viewportWidth >= 768) { // md breakpoint
                    numCols = parseInt(row.className.match(/row-cols-md-(\d+)/)?.[1] || 1);
                } else if (viewportWidth >= 576) { // sm breakpoint
                    numCols = parseInt(row.className.match(/row-cols-sm-(\d+)/)?.[1] || 1);
                } else { // xs breakpoint
                    numCols = parseInt(row.className.match(/row-cols-(\d+)/)?.[1] || 1);
                }
            }

            // Eğer `numCols` birden fazla ise, sütunun genişliği tüm satır genişliğine bölünmeli
            if (numCols > 1) {
                parentWidth = parent.offsetWidth / numCols; // Sütun genişliğini hesapla
            }
            let percent = (parentWidth / viewportWidth) * 100; // Viewport oranı hesapla

            // Resim için doğru sizes değerini ayarla
            if (el.hasAttribute("data-src") || el.hasAttribute("data-srcset")) {
                el.setAttribute("data-sizes", `${percent}vw`);
                el.sizes = `${percent}vw`;
            } else {
                el.sizes = `${percent}vw`;
            }
        }
    });
}

// Tarayıcı resmi yüklemeden önce `sizes` veya `data-sizes` hesapla
document.addEventListener("DOMContentLoaded", updateImageSizes);
window.addEventListener("resize", updateImageSizes);