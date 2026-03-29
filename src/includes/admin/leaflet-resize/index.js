jQuery(document).ready(function($) {
    if (typeof L === 'undefined' || typeof map === 'undefined') return;

    setTimeout(function() { map.invalidateSize(); }, 100);
    $(window).on('resize', function() { map.invalidateSize(); });
});
