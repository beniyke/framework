<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelSoftDeleteUserTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_soft_delete_user', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_soft_delete_users');
    }
}
