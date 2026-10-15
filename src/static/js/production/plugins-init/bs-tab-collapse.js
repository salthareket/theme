/**
 * bs-tab-collapse init
 * .tab-collapse veya .tab-collapse-{bp} class'ına sahip nav-tabs'ları init eder.
 * Fetch / dinamik DOM ekleme sonrası çağrılır.
 */
function init_bs_tab_collapse( $container ) {
    var $ctx = $container ? $( $container ) : $( document );
    $ctx.find( '.tab-collapse, [class*="tab-collapse-"]' )
        .not( '[data-bs-tab-collapse-inited]' )
        .each( function () {
            var $el = $( this );
            $el.attr( 'data-bs-tab-collapse-inited', '1' );
            if ( typeof $el.tabCollapse === 'function' ) {
                $el.tabCollapse();
            }
        } );
}
