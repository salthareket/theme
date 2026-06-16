<?php

namespace SaltHareket\ThemeExport\Concerns;

/**
 * HandlesDatabase
 *
 * PDO ile chunk'lı DB export, serialized/JSON safe URL replace, prefix replace.
 *
 * @version 1.0.0
 */
trait HandlesDatabase
{
    private const DB_CHUNK = 500;

    /**
     * Tüm DB'yi SQL dosyasına yaz.
     */
    protected function exportDatabase(
        string $path,
        string $cur_url,
        string $tar_url,
        string $tmp_dir,
        string $target_prefix = ''
    ): void {
        global $wpdb;

        if ( ! class_exists( 'PDO' ) ) {
            throw new \Exception( 'PDO extension is not installed.' );
        }

        $current_prefix = $wpdb->prefix;
        $live_url       = ! empty( $tar_url ) ? $tar_url : $cur_url;

        $pdo = new \PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASSWORD,
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $tables = $pdo->query( 'SHOW TABLES' )->fetchAll( \PDO::FETCH_COLUMN );

        $header = "-- WordPress MySQL Export\n"
                . "-- Target URL: {$live_url}\n"
                . "-- Prefix: {$current_prefix} -> " . ( $target_prefix ?: $current_prefix ) . "\n"
                . "-- Date: " . date( 'Y-m-d H:i:s' ) . "\n\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        file_put_contents( $path, $header );

        $bad_collations = [
            'utf8mb4_0900_ai_ci',
            'utf8mb4_0900_as_ci',
            'utf8mb4_0900_as_cs',
            'utf8mb4_0900_bin',
        ];

        foreach ( $tables as $table ) {
            $this->checkCancel( $tmp_dir );

            // Hedef tablo adı (prefix replace)
            $target_table = $table;
            if ( $target_prefix && str_starts_with( $table, $current_prefix ) ) {
                $target_table = $target_prefix . substr( $table, strlen( $current_prefix ) );
            }

            // CREATE TABLE
            $create     = $pdo->query( "SHOW CREATE TABLE `{$table}`" )->fetch();
            $create_sql = $create['Create Table'];
            $create_sql = str_replace( "CREATE TABLE `{$table}`", "CREATE TABLE `{$target_table}`", $create_sql );
            foreach ( $bad_collations as $coll ) {
                $create_sql = str_ireplace( $coll, 'utf8mb4_unicode_ci', $create_sql );
            }

            $sql = "DROP TABLE IF EXISTS `{$target_table}`;\n{$create_sql};\n\n";
            file_put_contents( $path, $sql, FILE_APPEND );

            // DATA — chunk'lı
            $total  = (int) $pdo->query( "SELECT COUNT(*) FROM `{$table}`" )->fetchColumn();
            $offset = 0;

            while ( $offset < $total ) {
                $this->checkCancel( $tmp_dir );

                $rows   = $pdo->query( "SELECT * FROM `{$table}` LIMIT " . self::DB_CHUNK . " OFFSET {$offset}" )->fetchAll();
                $buffer = '';

                foreach ( $rows as $row ) {
                    $values = [];
                    foreach ( $row as $key => $val ) {
                        if ( $val === null ) {
                            $values[] = 'NULL';
                            continue;
                        }

                        // Prefix replace (usermeta / options)
                        if ( $target_prefix ) {
                            $is_meta = str_contains( $table, 'usermeta' ) || str_contains( $table, 'options' );
                            if ( $is_meta && is_string( $val ) && str_starts_with( $val, $current_prefix ) ) {
                                $val = $target_prefix . substr( $val, strlen( $current_prefix ) );
                            }
                        }

                        // URL replace (serialized/JSON safe)
                        if ( $cur_url && $tar_url ) {
                            $val = $this->safeReplace( $cur_url, $tar_url, $val );
                        }

                        $values[] = $pdo->quote( (string) $val );
                    }

                    $buffer .= "INSERT INTO `{$target_table}` VALUES (" . implode( ', ', $values ) . ");\n";
                }

                if ( $buffer !== '' ) {
                    foreach ( $bad_collations as $coll ) {
                        $buffer = str_ireplace( $coll, 'utf8mb4_unicode_ci', $buffer );
                    }
                    file_put_contents( $path, $buffer, FILE_APPEND );
                }

                $offset += self::DB_CHUNK;
            }

            file_put_contents( $path, "\n", FILE_APPEND );
        }

        file_put_contents( $path, "SET FOREIGN_KEY_CHECKS = 1;\n", FILE_APPEND );
    }

    // ─── Safe Replace ─────────────────────────────────────────────────────────

    protected function safeReplace( string $search, string $replace, mixed $data ): mixed
    {
        if ( empty( $data ) || is_numeric( $data ) || empty( $search ) ) return $data;

        if ( is_string( $data ) ) {
            // Serialized
            if ( $this->isSerialized( $data ) ) {
                $unserialized = @unserialize( $data );
                if ( $unserialized !== false || $data === 'b:0;' ) {
                    return serialize( $this->safeReplace( $search, $replace, $unserialized ) );
                }
            }

            // JSON
            $json = json_decode( $data, true );
            if ( is_array( $json ) && json_last_error() === JSON_ERROR_NONE ) {
                array_walk_recursive( $json, function ( &$v ) use ( $search, $replace ) {
                    if ( is_string( $v ) ) $v = str_replace( $search, $replace, $v );
                } );
                return json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            }

            // Plain string
            $data = str_replace( $search, $replace, $data );
            $data = str_replace(
                str_replace( '/', '\\/', $search ),
                str_replace( '/', '\\/', $replace ),
                $data
            );
            return $data;
        }

        if ( is_array( $data ) ) {
            foreach ( $data as $k => $v ) {
                $data[ $k ] = $this->safeReplace( $search, $replace, $v );
            }
            return $data;
        }

        if ( is_object( $data ) ) {
            $clone = clone $data;
            foreach ( get_object_vars( $data ) as $k => $v ) {
                try { $clone->$k = $this->safeReplace( $search, $replace, $v ); } catch ( \Throwable $e ) {}
            }
            return $clone;
        }

        return $data;
    }

    protected function isSerialized( string $data ): bool
    {
        $data = trim( $data );
        if ( $data === 'N;' ) return true;
        if ( ! preg_match( '/^([adObis]):/', $data, $m ) ) return false;
        return match ( $m[1] ) {
            'a', 'O', 's' => (bool) preg_match( "/^{$m[1]}:[0-9]+:.*[;}]\$/s", $data ),
            'b', 'i', 'd' => (bool) preg_match( "/^{$m[1]}:[0-9.E+-]+;\$/", $data ),
            default        => false,
        };
    }
}
