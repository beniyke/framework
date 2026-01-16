<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Str provides secure, robust, and commonly needed
 * string manipulation, formatting, and conversion functionalities.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\String;

use InvalidArgumentException;
use Transliterator;

class Str
{
    /**
     * Generates a 16-character alphanumeric reference ID.
     */
    public static function refid(): string
    {
        return static::random('alnum', 16);
    }

    /**
     * Generates a cryptographically secure random string.
     */
    public static function random(string $type = 'alnum', int $len = 32): string
    {
        switch ($type) {
            case 'alpha':
                $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'alnum':
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'numeric':
                $pool = '0123456789';
                break;
            case 'nozero':
                $pool = '123456789';
                break;
            case 'secure':
                $bytes = (int) ceil($len / 2);

                return substr(bin2hex(random_bytes($bytes)), 0, $len);
            default:
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        $result = '';
        $max = strlen($pool) - 1;

        for ($i = 0; $i < $len; $i++) {
            $result .= $pool[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Generates a strong, random password based on specified options.
     */
    public static function password(int $length = 32, array $option = ['letters' => true, 'numbers' => true, 'symbols' => true, 'spaces' => false]): string
    {
        $characters = [];

        if (isset($option['letters']) && $option['letters']) {
            $characters = array_merge($characters, range('a', 'z'), range('A', 'Z'));
        }

        if (isset($option['numbers']) && $option['numbers']) {
            $characters = array_merge($characters, range(0, 9));
        }

        if (isset($option['symbols']) && $option['symbols']) {
            $symbols = ['~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '-', '.', '<', '>', '?', '/', '\\', '{', '}', '[', ']', ':', ';'];
            $characters = array_merge($characters, $symbols);
        }

        if (isset($option['spaces']) && $option['spaces']) {
            $characters[] = ' ';
        }

        if (empty($characters)) {
            throw new InvalidArgumentException('Password generation failed: At least one character type must be enabled.');
        }

        $password = [];
        $limit = count($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password[] = $characters[random_int(0, $limit)];
        }

        return implode('', $password);
    }

    /**
     * Escapes a string or an array of strings for HTML output (XSS prevention).
     */
    public static function htmlEscape(mixed $var, bool $doubleEncode = true): mixed
    {
        if (empty($var)) {
            return $var;
        }

        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $var[$key] = static::htmlEscape($value, $doubleEncode);
            }

            return $var;
        }

        return htmlspecialchars((string) $var, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }

    /**
     * Encodes special characters into HTML entities. (Wrapper for htmlspecialchars)
     */
    public static function htmlcharacterEncode(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Decodes special HTML entities back to characters. (Wrapper for htmlspecialchars_decode)
     */
    public static function htmlcharacterDecode(string $str): string
    {
        return htmlspecialchars_decode($str);
    }

    /**
     * Encodes all applicable characters to HTML entities. (Wrapper for htmlentities)
     */
    public static function htmlentityEncode(string $str): string
    {
        return htmlentities($str);
    }

    /**
     * Decodes HTML entities back to characters. (Wrapper for html_entity_decode)
     */
    public static function htmlentityDecode(string $str): string
    {
        return html_entity_decode($str);
    }

    /**
     * Recursively removes slashes from a string or array. (Wrapper for stripslashes)
     */
    public static function stripSlashes(string|array $str): string|array
    {
        if (! is_array($str)) {
            return stripslashes((string) $str);
        }

        foreach ($str as $key => $val) {
            $str[$key] = static::stripSlashes($val);
        }

        return $str;
    }

    /**
     * Legacy sanitization function. Use dedicated methods (`htmlEscape`, `stripSlashes`) instead.
     */
    public static function sanitize(string $string): string
    {
        return stripcslashes(htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Removes all whitespace characters from a string.
     */
    public static function removeSpaces(string $str): string
    {
        return preg_replace('/\s+/', '', $str);
    }

    /**
     * Reduces multiple whitespace characters to a single space and trims the string.
     */
    public static function removeExtraSpace(string $str): string
    {
        return trim(preg_replace('/\s+/', ' ', $str));
    }

    /**
     * Removes leading and trailing slashes from a string. (Wrapper for trim)
     */
    public static function trimSlashes(string $str): string
    {
        return trim($str, '/');
    }

    /**
     * Strips whitespace from the beginning and end of a string. (Wrapper for trim)
     */
    public static function trimSpaces(string $str): string
    {
        return trim($str);
    }

    /**
     * Strips characters/spaces from the left and right of a string. (Wrapper for ltrim/rtrim)
     */
    public static function lrtrim(string $string, ?string $character = null): string
    {
        $string = empty($character) ? ltrim($string) : ltrim($string, $character);

        return rtrim($string, $character);
    }

    /**
     * Strips HTML and PHP tags from a string. (Wrapper for strip_tags)
     */
    public static function strip(string $str): string
    {
        return strip_tags($str);
    }

    /**
     * Returns a substring. (Wrapper for substr)
     */
    public static function reduce(string $str, int $start, int $stop): string
    {
        return substr($str, $start, $stop);
    }

    /**
     * Replaces all occurrences of the search string/array. (Wrapper for str_replace)
     */
    public static function replace(string $str, string|array $find, string|array $replace): string
    {
        return str_replace($find, $replace, $str);
    }

    /**
     * Converts a string to all uppercase letters. (Wrapper for strtoupper)
     */
    public static function uppercase(string $string): string
    {
        return strtoupper($string);
    }

    /**
     * Converts a string to all lowercase letters. (Wrapper for strtolower)
     */
    public static function lowercase(string $string): string
    {
        return strtolower($string);
    }

    /**
     * Capitalizes the first letter of every word in a string. (Wrapper for ucwords)
     */
    public static function capitalize(string $string): string
    {
        return ucwords($string);
    }

    /**
     * Converts the string to Title Case, excluding common small words.
     */
    public static function titleCase(string $string): string
    {
        $smallWords = [
            'a',
            'an',
            'and',
            'as',
            'at',
            'but',
            'by',
            'for',
            'in',
            'nor',
            'of',
            'on',
            'or',
            'so',
            'the',
            'to',
            'up',
            'yet',
        ];

        $result = strtolower($string);
        $result = ucwords($result);

        foreach ($smallWords as $word) {
            $result = str_replace(
                ' ' . ucfirst($word) . ' ',
                ' ' . $word . ' ',
                $result
            );
        }

        return ucfirst($result);
    }

    /**
     * Cleans a string by removing common non-text characters and tags.
     */
    public static function clean(string $value): string
    {
        $pattern = '/<[^>]+>|[\[\]{}()]|[^a-zA-Z0-9\s,.!?]/';

        return preg_replace($pattern, '', $value);
    }

    /**
     * Cleans an email string to contain only valid email characters.
     */
    public static function cleanEmail(string $email): string
    {
        $pattern = '/[^a-zA-Z0-9@._-]/';

        return preg_replace($pattern, '', $email);
    }

    /**
     * Retains only alphabets, numbers, and spaces in a string.
     */
    public static function alphabetAndNumberOnly(string $value): string
    {
        $pattern = '/[^a-zA-Z0-9\s]/';

        return preg_replace($pattern, '', $value);
    }

    /**
     * Converts a string to snake_case (e.g., 'userName' becomes 'user_name').
     */
    public static function toSnakeCase(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     * Converts a string to camelCase (e.g., 'user_name' becomes 'userName').
     */
    public static function toCamelCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return lcfirst($string);
    }

    /**
     * Truncates a string by character length.
     */
    public static function limit(string $string, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($string) <= $limit) {
            return $string;
        }

        return rtrim(mb_substr($string, 0, $limit)) . $end;
    }

    /**
     * Limits the number of words in a string.
     */
    public static function limitWords(string $string, int $limit = 100, string $end = '...'): string
    {
        $words = explode(' ', $string);

        if (count($words) <= $limit) {
            return $string;
        }

        return implode(' ', array_slice($words, 0, $limit)) . $end;
    }

    /**
     * Pads the string to the specified length on the left. (Wrapper for str_pad)
     */
    public static function padLeft(string $string, int $length, string $padString = ' '): string
    {
        return str_pad($string, $length, $padString, STR_PAD_LEFT);
    }

    /**
     * Pads the string to the specified length on the right. (Wrapper for str_pad)
     */
    public static function padRight(string $string, int $length, string $padString = ' '): string
    {
        return str_pad($string, $length, $padString, STR_PAD_RIGHT);
    }

    /**
     * Determine if a string starts with a given substring.
     */
    public static function startsWith(string $haystack, string $needle, bool $ignoreCase = false): bool
    {
        if ($needle === '') {
            return true;
        }

        if ($ignoreCase) {
            return str_starts_with(mb_strtolower($haystack), mb_strtolower($needle));
        }

        return str_starts_with($haystack, $needle);
    }

    /**
     * Determine if a string ends with a given substring.
     */
    public static function endsWith(string $haystack, string $needle, bool $ignoreCase = false): bool
    {
        if ($needle === '') {
            return true;
        }

        if ($ignoreCase) {
            return str_ends_with(mb_strtolower($haystack), mb_strtolower($needle));
        }

        return str_ends_with($haystack, $needle);
    }

    /**
     * Ensure the string ends with the given suffix.
     */
    public static function finish(string $string, string $cap): string
    {
        $quoted = preg_quote($cap, '/');

        return preg_match('/' . $quoted . '$/', $string) ? $string : $string . $cap;
    }

    /**
     * Ensure the string begins with the given prefix.
     */
    public static function start(string $string, string $cap): string
    {
        return static::startsWith($string, $cap) ? $string : $cap . $string;
    }

    /**
     * Checks if a string contains any of the given needles.
     */
    public static function contains(string $haystack, string|array $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        $needles = is_array($needles) ? $needles : [$needles];

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a string contains only letters and numbers.
     */
    public static function isAlphaNumeric(string $string): bool
    {
        if ($string === '') {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9]+$/', $string);
    }

    /**
     * Determine if the string is entirely lowercase.
     */
    public static function isLowercase(string $string): bool
    {
        return strtolower($string) === $string && $string !== '';
    }

    /**
     * Determine if the string is entirely uppercase.
     */
    public static function isUppercase(string $string): bool
    {
        return strtoupper($string) === $string && $string !== '';
    }

    /**
     * Converts the string encoding from one charset to another.
     */
    public static function convertEncoding(string $string, string $toCharset, string $fromCharset = 'UTF-8'): string
    {
        if (! function_exists('mb_convert_encoding')) {
            return $string;
        }

        return mb_convert_encoding($string, $toCharset, $fromCharset);
    }

    /**
     * Transliterates a string to its closest ASCII equivalent (useful for safe slugs/filenames).
     */
    public static function transliterate(string $string): string
    {
        if (class_exists(Transliterator::class)) {
            $transliterator = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: [^[:alnum:]] remove;', Transliterator::FORWARD);
            if ($transliterator) {
                $string = $transliterator->transliterate($string);
            }
        }

        $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);

        return trim($string);
    }

    /**
     * Creates a URL-friendly slug from a string.
     */
    public static function slug(string $string, string $replacement = '-'): string
    {
        // Remove HTML tags
        $string = strip_tags($string);

        // Character transliteration map
        $charMap = [
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',
        ];

        // Replace special characters
        $string = strtr($string, $charMap);

        // Convert to lowercase
        $string = strtolower($string);

        // Replace non-alphanumeric characters with the replacement
        $string = preg_replace('/[^a-z0-9]+/', $replacement, $string);

        // Remove duplicate replacements
        $string = preg_replace('/' . preg_quote($replacement, '/') . '+/', $replacement, $string);

        // Trim replacement from start and end

        return trim($string, $replacement);
    }

    /**
     * Replaces slug separators with a space.
     */
    public static function removeSlug(string $string, string $replacement = '-'): string
    {
        return str_replace($replacement, ' ', trim(strip_tags($string)));
    }

    /**
     * Applies a list of actions (callbacks or Str methods) to array values based on keys.
     */
    public static function touch(array $array, array $actions): array
    {
        $data = [];

        $mapAction = function (string $key, mixed $value) use ($actions): mixed {
            if (! isset($actions[$key])) {
                return $value;
            }

            $functions = is_array($actions[$key]) ? $actions[$key] : [$actions[$key]];

            foreach ($functions as $function) {
                if (is_callable($function)) {
                    $value = $function($value);

                    continue;
                }

                if (method_exists(static::class, $function)) {
                    $value = static::$function($value);
                }
            }

            return $value;
        };

        foreach ($array as $key => $value) {
            $data[$key] = $mapAction($key, $value);
        }

        return $data;
    }

    /**
     * Creates a comma-separated list with a conjunction.
     */
    public static function prettyImplode(array $items, string $conjunction = 'and'): string
    {
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $items[0];
        }

        if ($count === 2) {
            return $items[0] . " $conjunction " . $items[1];
        }

        $lastItem = array_pop($items);

        return implode(', ', $items) . ", $conjunction " . $lastItem;
    }

    /**
     * Shortens a name to the first name and initials of middle/last names.
     */
    public static function shortenWithInitials(string $string): string
    {
        $parts = explode(' ', trim($string));

        if (count($parts) === 0) {
            return '';
        }

        $main = ucfirst(strtolower($parts[0]));
        $initials = '';

        for ($i = 1; $i < count($parts); $i++) {
            if (! empty($parts[$i])) {
                $char = strtoupper(substr($parts[$i], 0, 1));
                $initials .= $char . '.';
            }
        }

        return $main . ($initials ? ' ' . $initials : '');
    }

    /**
     * Masks a portion of a string with a given character.
     *
     * @param string $string    The string to mask
     * @param string $character The character to use for masking
     * @param int    $index     The starting index (characters before this are kept)
     * @param int    $length    The number of characters to mask
     *
     * @return string The masked string
     */
    public static function mask(string $string, string $character = '*', int $index = 0, ?int $length = null): string
    {
        if ($length === null) {
            $length = strlen($string) - $index;
        }

        $start = substr($string, 0, $index);
        $end = substr($string, $index + $length);
        $masked = str_repeat($character, $length);

        return $start . $masked . $end;
    }

    /**
     * Masks an email address while preserving the first character and the domain.
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return $email;
        }

        $localPart = $parts[0];
        $domainPart = $parts[1];

        if (strlen($localPart) <= 1) {
            return $email;
        }

        $maskedLocal = $localPart[0] . str_repeat('*', strlen($localPart) - 1);

        return $maskedLocal . '@' . $domainPart;
    }

    /**
     * Masks the values of sensitive keys in an array.
     */
    public static function maskSensitiveData(array $data, array $keywords, string $mask = '*'): array
    {
        $maskedData = [];
        $lowerKeywords = array_map('strtolower', $keywords);

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;

            foreach ($lowerKeywords as $keyword) {
                if (str_contains($lowerKey, $keyword)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                if (is_string($value) && $value !== '') {
                    $maskedData[$key] = str_repeat($mask, strlen($value) > 4 ? 10 : strlen($value));
                } elseif (is_numeric($value) || is_bool($value)) {
                    $maskedData[$key] = str_repeat($mask, 10);
                } else {
                    $maskedData[$key] = '[' . strtoupper(gettype($value)) . ' ' . str_repeat($mask, 6) . ']';
                }
            } elseif (is_array($value)) {
                $maskedData[$key] = static::maskSensitiveData($value, $keywords, $mask);
            } else {
                $maskedData[$key] = $value;
            }
        }

        return $maskedData;
    }

    /**
     * Converts newline characters to HTML entity for newlines.
     */
    public static function htmlencodeNewline(?string $string, string $connector = '&#10;'): ?string
    {
        if (! empty($string)) {
            $strings = preg_split('/\r\n|\r|\n/', $string);

            return implode($connector, $strings);
        }

        return $string;
    }

    /**
     * Converts URLs in a string into clickable HTML anchors.
     */
    public static function makeUrlClickable(string $string): string
    {
        $regPattern = "/(((http|https|ftp|ftps)\:\/\/)|(www\.))[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\:[0-9]+)?(\/\S*)?/";

        return preg_replace($regPattern, '<a style="text-decoration: underline; color: #0066b2" href="$0" target="_blank" rel="noopener noreferrer"><strong>$0</strong></a>', $string);
    }

    /**
     * Capitalize the first character of a string. (Alias for ucfirst)
     */
    public static function ucfirst(string $string): string
    {
        return ucfirst($string);
    }

    /**
     * Convert to title case. (Alias for capitalize)
     */
    public static function title(string $string): string
    {
        return static::capitalize($string);
    }

    /**
     * Convert to camelCase. (Alias for toCamelCase)
     */
    public static function camel(string $string): string
    {
        return static::toCamelCase($string);
    }

    /**
     * Convert to snake_case. (Alias for toSnakeCase)
     */
    public static function snake(string $string): string
    {
        return static::toSnakeCase($string);
    }

    public static function kebab(string $string): string
    {
        return str_replace('_', '-', static::toSnakeCase($string));
    }

    /**
     * Convert to StudlyCase (PascalCase).
     */
    public static function studly(string $string): string
    {
        $camel = static::toCamelCase($string);

        return ucfirst($camel);
    }

    /**
     * Replace placeholders with array values sequentially.
     */
    public static function replaceArray(string $search, array $replacements, string $subject): string
    {
        $segments = explode($search, $subject);
        $result = array_shift($segments);

        foreach ($segments as $segment) {
            $result .= (array_shift($replacements) ?? $search) . $segment;
        }

        return $result;
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }

        return substr($subject, $pos + strlen($search));
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }

        return substr($subject, 0, $pos);
    }

    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        $fromPos = strpos($subject, $from);
        if ($fromPos === false) {
            return '';
        }

        $start = $fromPos + strlen($from);
        $toPos = strpos($subject, $to, $start);

        if ($toPos === false) {
            return '';
        }

        return substr($subject, $start, $toPos - $start);
    }

    public static function length(string $string): int
    {
        return mb_strlen($string);
    }

    /**
     * Convert to lowercase. (Alias for lowercase)
     */
    public static function lower(string $string): string
    {
        return static::lowercase($string);
    }

    /**
     * Convert to uppercase. (Alias for uppercase)
     */
    public static function upper(string $string): string
    {
        return static::uppercase($string);
    }

    /**
     * Extract a substring. (Wrapper for substr)
     */
    public static function substr(string $string, int $start, ?int $length = null): string
    {
        if ($length === null) {
            return substr($string, $start);
        }

        return substr($string, $start, $length);
    }

    /**
     * Limit the number of words in a string. (Alias for limitWords)
     */
    public static function words(string $string, int $words = 100, string $end = '...'): string
    {
        return static::limitWords($string, $words, $end);
    }

    /**
     * Determine if a string matches a given pattern (supports wildcards).
     */
    public static function is(string $pattern, string $value): bool
    {
        // Convert wildcard pattern to regex
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\\*', '.*', $pattern);

        return (bool) preg_match('/^' . $pattern . '$/u', $value);
    }

    /**
     * Pad both sides of a string.
     */
    public static function padBoth(string $string, int $length, string $pad = ' '): string
    {
        return str_pad($string, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Repeat a string n times.
     */
    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    public static function reverse(string $string): string
    {
        return strrev($string);
    }

    /**
     * Replace multiple substrings in a string using an associative array.
     */
    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }
}
