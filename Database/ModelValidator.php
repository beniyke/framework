<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ModelValidator handles validation logic for model attributes.
 * It supports common validation rules such as required fields, uniqueness checks,
 * string length constraints, and email validation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database;

use UnitEnum;

class ModelValidator
{
    public function validate(array $attributes, array $rules, string $modelClass, string $primaryKey, mixed $ignoreIdValue): array
    {
        $errors = [];

        foreach ($rules as $attribute => $ruleString) {
            $value = $attributes[$attribute] ?? null;

            if ($value instanceof UnitEnum && method_exists($value, 'value')) {
                $value = $value->value;
            }

            $rules = explode('|', $ruleString);

            foreach ($rules as $rule) {
                if (isset($errors[$attribute])) {
                    continue;
                }

                $ruleParts = explode(':', $rule, 2);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;

                if ($ruleName === 'required' && (is_null($value) || $value === '' || (is_array($value) && empty($value)))) {
                    $errors[$attribute][] = "The {$attribute} is required.";

                    continue;
                }

                if (is_null($value) || $value === '') {
                    continue;
                }

                if ($ruleName === 'unique') {
                    [$table, $column, $ignoreId, $ignoreIdColumn] = array_pad(explode(',', $ruleValue), 4, null);

                    $query = $modelClass::query()->where($column ?? $attribute, '=', $value);

                    $ignoreId = $ignoreId ?? $ignoreIdValue;

                    if ($ignoreId) {
                        $ignoreColumn = $ignoreIdColumn ?? $primaryKey;
                        $query->where($ignoreColumn, '!=', $ignoreId);
                    }

                    if ($query->count() > 0) {
                        $errors[$attribute][] = "The {$attribute} is already taken.";
                    }
                }

                if ($ruleName === 'min' && $ruleValue !== null) {
                    $min = (int) $ruleValue;
                    $currentValue = is_numeric($value) ? (float) $value : strlen((string) $value);
                    if ($currentValue < $min) {
                        $errors[$attribute][] = "The {$attribute} must be at least {$min}.";
                    }
                }

                if ($ruleName === 'max' && $ruleValue !== null) {
                    $max = (int) $ruleValue;
                    $currentValue = is_numeric($value) ? (float) $value : strlen((string) $value);
                    if ($currentValue > $max) {
                        $errors[$attribute][] = "The {$attribute} may not be greater than {$max}.";
                    }
                }

                if ($ruleName === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$attribute][] = "The {$attribute} must be a valid email address.";
                }

                if ($ruleName === 'confirmed') {
                    $confirmedAttribute = $attribute . '_confirmation';
                    $confirmedValue = $attributes[$confirmedAttribute] ?? null;

                    if ($value !== $confirmedValue) {
                        $errors[$attribute][] = "The {$attribute} confirmation does not match.";
                    }
                }
            }
        }

        return $errors;
    }
}
