<?php
/**
 * @deprecated Taşındı: apps/asset-manager/SaltMinifier.php
 * Backward-compat shim — eski çağrılar kırılmasın diye.
 */
if ( ! class_exists( 'SaltMinifier' ) && class_exists( 'SaltHareket\AssetManager\SaltMinifier' ) ) {
    class_alias( 'SaltHareket\AssetManager\SaltMinifier', 'SaltMinifier' );
}
