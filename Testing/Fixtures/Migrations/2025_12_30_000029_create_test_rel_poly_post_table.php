<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelPolyPostTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_poly_post', function (SchemaBuilder $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_poly_post');
    }
}
