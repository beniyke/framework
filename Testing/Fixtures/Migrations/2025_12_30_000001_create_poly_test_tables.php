<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreatePolyTestTables extends BaseMigration
{
    public function up(): void
    {
        Schema::create('test_rel_poly_posts', function (SchemaBuilder $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('test_rel_poly_users', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_rel_poly_images', function (SchemaBuilder $table) {
            $table->id();
            $table->string('url');
            $table->unsignedBigInteger('imageable_id');
            $table->string('imageable_type');
            $table->timestamps();
        });

        Schema::create('test_rel_poly_comments', function (SchemaBuilder $table) {
            $table->id();
            $table->text('body');
            $table->unsignedBigInteger('commentable_id');
            $table->string('commentable_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_poly_comments');
        Schema::dropIfExists('test_rel_poly_images');
        Schema::dropIfExists('test_rel_poly_users');
        Schema::dropIfExists('test_rel_poly_posts');
    }
}
