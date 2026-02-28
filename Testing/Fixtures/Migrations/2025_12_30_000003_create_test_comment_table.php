<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestCommentTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_comment', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->text('content');
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('test_post')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_comment');
    }
}
