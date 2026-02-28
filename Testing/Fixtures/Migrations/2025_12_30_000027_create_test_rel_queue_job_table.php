<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelQueueJobTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_queue_job', function (SchemaBuilder $table) {
            $table->id();
            $table->string('queue');
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
            $table->integer('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_queue_job');
    }
}
