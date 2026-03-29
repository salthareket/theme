<?php

/**
 * Admin Users Table — Register Type, Activation Status, Password Set columns.
 */

add_filter('manage_users_columns', function($columns) {
    $columns['register_type'] = 'Register Type';
    $columns['user_status']   = 'Activated';
    $columns['password_set']  = 'Password Set';
    return $columns;
});

add_filter('manage_users_custom_column', function($val, $column_name, $user_id) {
    switch ($column_name) {
        case 'register_type':
            return esc_html(get_user_meta($user_id, 'register_type', true));

        case 'user_status':
            return get_user_meta($user_id, 'user_status', true)
                ? '<span style="color:green">Yes</span>'
                : '<span style="color:red">No</span>';

        case 'password_set':
            if (!metadata_exists('user', $user_id, 'password_set')) {
                return '<span style="color:red">No - not exist</span>';
            }
            return get_user_meta($user_id, 'password_set', true)
                ? '<span style="color:green">Yes</span>'
                : '<span style="color:red">No</span>';
    }
    return $val;
}, 10, 3);
