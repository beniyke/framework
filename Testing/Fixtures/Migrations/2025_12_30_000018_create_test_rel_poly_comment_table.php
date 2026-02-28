<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelPolyCommentTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_poly_comment', function (SchemaBuilder $table) {
            $table->id();
            $table->text('body');
            $table->unsignedBigInteger('commentable_id');
            $table->string('commentable_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_poly_comment');
    }
}
