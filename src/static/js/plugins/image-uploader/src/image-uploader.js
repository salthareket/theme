/*! Image Uploader - v2.0.0 - 2026-04-09
 * Vanilla JS rewrite — no jQuery dependency
 * Original by Christian Bayer (MIT), rewritten by SaltHareket
 *
 * Usage:
 *   new ImageUploader(element, options)
 *   new ImageUploader('.input-images', { maxFiles: 10 })
 *   new ImageUploader(document.querySelector('.input-images'))
 *
 * jQuery compat (optional):
 *   $('.input-images').imageUploader({ maxFiles: 10 })
 */

class ImageUploader {
    static defaults = {
        preloaded: [],
        imagesInputName: 'images',
        preloadedInputName: 'preloaded',
        label: 'Drag & Drop files here or click to browse',
        extensions: ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.avif'],
        mimes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'image/avif'],
        maxSize: undefined,
        maxFiles: undefined,
        onError: null,      // callback(message, file) — custom error handler
        onChange: null,      // callback(files, preloaded) — fires on any change
    };

    constructor(el, options = {}) {
        this.wrapper = typeof el === 'string' ? document.querySelector(el) : el;
        if (!this.wrapper) return;

        this.settings = { ...ImageUploader.defaults, ...options };
        this.dataTransfer = new DataTransfer();
        this.preloaded = [...this.settings.preloaded];
        this.input = null;
        this.container = null;
        this.uploadedContainer = null;

        this._init();
    }

    // ── INIT ─────────────────────────────────────────────

    _init() {
        this.container = this._createElement('div', { className: 'image-uploader' });

        // File input (hidden)
        this.input = this._createElement('input', {
            type: 'file',
            id: this.settings.imagesInputName + '-' + this._uid(),
            name: this.settings.imagesInputName + '[]',
            accept: this.settings.extensions.join(','),
            multiple: true,
        });
        this.container.appendChild(this.input);

        // Uploaded images area
        this.uploadedContainer = this._createElement('div', { className: 'uploaded' });
        this.container.appendChild(this.uploadedContainer);

        // Upload text / label
        const textContainer = this._createElement('div', { className: 'upload-text' });
        textContainer.innerHTML = `<i class="iui-cloud-upload"></i><span>${this.settings.label}</span>`;
        this.container.appendChild(textContainer);

        // Events
        this.container.addEventListener('click', (e) => {
            if (e.target.closest('.uploaded-image')) return;
            this.input.click();
        });
        this.input.addEventListener('click', (e) => e.stopPropagation());
        this.input.addEventListener('change', (e) => this._onFileSelect(e));

        this.container.addEventListener('dragover', (e) => this._onDragHover(e, true));
        this.container.addEventListener('dragleave', (e) => this._onDragHover(e, false));
        this.container.addEventListener('drop', (e) => this._onFileSelect(e));

        this.wrapper.appendChild(this.container);

        // Preloaded images
        if (this.preloaded.length) {
            this.container.classList.add('has-files');
            this.preloaded.forEach((img) => {
                this.uploadedContainer.appendChild(this._createImage(img.src, img.id, true));
            });
        }
    }

    // ── FILE HANDLING ────────────────────────────────────

    _onFileSelect(e) {
        e.preventDefault();
        e.stopPropagation();
        this.container.classList.remove('drag-over');

        const files = Array.from(e.target?.files || e.dataTransfer?.files || []);
        const valid = [];

        for (const file of files) {
            if (this.settings.extensions && !this._validateExt(file)) continue;
            if (this.settings.mimes && !this._validateMime(file)) continue;
            if (this.settings.maxSize && !this._validateSize(file)) continue;
            if (this.settings.maxFiles && !this._validateMax(valid.length, file)) continue;
            valid.push(file);
        }

        if (valid.length) {
            this._addFiles(valid);
        } else {
            // Restore input files (browser clears on empty select)
            this.input.files = this.dataTransfer.files;
        }
    }

    _addFiles(files) {
        this.container.classList.add('has-files');

        for (const file of files) {
            this.dataTransfer.items.add(file);
            const url = URL.createObjectURL(file);
            this.uploadedContainer.appendChild(
                this._createImage(url, this.dataTransfer.items.length - 1, false)
            );
        }

        this.input.files = this.dataTransfer.files;
        this._fireChange();
    }

    // ── IMAGE ELEMENT ───────────────────────────────────

    _createImage(src, id, isPreloaded) {
        const container = this._createElement('div', { className: 'uploaded-image' });
        const img = this._createElement('img', { src, alt: '' });
        img.loading = 'lazy';
        img.decoding = 'async';
        container.appendChild(img);

        const btn = this._createElement('button', {
            className: 'delete-image',
            type: 'button',
            ariaLabel: 'Remove image',
        });
        btn.innerHTML = '<i class="iui-close"></i>';
        container.appendChild(btn);

        if (isPreloaded) {
            container.dataset.preloaded = 'true';
            const hidden = this._createElement('input', {
                type: 'hidden',
                name: this.settings.preloadedInputName + '[]',
                value: id,
            });
            container.appendChild(hidden);
        } else {
            container.dataset.index = id;
        }

        // Prevent container click from triggering file input
        container.addEventListener('click', (e) => e.stopPropagation());

        // Delete handler
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this._removeImage(container, id, isPreloaded);
        });

        return container;
    }

    _removeImage(container, id, isPreloaded) {
        if (isPreloaded) {
            this.preloaded = this.preloaded.filter((p) => p.id !== id);
        } else {
            const index = parseInt(container.dataset.index, 10);

            // Update indexes of subsequent items
            this.uploadedContainer.querySelectorAll('.uploaded-image[data-index]').forEach((el) => {
                const i = parseInt(el.dataset.index, 10);
                if (i > index) el.dataset.index = i - 1;
            });

            this.dataTransfer.items.remove(index);
            this.input.files = this.dataTransfer.files;
        }

        container.remove();

        if (!this.uploadedContainer.children.length) {
            this.container.classList.remove('has-files');
        }

        this._fireChange();
    }

    // ── DRAG & DROP ─────────────────────────────────────

    _onDragHover(e, isOver) {
        e.preventDefault();
        e.stopPropagation();
        this.container.classList.toggle('drag-over', isOver);
    }

    // ── VALIDATION ──────────────────────────────────────

    _validateExt(file) {
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.settings.extensions.includes(ext)) {
            this._error(`"${file.name}" — extension not allowed. Accepted: ${this.settings.extensions.join(', ')}`, file);
            return false;
        }
        return true;
    }

    _validateMime(file) {
        if (!this.settings.mimes.includes(file.type)) {
            this._error(`"${file.name}" — file type not allowed. Accepted: ${this.settings.mimes.join(', ')}`, file);
            return false;
        }
        return true;
    }

    _validateSize(file) {
        if (file.size > this.settings.maxSize) {
            const mb = (this.settings.maxSize / 1024 / 1024).toFixed(1);
            this._error(`"${file.name}" exceeds maximum size of ${mb}MB`, file);
            return false;
        }
        return true;
    }

    _validateMax(newCount, file) {
        const total = newCount + this.dataTransfer.items.length + this.preloaded.length;
        if (total >= this.settings.maxFiles) {
            this._error(`"${file.name}" — maximum ${this.settings.maxFiles} files allowed`, file);
            return false;
        }
        return true;
    }

    _error(message, file) {
        if (typeof this.settings.onError === 'function') {
            this.settings.onError(message, file);
        } else {
            alert(message);
        }
    }

    // ── CALLBACKS ────────────────────────────────────────

    _fireChange() {
        if (typeof this.settings.onChange === 'function') {
            this.settings.onChange(this.dataTransfer.files, this.preloaded);
        }
    }

    // ── PUBLIC API ───────────────────────────────────────

    /** Get current files (FileList) */
    getFiles() {
        return this.dataTransfer.files;
    }

    /** Get remaining preloaded IDs */
    getPreloaded() {
        return this.preloaded.map((p) => p.id);
    }

    /** Reset to empty state */
    reset() {
        this.dataTransfer = new DataTransfer();
        this.preloaded = [];
        this.uploadedContainer.innerHTML = '';
        this.input.files = this.dataTransfer.files;
        this.container.classList.remove('has-files');
        this._fireChange();
    }

    /** Destroy instance and restore original element */
    destroy() {
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
    }

    // ── HELPERS ──────────────────────────────────────────

    _createElement(tag, attrs = {}) {
        const el = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'className') el.className = value;
            else if (key === 'ariaLabel') el.setAttribute('aria-label', value);
            else if (key in el) el[key] = value;
            else el.setAttribute(key, value);
        }
        return el;
    }

    _uid() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    }
}

// ── jQuery compat (optional) ────────────────────────────
if (typeof jQuery !== 'undefined') {
    jQuery.fn.imageUploader = function (options) {
        return this.each(function () {
            if (!this._imageUploader) {
                this._imageUploader = new ImageUploader(this, options);
            }
        });
    };
}
