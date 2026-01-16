<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateQueueTestTables extends BaseMigration
{
    public function up(): void
    {
        Schema::create('test_rel_queue_jobs', function (SchemaBuilder $table) {
            $table->id();
            $table->string('queue');
            $table->text('payload');
            $table->integer('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('test_rel_queue_failed_jobs', function (SchemaBuilder $table) {
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
        Schema::dropIfExists('test_rel_queue_failed_jobs');
        Schema::dropIfExists('test_rel_queue_jobs');
    }
}
