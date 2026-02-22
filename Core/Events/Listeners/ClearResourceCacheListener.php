<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class ClearResourceCacheListener implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Events\Listeners;

use Core\Events\KernelTerminateEvent;
use Helpers\File\Cache;

class ClearResourceCacheListener
{
    public function handle(KernelTerminateEvent $event): void
    {
        $request = $event->request;
        $response = $event->response;

        if (! $request->isStateChanging()) {
            return;
        }

        // Redirection often follows a successful state change in web apps
        $status = $response->getStatusCode();
        $isSuccessful = ($status >= 200 && $status < 400);

        if (! $isSuccessful) {
            return;
        }

        $entity = $request->getRouteContext('entity');

        if (! $entity) {
            return;
        }

        // Since Builder now auto-tags with the table name,
        // flushing the 'entity' tag will clear related queries.
        Cache::create('query')->flushTags([strtolower($entity)]);
    }
}
