<?php
/**
 * Forms Module
 *
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 * @author  SaltHareket
 * @since   1.0.0
 *
 * CHANGELOG:
 * 1.0.0 - 2026-04-29
 * - Initial release
 * - Bootstrap uyumlu form element stilleri
 * - Input, select, textarea, label, placeholder, validation, switch
 * - Size sistemi (sm/md/lg)
 *
 * HOW TO USE:
 * Form stilleri bu modülden yönetilir.
 * CSS variable'lar root.css'e yazılır, _form.scss bu variable'ları kullanır.
 *
 * @example CSS variable kullanımı:
 * .form-control { color: var(--form-input-color); }
 */

if (!defined('ABSPATH')) exit;

return [
    'id'          => 'forms',
    'title'       => __('Forms', 'theme-styles-new'),
    'description' => __('Configure form element styles', 'theme-styles-new'),
    'icon'        => 'dashicons-editor-table',
    'priority'    => 55,
    'template'    => __DIR__ . '/template.php',
    'processor'   => __DIR__ . '/processor.php',
    'enabled'     => true,
];
