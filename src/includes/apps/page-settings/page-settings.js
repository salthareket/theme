(function($){
    'use strict';

    var $wrap = $('#sh-ps-wrap');
    if (!$wrap.length) return;

    // Term edit sayfasında (#sh-ps-term-sidebar) kendi inline JS'i var,
    // bu dosya sadece post edit metabox için çalışır.
    if ($('#sh-ps-term-sidebar').length) return;

    // Move toggle to metabox header
    var $metabox = $('#sh-page-settings');
    var $toggle = $wrap.find('.sh-ps-header-toggle').detach();
    $metabox.find('.postbox-header h2, .hndle').append($toggle);
    $toggle.show();

    // Move metabox to top of sidebar (above Yoast etc.)
    var $sidebar = $metabox.closest('.meta-box-sortables');
    if ($sidebar.length) $sidebar.prepend($metabox);

    // Enable toggle → show/hide panel
    $toggle.find('input').on('change', function(){
        $wrap.find('.sh-ps-panel').toggle(this.checked);
    });

    // Tabs
    $wrap.on('click', '.sh-ps-tab', function(){
        var tab = $(this).data('tab');
        $wrap.find('.sh-ps-tab').removeClass('active');
        $(this).addClass('active');
        $wrap.find('.sh-ps-tab-content').removeClass('active');
        $wrap.find('.sh-ps-tab-content[data-tab="'+tab+'"]').addClass('active');
    });

    // Full width → hide container
    $wrap.on('change', 'input[name="sh_ps[full_width]"]', function(){
        $wrap.find('.sh-ps-container-field').toggle(!this.checked);
    });

    // Login required → show roles
    $wrap.on('change', 'input[name="sh_ps[login_required]"]', function(){
        $wrap.find('.sh-ps-roles-field').toggle(this.checked);
    });

    // Offcanvas template → show/hide overrides
    $wrap.on('change', 'select[name="sh_ps[offcanvas][template]"]', function(){
        $wrap.find('.sh-ps-offcanvas-opts').toggle(parseInt(this.value) > 0);
    });

    // Offcanvas visibility → sidebar override alanlari sadece sidebar tipinde (offcanvas-{bp})
    $wrap.on('change', 'select[name="sh_ps[offcanvas][visibility]"]', function(){
        var v = this.value;
        $wrap.find('.sh-ps-sidebar-opts').toggle(v !== '' && v !== 'offcanvas');
    });

    // Modal template → show/hide overrides
    $wrap.on('change', 'select[name="sh_ps[modal][template]"]', function(){
        $wrap.find('.sh-ps-modal-opts').toggle(parseInt(this.value) > 0);
    });

    // Modal view → show/hide time
    $wrap.on('change', 'select[name="sh_ps[modal][view]"]', function(){
        $wrap.find('.sh-ps-modal-time').toggle(this.value === 'scheduled');
    });

    // Init sh-select autocomplete on multi-selects
    if (window.shSelect) {
        $wrap.find('select[data-sh-select]').each(function(){
            window.shSelect.init(this, { placeholder: 'Select...' });
        });
    }

})(jQuery);
