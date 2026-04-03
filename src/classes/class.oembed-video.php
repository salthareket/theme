<?php

/**
 * OembedVideo
 * Parse YouTube, Vimeo, Dailymotion URLs - extract video data, poster, title.
 * Poster images are downloaded and cached in WP Media Library.
 *
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-04-01
 *     - Fix: $image_size type hint int -> string|int (ACF'den "1600x900" gibi string gelebiliyor)
 *   1.0.0 - Onceki stabil versiyon
 *
 * How to use:
 *   $video = new OembedVideo('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
 *   $data = $video->get();
 *   $data = $video->get(['autoplay' => 1, 'muted' => 1]);
 *   $video = new OembedVideo('https://vimeo.com/123456789', 1280);
 *
 * Supported: YouTube, Vimeo, Dailymotion, TikTok, Kick, Loom, Wistia, Twitch, TED, Reddit, Vevo
 * Any other URL with oEmbed support is auto-detected via Noembed fallback.
 */
class OembedVideo {

    private string $url;
    private string|int $image_size;
    private array $attrs;
    private $api = null;
    private string $type = '';
    private string $id = '';

    private static array $poster_cache = [];

    public function __construct(string $url = '', string|int $image_size = 0, array $attrs = []) {
        $this->url = $url;
        $this->image_size = $image_size;
        $this->attrs = $attrs;
    }

    public function get(array $attrs = []): array|false {
        if (empty($this->url)) return false;

        $url = function_exists('extract_url') ? extract_url($this->url) : $this->url;
        $parsed = $this->parse($url);
        if (!$parsed) return false;

        $this->type = $parsed['type'];
        $this->id = $parsed['id'];

        $autoplay = empty($attrs["autoplay"]) ? "0" : "1";
        $muted = empty($attrs["muted"]) ? "0" : "1";

        $result = [
            'type'      => $this->type,
            'id'        => $this->id,
            'embed'     => $this->url,
            'embed_url' => '',
            'watch'     => '',
            'src'       => $this->poster(),
            'title'     => $this->title(),
        ];

        switch ($this->type) {
            case 'youtube':
                $result['embed_url'] = "https://www.youtube-nocookie.com/embed/{$this->id}?rel=0&showinfo=0&autoplay={$autoplay}&mute={$muted}";
                $result['watch'] = "https://www.youtube.com/watch?v={$this->id}";
                break;
            case 'vimeo':
                $result['embed_url'] = "https://player.vimeo.com/video/{$this->id}?autoplay={$autoplay}&title=0&byline=0&portrait=0&mute={$muted}";
                $result['watch'] = "https://vimeo.com/{$this->id}";
                break;
            case 'dailymotion':
                $result['embed_url'] = "https://geo.dailymotion.com/player.html?autoplay={$autoplay}&ui-logo=0&ui-start-screen-info=0&startTime=0&mute={$muted}&video={$this->id}";
                $result['watch'] = "https://www.dailymotion.com/video/{$this->id}";
                break;
            case 'tiktok':
                $result['embed_url'] = "https://www.tiktok.com/embed/v2/{$this->id}";
                $result['watch'] = "https://www.tiktok.com/video/{$this->id}";
                break;
            case 'kick':
                $result['embed_url'] = "https://player.kick.com/video/{$this->id}";
                $result['watch'] = "https://kick.com/video/{$this->id}";
                break;
            case 'loom':
                $result['embed_url'] = "https://www.loom.com/embed/{$this->id}";
                $result['watch'] = "https://www.loom.com/share/{$this->id}";
                break;
            case 'wistia':
                $result['embed_url'] = "https://fast.wistia.net/embed/iframe/{$this->id}";
                $result['watch'] = "https://fast.wistia.com/medias/{$this->id}";
                break;
            case 'twitch':
                $result['embed_url'] = "https://player.twitch.tv/?video={$this->id}&parent=" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $result['watch'] = "https://www.twitch.tv/videos/{$this->id}";
                break;
            case 'twitch_clip':
                $result['embed_url'] = "https://clips.twitch.tv/embed?clip={$this->id}&parent=" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $result['watch'] = "https://clips.twitch.tv/{$this->id}";
                $result['type'] = 'twitch';
                break;
            case 'ted':
                $result['embed_url'] = "https://embed.ted.com/talks/{$this->id}";
                $result['watch'] = "https://www.ted.com/talks/{$this->id}";
                break;
            case 'reddit':
                $result['embed_url'] = ''; // Reddit videos don't have a standard embed iframe
                $result['watch'] = $this->url;
                break;
            case 'vevo':
                $result['embed_url'] = "https://embed.vevo.com/?isrc={$this->id}";
                $result['watch'] = $this->url;
                break;
        }

        return $result;
    }

    // ─── URL PARSING ─────────────────────────────────────

