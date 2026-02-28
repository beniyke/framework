<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateTestRelFeaturePostTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('test_rel_feature_post', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('title');
            $table->text('content');
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('test_rel_feature_users')->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_feature_posts');
    }
}
