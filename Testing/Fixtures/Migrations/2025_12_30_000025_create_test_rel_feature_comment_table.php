<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelFeatureCommentTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_feature_comment', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->text('content');
            $table->timestamps();
            $table->foreign('post_id')->references('id')->on('test_rel_feature_posts')->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_feature_comment');
    }
}
