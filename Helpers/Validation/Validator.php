<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class handles input data validation according to predefined rules.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Validation;

use Exception;
use Helpers\Array\ArrayCollection;
use Helpers\Array\Collections;
use Helpers\Data;
use Helpers\Validation\Email\EmailValidator;

class Validator
{
    use ValidationTrait;

    private array $rules = [];

    private array $errors = [];

    private array $parameters = [];

    private array $optional = [];

    private array $notempty = [];

    private array $file = [];

    private array $messages = [];

    private array $expected = [];

    private array $modify = [];

    private array $validated = [];

    private array $exclude = [];

    private array $include = [];

    private mixed $transform = false;

    public function reset(): self
    {
        $this->rules = [];
        $this->errors = [];
        $this->parameters = [];
        $this->optional = [];
        $this->notempty = [];
        $this->file = [];
        $this->messages = [];
        $this->expected = [];
        $this->modify = [];
        $this->validated = [];
        $this->exclude = [];
        $this->include = [];

        return $this;
    }

    public function expected(array $expected, array $include = []): self
    {
        $this->expected = $expected;
        $this->include = $include;

        return $this;
    }

    public function exclude(array $exclude): self
    {
        $this->exclude = $exclude;

        return $this;
    }

    public function modify(array $modify): self
    {
        $this->modify = $modify;

        return $this;
    }

    public function transform(callable $function): self
    {
        $this->transform = $function;

        return $this;
    }

    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function optional(array $optional): self
    {
        $this->optional[] = $optional;

        return $this;
    }

