<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelBuilderUserTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_builder_user', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age')->nullable();
            $table->integer('votes')->default(0);
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_builder_user');
    }
}
