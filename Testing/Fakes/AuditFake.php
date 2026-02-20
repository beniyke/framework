<?php

declare(strict_types=1);

namespace Testing\Fakes;

use App\Models\User;
use Audit\Models\AuditLog;
use Database\BaseModel;
use Mockery;

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

    public function assertLogged(string $event, ?callable $callback = null): void
    {
    }
}
