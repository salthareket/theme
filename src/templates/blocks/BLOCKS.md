# SaltHareket Block Sistemi — Referans Dökümanı

**Son güncelleme:** 2026-05-17  
**Block path:** `vendor/salthareket/theme/src/templates/blocks/{block}/`  
**Her block:** `block.json` + `render.php` + `{block}.twig`  
**Render fonksiyonu:** `salt_render_acf_block($block, $is_preview, $post_id)`

---

## Block Listesi

### 1. accordion
- **Ana field'lar:** `fields.post_type`, `fields.posts`, `fields.custom`, `fields.categories`
- **Tip:** Dynamic (post query) veya static (custom items)
- **Placeholder:** Post type seçilmemişse "Accordion — İçerik seçilmedi" uyarısı
- **Dummy data:** ✅ Mümkün — 3 dummy accordion item gösterilebilir

### 2. application-buttons
- **Ana field'lar:** Buton linkleri (App Store, Google Play vs)
- **Tip:** Static
- **Placeholder:** Buton eklenmemişse placeholder
- **Dummy data:** ✅ Mümkün — dummy App Store / Google Play butonları

### 3. archive
- **Ana field'lar:** Post archive query
- **Tip:** Dynamic
- **Placeholder:** ⚠️ Her zaman query çalışır, placeholder gerekmez

### 4. audio
- **Ana field'lar:** `fields.video_type = "audio"`, ses dosyası
- **Tip:** Static media
- **Placeholder:** ✅ Ses dosyası seçilmemişse audio placeholder
- **Dummy data:** ✅ Dummy audio player UI

### 5. bootstrap-columns
- **Ana field'lar:** InnerBlocks (eski sistem, V2 compat)
- **Tip:** InnerBlocks container
- **Placeholder:** CSS ile halledildi

### 6. buttons
- **Ana field'lar:** `fields.buttons` (repeater)
- **Tip:** Static
- **Placeholder:** ✅ `fields.buttons` boşsa "Buton eklenmedi"
- **Dummy data:** ✅ Dummy "Buton Metni" butonu

### 7. column
- **Ana field'lar:** InnerBlocks child, `fields.col_widths`
- **Tip:** InnerBlocks container
- **Placeholder:** CSS + JS ile halledildi (`has-blocks` class)

### 8. columns
- **Ana field'lar:** InnerBlocks parent, `fields.row_cols`, `fields.column_breakpoints`
- **Tip:** InnerBlocks container
- **Placeholder:** CSS ile halledildi

### 9. file
- **Ana field'lar:** `files` (repeater — dosya listesi)
- **Tip:** Static
- **Placeholder:** ✅ `files` boşsa "Dosya eklenmedi"
- **Dummy data:** ✅ Dummy dosya listesi (PDF, DOC vs)

### 10. gallery
- **Ana field'lar:** `fields.gallery` (image array), `fields.videos`
- **Tip:** Static media
- **Placeholder:** ✅ `fields.gallery` boşsa galeri placeholder
- **Dummy data:** ✅ Dummy grid placeholder (renkli kutular)

