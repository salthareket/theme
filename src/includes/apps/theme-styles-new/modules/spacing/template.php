<?php
/**
 * Spacing Module Template
 * 
 * @package SaltHareket\Theme\ThemeStylesNew\Modules
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

$data = $data ?? [];
?>

<div class="ts-module-spacing">
    
    <!-- Container Widths -->
    <div class="ts-field-group">
        <h3><?php _e('Container Widths', 'theme-styles-new'); ?></h3>
        <div class="ts-breakpoint-fields">
            <?php 
            $breakpoints = ['xxxl' => '>1600px', 'xxl' => '≤1599px', 'xl' => '≤1399px', 'lg' => '≤1199px', 'md' => '≤991px', 'sm' => '≤767px', 'xs' => '<575px'];
            foreach ($breakpoints as $bp => $label): 
                $value = $data['container'][$bp] ?? '';
            ?>
            <div class="ts-breakpoint-field">
                <label class="ts-breakpoint-label"><?php echo esc_html($label); ?></label>
                <input type="text" class="ts-breakpoint-input" data-field="container.<?php echo $bp; ?>" value="<?php echo esc_attr($value); ?>" placeholder="1320px" />
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Section Spacing (Responsive) -->
    <div class="ts-field-group">
        <h3><?php _e('Section Spacing', 'theme-styles-new'); ?></h3>
        <div class="ts-breakpoint-fields">
            <?php 
            foreach ($breakpoints as $bp => $label): 
                $value = $data['section'][$bp] ?? '';
            ?>
            <div class="ts-breakpoint-field">
                <label class="ts-breakpoint-label"><?php echo esc_html($label); ?></label>
                <input type="text" class="ts-breakpoint-input" data-field="section.<?php echo $bp; ?>" value="<?php echo esc_attr($value); ?>" placeholder="80px" />
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Gap Sizes -->
    <div class="ts-field-group">
        <h3><?php _e('Gap Sizes', 'theme-styles-new'); ?></h3>
        <?php 
        $gaps = [
            'xs' => __('Extra Small', 'theme-styles-new'),
            'sm' => __('Small', 'theme-styles-new'),
            'md' => __('Medium', 'theme-styles-new'),
            'lg' => __('Large', 'theme-styles-new'),
            'xl' => __('Extra Large', 'theme-styles-new')
        ];
        foreach ($gaps as $key => $label): 
            $value = $data['gap'][$key] ?? '';
        ?>
        <div class="ts-field-row">
            <label class="ts-field-label"><?php echo esc_html($label); ?></label>
            <input type="text" class="ts-field-input" data-field="gap.<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="8px" />
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Padding Sizes -->
    <div class="ts-field-group">
        <h3><?php _e('Padding Sizes', 'theme-styles-new'); ?></h3>
        <?php 
        $paddings = [
            'xs' => __('Extra Small', 'theme-styles-new'),
            'sm' => __('Small', 'theme-styles-new'),
            'md' => __('Medium', 'theme-styles-new'),
            'lg' => __('Large', 'theme-styles-new'),
            'xl' => __('Extra Large', 'theme-styles-new')
        ];
        foreach ($paddings as $key => $label): 
            $value = $data['padding'][$key] ?? '';
        ?>
        <div class="ts-field-row">
            <label class="ts-field-label"><?php echo esc_html($label); ?></label>
            <input type="text" class="ts-field-input" data-field="padding.<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="16px" />
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Margin Sizes -->
    <div class="ts-field-group">
        <h3><?php _e('Margin Sizes', 'theme-styles-new'); ?></h3>
        <?php 
        $margins = [
            'xs' => __('Extra Small', 'theme-styles-new'),
            'sm' => __('Small', 'theme-styles-new'),
            'md' => __('Medium', 'theme-styles-new'),
            'lg' => __('Large', 'theme-styles-new'),
            'xl' => __('Extra Large', 'theme-styles-new')
        ];
        foreach ($margins as $key => $label): 
            $value = $data['margin'][$key] ?? '';
        ?>
        <div class="ts-field-row">
            <label class="ts-field-label"><?php echo esc_html($label); ?></label>
            <input type="text" class="ts-field-input" data-field="margin.<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="16px" />
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Border Radius -->
    <div class="ts-field-group">
        <h3><?php _e('Border Radius', 'theme-styles-new'); ?></h3>
        <?php 
        $radius = [
            'xs' => __('Extra Small', 'theme-styles-new'),
            'sm' => __('Small', 'theme-styles-new'),
            'md' => __('Medium', 'theme-styles-new'),
            'lg' => __('Large', 'theme-styles-new'),
            'xl' => __('Extra Large', 'theme-styles-new'),
            'full' => __('Full (Circle)', 'theme-styles-new')
        ];
        foreach ($radius as $key => $label): 
            $value = $data['radius'][$key] ?? '';
        ?>
        <div class="ts-field-row">
            <label class="ts-field-label"><?php echo esc_html($label); ?></label>
            <input type="text" class="ts-field-input" data-field="radius.<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo $key === 'full' ? '50%' : '4px'; ?>" />
        </div>
        <?php endforeach; ?>
    </div>
    
</div>
