function init_panzoom() {
    const panzoomTargets = document.querySelectorAll('.panzoom');

    panzoomTargets.forEach(target => {
        if (target.classList.contains('init')) return;

        // --- HTML'den Gelen Dinamik Ayarlar ---
        const settings = {
            maxScale: parseFloat(target.dataset.maxScale) || 3,
            minScale: parseFloat(target.dataset.minScale) || 1.0,
            contain: target.dataset.contain || 'outside',
            startScale: parseFloat(target.dataset.startScale) || 1.0,
            mousewheel: target.dataset.mousewheel === 'true',
            disablePan: target.dataset.disablePan === 'true',
            disableZoom: target.dataset.disableZoom === 'true',
            step: parseFloat(target.dataset.step) || 0.3,
            fixedElements: target.dataset.fixedElements || '' 
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
            panOnlyWhenZoomed: true,
            startScale: settings.startScale,
            disablePan: settings.disablePan,
            disableZoom: settings.disableZoom,
            cursor: 'move'
        });

        // --- 🎯 SABİT BOYUTLU ELEMENTLERİ GÜNCELLEYEN MERKEZİ FONKSİYON ---
        const updateFixedElements = (scale) => {
            if (!settings.fixedElements) return;
            
            const selectors = settings.fixedElements.split(',').map(s => s.trim());
            selectors.forEach(selector => {
                const elements = target.querySelectorAll(selector);
                elements.forEach(el => {
                    // Mevcut translate'i koru ve scale'i ters orantılı uygula
                    el.style.transform = `translate(-50%, -100%) scale(${1 / scale})`;
                    el.style.transformOrigin = 'bottom center';
                });
            });
        };

        // Zoom işlemi sırasında (Mouse wheel veya Pinch)
        target.addEventListener('panzoomzoom', (e) => {
            updateFixedElements(e.detail.scale);
        });

        // Genel değişimlerde (Butonla zoom, Reset veya Pan bittiğinde)
        target.addEventListener('panzoomchange', () => {
            const currentScale = panzoomInstance.getScale();
            updateFixedElements(currentScale);
            
            // Buton durumlarını güncelle
            const { minScale, maxScale } = panzoomInstance.getOptions();
            zoomInBtn.classList.toggle('disabled', currentScale >= maxScale - 0.01);
            zoomOutBtn.classList.toggle('disabled', currentScale <= minScale + 0.01);
        });

        // --- 🖱️ MOUSE WHEEL ---
        if (settings.mousewheel) {
            container.addEventListener('wheel', (event) => {
                event.preventDefault();
                event.stopPropagation();
                panzoomInstance.zoomWithWheel(event);
            }, { passive: false });
        }

        // --- Butonları Oluştur ---
        const zoomInBtn = document.createElement('button');
        zoomInBtn.textContent = '+';
        zoomInBtn.classList.add('panzoom-zoom-in');
        zoomInBtn.addEventListener('click', () => panzoomInstance.zoomIn());

        const zoomOutBtn = document.createElement('button');
        zoomOutBtn.textContent = '-';
        zoomOutBtn.classList.add('panzoom-zoom-out');
        // Reset butonuna basınca her şey (scale dahil) sıfırlanır
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

        // --- İLK ÇALIŞTIRMA (Açılış Ayarı) ---
        target.classList.add('init');
        target.panzoomInstance = panzoomInstance;

        // Panzoom'un hazır olmasını bekle ve markerları ilk konuma getir
        setTimeout(() => {
            updateFixedElements(panzoomInstance.getScale());
        }, 10);
    });
}