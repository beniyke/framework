<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DTO (Data Transfer Object) Helper Class.
 *
 * This class provides utility for creating and managing Data Transfer Objects (DTOs)
 * by automatically mapping array data to class properties using PHP Reflection.
 * It enforces data structure and provides basic validation by checking for missing
 * required properties and converting the DTO back to an array.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use stdClass;

class DTO
{
    private array $errors = [];

    public function __construct(array $data)
    {
        $class = new ReflectionClass($this);

        foreach ($class->getProperties() as $property) {
            $property_name = $property->getName();
            $property_type = $property->getType();

            if (! $property->isPublic()) {
                $property->setAccessible(true);
            }

            if (! isset($data[$property_name])) {
                $this->errors[] = "The required property '{$property_name}' is missing.";

                if ($property->isReadOnly()) {
                    $default_value = $this->getDefaultValueForType($property_type);
                    $data[$property_name] = $default_value;
                }
            }

            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $property->setValue($this, $data[$property_name] ?? $property->getDefaultValue() ?? null);
        }
    }

    public function toArray(): array
    {
        $result = [];
        $class = new ReflectionClass($this);

        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $property_name = $property->getName();

            if (! $property->isPublic()) {
                $property->setAccessible(true);
            }

            if ($property->isInitialized($this)) {
                $result[$property_name] = $property->getValue($this);
            }
        }

        return $result;
    }

    public function getData(): Data
    {
        return Data::make($this->toArray());
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function getDefaultValueForType(ReflectionType $type): mixed
    {
        if ($type->allowsNull()) {
            return null;
        }

        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        switch ($type->getName()) {
            case 'string':
                return '';
            case 'int':
            case 'float':
                return 0;
            case 'bool':
                return false;
            case 'array':
                return [];
            case 'object':
                return new stdClass();
            default:
                return null;
        }
    }
}