    public function file(array $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function notempty(array $notempty): self
    {
        $this->notempty = $notempty;

        return $this;
    }

    public function messages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Prepares the parameters array for a field, merging default attributes with specific rules.
     */
    private function _prepare_parameters(string $key, string $value, string $default = 'required'): array
    {
        $array = [$default => true, 'label' => $value];
        $attributes = [
            'required',
            'length',
            'limit',
            'type',
            'exist',
            'confirm',
            'maxlength',
            'minlength',
            'date',
            'less_than',
            'greater_than',
            'allowed_file_type',
            'allowed_file_size',
            'same',
            'not_same',
            'not_contain',
            'config',
            'unique',
            'is_valid',
            'contains_valid',
            'contains_any_valid',
            'regex',
            'custom',
            'secure_file',
            'strict',
            'allow_domains',
            'block_domains',
            'email_pattern',
        ];

        foreach ($attributes as $attribute) {
            if (isset($this->rules[$key][$attribute])) {
                $array[$attribute] = $this->rules[$key][$attribute];
            }
        }

        return $array;
    }

    /**
     * Initializes the parameters for all data fields that will be validated.
     */
    public function parameters(array $data, string $default = 'required'): self
    {
        $this->parameters = [];

        foreach ($data as $key => $value) {
            $this->parameters[$key] = $this->_prepare_parameters($key, $value, $default);
        }

        return $this;
    }

    /**
     * Maps the incoming data to the expected parameter keys, handling nested and multi-dimensional data.
     */
    private function _mapdata(array $data): array
    {
        $mapped = [];

        foreach ($this->parameters as $key => $value) {
            $mapped[$key] = $data[$key] ?? $this->_check_multidimension($key, $data);
        }

        return $mapped;
    }

    /**
     * Attempts to retrieve a value from a multi-dimensional array using Collections helper.
     */
    private function _check_multidimension(string $key, array $data): mixed
    {
        $get = Collections::make($data)->value($key);

        return ! empty($get) ? $get : null;
    }

    /**
     * Checks if all expected keys are present or if included keys compensate for missing ones.
     */
    private function _has_expected_data(array $data): bool
    {
        if (empty($this->expected)) {
            return true;
        }

        foreach ($this->expected as $key) {
            if (! ArrayCollection::has($data, $key) && ! in_array($key, $this->include, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets file parameters if configured in $this->file.
     */
    private function _apply_file_parameters(): void
    {
        if (! empty($this->file)) {
            foreach ($this->file as $fk => $fv) {
                $this->parameters[$fk] = $this->_prepare_parameters($fk, $fv, 'file');
            }
        }
    }

    /**
     * Expands wildcard keys (e.g., items.*.name) into specific keys based on input data.
     */
    private function _expand_wildcards(array $data): void
    {
        $expanded_parameters = [];
        $expanded_rules = [];
        $expanded_messages = [];

        foreach ($this->parameters as $key => $params) {
            if (str_contains($key, '*')) {
                $this->_expand_recursive($key, $params, $data, $expanded_parameters, $expanded_rules, $expanded_messages);
            } else {
                $expanded_parameters[$key] = $params;
                if (isset($this->rules[$key])) {
                    $expanded_rules[$key] = $this->rules[$key];
                }
                if (isset($this->messages[$key])) {
                    $expanded_messages[$key] = $this->messages[$key];
                }
            }
        }

        $this->parameters = $expanded_parameters;
        $this->rules = $expanded_rules;
        $this->messages = $expanded_messages;
    }

    /**
     * Recursively expands a wildcard pattern into specific array paths.
     */
    private function _expand_recursive(string $pattern, array $params, array $data, array &$param_acc, array &$rule_acc, array &$msg_acc): void
    {
        // Handle both "items.*.name" and "items.*"
        $parts = explode('.*.', $pattern, 2);
        $has_suffix = true;

        if (count($parts) < 2) {
            $parts = explode('.*', $pattern, 2);
            $has_suffix = false;
        }

        if (count($parts) < 2) {
            $param_acc[$pattern] = $params;

            return;
        }

        $parent_path = $parts[0];
        $remaining_pattern = ltrim($parts[1], '.');

        $parent_data = Collections::make($data)->value($parent_path);

        if (is_array($parent_data)) {
            foreach (array_keys($parent_data) as $index) {
                $current_path = "{$parent_path}.{$index}";
                $new_key = $remaining_pattern !== '' ? "{$current_path}.{$remaining_pattern}" : $current_path;

                if (str_contains($new_key, '*')) {
                    $this->_expand_recursive($new_key, $params, $data, $param_acc, $rule_acc, $msg_acc);
                } else {
                    $new_params = $params;
                    $new_params['label'] = "{$params['label']} #" . ($index + 1);
                    $param_acc[$new_key] = $new_params;

                    if (isset($this->rules[$pattern])) {
                        $rule_acc[$new_key] = $this->rules[$pattern];
                    }
                    if (isset($this->messages[$pattern])) {
                        $msg_acc[$new_key] = $this->messages[$pattern];
                    }
                }
            }
        }
    }

    /**
     * Applies conditional rules based on $this->optional configuration.
     */
    private function _apply_optional_rules(array $data): void
    {
        if (empty($this->optional)) {
            return;
        }

        $validate = fn (string $key, mixed $value): bool =>
        isset($data[$key]) && (
            is_array($value) ? in_array($data[$key], $value) : $data[$key] == $value
        );

        foreach ($this->optional as $optional_group) {
            foreach ($optional_group as $key => $value_configs) {
                $configs = isset($value_configs['value']) ? [$value_configs] : $value_configs;

                foreach ($configs as $config) {
                    if (isset($config['value']) && $validate($key, $config['value'])) {
                        $this->_set_conditional_rules($config, 'required');
                    }
                }
            }
        }
    }

    /**
     * Applies conditional rules based on $this->notempty configuration.
     */
    private function _apply_notempty_rules(array $data): void
    {
        if (empty($this->notempty)) {
            return;
        }

        $is_empty = function (string $key, array $config) use ($data): bool {
            $value = $data[$key] ?? null;

            if (isset($config['file'])) {
                if (empty($value)) {
                    return true;
                }
                if (is_array($value)) {
                    foreach ($value as $file) {
                        if (method_exists($file, 'isEmpty') && $file->isEmpty()) {
                            return true;
                        }
                    }

                    return false;
                }

                return method_exists($value, 'isEmpty') ? $value->isEmpty() : empty($value);
            }

            return empty($value);
        };

        foreach ($this->notempty as $key => $config) {
            if (array_key_exists($key, $data) && ! $is_empty($key, $config)) {
                $this->_set_conditional_rules($config, 'required');
            }
        }
    }

    /**
     * A unified method to set parameters and rules for conditional validation (optional/notempty).
     */
    private function _set_conditional_rules(array $config, string $default_rule): void
    {
        // Set regular parameters
        if (isset($config['parameters'])) {
            foreach ($config['parameters'] as $pk => $pv) {
                $this->parameters[$pk] = $this->_prepare_parameters($pk, $pv, $default_rule);
            }
        }

        // Set file parameters
        if (isset($config['file'])) {
            foreach ($config['file'] as $fk => $fv) {
                $this->parameters[$fk] = $this->_prepare_parameters($fk, $fv, 'file');
            }
        }

        // Set additional rules on existing parameters
        if (isset($config['rules'])) {
            foreach ($config['rules'] as $rk => $rules) {
                if (isset($this->parameters[$rk])) {
                    foreach ($rules as $k => $v) {
                        $this->parameters[$rk][$k] = $v;
                    }
                }
            }
        }
    }

    /**
     * Orchestrates preparation and validation loops.
     */
    public function validate(array $data, ?array $additional_data = null): self
    {
        // Data Preparation and Initialization
        $data = $additional_data ? Collections::make($data)->attach($additional_data, true)->get() : $data;
        $this->validated = $data;
        $this->errors = [];

        // Conditional Rule Application
        $this->_apply_file_parameters();

        // Support items.*.quantity
        $this->_expand_wildcards($data);

        if (! $this->_has_expected_data($data)) {
            $this->errors[] = ['Invalid data'];

            return $this;
        }
        $this->_apply_optional_rules($data);
        $this->_apply_notempty_rules($data);

        // Map data to parameter keys (including nested keys)
        $mapped_data = $this->_mapdata($data);

        // Run Validation Rules
        $this->_run_validation_loops($mapped_data);

        return $this;
    }

    /**
     * Iterates through mapped data and runs validation rules for each field.
     */
    private function _run_validation_loops(array $mapped_data): void
    {
        foreach ($mapped_data as $key => $value) {
            $error = [];

            if (! isset($this->parameters[$key])) {
                continue;
            }

            foreach ($this->parameters[$key] as $rule => $condition) {
                $new_error = $this->_check_single_rule($key, $value, $rule, $condition, $mapped_data);

                if ($new_error !== null) {
                    $error = array_merge($error, (array) $new_error);
                }
            }

            if (! empty($error)) {
                $this->errors[$key] = $error;
            }
        }
    }

    /**
     * Handles the logic for a single validation rule (the core of the switch statement).
     *
     * @return array|string|null The error message(s), or null if validation passes.
     */
    private function _check_single_rule(string $key, mixed $value, string $rule, mixed $condition, array $mapped_data): string|array|null
    {
        if ($rule !== 'required' && $rule !== 'file' && $this->is_empty($value)) {
            return null;
        }

        $label = $this->parameters[$key]['label'];
        $message = $this->messages[$key][$rule] ?? null;

        switch ($rule) {
            case 'required':
                if ($condition === true && $this->is_empty($value)) {
                    return $message ?? "The {$label} field is {$rule}.";
                }
                break;

            case 'file':
                if ($condition === true && ! $this->is_file($value)) {
                    return $message ?? "The {$label} field is required.";
                }
                break;

            case 'type':
                if ($condition == 'password') {
                    $password = (new Password())->label($label)->config($this->parameters[$key]['config'] ?? [])->check($value);

                    return $password->is_valid() ? null : $password->errors();
                }

                if (! $this->type($value, $condition)) {
                    $expression = ['email' => 'must be a valid email address', 'phone' => 'must be a valid phone number'];
                    $info = $expression[$condition] ?? 'must be a valid ' . $condition;

                    return $message ?? "{$label} field characters {$info}";
                }
                break;

            case 'date':
                if (! $this->is_date($value, $condition)) {
                    return $message ?? "{$label} must be a valid date.";
                }
                break;

            case 'regex':
                if (! $this->pass_regex($value, $condition)) {
                    return $message ?? 'Invalid ' . $label;
                }
                break;

            case 'is_valid':
                if (! $this->regex_validate($value, $condition, 'is')) {
                    return $message ?? $label . ' input is not valid.';
                }
                break;

            case 'contains_valid':
                if (! $this->regex_validate($value, $condition, 'has')) {
                    return $message ?? 'Invalid ' . $label;
                }
                break;

            case 'contains_any_valid':
                if (! $this->regex_validate($value, $condition, 'any')) {
                    return $message ?? 'Invalid ' . $label;
                }
                break;

            case 'length':
                if (! $this->length($value, $condition)) {
                    return $message ?? $label . ' must be ' . str_replace(',', ' to ', $condition) . ' characters long.';
                }
                break;

            case 'less_than':
                if (! $this->less_than($value, $mapped_data[$condition])) {
                    $other_label = $this->parameters[$condition]['label'] ?? 'other field';

                    return $message ?? $label . ' must be less than ' . $other_label;
                }
                break;

            case 'greater_than':
                if (! $this->greater_than($value, $mapped_data[$condition])) {
                    $other_label = $this->parameters[$condition]['label'] ?? 'other field';

                    return $message ?? $label . ' must be greater than ' . $other_label;
                }
                break;

            case 'maxlength':
                if (! $this->len($value, 'max', $condition)) {
                    return $message ?? $label . ' must be at most ' . $condition . ' characters long.';
                }
                break;

            case 'minlength':
                if (! $this->len($value, 'min', $condition)) {
                    return $message ?? $label . ' must be at least ' . $condition . ' characters long.';
                }
                break;

            case 'exist':
                if (! $this->exist($value, $condition)) {
                    return $message ?? 'The option selected in ' . $label . ' field does not exist.';
                }
                break;

            case 'unique':
                if ($this->is_available($value, $condition)) {
                    return $message ?? 'The ' . $label . ' provided already exist.';
                }
                break;

            case 'allowed_file_type':
                if (! $this->allowed_type($value, $condition)) {
                    $types = implode(', ', $condition);
                    $s = count($condition) > 1 ? 's' : '';
                    $are = count($condition) > 1 ? 'are' : 'is';

                    return $message ?? "Invalid {$label} uploaded. Only file type{$s}: {$types} {$are} allowed.";
                }
                break;

            case 'allowed_file_size':
                $condition = strtolower($condition);
                if (! $this->allowed_size($value, $condition)) {
                    return $message ?? "The {$label} uploaded exceeds the max file size of {$condition}";
                }
                break;

            case 'limit':
                [$min, $max] = array_pad(explode('|', $condition), 2, null);
                $min = (int) $min;
                $max = isset($max) ? (int) $max : null;
                if (! $this->limit($value, $min, $max)) {
                    $msg_parts = [];
                    if (isset($min)) {
                        $msg_parts[] = $this->messages[$key][$rule]['min'] ?? 'at least ' . $min;
                    }
                    if (isset($max)) {
                        $msg_parts[] = $this->messages[$key][$rule]['max'] ?? 'at most ' . $max;
                    }

                    return $message ?? "{$label} must be " . implode(' & ', $msg_parts);
                }
                break;

            case 'confirm':
                $confirm_label = $this->parameters[$condition]['label'] ?? 'confirmation';
                if (! $this->confirm($mapped_data, $condition)) {
                    return $message ?? "The {$confirm_label} field does not exist.";
                }
                break;

            case 'same':
                $other_label = $this->parameters[$condition]['label'] ?? 'other field';
                if (! $this->same($value, $condition, $mapped_data)) {
                    return $message ?? $label . ' does not match ' . $other_label;
                }
                break;

            case 'not_same':
                $other_label = $this->parameters[$condition]['label'] ?? 'other field';
                if ($this->same($value, $condition, $mapped_data)) {
                    return $message ?? $label . ' must not be same as ' . $other_label;
                }
                break;

            case 'not_contain':
                $other_label = $this->parameters[$condition]['label'] ?? 'other field';
                if ($this->contain($value, $condition, $mapped_data)) {
                    return $message ?? $label . ' characters must not contain characters from ' . $other_label;
                }
                break;

            case 'secure_file':
                if (! $this->secure_file($value, $condition)) {
                    $errorMsg = $value instanceof \Helpers\Http\FileHandler
                        ? $value->getValidationError()
                        : 'Invalid file upload';

                    return $message ?? "The {$label} {$errorMsg}";
                }
                break;

            case 'custom':
                if (! $this->pass_custom_validation($value, $condition)) {
                    return $message ?? 'Invalid ' . $label;
                }
                break;

            case 'strict':
                $checks = array_map('trim', explode(',', $condition));
                $emailValidator = new EmailValidator($value);

                if (! $emailValidator->isValid()) {
                    return $message ?? "Invalid email format for {$label}";
                }

                foreach ($checks as $check) {
                    switch (strtolower($check)) {
                        case 'disposable':
                            if ($emailValidator->isDisposable()) {
                                return $message ?? "Disposable email addresses are not allowed for {$label}";
                            }
                            break;
                        case 'role':
                            if ($emailValidator->isRoleAccount()) {
                                return $message ?? "Role-based email addresses are not allowed for {$label}";
                            }
                            break;
                        case 'mx':
                            if (! $emailValidator->hasMxRecord()) {
                                return $message ?? "Email domain does not have valid MX records for {$label}";
                            }
                            break;
                        case 'smtp':
                            if (! $emailValidator->hasValidMailbox()) {
                                return $message ?? "Email mailbox does not exist for {$label}";
                            }
                            break;
                    }
                }
                break;

            case 'allow_domains':
                $domains = array_map('trim', explode(',', $condition));
                $emailValidator = new EmailValidator($value);

                if (! $emailValidator->domainMatches($domains)) {
                    $domainList = implode(', ', $domains);

                    return $message ?? "{$label} must be from allowed domains: {$domainList}";
                }
                break;

            case 'block_domains':
                $domains = array_map('trim', explode(',', $condition));
                $emailValidator = new EmailValidator($value);

                if ($emailValidator->domainMatches($domains)) {
                    return $message ?? "{$label} cannot be from blocked domains";
                }
                break;

            case 'email_pattern':
                // Sanitize pattern to prevent ReDoS attacks
                $pattern = trim($condition);
                if (empty($pattern)) {
                    break;
                }

                try {
                    if (! @preg_match('/' . $pattern . '/', $value)) {
                        return $message ?? "{$label} does not match the required email pattern";
                    }
                } catch (Exception $e) {
                    // Invalid regex pattern - fail gracefully
                    return $message ?? "{$label} validation pattern error";
                }
                break;

            default:
                break;
        }

        return null;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function has_error(): bool
    {
        return count($this->errors) > 0;
    }

    public function validated(): ?Data
    {
        if ($this->transform) {
            $callback = $this->transform;
            $this->validated = $callback($this->validated);
        }

        if ($this->has_error()) {
            return null;
        }

        $expected = $this->expected;

        // If no expected fields are defined, default to all validated data keys
        if (empty($expected)) {
            $expected = array_keys($this->validated);
        }

        if (! empty($this->exclude)) {
            $expected = array_diff($expected, $this->exclude);
        }

        if (! empty($this->modify)) {
            $this->validated = Collections::make($this->validated)->replaceKeys($this->modify, false)->get();

            $expected = array_map(fn ($value) => $this->modify[$value] ?? $value, $expected);
        }

        if (! empty($this->include) && Collections::make($this->include)->contains(array_keys($this->validated))) {
            $expected = array_merge($expected, $this->include);
        }

        return Data::make($this->validated, $expected);
    }
}
