<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelQueueFailedJobTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_queue_failed_job', function (SchemaBuilder $table) {
            $table->id();
            $table->string('job_connection');
            $table->string('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_queue_failed_job');
    }
}
