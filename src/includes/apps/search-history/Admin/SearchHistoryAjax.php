<?php

/**
 * SearchHistoryAjax — Admin AJAX Handler'lar Trait
 *
 * Tekil/toplu silme, CSV export, blacklist ekle/kaldır ve
 * tablo JS (sıralama, filtreleme, sayfalama, Chart.js).
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.0.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrıldı
 *   - Fix: GET ile silme → AJAX + nonce ile güvenli silme
 *   - Fix: meta refresh → AJAX response
 *   - Add: ajax_export_csv() — BOM'lu UTF-8 CSV
 *   - Add: ajax_blacklist_add() / ajax_blacklist_remove()
 *   - Add: render_admin_js() — Chart.js + tablo JS
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sınıfı içinde `use SearchHistoryAjax;` ile kullanılır.
 *   Tüm AJAX action'ları constructor'da kayıtlıdır.
 *
 * @example AJAX: Tekil sil (JS):
 *   fetch(ajaxurl, { method:'POST', body: new URLSearchParams({
 *       action:'sh_delete_term', nonce: shNonce, id: 42
 *   })});
 *
 * @example AJAX: Hepsini sil (JS):
 *   fetch(ajaxurl, { method:'POST', body: new URLSearchParams({
 *       action:'sh_delete_all', nonce: shNonce
 *   })});
 *
 * @example CSV export:
 *   window.location = ajaxurl + '?action=sh_export_csv&nonce=' + shNonce;
 *
 * @example Blacklist ekle (JS):
 *   fetch(ajaxurl, { method:'POST', body: new URLSearchParams({
 *       action:'sh_blacklist_add', nonce: shBlNonce, term: 'spam'
 *   })});
 */
trait SearchHistoryAjax {

    // =========================================================================
    // AJAX — Kayıt silme
    // =========================================================================

    /**
     * AJAX: Tekil kayıt sil.
     * Nonce: sh_nonce
     */
    public function ajax_delete_term(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( $id < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Geçersiz ID.', 'salthareket' ) ], 400 );
        }

        if ( $this->delete_term( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'Kayıt silindi.', 'salthareket' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Silinemedi.', 'salthareket' ) ], 500 );
        }
    }

    /**
     * AJAX: Tüm kayıtları sil — hem wp_search_terms hem wp_search_clicks.
     * Nonce: sh_nonce
     */
    public function ajax_delete_all(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $terms_ok  = $this->delete_all();
        $clicks_ok = $this->delete_all_clicks();

        if ( $terms_ok && $clicks_ok ) {
            wp_send_json_success( [ 'message' => __( 'Tüm arama ve tıklama kayıtları silindi.', 'salthareket' ) ] );
        } elseif ( $terms_ok ) {
            wp_send_json_success( [ 'message' => __( 'Arama kayıtları silindi. Tıklama kayıtları silinemedi.', 'salthareket' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Silinemedi.', 'salthareket' ) ], 500 );
        }
    }

    // =========================================================================
    // AJAX — CSV Export
    // =========================================================================

    /**
     * AJAX: Tüm click kayıtlarını sil.
     * Nonce: sh_nonce
     */
    public function ajax_delete_clicks(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ], 403 );
        }
        if ( $this->delete_all_clicks() ) {
            wp_send_json_success( [ 'message' => 'Tıklama kayıtları silindi.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Silinemedi.' ], 500 );
        }
    }

    /**
     * AJAX: CSV export.
     * BOM'lu UTF-8 — Excel uyumlu.
     * Nonce: sh_nonce
     */
    public function ajax_export_csv(): void {
        check_ajax_referer( 'sh_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetersiz yetki.', 'salthareket' ), 403 );
        }

        $rows     = $this->get_all( 'rank', 'DESC' );
        $filename = 'search-history-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        if ( false === $output ) wp_die( 'CSV stream açılamadı.' );

