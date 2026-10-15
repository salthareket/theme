<?php

namespace SaltHareket\Reviews\Admin;

use SaltHareket\Reviews\Reviews;
use SaltHareket\Reviews\ReviewsSettings;

/**
 * ReviewsAdmin
 * ACF-free admin sayfasi - sticky toolbar, filter, bulk islemler.
 *
 * @version 1.1.0
 */
class ReviewsAdmin
{
    public static function register(): void
    {
        // add_action( 'admin_menu', [ self::class, 'addMenuPage' ], 20 ); // Dashboard üzerinden erişiliyor
        add_action( 'admin_head', [ self::class, 'hideNotices' ] );
        add_action( 'admin_post_sh_reviews_save_settings', [ self::class, 'saveSettings' ] );
        add_action( 'wp_ajax_sh_reviews_save_enable_toggle', [ self::class, 'ajaxSaveEnableToggle' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ] );
    }

    public static function enqueueAssets( string $hook ): void
    {
        if ( strpos( $hook, 'sh-reviews' ) === false ) return;
        $css_path = dirname( __DIR__, 2 ) . '/sh-admin.css';
        $css_url  = trailingslashit( SH_INCLUDES_URL ) . 'apps/sh-admin.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style( 'sh-admin-kit', $css_url, [], filemtime( $css_path ) );
        }
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '&#11088; Reviews',
            '&#11088; Reviews',
            'moderate_comments',
            'sh-reviews',
            [ self::class, 'renderPage' ]
        );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-reviews' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important;}</style>';
    }

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'moderate_comments' ) ) wp_die( 'Unauthorized' );

        $tab = sanitize_key( $_GET['tab'] ?? 'all' );

        if ( $tab === 'settings' ) {
            self::renderSettingsPage();
            return;
        }

        $search   = sanitize_text_field( $_GET['s'] ?? '' );
        $rating   = isset( $_GET['rating'] ) && $_GET['rating'] !== '' ? (int) $_GET['rating'] : null;
        $verified = isset( $_GET['verified'] ) && $_GET['verified'] !== '' ? (int) $_GET['verified'] : null;
        $page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $nonce    = wp_create_nonce( 'sh_reviews_nonce' );
        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );

        global $wpdb;

        $where  = "c.comment_type = 'review'";
        $where .= match ( $tab ) {
            'pending' => " AND c.comment_approved = '0'",
            'spam'    => " AND c.comment_approved = 'spam'",
            default   => " AND c.comment_approved = '1'",
        };

        if ( $search ) {
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (c.comment_content LIKE %s OR c.comment_author LIKE %s)", $like, $like );
        }

        if ( $rating ) {
            $where .= $wpdb->prepare(
                " AND EXISTS (SELECT 1 FROM {$wpdb->commentmeta} rm WHERE rm.comment_id = c.comment_ID AND rm.meta_key = 'rating' AND rm.meta_value = %d)",
                $rating
            );
        }

        if ( $verified !== null ) {
            $where .= $wpdb->prepare(
                " AND EXISTS (SELECT 1 FROM {$wpdb->commentmeta} vm WHERE vm.comment_id = c.comment_ID AND vm.meta_key = 'verified' AND vm.meta_value = %s)",
                $verified ? '1' : '0'
            );
        }

        $per_page   = 20;
        $offset     = ( $page - 1 ) * $per_page;
        $total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} c WHERE {$where}" ); // phpcs:ignore
        $page_total = max( 1, (int) ceil( $total / $per_page ) );

        $rows = $wpdb->get_results( // phpcs:ignore
            "SELECT c.*,
                    (SELECT meta_value FROM {$wpdb->commentmeta} WHERE comment_id = c.comment_ID AND meta_key = 'rating' LIMIT 1) as rating,
                    (SELECT meta_value FROM {$wpdb->commentmeta} WHERE comment_id = c.comment_ID AND meta_key = 'verified' LIMIT 1) as verified,
                    (SELECT meta_value FROM {$wpdb->commentmeta} WHERE comment_id = c.comment_ID AND meta_key = 'helpful_score' LIMIT 1) as helpful_score
             FROM {$wpdb->comments} c
             WHERE {$where}
             ORDER BY c.comment_date_gmt DESC
             LIMIT {$offset}, {$per_page}"
        );

        $count_all     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '1'" ); // phpcs:ignore
        $count_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '0'" ); // phpcs:ignore

        ?>
        <div class="sh-wrap" id="sh-reviews-page">
        <?php self::renderStyles(); ?>

        <div class="sh-toolbar">
            <?php $reviews_enabled_badge = (bool) \SaltHareket\Reviews\ReviewsSettings::get( 'general.enable_reviews' ); ?>
            <h1 style="display:inline-flex;align-items:center;gap:8px;">&#11088; Reviews
                <span id="sh-reviews-header-badge" class="sh-badge <?php echo $reviews_enabled_badge ? 'sh-badge-green' : 'sh-badge-gray'; ?>" style="font-size:11px"><?php echo $reviews_enabled_badge ? 'Enabled' : 'Disabled'; ?></span>
            </h1>
            <span class="sh-badge sh-badge-blue"><?php echo $count_all; ?> approved</span>
            <?php if ( $count_pending > 0 ) : ?>
                <span class="sh-badge sh-badge-orange"><?php echo $count_pending; ?> pending</span>
            <?php endif; ?>
            <div class="sh-toolbar-right">
                <?php foreach ( [ 'all' => 'Approved', 'pending' => 'Pending', 'spam' => 'Spam' ] as $t => $label ) : ?>
                    <a href="?page=sh-reviews&amp;tab=<?php echo $t; ?>" class="sh-tab-btn <?php echo $tab === $t ? 'active' : ''; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
                <a href="?page=sh-reviews&amp;tab=settings" class="sh-tab-btn <?php echo $tab === 'settings' ? 'active' : ''; ?>">&#9881; Settings</a>
            </div>
        </div>
        <?php self::renderEnableToggle(); ?>

        <div class="sh-filter-bar">
            <form method="get" style="display:contents">
                <input type="hidden" name="page" value="sh-reviews">
                <input type="hidden" name="tab"  value="<?php echo esc_attr( $tab ); ?>">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search reviews..." class="sh-input" style="max-width:220px">
                <select name="rating" class="sh-select" style="width:auto">
                    <option value="">All Ratings</option>
                    <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                        <option value="<?php echo $i; ?>" <?php selected( $rating, $i ); ?>><?php echo str_repeat( '&#9733;', $i ); ?></option>
                    <?php endfor; ?>
                </select>
                <select name="verified" class="sh-select" style="width:auto">
                    <option value="">All</option>
                    <option value="1" <?php selected( $verified, 1 ); ?>>Verified</option>
                    <option value="0" <?php selected( $verified !== null && $verified === 0, true ); ?>>Unverified</option>
                </select>
                <button type="submit" class="sh-btn sh-btn-primary">Filter</button>
                <?php if ( $search || $rating || $verified !== null ) : ?>
                    <a href="?page=sh-reviews&amp;tab=<?php echo esc_attr( $tab ); ?>" class="sh-btn sh-btn-ghost">Clear</a>
                <?php endif; ?>
                <span class="sh-count-label" style="margin-left:auto"><?php echo $total; ?> reviews</span>
            </form>
        </div>

        <?php if ( empty( $rows ) ) : ?>
            <div class="sh-empty-box">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#c3c4c7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <p style="margin:0 0 4px;font-weight:500;color:#50575e">No reviews found</p>
                <p style="margin:0;font-size:12px;color:#9ca3af">Reviews will appear here once users submit them.</p>
            </div>
        <?php else : ?>

        <div class="sh-bulk-bar" id="sh-bulk-bar" style="display:none">
            <span id="sh-bulk-count">0</span> selected
            <button type="button" class="sh-btn sh-btn-primary sh-btn-sm" onclick="shBulkAction('approve')">Approve</button>
            <button type="button" class="sh-btn sh-btn-ghost sh-btn-sm"  onclick="shBulkAction('reject')">Reject</button>
            <button type="button" class="sh-btn sh-btn-danger sh-btn-sm" onclick="shBulkAction('delete')">Delete</button>
        </div>

        <div class="sh-reviews-list" id="sh-reviews-list">
        <?php foreach ( $rows as $row ) :
            $rating_val   = (int) $row->rating;
            $is_verified  = (bool) $row->verified;
            $helpful      = (int) $row->helpful_score;
            $avatar       = get_avatar( $row->comment_author_email, 40, 'mystery', $row->comment_author );
            $post_title   = $row->comment_post_ID ? get_the_title( $row->comment_post_ID ) : '--';
            $profile_id   = (int) get_comment_meta( $row->comment_ID, 'comment_profile', true );
            $target_label = $profile_id
                ? ( get_userdata( $profile_id )->display_name ?? "User #{$profile_id}" )
                : $post_title;
        ?>
            <div class="sh-review-card" data-id="<?php echo esc_attr( $row->comment_ID ); ?>">
                <div class="sh-review-check">
                    <input type="checkbox" class="sh-bulk-check" onchange="shBulkUpdate()">
                </div>
                <div class="sh-review-avatar"><?php echo $avatar; ?></div>
                <div class="sh-review-body">
                    <div class="sh-review-header">
                        <strong class="sh-review-author"><?php echo esc_html( $row->comment_author ); ?></strong>
                        <span class="sh-review-stars"><?php echo str_repeat( '&#9733;', $rating_val ) . str_repeat( '&#9734;', 5 - $rating_val ); ?></span>
                        <?php if ( $is_verified ) : ?>
                            <span class="sh-badge sh-badge-green" title="Verified">&#10003; Verified</span>
                        <?php endif; ?>
                        <?php if ( $helpful > 0 ) : ?>
                            <span class="sh-badge sh-badge-gray" title="Helpful score">&#128077; <?php echo $helpful; ?></span>
                        <?php endif; ?>
                        <span class="sh-review-target">&#8594; <?php echo esc_html( $target_label ); ?></span>
                        <span class="sh-review-date"><?php echo esc_html( date_i18n( 'd M Y', strtotime( $row->comment_date ) ) ); ?></span>
                    </div>
                    <div class="sh-review-content"><?php echo esc_html( wp_trim_words( $row->comment_content, 30 ) ); ?></div>
                </div>
                <div class="sh-review-actions">
                    <?php if ( $row->comment_approved === '0' ) : ?>
                        <button type="button" class="sh-rule-btn sh-rule-btn-edit" onclick="shReviewAction(this,'approve')" title="Approve">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="sh-rule-btn sh-rule-btn-test" onclick="shReviewAction(this,'reject')" title="Reject">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                    <button type="button" class="sh-rule-btn sh-rule-btn-delete" onclick="shReviewAction(this,'delete')" title="Delete">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <?php if ( $page_total > 1 ) : ?>
        <div class="sh-pagination">
            <?php for ( $i = 1; $i <= $page_total; $i++ ) :
                $page_args = array_filter( [
                    'page'     => 'sh-reviews',
                    'tab'      => $tab,
                    'paged'    => $i,
                    's'        => $search ?: null,
                    'rating'   => $rating ?: null,
                    'verified' => $verified !== null ? $verified : null,
                ] );
            ?>
                <a href="?<?php echo http_build_query( $page_args ); ?>"
                   class="sh-page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <div id="sh-toast"></div>
        </div>

        <script>
        var SH_NONCE = <?php echo wp_json_encode( $nonce ); ?>;
        var SH_AJAX  = <?php echo wp_json_encode( $ajax_url ); ?>;

        function shReviewAction(btn, action) {
            var card = btn.closest('.sh-review-card');
            var id   = card.dataset.id;
            if (action === 'delete' && !confirm('Delete this review?')) return;
            card.style.opacity = '.5';
            fetch(SH_AJAX, {
                method: 'POST',
                body: new URLSearchParams({ action: 'sh_review_' + action, id: id, _wpnonce: SH_NONCE })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    card.remove();
                    shToast(res.message || 'Done', 'success');
                } else {
                    card.style.opacity = '1';
                    shToast(res.message || 'Error', 'error');
                }
            });
        }

        function shBulkUpdate() {
            var checked = document.querySelectorAll('.sh-bulk-check:checked');
            var bar     = document.getElementById('sh-bulk-bar');
            document.getElementById('sh-bulk-count').textContent = checked.length;
            bar.style.display = checked.length > 0 ? 'flex' : 'none';
        }

        function shBulkAction(action) {
            var checked = document.querySelectorAll('.sh-bulk-check:checked');
            var ids     = Array.from(checked).map(function(c) { return c.closest('.sh-review-card').dataset.id; });
            if (!ids.length) return;
            if (action === 'delete' && !confirm('Delete ' + ids.length + ' reviews?')) return;
            fetch(SH_AJAX, {
                method: 'POST',
                body: new URLSearchParams({ action: 'sh_review_bulk', bulk_action: action, 'ids[]': ids, _wpnonce: SH_NONCE })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    checked.forEach(function(c) { c.closest('.sh-review-card').remove(); });
                    document.getElementById('sh-bulk-bar').style.display = 'none';
                    shToast('Done', 'success');
                }
            });
        }

        function shToast(msg, type) {
            var t = document.createElement('div');
            t.className = 'sh-toast-item sh-toast-' + (type || 'success');
            t.textContent = msg;
            document.getElementById('sh-toast').appendChild(t);
            setTimeout(function() { t.remove(); }, 3000);
        }
        </script>
        <?php
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    private static function renderSettingsPage(): void
    {
        require_once __DIR__ . '/ReviewsSettingsRenderer.php';
        \SaltHareket\Reviews\Admin\ReviewsSettingsRenderer::render();
    }

    private static function renderEnableToggle(): void
    {
        $enabled  = (bool) \SaltHareket\Reviews\ReviewsSettings::get( 'general.enable_reviews' );
        $nonce    = wp_create_nonce( 'sh_reviews_nonce' );
        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
            <strong style="font-size:13px;color:#1d2327;">Enable Reviews</strong>
            <label class="sh-toggle">
                <input type="checkbox" id="sh-reviews-enable-toggle"
                       <?php checked( $enabled ); ?>
                       onchange="shReviewsEnableToggle(this.checked)">
                <span class="sh-toggle-slider"></span>
            </label>
            <span id="sh-reviews-enable-label" style="font-size:12px;color:<?php echo $enabled ? '#00a32a' : '#9ca3af'; ?>">
                <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
            </span>
            <span id="sh-reviews-enable-saving" style="display:none;font-size:12px;color:#9ca3af;margin-left:4px;">
                <span style="display:inline-block;width:12px;height:12px;border:2px solid #ddd;border-top-color:#2271b1;border-radius:50%;animation:sh-spin .6s linear infinite;vertical-align:middle;margin-right:4px;"></span>Saving...
            </span>
        </div>
        <script>
        function shReviewsEnableToggle(enabled) {
            var label  = document.getElementById('sh-reviews-enable-label');
            var saving = document.getElementById('sh-reviews-enable-saving');
            var toggle = document.getElementById('sh-reviews-enable-toggle');
            if (saving) saving.style.display = 'inline-flex';
            if (label)  label.style.display  = 'none';
            var fd = new FormData();
            fd.append('action',  'sh_reviews_save_enable_toggle');
            fd.append('nonce',   <?php echo wp_json_encode( $nonce ); ?>);
            fd.append('enabled', enabled ? '1' : '0');
            fetch(<?php echo wp_json_encode( $ajax_url ); ?>, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (saving) saving.style.display = 'none';
                    if (label) {
                        label.style.display = '';
                        label.style.color   = enabled ? '#00a32a' : '#9ca3af';
                        label.textContent   = enabled ? 'Enabled' : 'Disabled';
                    }
                    // h1 badge sync
                    var hb = document.getElementById('sh-reviews-header-badge');
                    if (hb) { hb.textContent = enabled ? 'Enabled' : 'Disabled'; hb.className = 'sh-badge ' + (enabled ? 'sh-badge-green' : 'sh-badge-gray'); }
                })
                .catch(function() {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = '';
                    if (toggle) toggle.checked = !enabled;
                });
        }
        </script>
        <?php
    }

    private static function renderToggle( string $name, string $label, bool $checked, string $desc = '' ): void
    {
        ?>
        <div class="sh-settings-toggle">
            <label class="sh-toggle" style="flex-shrink:0">
                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
                <span class="sh-toggle-slider"></span>
            </label>
            <div>
                <span class="sh-settings-toggle-label"><?php echo esc_html( $label ); ?></span>
                <?php if ( $desc ) : ?>
                    <br><span style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $desc ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function ajaxSaveEnableToggle(): void
    {
        check_ajax_referer( 'sh_reviews_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $enabled = ! empty( $_POST['enabled'] ) && $_POST['enabled'] !== '0';
        $saved   = \SaltHareket\Reviews\ReviewsSettings::all();
        $saved['general']['enable_reviews'] = $enabled;
        \SaltHareket\Reviews\ReviewsSettings::saveAll( $saved );

        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    public static function saveSettings(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sh_reviews_save_settings' );

        if ( isset( $_GET['reset'] ) ) {
            \SaltHareket\Reviews\ReviewsSettings::reset();
            wp_redirect( admin_url( 'admin.php?page=sh-reviews&tab=settings&saved=1' ) );
            exit;
        }

        $post = $_POST;

        // Criteria normalize
        if ( isset( $post['rating']['criteria'] ) ) {
            $raw_criteria = $post['rating']['criteria'];

            if ( isset( $raw_criteria['default'] ) && is_array( $raw_criteria['default'] ) ) {
                $post['rating']['criteria']['default'] = \SaltHareket\Reviews\ReviewsSettings::normalizeCriteria( $raw_criteria['default'] );
            } else {
                $post['rating']['criteria']['default'] = [];
            }

            if ( isset( $raw_criteria['post_types'] ) && is_array( $raw_criteria['post_types'] ) ) {
                foreach ( $raw_criteria['post_types'] as $pt => $rows ) {
                    $normalized = \SaltHareket\Reviews\ReviewsSettings::normalizeCriteria( is_array( $rows ) ? $rows : [] );
                    $post['rating']['criteria']['post_types'][ sanitize_key( $pt ) ] = $normalized;
                }
            } else {
                $post['rating']['criteria']['post_types'] = [];
            }

            // User role bazlı criteria
            if ( isset( $raw_criteria['user_roles'] ) && is_array( $raw_criteria['user_roles'] ) ) {
                foreach ( $raw_criteria['user_roles'] as $role => $rows ) {
                    $normalized = \SaltHareket\Reviews\ReviewsSettings::normalizeCriteria( is_array( $rows ) ? $rows : [] );
                    $post['rating']['criteria']['user_roles'][ sanitize_key( $role ) ] = $normalized;
                }
            } else {
                $post['rating']['criteria']['user_roles'] = [];
            }
        }

        // Bool fields — sadece POST'ta gelen gruplar için normalize et
        // Gelmeyenler mevcut DB değerini korur
        $current_settings = \SaltHareket\Reviews\ReviewsSettings::all();
        $bool_fields = [
            'general'       => [ 'enable_reviews', 'disable_review_approve', 'auto_approve_reviews', 'auto_approve_verified', 'auto_approve_trusted', 'require_login', 'one_review_per_user' ],
            'rating'        => [ 'allow_half', 'show_breakdown' ],
            'reply'         => [ 'enable_replies', 'auto_approve_owner_reply', 'user_approves_reply' ],
            'helpful'       => [ 'enable_helpful', 'require_login_to_vote' ],
            'media'         => [ 'enable_media' ],
            'notifications' => [ 'notify_on_new_review', 'notify_on_reply', 'notify_on_approve' ],
        ];

        foreach ( $bool_fields as $group => $fields ) {
            foreach ( $fields as $field ) {
                if ( isset( $post[ $group ] ) ) {
                    // Bu grup POST'ta geldi — checkbox mantığıyla normalize et
                    $post[ $group ][ $field ] = ! empty( $post[ $group ][ $field ] );
                } else {
                    // Bu grup POST'ta yok (farklı tab'dan save) — mevcut değeri koru
                    $post[ $group ][ $field ] = $current_settings[ $group ][ $field ] ?? false;
                }
            }
        }


        // Int fields
        $post['general']['trusted_threshold']   = max( 1, (int) ( $post['general']['trusted_threshold'] ?? 3 ) );
        $post['general']['rate_limit_per_hour'] = max( 0, (int) ( $post['general']['rate_limit_per_hour'] ?? 5 ) );
        $post['rating']['max_rating']           = in_array( (int) ( $post['rating']['max_rating'] ?? 5 ), [ 5, 10 ] ) ? (int) $post['rating']['max_rating'] : 5;
        $post['reply']['max_depth']             = max( 1, min( 5, (int) ( $post['reply']['max_depth'] ?? 1 ) ) );
        $post['media']['max_images']            = max( 1, min( 20, (int) ( $post['media']['max_images'] ?? 5 ) ) );
        $post['media']['max_image_size']        = max( 1, min( 20, (int) ( $post['media']['max_image_size'] ?? 5 ) ) );

        // Enum fields
        $post['rating']['type']             = in_array( $post['rating']['type'] ?? '', [ 'stars', 'thumbs', 'nps', 'multi' ] ) ? $post['rating']['type'] : 'stars';
        $post['rating']['criteria_display'] = in_array( $post['rating']['criteria_display'] ?? '', [ 'inline', 'popover' ] ) ? $post['rating']['criteria_display'] : 'inline';

        $allowed = [ 'general', 'rating', 'reply', 'helpful', 'media', 'notifications', 'types' ];
        $clean   = array_intersect_key( $post, array_flip( $allowed ) );

        // enable_reviews toggle ayrı AJAX ile yönetiliyor — form submit'te her zaman mevcut değeri koru
        $clean['general']['enable_reviews'] = $current_settings['general']['enable_reviews'] ?? true;

        // Types normalize — enabled bool, modes array, criteria normalized
        if ( ! empty( $clean['types'] ) && is_array( $clean['types'] ) ) {
            $clean_types = [];
            foreach ( $clean['types'] as $type_key => $type_data ) {
                $key = sanitize_text_field( $type_key );
                $clean_types[ $key ] = [
                    'enabled'  => ! empty( $type_data['enabled'] ),
                    'modes'    => array_filter( array_map( 'sanitize_key', (array) ( $type_data['modes'] ?? [] ) ) ),
                    'criteria' => \SaltHareket\Reviews\ReviewsSettings::normalizeCriteria( (array) ( $type_data['criteria'] ?? [] ) ),
                ];
            }
            $clean['types'] = $clean_types;
        }

        \SaltHareket\Reviews\ReviewsSettings::saveAll( $clean );

        // Criteria label'larını translates.json'a ekle — Loco extract için
        self::syncCriteriaLabelsToTranslates( $clean );

        wp_redirect( admin_url( 'admin.php?page=sh-reviews&tab=settings&saved=1' ) );
        exit;
    }

    /**
     * Criteria label'larını translates.json'a ekle — Loco translate extract'inin
     * yakalayamadığı dinamik stringleri dosyaya yaz.
     */
    private static function syncCriteriaLabelsToTranslates( array $settings ): void
    {
        $translates_path = get_template_directory() . '/theme/static/data/translates.json';
        if ( ! file_exists( $translates_path ) ) return;

        $existing = json_decode( file_get_contents( $translates_path ), true );
        if ( ! is_array( $existing ) ) return;

        $new_labels = [];

        // rating.criteria'daki tüm label'ları topla
        $criteria_groups = $settings['rating']['criteria'] ?? [];
        $all_rows = array_merge(
            $criteria_groups['default']    ?? [],
            ...array_values( $criteria_groups['post_types'] ?? [] ),
            ...array_values( $criteria_groups['user_roles']  ?? [] )
        );
        foreach ( $all_rows as $row ) {
            $label = trim( $row['label'] ?? '' );
            if ( $label ) $new_labels[] = $label;
        }

        // types config'deki label'lar (varsa)
        foreach ( $settings['types'] ?? [] as $type_cfg ) {
            foreach ( $type_cfg['criteria'] ?? [] as $row ) {
                $label = trim( $row['label'] ?? '' );
                if ( $label ) $new_labels[] = $label;
            }
        }

        $added = false;
        foreach ( array_unique( $new_labels ) as $label ) {
            if ( ! in_array( $label, $existing, true ) ) {
                $existing[] = $label;
                $added = true;
            }
        }

        if ( $added ) {
            sort( $existing );
            file_put_contents( $translates_path, json_encode( $existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
        }
    }

    private static function renderStyles(): void
    {
        ?>
        <style>
        .sh-wrap{--ts-primary:#2271b1;--ts-primary-hover:#135e96;--ts-success:#00a32a;--ts-danger:#d63638;--ts-warning:#dba617;--ts-gray-50:#f6f7f7;--ts-gray-100:#f0f0f1;--ts-gray-200:#dcdcde;--ts-gray-300:#c3c4c7;--ts-gray-400:#a7aaad;--ts-gray-500:#8c8f94;--ts-gray-600:#646970;--ts-gray-700:#50575e;--ts-gray-800:#3c434a;--ts-gray-900:#2c3338;--ts-white:#fff;--ts-shadow-sm:0 1px 2px rgba(0,0,0,.05);--ts-shadow:0 1px 3px rgba(0,0,0,.1);--ts-radius:4px;--ts-radius-lg:8px;--ts-transition:all .2s ease}
        .sh-wrap{margin:20px 20px 0 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;color:var(--ts-gray-900)}
        .sh-wrap *{box-sizing:border-box}
        .sh-toolbar{position:sticky;top:32px;z-index:100;display:flex;align-items:center;gap:10px;padding:14px 20px;margin-bottom:24px;background:var(--ts-white);border-radius:var(--ts-radius-lg);box-shadow:var(--ts-shadow)}
        .sh-toolbar h1{margin:0;font-size:20px;font-weight:600;flex-shrink:0}
        .sh-toolbar-right{margin-left:auto;display:flex;gap:6px}
        .sh-tab-btn{display:inline-flex;align-items:center;padding:6px 14px;border-radius:var(--ts-radius);font-size:13px;font-weight:500;color:var(--ts-gray-700);text-decoration:none;border:1px solid transparent;transition:var(--ts-transition)}
        .sh-tab-btn:hover{background:var(--ts-gray-50);color:var(--ts-gray-900)}
        .sh-tab-btn.active{background:var(--ts-primary);color:var(--ts-white);border-color:var(--ts-primary)}
        .sh-badge{display:inline-flex;align-items:center;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600}
        .sh-badge-blue{background:#dbeafe;color:#1d4ed8}
        .sh-badge-orange{background:#fef3c7;color:#d97706}
        .sh-badge-green{background:#dcfce7;color:#15803d}
        .sh-badge-gray{background:var(--ts-gray-100);color:var(--ts-gray-600)}
        .sh-filter-bar{display:flex;align-items:center;gap:10px;padding:10px 16px;margin-bottom:16px;background:var(--ts-white);border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius-lg);box-shadow:var(--ts-shadow-sm);flex-wrap:wrap}
        .sh-input{border:1px solid var(--ts-gray-300);border-radius:var(--ts-radius);padding:7px 10px;font-size:13px;color:var(--ts-gray-800);background:var(--ts-white);transition:var(--ts-transition)}
        .sh-input:focus{border-color:var(--ts-primary);outline:none;box-shadow:0 0 0 1px var(--ts-primary)}
        .sh-select{border:1px solid var(--ts-gray-300);border-radius:var(--ts-radius);padding:7px 10px;font-size:13px;color:var(--ts-gray-800);background:var(--ts-white)}
        .sh-btn{display:inline-flex;align-items:center;padding:8px 16px;border-radius:var(--ts-radius);font-size:13px;font-weight:500;cursor:pointer;border:1px solid transparent;transition:var(--ts-transition)}
        .sh-btn-sm{padding:5px 12px;font-size:12px}
        .sh-btn-primary{background:var(--ts-primary);color:var(--ts-white);border-color:var(--ts-primary)}
        .sh-btn-primary:hover{background:var(--ts-primary-hover);color:var(--ts-white)}
        .sh-btn-ghost{background:var(--ts-white);color:var(--ts-gray-700);border-color:var(--ts-gray-300)}
        .sh-btn-ghost:hover{background:var(--ts-gray-50)}
        .sh-btn-danger{background:var(--ts-danger);color:var(--ts-white);border-color:var(--ts-danger)}
        .sh-count-label{font-size:12px;color:var(--ts-gray-400)}
        .sh-bulk-bar{display:flex;align-items:center;gap:10px;padding:10px 16px;margin-bottom:12px;background:#fef9c3;border:1px solid #fde68a;border-radius:var(--ts-radius-lg);font-size:13px;font-weight:500}
        .sh-reviews-list{display:flex;flex-direction:column;gap:8px}
        .sh-review-card{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:var(--ts-white);border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius-lg);box-shadow:var(--ts-shadow-sm);transition:var(--ts-transition)}
        .sh-review-card:hover{box-shadow:var(--ts-shadow)}
        .sh-review-check{padding-top:2px}
        .sh-review-avatar img{border-radius:50%;width:40px;height:40px}
        .sh-review-body{flex:1;min-width:0}
        .sh-review-header{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
        .sh-review-author{font-size:13px;color:var(--ts-gray-900)}
        .sh-review-stars{color:#f59e0b;font-size:14px;letter-spacing:1px}
        .sh-review-target{font-size:12px;color:var(--ts-gray-500)}
        .sh-review-date{font-size:11px;color:var(--ts-gray-400);margin-left:auto}
        .sh-review-content{font-size:13px;color:var(--ts-gray-700);line-height:1.5}
        .sh-review-actions{display:flex;gap:6px;flex-shrink:0}
        .sh-rule-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--ts-radius);border:1px solid var(--ts-gray-200);background:var(--ts-white);cursor:pointer;color:var(--ts-gray-500);transition:var(--ts-transition)}
        .sh-rule-btn:hover{border-color:var(--ts-gray-300);color:var(--ts-gray-800);background:var(--ts-gray-50)}
        .sh-rule-btn .dashicons{font-size:16px;width:16px;height:16px;line-height:1}
        .sh-rule-btn-edit:hover{border-color:var(--ts-success);color:var(--ts-success);background:#dcfce7}
        .sh-rule-btn-test:hover{border-color:var(--ts-warning);color:var(--ts-warning);background:#fef9c3}
        .sh-rule-btn-delete:hover{border-color:var(--ts-danger);color:var(--ts-danger);background:#fee2e2}
        .sh-empty-box{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;background:var(--ts-gray-50);border:2px dashed var(--ts-gray-300);border-radius:12px;text-align:center}
        .sh-pagination{display:flex;gap:4px;margin-top:16px;justify-content:center}
        .sh-page-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:var(--ts-radius);border:1px solid var(--ts-gray-200);background:var(--ts-white);font-size:13px;color:var(--ts-gray-700);text-decoration:none;transition:var(--ts-transition)}
        .sh-page-btn:hover{background:var(--ts-gray-50)}
        .sh-page-btn.active{background:var(--ts-primary);color:var(--ts-white);border-color:var(--ts-primary)}
        #sh-toast{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
        .sh-toast-item{background:var(--ts-gray-900);color:var(--ts-white);padding:10px 18px;border-radius:var(--ts-radius-lg);font-size:13px;box-shadow:0 4px 6px rgba(0,0,0,.1);animation:shToastIn .2s ease}
        .sh-toast-success{background:var(--ts-success)}
        .sh-toast-error{background:var(--ts-danger)}
        @keyframes shToastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        @keyframes sh-spin{to{transform:rotate(360deg)}}
        .sh-toggle{position:relative;display:inline-block;width:36px;height:20px;flex-shrink:0}
        .sh-toggle input{opacity:0;width:0;height:0}
        .sh-toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#c3c4c7;border-radius:20px;transition:.3s}
        .sh-toggle-slider:before{position:absolute;content:"";height:14px;width:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
        .sh-toggle input:checked + .sh-toggle-slider{background:#2271b1}
        .sh-toggle input:checked + .sh-toggle-slider:before{transform:translateX(16px)}
        .sh-settings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:8px}
        .sh-settings-card{background:#fff;border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius-lg);padding:20px;box-shadow:var(--ts-shadow-sm)}
        .sh-settings-card-wide{grid-column:1/-1}
        .sh-settings-card h3{margin:0 0 16px;font-size:14px;font-weight:700;color:var(--ts-gray-800);padding-bottom:10px;border-bottom:1px solid var(--ts-gray-100)}
        .sh-settings-field{margin-bottom:12px}
        .sh-settings-field label{display:block;font-size:12px;font-weight:600;color:var(--ts-gray-600);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px}
        .sh-settings-field label small{font-weight:400;text-transform:none;letter-spacing:0;color:var(--ts-gray-400)}
        .sh-settings-toggle{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--ts-gray-50)}
        .sh-settings-toggle:last-child{border-bottom:none}
        .sh-settings-toggle-label{font-size:13px;color:var(--ts-gray-800);flex:1}
        .sh-rating-top{display:grid;grid-template-columns:200px 120px 1fr;gap:16px;align-items:start;margin-bottom:16px}
        .sh-rating-top .sh-settings-field{margin-bottom:0}
        .sh-rating-toggles{display:flex;flex-direction:column;gap:0}
        .sh-input-sm{width:80px!important}
        .sh-notice{padding:10px 16px;border-radius:var(--ts-radius);margin-bottom:16px;font-size:13px}
        .sh-notice-success{background:#dcfce7;color:#15803d;border:1px solid #86efac}
        .sh-criteria-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:12px}
        .sh-criteria-tab{padding:5px 12px;border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius);background:var(--ts-white);font-size:12px;cursor:pointer;transition:var(--ts-transition);color:var(--ts-gray-700)}
        .sh-criteria-tab:hover{background:var(--ts-gray-50)}
        .sh-criteria-tab.active{background:var(--ts-primary);color:#fff;border-color:var(--ts-primary)}
        .sh-criteria-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:rgba(255,255,255,.3);border-radius:10px;font-size:10px;font-weight:700;margin-left:5px}
        .sh-criteria-tab:not(.active) .sh-criteria-tab-count{background:var(--ts-primary);color:#fff}
        .sh-criteria-repeater{display:flex;flex-direction:column;gap:6px;margin-bottom:10px;min-height:40px}
        .sh-criteria-row{display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--ts-gray-50);border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius);transition:var(--ts-transition)}
        .sh-criteria-row:hover{border-color:var(--ts-gray-300);background:#fff}
        .sh-criteria-row.sh-dragging{opacity:.5;border-style:dashed}
        .sh-criteria-row.sh-drag-over{border-color:var(--ts-primary);background:#dbeafe}
        .sh-criteria-drag{cursor:grab;color:var(--ts-gray-400);font-size:16px;flex-shrink:0;user-select:none}
        .sh-criteria-drag:active{cursor:grabbing}
        .sh-criteria-key{width:140px!important;font-family:Consolas,monospace;font-size:12px}
        .sh-criteria-label{flex:1}
        .sh-criteria-weight-wrap{display:flex;align-items:center;gap:4px;flex-shrink:0}
        .sh-criteria-weight-wrap>span{font-size:11px;color:var(--ts-gray-500);white-space:nowrap}
        .sh-criteria-weight{width:60px!important;text-align:center}
        .sh-criteria-remove{background:none;border:none;color:var(--ts-gray-400);cursor:pointer;font-size:14px;padding:2px 6px;border-radius:var(--ts-radius);transition:var(--ts-transition);flex-shrink:0}
        .sh-criteria-remove:hover{color:var(--ts-danger);background:#fee2e2}
        .sh-criteria-empty{font-size:12px;color:var(--ts-gray-400);padding:12px;text-align:center;border:2px dashed var(--ts-gray-200);border-radius:var(--ts-radius)}
        .sh-criteria-add-btn{margin-top:4px}
        @media(max-width:1200px){.sh-settings-grid{grid-template-columns:repeat(2,1fr)}.sh-rating-top{grid-template-columns:1fr 1fr}}
        @media(max-width:782px){.sh-settings-grid{grid-template-columns:1fr}.sh-rating-top{grid-template-columns:1fr}}
        </style>
        <?php
    }
}
