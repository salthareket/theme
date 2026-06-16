<?php
namespace SaltHareket\Reactions\Admin;

use SaltHareket\Reactions\ReactionsSettings;

/**
 * ReactionsAnalytics
 * Reactions admin analytics tab — stat cards, Chart.js grafik, top content, activity feed.
 * SearchHistoryAdmin ile ayni pattern kullanilir.
 *
 * @version 1.1.0
 * @changelog
 *   1.1.0 - 2026-05-12
 *     - Fix: ajaxGetData() — buildChartJson() formatında döndürüyor (ham getChartData() yerine)
 *     - Fix: updateTopContent() — doğru data field'ları (url, subtype, last_at), allRows/filtered güncelleniyor
 *     - Fix: updateActivity() — avatar, user_name, type, title, url, created_at field'ları
 *   1.0.0 - 2026-05-08 — Initial release
 *     - Add: getStats() — bugun/hafta/ay/unique kullanici/son reaction (kim+ne+ne zaman)
 *     - Add: getTypeCounts() — type bazli reaction sayilari
 *     - Add: getChartData() — 7/30/90/365 gunluk zaman serisi, type bazli
 *     - Add: getTopContent() — en cok reaction alan icerikler, subtype dahil
 *     - Add: getTopUsers() — en aktif kullanicilar
 *     - Add: getRecentActivity() — son 40 aktivite feed
 *     - Add: buildChartJson() — tum gunleri doldurur (data olmasa 0), search-history gibi
 *     - Add: renderTab() — stat kartlar, chart, tablolar, activity feed
 *     - Add: ajaxGetData() — AJAX ile data guncelleme
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Admin'de otomatik yuklenir — ReactionsAdmin::renderPage() tarafindan cagrilir
 * // Tab: ?page=salt-reactions&tab=analytics
 *
 * // AJAX ile data guncelle:
 * wp_ajax_sh_reactions_analytics_data → ReactionsAnalytics::ajaxGetData()
 *
 * ──────────────────────────────────────────────────────────
 *
 * @example
 *   // Istatistikleri al
 *   $stats = ReactionsAnalytics::getStats();
 *   // ['total'=>284, 'today'=>12, 'week'=>84, 'last_user_name'=>'Ahmet', ...]
 *
 * @example
 *   // Type bazli sayilar
 *   $counts = ReactionsAnalytics::getTypeCounts();
 *   // ['like'=>156, 'clap'=>84, 'favorite'=>44]
 *
 * @example
 *   // En cok reaction alan icerikler
 *   $top = ReactionsAnalytics::getTopContent(10, 'like', 'post');
 *   // [['title'=>'Urun X', 'count'=>42, 'subtype'=>'product', ...], ...]
 *
 * @example
 *   // 30 gunluk chart data
 *   $chart = ReactionsAnalytics::getChartData(30);
 *   // ['like'=>['labels'=>[...], 'counts'=>[...]], '_all'=>[...]]
 *
 * @example
 *   // Son aktivite
 *   $activity = ReactionsAnalytics::getRecentActivity(20);
 *   // [['user_name'=>'Ahmet', 'type'=>'clap', 'title'=>'Urun X', 'created_at'=>'...'], ...]
 *
 * @package SaltHareket\Reactions\Admin
 */
