<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class AuditFake implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use App\Models\User;
use Audit\Models\AuditLog;
use Database\BaseModel;
use Mockery;
use PHPUnit\Framework\Assert as PHPUnit;

class AuditFake
{
    protected array $logs = [];

    public function log(string $event, array $data = [], ?BaseModel $model = null, ?User $user = null): AuditLog
    {
        $this->logs[] = [
            'event' => $event,
            'data' => $data,
            'model' => $model,
            'user' => $user,
        ];

        $mock = Mockery::mock(AuditLog::class);

        return $mock;
    }

    public function logModelEvent(
        BaseModel $model,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        ?User $user = null
    ): AuditLog {
        return $this->log($event, [
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ], $model, $user);
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Assert if an event was logged.
     */
    public function assertLogged(string $event, ?callable $callback = null): void
    {
        $loggedCount = count(array_filter($this->logs, function ($l) use ($event, $callback) {
            if ($l['event'] !== $event) {
                return false;
            }

            return $callback ? $callback($l['data'], $l['model'], $l['user']) : true;
        }));

        PHPUnit::assertTrue(
            $loggedCount > 0,
            "The expected [{$event}] event was not logged."
        );
    }

    /**
     * Assert if an event was not logged.
     */
    public function assertNotLogged(string $event, ?callable $callback = null): void
    {
        $loggedCount = count(array_filter($this->logs, function ($l) use ($event, $callback) {
            if ($l['event'] !== $event) {
                return false;
            }

            return $callback ? $callback($l['data'], $l['model'], $l['user']) : true;
        }));

        PHPUnit::assertEquals(
            0,
            $loggedCount,
            "The unexpected [{$event}] event was logged."
        );
    }

    /**
     * Assert that nothing was logged.
     */
    public function assertNothingLogged(): void
    {
        PHPUnit::assertEmpty($this->logs, 'Audit events were logged unexpectedly.');
    }
}
