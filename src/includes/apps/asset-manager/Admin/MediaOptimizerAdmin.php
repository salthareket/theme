<?php

namespace SaltHareket\AssetManager\Admin;

use SaltHareket\AssetManager\MediaOptimizer;

/**
 * MediaOptimizerAdmin
 *
 * @version 1.1.0
 */
class MediaOptimizerAdmin
{
    public static function registerAjax(): void
    {
        add_action( 'wp_ajax_sh_mo_scan',           [ self::class, 'ajaxScan' ] );
        add_action( 'wp_ajax_sh_mo_enqueue',        [ self::class, 'ajaxEnqueue' ] );
        add_action( 'wp_ajax_sh_mo_process',        [ self::class, 'ajaxProcess' ] );
        add_action( 'wp_ajax_sh_mo_convert_single', [ self::class, 'ajaxConvertSingle' ] );
        add_action( 'wp_ajax_sh_mo_status',         [ self::class, 'ajaxStatus' ] );
        add_action( 'wp_ajax_sh_mo_reset_stats',    [ self::class, 'ajaxResetStats' ] );
        add_action( 'wp_ajax_sh_mo_clear_queue',    [ self::class, 'ajaxClearQueue' ] );
    }

    public static function ajaxScan(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $per_page = max( 5, min( 9999, (int) ( $_POST['per_page'] ?? MediaOptimizer::PER_PAGE ) ) );
        $result   = MediaOptimizer::scan( $page, $per_page );
        ob_start();
        self::renderTableRows( $result['items'] );
        $html = ob_get_clean();
        wp_send_json_success( array_merge( $result, [ 'html' => $html ] ) );
    }

    public static function ajaxEnqueue(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $ids   = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( empty( $ids ) ) wp_send_json_error( 'No IDs' );
        MediaOptimizer::enqueue( $ids, $email );
        wp_send_json_success( [ 'queued' => count( $ids ) ] );
    }

    public static function ajaxProcess(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        wp_send_json_success( MediaOptimizer::processNext( 3 ) );
    }

