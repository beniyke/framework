<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Collection of common regex patterns for validation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation;

class Regex
{
    private static array $patterns = [
        'int' => '\b\d+\b',
        'float' => '\b\d+\.\d+\b',
        'mailstrings' => '(content-type|mime-version|multipart/mixed|Content-Transfer-Encoding|bcc|cc|to|headers):',
        'email' => '[\w.-]+@[\w.-]+\.[\w-]{2,}',
        'html' => "<[^>]+>.*?<\/[^>]+>|<\w+\s*\/?>",
        'url' => 'https?:\/\/(?:[\w_-]+\.)+[\w-]{2,}(?:[-\w._~:\/?#\[\]@!$&\'()*+,;=]*)',
        'zip' => '(\d{5}-\d{4}|\d{5}|[A-Z]\d[A-Z]\s\d[A-Z]\d)',
        'alpha' => '\b[a-zA-Z]+\b',
        'num' => '\b\d+\b',
        'alphanum' => '\b[a-zA-Z0-9]+\b',
        'bbcode' => '\[([a-zA-Z][a-zA-Z0-9]*)\b[^]]*].*?\[\/\1]',
        'intphone' => '\+\d{1,4}[\s.-]?\d{3,14}',
        'address' => '\d+\s[-\w.,\s#:]+',
        'fullname' => "[a-zA-Z]+\s+([-a-zA-Z.'\s]|[0-9](nd|rd|th))+",
        'name' => "[-a-zA-Z.'\s]+",
        'lastname' => "([-a-zA-Z.'\s]|[0-9](nd|rd|th))+",
        'ipv4' => '\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b',
        'ipv6' => '([a-fA-F0-9]{1,4}:){7}[a-fA-F0-9]{1,4}',
        'username' => '[a-zA-Z0-9_]{3,16}',
        'password' => '(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,128}',
        'creditcard' => '\b(?:\d{4}[ -]?){3}\d{4}\b',
        'date' => '\b(?:[0-9]{4})[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])\b',
        'time' => '\b([01]?[0-9]|2[0-3]):[0-5][0-9]\b',
        'hexcolor' => '#(?:[0-9a-fA-F]{3}){1,2}',
        'uuid' => '[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}',
        'timestamp' => '\b\d{10}(?:\d{3})?\b',
        'iso8601' => '\b\d{4}-[01]\d-[0-3]\d[T\s][0-2]\d:[0-5]\d:[0-5]\d(?:\.\d+)?(?:Z|[+-][0-2]\d:[0-5]\d)?\b',
        'datetime' => '\b(?:[0-9]{4})[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])\s(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\b',
        'daterange' => '\b(?:[0-9]{4})[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])\s+to\s+(?:[0-9]{4})[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])\b',
    ];

    private static array $descriptions = [
        'int' => 'Integer',
        'float' => 'Float',
        'mailstrings' => 'Mail Strings',
        'email' => 'Email',
        'html' => 'HTML',
        'url' => 'URL',
        'zip' => 'Zip Code',
        'alpha' => 'Alphabetic Character',
        'num' => 'Number',
        'alphanum' => 'Alphanumeric',
        'bbcode' => 'BB Code',
        'intphone' => 'International Phone Number',
        'address' => 'Address',
        'name' => 'Name',
        'fullname' => 'Full Name',
        'lastname' => 'Last Name',
        'ipv4' => 'IPv4 Address',
        'ipv6' => 'IPv6 Address',
        'username' => 'Username',
        'password' => 'Password',
        'creditcard' => 'Credit Card',
        'date' => 'Date',
        'time' => 'Time',
        'hexcolor' => 'Hex Color',
        'uuid' => 'UUID',
        'timestamp' => 'Timestamp',
        'iso8601' => 'ISO 8601 DateTime',
        'datetime' => 'DateTime',
        'daterange' => 'Date Range',
    ];

    /**
     * Checks if the entire value matches the specified regex type exactly.
     */
    public static function is(string $type, string $val): bool
    {
        return isset(self::$patterns[$type]) && preg_match('/^' . self::$patterns[$type] . '$/iu', (string) $val) === 1;
    }

    /**
     * Checks if the value contains a substring that matches the specified regex type.
     */
    public static function has(string $type, string $val): bool
    {
        return isset(self::$patterns[$type]) && preg_match('/' . self::$patterns[$type] . '/iu', (string) $val) === 1;
    }

    public static function hasAny(array $types, string $val): bool
    {
        foreach ($types as $type) {
            if (self::has($type, $val)) {
                return true;
            }
        }

        return false;
    }

    public static function getDescription(string $type): ?string
    {
        return self::$descriptions[$type] ?? null;
    }

    public static function getArray(string $str): array
    {
        return array_filter(array_map('trim', explode(',', $str)));
    }
}
