/* global shDlAdmin, jQuery */
(function ($) {
    'use strict';

    var AJAX       = shDlAdmin.ajax;
    var NONCE      = shDlAdmin.nonce;
    var POST_TYPES = shDlAdmin.post_types || {};
    var TAXONOMIES = shDlAdmin.taxonomies || {};
    var CF7_FORMS  = shDlAdmin.cf7_forms  || {};

    // ── Toast ─────────────────────────────────────────────────────────────────

    function toast(msg, isError) {
        var el = document.getElementById('sh-toast');
        if (!el) return;
        var item = document.createElement('div');
        item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
        item.textContent = msg;
        el.appendChild(item);
        setTimeout(function () { item.remove(); }, 3000);
    }

    // ── SCOPE INIT ────────────────────────────────────────────────────────────
    // Hem yeni hem mevcut kartlarda scope/mode değişince doğru alanları göster

    function syncScopeFields($card) {
        var scope = $card.find('.sh-dl-rule-scope').val() || 'global';
        var mode  = $card.find('.sh-dl-rule-mode').val()  || 'public';

        $card.find('.sh-dl-form-wrap').toggleClass('sh-hidden', mode !== 'lead_capture');
        $card.find('.sh-dl-scope-post_type').toggleClass('sh-hidden', scope !== 'post_type');
        $card.find('.sh-dl-scope-term').toggleClass('sh-hidden', scope !== 'term');
        $card.find('.sh-dl-scope-post').toggleClass('sh-hidden', scope !== 'post');

        // Taxonomy seçiliyse term'leri yükle
        if (scope === 'term') {
            var tax = $card.find('.sh-dl-rule-taxonomy').val();
            if (tax) loadTerms($card, tax);
        }
    }

    // ── RULES TAB ─────────────────────────────────────────────────────────────

    $(document).on('click', '#sh-dl-add-rule, #sh-dl-add-rule2', function () {
        addRuleCard();
    });

    function buildOpts(map, placeholder) {
        var s = '<option value="">' + placeholder + '</option>';
        Object.keys(map).forEach(function (k) { s += '<option value="' + k + '">' + map[k] + '</option>'; });
        return s;
    }

    function addRuleCard() {
        var uid = 'new_' + Date.now();

        var $card = $('<div class="sh-rule-card" data-rule-id="' + uid + '"></div>');
        $card.html(
            '<div class="sh-rule-header">' +
            '<div class="sh-rule-meta"><span class="sh-rule-meta-inner"><strong style="color:#2271b1;">New Rule</strong></span></div>' +
            '<div class="sh-rule-actions"><div class="sh-rule-btns">' +
            '<button type="button" class="sh-rule-btn sh-rule-btn-delete sh-dl-cancel-new-btn" title="Cancel"><span class="dashicons dashicons-no"></span></button>' +
            '</div></div></div>' +
            '<div class="sh-rule-form">' +
            '<div class="sh-form-row">' +

            '<div class="sh-form-col sh-form-col-sm"><label>Mode</label>' +
            '<select class="sh-select sh-dl-rule-mode">' +
            '<option value="public">Public</option>' +
            '<option value="login_required">Login Required</option>' +
            '<option value="lead_capture">Lead Capture</option>' +
            '</select></div>' +

            '<div class="sh-form-col sh-form-col-sm sh-dl-form-wrap sh-hidden"><label>CF7 Form</label>' +
            '<select class="sh-select sh-dl-rule-form-id">' + buildOpts(CF7_FORMS, '— Select Form —') + '</select></div>' +

            '<div class="sh-form-col sh-form-col-sm"><label>Scope</label>' +
            '<select class="sh-select sh-dl-rule-scope">' +
            '<option value="global">Global (all site)</option>' +
            '<option value="post_type">Post Type</option>' +
            '<option value="term">Term / Taxonomy</option>' +
            '<option value="post">Specific Post</option>' +
            '</select></div>' +

            '<div class="sh-form-col sh-dl-scope-post_type sh-hidden"><label>Post Type</label>' +
            '<select class="sh-select sh-dl-rule-post-type">' + buildOpts(POST_TYPES, '— Select —') + '</select></div>' +

            '<div class="sh-form-col sh-dl-scope-term sh-hidden"><label>Taxonomy</label>' +
            '<select class="sh-select sh-dl-rule-taxonomy">' + buildOpts(TAXONOMIES, '— Select —') + '</select></div>' +

            '<div class="sh-form-col sh-dl-scope-term sh-hidden"><label>Term</label>' +
            '<select class="sh-select sh-dl-rule-term-select"><option value="">— Select Taxonomy First —</option></select>' +
            '<input type="hidden" class="sh-dl-rule-term-id"></div>' +

            '<div class="sh-form-col sh-dl-scope-post sh-hidden"><label>Post</label>' +
            '<div style="position:relative;">' +
            '<input type="text" class="sh-input sh-dl-rule-post-search" placeholder="Type to search...">' +
            '<input type="hidden" class="sh-dl-rule-post-id">' +
            '<div class="sh-dl-autocomplete-results" style="display:none;position:absolute;top:100%;left:0;background:#fff;border:1px solid #ddd;border-radius:4px;z-index:9999;max-height:200px;overflow-y:auto;min-width:220px;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>' +
            '</div></div>' +

            '</div>' +
            '<div class="sh-form-footer">' +
            '<button type="button" class="sh-btn sh-btn-primary sh-dl-rule-save-btn" data-id="">Save</button> ' +
            '<button type="button" class="sh-btn sh-btn-ghost sh-dl-cancel-new-btn">Cancel</button>' +
            '</div></div>'
        );

        $('#sh-dl-rules-list').prepend($card);
        $('.sh-empty-box').hide();
        $card.find('.sh-dl-rule-mode').focus();
    }

    // Cancel new
    $(document).on('click', '.sh-dl-cancel-new-btn', function () {
        $(this).closest('.sh-rule-card').remove();
        if ($('#sh-dl-rules-list .sh-rule-card').length === 0) $('.sh-empty-box').show();
    });

    // Mode change
    $(document).on('change', '.sh-dl-rule-mode', function () {
        syncScopeFields($(this).closest('.sh-rule-card'));
    });

    // Scope change
    $(document).on('change', '.sh-dl-rule-scope', function () {
        syncScopeFields($(this).closest('.sh-rule-card'));
    });

    // Edit button — form aç ve scope'u initialize et
    $(document).on('click', '.sh-dl-rule-edit-btn', function () {
        var $card = $(this).closest('.sh-rule-card');
        var $form = $card.find('.sh-rule-form');
        $form.slideToggle(150, function () {
            if ($form.is(':visible')) {
                syncScopeFields($card);
            }
        });
    });

    // Cancel edit
    $(document).on('click', '.sh-dl-rule-cancel-btn', function () {
        $(this).closest('.sh-rule-form').slideUp(150);
    });

    // Delete rule
    $(document).on('click', '.sh-dl-rule-delete-btn', function () {
        if (!confirm('Delete this rule?')) return;
        var $card  = $(this).closest('.sh-rule-card');
        var ruleId = $card.data('rule-id');
        if (!ruleId || String(ruleId).indexOf('new_') === 0) { $card.remove(); return; }
        $.post(AJAX, { action: 'sh_download_delete_rule', nonce: NONCE, rule_id: ruleId }, function (res) {
            if (res.success) { $card.fadeOut(200, function () { $(this).remove(); }); toast('Rule deleted.'); }
            else { toast(res.data || 'Error', true); }
        });
    });

    // Save rule
    $(document).on('click', '.sh-dl-rule-save-btn', function () {
        var $btn   = $(this);
        var $card  = $btn.closest('.sh-rule-card');
        var ruleId = $btn.data('id') || $card.data('rule-id') || '';
        var scope  = $card.find('.sh-dl-rule-scope').val() || 'global';

        var rule = {
            id:      String(ruleId).indexOf('new_') === 0 ? '' : ruleId,
            mode:    $card.find('.sh-dl-rule-mode').val()    || 'public',
            scope:   scope,
            form_id: $card.find('.sh-dl-rule-form-id').val() || 0,
        };

        if (scope === 'post_type') {
            rule.post_type = $card.find('.sh-dl-rule-post-type').val() || '';
        } else if (scope === 'term') {
            rule.tax       = $card.find('.sh-dl-rule-taxonomy').val()    || '';
            rule.term_id   = $card.find('.sh-dl-rule-term-id').val()     || 0;
            rule.term_name = $card.find('.sh-dl-rule-term-select option:selected').text() || '';
        } else if (scope === 'post') {
            rule.post_id    = $card.find('.sh-dl-rule-post-id').val()     || 0;
            rule.post_title = $card.find('.sh-dl-rule-post-search').val() || '';
        }

        $btn.text('Saving...').prop('disabled', true);
        $.post(AJAX, { action: 'sh_download_save_rule', nonce: NONCE, rule: JSON.stringify(rule) }, function (res) {
            $btn.text('Save').prop('disabled', false);
            if (res.success) { toast('Rule saved!'); window.location.reload(); }
            else { toast(res.data || 'Error', true); }
        });
    });

    // ── TAXONOMY → TERM LOADER ────────────────────────────────────────────────

    function loadTerms($card, tax) {
        var $select = $card.find('.sh-dl-rule-term-select');
        var savedId = $card.find('.sh-dl-rule-term-id').val();
        $select.html('<option value="">Loading...</option>').prop('disabled', true);

        $.post(AJAX, { action: 'sh_download_search_terms', nonce: NONCE, keyword: '', taxonomy: tax }, function (res) {
            $select.empty().prop('disabled', false);
            $select.append('<option value="">— Select Term —</option>');
            if (res.success && res.data.results.length) {
                res.data.results.forEach(function (item) {
                    var selected = (String(item.id) === String(savedId)) ? ' selected' : '';
                    $select.append('<option value="' + item.id + '"' + selected + '>' + item.text + '</option>');
                });
            } else {
                $select.append('<option value="" disabled>No terms found</option>');
            }
        });
    }

    $(document).on('change', '.sh-dl-rule-taxonomy', function () {
        var $card = $(this).closest('.sh-rule-card');
        loadTerms($card, $(this).val());
    });

    $(document).on('change', '.sh-dl-rule-term-select', function () {
        $(this).closest('.sh-rule-card').find('.sh-dl-rule-term-id').val($(this).val());
    });

    // ── POST AUTOCOMPLETE ─────────────────────────────────────────────────────

    var acTimer = null;

    $(document).on('input', '.sh-dl-rule-post-search', function () {
        var $inp  = $(this);
        var $card = $inp.closest('.sh-rule-card');
        clearTimeout(acTimer);
        acTimer = setTimeout(function () {
            var kw = $inp.val().trim();
            if (!kw) { $card.find('.sh-dl-autocomplete-results').hide(); return; }
            $.post(AJAX, { action: 'sh_download_search_posts', nonce: NONCE, keyword: kw, post_type: 'any' }, function (res) {
                var $results = $card.find('.sh-dl-autocomplete-results');
                $results.empty().show();
                if (!res.success || !res.data.results.length) {
                    $results.html('<div style="padding:8px 12px;color:#9ca3af;font-size:12px;">No results</div>');
                    return;
                }
                res.data.results.forEach(function (item) {
                    var $item = $('<div style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f0f0f1;">' + item.text + '</div>');
                    $item.on('mouseenter', function () { $(this).css('background', '#f0f7ff'); });
                    $item.on('mouseleave', function () { $(this).css('background', ''); });
                    $item.on('click', function () {
                        $card.find('.sh-dl-rule-post-id').val(item.id);
                        $inp.val(item.text);
                        $results.hide();
                    });
                    $results.append($item);
                });
            });
        }, 300);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).hasClass('sh-dl-rule-post-search')) {
            $('.sh-dl-autocomplete-results').hide();
        }
    });

    // ── COPY BUTTONS ──────────────────────────────────────────────────────────

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(function () { toast('Copied!'); })
                .catch(function () { fallbackCopy(text); });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity  = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); toast('Copied!'); }
        catch (e) { toast('Copy failed', true); }
        document.body.removeChild(ta);
    }

    $(document).on('click', '.sh-dl-copy-btn', function () {
        var $wrap = $(this).closest('.sh-twig-wrap');
        var code  = $wrap.find('.sh-dl-copy-code').text().trim();
        if (code) copyText(code);
    });

    $(document).on('click', '.sh-dl-copy-code', function () {
        copyText($(this).text().trim());
    });

    // ── LEAD DATA MODAL ───────────────────────────────────────────────────────

    $(document).on('click', '.sh-dl-lead-more', function () {
        var raw     = $(this).data('lead');
        var guestId = $(this).data('guest') || '';
        var data;
        try { data = typeof raw === 'object' ? raw : JSON.parse(raw); }
        catch (e) { data = {}; }

        var rows = '';
        Object.keys(data).forEach(function (key) {
            var val = data[key];
            if (Array.isArray(val)) val = val.join(', ');
            if (val !== null && typeof val === 'object') val = JSON.stringify(val);
            val = String(val || '');
            if (!val) return;
            rows += '<tr>' +
                '<td style="padding:8px 12px;font-weight:600;color:#374151;white-space:nowrap;border-bottom:1px solid #f0f0f1;font-size:12px;width:40%;">' + $('<span>').text(key).html() + '</td>' +
                '<td style="padding:8px 12px;color:#6b7280;border-bottom:1px solid #f0f0f1;font-size:12px;">' + $('<span>').text(val).html() + '</td>' +
                '</tr>';
        });

        var tableHtml = rows
            ? '<table style="width:100%;border-collapse:collapse;"><thead><tr>' +
              '<th style="padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:2px solid #e5e7eb;">Field</th>' +
              '<th style="padding:8px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:2px solid #e5e7eb;">Value</th>' +
              '</tr></thead><tbody>' + rows + '</tbody></table>'
            : '<div style="text-align:center;color:#9ca3af;padding:24px;font-size:13px;">No lead data</div>';

        var titleHtml = 'Lead Data' + (guestId
            ? ' <code style="font-size:10px;background:#f0f0f1;padding:2px 8px;border-radius:4px;color:#6b7280;font-weight:400;">' + $('<span>').text(guestId).html() + '</code>'
            : '');

        // sh-admin overlay modal — bootbox gerektirmez
        var $overlay = $('<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999999;display:flex;align-items:center;justify-content:center;"></div>');
        var $box     = $('<div class="sh-wrap" style="background:#fff;border-radius:8px;width:520px;max-width:90vw;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.2);"></div>');
        var $header  = $('<div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">' +
                         '<h3 style="margin:0;font-size:14px;font-weight:600;">' + titleHtml + '</h3>' +
                         '<button type="button" class="sh-dl-modal-close" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px;line-height:1;padding:0;">&times;</button>' +
                         '</div>');
        var $body    = $('<div style="padding:0;overflow-y:auto;flex:1;">' + tableHtml + '</div>');

        $box.append($header).append($body);
        $overlay.append($box);
        $('body').append($overlay);

        $overlay.on('click', '.sh-dl-modal-close', function () { $overlay.remove(); });
        $overlay.on('click', function (e) { if ($(e.target).is($overlay)) $overlay.remove(); });
        $(document).one('keydown.sh-dl-modal', function (e) { if (e.key === 'Escape') $overlay.remove(); });
    });

    // ── CLEAR LOGS ────────────────────────────────────────────────────────────

    $(document).on('click', '#sh-dl-clear-logs', function () {
        var $btn = $(this);

        // İlk confirm
        if (!confirm('Tüm download log kayıtları silinecek. Bu işlem geri alınamaz.\n\nDevam etmek istiyor musunuz?')) return;

        // İkinci confirm — are you sure
        if (!confirm('Emin misiniz? Tüm veriler kalıcı olarak silinecek.')) return;

        $btn.text('Clearing...').prop('disabled', true);

        $.post(AJAX, {
            action: 'sh_download_clear_logs',
            nonce:  NONCE,
        }, function (res) {
            $btn.text('&#128465; Clear All Logs').prop('disabled', false);
            if (res.success) {
                toast('All logs cleared.');
                setTimeout(function () { window.location.reload(); }, 1000);
            } else {
                toast(res.data || 'Error', true);
            }
        });
    });

    // ── ANALYTICS TAB ─────────────────────────────────────────────────────────

    var dlChart = null; // Chart instance

    function initAnalyticsRange() {
        var $chartWrap = $('.sh-chart-wrap').first();
        if (!$chartWrap.length) return;

        var $header = $chartWrap.find('.sh-chart-header');
        if (!$header.length) {
            $header = $('<div class="sh-chart-header"></div>');
            $chartWrap.prepend($header);
        }

        // PHP'den gelen days değeri — dropdown'u buna göre set et
        var currentDays = (typeof window.shDlDays !== 'undefined') ? window.shDlDays : 30;
        var validDays   = [7, 30, 90, 365];
        var defaultVal  = validDays.indexOf(currentDays) !== -1 ? String(currentDays) : '30';

        var $preset = $('<select id="sh-dl-range-preset" class="sh-period-select" style="margin-left:auto;">' +
            '<option value="7"' + (defaultVal === '7' ? ' selected' : '') + '>Son 7 Gun</option>' +
            '<option value="30"' + (defaultVal === '30' ? ' selected' : '') + '>Son 30 Gun</option>' +
            '<option value="90"' + (defaultVal === '90' ? ' selected' : '') + '>Son 90 Gun</option>' +
            '<option value="365"' + (defaultVal === '365' ? ' selected' : '') + '>Son 1 Yil</option>' +
            '<option value="custom">Ozel Aralik</option>' +
            '</select>');

        var $customRange = $('<span id="sh-dl-custom-range" style="display:none;gap:6px;align-items:center;">' +
            '<input type="date" id="sh-dl-date-from" class="sh-input" style="width:130px;">' +
            '<span style="color:#9ca3af;">—</span>' +
            '<input type="date" id="sh-dl-date-to" class="sh-input" style="width:130px;">' +
            '<button type="button" id="sh-dl-range-apply" class="sh-btn sh-btn-primary sh-btn-sm">Apply</button>' +
            '</span>');

        var $exportBtn = $('<a id="sh-dl-export-range" href="#" class="sh-btn sh-btn-secondary sh-btn-sm" style="margin-left:8px;">&#8595; Export XLSX</a>');

        $('#sh-dl-analytics-range').remove();
        $header.append($preset).append($customRange).append($exportBtn);

        var today = new Date();
        var from  = new Date(); from.setDate(today.getDate() - 30);
        $('#sh-dl-date-from').val(from.toISOString().split('T')[0]);
        $('#sh-dl-date-to').val(today.toISOString().split('T')[0]);

        // Custom aralık varsa dropdown'u custom yap, date input'ları doldur
        if (window.shDlIsCustom && window.shDlDateFrom && window.shDlDateTo) {
            $preset.find('option[value="custom"]').prop('selected', true);
            // defaultVal override
            $preset.find('option').prop('selected', false);
            $preset.find('option[value="custom"]').prop('selected', true);
            $('#sh-dl-custom-range').css('display', 'flex');
            $('#sh-dl-date-from').val(window.shDlDateFrom);
            $('#sh-dl-date-to').val(window.shDlDateTo);
        }

        updateExportLink();
    }

    function updateExportLink() {
        var preset = $('#sh-dl-range-preset').val() || '30';
        var today  = new Date();
        var from, to;
        to = today.toISOString().split('T')[0];

        if (preset === 'custom') {
            from = $('#sh-dl-date-from').val();
            to   = $('#sh-dl-date-to').val();
        } else {
            var d = new Date(); d.setDate(today.getDate() - parseInt(preset));
            from = d.toISOString().split('T')[0];
        }

        var adminUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl.replace('admin-ajax.php', 'admin.php') : '';
        var params   = new URLSearchParams();
        params.set('page',      'salt-download-log');
        params.set('tab',       'log');
        params.set('action',    'sh_download_export');
        params.set('format',    'xlsx');
        params.set('date_from', from);
        params.set('date_to',   to);
        params.set('_wpnonce',  (typeof shDlAdmin !== 'undefined' && shDlAdmin.export_nonce) ? shDlAdmin.export_nonce : '');

        $('#sh-dl-export-range').attr('href', adminUrl + '?' + params.toString());
    }

    // Chart'ı period'a göre çiz — Reactions analytics pattern'i
    // window.shDlCPD = { 7: {labels:[], counts:[]}, 30: {...}, 90: {...}, 365: {...} }
    function redrawChart(days) {
        if (typeof Chart === 'undefined') { setTimeout(function () { redrawChart(days); }, 100); return; }
        var ctx = document.getElementById('sh-dl-chart');
        if (!ctx) return;

        var cpd = window.shDlCPD || {};
        var d   = cpd[days] || cpd[30] || { labels: [], counts: [] };

        var labels = d.labels.map(function (dt) { return dt.substring(5); });
        var counts = d.counts;

        // Chart header güncelle
        var headerText = days === 'custom'
            ? ((window.shDlDateFrom || '') + ' — ' + (window.shDlDateTo || ''))
            : 'Son ' + days + ' Gun Indirme Hacmi';
        $('.sh-chart-header h2').text(headerText);

        if (dlChart) {
            dlChart.data.labels               = labels;
            dlChart.data.datasets[0].data     = counts;
            dlChart.update();
        } else {
            dlChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels:   labels,
                    datasets: [{
                        data:            counts,
                        borderColor:     '#2271b1',
                        backgroundColor: 'rgba(34,113,177,0.06)',
                        fill:            true,
                        tension:         0.3,
                        borderWidth:     2,
                        pointRadius:     3,
                    }]
                },
                options: {
                    responsive: true,
                    plugins:    { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxTicksLimit: 10 } }
                    }
                }
            });
        }
    }

    $(document).on('change', '#sh-dl-range-preset', function () {
        var val      = $(this).val();
        var isCustom = val === 'custom';
        $('#sh-dl-custom-range').css('display', isCustom ? 'flex' : 'none');
        if (!isCustom) {
            updateExportLink();
            redrawChart(parseInt(val) || 30);
            // Chart header'ı güncelle
            $('.sh-chart-header h2').text('Son ' + val + ' Gun Indirme Hacmi');
        }
    });

    $(document).on('click', '#sh-dl-range-apply', function () {
        var from = $('#sh-dl-date-from').val();
        var to   = $('#sh-dl-date-to').val();
        if (!from || !to) return;
        updateExportLink();
        // Özel aralık için gün sayısını hesapla
        var days = Math.ceil((new Date(to) - new Date(from)) / 864e5) + 1;
        // Sayfayı reload et — PHP tarafı date_from/date_to ile yeni data çeker
        var url = new URL(window.location.href);
        url.searchParams.set('tab', 'analytics');
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        window.location.href = url.toString();
    });

    $(document).on('change', '#sh-dl-date-from, #sh-dl-date-to', updateExportLink);

    if ($('.sh-chart-wrap').length) {
        initAnalyticsRange();
        // İlk yüklemede chart'ı çiz — custom aralık varsa onu, yoksa shDlDays
        var initPeriod = (window.shDlIsCustom) ? 'custom' : ((typeof window.shDlDays !== 'undefined') ? window.shDlDays : 30);
        setTimeout(function () { redrawChart(initPeriod); }, 100);
    }

})(jQuery);