    public static function ajaxConvertSingle(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'No ID' );
        wp_send_json_success( MediaOptimizer::convertSingle( $id ) );
    }

    public static function ajaxStatus(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        wp_send_json_success( [ 'stats' => MediaOptimizer::getSessionStats() ] );
    }

    public static function ajaxResetStats(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        MediaOptimizer::resetStats();
        wp_send_json_success();
    }

    public static function ajaxClearQueue(): void
    {
        check_ajax_referer( 'sh_mo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        MediaOptimizer::clearQueue();
        wp_send_json_success();
    }

    public static function renderTab( string $nonce, string $ajax_url ): void
    {
        $supported   = MediaOptimizer::isSupported();
        $stats       = MediaOptimizer::getSessionStats();
        $total_count = MediaOptimizer::countUnoptimized();
        ?>
        <div class="sh-layout" id="sh-mo-wrap">
        <div class="sh-main">

        <?php if ( ! $supported ) : ?>
        <div class="sh-notice sh-notice-error sh-inline" style="margin-bottom:20px">
            ⚠️ Your server does not support AVIF or WebP conversion.
        </div>
        <?php endif; ?>

        <!-- Stats Bar -->
        <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
            <div class="sh-card" style="flex:1;min-width:140px;padding:16px 20px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:var(--ts-primary)" id="sh-mo-stat-count"><?php echo number_format_i18n( $stats['converted_count'] ); ?></div>
                <div style="font-size:12px;color:#9ca3af;margin-top:4px">Images Optimized</div>
            </div>
            <div class="sh-card" style="flex:1;min-width:140px;padding:16px 20px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#00a32a" id="sh-mo-stat-saved"><?php echo $stats['saved_bytes'] > 0 ? size_format( $stats['saved_bytes'], 1 ) : '0 B'; ?></div>
                <div style="font-size:12px;color:#9ca3af;margin-top:4px">Space Saved</div>
            </div>
            <div class="sh-card" style="flex:1;min-width:140px;padding:16px 20px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#f59e0b" id="sh-mo-stat-unopt"><?php echo number_format_i18n( $total_count ); ?></div>
                <div style="font-size:12px;color:#9ca3af;margin-top:4px">Unoptimized</div>
            </div>
            <div class="sh-card" style="flex:1;min-width:140px;padding:16px 20px;text-align:center">
                <div style="font-size:28px;font-weight:700;color:#8b5cf6" id="sh-mo-stat-sessions"><?php echo (int) ( $stats['sessions'] ?? 0 ); ?></div>
                <div style="font-size:12px;color:#9ca3af;margin-top:4px">Sessions Run</div>
            </div>
        </div>

        <!-- Progress Bar — sadece Convert All için, kapalı başlar -->
        <div id="sh-mo-progress-wrap" style="display:none;margin-bottom:20px">
            <div class="sh-card" style="padding:16px 20px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                    <strong style="font-size:13px">⚙️ Optimizing in background...</strong>
                    <span id="sh-mo-progress-text" style="font-size:12px;color:#6b7280">0 / 0</span>
                </div>
                <div style="background:#f3f4f6;border-radius:999px;height:8px;overflow:hidden">
                    <div id="sh-mo-progress-bar" style="height:100%;background:linear-gradient(90deg,#2271b1,#00a32a);border-radius:999px;width:0%;transition:width .4s"></div>
                </div>
                <div style="margin-top:10px">
                    <button type="button" class="sh-btn sh-btn-danger sh-btn-sm" id="sh-mo-stop-btn">⏹ Stop</button>
                </div>
            </div>
        </div>

        <!-- Toolbar: sol = Find + Select All, sağ = Convert Selected + Convert All + badge -->
        <div class="sh-filter-bar" style="margin-bottom:16px;justify-content:space-between">
            <div style="display:flex;gap:8px;align-items:center">
                <button type="button" class="sh-btn sh-btn-primary" id="sh-mo-find-btn" <?php echo $supported ? '' : 'disabled'; ?>>
                    🔍 Find Unoptimized
                </button>
                <button type="button" class="sh-btn sh-btn-secondary" id="sh-mo-select-all-btn" style="display:none">
                    ☐ Select All
                </button>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <button type="button" class="sh-btn sh-btn-primary" id="sh-mo-convert-selected-btn" style="display:none" <?php echo $supported ? '' : 'disabled'; ?>>
                    ⚡ Convert Selected
                </button>
                <button type="button" class="sh-btn sh-btn-secondary" id="sh-mo-convert-all-btn" style="display:none" <?php echo $supported ? '' : 'disabled'; ?>>
                    ⚡ Convert All
                </button>
                <span id="sh-mo-found-badge" style="font-size:12px;color:#6b7280;display:none"></span>
            </div>
        </div>

        <!-- Table -->
        <div id="sh-mo-table-wrap" style="display:none">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                            <th style="padding:10px 14px;width:36px"><input type="checkbox" id="sh-mo-check-all"></th>
                            <th style="padding:10px 14px;width:50px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Thumb</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Filename</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Type</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Size → Est.</th>
                            <th style="padding:10px 14px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Convert</th>
                        </tr>
                    </thead>
                    <tbody id="sh-mo-tbody"></tbody>
                </table>
            </div>
            <div id="sh-mo-pagination" style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;font-size:13px">
                <span id="sh-mo-page-info" style="color:#6b7280"></span>
                <div style="display:flex;gap:8px">
                    <button type="button" class="sh-btn sh-btn-secondary sh-btn-sm" id="sh-mo-prev-btn" disabled>← Prev</button>
                    <button type="button" class="sh-btn sh-btn-secondary sh-btn-sm" id="sh-mo-next-btn" disabled>Next →</button>
                </div>
            </div>
        </div>

        <!-- Email on complete -->
        <div style="margin-top:20px;display:none;align-items:center;gap:12px" id="sh-mo-email-wrap">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="checkbox" id="sh-mo-email-toggle">
                <span>Email me on complete</span>
            </label>
            <input type="email" id="sh-mo-email-input" placeholder="<?php echo esc_attr( get_option('admin_email') ); ?>"
                   style="display:none;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;min-width:240px">
        </div>

        </div><!-- .sh-main -->

        <!-- Sidebar -->
        <div class="sh-sidebar">
            <div class="sh-card">
                <div class="sh-card-header"><h3 style="margin:0;font-size:13px">How it works</h3></div>
                <div class="sh-card-body" style="padding:14px;font-size:12px;color:#6b7280;line-height:1.8">
                    <p style="margin:0 0 8px"><strong style="color:#374151">1. Find</strong><br>Scans for JPG/PNG/GIF/BMP files.</p>
                    <p style="margin:0 0 8px"><strong style="color:#374151">2. Convert Selected</strong><br>Directly converts selected images. Instant feedback per row.</p>
                    <p style="margin:0 0 8px"><strong style="color:#374151">3. Convert All</strong><br>Queues everything, runs via WP Cron in background.</p>
                    <p style="margin:0"><strong style="color:#374151">Format logic</strong><br>🖼️ With alpha → <strong>WebP</strong><br>📸 Without alpha → <strong>AVIF</strong></p>
                </div>
            </div>
            <div class="sh-card" style="margin-top:12px">
                <div class="sh-card-header"><h3 style="margin:0;font-size:13px">Server Support</h3></div>
                <div class="sh-card-body" style="padding:14px;font-size:12px">
                    <?php
                    $gd_avif = function_exists('imageavif');
                    $gd_webp = function_exists('imagewebp');
                    $im_avif = $im_webp = false;
                    if ( class_exists('Imagick') ) {
                        try { $f = \Imagick::queryFormats(); $im_avif = in_array('AVIF',$f,true); $im_webp = in_array('WEBP',$f,true); } catch(\Exception $e){}
                    }
                    foreach ( [['GD AVIF',$gd_avif],['GD WebP',$gd_webp],['Imagick AVIF',$im_avif],['Imagick WebP',$im_webp]] as [$label,$ok] ) : ?>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span><?php echo esc_html($label); ?></span>
                        <span style="color:<?php echo $ok?'#00a32a':'#d63638'; ?>;font-weight:600"><?php echo $ok?'✓':'✗'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sh-card" style="margin-top:12px">
                <div class="sh-card-header"><h3 style="margin:0;font-size:13px">Stats</h3></div>
                <div class="sh-card-body" style="padding:14px">
                    <button type="button" class="sh-btn sh-btn-danger sh-btn-sm" id="sh-mo-reset-stats-btn" style="width:100%;margin-bottom:8px">↺ Reset Stats</button>
                    <p style="font-size:11px;color:#9ca3af;margin:0">Clears counters. History kept.</p>
                </div>
            </div>
        </div>
        </div><!-- .sh-layout -->
        <?php
        self::renderScript( $ajax_url );
    }

    private static function renderTableRows( array $items ): void
    {
        foreach ( $items as $item ) :
            $ext        = strtoupper( pathinfo( $item['filename'], PATHINFO_EXTENSION ) );
            $ext_color  = $ext === 'PNG' ? '#6366f1' : ( in_array( $ext, ['JPG','JPEG'] ) ? '#e11d48' : '#f59e0b' );
            $target     = $item['target_fmt']; // 'AVIF' or 'WebP'
            $ratio      = $target === 'WebP' ? 0.35 : 0.45;
            $est_size   = $item['size'] > 0 ? size_format( (int) ( $item['size'] * $ratio ), 1 ) : '—';
            ?>
            <tr class="sh-mo-row" data-id="<?php echo (int)$item['id']; ?>" style="border-bottom:1px solid #f9fafb;transition:opacity .2s,background .2s">
                <td style="padding:10px 14px"><input type="checkbox" class="sh-mo-cb" value="<?php echo (int)$item['id']; ?>"></td>
                <td style="padding:8px 14px">
                    <?php if ( $item['thumb'] ) : ?>
                    <img src="<?php echo esc_url($item['thumb']); ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #e5e7eb" alt="">
                    <?php else : ?>
                    <div style="width:36px;height:36px;background:#f3f4f6;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:16px">🖼️</div>
                    <?php endif; ?>
                </td>
                <td style="padding:10px 14px">
                    <div style="font-size:13px;font-weight:500;color:#374151;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo esc_attr($item['filename']); ?>"><?php echo esc_html($item['filename']); ?></div>
                    <div style="font-size:11px;color:#9ca3af"><?php echo esc_html($item['date']); ?></div>
                </td>
                <td style="padding:10px 14px">
                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo esc_attr($ext_color); ?>22;color:<?php echo esc_attr($ext_color); ?>"><?php echo esc_html($ext); ?></span>
                </td>
                <td style="padding:10px 14px;font-size:12px;color:#6b7280">
                    <?php echo esc_html($item['size_human']); ?>
                    <div style="font-size:11px;color:#9ca3af">~<?php echo esc_html($est_size); ?></div>
                </td>
                <td style="padding:10px 14px;text-align:right">
                    <button type="button"
                        class="sh-btn sh-btn-primary sh-btn-sm sh-mo-single-btn"
                        data-id="<?php echo (int)$item['id']; ?>"
                        title="Convert to <?php echo esc_attr($target); ?>">
                        → <?php echo esc_html($target); ?>
                    </button>
                </td>
            </tr>
            <?php
        endforeach;
    }

    private static function renderScript( string $ajax_url ): void
    {
        $nonce = wp_create_nonce( 'sh_mo_nonce' );
        ?>
        <style>
        @keyframes sh-spin { to { transform: rotate(360deg); } }
        .sh-mo-spinner {
            display: inline-block;
            width: 13px; height: 13px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: sh-spin .6s linear infinite;
            vertical-align: middle;
        }
        .sh-mo-row.sh-processing { opacity: .5; pointer-events: none; }
        .sh-mo-row.sh-done { opacity: .35; pointer-events: none; background: #f0fdf4; }
        .sh-mo-row.sh-error { background: #fef2f2; }
        .sh-mo-single-btn:hover { filter: brightness(.9); }
        </style>
        <script>
        (function(){
        var MO = {
            nonce: <?php echo wp_json_encode($nonce); ?>,
            ajax:  <?php echo wp_json_encode($ajax_url); ?>,
            page: 1, pages: 1, total: 0,
            converting: false, polling: null
        };

        function ajax(action, data, cb) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', MO.nonce);
            if (data) Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
            fetch(MO.ajax, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();}).then(cb).catch(console.error);
        }

        function formatBytes(b) {
            b = parseInt(b)||0;
            if(b<1024) return b+' B';
            if(b<1048576) return (b/1024).toFixed(1)+' KB';
            return (b/1048576).toFixed(2)+' MB';
        }

        // ── Stats ──
        function bumpStat(savedBytes) {
            var el = document.getElementById('sh-mo-stat-count');
            el.textContent = (parseInt(el.textContent)||0)+1;
            var u = document.getElementById('sh-mo-stat-unopt');
            var n = parseInt(u.textContent)||0; if(n>0) u.textContent = n-1;
            if(savedBytes>0) document.getElementById('sh-mo-stat-saved').textContent = formatBytes(savedBytes);
        }

        // ── Row feedback ──
        function rowProcessing(id) {
            var r = document.querySelector('tr.sh-mo-row[data-id="'+id+'"]');
            if(!r) return;
            r.classList.add('sh-processing');
            var cb=r.querySelector('.sh-mo-cb'); if(cb){cb.checked=false;cb.disabled=true;}
            var b=r.querySelector('.sh-mo-single-btn');
            if(b){b.disabled=true;b.innerHTML='<span class="sh-mo-spinner"></span>';}
        }
        function rowDone(id) {
            var r = document.querySelector('tr.sh-mo-row[data-id="'+id+'"]');
            if(!r) return;
            r.classList.remove('sh-processing');
            r.classList.add('sh-done');
            var b=r.querySelector('.sh-mo-single-btn');
            if(b){b.textContent='✓';b.disabled=true;b.style.color='#00a32a';}
        }
        function rowError(id) {
            var r = document.querySelector('tr.sh-mo-row[data-id="'+id+'"]');
            if(!r) return;
            r.classList.remove('sh-processing');
            r.classList.add('sh-error');
            var b=r.querySelector('.sh-mo-single-btn');
            if(b){b.textContent='✗ Retry';b.disabled=false;b.style.color='#dc2626';}
        }

        // ── Progress (Convert All only) ──
        function progressShow(done, total) {
            var wrap=document.getElementById('sh-mo-progress-wrap');
            var bar=document.getElementById('sh-mo-progress-bar');
            var txt=document.getElementById('sh-mo-progress-text');
            wrap.style.display='';
            var pct=total>0?Math.round(done/total*100):0;
            bar.style.width=pct+'%';
            txt.textContent=done+' / '+total;
        }
        function progressHide(){document.getElementById('sh-mo-progress-wrap').style.display='none';}

        function toast(msg, isErr) {
            var el=document.createElement('div');
            el.textContent=msg;
            el.style.cssText='position:fixed;bottom:24px;right:24px;padding:10px 18px;border-radius:6px;font-size:13px;font-weight:500;z-index:99999;color:#fff;background:'+(isErr?'#d63638':'#00a32a');
            document.body.appendChild(el);
            setTimeout(function(){el.remove();},3500);
        }

        // ── Convert Selected: sıralı direkt AJAX ──
        function convertIds(ids) {
            if(!ids.length) return;
            MO.converting=true;
            var selBtn=document.getElementById('sh-mo-convert-selected-btn');
            var allBtn=document.getElementById('sh-mo-convert-all-btn');
            if(selBtn){selBtn.disabled=true; selBtn.textContent='⏳ Converting...';}
            if(allBtn) allBtn.disabled=true;
            document.querySelectorAll('.sh-mo-cb').forEach(function(cb){cb.disabled=true;});

            var total=ids.length, done=0, failed=0;
            var queue=ids.slice();

            function next() {
                if(!queue.length) {
                    MO.converting=false;
                    document.querySelectorAll('.sh-mo-cb').forEach(function(cb){cb.disabled=false;});
                    if(allBtn) allBtn.disabled=false;
                    if(selBtn){selBtn.style.display='none';}
                    toggleSelectedBtn();
                    var msg=done+' image'+(done!==1?'s':'')+' converted'+(failed>0?', '+failed+' failed':'');
                    toast((failed>0?'⚠️ ':'✓ ')+msg, failed>0);
                    return;
                }
                var id=queue.shift();
                rowProcessing(id);
                ajax('sh_mo_convert_single',{id:id},function(res){
                    if(res&&res.success){rowDone(id);bumpStat((res.data&&res.data.saved_bytes)||0);done++;}
                    else{rowError(id);failed++;}
                    next();
                });
            }
            next();
        }

        // ── Convert All: queue + cron + progress ──
        function convertAll() {
            ajax('sh_mo_scan',{page:1,per_page:9999},function(res){
                if(!res.success) return;
                var ids=(res.data.items||[]).map(function(i){return i.id;});
                if(!ids.length){toast('Nothing to convert');return;}
                var email='';
                if(document.getElementById('sh-mo-email-toggle').checked)
                    email=document.getElementById('sh-mo-email-input').value.trim();

                var fd=new FormData();
                fd.append('action','sh_mo_enqueue');fd.append('nonce',MO.nonce);
                ids.forEach(function(id){fd.append('ids[]',id);});
                fd.append('email',email);
                fetch(MO.ajax,{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(r){
                        if(!r.success){toast('Queue failed',true);return;}
                        MO.total=ids.length;
                        progressShow(0,MO.total);
                        startPolling();
                    });
            });
        }

        function startPolling() {
            if(MO.polling) return;
            MO.polling=setInterval(function(){
                ajax('sh_mo_process',{},function(res){
                    if(!res.success){stopPolling();return;}
                    var done=MO.total-res.data.remaining;
                    progressShow(done,MO.total);
                    if(res.data.done){
                        stopPolling();
                        ajax('sh_mo_status',{},function(r){
                            if(r.success){
                                var s=r.data.stats;
                                document.getElementById('sh-mo-stat-count').textContent=s.converted_count||0;
                                document.getElementById('sh-mo-stat-saved').textContent=s.saved_bytes>0?formatBytes(s.saved_bytes):'0 B';
                                document.getElementById('sh-mo-stat-unopt').textContent='0';
                            }
                        });
                        toast('✓ All '+MO.total+' images optimized!');
                    }
                });
            },2500);
        }
        function stopPolling(){if(MO.polling){clearInterval(MO.polling);MO.polling=null;}progressHide();}

        // ── Find ──
        document.getElementById('sh-mo-find-btn').addEventListener('click',function(){
            var btn=this; btn.disabled=true; btn.textContent='Scanning...';
            ajax('sh_mo_scan',{page:1,per_page:20},function(res){
                btn.disabled=false; btn.textContent='🔍 Find Unoptimized';
                if(!res.success) return;
                var d=res.data; MO.page=1; MO.pages=d.pages; MO.total=d.total;
                document.getElementById('sh-mo-tbody').innerHTML=d.html;
                document.getElementById('sh-mo-table-wrap').style.display='';
                document.getElementById('sh-mo-select-all-btn').style.display='';
                document.getElementById('sh-mo-convert-all-btn').style.display='';
                document.getElementById('sh-mo-found-badge').style.display='';
                document.getElementById('sh-mo-found-badge').textContent=d.total+' unoptimized found';
                document.getElementById('sh-mo-email-wrap').style.display='flex';
                updatePagination(d); toggleSelectedBtn();
            });
        });

        // ── Select All ──
        document.getElementById('sh-mo-check-all').addEventListener('change',function(){
            document.querySelectorAll('.sh-mo-cb:not(:disabled)').forEach(function(cb){cb.checked=this.checked;}.bind(this));
            toggleSelectedBtn();
        });
        document.addEventListener('change',function(e){
            if(e.target.classList.contains('sh-mo-cb')) toggleSelectedBtn();
        });
        document.getElementById('sh-mo-select-all-btn').addEventListener('click',function(){
            var cbs=document.querySelectorAll('.sh-mo-cb:not(:disabled)');
            var allChk=Array.from(cbs).every(function(c){return c.checked;});
            cbs.forEach(function(c){c.checked=!allChk;});
            this.textContent=allChk?'☐ Select All':'☑ Deselect All';
            toggleSelectedBtn();
        });

        function toggleSelectedBtn(){
            if(MO.converting) return;
            var count=document.querySelectorAll('.sh-mo-cb:checked').length;
            var btn=document.getElementById('sh-mo-convert-selected-btn');
            if(!btn) return;
            btn.style.display=count>0?'':'none';
            btn.textContent='⚡ Convert Selected ('+count+')';
        }

        // ── Convert Selected button ──
        document.getElementById('sh-mo-convert-selected-btn').addEventListener('click',function(){
            if(MO.converting) return;
            var ids=Array.from(document.querySelectorAll('.sh-mo-cb:checked')).map(function(cb){return parseInt(cb.value);});
            if(!ids.length) return;
            convertIds(ids);
        });

        // ── Convert All button ──
        document.getElementById('sh-mo-convert-all-btn').addEventListener('click',function(){
            if(MO.converting) return;
            convertAll();
        });

        // ── Single ⚡ button ──
        document.addEventListener('click',function(e){
            if(!e.target.classList.contains('sh-mo-single-btn')) return;
            if(MO.converting) return;
            var id=parseInt(e.target.dataset.id);
            rowProcessing(id);
            ajax('sh_mo_convert_single',{id:id},function(res){
                if(res&&res.success){rowDone(id);bumpStat((res.data&&res.data.saved_bytes)||0);}
                else rowError(id);
            });
        });

        // ── Stop ──
        document.getElementById('sh-mo-stop-btn').addEventListener('click',function(){
            ajax('sh_mo_clear_queue',{},function(){stopPolling();toast('Stopped');});
        });

        // ── Email toggle ──
        document.getElementById('sh-mo-email-toggle').addEventListener('change',function(){
            document.getElementById('sh-mo-email-input').style.display=this.checked?'':'none';
        });

        // ── Pagination ──
        document.getElementById('sh-mo-prev-btn').addEventListener('click',function(){if(MO.page>1&&!MO.converting) loadPage(MO.page-1);});
        document.getElementById('sh-mo-next-btn').addEventListener('click',function(){if(MO.page<MO.pages&&!MO.converting) loadPage(MO.page+1);});

        function loadPage(p){
            ajax('sh_mo_scan',{page:p,per_page:20},function(res){
                if(!res.success) return;
                var d=res.data; MO.page=p; MO.pages=d.pages;
                document.getElementById('sh-mo-tbody').innerHTML=d.html;
                updatePagination(d); toggleSelectedBtn();
            });
        }
        function updatePagination(d){
            var pi=document.getElementById('sh-mo-page-info');
            if(pi) pi.textContent='Page '+d.page+' of '+d.pages+' ('+d.total+' images)';
            document.getElementById('sh-mo-prev-btn').disabled=d.page<=1;
            document.getElementById('sh-mo-next-btn').disabled=d.page>=d.pages;
        }

        // ── Reset Stats ──
        document.getElementById('sh-mo-reset-stats-btn').addEventListener('click',function(){
            ajax('sh_mo_reset_stats',{},function(){
                document.getElementById('sh-mo-stat-count').textContent='0';
                document.getElementById('sh-mo-stat-saved').textContent='0 B';
                toast('Stats reset');
            });
        });

        })();
        </script>
        <?php
    }
}
