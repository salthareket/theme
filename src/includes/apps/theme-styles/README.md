# Theme Styles New

Modern, modular, and extensible theme styles management system for WordPress themes.

## Features

- **Modular Architecture**: Enable/disable modules as needed
- **Custom UI**: Professional admin interface (no ACF dependency for UI)
- **ACF Data Compatible**: Data format compatible with existing ACF-based systems
- **Live Preview**: Real-time preview of style changes
- **Preset System**: Save, load, import, and export style presets
- **Responsive**: Full responsive support with breakpoint-based values
- **CSS Variables**: Generates CSS custom properties for easy SCSS integration
- **Performance**: Optimized with caching and static file generation

## Installation

Include the system in your theme:

```php
// In functions.php or includes/theme.php
include_once get_template_directory() . '/theme/includes/theme-styles/index.php';
```

## Available Modules

1. **Typography** - Font families, headings, text sizes, line heights
2. **Colors** - Primary colors, custom colors, gradients
3. **Spacing** - Container widths, section spacing, gaps, padding, margin, border radius
4. **Background** - Body background, section backgrounds, overlays, borders
5. **Buttons** - Button styles, sizes, colors
6. **Header** - Header background, height, navigation, logo size
7. **Footer** - Footer background, padding, links, copyright
8. **Breadcrumb** - Breadcrumb colors, separator, font size
9. **Pagination** - Pagination colors, hover/active states, sizing

## Usage

### Basic Usage

After including the system, go to **Appearance → Theme Styles** in WordPress admin.

### Enable Specific Modules

```php
// Enable only specific modules
define('THEME_STYLES_MODULES', ['typography', 'colors', 'spacing']);
include_once get_template_directory() . '/theme/includes/theme-styles/index.php';
```

### Load Modules for Template Post Types

```php
// In template single file
if (get_post_type() === 'template') {
    $template_type = get_field('template_type');
    
    if ($template_type === 'modal') {
        // Load only typography and spacing for modals
        theme_styles_load_modules(['typography', 'spacing'], get_the_ID());
    }
}
```

### Get Style Data in Templates

```php
// Get all data
$styles = theme_styles_get_data();

// Get specific module data
$typography = theme_styles_get_module_data('typography');

// Get specific value
$primary_color = theme_styles_get_value('colors.primary');
```

### Use in SCSS

The system generates CSS variables that can be used in SCSS:

```scss
.my-element {
    color: var(--color-primary);
    font-size: var(--text-fs);
    padding: var(--padding-md);
    border-radius: var(--radius-sm);
}

.my-button {
    background: var(--btn-primary-bg);
    color: var(--btn-primary-color);
    
    &:hover {
        background: var(--btn-primary-hover-bg);
    }
}
```

## File Structure

```
theme-styles/
├── index.php                 # Main loader
├── README.md                 # This file
├── data/
│   └── default.json         # Default style values
├── includes/
│   ├── class-theme-styles.php      # Main class
│   ├── class-module-manager.php    # Module management
│   ├── class-css-generator.php     # CSS generation
│   └── class-preset-manager.php    # Preset management
├── admin/
│   ├── settings.php         # Admin page registration
│   ├── save-handler.php     # AJAX save handlers
│   ├── templates/
│   │   └── main.php        # Admin UI template
│   └── assets/
│       ├── css/
│       │   └── admin.css   # Admin styles
│       └── js/
│           └── admin.js    # Admin JavaScript
├── frontend/
│   ├── helpers.php          # Helper functions
│   └── generator.php        # Frontend CSS generation
└── modules/
    ├── typography/
    │   ├── index.php
    │   ├── module.php       # Module config
    │   ├── template.php     # Admin UI fields
    │   └── processor.php    # CSS generation logic
    ├── colors/
    ├── spacing/
    ├── background/
    ├── buttons/
    ├── header/
    ├── footer/
    ├── breadcrumb/
    └── pagination/
```

## Creating Custom Modules

1. Create a new folder in `modules/` directory
2. Create three files:
   - `module.php` - Module configuration
   - `template.php` - Admin UI fields
   - `processor.php` - CSS generation logic

### Example Module

**module.php:**
```php
<?php
return [
    'id' => 'my-module',
    'title' => __('My Module', 'theme-styles'),
    'description' => __('My custom module', 'theme-styles'),
    'icon' => 'dashicons-admin-generic',
    'priority' => 100,
    'template' => __DIR__ . '/template.php',
    'processor' => __DIR__ . '/processor.php'
];
```

**template.php:**
```php
<?php
$data = $data ?? [];
?>
<div class="ts-module-my-module">
    <div class="ts-field-group">
        <label class="ts-field-label"><?php _e('My Field', 'theme-styles'); ?></label>
        <input type="text" class="ts-field-input" data-field="my_field" value="<?php echo esc_attr($data['my_field'] ?? ''); ?>" />
    </div>
</div>
```

**processor.php:**
```php
<?php
function theme_styles_process_my_module($data, $generator) {
    $module_data = $data['my-module'] ?? [];
    
    return [
        'variables' => [
            'my-var' => $module_data['my_field'] ?? ''
        ],
        'mobile' => [],
        'media_queries' => []
    ];
}
```

## Data Format

The system uses a nested array structure compatible with ACF:

```json
{
  "typography": {
    "font_family": "Roboto",
    "headings": {
      "h1": {
        "font_size": "48px",
        "font_weight": "700"
      }
    }
  },
  "colors": {
    "primary": "#007bff",
    "custom": [
      {
        "title": "accent",
        "color": "#ff6b6b"
      }
    ]
  }
}
```

## Preset System

### Save Preset
1. Configure your styles
2. Click "Save Preset"
3. Enter preset name
4. Preset is saved to database

### Load Preset
1. Click "Load Preset"
2. Select preset from list
3. Styles are applied

### Export Preset
1. Click "Load Preset"
2. Click "Export" on desired preset
3. JSON file is downloaded

### Import Preset
1. Click "Import"
2. Select JSON file
3. Preset is imported

## Cache System

The system uses a 3-layer cache:

1. **Memory Cache**: In-memory during request
2. **Transient Cache**: WordPress transients (24 hours)
3. **Static Files**: JSON and CSS files in `theme/static/`

Cache is automatically cleared when styles are saved.

## Hooks & Filters

### Actions

```php
// Before styles are saved
do_action('theme_styles_before_save', $data);

// After styles are saved
do_action('theme_styles_after_save', $data);

// Before CSS generation
do_action('theme_styles_before_css_generation', $data);

// After CSS generation
do_action('theme_styles_after_css_generation', $css);
```

### Filters

```php
// Modify data before save
$data = apply_filters('theme_styles_save_data', $data);

// Modify generated CSS
$css = apply_filters('theme_styles_generated_css', $css, $data);

// Modify default data
$default = apply_filters('theme_styles_default_data', $default);
```

## Version

1.0.0 - Initial release (2026-04-23)

## Author

Tolga Koçak

## License

Proprietary - Part of SaltHareket Theme
