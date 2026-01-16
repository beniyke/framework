<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestIndexTableMigration extends BaseMigration
{
    public function up(): void
    {
        if (!Schema::hasTable('test_index_table')) {
            Schema::create('test_index_table', function (SchemaBuilder $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->index('name', 'test_index_table_name_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_index_table');
    }
}
