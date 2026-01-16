<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * PSR-7 Stream implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http\Psr7;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class Stream implements StreamInterface
{
    private $stream;

    private bool $seekable = false;

    private bool $readable = false;

    private bool $writable = false;

    public function __construct(string $content = '')
    {
        $this->stream = fopen('php://temp', 'r+');
        if ($this->stream === false) {
            throw new RuntimeException('Failed to create a temporary stream.');
        }

        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = $this->isReadableStream($meta);
        $this->writable = $this->isWritableStream($meta);

        if ($content !== '') {
            $this->write($content);
            $this->rewind();
        }
    }

    private function isReadableStream(array $meta): bool
    {
        $mode = $meta['mode'];

        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    private function isWritableStream(array $meta): bool
    {
        $mode = $meta['mode'];

        return str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, 'c') || str_contains($mode, 'x') || str_contains($mode, '+');
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (RuntimeException $e) {
            return '';
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->detach();
    }

    public function detach(): ?object
    {
        $result = $this->stream;
        $this->stream = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;

        return is_resource($result) ? $result : null;
    }

    public function getSize(): ?int
    {
        if (! is_resource($this->stream)) {
            return null;
        }
        $stats = fstat($this->stream);

        return $stats ? $stats['size'] : null;
    }

    public function tell(): int
    {
        if (! is_resource($this->stream)) {
            throw new RuntimeException('Stream is detached.');
        }
        $position = ftell($this->stream);
        if ($position === false) {
            throw new RuntimeException('Could not get stream position.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return ! is_resource($this->stream) || feof($this->stream);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (! $this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }
        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException('Could not seek to stream position.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if (! $this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }
        $bytes = fwrite($this->stream, $string);
        if ($bytes === false) {
            throw new RuntimeException('Could not write to stream.');
        }

        return $bytes;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if (! $this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        $data = fread($this->stream, $length);
        if ($data === false) {
            throw new RuntimeException('Could not read from stream.');
        }

        return $data;
    }

    public function getContents(): string
    {
        if (! is_resource($this->stream)) {
            return '';
        }
        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new RuntimeException('Could not get stream contents.');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if (! is_resource($this->stream)) {
            return $key ? null : [];
        }
        $meta = stream_get_meta_data($this->stream);

        return $key ? ($meta[$key] ?? null) : $meta;
    }
}
