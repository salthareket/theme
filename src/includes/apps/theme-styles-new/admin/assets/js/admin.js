/**
 * Theme Styles New - Admin JavaScript
 * Modern, reactive, professional admin interface
 * 
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    const ThemeStylesAdmin = {
        
        data: {},
        originalData: {},
        hasChanges: false,
        previewTimeout: null,
        
        /**
         * Initialize
         */
        init() {
            this.data = themeStylesNew.data || {};
            this.originalData = JSON.parse(JSON.stringify(this.data));
            
            this.bindEvents();
            this.initColorPickers();
            this.trackChanges();
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            // Module navigation
            $('.theme-styles-modules-nav a').on('click', (e) => {
                e.preventDefault();
                const module = $(e.currentTarget).data('module');
                this.switchModule(module);
            });
            
            // Save
            $('#ts-save').on('click', () => this.save());

            // Ctrl+S shortcut
            $(document).on('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    this.save();
                }
            });
            
            // Preset actions
            $('#ts-load-preset').on('click', () => this.showModal('#ts-load-preset-modal'));
            $('#ts-save-preset').on('click', () => this.showModal('#ts-save-preset-modal'));
            $('#ts-import-preset').on('click', () => this.showModal('#ts-import-preset-modal'));
            $('#ts-revert').on('click', () => this.revert());
            
            // Preset modal actions - event delegation (PHP render + JS refresh için)
            $(document).on('click', '.ts-load-preset-btn', (e) => {
                this.loadPreset($(e.currentTarget).data('preset'));
            });
            $(document).on('click', '.ts-export-preset-btn', (e) => {
                this.exportPreset($(e.currentTarget).data('preset'));
            });
            $(document).on('click', '.ts-delete-preset-btn', (e) => {
                this.deletePreset($(e.currentTarget).data('preset'));
            });
            $(document).on('click', '.ts-duplicate-preset-btn', (e) => {
                this.duplicatePreset($(e.currentTarget).data('preset'));
            });
            $(document).on('click', '.ts-rename-preset-btn', (e) => {
                const $btn = $(e.currentTarget);
                this.renamePreset($btn.data('preset'), $btn.data('label'));
            });
            
            $('#ts-save-preset-confirm').on('click', () => this.savePreset());
            $('#ts-import-preset-confirm').on('click', () => this.importPreset());
            
            // Modal close
            $('.modal-close, .modal-overlay').on('click', (e) => {
                $(e.currentTarget).closest('.theme-styles-modal').fadeOut(200);
            });
            
            // Live preview
            $('#ts-live-preview').on('change', (e) => {
                if ($(e.currentTarget).is(':checked')) {
                    this.showPreview();
                } else {
                    this.hidePreview();
                }
            });
            
            $('#ts-close-preview').on('click', () => {
                $('#ts-live-preview').prop('checked', false);
                this.hidePreview();
            });
            
            // Prevent accidental navigation
            $(window).on('beforeunload', () => {
                if (this.hasChanges) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            // ── WP Media Library - image select ───────────────────
            $(document).on('click', '.ts-media-select', (e) => {
                e.preventDefault();
                const target = $(e.currentTarget).data('target');
                this.openMediaLibrary(target);
            });

            // ── WP Media Library - image remove ───────────────────
            $(document).on('click', '.ts-media-remove', (e) => {
                e.preventDefault();
                const target = $(e.currentTarget).data('target');
                this.removeMediaImage(target);
            });

            // ── Background type switcher ───────────────────────────
            $(document).on('click', '.ts-bg-type-btn', (e) => {
                const $btn    = $(e.currentTarget);
                const type    = $btn.data('type');
                const bgId    = $btn.data('target');
                const $wrap   = $btn.closest('.ts-bg-field-wrapper');

                $wrap.find('.ts-bg-type-btn').removeClass('active');
                $btn.addClass('active');
                $wrap.find('.ts-bg-tab').removeClass('active').hide();
                $wrap.find(`.ts-bg-tab[data-tab="${type}"]`).addClass('active').show();
                $wrap.find('.ts-bg-type-value').val(type).trigger('change');
            });

            // ── Background position grid ───────────────────────────
            $(document).on('click', '.ts-bg-pos-btn', (e) => {
                const $btn  = $(e.currentTarget);
                const val   = $btn.data('value');
                const bgId  = $btn.data('target');
                $btn.closest('.ts-bg-position-grid').find('.ts-bg-pos-btn').removeClass('active');
                $btn.addClass('active');
                $btn.closest('.ts-bg-field-wrapper').find('.ts-bg-position-value').val(val).trigger('change');
            });

            // ── Background size button group ───────────────────────
            $(document).on('click', '.ts-btn-group-item', (e) => {
                const $btn   = $(e.currentTarget);
                const val    = $btn.data('value');
                const $group = $btn.closest('.ts-button-group');
                $group.find('.ts-btn-group-item').removeClass('active');
                $btn.addClass('active');
                // Custom size panel
                const $wrap = $btn.closest('.ts-bg-field-wrapper');
                if ($wrap.length) {
                    $wrap.find('.ts-bg-custom-size').toggleClass('hidden', val !== 'custom');
                }
                $group.trigger('change');
                this.hasChanges = true;
                this.collectData();
                this.showUnsavedBadge(true);
            });
        },
        
        /**
         * Open WP Media Library for image selection
         */
        openMediaLibrary(target) {
            if (this._mediaFrame) {
                this._mediaFrame.open();
                this._mediaTarget = target;
                return;
            }

            this._mediaTarget = target;
            this._mediaFrame = wp.media({
                title: 'Select Background Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            this._mediaFrame.on('select', () => {
                const attachment = this._mediaFrame.state().get('selection').first().toJSON();
                const bgId       = this._mediaTarget;
                const url        = attachment.url;
                const id         = attachment.id;

                // Hidden inputs güncelle
                $(`.ts-bg-image-id[data-target="${bgId}"]`).val(id).trigger('change');
                $(`.ts-bg-image-url[data-target="${bgId}"]`).val(url).trigger('change');

                // Preview güncelle
                const $preview = $(`#ts-bg-image-preview-${bgId}`);
                $preview.html(`<img src="${url}" alt="" style="max-width:100%;max-height:120px;object-fit:contain;" />`);

                // Options & remove button göster
                $(`.ts-bg-image-options[data-target="${bgId}"]`).removeClass('hidden');
                $(`.ts-media-remove[data-target="${bgId}"]`).removeClass('hidden');

                this.hasChanges = true;
                this.collectData();
                this.showUnsavedBadge(true);
            });

            this._mediaFrame.open();
        },

        /**
         * Remove media image
         */
        removeMediaImage(target) {
            const bgId = target;

            $(`.ts-bg-image-id[data-target="${bgId}"]`).val('').trigger('change');
            $(`.ts-bg-image-url[data-target="${bgId}"]`).val('').trigger('change');

            const $preview = $(`#ts-bg-image-preview-${bgId}`);
            $preview.html('<div class="ts-media-placeholder"><span class="dashicons dashicons-format-image"></span><span>No image selected</span></div>');

            $(`.ts-bg-image-options[data-target="${bgId}"]`).addClass('hidden');
            $(`.ts-media-remove[data-target="${bgId}"]`).addClass('hidden');

            this.hasChanges = true;
            this.collectData();
            this.showUnsavedBadge(true);
        },

        /**
         * Switch module
         */
        switchModule(module) {
            // Update nav
            $('.theme-styles-modules-nav li').removeClass('active');
            $(`.theme-styles-modules-nav a[data-module="${module}"]`).parent().addClass('active');
            
            // Update panel
            $('.theme-styles-module-panel').removeClass('active');
            $(`#module-${module}`).addClass('active');
            
            // Scroll to top
            $('.theme-styles-main-inner').scrollTop(0);
        },
        
        /**
         * Initialize color pickers
         */
        initColorPickers($context) {
            const $inputs = $context 
                ? $context.find('.ts-color-input') 
                : $('.ts-color-input');
            
            const palettes = this.getColorPalettes();
            
            $inputs.not('.wp-color-picker').wpColorPicker({
                palettes: palettes,
                change: (event, ui) => {
                    const color = ui.color.toString();
                    $(event.target).val(color).trigger('change');
                },
                clear: (event, ui) => {
                    // setTimeout: WP Color Picker kendi işini bitirsin, sonra tetikle
                    // trigger('change') değil trigger('input') - sonsuz döngüyü önler
                    setTimeout(() => {
                        $(event.target).closest('.wp-picker-container')
                            .find('.wp-color-picker').val('').trigger('input');
                    }, 10);
                }
            });
        },
        
        /**
         * Get color palettes from colors module data
         */
        getColorPalettes() {
            const colors = this.data.colors || {};
            const palettes = [];
            
            // Primary colors
            const primaryKeys = ['primary', 'secondary', 'tertiary', 'quaternary'];
            primaryKeys.forEach(key => {
                if (colors[key]) palettes.push(colors[key]);
            });
            
            // Custom colors
            if (Array.isArray(colors.custom)) {
                colors.custom.forEach(item => {
                    if (item && item.color) palettes.push(item.color);
                });
            }
            
            // Fallback if no colors defined
            if (palettes.length === 0) {
                return ['#000000', '#ffffff', '#007bff', '#6c757d', '#28a745', '#dc3545'];
            }
            
            return palettes.slice(0, 8); // WP Color Picker max 8 palette
        },
        
        /**
         * Refresh all color picker palettes
         */
        refreshColorPalettes() {
            this.collectData();
            const palettes = this.getColorPalettes();
            
            // Update existing pickers
            $('.wp-color-picker').each(function() {
                const $input = $(this);
                const $wrap = $input.closest('.wp-picker-container');
                $wrap.find('.iris-palette').each(function(i) {
                    if (palettes[i]) {
                        $(this).css('background-color', palettes[i])
                               .attr('data-color', palettes[i]);
                    }
                });
            });
        },
        
        /**
         * Track changes
         */
        trackChanges() {
            $('.theme-styles-main').on('change input', 'input, select, textarea', () => {
                this.hasChanges = true;
                this.collectData();
                this.showUnsavedBadge(true);
            });
            // Button group click
            $('.theme-styles-main').on('click', '.ts-btn-group-item', () => {
                this.hasChanges = true;
                this.collectData();
                this.showUnsavedBadge(true);
            });
        },
        
        /**
         * Collect data from form
         */
        collectData() {
            const data = {};
            
            // Collect from each module
            $('.theme-styles-module-panel').each((i, panel) => {
                const module = $(panel).data('module');
                data[module] = {};
                
                // Collect all inputs
                $(panel).find('[data-field]').each((j, field) => {
                    const $field = $(field);
                    const fieldName = $field.data('field');
                    let value = $field.val();
                    
                    // Handle different field types
                    if ($field.is(':checkbox')) {
                        value = $field.is(':checked');
                    } else if ($field.is(':radio')) {
                        value = $field.filter(':checked').val();
                    } else if ($field.hasClass('ts-button-group')) {
                        // Button group: active button'ın data-value'su
                        const $active = $field.find('.ts-btn-group-item.active');
                        value = $active.length ? $active.data('value') : '';
                    } else if (value === undefined || value === null) {
                        value = '';
                    }
                    
                    // Set nested value
                    this.setNestedValue(data[module], fieldName, value);
                });
            });
            
            this.data = data;
            return data;
        },
        
        /**
         * Set nested value
         */
        setNestedValue(obj, path, value) {
            const keys = path.split('.');
            const lastKey = keys.pop();
            const target = keys.reduce((o, k) => o[k] = o[k] || {}, obj);
            target[lastKey] = value;
        },
        
        /**
         * Save
         */
        save() {
            this.showLoading();
            
            const data = this.collectData();
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'theme_styles_new_save',
                    nonce: themeStylesNew.nonce,
                    data: JSON.stringify(data)
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showNotice('success', response.data.message || 'Saved successfully');
                        this.hasChanges = false;
                        this.originalData = JSON.parse(JSON.stringify(data));
                        if (response.data.activePreset !== undefined) {
                            this.updatePresetBadge(response.data.activePreset);
                        }
                        this.showUnsavedBadge(false);
                        
                        // Refresh preview if active
                        if ($('#ts-live-preview').is(':checked')) {
                            this.refreshPreview();
                        }
                    } else {
                        this.showNotice('error', response.data.message || 'Save failed');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showNotice('error', 'Network error');
                }
            });
        },
        
        /**
         * Save preset
         */
        savePreset() {
            const name = $('#ts-preset-name').val().trim();
            
            if (!name) {
                this.showNotice('error', 'Please enter a preset name');
                return;
            }
            
            this.showLoading();
            
            const data = this.collectData();
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'theme_styles_new_save_preset',
                    nonce: themeStylesNew.nonce,
                    name: name,
                    data: JSON.stringify(data)
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showNotice('success', response.data.message || 'Preset saved');
                        $('#ts-save-preset-modal').fadeOut(200);
                        $('#ts-preset-name').val('');
                        
                        // Refresh preset list
                        this.refreshPresetList(response.data.presets);
                    } else {
                        this.showNotice('error', response.data.message || 'Preset save failed');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showNotice('error', 'Network error');
                }
            });
        },
        
        /**
         * Load preset
         */
        loadPreset(name) {
            if (!confirm(`Load preset "${name}"? Current changes will be lost.`)) {
                return;
            }
            
            this.showLoading();
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'theme_styles_new_load_preset',
                    nonce: themeStylesNew.nonce,
                    name: name
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.data = response.data.data;
                        this.populateForm(this.data);
                        this.showNotice('success', 'Preset loaded');
                        $('#ts-load-preset-modal').fadeOut(200);
                        this.hasChanges = true;
                        this.updatePresetBadge(response.data.activePreset || name);
                    } else {
                        this.showNotice('error', response.data.message || 'Preset load failed');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showNotice('error', 'Network error');
                }
            });
        },
        
        /**
         * Delete preset
         */
        deletePreset(name) {
            if (!confirm(`Delete preset "${name}"? This cannot be undone.`)) {
                return;
            }
            
            this.showLoading();
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'theme_styles_new_delete_preset',
                    nonce: themeStylesNew.nonce,
                    name: name
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showNotice('success', response.data.message || 'Preset deleted');
                        
                        // Refresh preset list
                        this.refreshPresetList(response.data.presets);
                    } else {
                        this.showNotice('error', response.data.message || 'Preset delete failed');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showNotice('error', 'Network error');
                }
            });
        },
        
        /**
         * Export preset
         */
        exportPreset(name) {
            window.location.href = `${themeStylesNew.ajaxUrl.replace('admin-ajax.php', 'admin-post.php')}?action=theme_styles_new_export_preset&name=${name}`;
        },

        /**
         * Duplicate preset
         */
        duplicatePreset(name) {
            const newName = prompt(`Duplicate "${name}" as:`, name + ' Copy');
            if (!newName || !newName.trim()) return;

            this.showLoading();
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: { action: 'theme_styles_new_duplicate_preset', nonce: themeStylesNew.nonce, name, new_name: newName.trim() },
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.showNotice('success', `Duplicated as "${newName}"`);
                        this.refreshPresetList(response.data.presets);
                    } else {
                        this.showNotice('error', response.data.message || 'Duplicate failed');
                    }
                },
                error: () => { this.hideLoading(); this.showNotice('error', 'Network error'); }
            });
        },

        /**
         * Rename preset
         */
        renamePreset(name, currentLabel) {
            const newName = prompt(`Rename "${currentLabel}" to:`, currentLabel);
            if (!newName || !newName.trim() || newName.trim() === currentLabel) return;

            this.showLoading();
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: { action: 'theme_styles_new_rename_preset', nonce: themeStylesNew.nonce, name, new_name: newName.trim() },
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.showNotice('success', `Renamed to "${newName}"`);
                        this.refreshPresetList(response.data.presets);
                        // Badge güncelle
                        const activePreset = $('#ts-active-preset-badge').text().trim();
                        if (activePreset === currentLabel || activePreset === name) {
                            this.updatePresetBadge(newName.trim());
                        }
                    } else {
                        this.showNotice('error', response.data.message || 'Rename failed');
                    }
                },
                error: () => { this.hideLoading(); this.showNotice('error', 'Network error'); }
            });
        },
        
        /**
         * Import preset
         */
        importPreset() {
            const file = $('#ts-import-file')[0].files[0];
            
            if (!file) {
                this.showNotice('error', 'Please select a file');
                return;
            }
            
            this.showLoading();
            
            const formData = new FormData();
            formData.append('action', 'theme_styles_new_import_preset');
            formData.append('nonce', themeStylesNew.nonce);
            formData.append('file', file);
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showNotice('success', response.data.message || 'Preset imported');
                        $('#ts-import-preset-modal').fadeOut(200);
                        $('#ts-import-file').val('');
                        
                        // Refresh preset list
                        this.refreshPresetList(response.data.presets);
                    } else {
                        this.showNotice('error', response.data.message || 'Import failed');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showNotice('error', 'Network error');
                }
            });
        },
        
        /**
         * Revert to default
         */
        revert() {
            if (!confirm('Revert to default settings? All changes will be lost.')) {
                return;
            }
            
            this.showLoading();
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'theme_styles_new_revert',
                    nonce: themeStylesNew.nonce
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.data = response.data.data;
                        this.populateForm(this.data);
                        this.showNotice('success', response.data.message || 'Reverted to default');
                        this.hasChanges = false;
                        this.updatePresetBadge('');
                    } else {
                        this.showNotice('error', response.data.message || 'Revert failed');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showNotice('error', 'Network error');
                }
            });
        },
        
        /**
         * Populate form
         */
        populateForm(data) {
            Object.keys(data).forEach(module => {
                const moduleData = data[module];
                this.populateModuleFields(module, moduleData);
            });
            
            // Reinit color pickers
            this.initColorPickers();
        },
        
        /**
         * Populate module fields
         */
        populateModuleFields(module, data, prefix = '') {
            Object.keys(data).forEach(key => {
                const value = data[key];
                const fieldName = prefix ? `${prefix}.${key}` : key;
                const $field = $(`#module-${module} [data-field="${fieldName}"]`);
                
                if ($field.length) {
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', !!value);
                    } else if ($field.is(':radio')) {
                        $field.filter(`[value="${value}"]`).prop('checked', true);
                    } else {
                        $field.val(value);
                    }
                } else if (typeof value === 'object' && value !== null) {
                    // Recursive for nested objects
                    this.populateModuleFields(module, value, fieldName);
                }
            });
        },

        /**
         * Update active preset badge in toolbar
         */
        updatePresetBadge(presetName) {
            const $badge = $('#ts-active-preset-badge');
            if (!$badge.length) return;
            const label = presetName || 'Default';
            $badge
                .removeClass('ts-preset-badge-custom ts-preset-badge-default')
                .addClass(presetName ? 'ts-preset-badge-custom' : 'ts-preset-badge-default')
                .html(`<span class="dashicons dashicons-saved"></span> ${label}`);
            // Localize objesini de güncelle ki refreshPresetList doğru aktif preset'i işaretlesin
            themeStylesNew.activePreset = presetName || '';
        },

        /**
         * Show/hide unsaved changes badge
         */
        showUnsavedBadge(show) {
            let $badge = $('#ts-unsaved-badge');
            if (show) {
                if (!$badge.length) {
                    $badge = $('<span id="ts-unsaved-badge" class="ts-unsaved-badge"><span class="dashicons dashicons-edit"></span> Unsaved</span>');
                    $('#ts-active-preset-badge').after($badge);
                }
                $badge.show();
            } else {
                $badge.hide();
            }
        },
        
        /**
         * Refresh preset list
         */
        refreshPresetList(presets) {
            const $tbody = $('#ts-preset-table tbody');
            if (!$tbody.length) return;
            $tbody.empty();

            if (!presets || Object.keys(presets).length === 0) {
                $tbody.html('<tr><td colspan="2" style="text-align:center;color:var(--ts-gray-500);padding:20px 0;">No presets available</td></tr>');
                return;
            }

            const activePreset = themeStylesNew.activePreset || '';

            Object.values(presets).forEach(preset => {
                const isActive = preset.name === activePreset || preset.label === activePreset;
                const meta = preset.meta || {};
                const modified = meta.modified
                    ? new Date(meta.modified * 1000).toLocaleString('tr-TR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'})
                    : '';
                const swatches = (meta.colors || []).map(c =>
                    `<span class="ts-preset-swatch" style="background:${c};" title="${c}"></span>`
                ).join('');

                const $row = $(`
                    <tr class="ts-preset-row ${isActive ? 'ts-preset-row-active' : ''}" data-preset="${preset.name}">
                        <td class="ts-preset-name">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="ts-preset-dot ${isActive ? 'ts-preset-dot-active' : 'ts-preset-dot-inactive'}">●</span>
                                <div>
                                    <div><span class="ts-preset-label-text">${preset.label}</span></div>
                                    ${swatches ? `<div class="ts-preset-swatches">${swatches}</div>` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="ts-preset-date">${modified}</td>
                        <td class="ts-preset-actions" style="text-align:right;white-space:nowrap;">
                            <button type="button" class="ts-preset-action-btn ts-load-preset-btn" data-preset="${preset.name}" title="Load">
                                <span class="dashicons dashicons-download"></span> Load
                            </button>
                            <button type="button" class="ts-preset-action-btn ts-duplicate-preset-btn" data-preset="${preset.name}" title="Duplicate">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="ts-preset-action-btn ts-rename-preset-btn" data-preset="${preset.name}" data-label="${preset.label}" title="Rename">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="ts-preset-action-btn ts-export-preset-btn" data-preset="${preset.name}" title="Export">
                                <span class="dashicons dashicons-share"></span>
                            </button>
                            <button type="button" class="ts-preset-action-btn ts-preset-action-delete ts-delete-preset-btn" data-preset="${preset.name}" title="Delete">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                `);
                $tbody.append($row);
            });
        },
        
        /**
         * Show preview
         */
        showPreview() {
            $('#ts-preview-panel').addClass('active').show();
            
            const $iframe = $('#ts-preview-iframe');
            
            // Wait for iframe to load, then inject CSS
            $iframe.off('load.preview').on('load.preview', () => {
                this.injectPreviewCSS();
            });
            
            // If already loaded, inject immediately
            if ($iframe[0].contentDocument && $iframe[0].contentDocument.readyState === 'complete') {
                this.injectPreviewCSS();
            }
        },
        
        /**
         * Hide preview
         */
        hidePreview() {
            $('#ts-preview-panel').removeClass('active').fadeOut(300);
        },
        
        /**
         * Refresh preview
         */
        refreshPreview() {
            this.injectPreviewCSS();
        },
        
        /**
         * Inject preview CSS into iframe
         */
        injectPreviewCSS() {
            const $iframe = $('#ts-preview-iframe');
            
            if (!$iframe.length || !$iframe[0].contentDocument) {
                return;
            }
            
            const data = this.collectData();
            
            $.ajax({
                url: themeStylesNew.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'theme_styles_new_generate_preview',
                    nonce: themeStylesNew.nonce,
                    data: JSON.stringify(data)
                },
                success: (response) => {
                    if (response.success && response.data.css) {
                        const iframeDoc = $iframe[0].contentDocument;
                        const iframeHead = iframeDoc.head || iframeDoc.getElementsByTagName('head')[0];
                        
                        // Remove old preview style
                        const oldStyle = iframeDoc.getElementById('theme-styles-preview');
                        if (oldStyle) {
                            oldStyle.remove();
                        }
                        
                        // Inject new preview style
                        const style = iframeDoc.createElement('style');
                        style.id = 'theme-styles-preview';
                        style.textContent = response.data.css;
                        iframeHead.appendChild(style);
                    }
                },
                error: () => {
                    console.error('Preview CSS generation failed');
                }
            });
        },
        
        /**
         * Show modal
         */
        showModal(selector) {
            $(selector).fadeIn(200);
        },
        
        /**
         * Show loading
         */
        showLoading() {
            $('#ts-loading').fadeIn(200);
        },
        
        /**
         * Hide loading
         */
        hideLoading() {
            $('#ts-loading').fadeOut(200);
        },
        
        /**
         * Show notice
         */
        showNotice(type, message) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.theme-styles-header').before($notice);
            
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => {
        ThemeStylesAdmin.init();
        window.ThemeStylesAdmin = ThemeStylesAdmin; // Global erişim için
    });
    
})(jQuery);


