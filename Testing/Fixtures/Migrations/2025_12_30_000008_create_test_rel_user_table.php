<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelUserTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_user', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('country_id')->unsigned()->nullable();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_user');
    }
}
