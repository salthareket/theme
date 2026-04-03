/**
 * Leaflet Custom Init
 * - LazyLoad TileLayer
 * - MarkerCluster
 * - Popup (static, click, mouseover, ajax)
 * - Custom Buttons
 * - Modal & resize desteği
 */

// ─── 1. TileLayer – standart Leaflet, lazy yok ───────────────────────────────
// tile.loading = 'lazy' Leaflet ile uyumsuz: viewport dışı tile'ları engeller,
// zoom yapınca tile'lar kaybolur. Standart L.tileLayer kullanıyoruz.
/*L.tileLayer.lazyLoad = function (url, options) {
    return L.tileLayer(url, options);
};*/


L.TileLayer.LazyLoad = L.TileLayer.extend({
    createTile: function (coords, done) {
        var tile = document.createElement('img');

        L.DomEvent.on(tile, 'load', L.bind(this._tileOnLoad, this, done, tile));
        L.DomEvent.on(tile, 'error', L.bind(this._tileOnError, this, done, tile));

        if (this.options.crossOrigin || this.options.crossOrigin === "") {
            tile.crossOrigin = this.options.crossOrigin === true ? '' : this.options.crossOrigin;
        }

        tile.alt = '';
        tile.setAttribute('role', 'presentation');

        // Burası sihirli kısım: Hemen yüklemek yerine 'loading' attribute kullanıyor
        tile.loading = 'lazy'; 
        tile.src = this.getTileUrl(coords);

        return tile;
    }
});

L.tileLayer.lazyLoad = function (url, options) {
    return new L.TileLayer.LazyLoad(url, options);
};

// ─── 2. Icon default path düzeltmesi ─────────────────────────────────────────
// Leaflet'in CSS'ten relative path resolve etmesini tamamen devre dışı bırak,
// ikonları JS üzerinden absolute URL ile set et.
(function setupIcons() {
    if (typeof ajax_request_vars === 'undefined') return;
    var base = ajax_request_vars.theme_url.replace(/\/$/, '') + '/static/js/assets/';

    // CSS'teki _getIconUrl'yi override et — artık CSS'ten path resolve etmez
    delete L.Icon.Default.prototype._getIconUrl;

    L.Icon.Default.mergeOptions({
        iconUrl:       base + 'marker-icon.png',
        iconRetinaUrl: base + 'marker-icon-2x.png',
        shadowUrl:     base + 'marker-shadow.png'
    });
})();

// ─── 3. Popup extend – sadece bir kez ────────────────────────────────────────
// closeOnClick davranışını override et (preclick'i devre dışı bırak)
L.Popup.include({
    getEvents: function () {
        var events = L.DivOverlay.prototype.getEvents.call(this);
        if (this.options.keepInView) {
            events.moveend = this._adjustPan;
        }
        return events;
    }
});

// ─── 4. Ana init fonksiyonu ───────────────────────────────────────────────────
window.leafletResizeHandlers = window.leafletResizeHandlers || {};

