<?php

/**
 * YoBro Chat Plugin Integration
 *
 * Messenger class + hook callback'ler + asset dequeue.
 * Tüm mesajlaşma işlemleri Messenger:: üzerinden yapılır.
 *
 * Yükleme: variables.php → if (class_exists('Redq_YoBro')) include
 */

// ═══════════════════════════════════════════════════════════════
// MESSENGER CLASS
// ═══════════════════════════════════════════════════════════════

class Messenger {

    // ─── Mesaj Gönderme ─────────────────────────────────────

    /**
     * Mesaj gönderir. Konuşma yoksa oluşturur.
     */
    public static function send(int $to, string $message, int $post_id = 0) {
        $from    = get_current_user_id();
        $conv_id = self::find_conversation($post_id, $from, $to, true);

        return $conv_id
            ? self::store($conv_id, $from, $to, $message)
            : self::create_conversation($from, $to, $message, $post_id);
    }

    /**
     * Mevcut konuşmaya mesaj ekler.
     */
    public static function store(int $conv_id, int $sender, int $reciever, string $message) {
        return do_store_message([
            'conv_id'     => $conv_id,
            'message'     => $message,
            'sender_id'   => $sender,
            'reciever_id' => $reciever,
        ]);
    }

    // ─── Konuşma Oluşturma & Arama ─────────────────────────

    public static function create_conversation(int $sender, int $reciever, string $message = '', int $post_id = 0) {
        global $wpdb;

        $conv = \YoBro\App\Conversation::create(['sender' => $sender, 'reciever' => $reciever]);
        if (!$conv || !isset($conv['id'])) return false;

        $conv_id    = $conv['id'];
        $created_at = current_time('mysql', 1);

        $wpdb->update(
            "{$wpdb->prefix}yobro_conversation",
            ['post_id' => $post_id, 'created_at' => $created_at],
            ['id' => $conv_id], ['%d', '%s'], ['%d']
        );

        if ($message !== '') {
            return \YoBro\App\Message::create([
                'conv_id' => $conv_id, 'sender_id' => $sender, 'reciever_id' => $reciever,
                'message' => encrypt_decrypt($message, $sender), 'created_at' => $created_at,
            ]);
        }
        return $conv_id;
    }

    public static function find_conversation(int $post_id, int $sender, int $reciever, bool $bidirectional = false): ?int {
        global $wpdb;
        if ($sender <= 0 || $reciever <= 0) return null;

        $table = $wpdb->prefix . 'yobro_conversation';

        if ($sender === $reciever && $post_id > 0) {
            $w = $wpdb->prepare('(reciever = %d OR sender = %d)', $sender, $sender);
        } elseif ($bidirectional) {
            $w = $wpdb->prepare('((reciever=%d AND sender=%d) OR (reciever=%d AND sender=%d))', $reciever, $sender, $sender, $reciever);
        } else {
            $w = $wpdb->prepare('(reciever = %d AND sender = %d)', $reciever, $sender);
        }

        $r = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE {$w} AND post_id = %d ORDER BY id ASC LIMIT 1", $post_id));
        return $r ? (int) $r : null;
    }

