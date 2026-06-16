<?php

/**
 * Metronet Profile Picture — User avatar helper.
 */

function get_user_profile_image($user_id = false, $size = 'thumbnail') {
    mt_profile_img($user_id, [
        'size' => $size,
        'attr' => ['alt' => ''],
        'echo' => true,
    ]);
}
