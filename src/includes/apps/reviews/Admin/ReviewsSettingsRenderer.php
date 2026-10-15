<?php
namespace SaltHareket\Reviews\Admin;

/**
 * ReviewsSettingsRenderer
 * Tab layout: General | Replies | Rating | Helpful | Media | Notifications
 *
 * @version 1.0.0
 */
class ReviewsSettingsRenderer
{
    public static function render(): void
    {
        $s    = \SaltHareket\Reviews\ReviewsSettings::all();
        $url  = esc_url( admin_url( 'admin-post.php' ) );
        $stab = sanitize_key( $_GET['stab'] ?? 'general' );
        $nonce_enable = wp_create_nonce( 'sh_reviews_nonce' );
        $ajax_url     = esc_url( admin_url( 'admin-ajax.php' ) );
        $enabled      = (bool) $s['general']['enable_reviews'];

        $criteria_data  = $s['rating']['criteria'] ?? [];
        $all_post_types = get_post_types( [ 'public' => true ], 'objects' );
        $pt_tabs        = [ 'default' => 'Default (All)' ];
        foreach ( $all_post_types as $pt ) {
            if ( $pt->name === 'attachment' ) continue;
            $pt_tabs[ $pt->name ] = $pt->label;
        }
        // User rolleri — user review criteria için
        global $wp_roles;
        $user_role_tabs = [ 'user_default' => 'User: Default (All)' ];
        foreach ( $wp_roles->roles as $slug => $role ) {
            $user_role_tabs[ 'user_role_' . $slug ] = 'User: ' . $role['name'];
        }
        $all_criteria_tabs = array_merge( $pt_tabs, $user_role_tabs );
        $active_pt = array_key_first( $pt_tabs );
        ?>
        <div class="sh-wrap" id="sh-reviews-page">

        <div class="sh-toolbar">
            <h1 style="display:inline-flex;align-items:center;gap:8px;">&#11088; Reviews
                <span class="sh-badge <?php echo $enabled ? 'sh-badge-green' : 'sh-badge-gray'; ?>" id="sh-reviews-header-badge" style="font-size:11px"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
            </h1>
            <div class="sh-toolbar-right">
                <?php foreach ( [ 'all' => 'Approved', 'pending' => 'Pending', 'spam' => 'Spam' ] as $t => $label ) : ?>
                    <a href="?page=sh-reviews&amp;tab=<?php echo $t; ?>" class="sh-tab-btn"><?php echo $label; ?></a>
                <?php endforeach; ?>
                <a href="?page=sh-reviews&amp;tab=settings" class="sh-tab-btn active">&#9881; Settings</a>
            </div>
        </div>

        <!-- Enable Toggle -->
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
            <span id="sh-reviews-enable-saving" style="display:none;font-size:12px;color:#9ca3af;margin-left:4px;">Saving...</span>
        </div>
        <script>
        function shReviewsEnableToggle(enabled) {
            var label  = document.getElementById('sh-reviews-enable-label');
            var saving = document.getElementById('sh-reviews-enable-saving');
            var toggle = document.getElementById('sh-reviews-enable-toggle');
            if (saving) saving.style.display = 'inline';
            if (label)  label.style.display  = 'none';
            var fd = new FormData();
            fd.append('action',  'sh_reviews_save_enable_toggle');
            fd.append('nonce',   <?php echo wp_json_encode( $nonce_enable ); ?>);
            fd.append('enabled', enabled ? '1' : '0');
            fetch(<?php echo wp_json_encode( $ajax_url ); ?>, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function() {
                    if (saving) saving.style.display = 'none';
                    if (label) { label.style.display=''; label.style.color=enabled?'#00a32a':'#9ca3af'; label.textContent=enabled?'Enabled':'Disabled'; }
                    setTimeout(function() { window.location.reload(); }, 400);
                })
                .catch(function() {
                    if (saving) saving.style.display = 'none';
                    if (label)  label.style.display  = '';
                    if (toggle) toggle.checked = !enabled;
                });
        }
        </script>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="sh-notice sh-notice-success sh-inline">&#10003; Settings saved.</div>
        <?php endif; ?>

        <?php if ( ! $enabled ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:32px 20px;text-align:center;color:#9ca3af;">
                <p style="margin:0 0 6px;font-weight:600;color:#6b7280;">Reviews is disabled</p>
                <p style="margin:0;font-size:12px;">Enable Reviews above to configure settings.</p>
            </div>
        <?php else : ?>

        <!-- Tab Nav -->
        <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:0;background:#fff;border:1px solid #dcdcde;border-bottom:none;border-radius:8px 8px 0 0;padding:12px 16px">
            <?php foreach ( [ 'general'=>'General','replies'=>'Replies','rating'=>'Rating','helpful'=>'Helpful','media'=>'Media','notifications'=>'Notifications','types'=>'&#127919; Types' ] as $slug => $label ) : ?>
            <button type="button"
                    class="sh-tab-btn <?php echo $stab === $slug ? 'active' : ''; ?>"
                    onclick="shrSwitchTab('<?php echo $slug; ?>')"
                    id="shr-tab-btn-<?php echo $slug; ?>"><?php echo $label; ?></button>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?php echo $url; ?>">
            <input type="hidden" name="action" value="sh_reviews_save_settings">
            <?php wp_nonce_field( 'sh_reviews_save_settings' ); ?>

            <div style="background:#fff;border:1px solid #dcdcde;border-top:none;border-radius:0 0 8px 8px;padding:24px">

            <!-- GENERAL TAB -->
            <div id="shr-stab-general" class="shr-stab-panel" style="<?php echo $stab !== 'general' ? 'display:none' : ''; ?>">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:12px">APPROVAL</div>
                <?php self::toggle( 'general[disable_review_approve]', 'Disable Review Approve', $s['general']['disable_review_approve'] ?? false, 'Reviews published directly without admin approval' ); ?>
                <?php self::toggle( 'general[auto_approve_reviews]',   'Auto-approve all reviews',       $s['general']['auto_approve_reviews'] ); ?>
                <?php self::toggle( 'general[auto_approve_verified]',  'Auto-approve verified users',    $s['general']['auto_approve_verified'] ); ?>
                <?php self::toggle( 'general[auto_approve_trusted]',   'Auto-approve trusted users',     $s['general']['auto_approve_trusted'] ); ?>
                <div class="sh-field-group" style="margin-top:12px;max-width:200px">
                    <label>Trusted threshold <small style="font-weight:400;text-transform:none">(min approved reviews)</small></label>
                    <input type="number" name="general[trusted_threshold]" value="<?php echo (int) $s['general']['trusted_threshold']; ?>" min="1" max="50" class="sh-input">
                </div>
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin:16px 0 12px">ACCESS</div>
                <?php self::toggle( 'general[require_login]',       'Require login to review', $s['general']['require_login'] ); ?>
                <?php self::toggle( 'general[one_review_per_user]', 'One review per user',     $s['general']['one_review_per_user'] ); ?>
                <div class="sh-field-group" style="margin-top:12px;max-width:240px">
                    <label>Rate limit <small style="font-weight:400;text-transform:none">(max reviews per hour per user, 0 = disabled)</small></label>
                    <input type="number" name="general[rate_limit_per_hour]" value="<?php echo (int) ( $s['general']['rate_limit_per_hour'] ?? 5 ); ?>" min="0" max="100" class="sh-input">
                </div>
            </div>

            <!-- REPLIES TAB -->
            <div id="shr-stab-replies" class="shr-stab-panel" style="<?php echo $stab !== 'replies' ? 'display:none' : ''; ?>">
                <?php self::toggle( 'reply[enable_replies]',           'Enable replies',                        $s['reply']['enable_replies'] ); ?>
                <?php self::toggle( 'reply[auto_approve_owner_reply]', 'Auto-approve content owner reply',      $s['reply']['auto_approve_owner_reply'] ); ?>
                <?php self::toggle( 'reply[user_approves_reply]',      'User approves replies to their reviews',$s['reply']['user_approves_reply'] ); ?>
                <div class="sh-field-group" style="margin-top:12px;max-width:200px">
                    <label>Max reply depth</label>
                    <input type="number" name="reply[max_depth]" value="<?php echo (int) $s['reply']['max_depth']; ?>" min="1" max="5" class="sh-input">
                </div>
            </div>

            <!-- RATING TAB -->
            <div id="shr-stab-rating" class="shr-stab-panel" style="<?php echo $stab !== 'rating' ? 'display:none' : ''; ?>">
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
                    <div>
                        <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">TYPE</div>
                        <select name="rating[type]" class="sh-select" id="sh-rating-type-select" onchange="shrRatingTypeChange(this.value)">
                            <?php foreach ( [ 'stars'=>'&#11088; Stars', 'thumbs'=>'&#128077; Thumbs', 'nps'=>'&#128202; NPS (0-10)', 'multi'=>'&#127919; Multi-criteria' ] as $v => $l ) : ?>
                            <option value="<?php echo $v; ?>" <?php selected( $s['rating']['type'], $v ); ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="sh-max-rating-wrap" <?php echo in_array( $s['rating']['type'], ['thumbs','nps'] ) ? 'style="display:none"' : ''; ?>>
                        <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">MAX RATING</div>
                        <select name="rating[max_rating]" class="sh-select">
                            <option value="5"  <?php selected( $s['rating']['max_rating'], 5 ); ?>>5</option>
                            <option value="10" <?php selected( $s['rating']['max_rating'], 10 ); ?>>10</option>
                        </select>
                    </div>
                </div>
                <?php self::toggle( 'rating[allow_half]',     'Allow half-star (e.g. 4.5)', $s['rating']['allow_half'] ); ?>
                <?php self::toggle( 'rating[show_breakdown]', 'Show rating breakdown',      $s['rating']['show_breakdown'] ); ?>

                <!-- Criteria Display Mode -->
                <div style="margin-top:12px;padding:14px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:#9ca3af;margin-bottom:8px">CRITERIA DISPLAY MODE</div>
                    <div style="display:flex;gap:16px;flex-wrap:wrap">
                        <?php foreach ( [
                            'inline'  => [ 'icon' => '&#9776;', 'title' => 'Inline', 'desc' => 'Criteria scores printed below the stars (current behaviour)' ],
                            'popover' => [ 'icon' => '&#128269;', 'title' => 'Popover', 'desc' => 'Average score shown; hover/click reveals criteria in a Bootstrap popover' ],
                        ] as $val => $opt ) :
                            $checked = ( ( $s['rating']['criteria_display'] ?? 'inline' ) === $val );
                        ?>
                        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 14px;border:2px solid <?php echo $checked ? '#2271b1' : '#e5e7eb'; ?>;border-radius:8px;background:<?php echo $checked ? '#eff6ff' : '#fff'; ?>;min-width:180px;flex:1">
                            <input type="radio" name="rating[criteria_display]" value="<?php echo $val; ?>" <?php checked( $checked ); ?> style="margin-top:3px" onchange="this.closest('.sh-criteria-display-options').querySelectorAll('label').forEach(function(l){var inp=l.querySelector('input');l.style.borderColor=inp.checked?'#2271b1':'#e5e7eb';l.style.background=inp.checked?'#eff6ff':'#fff';})">
                            <div>
                                <div style="font-weight:600;font-size:13px"><?php echo $opt['icon']; ?> <?php echo $opt['title']; ?></div>
                                <div style="font-size:11px;color:#6b7280;margin-top:2px"><?php echo $opt['desc']; ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:11px;color:#9ca3af;margin:8px 0 0">&#9432; Only effective when <strong>multi</strong> type with criteria is active.</p>
                </div>
                <script>
                // wrap radio labels için class ekle
                document.addEventListener('DOMContentLoaded', function() {
                    var opts = document.querySelectorAll('input[name="rating\\[criteria_display\\]"]');
                    if (!opts.length) return;
                    var wrap = opts[0].closest('div').parentElement;
                    if (wrap) wrap.classList.add('sh-criteria-display-options');
                });
                </script>

                <!-- Multi-criteria -->
                <div id="sh-criteria-section" <?php echo $s['rating']['type'] !== 'multi' ? 'style="display:none"' : ''; ?>>
                    <hr style="margin:16px 0">
                    <h4 style="font-size:13px;font-weight:700;margin:0 0 8px">Criteria <small style="font-weight:400;color:#9ca3af">per post type &amp; user role</small></h4>
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;">
                        <span style="font-size:15px;flex-shrink:0;">⚠️</span>
                        <div>
                            <strong>Enter criteria labels in the default language (English).</strong><br>
                            Labels are added to the translation file when you run "Extract Translations". Loco Translate will then detect them for translation into other languages.
                        </div>
                    </div>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:12px">
                        <?php foreach ( $all_criteria_tabs as $pt_key => $pt_label ) :
                            $is_user_role = str_starts_with( $pt_key, 'user_role_' ) || $pt_key === 'user_default';
                            if ( $is_user_role ) {
                                $role_slug = $pt_key === 'user_default' ? 'default' : str_replace( 'user_role_', '', $pt_key );
                                $tab_rows  = $criteria_data['user_roles'][ $role_slug ] ?? [];
                            } else {
                                $tab_rows  = $pt_key === 'default' ? ( $criteria_data['default'] ?? [] ) : ( $criteria_data['post_types'][ $pt_key ] ?? [] );
                            }
                            $tab_count = count( $tab_rows );
                        ?>
                        <button type="button" class="sh-criteria-tab <?php echo $pt_key === $active_pt ? 'active' : ''; ?>" onclick="shCriteriaTab('<?php echo esc_attr( $pt_key ); ?>', this)"
                                <?php if ( $is_user_role ) echo 'data-user-role="1"'; ?>>
                            <?php echo esc_html( $pt_label ); ?>
                            <?php if ( $tab_count ) : ?><span class="sh-criteria-tab-count"><?php echo $tab_count; ?></span><?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ( $all_criteria_tabs as $pt_key => $pt_label ) :
                        $is_user_role = str_starts_with( $pt_key, 'user_role_' ) || $pt_key === 'user_default';
                        if ( $is_user_role ) {
                            $role_slug = $pt_key === 'user_default' ? 'default' : str_replace( 'user_role_', '', $pt_key );
                            $fp        = 'rating[criteria][user_roles][' . $role_slug . ']';
                            $rows      = $criteria_data['user_roles'][ $role_slug ] ?? [];
                        } else {
                            $fp   = $pt_key === 'default' ? 'rating[criteria][default]' : 'rating[criteria][post_types][' . $pt_key . ']';
                            $rows = $pt_key === 'default' ? ( $criteria_data['default'] ?? [] ) : ( $criteria_data['post_types'][ $pt_key ] ?? [] );
                        }
                    ?>
                    <div class="sh-criteria-panel" id="sh-criteria-panel-<?php echo esc_attr( $pt_key ); ?>" style="<?php echo $pt_key !== $active_pt ? 'display:none' : ''; ?>">
                        <div class="sh-criteria-repeater" id="sh-repeater-<?php echo esc_attr( $pt_key ); ?>">
                            <?php if ( empty( $rows ) ) : ?><div class="sh-criteria-empty">No criteria. Click "+ Add Criterion".</div><?php endif; ?>
                            <?php foreach ( $rows as $i => $row ) : ?>
                            <div class="sh-criteria-row" draggable="true">
                                <span class="sh-criteria-drag">&#9776;</span>
                                <input type="text" name="<?php echo esc_attr( $fp ); ?>[<?php echo $i; ?>][key]" value="<?php echo esc_attr( $row['key'] ?? '' ); ?>" placeholder="key" class="sh-input sh-criteria-key" onblur="shSanitizeCriteriaKey(this)">
                                <input type="text" name="<?php echo esc_attr( $fp ); ?>[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $row['label'] ?? '' ); ?>" placeholder="Label" class="sh-input sh-criteria-label">
                                <div class="sh-criteria-weight-wrap"><span>Weight</span><input type="number" name="<?php echo esc_attr( $fp ); ?>[<?php echo $i; ?>][weight]" value="<?php echo esc_attr( $row['weight'] ?? 1.0 ); ?>" step="0.1" min="0.1" max="5" class="sh-input sh-criteria-weight"></div>
                                <button type="button" class="sh-criteria-remove" onclick="shCriteriaRemove(this)">&#10005;</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="sh-btn sh-btn-ghost sh-btn-sm" style="margin-top:6px" onclick="shCriteriaAdd('<?php echo esc_attr( $pt_key ); ?>','<?php echo esc_attr( $fp ); ?>')">+ Add Criterion</button>
                        <?php if ( $pt_key !== 'default' && $pt_key !== 'user_default' ) : ?>
                        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">Leave empty to use Default criteria<?php echo $is_user_role ? ' for users' : ''; ?>.</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- HELPFUL TAB -->
            <div id="shr-stab-helpful" class="shr-stab-panel" style="<?php echo $stab !== 'helpful' ? 'display:none' : ''; ?>">
                <?php self::toggle( 'helpful[enable_helpful]',        'Enable helpful votes', $s['helpful']['enable_helpful'] ); ?>
                <div class="sh-field-group" style="margin-top:12px;max-width:280px">
                    <label>Algorithm</label>
                    <select name="helpful[algorithm]" class="sh-select">
                        <option value="simple" <?php selected( $s['helpful']['algorithm'], 'simple' ); ?>>Simple (helpful - unhelpful)</option>
                        <option value="wilson" <?php selected( $s['helpful']['algorithm'], 'wilson' ); ?>>Wilson Score (Reddit-style)</option>
                    </select>
                </div>
                <?php self::toggle( 'helpful[require_login_to_vote]', 'Require login to vote', $s['helpful']['require_login_to_vote'] ); ?>
            </div>

            <!-- MEDIA TAB -->
            <div id="shr-stab-media" class="shr-stab-panel" style="<?php echo $stab !== 'media' ? 'display:none' : ''; ?>">
                <?php self::toggle( 'media[enable_media]', 'Allow photo uploads', $s['media']['enable_media'] ); ?>
                <div class="sh-fields-row" style="margin-top:12px">
                    <div class="sh-field-group" style="max-width:200px">
                        <label>Max images per review</label>
                        <input type="number" name="media[max_images]" value="<?php echo (int) $s['media']['max_images']; ?>" min="1" max="20" class="sh-input">
                    </div>
                    <div class="sh-field-group" style="max-width:200px">
                        <label>Max image size (MB)</label>
                        <input type="number" name="media[max_image_size]" value="<?php echo (int) $s['media']['max_image_size']; ?>" min="1" max="20" class="sh-input">
                    </div>
                </div>
            </div>

            <!-- NOTIFICATIONS TAB -->
            <div id="shr-stab-notifications" class="shr-stab-panel" style="<?php echo $stab !== 'notifications' ? 'display:none' : ''; ?>">
                <?php self::toggle( 'notifications[notify_on_new_review]', 'Notify on new review', $s['notifications']['notify_on_new_review'] ); ?>
                <?php self::toggle( 'notifications[notify_on_reply]',      'Notify on reply',      $s['notifications']['notify_on_reply'] ); ?>
                <?php self::toggle( 'notifications[notify_on_approve]',    'Notify on approve',    $s['notifications']['notify_on_approve'] ); ?>
            </div>

            <!-- TYPES TAB -->
            <div id="shr-stab-types" class="shr-stab-panel" style="<?php echo $stab !== 'types' ? 'display:none' : ''; ?>">
                <?php
                $types_config    = $s['types'] ?? [];
                $all_post_types  = get_post_types( [ 'public' => true ], 'objects' );
                $modes_all       = [ 'rate' => 'Rate', 'comment' => 'Comment', 'criteria' => 'Criteria', 'uploads' => 'Uploads' ];
                global $wp_roles;

                // Mevcut kayıtlı type'ları listele
                $object_options = [
                    'user' => 'User (All roles)',
                    'post' => 'Post (All types)',
                ];
                foreach ( $wp_roles->roles as $slug => $role ) {
                    $object_options[ 'user:' . $slug ] = 'User: ' . $role['name'];
                }
                foreach ( $all_post_types as $pt ) {
                    if ( $pt->name === 'attachment' ) continue;
                    $object_options[ 'post:' . $pt->name ] = 'Post: ' . $pt->label;
                }
                ?>
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:16px">REVIEWABLE TYPES</div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:16px">Define which object types (and subtypes) can receive reviews. Subtype-specific rules override the parent type.</p>

                <div id="shr-types-list">
                <?php foreach ( $object_options as $key => $label ) :
                    $cfg      = $types_config[ $key ] ?? [ 'enabled' => false, 'modes' => [], 'criteria' => [] ];
                    $enabled  = ! empty( $cfg['enabled'] );
                    $modes    = $cfg['modes'] ?? [];
                    $criteria = $cfg['criteria'] ?? [];
                    $safe_key = str_replace( ':', '__', $key );
                ?>
                <div class="shr-type-row" style="border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:10px;background:#fff">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <label class="sh-toggle" style="flex-shrink:0">
                            <input type="checkbox" name="types[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled); ?>
                                   onchange="shrTypeToggle(this,'<?php echo esc_attr($safe_key); ?>')">
                            <span class="sh-toggle-slider"></span>
                        </label>
                        <strong style="font-size:13px;min-width:160px"><?php echo esc_html($label); ?></strong>
                        <span style="font-size:11px;color:#9ca3af;font-family:monospace"><?php echo esc_html($key); ?></span>
                    </div>
                    <div id="shr-type-opts-<?php echo esc_attr($safe_key); ?>" style="margin-top:12px;padding-top:12px;border-top:1px solid #f3f4f6;<?php echo !$enabled ? 'display:none' : ''; ?>">
                        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:4px">
                        <?php foreach ( $modes_all as $mode_key => $mode_label ) : ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                                <input type="checkbox" name="types[<?php echo esc_attr($key); ?>][modes][]" value="<?php echo esc_attr($mode_key); ?>"
                                       <?php checked( in_array( $mode_key, $modes, true ) ); ?>>
                                <?php echo esc_html($mode_label); ?>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <?php if ( in_array( 'criteria', $modes, true ) ) : ?>
                        <p style="font-size:11px;color:#8b5cf6;margin:6px 0 0">&#9432; Criteria are defined in the <strong>Rating</strong> tab.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            </div><!-- padding -->

            <div style="margin-top:16px;display:flex;gap:8px">
                <button type="submit" class="sh-btn sh-btn-primary">Save Settings</button>
                <a href="?page=sh-reviews&amp;tab=settings&amp;reset=1" class="sh-btn sh-btn-ghost" onclick="return confirm('Reset all settings to defaults?')">Reset to Defaults</a>
            </div>
        </form>

        <?php endif; // enabled ?>

        <script>
        function shSanitizeCriteriaKey(el) {
            el.value = el.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        }
        function shrSwitchTab(slug) {
            document.querySelectorAll('.shr-stab-panel').forEach(function(p){ p.style.display='none'; });
            document.querySelectorAll('[id^="shr-tab-btn-"]').forEach(function(b){ b.classList.remove('active'); });
            var panel = document.getElementById('shr-stab-' + slug);
            if (panel) panel.style.display = '';
            var btn = document.getElementById('shr-tab-btn-' + slug);
            if (btn) btn.classList.add('active');
        }

        function shrTypeToggle(checkbox, safeKey) {
            var opts = document.getElementById('shr-type-opts-' + safeKey);
            if (opts) opts.style.display = checkbox.checked ? '' : 'none';
        }

        function shrAddCriteria(safeKey, origKey) {
            var list = document.getElementById('shr-criteria-' + safeKey);
            if (!list) return;
            var idx  = list.querySelectorAll('.shr-crit-row').length;
            var row  = document.createElement('div');
            row.className = 'shr-crit-row';
            row.style.cssText = 'display:flex;gap:8px;margin-bottom:6px;align-items:center';
            row.innerHTML = '<input type="text" class="sh-input" style="width:120px" placeholder="key" onblur="shSanitizeCriteriaKey(this)" name="types[' + origKey + '][criteria][' + idx + '][key]">'
                          + '<input type="text" class="sh-input" style="flex:1" placeholder="Label" name="types[' + origKey + '][criteria][' + idx + '][label]">'
                          + '<input type="number" class="sh-input" style="width:70px" placeholder="Weight" min="0.1" max="5" step="0.1" value="1" name="types[' + origKey + '][criteria][' + idx + '][weight]">'
                          + '<button type="button" onclick="this.closest(\'.shr-crit-row\').remove()" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:18px" title="Remove">×</button>';
            list.appendChild(row);
        }

        // criteria mode checkbox — criteria wrap'i enable/disable et
        document.addEventListener('change', function(e) {
            if (!e.target.matches('input[type="checkbox"][name*="[modes][]"]')) return;
            if (e.target.value !== 'criteria') return;
            var wrap = e.target.closest('.shr-type-row').querySelector('.shr-criteria-wrap');
            if (wrap) {
                wrap.style.opacity = e.target.checked ? '1' : '.4';
                wrap.style.pointerEvents = e.target.checked ? '' : 'none';
            }
        });
        function shrRatingTypeChange(val) {
            var sec = document.getElementById('sh-criteria-section');
            var mw  = document.getElementById('sh-max-rating-wrap');
            if (sec) sec.style.display = val === 'multi' ? '' : 'none';
            if (mw)  mw.style.display  = (val === 'thumbs' || val === 'nps') ? 'none' : '';
        }
        function shCriteriaTab(ptKey, btn) {
            document.querySelectorAll('.sh-criteria-tab').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.sh-criteria-panel').forEach(function(p){ p.style.display='none'; });
            btn.classList.add('active');
            document.getElementById('sh-criteria-panel-' + ptKey).style.display = '';
        }
        function shCriteriaAdd(ptKey, prefix) {
            var rep = document.getElementById('sh-repeater-' + ptKey);
            var empty = rep ? rep.querySelector('.sh-criteria-empty') : null;
            if (empty) empty.remove();
            var idx = rep ? rep.querySelectorAll('.sh-criteria-row').length : 0;
            var row = document.createElement('div');
            row.className = 'sh-criteria-row';
            row.draggable = true;
            row.innerHTML = '<span class="sh-criteria-drag">&#9776;</span>' +
                '<input type="text" name="'+prefix+'['+idx+'][key]" placeholder="key" class="sh-input sh-criteria-key" onblur="shSanitizeCriteriaKey(this)">' +
                '<input type="text" name="'+prefix+'['+idx+'][label]" placeholder="Label" class="sh-input sh-criteria-label">' +
                '<div class="sh-criteria-weight-wrap"><span>Weight</span><input type="number" name="'+prefix+'['+idx+'][weight]" value="1" step="0.1" min="0.1" max="5" class="sh-input sh-criteria-weight"></div>' +
                '<button type="button" class="sh-criteria-remove" onclick="shCriteriaRemove(this)">&#10005;</button>';
            if (rep) rep.appendChild(row);
            var keyInput = row.querySelector('.sh-criteria-key');
            if (keyInput) keyInput.focus();
        }
        function shCriteriaRemove(btn) {
            var row = btn.closest('.sh-criteria-row');
            var rep = row ? row.closest('.sh-criteria-repeater') : null;
            if (row) row.remove();
            if (rep && !rep.querySelector('.sh-criteria-row')) {
                rep.innerHTML = '<div class="sh-criteria-empty">No criteria. Click "+ Add Criterion".</div>';
            }
        }
        </script>

        <style>
        .sh-toggle{position:relative;display:inline-block;width:36px;height:20px;flex-shrink:0}
        .sh-toggle input{opacity:0;width:0;height:0}
        .sh-toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#c3c4c7;border-radius:20px;transition:.3s}
        .sh-toggle-slider:before{position:absolute;content:"";height:14px;width:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
        .sh-toggle input:checked + .sh-toggle-slider{background:#2271b1}
        .sh-toggle input:checked + .sh-toggle-slider:before{transform:translateX(16px)}
        .shr-stab-panel .sh-toggle-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6}
        .shr-stab-panel .sh-toggle-row:last-child{border-bottom:none}
        .sh-criteria-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:12px}
        .sh-criteria-tab{padding:5px 12px;border:1px solid #dcdcde;border-radius:4px;background:#fff;font-size:12px;cursor:pointer;color:#374151}
        .sh-criteria-tab.active{background:#2271b1;color:#fff;border-color:#2271b1}
        .sh-criteria-tab[data-user-role]{border-color:#8b5cf6;color:#8b5cf6}
        .sh-criteria-tab[data-user-role].active{background:#8b5cf6;color:#fff;border-color:#8b5cf6}
        .sh-criteria-tab[data-user-role] .sh-criteria-tab-count{background:#8b5cf6}
        .sh-criteria-tab[data-user-role].active .sh-criteria-tab-count{background:rgba(255,255,255,.3)}
        .sh-criteria-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:#2271b1;color:#fff;border-radius:10px;font-size:10px;font-weight:700;margin-left:5px}
        .sh-criteria-tab.active .sh-criteria-tab-count{background:rgba(255,255,255,.3)}
        .sh-criteria-repeater{display:flex;flex-direction:column;gap:6px;margin-bottom:8px;min-height:30px}
        .sh-criteria-row{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px}
        .sh-criteria-drag{cursor:grab;color:#a7aaad;font-size:16px;flex-shrink:0;user-select:none}
        .sh-criteria-key{width:140px!important;font-family:monospace;font-size:12px}
        .sh-criteria-label{flex:1}
        .sh-criteria-weight-wrap{display:flex;align-items:center;gap:4px;flex-shrink:0}
        .sh-criteria-weight-wrap>span{font-size:11px;color:#6b7280;white-space:nowrap}
        .sh-criteria-weight{width:60px!important;text-align:center}
        .sh-criteria-remove{background:none;border:none;color:#a7aaad;cursor:pointer;font-size:14px;padding:2px 6px;border-radius:4px;flex-shrink:0}
        .sh-criteria-remove:hover{color:#d63638;background:#fee2e2}
        .sh-criteria-empty{font-size:12px;color:#9ca3af;padding:12px;text-align:center;border:2px dashed #dcdcde;border-radius:4px}
        .sh-notice{padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:13px}
        .sh-notice-success{background:#dcfce7;color:#15803d;border:1px solid #86efac}
        </style>

        </div><!-- sh-wrap -->
        <?php
    }

    private static function toggle( string $name, string $label, bool $checked, string $desc = '' ): void
    {
        ?>
        <div class="sh-toggle-row" style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6">
            <label class="sh-toggle">
                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
                <span class="sh-toggle-slider"></span>
            </label>
            <div style="flex:1">
                <span style="font-size:13px;color:#374151"><?php echo esc_html( $label ); ?></span>
                <?php if ( $desc ) : ?>
                    <br><span style="font-size:11px;color:#9ca3af"><?php echo esc_html( $desc ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
