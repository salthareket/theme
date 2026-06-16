/* global shExport, jQuery */
(function ($) {
    'use strict';

    var cfg       = window.shExport || {};
    var NONCE     = cfg.nonce     || '';
    var NF        = cfg.nonceField || '_export_nonce';
    var AJAX      = cfg.ajaxUrl   || '';
    var ACTIONS   = cfg.actions   || {};

    var eTmp      = '';
    var eZip      = '';
    var eId       = '';
    var activeSteps = [];
    var isCancelled = false;

    // ─── Step Labels ──────────────────────────────────────────────────────────

    var STEP_LABELS = {
        init:         { icon: '⚙️',  label: 'Initialize workspace' },
        db_dump:      { icon: '🗄️',  label: 'Export database' },
        core_files:   { icon: '📁',  label: 'Copy core files' },
        theme_export: { icon: '🎨',  label: 'Copy theme / wp-content' },
        zip_create:   { icon: '📦',  label: 'Create ZIP archive' },
        done:         { icon: '✅',  label: 'Complete' },
    };

    // ─── Toast ────────────────────────────────────────────────────────────────

    function toast(msg, isError) {
        var el = document.getElementById('sh-toast');
        if (!el) return;
        var item = document.createElement('div');
        item.className = 'sh-toast-item' + (isError ? ' sh-toast-error' : ' sh-toast-success');
        item.textContent = msg;
        el.appendChild(item);
        setTimeout(function () { item.remove(); }, 3500);
    }

    // ─── Mode Selector ────────────────────────────────────────────────────────

    $(document).on('change', '.sh-mode-radio', function () {
        var mode = $(this).val();
        $('.sh-mode-card').removeClass('active');
        $(this).closest('.sh-mode-card').addClass('active');
        updateModeFields(mode);
    });

    function updateModeFields(mode) {
        $('.sh-mode-field').hide();
        $('.sh-mode-' + mode).show();
        if (mode === 'full') {
            $('.sh-mode-full').show();
        }
    }

    // Init mode fields on load
    var initialMode = $('input[name="export_mode"]:checked').val() || 'full';
    updateModeFields(initialMode);

    // ─── Start Export ─────────────────────────────────────────────────────────

    $('#sh-start-export').on('click', function () {
        isCancelled = false;
        eTmp = ''; eZip = ''; eId = ''; activeSteps = [];

        $('#sh-export-form-card').hide();
        $('#sh-export-progress').show();
        $('#sh-export-done').hide();
        $('#sh-cancel-export').show();
        $('#sh-log-stream').empty();
        $('#sh-steps-list').empty();
        $('#sh-progress-fill').css({ width: '0%', background: '' });
        $('#sh-progress-label').text('0%');
        $('#sh-progress-title').text('Exporting...');

        runStep('init');
    });

    // ─── Cancel ───────────────────────────────────────────────────────────────

    $('#sh-cancel-export').on('click', function () {
        if (!confirm('Cancel the export?')) return;
        isCancelled = true;
        $.post(AJAX, { action: ACTIONS.cancel, [NF]: NONCE, temp_dir: eTmp });
        addLog('⚠ Cancel signal sent...', 'warn');
        $('#sh-cancel-export').prop('disabled', true).text('Cancelling...');
    });

    // ─── New Export ───────────────────────────────────────────────────────────

    $('#sh-new-export-btn').on('click', function () {
        $('#sh-export-progress').hide();
        $('#sh-export-form-card').show();
    });

    // ─── Download ─────────────────────────────────────────────────────────────

    $('#sh-download-btn').on('click', function () {
        if (!eId) return;
        var url = AJAX + '?action=' + ACTIONS.download + '&export_id=' + encodeURIComponent(eId) + '&_wpnonce=' + encodeURIComponent(NONCE);
        window.location.href = url;
    });

    // ─── Delete from History ──────────────────────────────────────────────────

    $(document).on('click', '.sh-delete-export', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        $btn.prop('disabled', true).text('Deleting...');
        $.post(AJAX, { action: ACTIONS.delete, [NF]: NONCE, export_id: id }, function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                toast('Export deleted.');
            } else {
                toast(res.data || 'Error', true);
                $btn.prop('disabled', false).text('🗑 Delete');
            }
        });
    });

    // ─── Settings ─────────────────────────────────────────────────────────────

    $('#sh-scheduled-export').on('change', function () {
        $('#sh-schedule-freq-wrap').toggle($(this).is(':checked'));
    });

    $('#sh-save-settings').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        $.post(AJAX, {
            action: ACTIONS.settings,
            [NF]: NONCE,
            settings: {
                max_history:      $('#sh-max-history').val(),
                default_mode:     $('#sh-default-mode').val(),
                memory_limit:     $('#sh-memory-limit').val(),
                scheduled_export: $('#sh-scheduled-export').is(':checked') ? '1' : '',
                schedule_freq:    $('#sh-schedule-freq').val(),
            }
        }, function (res) {
            $btn.prop('disabled', false).text('Save Settings');
            if (res.success) toast('Settings saved!');
            else toast(res.data || 'Error', true);
        });
    });

    // ─── Step Runner ──────────────────────────────────────────────────────────

    function runStep(step) {
        if (isCancelled) return;

        updateProgress(step);

        var configData = {
            export_mode:  $('input[name="export_mode"]:checked').val() || 'full',
            url:          $('#sh-target-url').val().trim(),
            db:           $('#sh-db-name').val().trim(),
            user:         $('#sh-db-user').val().trim(),
            pass:         $('#sh-db-pass').val().trim(),
            table_prefix: $('#sh-db-prefix').val().trim(),
            wp_content:   $('#sh-inc-wp-content').is(':checked') ? 'true' : 'false',
            wp_admin:     $('#sh-inc-wp-admin').is(':checked')   ? 'true' : 'false',
            wp_includes:  $('#sh-inc-wp-includes').is(':checked')? 'true' : 'false',
            root_files:   $('#sh-inc-root-files').is(':checked') ? 'true' : 'false',
            wp_config:    $('#sh-inc-wp-config').is(':checked')  ? 'true' : 'false',
        };

        $.post(AJAX, {
            action:      ACTIONS.export,
            [NF]:        NONCE,
            step:        step,
            temp_dir:    eTmp,
            zip_path:    eZip,
            config_data: configData,
        }, function (res) {
            if (isCancelled) return;

            if (res.success) {
                var data = res.data;

                if (step === 'init') {
                    activeSteps = data.active_steps || [];
                    eTmp        = data.temp_dir || '';
                    eZip        = data.zip_path || '';
                    buildStepsList(activeSteps);
                }

                if (data.log) addLog(data.log, data.log_type || 'ok');

                markStepDone(step);

                if (data.next_step === 'done') {
                    eId = data.export_id || '';
                    onExportDone();
                } else {
                    runStep(data.next_step);
                }
            } else {
                var msg = res.data && res.data.message ? res.data.message : 'Unknown error';
                addLog('ERROR: ' + msg, 'err');
                onExportFailed(msg);
            }
        }).fail(function () {
            addLog('ERROR: AJAX request failed.', 'err');
            onExportFailed('AJAX request failed.');
        });
    }

    // ─── Steps UI ─────────────────────────────────────────────────────────────

    function buildStepsList(steps) {
        var $list = $('#sh-steps-list');
        $list.empty();
        steps.forEach(function (step) {
            if (step === 'init') return;
            var info = STEP_LABELS[step] || { icon: '⚙️', label: step };
            $list.append(
                '<div class="sh-step-item" data-step="' + step + '">' +
                '<span class="sh-step-icon">' + info.icon + '</span>' +
                '<span class="sh-step-label">' + info.label + '</span>' +
                '<span class="sh-step-status sh-step-pending">Waiting</span>' +
                '</div>'
            );
        });
    }

    function updateProgress(step) {
        if (!activeSteps.length) return;

        // Aktif step'i işaretle
        $('.sh-step-item[data-step="' + step + '"] .sh-step-status')
            .removeClass('sh-step-pending sh-step-done')
            .addClass('sh-step-active')
            .text('Running...');

        var idx = activeSteps.indexOf(step);
        var pct = idx >= 0 ? Math.round( (idx / activeSteps.length) * 100 ) : 0;
        $('#sh-progress-fill').css('width', pct + '%');
        $('#sh-progress-label').text(pct + '%');

        var info = STEP_LABELS[step] || { label: step };
        $('#sh-progress-title').text(info.label + '...');
    }

    function markStepDone(step) {
        $('.sh-step-item[data-step="' + step + '"] .sh-step-status')
            .removeClass('sh-step-active sh-step-pending')
            .addClass('sh-step-done')
            .text('Done');
    }

    // ─── Log Stream ───────────────────────────────────────────────────────────

    function addLog(msg, type) {
        var $log = $('#sh-log-stream');
        var lines = String(msg).split('\n');
        lines.forEach(function (line) {
            if (!line.trim()) return;
            var cls = 'sh-log-sys';
            if (type === 'ok' || /created|copied|done|exported|complete/i.test(line)) cls = 'sh-log-ok';
            else if (type === 'err' || /error|failed|cancel/i.test(line)) cls = 'sh-log-err';
            else if (type === 'info' || /init|system|mode/i.test(line)) cls = 'sh-log-info';
            else if (type === 'warn' || /warn|skip|cancel/i.test(line)) cls = 'sh-log-warn';
            $log.append('<div class="' + cls + '"><span class="sh-log-prefix">›</span> ' + escHtml(line) + '</div>');
        });
        $log.scrollTop($log[0].scrollHeight);
    }

    // ─── Done / Failed ────────────────────────────────────────────────────────

    function onExportDone() {
        $('#sh-progress-fill').css({ width: '100%' });
        $('#sh-progress-label').text('100%');
        $('#sh-progress-title').text('Export Complete');
        $('#sh-cancel-export').hide();
        $('#sh-export-done').show();

        // Tüm step'leri done yap
        $('.sh-step-status').removeClass('sh-step-active sh-step-pending').addClass('sh-step-done').text('Done');

        toast('Export completed successfully!');
    }

    function onExportFailed(msg) {
        $('#sh-progress-fill').css({ width: '100%', background: '#ef4444' });
        $('#sh-progress-label').text('Failed');
        $('#sh-progress-title').text('Export Failed');
        $('#sh-cancel-export').hide();
        toast(msg || 'Export failed.', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
