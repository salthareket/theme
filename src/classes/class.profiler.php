<?php

/**
 * SaltProfiler — Lightweight PHP profiler
 *
 * Kullanım:
 *   SaltProfiler::start('my_operation');
 *   ... // ölçülecek kod
 *   SaltProfiler::stop('my_operation');
 *
 *   SaltProfiler::report(); // tüm ölçümleri logla
 *
 * Aktif/pasif:
 *   define('SALT_PROFILER', true); // variables.php veya wp-config.php'de
 *
 * @package SaltHareket
 * @version 1.0.0
 */
class SaltProfiler
{
    /** @var array<string, float> */
    private static array $timers = [];

    /** @var array<array{label:string, duration:float, memory:int, queries:int}> */
    private static array $log = [];

    /** @var int DB sorgu sayacı başlangıcı */
    private static array $queryCount = [];

    /** @var float|null Toplam süre başlangıcı */
    private static ?float $boot = null;

    /** @var bool Aktif mi */
    private static bool $enabled = false;

    // ─────────────────────────────────────────────────────────────────────────

    public static function enable(): void
    {
        // Zaten aktifse tekrar bağlama
        if ( self::$enabled ) return;

        self::$enabled = true;
        if ( self::$boot === null ) {
            self::$boot = microtime( true );
        }
        // NOT: shutdown hook variables.php'de bağlanıyor — burada bağlanmıyor
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Ölçüm başlat.
     */
    public static function start( string $label ): void
    {
        if ( ! self::$enabled ) return;

        self::$timers[ $label ]     = microtime( true );
        self::$queryCount[ $label ] = self::getQueryCount();
    }

    /**
     * Ölçüm bitir ve kaydet.
     */
    public static function stop( string $label ): void
    {
        if ( ! self::$enabled ) return;
        if ( ! isset( self::$timers[ $label ] ) ) return;

        $duration = microtime( true ) - self::$timers[ $label ];
        $queries  = self::getQueryCount() - ( self::$queryCount[ $label ] ?? 0 );
        $memory   = memory_get_usage( true );

        self::$log[] = [
            'label'    => $label,
            'duration' => $duration,
            'memory'   => $memory,
            'queries'  => $queries,
        ];

        unset( self::$timers[ $label ] );
        unset( self::$queryCount[ $label ] );
    }

    /**
     * Anlık checkpoint — start/stop arasında kullanılabilir.
     */
    public static function mark( string $label ): void
    {
        if ( ! self::$enabled ) return;

        $elapsed = self::$boot ? ( microtime( true ) - self::$boot ) : 0;
        self::$log[] = [
            'label'    => '📍 ' . $label,
            'duration' => $elapsed,
            'memory'   => memory_get_usage( true ),
            'queries'  => self::getQueryCount(),
        ];
    }

    /**
     * Tüm ölçümleri error_log'a yaz.
     */
    public static function report( string $title = 'PROFILER REPORT' ): void
    {
        if ( ! self::$enabled || empty( self::$log ) ) return;

        $total = self::$boot ? ( microtime( true ) - self::$boot ) : 0;
        $peak  = memory_get_peak_usage( true );

        $lines = [
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
            "  ⏱  {$title}",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        // En yavaş 5'i bul
        $sorted = self::$log;
        usort( $sorted, fn( $a, $b ) => $b['duration'] <=> $a['duration'] );

        foreach ( self::$log as $entry ) {
            $ms      = number_format( $entry['duration'] * 1000, 2 );
            $mem     = self::formatBytes( $entry['memory'] );
            $q       = $entry['queries'];
            $slow    = $entry['duration'] > 0.1 ? ' 🔴' : ( $entry['duration'] > 0.05 ? ' 🟡' : '' );

            $lines[] = sprintf(
                '  %-45s %8s ms  %8s  %d q%s',
                $entry['label'],
                $ms,
                $mem,
                $q,
                $slow
            );
        }

        $lines[] = '─────────────────────────────────────────────────';
        $lines[] = sprintf(
            '  %-45s %8s ms  %8s  %d q',
            'TOTAL',
            number_format( $total * 1000, 2 ),
            self::formatBytes( $peak ),
            self::getQueryCount()
        );
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

        foreach ( $lines as $line ) {
            error_log( '[SaltProfiler] ' . $line );
        }
    }

    /**
     * Log array'i döndür (debug panel için).
     */
    public static function getLog(): array
    {
        return self::$log;
    }

    /**
     * Temizle.
     */
    public static function reset(): void
    {
        self::$timers     = [];
        self::$log        = [];
        self::$queryCount = [];
        self::$boot       = null;
    }

    // ─── Yardımcılar ──────────────────────────────────────────────────────────

    private static function getQueryCount(): int
    {
        global $wpdb;
        return isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : 0;
    }

    private static function formatBytes( int $bytes ): string
    {
        if ( $bytes >= 1024 * 1024 ) {
            return number_format( $bytes / 1024 / 1024, 1 ) . ' MB';
        }
        return number_format( $bytes / 1024, 0 ) . ' KB';
    }
}
