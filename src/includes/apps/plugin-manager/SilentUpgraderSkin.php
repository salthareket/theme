<?php
/**
 * SilentUpgraderSkin — WP 6.7+ uyumlu minimal upgrader skin.
 * Silent_Upgrader_Skin WP 6.7'de kaldırıldı, bu sınıf onun yerini alır.
 * PluginManager::requireWpUpgrader() tarafından include edilir.
 */
if ( ! class_exists( 'SilentUpgraderSkin' ) ) {
    if ( class_exists( 'Silent_Upgrader_Skin' ) ) {
        // Eski WP sürümü — alias yeterli
        class_alias( 'Silent_Upgrader_Skin', 'SilentUpgraderSkin' );
    } elseif ( class_exists( 'WP_Upgrader_Skin' ) ) {
        // WP 6.7+ — minimal silent skin
        class SilentUpgraderSkin extends WP_Upgrader_Skin {
            public function feedback( $string, ...$args ): void {}
            public function header(): void {}
            public function footer(): void {}
            public function error( $errors ): void {}
            public function before(): void {}
            public function after(): void {}
        }
    }
}