class ReactionsAnalytics {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'reactions';
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public static function getStats(): array {
        global $wpdb;
        $t = self::table();
        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" );
        $today   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) = CURDATE()" );
        $week    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );
        $month   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" );
        $users   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$t}" );
        $yesterday = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)" );
        $today_vs_yesterday = $yesterday > 0 ? round( ( ( $today - $yesterday ) / $yesterday ) * 100 ) : ( $today > 0 ? 100 : 0 );
        $top_type_row = $wpdb->get_row( "SELECT type, COUNT(*) as cnt FROM {$t} GROUP BY type ORDER BY cnt DESC LIMIT 1" );
        $last_row     = $wpdb->get_row( "SELECT r.user_id, r.object_id, r.object_type, r.type, r.created_at FROM {$t} r ORDER BY r.created_at DESC LIMIT 1" );

        $last_user_name = '';
        $last_user_avatar = '';
        $last_obj_title = '';
        $last_type_label = '';
        if ( $last_row ) {
            $lu = get_userdata( (int) $last_row->user_id );
            $last_user_name   = $lu ? $lu->display_name : '#' . $last_row->user_id;
            $last_user_avatar = $lu ? get_avatar_url( (int) $last_row->user_id, ['size'=>20] ) : '';
            $last_type_label  = ReactionsSettings::getType( $last_row->type )['label'] ?? $last_row->type;
            if ( $last_row->object_type === 'post' ) {
                $last_obj_title = get_the_title( (int) $last_row->object_id ) ?: '#' . $last_row->object_id;
            } elseif ( $last_row->object_type === 'user' ) {
                $lu2 = get_userdata( (int) $last_row->object_id );
                $last_obj_title = $lu2 ? $lu2->display_name : '#' . $last_row->object_id;
            } elseif ( $last_row->object_type === 'term' ) {
                $term = get_term( (int) $last_row->object_id );
                $last_obj_title = ( $term && ! is_wp_error($term) ) ? $term->name : '#' . $last_row->object_id;
            } else {
                $last_obj_title = '#' . $last_row->object_id;
            }
        }

        return [
            'total'                => $total,
            'today'                => $today,
            'week'                 => $week,
            'month'                => $month,
            'unique_users'         => $users,
            'today_vs_yesterday'   => $today_vs_yesterday,
            'top_type'             => $top_type_row ? $top_type_row->type : '',
            'top_type_count'       => $top_type_row ? (int) $top_type_row->cnt : 0,
            'last_created_at'      => $last_row ? $last_row->created_at : '',
            'last_user_name'       => $last_user_name,
            'last_user_avatar'     => $last_user_avatar,
            'last_type_label'      => $last_type_label,
            'last_obj_title'       => $last_obj_title,
        ];
    }

    public static function getTypeCounts(): array {
        global $wpdb;
        $t    = self::table();
        $rows = $wpdb->get_results( "SELECT type, COUNT(*) as cnt FROM {$t} GROUP BY type ORDER BY cnt DESC" );
        $out  = [];
        foreach ( $rows as $r ) {
            $out[ $r->type ] = (int) $r->cnt;
        }
        return $out;
    }

    public static function getChartData( int $days = 30 ): array {
        global $wpdb;
        $t    = self::table();
        $types = array_keys( ReactionsSettings::getTypes() );
        $out   = [];
        foreach ( $types as $type ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) as day, COUNT(*) as cnt
                 FROM {$t}
                 WHERE type = %s AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY day ORDER BY day ASC",
                $type, $days
            ) );
            $out[ $type ] = [
                'labels' => array_column( $rows, 'day' ),
                'counts' => array_map( 'intval', array_column( $rows, 'cnt' ) ),
            ];
        }
        // All combined
        $all = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as day, COUNT(*) as cnt
             FROM {$t}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY day ORDER BY day ASC",
            $days
        ) );
        $out['_all'] = [
            'labels' => array_column( $all, 'day' ),
            'counts' => array_map( 'intval', array_column( $all, 'cnt' ) ),
        ];
        return $out;
    }

    public static function getTopContent( int $limit = 20, string $type_filter = '', string $obj_filter = '' ): array {
        global $wpdb;
        $t     = self::table();
        $where = '1=1';
        $args  = [];
        if ( $type_filter ) { $where .= ' AND type = %s'; $args[] = $type_filter; }
        if ( $obj_filter  ) { $where .= ' AND object_type = %s'; $args[] = $obj_filter; }
        $args[] = $limit;
        $sql = "SELECT object_id, object_type, type, COUNT(*) as cnt, MAX(created_at) as last_at
                FROM {$t} WHERE {$where}
                GROUP BY object_id, object_type, type
                ORDER BY cnt DESC LIMIT %d";
        $rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
        $out  = [];
        foreach ( $rows as $r ) {
            $title   = '';
            $url     = '';
            $subtype = '';
            if ( $r->object_type === 'post' ) {
                $title   = get_the_title( (int) $r->object_id ) ?: '#' . $r->object_id;
                $url     = get_permalink( (int) $r->object_id ) ?: '';
                $subtype = get_post_type( (int) $r->object_id ) ?: '';
            } elseif ( $r->object_type === 'user' ) {
                $u       = get_userdata( (int) $r->object_id );
                $title   = $u ? $u->display_name : '#' . $r->object_id;
                $url     = $u ? get_author_posts_url( (int) $r->object_id ) : '';
                $roles   = $u ? (array) $u->roles : [];
                $subtype = $roles[0] ?? '';
            } elseif ( $r->object_type === 'term' ) {
                $term    = get_term( (int) $r->object_id );
                $title   = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $r->object_id;
                $url     = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : '';
                $subtype = ( $term && ! is_wp_error( $term ) ) ? $term->taxonomy : '';
            } elseif ( $r->object_type === 'comment' ) {
                $comment = get_comment( (int) $r->object_id );
                $title   = $comment ? wp_trim_words( $comment->comment_content, 8 ) : '#' . $r->object_id;
                $subtype = $comment ? $comment->comment_type : '';
            } else {
                $title = '#' . $r->object_id;
            }
            $out[] = [
                'object_id'   => (int) $r->object_id,
                'object_type' => $r->object_type,
                'subtype'     => $subtype,
                'type'        => $r->type,
                'count'       => (int) $r->cnt,
                'last_at'     => $r->last_at,
                'title'       => $title,
                'url'         => is_string( $url ) ? $url : '',
            ];
        }
        return $out;
    }

    public static function getTopUsers( int $limit = 15 ): array {
        global $wpdb;
        $t    = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, COUNT(*) as total,
                    SUM(type='like') as likes,
                    SUM(type='favorite') as favs,
                    SUM(type='follow') as follows,
                    MIN(created_at) as first_at,
                    MAX(created_at) as last_at
             FROM {$t} GROUP BY user_id ORDER BY total DESC LIMIT %d",
            $limit
        ) );
        $out = [];
        foreach ( $rows as $r ) {
            $u = get_userdata( (int) $r->user_id );
            $out[] = [
                'user_id'  => (int) $r->user_id,
                'name'     => $u ? $u->display_name : '#' . $r->user_id,
                'avatar'   => $u ? get_avatar_url( (int) $r->user_id, [ 'size' => 28 ] ) : '',
                'profile'  => $u ? get_author_posts_url( (int) $r->user_id ) : '',
                'total'    => (int) $r->total,
                'likes'    => (int) $r->likes,
                'favs'     => (int) $r->favs,
                'follows'  => (int) $r->follows,
                'first_at' => $r->first_at,
                'last_at'  => $r->last_at,
            ];
        }
        return $out;
    }

    public static function getRecentActivity( int $limit = 40 ): array {
        global $wpdb;
        $t    = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, object_id, object_type, type, created_at
             FROM {$t} ORDER BY created_at DESC LIMIT %d",
            $limit
        ) );
        $out = [];
        foreach ( $rows as $r ) {
            $u     = get_userdata( (int) $r->user_id );
            $uname = $u ? $u->display_name : '#' . $r->user_id;
            $title = '';
            $url   = '';
            if ( $r->object_type === 'post' ) {
                $title = get_the_title( (int) $r->object_id ) ?: '#' . $r->object_id;
                $url   = get_permalink( (int) $r->object_id ) ?: '';
            } elseif ( $r->object_type === 'user' ) {
                $uu    = get_userdata( (int) $r->object_id );
                $title = $uu ? $uu->display_name : '#' . $r->object_id;
                $url   = $uu ? get_author_posts_url( (int) $r->object_id ) : '';
            } elseif ( $r->object_type === 'term' ) {
                $term  = get_term( (int) $r->object_id );
                $title = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $r->object_id;
                $url   = ( $term && ! is_wp_error( $term ) ) ? get_term_link( $term ) : '';
            } else {
                $title = '#' . $r->object_id;
            }
            $out[] = [
                'user_id'     => (int) $r->user_id,
                'user_name'   => $uname,
                'avatar'      => $u ? get_avatar_url( (int) $r->user_id, [ 'size' => 28 ] ) : '',
                'type'        => $r->type,
                'object_type' => $r->object_type,
                'object_id'   => (int) $r->object_id,
                'title'       => $title,
                'url'         => is_string( $url ) ? $url : '',
                'created_at'  => $r->created_at,
            ];
        }
        return $out;
    }

    // ── AJAX handler ─────────────────────────────────────────────────────────

    public static function ajaxGetData(): void {
        check_ajax_referer( 'sh_reactions_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $days = (int) ( $_POST['days'] ?? 30 );
        if ( ! in_array( $days, [ 7, 30, 90, 365 ], true ) ) $days = 30;

        $types          = ReactionsSettings::getTypes();
        $colors         = [ 'like'=>'#e11d48','follow'=>'#2271b1','favorite'=>'#f59e0b','bookmark'=>'#6366f1','_all'=>'#10b981' ];
        $default_colors = ['#8b5cf6','#ec4899','#14b8a6','#f97316','#64748b'];

        // buildChartJson formatında tüm period'lar için chart data
        $chart = [
            '7'   => self::buildChartJson( $types, $colors, $default_colors, 7 ),
            '30'  => self::buildChartJson( $types, $colors, $default_colors, 30 ),
            '90'  => self::buildChartJson( $types, $colors, $default_colors, 90 ),
            '365' => self::buildChartJson( $types, $colors, $default_colors, 365 ),
        ];

        wp_send_json_success( [
            'stats'    => self::getStats(),
            'types'    => self::getTypeCounts(),
            'chart'    => $chart,
            'content'  => self::getTopContent( 20, sanitize_key( $_POST['type_filter'] ?? '' ), sanitize_key( $_POST['obj_filter'] ?? '' ) ),
            'users'    => self::getTopUsers( 15 ),
            'activity' => self::getRecentActivity( 40 ),
        ] );
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public static function renderTab(): void {
        $types      = ReactionsSettings::getTypes();
        $stats      = self::getStats();
        $type_cnts  = self::getTypeCounts();
        $top_content= self::getTopContent( 20 );
        $top_users  = self::getTopUsers( 15 );
        $activity   = self::getRecentActivity( 40 );
        $nonce      = wp_create_nonce( 'sh_reactions_nonce' );
        $ajax_url   = esc_url( admin_url( 'admin-ajax.php' ) );

        $colors        = [ 'like'=>'#e11d48','follow'=>'#2271b1','favorite'=>'#f59e0b','bookmark'=>'#6366f1','_all'=>'#10b981' ];
        $default_colors = ['#8b5cf6','#ec4899','#14b8a6','#f97316','#64748b'];

        $chart_json = wp_json_encode([
            '7'   => self::buildChartJson( $types, $colors, $default_colors, 7 ),
            '30'  => self::buildChartJson( $types, $colors, $default_colors, 30 ),
            '90'  => self::buildChartJson( $types, $colors, $default_colors, 90 ),
            '365' => self::buildChartJson( $types, $colors, $default_colors, 365 ),
        ]);

        $today_delta = $stats['today_vs_yesterday'];
        $delta_html  = $today_delta > 0
            ? '<span class="sh-stat-up">&#8593;' . $today_delta . '%</span>'
            : ( $today_delta < 0 ? '<span class="sh-stat-down">&#8595;' . abs($today_delta) . '%</span>' : '' );

        $post_types = get_post_types(['public'=>true],'objects');
        ?>

        <!-- Stat Cards -->
        <div class="sh-cards">
            <div class="sh-card">
                <h3>Bugun</h3>
                <div class="sh-card-val" id="sh-stat-today"><?php echo number_format_i18n($stats['today']); ?></div>
                <div class="sh-card-sub"><?php echo $delta_html; ?> dun ile karsilastirma</div>
            </div>
            <div class="sh-card">
                <h3>Bu Hafta</h3>
                <div class="sh-card-val" id="sh-stat-week"><?php echo number_format_i18n($stats['week']); ?></div>
                <div class="sh-card-sub">Son 7 gun</div>
            </div>
            <div class="sh-card">
                <h3>Bu Ay</h3>
                <div class="sh-card-val" id="sh-stat-month"><?php echo number_format_i18n($stats['month']); ?></div>
                <div class="sh-card-sub">Son 30 gun</div>
            </div>
            <div class="sh-card">
                <h3>Unique Kullanici</h3>
                <div class="sh-card-val" id="sh-stat-users"><?php echo number_format_i18n($stats['unique_users']); ?></div>
                <div class="sh-card-sub">Reaction yapan</div>
            </div>
            <?php if ( $stats['top_type'] ) : $td = $types[$stats['top_type']] ?? []; ?>
            <div class="sh-card">
                <h3>En Populer</h3>
                <div class="sh-card-val" style="font-size:18px;"><?php echo esc_html($td['label'] ?? $stats['top_type']); ?></div>
                <div class="sh-card-sub"><?php echo number_format_i18n($stats['top_type_count']); ?> reaction</div>
            </div>
            <?php endif; ?>
            <?php if ( $stats['last_created_at'] ) : ?>
            <div class="sh-card">
                <h3>Son Reaction</h3>
                <div class="sh-card-val" style="font-size:13px;line-height:1.6;font-weight:500;">
                    <?php if ($stats['last_user_avatar']) : ?>
                        <img src="<?php echo esc_url($stats['last_user_avatar']); ?>" style="width:18px;height:18px;border-radius:50%;vertical-align:middle;margin-right:4px;" alt="">
                    <?php endif; ?>
                    <?php echo esc_html(mb_substr($stats['last_user_name'],0,16)); ?>
                    <span style="color:#6b7280;font-weight:400;font-size:11px;"> &rarr; </span>
                    <span style="color:<?php echo esc_attr($colors[$stats['top_type']] ?? '#6b7280'); ?>;"><?php echo esc_html($stats['last_type_label']); ?></span>
                    <br><span style="font-size:11px;color:#6b7280;font-weight:400;"><?php echo esc_html(mb_substr($stats['last_obj_title'],0,24)); ?></span>
                </div>
                <div class="sh-card-sub"><?php echo esc_html(wp_date('d M Y H:i', strtotime($stats['last_created_at']))); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Per-type breakdown -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
        <?php foreach ( $types as $tk => $td ) :
            $cnt   = $type_cnts[$tk] ?? 0;
            $color = $colors[$tk] ?? '#6b7280';
            $enabled = !isset($td['enabled']) || !empty($td['enabled']);
        ?>
            <div class="sh-card" style="flex:1;min-width:120px;max-width:180px;padding:12px 14px;<?php echo !$enabled ? 'opacity:.45;' : ''; ?>">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span style="width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($color); ?>22;display:flex;align-items:center;justify-content:center;font-size:13px;">
                        <?php
                        $io = $td['icon_off'] ?? '';
                        if ( is_numeric($io) && (int)$io > 0 ) {
                            $iu = wp_get_attachment_url((int)$io);
                            if ($iu) echo '<img src="'.esc_url($iu).'" style="width:14px;height:14px;object-fit:contain;" alt="">';
                        } else {
                            echo '<i class="'.esc_attr($io ?: 'far fa-circle').'" style="color:'.esc_attr($color).';font-size:13px;"></i>';
                        }
                        ?>
                    </span>
                    <span style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;"><?php echo esc_html($td['label'] ?? $tk); ?></span>
                    <?php if (!$enabled) echo '<span style="font-size:9px;color:#9ca3af;">(pasif)</span>'; ?>
                </div>
                <div style="font-size:24px;font-weight:700;color:<?php echo esc_attr($color); ?>;"><?php echo number_format_i18n($cnt); ?></div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Chart -->
        <div class="sh-chart-wrap">
            <div class="sh-chart-header">
                <h2>Reaction Hacmi</h2>
                <select class="sh-period-select" id="sh-rxn-period">
                    <option value="7">Son 7 Gun</option>
                    <option value="30" selected>Son 30 Gun</option>
                    <option value="90">Son 90 Gun</option>
                    <option value="365">Son 1 Yil</option>
                </select>
                <button type="button" id="sh-rxn-refresh" class="sh-btn sh-btn-sm" style="margin-left:8px" title="Refresh analytics data">
                    ↻ Refresh
                </button>
            </div>
            <canvas id="sh-rxn-chart" height="80"></canvas>
        </div>


        <!-- Top Content Table -->
        <div class="sh-table-wrap">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <h2 style="margin:0;font-size:15px;font-weight:600;">&#127942; En Cok Reaction Alan Icerikler</h2>
                <div class="sh-filters" style="margin:0;">
                    <select id="sh-rxn-type-f" class="sh-period-select">
                        <option value="">Tum tipler</option>
                        <?php foreach ($types as $tk=>$td) : ?>
                            <option value="<?php echo esc_attr($tk); ?>"><?php echo esc_html($td['label']??$tk); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="sh-rxn-obj-f" class="sh-period-select">
                        <option value="">Tum objeler</option>
                        <option value="post">Post</option>
                        <option value="user">User</option>
                        <option value="comment">Comment</option>
                        <option value="term">Term</option>
                    </select>
                    <span id="sh-rxn-cnt" style="font-size:12px;color:#6b7280;"></span>
                </div>
            </div>
            <table class="sh-table" id="sh-rxn-tbl">
                <thead><tr>
                    <th style="width:32px;">#</th>
                    <th data-col="title">Baslik<span class="si"></span></th>
                    <th data-col="object_type">Tip<span class="si"></span></th>
                    <th data-col="subtype">Subtype<span class="si"></span></th>
                    <th data-col="type">Reaction<span class="si"></span></th>
                    <th data-col="count" class="sd">Sayi<span class="si"></span></th>
                    <th data-col="last_at">Son<span class="si"></span></th>
                </tr></thead>
                <tbody id="sh-rxn-tbody">
                <?php foreach ($top_content as $i => $row) :
                    $type_def = $types[$row['type']] ?? [];
                    $color    = $colors[$row['type']] ?? '#6b7280';
                    $max_cnt  = $top_content[0]['count'] ?? 1;
                    $bar_w    = $max_cnt > 0 ? round(($row['count']/$max_cnt)*80) : 0;
                    $subtype  = $row['subtype'] ?? '';
                ?>
                <tr data-title="<?php echo esc_attr($row['title']); ?>"
                    data-object-type="<?php echo esc_attr($row['object_type']); ?>"
                    data-subtype="<?php echo esc_attr($subtype); ?>"
                    data-type="<?php echo esc_attr($row['type']); ?>"
                    data-count="<?php echo esc_attr($row['count']); ?>"
                    data-last-at="<?php echo esc_attr($row['last_at']); ?>">
                    <td>
                        <span class="sh-analytics-rank <?php echo $i===0?'sh-analytics-rank-1':($i===1?'sh-analytics-rank-2':($i===2?'sh-analytics-rank-3':'')); ?>">
                            <?php echo $i+1; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['url']) : ?>
                            <a href="<?php echo esc_url($row['url']); ?>" target="_blank"><?php echo esc_html(mb_substr($row['title'],0,60)); ?></a>
                        <?php else : ?>
                            <?php echo esc_html(mb_substr($row['title'],0,60)); ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="sh-type"><?php echo esc_html($row['object_type']); ?></span></td>
                    <td><?php if ($subtype) : ?><span class="sh-type" style="background:#f0f7ff;color:#2271b1;"><?php echo esc_html($subtype); ?></span><?php else : ?><span style="color:#d1d5db;">—</span><?php endif; ?></td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;">
                            <?php
                            $io2 = $type_def['icon_off'] ?? '';
                            if ( is_numeric($io2) && (int)$io2 > 0 ) {
                                $iu2 = wp_get_attachment_url((int)$io2);
                                if ($iu2) echo '<img src="'.esc_url($iu2).'" style="width:12px;height:12px;object-fit:contain;" alt="">';
                            } else {
                                echo '<i class="'.esc_attr($io2 ?: 'far fa-circle').'" style="color:'.esc_attr($color).';font-size:12px;"></i>';
                            }
                            ?>
                            <span class="sh-type" style="background:<?php echo esc_attr($color); ?>18;color:<?php echo esc_attr($color); ?>;"><?php echo esc_html($type_def['label']??$row['type']); ?></span>
                        </span>
                    </td>
                    <td>
                        <strong style="color:<?php echo esc_attr($color); ?>;"><?php echo number_format_i18n($row['count']); ?></strong>
                        <span class="sh-analytics-bar" style="background:<?php echo esc_attr($color); ?>;width:<?php echo $bar_w; ?>px;margin-left:6px;"></span>
                    </td>
                    <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html(wp_date('d M Y', strtotime($row['last_at']))); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_content)) : ?>
                    <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px;">Henuz reaction yok.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="sh-pag" id="sh-rxn-pag"></div>
        </div>

        <!-- Bottom: Top Users + Activity Feed -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

            <!-- Top Users -->
            <div class="sh-table-wrap" style="margin-bottom:0;">
                <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                    <h2 style="margin:0;font-size:15px;font-weight:600;">&#128100; En Aktif Kullanicilar</h2>
                </div>
                <table class="sh-table">
                    <thead><tr>
                        <th>#</th><th>Kullanici</th><th>Toplam</th>
                        <?php foreach (array_slice(array_keys($types),0,3) as $tk) : $td=$types[$tk]??[]; ?>
                            <th><?php echo esc_html($td['label']??$tk); ?></th>
                        <?php endforeach; ?>
                        <th>Son</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($top_users as $i => $u) : ?>
                    <tr>
                        <td><span class="sh-analytics-rank <?php echo $i===0?'sh-analytics-rank-1':($i===1?'sh-analytics-rank-2':($i===2?'sh-analytics-rank-3':'')); ?>"><?php echo $i+1; ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:7px;">
                                <?php if ($u['avatar']) : ?>
                                    <img src="<?php echo esc_url($u['avatar']); ?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" alt="">
                                <?php else : ?>
                                    <span class="sh-activity-avatar-placeholder"><?php echo esc_html(mb_substr($u['name'],0,1)); ?></span>
                                <?php endif; ?>
                                <?php if ($u['profile']) : ?>
                                    <a href="<?php echo esc_url($u['profile']); ?>" target="_blank" style="font-size:12px;font-weight:500;"><?php echo esc_html(mb_substr($u['name'],0,20)); ?></a>
                                <?php else : ?>
                                    <span style="font-size:12px;"><?php echo esc_html(mb_substr($u['name'],0,20)); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong><?php echo number_format_i18n($u['total']); ?></strong></td>
                        <?php $type_keys = array_slice(array_keys($types),0,3);
                        foreach ($type_keys as $tk) :
                            $val = $tk==='like'?$u['likes']:($tk==='favorite'?$u['favs']:($tk==='follow'?$u['follows']:0));
                        ?>
                            <td style="color:#6b7280;"><?php echo $val ?: '—'; ?></td>
                        <?php endforeach; ?>
                        <td style="font-size:11px;color:#9ca3af;"><?php echo esc_html(wp_date('d M', strtotime($u['last_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_users)) : ?>
                        <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:20px;">Henuz veri yok.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Activity Feed -->
            <div class="sh-activity-feed" style="margin-bottom:0;">
                <div class="sh-activity-feed-header">
                    <h2 class="sh-activity-feed-title">&#9889; Son Aktivite</h2>
                </div>
                <div class="sh-activity-list">
                <?php foreach ($activity as $item) :
                    $td    = $types[$item['type']] ?? [];
                    $color = $colors[$item['type']] ?? '#6b7280';
                    $icon  = $td['icon_on'] ?? 'fas fa-circle';
                    $ago   = human_time_diff(strtotime($item['created_at']), current_time('timestamp')) . ' once';
                ?>
                <div class="sh-activity-item">
                    <?php if ($item['avatar']) : ?>
                        <img src="<?php echo esc_url($item['avatar']); ?>" class="sh-activity-avatar" alt="">
                    <?php else : ?>
                        <span class="sh-activity-avatar-placeholder"><?php echo esc_html(mb_substr($item['user_name'],0,1)); ?></span>
                    <?php endif; ?>
                    <div class="sh-activity-body">
                        <div class="sh-activity-text">
                            <strong><?php echo esc_html(mb_substr($item['user_name'],0,20)); ?></strong>
                            <span style="color:<?php echo esc_attr($color); ?>;font-size:11px;margin:0 3px;">
                                <?php
                                if ( is_numeric($icon) && (int)$icon > 0 ) {
                                    $iu3 = wp_get_attachment_url((int)$icon);
                                    if ($iu3) echo '<img src="'.esc_url($iu3).'" style="width:11px;height:11px;object-fit:contain;vertical-align:middle;" alt="">';
                                } else {
                                    echo '<i class="'.esc_attr($icon ?: 'fas fa-circle').'"></i>';
                                }
                                ?>
                                <?php echo esc_html($td['label'] ?? $item['type']); ?>
                            </span>
                            <?php if ($item['url']) : ?>
                                <a href="<?php echo esc_url($item['url']); ?>" target="_blank"><?php echo esc_html(mb_substr($item['title'],0,30)); ?></a>
                            <?php else : ?>
                                <?php echo esc_html(mb_substr($item['title'],0,30)); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="sh-activity-time"><?php echo esc_html($ago); ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($activity)) : ?>
                    <div style="text-align:center;color:#9ca3af;padding:24px;font-size:13px;">Henuz aktivite yok.</div>
                <?php endif; ?>
                </div>
            </div>

        </div>

        <div id="sh-toast"></div>

        <?php self::renderJs( $nonce, $ajax_url, $chart_json, $types, $colors ); ?>
        <?php
    }

    private static function buildChartJson( array $types, array $colors, array $default_colors, int $days ): array {
        $data = self::getChartData( $days );

        // Tum gunleri olustur (data olmasa da 0 ile doldur) — search-history gibi
        $all_dates = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $all_dates[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
        }

        $datasets = [];
        $ci = 0;
        foreach ( $data as $type_key => $series ) {
            if ( $type_key === '_all' ) continue;
            $color = $colors[$type_key] ?? $default_colors[$ci++ % count($default_colors)];
            $td    = $types[$type_key] ?? [];
            // Sparse data'yi dense'e cevir
            $sparse = array_combine( $series['labels'], $series['counts'] );
            $dense  = array_map( fn($d) => $sparse[$d] ?? 0, $all_dates );
            $datasets[] = [
                'label'           => $td['label'] ?? ucfirst($type_key),
                'data'            => $dense,
                'borderColor'     => $color,
                'backgroundColor' => $color . '18',
                'borderWidth'     => 2,
                'pointRadius'     => 2,
                'fill'            => true,
                'tension'         => 0.4,
            ];
        }
        // Toplam dataset
        $sparse_all = array_combine( $data['_all']['labels'] ?? [], $data['_all']['counts'] ?? [] );
        $dense_all  = array_map( fn($d) => $sparse_all[$d] ?? 0, $all_dates );
        $datasets[] = [
            'label'           => 'Toplam',
            'data'            => $dense_all,
            'borderColor'     => '#10b981',
            'backgroundColor' => '#10b98118',
            'borderWidth'     => 2,
            'pointRadius'     => 2,
            'fill'            => true,
            'tension'         => 0.4,
        ];
        return [ 'labels' => $all_dates, 'datasets' => $datasets ];
    }

    private static function renderJs( string $nonce, string $ajax_url, string $chart_json, array $types, array $colors ): void {
        $n = wp_json_encode( $nonce );
        $a = wp_json_encode( $ajax_url );
        echo '<script>' . "\n";
        echo '(function(){' . "\n";
        echo "'use strict';\n";
        echo 'var AJAX=' . $a . ';' . "\n";
        echo 'var NONCE=' . $n . ';' . "\n";
        echo 'var CPD=' . $chart_json . ';' . "\n";
        echo 'var rxnChart=null;' . "\n";
        echo <<<'JSEOF'
function toast(m,e){var el=document.getElementById('sh-toast');if(!el)return;el.textContent=m;el.style.background=e?'#dc2626':'#1f2937';el.classList.add('show');setTimeout(function(){el.classList.remove('show');},3000);}
function buildChart(period){
    if(typeof Chart==='undefined'){setTimeout(function(){buildChart(period);},100);return;}
    var d=CPD[period]||CPD['30'];
    var ctx=document.getElementById('sh-rxn-chart');
    if(!ctx)return;
    if(rxnChart){rxnChart.destroy();}
    rxnChart=new Chart(ctx,{type:'line',data:{labels:d.labels,datasets:d.datasets},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11},usePointStyle:true}},tooltip:{callbacks:{label:function(c){return' '+c.dataset.label+': '+c.parsed.y;}}}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:12}},y:{beginAtZero:true,ticks:{precision:0}}}}});
}

