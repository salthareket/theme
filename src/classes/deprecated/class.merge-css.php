<?php
/**
 * @deprecated Taşındı: apps/asset-manager/MergeCSS.php
 * Backward-compat shim — eski çağrılar kırılmasın diye.
 */
namespace SaltHareket\Theme;

if ( ! class_exists( 'SaltHareket\Theme\MergeCSS' ) && class_exists( 'SaltHareket\AssetManager\MergeCSS' ) ) {
    class_alias( 'SaltHareket\AssetManager\MergeCSS', 'SaltHareket\Theme\MergeCSS' );
}
