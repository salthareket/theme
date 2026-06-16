<?php

/**
 * ManagesBlacklist — SearchHistory Kara Liste Yönetimi Trait
 *
 * Kaydedilmeyecek arama terimlerinin wp_options'da saklanması ve yönetimi.
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.0.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrıldı
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sınıfı içinde `use ManagesBlacklist;` ile kullanılır.
 *   Blacklist wp_options'da 'sh_blacklist' key'i ile saklanır.
 *   Constructor'da load_blacklist() otomatik çağrılır.
 *
 * @example Blacklist'e ekle:
 *   $sh->add_to_blacklist('test');
 *
 * @example Blacklist'ten kaldır:
 *   $sh->remove_from_blacklist($id);
 *
 * @example Tüm blacklist:
 *   $list = $sh->get_blacklist();
 *   // [['id' => 1234567890, 'term' => 'test'], ...]
 *
 * @example Geçerlilik kontrolü (internal):
 *   if ($this->is_valid_term('ab')) { ... } // false — min 2 karakter
 */
trait ManagesBlacklist {

    /**
     * Blacklist'i option'dan yükle — $this->blacklist'e ata.
     */
    private function load_blacklist(): void {
        $saved           = get_option( self::BLACKLIST_OPTION, [] );
        $this->blacklist = is_array( $saved )
            ? array_map( 'mb_strtolower', array_column( $saved, 'term' ) )
            : [];
    }

    /**
     * Tüm blacklist kayıtlarını döner.
     *
     * @return array  [['id' => int, 'term' => string], ...]
     *
     * @example
     *   $list = $sh->get_blacklist();
     */
    public function get_blacklist(): array {
        $saved = get_option( self::BLACKLIST_OPTION, [] );
        return is_array( $saved ) ? $saved : [];
    }

    /**
     * Blacklist'e yeni terim ekle.
     *
     * @param  string $term
     * @return bool
     *
     * @example
     *   $sh->add_to_blacklist('spam');
     */
    public function add_to_blacklist( string $term ): bool {
        $term = trim( mb_strtolower( $term ) );
        if ( empty( $term ) ) return false;

        $list = $this->get_blacklist();
        foreach ( $list as $item ) {
            if ( isset( $item['term'] ) && $item['term'] === $term ) return false;
        }

        $list[] = [ 'id' => time(), 'term' => $term ];

        $updated = update_option( self::BLACKLIST_OPTION, $list );
        if ( $updated ) $this->load_blacklist();
        return $updated;
    }

    /**
     * Blacklist'ten terim kaldır.
     *
     * @param  int $id  add_to_blacklist'te atanan timestamp ID
     * @return bool
     *
     * @example
     *   $sh->remove_from_blacklist(1714900000);
     */
    public function remove_from_blacklist( int $id ): bool {
        $list    = $this->get_blacklist();
        $updated = array_values( array_filter( $list, fn( $item ) => (int) ( $item['id'] ?? 0 ) !== $id ) );

        $result = update_option( self::BLACKLIST_OPTION, $updated );
        if ( $result ) $this->load_blacklist();
        return $result;
    }

    /**
     * Terimin kaydedilip kaydedilmeyeceğini kontrol eder.
     * MIN_TERM_LENGTH ve blacklist kontrolü yapar.
     *
     * @param  string $term
     * @return bool
     *
     * @example
     *   $this->is_valid_term('a');      // false — çok kısa
     *   $this->is_valid_term('test');   // false — blacklist'te
     *   $this->is_valid_term('iphone'); // true
     */
    private function is_valid_term( string $term ): bool {
        if ( mb_strlen( $term ) < self::MIN_TERM_LENGTH ) return false;
        if ( in_array( mb_strtolower( $term ), $this->blacklist, true ) ) return false;
        return true;
    }
}
