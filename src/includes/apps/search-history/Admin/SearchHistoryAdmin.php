<?php
/**
 * SearchHistoryAdmin — Admin Sayfa Trait
 *
 * @package    SaltHareket\Theme\SearchHistory
 * @version    1.3.0
 * @since      3.0.0
 *
 * CHANGELOG:
 * 1.3.0 - 2026-05-08
 *   - Change: enqueue_admin_assets() — sh-admin.css enqueue edildi
 *   - Remove: inline <style> blogu kaldirildi (150+ satir) — CSS artik sh-admin.css'den geliyor
 *
 * 1.2.0 - 2026-05-04
 *   - Add: Clicks analytics — Tiklanan Sonuclar tablosu
 *   - Add: lang kolonu admin tabloda
 *   - Add: click_count + last_clicked_url kolonlari
 *   - Fix: BOM sorunu — UTF-8 without BOM
 *
 * 1.1.0 - 2026-05-04
 *   - Fix: Chart.js admin_head ile inline script tag
 *   - Add: Period selector (7/30/90/365 gun)
 *   - Add: En cok aranan + Son aranan stat kartlari
 *
 * 1.0.0 - 2026-05-04
 *   - Refactor: class.search-history.php'den ayrildi
 *
 * HOW TO USE:
 *   Bu trait SearchHistory sinifi icinde `use SearchHistoryAdmin;` ile kullanilir.
 *   Admin URL: /wp-admin/admin.php?page=search-history
 *
 * @example
 *   // Otomatik — register_admin_page() hook'u ile kayitli
 */
trait SearchHistoryAdmin {

