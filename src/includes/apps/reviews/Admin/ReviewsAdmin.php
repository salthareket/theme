<?php

namespace SaltHareket\Reviews\Admin;

use SaltHareket\Reviews\Reviews;

/**
 * ReviewsAdmin
 * ACF-free admin sayfası — sticky toolbar, filter, bulk işlemler.
 * PAE / SearchHistory / NotificationsAdmin ile aynı tasarım dili.
 *
 * @version 1.0.0
 */
class ReviewsAdmin
{
    public static function register(): void
    {
        add_action( 'admin_menu', [ self::class, 'addMenuPage' ], 20 );
        add_action( 'admin_head', [ self::class, 'hideNotices' ] );
        add_action( 'admin_post_sh_reviews_save_settings', [ self::class, 'saveSettings' ] );
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            'theme-settings',
            '⭐ Reviews',
            '⭐ Reviews',
            'moderate_comments',
            'sh-reviews',
            [ self::class, 'renderPage' ]
        );
    }

    public static function hideNotices(): void
    {
        if ( ( $_GET['page'] ?? '' ) !== 'sh-reviews' ) return;
        echo '<style>body .notice:not(.sh-inline),body .updated:not(.sh-inline),body .error:not(.sh-inline){display:none!important}</style>';
    }

    public static function renderPage(): void
    {
        if ( ! current_user_can( 'moderate_comments' ) ) wp_die( 'Unauthorized' );

        $tab      = sanitize_key( $_GET['tab'] ?? 'all' );

        // Settings tab ayrı render
        if ( $tab === 'settings' ) {
            self::renderSettingsPage();
            return;
        }

        $search   = sanitize_text_field( $_GET['s'] ?? '' );
        $rating   = isset( $_GET['rating'] ) ? (int) $_GET['rating'] : null;
        $verified = isset( $_GET['verified'] ) ? (bool) $_GET['verified'] : null;
        $page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $nonce    = wp_create_nonce( 'sh_reviews_nonce' );
        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );

        $reviews_obj = new Reviews();

        $args = [
            'page'     => $page,
            'per_page' => 20,
            'status'   => match ( $tab ) {
                'pending' => 'hold',
                'spam'    => 'spam',
                default   => 'approve',
            },
        ];
        if ( $rating !== null )   $args['rating']   = $rating;
        if ( $verified !== null ) $args['verified']  = $verified;

        // Tüm review'lar (post + user)
        global $wpdb;
        $where = "c.comment_type = 'review'";
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

        // Tab counts
        $count_all     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '1'" ); // phpcs:ignore
        $count_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved = '0'" ); // phpcs:ignore

        ?>
        <div class="sh-wrap" id="sh-reviews-page">
        <?php self::renderStyles(); ?>

        <div class="sh-toolbar">
            <h1>⭐ Reviews</h1>
            <span class="sh-badge sh-badge-blue"><?php echo $count_all; ?> approved</span>
            <?php if ( $count_pending > 0 ) : ?>
                <span class="sh-badge sh-badge-orange"><?php echo $count_pending; ?> pending</span>
            <?php endif; ?>
            <div class="sh-toolbar-right">
                <?php foreach ( [ 'all' => 'Approved', 'pending' => 'Pending', 'spam' => 'Spam' ] as $t => $label ) : ?>
                    <a href="?page=sh-reviews&tab=<?php echo $t; ?>" class="sh-tab-btn <?php echo $tab === $t ? 'active' : ''; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
                <a href="?page=sh-reviews&tab=settings" class="sh-tab-btn <?php echo $tab === 'settings' ? 'active' : ''; ?>">⚙ Settings</a>
            </div>
        </div>

        <div class="sh-filter-bar">
            <form method="get" style="display:contents">
                <input type="hidden" name="page" value="sh-reviews">
                <input type="hidden" name="tab"  value="<?php echo esc_attr( $tab ); ?>">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search reviews..." class="sh-input" style="max-width:220px">
                <select name="rating" class="sh-select" style="width:auto">
                    <option value="">All Ratings</option>
                    <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                        <option value="<?php echo $i; ?>" <?php selected( $rating, $i ); ?>><?php echo str_repeat( '★', $i ); ?></option>
                    <?php endfor; ?>
                </select>
                <select name="verified" class="sh-select" style="width:auto">
                    <option value="">All</option>
                    <option value="1" <?php selected( $verified, true ); ?>>Verified</option>
                    <option value="0" <?php selected( $verified, false ); ?>>Unverified</option>
                </select>
                <button type="submit" class="sh-btn sh-btn-primary">Filter</button>
                <?php if ( $search || $rating || $verified !== null ) : ?>
                    <a href="?page=sh-reviews&tab=<?php echo esc_attr( $tab ); ?>" class="sh-btn sh-btn-ghost">Clear</a>
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
            $rating_val  = (int) $row->rating;
            $verified    = (bool) $row->verified;
            $helpful     = (int) $row->helpful_score;
            $avatar      = get_avatar( $row->comment_author_email, 40, 'mystery', $row->comment_author );
            $post_title  = $row->comment_post_ID ? get_the_title( $row->comment_post_ID ) : '—';
            $profile_id  = (int) get_comment_meta( $row->comment_ID, 'comment_profile', true );
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
                        <span class="sh-review-stars"><?php echo str_repeat( '★', $rating_val ) . str_repeat( '☆', 5 - $rating_val ); ?></span>
                        <?php if ( $verified ) : ?>
                            <span class="sh-badge sh-badge-green" title="Verified">✓ Verified</span>
                        <?php endif; ?>
                        <?php if ( $helpful > 0 ) : ?>
                            <span class="sh-badge sh-badge-gray" title="Helpful score">👍 <?php echo $helpful; ?></span>
                        <?php endif; ?>
                        <span class="sh-review-target">→ <?php echo esc_html( $target_label ); ?></span>
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
            <?php for ( $i = 1; $i <= $page_total; $i++ ) : ?>
                <a href="?page=sh-reviews&tab=<?php echo esc_attr( $tab ); ?>&paged=<?php echo $i; ?><?php echo $search ? '&s=' . urlencode( $search ) : ''; ?>"
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
            .then(r => r.json())
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
            var ids     = Array.from(checked).map(c => c.closest('.sh-review-card').dataset.id);
            if (!ids.length) return;
            if (action === 'delete' && !confirm('Delete ' + ids.length + ' reviews?')) return;
            fetch(SH_AJAX, {
                method: 'POST',
                body: new URLSearchParams({ action: 'sh_review_bulk', bulk_action: action, 'ids[]': ids, _wpnonce: SH_NONCE })
            })
            .then(r => r.json())
            .then(function(res) {
                if (res.success) {
                    checked.forEach(c => c.closest('.sh-review-card').remove());
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
            setTimeout(() => t.remove(), 3000);
        }
        </script>
        <?php
    }

    // =========================================================================
    // SETTINGS PAGE
    // =========================================================================

    private static function renderSettingsPage(): void
    {
        $s   = \SaltHareket\Reviews\ReviewsSettings::all();
        $url = esc_url( admin_url( 'admin-post.php' ) );
        ?>
        <div class="sh-wrap" id="sh-reviews-page">
        <?php self::renderStyles(); ?>

        <div class="sh-toolbar">
            <h1>⭐ Reviews</h1>
            <div class="sh-toolbar-right">
                <?php foreach ( [ 'all' => 'Approved', 'pending' => 'Pending', 'spam' => 'Spam' ] as $t => $label ) : ?>
                    <a href="?page=sh-reviews&tab=<?php echo $t; ?>" class="sh-tab-btn"><?php echo $label; ?></a>
                <?php endforeach; ?>
                <a href="?page=sh-reviews&tab=settings" class="sh-tab-btn active">⚙ Settings</a>
            </div>
        </div>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="sh-notice sh-notice-success sh-inline">✓ Settings saved.</div>
        <?php endif; ?>

        <form method="post" action="<?php echo $url; ?>">
            <input type="hidden" name="action" value="sh_reviews_save_settings">
            <?php wp_nonce_field( 'sh_reviews_save_settings' ); ?>

            <div class="sh-settings-grid">

                <?php /* ── General ── */ ?>
                <div class="sh-settings-card">
                    <h3>General</h3>
                    <?php self::renderToggle( 'general[enable_reviews]',         'Enable Reviews',                    $s['general']['enable_reviews'] ); ?>
                    <?php self::renderToggle( 'general[disable_review_approve]', 'Disable Review Approve',            $s['general']['disable_review_approve'] ?? false, 'Açıkken yorumlar admin onayı olmadan direkt yayınlanır' ); ?>
                    <?php self::renderToggle( 'general[auto_approve_reviews]',   'Auto-approve all reviews',          $s['general']['auto_approve_reviews'] ); ?>
                    <?php self::renderToggle( 'general[auto_approve_verified]',  'Auto-approve verified users',       $s['general']['auto_approve_verified'] ); ?>
                    <?php self::renderToggle( 'general[auto_approve_trusted]',   'Auto-approve trusted users',        $s['general']['auto_approve_trusted'] ); ?>
                    <div class="sh-settings-field">
                        <label>Trusted threshold <small>(min approved reviews)</small></label>
                        <input type="number" name="general[trusted_threshold]" value="<?php echo (int) $s['general']['trusted_threshold']; ?>" min="1" max="50" class="sh-input sh-input-sm">
                    </div>
                    <?php self::renderToggle( 'general[require_login]',          'Require login to review',           $s['general']['require_login'] ); ?>
                    <?php self::renderToggle( 'general[one_review_per_user]',    'One review per user',               $s['general']['one_review_per_user'] ); ?>
                </div>

                <?php /* ── Replies ── */ ?>
                <div class="sh-settings-card">
                    <h3>Replies</h3>
                    <?php self::renderToggle( 'reply[enable_replies]',           'Enable replies',                          $s['reply']['enable_replies'] ); ?>
                    <?php self::renderToggle( 'reply[auto_approve_owner_reply]', 'Auto-approve content owner reply',        $s['reply']['auto_approve_owner_reply'] ); ?>
                    <?php self::renderToggle( 'reply[user_approves_reply]',      'User approves replies to their reviews',  $s['reply']['user_approves_reply'] ); ?>
                    <div class="sh-settings-field">
                        <label>Max reply depth</label>
                        <input type="number" name="reply[max_depth]" value="<?php echo (int) $s['reply']['max_depth']; ?>" min="1" max="5" class="sh-input sh-input-sm">
                    </div>
                </div>

                <?php /* ── Helpful ── */ ?>
                <div class="sh-settings-card">
                    <h3>Helpful Votes</h3>
                    <?php self::renderToggle( 'helpful[enable_helpful]',        'Enable helpful votes',       $s['helpful']['enable_helpful'] ); ?>
                    <div class="sh-settings-field">
                        <label>Algorithm</label>
                        <select name="helpful[algorithm]" class="sh-select">
                            <option value="simple" <?php selected( $s['helpful']['algorithm'], 'simple' ); ?>>Simple (helpful - unhelpful)</option>
                            <option value="wilson" <?php selected( $s['helpful']['algorithm'], 'wilson' ); ?>>Wilson Score (Reddit-style)</option>
                        </select>
                    </div>
                    <?php self::renderToggle( 'helpful[require_login_to_vote]', 'Require login to vote',      $s['helpful']['require_login_to_vote'] ); ?>
                </div>

                <?php /* ── Media ── */ ?>
                <div class="sh-settings-card">
                    <h3>Media</h3>
                    <?php self::renderToggle( 'media[enable_media]', 'Allow photo uploads', $s['media']['enable_media'] ); ?>
                    <div class="sh-settings-field">
                        <label>Max images per review</label>
                        <input type="number" name="media[max_images]" value="<?php echo (int) $s['media']['max_images']; ?>" min="1" max="20" class="sh-input sh-input-sm">
                    </div>
                    <div class="sh-settings-field">
                        <label>Max image size (MB)</label>
                        <input type="number" name="media[max_image_size]" value="<?php echo (int) $s['media']['max_image_size']; ?>" min="1" max="20" class="sh-input sh-input-sm">
                    </div>
                </div>

                <?php /* ── Notifications ── */ ?>
                <div class="sh-settings-card">
                    <h3>Notifications</h3>
                    <?php self::renderToggle( 'notifications[notify_on_new_review]', 'Notify on new review', $s['notifications']['notify_on_new_review'] ); ?>
                    <?php self::renderToggle( 'notifications[notify_on_reply]',      'Notify on reply',      $s['notifications']['notify_on_reply'] ); ?>
                    <?php self::renderToggle( 'notifications[notify_on_approve]',    'Notify on approve',    $s['notifications']['notify_on_approve'] ); ?>
                </div>

                <?php /* ── Rating — en alta, tam genişlik ── */ ?>
                <div class="sh-settings-card sh-settings-card-wide">
                    <h3>Rating</h3>

                    <div class="sh-rating-top">
                        <div class="sh-settings-field">
                            <label>Rating Type</label>
                            <select name="rating[type]" class="sh-select" id="sh-rating-type-select" onchange="shRatingTypeChange(this.value)">
                                <?php foreach ( [ 'stars' => '⭐ Stars', 'thumbs' => '👍 Thumbs', 'nps' => '📊 NPS (0-10)', 'multi' => '🎯 Multi-criteria' ] as $val => $label ) : ?>
                                    <option value="<?php echo $val; ?>" <?php selected( $s['rating']['type'], $val ); ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sh-settings-field" id="sh-max-rating-wrap" <?php echo in_array( $s['rating']['type'], ['thumbs','nps'] ) ? 'style="display:none"' : ''; ?>>
                            <label>Max Rating</label>
                            <select name="rating[max_rating]" class="sh-select">
                                <option value="5"  <?php selected( $s['rating']['max_rating'], 5 ); ?>>5</option>
                                <option value="10" <?php selected( $s['rating']['max_rating'], 10 ); ?>>10</option>
                            </select>
                        </div>

                        <div class="sh-rating-toggles">
                            <?php self::renderToggle( 'rating[allow_half]',     'Allow half-star (e.g. 4.5)', $s['rating']['allow_half'] ); ?>
                            <?php self::renderToggle( 'rating[show_breakdown]', 'Show rating breakdown',      $s['rating']['show_breakdown'] ); ?>
                        </div>
                    </div>

                    <?php /* ── Multi-criteria repeater ── */ ?>
                    <div id="sh-criteria-section" <?php echo $s['rating']['type'] !== 'multi' ? 'style="display:none"' : ''; ?>>
                        <hr style="margin:16px 0;border-color:var(--ts-gray-100)">
                        <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;color:var(--ts-gray-800)">
                            Criteria
                            <small style="font-weight:400;color:var(--ts-gray-400);font-size:11px">— per post type</small>
                        </h4>

                        <?php
                        $criteria_data  = $s['rating']['criteria'] ?? [];
                        $all_post_types = get_post_types( [ 'public' => true ], 'objects' );
                        $tabs           = [ 'default' => 'Default (All)' ];
                        foreach ( $all_post_types as $pt ) {
                            if ( in_array( $pt->name, [ 'attachment' ], true ) ) continue;
                            $tabs[ $pt->name ] = $pt->label;
                        }
                        $active_pt = array_key_first( $tabs );
                        ?>

                        <div class="sh-criteria-tabs">
                            <?php foreach ( $tabs as $pt_key => $pt_label ) :
                                // Kriter sayısını hesapla
                                $tab_rows = $pt_key === 'default'
                                    ? ( $criteria_data['default'] ?? [] )
                                    : ( $criteria_data['post_types'][ $pt_key ] ?? [] );
                                $tab_count = count( $tab_rows );
                            ?>
                                <button type="button"
                                        class="sh-criteria-tab <?php echo $pt_key === $active_pt ? 'active' : ''; ?>"
                                        onclick="shCriteriaTab('<?php echo esc_attr( $pt_key ); ?>', this)">
                                    <?php echo esc_html( $pt_label ); ?>
                                    <?php if ( $tab_count > 0 ) : ?>
                                        <span class="sh-criteria-tab-count"><?php echo $tab_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ( $tabs as $pt_key => $pt_label ) :
                            $field_prefix = $pt_key === 'default'
                                ? 'rating[criteria][default]'
                                : 'rating[criteria][post_types][' . $pt_key . ']';

                            $rows = $pt_key === 'default'
                                ? ( $criteria_data['default'] ?? [] )
                                : ( $criteria_data['post_types'][ $pt_key ] ?? [] );
                        ?>
                        <div class="sh-criteria-panel" id="sh-criteria-panel-<?php echo esc_attr( $pt_key ); ?>"
                             style="<?php echo $pt_key !== $active_pt ? 'display:none' : ''; ?>">

                            <div class="sh-criteria-repeater" id="sh-repeater-<?php echo esc_attr( $pt_key ); ?>">
                                <?php if ( empty( $rows ) ) : ?>
                                <div class="sh-criteria-empty">
                                    No criteria defined. Click "+ Add Criterion" to start.
                                </div>
                                <?php endif; ?>
                                <?php foreach ( $rows as $i => $row ) : ?>
                                <div class="sh-criteria-row" draggable="true">
                                    <span class="sh-criteria-drag" title="Drag to reorder">☰</span>
                                    <input type="text"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[<?php echo $i; ?>][key]"
                                           value="<?php echo esc_attr( $row['key'] ?? '' ); ?>"
                                           placeholder="key (e.g. quality)"
                                           class="sh-input sh-criteria-key"
                                           oninput="this.value=this.value.replace(/[^a-z0-9_]/g,'')">
                                    <input type="text"
                                           name="<?php echo esc_attr( $field_prefix ); ?>[<?php echo $i; ?>][label]"
                                           value="<?php echo esc_attr( $row['label'] ?? '' ); ?>"
                                           placeholder="Label (e.g. Quality)"
                                           class="sh-input sh-criteria-label">
                                    <div class="sh-criteria-weight-wrap" title="Weight — how much this criterion affects the overall score (1.0 = normal)">
                                        <span>Weight</span>
                                        <input type="number"
                                               name="<?php echo esc_attr( $field_prefix ); ?>[<?php echo $i; ?>][weight]"
                                               value="<?php echo esc_attr( $row['weight'] ?? 1.0 ); ?>"
                                               step="0.1" min="0.1" max="5"
                                               class="sh-input sh-criteria-weight">
                                    </div>
                                    <button type="button" class="sh-criteria-remove" onclick="shCriteriaRemove(this)" title="Remove">✕</button>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" class="sh-btn sh-btn-ghost sh-btn-sm sh-criteria-add-btn"
                                    onclick="shCriteriaAdd('<?php echo esc_attr( $pt_key ); ?>', '<?php echo esc_attr( $field_prefix ); ?>')">
                                + Add Criterion
                            </button>

                            <?php if ( $pt_key !== 'default' ) : ?>
                            <p style="font-size:11px;color:var(--ts-gray-400);margin:6px 0 0">
                                Leave empty to use Default criteria for this post type.
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <div style="margin-top:20px">
                <button type="submit" class="sh-btn sh-btn-primary">Save Settings</button>
                <a href="?page=sh-reviews&tab=settings&reset=1" class="sh-btn sh-btn-ghost"
                   onclick="return confirm('Reset all settings to defaults?')">Reset to Defaults</a>
            </div>
        </form>

        <style>
        .sh-settings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:8px}
        .sh-settings-card{background:#fff;border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius-lg);padding:20px;box-shadow:var(--ts-shadow-sm)}
        .sh-settings-card-wide{grid-column:1/-1}
        .sh-settings-card h3{margin:0 0 16px;font-size:14px;font-weight:700;color:var(--ts-gray-800);padding-bottom:10px;border-bottom:1px solid var(--ts-gray-100)}
        .sh-settings-card h4{margin:0 0 8px;font-size:13px;font-weight:700;color:var(--ts-gray-800)}
        .sh-settings-field{margin-bottom:12px}
        .sh-settings-field label{display:block;font-size:12px;font-weight:600;color:var(--ts-gray-600);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px}
        .sh-settings-field label small{font-weight:400;text-transform:none;letter-spacing:0;color:var(--ts-gray-400)}
        /* Toggle — başta toggle, sonda label */
        .sh-settings-toggle{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--ts-gray-50)}
        .sh-settings-toggle:last-child{border-bottom:none}
        .sh-settings-toggle-label{font-size:13px;color:var(--ts-gray-800);flex:1}
        /* Rating card inner layout */
        .sh-rating-top{display:grid;grid-template-columns:200px 120px 1fr;gap:16px;align-items:start;margin-bottom:16px}
        .sh-rating-top .sh-settings-field{margin-bottom:0}
        .sh-rating-toggles{display:flex;flex-direction:column;gap:0}
        .sh-input-sm{width:80px!important}
        .sh-notice{padding:10px 16px;border-radius:var(--ts-radius);margin-bottom:16px;font-size:13px}
        .sh-notice-success{background:#dcfce7;color:#15803d;border:1px solid #86efac}
        /* Criteria tabs */
        .sh-criteria-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:12px}
        .sh-criteria-tab{padding:5px 12px;border:1px solid var(--ts-gray-200);border-radius:var(--ts-radius);background:var(--ts-white);font-size:12px;cursor:pointer;transition:var(--ts-transition);color:var(--ts-gray-700)}
        .sh-criteria-tab:hover{background:var(--ts-gray-50)}
        .sh-criteria-tab.active{background:var(--ts-primary);color:#fff;border-color:var(--ts-primary)}
        .sh-criteria-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:rgba(255,255,255,.3);border-radius:10px;font-size:10px;font-weight:700;margin-left:5px}
        .sh-criteria-tab:not(.active) .sh-criteria-tab-count{background:var(--ts-primary);color:#fff}
        /* Criteria repeater */
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

        <script>
        // Rating type change
        function shRatingTypeChange(val) {
            document.getElementById('sh-criteria-section').style.display = val === 'multi' ? '' : 'none';
            var maxWrap = document.getElementById('sh-max-rating-wrap');
            if (maxWrap) maxWrap.style.display = ['thumbs','nps'].includes(val) ? 'none' : '';
        }

        // Criteria tabs
        function shCriteriaTab(ptKey, btn) {
            document.querySelectorAll('.sh-criteria-tab').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.sh-criteria-panel').forEach(p => p.style.display = 'none');
            btn.classList.add('active');
            document.getElementById('sh-criteria-panel-' + ptKey).style.display = '';
        }

        // Add criterion row
        function shCriteriaAdd(ptKey, prefix) {
            var repeater = document.getElementById('sh-repeater-' + ptKey);
            // Remove empty state
            var empty = repeater.querySelector('.sh-criteria-empty');
            if (empty) empty.remove();

            var idx = repeater.querySelectorAll('.sh-criteria-row').length;
            var row = document.createElement('div');
            row.className = 'sh-criteria-row';
            row.draggable = true;
            row.innerHTML =
                '<span class="sh-criteria-drag" title="Drag to reorder">☰</span>' +
                '<input type="text" name="' + prefix + '[' + idx + '][key]" placeholder="key (e.g. quality)" class="sh-input sh-criteria-key" oninput="this.value=this.value.replace(/[^a-z0-9_]/g,\'\')">' +
                '<input type="text" name="' + prefix + '[' + idx + '][label]" placeholder="Label (e.g. Quality)" class="sh-input sh-criteria-label">' +
                '<div class="sh-criteria-weight-wrap" title="Weight — how much this criterion affects the overall score (1.0 = normal)"><span>Weight</span>' +
                '<input type="number" name="' + prefix + '[' + idx + '][weight]" value="1" step="0.1" min="0.1" max="5" class="sh-input sh-criteria-weight"></div>' +
                '<button type="button" class="sh-criteria-remove" onclick="shCriteriaRemove(this)" title="Remove">✕</button>';
            repeater.appendChild(row);
            shInitDrag(row);
            row.querySelector('.sh-criteria-key').focus();
        }

        // Remove criterion row
        function shCriteriaRemove(btn) {
            var row      = btn.closest('.sh-criteria-row');
            var repeater = row.closest('.sh-criteria-repeater');
            row.remove();
            shReindex(repeater);
            if (!repeater.querySelector('.sh-criteria-row')) {
                repeater.innerHTML = '<div class="sh-criteria-empty">No criteria defined. Click "+ Add Criterion" to start.</div>';
            }
        }

        // Reindex names after remove/reorder
        function shReindex(repeater) {
            repeater.querySelectorAll('.sh-criteria-row').forEach(function(row, i) {
                row.querySelectorAll('input[name]').forEach(function(inp) {
                    inp.name = inp.name.replace(/\[\d+\](\[[^\]]+\])$/, '[' + i + ']$1');
                });
            });
        }

        // Drag & drop reorder
        function shInitDrag(row) {
            row.addEventListener('dragstart', function(e) {
                e.dataTransfer.effectAllowed = 'move';
                row.classList.add('sh-dragging');
                window._shDragRow = row;
            });
            row.addEventListener('dragend', function() {
                row.classList.remove('sh-dragging');
                document.querySelectorAll('.sh-criteria-row').forEach(r => r.classList.remove('sh-drag-over'));
                shReindex(row.closest('.sh-criteria-repeater'));
            });
            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (window._shDragRow && window._shDragRow !== row) {
                    document.querySelectorAll('.sh-criteria-row').forEach(r => r.classList.remove('sh-drag-over'));
                    row.classList.add('sh-drag-over');
                }
            });
            row.addEventListener('drop', function(e) {
                e.preventDefault();
                if (window._shDragRow && window._shDragRow !== row) {
                    var rep = row.closest('.sh-criteria-repeater');
                    var rows = Array.from(rep.querySelectorAll('.sh-criteria-row'));
                    var fromIdx = rows.indexOf(window._shDragRow);
                    var toIdx   = rows.indexOf(row);
                    if (fromIdx < toIdx) {
                        row.after(window._shDragRow);
                    } else {
                        row.before(window._shDragRow);
                    }
                }
            });
        }

        // Init drag on existing rows
        document.querySelectorAll('.sh-criteria-row').forEach(shInitDrag);
        </script>

        </div>
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
                <?php if ( $desc ) : ?><br><span style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $desc ); ?></span><?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function saveSettings(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'sh_reviews_save_settings' );

        // Reset
        if ( isset( $_GET['reset'] ) ) {
            \SaltHareket\Reviews\ReviewsSettings::reset();
            wp_redirect( admin_url( 'admin.php?page=sh-reviews&tab=settings&saved=1' ) );
            exit;
        }

        $post = $_POST;

        // Criteria normalize et — repeater'dan gelen array'leri temizle
        if ( isset( $post['rating']['criteria'] ) ) {
            $raw_criteria = $post['rating']['criteria'];

            // Default criteria
            if ( isset( $raw_criteria['default'] ) && is_array( $raw_criteria['default'] ) ) {
                $post['rating']['criteria']['default'] = \SaltHareket\Reviews\ReviewsSettings::normalizeCriteria( $raw_criteria['default'] );
            } else {
                $post['rating']['criteria']['default'] = [];
            }

            // Post type bazlı criteria
            if ( isset( $raw_criteria['post_types'] ) && is_array( $raw_criteria['post_types'] ) ) {
                foreach ( $raw_criteria['post_types'] as $pt => $rows ) {
                    $normalized = \SaltHareket\Reviews\ReviewsSettings::normalizeCriteria( is_array( $rows ) ? $rows : [] );
                    $post['rating']['criteria']['post_types'][ sanitize_key( $pt ) ] = $normalized;
                }
            } else {
                $post['rating']['criteria']['post_types'] = [];
            }
        }

        // Checkbox'ları normalize et (işaretlenmeyenler POST'ta gelmiyor)
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
                $post[ $group ][ $field ] = ! empty( $post[ $group ][ $field ] );
            }
        }

        // Int fields
        $post['general']['trusted_threshold']  = max( 1, (int) ( $post['general']['trusted_threshold'] ?? 3 ) );
        $post['rating']['max_rating']          = in_array( (int) ( $post['rating']['max_rating'] ?? 5 ), [ 5, 10 ] ) ? (int) $post['rating']['max_rating'] : 5;
        $post['reply']['max_depth']            = max( 1, min( 5, (int) ( $post['reply']['max_depth'] ?? 1 ) ) );
        $post['media']['max_images']           = max( 1, min( 20, (int) ( $post['media']['max_images'] ?? 5 ) ) );
        $post['media']['max_image_size']       = max( 1, min( 20, (int) ( $post['media']['max_image_size'] ?? 5 ) ) );

        // Sadece bilinen grupları kaydet
        $allowed = [ 'general', 'rating', 'reply', 'helpful', 'media', 'notifications' ];
        $clean   = array_intersect_key( $post, array_flip( $allowed ) );

        \SaltHareket\Reviews\ReviewsSettings::saveAll( $clean );

        wp_redirect( admin_url( 'admin.php?page=sh-reviews&tab=settings&saved=1' ) );
        exit;
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
        </style>
        <?php
    }
}
