(async function () {
    let results = {
        desktop: null,
        mobile: null
    };

    // Pencere açılmış mı kontrol etmek için bir flag
    let isWindowOpened = { desktop: false, mobile: false }; 

    async function checkLCP(viewport, callback) {
        // Eğer pencere zaten açıldıysa tekrar açma
        if (isWindowOpened[viewport]) {
            return;
        }

        // Pencereyi açma
        isWindowOpened[viewport] = true;

        let newWindow;
        
        // Desktop için pencere aç
        if (viewport === 'desktop') {
            newWindow = window.open(window.location.href, '_blank', 'width=1280,height=720');
        } else {
            newWindow = window.open(window.location.href, '_blank', 'width=375,height=812');
        }

        // Pencere açıldıktan sonra
        newWindow.onload = function () {
            let script = newWindow.document.createElement('script');
            script.src = 'https://unpkg.com/web-vitals@4/dist/web-vitals.attribution.iife.js';
            script.onload = function () {

                console.log("script loaded...");

                // Mobil veya desktop için doğru user-agent ve ağ ayarlarını yap
                if (viewport === 'mobile') {
                    newWindow.navigator.__defineGetter__('userAgent', function () {
                        return "Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/537.36";
                    });
                    Object.defineProperty(newWindow.navigator, "maxTouchPoints", { get: () => 5 });

                    if ('connection' in newWindow.navigator) {
                        newWindow.navigator.connection.__defineGetter__('effectiveType', function () {
                            return '3g';
                        });
                        newWindow.navigator.connection.__defineGetter__('downlink', function () {
                            return 1.5;
                        });
                    }
                } else {
                    newWindow.navigator.__defineGetter__('userAgent', function () {
                        return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36";
                    });
                    Object.defineProperty(newWindow.navigator, "maxTouchPoints", { get: () => 0 });

                    if ('connection' in newWindow.navigator) {
                        newWindow.navigator.connection.__defineGetter__('effectiveType', function () {
                            return '4g';
                        });
                        newWindow.navigator.connection.__defineGetter__('downlink', function () {
                            return 10;
                        });
                    }
                }

                // LCP metriklerini al
                newWindow.webVitals.onLCP(function (metric) {
                    console.log(metric);
                    results[viewport] = {
                        element: metric.attribution.element || null,
                        url: metric.attribution.url || null,
                        timestamp: Date.now()
                    };
                    console.log(results[viewport]);

                    // Pencereyi kapat
                    newWindow.close();

                    // Eğer her iki sonuç da geldiyse, callback fonksiyonunu çağır
                    if (results.desktop && results.mobile) {
                        console.log("📊 LCP Test Sonuçları (Tam PSI Simülasyonu):", results);
                        callback(results);
                    }
                });
            };

            newWindow.document.head.appendChild(script);
        };

        // Hata olursa bildir
        newWindow.onerror = function () {
            console.log('Yeni pencere yüklenemedi, tarayıcı engelledi olabilir.');
        };
    }

    // İlk önce Desktop'ı kontrol et, sonra Mobile'ı kontrol et
    checkLCP('desktop', function () {
        checkLCP('mobile', function (finalResults) {
            console.log("Final LCP Data:", finalResults);
            // Burada verileri DB'ye kaydedebilirsin
        });
    });
})();
