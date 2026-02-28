<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Database migration for create_test_index_table_migration.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestIndexTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_index_table', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->index('name', 'test_index_table_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_index_table');
    }
}
