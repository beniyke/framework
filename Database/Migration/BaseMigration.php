<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Abstract base class for migrations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Migration;

use Database\Schema\Schema;

abstract class BaseMigration
{
    abstract public function up(): void;

    abstract public function down(): void;

    protected function schema(): Schema
    {
        return new Schema();
    }
}
