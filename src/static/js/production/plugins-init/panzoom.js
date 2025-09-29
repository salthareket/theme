/**
 * `panzoom` sınıfına sahip tüm div'leri Panzoom ile başlatır.
 * Yakınlaştırma ve uzaklaştırma butonları ekler ve durumlarını CSS sınıflarıyla yönetir.
 */
function init_panzoom() {
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