function init_leaflet(context) {
    var token_init = 'leaflet-init';
    var $scope = context ? $(context) : $(document);
    var $maps = $scope.find('.leaflet-custom').addBack('.leaflet-custom').not('.' + token_init);

    if ($maps.length === 0) return;

    $maps.each(function () {
        var obj = $(this);
        obj.addClass(token_init);

        // ID garantisi
        var id = obj.attr('id');
        if (!id || id === '') {
            id = 'lmap_' + generateCode(5);
            obj.attr('id', id);
        }

        // Config oku
        var configKey = obj.data('config');
        var config = (configKey && typeof window[configKey] !== 'undefined') ? window[configKey] : null;
        if (!config) { console.warn('Leaflet: config bulunamadı', configKey); return; }

        debugJS(config);

        // .block-map ve parent'larındaki overflow:hidden tile'ların kesilmesine neden olur
        obj.closest('.block-map').removeClass('overflow-hidden').css('overflow', 'visible');
        obj.closest('.block-map').find('.container').css('overflow', 'visible');
        // block-map'in tüm ancestor'larında da overflow:hidden varsa kaldır (body'ye kadar)
        obj.closest('.block-map').parentsUntil('body').each(function() {
            if ($(this).css('overflow') === 'hidden') {
                $(this).css('overflow', 'visible');
            }
        });

        var locations = config.locations || [];
        var buttons   = Array.isArray(config.buttons) ? null : config.buttons; // array ise boş kabul et
        var popup_cfg = config.popup || { active: false };

        // ── Harita oluştur ──────────────────────────────────────────────────
        var map_config = {
            scrollWheelZoom: false,
            dragging: !L.Browser.mobile
        };
        if (buttons && buttons.zoom_position) {
            map_config.zoomControl = false;
        }

        var map = L.map(id, map_config).setView([39.9, 32.8], 6);

        // DEBUG: container boyutunu logla – tile sorununun kaynağını görmek için
        var _cont = document.getElementById(id);
        console.log('[Leaflet] container boyutu init anında:', id, _cont ? _cont.offsetWidth + 'x' + _cont.offsetHeight : 'BULUNAMADI');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            tileSize: 256,
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
        }).addTo(map);

        // ── Marker cluster ──────────────────────────────────────────────────
        var clusterGroup = L.markerClusterGroup({
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: true,
            zoomToBoundsOnClick: true
        });

        var markerList = [];

        // ── Marker & popup döngüsü ──────────────────────────────────────────
        for (var i = 0; i < locations.length; i++) {
            (function (loc) {
                var latlng = L.latLng(loc.lat, loc.lng);

                // Static popup
                if (popup_cfg.active && popup_cfg.type === 'static') {
                    _addStaticPopup(map, latlng, loc, popup_cfg, markerList);
                    return;
                }

                // Marker ikonu
                var myIcon;
                if (loc.marker && loc.marker.icon) {
                    myIcon = L.icon({
                        iconUrl:     loc.marker.icon,
                        iconSize:    [loc.marker.width, loc.marker.height],
                        iconAnchor:  [loc.marker.width / 2, loc.marker.height],
                        popupAnchor: [0, -loc.marker.height]
                    });
                } else {
                    myIcon = new L.Icon.Default();
                }

                var marker_item = L.marker(latlng, { icon: myIcon });
                marker_item.post_id = loc.id;

                // Popup bağla
                if (popup_cfg.active && ['click', 'mouseover'].indexOf(popup_cfg.type) > -1) {
                    _bindPopup(marker_item, map, latlng, loc, popup_cfg);
                }

                // Callback
                if (config.callback && typeof window[config.callback] === 'function') {
                    marker_item.on('click', function (e) {
                        window[config.callback](map, this);
                    });
                } else if (config.callback && typeof config.callback === 'string' && config.callback !== '') {
                    try {
                        var cbFn = new Function('map', 'marker', config.callback);
                        marker_item.on('click', function (e) { cbFn(map, this); });
                    } catch(ex) { console.warn('Leaflet callback hatası', ex); }
                }

                markerList.push(marker_item);

            })(locations[i]);
        }

        // Cluster'a ekle (tek seferde)
        if (markerList.length > 0) {
            markerList.forEach(function (m) { clusterGroup.addLayer(m); });
            map.addLayer(clusterGroup);
        }

        // ── invalidateSize – container görünür olduktan sonra ────────────────
        // fitBounds/setView resize event'inden asla çağrılmaz (sonsuz döngü riski)
        var _fitDone = false;
        function _doFit() {
            if (_fitDone) return;
            _fitDone = true;
            _fitMap(map, locations, markerList);
        }

        // İlk yükleme: rAF + 400ms – CSS ve transition bitmesini bekle
        requestAnimationFrame(function () {
            map.invalidateSize({ pan: false });
            setTimeout(function () {
                console.log('[Leaflet] invalidateSize + _doFit çağrılıyor, map size:', map.getSize());
                map.invalidateSize({ pan: false });
                // Tile pane'i sıfırla – top/left boşsa tile grid yeniden hesaplansın
                map.eachLayer(function(layer) {
                    if (layer._resetView) { layer._resetView(); }
                    else if (layer.redraw) { layer.redraw(); }
                });
                _doFit();
            }, 400);
        });

        // Resize handler – sadece invalidateSize, kesinlikle fitBounds yok
        if (window.leafletResizeHandlers[id]) {
            $(window).off('resize.leaflet_' + id);
        }
        var _resizeTimer;
        window.leafletResizeHandlers[id] = function () {
            clearTimeout(_resizeTimer);
            _resizeTimer = setTimeout(function () {
                if (!$.contains(document, obj[0])) {
                    $(window).off('resize.leaflet_' + id);
                    delete window.leafletResizeHandlers[id];
                    return;
                }
                map.invalidateSize({ pan: false });
            }, 200);
        };
        $(window).on('resize.leaflet_' + id, window.leafletResizeHandlers[id]);

        // ── Butonlar ────────────────────────────────────────────────────────
        if (buttons) {
            _addButtons(map, buttons, config);
        }

        // Map referansını sakla (modal invalidateSize için)
        obj.data('map', map);
        obj[0]._leafletMap = map;

        // Tooltip
        obj[0].querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

        return obj;
    });
}