    private function parse(string $url = ''): array|false {
        if (empty($url)) return false;

        $parsed = parse_url($url);
        if (!isset($parsed['host'])) return false;

        $host = str_replace('www.', '', $parsed['host']);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        $type = '';
        $id = '';

        // YouTube
        if ($host === 'youtu.be') {
            $type = 'youtube';
            $id = ltrim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com'])) {
            $type = 'youtube';
            if (!empty($query)) {
                parse_str($query, $params);
                $id = $params['v'] ?? '';
            }
            if (empty($id) && preg_match('#/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
                $id = $m[2];
            }
        }
        // Vimeo
        elseif (in_array($host, ['vimeo.com', 'player.vimeo.com'])) {
            $type = 'vimeo';
            if (preg_match('/(\d+)/', $path, $m)) {
                $id = $m[1];
            }
        }
        // Dailymotion
        elseif (in_array($host, ['dailymotion.com', 'geo.dailymotion.com'])) {
            $type = 'dailymotion';
            if (strpos($path, '/video/') !== false) {
                $id = explode('/video/', $path)[1] ?? '';
                $id = strtok($id, '?#/');
            } elseif (!empty($query)) {
                parse_str($query, $params);
                $id = $params['video'] ?? '';
            }
        } elseif ($host === 'dai.ly') {
            $type = 'dailymotion';
            $id = ltrim($path, '/');
            $id = strtok($id, '?#/');
        }
        // TikTok
        elseif (in_array($host, ['tiktok.com', 'vm.tiktok.com'])) {
            $type = 'tiktok';
            if (preg_match('#/video/(\d+)#', $path, $m)) {
                $id = $m[1];
            } elseif ($host === 'vm.tiktok.com') {
                $id = ltrim($path, '/');
            }
        }
        // Kick
        elseif ($host === 'kick.com') {
            $type = 'kick';
            if (preg_match('#/video/([a-zA-Z0-9_-]+)#', $path, $m)) {
                $id = $m[1];
            } elseif (preg_match('#/([^/]+)/clip/([a-zA-Z0-9_-]+)#', $path, $m)) {
                $id = $m[2];
            }
        }
        // Loom
        elseif (in_array($host, ['loom.com', 'www.loom.com'])) {
            $type = 'loom';
            if (preg_match('#/share/([a-f0-9]+)#', $path, $m)) {
                $id = $m[1];
            }
        }
        // Wistia
        elseif (str_contains($host, 'wistia.com') || str_contains($host, 'wistia.net')) {
            $type = 'wistia';
            if (preg_match('#/medias/([a-zA-Z0-9]+)#', $path, $m)) {
                $id = $m[1];
            } elseif (preg_match('#/iframe/([a-zA-Z0-9]+)#', $path, $m)) {
                $id = $m[1];
            }
        }
        // Twitch
        elseif (in_array($host, ['twitch.tv', 'clips.twitch.tv'])) {
            $type = 'twitch';
            if (preg_match('#/videos/(\d+)#', $path, $m)) {
                $id = $m[1];
            } elseif ($host === 'clips.twitch.tv') {
                $id = ltrim($path, '/');
            } elseif (preg_match('#/([^/]+)/clip/([a-zA-Z0-9_-]+)#', $path, $m)) {
                $id = $m[2];
                $type = 'twitch_clip';
            }
        }
        // TED
        elseif ($host === 'ted.com') {
            $type = 'ted';
            if (preg_match('#/talks/([a-zA-Z0-9_]+)#', $path, $m)) {
                $id = $m[1];
            }
        }
        // Reddit video
        elseif (in_array($host, ['reddit.com', 'old.reddit.com']) || $host === 'v.redd.it') {
            $type = 'reddit';
            if ($host === 'v.redd.it') {
                $id = ltrim($path, '/');
            } elseif (preg_match('#/comments/([a-zA-Z0-9]+)#', $path, $m)) {
                $id = $m[1];
            }
        }
        // Vevo
        elseif ($host === 'vevo.com') {
            $type = 'vevo';
            if (preg_match('#/watch/[^/]+/[^/]+/([a-zA-Z0-9]+)#', $path, $m)) {
                $id = $m[1];
            }
        }

        return (!empty($type) && !empty($id)) ? ['type' => $type, 'id' => $id] : false;
    }

    // ─── POSTER IMAGE ────────────────────────────────────

    private function poster(): string {
        if (empty($this->type) || empty($this->id)) return '';

        $meta_key = "{$this->type}-{$this->id}";

        // Runtime cache
        if (isset(self::$poster_cache[$meta_key])) {
            return self::$poster_cache[$meta_key];
        }

        // DB cache — check if already downloaded
        global $wpdb;
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
            $meta_key
        ));

        if (!empty($attachment_id) && get_post($attachment_id)) {
            $url = wp_get_attachment_url($attachment_id);
            self::$poster_cache[$meta_key] = $url;
            return $url;
        }

