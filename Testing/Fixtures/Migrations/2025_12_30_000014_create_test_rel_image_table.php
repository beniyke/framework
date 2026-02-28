<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelImageTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_image', function (SchemaBuilder $table) {
            $table->id();
            $table->string('url');
            $table->bigInteger('imageable_id')->unsigned();
            $table->string('imageable_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_image');
    }
}