// ─── 5. Yardımcı: Static popup ───────────────────────────────────────────────
function _addStaticPopup(map, latlng, loc, popup_cfg, markerList) {
    if (popup_cfg.template === 'default') {
        var popup = L.popup({ autoPan: false })
            .setLatLng(latlng)
            .setContent(loc.title);
        popup.addTo(map).openPopup();
        markerList.push(popup);
    } else {
        requirePlugin("twig", function() {
            twig({
                href: ajax_request_vars.theme_url + popup_cfg.template,
                async: true,
                allowInlineIncludes: false,
                load: function (template) {
                    var html  = template.render(loc);
                    var popup = L.popup({ autoPan: false, closeButton: false, autoClose: false, closeOnClick: false, className: 'leaflet-popup-custom' })
                        .setLatLng(latlng)
                        .setContent(html);
                    popup.addTo(map).openPopup();
                    markerList.push(popup);
                }
            });
        });
    }
}

// ─── 6. Yardımcı: Click/mouseover popup ──────────────────────────────────────
function _bindPopup(marker_item, map, latlng, loc, popup_cfg) {
    var popupOpts = {
        minWidth:    120,
        maxWidth:    popup_cfg.width || 300,
        closeButton: false,
        closeOnClick: popup_cfg.type === 'click',
        autoClose:   popup_cfg.type === 'click',
        className:   'leaflet-popup-custom'
    };

    if (popup_cfg.ajax && popup_cfg.template !== 'default') {
        // AJAX popup
        marker_item.bindPopup('', popupOpts);

        marker_item.on(popup_cfg.type, function () {
            var self = this;
            if (self.isPopupOpen()) {
                self.closePopup();
                return;
            }
            if (self._popupLoaded) {
                self.openPopup();
                return;
            }
            self.setPopupContent('<span class="leaflet-loading">...</span>');
            self.openPopup();

            var query = new ajax_query();
            query.method = 'get_post';
            query.vars   = { id: self.post_id, template: popup_cfg.template };
            query.after  = function (response) {
                self.setPopupContent(response.html);
                self._popupLoaded = true;
            };
            query.request();
            self._activeQuery = query;
        });

        if (popup_cfg.type === 'mouseover') {
            marker_item.on('mouseout', function () {
                if (this._activeQuery) { this._activeQuery.abort(); }
                this.closePopup();
            });
        }

    } else {
        // Inline / twig popup
        if (popup_cfg.template === 'default') {
            marker_item.bindPopup(loc.title || '', popupOpts);
            marker_item.on(popup_cfg.type, function () {
                if (this.isPopupOpen()) { this.closePopup(); return; }
                this.openPopup();
            });
            if (popup_cfg.type === 'mouseover') {
                marker_item.on('mouseout', function () { this.closePopup(); });
            }
        } else {
            requirePlugin("twig", function() {
                twig({
                    href: ajax_request_vars.theme_url + popup_cfg.template,
                    async: true,
                    allowInlineIncludes: false,
                    load: function (template) {
                        var html = template.render(loc);
                        marker_item.bindPopup(html, popupOpts);
                        marker_item.on(popup_cfg.type, function () {
                            if (this.isPopupOpen()) { this.closePopup(); return; }
                            this.openPopup();
                        });
                        if (popup_cfg.type === 'mouseover') {
                            marker_item.on('mouseout', function () { this.closePopup(); });
                        }
                    }
                });
            });
        }
    }
}

