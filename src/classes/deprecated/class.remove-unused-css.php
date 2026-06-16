<?php
/**
 * @deprecated Taşındı: apps/asset-manager/RemoveUnusedCss.php
 * Backward-compat shim — eski çağrılar kırılmasın diye.
 */
if ( ! class_exists( 'RemoveUnusedCss' ) && class_exists( 'SaltHareket\AssetManager\RemoveUnusedCss' ) ) {
    class_alias( 'SaltHareket\AssetManager\RemoveUnusedCss', 'RemoveUnusedCss' );
}
