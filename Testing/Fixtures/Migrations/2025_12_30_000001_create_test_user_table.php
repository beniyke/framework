<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestUserTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_user', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_user');
    }
}
