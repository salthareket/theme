<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Bu block klasöründe twig adı 'files' — override ile belirtiyoruz
salt_render_acf_block( $block, $is_preview, $post_id, 'files' );
