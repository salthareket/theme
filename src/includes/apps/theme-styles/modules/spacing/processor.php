<?php
/**
 * Spacing Module Processor
 * 
 * @package SaltHareket\Theme\ThemeStyles\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

function theme_styles_process_spacing($data, $generator) {
    $spacing = $data['spacing'] ?? [];
    
    $result = [
        'variables' => [],
        'mobile' => [],
        'media_queries' => []
    ];
    
    // Container widths (responsive)
    if (!empty($spacing['container'])) {
        foreach ($spacing['container'] as $bp => $width) {
            if (!empty($width)) {
                $result['media_queries'][$bp]["container-width"] = $width;
            }
        }
    }
    
    // Section spacing (responsive)
    if (!empty($spacing['section'])) {
        foreach ($spacing['section'] as $bp => $space) {
            if (!empty($space)) {
                $result['media_queries'][$bp]["section-spacing"] = $space;
            }
        }
    }
    
    // Gap sizes
    if (!empty($spacing['gap'])) {
        foreach ($spacing['gap'] as $size => $value) {
            if (!empty($value)) {
                $result['variables']["gap-{$size}"] = $value;
            }
        }
    }
    
    // Padding sizes
    if (!empty($spacing['padding'])) {
        foreach ($spacing['padding'] as $size => $value) {
            if (!empty($value)) {
                $result['variables']["padding-{$size}"] = $value;
            }
        }
    }
    
    // Margin sizes
    if (!empty($spacing['margin'])) {
        foreach ($spacing['margin'] as $size => $value) {
            if (!empty($value)) {
                $result['variables']["margin-{$size}"] = $value;
            }
        }
    }
    
    // Border radius
    if (!empty($spacing['radius'])) {
        foreach ($spacing['radius'] as $size => $value) {
            if (!empty($value)) {
                $result['variables']["radius-{$size}"] = $value;
            }
        }
    }
    
    return $result;
}