### 11. hero
- **Ana field'lar:** `fields.page_title`, `fields.description`, `fields.show_breadcrumb`
- **Tip:** Static + background (block_settings'ten)
- **Placeholder:** ⚠️ Hero genellikle background ile çalışır, her zaman görünür
- **Dummy data:** ✅ `fields.page_title` false ise dummy başlık göster

### 12. icons
- **Ana field'lar:** `fields.icons` (repeater — icon + description)
- **Tip:** Static
- **Placeholder:** ✅ `fields.icons` boşsa "İkon eklenmedi"
- **Dummy data:** ✅ 3 dummy ikon placeholder

### 13. image ✅ YAPILDI
- **Ana field'lar:** `fields.image` (image object)
- **Tip:** Static media
- **Placeholder:** ✅ `fields.image` boşsa SVG image placeholder
- **Dummy data:** ✅ Placeholder SVG gösteriliyor

### 14. map
- **Ana field'lar:** `fields.map_settings`, `fields.map_type`
- **Tip:** Dynamic (JS map render)
- **Placeholder:** ✅ ZATEN VAR — `is_preview` ise "Please view page for map locations" gösteriyor

### 15. marquee
- **Ana field'lar:** `fields.text` (static) veya `fields.query` (dynamic)
- **Tip:** Static veya Dynamic
- **Placeholder:** ✅ `fields.text` boşsa "Marquee metni eklenmedi"
- **Dummy data:** ✅ Dummy scrolling text

### 16. milestones
- **Ana field'lar:** `fields.timeline` (repeater — title + events)
- **Tip:** Static
- **Placeholder:** ✅ `fields.timeline` boşsa "Timeline eklenmedi"
- **Dummy data:** ✅ 2-3 dummy milestone item

### 17. navigation
- **Ana field'lar:** Navigasyon menüsü
- **Tip:** Dynamic (WP menu)
- **Placeholder:** ⚠️ Menü her zaman render edilir

### 18. post-archive
- **Ana field'lar:** Post archive + pagination
- **Tip:** Dynamic
- **Placeholder:** ⚠️ Her zaman query çalışır

### 19. search-results
- **Ana field'lar:** Arama sonuçları
- **Tip:** Dynamic
- **Placeholder:** ⚠️ Her zaman query çalışır

### 20. slider
- **Ana field'lar:** `fields.slider` (repeater — image/video slides)
- **Tip:** Static media
- **Placeholder:** ✅ `fields.slider` boşsa slider placeholder
- **Dummy data:** ✅ Dummy slide placeholder (gri alan + ikon)

### 21. slider-advanced
- **Ana field'lar:** Gelişmiş slider ayarları
- **Tip:** Static media
- **Placeholder:** ✅ Slider boşsa placeholder

### 22. social-media
- **Ana field'lar:** `fields.social_accounts_custom` veya contacts'tan
- **Tip:** Static veya Dynamic
- **Placeholder:** ✅ Hesap eklenmemişse "Sosyal medya hesabı eklenmedi"
- **Dummy data:** ✅ Dummy sosyal medya ikonları

### 23. table
- **Ana field'lar:** `fields.table` (tablo verisi)
- **Tip:** Static
- **Placeholder:** ✅ Tablo boşsa "Tablo verisi eklenmedi"
- **Dummy data:** ✅ Dummy 3x3 tablo

### 24. table-extended
- **Ana field'lar:** Gelişmiş tablo
- **Tip:** Static
- **Placeholder:** ✅ Tablo boşsa placeholder

### 25. tease-list
- **Ana field'lar:** `fields.post_type`, `fields.posts`
- **Tip:** Dynamic (post query)
- **Placeholder:** ✅ Post type seçilmemişse "Post type seçilmedi"
- **Dummy data:** ✅ 3 dummy tease card

### 26. text
- **Ana field'lar:** `fields.text` (wysiwyg)
- **Tip:** Static
- **Placeholder:** ✅ `fields.text` boşsa "Metin içeriği eklenmedi"
- **Dummy data:** ✅ Lorem ipsum dummy text

### 27. text-image
- **Ana field'lar:** `fields.text_image` (repeater — content + image/video)
- **Tip:** Static
- **Placeholder:** ✅ `fields.text_image` boşsa placeholder
- **Dummy data:** ✅ Dummy text + image layout

### 28. video
- **Ana field'lar:** `fields.video_type`, `fields.video_url` veya `fields.video_file`
- **Tip:** Static media
- **Placeholder:** ✅ Video seçilmemişse video placeholder
- **Dummy data:** ✅ Dummy video player UI (play butonu + gri alan)

### 29. world-map
- **Ana field'lar:** Dünya haritası (SVG/JS)
- **Tip:** Dynamic
- **Placeholder:** ⚠️ Her zaman render edilir

---

## Placeholder Stratejisi

### Sadece `$is_preview && empty(field)` kontrolü yeterli olanlar:
image, video, audio, gallery, slider, file, buttons, text, text-image, icons, social-media, milestones, marquee, table

### Dummy data ile daha iyi görünen bloklar:
text (lorem ipsum), tease-list (dummy cards), accordion (dummy items), icons (dummy icons), milestones (dummy timeline)

### Placeholder gerekmeyenler (her zaman render edilir):
map (kendi placeholder'ı var), archive, post-archive, search-results, navigation, world-map, hero (background'dan gelir)

---

## Önemli Notlar

- `$is_preview` = admin block editor'da true, frontend'de false
- `get_field('field_name')` ile field değeri okunur
- Placeholder sadece `$is_preview && empty` durumunda gösterilmeli
- `salt_render_acf_block()` çağrısından önce kontrol yapılmalı
- Block settings (padding, margin, bg) her zaman uygulanır — sadece içerik placeholder olur

---

## Dosya Referansları

- Block path: `wp-content/themes/salthareket/vendor/salthareket/theme/src/templates/blocks/`
- Render engine: `timber-acf-blocks.php` → `salt_render_acf_block()`
- Block CSS (admin): `blocks.php` → `wp_block_editor_width()`
- Block JS (admin): `blocks.php` → `salt_block_editor_scripts()`
- ACF JSON: `wp-content/themes/salthareket/acf-json/`
