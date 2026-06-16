<?php
/**
 * @deprecated Taşındı: apps/asset-manager/AssetPacker.php
 * Backward-compat shim — eski çağrılar kırılmasın diye.
 */
namespace SaltHareket\Theme;

if ( ! class_exists( 'SaltHareket\Theme\AssetPacker' ) && class_exists( 'SaltHareket\AssetManager\AssetPacker' ) ) {
    class_alias( 'SaltHareket\AssetManager\AssetPacker', 'SaltHareket\Theme\AssetPacker' );
}
