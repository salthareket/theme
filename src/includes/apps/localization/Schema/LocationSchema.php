<?php

namespace SaltHareket\Localization\Schema;

/**
 * LocationSchema
 *
 * DB tablolarını oluşturur/kontrol eder.
 * SQL dosyaları static/data/ klasöründen okunur.
 *
 * @version 1.0.0
 */
class LocationSchema
{
    // cities tablosu kaldırıldı — salt-next ile uyumlu (cities DB tablosu kullanılmıyor)
    private const TABLES = [ 'countries', 'states', 'ip2country' ];
    private const VERSION_KEY = 'sh_location_schema_version';
    private const CURRENT_VERSION = '1.0.0';

    /**
     * Gerekli tabloları kur.
     * Transient cache ile korunur — her request'te çalışmaz.
     */
    public static function install(): void
    {
        // Versiyon kontrolü — aynı versiyon kuruluysa atla
        if ( get_option( self::VERSION_KEY ) === self::CURRENT_VERSION ) return;

        $settings = \SaltHareket\Localization\LocationSettings::get();

        $needs_ip    = $settings['enable_ip2country'] && $settings['ip2country_source'] === 'db';
        $needs_geo   = $settings['enable_ip2country'] || $settings['enable_location_db'];

        if ( $needs_ip )  self::maybeCreateTable( 'ip2country' );
        else              self::maybeDropTable( 'ip2country' );

        if ( $needs_geo ) {
            self::maybeCreateTable( 'countries' );
            self::maybeCreateTable( 'states' );
        } else {
            self::maybeDropTable( 'countries' );
            self::maybeDropTable( 'states' );
        }

        update_option( self::VERSION_KEY, self::CURRENT_VERSION, false );
    }

    /**
     * Tablo yoksa oluştur.
     * Transient ile korunur — 7 gün boyunca tekrar kontrol etmez.
     */
    public static function maybeCreateTable( string $table ): void
    {
        $cache_key = 'sh_table_exists_' . $table;
        if ( get_transient( $cache_key ) ) return;

        global $wpdb;
        $exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) );

        if ( ! $exists ) {
            self::createTable( $table );
        }

        set_transient( $cache_key, true, 7 * DAY_IN_SECONDS );
    }

    /**
     * SQL dosyasından tabloyu oluştur.
     */
    public static function createTable( string $table ): bool
    {
        global $wpdb;

        $filename = SH_STATIC_PATH . 'data/' . $table . '.sql';
        if ( ! file_exists( $filename ) ) {
            error_log( "[Localization] SQL file not found: {$filename}" );
            return false;
        }

        $sql_raw = file_get_contents( $filename );
        if ( empty( trim( $sql_raw ) ) ) return false;

        // Prefix replace
        $sql_raw = str_replace( '`' . $table . '`', '`' . $wpdb->prefix . $table . '`', $sql_raw );

        $templine = '';
        foreach ( explode( "\n", $sql_raw ) as $line ) {
            $line = rtrim( $line );
            if ( $line === '' || str_starts_with( $line, '--' ) ) continue;
            $templine .= $line . "\n";
            if ( str_ends_with( trim( $line ), ';' ) ) {
                $wpdb->query( $templine ); // phpcs:ignore
                $templine = '';
            }
        }

        // Transient'i temizle — tablo artık var
        delete_transient( 'sh_table_exists_' . $table );
        set_transient( 'sh_table_exists_' . $table, true, 7 * DAY_IN_SECONDS );

        error_log( "[Localization] Table created: {$wpdb->prefix}{$table}" );
        return true;
    }

    /**
     * Tabloyu sil.
     */
    public static function maybeDropTable( string $table ): void
    {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`" );
        delete_transient( 'sh_table_exists_' . $table );
    }

    /**
     * Tablo durumunu döndür.
     */
    public static function getTableStatus(): array
    {
        global $wpdb;
        $status = [];

        foreach ( self::TABLES as $table ) {
            $full_name = $wpdb->prefix . $table;
            $exists    = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) );
            $count     = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$full_name}`" ) : 0;

            $status[ $table ] = [
                'exists' => $exists,
                'count'  => $count,
                'name'   => $full_name,
            ];
        }

        return $status;
    }

    /**
     * Tüm transient'leri temizle — sonraki request'te yeniden kontrol edilir.
     */
    public static function clearCache(): void
    {
        foreach ( self::TABLES as $table ) {
            delete_transient( 'sh_table_exists_' . $table );
        }
        delete_option( self::VERSION_KEY );
    }
}