        // Download poster
        $thumbnail_uri = $this->get_poster_url();
        if (empty($thumbnail_uri)) return '';

        if (function_exists('featured_image_from_url')) {
            $attachment_id = featured_image_from_url($thumbnail_uri, 0, false, $meta_key);
            if ($attachment_id) {
                update_post_meta($attachment_id, $meta_key, $attachment_id);
                $url = wp_get_attachment_url($attachment_id);
                self::$poster_cache[$meta_key] = $url;
                return $url;
            }
        }

        // Fallback: return remote URL directly
        self::$poster_cache[$meta_key] = $thumbnail_uri;
        return $thumbnail_uri;
    }

    private function get_poster_url(): string {
        return match ($this->type) {
            'youtube'      => $this->get_youtube_poster(),
            'vimeo'        => $this->get_vimeo_poster(),
            'dailymotion'  => $this->get_dailymotion_poster(),
            'tiktok', 'loom', 'wistia', 'ted', 'vevo' => $this->get_oembed_poster(),
            'twitch', 'twitch_clip' => $this->get_noembed_poster(),
            'reddit'       => $this->get_noembed_poster(),
            'kick'         => '',
            default        => $this->get_noembed_poster(), // Universal fallback
        };
    }

    private function get_youtube_poster(): string {
        // Try maxresdefault first, fallback to hqdefault
        $max = "https://img.youtube.com/vi/{$this->id}/maxresdefault.jpg";
        $response = wp_remote_head($max, ['timeout' => 3]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return $max;
        }
        return "https://img.youtube.com/vi/{$this->id}/hqdefault.jpg";
    }

    private function get_vimeo_poster(): string {
        $this->fetch_api();
        if (!$this->api || empty($this->api->thumbnail_url)) return '';

        $url = $this->api->thumbnail_url;
        $high_res = preg_replace('/-[d_]*\\d+x\\d+$/', '', $url);
        if (!empty($this->image_size)) {
            $high_res .= "-d_{$this->image_size}";
        }

        // Quick check if high-res exists
        $response = wp_remote_head($high_res, ['timeout' => 3]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return $high_res;
        }
        return $url;
    }

    private function get_dailymotion_poster(): string {
        $this->fetch_api();
        return ($this->api && isset($this->api->thumbnail_720_url)) ? $this->api->thumbnail_720_url : '';
    }

    /**
     * Generic oEmbed poster — works for TikTok, Loom, Wistia, TED, Vevo (all return thumbnail_url)
     */
    private function get_oembed_poster(): string {
        $this->fetch_api();
        return ($this->api && isset($this->api->thumbnail_url)) ? $this->api->thumbnail_url : '';
    }

    /**
     * Universal poster via Noembed.com — works for any URL Noembed supports
     */
    private function get_noembed_poster(): string {
        $response = wp_remote_get("https://noembed.com/embed?url=" . urlencode($this->url), ['timeout' => 5]);
        if (is_wp_error($response)) return '';
        $data = json_decode(wp_remote_retrieve_body($response));
        return (is_object($data) && isset($data->thumbnail_url)) ? $data->thumbnail_url : '';
    }

    // ─── API ─────────────────────────────────────────────

    private function title(): string {
        $this->fetch_api();
        return ($this->api && isset($this->api->title)) ? $this->api->title : '';
    }

    private function fetch_api(): void {
        if ($this->api !== null) return; // Already fetched (or failed)

        $api_uri = match ($this->type) {
            'youtube'              => "https://noembed.com/embed?url=" . urlencode(function_exists('extract_url') ? extract_url($this->url) : $this->url),
            'vimeo'                => "https://vimeo.com/api/oembed.json?url=" . urlencode("https://vimeo.com/{$this->id}"),
            'dailymotion'          => "https://api.dailymotion.com/video/{$this->id}?fields=title,thumbnail_720_url",
            'tiktok'               => "https://www.tiktok.com/oembed?url=" . urlencode($this->url),
            'loom'                 => "https://www.loom.com/v1/oembed?url=" . urlencode("https://www.loom.com/share/{$this->id}"),
            'wistia'               => "https://fast.wistia.com/oembed?url=" . urlencode("https://fast.wistia.com/medias/{$this->id}"),
            'ted'                  => "https://www.ted.com/services/v1/oembed.json?url=" . urlencode("https://www.ted.com/talks/{$this->id}"),
            'twitch', 'twitch_clip', 'reddit', 'vevo' => "https://noembed.com/embed?url=" . urlencode($this->url),
            default                => "https://noembed.com/embed?url=" . urlencode($this->url), // Noembed universal fallback
        };

        if (empty($api_uri)) {
            $this->api = (object) [];
            return;
        }

        $response = wp_remote_get($api_uri, ['timeout' => 5]);
        if (is_wp_error($response)) {
            $this->api = (object) [];
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body);
        $this->api = is_object($decoded) ? $decoded : (object) [];
    }
}
