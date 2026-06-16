<?php
/**
 * @deprecated Taşındı: apps/asset-manager/SCSSCompiler.php
 * Backward-compat shim — eski çağrılar kırılmasın diye.
 */
if ( ! class_exists( 'SCSSCompiler' ) && class_exists( 'SaltHareket\AssetManager\SCSSCompiler' ) ) {
    class_alias( 'SaltHareket\AssetManager\SCSSCompiler', 'SCSSCompiler' );
}
