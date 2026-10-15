/**
 * Plugin Manager — Admin JS v2.1
 * Anlık filtre: kaynak select + kategori select → show/hide rows
 */
jQuery(function ($) {
    var ajaxUrl = pmAjax.ajax_url;
    var nonce   = pmAjax.nonce;

    // ── Toast ──────────────────────────────────────────────────────────────
    function toast(msg, ok) {
        var $t = $('#pm-toast');
        $t.stop(true)
          .css({ background: ok ? '#15803d' : '#dc2626', color: '#fff' })
          .text(msg).fadeIn(150);
        setTimeout(function () { $t.fadeOut(400); }, 3200);
    }

    // ── Anlık filtre ───────────────────────────────────────────────────────
    function applyFilters() {
        var source = $('#pm-filter-source').val() || 'all';
        var cat    = $('#pm-filter-cat').val()    || 'all';

        $('.pm-row').each(function () {
            var $r      = $(this);
            var rowSrc  = $r.data('source') + '';
            var rowCats = ($r.data('cats') + '').split(',');

            var okSrc = source === 'all' || rowSrc === source;
            var okCat = cat === 'all'    || rowCats.indexOf(cat) !== -1;

            $r.toggle(okSrc && okCat);
        });

        // Grup başlıklarını: altında görünür row yoksa gizle
        $('.pm-group-hdr').each(function () {
            var group   = $(this).data('group');
            var visible = $('.pm-row[data-group="' + group + '"]:visible').length;
            $(this).toggle(visible > 0);
        });
    }

    // Select change'leri dinle
    $('#pm-filter-source, #pm-filter-cat').on('change', function () {
        applyFilters();
    });

    // ── Tekil aksiyon ──────────────────────────────────────────────────────
    $(document).on('click', '.pm-btn', function () {
        var $btn  = $(this);
        var slug  = $btn.data('slug') + '';
        var local = $btn.data('local') + '';
        var act   = $btn.data('act')   + '';
        var orig  = $btn.text();

        $btn.prop('disabled', true).text('⏳...');

        $.post(ajaxUrl, {
            action:      'pm_process_plugin',
            _pm_nonce:   nonce,
            plugin_slug: slug,
            action_type: act,
            local:       local
        }, function (res) {
            if (res.success) {
                toast('✅ ' + res.data.message, true);
                setTimeout(function () { location.reload(); }, 1100);
            } else {
                toast('❌ ' + (res.data ? res.data.message : 'Hata'), false);
                $btn.prop('disabled', false).text(orig);
            }
        }).fail(function () {
            toast('❌ AJAX hatası', false);
            $btn.prop('disabled', false).text(orig);
        });
    });

    // ── Bulk: eksik yükle ─────────────────────────────────────────────────
    $('#pm-install-missing').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('⏳ Yükleniyor...');
        $.post(ajaxUrl, {
            action:    'pm_bulk_action',
            _pm_nonce: nonce,
            bulk_type: 'install_missing'
        }, function (res) {
            toast(res.success ? '✅ ' + res.data.message : '❌ Hata', res.success);
            if (res.success) setTimeout(function () { location.reload(); }, 1300);
            else $btn.prop('disabled', false).text('⬇ Eksikleri Yükle');
        });
    });

    // ── Bulk: tümünü güncelle ─────────────────────────────────────────────
    $('#pm-update-all').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('⏳ Güncelleniyor...');
        $.post(ajaxUrl, {
            action:    'pm_bulk_action',
            _pm_nonce: nonce,
            bulk_type: 'update_all'
        }, function (res) {
            toast(res.success ? '✅ ' + res.data.message : '❌ Hata', res.success);
            if (res.success) setTimeout(function () { location.reload(); }, 1300);
            else $btn.prop('disabled', false).text('⬆ Güncelle');
        });
    });
});
