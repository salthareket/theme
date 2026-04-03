<?php

/**
 * WebBotChecker
 * Detects whether the current visitor is a bot or a real user.
 * Modernized with comprehensive bot database and categorization.
 *
 * Originally based on tisuchi's WebBotChecker, fully rewritten.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Quick check (static)
 * if (WebBotChecker::check()) { // it's a bot }
 *
 * // Instance usage
 * $bot = WebBotChecker::instance();
 *
 * $bot->isBot();              // true/false
 * $bot->getBotName();         // "Googlebot" or ""
 * $bot->getBotCategory();     // "search", "social", "ai", "seo", "monitor", "feed" or ""
 * $bot->getBot();             // ['key'=>'googlebot', 'pattern'=>'googlebot', 'name'=>'Googlebot', 'category'=>'search'] or null
 *
 * // Category checks
 * $bot->isSearchEngine();     // Google, Bing, Yandex, Baidu...
 * $bot->isSocialBot();        // Facebook, Twitter, WhatsApp, Telegram...
 * $bot->isAIBot();            // GPTBot, ClaudeBot, ByteSpider, CCBot...
 * $bot->isSEOBot();           // Semrush, Ahrefs, Screaming Frog...
 * $bot->isBotCategory('monitor');              // UptimeRobot, Pingdom, GTmetrix...
 * $bot->isBotCategory(['search', 'social']);   // multiple categories
 *
 * // Real-world examples
 *
 * // Don't count bot visits in analytics
 * if (!WebBotChecker::check()) { track_page_view(); }
 *
 * // Block AI scrapers
 * if ($bot->isAIBot()) { wp_die('AI scraping not allowed', '', 403); }
 *
 * // Serve lightweight HTML to search engines (no JS heavy stuff)
 * if ($bot->isSearchEngine()) { $context['lightweight'] = true; }
 *
 * // Log which bot visited
 * if ($bot->isBot()) { error_log('Bot visit: ' . $bot->getBotName() . ' [' . $bot->getBotCategory() . ']'); }
 *
 * ──────────────────────────────────────────────────────────
 */
class WebBotChecker {

    private static $instance = null;
    private $user_agent = '';
    private $matched_bot = null;
    private $checked = false;

    /**
     * Bot categories:
     * - search    : Search engine crawlers
     * - social    : Social media preview bots
     * - ai        : AI training/scraping bots
     * - seo       : SEO tool crawlers
     * - monitor   : Uptime/performance monitors
     * - feed      : RSS/feed readers
     * - other     : Misc bots
     */
    private static $bots = [
        // Search engines
        'googlebot'       => ['pattern' => 'googlebot',       'name' => 'Googlebot',        'category' => 'search'],
        'bingbot'         => ['pattern' => 'bingbot',         'name' => 'Bingbot',          'category' => 'search'],
        'yandexbot'       => ['pattern' => 'yandexbot',       'name' => 'YandexBot',        'category' => 'search'],
        'baiduspider'     => ['pattern' => 'baiduspider',     'name' => 'Baiduspider',      'category' => 'search'],
        'duckduckbot'     => ['pattern' => 'duckduckbot',     'name' => 'DuckDuckBot',      'category' => 'search'],
        'slurp'           => ['pattern' => 'slurp',           'name' => 'Yahoo Slurp',      'category' => 'search'],
        'applebot'        => ['pattern' => 'applebot',        'name' => 'Applebot',         'category' => 'search'],
        'googleother'     => ['pattern' => 'googleother',     'name' => 'GoogleOther',      'category' => 'search'],
        'google-inspectiontool' => ['pattern' => 'google-inspectiontool', 'name' => 'Google Inspection', 'category' => 'search'],

        // Social media
        'facebookbot'     => ['pattern' => 'facebookexternalhit', 'name' => 'Facebook',     'category' => 'social'],
        'twitterbot'      => ['pattern' => 'twitterbot',      'name' => 'Twitter/X',        'category' => 'social'],
        'linkedinbot'     => ['pattern' => 'linkedinbot',     'name' => 'LinkedIn',         'category' => 'social'],
        'whatsapp'        => ['pattern' => 'whatsapp',        'name' => 'WhatsApp',         'category' => 'social'],
        'telegrambot'     => ['pattern' => 'telegrambot',     'name' => 'Telegram',         'category' => 'social'],
        'discordbot'      => ['pattern' => 'discordbot',      'name' => 'Discord',          'category' => 'social'],
        'slackbot'        => ['pattern' => 'slackbot',        'name' => 'Slack',            'category' => 'social'],
        'pinterestbot'    => ['pattern' => 'pinterest',       'name' => 'Pinterest',        'category' => 'social'],

        // AI bots
        'gptbot'          => ['pattern' => 'gptbot',          'name' => 'GPTBot',           'category' => 'ai'],
        'chatgpt-user'    => ['pattern' => 'chatgpt-user',    'name' => 'ChatGPT User',     'category' => 'ai'],
        'claudebot'       => ['pattern' => 'claude-web',      'name' => 'ClaudeBot',        'category' => 'ai'],
        'anthropic'       => ['pattern' => 'anthropic',       'name' => 'Anthropic',        'category' => 'ai'],
        'cohere-ai'       => ['pattern' => 'cohere-ai',       'name' => 'Cohere',           'category' => 'ai'],
        'bytespider'      => ['pattern' => 'bytespider',      'name' => 'ByteSpider',       'category' => 'ai'],
        'ccbot'           => ['pattern' => 'ccbot',           'name' => 'CCBot',            'category' => 'ai'],
        'perplexitybot'   => ['pattern' => 'perplexitybot',   'name' => 'PerplexityBot',    'category' => 'ai'],

        // SEO tools
        'semrushbot'      => ['pattern' => 'semrushbot',      'name' => 'SemrushBot',       'category' => 'seo'],
        'ahrefsbot'       => ['pattern' => 'ahrefsbot',       'name' => 'AhrefsBot',        'category' => 'seo'],
        'mj12bot'         => ['pattern' => 'mj12bot',         'name' => 'Majestic',         'category' => 'seo'],
        'dotbot'          => ['pattern' => 'dotbot',          'name' => 'DotBot',           'category' => 'seo'],
        'rogerbot'        => ['pattern' => 'rogerbot',        'name' => 'Moz RogerBot',     'category' => 'seo'],
        'screaming frog'  => ['pattern' => 'screaming frog',  'name' => 'Screaming Frog',   'category' => 'seo'],

        // Monitors & misc
        'uptimerobot'     => ['pattern' => 'uptimerobot',     'name' => 'UptimeRobot',      'category' => 'monitor'],
        'pingdom'         => ['pattern' => 'pingdom',         'name' => 'Pingdom',          'category' => 'monitor'],
        'gtmetrix'        => ['pattern' => 'gtmetrix',        'name' => 'GTmetrix',         'category' => 'monitor'],
        'pagespeed'       => ['pattern' => 'pagespeed',       'name' => 'PageSpeed',        'category' => 'monitor'],
        'feedfetcher'     => ['pattern' => 'feedfetcher',     'name' => 'FeedFetcher',      'category' => 'feed'],
        'feedly'          => ['pattern' => 'feedly',          'name' => 'Feedly',           'category' => 'feed'],
    ];

