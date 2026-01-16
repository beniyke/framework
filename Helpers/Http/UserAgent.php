<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * UserAgent has functionality to detect the type of user agent that is accessing a web page.
 * The information gotten can be used to customize the user experience
 * or serve different content to users based on their device or browser.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

class UserAgent
{
    private const PLATFORMS = [
        'windows nt 10.0' => 'Windows 10',
        'windows nt 6.3' => 'Windows 8.1',
        'windows nt 6.2' => 'Windows 8',
        'windows nt 6.1' => 'Windows 7',
        'windows nt 6.0' => 'Windows Vista',
        'windows nt 5.2' => 'Windows 2003',
        'windows nt 5.1' => 'Windows XP',
        'os x' => 'Mac OS X',
        'android' => 'Android',
        'linux' => 'Linux',
        'iphone' => 'iOS',
        'ipad' => 'iOS',
        'ipod' => 'iOS',
        'blackberry' => 'BlackBerry',
        'windows phone' => 'Windows Phone',
        'symbian' => 'Symbian OS',
    ];

    private const BROWSERS = [
        'OPR' => 'Opera',
        'Chrome' => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari' => 'Safari',
        'Trident.* rv' => 'Internet Explorer',
        'MSIE' => 'Internet Explorer',
        'Opera.*?Version' => 'Opera',
    ];

    private const MOBILES = [
        'mobile' => 'Generic Mobile',
        'iphone' => 'iPhone',
        'ipad' => 'iPad',
        'ipod' => 'iPod',
        'android' => 'Android',
        'blackberry' => 'BlackBerry',
        'windows phone' => 'Windows Phone',
        'opera mini' => 'Opera Mini',
        'opera mobi' => 'Opera Mobile',
    ];

    private const ROBOTS = [
        'googlebot' => 'Googlebot',
        'bingbot' => 'Bingbot',
        'baiduspider' => 'Baiduspider',
        'yandex' => 'YandexBot',
        'msnbot' => 'MSNBot',
    ];

    private readonly string $agent;

    private readonly array $languages;

    private readonly array $charsets;

    private ?string $platform = null;

    private ?string $browser = null;

    private ?string $version = null;

    private ?string $mobile = null;

    private ?string $robot = null;

    private bool $is_parsed = false;

    public function __construct(array $server)
    {
        $this->agent = trim($server['HTTP_USER_AGENT'] ?? '');

        $accept_lang = strtolower(trim($server['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $this->languages = $accept_lang ? explode(',', preg_replace('/(;\s?q=[0-9\.]+)|\s/i', '', $accept_lang)) : [];

        $acceptCharset = strtolower(trim($server['HTTP_ACCEPT_CHARSET'] ?? ''));
        $this->charsets = $acceptCharset ? explode(',', preg_replace('/(;\s?q=.+)|\s/i', '', $acceptCharset)) : [];
    }

    public function agentString(): string
    {
        return $this->agent;
    }

    public function platform(): ?string
    {
        $this->parse();

        return $this->platform;
    }

    public function browser(): ?string
    {
        $this->parse();

        return $this->browser;
    }

    public function version(): ?string
    {
        $this->parse();

        return $this->version;
    }

    public function robot(): ?string
    {
        $this->parse();

        return $this->robot;
    }

    public function mobile(): ?string
    {
        $this->parse();

        return $this->mobile;
    }

    public function device(): string
    {
        $this->parse();

        return $this->mobile ?? 'PC';
    }

    public function languages(): array
    {
        return $this->languages;
    }

    public function isRobot(): bool
    {
        $this->parse();

        return $this->robot !== null;
    }

    public function isMobile(): bool
    {
        $this->parse();

        return $this->mobile !== null;
    }

    private function parse(): void
    {
        if ($this->is_parsed || empty($this->agent)) {
            return;
        }

        $this->robot = $this->findMatch(self::ROBOTS);

        if ($this->robot === null) {
            $this->mobile = $this->findMatch(self::MOBILES);
            $this->platform = $this->findMatch(self::PLATFORMS);
            [$this->browser, $this->version] = $this->findBrowserAndVersion();
        }

        $this->is_parsed = true;
    }

    private function findMatch(array $definitions): ?string
    {
        foreach ($definitions as $key => $value) {
            if (preg_match('|' . preg_quote($key) . '|i', $this->agent)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Specifically parses for browser and version number.
     */
    private function findBrowserAndVersion(): array
    {
        foreach (self::BROWSERS as $key => $value) {
            if (preg_match('|' . $key . '.*?([0-9\.]+)|i', $this->agent, $matches)) {
                return [$value, $matches[1] ?? null];
            }
        }

        return [null, null];
    }

    public static function referrer(?array $server_vars = null): string
    {
        $server = $server_vars ?? $_SERVER;

        return trim($server['HTTP_REFERER'] ?? '');
    }

    public static function ip(?array $server_vars = null): string
    {
        $server = $server_vars ?? $_SERVER;
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (! empty($server[$header])) {
                $ip = trim(explode(',', $server[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
