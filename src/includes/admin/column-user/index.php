<?php


function new_modify_user_table( $column ) {
    $column['register_type'] = 'Register Type';
    $column['user_status'] = 'Activated';
    $column['password_set'] = 'Password Set';
    return $column;
}
add_filter( 'manage_users_columns', 'new_modify_user_table' );

function new_modify_user_table_row( $val, $column_name, $user_id ) {
    switch ($column_name) {
        case 'register_type' :
            $value = get_user_meta( $user_id, 'register_type', true);
            return $value;
        case 'user_status' :
            $value = get_user_meta( $user_id, 'user_status', true);
            if($value){
                return "<span style='color:green;'>Yes</span>";
            }else{
                return "<span style='color:red;'>No</span>";
            }
        case 'password_set' :
            $value = get_user_meta( $user_id, 'password_set', true);
            if(!metadata_exists( 'user', $user_id, 'password_set')){
                return "<span style='color:red;'>No - not exist</span>";
            }else{
                if(!$value){
                    return "<span style='color:red;'>No".$value."</span>";
                }else{
                    return "<span style='color:green;'>Yes</span>";
                }
            }
        default:
    }
    return $val;
}
add_filter( 'manage_users_custom_column', 'new_modify_user_table_row', 10, 3 );