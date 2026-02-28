<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelModelTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_model', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('status')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_model');
    }
}