    public static function get_conversation(int $conv_id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}yobro_conversation WHERE id = %d LIMIT 1", $conv_id));
    }

    public static function last_message(int $conv_id, int $sender_id = 0): ?object {
        global $wpdb;
        $extra = $sender_id > 0 ? $wpdb->prepare(' AND sender_id = %d', $sender_id) : '';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, conv_id, sender_id, message, created_at, seen FROM {$wpdb->prefix}yobro_messages WHERE conv_id = %d{$extra} ORDER BY created_at DESC LIMIT 1", $conv_id
        ));
    }

    // ─── Sayaçlar ───────────────────────────────────────────

    public static function count(int $conv_id = 0): int {
        global $wpdb;
        $uid   = get_current_user_id();
        $extra = $conv_id > 0 ? $wpdb->prepare(' AND conv_id = %d', $conv_id) : '';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT conv_id) FROM {$wpdb->prefix}yobro_messages WHERE reciever_id = %d AND seen IS NULL{$extra}", $uid
        ));
    }

    public static function conversation_count(int $uid = 0): int {
        global $wpdb;
        $uid = $uid ?: get_current_user_id();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT conv_id) FROM {$wpdb->prefix}yobro_messages WHERE reciever_id = %d", $uid
        ));
    }


    // ─── Bildirimler ────────────────────────────────────────

    public static function notifications(string $action = ''): array {
        global $wpdb;
        $uid = get_current_user_id();
        if (!$uid) return [];

        $user = new User($uid);
        $cond = '';
        if ($action === 'seen')         $cond = ' AND messages.seen IS NULL';
        if ($action === 'notification') $cond = ' AND messages.notification IS NULL';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT messages.id, messages.conv_id, messages.sender_id, messages.message, messages.created_at, conversation.post_id
             FROM {$wpdb->prefix}yobro_messages messages
             INNER JOIN {$wpdb->prefix}yobro_conversation conversation ON messages.conv_id = conversation.id
             WHERE messages.reciever_id = %d {$cond} ORDER BY messages.created_at ASC LIMIT 1", $uid
        ));

        $timeAgo = new Westsworld\TimeAgo();
        $out     = [];

        foreach ($rows as $msg) {
            $url   = get_account_endpoint_url('messages') . $msg->conv_id;
            $title = 'Message';
            if (!empty($msg->post_id) && $msg->post_id > 0) {
                $url   = get_permalink($msg->post_id) . '#messages';
                $title = get_the_title($msg->post_id);
            }
            $sender = new User($msg->sender_id);
            $out[]  = [
                'id' => $msg->conv_id, 'type' => 'message', 'title' => $title,
                'sender'  => ['id' => $msg->sender_id, 'image' => get_avatar($msg->sender_id, 32), 'name' => $sender->display_name],
                'message' => truncate(strip_tags(encrypt_decrypt($msg->message, $msg->sender_id, 'decrypt')), 150),
                'url'     => $url,
                'time'    => $timeAgo->inWordsFromStrings($user->get_local_date($msg->created_at, 'GMT', $user->get_timezone())),
            ];
            $wpdb->update("{$wpdb->prefix}yobro_messages", ['notification' => 1], ['id' => $msg->id], ['%d'], ['%d']);
        }
        return $out;
    }

    // ─── Konuşma Listeleri ──────────────────────────────────

    public static function conversations(int $uid, string $dir = 'all'): array {
        global $wpdb;
        $w = match ($dir) {
            'sent'     => $wpdb->prepare('c.sender = %d', $uid),
            'received' => $wpdb->prepare('c.reciever = %d', $uid),
            default    => $wpdb->prepare('(c.sender = %d OR c.reciever = %d)', $uid, $uid),
        };
        return $wpdb->get_results(
            "SELECT c.id as conversation_id, t.post_title as title, u.display_name as agent
             FROM {$wpdb->prefix}yobro_conversation c
             INNER JOIN {$wpdb->posts} t ON c.post_id = t.ID
             INNER JOIN {$wpdb->users} u ON c.sender = u.ID
             WHERE t.post_type = 'project' AND {$w}"
        );
    }

    public static function post_conversations(int $post_id, int $uid): array {
        global $wpdb;
        $extra = $post_id > 0 ? $wpdb->prepare(' AND c.post_id = %d', $post_id) : '';
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT m.conv_id, c.post_id, c.sender, c.reciever,
                    (COUNT(m.id) - COUNT(m.seen)) as new_messages, MAX(m.created_at) as last_date
             FROM {$wpdb->prefix}yobro_messages m
             INNER JOIN {$wpdb->prefix}yobro_conversation c ON m.conv_id = c.id
             WHERE (c.reciever = %d OR c.sender = %d){$extra}
             GROUP BY m.conv_id ORDER BY new_messages DESC, last_date DESC", $uid, $uid
        ));

        $out = [];
        foreach ($rows as $r) {
            $other = ($uid == $r->sender) ? $r->reciever : $r->sender;
            $ou    = new User($other);
            $last  = self::last_message($r->conv_id);
            $item  = [
                'id' => $r->conv_id, 'post_id' => $r->post_id,
                'sender' => ['id' => $other, 'image' => $ou->get_avatar_url(), 'name' => $ou->get_title()],
                'message' => '', 'new_messages' => (int) $r->new_messages, 'time' => $r->last_date,
            ];
            if ($last) {
                $item['message'] = removeUrls(strip_tags(encrypt_decrypt($last->message, $last->sender_id, 'decrypt')));
                $item['time']    = $last->created_at;
                $item['seen']    = $last->seen;
            }
            $out[] = $item;
        }
        return $out;
    }

    // ─── Mesaj Okuma ────────────────────────────────────────

    public static function messages(int $conv_id): array {
        $cur  = get_current_user_id();
        $user = new User($cur);

        if (isset($_SESSION['querystring'])) {
            $p = json_decode($_SESSION['querystring'], true);
            unset_filter_session('querystring');
            if (!empty($p['conversationId'])) $conv_id = (int) $p['conversationId'];
        }

        $msgs = \YoBro\App\Message::where('conv_id', '=', $conv_id)
            ->where('delete_status', '!=', 1)
            ->where(fn($q) => $q->where('sender_id', $cur)->orWhere('reciever_id', $cur))
            ->orderBy('id', 'asc')->get()->toArray();

        $out = [];
        foreach ($msgs as &$m) {
            $m['message']       = encrypt_decrypt($m['message'], $m['sender_id'], 'decrypt');
            $m['owner']         = ($m['sender_id'] == $cur) ? 'true' : 'false';
            $m['pic']           = get_avatar($m['sender_id']) ?: (function_exists('up_user_placeholder_image') ? up_user_placeholder_image() : '');
            $m['reciever_name'] = function_exists('get_user_name_by_id') ? (get_user_name_by_id($m['reciever_id']) ?: 'Untitled') : 'Untitled';
            $m['sender_name']   = function_exists('get_user_name_by_id') ? (get_user_name_by_id($m['sender_id']) ?: 'Untitled') : 'Untitled';
            $m['time']          = $user->get_local_date($m['created_at'], 'GMT', $user->get_timezone());
            if (!empty($m['attachment_id'])) {
                $m['attachments'] = \YoBro\App\Attachment::where('id', '=', $m['attachment_id'])->first();
            }
            $out[$m['id']] = $m;
        }
        return $out;
    }

    public static function unseen(int $conv_id, int $uid): array {
        global $wpdb;
        $cur   = get_current_user_id();
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yobro_messages WHERE conv_id = %d AND reciever_id = %d AND seen IS NULL ORDER BY created_at ASC",
            $conv_id, $uid
        ), ARRAY_A);

        $ta = new Westsworld\TimeAgo();
        foreach ($items as &$i) {
            $i['message'] = encrypt_decrypt($i['message'], $i['sender_id'], 'decrypt');
            $own          = ($i['sender_id'] == $cur);
            $i['owner']   = $own ? 'true' : 'false';
            $actor        = $own ? Data::get('user') : new User($i['sender_id']);
            $lt           = $actor->get_local_date($i['created_at'], 'GMT', $actor->get_timezone());
            $i['pic']        = get_avatar($i['sender_id'], 32);
            $i['time']       = $ta->inWordsFromStrings($lt);
            $i['created_at'] = $i['time'];
            if (!empty($i['attachment_id'])) {
                $i['attachments'] = \YoBro\App\Attachment::where('id', '=', $i['attachment_id'])->first();
            }
        }
        return $items;
    }

    // ─── Silme ──────────────────────────────────────────────

    public static function remove_conversation(int $id): void {
        global $wpdb;
        if ($id <= 0) return;
        $wpdb->delete("{$wpdb->prefix}yobro_messages", ['conv_id' => $id], ['%d']);
        $wpdb->delete("{$wpdb->prefix}yobro_conversation", ['id' => $id], ['%d']);
    }

    public static function remove_by_user(int $uid): void {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}yobro_conversation WHERE sender = %d OR reciever = %d", $uid, $uid));
        array_map([self::class, 'remove_conversation'], array_map('intval', $ids));
    }

    public static function remove_by_post(int $pid): void {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}yobro_conversation WHERE post_id = %d", $pid));
        array_map([self::class, 'remove_conversation'], array_map('intval', $ids));
    }

    // ─── UI ─────────────────────────────────────────────────

    public static function dropdown(int $uid): string {
        $url = get_account_endpoint_url('messages');
        $cs  = self::conversations($uid);
        if (empty($cs)) return '';
        $opts = '';
        foreach ($cs as $c) {
            $sel   = ($c->conversation_id == get_query_var('conversationId')) ? ' selected' : '';
            $opts .= '<option value="' . esc_url($url . '?conversationId=' . $c->conversation_id) . '"' . $sel . '>' . esc_html($c->title) . '</option>';
        }
        return '<select class="selectpicker selectpicker-url-update" name="conversations">' . $opts . '</select>';
    }

    // ─── DB Migration ───────────────────────────────────────

    public static function migrate(): void {
        $v = 'v1.1';
        if (get_option('yobro_schema_migrated_' . $v)) return;
        global $wpdb;
        $ch = $wpdb->get_charset_collate();
        $ms = [
            ['t' => $wpdb->prefix . 'yobro_conversation', 'c' => 'post_id',      'd' => 'bigint(20) NOT NULL DEFAULT 0'],
            ['t' => $wpdb->prefix . 'yobro_messages',     'c' => 'notification',  'd' => "tinytext {$ch}"],
        ];
        foreach ($ms as $m) {
            if (!$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME=%s", $wpdb->dbname, $m['t'], $m['c']))) {
                $wpdb->query("ALTER TABLE `{$m['t']}` ADD `{$m['c']}` {$m['d']}");
            }
        }
        update_option('yobro_schema_migrated_' . $v, true);
    }
}

