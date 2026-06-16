/* global shDownloadLog, saltConfig, jQuery, SHModal */
(function ($) {
    'use strict';

    var STR     = shDownloadLog.strings;
    var NONCE   = (typeof saltConfig !== 'undefined') ? saltConfig.nonce : (shDownloadLog.nonce || '');
    var API_URL = shDownloadLog.api_url;

    // ── TurboAPI helper ──────────────────────────────────────────────────────

    function turboPost(method, vars, callback) {
        fetch(API_URL + method, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
            body:    JSON.stringify({ vars: vars }),
        })
        .then(function (r) { return r.json(); })
        .then(callback)
        .catch(function () { callback({ error: true, message: STR.error }); });
    }

    // ── Lead cookie helpers ───────────────────────────────────────────────────

    function setLeadCookie(formId) {
        var key     = 'sh_lead_' + formId;
        var expires = new Date(Date.now() + 365 * 864e5).toUTCString();
        document.cookie = key + '=1; expires=' + expires + '; path=/; SameSite=Lax';
    }

    function hasLeadCookie(formId) {
        var key = 'sh_lead_' + formId;
        return document.cookie.split(';').some(function (c) {
            return c.trim().indexOf(key + '=') === 0;
        });
    }

    // ── Download button click ─────────────────────────────────────────────────

    $(document).on('click', '[data-sh-download]', function (e) {
        e.preventDefault();

        var $btn       = $(this);
        var fileId     = $btn.data('file-id');
        var sourcePost = $btn.data('source-post') || 0;
        var access     = $btn.data('access') || 'allowed';
        var formId     = $btn.data('form-id') || 0;
        var loginUrl   = $btn.data('login-url') || '';

        if ($btn.hasClass('sh-dl-loading')) return;

        if (access === 'lead_required' && formId && hasLeadCookie(formId)) {
            access = 'lead_already_given';
            $btn.attr('data-access', 'lead_already_given');
        }

        if (access === 'allowed' || access === 'lead_already_given') {
            requestDownload($btn, fileId, sourcePost);
        } else if (access === 'login_required') {
            if (loginUrl) window.location.href = loginUrl;
        } else if (access === 'lead_required') {
            openLeadModal($btn, fileId, sourcePost, formId);
        } else {
            requestDownload($btn, fileId, sourcePost);
        }
    });

    // ── Token request → download ─────────────────────────────────────────────

    function requestDownload($btn, fileId, sourcePost) {
        $btn.addClass('sh-dl-loading');
        var origHtml = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin"></i> ' + STR.downloading);

        turboPost('download_request', { file_id: fileId, source_post: sourcePost }, function (res) {
            $btn.removeClass('sh-dl-loading').html(origHtml);
            if (res.error) { showError($btn, res.message || STR.error); return; }
            if (res.status === 'allowed') {
                triggerDownload(res.download_url);
            } else if (res.status === 'login_required') {
                window.location.href = res.login_url;
            } else if (res.status === 'lead_required') {
                openLeadModal($btn, fileId, sourcePost, res.form_id, res.form_html, res.title);
            }
        });
    }

    // ── Trigger download ─────────────────────────────────────────────────────

    function triggerDownload(url) {
        var a = document.createElement('a');
        a.href = url;
        a.download = '';
        a.style.cssText = 'position:fixed;top:-100px;left:-100px;opacity:0;';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { if (a.parentNode) a.parentNode.removeChild(a); }, 500);
    }

    // ── Lead capture modal ───────────────────────────────────────────────────

    function openLeadModal($btn, fileId, sourcePost, formId, formHtml, title) {
        // Form HTML varsa direkt aç
        if (formHtml) {
            var modal = SHModal.open({
                title: title || STR.download || 'Download',
                content: formHtml,
                size: 'md',
                onOpen: function(modalObj) {
                    if (typeof window.AppCF7 !== 'undefined') window.AppCF7.initForms(modalObj.element);
                    attachLeadHandler($btn, fileId, sourcePost, formId, modalObj);
                }
            });
            return;
        }

        // Form HTML yok — AJAX ile al
        turboPost('form_modal', { id: formId, title: title || '' }, function (res) {
            if (!res || res.error || !res.data) {
                requestDownload($btn, fileId, sourcePost);
                return;
            }

            SHModal.open({
                title: res.data.title || title || STR.download || 'Download',
                content: res.data.content || '',
                size: 'md',
                onOpen: function(modalObj) {
                    if (typeof window.AppCF7 !== 'undefined') window.AppCF7.initForms(modalObj.element);
                    attachLeadHandler($btn, fileId, sourcePost, formId, modalObj);
                }
            });
        });
    }

    // ── CF7 submit handler ───────────────────────────────────────────────────

    function attachLeadHandler($btn, fileId, sourcePost, formId, modalObj) {
        var handled = false;

        // Modal içindeki CF7 form element'ini bul
        var $container = modalObj.element ? $(modalObj.element) : $('body');
        var $form = $container.find('.wpcf7-form').filter(function () {
            return parseInt($(this).find('input[name="_wpcf7"]').val(), 10) === formId;
        }).first();

        if (!$form.length) {
            // Fallback: sayfadaki ilk eşleşen form
            $form = $('.wpcf7-form').filter(function () {
                return parseInt($(this).find('input[name="_wpcf7"]').val(), 10) === formId;
            }).first();
        }

        var formEl = $form.length ? $form[0] : null;

        function processSubmit(target) {
            if (handled) return;
            handled = true;

            // Event listener'ları temizle
            if (formEl) {
                formEl.removeEventListener('wpcf7mailsent', onMailSent, false);
                formEl.removeEventListener('wpcf7submit',   onSubmit,   false);
            }
            document.removeEventListener('wpcf7mailsent', onMailSentDoc, false);
            document.removeEventListener('wpcf7submit',   onSubmitDoc,   false);

            var formData = {};
            $(target).serializeArray().forEach(function (item) {
                if (item.name.indexOf('_wpcf7') === 0 || item.name === '_wpnonce' || item.name === 'action') return;
                formData[item.name] = item.value;
            });

            turboPost('download_lead', {
                file_id: fileId, source_post: sourcePost, form_id: formId, lead_data: formData,
            }, function (res) {
                // Modal'ı kapat
                if (modalObj && modalObj.close) {
                    modalObj.close();
                }

                if (!res.error && res.download_url) {
                    setLeadCookie(formId);
                    triggerDownload(res.download_url);
                    $btn.attr('data-access', 'lead_already_given');
                    $('[data-sh-download][data-form-id="' + formId + '"]').attr('data-access', 'lead_already_given');
                }
            });
        }

        // Form element'ine doğrudan bağla (varsa) — sadece bu form tetikler
        function onMailSent(e) { processSubmit(e.target); }
        function onSubmit(e) {
            var apiRes = e.detail && e.detail.apiResponse;
            if (!apiRes) return;
            if (apiRes.invalid_fields && apiRes.invalid_fields.length) return;
            if (apiRes.status === 'mail_sent') return; // onMailSent handle eder
            if (apiRes.status === 'mail_failed') { processSubmit(e.target); return; }
            if (apiRes.status === 'aborted')     { processSubmit(e.target); return; }
        }

        // document fallback — form element bulunamazsa (formId ile filtrele)
        function onMailSentDoc(e) {
            var fid = parseInt($(e.target).find('input[name="_wpcf7"]').val(), 10);
            if (fid !== formId) return;
            processSubmit(e.target);
        }
        function onSubmitDoc(e) {
            var fid = parseInt($(e.target).find('input[name="_wpcf7"]').val(), 10);
            if (fid !== formId) return;
            var apiRes = e.detail && e.detail.apiResponse;
            if (!apiRes) return;
            if (apiRes.invalid_fields && apiRes.invalid_fields.length) return;
            if (apiRes.status === 'mail_sent') return;
            if (apiRes.status === 'mail_failed') { processSubmit(e.target); return; }
            if (apiRes.status === 'aborted')     { processSubmit(e.target); return; }
        }

        if (formEl) {
            formEl.addEventListener('wpcf7mailsent', onMailSent, false);
            formEl.addEventListener('wpcf7submit',   onSubmit,   false);
        } else {
            document.addEventListener('wpcf7mailsent', onMailSentDoc, false);
            document.addEventListener('wpcf7submit',   onSubmitDoc,   false);
        }
    }

    // ── Error display ────────────────────────────────────────────────────────

    function showError($btn, msg) {
        var $err = $('<span style="color:#dc2626;font-size:12px;margin-left:8px;">' + msg + '</span>');
        $btn.after($err);
        setTimeout(function () { $err.fadeOut(300, function () { $(this).remove(); }); }, 4000);
    }

})(jQuery);
