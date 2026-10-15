<?php

namespace SaltHareket\PluginManager;

/**
 * PluginManager
 *
 * WP repo ve local ZIP plugin yükleme / güncelleme / aktifleştirme / deaktifleştirme.
 * Global \PluginManager backward-compat alias olarak tanımlanır (update.php, theme.php için).
 *
 * @version 1.0.0
 */
class PluginManager
{
    /** Local plugin ZIP'lerinin bulunduğu klasör */
    public static string $plugin_dir = '';

    public static function getPluginDir(): string
    {
        if ( self::$plugin_dir ) return self::$plugin_dir;
        // SH_INCLUDES_PATH: .../theme/src/includes/
        // content/plugins: .../theme/src/content/plugins/
        return defined( 'SH_INCLUDES_PATH' )
            ? dirname( SH_INCLUDES_PATH ) . '/content/plugins'
            : __DIR__ . '/../../../content/plugins';
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    /** theme.php'den çağrılır */
    public static function init(): void
    {
        // AJAX handler + script enqueue Admin class'ta
        // Bu metod eski kodların kırılmaması için korunur
    }

    // ─── Toplu Kurulum (update.php'den çağrılır) ──────────────────────────────

    /**
     * $plugin_types listesine göre WP repo plugin'lerini yükler.
     * "main" type her zaman yüklenir.
     */
    public static function check_and_install_required_plugins( array $plugin_types ): void
    {
        foreach ( \Data::get( 'plugins', [] ) as $plugin ) {
            if ( self::shouldProcess( $plugin['type'], $plugin_types ) && ! self::is_plugin_installed( $plugin['name'] ) ) {
                self::install_plugin_from_wp_repo( $plugin['name'] );
                self::activatePlugin( $plugin['name'] );
            }
        }
    }

    /**
     * $plugin_types listesine göre local plugin'leri yükler / günceller.
     * "main" type her zaman işlenir.
     */
    public static function check_and_update_local_plugins( array $plugin_types ): void
    {
        foreach ( \Data::get( 'plugins_local', [] ) as $plugin ) {
            $should = self::shouldProcess( $plugin['type'], $plugin_types );
            $outdated = ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['name'] )
                || self::is_version_outdated( $plugin['v'], $plugin['name'] );

            if ( $outdated ) {
                self::remove_plugin( $plugin['file'] );
                if ( $should ) self::install_local_plugin( $plugin );
            }

            if ( $should && ! self::is_plugin_active( $plugin['name'] ) ) {
                self::activatePlugin( $plugin['name'] );
            }
        }
    }

    // ─── Tekil İşlemler ───────────────────────────────────────────────────────

    public static function install_plugin_from_wp_repo( string $plugin_slug ): bool
    {
        self::requireWpUpgrader();
        \WP_Filesystem();
        $upgrader = new \Plugin_Upgrader( new \SilentUpgraderSkin() );
        $slug_clean = explode( '/', $plugin_slug )[0];
        $url    = 'https://downloads.wordpress.org/plugin/' . $slug_clean . '.zip';
        $result = $upgrader->install( $url );
        if ( ! is_wp_error( $result ) ) {
            self::installDependency( $plugin_slug, false );
        }
        return ! is_wp_error( $result );
    }

    public static function install_local_plugin( array $plugin_info ): bool
    {
        self::requireWpUpgrader();
        $zip = self::getPluginDir() . '/' . $plugin_info['file'] . '.zip';
        if ( ! file_exists( $zip ) ) return false;
        \WP_Filesystem();
        $upgrader = new \Plugin_Upgrader( new \SilentUpgraderSkin() );
        $result   = $upgrader->install( $zip );
        if ( ! is_wp_error( $result ) ) {
            self::installDependency( $plugin_info, true );
        }
        return ! is_wp_error( $result );
    }

    public static function activatePlugin( string $slug ): void
    {
        if ( ! self::is_plugin_active( $slug ) ) {
            activate_plugin( $slug );
        }
    }