// Fix WP footer appearing inside grid
$(document).ready(function() {
    $('#wpfooter').css('position', 'relative');
});


// Toggle switch handler
$(document).on('change', '.ts-switch-input', function() {
    const $cb    = $(this);
    const onVal  = $cb.data('on')  || '1';
    const offVal = $cb.data('off') || '0';
    const val    = $cb.is(':checked') ? onVal : offVal;
    // Update hidden value input
    $cb.closest('.ts-switch-wrapper').find('.ts-switch-value').val(val).trigger('change');
});


// Sticky preview polyfill - CSS sticky çalışmıyorsa JS fallback
$(document).ready(function() {
    function updateStickyPreviews() {
        const scrollTop = $(window).scrollTop();
        const adminBarH = $('#wpadminbar').outerHeight() || 32;
        const targetTop = adminBarH + 32;

        $('.ts-sticky-preview').each(function() {
            const $el = $(this);
            const $parent = $el.closest('.theme-styles-module-panel');
            if (!$parent.hasClass('active')) return;

            const parentTop = $parent.offset().top;
            const parentBottom = parentTop + $parent.outerHeight();
            const elH = $el.outerHeight();

            if (scrollTop + targetTop > parentTop && scrollTop + targetTop + elH < parentBottom) {
                $el.css({ position: 'fixed', top: targetTop + 'px', width: $el.parent().width() + 'px', zIndex: 100 });
            } else {
                $el.css({ position: '', top: '', width: '', zIndex: '' });
            }
        });
    }

    $(window).on('scroll resize', updateStickyPreviews);
    updateStickyPreviews();
});
