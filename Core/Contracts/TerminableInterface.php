<?php

declare(strict_types=1);

namespace Core\Contracts;

/**
 * Anchor Framework
 *
 * TerminableInterface defines a contract for services that require cleanup
 * after a request has been handled or a job has been processed.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
interface TerminableInterface
{
    /**
     * Perform any final cleanup or state reset.
     *
     * @return void
     */
    public function terminate(): void;
}
