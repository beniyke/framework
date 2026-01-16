<?php

/**
 * Anchor Framework
 *
 * Miscellaneous helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

declare(strict_types=1);

use Helpers\DateTimeHelper;
use Mail\Contracts\Mailable;
use Mail\Mailer;
use Notify\NotificationBuilder;
use Notify\Notifier;

if (! function_exists('datetime')) {
    function datetime(mixed $date = null): DateTimeHelper
    {
        if ($date !== null) {
            return DateTimeHelper::parse($date);
        }

        return resolve(DateTimeHelper::class);
    }
}

if (! function_exists('notify')) {
    function notify(string $channel): NotificationBuilder
    {
        return resolve(Notifier::class)->channel($channel);
    }
}

if (! function_exists('null_if_blank')) {
    function null_if_blank(mixed $value): mixed
    {
        return ($value === 0 || $value === 0.0 || ! empty($value)) ? $value : null;
    }
}

if (! function_exists('mailer')) {
    function mailer(Mailable $mail): mixed
    {
        return resolve(Mailer::class)
            ->send($mail);
    }
}

if (! function_exists('route_name')) {
    function route_name(string $name): string
    {
        $routes = config('route.names') ?? [];

        if (! isset($routes[$name])) {
            throw new Exception("Route name '{$name}' does not exist.");
        }

        return $routes[$name];
    }
}