    public function register_admin_page(): void {
        add_submenu_page(
            'theme-settings',
            __( '🔍 Search Ranks', 'salthareket' ),
            __( '🔍 Search Ranks', 'salthareket' ),
            'manage_options',
            'search-history',
            [ $this, 'render_admin_page' ]
        );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_head', function() {
            if ( ( $_GET['page'] ?? '' ) !== 'search-history' ) return;
            echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
        });
    }

    public function enqueue_admin_assets( string $hook = '' ): void {
        if ( ( $_GET['page'] ?? '' ) !== 'search-history' ) return;
        // Chart.js
        add_action( 'admin_head', function() {
            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>' . "\n";
        }, 1 );
        // Shared CSS kit
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetersiz yetki.' );

        $stats         = $this->get_stats();
        $all_rows      = $this->get_all( 'rank', 'DESC' );
        $chart_7       = $this->get_chart_data( 7 );
        $chart_30      = $this->get_chart_data( 30 );
        $chart_90      = $this->get_chart_data( 90 );
        $chart_365     = $this->get_chart_data( 365 );
        $trending      = $this->get_trending_terms( 7, 50 );
        $no_res_terms  = $this->get_no_results_terms( 10 );
        $top_searched  = $this->get_top_searched();
        $last_searched = $this->get_last_searched();
        $blacklist     = $this->get_blacklist();
        $total_clicks  = $this->get_total_clicks();
        $top_clicked   = $this->get_top_clicked( 50 );
        $top_clicked_1 = $top_clicked[0] ?? null;
        $nonce         = wp_create_nonce( 'sh_nonce' );
        $ajax_url      = esc_url( admin_url( 'admin-ajax.php' ) );
        $trending_names = array_column( $trending, 'name' );

        $mk = function( array $rows ): array {
            return [
                'labels' => array_column( $rows, 'date' ),
                'counts' => array_map( 'intval', array_column( $rows, 'count' ) ),
            ];
        };

        $clicks_7   = $this->get_clicks_chart_data( 7 );
        $clicks_30  = $this->get_clicks_chart_data( 30 );
        $clicks_90  = $this->get_clicks_chart_data( 90 );
        $clicks_365 = $this->get_clicks_chart_data( 365 );
        $nores_7    = $this->get_no_results_chart_data( 7 );
        $nores_30   = $this->get_no_results_chart_data( 30 );
        $nores_90   = $this->get_no_results_chart_data( 90 );
        $nores_365  = $this->get_no_results_chart_data( 365 );

        $chart_json = wp_json_encode([
            '7'   => [ 'searches' => $mk($chart_7),   'clicks' => $mk($clicks_7),   'nores' => $mk($nores_7)   ],
            '30'  => [ 'searches' => $mk($chart_30),  'clicks' => $mk($clicks_30),  'nores' => $mk($nores_30)  ],
            '90'  => [ 'searches' => $mk($chart_90),  'clicks' => $mk($clicks_90),  'nores' => $mk($nores_90)  ],
            '365' => [ 'searches' => $mk($chart_365), 'clicks' => $mk($clicks_365), 'nores' => $mk($nores_365) ],
        ]);
        ?>
        <div class="wrap sh-wrap" id="sh-admin-page">
        <style>
        body.theme-settings_page_search-history .notice,
        body.theme-settings_page_search-history .updated,
        body.theme-settings_page_search-history .error{display:none!important;}
        </style>

        <div class="sh-toolbar">
            <h1>&#128269; Search Ranks</h1>
            <span class="sh-badge sh-badge-blue">Toplam: <strong><?php echo esc_html(number_format_i18n($stats['total_searches'])); ?></strong></span>
            <?php if ($stats['no_results_count'] > 0): ?><span class="sh-badge sh-badge-red">Sonucsuz: <strong><?php echo esc_html(number_format_i18n($stats['no_results_count'])); ?></strong></span><?php endif; ?>
            <?php if ($stats['top_type']): ?><span class="sh-badge sh-badge-orange">Top: <strong><?php echo esc_html($stats['top_type']); ?></strong></span><?php endif; ?>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=sh_export_csv'), 'sh_nonce', 'nonce')); ?>" class="sh-btn sh-btn-secondary">&#8595; Export CSV</a>
            <button class="sh-btn sh-btn-danger" id="sh-del-all" data-nonce="<?php echo esc_attr($nonce); ?>">&#128465; Delete All</button>
        </div>

        <?php
        // ── Enable Search History toggle box ─────────────────────────────────
        $sh_enabled = (bool) \SaltHareket\SearchHistory\SearchHistorySettings::getSetting('enable_search_history');
        $sh_nonce   = wp_create_nonce('sh_search_history_nonce');
        $sh_ajax    = esc_url( admin_url('admin-ajax.php') );
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
            <strong style="font-size:13px;color:#1d2327;">Enable Search History</strong>
            <label class="sh-toggle">
                <input type="checkbox" id="sh-search-history-toggle"
                       <?php checked( $sh_enabled ); ?>
                       onchange="shSearchHistoryToggle(this.checked)">
                <span class="sh-toggle-slider"></span>
            </label>
            <span id="sh-search-history-label" style="font-size:12px;color:<?php echo $sh_enabled ? '#00a32a' : '#9ca3af'; ?>">
                <?php echo $sh_enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
            <span id="sh-search-history-saving" style="display:none;font-size:12px;color:#9ca3af;margin-left:4px;">
                <span style="display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#2271b1;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:4px;"></span>Saving...
            </span>
        </div>
        <script>
        function shSearchHistoryToggle(enabled) {
            var label  = document.getElementById('sh-search-history-label');
            var saving = document.getElementById('sh-search-history-saving');
            var toggle = document.getElementById('sh-search-history-toggle');
            if (saving) saving.style.display = 'inline-flex';
            if (label)  label.style.display  = 'none';
            var fd = new FormData();
            fd.append('action',  'sh_search_history_save_toggle');
            fd.append('nonce',   <?php echo wp_json_encode( $sh_nonce ); ?>);
            fd.append('enabled', enabled ? '1' : '0');
            fetch(<?php echo wp_json_encode( $sh_ajax ); ?>, { method:'POST', body:fd, credentials:'same-origin' })
                .then(r => r.json())
                .then(function(res) {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = 'inline';
                    if (res.success) {
                        label.textContent = enabled ? 'Enabled' : 'Disabled';
                        label.style.color = enabled ? '#00a32a' : '#9ca3af';
                        if (typeof window.shShowToast === 'function') window.shShowToast(enabled ? 'Search History enabled' : 'Search History disabled', 'success');
                    } else {
                        toggle.checked = !enabled;
                        label.textContent = !enabled ? 'Enabled' : 'Disabled';
                        label.style.color = !enabled ? '#00a32a' : '#9ca3af';
                    }
                })
                .catch(function() {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = 'inline';
                    toggle.checked = !enabled;
                });
        }
        </script>

        <div class="sh-cards">
            <div class="sh-card"><h3>Toplam Arama</h3><div class="sh-card-val"><?php echo esc_html(number_format_i18n($stats['total_searches'])); ?></div><div class="sh-card-sub">Tum zamanlar</div></div>
            <div class="sh-card"><h3>Unique Terim</h3><div class="sh-card-val"><?php echo esc_html(number_format_i18n($stats['unique_terms'])); ?></div><div class="sh-card-sub">Farkli arama terimi</div></div>
            <div class="sh-card"><h3>No Results</h3><div class="sh-card-val" style="color:#dc2626"><?php echo esc_html(number_format_i18n($stats['no_results_count'])); ?></div><div class="sh-card-sub">Sonucsuz arama</div></div>
            <div class="sh-card"><h3>En Cok Aranan</h3><div class="sh-card-val" style="font-size:18px"><?php echo esc_html($top_searched['name'] ?? '-'); ?></div><div class="sh-card-sub"><?php echo $top_searched ? esc_html($top_searched['rank']).' kez arandi' : '&mdash;'; ?></div></div>
            <div class="sh-card"><h3>Son Aranan</h3><div class="sh-card-val" style="font-size:18px"><?php echo esc_html($last_searched['name'] ?? '-'); ?></div><div class="sh-card-sub"><?php echo $last_searched ? esc_html(wp_date('d M Y H:i', strtotime($last_searched['date_modified']))) : '&mdash;'; ?></div></div>
            <?php if (!empty($no_res_terms)): ?><div class="sh-card"><h3>Top No-Result</h3><div class="sh-card-val" style="font-size:18px;color:#dc2626"><?php echo esc_html($no_res_terms[0]['name'] ?? '-'); ?></div><div class="sh-card-sub"><?php echo esc_html(($no_res_terms[0]['rank'] ?? 0).' kez aranip bulunamadi'); ?></div></div><?php endif; ?>
            <div class="sh-card"><h3>Toplam Tiklama</h3><div class="sh-card-val" style="color:#7c3aed"><?php echo esc_html(number_format_i18n($total_clicks)); ?></div><div class="sh-card-sub">Autocomplete tiklama</div></div>
            <?php if ($top_clicked_1): ?><div class="sh-card"><h3>En Cok Tiklanan</h3><div class="sh-card-val" style="font-size:14px;color:#7c3aed;line-height:1.3;"><a href="<?php echo esc_attr($top_clicked_1['clicked_url']); ?>" target="_blank" style="color:inherit;text-decoration:none;"><?php echo esc_html(mb_substr($top_clicked_1['clicked_title'] ?: $top_clicked_1['clicked_url'], 0, 40)); ?></a></div><div class="sh-card-sub"><?php echo esc_html($top_clicked_1['clicks'].' tiklama'); ?></div></div><?php endif; ?>
        </div>

        <div class="sh-chart-wrap">
            <div class="sh-chart-header">
                <h2>Arama Hacmi</h2>
                <select class="sh-period-select" id="sh-period-select">
                    <option value="7">Son 7 Gun</option>
                    <option value="30" selected>Son 30 Gun</option>
                    <option value="90">Son 90 Gun</option>
                    <option value="365">Son 1 Yil</option>
                </select>
            </div>
            <canvas id="sh-chart" height="80"></canvas>
        </div>

        <div class="sh-filters">
            <input type="text" id="sh-q" placeholder="Terim ara..." />
            <select id="sh-type-f"><option value="">Tum tipler</option><?php foreach (array_unique(array_column($all_rows, 'type')) as $t): ?><option value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></option><?php endforeach; ?></select>
            <select id="sh-nr-f"><option value="">Tum sonuclar</option><option value="0">Sonuclu</option><option value="1">Sonucsuz</option></select>
            <span id="sh-cnt" style="font-size:12px;color:#6b7280;margin-left:auto;"></span>
        </div>

        <div class="sh-table-wrap">
            <table class="sh-table" id="sh-tbl">
                <thead><tr>
                    <th data-col="name">Terim<span class="si"></span></th>
                    <th data-col="type">Tip<span class="si"></span></th>
                    <th data-col="lang">Dil<span class="si"></span></th>
                    <th data-col="rank" class="sd">Rank<span class="si"></span></th>
                    <th data-col="no_results">No Results<span class="si"></span></th>
                    <th data-col="click_count">Tiklama<span class="si"></span></th>
                    <th>Son Tiklanan</th>
                    <th data-col="date">Ilk Gorulme<span class="si"></span></th>
                    <th data-col="date_modified">Son Arama<span class="si"></span></th>
                    <th>Islem</th>
                </tr></thead>
                <tbody id="sh-tbody">
                <?php foreach ($all_rows as $row):
                    $is_trend = in_array($row->name, $trending_names, true) && (int)$row->rank > 5;
                    $nr = (int)$row->no_results;
                ?>
                <tr data-name="<?php echo esc_attr($row->name); ?>" data-type="<?php echo esc_attr($row->type); ?>" data-rank="<?php echo esc_attr($row->rank); ?>" data-no-results="<?php echo esc_attr($nr); ?>" data-date="<?php echo esc_attr($row->date); ?>" data-date-modified="<?php echo esc_attr($row->date_modified); ?>">
                    <td class="sh-term"><?php echo esc_html(urldecode($row->name)); ?><?php if ($is_trend): ?><span class="sh-trend" title="Son 7 gunde trend">&#128293;</span><?php endif; ?></td>
                    <td><span class="sh-type"><?php echo esc_html($row->type); ?></span></td>
                    <td><?php if (!empty($row->lang)): ?><span style="font-size:11px;background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:10px;font-weight:600;"><?php echo esc_html(strtoupper($row->lang)); ?></span><?php else: ?><span style="color:#d1d5db;">&mdash;</span><?php endif; ?></td>
                    <td class="sh-rank"><?php echo esc_html($row->rank); ?></td>
                    <td><?php if ($nr): ?><span class="sh-nr-badge">Sonucsuz</span><?php else: ?><span style="color:#15803d">&#10003;</span><?php endif; ?></td>
                    <td style="color:#7c3aed;font-weight:700;"><?php echo esc_html((int)($row->click_count ?? 0) ?: '&mdash;'); ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php if (!empty($row->last_clicked_url)): ?><a href="<?php echo esc_attr($row->last_clicked_url); ?>" target="_blank" style="color:#7c3aed;text-decoration:none;font-size:12px;" title="<?php echo esc_attr($row->last_clicked_url); ?>"><?php echo esc_html(mb_substr($row->last_clicked_title ?: $row->last_clicked_url, 0, 40)); ?> &#8599;</a><?php else: ?><span style="color:#d1d5db;">&mdash;</span><?php endif; ?></td>
                    <td><?php echo esc_html(wp_date('d M Y', strtotime($row->date))); ?></td>
                    <td><?php echo esc_html(wp_date('d M Y', strtotime($row->date_modified))); ?></td>
                    <td><button class="sh-del-btn" data-id="<?php echo esc_attr($row->id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" title="Sil">&#128465;</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="sh-pag" id="sh-pag"></div>
        </div>

        <?php if (!empty($top_clicked)): ?>
        <div class="sh-table-wrap">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#128432; Tiklanan Sonuclar <span style="font-size:12px;color:#6b7280;font-weight:400;">(Autocomplete)</span></h2>
                <button class="sh-btn sh-btn-secondary" id="sh-del-clicks" data-nonce="<?php echo esc_attr($nonce); ?>" style="font-size:12px;padding:4px 10px;">Temizle</button>
            </div>
            <table class="sh-table">
                <thead><tr><th>Arama Terimi</th><th>Tiklanan Sayfa</th><th>Tip</th><th>Tiklama</th><th>Son Tiklama</th></tr></thead>
                <tbody>
                <?php foreach ($top_clicked as $click): ?>
                <tr>
                    <td style="font-size:12px;color:#6b7280;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($click['terms']); ?>"><?php echo esc_html(mb_substr($click['terms'], 0, 60)); ?></td>
                    <td style="max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="<?php echo esc_attr($click['clicked_url']); ?>" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:500;"><?php echo esc_html($click['clicked_title'] ?: $click['clicked_url']); ?></a></td>
                    <td><span class="sh-type"><?php echo esc_html($click['clicked_type']); ?></span></td>
                    <td><strong style="color:#7c3aed;"><?php echo esc_html($click['clicks']); ?></strong></td>
                    <td style="font-size:12px;color:#9ca3af;"><?php echo ! empty( $click['last_click'] ) ? esc_html( wp_date( 'd M Y', strtotime( $click['last_click'] ) ) ) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="sh-bl-wrap">
            <p class="sh-bl-title">Kara Liste (Blacklist)</p>
            <ul class="sh-bl-list" id="sh-bl-list">
                <?php foreach ($blacklist as $item): ?><li><span><?php echo esc_html($item['term']); ?></span><button class="sh-bl-rm" data-id="<?php echo esc_attr($item['id']); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('sh_blacklist_nonce')); ?>">&#215;</button></li><?php endforeach; ?>
                <?php if (empty($blacklist)): ?><li style="color:#9ca3af;background:none;padding:0">Kara liste bos.</li><?php endif; ?>
            </ul>
            <div class="sh-bl-add">
                <input type="text" id="sh-bl-input" placeholder="Yeni terim ekle..." />
                <button class="sh-btn sh-btn-secondary" id="sh-bl-add" data-nonce="<?php echo esc_attr(wp_create_nonce('sh_blacklist_nonce')); ?>">Ekle</button>
            </div>
            <p style="font-size:12px;color:#9ca3af;margin-top:8px">Kara listedeki terimler kaydedilmez.</p>
        </div>

        <div id="sh-toast"></div>
        </div>
        <?php
        $this->_render_sh_js($nonce, $ajax_url, $chart_json);
    }

    private function _render_sh_js(string $nonce, string $ajax_url, string $chart_json): void {
        ?>
        <script>
        (function(){
            'use strict';
            var AJAX=<?php echo wp_json_encode($ajax_url); ?>;
            var NONCE=<?php echo wp_json_encode($nonce); ?>;
            var CPD=<?php echo $chart_json; ?>;
            var shChart=null;

            function toast(m,e){var el=document.getElementById('sh-toast');if(!el)return;el.textContent=m;el.style.background=e?'#dc2626':'#1f2937';el.classList.add('show');setTimeout(function(){el.classList.remove('show');},3000);}
            function ajax(a,d,cb){d.action=a;var p=new URLSearchParams(d);fetch(AJAX,{method:'POST',body:p}).then(function(r){return r.json();}).then(function(res){cb(res);}).catch(function(){toast('Baglanti hatasi.',true);});}

            function buildChart(period){
                if(typeof Chart==='undefined'){setTimeout(function(){buildChart(period);},100);return;}
                var d=CPD[period]||CPD['30'];
                var ctx=document.getElementById('sh-chart');
                if(!ctx)return;
                if(shChart){shChart.destroy();}
                shChart=new Chart(ctx,{
                    type:'line',
                    data:{
                        labels:d.searches.labels,
                        datasets:[
                            {
                                label:'Arama',
                                data:d.searches.counts,
                                borderColor:'#2563eb',
                                backgroundColor:'rgba(37,99,235,.08)',
                                borderWidth:2,pointRadius:3,fill:true,tension:0.4,order:3
                            },
                            {
                                label:'Tiklama',
                                data:d.clicks.counts,
                                borderColor:'#7c3aed',
                                backgroundColor:'rgba(124,58,237,.06)',
                                borderWidth:2,pointRadius:3,fill:true,tension:0.4,order:2
                            },
                            {
                                label:'Sonucsuz',
                                data:d.nores.counts,
                                borderColor:'#dc2626',
                                backgroundColor:'transparent',
                                borderWidth:1.5,
                                borderDash:[4,4],
                                pointRadius:2,fill:false,tension:0.4,order:1
                            }
                        ]
                    },
                    options:{
                        responsive:true,
                        interaction:{mode:'index',intersect:false},
                        plugins:{
                            legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11},usePointStyle:true}},
                            tooltip:{callbacks:{label:function(c){return' '+c.dataset.label+': '+c.parsed.y;}}}
                        },
                        scales:{
                            x:{grid:{display:false},ticks:{maxTicksLimit:12}},
                            y:{beginAtZero:true,ticks:{precision:0}}
                        }
                    }
                });
            }

            var ps=document.getElementById('sh-period-select');
            if(ps)ps.addEventListener('change',function(){buildChart(this.value);});
            buildChart('30');

            var tbody=document.getElementById('sh-tbody');
            var allRows=Array.from(tbody?tbody.querySelectorAll('tr'):[]);
            var filtered=allRows.slice();
            var sortCol='rank',sortDir='desc',PAGE=50,page=1;

            document.querySelectorAll('#sh-tbl th[data-col]').forEach(function(th){
                th.addEventListener('click',function(){
                    var col=this.getAttribute('data-col');
                    sortDir=(sortCol===col)?(sortDir==='asc'?'desc':'asc'):'desc';
                    sortCol=col;
                    document.querySelectorAll('#sh-tbl th').forEach(function(t){t.classList.remove('sa','sd');});
                    this.classList.add(sortDir==='asc'?'sa':'sd');
                    go();
                });
            });

            function val(r,c){return r.getAttribute('data-'+c.replace(/_/g,'-'))||'';}
            function sortRows(rows){return rows.slice().sort(function(a,b){var av=val(a,sortCol),bv=val(b,sortCol);var na=parseFloat(av),nb=parseFloat(bv);var cmp=(!isNaN(na)&&!isNaN(nb))?na-nb:av.localeCompare(bv,undefined,{sensitivity:'base'});return sortDir==='asc'?cmp:-cmp;});}

            var qEl=document.getElementById('sh-q');
            var tEl=document.getElementById('sh-type-f');
            var nEl=document.getElementById('sh-nr-f');

            function go(){
                var q=qEl?qEl.value.toLowerCase().trim():'';
                var t=tEl?tEl.value:'';
                var n=nEl?nEl.value:'';
                filtered=allRows.filter(function(r){
                    if(q&&(r.getAttribute('data-name')||'').toLowerCase().indexOf(q)===-1)return false;
                    if(t&&(r.getAttribute('data-type')||'')!==t)return false;
                    if(n!==''&&(r.getAttribute('data-no-results')||'')!==n)return false;
                    return true;
                });
                filtered=sortRows(filtered);
                page=1;
                render();
            }

            if(qEl)qEl.addEventListener('input',go);
            if(tEl)tEl.addEventListener('change',go);
            if(nEl)nEl.addEventListener('change',go);

            function render(){
                var start=(page-1)*PAGE;
                allRows.forEach(function(r){r.classList.add('sh-hidden');});
                filtered.slice(start,start+PAGE).forEach(function(r){r.classList.remove('sh-hidden');});
                var cnt=document.getElementById('sh-cnt');
                if(cnt)cnt.textContent=filtered.length+' kayit';
                renderPag();
            }

            function renderPag(){
                var pag=document.getElementById('sh-pag');
                if(!pag)return;
                var total=Math.ceil(filtered.length/PAGE);
                pag.innerHTML='';
                if(total<=1)return;
                for(var i=1;i<=total;i++){
                    (function(p){
                        var btn=document.createElement('button');
                        btn.className='sh-pg-btn'+(p===page?' active':'');
                        btn.textContent=p;
                        btn.addEventListener('click',function(){page=p;render();});
                        pag.appendChild(btn);
                    })(i);
                }
            }

            go();

            // Delete row
            tbody&&tbody.addEventListener('click',function(e){
                var btn=e.target.closest('.sh-del-btn');
                if(!btn)return;
                if(!confirm('Bu kaydi silmek istediginizden emin misiniz?'))return;
                ajax('sh_delete_term',{id:btn.getAttribute('data-id'),nonce:btn.getAttribute('data-nonce')},function(res){
                    if(res.success){
                        var row=btn.closest('tr');
                        if(row){allRows=allRows.filter(function(r){return r!==row;});row.remove();}
                        go();
                        toast(res.data.message||'Silindi.');
                    }else{toast((res.data&&res.data.message)||'Hata.',true);}
                });
            });

            // Delete all
            var delAll=document.getElementById('sh-del-all');
            if(delAll){
                delAll.addEventListener('click',function(){
                    if(!confirm('TUM kayitlari silmek istediginizden emin misiniz?'))return;
                    ajax('sh_delete_all',{nonce:this.getAttribute('data-nonce')},function(res){
                        if(res.success){
                            allRows=[];filtered=[];
                            if(tbody)tbody.innerHTML='<tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:24px">Kayit bulunamadi.</td></tr>';
                            render();
                            toast(res.data.message||'Tum kayitlar silindi.');
                        }else{toast((res.data&&res.data.message)||'Hata.',true);}
                    });
                });
            }

            // Delete clicks
            var delClicks=document.getElementById('sh-del-clicks');
            if(delClicks){
                delClicks.addEventListener('click',function(){
                    if(!confirm('Tum tiklama kayitlarini silmek istediginizden emin misiniz?'))return;
                    ajax('sh_delete_clicks',{nonce:this.getAttribute('data-nonce')},function(res){
                        if(res.success){
                            var tbl=document.getElementById('sh-clicks-tbl');
                            if(tbl){var tb=tbl.querySelector('tbody');if(tb)tb.innerHTML='<tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:24px">Kayit bulunamadi.</td></tr>';}
                            toast(res.data.message||'Tiklama kayitlari silindi.');
                        }else{toast((res.data&&res.data.message)||'Hata.',true);}
                    });
                });
            }

            // Blacklist remove
            var blList=document.getElementById('sh-bl-list');
            if(blList){
                blList.addEventListener('click',function(e){
                    var btn=e.target.closest('.sh-bl-rm');
                    if(!btn)return;
                    ajax('sh_blacklist_remove',{id:btn.getAttribute('data-id'),nonce:btn.getAttribute('data-nonce')},function(res){
                        if(res.success){var li=btn.closest('li');if(li)li.remove();toast('Kaldirildi.');}
                        else toast((res.data&&res.data.message)||'Hata.',true);
                    });
                });
            }

            // Blacklist add
            var blAdd=document.getElementById('sh-bl-add');
            if(blAdd){
                blAdd.addEventListener('click',function(){
                    var inp=document.getElementById('sh-bl-input');
                    var term=inp?inp.value.trim():'';
                    if(!term)return;
                    var n=this.getAttribute('data-nonce');
                    ajax('sh_blacklist_add',{term:term,nonce:n},function(res){
                        if(res.success){
                            if(inp)inp.value='';
                            var li=document.createElement('li');
                            li.innerHTML='<span>'+term+'</span><button class="sh-bl-rm" data-id="'+(res.data.id||'')+'" data-nonce="'+n+'">&#215;</button>';
                            var empty=blList?blList.querySelector('li[style]'):null;
                            if(empty)empty.remove();
                            if(blList)blList.appendChild(li);
                            toast('Eklendi.');
                        }else toast((res.data&&res.data.message)||'Hata.',true);
                    });
                });
            }

        })();
        </script>
        <?php
    }
}