// ── AJAX Refresh ──────────────────────────────────────────────────────────────
function refreshAnalytics(days, typeFilter, objFilter) {
    var $btn = document.getElementById('sh-rxn-refresh');
    if ($btn) { $btn.disabled = true; $btn.textContent = '↻ Loading...'; }

    var fd = new FormData();
    fd.append('action', 'sh_reactions_get_data');
    fd.append('nonce', NONCE);
    fd.append('days', days || document.getElementById('sh-rxn-period')?.value || '30');
    fd.append('type_filter', typeFilter || document.getElementById('sh-rxn-type-f')?.value || '');
    fd.append('obj_filter', objFilter || document.getElementById('sh-rxn-obj-f')?.value || '');

    fetch(AJAX, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (!res.success) { toast('Refresh failed', true); return; }
            var d = res.data;

            // Stat cards güncelle
            if (d.stats) {
                var map = {
                    'sh-stat-today':   d.stats.today,
                    'sh-stat-week':    d.stats.week,
                    'sh-stat-month':   d.stats.month,
                    'sh-stat-users':   d.stats.unique_users,
                };
                Object.keys(map).forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.textContent = (map[id] || 0).toLocaleString();
                });
            }

            // Chart güncelle
            if (d.chart) {
                var period = document.getElementById('sh-rxn-period')?.value || '30';
                var cd = d.chart[period] || d.chart['30'];
                if (rxnChart && cd) {
                    rxnChart.data.labels   = cd.labels;
                    rxnChart.data.datasets = cd.datasets;
                    rxnChart.update();
                }
                // CPD'yi güncelle — period değişince yeni data kullanılsın
                Object.assign(CPD, d.chart);
            }

            // Top content tablosu güncelle
            if (d.content && Array.isArray(d.content)) {
                updateTopContent(d.content);
            }

            // Recent activity güncelle
            if (d.activity && Array.isArray(d.activity)) {
                updateActivity(d.activity);
            }

            toast('Analytics refreshed');
        })
        .catch(function(){ toast('Network error', true); })
        .finally(function() {
            if ($btn) { $btn.disabled = false; $btn.textContent = '↻ Refresh'; }
        });
}

