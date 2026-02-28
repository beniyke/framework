<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelProfileTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_profile', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_profile');
    }
}
