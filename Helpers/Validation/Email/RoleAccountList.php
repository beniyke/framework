<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Maintains a list of role-based email account prefixes.
 *
 * Role-based emails are those not specific to an individual but to a role
 * or function within an organization (e.g., admin@, support@, info@)
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation\Email;

class RoleAccountList
{
    /**
     * Core list of role-based email prefixes
     */
    private static array $prefixes = [
        'abuse',
        'accounts',
        'admin',
        'administrator',
        'billing',
        'careers',
        'contact',
        'feedback',
        'hello',
        'help',
        'hostmaster',
        'hr',
        'info',
        'jobs',
        'mail',
        'marketing',
        'noreply',
        'no-reply',
        'office',
        'postmaster',
        'recruitment',
        'sales',
        'security',
        'spam',
        'support',
        'team',
        'webmaster',
    ];

    /**
     * Custom role prefixes added via configuration
     */
    private static array $customPrefixes = [];

    /**
     * Get all role account prefixes
     */
    public static function get(): array
    {
        return array_merge(self::$prefixes, self::$customPrefixes);
    }

    /**
     * Check if an email is a role-based account
     */
    public static function isRoleAccount(string $email): bool
    {
        $email = strtolower(trim($email));

        // Extract local part (before @)
        if (strpos($email, '@') === false) {
            return false;
        }

        [$localPart] = explode('@', $email, 2);
        $localPart = strtolower(trim($localPart));

        $allPrefixes = self::get();

        return in_array($localPart, $allPrefixes, true);
    }

    public static function addCustom(array $prefixes): void
    {
        $prefixes = array_map('strtolower', array_map('trim', $prefixes));
        self::$customPrefixes = array_unique(array_merge(self::$customPrefixes, $prefixes));
    }

    public static function removeCustom(array $prefixes): void
    {
        $prefixes = array_map('strtolower', array_map('trim', $prefixes));
        self::$customPrefixes = array_diff(self::$customPrefixes, $prefixes);
    }

    /**
     * Clear all custom prefixes
     */
    public static function clearCustom(): void
    {
        self::$customPrefixes = [];
    }

    public static function count(): int
    {
        return count(self::get());
    }
}
