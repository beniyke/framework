<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateFeatureTestTables extends BaseMigration
{
    public function up(): void
    {
        Schema::create('test_rel_feature_users', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('test_rel_feature_posts', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('title');
            $table->text('content');
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('test_rel_feature_users')->onDelete('CASCADE');
        });

        Schema::create('test_rel_feature_comments', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->text('content');
            $table->timestamps();
            $table->foreign('post_id')->references('id')->on('test_rel_feature_posts')->onDelete('CASCADE');
        });

        Schema::create('test_rel_feature_products', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_feature_products');
        Schema::dropIfExists('test_rel_feature_comments');
        Schema::dropIfExists('test_rel_feature_posts');
        Schema::dropIfExists('test_rel_feature_users');
    }
}
