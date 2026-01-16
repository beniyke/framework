<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestModelTablesMigration extends BaseMigration
{
    public function up(): void
    {
        if (!Schema::hasTable('test_rel_models')) {
            Schema::create('test_rel_models', function (SchemaBuilder $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('password')->nullable();
                $table->string('status')->nullable();
                $table->boolean('is_active')->default(true);
                $table->dateTimestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_models');
    }
}
