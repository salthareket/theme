/**
 * `panzoom` sınıfına sahip tüm div'leri Panzoom ile başlatır.
 * Yakınlaştırma ve uzaklaştırma butonları ekler ve durumlarını CSS sınıflarıyla yönetir.
 */
/*
function init_panzoom_v1() {
    const panzoomTargets = document.querySelectorAll('.panzoom');

    panzoomTargets.forEach(target => {
        if (target.classList.contains('init')) {
            return;
        }

        const container = document.createElement('div');
        container.classList.add('panzoom-container');

        target.parentNode.insertBefore(container, target);
        container.appendChild(target);

        const panzoomInstance = Panzoom(target, {
            maxScale: 3,
            minScale: 1.0,
            contain: 'outside',
            panOnlyWhenZoomed: true,
            startScale: 1.0,
        });

        const zoomInBtn = document.createElement('button');
        zoomInBtn.textContent = '+';
        zoomInBtn.classList.add('panzoom-zoom-in');
        zoomInBtn.addEventListener('click', () => {
             panzoomInstance.zoomIn();
        });

        const zoomOutBtn = document.createElement('button');
        zoomOutBtn.textContent = '-';
        zoomOutBtn.classList.add('panzoom-zoom-out');
        zoomOutBtn.addEventListener('click', () => {
            panzoomInstance.reset();
        });

        const controlsContainer = document.createElement('div');
        controlsContainer.classList.add('panzoom-controls');
        controlsContainer.appendChild(zoomInBtn);
        controlsContainer.appendChild(zoomOutBtn);
        container.appendChild(controlsContainer);
        
        container.addEventListener('dblclick', (event) => {
            panzoomInstance.zoomIn({
                animate: true,
                duration: 300,
                easing: 'ease-in-out',
                x: event.clientX,
                y: event.clientY,
                focal: event.target,
            });
        });

        const updateButtonState = () => {
            const currentScale = panzoomInstance.getScale();
            // Düzeltme burada: options'ı getOptions() metoduyla alıyoruz.
            const { minScale, maxScale } = panzoomInstance.getOptions();
            
            // `+` butonu
            if (currentScale >= maxScale - 0.001) {
                zoomInBtn.classList.add('disabled');
            } else {
                zoomInBtn.classList.remove('disabled');
            }

            // `-` butonu
            if (currentScale <= minScale + 0.001) {
                zoomOutBtn.classList.add('disabled');
            } else {
                zoomOutBtn.classList.remove('disabled');
            }
        };

        target.addEventListener('panzoomchange', updateButtonState);

        updateButtonState();

        target.classList.add('init');

        target.panzoom = panzoomInstance;
    });
}
*/

function init_panzoom() {
    const panzoomTargets = document.querySelectorAll('.panzoom');

    panzoomTargets.forEach(target => {
        if (target.classList.contains('init')) return;

        // --- HTML'den Gelen Dinamik Ayarlar (Data Attributes) ---
        // Eğer data attribute yoksa yanındaki varsayılan değerleri kullanır
        const settings = {
            maxScale: parseFloat(target.dataset.maxScale) || 3,
            minScale: parseFloat(target.dataset.minScale) || 1.0,
            contain: target.dataset.contain || 'outside', // 'inside', 'outside' veya null
            startScale: parseFloat(target.dataset.startScale) || 1.0,
            mousewheel: target.dataset.mousewheel === 'true',
            disablePan: target.dataset.disablePan === 'true',
            disableZoom: target.dataset.disableZoom === 'true',
            step: parseFloat(target.dataset.step) || 0.3, // Zoom hızı
        };

        // Kapsayıcı oluşturma
        const container = document.createElement('div');
        container.classList.add('panzoom-container');
        target.parentNode.insertBefore(container, target);
        container.appendChild(target);

        // Panzoom'u Başlat
        const panzoomInstance = Panzoom(target, {
            maxScale: settings.maxScale,
            minScale: settings.minScale,
            contain: settings.contain,
            panOnlyWhenZoomed: true, // Scale 1 iken kaydırmayı engeller (Senin tercihin)
            startScale: settings.startScale,
            disablePan: settings.disablePan,
            disableZoom: settings.disableZoom,
            cursor: 'move'
        });

        // --- 🖱️ MOUSE WHEEL (MOUSE TEKERLEĞİ) ÖZELLİĞİ ---
        if (settings.mousewheel) {
            // container yerine direkt target (resim/harita) üzerinden yakalamak daha etkili olabilir
            container.addEventListener('wheel', (event) => {
                // 1. Sayfa scroll'unu durdur (Kesin çözüm için ilk satırda olmalı)
                event.preventDefault();
                
                // 2. Event'in yukarıdaki elementlere (body/window) ulaşmasını engelle
                event.stopPropagation();

                // 3. Panzoom zoom işlemini yap
                panzoomInstance.zoomWithWheel(event);

            }, { passive: false }); // preventDefault'un çalışması için bu şart
        }

        // --- Butonları Oluştur ---
        const zoomInBtn = document.createElement('button');
        zoomInBtn.innerHTML = '<i class="fas fa-plus"></i>'; // FontAwesome kullanıyorsan şık durur, yoksa '+' kalsın
        zoomInBtn.textContent = '+';
        zoomInBtn.classList.add('panzoom-zoom-in');
        zoomInBtn.addEventListener('click', () => panzoomInstance.zoomIn());

        const zoomOutBtn = document.createElement('button');
        zoomOutBtn.textContent = '-';
        zoomOutBtn.classList.add('panzoom-zoom-out');
        // İpucu: Reset yerine zoomOut() istersen: panzoomInstance.zoomOut()
        zoomOutBtn.addEventListener('click', () => panzoomInstance.reset());

        const controlsContainer = document.createElement('div');
        controlsContainer.classList.add('panzoom-controls');
        controlsContainer.appendChild(zoomInBtn);
        controlsContainer.appendChild(zoomOutBtn);
        container.appendChild(controlsContainer);

        // Çift Tıklama Zoom
        container.addEventListener('dblclick', (event) => {
            panzoomInstance.zoomIn({
                animate: true,
                x: event.clientX,
                y: event.clientY,
            });
        });

        // Buton Durumlarını Güncelle (+ veya - sona dayandıysa pasif yap)
        const updateButtonState = () => {
            const currentScale = panzoomInstance.getScale();
            const { minScale, maxScale } = panzoomInstance.getOptions();
            
            zoomInBtn.classList.toggle('disabled', currentScale >= maxScale - 0.01);
            zoomOutBtn.classList.toggle('disabled', currentScale <= minScale + 0.01);
        };

        target.addEventListener('panzoomchange', updateButtonState);
        updateButtonState();

        // Instance'ı elemente bağla (Dışarıdan erişmek için)
        target.classList.add('init');
        target.panzoomInstance = panzoomInstance; 
    });
}