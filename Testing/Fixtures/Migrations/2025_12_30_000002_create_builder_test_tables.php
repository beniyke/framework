<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateBuilderTestTables extends BaseMigration
{
    public function up(): void
    {
        Schema::create('test_rel_builder_users', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age')->nullable();
            $table->integer('votes')->default(0);
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('test_rel_collection_users', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_collection_users');
        Schema::dropIfExists('test_rel_builder_users');
    }
}
