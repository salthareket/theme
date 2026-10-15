/**
 * tom-select init
 *
 * Replaces bootstrap-4-autocomplete (bootstrap-3-typeahead) with Tom Select.
 * Backwards compatible with existing data attributes.
 *
 * Supported attributes on .ts-select / .typeahead elements:
 *
 *   data-ajax-method      — AJAX method to call (turbo API) for remote options
 *   data-ajax-vars        — JSON extra vars to send with AJAX request
 *   data-min-length       — Min chars to start searching (default: 1)
 *   data-items            — Max items to show (default: 10)
 *   data-placeholder      — Placeholder text
 *   data-create           — Allow creating new options (default: false)
 *   data-max-items        — Max selectable items (default: 1 = single select)
 *   data-value-field      — Object key for value (default: "id")
 *   data-label-field      — Object key for label (default: "name")
 *   data-search-field     — Comma separated fields to search (default: "name")
 *   data-history          — Show search history when empty (default: false)
 *   data-popular-terms    — Selector for popular terms container
 *   data-url-select       — Navigate to item.url on select (default: true if item has url)
 *   data-render-item      — Template name: 'product' | 'simple' (default: 'simple')
 *
 * Events fired on original element:
 *   ts:change(value, item)   — selection changed
 *   ts:select(item)          — item selected
 *   ts:clear()               — selection cleared
 */
function init_tom_select( $scope ) {
    'use strict';

    var $container = $scope ? $( $scope ) : $( document );

    // ── Selector: .ts-select or .typeahead (backward compat) ────────────────
    var selector = '.ts-select, .typeahead, [data-ts]';

    $container.find( selector ).each( function () {
        var $el = $( this );

        // Çift init engelle
        if ( $el.data( 'tomselect-init' ) ) return;
        $el.data( 'tomselect-init', true );

        // ── Options ────────────────────────────────────────────────────────
        var ajaxMethod    = $el.data( 'ajax-method' ) || $el.data( 'method' ) || '';
        var ajaxVars      = $el.data( 'ajax-vars' ) || {};
        var minLength     = parseInt( $el.data( 'min-length' ) || $el.data( 'minlength' ) || 1 );
        var maxItems      = parseInt( $el.data( 'max-items' ) || 1 );
        var maxResults    = parseInt( $el.data( 'items' ) || 10 );
        var placeholder   = $el.attr( 'placeholder' ) || $el.data( 'placeholder' ) || '';
        var allowCreate   = !! $el.data( 'create' );
        var valueField    = $el.data( 'value-field' ) || 'id';
        var labelField    = $el.data( 'label-field' ) || 'name';
        var searchFields  = ( $el.data( 'search-field' ) || 'name' ).split( ',' ).map( function(s){ return s.trim(); } );
        var showHistory   = !! $el.data( 'history' );
        var popularSel    = $el.data( 'popular-terms' ) || '';
        var renderMode    = $el.data( 'render-item' ) || 'simple';

        if ( typeof ajaxVars === 'string' ) {
            try { ajaxVars = JSON.parse( ajaxVars ); } catch(e) { ajaxVars = {}; }
        }

        // ── Render functions ───────────────────────────────────────────────
        var renders = {};

        if ( renderMode === 'product' ) {
            renders.option = function( item, escape ) {
                var img = item.image
                    ? '<img src="' + escape(item.image) + '" class="ts-thumb" alt="' + escape(item.name||'') + '">'
                    : '';
                var price = item.price
                    ? '<span class="ts-price">' + escape(item.price) + '</span>'
                    : '';
                return '<div class="ts-item-product">' +
                    img +
                    '<div class="ts-item-body">' +
                        '<span class="ts-item-title">' + escape(item.name||'') + '</span>' +
                        price +
                    '</div>' +
                '</div>';
            };
        } else {
            renders.option = function( item, escape ) {
                return '<div class="ts-item">' + escape( item[ labelField ] || '' ) + '</div>';
            };
        }

        renders.item = function( item, escape ) {
            return '<div>' + escape( item[ labelField ] || '' ) + '</div>';
        };

        renders.no_results = function( data, escape ) {
            return '<div class="no-results">No results for "' + escape( data.input ) + '"</div>';
        };

        renders.loading = function() {
            return '<div class="ts-loading">Loading...</div>';
        };

        // ── Load function (AJAX) ───────────────────────────────────────────
        var loadFn = null;

        if ( ajaxMethod ) {
            loadFn = function ( query, callback ) {
                if ( query.length < minLength ) {
                    // Geçmiş aramaları göster
                    if ( showHistory && query.length === 0 &&
                         window.site_config && site_config.search_history &&
                         site_config.search_history.length > 0 ) {
                        callback( site_config.search_history );
                        return;
                    }
                    callback();
                    return;
                }

                var vars = $.extend( {}, ajaxVars, {
                    keyword : query,
                    count   : maxResults,
                } );

                var q        = new ajax_query();
                q.method     = ajaxMethod;
                q.vars       = vars;
                q.done       = function ( res ) {
                    if ( res && res.error ) { callback(); return; }
                    var data = [];
                    if ( res && res.data ) {
                        if ( res.data.results ) {
                            data = res.data.results;
                        } else if ( Array.isArray( res.data ) ) {
                            data = res.data;
                        }
                    } else if ( Array.isArray( res ) ) {
                        data = res;
                    }
                    callback( data );
                };
                q.request();
            };
        }

        // ── Tom Select Config ──────────────────────────────────────────────
        var config = {
            valueField        : valueField,
            labelField        : labelField,
            searchField       : searchFields,
            maxItems          : maxItems,
            maxOptions        : maxResults,
            placeholder       : placeholder,
            create            : allowCreate,
            shouldLoad        : function( query ) {
                return query.length >= minLength;
            },
            render            : renders,

            onItemAdd: function ( value, $item ) {
                var ts   = this;
                var item = ts.options[ value ];
                if ( ! item ) return;

                $el.trigger( 'ts:select', [ item ] );
                $el.trigger( 'ts:change', [ value, item ] );

                // URL'e git
                if ( item.url ) {
                    $( 'body' ).addClass( 'loading' );
                    window.location.href = item.url;
                    return;
                }

                // Form submit
                var $form = $el.closest( 'form' );
                if ( $form.length ) {
                    var baseUrl = $form.attr( 'action' ) || '';
                    if ( baseUrl ) {
                        $( 'body' ).addClass( 'loading' );
                        window.location.href = baseUrl + encodeURIComponent( item[ labelField ] || '' );
                    }
                }
            },

            onClear: function() {
                $el.trigger( 'ts:clear' );
            },
        };

        // AJAX load varsa ekle
        if ( loadFn ) {
            config.load = loadFn;
        }

        // ── Init ───────────────────────────────────────────────────────────
        var ts = new TomSelect( this, config );
        $el.data( 'tomselect', ts );

        // ── Popular terms ──────────────────────────────────────────────────
        if ( popularSel && $( popularSel ).length ) {
            $( popularSel ).find( '.label, .term' ).on( 'click', function ( e ) {
                e.preventDefault();
                var term = $( this ).text().trim();
                ts.setValue( '' );
                ts.setTextboxValue( term );
                ts.load( term );
                ts.open();
            } );
        }

    } );
}