// Twig template'lerden çağrılabilmesi için wrapper (Twig static method çağıramaz)
function yobro_check_conversation_exist($p, $s, $r, $f = false) { return Messenger::find_conversation((int)$p, (int)$s, (int)$r, $f); }
function yobro_get_post_conversations($p = 0, $u = 0) { return Messenger::post_conversations((int)$p, (int)$u); }


// ═══════════════════════════════════════════════════════════════
// HOOK CALLBACK'LER
// ═══════════════════════════════════════════════════════════════

add_action('admin_init', [Messenger::class, 'migrate']);

add_filter('yobro_before_store_new_message', 'before_store_new_message');
function before_store_new_message($message) {
    global $wpdb;
    $user       = Data::get('user');
    $attrs      = $message['attributes'] ?? $message;
    $msg_id     = (int) ($attrs['id'] ?? 0);
    $created_at = current_time('mysql', 1);

    $conv = Messenger::get_conversation($attrs['conv_id'] ?? 0);
    if (!$conv) return $attrs;

    $other = ($conv->reciever == $user->ID) ? $conv->sender : $conv->reciever;
    $wpdb->update("{$wpdb->prefix}yobro_messages", ['reciever_id' => (int) $other, 'created_at' => $created_at], ['id' => $msg_id], ['%d', '%s'], ['%d']);

    $ta = new Westsworld\TimeAgo();
    $attrs['created_at'] = $ta->inWordsFromStrings($user->get_local_date($created_at, 'GMT', $user->get_timezone(), 'Y-m-d H:i:s'));
    return $attrs;
}

