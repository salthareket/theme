<?php

/**
 * TinyMCE Dark Mode Toggle - Toolbar Button
 */
add_filter('mce_external_plugins', function($plugins){
    $plugins['darkmode_toggle'] = SH_INCLUDES_URL."admin/editor-dark-mode/darkmode-toggle.js";
    return $plugins;
});

add_filter('mce_buttons', function($buttons){
    $buttons[] = 'darkmode_toggle';
    return $buttons;
});
