<?php

declare(strict_types=1);

namespace Testing\Fixtures\Migrations;

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreateRelationTestTables extends BaseMigration
{
    public function up(): void
    {
        Schema::create('test_rel_models', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('status')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('test_rel_countries', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_rel_users', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('country_id')->unsigned()->nullable();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('test_rel_profiles', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
        });

        Schema::create('test_rel_posts', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('test_rel_comments', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('post_id')->unsigned();
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('test_rel_roles', function (SchemaBuilder $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_rel_user_roles', function (SchemaBuilder $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('role_id')->unsigned();
            $table->timestamps();
        });

        Schema::create('test_rel_images', function (SchemaBuilder $table) {
            $table->id();
            $table->string('url');
            $table->bigInteger('imageable_id')->unsigned();
            $table->string('imageable_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_rel_images');
        Schema::dropIfExists('test_rel_user_roles');
        Schema::dropIfExists('test_rel_roles');
        Schema::dropIfExists('test_rel_comments');
        Schema::dropIfExists('test_rel_posts');
        Schema::dropIfExists('test_rel_profiles');
        Schema::dropIfExists('test_rel_users');
        Schema::dropIfExists('test_rel_countries');
        Schema::dropIfExists('test_rel_models');
    }
}