add_filter('yobro_after_store_message', 'after_store_new_message');
function after_store_new_message($message) {
    $attrs = $message;
    $salt  = Salt::get_instance();
    if (!$salt->user_is_online($attrs['reciever_id'])) {
        $salt->notification((new User($attrs['reciever_id']))->get_role() . '/new-message', [
            'conv_id' => $attrs['conv_id'],
            'sender'  => new User($attrs['sender_id']),
            'user'    => new User($attrs['reciever_id']),
            'message' => $attrs['message'],
        ]);
    }
    return $attrs;
}

add_filter('yobro_automatic_pull_messages', function($messages) {
    $conv_id = get_query_var('conversationId') ?: ($_POST['conv_id'] ?? 0);
    $messages['new_unseen_messages'] = Messenger::unseen((int) $conv_id, Data::get('user')->ID);
    return $messages;
});

add_filter('yobro_conversation_messages', function($messages) {
    $ta = new Westsworld\TimeAgo();
    foreach ($messages as &$m) {
        $actor = ($m['owner'] === 'true') ? new User($m['sender_id']) : new User($m['reciever_id']);
        $local = $actor->get_local_date($m['time'], 'GMT', '', 'Y-m-d H:i:s');
        $m['created_at'] = $local;
        $m['time']       = $ta->inWordsFromStrings($local);
    }
    return $messages;
});

add_filter('yobro_message_deleted', function($mid) {
    global $wpdb;
    $mid = (int) $mid;
    if ($mid > 0) $wpdb->get_var($wpdb->prepare("SELECT conv_id FROM {$wpdb->prefix}yobro_messages WHERE id = %d", $mid));
});

// ─── Asset Dequeue ──────────────────────────────────────────

add_action('wp_print_styles', function() {
    wp_deregister_style('font-awesome');
    wp_deregister_style('font-for-body');
    wp_deregister_style('font-for-new');
}, 100);

// ─── htaccess Rewrite ───────────────────────────────────────

add_filter('mod_rewrite_rules', function($rules) {
    $f = getSiteSubfolder();
    return <<<HTACCESS
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase {$f}
        RewriteRule ^index\\.php$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . {$f}index.php [L]
        RewriteCond %{REQUEST_METHOD} POST
        RewriteCond %{REQUEST_URI} ^{$f}wp-admin/
        RewriteCond %{QUERY_STRING} action=up_asset_upload
        RewriteRule (.*) {$f}index.php?ajax=query&method=message_upload [L,R=307]
    </IfModule>
HTACCESS;
});