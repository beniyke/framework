<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait provides functionalities for data validation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation;

use Database\DB;
use DateTime;
use Exception;

trait ValidationTrait
{
    public function email(?string $email): bool
    {
        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function integer(mixed $value): bool
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_INT);
    }

    public function url(?string $url): bool
    {
        return $url !== null && filter_var($url, FILTER_VALIDATE_URL);
    }

    public function numeric(mixed $value): bool
    {
        return $value !== null && is_numeric($value);
    }

    public function string(mixed $value): bool
    {
        return $value !== null && is_string($value);
    }

    public function alnum(?string $value): bool
    {
        return $value !== null && ctype_alnum($value);
    }

    public function isBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
    }

    public function alpha(?string $value): bool
    {
        return $value !== null && preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    protected function alphabetical(string $value): bool
    {
        return preg_match('/^[ äöüèéàáíìóòôîêÄÖÜÈÉÀÁÍÌÓÒÔÊa-z]{2,}$/is', $value) === 1;
    }

    public function phone(string $value): bool
    {
        return preg_match("/^[+]?[- \(\)\/0-9]{10,18}$/is", $value) === 1;
    }

    public function ipv4(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public function ipv6(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    public function creditcard(string $value): bool
    {
        $number = preg_replace('/\D/', '', $value);

        if (! is_numeric($number)) {
            return false;
        }

        // Luhn Algorithm
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($i % 2 == $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return ($sum % 10) === 0;
    }

    public function uppercase(string $string, int $min_occurence = 1): bool
    {
        return preg_match_all('/[A-Z]/', $string) >= $min_occurence;
    }

    public function lowercase(string $string, int $min_occurence = 1): bool
    {
        return preg_match_all('/[a-z]/', $string) >= $min_occurence;
    }

    public function specialcharacter(string $string, int $min_occurence = 1): bool
    {
        return preg_match_all("/[^a-zA-Z0-9\s]/", $string) >= $min_occurence;
    }

    public function numericcharacter(string $string, int $min_occurence = 1): bool
    {
        return preg_match_all('/[0-9]/', $string) >= $min_occurence;
    }

    protected function minlength(string $value, int $length): bool
    {
        return mb_strlen($value) >= $length;
    }

    protected function exactlength(string $value, int $length): bool
    {
        return mb_strlen($value) === $length;
    }

    protected function maxlength(string $value, int $length): bool
    {
        return mb_strlen($value) <= $length;
    }

    public function length(mixed $value, string $boundry): bool
    {
        if (is_array($value) || is_object($value)) {
            foreach ($value as $val) {
                if (! $this->length($val, $boundry)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $value = (string) $value;
        $split = explode(',', $boundry);
        $min = (int) $split[0];
        $max = isset($split[1]) ? (int) $split[1] : null;

        if ($max === null) {
            return $this->exactlength($value, $min);
        }

        return $this->minlength($value, $min) && $this->maxlength($value, $max);
    }

    public function len(mixed $value, string $type, int $limit): bool
    {
        if (is_array($value) || is_object($value)) {
            foreach ($value as $str) {
                if (! $this->len($str, $type, $limit)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        switch ($type) {
            case 'min':
                return $this->minlength((string) $value, $limit);
            case 'max':
                return $this->maxlength((string) $value, $limit);
            default:
                return false;
        }
    }

    public function limit(mixed $value, int $min, ?int $max = null): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->limit($val, $min, $max)) {
                    return false;
                }
            }

            return true;
        }

        if (! $this->numeric($value)) {
            return false;
        }

        $val = (float) $value;

        if ($max === null) {
            return $val >= $min;
        }

        return ($val >= $min) && ($val <= $max);
    }

    public function less_than(float $value, float $compare_against): bool
    {
        return $value < $compare_against;
    }

    public function greater_than(float $value, float $compare_against): bool
    {
        return $value > $compare_against;
    }

    public function exist(mixed $value, string|array $array): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (! $this->exist($v, $array)) {
                    return false;
                }
            }

            return true;
        }

        if (is_string($array) && str_contains($array, '.')) {
            [$table, $column] = explode('.', $array, 2);

            return DB::table($table)->where($column, $value)->exists();
        }

        return in_array($value, $array, true);
    }

    public function is_available(mixed $value, string|callable $callback): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if ($this->is_available($val, $callback)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($callback) && str_contains($callback, '.')) {
            [$table, $column] = explode('.', $callback, 2);
            $ignoreValue = null;
            $ignoreColumn = 'id';

            if (str_contains($column, ':')) {
                [$column, $extra] = explode(':', $column, 2);
                if (str_contains($extra, ',')) {
                    [$ignoreValue, $ignoreColumn] = explode(',', $extra, 2);
                } else {
                    $ignoreValue = $extra;
                }
            }

            $query = DB::table($table)->where($column, $value);

            if ($ignoreValue !== null) {
                $query->where($ignoreColumn, '!=', $ignoreValue);
            }

            return $query->exists();
        }

        return call_user_func($callback, $value);
    }

    public function confirm(array $value, string $field): bool
    {
        return isset($value[$field]) && ! $this->is_empty($value[$field]);
    }

    public function same(mixed $value, string $field, array $data): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->same($val, $field, $data)) {
                    return false;
                }
            }

            return true;
        }

        return isset($data[$field]) && ($value === $data[$field]);
    }

    public function contain(mixed $value, string $field, array $data): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->contain($val, $field, $data)) {
                    return false;
                }
            }

            return true;
        }

        if (! isset($value, $data[$field]) || ! is_string($value) || ! is_string($data[$field])) {
            return false;
        }

        $search_terms = array_filter(explode(' ', $data[$field]));
        $normalized_value = strtolower($value);

        foreach ($search_terms as $check) {
            if (str_contains($normalized_value, strtolower($check))) {
                return true;
            }
        }

        return false;
    }

    public function is_valid_date(string $date, string $format): bool
    {
        $split = array_map('trim', explode('to', $date));

        foreach ($split as $value) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt === false || $dt->format($format) !== $value) {
                return false;
            }
        }

        return true;
    }

    public function is_date(mixed $value, string $condition): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->is_date($val, $condition)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return $this->is_valid_date($value, $condition);
    }

    private function _to_bytes(string $input): int
    {
        $number = (int) $input;
        $units = [
            'bt' => 1,
            'kb' => 1024,
            'mb' => 1048576,
            'gb' => 1073741824,
        ];

        $unit = strtolower(substr($input, -2));

        if (isset($units[$unit])) {
            $number = $number * $units[$unit];
        }

        return $number;
    }

    public function is_file(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->is_file($val)) {
                    return false;
                }
            }

            return true;
        }

        if (is_object($value) && method_exists($value, 'isEmpty')) {
            return ! $value->isEmpty();
        }

        return ! empty($value);
    }

    public function allowed_size(mixed $value, string $allowed_size): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->allowed_size($val, $allowed_size)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_object($value) || ! method_exists($value, 'getClientSize') || ! method_exists($value, 'getMaxFilesize')) {
            return false;
        }

        $allowed_size_bytes = $this->_to_bytes($allowed_size);

        $php_max_upload = $value->getMaxFilesize();
        if ($allowed_size_bytes > $php_max_upload) {
            $allowed_size_bytes = $php_max_upload;
        }

        return $value->getClientSize() <= $allowed_size_bytes;
    }

    public function allowed_type(mixed $value, array $allowed_type): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->allowed_type($val, $allowed_type)) {
                    return false;
                }
            }

            return true;
        }

        if (empty($value) || ! is_object($value) || ! method_exists($value, 'getExtension')) {
            return false;
        }

        $extension = strtolower($value->getExtension());

        return $this->exist($extension, $allowed_type);
    }

    public function is_empty(mixed $value): bool
    {
        if (! is_array($value) && ! is_object($value)) {
            if (! is_string($value)) {
                return $value !== 0 && empty($value);
            }

            $trimmed_value = trim($value);

            return $trimmed_value !== '0' && empty($trimmed_value);
        }

        if (is_array($value)) {
            if (count($value) === 0) {
                return true;
            }

            foreach ($value as $str) {
                if (! $this->is_empty($str)) {
                    return false;
                }
            }

            return true;
        }

        if (is_object($value)) {
            if (method_exists($value, 'isEmpty')) {
                return $value->isEmpty();
            }

            return empty((array) $value);
        }

        return empty($value);
    }

    public function pass_regex(mixed $value, string $condition): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->pass_regex($val, $condition)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        return preg_match($condition, (string) $value) === 1;
    }

    public function regex_validate(mixed $value, array $conditions, string $option): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->regex_validate($val, $conditions, $option)) {
                    return false;
                }
            }

            return true;
        }

        if (! class_exists(Regex::class)) {
            throw new Exception('Regex validation requires the ' . Regex::class . ' class.');
        }

        if ($option === 'any') {
            return Regex::hasAny($conditions, $value);
        }

        $validate = ($option === 'is')
            ? fn ($condition) => Regex::is($condition, $value)
            : fn ($condition) => Regex::has($condition, $value);

        foreach ($conditions as $condition) {
            if (! $validate($condition)) {
                return false;
            }
        }

        return true;
    }

    public function pass_custom_validation(mixed $value, callable $callback): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->pass_custom_validation($val, $callback)) {
                    return false;
                }
            }

            return true;
        }

        return call_user_func($callback, $value);
    }

    public function isValidLatitude(mixed $latitude): bool
    {
        if (! is_numeric($latitude)) {
            return false;
        }

        $lat = (float) $latitude;

        return $lat >= -90.0 && $lat <= 90.0;
    }

    public function isValidLongitude(mixed $longitude): bool
    {
        if (! is_numeric($longitude)) {
            return false;
        }

        $lon = (float) $longitude;

        return $lon >= -180.0 && $lon <= 180.0;
    }

    public function validateCoordinate(string $coordinate): bool
    {
        $parts = explode(',', $coordinate);

        if (count($parts) !== 2) {
            return false;
        }

        [$latitude, $longitude] = $parts;

        return $this->isValidLatitude(trim($latitude)) && $this->isValidLongitude(trim($longitude));
    }

    public function partially_empty(array $array): bool
    {
        $filtered_array = array_filter($array, function ($value) {
            return $value === 0 || $value === '0' || ! empty($value);
        });

        return count($filtered_array) > 0;
    }

    public function type(mixed $value, string $type = 'string'): bool
    {
        if (is_array($value) || is_object($value)) {
            foreach ($value as $val) {
                if (! $this->type($val, $type)) {
                    return false;
                }
            }

            return true;
        }

        $type = strtolower($type);
        $result = false;

        if ($type === 'array') {
            return false;
        }

        switch ($type) {
            case 'string':
                $result = $this->string($value);
                break;

            case 'boolean':
                $result = $this->isBoolean($value);
                break;

            case 'int':
            case 'integer':
                $result = $this->integer($value);
                break;

            case 'numeric':
            case 'number':
                $result = $this->numeric($value);
                break;

            case 'email':
                $result = is_string($value) ? $this->email($value) : false;
                break;

            case 'alnum':
            case 'alphanumeric':
                $result = is_string($value) ? $this->alnum($value) : false;
                break;

            case 'alpha':
            case 'alphabet':
                $result = is_string($value) ? $this->alpha($value) : false;
                break;

            case 'alphabetical':
                $result = is_string($value) ? $this->alphabetical($value) : false;
                break;

            case 'url':
                $result = is_string($value) ? $this->url($value) : false;
                break;

            case 'phone':
            case 'phonenumber':
                $result = is_string($value) ? $this->phone($value) : false;
                break;

            case 'coordinate':
                $result = is_string($value) ? $this->validateCoordinate($value) : false;
                break;

            case 'ipv4':
                $result = is_string($value) ? $this->ipv4($value) : false;
                break;

            case 'ipv6':
                $result = is_string($value) ? $this->ipv6($value) : false;
                break;

            case 'creditcard':
                $result = is_string($value) ? $this->creditcard($value) : false;
                break;

            default:
                $result = false;
                break;
        }

        return $result;
    }

    /**
     * Validate file upload using FileUploadValidator
     *
     * @param mixed $value   FileHandler object or array of FileHandler objects
     * @param array $options Validation options:
     *                       - 'type' => 'image'|'document'|'archive'
     *                       - 'maxSize' => int (bytes)
     *                       - 'mimeTypes' => array
     *                       - 'extensions' => array
     */
    public function secure_file(mixed $value, array $options): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (! $this->secure_file($val, $options)) {
                    return false;
                }
            }

            return true;
        }

        if (! is_object($value) || ! method_exists($value, 'validate')) {
            return false;
        }

        return $value->validate($options);
    }
}