function updateTopContent(rows) {
    var tbody = document.getElementById('sh-rxn-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    var maxCnt = rows.length > 0 ? (rows[0].count || 1) : 1;
    rows.forEach(function(r, i) {
        var barW = maxCnt > 0 ? Math.round((r.count / maxCnt) * 80) : 0;
        var tr = document.createElement('tr');
        tr.setAttribute('data-type', r.type || '');
        tr.setAttribute('data-object-type', r.object_type || '');
        tr.setAttribute('data-subtype', r.subtype || '');
        tr.setAttribute('data-count', r.count || 0);
        tr.setAttribute('data-title', r.title || '');
        tr.setAttribute('data-last-at', r.last_at || '');
        tr.innerHTML =
            '<td style="padding:8px 12px"><span class="sh-analytics-rank' + (i===0?' sh-analytics-rank-1':i===1?' sh-analytics-rank-2':i===2?' sh-analytics-rank-3':'') + '">' + (i+1) + '</span></td>' +
            '<td style="padding:8px 12px">' + (r.url ? '<a href="' + escHtml(r.url) + '" target="_blank">' + escHtml((r.title||'').substring(0,60)) + '</a>' : escHtml((r.title||'').substring(0,60))) + '</td>' +
            '<td style="padding:8px 12px"><span class="sh-type">' + escHtml(r.object_type || '') + '</span></td>' +
            '<td style="padding:8px 12px">' + (r.subtype ? '<span class="sh-type" style="background:#f0f7ff;color:#2271b1;">' + escHtml(r.subtype) + '</span>' : '<span style="color:#d1d5db;">—</span>') + '</td>' +
            '<td style="padding:8px 12px"><span class="sh-type">' + escHtml(r.type || '') + '</span></td>' +
            '<td style="padding:8px 12px"><strong>' + (r.count||0).toLocaleString() + '</strong><span class="sh-analytics-bar" style="width:' + barW + 'px;margin-left:6px;"></span></td>' +
            '<td style="padding:8px 12px;font-size:11px;color:#9ca3af;">' + escHtml((r.last_at||'').substring(0,10)) + '</td>';
        tbody.appendChild(tr);
    });
    // Mevcut sort/filter'ı yeniden uygula
    allRows = Array.from(tbody.querySelectorAll('tr'));
    filtered = allRows.slice();
    go();
}

function updateActivity(rows) {
    var list = document.querySelector('.sh-activity-list');
    if (!list) return;
    list.innerHTML = '';
    rows.forEach(function(r) {
        var ago = r.created_at ? r.created_at.substring(0,10) : '';
        var div = document.createElement('div');
        div.className = 'sh-activity-item';
        div.innerHTML =
            (r.avatar ? '<img src="' + escHtml(r.avatar) + '" class="sh-activity-avatar" alt="">' : '<span class="sh-activity-avatar-placeholder">' + escHtml((r.user_name||'?').substring(0,1)) + '</span>') +
            '<div class="sh-activity-body"><div class="sh-activity-text">' +
            '<strong>' + escHtml((r.user_name||'').substring(0,20)) + '</strong>' +
            ' <span style="font-size:11px;margin:0 3px;">' + escHtml(r.type||'') + '</span>' +
            (r.url ? ' <a href="' + escHtml(r.url) + '" target="_blank">' + escHtml((r.title||'').substring(0,30)) + '</a>' : ' ' + escHtml((r.title||'').substring(0,30))) +
            '</div></div>' +
            '<span class="sh-activity-time">' + escHtml(ago) + '</span>';
        list.appendChild(div);
    });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Refresh butonu
var refreshBtn = document.getElementById('sh-rxn-refresh');
if (refreshBtn) {
    refreshBtn.addEventListener('click', function() {
        refreshAnalytics();
    });
}

// Period değişince chart güncelle (AJAX değil — CPD'den)
var ps=document.getElementById('sh-rxn-period');
if(ps)ps.addEventListener('change',function(){buildChart(this.value);});
buildChart('30');

// ── Table Sort & Filter ───────────────────────────────────────────────────────
var tbody=document.getElementById('sh-rxn-tbody');
var allRows=Array.from(tbody?tbody.querySelectorAll('tr'):[]);
var filtered=allRows.slice();
var sortCol='count',sortDir='desc',PAGE=20,page=1;
document.querySelectorAll('#sh-rxn-tbl th[data-col]').forEach(function(th){
    th.addEventListener('click',function(){
        var col=this.getAttribute('data-col');
        sortDir=(sortCol===col)?(sortDir==='asc'?'desc':'asc'):'desc';
        sortCol=col;
        document.querySelectorAll('#sh-rxn-tbl th').forEach(function(t){t.classList.remove('sa','sd');});
        this.classList.add(sortDir==='asc'?'sa':'sd');
        go();
    });
});
function val(r,c){return r.getAttribute('data-'+c.replace(/_/g,'-'))||'';}
function sortRows(rows){return rows.slice().sort(function(a,b){var av=val(a,sortCol),bv=val(b,sortCol);var na=parseFloat(av),nb=parseFloat(bv);var cmp=(!isNaN(na)&&!isNaN(nb))?na-nb:av.localeCompare(bv,undefined,{sensitivity:'base'});return sortDir==='asc'?cmp:-cmp;});}
var tEl=document.getElementById('sh-rxn-type-f');
var oEl=document.getElementById('sh-rxn-obj-f');
function go(){
    var t=tEl?tEl.value:'';
    var o=oEl?oEl.value:'';
    filtered=allRows.filter(function(r){
        if(t&&(r.getAttribute('data-type')||'')!==t)return false;
        if(o&&(r.getAttribute('data-object-type')||'')!==o)return false;
        return true;
    });
    filtered=sortRows(filtered);
    page=1;
    render();
}
if(tEl)tEl.addEventListener('change',go);
if(oEl)oEl.addEventListener('change',go);
function render(){
    var start=(page-1)*PAGE;
    allRows.forEach(function(r){r.classList.add('sh-hidden');});
    filtered.slice(start,start+PAGE).forEach(function(r){r.classList.remove('sh-hidden');});
    var cnt=document.getElementById('sh-rxn-cnt');
    if(cnt)cnt.textContent=filtered.length+' kayit';
    renderPag();
}
function renderPag(){
    var pag=document.getElementById('sh-rxn-pag');
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
JSEOF;
        echo '})();' . "\n";
        echo '</script>' . "\n";
    }
}
