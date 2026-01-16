<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides a simple and flexible way to add color and formatting to console output.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

use InvalidArgumentException;

class ConsoleColor
{
    private static array $foreground_colors = [
        'bold' => '1',
        'dim' => '2',
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1;31',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37',
        'normal' => '0;39',
    ];

    private static array $background_colors = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light_gray' => '47',
    ];

    private static array $options = [
        'underline' => '4',
        'blink' => '5',
        'reverse' => '7',
        'hidden' => '8',
    ];

    private static string $EOF = "\n";

    public static function log(string $str = '', string $color = 'normal', bool $newline = true, ?string $background_color = null): void
    {
        if (is_bool($color)) {
            $newline = $color;
            $color = 'normal';
        } elseif (is_string($color) && is_string($newline)) {
            $background_color = $newline;
            $newline = true;
        }

        $str = $newline ? $str . self::$EOF : $str;
        echo self::applyColor($str, $color, $background_color);
    }

    private static function applyColor(string $string, string $foreground_color, ?string $background_color): string
    {
        $colored_string = '';

        if (isset(self::$foreground_colors[$foreground_color])) {
            $colored_string .= "\033[" . self::$foreground_colors[$foreground_color] . 'm';
        } else {
            throw new InvalidArgumentException($foreground_color . ' is not a valid foreground color');
        }

        if ($background_color && isset(self::$background_colors[$background_color])) {
            $colored_string .= "\033[" . self::$background_colors[$background_color] . 'm';
        }

        $colored_string .= $string . "\033[0m";

        return $colored_string;
    }

    public static function bell(int $count = 1): void
    {
        echo str_repeat("\007", $count);
    }

    public static function __callStatic(string $name, array $args): string
    {
        $string = $args[0];
        $formatted_string = '';

        if (isset(self::$foreground_colors[$name])) {
            $formatted_string .= "\033[" . self::$foreground_colors[$name] . 'm';
        } else {
            throw new InvalidArgumentException($name . ' is not a valid foreground color');
        }

        array_shift($args);

        foreach ($args as $option) {
            if (isset(self::$background_colors[$option])) {
                $formatted_string .= "\033[" . self::$background_colors[$option] . 'm';
            } elseif (isset(self::$options[$option])) {
                $formatted_string .= "\033[" . self::$options[$option] . 'm';
            }
        }

        $formatted_string .= $string . "\033[0m";

        return $formatted_string;
    }
}
