<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provides comprehensive email validation including:
 * - Format validation
 * - Disposable email detection
 * - Role-based account detection
 * - Domain pattern matching (with wildcard support)
 * - DNS MX record validation
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation\Email;

use Exception;
use Helpers\File\Cache;

class EmailValidator
{
    private string $email;

    private string $domain;

    private string $localPart;

    private bool $isValidFormat = false;

    public function __construct(string $email)
    {
        $this->email = trim($email);
        $this->parseEmail();
    }

    private function parseEmail(): void
    {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->isValidFormat = true;
            $parts = explode('@', $this->email, 2);
            $this->localPart = $parts[0] ?? '';
            $this->domain = strtolower($parts[1] ?? '');
        } else {
            $this->isValidFormat = false;
            $this->localPart = '';
            $this->domain = '';
        }
    }

    public function isValid(): bool
    {
        return $this->isValidFormat;
    }

    public function isDisposable(): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        return DisposableDomainList::isDisposable($this->domain);
    }

    /**
     * Check if email is a role-based account
     */
    public function isRoleAccount(): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        return RoleAccountList::isRoleAccount($this->email);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getLocalPart(): string
    {
        return $this->localPart;
    }

    public function domainMatches(array $patterns): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $domain = $this->domain;

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($domain === $pattern) {
                return true;
            }

            // Wildcard pattern matching
            if (strpos($pattern, '*') !== false) {
                // Escape dots first, then convert wildcards to regex
                $regexPattern = preg_quote($pattern, '/');
                $regexPattern = str_replace('\\*', '.*', $regexPattern);
                $regexPattern = '/^' . $regexPattern . '$/i';

                if (preg_match($regexPattern, $domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function domainDoesNotMatch(array $patterns): bool
    {
        return ! $this->domainMatches($patterns);
    }

    public function isFromDomain(string|array $domains): bool
    {
        if (is_string($domains)) {
            $domains = [$domains];
        }

        return $this->domainMatches($domains);
    }

    public function hasMxRecord(int $timeout = 5): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        try {
            // Set DNS timeout (if supported by system)
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', (string) $timeout);

            // Check for MX records
            $hasMx = checkdnsrr($this->domain, 'MX');

            // Restore original timeout
            ini_set('default_socket_timeout', $originalTimeout);

            return $hasMx;
        } catch (Exception $e) {
            /**
             * Graceful fallback - if DNS check fails, assume valid
             * This prevents blocking legitimate emails due to DNS issues
             */
            return true;
        }
    }

    /**
     * Verify if email mailbox actually exists via SMTP
     *
     * Results are cached by default for 24 hours to improve performance.
     * WARNING: This is slow (10+ seconds) on cache miss and may be blocked by mail servers.
     * Respects configuration settings for excluded domains and caching.
     */
    public function hasValidMailbox(?int $timeout = null, ?bool $debug = null): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $config = config('email_validation.php');
        $smtpConfig = $config['smtp_verification'] ?? [];

        if (! ($smtpConfig['enabled'] ?? false)) {
            return true;
        }

        $excludedDomains = $smtpConfig['exclude_domains'] ?? [];

        foreach ($excludedDomains as $excludedDomain) {
            if (strtolower($this->domain) === strtolower($excludedDomain)) {
                return true;
            }
        }

        $cacheConfig = $smtpConfig['cache'] ?? [];
        $cacheEnabled = $cacheConfig['enabled'] ?? true;

        if ($cacheEnabled) {
            $cacheKey = ($cacheConfig['key_prefix'] ?? 'smtp_verify_') . md5(strtolower($this->email));
            $cacheDuration = $cacheConfig['duration'] ?? 86400; // 24 hours default

            $cached = $this->getCachedResult($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $timeout = $timeout ?? ($smtpConfig['timeout'] ?? 10);
        $debug = $debug ?? ($smtpConfig['debug'] ?? false);

        try {
            $verifier = new SmtpVerifier($this->email, $timeout, $debug);
            $result = $verifier->verify();

            if ($cacheEnabled) {
                $this->cacheResult($cacheKey, $result, $cacheDuration);
            }

            return $result;
        } catch (Exception $e) {
            $gracefulFallback = $smtpConfig['graceful_fallback'] ?? true;

            if ($cacheEnabled && $gracefulFallback) {
                $this->cacheResult($cacheKey, true, 3600); // Cache for 1 hour
            }

            return $gracefulFallback;
        }
    }

    private function getCachedResult(string $key): ?bool
    {
        return Cache::create('cache')->read($key);
    }

    /**
     * Cache SMTP verification result
     */
    private function cacheResult(string $key, bool $result, int $duration): void
    {
        Cache::create('cache')->write($key, $result ? 1 : 0, $duration);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Validate email against multiple criteria
     *
     * @param array $checks Array of checks to perform: ['disposable', 'role', 'mx', 'smtp']
     *
     * @return array Array of failed checks (empty if all pass)
     */
    public function validateAgainst(array $checks): array
    {
        $failures = [];

        if (! $this->isValid()) {
            $failures[] = 'format';

            return $failures;
        }

        foreach ($checks as $check) {
            switch (strtolower($check)) {
                case 'disposable':
                    if ($this->isDisposable()) {
                        $failures[] = 'disposable';
                    }
                    break;
                case 'role':
                    if ($this->isRoleAccount()) {
                        $failures[] = 'role';
                    }
                    break;
                case 'mx':
                    if (! $this->hasMxRecord()) {
                        $failures[] = 'mx';
                    }
                    break;
                case 'smtp':
                    if (! $this->hasValidMailbox()) {
                        $failures[] = 'smtp';
                    }
                    break;
            }
        }

        return $failures;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->email;
    }
}