        fputs( $output, "\xEF\xBB\xBF" ); // BOM
        fputcsv( $output, [ 'ID', 'Term', 'Type', 'Rank', 'No Results', 'First Seen', 'Last Searched' ] );

        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $row->id,
                urldecode( $row->name ),
                $row->type,
                $row->rank,
                $row->no_results ? 'Yes' : 'No',
                $row->date,
                $row->date_modified,
            ] );
        }

        fclose( $output );
        exit;
    }

    // =========================================================================
    // AJAX — Blacklist
    // =========================================================================

    /**
     * AJAX: Blacklist'e terim ekle.
     * Nonce: sh_blacklist_nonce
     */
    public function ajax_blacklist_add(): void {
        check_ajax_referer( 'sh_blacklist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        if ( '' === $term ) {
            wp_send_json_error( [ 'message' => __( 'Terim boş olamaz.', 'salthareket' ) ], 400 );
        }

        if ( $this->add_to_blacklist( $term ) ) {
            $list = $this->get_blacklist();
            $id   = 0;
            foreach ( array_reverse( $list ) as $item ) {
                if ( isset( $item['term'] ) && $item['term'] === mb_strtolower( $term ) ) {
                    $id = (int) $item['id'];
                    break;
                }
            }
            wp_send_json_success( [ 'message' => __( 'Eklendi.', 'salthareket' ), 'id' => $id ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Zaten mevcut veya eklenemedi.', 'salthareket' ) ], 400 );
        }
    }

    /**
     * AJAX: Blacklist'ten terim kaldır.
     * Nonce: sh_blacklist_nonce
     */
    public function ajax_blacklist_remove(): void {
        check_ajax_referer( 'sh_blacklist_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetersiz yetki.', 'salthareket' ) ], 403 );
        }

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( $id < 1 ) {
            wp_send_json_error( [ 'message' => __( 'Geçersiz ID.', 'salthareket' ) ], 400 );
        }

        if ( $this->remove_from_blacklist( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'Kaldırıldı.', 'salthareket' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Kaldırılamadı.', 'salthareket' ) ], 500 );
        }
    }

    /**
     * Enable/Disable Search History toggle AJAX handler.
     * action: sh_search_history_save_toggle
     */
    public function ajax_save_toggle(): void {
        check_ajax_referer( 'sh_search_history_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        $enabled = filter_var( $_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
        \SaltHareket\SearchHistory\SearchHistorySettings::save( [ 'enable_search_history' => $enabled ] );
        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    // =========================================================================
    // Admin JS — Chart.js + Tablo (sıralama, filtreleme, sayfalama)
    // =========================================================================

    /**
     * Admin sayfası için inline JavaScript.
     *
     * @param string $nonce
     * @param string $ajax_url
     * @param string $chart_labels  JSON encoded
     * @param string $chart_counts  JSON encoded
     */
    private function render_admin_js(
        string $nonce,
        string $ajax_url,
        string $chart_labels,
        string $chart_counts
    ): void {
        ?>
        <script>
        (function() {
            'use strict';

            // ── Toast ─────────────────────────────────────────────────────────
            function toast(msg, isError) {
                var el = document.getElementById('sh-toast');
                if (!el) return;
                el.textContent = msg;
                el.style.background = isError ? '#dc2626' : '#1f2937';
                el.classList.add('show');
                setTimeout(function() { el.classList.remove('show'); }, 3000);
            }

            // ── AJAX helper ───────────────────────────────────────────────────
            function doAjax(action, data, cb) {
                data.action = action;
                var fd = new FormData();
                Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
                fetch(<?php echo wp_json_encode( $ajax_url ); ?>, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) { cb(res); })
                    .catch(function() { toast('Bağlantı hatası.', true); });
            }

            // ── Chart.js ──────────────────────────────────────────────────────
            var chartLabels = <?php echo $chart_labels; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
            var chartCounts = <?php echo $chart_counts; // phpcs:ignore WordPress.Security.EscapeOutput ?>;

            function initChart() {
                if (typeof Chart === 'undefined') return;
                var ctx = document.getElementById('sh-chart');
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Arama Sayısı',
                            data: chartCounts,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,.08)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#2563eb',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) { return ' ' + ctx.parsed.y + ' arama'; }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            // Chart.js yüklendikten sonra çalıştır
            if (typeof Chart !== 'undefined') {
                initChart();
            } else {
                // Script henüz yüklenmemişse load event'ini bekle
                window.addEventListener('load', initChart);
            }

            // ── Tablo ─────────────────────────────────────────────────────────
            var tbody       = document.getElementById('sh-tbody');
            var allRows     = Array.from(tbody ? tbody.querySelectorAll('tr') : []);
            var filtered    = allRows.slice();
            var sortCol     = 'rank';
            var sortDir     = 'desc';
            var PAGE_SIZE   = 50;
            var currentPage = 1;

            // Sıralama
            document.querySelectorAll('#sh-main-table th[data-col]').forEach(function(th) {
                th.addEventListener('click', function() {
                    var col = this.getAttribute('data-col');
                    sortDir = (sortCol === col) ? (sortDir === 'asc' ? 'desc' : 'asc') : 'desc';
                    sortCol = col;
                    document.querySelectorAll('#sh-main-table th').forEach(function(t) {
                        t.classList.remove('sorted-asc', 'sorted-desc');
                    });
                    this.classList.add('sorted-' + sortDir);
                    applyFiltersAndSort();
                });
            });

            function getVal(row, col) {
                return row.getAttribute('data-' + col.replace(/_/g, '-')) || '';
            }

            function sortRows(rows) {
                return rows.slice().sort(function(a, b) {
                    var av = getVal(a, sortCol), bv = getVal(b, sortCol);
                    var na = parseFloat(av), nb = parseFloat(bv);
                    var cmp = (!isNaN(na) && !isNaN(nb)) ? (na - nb) : av.localeCompare(bv, undefined, { sensitivity: 'base' });
                    return sortDir === 'asc' ? cmp : -cmp;
                });
            }

            // Filtreleme
            var searchInput = document.getElementById('sh-search-input');
            var typeFilter  = document.getElementById('sh-type-filter');
            var noResFilter = document.getElementById('sh-noresults-filter');

            function applyFiltersAndSort() {
                var q       = searchInput ? searchInput.value.toLowerCase().trim() : '';
                var typeVal = typeFilter  ? typeFilter.value  : '';
                var noRes   = noResFilter ? noResFilter.value : '';

                filtered = allRows.filter(function(row) {
                    if (q       && (row.getAttribute('data-name') || '').toLowerCase().indexOf(q) === -1) return false;
                    if (typeVal && (row.getAttribute('data-type') || '') !== typeVal) return false;
                    if (noRes !== '' && (row.getAttribute('data-no-results') || '') !== noRes) return false;
                    return true;
                });

                filtered = sortRows(filtered);
                currentPage = 1;
                renderPage();
            }

            if (searchInput) searchInput.addEventListener('input',  applyFiltersAndSort);
            if (typeFilter)  typeFilter.addEventListener('change',  applyFiltersAndSort);
            if (noResFilter) noResFilter.addEventListener('change', applyFiltersAndSort);

            // Sayfalama
            function renderPage() {
                var start = (currentPage - 1) * PAGE_SIZE;
                var page  = filtered.slice(start, start + PAGE_SIZE);
                allRows.forEach(function(r) { r.classList.add('sh-hidden'); });
                page.forEach(function(r)    { r.classList.remove('sh-hidden'); });
                var countEl = document.getElementById('sh-row-count');
                if (countEl) countEl.textContent = filtered.length + ' kayıt';
                renderPagination();
            }

            function renderPagination() {
                var pag   = document.getElementById('sh-pagination');
                if (!pag) return;
                var total = Math.ceil(filtered.length / PAGE_SIZE);
                pag.innerHTML = '';
                if (total <= 1) return;
                for (var i = 1; i <= total; i++) {
                    (function(p) {
                        var btn = document.createElement('button');
                        btn.className = 'sh-page-btn' + (p === currentPage ? ' active' : '');
                        btn.textContent = p;
                        btn.addEventListener('click', function() { currentPage = p; renderPage(); });
                        pag.appendChild(btn);
                    })(i);
                }
            }

            applyFiltersAndSort();

            // Tekil sil
            tbody && tbody.addEventListener('click', function(e) {
                var btn = e.target.closest('.sh-delete-btn');
                if (!btn) return;
                if (!confirm('Bu kaydı silmek istediğinizden emin misiniz?')) return;
                doAjax('sh_delete_term', { id: btn.getAttribute('data-id'), nonce: btn.getAttribute('data-nonce') }, function(res) {
                    if (res.success) {
                        var row = btn.closest('tr');
                        if (row) { allRows = allRows.filter(function(r) { return r !== row; }); row.remove(); }
                        applyFiltersAndSort();
                        toast(res.data.message || 'Silindi.');
                    } else {
                        toast((res.data && res.data.message) || 'Hata.', true);
                    }
                });
            });

            // Hepsini sil
            var delAllBtn = document.getElementById('sh-delete-all-btn');
            if (delAllBtn) {
                delAllBtn.addEventListener('click', function() {
                    if (!confirm('TÜM kayıtları silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) return;
                    doAjax('sh_delete_all', { nonce: this.getAttribute('data-nonce') }, function(res) {
                        if (res.success) {
                            allRows = []; filtered = [];
                            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">Kayıt bulunamadı.</td></tr>';
                            renderPage();
                            toast(res.data.message || 'Tüm kayıtlar silindi.');
                        } else {
                            toast((res.data && res.data.message) || 'Hata.', true);
                        }
                    });
                });
            }

            // Blacklist: kaldır
            var blList = document.getElementById('sh-blacklist-list');
            if (blList) {
                blList.addEventListener('click', function(e) {
                    var btn = e.target.closest('.sh-blacklist-remove');
                    if (!btn) return;
                    doAjax('sh_blacklist_remove', { id: btn.getAttribute('data-id'), nonce: btn.getAttribute('data-nonce') }, function(res) {
                        if (res.success) { var li = btn.closest('li'); if (li) li.remove(); toast('Kaldırıldı.'); }
                        else toast((res.data && res.data.message) || 'Hata.', true);
                    });
                });
            }

            // Blacklist: ekle
            var blAddBtn = document.getElementById('sh-blacklist-add-btn');
            if (blAddBtn) {
                blAddBtn.addEventListener('click', function() {
                    var input = document.getElementById('sh-blacklist-input');
                    var term  = input ? input.value.trim() : '';
                    if (!term) return;
                    var nonce = this.getAttribute('data-nonce');
                    doAjax('sh_blacklist_add', { term: term, nonce: nonce }, function(res) {
                        if (res.success) {
                            if (input) input.value = '';
                            var li = document.createElement('li');
                            li.setAttribute('data-id', res.data.id || '');
                            li.innerHTML = '<span>' + term + '</span>'
                                + '<button class="sh-blacklist-remove" data-id="' + (res.data.id || '') + '" data-nonce="' + nonce + '" title="Kaldır">&#215;</button>';
                            var emptyLi = blList ? blList.querySelector('li[style]') : null;
                            if (emptyLi) emptyLi.remove();
                            if (blList) blList.appendChild(li);
                            toast('Eklendi.');
                        } else {
                            toast((res.data && res.data.message) || 'Hata.', true);
                        }
                    });
                });
            }

        })();
        </script>
        <?php
    }
}
