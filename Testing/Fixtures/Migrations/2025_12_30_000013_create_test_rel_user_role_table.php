<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

// Added this import

class CreateTestRelUserRoleTable extends BaseMigration // Changed class name
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_user_role', function (SchemaBuilder $table) { // Changed table name and type hint
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('role_id')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_user_role');
    }
}
