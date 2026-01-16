<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Password validation helper rules.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation;

use Helpers\Array\Collections;
use Helpers\String\Text;

class Password
{
    use ValidationTrait;

    private const RULE_UPPER = 'uppercase';
    private const RULE_LOWER = 'lowercase';
    private const RULE_SPECIAL = 'special';
    private const RULE_NUMERIC = 'numeric';
    public const RULE_LENGTH_MIN = 'length_min';
    public const RULE_LENGTH_MAX = 'length_max';
    private const RULE_NOT_IN = 'not_in';
    private const RULE_NOT_HAVING = 'not_having';
    private const RULE_NOT_COMMON = 'not_common';

    private const RULE_UPPER_ERROR = '{password} must contain {value} uppercase {character}.';
    private const RULE_LOWER_ERROR = '{password} must contain {value} lowercase {character}.';
    private const RULE_NUMERIC_ERROR = '{password} must contain {value} numeric {character}.';
    private const RULE_SPECIAL_ERROR = '{password} must contain {value} special {character}.';
    private const RULE_LENGTH_MIN_ERROR = '{password} must be a minimum of {value} {character}';
    private const RULE_LENGTH_MAX_ERROR = '{password} must be a maximum of {value} {character}';
    private const RULE_NOT_IN_ERROR = '{password} must not be any of these: {value}';
    private const RULE_NOT_HAVING_ERROR = '{password} must not contain the following: {value}';
    private const RULE_NOT_COMMON_ERROR = '{password} is too common';

    public array $config = [
        self::RULE_UPPER => 0,
        self::RULE_LOWER => 0,
        self::RULE_SPECIAL => 0,
        self::RULE_NUMERIC => 0,
        self::RULE_LENGTH_MIN => 0,
        self::RULE_LENGTH_MAX => 0,
        self::RULE_NOT_IN => [],
        self::RULE_NOT_HAVING => [],
        self::RULE_NOT_COMMON => false,
    ];

    private Collections $rules;

    private array $errors = [];

    private static string $label = 'Password';

    private static array $commonPasswords = [];

    public function __construct()
    {
        $this->rules = Collections::make($this->config);
    }

    public function label(string $label): self
    {
        static::$label = $label;

        return $this;
    }

    public function config(array $config): self
    {
        $this->rules->push($config);

        return $this;
    }

    public function require_uppercase(int $occurance = 1): self
    {
        $this->rules->put(self::RULE_UPPER, $occurance);

        return $this;
    }

    public function require_lowercase(int $occurance = 1): self
    {
        $this->rules->put(self::RULE_LOWER, $occurance);

        return $this;
    }

    public function require_special(int $occurance = 1): self
    {
        $this->rules->put(self::RULE_SPECIAL, $occurance);

        return $this;
    }

    public function require_numeric(int $occurance = 1): self
    {
        $this->rules->put(self::RULE_NUMERIC, $occurance);

        return $this;
    }

    public function not_in(array $stack): self
    {
        $this->rules->put(self::RULE_NOT_IN, $stack);

        return $this;
    }

    public function not_having(array $stack): self
    {
        $this->rules->put(self::RULE_NOT_HAVING, $stack);

        return $this;
    }

    public function minimum_length(int $length): self
    {
        $this->rules->put(self::RULE_LENGTH_MIN, $length);

        return $this;
    }

    public function maximum_length(int $length): self
    {
        $this->rules->put(self::RULE_LENGTH_MAX, $length);

        return $this;
    }

    public function not_common(bool $value = true): self
    {
        $this->rules->put(self::RULE_NOT_COMMON, $value);

        return $this;
    }

    public function check(string $string): self
    {
        $error = [];

        foreach ($this->rules->get() as $rule => $value) {
            switch ($rule) {
                case self::RULE_UPPER:
                    if ($value > 0 && ! $this->uppercase($string, $value)) {
                        $error[] = static::_format_error_message(self::RULE_UPPER_ERROR, $value);
                    }
                    break;

                case self::RULE_LOWER:
                    if ($value > 0 && ! $this->lowercase($string, $value)) {
                        $error[] = static::_format_error_message(self::RULE_LOWER_ERROR, $value);
                    }
                    break;

                case self::RULE_SPECIAL:
                    if ($value > 0 && ! $this->specialcharacter($string, $value)) {
                        $error[] = static::_format_error_message(self::RULE_SPECIAL_ERROR, $value);
                    }
                    break;

                case self::RULE_NUMERIC:
                    if ($value > 0 && ! $this->numericcharacter($string, $value)) {
                        $error[] = static::_format_error_message(self::RULE_NUMERIC_ERROR, $value);
                    }
                    break;

                case self::RULE_LENGTH_MIN:
                    if ($value > 0 && ! $this->len($string, 'min', $value)) {
                        $error[] = static::_format_error_message(self::RULE_LENGTH_MIN_ERROR, $value);
                    }
                    break;

                case self::RULE_LENGTH_MAX:
                    if ($value > 0 && ! $this->len($string, 'max', $value)) {
                        $error[] = static::_format_error_message(self::RULE_LENGTH_MAX_ERROR, $value);
                    }
                    break;

                case self::RULE_NOT_IN:
                    if (count($value) > 0 && ! $this->is_not_in($value, $string)) {
                        $error[] = static::_format_error_message(self::RULE_NOT_IN_ERROR, $value);
                    }
                    break;

                case self::RULE_NOT_HAVING:
                    if (count($value) > 0 && ! $this->is_not_having($value, $string)) {
                        $error[] = static::_format_error_message(self::RULE_NOT_HAVING_ERROR, $value);
                    }
                    break;

                case self::RULE_NOT_COMMON:
                    if ($value && ! $this->is_not_common($string)) {
                        $error[] = static::_format_error_message(self::RULE_NOT_COMMON_ERROR);
                    }
                    break;

                default:
                    break;
            }
        }

        $this->errors = $error;

        return $this;
    }

    private static function _format_error_message(string $error_message, mixed $value = null): string
    {
        $val_str = is_array($value) ? implode(', ', $value) : (string) $value;

        if (is_array($value)) {
            $char_count = 0;
        } elseif (is_numeric($value)) {
            $char_count = (int) $value;
        } else {
            $char_count = 0;
        }

        $error_message = str_replace(
            ['{value}', '{character}', '{password}'],
            [$val_str, Text::inflect('character', $char_count), static::$label],
            $error_message
        );

        return $error_message;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function is_valid(): bool
    {
        return count($this->errors) === 0;
    }

    public function is_not_having(array $stack, string $string): bool
    {
        foreach ($stack as $forbidden_substring) {
            if (stripos($string, $forbidden_substring) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the password is NOT an item in the stack.
     *
     * @param array<string> $stack  Forbidden full passwords (e.g., ['password123', 'admin'])
     * @param string        $string The password being checked
     *
     * @return bool True if the password is NOT in the stack.
     */
    public function is_not_in(array $stack, string $string): bool
    {
        return ! in_array($string, $stack, true);
    }

    public function is_not_common(?string $value = null): bool
    {
        if ($value === null) {
            return true;
        }

        return ! static::is_common($value);
    }

    public static function is_common(string $password): bool
    {
        if (empty(static::$commonPasswords)) {
            $file = static::_get_resource();
            static::$commonPasswords = array_map(
                'trim',
                file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            );
        }

        return in_array($password, static::$commonPasswords, true);
    }

    /**
     * Returns the absolute path to the common password list resource.
     */
    private static function _get_resource(): string
    {
        return __DIR__ . '/Resource/passwordlist.txt';
    }
}