// ─── 7. Yardımcı: Fit map ────────────────────────────────────────────────────
function _fitMap(map, locations, markerList) {
    console.log('[_fitMap] locations:', locations.length, 'markerList:', markerList.length);
    if (locations.length > 1 && markerList.length > 0) {
        var realMarkers = markerList.filter(function (m) { return m instanceof L.Marker; });
        console.log('[_fitMap] realMarkers:', realMarkers.length);
        if (realMarkers.length > 0) {
            var group = L.featureGroup(realMarkers);
            map.fitBounds(group.getBounds(), { padding: [30, 30] });
        }
    } else if (locations.length === 1) {
        var zoom = locations[0].zoom || 15;
        console.log('[_fitMap] setView', locations[0].lat, locations[0].lng, zoom);
        map.setView([locations[0].lat, locations[0].lng], zoom);
    }
}

// ─── 8. Yardımcı: Butonlar ───────────────────────────────────────────────────
function _addButtons(map, buttons, config) {
    if (buttons.zoom_position) {
        L.control.zoom({ position: buttons.zoom_position }).addTo(map);
    }

    if (!buttons.items || !buttons.items.length) return;

    // L.Control.Button – her harita için ayrı tanımlamaya gerek yok, global yeterli
    if (!L.Control.Button) {
        L.Control.Button = L.Control.extend({
            options: { position: 'topright' },
            initialize: function (opts) {
                L.setOptions(this, opts);
                this._btnConfig = opts;
            },
            onAdd: function () {
                var container = L.DomUtil.create('div', 'leaflet-control-button leaflet-bar');
                var btn = L.DomUtil.create('a', 'leaflet-buttons-control-button ' + (this._btnConfig.class || ''), container);
                btn.href  = '#';
                btn.title = this._btnConfig.title || '';
                btn.innerHTML = this._btnConfig.text || '';

                if (this._btnConfig.data) {
                    Object.keys(this._btnConfig.data).forEach(function (k) {
                        btn.setAttribute(k, this._btnConfig.data[k]);
                    }, this);
                }

                var self = this;
                L.DomEvent.on(btn, 'click', function (e) {
                    L.DomEvent.preventDefault(e);
                    if (typeof self._btnConfig.onClick === 'function') {
                        self._btnConfig.onClick(e, btn);
                    }
                });
                return container;
            }
        });
    }

    var position = (buttons.position) || 'topright';

    buttons.items.forEach(function (item) {
        var btn_cfg = {
            position: position,
            title:    item.title  || '',
            class:    item.class  || '',
            text:     item.text   || ''
        };

        if (item.attributes) {
            var data = {};
            item.attributes.forEach(function (a) { data[a.name] = a.value; });
            btn_cfg.data = data;
        }

        if (item.onclick) {
            try {
                var fn = new Function('map', item.onclick);
                btn_cfg.onClick = function (e) { fn(map); };
            } catch (ex) { console.warn('Button onclick hatası', ex); }
        }

        new L.Control.Button(btn_cfg).addTo(map);
    });
}
