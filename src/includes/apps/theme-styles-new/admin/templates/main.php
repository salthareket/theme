<?php
/**
 * Admin Main Template
 * 
 * @package SaltHareket\Theme\ThemeStylesNew
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

$instance = Theme_Styles_New::init();
$data = $instance->get_data();
$modules = Theme_Styles_Module_Manager::get_enabled();
$presets = Theme_Styles_Preset_Manager::get_all();
?>

<!-- Theme Styles Sticky Toolbar -->
<div class="ts-sticky-toolbar" id="ts-sticky-toolbar">
    <div class="ts-sticky-toolbar-inner">
        <div class="ts-sticky-toolbar-left">
            <span class="dashicons dashicons-admin-appearance"></span>
            <span class="ts-sticky-toolbar-title"><?php _e('Theme Styles', 'theme-styles-new'); ?></span>
            <?php
            $active_preset = get_option('theme_styles_new_active_preset', '');
            $badge_label   = $active_preset ? esc_html($active_preset) : __('Default', 'theme-styles-new');
            $badge_class   = $active_preset ? 'ts-preset-badge ts-preset-badge-custom' : 'ts-preset-badge ts-preset-badge-default';
            ?>
            <span class="<?php echo $badge_class; ?>" id="ts-active-preset-badge">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $badge_label; ?>
            </span>
        </div>
        <div class="ts-sticky-toolbar-right">
            <button type="button" class="ts-toolbar-btn" id="ts-load-preset">
                <span class="dashicons dashicons-download"></span>
                <span><?php _e('Load Preset', 'theme-styles-new'); ?></span>
            </button>
            <button type="button" class="ts-toolbar-btn" id="ts-save-preset">
                <span class="dashicons dashicons-upload"></span>
                <span><?php _e('Save Preset', 'theme-styles-new'); ?></span>
            </button>
            <button type="button" class="ts-toolbar-btn" id="ts-import-preset">
                <span class="dashicons dashicons-database-import"></span>
                <span><?php _e('Import', 'theme-styles-new'); ?></span>
            </button>
            <button type="button" class="ts-toolbar-btn ts-toolbar-btn-danger" id="ts-revert">
                <span class="dashicons dashicons-undo"></span>
                <span><?php _e('Revert', 'theme-styles-new'); ?></span>
            </button>
            <div class="ts-toolbar-divider"></div>
            <button type="button" class="ts-toolbar-btn ts-toolbar-btn-primary" id="ts-save">
                <span class="dashicons dashicons-yes-alt"></span>
                <span><?php _e('Save Changes', 'theme-styles-new'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="wrap theme-styles-new-wrap">
    
    <!-- Page Title (WP standard - diğer plugin notice'ları buraya gelir) -->
    <h1 class="ts-page-title-hidden"><?php _e('Theme Styles', 'theme-styles-new'); ?></h1>
    
    <!-- Main Content -->
    <div class="theme-styles-content">
        
        <!-- Sidebar (Module Navigation) -->
        <div class="theme-styles-sidebar">
            <div class="theme-styles-sidebar-inner">
                <h3><?php _e('Modules', 'theme-styles-new'); ?></h3>
                <ul class="theme-styles-modules-nav">
                    <?php 
                    $first = true;
                    foreach ($modules as $module_id => $module): 
                        $active = $first ? 'active' : '';
                        $first = false;
                    ?>
                    <li class="<?php echo esc_attr($active); ?>">
                        <a href="#module-<?php echo esc_attr($module_id); ?>" data-module="<?php echo esc_attr($module_id); ?>">
                            <span class="dashicons <?php echo esc_attr($module['icon'] ?? 'dashicons-admin-generic'); ?>"></span>
                            <span class="module-label"><?php echo esc_html($module['title'] ?? ucfirst($module_id)); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <!-- Live Preview Toggle -->
                <div class="theme-styles-preview-toggle">
                    <label>
                        <input type="checkbox" id="ts-live-preview" />
                        <span><?php _e('Live Preview', 'theme-styles-new'); ?></span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Main Panel (Module Content) -->
        <div class="theme-styles-main">
            <div class="theme-styles-main-inner">
                
                <!-- Module Panels -->
                <?php 
                $first = true;
                foreach ($modules as $module_id => $module): 
                    $active = $first ? 'active' : '';
                    $first = false;
                ?>
                <div class="theme-styles-module-panel <?php echo esc_attr($active); ?>" id="module-<?php echo esc_attr($module_id); ?>" data-module="<?php echo esc_attr($module_id); ?>">
                    
                    <div class="module-panel-header">
                        <h2>
                            <span class="dashicons <?php echo esc_attr($module['icon'] ?? 'dashicons-admin-generic'); ?>"></span>
                            <?php echo esc_html($module['title'] ?? ucfirst($module_id)); ?>
                        </h2>
                        <p class="description"><?php echo esc_html($module['description'] ?? ''); ?></p>
                    </div>
                    
                    <div class="module-panel-content">
                        <?php Theme_Styles_Module_Manager::render_fields($module_id, $data[$module_id] ?? []); ?>
                    </div>
                    
                </div>
                <?php endforeach; ?>
                
            </div>
        </div>
        
        <!-- Preview Panel (Optional) -->
        <div class="theme-styles-preview" id="ts-preview-panel" style="display: none;">
            <div class="theme-styles-preview-inner">
                <div class="preview-header">
                    <h3><?php _e('Live Preview', 'theme-styles-new'); ?></h3>
                    <button type="button" class="button-link" id="ts-close-preview">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="preview-content">
                    <iframe id="ts-preview-iframe" src="<?php echo esc_url(home_url('/')); ?>"></iframe>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Modals -->
    
    <!-- Load Preset Modal -->
    <div class="theme-styles-modal" id="ts-load-preset-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Load Preset', 'theme-styles-new'); ?></h2>
                <button type="button" class="button-link modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <table class="ts-preset-table" id="ts-preset-table">
                    <tbody>
                    <?php if (empty($presets)): ?>
                        <tr><td colspan="2" style="text-align:center;color:var(--ts-gray-500);padding:20px 0;"><?php _e('No presets available', 'theme-styles-new'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($presets as $preset):
                            $meta     = $preset['meta'] ?? [];
                            $modified = !empty($meta['modified']) ? date('d.m.Y', $meta['modified']) : '';
                            $colors   = $meta['colors'] ?? [];
                            $is_active = (get_option('theme_styles_new_active_preset', '') === $preset['name']);
                        ?>
                        <tr class="ts-preset-row <?php echo $is_active ? 'ts-preset-row-active' : ''; ?>" data-preset="<?php echo esc_attr($preset['name']); ?>">
                            <td class="ts-preset-name">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="ts-preset-dot <?php echo $is_active ? 'ts-preset-dot-active' : 'ts-preset-dot-inactive'; ?>">●</span>
                                    <div>
                                        <div><span class="ts-preset-label-text"><?php echo esc_html($preset['label']); ?></span></div>
                                        <?php if (!empty($colors)): ?>
                                        <div class="ts-preset-swatches">
                                            <?php foreach ($colors as $c): ?>
                                            <span class="ts-preset-swatch" style="background:<?php echo esc_attr($c); ?>;" title="<?php echo esc_attr($c); ?>"></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="ts-preset-date">
                                <?php if ($modified): ?><?php echo esc_html(date('d.m.Y H:i', $meta['modified'])); ?><?php endif; ?>
                            </td>
                            <td class="ts-preset-actions" style="text-align:right;white-space:nowrap;">
                                <button type="button" class="ts-preset-action-btn ts-load-preset-btn" data-preset="<?php echo esc_attr($preset['name']); ?>" title="Load">
                                    <span class="dashicons dashicons-download"></span> <?php _e('Load', 'theme-styles-new'); ?>
                                </button>
                                <button type="button" class="ts-preset-action-btn ts-duplicate-preset-btn" data-preset="<?php echo esc_attr($preset['name']); ?>" title="Duplicate">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </button>
                                <button type="button" class="ts-preset-action-btn ts-rename-preset-btn" data-preset="<?php echo esc_attr($preset['name']); ?>" data-label="<?php echo esc_attr($preset['label']); ?>" title="Rename">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button type="button" class="ts-preset-action-btn ts-export-preset-btn" data-preset="<?php echo esc_attr($preset['name']); ?>" title="Export JSON">
                                    <span class="dashicons dashicons-database-export"></span>
                                </button>
                                <button type="button" class="ts-preset-action-btn ts-preset-action-delete ts-delete-preset-btn" data-preset="<?php echo esc_attr($preset['name']); ?>" title="Delete">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Save Preset Modal -->
    <div class="theme-styles-modal" id="ts-save-preset-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Save Preset', 'theme-styles-new'); ?></h2>
                <button type="button" class="button-link modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <p>
                    <label for="ts-preset-name"><?php _e('Preset Name', 'theme-styles-new'); ?></label>
                    <input type="text" id="ts-preset-name" class="regular-text" placeholder="<?php esc_attr_e('Enter preset name', 'theme-styles-new'); ?>" />
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-primary" id="ts-save-preset-confirm">
                    <?php _e('Save Preset', 'theme-styles-new'); ?>
                </button>
                <button type="button" class="button modal-close">
                    <?php _e('Cancel', 'theme-styles-new'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Import Preset Modal -->
    <div class="theme-styles-modal" id="ts-import-preset-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Import Preset', 'theme-styles-new'); ?></h2>
                <button type="button" class="button-link modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="modal-body">
                <p>
                    <label for="ts-import-file"><?php _e('Select JSON File', 'theme-styles-new'); ?></label>
                    <input type="file" id="ts-import-file" accept=".json" />
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-primary" id="ts-import-preset-confirm">
                    <?php _e('Import', 'theme-styles-new'); ?>
                </button>
                <button type="button" class="button modal-close">
                    <?php _e('Cancel', 'theme-styles-new'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="theme-styles-loading" id="ts-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p><?php _e('Processing...', 'theme-styles-new'); ?></p>
    </div>
    
</div>