    public static function instance() {
        if (is_null(self::$instance)) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {
        $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : '';
    }

    /**
     * Lazy detection — runs once, caches result
     */
    private function detect() {
        if ($this->checked) return;
        $this->checked = true;

        if (empty($this->user_agent)) return;

        $ua_lower = strtolower($this->user_agent);
        foreach (self::$bots as $key => $bot) {
            if (strpos($ua_lower, $bot['pattern']) !== false) {
                $this->matched_bot = array_merge($bot, ['key' => $key]);
                return;
            }
        }
    }

    /**
     * Is the visitor a bot?
     */
    public function isBot(): bool {
        $this->detect();
        return $this->matched_bot !== null;
    }

    /**
     * Is the visitor a bot of a specific category?
     * @param string|array $category  'search', 'social', 'ai', 'seo', 'monitor', 'feed'
     */
    public function isBotCategory($category): bool {
        $this->detect();
        if (!$this->matched_bot) return false;
        $categories = is_array($category) ? $category : [$category];
        return in_array($this->matched_bot['category'], $categories, true);
    }

    /**
     * Get matched bot info or null
     * @return array|null  ['key', 'pattern', 'name', 'category']
     */
    public function getBot(): ?array {
        $this->detect();
        return $this->matched_bot;
    }

    /**
     * Get bot name or empty string
     */
    public function getBotName(): string {
        $this->detect();
        return $this->matched_bot ? $this->matched_bot['name'] : '';
    }

    /**
     * Get bot category or empty string
     */
    public function getBotCategory(): string {
        $this->detect();
        return $this->matched_bot ? $this->matched_bot['category'] : '';
    }

    /**
     * Is this a search engine crawler?
     */
    public function isSearchEngine(): bool {
        return $this->isBotCategory('search');
    }

    /**
     * Is this a social media preview bot?
     */
    public function isSocialBot(): bool {
        return $this->isBotCategory('social');
    }

    /**
     * Is this an AI scraper/training bot?
     */
    public function isAIBot(): bool {
        return $this->isBotCategory('ai');
    }

    /**
     * Is this an SEO tool crawler?
     */
    public function isSEOBot(): bool {
        return $this->isBotCategory('seo');
    }

    /**
     * Static shortcut — check if current request is a bot
     */
    public static function check(): bool {
        return self::instance()->isBot();
    }

    // Legacy compatibility
    public function isThatBot(): int {
        return $this->isBot() ? 1 : 0;
    }
    public function getMyBot(): string {
        return $this->getBotName() ?: 'Not a bot';
    }
}
