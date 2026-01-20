<?php

declare(strict_types=1);
/**
 * Anchor Framework
 *
 * Migration for creating the queued_job table.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateQueuedJobTable extends BaseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queued_job', function (SchemaBuilder $table) {
            $table->id();
            $table->string('identifier');
            $table->longText('payload');
            $table->enum('status', ['pending', 'failed', 'success'])->default('pending');
            $table->integer('failed')->unsigned()->default(0);
            $table->datetime('schedule');
            $table->datetime('reserved_at')->nullable();
            $table->text('response')->nullable();
            $table->dateTimestamps();
            $table->index('identifier');
            $table->index(['status', 'schedule'], 'queued_job_status_schedule_index');
            $table->index('created_at', 'queued_job_created_at_index');
            $table->index(['status', 'failed'], 'queued_job_status_failed_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queued_job');
    }
}
