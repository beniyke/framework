<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Capsule class acts as a fluent, immutable-capable data container.
 * It supports schema validation, casting, computed properties, and event listeners
 * for managing structured data.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Throwable;

final class Capsule implements JsonSerializable
{
    private array $data = [];

    private array $original;

    private array $schema = [];

    private array $casters = [];

    private array $validators = [];

    private array $computed = [];

    private array $listeners = [];

    private bool $sealed = false;

    private bool $frozen = false;

    private function __construct(array $data = [])
    {
        $this->data = $data;
        $this->original = $this->data;
    }

    public static function make(array|object $data = []): self
    {
        return new self(is_array($data) ? $data : get_object_vars($data));
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function seal(array|object $data = []): self
    {
        return (new self(is_array($data) ? $data : get_object_vars($data)))->immutable();
    }

    public static function fromJson(string $json): self
    {
        return new self(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    public function fill(array|object $data): self
    {
        foreach ($this->normalize($data) as $k => $v) {
            $this->set((string) $k, $v);
        }

        return $this;
    }

    public function set(string $key, mixed $value): self
    {
        $this->preventNewKey($key);
        $this->assertMutable();
        $this->enforceSchema($key);
        $value = $this->cast($key, $value);
        $this->validate($key, $value);

        $old = $this->get($key);
        $this->setNested($this->data, $key, $value);

        $this->emit("set:{$key}", $value, $old);
        $this->emit('set', $key, $value, $old);

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->computed[$key])) {
            return ($this->computed[$key])($this);
        }

        return $this->getNested($this->data, $key, $default);
    }

    public function has(string $key): bool
    {
        return $this->getNested($this->data, $key, '__NOT_SET') !== '__NOT_SET';
    }

    public function forget(string $key): self
    {
        $this->assertMutable();
        $this->forgetNested($this->data, $key);
        $this->emit("forget:{$key}");

        return $this;
    }

    public function schema(array $rules): self
    {
        foreach ($rules as $k => $r) {
            $key = (string) $k;
            if (is_string($r)) {
                $this->casters[$key] = $r;
            } elseif (is_array($r)) {
                if (isset($r['type'])) {
                    $this->casters[$key] = $r['type'];
                }

                if (isset($r['validate'])) {
                    $this->validators[$key] = $r['validate'];
                }

                if (isset($r['default']) && ! $this->has($key)) {
                    $this->set($key, $r['default']);
                }
            }
        }
        $this->schema = array_keys($rules);

        return $this;
    }

    public function computed(string $key, Closure $fn): self
    {
        $this->computed[$key] = $fn;

        return $this;
    }

    public function with(array|object $data): self
    {
        $clone = clone $this;
        $clone->fill($data);

        return $clone;
    }

    public function without(string ...$keys): self
    {
        $clone = clone $this;
        foreach ($keys as $k) {
            $clone->forget($k);
        }

        return $clone;
    }

    public function immutable(): self
    {
        $clone = clone $this;
        $clone->sealed = true;

        return $clone;
    }

    public function freeze(): self
    {
        $clone = clone $this;
        $clone->frozen = true;

        return $clone->immutable();
    }

    public function cloneWith(array|object $data): self
    {
        return $this->with($data)->immutable();
    }

    public function when(bool $c, Closure $t, ?Closure $e = null): self
    {
        return $c ? $t($this) : ($e ? $e($this) : $this);
    }

    public function unless(bool $c, Closure $t, ?Closure $e = null): self
    {
        return ! $c ? $t($this) : ($e ? $e($this) : $this);
    }

    public function tap(Closure $c): self
    {
        $c($this);

        return $this;
    }

    public function pipe(Closure $c): mixed
    {
        return $c($this);
    }

    public function validateOnly(array $keys): void
    {
        foreach ($keys as $k) {
            if ($this->has($k) && isset($this->validators[$k])) {
                ($this->validators[$k])($this->get($k), $k, $this);
            }
        }
    }

    public function export(Closure $s): array
    {
        return $s($this->data);
    }

    public function cacheKey(): string
    {
        return hash('xxh3', serialize($this->data));
    }

    public function equals(self $o): bool
    {
        return $this->data === $o->data;
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->data !== [];
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function sum(?string $p = null, ?Closure $c = null): int|float
    {
        $vals = $p ? $this->pluck($p) : array_values($this->data);
        if ($c) {
            $total = 0.0;
            foreach ($vals as $v) {
                $total += $c($v);
            }

            return $total;
        }

        return array_sum($vals);
    }

    public function merge($d, bool $deep = true): self
    {
        $clone = clone $this;
        $norm = $this->normalize($d);
        $clone->fill($deep ? $this->deepMerge($clone->data, $norm) : array_merge($clone->data, $norm));

        return $clone;
    }

    public function only(array $keys): self
    {
        $out = [];
        foreach ($keys as $k) {
            if ($this->has($k)) {
                $out[$k] = $this->get($k);
            }
        }

        return self::make($out);
    }

    public function except(array $keys): self
    {
        $out = $this->data;
        foreach ($keys as $k) {
            unset($out[$k]);
        }

        return $this->with($out);
    }

    public function pluck(string $p): array
    {
        $res = $this->getNested($this->data, $p, []);

        return is_array($res) ? $res : [$res];
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(int $f = 0): string
    {
        return json_encode($this->data, $f | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    private function assertMutable(): void
    {
        if ($this->sealed) {
            throw new RuntimeException('Sealed');
        }
    }

    private function preventNewKey(string $k): void
    {
        if ($this->frozen && ! $this->has($k) && ! str_contains($k, '.')) {
            throw new RuntimeException('Frozen');
        }
    }

    private function enforceSchema(string $k): void
    {
        if ($this->schema && ! in_array($k, $this->schema, true) && ! str_contains($k, '.')) {
            throw new InvalidArgumentException("Schema: [{$k}] not allowed");
        }
    }

    private function validate(string $k, mixed $v): void
    {
        if (isset($this->validators[$k]) && ! ($this->validators[$k])($v, $k, $this)) {
            throw new InvalidArgumentException("Validation failed for [{$k}]");
        }
    }

    private function cast(string $k, mixed $v): mixed
    {
        return match ($this->casters[$k] ?? null) {
            'int' => (int) $v,
            'float' => (float) $v,
            'string' => (string) $v,
            'bool' => (bool) $v,
            'array' => (array) $v,
            'json' => is_string($v) ? json_decode($v, true) : $v,
            'date' => $v instanceof DateTimeInterface ? $v : DateTimeHelper::parse($v),
            default => class_exists($this->casters[$k] ?? '') ? new ($this->casters[$k])($v) : $v,
        };
    }

    private function normalize(array|object $i): array
    {
        return is_array($i) ? $i : ($i instanceof self ? $i->data : get_object_vars($i));
    }

    private function setNested(array &$a, string $k, mixed $v): void
    {
        $ks = explode('.', $k);
        $r = &$a;
        foreach ($ks as $sk) {
            $r[$sk] ??= [];
            $r = &$r[$sk];
        }
        $r = $v;
    }

    private function getNested(array $a, string $k, mixed $d = null): mixed
    {
        $ks = explode('.', $k);
        $v = $a;
        foreach ($ks as $sk) {
            if (! is_array($v) || ! array_key_exists($sk, $v)) {
                return $d;
            }

            $v = $v[$sk];
        }

        return $v;
    }

    private function forgetNested(array &$a, string $k): void
    {
        $ks = explode('.', $k);
        $last = array_pop($ks);
        $r = &$a;
        foreach ($ks as $sk) {
            if (! is_array($r) || ! array_key_exists($sk, $r)) {
                return;
            }

            $r = &$r[$sk];
        }
        if (is_array($r)) {
            unset($r[$last]);
        }
    }

    private function deepMerge(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            $array1[$key] = is_array($value) && isset($array1[$key]) && is_array($array1[$key])
                ? $this->deepMerge($array1[$key], $value)
                : $value;
        }

        return $array1;
    }

    private function emit(string $event, ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            try {
                $listener(...$args);
            } catch (Throwable $error) {
                error_log("Capsule listener error on event [{$event}]: " . $error->getMessage() . ' in ' . $error->getFile() . ' on line ' . $error->getLine());
            }
        }
    }
}