    public static function deactivatePlugin( string $slug ): void
    {
        deactivate_plugins( $slug );
    }

    public static function remove_plugin( string $plugin_dir_name ): void
    {
        if ( empty( $plugin_dir_name ) ) return;
        $path = WP_PLUGIN_DIR . '/' . $plugin_dir_name;
        if ( is_dir( $path ) ) self::deleteDirectory( $path );
    }

    // ─── Durum Sorguları ──────────────────────────────────────────────────────

    public static function is_plugin_installed( string $plugin_slug ): bool
    {
        return file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug );
    }

    public static function is_plugin_active( string $plugin_slug ): bool
    {
        return is_plugin_active( $plugin_slug );
    }

    /** Installed version < expected version ise true döner */
    public static function is_version_outdated( string $expected, string $plugin_name ): bool
    {
        $path = WP_PLUGIN_DIR . '/' . $plugin_name;
        if ( ! file_exists( $path ) ) return true;
        $data = get_plugin_data( $path, false, false );
        return version_compare( $data['Version'] ?? '0', $expected, '<' );
    }

    // ─── Yardımcı Metodlar ───────────────────────────────────────────────────

    /** "main" her zaman true, yoksa type kesişimi var mı? */
    private static function shouldProcess( array $plugin_types, array $selected_types ): bool
    {
        return in_array( 'main', $plugin_types, true )
            || ! empty( array_intersect( $plugin_types, $selected_types ) );
    }

    private static function installDependency( $plugin_info, bool $is_local ): void
    {
        // 'dependency' (+ backward compat 'depency')
        $dep = null;
        if ( $is_local && is_array( $plugin_info ) ) {
            $dep = $plugin_info['dependency'] ?? $plugin_info['depency'] ?? null;
            if ( $dep && ! self::is_plugin_installed( $dep ) ) {
                // WP repo'dan yükle
                self::install_plugin_from_wp_repo( $dep );
            }
        } else {
            $slug    = is_string( $plugin_info ) ? $plugin_info : ( $plugin_info['name'] ?? '' );
            $plugins = \Data::get( 'plugins', [] );
            $found   = current( array_filter( $plugins, fn( $p ) => $p['name'] === $slug ) );
            if ( $found ) {
                $dep_slug = $found['dependency'] ?? $found['depency'] ?? null;
                if ( $dep_slug ) {
                    $local_plugins = \Data::get( 'plugins_local', [] );
                    $dep_local     = current( array_filter( $local_plugins, fn( $p ) => $p['name'] === $dep_slug ) );
                    if ( $dep_local && ! self::is_plugin_installed( $dep_slug ) ) {
                        self::install_local_plugin( $dep_local );
                    }
                }
            }
        }
    }

    private static function requireWpUpgrader(): void
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // SilentUpgraderSkin — ayrı dosyada tanımlı (PHP class nested tanım yasak)
        require_once __DIR__ . '/SilentUpgraderSkin.php';
    }

    private static function deleteDirectory( string $dir ): void
    {
        foreach ( array_diff( scandir( $dir ), [ '.', '..' ] ) as $file ) {
            $path = $dir . '/' . $file;
            is_dir( $path ) ? self::deleteDirectory( $path ) : unlink( $path );
        }
        rmdir( $dir );
    }

    // ─── Render (backward compat — Admin class'a delege eder) ────────────────

    public static function render_option_page(): void
    {
        \SaltHareket\PluginManager\Admin\PluginManagerAdmin::renderPage();
    }
}

// ─── Global alias (backward compat) ──────────────────────────────────────────
// update.php: PluginManager::check_and_install_required_plugins(...)
// theme.php:  PluginManager::init()
if ( ! class_exists( 'PluginManager' ) ) {
    class_alias( \SaltHareket\PluginManager\PluginManager::class, 'PluginManager' );
}
