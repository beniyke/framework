<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for service provider registration and booting.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

interface ServiceProviderInterface
{
    public function register(): void;

    public function boot(): void;
}
