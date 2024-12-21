<?php

function wporg_block_renderer( $parsed_block, $source_block ) {
    //$parsed_block["attrs"]["disabled"] = "fade-in";
    return $parsed_block;
}
add_filter( 'render_block_data', 'wporg_block_renderer', 10, 2 );