<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Number provides a functionality for working with numbers in various contexts.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Number;

use NumberFormatter;

class Number
{
    public static function trailingZeros(int $number, int $pad, int $limit): string
    {
        if ($number < $limit) {
            return sprintf("%0$pad" . 'd', $number);
        }

        return (string) $number;
    }

    public static function tosize(int $number): string
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return round($number / pow(1024, ($i = floor(log($number, 1024)))), 2) . ' ' . $unit[$i];
    }

    public static function toAlphabet(int $number): string
    {
        $alphabet_array = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'E', '6' => 'F', '7' => 'G', '8' => 'H', '9' => 'I', '10' => 'J', '11' => 'K', '12' => 'L', '13' => 'M', '14' => 'N', '15' => 'O', '16' => 'P', '17' => 'Q', '18' => 'R', '19' => 'S', '20' => 'T', '21' => 'U', '22' => 'V', '23' => 'W', '24' => 'X', '25' => 'Y', '26' => 'Z'];

        return $alphabet_array[$number] ?? '';
    }

    public static function ordinal(int $num): string
    {
        if (! empty($num)) {
            $append = 'th';

            if ($num % 10 == 1 && $num % 100 != 11) {
                $append = 'st';
            }

            if ($num % 10 == 2 && $num % 100 != 12) {
                $append = 'nd';
            }

            if ($num % 10 == 3 && $num % 100 != 13) {
                $append = 'rd';
            }

            return $num . $append;
        }

        return '';
    }

    public static function toWords(int $number, bool $default = false): string
    {
        if ($default) {
            $formater = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            $formater->setTextAttribute(NumberFormatter::DEFAULT_RULESET, '%spellout-numbering-verbose');

            return $formater->format($number);
        }

        return self::numberTowords($number);
    }

    private static function numberTowords(int $number): string
    {
        $words = [
            'zero',
            'one',
            'two',
            'three',
            'four',
            'five',
            'six',
            'seven',
            'eight',
            'nine',
            'ten',
            'eleven',
            'twelve',
            'thirteen',
            'fourteen',
            'fifteen',
            'sixteen',
            'seventeen',
            'eighteen',
            'nineteen',
            'twenty',
            30 => 'thirty',
            40 => 'forty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety',
            100 => 'hundred',
            1000 => 'thousand',
            1000000 => 'million',
            1000000000 => 'billion',
            1000000000000 => 'trillion',
            1000000000000000 => 'quadrillion',
            1000000000000000000 => 'quintillion'
        ];

        if (is_numeric($number)) {
            $number_in_words = '';
            $number = (int) round($number);

            if ($number < 0) {
                $number = -$number;
                $number_in_words = 'minus ';
            }

            if ($number > 999) {
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;

                $number_in_words = $number_in_words . self::numberTowords($numBaseUnits) . ' ' . $words[$baseUnit];

                if ($remainder) {
                    $number_in_words = $number_in_words . ($remainder > 100 ? ', ' : ' and ') . self::numberTowords($remainder);
                }
            } elseif ($number > 100) {
                $number_in_words = $number_in_words . self::numberTowords((int) floor($number / 100)) . ' ' . $words[100];
                $tens = $number % 100;

                if ($tens) {
                    $number_in_words = $number_in_words . ' and ' . self::numberTowords($tens);
                }
            } elseif ($number > 20) {
                $number_in_words = $number_in_words . $words[10 * floor($number / 10)];
                $units = $number % 10;

                if ($units) {
                    $number_in_words = $number_in_words . ' ' . self::numberTowords($units);
                }
            } else {
                $number_in_words = $number_in_words . $words[$number];
            }

            return $number_in_words;
        }

        return '';
    }

    public static function toDecimal(mixed $value): mixed
    {
        return $value / 100;
    }

    public static function toInteger(mixed $value): int
    {
        return (int) ($value * 100);
    }

    public static function to_integer(mixed $value): int
    {
        return self::toInteger($value);
    }

    public static function pretify(mixed $number, int $decimal_place = 0): string
    {
        $split = explode('.', (string) $number);

        $number = $split[0];

        $number = preg_replace('/[^\d]+/', '', $number);

        if (! is_numeric($number)) {
            return '0';
        }

        if ($number < 1000) {
            return $number;
        }

        $unit = intval(log((float) $number, 1000));

        $units = ['', 'K', 'M', 'B', 'T', 'Q'];

        if (array_key_exists($unit, $units)) {
            $value = number_format($number / pow(1000, $unit), $decimal_place);

            $split = explode('.', $value);

            if (! empty($split[1])) {
                $sub = substr($split[1], 0, 1);
                $split[1] = $sub > 0 ? $sub : '';
            }

            if (empty($split[1])) {
                unset($split[1]);
            }

            $value = implode('.', $split);

            return sprintf('%s%s', $value, $units[$unit]);
        }

        return $number;
    }

    public static function toRoman(int $number): string
    {
        $roman_numerals = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        $result = '';

        foreach ($roman_numerals as $roman => $value) {
            $matches = intval($number / $value);
            $result .= str_repeat($roman, $matches);
            $number %= $value;
        }

        return $result;
    }

    public static function clamp(int|float $number, int|float $min, int|float $max): int|float
    {
        return max($min, min($max, $number));
    }

    public static function percentage(int|float $value, int|float $total): float
    {
        if ($total == 0) {
            return 0;
        }

        return ($value / $total) * 100;
    }

    public static function toPercentage(int|float $value, int $precision = 0): string
    {
        return round($value, $precision) . '%';
    }

    public static function random(int $min = 0, int|null $max = null): int
    {
        if ($max === null) {
            $max = PHP_INT_MAX;
        }

        return random_int($min, $max);
    }

    public static function fromRoman(string $roman): int
    {
        $romans = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        $result = 0;
        $roman = strtoupper($roman);

        foreach ($romans as $key => $value) {
            while (str_starts_with($roman, $key)) {
                $result += $value;
                $roman = substr($roman, strlen($key));
            }
        }

        return $result;
    }

    public static function abbreviate(mixed $number, int $decimal_place = 0): string
    {
        return self::pretify($number, $decimal_place);
    }

    public static function format(mixed $number, int $pos = 0): string
    {
        return number_format((float) $number, $pos);
    }
}
