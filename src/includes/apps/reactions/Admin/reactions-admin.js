/* global shReactionsAdmin, jQuery */
(function ($) {
    'use strict';

    var NONCE   = shReactionsAdmin.nonce;
    var AJAX    = shReactionsAdmin.ajax;
    var PALETTE = shReactionsAdmin.palette;

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

    // ── Color picker init ─────────────────────────────────────────────────────

    function initColorPicker($card) {
        $card.find('.sh-color-picker-inline').each(function () {
            if ($(this).hasClass('wp-color-picker')) return;
            if (typeof $.fn.wpColorPicker === 'undefined') return;
            var $inp = $(this);
            $inp.wpColorPicker({
                palettes: PALETTE,
                change: function (e, ui) {
                    var color = ui.color.toString();
                    $inp.val(color);
                    var $c = $inp.closest('.sh-rule-card');
                    $c.find('.sh-type-icon-on~i').css('color', color);
                    $c.find('.sh-rule-meta span[style*=background]').css('background', color + '22');
                    $c.find('.sh-rule-meta i').css('color', color);
                },
                clear: function () { $inp.val('#2271b1'); }
            });
        });
    }

    // ── Types: edit / cancel ──────────────────────────────────────────────────

    $(document).on('click', '.sh-type-edit-btn', function (e) {
        e.stopPropagation();
        var $card = $(this).closest('.sh-rule-card');
        $card.find('.sh-rule-form').slideDown(150);
        initColorPicker($card);
    });

    $(document).on('click', '.sh-type-cancel-btn', function () {
        $(this).closest('.sh-rule-form').slideUp(150);
    });

    // Mode dropdown degisince limit alanini goster/gizle
    $(document).on('change', '.sh-type-mode', function () {
        var $wrap = $(this).closest('.sh-rule-form').find('.sh-type-limit-wrap');
        if ($(this).val() === 'cumulative') {
            $wrap.show();
        } else {
            $wrap.hide();
        }
    });

    // ── Code panel: Tab + Customize params ───────────────────────────────────

    // Tab switch
    $(document).on('click', '.sh-code-tab-btn', function () {
        var $card  = $(this).closest('.sh-rule-card, .sh-new-btn-card');
        var tab    = $(this).data('tab');
        // Tab butonlari
        $card.find('.sh-code-tab-btn').each(function(){
            var active = $(this).data('tab') === tab;
            $(this).css({ background: active ? 'var(--ts-primary)' : 'var(--ts-white)', color: active ? '#fff' : 'var(--ts-gray-700)' });
        });
        // Paneller
        $card.find('.sh-code-panel').each(function(){
            $(this).toggle($(this).data('panel') === tab);
        });
    });

    // Customize params checkbox
    $(document).on('change', '.sh-code-customize', function () {
        var $card      = $(this).closest('.sh-rule-card, .sh-new-btn-card');
        var customize  = $(this).is(':checked');
        var $data      = $card.find('.sh-code-data');
        var extendCode = customize ? $data.data('extend-full') : $data.data('extend-min');
        var fnCode     = customize ? $data.data('fn-full')     : $data.data('fn-min');
        var phpCode    = customize ? $data.data('php-full').replace(/&#10;/g, '\n') : $data.data('php-min');
        $card.find('.sh-code-extend-min').text(extendCode);
        $card.find('.sh-code-fn-min').text(fnCode);
        $card.find('.sh-code-php-min').text(phpCode);
    });

    // ── Types: icon live preview (FA class) ──────────────────────────────────

    $(document).on('input', '.sh-type-icon-off', function () {
        var val   = $(this).val();
        var $prev = $(this).closest('.sh-icon-field').find('.sh-icon-preview');
        if (val && !isNumeric(val)) {
            $prev.html('<i class="' + val + '" style="font-size:18px;color:#9ca3af;"></i>');
        }
    });

    $(document).on('input', '.sh-type-icon-on', function () {
        var val   = $(this).val();
        var $card = $(this).closest('.sh-rule-card');
        var color = $card.find('.sh-type-color').val() || '#2271b1';
        var $prev = $(this).closest('.sh-icon-field').find('.sh-icon-preview');
        if (val && !isNumeric(val)) {
            $prev.html('<i class="' + val + '" style="font-size:18px;color:' + color + ';"></i>');
        }
    });

    function isNumeric(val) { return /^\d+$/.test(String(val).trim()); }

    // ── Types: WP Media Picker for icons ─────────────────────────────────────

    $(document).on('click', '.sh-media-pick-btn', function (e) {
        e.preventDefault();
        var $btn    = $(this);
        var $field  = $btn.closest('.sh-icon-field');
        var $input  = $field.find('.sh-type-icon-off, .sh-type-icon-on').first();
        var $preview = $field.find('.sh-icon-preview');

        var frame = wp.media({
            title:    'Select Icon',
            button:   { text: 'Use this icon' },
            multiple: false,
            library:  { type: ['image', 'image/svg+xml'] }
        });

        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $input.val(att.id);
            $preview.html('<img src="' + att.url + '" style="width:22px;height:22px;object-fit:contain;border-radius:3px;" alt="">');
        });

        frame.open();
    });

    // Clear icon — attachment ID'yi temizle
    $(document).on('click', '.sh-icon-clear-btn', function () {
        var $field  = $(this).closest('.sh-icon-field');
        var $input  = $field.find('.sh-type-icon-off, .sh-type-icon-on').first();
        var $preview = $field.find('.sh-icon-preview');
        $input.val('');
        $preview.html('<i class="far fa-circle" style="font-size:18px;color:#9ca3af;"></i>');
    });

    // ── Types: save ───────────────────────────────────────────────────────────

    $(document).on('click', '.sh-type-save-btn', function () {
        var $btn  = $(this);
        var $card = $btn.closest('.sh-rule-card');
        var key   = String($card.data('key') || '');

        // Yeni type ise key input'tan al
        if (!key || key.indexOf('new_') === 0) {
            key = ($card.find('.sh-new-type-key-input').val() || '')
                .trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
            if (!key) { toast('Key is required', true); return; }
            $card.attr('data-key', key);
        }

        $btn.text('Saving...').prop('disabled', true);

        $.post(AJAX, {
            action:       'sh_reactions_save_type',
            nonce:        NONCE,
            key:          key,
            label:        $card.find('.sh-type-label').val(),
            label_on:     $card.find('.sh-type-label-on').val(),
            icon_off:     $card.find('.sh-type-icon-off').val(),
            icon_on:      $card.find('.sh-type-icon-on').val(),
            color:        $card.find('.sh-type-color').val(),
            notify_event: $card.find('.sh-type-notify').val(),
            mode:         $card.find('.sh-type-mode').val(),
            limit:        $card.find('.sh-type-limit').val(),
            enabled:      $card.find('.sh-type-enabled-toggle').is(':checked') ? 1 : 0
        }, function (res) {
            if (res.success) {
                $card.replaceWith(res.data.html);
                toast('Saved!');
            } else {
                toast(res.data || 'Error', true);
                $btn.text('Save').prop('disabled', false);
            }
        });
    });

    // ── Types: delete ─────────────────────────────────────────────────────────

    $(document).on('click', '.sh-type-delete-btn', function () {
        if (!confirm('Delete this reaction type?')) return;
        var $card = $(this).closest('.sh-rule-card');
        var key   = $card.data('key');
        $.post(AJAX, { action: 'sh_reactions_delete_type', nonce: NONCE, key: key }, function (res) {
            if (res.success) { $card.fadeOut(200, function () { $(this).remove(); }); }
            else { toast(res.data || 'Error', true); }
        });
    });

    // ── Types: enabled toggle — AJAX ile kaydet ──────────────────────────────

    $(document).on('change', '.sh-type-enabled-toggle', function () {
        var $chk  = $(this);
        var $card = $chk.closest('.sh-rule-card');
        var key   = String($card.data('key') || '');

        // Optimistic UI
        $card.toggleClass('sh-rule-inactive', !$chk.is(':checked'));

        if (!key || key.indexOf('new_') === 0) return; // Henuz kaydedilmemis yeni type

        $.post(AJAX, {
            action:         'sh_reactions_save_type',
            nonce:          NONCE,
            key:            key,
            toggle_enabled: 1
        }, function (res) {
            if (!res.success) {
                // Revert
                var current = $chk.is(':checked');
                $chk.prop('checked', !current);
                $card.toggleClass('sh-rule-inactive', current);
                toast(res.data || 'Error', true);
            }
        });
    });

    // ── Types: add new — prompt yok, direkt inline form ───────────────────────

    function shAddTypeCard() {
        var uid  = 'new_' + Date.now();
        var $card = $(
            '<div class="sh-rule-card sh-new-type-card" data-key="' + uid + '">' +
            '<div class="sh-rule-header" style="background:#f0f7ff;border-radius:8px 8px 0 0;">' +
            '<div class="sh-rule-meta" style="flex:1;">' +
            '<input type="text" class="sh-input sh-new-type-key-input" placeholder="Type key (e.g. clap)" style="max-width:200px;font-weight:600;">' +
            '<span style="font-size:12px;color:#6b7280;margin-left:8px;">Enter a key and fill the form below</span>' +
            '</div>' +
            '<div class="sh-rule-actions"><div class="sh-rule-btns">' +
            '<button type="button" class="sh-rule-btn sh-rule-btn-delete sh-type-cancel-new-btn" title="Cancel"><span class="dashicons dashicons-no"></span></button>' +
            '</div></div></div>' +
            '<div class="sh-rule-form" style="display:block;">' +
            '<div class="sh-form-row">' +
            '<div class="sh-form-col"><label>Label</label><input type="text" class="sh-input sh-type-label" placeholder="Like"></div>' +
            '<div class="sh-form-col"><label>Label (Active)</label><input type="text" class="sh-input sh-type-label-on" placeholder="Liked"></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Color</label><input type="text" class="sh-input sh-type-color sh-color-picker-inline" value="#2271b1" style="width:100px;"></div>' +
            '</div><div class="sh-form-row">' +
            '<div class="sh-form-col"><label>Icon Off</label><div style="display:flex;align-items:center;gap:8px;"><input type="text" class="sh-input sh-type-icon-off" value="far fa-circle" style="max-width:160px;"><i class="far fa-circle" style="font-size:18px;color:#9ca3af;"></i></div></div>' +
            '<div class="sh-form-col"><label>Icon On</label><div style="display:flex;align-items:center;gap:8px;"><input type="text" class="sh-input sh-type-icon-on" value="fas fa-circle" style="max-width:160px;"><i class="fas fa-circle" style="font-size:18px;color:#2271b1;"></i></div></div>' +
            '<div class="sh-form-col"><label>Notify Event</label><input type="text" class="sh-input sh-type-notify" placeholder="new-follower"></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Toggleable</label><label class="sh-toggle" style="margin-top:6px;"><input type="checkbox" class="sh-type-toggle" value="1" checked><span class="sh-toggle-slider"></span></label></div>' +
            '</div><div class="sh-form-footer">' +
            '<button type="button" class="sh-btn sh-btn-primary sh-type-save-btn">Save</button>' +
            '<button type="button" class="sh-btn sh-btn-ghost sh-type-cancel-new-btn">Cancel</button>' +
            '</div></div></div>'
        );

        $('#sh-types-list').prepend($card);
        $card.find('.sh-new-type-key-input').focus();
        initColorPicker($card);

        $card.find('.sh-new-type-key-input').on('input', function () {
            var k = $(this).val().toLowerCase().replace(/[^a-z0-9_]/g, '');
            $card.attr('data-key', k || uid);
        });
    }

    $(document).on('click', '.sh-type-cancel-new-btn', function () {
        $(this).closest('.sh-new-type-card').remove();
    });

    $('#sh-add-type-btn, #sh-add-type-btn2').on('click', shAddTypeCard);

    // ── Placements: add ───────────────────────────────────────────────────────

    // 1. dropdown degisince 2. dropdown'u filtrele
    $('#sh-new-obj-type').on('change', function () {
        var type = $(this).val();
        var $sub = $('#sh-new-obj-subtype');
        // Tum option'lari gizle
        $sub.find('option').each(function () {
            var group = $(this).data('group');
            if (!group || group === type) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // Ilk gorunen option'u sec
        $sub.val('');
        var $first = $sub.find('option:visible').first();
        if ($first.length) $sub.val($first.val());
    });

    $('#sh-add-placement').on('click', function () {
        var type    = $('#sh-new-obj-type').val();
        var subtype = $('#sh-new-obj-subtype').val();
        // key olustur: 'post' veya 'post:product'
        var key = subtype ? (type + ':' + subtype) : type;
        if (!key) return;
        var tpl = $('#sh-placement-tpl').html();
        if (!tpl) return;
        tpl = tpl.replace(/__KEY__/g, key);
        $('#sh-placements-list').append(tpl);
        toast('Placement added. Save to persist.');
    });

    $(document).on('click', '.sh-remove-placement', function () {
        if (!confirm('Remove this placement?')) return;
        $(this).closest('.sh-placement-card').remove();
    });

    // ── Placements: style dropdown -> live preview + twig code ────────────────

    function buildPreview(style, iconOff, iconOn, label, labelOn, color, iconOffUrl, iconOnUrl) {
        // iconOff/iconOn: FA class veya attachment ID
        // iconOffUrl/iconOnUrl: attachment URL (PHP'den data-icon-off-url ile gelir)
        var icon;
        if (iconOffUrl) {
            icon = '<img src="' + iconOffUrl + '" style="width:1em;height:1em;object-fit:contain;vertical-align:middle;pointer-events:none;" alt="">';
        } else if (iconOff && isNumeric(iconOff)) {
            icon = ''; // URL yok, bos
        } else {
            icon = '<i class="' + (iconOff || 'far fa-circle') + '" style="pointer-events:none;"></i>';
        }
        var cnt  = '<span style="font-size:12px;font-weight:600;">42</span>';
        var lbl  = '<span style="font-size:12px;">' + label + '</span>';
        var base = 'display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;border:none;background:none;cursor:default;font-size:13px;color:#374151;';
        switch (style) {
            case 'icon-only':  return '<span style="' + base + '">' + icon + '</span>';
            case 'icon-count': return '<span style="' + base + '">' + icon + ' ' + cnt + '</span>';
            case 'icon-text':  return '<span style="' + base + '">' + icon + ' ' + lbl + '</span>';
            case 'text-only':  return '<span style="' + base + '">' + lbl + '</span>';
            case 'pill':       return '<span style="' + base + 'border:1.5px solid ' + color + ';border-radius:999px;color:' + color + ';">' + icon + ' ' + lbl + '</span>';
            case 'pill-count': return '<span style="' + base + 'border:1.5px solid ' + color + ';border-radius:999px;color:' + color + ';">' + icon + ' ' + cnt + '</span>';
            default:           return '<span style="' + base + '">' + icon + ' ' + cnt + '</span>';
        }
    }

    $(document).on('change', '.sh-style-select', function () {
        var $sel    = $(this);
        var style   = $sel.val();
        var iconOff = $sel.data('icon-off') || 'far fa-circle';
        var iconOn  = $sel.data('icon-on')  || 'fas fa-circle';
        var label   = $sel.data('label')    || 'Like';
        var labelOn = $sel.data('label-on') || label;
        var color   = $sel.data('color')    || '#6b7280';
        var objType = $sel.data('object-type') || 'post';
        var type    = $sel.data('type')     || 'like';
        var $row    = $sel.closest('tr');

        // objType normalize — postproduct gibi legacy key'leri duzelt
        var validTypes = ['post', 'user', 'comment', 'term'];
        var twigObjType = validTypes.indexOf(objType) !== -1 ? objType : 'post';

        // ID degiskeni object type'a gore
        var idVarMap = { 'post': 'post.ID', 'user': 'user.ID', 'comment': 'comment.ID', 'term': 'term.term_id' };
        var twigIdVar = idVarMap[twigObjType] || 'post.ID';

        $row.find('.sh-preview-cell').html(buildPreview(style, iconOff, iconOn, label, labelOn, color, '', ''));

        var twigCode = "{{ function('salt_reaction_button', " + twigIdVar + ", '" + twigObjType + "', '" + type + "', {'style': '" + style + "', 'class': ''}) }}";
        $row.find('.sh-twig-code').text(twigCode).attr('title', twigCode);
    });

    // ── Generator tab ─────────────────────────────────────────────────────────

    function shGenGetIdVar(objType) {
        var map = { 'post': 'post.ID', 'user': 'user.ID', 'comment': 'comment.ID', 'term': 'term.term_id' };
        return map[objType] || 'post.ID';
    }

    function shGenBuildTwig($card) {
        var objType  = $card.find('.sh-btn-obj-type').val()  || 'post';
        var type     = $card.find('.sh-btn-reaction').val()  || 'like';
        var style    = $card.find('.sh-btn-style').val()     || 'icon-count';
        var cssClass = $card.find('.sh-btn-class').val().trim();
        var showCnt  = $card.find('.sh-btn-show-count').is(':checked');
        var reqLogin = $card.find('.sh-btn-require-login').is(':checked');
        var idVar    = shGenGetIdVar(objType);
        var opts     = ["'style': '" + style + "'"];
        if (cssClass)  opts.push("'class': '" + cssClass + "'");
        if (!showCnt)  opts.push("'show_count': false");
        if (!reqLogin) opts.push("'require_login': false");
        return "{{ function('salt_reaction_button', " + idVar + ", '" + objType + "', '" + type + "', {" + opts.join(', ') + "}) }}";
    }

    function shGenBuildPreview($card) {
        var style    = $card.find('.sh-btn-style').val() || 'icon-count';
        var $rxn     = $card.find('.sh-btn-reaction option:selected');
        var iconOff  = $rxn.data('icon-off')     || 'far fa-circle';
        var iconOn   = $rxn.data('icon-on')      || 'fas fa-circle';
        var iconOffUrl = $rxn.data('icon-off-url') || '';
        var iconOnUrl  = $rxn.data('icon-on-url')  || '';
        var label    = $rxn.data('label')        || 'Like';
        var labelOn  = $rxn.data('label-on')     || label;
        var color    = $rxn.data('color')        || '#6b7280';
        return buildPreview(style, iconOff, iconOn, label, labelOn, color, iconOffUrl, iconOnUrl);
    }

    function shGenUpdateCard($card) {
        $card.find('.sh-btn-preview-cell').html(shGenBuildPreview($card));

        var objType  = $card.find('.sh-btn-obj-type').val()  || 'post';
        var type     = $card.find('.sh-btn-reaction').val()  || 'like';
        var style    = $card.find('.sh-btn-style').val()     || 'icon-count';
        var cssClass = $card.find('.sh-btn-class').val().trim();
        var showCnt  = $card.find('.sh-btn-show-count').is(':checked');
        var reqLogin = $card.find('.sh-btn-require-login').is(':checked');
        var idVar    = shGenGetIdVar(objType);

        // Minimal codes (no params)
        var extendMin = "{{ post.reaction_button('" + type + "')|raw }}";
        var fnMin     = "{{ function('salt_reaction_button', " + idVar + ", '" + objType + "', '" + type + "') }}";
        var phpMin    = "salt_reaction_button(get_the_ID(), '" + objType + "', '" + type + "');";

        // Full codes (with params)
        var opts = ["'style': '" + style + "'"];
        if (cssClass)  opts.push("'class': '" + cssClass + "'");
        opts.push("'require_login': " + (reqLogin ? 'true' : 'false'));
        opts.push("'show_count': " + (showCnt ? 'true' : 'false'));
        var optsStr = opts.join(', ');

        var extendFull = "{{ post.reaction_button('" + type + "', {" + optsStr + "})|raw }}";
        var fnFull     = "{{ function('salt_reaction_button', " + idVar + ", '" + objType + "', '" + type + "', {" + optsStr + "}) }}";
        var phpFull    = "salt_reaction_button(get_the_ID(), '" + objType + "', '" + type + "', [\n" +
                         "    'style'         => '" + style + "'," +
                         (cssClass ? "\n    'class'         => '" + cssClass + "'," : '') +
                         "\n    'require_login' => " + (reqLogin ? 'true' : 'false') + "," +
                         "\n    'show_count'    => " + (showCnt ? 'true' : 'false') + "," +
                         "\n]);";

        // Customize secili mi?
        var customize = $card.find('.sh-code-customize').is(':checked');

        $card.find('.sh-code-extend-min').text(customize ? extendFull : extendMin);
        $card.find('.sh-code-fn-min').text(customize ? fnFull : fnMin);
        $card.find('.sh-code-php-min').text(customize ? phpFull : phpMin);

        // data attrs guncelle
        var $data = $card.find('.sh-code-data');
        $data.data('extend-min', extendMin).data('extend-full', extendFull);
        $data.data('fn-min', fnMin).data('fn-full', fnFull);
        $data.data('php-min', phpMin).data('php-full', phpFull);
    }

    // Subtype dropdown'u doldur
    function shGenFillSubtype($card, objType, currentVal) {
        var $sub = $card.find('.sh-btn-subtype');
        var opts = SH_GEN_SUBTYPES[objType] || [];
        $sub.empty();
        opts.forEach(function(o) {
            $sub.append('<option value="' + o.value + '">' + o.label + '</option>');
        });
        if (currentVal !== undefined) $sub.val(currentVal);
    }

    // Object type degisince subtype'i guncelle
    $(document).on('change', '.sh-btn-obj-type', function () {
        var $card = $(this).closest('.sh-rule-card');
        shGenFillSubtype($card, $(this).val(), '');
        shGenUpdateCard($card);
    });

    // Herhangi bir alan degisince preview + twig guncelle
    $(document).on('change input', '.sh-btn-reaction, .sh-btn-style, .sh-btn-class, .sh-btn-show-count, .sh-btn-require-login, .sh-btn-subtype', function () {
        shGenUpdateCard($(this).closest('.sh-rule-card'));
    });

    // Edit butonu — form ac/kapat
    $(document).on('click', '.sh-btn-expand-btn', function (e) {
        e.stopPropagation();
        var $btn  = $(this);
        var $card = $btn.closest('.sh-rule-card');
        var $form = $card.find('.sh-rule-form');
        if ($form.is(':hidden')) {
            $form.slideDown(150, function () {
                // Animasyon bittikten sonra subtype doldur ve preview guncelle
                var objType = $card.find('.sh-btn-obj-type').val() || 'post';
                var current = $card.find('.sh-btn-subtype').data('current') || '';
                shGenFillSubtype($card, objType, current);
                shGenUpdateCard($card);
            });
        } else {
            $form.slideUp(150);
        }
    });

    // Cancel
    $(document).on('click', '.sh-btn-cancel-btn', function () {
        $(this).closest('.sh-rule-form').slideUp(150);
    });

    // Save existing card
    $(document).on('click', '.sh-btn-save-btn', function () {
        var $btn  = $(this);
        var $card = $btn.closest('.sh-rule-card');
        var key   = $btn.data('key');
        var isNew = String(key).indexOf('new_') === 0;
        $btn.text('Saving...').prop('disabled', true);
        $.post(AJAX, {
            action:        isNew ? 'sh_reactions_save_button' : 'sh_reactions_save_button',
            nonce:         NONCE,
            key:           isNew ? '' : key,
            object_type:   $card.find('.sh-btn-obj-type').val(),
            subtype:       $card.find('.sh-btn-subtype').val(),
            type:          $card.find('.sh-btn-reaction').val(),
            style:         $card.find('.sh-btn-style').val(),
            class:         $card.find('.sh-btn-class').val().trim(),
            show_count:    $card.find('.sh-btn-show-count').is(':checked') ? 1 : 0,
            require_login: $card.find('.sh-btn-require-login').is(':checked') ? 1 : 0
        }, function (res) {
            $btn.text('Save').prop('disabled', false);
            if (res.success) {
                toast('Saved!');
                if (isNew) {
                    // Reload to get server-rendered card
                    window.location.reload();
                } else {
                    $card.find('.sh-rule-form').slideUp(150);
                }
            } else {
                toast(res.data || 'Error', true);
            }
        });
    });

    // Add new button — yeni bos card ekle
    function shGenAddCard() {
        var uid  = 'new_' + Date.now();
        var $card = $('<div class="sh-rule-card sh-new-btn-card" data-key="' + uid + '"></div>');
        $card.html(
            '<div class="sh-rule-header" style="background:var(--ts-gray-50);">' +
            '<div class="sh-rule-meta" style="flex:1;"><strong style="color:var(--ts-gray-600);">New Button</strong></div>' +
            '<div class="sh-rule-actions"><div class="sh-rule-btns">' +
            '<button type="button" class="sh-rule-btn sh-rule-btn-delete sh-btn-cancel-new" title="Cancel"><span class="dashicons dashicons-no"></span></button>' +
            '</div></div></div>' +
            '<div class="sh-rule-form" style="display:block;">' +
            '<div class="sh-form-row">' +
            '<div class="sh-form-col sh-form-col-sm"><label>Object Type</label><select class="sh-select sh-btn-obj-type"><option value="post">Post</option><option value="user">User</option><option value="comment">Comment</option><option value="term">Term</option></select></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Subtype</label><select class="sh-select sh-btn-subtype"></select></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Reaction</label><select class="sh-select sh-btn-reaction">' +
            Object.keys(SH_GEN_TYPES).map(function(k){ var t=SH_GEN_TYPES[k]; return '<option value="'+k+'" data-icon-off="'+(t.icon_off||'far fa-circle')+'" data-icon-on="'+(t.icon_on||'fas fa-circle')+'" data-icon-off-url="'+(t.icon_off_url||'')+'" data-icon-on-url="'+(t.icon_on_url||'')+'" data-label="'+(t.label||k)+'" data-label-on="'+(t.label_on||t.label||k)+'" data-color="'+(t.color||'#6b7280')+'">'+(t.label||k)+'</option>'; }).join('') +
            '</select></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Style</label><select class="sh-select sh-btn-style">' +
            Object.keys(SH_GEN_STYLES).map(function(k){ return '<option value="'+k+'">'+SH_GEN_STYLES[k]+'</option>'; }).join('') +
            '</select></div>' +
            '<div class="sh-form-col"><label>CSS Class</label><input type="text" class="sh-input sh-btn-class" placeholder="btn-sm my-class"></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Show Count</label><label class="sh-toggle" style="margin-top:6px;"><input type="checkbox" class="sh-btn-show-count" checked><span class="sh-toggle-slider"></span></label></div>' +
            '<div class="sh-form-col sh-form-col-sm"><label>Require Login</label><label class="sh-toggle" style="margin-top:6px;"><input type="checkbox" class="sh-btn-require-login" checked><span class="sh-toggle-slider"></span></label></div>' +
            '</div>' +
            '<div style="padding:12px;background:var(--ts-gray-50);border-radius:var(--ts-radius);margin-bottom:12px;">' +
            '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">' +
            '<div style="display:flex;gap:4px;">' +
            '<button type="button" class="sh-code-tab-btn active" data-tab="twig" style="padding:4px 10px;border-radius:4px;border:1px solid var(--ts-gray-300);background:var(--ts-primary);color:#fff;font-size:11px;font-weight:600;cursor:pointer;">Twig</button>' +
            '<button type="button" class="sh-code-tab-btn" data-tab="php" style="padding:4px 10px;border-radius:4px;border:1px solid var(--ts-gray-300);background:var(--ts-white);color:var(--ts-gray-700);font-size:11px;font-weight:600;cursor:pointer;">PHP</button>' +
            '</div>' +
            '<label style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--ts-gray-600);cursor:pointer;margin-left:16px;"><input type="checkbox" class="sh-code-customize" style="margin:0;"> Customize params</label>' +
            '</div>' +
            '<div class="sh-code-panel" data-panel="twig">' +
            '<div class="sh-twig-wrap" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;"><code class="sh-twig-code sh-code-extend-min" style="background:var(--ts-gray-100);padding:5px 10px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;flex:1;cursor:pointer;"></code><button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;">&#128203;</button></div>' +
            '<div class="sh-twig-wrap" style="display:flex;align-items:center;gap:6px;"><code class="sh-twig-code sh-code-fn-min" style="background:var(--ts-gray-100);padding:5px 10px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;flex:1;cursor:pointer;"></code><button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;">&#128203;</button></div>' +
            '</div>' +
            '<div class="sh-code-panel" data-panel="php" style="display:none;">' +
            '<div class="sh-twig-wrap" style="display:flex;align-items:center;gap:6px;"><code class="sh-twig-code sh-code-php-min" style="background:var(--ts-gray-100);padding:5px 10px;border-radius:4px;font-size:11px;font-family:Consolas,monospace;flex:1;cursor:pointer;"></code><button type="button" class="sh-copy-btn sh-icon-btn" title="Copy" style="color:var(--ts-primary);flex-shrink:0;">&#128203;</button></div>' +
            '</div>' +
            '<span class="sh-code-data" style="display:none;"></span>' +
            '</div>' +
            '<div class="sh-form-footer"><button type="button" class="sh-btn sh-btn-primary sh-btn-save-btn" data-key="' + uid + '">Save</button><button type="button" class="sh-btn sh-btn-ghost sh-btn-cancel-new">Cancel</button></div>' +
            '</div>'
        );
        $('#sh-saved-buttons-list').prepend($card);
        $('#sh-gen-empty').hide();
        shGenFillSubtype($card, 'post', '');
        shGenUpdateCard($card);
        $card.find('.sh-btn-obj-type').focus();
    }

    $(document).on('click', '.sh-btn-cancel-new', function () {
        $(this).closest('.sh-new-btn-card').remove();
        if ($('#sh-saved-buttons-list .sh-rule-card').length === 0) $('#sh-gen-empty').show();
    });

    $('#sh-gen-add-btn, #sh-gen-add-btn2').on('click', shGenAddCard);

    function shGenUpdate() {
        var objType    = $('#sh-gen-obj-type').val() || 'post';
        var style      = $('#sh-gen-style').val() || 'icon-count';
        var $rxn       = $('#sh-gen-reaction option:selected');
        var type       = $rxn.val() || 'like';
        var iconOff    = $rxn.data('icon-off')     || 'far fa-circle';
        var iconOn     = $rxn.data('icon-on')      || 'fas fa-circle';
        var iconOffUrl = $rxn.data('icon-off-url') || '';
        var iconOnUrl  = $rxn.data('icon-on-url')  || '';
        var label      = $rxn.data('label')        || 'Like';
        var labelOn    = $rxn.data('label-on')     || label;
        var color      = $rxn.data('color')        || '#6b7280';
        var idVar      = shGenGetIdVar(objType);
        var cssClass   = $('#sh-gen-class').val().trim();
        var showCount  = $('#sh-gen-show-count').is(':checked');
        var reqLogin   = $('#sh-gen-require-login').is(':checked');

        // Preview
        $('#sh-gen-preview').html(buildPreview(style, iconOff, iconOn, label, labelOn, color, iconOffUrl, iconOnUrl));

        // Twig code — sadece default'tan farkli olanlari ekle
        var opts = [];
        opts.push("'style': '" + style + "'");
        if (cssClass)    opts.push("'class': '" + cssClass + "'");
        if (!showCount)  opts.push("'show_count': false");
        if (!reqLogin)   opts.push("'require_login': false");

        var twigCode = "{{ function('salt_reaction_button', " + idVar + ", '" + objType + "', '" + type + "', {" + opts.join(', ') + "}) }}";
        $('#sh-gen-twig').text(twigCode).attr('title', twigCode);
    }

    // Copy generator twig
    $('#sh-gen-copy-btn').on('click', function () {
        var code = $('#sh-gen-twig').text().trim();
        if (!code) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function () { toast('Copied!'); }).catch(function () { fallbackCopy(code); });
        } else { fallbackCopy(code); }
    });

    // Save button
    $('#sh-gen-save-btn').on('click', function () {
        var $btn      = $(this);
        var objType   = $('#sh-gen-obj-type').val();
        var subtype   = $('#sh-gen-subtype').val();
        var type      = $('#sh-gen-reaction').val();
        var style     = $('#sh-gen-style').val();
        var cssClass  = $('#sh-gen-class').val().trim();
        var showCount = $('#sh-gen-show-count').is(':checked') ? 1 : 0;
        var reqLogin  = $('#sh-gen-require-login').is(':checked') ? 1 : 0;
        $btn.text('Saving...').prop('disabled', true);
        $.post(AJAX, {
            action:        'sh_reactions_save_button',
            nonce:         NONCE,
            object_type:   objType,
            subtype:       subtype,
            type:          type,
            style:         style,
            class:         cssClass,
            show_count:    showCount,
            require_login: reqLogin
        }, function (res) {
            $btn.text('Save').prop('disabled', false);
            if (res.success) { toast('Saved! Reload to see in list.'); }
            else { toast(res.data || 'Error', true); }
        });
    });

    // Delete saved button
    $(document).on('click', '.sh-gen-delete-btn', function () {
        if (!confirm('Remove this saved button?')) return;
        var $card = $(this).closest('.sh-rule-card, tr');
        var key   = $(this).data('key');
        $.post(AJAX, { action: 'sh_reactions_delete_button', nonce: NONCE, key: key }, function (res) {
            if (res.success) { $card.fadeOut(200, function () { $(this).remove(); }); toast('Removed.'); }
            else { toast(res.data || 'Error', true); }
        });
    });

    // Toggle saved button active/inactive
    $(document).on('change', '.sh-btn-active-toggle', function () {
        var $chk  = $(this);
        var $card = $chk.closest('.sh-rule-card');
        var key   = $chk.data('key');
        $card.toggleClass('sh-rule-inactive', !$chk.is(':checked'));
        $.post(AJAX, { action: 'sh_reactions_toggle_button', nonce: NONCE, key: key }, function (res) {
            if (!res.success) {
                $chk.prop('checked', !$chk.is(':checked'));
                $card.toggleClass('sh-rule-inactive', !$chk.is(':checked'));
                toast('Error', true);
            }
        });
    });

    $(document).on('click', '.sh-copy-btn, .sh-twig-code', function () {
        var $wrap = $(this).closest('.sh-twig-wrap');
        var code  = $wrap.find('.sh-twig-code').text().trim();
        if (!code) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code)
                .then(function () { toast('Copied!'); })
                .catch(function () { fallbackCopy(code); });
        } else {
            fallbackCopy(code);
        }
    });

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

})(jQuery);
