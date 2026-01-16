<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides a flexible and extensible functionality for text manipulation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\String;

final class Text extends Inflector
{
    public static function wrap(string $str, int $charlim = 76): string
    {
        is_numeric($charlim) or $charlim = 76;

        $str = preg_replace('| +|', ' ', $str);

        if (strpos($str, "\r") !== false) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }

        $unwrap = [];
        if (preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches)) {
            for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
                $unwrap[] = $matches[1][$i];
                $str = str_replace($matches[0][$i], '{{unwrapped' . $i . '}}', $str);
            }
        }

        $str = wordwrap($str, $charlim, "\n", false);
        $output = '';

        foreach (explode("\n", $str) as $line) {
            if (mb_strlen($line) <= $charlim) {
                $output .= $line . "\n";

                continue;
            }

            $temp = '';

            while (mb_strlen($line) > $charlim) {
                if (preg_match('!\[url.+\]|://|www\.!', $line)) {
                    break;
                }

                $temp .= mb_substr($line, 0, $charlim - 1);
                $line = mb_substr($line, $charlim - 1);
            }

            if ($temp !== '') {
                $output .= $temp . "\n" . $line . "\n";
            } else {
                $output .= $line . "\n";
            }
        }

        if (count($unwrap) > 0) {
            foreach ($unwrap as $key => $val) {
                $output = str_replace('{{unwrapped' . $key . '}}', $val, $output);
            }
        }

        return $output;
    }

    public static function censor(string $str, array $censored, string $replacement = ''): string
    {
        if (empty($censored)) {
            return $str;
        }

        $str = ' ' . $str . ' ';
        $delim = '[-_\'\"`(){}<>\[\]|!?@#%&,.:;^~*+=\/ 0-9\n\r\t]';

        foreach ($censored as $badword) {
            $badword = str_replace('\*', '\w*?', preg_quote($badword, '/'));

            if ($replacement !== '') {
                $str = preg_replace(
                    "/({$delim})(" . $badword . ")({$delim})/i",
                    "\\1{$replacement}\\3",
                    $str
                );
            } elseif (preg_match_all("/{$delim}(" . $badword . "){$delim}/i", $str, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
                $matches = $matches[1];

                for ($i = count($matches) - 1; $i >= 0; $i--) {
                    $length = strlen($matches[$i][0]);
                    $str = substr_replace(
                        $str,
                        str_repeat('#', $length),
                        (int) $matches[$i][1],
                        $length
                    );
                }
            }
        }

        return trim($str);
    }

    /**
     * trims text to a space then adds ellipses if desired
     */
    public static function trim(string $input, int $length, bool $ellipses = true, bool $strip_html = true): string
    {
        if ($strip_html) {
            $input = strip_tags($input);
        }

        if (mb_strlen($input) <= $length) {
            return $input;
        }

        $trimmed_text = mb_substr($input, 0, $length);
        $last_space = mb_strrpos($trimmed_text, ' ');

        if ($last_space !== false) {
            $trimmed_text = mb_substr($trimmed_text, 0, $last_space);
        }

        if ($ellipses) {
            $trimmed_text .= '...';
        }

        return $trimmed_text;
    }

    public static function estimated_read_time(string $content, int $words_per_minute = 200, bool $with_seconds = false): string
    {
        $words = str_word_count(strip_tags($content));

        $minutes = floor($words / $words_per_minute);
        $seconds = floor($words % $words_per_minute / ($words_per_minute / 60));

        if ($minutes < 1) {
            return $seconds . ' second' . ($seconds == 1 ? '' : 's');
        }

        $estimation = $minutes . ' minute' . ($minutes == 1 ? '' : 's');

        if ($with_seconds && $seconds > 0) {
            $estimation .= ', ' . $seconds . ' second' . ($seconds == 1 ? '' : 's');
        }

        return $estimation;
    }

    public static function inflect(string $value, int $count): string
    {
        // Return singular if count is 1 or 0 (as per test expectations)
        if ($count === 1 || $count === 0) {
            return $value;
        }

        return self::pluralize($value);
    }
}
