function updateImageSizes() {
    document.querySelectorAll("img, picture source").forEach(el => {
        let parent = el.closest("[class*='col-'], [class*='row-cols-']"); // Sütunu bul
        let row = el.closest("[class*='row-cols-']"); // Eğer row-cols-* varsa onu da bul
        let viewportWidth = window.innerWidth; // Viewport genişliği
        if (parent) {
            let parentWidth = parent.offsetWidth; // Varsayılan sütun genişliği
            if (row) {
                let computedStyle = window.getComputedStyle(row); // CSS'den hesaplanmış genişliği al
                let rowWidth = row.offsetWidth; // Row'un toplam genişliği
                let numCols = parseInt(row.className.match(/row-cols-(xl|lg|md|sm|xs)?-?(\d+)/)?.[2] || 1); // `row-cols-*` değerini al

                if (numCols > 1) {
                    parentWidth = rowWidth / numCols; // Sütunun gerçek genişliği
                }
            }
            let percent = (parentWidth / viewportWidth) * 100; // Viewport oranı hesapla
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