<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ApiResponseTrait provides a convenient way to format API responses.
 * It includes methods to generate standardized responses for both successful
 * and failed API operations, with optional data inclusion.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format\Traits;

trait ApiResponseTrait
{
    public static function asSuccessfulApiResponse(string $message, mixed $data = null, ?array $metadata = null): array
    {
        $payload = [
            'status' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        return $payload;
    }

    public static function asFailedApiResponse(string|array $message, mixed $data = null): array
    {
        $payload = [
            'status' => false,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $payload;
    }
}
